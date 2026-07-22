<?php

namespace Modules\Menu\Sidebar;

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
            "title" => "menu::menu.titles.menu",
            "route" => "admin.menu.index",
            "icon" => "fas fa-file nav-icon",
            "active_actions" => [
                "admin.menu.index",
                "admin.menu.create",
                "admin.menu.edit"
            ],
            // "create" => "admin.menu.create",
            "order" => 5000
        ];

        $this->_menu->addMenuItem($menuItems);
    }
}
