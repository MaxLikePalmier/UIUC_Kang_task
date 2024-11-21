<?php

namespace UkrSolution\BarcodeScanner\features\settings;

use UkrSolution\BarcodeScanner\API\actions\ManagementActions;
use UkrSolution\BarcodeScanner\API\classes\SearchFilter;
use UkrSolution\BarcodeScanner\API\classes\WPML;
use UkrSolution\BarcodeScanner\Database;
use UkrSolution\BarcodeScanner\features\cart\Cart;
use UkrSolution\BarcodeScanner\features\interfaceData\InterfaceData;
use UkrSolution\BarcodeScanner\features\locations\Locations;
use UkrSolution\BarcodeScanner\features\locations\LocationsData;
use UkrSolution\BarcodeScanner\features\mobile\MobileRouter;
use WP_REST_Request;

class Settings
{
    private $coreInstance = null;
    private $post = array();
    private $frontendPermissions = array();
    private $dbOptionSettingsKey = "barcode-scanner-settings-options";
    private $dbOptionRolesPermissionsKey = "barcode-scanner-roles-permissions";
    private $rolesPermissions = array();
    private $userPermissionKey = "barcode-scanner-permission";
    private $dbOptionPluginsKey = "barcode-scanner-plugins";
    private $plugins = array();
    public $userAppPermissionKey = "scanner-app-token";
    public $activeTab = "";

    public function __construct($coreInstance = null)
    {
        $this->coreInstance = $coreInstance;
    }

    public function formSubmitted()
    {
        $this->formListener();
    }

    public function restoreSettings()
    {
        if (isset($_GET["usbsRestoreTpl"])) {
            SettingsHelper::restoreReceiptTemplate();
            \wp_redirect(admin_url("admin.php?page=barcode-scanner-settings&tab=receipt-printing"));
            exit;
        }
    }

    public function updateSettings($key, $value, $type = "json")
    {
        global $wpdb;

        $table = $wpdb->prefix . Database::$settings;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT S.id FROM {$table} AS S WHERE S.field_name = '%s' LIMIT 1;", $key)
        );

        if ($row) {
            $wpdb->update($table, array("value" => $value, "type" => $type), array("id" => $row->id));
        } else {
            $wpdb->insert($table, array("field_name" => $key, "value" => $value, "type" => $type), array('%s', '%s', '%s'));
        }

        $row = null;
    }

    public function getSettings($key = "", $asArray = false, $reSelectData = false)
    {
        return SettingsHelper::getSettingsField($this, $key, $asArray, $reSelectData);
    }

    public function formListener()
    {
        try {
            if (isset($_POST) && !empty($_POST)) {
                $keys = array(
                    'tab',
                    'key',
                    'defaultOrderStatus',
                    'defaultShippingMethod',
                    'fieldForNewProduct',
                    'cfForNewProduct',
                    'wpmlUpdateProductsTree',
                    'orderCreateEmail',
                    'allowToUseOnFrontend',
                    'frontendIntegration',
                    'allowFrontendShortcodes',
                    'allowNegativeStock',
                    'indexationStep',
                    'searchResultsLimit',
                    'debugInfo',
                    'searchCF',
                    'searchCFLabel',
                    'newProductQty',
                    'newProductStatus',
                    'directDbUpdate',
                    'directDbSearch',
                    'customCss',
                    'customCssMobile',
                    'show_price_1',
                    'show_price_2',
                    'show_price_3',
                    'price_1_label',
                    'price_2_label',
                    'price_3_label',
                    'cartDecimalQuantity',
                    'defaultPriceField',
                    'sendAdminEmailCreatedOrder',
                    'sendClientEmailCreatedOrder',
                    'price_1_field',
                    'price_2_field',
                    'price_3_field',
                    'productStatuses',
                    'orderStatuses',
                    'productsCatalogVisibility',
                    'displayPayButton',
                    'orderStatusesAreStillNotCompleted',
                    'addAppUsersPermissions',
                    'removeAppUsersPermissions',
                    'storage',
                    'locations',
                    'enableLocations',
                    'fields',
                    'newOrderUserRequired',
                    'fulfillmentScanItemQty',
                    'notifyUsersStock',
                    'nowOrderDefaultUser',
                    'displaySearchCounter',
                    'openOrderAfterCreation',
                    'shippingRequired',
                    'paymentRequired',
                    'orderFulfillmentEnabled',
                    'orderFulFillmentField',
                    'ffQtyStep',
                    'receipt-width',
                    'receipt-template',
                    'modifyPreProcessSearchString',
                    'receiptOrderPreview',
                    'autoStatusFulfilled',
                    'delayBetweenScanning',
                    'displayCouponField',
                    'displayNoteField',
                    'defaultShippingMethods',
                    'orderFulfillmentByDefault',
                    'productLeftSidebarWidth',
                    'productMiddleRightWidth',
                    'productMiddleLeftWidth',
                    'productColumn4Width',
                    'defaultPaymentMethod',
                    'fulfilledNotAllowStatus',
                    'fulfilledCloseOrderAfter',
                    'dontAllowSwitchOrder',
                    'productsIndexation',
                    'ordersIndexation',
                    'resetFulfillmentByCloseOrder',
                    'sortOrderItemsByCategories',
                    'pickListProductCode',
                    'defaultProductQty',
                    'allowMarkFulfilled',
                    'role',
                    'disabledVariationsProducts',
                    'disabledVariationsOrders',
                    'defaultOrderTax',
                    'wpml'
                );

                foreach ($keys as $key) {
                    if (isset($_POST[$key])) {
                        $this->post[$key] = $this->getRequestData($_POST[$key], $key);

                        if (in_array($key, array(
                            "productStatuses",
                            "orderStatuses",
                            "orderStatusesAreStillNotCompleted",
                            'productsCatalogVisibility',
                            "notifyUsersStock",
                            'defaultShippingMethods'
                        )) && is_array($this->post[$key])) {
                            $this->post[$key] = implode(",", $this->post[$key]);
                        }
                    }
                }

                $this->post = SettingsHelper::stripslashesDeep($this->post, false);

                $this->updateSettings("updated_timestamp", time(), "text");

                $isIndexed = $this->getField("indexing", "indexed", false);
                if (!$isIndexed) $this->updateField("indexing", "indexed", false);

                $this->formSubmitSounds();

                if (isset($this->post["storage"]) && $this->post["storage"] === "table") {
                    $this->formSubmitStorageTable();
                    return;
                }

                if (isset($_POST["tab"]) && $_POST["tab"] === "permissions") {
                    if (isset($_POST["rolesPermissions"]) && is_array($_POST["rolesPermissions"])) {
                        $this->rolesPermissions = $_POST["rolesPermissions"];
                    }
                    $this->formSubmitRolePermissions();
                }

                if (isset($_POST["tab"]) && $_POST["tab"] === "plugins") {
                    if (isset($_POST["plugins"]) && is_array($_POST["plugins"])) {
                        $this->plugins = $_POST["plugins"];
                    }
                    $this->formSubmitPlugins();
                }

                if (isset($_POST["addAppUsersPermissions"])) {
                    $this->addAppUsersPermissions($_POST["addAppUsersPermissions"]);
                }

                if (isset($_POST["removeAppUsersPermissions"])) {
                    $this->removeAppUsersPermissions($_POST["removeAppUsersPermissions"]);
                }

                if (isset($this->post["key"])) {
                    @delete_transient('ukrsolution_upgrade_scanner_1.6.4');
                    $user_id = get_current_user_id();
                    update_option($user_id . '_' . basename(USBS_PLUGIN_BASE_PATH) . '_notice_dismissed', '', true);
                }
            }

            if (isset($this->post["tab"])) {
                $this->activeTab = $this->post["tab"];
            }

            $this->formSubmit();
        } catch (\Throwable $th) {
        }
    }

    private function getRequestData($value, $key = "")
    {

        if (is_array($value)) {
            $data = array();

            foreach ($value as $key => $_value) {
                if (is_array($_value)) {
                    $data[$key] = $this->getRequestData($_value);
                } else {
                    if (in_array($key, array("customCss", 'customCssMobile', "receipt-template", "modifyPreProcessSearchString"))) {
                        $data[$key] = $_value;
                    } else {
                        $data[$key] = sanitize_text_field($_value);
                    }
                }
            }

            return $data;
        } else {
            if (in_array($key, array("customCss", 'customCssMobile', "receipt-template", "modifyPreProcessSearchString"))) {
                return $value;
            } else {
                return sanitize_text_field($value);
            }
        }
    }

    public function getField($tab = "", $field = "", $defaultValue = "", $isEncode = false, $reSelectData = false, $excludes = array())
    {
        try {
            $settings = get_option($this->dbOptionSettingsKey, array());

            if (!$tab) {
                $settingsTable = $this->getSettings("", false, $reSelectData);

                $settings["prices"] = (object)array();
                $settings["modalShowLocations"] = 0;
                $settings["directDbSearch"] = "on";
                $settings["receipt-width"] = "55";
                $settings["displayCouponField"] = "on";
                $settings["displayNoteField"] = "on";
                $settings["displayPayButton"] = "on";
                $settings["orderFulfillmentEnabled"] = "on";
                $settings["productLeftSidebarWidth"] = "235";
                $settings["productMiddleLeftWidth"] = "320";
                $settings["productMiddleRightWidth"] = "260";
                $settings["productColumn4Width"] = "260";
                $settings["productsIndexation"] = "on";
                $settings["disabledVariationsProducts"] = "on";
                $settings["ordersIndexation"] = "on";
                $settings["pickListProductCode"] = "";
                $settings["defaultProductQty"] = "1";
                $settings["orderStatusesAreStillNotCompleted"] = "wc-pending,wc-processing,wc-on-hold";
                $settings["allowMarkFulfilled"] = "on";
                $settings["defaultOrderTax"] = "based_on_store";

                foreach ($settingsTable as $key => $setting) {
                    if (in_array($setting->field_name, $excludes)) continue;

                    if ($setting->field_name === "settings_prices") {
                        if ($setting->value) {
                            $settings["prices"] = (array)$setting->value;
                        }
                    } else if (in_array($setting->field_name, array(
                        "modalShowLocations",
                        "directDbUpdate",
                        "directDbSearch",
                        "cartDecimalQuantity",
                        "newOrderUserRequired",
                        "fulfillmentScanItemQty",
                        "nowOrderDefaultUser",
                        'displaySearchCounter',
                        'openOrderAfterCreation',
                        'shippingRequired',
                        'paymentRequired',
                        "fieldForNewProduct",
                        'orderFulfillmentEnabled',
                        'orderFulFillmentField',
                        'receipt-width',
                        'receipt-template',
                        'modifyPreProcessSearchString',
                        'updated_timestamp',
                        'autoStatusFulfilled',
                        'delayBetweenScanning',
                        'displayCouponField',
                        'displayNoteField',
                        'displayPayButton',
                        'orderFulfillmentByDefault',
                        'productLeftSidebarWidth',
                        'productMiddleRightWidth',
                        'productMiddleLeftWidth',
                        'productColumn4Width',
                        'defaultPaymentMethod',
                        'fulfilledNotAllowStatus',
                        'fulfilledCloseOrderAfter',
                        'dontAllowSwitchOrder',
                        'productsIndexation',
                        'ordersIndexation',
                        'resetFulfillmentByCloseOrder',
                        'sortOrderItemsByCategories',
                        'pickListProductCode',
                        'defaultProductQty',
                        'allowMarkFulfilled',
                        'disabledVariationsProducts',
                        'disabledVariationsOrders',
                        'defaultOrderTax',
                        'cfForNewProduct',
                    ))) {
                        $settings[$setting->field_name] = $setting->value;
                    } else if (in_array($setting->field_name, ["defaultOrderStatus", "orderStatusesAreStillNotCompleted", "defaultShippingMethod", "defaultPriceField", "allowNegativeStock", 'defaultShippingMethods'])) {
                        $settings[$setting->field_name] = $setting->value;
                    }
                }

                return $settings;
            }

            if ($tab === "prices") {
                $settingsTable = $this->getSettings("settings_prices");

                if (!$settingsTable) return $defaultValue;

                if (!$field) return $settingsTable;

                if (!isset($settingsTable->value) || !isset($settingsTable->value->$field)) return $defaultValue;

                return $settingsTable->value->$field;
            } else {
                if (!isset($settings[$tab])) return $defaultValue;

                if (!$field) return $settings[$tab];

                if (!isset($settings[$tab][$field])) return $defaultValue;

                if (!$settings[$tab][$field] && $defaultValue) return $defaultValue;

                return $settings[$tab][$field];
            }
        } catch (\Throwable $th) {
            return "";
        }
    }

    public function getOrderStatuses()
    {
        if (!function_exists("wc_get_order_statuses")) {
            return array();
        }

        $statuses = \wc_get_order_statuses();

        try {
            if (!$statuses) {
                $statuses = array();
            } else {
                foreach ($statuses as $key => &$value) {
                    $value = trim($value);
                    $value = strip_tags($value);
                }
            }
        } catch (\Throwable $th) {
        }

        return $statuses;
    }

    public function getCatalogVisibility()
    {
        return array(
            'visible' => __('Shop and search results', 'woocommerce'),
            'catalog' => __('Shop only', 'woocommerce'),
            'search' => __('Search results only', 'woocommerce'),
            'hidden' => __('Hidden', 'woocommerce'),
        );
    }

    public function getAllShippingMethod()
    {
        $cart = new Cart();
        $methods = $cart->getAllShippingMethods();

        if (!$methods) {
            $methods = array();
        }

        return $methods;
    }

    public function getUsers()
    {
        $users = array();
        $result = array();

        if (!$users) {
            $users = array();
        }

        foreach ($users as $user) {
            $result[] = array(
                "ID" => $user->ID,
                "display_name" => $user->display_name,
                "permission" => get_user_meta($user->ID, $this->userPermissionKey, true)
            );
        }

        return $result;
    }

    public function getTotalIndexedRecords()
    {
        global $wpdb;

        $tablePosts = $wpdb->prefix . Database::$posts;
        $posts = $wpdb->get_row("SELECT COUNT(P.id) as 'total' FROM {$tablePosts} AS P WHERE P.successful_update = '1' AND P.updated != '0000-00-00 00:00:00';");

        if ($posts && $posts->total) {
            return $posts->total;
        }

        return 0;
    }

    public function getTotalCantIndexedRecords()
    {
        global $wpdb;

        $tablePosts = $wpdb->prefix . Database::$posts;
        $posts = $wpdb->get_row("SELECT COUNT(P.id) as 'total' FROM {$tablePosts} AS P WHERE P.successful_update = '0';");

        if ($posts && $posts->total) {
            return $posts->total;
        }

        return 0;
    }

    public function getTotalPosts()
    {
        $result = Database::updatePostsTable(0, 1, false, true);
        $total = 0;

        if ($result && isset($result["total"])) {
            $total = $result["total"];
        }


        return $total;
    }

    public function updateField($tab, $field, $value)
    {
        try {
            if (!$tab || !$field) {
                return;
            }

            $settings = get_option($this->dbOptionSettingsKey, array());

            if (!isset($settings[$tab])) {
                $settings[$tab] = array();
            }

            $settings[$tab][$field] = $value;

            update_option($this->dbOptionSettingsKey, $settings);
        } catch (\Throwable $th) {
        }
    }
    private function formSubmit()
    {
        try {
            if (!$this->post) {
                return;
            }

            if (!isset($this->post["tab"])) {
                return;
            }

            if (isset($this->post["storage"]) && $this->post["storage"] === "table") {
                return;
            }

            if ($this->post["tab"] === "prices") {
                $this->updateSettings("settings_prices", json_encode($this->post));
            } else {
                $settings = get_option($this->dbOptionSettingsKey, array());

                $settings[$this->post["tab"]] = $this->post;

                update_option($this->dbOptionSettingsKey, $settings);
            }
        } catch (\Throwable $th) {
        }
    }

    private function formSubmitFrontendPermissions()
    {
        try {
            foreach ($this->getUsers() as $user) {
                update_user_meta($user["ID"], $this->userPermissionKey, "0");
            }

            foreach ($this->frontendPermissions as $id) {
                update_user_meta($id, $this->userPermissionKey, "1");
            }
        } catch (\Throwable $th) {
        }
    }

    private function formSubmitRolePermissions()
    {
        try {
            update_option($this->dbOptionRolesPermissionsKey, $this->rolesPermissions);
        } catch (\Throwable $th) {
        }
    }

    private function formSubmitPlugins()
    {
        try {
            update_option($this->dbOptionPluginsKey, $this->plugins);
        } catch (\Throwable $th) {
        }
    }

    private function formSubmitStorageTable()
    {
        try {
            if (isset($this->post['locations'])) {
                $location = new Locations();
                $location->update($this->post['locations']);
            }

            if (isset($this->post['wpml']) && isset($this->post['wpml']['languages']) && is_array($this->post['wpml']['languages'])) {
                $searchFilter = SearchFilter::get();
                $translations = null;

                if (WPML::status()) {
                    $translations = WPML::getTranslations();
                }

                if ($searchFilter) {
                    $searchFilter['wpml'] = array();

                    foreach ($translations as $lang => $translation) {
                        if (isset($this->post['wpml']['languages'][$lang])) {
                            $searchFilter['wpml'][$lang] = $this->post['wpml']['languages'][$lang] == 1;
                        } else {
                            $searchFilter['wpml'][$lang] = false;
                        }
                    }

                    $this->updateSettings('search_filter', json_encode($searchFilter));
                }
            }

            if (isset($this->post["tab"]) && $this->post["tab"] == "fields") {
                InterfaceData::saveFields($this->post["fields"], $this->post["role"]);

                if (isset($this->post["defaultPriceField"])) {
                    $this->updateSettings("settings_prices", json_encode(array(
                        "defaultPriceField" => $this->post["defaultPriceField"]
                    )));
                }
                if (isset($this->post["productLeftSidebarWidth"])) {
                    $this->updateSettings("productLeftSidebarWidth", $this->post["productLeftSidebarWidth"]);
                    $this->updateSettings("productMiddleRightWidth", $this->post["productMiddleRightWidth"]);
                    $this->updateSettings("productMiddleLeftWidth", $this->post["productMiddleLeftWidth"]);
                    $this->updateSettings("productColumn4Width", $this->post["productColumn4Width"]);
                }
                return;
            }

            if (isset($this->post["tab"]) && $this->post["tab"] == "locations-data") {
                $locationData = isset($_POST["locationData"]) ? $_POST["locationData"] : array();
                LocationsData::saveLocations($locationData);
                return;
            }

            foreach ($this->post as $key => $value) {
                if (!in_array($key, array("tab", "storage", "locations"))) {
                    if (in_array($key, array("customCss", 'customCssMobile', "modifyPreProcessSearchString"))) {
                        $this->updateSettings($key, stripslashes($value), "text");
                    } else {
                        $this->updateSettings($key, $value, "text");
                    }
                }
            }
        } catch (\Throwable $th) {
        }
    }

    private function formSubmitSounds()
    {
        try {
            if (isset($_FILES["increaseFile"]) && isset($_FILES["increaseFile"]["name"]) && $_FILES["increaseFile"]["name"]) {
                $url = $this->uploadFile($_FILES["increaseFile"]);
                $this->updateSettings("sound_increase", $url, "text");
            }

            if (isset($_FILES["decreaseFile"]) && isset($_FILES["decreaseFile"]["name"]) && $_FILES["decreaseFile"]["name"]) {
                $url = $this->uploadFile($_FILES["decreaseFile"]);
                $this->updateSettings("sound_decrease", $url, "text");
            }

            if (isset($_FILES["failFile"]) && isset($_FILES["failFile"]["name"]) && $_FILES["failFile"]["name"]) {
                $url = $this->uploadFile($_FILES["failFile"]);
                $this->updateSettings("sound_fail", $url, "text");
            }
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    private function uploadFile($file)
    {
        try {
            $wp_upload_dir = wp_upload_dir();
            $upload_dir = $wp_upload_dir['basedir'] . '/barcode-scanner/';
            $upload_dir_url = $wp_upload_dir['baseurl'] . '/barcode-scanner/';

            if (!file_exists($upload_dir)) {
                wp_mkdir_p($upload_dir);
            }
            if (!file_exists($upload_dir . 'sounds')) {
                wp_mkdir_p($upload_dir . 'sounds');
            }

            $dt = new \DateTime("now");
            $fileName = $dt->format("dmYhis") . "-" . $file["name"];

            $allowedFileTypes = ['audio/mp3', 'audio/mpeg'];
            $fileType = $file["type"];

            if (in_array($fileType, $allowedFileTypes) && move_uploaded_file($file["tmp_name"], $upload_dir . "sounds/" . $fileName)) {
                return str_replace(home_url(), '', $upload_dir_url . "sounds/" . $fileName);
            }

            return null;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function getRoles()
    {
        global $wp_roles;

        $allRoles = $wp_roles->roles;

        return $allRoles;
    }

    public function getPlugins()
    {
        $plugins = get_plugins();

        $userSettings = get_option($this->dbOptionPluginsKey, array());

        $default = array(
            "barcode-scanner.php",
            "woocommerce/woocommerce.php",
            "atum-stock-manager-for-woocommerce.php",
            "ean-for-woocommerce/ean-for-woocommerce.php",
            "ean-for-woocommerce-pro/ean-for-woocommerce-pro.php",
            "woo-add-gtin/woocommerce-gtin.php",
            "product-gtin-ean-upc-isbn-for-woocommerce/product-gtin-ean-upc-isbn-for-woocommerce.php",
            "aftership-woocommerce-tracking/aftership-woocommerce-tracking.php",
            "woo-advanced-shipment-tracking/woocommerce-advanced-shipment-tracking.php",
            "yith-woocommerce-order-tracking/init.php",
            "wt-woocommerce-sequential-order-numbers/wt-advanced-order-number.php",
            "woocommerce-sequential-order-numbers-pro/woocommerce-sequential-order-numbers-pro.php",
            "stock-locations-for-woocommerce/stock-locations-for-woocommerce.php",
            "woocommerce-wholesale-pricing/woocommerce-wholesale-pricing.php",
            "zettle-pos-integration/zettle-pos-integration.php",
            "dokan-lite/dokan.php",
            "custom-order-statuses-for-woocommerce/custom-order-statuses-for-woocommerce.php",
            "checkout-fees-for-woocommerce/checkout-fees-for-woocommerce.php",
            "sitepress-multilingual-cms/sitepress.php",
            "bp-custom-order-status-for-woocommerce/main.php",
            "polylang/polylang.php",
            "polylang-pro/polylang.php",
            "woocommerce-order-status-manager/woocommerce-order-status-manager.php",
            "wp-mail-smtp/wp_mail_smtp.php",
            "yith-woocommerce-barcodes-premium/init.php",
            "ni-woocommerce-custom-order-status/ni-woocommerce-custom-order-status.php"
        );

        foreach ($plugins as $slug => &$value) {
            if (empty($userSettings)) {
                $value["bs_active"] = in_array($slug, $default) ? 1 : 0;
            } else {
                $value["bs_active"] = in_array($slug, $userSettings) ? 1 : 0;
            }
        }

        return $plugins;
    }

    public function getRolePermissions($role)
    {
        $roles = get_option($this->dbOptionRolesPermissionsKey, null);
        $defaultAccess = array("administrator", "shop_manager");
        $defaultFrontAccess = array("barcode_scanner_front_end");

        if ($roles) {
            foreach ($roles as $key => &$value) {
                if (!isset($value["show_prices"])) {
                    $value["show_prices"] = in_array($key, $defaultAccess) || in_array($key, $defaultFrontAccess) ? 1 : 0;
                }
            }
        }

        if ($roles === null || !$roles) {
            $roles = array();

            foreach ($this->getRoles() as $key => $value) {
                $roles[$key] = array(
                    "inventory" => in_array($key, $defaultAccess) || in_array($key, $defaultFrontAccess) ? 1 : 0,
                    "newprod" => in_array($key, $defaultAccess) || in_array($key, $defaultFrontAccess) ? 1 : 0,
                    "orders" => in_array($key, $defaultAccess) || in_array($key, $defaultFrontAccess) ? 1 : 0,
                    "onlymy" => 0,
                    "show_prices" => in_array($key, $defaultAccess) || in_array($key, $defaultFrontAccess) ? 1 : 0,
                    "cart" => in_array($key, $defaultAccess) || in_array($key, $defaultFrontAccess) ? 1 : 0,
                    "linkcustomer" => in_array($key, $defaultAccess) || in_array($key, $defaultFrontAccess) ? 1 : 0,
                    "frontend" => in_array($key,  $defaultAccess) || in_array($key, $defaultFrontAccess) ? 1 : 0,
                    "plugin_settings" => in_array($key,  $defaultAccess) ? 1 : 0,
                    "plugin_logs" => in_array($key,  $defaultAccess) ? 1 : 0
                );
            }
        }

        if (!isset($roles["barcode_scanner_front_end"])) {
            $roles["barcode_scanner_front_end"] = array(
                "inventory" => 1,
                "newprod" => 1,
                "orders" => 1,
                "onlymy" => 0,
                "show_prices" => 0,
                "cart" => 1,
                "linkcustomer" => 1,
                "frontend" => 1,
                "plugin_settings" => 0,
                "plugin_logs" => 0
            );
        }

        if (isset($roles[$role])) {
            $data = $roles[$role];
            if (!isset($data["newprod"]) && isset($data["inventory"])) $data["newprod"] = $data["inventory"];
            if (!isset($data["linkcustomer"]) && isset($data["cart"])) $data["linkcustomer"] = $data["cart"];

            return $data;
        }

        return array();
    }

    public function resetOptionsSettings()
    {
        delete_option($this->dbOptionRolesPermissionsKey);
        delete_option("barcode-scanner-settings-options");
    }

    public function getUserRolePermissions($userId = null)
    {
        $result = array("inventory" => 0, "newprod" => 0, "orders" => 0, "onlymy" => 0, "show_prices" => 0, "cart" => 0, "linkcustomer" => 0, "frontend" => 0, "plugin_settings" => 0, "plugin_logs" => 0);

        if (!$userId) {
            $userId = get_current_user_id();
        }

        if (!$userId) {
            return $result;
        }

        $userMeta = get_userdata($userId);
        $userRoles = $userMeta ? $userMeta->roles : null;

        if ($userRoles) {
            foreach ($userRoles as $roleKey) {
                $permissions = $this->getRolePermissions($roleKey);

                if (is_array($permissions)) {
                    foreach ($permissions as $key => $value) {
                        if ($value == 1) {
                            $result[$key] = 1;
                        }
                    }
                }
            }
        }

        return $result;
    }

    public function getAppUsersPermissions()
    {
        return array();
    }

    private function addAppUsersPermissions($userId)
    {
        update_user_meta($userId, $this->userAppPermissionKey, $this->generateRandomString(16));
    }

    private function removeAppUsersPermissions($userId)
    {
        update_user_meta($userId, $this->userAppPermissionKey, "");
    }

    public function generateRandomString($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public function updateSettingsArray(WP_REST_Request $request)
    {
        $data = $request->get_param("data");

        if (!$data) {
            return;
        }

        if (!is_array($data)) {
            return;
        }

        foreach ($data as $key => $value) {
            $this->updateSettings($key, $value, "text");
        }

        return rest_ensure_response($data);
    }

    public function loadSettingsArray(WP_REST_Request $request)
    {
        $platform = $request->get_param("platform");
        $result = array();

        if ($platform !== "web") {
            if ($this->coreInstance) {
                $MobileRouter = new MobileRouter();
                $urlData = $MobileRouter->getParamsFromPlainUrl();
                $jsData = $this->coreInstance->adminEnqueueScripts(true, true, $urlData);
                $usbs = $jsData && isset($jsData['usbs']) ? $jsData['usbs'] : array();

                $result["usbs"] = $usbs;
            }
        }

        return rest_ensure_response($result);
    }

    public function saveTemplateOrderId(WP_REST_Request $request)
    {
        $orderId = $request->get_param("orderId");

        if ($orderId && is_numeric($orderId)) {
            $settings = new Settings();
            $settings->updateSettings("receiptOrderPreview", $orderId, "text");
        }

        return rest_ensure_response(array("orderId" => esc_attr($orderId)));
    }

    public function findOrder(WP_REST_Request $request)
    {
        $query = $request->get_param("query");
        $query = trim($query);
        $query = addslashes($query);

        if ($query) {
            $managementActions = new ManagementActions();
            $orderRequest = new WP_REST_Request("", "");
            $orderRequest->set_param("query", $query);
            $orderRequest->set_param("autoFill", true);
            $orderRequest->set_param("page", "history");
            $result = $managementActions->orderSearch($orderRequest);
            if ($result->data && isset($result->data["orders"]) && count($result->data["orders"])) {
                return rest_ensure_response(array('orders' => $result->data["orders"]));
            }
        }

        return rest_ensure_response(array('orders' => array()));
    }
}
