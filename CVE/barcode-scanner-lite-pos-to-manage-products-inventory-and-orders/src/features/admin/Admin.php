<?php

namespace UkrSolution\BarcodeScanner\features\admin;

class Admin
{
    private $frontend = null;

    function __construct($frontend)
    {
        $this->frontend = $frontend;

        try {
            add_action('init', function () {
                $this->adminBarMenu();
            });
        } catch (\Throwable $th) {
        }
    }

    public function adminBarMenu()
    {
        $access = false;

        try {
            $access = $this->frontend->statusFrontend && $this->frontend->checkUserPermissions();
        } catch (\Throwable $th) {
        }

        if (is_admin() || $access) {
            add_action('admin_bar_menu', function ($wp_admin_bar) {
                $icons = str_replace("src/", "", \plugin_dir_url(__FILE__)) . "../../assets/icons/";
                $icon = $icons . 'barcode-scanner-menu-logo.svg';
                $args = array(
                    'id' => 'barcode-scanner-admin-bar',
                    'title' => '<span class="ab-icon" aria-hidden="true"><img src="' . $icon . '" style="position: relative; vertical-align: top; opacity: 0.9; margin-right: 3px; width: 20px; height: 20px; display: inline-block;" /></span> <span class="ab-label">Barcode Scanner</span>',
                    'href' => '#barcode-scanner-admin-bar',
                    'meta' => array(
                        'class' => 'barcode-scanner-admin-bar',
                        'title' => 'Barcode Scanner (Alt+B / &#8997;+B)'
                    )
                );
                $wp_admin_bar->add_node($args);
            }, 1000);
        }
    }
}
