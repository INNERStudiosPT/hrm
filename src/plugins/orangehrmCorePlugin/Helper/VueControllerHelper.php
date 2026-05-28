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

namespace OrangeHRM\Core\Helper;

use OrangeHRM\Admin\Traits\Service\LocalizationServiceTrait;
use OrangeHRM\Admin\Traits\Service\UserServiceTrait;
use OrangeHRM\Authentication\Auth\User;
use OrangeHRM\Config\Config;
use OrangeHRM\Core\Authorization\Service\ScreenPermissionService;
use OrangeHRM\Core\Dto\AttributeBag;
use OrangeHRM\Core\Exception\ServiceException;
use OrangeHRM\Core\Traits\Auth\AuthUserTrait;
use OrangeHRM\Core\Traits\ModuleScreenHelperTrait;
use OrangeHRM\Core\Traits\Service\MenuServiceTrait;
use OrangeHRM\Core\Vue\Component;
use OrangeHRM\CorporateBranding\Traits\ThemeServiceTrait;
use OrangeHRM\Entity\MenuItem;
use OrangeHRM\Entity\Module;
use OrangeHRM\Framework\Http\Request;
use OrangeHRM\I18N\Traits\Service\I18NHelperTrait;

class VueControllerHelper
{
    use ModuleScreenHelperTrait;
    use UserServiceTrait;
    use MenuServiceTrait;
    use I18NHelperTrait;
    use ThemeServiceTrait;
    use LocalizationServiceTrait;
    use AuthUserTrait;

    public const COMPONENT_NAME = 'componentName';
    public const COMPONENT_PROPS = 'componentProps';
    public const PUBLIC_PATH = 'publicPath';
    public const BASE_URL = 'baseUrl';
    public const ASSETS_VERSION = 'assetsVersion';
    public const USER = 'user';
    public const SIDE_PANEL_MENU_ITEMS = 'sidePanelMenuItems';
    public const TOP_MENU_ITEMS = 'topMenuItems';
    public const CONTEXT_TITLE = 'contextTitle';
    public const CONTEXT_ICON = 'contextIcon';
    public const COPYRIGHT_YEAR = 'copyrightYear';
    public const PRODUCT_VERSION = 'productVersion';
    public const PRODUCT_NAME = 'productName';
    public const PERMISSIONS = 'permissions';
    public const BREADCRUMB = 'breadcrumb';
    public const DATE_FORMAT = 'dateFormat';
    public const CLIENT_LOGO_URL = 'clientLogoUrl';
    public const CLIENT_BANNER_URL = 'clientBannerUrl';
    public const THEME_VARIABLES = 'themeVariables';
    public const HELP_URL = 'helpUrl';
    public const SHOW_UPGRADE = 'showUpgrade';

    /**
     * @var Request|null
     */
    protected ?Request $request = null;
    /**
     * @var null|Component
     */
    protected ?Component $component = null;
    /**
     * @var AttributeBag
     */
    protected AttributeBag $context;

    /**
     * @var ScreenPermissionService|null
     */
    protected ?ScreenPermissionService $screenPermissionService = null;

    public function __construct()
    {
        $this->context = new AttributeBag();
    }

    /**
     * @return Request|null
     */
    public function getRequest(): ?Request
    {
        return $this->request;
    }

    /**
     * @param Request|null $request
     */
    public function setRequest(?Request $request): void
    {
        $this->request = $request;
    }

    /**
     * @return Component|null
     */
    public function getComponent(): ?Component
    {
        return $this->component;
    }

    /**
     * @param Component|null $component
     */
    public function setComponent(?Component $component): void
    {
        $this->component = $component;
    }

    /**
     * @return array
     */
    public function getContextParams(): array
    {
        list($sidePanelMenuItems, $topMenuItems) = $this->getMenuItems();
        list($contextTitle, $contextIcon) = $this->getContextItems();
        list($clientLogoUrl, $clientBannerUrl, $themeVariables) = $this->getThemeData();

        $this->context->add(
            [
                self::COMPONENT_NAME => $this->getComponent()->getName(),
                self::COMPONENT_PROPS => $this->getComponent()->getProps(),
                self::PUBLIC_PATH => $this->getRequest()->getBasePath(),
                self::BASE_URL => $this->getRequest()->getBaseUrl(),
                self::ASSETS_VERSION => $this->getAssetsVersion(),
                self::USER => $this->getUserObject(),
                self::SIDE_PANEL_MENU_ITEMS => $sidePanelMenuItems,
                self::TOP_MENU_ITEMS => $topMenuItems,
                self::CONTEXT_TITLE => $contextTitle,
                self::CONTEXT_ICON => $contextIcon,
                self::COPYRIGHT_YEAR => date('Y'),
                self::PRODUCT_VERSION => Config::PRODUCT_VERSION,
                self::PRODUCT_NAME => Config::PRODUCT_NAME,
                self::BREADCRUMB => $this->getBreadcrumb(),
                self::DATE_FORMAT => $this->getLocalizationService()->getCurrentDateFormat(),
                self::CLIENT_LOGO_URL => $clientLogoUrl,
                self::CLIENT_BANNER_URL => $clientBannerUrl,
                self::THEME_VARIABLES => $themeVariables,
                self::HELP_URL => $this->getHelpUrl(),
                self::SHOW_UPGRADE => $this->getAuthUser()->getUserRoleId() === 1
            ]
        );
        return $this->context->all();
    }

    /**
     * @return string
     */
    protected function getAssetsVersion(): string
    {
        return Config::get(Config::VUE_BUILD_TIMESTAMP);
    }

    /**
     * @return int|null
     */
    protected function getUserId(): ?int
    {
        return User::getInstance()->getUserId();
    }

    /**
     * @return array
     */
    protected function getUserObject(): array
    {
        if (is_null($this->getUserId())) {
            // No logged in user, may be in login page
            return [];
        }

        $user = $this->getUserService()->getSystemUser($this->getUserId());
        if (is_null($user->getEmployee())) {
            $profileImgUrl = sprintf(
                '%s/images/default-photo.png?%s',
                $this->getRequest()->getBasePath(),
                $this->getAssetsVersion()
            );
        } else {
            $profileImgUrl = sprintf(
                '%s/pim/viewPhoto/empNumber/%s',
                $this->getRequest()->getBaseUrl(),
                $user->getEmployee()->getEmpNumber()
            );
        }

        $firstName = $user->getEmployee() ?
            $user->getEmployee()->getFirstName() :
            $user->getUserRole()->getDisplayName();
        $lastName = $user->getEmployee() ? $user->getEmployee()->getLastName() : null;
        return [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'profImgSrc' => $profileImgUrl,
            'hasPassword' => !is_null($user->getUserPassword()),
        ];
    }

    /**
     * @return array[]
     */
    protected function getMenuItems(): array
    {
        try {
            $menuItems = $this->getMenuService()->getMenuItems($this->getRequest()->getBaseUrl());
            $menuItems = $this->applyInnerStudiosMenuLayout($menuItems);
            if (str_starts_with($this->getRequest()->getPathInfo(), '/recruitment')) {
                $menuItems[1][] = [
                    'id' => 900002,
                    'name' => 'Templates',
                    'url' => $this->getRequest()->getBaseUrl() . '/recruitment/innerstudiosTemplates',
                    'active' => $this->getRequest()->getPathInfo() === '/recruitment/innerstudiosTemplates',
                ];
            }
            return $menuItems;
        } catch (ServiceException $e) {
        }
        return [[], []];
    }

    /**
     * @param array<int, array<int, array<string, mixed>>> $menuItems
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function applyInnerStudiosMenuLayout(array $menuItems): array
    {
        $sidePanelItems = $menuItems[0] ?? [];
        $topMenuItems = $menuItems[1] ?? [];
        $maintenanceItem = null;
        $adminIndex = null;
        $dashboardItem = null;
        $pathInfo = $this->getRequest()->getPathInfo();

        foreach ($sidePanelItems as $index => $item) {
            $name = strtolower((string)($item['name'] ?? ''));
            $url = (string)($item['url'] ?? '');
            if ($name === 'my info' || str_contains($url, '/pim/viewMyDetails')) {
                unset($sidePanelItems[$index]);
                continue;
            }
            if ($name === 'maintenance' || str_contains($url, '/maintenance/')) {
                $maintenanceItem = $item;
                unset($sidePanelItems[$index]);
                continue;
            }
            if ($name === 'dashboard' || str_contains($url, '/dashboard/')) {
                $dashboardItem = $item;
                unset($sidePanelItems[$index]);
                continue;
            }
            if ($name === 'admin' || str_contains($url, '/admin/')) {
                $adminIndex = $index;
            }
        }

        $sidePanelItems = array_values($sidePanelItems);
        if (is_array($dashboardItem)) {
            array_unshift($sidePanelItems, $dashboardItem);
        }

        if (str_starts_with($pathInfo, '/maintenance')) {
            foreach ($sidePanelItems as $index => $item) {
                unset($sidePanelItems[$index]['active']);
                if (
                    strtolower((string)($item['name'] ?? '')) === 'admin' ||
                    str_contains((string)($item['url'] ?? ''), '/admin/')
                ) {
                    $sidePanelItems[$index]['active'] = true;
                }
            }
        } elseif ($adminIndex !== null) {
            $sidePanelItems = array_values($sidePanelItems);
        }

        if (is_array($maintenanceItem) && $this->shouldShowMaintenanceUnderAdmin($sidePanelItems, $pathInfo)) {
            $topMenuItems[] = [
                'id' => $maintenanceItem['id'] ?? 900003,
                'name' => $maintenanceItem['name'] ?? 'Maintenance',
                'url' => $maintenanceItem['url'] ?? $this->getRequest()->getBaseUrl() . '/maintenance/viewMaintenanceModule',
                'active' => str_starts_with($pathInfo, '/maintenance'),
                'children' => [],
            ];
        }

        return [$sidePanelItems, $topMenuItems];
    }

    /**
     * @param array<int, array<string, mixed>> $sidePanelItems
     */
    private function shouldShowMaintenanceUnderAdmin(array $sidePanelItems, string $pathInfo): bool
    {
        if (str_starts_with($pathInfo, '/maintenance')) {
            return true;
        }

        foreach ($sidePanelItems as $item) {
            if (
                (
                    strtolower((string)($item['name'] ?? '')) === 'admin' ||
                    str_contains((string)($item['url'] ?? ''), '/admin/')
                ) &&
                !empty($item['active'])
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array|null[]
     */
    protected function getContextItems(): array
    {
        $currentScreen = $this->getScreenPermissionService()->getCurrentScreen();
        if ($currentScreen) {
            // TODO:: nav bar icon per screen
            return [$currentScreen->getName(), null];
        }
        return [null, null];
    }

    /**
     * @return ScreenPermissionService
     */
    public function getScreenPermissionService(): ScreenPermissionService
    {
        if (!$this->screenPermissionService instanceof ScreenPermissionService) {
            $this->screenPermissionService = new ScreenPermissionService();
        }
        return $this->screenPermissionService;
    }

    /**
     * @return array
     */
    public function getBreadcrumb(): array
    {
        $breadcrumb = [];
        $module = $this->getMenuService()
            ->getMenuDao()
            ->getModuleByName($this->getCurrentModuleAndScreen()->getModule());
        if ($module instanceof Module) {
            $breadcrumb['moduleName'] = $this->getI18NHelper()->transBySource($module->getDisplayName());
        }

        $menuItem = $this->getMenuService()->getMenuDao()->getMenuItemByModuleAndScreen(
            $this->getCurrentModuleAndScreen()->getModule(),
            $this->getCurrentModuleAndScreen()->getScreen()
        );
        if ($menuItem instanceof MenuItem) {
            $breadcrumb['level'] = $menuItem->getLevel() == 3 ? $menuItem->getParent()->getMenuTitle() : null;
            if (!is_null($breadcrumb['level'])) {
                $breadcrumb['level'] = $this->getI18NHelper()->transBySource($breadcrumb['level']);
            }
        }
        return $breadcrumb;
    }

    /**
     * @return string[]
     */
    private function getThemeData(): array
    {
        $clientLogoUrl = $this->getThemeService()->getClientLogoURL($this->getRequest());
        $clientBannerUrl = $this->getThemeService()->getClientBannerURL($this->getRequest());
        $themeVariables = $this->getThemeService()->getCurrentThemeVariables();

        return [$clientLogoUrl, $clientBannerUrl, $themeVariables];
    }

    /**
     * @return string
     */
    private function getHelpUrl(): string
    {
        return $this->getRequest()->getBaseUrl()
            . '/help/help?label='
            . $this->getCurrentModuleAndScreen()->getModule() . '_'
            . $this->getCurrentModuleAndScreen()->getScreen();
    }
}
