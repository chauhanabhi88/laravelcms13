<?php

namespace Modules\LaravelPWA\Sidebar;

use Modules\Core\Foundations\Menu;

class MenuSidebar
{
    /**
     * @var Modules\Core\Foundations\Menu
     */
    protected $_menu;
    
    /**
     * Initialize menu object
     */
    public function __construct()
    {
        $this->_menu = app(Menu::class);
        // call function to add item to menu collection
        $this->initMenu();
    }

    /**
     *  Add module entity links to menu item to backend sidebar
     */
    public function initMenu()
    {
        $menuItems = [
            "group" => "core::core.menu.core_modules",
            "title" => "laravelpwa::laravelpwa.titles.laravelpwa",
            "route" => "admin.laravelpwa.index",
            "icon" => "fas fa-file nav-icon",
            "active_actions" => [
                "admin.laravelpwa.index",
                "admin.laravelpwa.create",
                "admin.laravelpwa.edit"
            ],
            "order" => 20
        ];

        //$this->_menu->addMenuItem($menuItems);
    }
}
