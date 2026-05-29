<?php

/**
 * OrangeHRM is a comprehensive Human Resource Management (HRM) System that captures
 * all the essential functionalities required for any enterprise.
 * Copyright (C) 2006 OrangeHRM Inc., http://www.orangehrm.com
 *
 * OrangeHRM is free software: you can redistribute it and/or modify it under the terms of
 * the GNU General Public License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * OrangeHRM is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with OrangeHRM.
 * If not, see <https://www.gnu.org/licenses/>.
 */

namespace OrangeHRM\Authentication\Controller;

use OrangeHRM\Authentication\Auth\User as AuthUser;
use OrangeHRM\Authentication\Exception\AuthenticationException;
use OrangeHRM\Authentication\Service\AuthenticationService;
use OrangeHRM\Authentication\Service\InnerStudiosSsoService;
use OrangeHRM\Authentication\Traits\CsrfTokenManagerTrait;
use OrangeHRM\Config\Config;
use OrangeHRM\Core\Authorization\Service\HomePageService;
use OrangeHRM\Core\Controller\AbstractVueController;
use OrangeHRM\Core\Controller\PublicControllerInterface;
use OrangeHRM\Core\Traits\Auth\AuthUserTrait;
use OrangeHRM\Core\Traits\EventDispatcherTrait;
use OrangeHRM\Core\Vue\Component;
use OrangeHRM\Core\Vue\Prop;
use OrangeHRM\CorporateBranding\Traits\ThemeServiceTrait;
use OrangeHRM\Entity\OpenIdProvider;
use OrangeHRM\Entity\User;
use OrangeHRM\Framework\Http\RedirectResponse;
use OrangeHRM\Framework\Http\Request;
use OrangeHRM\Framework\Http\Response;
use OrangeHRM\OpenidAuthentication\Traits\Service\SocialMediaAuthenticationServiceTrait;
use Throwable;

class LoginController extends AbstractVueController implements PublicControllerInterface
{
    use AuthUserTrait;
    use EventDispatcherTrait;
    use ThemeServiceTrait;
    use CsrfTokenManagerTrait;
    use SocialMediaAuthenticationServiceTrait;

    /**
     * @var HomePageService|null
     */
    protected ?HomePageService $homePageService = null;
    protected ?AuthenticationService $authenticationService = null;
    protected ?InnerStudiosSsoService $innerStudiosSsoService = null;

    /**
     * @return HomePageService
     */
    public function getHomePageService(): HomePageService
    {
        if (!$this->homePageService instanceof HomePageService) {
            $this->homePageService = new HomePageService();
        }
        return $this->homePageService;
    }

    public function getAuthenticationService(): AuthenticationService
    {
        if (!$this->authenticationService instanceof AuthenticationService) {
            $this->authenticationService = new AuthenticationService();
        }
        return $this->authenticationService;
    }

    public function getInnerStudiosSsoService(): InnerStudiosSsoService
    {
        if (!$this->innerStudiosSsoService instanceof InnerStudiosSsoService) {
            $this->innerStudiosSsoService = new InnerStudiosSsoService();
        }
        return $this->innerStudiosSsoService;
    }

    /**
     * @inheritDoc
     */
    public function preRender(Request $request): void
    {
        $component = new Component('auth-login');
        if ($this->getAuthUser()->hasFlash(AuthUser::FLASH_LOGIN_ERROR)) {
            $error = $this->getAuthUser()->getFlash(AuthUser::FLASH_LOGIN_ERROR);
            $component->addProp(
                new Prop(
                    'error',
                    Prop::TYPE_OBJECT,
                    $error[0] ?? []
                )
            );
        }

        $component->addProp(
            new Prop(
                'token',
                Prop::TYPE_STRING,
                $this->getCsrfTokenManager()->getToken('login')->getValue()
            )
        );
        $component->addProp(
            new Prop('login-logo-src', Prop::TYPE_STRING, $request->getBasePath() . '/images/innerstudios-logo.png')
        );
        $component->addProp(
            new Prop('login-banner-src', Prop::TYPE_STRING, $this->getThemeService()->getLoginBannerURL($request))
        );
        $component->addProp(
            new Prop('show-social-media', Prop::TYPE_BOOLEAN, $this->getThemeService()->showSocialMediaImages())
        );
        $component->addProp(new Prop('is-demo-mode', Prop::TYPE_BOOLEAN, Config::PRODUCT_MODE === Config::MODE_DEMO));

        $providersArray = $this->getProvidersList();
        $providers = array_map(function ($provider) {
            return [
                'id' => $provider->getId(),
                'label' => $provider->getProviderName(),
                'url' => $provider->getProviderUrl(), //use only for select icon
            ];
        }, $providersArray);

        $component->addProp(new Prop('authenticators', Prop::TYPE_ARRAY, $providers));
        $this->setComponent($component);
        $this->setTemplate('no_header.html.twig');
    }

    /**
     * @inheritDoc
     */
    public function handle(Request $request)
    {
        if ($this->getAuthUser()->isAuthenticated()) {
            $homePagePath = $this->getHomePageService()->getHomePagePath();
            return $this->redirect($homePagePath);
        }

        $profile = $this->getInnerStudiosSsoService()->getProfile($request);
        if ($profile === null) {
            return new RedirectResponse($this->getInnerStudiosLoginUrl($request));
        }

        $user = $this->getInnerStudiosSsoService()->findMatchingUser($profile);
        if (!$user instanceof User) {
            return new Response(
                'Conta InnerStudios autenticada, mas sem utilizador correspondente no HRM. ' .
                'Cria um utilizador OrangeHRM com o mesmo username do auth.innerstudios.pt.',
                Response::HTTP_FORBIDDEN
            );
        }

        // Sync profile data to employee record
        try {
            $this->getInnerStudiosSsoService()->syncProfile($user, $profile);
        } catch (Throwable $e) {
            // Ignore minor sync errors so that login itself remains robust
        }

        try {
            $success = $this->getAuthenticationService()->setCredentialsForUser($user);
            $this->getAuthUser()->setIsAuthenticated($success);
        } catch (AuthenticationException $e) {
            return new Response($e->getMessage(), Response::HTTP_FORBIDDEN);
        } catch (Throwable $e) {
            return new Response('Unexpected error occurred', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $homePagePath = $this->getHomePageService()->getHomePagePath();
        return $this->redirect($homePagePath);
    }

    private function getInnerStudiosLoginUrl(Request $request): string
    {
        $baseUrl = 'https://hrm.innerstudios.pt' . $request->getBaseUrl();
        $returnUrl = $baseUrl . '/auth/login';
        return 'https://auth.innerstudios.pt/auth?service=hrm&redirect=' . rawurlencode($returnUrl);
    }

    /**
     * @return OpenIdProvider[]
     */
    public function getProvidersList(): array
    {
        return $this->getSocialMediaAuthenticationService()->getAuthProviderDao()->getAuthProvidersForLoginPage();
    }
}
