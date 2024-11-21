<?php

namespace UkrSolution\BarcodeScanner;

use UkrSolution\BarcodeScanner\API\actions\ManagementActions;
use UkrSolution\BarcodeScanner\API\actions\UsersActions;
use UkrSolution\BarcodeScanner\API\AjaxRoutes;
use UkrSolution\BarcodeScanner\API\classes\Auth;
use UkrSolution\BarcodeScanner\API\classes\Checker;
use UkrSolution\BarcodeScanner\API\classes\Integrations;
use UkrSolution\BarcodeScanner\API\classes\PostsList;
use UkrSolution\BarcodeScanner\API\classes\Results;
use UkrSolution\BarcodeScanner\API\classes\ResultsHelper;
use UkrSolution\BarcodeScanner\API\classes\Roles;
use UkrSolution\BarcodeScanner\API\classes\SearchFilter;
use UkrSolution\BarcodeScanner\API\classes\Users;
use UkrSolution\BarcodeScanner\API\classes\WPML;
use UkrSolution\BarcodeScanner\API\PluginsHelper;
use UkrSolution\BarcodeScanner\API\Routes;
use UkrSolution\BarcodeScanner\features\admin\Admin;
use UkrSolution\BarcodeScanner\features\cart\Cart;
use UkrSolution\BarcodeScanner\features\debug\Debug;
use UkrSolution\BarcodeScanner\features\export\Export;
use UkrSolution\BarcodeScanner\features\frontend\Frontend;
use UkrSolution\BarcodeScanner\features\frontend\FrontendRouter;
use UkrSolution\BarcodeScanner\features\history\History;
use UkrSolution\BarcodeScanner\features\import\Import;
use UkrSolution\BarcodeScanner\features\indexedData\IndexedData;
use UkrSolution\BarcodeScanner\features\interfaceData\InterfaceData;
use UkrSolution\BarcodeScanner\features\locations\Locations;
use UkrSolution\BarcodeScanner\features\locations\LocationsData;
use UkrSolution\BarcodeScanner\features\logs\Logs;
use UkrSolution\BarcodeScanner\features\mobile\MobileRouter;
use UkrSolution\BarcodeScanner\features\orders\Orders;
use UkrSolution\BarcodeScanner\features\products\Products;
use UkrSolution\BarcodeScanner\features\settings\PermissionsHelper;
use UkrSolution\BarcodeScanner\features\settings\Settings;
use UkrSolution\BarcodeScanner\features\settings\SettingsHelper;
use UkrSolution\BarcodeScanner\features\sounds\Sounds;
use UkrSolution\BarcodeScanner\features\updater\Updater;
use WP_REST_Request;

class Core
{
    protected $updater;
    private $appLinkProtocol = "scan-app://";

        public function __construct() 
    {        
        Debug::init();

        $this->updater = new Updater();
        Debug::addPoint("- Updater");

        $integrations = new Integrations($this);
        Debug::addPoint("- integrations inicializiated");

        add_action('rest_api_init', function () {
            try {
                if(class_exists("Routes")) {
                    (new Routes())->registerRoutes();
                }
            } catch (\Throwable $th) {
            }
        });

        add_action('wp_enqueue_media', function () {
            Checker::setMediaLoader();
        });

        $priority = 10;

        try {
           $post = json_decode(file_get_contents("php://input"), true);

            if ($post && isset($post['rout']) && in_array($post['rout'], array("changeStatus", "orderCreate"))) {
                $priority = 1000;
            }
        } catch (\Throwable $th) {
        }

        if(isset($_GET["action"]) && $_GET["action"] == "barcodeScannerAction") {
            add_action('init', array($this, 'ajaxRequest'), $priority);
        } else if(isset($_POST["action"]) && $_POST["action"] == "barcodeScannerAction") {
            add_action('init', array($this, 'ajaxRequest'), $priority);
        }

        add_action('admin_menu', array($this, 'createMenu'), 9);
        add_action('admin_menu', array($this, 'adminEnqueueScripts'), 9);

        if(isset($_GET["action"]) && $_GET["action"] == "barcodeScannerConfigs") {
            add_action('init', array($this, 'handleConfigs'));
        }

        $cartDecimalQuantity = false;

        try {
            $settings = new Settings();

            $field = $settings->getSettings("cartDecimalQuantity");
            $value = $field === null ? "off" : $field->value;
            $cartDecimalQuantity = $value === "on";
        } catch (\Throwable $th) {
        }

        add_action('shutdown', function() {
            foreach (PluginsHelper::$postsForUpdate as $postId) {
                Database::updatePost($postId, array(), null, null, "updated_post_meta_admin");
            }
        }, 999999);

        add_action('updated_post_meta', function($metaId, $postId, $metaKey, $metaValue) {
            if(\is_admin()) {
                if(!in_array($postId, PluginsHelper::$postsForUpdate)) {
                    PluginsHelper::$postsForUpdate[] = $postId;
                }
            } else if(true) {
                if(mt_rand(0, 100) === 100) {
                    Database::updatePost($postId, array(), null, null, "updated_post_meta");
                }
            }
        }, 1000, 4);

        add_action('woocommerce_save_product_variation', function($variationId){
            Database::updatePost($variationId, array(), null, null, "woocommerce_save_product_variation");
        }, 1000, 2);

        add_action('transition_post_status', function($newStatus, $oldStatus, $post){            
            if ($post->post_type !== "product") {
                return;
            }
            Database::updatePost($post->ID, array(), null, null, "transition_post_status");
        }, 9999, 3);

        add_action('wp_insert_post', function($orderId) {
            if(in_array(get_post_type($orderId), array("shop_order"))) {
                Database::updatePost($orderId, array(), null, null, "wp_insert_post");
            }
        });

                add_action('init', function () {
            try {
                if (isset($_GET['page']) && $_GET['page'] === "wc-orders") {
                    $ids = array();

                                        if (isset($_GET['bulk_action']) && $_GET['bulk_action'] === "trashed" && isset($_GET['ids'])) {
                        $ids = sanitize_text_field($_GET['ids']);
                        $ids = trim($ids);
                    }
                    if (isset($_GET['bulk_action']) && $_GET['bulk_action'] === "untrashed" && isset($_GET['ids'])) {
                        $ids = sanitize_text_field($_GET['ids']);
                        $ids = trim($ids);
                    }

                    if($ids) {
                        $ids = explode(",",$ids);

                        foreach ($ids as $id) {
                            Database::updatePost($id, array(), null, null, "bulk_action_trashed");
                        }
                    }
                }
            } catch (\Throwable $th) {
            }
        });

        add_filter('woocommerce_order_item_get_formatted_meta_data', function ($formatted_meta) {
            if($formatted_meta) {
                foreach ($formatted_meta as $key => $value) {
                    if(in_array($value->key, array("usbs_check_product","usbs_check_product_scanned"))) {
                    unset($formatted_meta[$key]);
                    }
                }
            }
            return $formatted_meta;
        }, 10, 1 );

        if($cartDecimalQuantity && \is_admin()) {
            add_filter('woocommerce_quantity_input_min', function ($val) { 
                return 0.1;
            });

            add_filter('woocommerce_quantity_input_step', function ($val) {
                return 0.1;
            });

            add_filter('woocommerce_order_item_get_quantity', function ($quantity, $item) {
                if($item->get_type() == "line_item") {
                    $metaQty = \wc_get_order_item_meta($item->get_id(), "_qty");
                    if($metaQty && $quantity != $metaQty) {
                        $quantity = (float)$metaQty;
                    }
                }

                    return $quantity;
            }, 10, 3);
        }

        $isMobileRoute = (new MobileRouter())->init($this);
        if(isset($isMobileRoute["route"]) && $isMobileRoute["route"] && isset($isMobileRoute["params"]) && $isMobileRoute["params"]) {
            return;
        }

        $isFrontendRoute = (new FrontendRouter())->init($this); 
        if(isset($isFrontendRoute["route"]) && $isFrontendRoute["route"]) {
            return;
        }      

        $auth = new Auth();

        $frontend = new Frontend($this);
        $admin = new Admin($frontend);
        $products = new Products();
        $orders = new Orders();
        $locations = new Locations();
        $indexedData = new IndexedData();
        $roles = new Roles();
        $import = new Import();
        $export = new Export();

        add_action('wp_ajax_usbs_auth', array($auth, 'login'));
        add_action('wp_ajax_nopriv_usbs_auth', array($auth, 'login'));
        add_action('wp_ajax_usbs_auth_otp', array($auth, 'loginOtp'));
        add_action('wp_ajax_nopriv_usbs_auth_otp', array($auth, 'loginOtp'));
        add_action('wp_ajax_usbs_auth_link', array($auth, 'loginLink'));
        add_action('wp_ajax_nopriv_usbs_auth_link', array($auth, 'loginLink'));

        add_action('init', array($this, "parseAuthRequest"));




        add_filter('user_has_cap', function ($allCaps, $caps, $args) {
            if (isset($caps[0]) ) {
                switch ($caps[0]) {
                case 'pay_for_order':
                    $user_id = $args[1];
                    $order_id = isset($args[2]) ? $args[2] : null;

                    $user = get_userdata($user_id);

                    if ($order_id && $user && in_array('administrator', (array)$user->roles ) ) { 
                        $allcaps['pay_for_order'] = true;
                    }
                    else if($order_id) {
                        $settings = new Settings();
                        $permissions = $settings->getUserRolePermissions();

                            if (is_array($permissions) && isset($permissions['orders']) && $permissions['orders'] == 1) {
                            $allCaps['pay_for_order'] = true;
                        }
                    }

                    break;
                }
            }

            $allCaps['pay_for_order'] = true;

            return $allCaps;
        }, 10, 3);



        add_action('admin_enqueue_scripts', function () {
            $action = isset($_GET["action"]) ? $_GET["action"] : "";
            $postId = isset($_GET["post"]) ? $_GET["post"] : "";

            if(Checker::getMediaLoader()) {
            }else if ($action == 'edit' && $postId) {
            } else {
                wp_enqueue_media();
            }
        });

        add_action('init', function() use ($frontend) {
            $frontend->userMenuIntegration();
            $frontend->shordcodesIntegration();
        });
    }

    public function createMenu()
    {
        $icons = str_replace("src/", "", \plugin_dir_url(__FILE__)) . "assets/icons/";
        $icons = USBS_PLUGIN_BASE_URL . "assets/icons/";


        $suf = '';
        $mainRout = 'barcode-scanner';
        $icon = $icons . 'barcode-scanner-menu-logo.svg';

                add_menu_page(__('Barcode Scanner', 'barcode-scanner'), __('Barcode Scanner', 'barcode-scanner'), 'read', $mainRout, array($this, 'modalPage'), $icon);

        add_submenu_page($mainRout, __('Scan & Find item', 'barcode-scanner'), __('Scan & Find item', 'barcode-scanner'), 'read', $mainRout, array($this, 'modalPage'));


        add_submenu_page($mainRout, __('Settings', 'barcode-scanner'), __('Settings', 'barcode-scanner'), 'read', 'barcode-scanner-settings' . $suf, array($this, 'pageSettings'));
        add_submenu_page("", __('Settings', 'barcode-scanner'), __('Settings', 'barcode-scanner'), 'read', 'barcode-scanner-settings-update' . $suf, array($this, 'pageSettingsUpdate'));
        add_submenu_page("", __('Settings', 'barcode-scanner'), __('Settings', 'barcode-scanner'), 'read', 'barcode-scanner-settings-reset' . $suf, array($this, 'pageSettingsReset'));

        add_submenu_page($mainRout, __('Logs', 'barcode-scanner'), __('Logs', 'barcode-scanner'), 'read', 'barcode-scanner-logs' . $suf, array($this, 'pageLogs'));
        add_submenu_page("", __('Download log file', 'barcode-scanner'), __('Download log file', 'barcode-scanner'), 'read', 'barcode-scanner-logs-download' . $suf, array($this, 'pageLogsDownload'));

        add_submenu_page($mainRout, __('Indexed data', 'barcode-scanner'), __('Indexed data', 'barcode-scanner'), 'read', 'barcode-scanner-indexed-data', array($this, 'pageIndexedData'));

        add_submenu_page($mainRout, __('Support & Chat', 'barcode-scanner'), '<span class="barcode_scanner_support">' . __('Support & Chat', 'barcode-scanner') . '</span>', 'read', 'barcode-scanner-support', array($this, 'emptyPage'));

        add_submenu_page($mainRout, __('FAQ', 'barcode-scanner'), '<span class="barcode_scanner_faq">' . __('FAQ', 'barcode-scanner') . '</span>', 'read', 'barcode-scanner-faq', array($this, 'emptyPage'));

        add_submenu_page("", __('Barcode Scanner', 'barcode-scanner'), __('Barcode Scanner', 'barcode-scanner'), 'read', 'bs-mobile-home', array($this, 'mobilePageHome'));

        add_submenu_page("", __('Barcode Scanner', 'barcode-scanner'), __('Barcode Scanner', 'barcode-scanner'), 'read', 'bs-redirect', array($this, 'redirectPage'));
    }

    public function adminEnqueueScripts($isReturn = false, $webview = false, $urlData = array())
    {
        global $wp_version;

        Debug::addPoint("ajax_action::Core->adminEnqueueScripts()");

        $path = plugin_dir_url(__FILE__);
        $path = str_replace('src/', '', $path);

        if ($webview) {
            
  $appJsPath = plugin_dir_url(__FILE__)."../assets/js/bundle-business-1.6.4-1727186327459.js";

  $vendorJsPath = plugin_dir_url(__FILE__)."../assets/js/chunk-business-1.6.4-1727186327459.js";

  
        } else {
            wp_enqueue_script("barcode_scanner_loader", $path."assets/js/index-business-1.6.4-1727186327459.js", array("jquery"), 1727186327459, true);

    $appJsPath = $path."assets/js/bundle-business-1.6.4-1727186327459.js";

    $vendorJsPath = $path."assets/js/chunk-business-1.6.4-1727186327459.js";

        }

        wp_enqueue_style('barcode_scanner_main', USBS_PLUGIN_BASE_URL . '/assets/css/style.css', array(), '1.6.4');

        if(!$isReturn) {
            $usbs = array(
                'appJsPath' => $appJsPath,
                'ajaxUrl' => SettingsHelper::getAjaxUrl(),
                'jsonUrl' => get_rest_url(),
                'nonce' => wp_create_nonce('wp_rest'),
                'wc_nonce' => wp_create_nonce('wc_store_api'),
                'wp_version' => $wp_version,
                'wc_version' => defined("WC_VERSION") ? WC_VERSION : 0,
            );
            wp_localize_script("barcode_scanner_loader", "usbs", $usbs);
            return;
        }

        $userId = get_current_user_id();
        $request = null;

        if(!$userId && isset($_GET["token"])) {
            $request = new WP_REST_Request("", "");
            $request->set_param("token", $_GET["token"]);
        }
        $userId = $request ? Users::getUserId($request) : $userId;
        $userRole = Users::getUserRole($userId);

                Debug::addPoint("- user data collected");

        $platform = isset($_POST["platform"]) ? sanitize_key("platform") : "";

        if (!$platform && $urlData && isset($urlData['route'])) {
            $platform = $urlData['route'];
        }

        $userLocale = $userId ? get_user_meta($userId, 'locale', true) : "";
        if ($userLocale && in_array($platform, array("android", "ios"))) switch_to_locale($userLocale);

        $settings = new Settings();
        $sounds = new Sounds();
        $cart = new Cart();
        $interfaceData = new InterfaceData();
        $location = new Locations();
        $usersActions = new UsersActions();

        $wpml = null;

        if(WPML::status()) {
            $wpml = array(
                "translations" => WPML::getTranslations()
            );
        }

        $currency = "$";
        $currencyLabel = "USD";
        $priceThousandSeparator = "";
        $priceDecimalSeparator = ".";
        $priceDecimals = 2;

        if(function_exists('get_woocommerce_currency_symbol') && function_exists('get_woocommerce_currency')) {
            $currency = html_entity_decode(get_woocommerce_currency_symbol());
            $currencyLabel = get_woocommerce_currency();
        }

        if(function_exists('wc_get_price_decimal_separator')) {
            $priceDecimalSeparator = wc_get_price_decimal_separator();
        }

        if(function_exists('wc_get_price_thousand_separator')) {
            $priceThousandSeparator = wc_get_price_thousand_separator();
        }

        if(function_exists('wc_get_price_decimals')) {
            $priceDecimals = wc_get_price_decimals();
        }

                Debug::addPoint("- WC settings collected");

        $userSessions = $settings->getSettings("userSessions", false, $webview);
        if ($userSessions) {
            $userSessions = $userSessions->value;
        }



        $session = $settings->getSettings("session");
        $sessionStamp = $settings->getSettings("sessionStamp");
        $usbsInterfaceMobile = $interfaceData::getFields(true, "", false, $userRole);


        $countries = array();
        if(PluginsHelper::is_plugin_active('woocommerce/woocommerce.php')) {
            try {
                $countries = WC()->countries->countries;
            } catch (\Throwable $th) {
            }
        }

        $productsList = PostsList::getList($userId, true);

        if ($webview) {
            $pluginSettings = $settings->getField('', '', '', false, false, array('receipt-template'));
        } else {
            $pluginSettings = $settings->getField("", "", "", false, true);
        }

        $usbs = array(
            'appJsPath' => $appJsPath,
            'vendorJsPath' => $vendorJsPath,
            'websiteUrl' => get_bloginfo("url"),
            'adminUrl' => get_admin_url(),
            'pluginUrl' => USBS_PLUGIN_BASE_URL,
            'frontendLink' => get_home_url() . "/barcode-scanner-front",
            'jsonUrl' => get_rest_url(),
            'pluginVersion' => '1.6.4',
            'isWoocommerceActive' => PluginsHelper::is_plugin_active('woocommerce/woocommerce.php'),
            'isStockLocations' => PluginsHelper::is_plugin_active('stock-locations-for-woocommerce/stock-locations-for-woocommerce.php'),
            'currencySymbol' => $currency,
            'currencyLabel' => $currencyLabel,
            'priceDecimalSeparator' => $priceDecimalSeparator,
            'priceThousandSeparator' => $priceThousandSeparator,
            'priceDecimals' => $priceDecimals,
            'rest_root' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'ajaxUrl' => SettingsHelper::getAjaxUrl(),
            'ajaxUrlUS' => SettingsHelper::getRequestUrl(),
            'urlSettings' => admin_url('/admin.php?page=barcode-scanner-settings'),
            'urlSettingsLocations' => admin_url('/admin.php?page=barcode-scanner-settings&tab=locations-data'),
            'wc_nonce' => wp_create_nonce('wc_store_api'),
            'uid' => $userId ? $userId : get_current_user_id(),
            'userId' => $userId,
            'settings' => $pluginSettings,
            'searchFilter' => SearchFilter::get(),
            'wp_version' => $wp_version,
            'wc_version' => defined("WC_VERSION") ? WC_VERSION : 0,
            "phpVersion" => phpversion(),
            'wpml' => $wpml,
            'plugins' => PluginsHelper::checkExternalPlugins(),
            'tabsPermissions' => $settings->getUserRolePermissions($userId),
            'session' => $session ? $session->value : "",
            'sessionStamp' => $sessionStamp ? $sessionStamp->value : "",
            'customSearchFilters' => apply_filters("scanner_custom_search_filters", array()),
            "locations" => array(),
            "pp_locations" => array(),
            "prefix" => "",
            "mode" => 'WEl5I+xhJLxE9d0ZGEOn2g==',
            "userSessions" => $userSessions,
            "shippingMethods" => array(),
            "paymentMethods" => $cart->getPaymentMethods(),
            "wcPricesInclTax" => function_exists("wc_prices_include_tax") ? \wc_prices_include_tax() : "",
            "orderStatuses" => $settings->getOrderStatuses(),
            "countries" => $countries,
            "searchHistory"=> array(),
            "productsListCount" => $productsList ? count($productsList) : 0,
            "userLocale" => $userLocale,
            "platform" => $platform,
            'sounds' => $sounds->getList(),
            "ip" => $_SERVER && isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
            'utoken' => Users::getUToken($userId),
            'upload_max_filesize' => SettingsHelper::getUploadMaxFilesize(),
            'taxBasedOn' => get_option('woocommerce_tax_based_on', 'shipping'),
            'm_otp_status' => $usersActions->getUsersOtpStatus(),
            'm_otp_expired' => $usersActions->getUsersOtpExpired(),
        );

        $enableLocations = $settings->getSettings("enableLocations");
        if(($enableLocations && $enableLocations->value === "on") || !$enableLocations) {
            $usbs["locations"] = $location->get();
        }
        Debug::addPoint("- usbs collected");


        $customCss = $settings->getSettings("customCss");
        Debug::addPoint("- usbsCustomCss collected");


        $usbsHistory = History::getByUser($userId);
        Debug::addPoint("- usbsHistory collected");

        $usbsUserCF = InterfaceData::getUserFields();
        Debug::addPoint("- usbsUserCF collected");

        $usbsOrderCF = InterfaceData::getOrderFields();
        Debug::addPoint("- usbsOrderCF collected");

        $usbsWooShippmentProviders = InterfaceData::getWooShippmentProviders();
        Debug::addPoint("- usbsWooShippmentProviders collected");

        $usbsLangs = $this->getLangs();
        Debug::addPoint("- usbsLangs collected");

        $usbsInterface = apply_filters("scanner_product_fields_filter", $usbsInterfaceMobile);
        Debug::addPoint("- usbsInterface collected");

        $cartExtraData = ResultsHelper::getExtraData($userId ? $userId : get_current_user_id());
        Debug::addPoint("- cartExtraData collected");

                return array(
            'usbs' => $usbs,
            'usbsCustomCss' => array("css" => $customCss ? $customCss->value : ""),
            'usbsHistory' => $usbsHistory,
            'usbsUserCF' => $usbsUserCF,
            'usbsOrderCF' => $usbsOrderCF,
            'usbsWooShippmentProviders' => $usbsWooShippmentProviders,
            'usbsLangs' => $usbsLangs,
            'usbsInterface' => $usbsInterface,
            'usbsCategories' => array(), 
            "cartExtraData" => $cartExtraData,
        );
    }

    public function handleConfigs() {
        $response = $this->adminEnqueueScripts(true);

        $callback = isset($_GET['callback']) ? sanitize_text_field($_GET['callback']) : '';

        $response['performance'] = Debug::getResult(true);

        if ($callback) {
            header('Content-Type: application/javascript');

            echo $callback . '(' . json_encode($response) . ');';
        } else {
            wp_send_json($response);
        }

        wp_die();
    }

    private function getLangs() {
        $languages = require USBS_PLUGIN_BASE_PATH . "src/Languages.php";
        return $languages;
    }

    public function pageSettings () {
        PermissionsHelper::setUser(get_current_user_id());
        $error = PermissionsHelper::onePermRequired(['plugin_settings'], true);

        if(is_array($error) && isset($error['message'])) {
            $title = esc_html__("Barcode Scanner settings", "us-barcode-scanner");
            require_once USBS_PLUGIN_BASE_PATH . "src/features/settings/error.php";
            return;
        }

        $settings = new Settings();
        $wpml = WPML::status();
        $locations = new Locations();
        $interfaceData = new InterfaceData();
        $settingsHelper = new SettingsHelper();
        $managementActions = new ManagementActions();
        $cart = new Cart();

        $settings->restoreSettings();

        $customTabs = array();
        $customTabs = apply_filters("scanner_settings_tabs", array());

                wp_enqueue_script('jquery-ui-sortable');

        $deps = array('jquery');

                wp_enqueue_script('barcode_scanner_settings', USBS_PLUGIN_BASE_URL . '/src/features/settings/assets/js/index-business-1.6.4-1727186327459.js', $deps, null, true);
        wp_enqueue_style('barcode_scanner_settings', USBS_PLUGIN_BASE_URL . '/src/features/settings/assets/css/index.css');

        wp_enqueue_script('barcode_scanner_settings_chosen', USBS_PLUGIN_BASE_URL . '/src/features/settings/assets/js/chosen.jquery.min.js', $deps, null, true);
        wp_enqueue_style('barcode_scanner_settings_chosen', USBS_PLUGIN_BASE_URL . '/src/features/settings/assets/css/chosen.min.css');

        wp_enqueue_script('barcode_scanner_settings_nestable', USBS_PLUGIN_BASE_URL . '/src/features/settings/assets/js/jquery.nestable.js', $deps, null, true);
        wp_enqueue_style('barcode_scanner_settings_nestable', USBS_PLUGIN_BASE_URL . '/src/features/settings/assets/css/jquery.nestable.css');

        wp_enqueue_script('barcode_scanner_settings_codemirror', USBS_PLUGIN_BASE_URL . '/assets/js/codemirror/codemirror.js', $deps, null, true);
        wp_enqueue_script('barcode_scanner_settings_codemirror_xml', USBS_PLUGIN_BASE_URL . '/assets/js/codemirror/mode/xml/xml.js', array('barcode_scanner_settings_codemirror'), null, true);
        wp_enqueue_script('barcode_scanner_settings_codemirror_js', USBS_PLUGIN_BASE_URL . '/assets/js/codemirror/mode/javascript/javascript.js', array('barcode_scanner_settings_codemirror'), null, true);
        wp_enqueue_script('barcode_scanner_settings_codemirror_css', USBS_PLUGIN_BASE_URL . '/assets/js/codemirror/mode/css/css.js', array('barcode_scanner_settings_codemirror'), null, true);
        wp_enqueue_script('barcode_scanner_settings_codemirror_html', USBS_PLUGIN_BASE_URL . '/assets/js/codemirror/mode/htmlmixed/htmlmixed.js', array('barcode_scanner_settings_codemirror'), null, true);
        wp_enqueue_style('barcode_scanner_settings_codemirror', USBS_PLUGIN_BASE_URL . '/assets/js/codemirror/codemirror.css');

        require_once USBS_PLUGIN_BASE_PATH . "src/features/settings/index.php";
    }

    public function pageSettingsUpdate () {
        $nonce = isset($_POST["nonce"]) ? sanitize_text_field($_POST["nonce"]) : "";

        if($nonce && wp_verify_nonce($nonce, USBS_PLUGIN_BASE_NAME . "-settings")) {
            $settings = new Settings();
            $settings->formSubmitted();
        }

        $tab = isset($_POST["tab"]) ? "&tab=" . sanitize_text_field($_POST["tab"]) : "";
        $subTab = isset($_POST["sub"]) ? "&sub=" . sanitize_text_field($_POST["sub"]) : "";
        $role = isset($_POST["role"]) ? "&role=" . sanitize_text_field($_POST["role"]) : "";

        wp_redirect(admin_url('/admin.php?page=barcode-scanner-settings' . $tab . $subTab . $role));
        exit;
    }

    public function pageSettingsReset () {
        $tab = isset($_GET["tab"]) ? "&tab=" . sanitize_text_field($_GET["tab"]) : "";
        $settings = new Settings();
        $userId = get_current_user_id();

        $settings->resetOptionsSettings();

        update_user_meta($userId, "scanner_custom_order_total", "");

        Database::removeAllTables();

        Database::setupTables(null);

        wp_redirect(admin_url('/admin.php?page=barcode-scanner-settings' . $tab));
        exit;
    }

    public function pageLogs () {
        PermissionsHelper::setUser(get_current_user_id());
        $error = PermissionsHelper::onePermRequired(['plugin_logs'], true);

        if(is_array($error) && isset($error['message'])) {
            $title = esc_html__("Barcode Scanner logs", "us-barcode-scanner");
            require_once USBS_PLUGIN_BASE_PATH . "src/features/settings/error.php";
            return;
        }

        $logs = new Logs();

        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_script('barcode_scanner_logs', USBS_PLUGIN_BASE_URL . '/src/features/logs/assets/js/index-business-1.6.4-1727186327459.js', array('jquery'), null, true);
        wp_enqueue_style('barcode_scanner_logs', USBS_PLUGIN_BASE_URL . '/src/features/logs/assets/css/index.css');
        wp_register_style('jquery-ui', 'https://code.jquery.com/ui/1.12.1/themes/smoothness/jquery-ui.css');
        wp_enqueue_style('jquery-ui'); 

                require_once USBS_PLUGIN_BASE_PATH . "src/features/logs/index.php";
    }

    public function pageLogsDownload () {
        $logs = new Logs();
        $logs->downloadFile();
    }

    public function pageIndexedData () {
        PermissionsHelper::setUser(get_current_user_id());
        $error = PermissionsHelper::onePermRequired(['plugin_settings'], true);

                if(is_array($error) && isset($error['message'])) {
            $title = esc_html__("Barcode Scanner Indexed Data", "us-barcode-scanner");
            require_once USBS_PLUGIN_BASE_PATH . "src/features/settings/error.php";
            return;
        }

                $indexedData = new IndexedData();

        if(isset($_GET["index"]) && $_GET["index"]) {
            $pid = sanitize_text_field($_GET["index"]);
            Database::updatePost($pid, array(), null, null, "pageIndexedData");
        }

        if(isset($_GET["reCreateTable"]) && $_GET["reCreateTable"]) {
            Database::clearTableColumns();
            Database::initDataTableColumns();
            Database::removeTableProducts();
            Database::setupTableProducts(true, true);      
            update_option("usbs_reCreateTable_msg", true);      
            wp_redirect(admin_url('/admin.php?page=barcode-scanner-indexed-data'));
            exit;
        }

                if(isset($_GET["triggers"])) {
            $itc = isset($_GET["index_triggers_counting"]) ? sanitize_text_field($_GET["index_triggers_counting"]) : "";
            update_option("usbs_index_triggers_counting", $itc);
            update_option("usbs_iic_updated_post_meta_admin", 0);
            update_option("usbs_iic_updated_post_meta", 0);
            update_option("usbs_iic_woocommerce_save_product_variation", 0);
            update_option("usbs_iic_transition_post_status", 0);
            update_option("usbs_iic_wp_insert_post", 0);
            update_option("usbs_iic_pageIndexedData", 0);
            update_option("usbs_iic_updatePostsTable", 0);

            wp_redirect(admin_url('/admin.php?page=barcode-scanner-indexed-data'));
        }

        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_script('barcode_scanner_logs', USBS_PLUGIN_BASE_URL . '/src/features/indexedData/assets/js/index-business-1.6.4-1727186327459.js', array('jquery'), null, true);
        wp_enqueue_style('barcode_scanner_logs', USBS_PLUGIN_BASE_URL . '/src/features/indexedData/assets/css/index.css');
        wp_register_style('jquery-ui', 'https://code.jquery.com/ui/1.12.1/themes/smoothness/jquery-ui.css');
        wp_enqueue_style('jquery-ui'); 

               require_once USBS_PLUGIN_BASE_PATH . "src/features/indexedData/index.php";
    }

    public function mobilePageHome () {
        echo "<hr/>[mobilePageHome]<hr/>";
    }

    public function redirectPage () {
        $referer = \wp_get_referer();

        if($referer) {
            \wp_redirect($referer);
        } else {
            \wp_redirect(\get_admin_url());
        }
    }

    public function emptyPage () {}

    public function modalPage () {
        echo '<a href="admin.php?page=barcode-scanner" class="usbs-auto-start-modal"></a>';
    }

    public function ajaxRequest() {
        $post = json_decode(file_get_contents("php://input"), true);

        if(!$post) $post = $_POST;
        $get = array();

        foreach ($_GET as $key => $value) {
            $get[$key] = sanitize_text_field($value);
        }        

        new AjaxRoutes($post, $get, $this);
    }

    public function parseAuthRequest()
    {
        if (preg_match('/\/.*?usbs-mobile\?u=(.*?)?$/', $_SERVER["REQUEST_URI"], $m)) {
            $siteUrl = get_site_url();
            $token = "";

            if(count($m) === 2) {
                $token = trim($m[1]);
            }

            $users = get_users(array('meta_key' => 'scanner-app-token', 'meta_value' => $token));

            if($users && count($users) > 0 && strlen($token) >= 14 && strlen($token) <= 18) {
                $user = $users[0];
                $fullName = trim($user->first_name . " " . $user->last_name);

                $link = $this->appLinkProtocol . "login/?u=" . $siteUrl . "?" . $token;

                $logoUrl = esc_url(wp_get_attachment_url(get_theme_mod('custom_logo')));
                $blogName = get_bloginfo("name");

                require_once USBS_PLUGIN_BASE_PATH . "src/features/settings/views/page-app-login.php";
            }else{
                echo "Wrong request";
            }

            exit;

        }
    }
}
