<?php

namespace UkrSolution\BarcodeScanner\features\frontend;

use UkrSolution\BarcodeScanner\API\classes\Checker;
use UkrSolution\BarcodeScanner\features\settings\Settings;

class Frontend
{
    private $core = null;
    public $statusFrontend = null;
    private $statusShortcodes = null;
    private $statusMyAccount = null;
    private $userPermissionKey = "barcode-scanner-permission";

    function __construct($core = null)
    {
        try {
            $this->core = $core;

            $settings = new Settings();
            $allowToUseOnFrontend = $settings->getField("general", "allowToUseOnFrontend", "on");
            $myAccountMenu = $settings->getField("general", "frontendIntegration", "");
            $allowFrontendShortcodes = $settings->getField("general", "allowFrontendShortcodes", "");

            if ($allowToUseOnFrontend === "" && $myAccountMenu === "on") {
                $allowToUseOnFrontend = "on";
            }

            $this->statusFrontend = $allowToUseOnFrontend === "on";
            $this->statusMyAccount = $myAccountMenu === "on";
            $this->statusShortcodes = $allowFrontendShortcodes === "on";
        } catch (\Throwable $th) {
        }
    }

    public function userMenuIntegration()
    {
        global $wp;

        try {
            if (!$this->statusFrontend) {
                return;
            }

            if (!$this->checkUserPermissions()) {
                return;
            }

            if ($this->statusMyAccount) {
                $currentUrl = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
                $myAccountUrl = str_replace(home_url(), '', get_permalink(get_option('woocommerce_myaccount_page_id')));

                if ($currentUrl && $myAccountUrl && strpos($currentUrl, $myAccountUrl)) {
                    add_filter('woocommerce_account_menu_items', array($this, 'woocommerce_account_menu_items'));
                }

                add_action('wp_enqueue_scripts', function () {
                    $action = isset($_GET["action"]) ? $_GET["action"] : "";
                    $postId = isset($_GET["post"]) ? $_GET["post"] : "";

                    if (Checker::getMediaLoader()) {
                        return;
                    }

                    if (is_admin() && $action == 'edit' && $postId) {
                        $post = get_post($postId);

                        if ($post) {
                            return;
                        }
                    }

                    wp_enqueue_media();
                });
            }

            if ($this->core !== null) {
                add_action('wp_enqueue_scripts', array($this->core, 'adminEnqueueScripts'));
            }
        } catch (\Throwable $th) {
        }
    }

    public function woocommerce_account_menu_items($menu_items)
    {
        $menu_items["barcode-scanner-frontend"] = __('Scan Barcodes', 'barcode-scanner');
        return $menu_items;
    }

    public function checkUserPermissions($userId = null)
    {
        try {
            if (!$userId) {
                $userId = \get_current_user_id();
            }

            $settings = new Settings();
            $rolePermissions = $settings->getUserRolePermissions($userId);

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

    public function shordcodesIntegration()
    {
        try {
            if (!$this->statusFrontend) {
                add_shortcode('barcode-scanner-popup', array($this, 'barcodeScannerPopupEmpty'));
                return;
            }

            if (!$this->statusShortcodes) {
                add_shortcode('barcode-scanner-popup', array($this, 'barcodeScannerPopupEmpty'));
                return;
            }

            if (!$this->checkUserPermissions()) {
                add_shortcode('barcode-scanner-popup', array($this, 'barcodeScannerPopupEmpty'));
                return;
            }

            add_shortcode('barcode-scanner-popup', array($this, 'barcodeScannerPopup'));
        } catch (\Throwable $th) {
        }
    }

    public function barcodeScannerPopupEmpty()
    {
        return "";
    }

    public function barcodeScannerPopup($args = array())
    {
        try {
            ob_start();

            $autoShow = isset($args["auto-show"]) ? $args["auto-show"] === "true" : false;
            $showLink = isset($args["show-link"]) ? trim($args["show-link"]) : __("Show Scanner", "us-barcode-scanner");

            if ($showLink === "false") {
                $showLink = "";
            }

            if ($this->core !== null) {
                $this->core->adminEnqueueScripts();

                if ($autoShow === true) {
                    wp_add_inline_script("barcode_scanner_loader", "window.BarcodeScannerAutoShow = true;");
                }
            }

            require_once USBS_PLUGIN_BASE_PATH . "src/features/frontend/link.php";

            return ob_get_clean();
        } catch (\Throwable $th) {
            throw $th;
        }
    }
}
