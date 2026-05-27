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

namespace OrangeHRM\Pim\Controller\File;

use OrangeHRM\Authentication\Service\InnerStudiosSsoService;
use OrangeHRM\Config\Config;
use OrangeHRM\Core\Controller\AbstractFileController;
use OrangeHRM\Core\Traits\Auth\AuthUserTrait;
use OrangeHRM\Entity\EmpPicture;
use OrangeHRM\Framework\Http\BinaryFileResponse;
use OrangeHRM\Framework\Http\RedirectResponse;
use OrangeHRM\Framework\Http\Request;
use OrangeHRM\Framework\Http\Response;
use OrangeHRM\Pim\Service\EmployeePictureService;

class EmployeePictureController extends AbstractFileController
{
    use AuthUserTrait;

    /**
     * @var EmployeePictureService|null
     */
    protected ?EmployeePictureService $employeePictureService = null;
    protected ?InnerStudiosSsoService $innerStudiosSsoService = null;

    /**
     * @return EmployeePictureService
     */
    public function getEmployeePictureService(): EmployeePictureService
    {
        if (!$this->employeePictureService instanceof EmployeePictureService) {
            $this->employeePictureService = new EmployeePictureService();
        }
        return $this->employeePictureService;
    }

    public function getInnerStudiosSsoService(): InnerStudiosSsoService
    {
        if (!$this->innerStudiosSsoService instanceof InnerStudiosSsoService) {
            $this->innerStudiosSsoService = new InnerStudiosSsoService();
        }
        return $this->innerStudiosSsoService;
    }

    /**
     * @param Request $request
     * @return BinaryFileResponse|RedirectResponse|Response
     */
    public function handle(Request $request)
    {
        $empNumber = $request->attributes->get('empNumber');
        if (!is_null($empNumber)) {
            $ssoAvatarUrl = $this->getSsoAvatarUrl($request, (int)$empNumber);
            if ($ssoAvatarUrl !== null) {
                return new RedirectResponse($ssoAvatarUrl);
            }

            $response = $this->getResponse();
            $eTag = $this->getEmployeePictureService()->getEmpPictureETagByEmpNumber($empNumber);

            if (!is_null($eTag)) {
                $response->setEtag($eTag);
                if (!$response->isNotModified($request)) {
                    $empPicture = $this->getEmployeePictureService()->getEmpPictureByEmpNumber($empNumber);
                    if ($empPicture instanceof EmpPicture) {
                        $response->setContent($empPicture->getDecorator()->getPicture());
                        $this->setCommonHeaders($response, $empPicture->getFileType());
                    }
                }
                return $response;
            }
        }

        $publicPath = Config::get(Config::PUBLIC_DIR);
        $response = $this->getFileResponse(realpath($publicPath . '/images/default-photo.png'));
        $response->setAutoEtag();
        $this->setCommonHeaders($response, "image/png");
        $response->isNotModified($request);
        return $response;
    }

    private function getSsoAvatarUrl(Request $request, int $empNumber): ?string
    {
        if ($this->getAuthUser()->getEmpNumber() !== $empNumber) {
            return null;
        }

        $profile = $this->getInnerStudiosSsoService()->getProfile($request);
        if (!is_array($profile)) {
            return null;
        }

        return $this->getInnerStudiosSsoService()->getAvatarUrl($profile);
    }

    /**
     * @param BinaryFileResponse|Response $response
     * @param string $contentType
     */
    private function setCommonHeaders($response, string $contentType): void
    {
        $response->headers->set('Content-Type', $contentType);
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('must-revalidate', true);
        $response->headers->set('Pragma', 'Public');
    }
}
