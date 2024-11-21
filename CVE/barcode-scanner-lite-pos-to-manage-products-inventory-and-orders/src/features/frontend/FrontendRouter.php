<?php

namespace UkrSolution\BarcodeScanner\features\frontend;

use UkrSolution\BarcodeScanner\API\actions\UsersActions;
use UkrSolution\BarcodeScanner\API\classes\PostsList;
use UkrSolution\BarcodeScanner\API\classes\ResultsHelper;
use UkrSolution\BarcodeScanner\API\classes\SearchFilter;
use UkrSolution\BarcodeScanner\API\classes\WPML;
use UkrSolution\BarcodeScanner\API\PluginsHelper;
use UkrSolution\BarcodeScanner\features\cart\Cart;
use UkrSolution\BarcodeScanner\features\history\History;
use UkrSolution\BarcodeScanner\features\interfaceData\InterfaceData;
use UkrSolution\BarcodeScanner\features\locations\Locations;
use UkrSolution\BarcodeScanner\features\locations\LocationsData;
use UkrSolution\BarcodeScanner\features\settings\Settings;
use UkrSolution\BarcodeScanner\features\settings\SettingsHelper;
use UkrSolution\BarcodeScanner\features\sounds\Sounds;

class FrontendRouter
{
    private $userPermissionKey = "barcode-scanner-permission";
    private $core = null;

    public function init($core)
    {
        $this->core = $core;

        $urlData = $this->getParamsFromPlainUrl();

        add_filter('init', function () use ($urlData) {
            $this->frontendPagesByUrl($urlData);
        });

        return $urlData;
    }

    private function getParamsFromPlainUrl()
    {
        $result = array("route" => "", "params" => array());

        try {
            if (isset($_SERVER["REQUEST_URI"])) {
                $key = $_SERVER["REQUEST_URI"];

                if (!$key) return $result;

                if (preg_match("/^\/?(barcode-scanner-front)(.*?)?$/", $key, $m)) {
                    if (count($m) >= 2) {
                        return array(
                            "route" => $m[1],
                            "params" => $_GET,
                        );
                    }
                }
            }
        } catch (\Throwable $th) {
            return $result;
        }

        return $result;
    }

    public function frontendPagesByUrl($urlData)
    {
        if ($urlData["route"] != "barcode-scanner-front") {
            return null;
        }

        header("HTTP/1.1 200 OK");
        header("Expires: on, 01 Jan 1970 00:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");

        add_filter('show_admin_bar', '__return_false');

        include_once(ABSPATH . 'wp-admin/includes/plugin.php');

        if (!is_user_logged_in()) {
            auth_redirect();
        }

        $accessDenied = false;

        if (!$this->checkUserPermissions()) {
            $accessDenied = true;

                        if (function_exists("http_response_code")) {
                http_response_code(403);
            } else {
                header("HTTP/1.1 403 Access denied");
            }

            require_once __DIR__ . '/FrontendRouterAccessDenied.php';
            exit;
        }

        $path = plugin_dir_url(__FILE__);
        $path = str_replace('src/features/frontend/', '', $path);

        $settings = new Settings();
        $interfaceData = new InterfaceData();
        $jsData = $this->core->adminEnqueueScripts(true, false, $urlData);

        $printingScripts = array();
        $printingDOMData = array();
        $printingInitHTML = "";

        if (function_exists('ProductLabelPrintingApp_getScripts')) {
            $printingScripts = ProductLabelPrintingApp_getScripts();
        }

        if (function_exists('ProductLabelPrintingApp_getDOMData')) {
            $printingDOMData = ProductLabelPrintingApp_getDOMData();
        }

        if (function_exists('ProductLabelPrintingApp_getInitHTML')) {
            $printingInitHTML = ProductLabelPrintingApp_getInitHTML();
        }

        require_once __DIR__ . '/FrontendRouterIndex.php';

        exit;

    }

    private function getLangs()
    {
        $languages = require USBS_PLUGIN_BASE_PATH . "src/Languages.php";


        return $languages;

    }

    public function checkUserPermissions($userId = null)
    {
        try {
            if (!$userId) {
                $userId = \get_current_user_id();
            }

            $settings = new Settings();
            $rolePermissions = $settings->getUserRolePermissions($userId);
            $allowToUseOnFrontend = $settings->getField("general", "allowToUseOnFrontend", "on");

            if ($allowToUseOnFrontend != "on") {
                return false;
            }

            if ($rolePermissions && isset($rolePermissions["frontend"]) && $rolePermissions["frontend"]) {
                return true;
            }

            $permission = \get_user_meta($userId, $this->userPermissionKey, true);

            if ($permission && (int)$permission) {
                return true;
            } else {
                return false;
            }
        } catch (\Throwable $th) {
            return false;
        }
    }
}
