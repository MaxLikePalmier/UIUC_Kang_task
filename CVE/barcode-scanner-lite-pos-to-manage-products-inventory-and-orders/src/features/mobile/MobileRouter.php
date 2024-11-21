<?php

namespace UkrSolution\BarcodeScanner\features\mobile;

use UkrSolution\BarcodeScanner\API\actions\UsersActions;
use UkrSolution\BarcodeScanner\API\classes\Auth;
use UkrSolution\BarcodeScanner\API\classes\PostsList;
use UkrSolution\BarcodeScanner\API\classes\ResultsHelper;
use UkrSolution\BarcodeScanner\API\classes\Users;
use UkrSolution\BarcodeScanner\features\settings\Settings;
use UkrSolution\BarcodeScanner\features\sounds\Sounds;
use UkrSolution\BarcodeScanner\API\classes\WPML;
use UkrSolution\BarcodeScanner\features\cart\Cart;
use UkrSolution\BarcodeScanner\features\history\History;
use UkrSolution\BarcodeScanner\features\interfaceData\InterfaceData;
use UkrSolution\BarcodeScanner\features\locations\Locations;
use UkrSolution\BarcodeScanner\features\settings\SettingsHelper;
use WP_REST_Request;

class MobileRouter
{
    private $coreInstance = null;
    private $tn = "script";

    public function init($coreInstance)
    {
        $this->coreInstance = $coreInstance;
        $urlData = $this->getParamsFromPlainUrl();

        add_filter('init', function () use ($urlData) {
            $this->mobilePagesByUrl($urlData);
        });

        return $urlData;
    }

    public function getParamsFromPlainUrl()
    {
        $data = array("route" => "", "params" => "");

        try {
            if ($_GET && count($_GET) === 1 || isset($_SERVER["REQUEST_URI"])) {
                $key = $_SERVER["REQUEST_URI"];

                if (!$key) return null;

                if (preg_match("/^\/?mobile-barcode-scanner\/(plugin|android|ios)\/(checker|display|auth)\/?(.*?)?$/", $key, $m)) {
                    if (count($m) >= 3) {
                        $data = array(
                            "route" => str_replace("/", "", $m[1]),
                            "params" => str_replace("/", "", $m[2]),
                        );
                    }
                }
            }
        } catch (\Throwable $th) {
        }

        return $data;
    }

    public function mobilePagesByUrl($urlData)
    {
        if (!$urlData || !isset($urlData["route"]) || !isset($urlData["params"])) {
            return;
        }

        if ($urlData["route"] === "plugin" && $urlData["params"] === "checker") {
            $this->pluginChecker();
        }
        else if ($urlData["route"] === "plugin" && $urlData["params"] === "auth") {
            $this->userAuth();
        }
        else if (in_array($urlData["route"], array("android", "ios")) && $urlData["params"] === "display") {
            $this->loadWebviewData($urlData);
        }

    }

    private function pluginChecker()
    {
        header("Expires: on, 01 Jan 1970 00:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");

        require __DIR__ . '/checker.php';
        exit();
    }

    private function userAuth()
    {
        header("Expires: on, 01 Jan 1970 00:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");

        $auth = new Auth();
        $auth->loginOtp();

        exit();
    }

    private function loadWebviewData($urlData)
    {
        header("Expires: on, 01 Jan 1970 00:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");

        include_once(ABSPATH . 'wp-admin/includes/plugin.php');

        $jsData = $this->coreInstance->adminEnqueueScripts(true, true, $urlData);
        $settings = new Settings();

        $customCssMobile = $settings->getSettings("customCssMobile");
        $customCssMobile = $customCssMobile ? $customCssMobile->value : "";

        echo "\n<";
        esc_html_e($this->tn, 'us-barcode-scanner');
        echo " src='" . esc_url(home_url()) . "/wp-includes/js/jquery/jquery.js' ";
        echo ">";
        echo "<";
        echo "/";
        esc_html_e($this->tn, 'us-barcode-scanner');
        echo ">";

        echo "<";
        esc_html_e($this->tn, 'us-barcode-scanner');
        echo " src='" . esc_url(home_url()) . "/wp-includes/js/jquery/jquery-migrate.min.js' ";
        echo ">";
        echo "<";
        echo "/";
        esc_html_e($this->tn, 'us-barcode-scanner');
        echo ">";

        echo "<";
        esc_html_e($this->tn, 'us-barcode-scanner');
        echo " src='" . esc_url(USBS_PLUGIN_BASE_URL) . "src/features/mobile/assets/js/index.js?v=1.6.4&t=1727186327459' ";
        echo ">";
        echo "<";
        echo "/";
        esc_html_e($this->tn, 'us-barcode-scanner');
        echo ">";

        echo "<";
        esc_html_e($this->tn, 'us-barcode-scanner');
        echo " src='" . esc_url(USBS_PLUGIN_BASE_URL) . "src/features/mobile/assets/js/loader.js?v=1.6.4&t=1727186327459' ";
        echo ">";
        echo "<";
        echo "/";
        esc_html_e($this->tn, 'us-barcode-scanner');
        echo ">";

        require __DIR__ . '/index.php';
        exit();
    }
}
