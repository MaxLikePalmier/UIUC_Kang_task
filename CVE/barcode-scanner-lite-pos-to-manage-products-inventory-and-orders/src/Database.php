<?php

namespace UkrSolution\BarcodeScanner;

use Atum\Inc\Helpers;
use UkrSolution\BarcodeScanner\API\actions\HPOS;
use UkrSolution\BarcodeScanner\API\actions\ManagementActions;
use UkrSolution\BarcodeScanner\API\classes\SearchFilter;
use UkrSolution\BarcodeScanner\API\PluginsHelper;
use UkrSolution\BarcodeScanner\features\Debug\Debug;
use UkrSolution\BarcodeScanner\features\interfaceData\InterfaceData;
use UkrSolution\BarcodeScanner\features\logs\LogActions;
use UkrSolution\BarcodeScanner\features\settings\Settings;
use UkrSolution\BarcodeScanner\features\settings\SettingsHelper;

class Database
{
    public static $posts = "barcode_scanner_posts";
    public static $columns = "barcode_scanner_posts_columns";
    public static $settings = "barcode_scanner_settings";
    public static $logs = "barcode_scanner_logs";
    public static $locations = "barcode_scanner_locations";
    public static $locationsTree = "barcode_scanner_locations_tree";
    public static $interface = "barcode_scanner_interface";
    public static $history = "barcode_scanner_history";
    public static $cart = "barcode_scanner_cart";
    public static $cartData = "barcode_scanner_cart_data";
    public static $postsList = "barcode_scanner_posts_list";
    public static $postMetaFieldPrefix = "postmeta_";
    public static $postsFields = array("post_excerpt" => "like", "post_title" => "like");
    private static $isTriggerTracked = null;
    private static $managementActions = null;
    private static $indexedPostIds = array();

    public static function setupTables($network_wide)
    {
        global $wpdb;

        if (is_multisite() && $network_wide) {
            $blog_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");

            foreach ($blog_ids as $blog_id) {
                switch_to_blog($blog_id);
                self::createTables();
                restore_current_blog();
            }
        } else {
            self::createTables();
        }

        self::createTables();
        self::migrateData();
        self::defaultData();
    }

    public static function createTables()
    {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        try {
            ob_start();

            self::setupTableProducts();
            self::setupTableColumns();
            self::setupTableSettings();
            self::setupTableLogs();
            self::setupTableLocations();
            self::setupTableLocationsTree();
            self::setupTableInterface();
            self::setupTableHistory();
            self::setupTablePostsList();
            self::setupTableCart();

            $result = ob_get_clean();
        } catch (\Throwable $th) {
        }
    }

    public static function migrateData()
    {
        global $wpdb;

        $table = $wpdb->prefix . self::$settings;
        $result = $wpdb->get_row("SELECT COUNT(id) as 'counter' FROM {$table} WHERE field_name IS NOT NULL;");

        if ($result && $result->counter == 0) {
            $wpdb->query("UPDATE {$table} SET field_name = `key` WHERE field_name IS NULL;");
        }

        $permissions = get_option("barcode-scanner-roles-permissions", null);

        if ($permissions) {
            $defaultAccess = array("administrator", "shop_manager");

            foreach ($permissions as $key => &$value) {
                if (!isset($value['plugin_settings'])) {
                    $value['plugin_settings'] = in_array($key, $defaultAccess) ? 1 : 0;
                }

                if (!isset($value['plugin_logs'])) {
                    $value['plugin_logs'] = in_array($key, $defaultAccess) ? 1 : 0;
                }
            }

            update_option("barcode-scanner-roles-permissions", $permissions);
        }
    }

    public static function defaultData()
    {
        global $wpdb;

        $dt = new \DateTime("now");
        $created = $dt->format("Y-m-d H:i:s");
        $settings = new Settings();

        try {
            $table = $wpdb->prefix . self::$interface;
            $dt = new \DateTime("now");
            $dt->modify("-5 second");
            $created = $dt->format("Y-m-d H:i:s");

            $oldPrice1 = $settings->getField("prices", "show_regular_price", "");
            $status = (($settings->getField("prices", "show_price_1", "on") === "on" || $oldPrice1 === "on") && $oldPrice1 !== "off") ? 1 : 0;
            $label = $settings->getField("prices", "price_1_label", "Regular price");
            $field = $settings->getField("prices", "price_1_field", "_regular_price");
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET `status` = %d, `field_name` = %s, `field_label` = %s, `created` = %s WHERE field_name = %s AND `created` = `updated`;",
                    $status,
                    $field,
                    $label,
                    $created,
                    "_regular_price"
                )
            );


            $oldPrice2 = $settings->getField("prices", "show_sale_price", "");
            $status = (($settings->getField("prices", "show_price_2", "on") === "on" || $oldPrice2 === "on") && $oldPrice2 !== "off") ? 1 : 0;
            $label = $settings->getField("prices", "price_2_label", "Sale price");
            $field = $settings->getField("prices", "price_2_field", "_sale_price");
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET `status` = %d, `field_name` = %s, `field_label` = %s, `created` = %s WHERE field_name = %s AND `created` = `updated`;",
                    $status,
                    $field,
                    $label,
                    $created,
                    "_sale_price"
                )
            );

            $oldPrice3 = $settings->getField("prices", "show_other_price", "");
            $status = (($settings->getField("prices", "show_price_3", "off") === "on" || $oldPrice3 === "on") && $oldPrice3 !== "off") ? 1 : 0;
            $label = $settings->getField("prices", "other_price_label", "");
            if (!$label) $label = $settings->getField("prices", "price_3_label", "Purchase price");
            $field = $settings->getField("prices", "other_price_field", "");
            if (!$field) $field = $settings->getField("prices", "price_3_field", "_purchase_price");
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET `status` = %d, `field_name` = %s, `field_label` = %s, `created` = %s WHERE field_name = %s AND `created` = `updated`;",
                    $status,
                    $field,
                    $label,
                    $created,
                    "_purchase_price"
                )
            );

            $allowNegativeStock = $settings->getField("general", "allowNegativeStock", "");
            if ($settings->getSettings("allowNegativeStock") == null && $allowNegativeStock) {
                $settings->updateSettings("allowNegativeStock", $allowNegativeStock, "text");
            }

            $searchCF = $settings->getField("general", "searchCF", "on");
            if ($settings->getSettings("searchCF") == null) {
                $settings->updateSettings("searchCF", $searchCF, "text");
            }

            $searchCFLabel = $settings->getField("general", "searchCFLabel", "");
            if ($settings->getSettings("searchCFLabel") == null && $searchCFLabel) {
                $settings->updateSettings("searchCFLabel", $searchCFLabel, "text");
            }
        } catch (\Throwable $th) {
        }

        try {
            $interfaceData = new InterfaceData();

            $fields = $interfaceData::getFields(true, "", false, "default");

            $interfaceData::generateFieldsTranslationsFile($fields);
        } catch (\Throwable $th) {
        }

        try {
            $template = $settings->getSettings("receipt-template");

            if (!$template) {
                SettingsHelper::restoreReceiptTemplate();
            }
        } catch (\Throwable $th) {
        }

        try {
            $filterRecord = $settings->getSettings("search_filter", true);

            if (!$filterRecord) {
                $filter = SearchFilter::get(get_current_user_id());

                if ($filter) {
                    $settings->updateSettings('search_filter', json_encode($filter));
                }
            }
        } catch (\Throwable $th) {
        }
    }

    public static function clearTableColumns()
    {
        global $wpdb;

        $table = $wpdb->prefix . self::$columns;
        $wpdb->query("DELETE FROM {$table};");
    }

    public static function initDataTableColumns()
    {
        global $wpdb;

        $settings = new Settings();
        $filterRecord = $settings->getSettings("search_filter", true);

        if ($filterRecord && $filterRecord->value) {
            $fields = array();

            if (isset($filterRecord->value['products']) && $filterRecord->value['products']) {
                foreach ($filterRecord->value['products'] as $key => $value) {
                    if (preg_match("/^custom-\d+$/", $key, $m) || $key === 'custom') {
                        if (!in_array($value, $fields) && !key_exists($value, self::$postsFields)) $fields[] = trim($value);
                    }
                }
            }

            if (isset($filterRecord->value['orders']) && $filterRecord->value['orders']) {
                foreach ($filterRecord->value['orders'] as $key => $value) {
                    if (preg_match("/^custom-\d+$/", $key, $m) || $key === 'custom') {
                        if (!in_array($value, $fields) && !key_exists($value, self::$postsFields)) $fields[] = trim($value);
                    }
                }
            }

            $tableColumns = $wpdb->prefix . self::$columns;

            $columnsMaxId = $wpdb->get_row("SELECT MAX(id) AS maxId FROM {$tableColumns} LIMIT 1;");
            $maxId = 1;

            if ($columnsMaxId && $columnsMaxId->maxId) {
                $maxId = $columnsMaxId->maxId + 1;
            }

            foreach ($fields as $field) {
                if ($field) {
                    $wpdb->insert($tableColumns, array("name" => $field, "column" => "column_{$maxId}", "table" => "postmeta"), array('%s', '%s', '%s'));
                    $maxId++;
                }
            }
        }
    }

    public static function removeTableProducts()
    {
        global $wpdb;

        $table = $wpdb->prefix . self::$posts;
        $wpdb->query("DROP TABLE IF EXISTS {$table};");
    }

    public static function removeAllTables()
    {
        global $wpdb;

        $table = $wpdb->prefix . self::$posts;
        $wpdb->query("DROP TABLE IF EXISTS {$table};");

        $table = $wpdb->prefix . self::$columns;
        $wpdb->query("DROP TABLE IF EXISTS {$table};");

        $table = $wpdb->prefix . self::$settings;
        $wpdb->query("DROP TABLE IF EXISTS {$table};");

        $table = $wpdb->prefix . self::$locations;
        $wpdb->query("DROP TABLE IF EXISTS {$table};");

        $table = $wpdb->prefix . self::$locationsTree;
        $wpdb->query("DROP TABLE IF EXISTS {$table};");

        $table = $wpdb->prefix . self::$interface;
        $wpdb->query("DROP TABLE IF EXISTS {$table};");

        $table = $wpdb->prefix . self::$history;
        $wpdb->query("DROP TABLE IF EXISTS {$table};");

        $table = $wpdb->prefix . self::$postsList;
        $wpdb->query("DROP TABLE IF EXISTS {$table};");

        $table = $wpdb->prefix . self::$cart;
        $wpdb->query("DROP TABLE IF EXISTS {$table};");

        $table = $wpdb->prefix . self::$cartData;
        $wpdb->query("DROP TABLE IF EXISTS {$table};");
    }

    public static function setupTableProducts($useUniqueIndex = true, $isCHeckCustomColumns = false)
    {
        global $wpdb;

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $table = $wpdb->prefix . self::$posts;
        $prefixMF = self::$postMetaFieldPrefix;
        $postMetaFields = array(
            "`{$prefixMF}_sku`",
            "`{$prefixMF}_variation_description`",
            "`{$prefixMF}_customer_user`",
            "`{$prefixMF}_alg_ean`",
            "`{$prefixMF}_wpm_gtin_code`",
            "`{$prefixMF}hwp_product_gtin`",
            "`{$prefixMF}_ywbc_barcode_display_value`",
            "`{$prefixMF}_wepos_barcode`",
            "`{$prefixMF}_ts_gtin`",
            "`{$prefixMF}_ts_mpn`",
            "`{$prefixMF}_zettle_barcode`",
            "`{$prefixMF}_order_number`",
            "`{$prefixMF}_billing_address_index`",
            "`{$prefixMF}_shipping_address_index`",
            "`{$prefixMF}_wc_shipment_tracking_items`",
            "`{$prefixMF}_aftership_tracking_items`",
            "`{$prefixMF}ywot_tracking_code`",
            "`usbs_barcode_field`",
            "`atum_supplier_sku`",
            "`atum_barcode`",
            "`atum_supplier_id`",
            "`client_email`",
            "`client_name`",
        );

        $uniqueIndex = $useUniqueIndex ? " UNIQUE INDEX `post_id` (`post_id`), " : "";

        $sql = "CREATE TABLE `{$table}` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `post_id` bigint(20) DEFAULT NULL,
            `successful_update` TINYINT(1) NULL DEFAULT '1',
            `post_title` text DEFAULT NULL,
            `post_excerpt` text DEFAULT NULL,
            `post_type` varchar(20) DEFAULT NULL,
            `post_status` varchar(20) DEFAULT NULL,
            `post_parent_status` varchar(20) DEFAULT NULL,
            `post_parent` bigint(20) DEFAULT NULL,
            `post_author` bigint(20) DEFAULT NULL,
            " . implode(" longtext DEFAULT NULL,\n", $postMetaFields) . " longtext DEFAULT NULL,
            `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated` datetime DEFAULT NULL,
            `post_date` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            {$uniqueIndex}
            INDEX `post_parent` (`post_parent`)
        ) CHARSET=UTF8MB4 COLLATE=utf8mb4_unicode_ci ENGINE=InnoDB;";

        \dbDelta($sql);

        if ($isCHeckCustomColumns) {
            $tableColumns = $wpdb->prefix . self::$columns;
            $columns = $wpdb->get_results("SELECT * FROM {$tableColumns}");

            if (!$columns) {
                return;
            }

            foreach ($columns as $column) {
                try {
                    if ($column->column) {
                        $alterTable = "ALTER TABLE `{$table}` ADD `{$column->column}` longtext DEFAULT NULL; ";
                        $alterTable = $wpdb->query($alterTable);
                    }
                } catch (\Throwable $th) {
                }
            }
        }
    }

    public static function setupTableColumns()
    {
        global $wpdb;

        $table = $wpdb->prefix . self::$columns;

        $sql = "CREATE TABLE `{$table}` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(255) DEFAULT NULL,
            `column` varchar(255) DEFAULT NULL,
            `table` varchar(255) DEFAULT NULL,
            `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated` datetime DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) CHARSET=UTF8MB4 COLLATE=utf8mb4_unicode_ci ENGINE=InnoDB";

        \dbDelta($sql);
    }

    public static function setupTableSettings()
    {
        global $wpdb;

        $table = $wpdb->prefix . self::$settings;

        $sql = "CREATE TABLE `{$table}` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) DEFAULT NULL,
            `field_name` varchar(255) DEFAULT NULL,
            `value` longtext DEFAULT NULL,
            `type` varchar(255) DEFAULT NULL,
            `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated` datetime DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) CHARSET=UTF8MB4 COLLATE=utf8mb4_unicode_ci ENGINE=InnoDB;";

        \dbDelta($sql);
    }

    public static function setupTableLogs()
    {
        global $wpdb;

        $table = $wpdb->prefix . self::$logs;
        $actions = "'" . implode("','", LogActions::$actions) . "'";

        $sql = "CREATE TABLE `{$table}` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) DEFAULT NULL,
            `post_id` bigint(20) DEFAULT NULL,
            `parent_post_id` bigint(20) DEFAULT NULL,
            `datetime` datetime DEFAULT NULL,
            `action` ENUM({$actions}) NULL DEFAULT NULL,
            `custom_action` varchar(255) DEFAULT NULL,
            `field` varchar(255) DEFAULT NULL,
            `value` varchar(255) DEFAULT NULL,
            `old_value` varchar(255) DEFAULT NULL,
            `type` varchar(255) DEFAULT NULL,
            `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
	        INDEX `post_id` (`post_id`)
        ) CHARSET=UTF8MB4 COLLATE=utf8mb4_unicode_ci ENGINE=InnoDB";

        \dbDelta($sql);
    }

    public static function setupTableLocations()
    {
        global $wpdb;

        $table = $wpdb->prefix . self::$locations;

        $sql = "CREATE TABLE `{$table}` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `slug` varchar(255) DEFAULT NULL,
            `name` varchar(255) DEFAULT NULL,
            `order` int(10) DEFAULT NULL,
            `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated` datetime DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) CHARSET=UTF8MB4 COLLATE=utf8mb4_unicode_ci ENGINE=InnoDB";

        \dbDelta($sql);
    }

    public static function setupTableLocationsTree()
    {
        global $wpdb;

        $table = $wpdb->prefix . self::$locationsTree;

        $sql = "CREATE TABLE `{$table}` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `parent_id` int(11) DEFAULT NULL,
            `name` varchar(255) DEFAULT NULL,
            `order` int(10) DEFAULT NULL,
            `is_removed` int(1) DEFAULT NULL,
            `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated` datetime DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) CHARSET=UTF8MB4 COLLATE=utf8mb4_unicode_ci ENGINE=InnoDB";

        \dbDelta($sql);
    }

    public static function setupTableInterface()
    {
        global $wpdb;

        $table = $wpdb->prefix . self::$interface;

        $sql = "CREATE TABLE `{$table}` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `field_name` varchar(255) DEFAULT NULL,
            `field_label` varchar(255) DEFAULT NULL,
            `label_position` varchar(255) DEFAULT NULL,
            `field_height` int(10) DEFAULT NULL,
            `label_width` int(10) DEFAULT 50,
            `position` varchar(255) DEFAULT NULL,
            `type` varchar(255) DEFAULT NULL,
            `options` longtext DEFAULT NULL,
            `attribute_id` bigint(10) DEFAULT NULL,
            `order` int(10) DEFAULT NULL,
            `order_mobile` int(10) DEFAULT NULL,
            `show_in_create_order` int(1) DEFAULT 0,
            `show_in_products_list` int(1) DEFAULT 0,
            `disabled_field` int(1) DEFAULT 0,
            `use_for_auto_action` int(1) DEFAULT 0,
            `role` varchar(255) DEFAULT NULL,
            `status` int(1) DEFAULT 1,
            `mobile_status` int(1) DEFAULT 1,
            `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated` datetime DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) CHARSET=UTF8MB4 COLLATE=utf8mb4_unicode_ci ENGINE=InnoDB";

        \dbDelta($sql);

        $_stock_statuses = array("instock" => "Instock", "outofstock" => "Out of stock", "onbackorder" => "On backorder");
        $product_statuses = array("publish" => "Publish", "pending" => "Pending", "private" => "Private", "draft" => "Draft");
        $widthLeft = 40;
        $widthRight = 50;
        $widthBottom = 100;

        $defaultFieldsForNewInstal = array(
            array("field_name" => "", "field_label" => "White space", "label_position" => "left", "label_width" => $widthLeft, "position" => "product-middle-left", "type" => "white_space", "field_height" => 10, "status" => 1, "mobile_status" => 0, "order" => 2000),
            array("field_name" => "_sku", "field_label" => "SKU", "label_position" => "left", "label_width" => $widthLeft, "position" => "product-middle-left", "type" => "text", "field_height" => 0, "show_in_create_order" => 1, "status" => 1, "order" => 990),
            array("field_name" => "_regular_price", "field_label" => "Regular price", "label_position" => "left", "label_width" => $widthLeft, "position" => "product-middle-left", "type" => "price", "field_height" => 0, "status" => 1, "order" => 980),
            array("field_name" => "_sale_price", "field_label" => "Sale price", "label_position" => "left", "label_width" => $widthLeft, "position" => "product-middle-left", "type" => "price", "field_height" => 0, "status" => 1, "order" => 970),
            array("field_name" => "usbs_barcode_field", "field_label" => "Barcode", "label_position" => "left", "label_width" => $widthLeft, "position" => "product-middle-left", "type" => "text", "field_height" => 0, "status" => 1, "order" => 940),
            array("field_name" => "usbs_product_status", "field_label" => "Product Status", "label_position" => "top", "label_width" => $widthRight, "position" => "product-middle-right", "type" => "select", "field_height" => 0, "status" => 1, "options" => json_encode($product_statuses), "order" => 930),
            array("field_name" => "_stock_status", "field_label" => "Stock Status", "label_position" => "top", "label_width" => $widthRight, "position" => "product-middle-right", "type" => "select", "field_height" => 0, "status" => 1, "options" => json_encode($_stock_statuses), "order" => 920),
            array("field_name" => "_stock", "field_label" => "Quantity", "label_position" => "top", "label_width" => $widthRight, "position" => "product-middle-right", "type" => "number_plus_minus", "field_height" => 0, "status" => 1, "order" => 910),
            array("field_name" => "usbs_stock_location_level_1", "field_label" => "Warehouse", "label_position" => "top", "label_width" => $widthRight, "position" => "product-left-sidebar", "type" => "text", "field_height" => 0, "status" => 1, "order" => 300),
            array("field_name" => "usbs_stock_location_level_2", "field_label" => "Rack", "label_position" => "top", "label_width" => $widthRight, "position" => "product-left-sidebar", "type" => "text", "field_height" => 0, "status" => 1, "order" => 290),
            array("field_name" => "usbs_stock_location_level_3", "field_label" => "Shelf", "label_position" => "top", "label_width" => $widthRight, "position" => "product-left-sidebar", "type" => "text", "field_height" => 0, "status" => 1, "order" => 280),
        );
        $defaultFieldsForAll = array(
            array("field_name" => "usbs_categories", "field_label" => "Categories", "label_position" => "left", "label_width" => $widthLeft, "position" => "product-middle-left", "type" => "categories", "field_height" => 0, "status" => 1, "order" => 400),
            array("field_name" => "usbs_variation_attributes", "field_label" => "Variation attributes", "label_position" => "top", "label_width" => $widthRight, "position" => "product-middle-right", "type" => "variation_attributes", "field_height" => 0, "status" => 1, "order" => 870),
            array("field_name" => "_tax_class", "field_label" => "Tax class", "label_position" => "top", "label_width" => $widthRight, "position" => "product-middle-right", "type" => "select", "field_height" => 0, "status" => 0, "mobile_status" => 0, "order" => 850),
            array("field_name" => "_shipping_class", "field_label" => "Shipping class", "label_position" => "top", "label_width" => $widthRight, "position" => "product-middle-right", "type" => "select", "field_height" => 0, "status" => 0, "mobile_status" => 0, "order" => 860),
        );



        $dt = new \DateTime("now");
        $created = $dt->format("Y-m-d H:i:s");
        $records = $wpdb->get_row("SELECT COUNT(T.id) AS 'count' FROM {$table} AS T;");

        if (!$records || $records->count == 0) {
            foreach ($defaultFieldsForNewInstal as $field) {
                try {
                    $record = $wpdb->get_row($wpdb->prepare("SELECT T.id FROM {$table} AS T WHERE T.field_name = %s;", $field["field_name"]));

                    if (!$record) {
                        $field["updated"] = $created;
                        $field["order_mobile"] = $field["order"];
                        $wpdb->insert($table, $field);
                    }
                } catch (\Throwable $th) {
                }
            }
        }

        foreach ($defaultFieldsForAll as $field) {
            try {
                $record = $wpdb->get_row($wpdb->prepare("SELECT T.id FROM {$table} AS T WHERE T.field_name = %s;", $field["field_name"]));

                if (!$record) {
                    $field["updated"] = $created;
                    $field["order_mobile"] = $field["order"];
                    $wpdb->insert($table, $field);
                }
            } catch (\Throwable $th) {
            }
        }

        $plugins = PluginsHelper::customPluginFields();
        $position = "product-middle-left";

        $orderData = $wpdb->get_row("SELECT T.* FROM {$table} AS T WHERE T.field_name = '_sale_price';");

        $order = 500;

        foreach ($plugins as $key => $value) {
            if ($value["status"] == 1) {
                try {
                    if (isset($value['position']) && $value['position'] == "product-middle-bottom") {
                        $fieldPosition = $value['position'];
                        $lWidth = $widthBottom;
                    } else {
                        $fieldPosition = $position;
                        $lWidth = $position == "product-middle-left" ? $widthLeft : $widthRight;
                    }

                    if (isset($value['label_position'])) {
                        $lPosition = $value['label_position'];
                    } else {
                        $lPosition = $fieldPosition == "product-middle-right" ? "top" : "left";
                    }

                    $type = isset($value["type"]) && $value["type"] ? $value["type"] : "text";
                    $_order = $orderData && $type == "price" ? $orderData->order : 0;
                    $_order_mobile = $orderData && $type == "price" ? $orderData->order_mobile : 0;

                    if (!$_order) {
                        $_order = isset($value["order"]) ? $value["order"] : $order;
                        $order -= 3;
                    }

                    if (!$_order_mobile) {
                        $_order_mobile = isset($value["order"]) ? $value["order"] : $order;
                        $order -= 3;
                    }

                    $field = array(
                        "field_name" => $key,
                        "field_label" => $value["label"],
                        "label_position" => $orderData && $type == "price" ? $orderData->label_position : $lPosition,
                        "label_width" => $orderData && $type == "price" ? $orderData->label_width : $lWidth,
                        "position" => $orderData && $type == "price" ? $orderData->position : $fieldPosition,
                        "type" => $type,
                        "field_height" => $orderData && $type == "price" ? $orderData->field_height : 0,
                        "status" => 1,
                        "order" => $_order,
                        "order_mobile" => $_order_mobile,
                    );

                    $record = $wpdb->get_row($wpdb->prepare("SELECT T.id FROM {$table} AS T WHERE T.field_name = %s;", $field["field_name"]));

                    if (!$record) {
                        $field["updated"] = $created;
                        $wpdb->insert($table, $field);
                        $position = $position == "product-middle-right" ? "product-middle-left" : "product-middle-right";
                    }
                } catch (\Throwable $th) {
                }
            }
        }
    }

    public static function setupTableHistory()
    {
        global $wpdb;

        $table = $wpdb->prefix . self::$history;

        $sql = "CREATE TABLE `{$table}` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) DEFAULT NULL,
            `post_id` bigint(20) DEFAULT NULL,
            `query` varchar(255) DEFAULT NULL,
            `counter` int(10) DEFAULT 1,
            `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated` datetime DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) CHARSET=UTF8MB4 COLLATE=utf8mb4_unicode_ci ENGINE=InnoDB";

        \dbDelta($sql);
    }

    public static function setupTablePostsList()
    {
        global $wpdb;

        $table = $wpdb->prefix . self::$postsList;

        $sql = "CREATE TABLE `{$table}` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) DEFAULT NULL,
            `post_id` bigint(20) DEFAULT NULL,
            `counter` int(11) DEFAULT 0,
            `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated` datetime DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) CHARSET=UTF8MB4 COLLATE=utf8mb4_unicode_ci ENGINE=InnoDB";

        \dbDelta($sql);
    }

    public static function setupTableCart()
    {
        global $wpdb;

        $table = $wpdb->prefix . self::$cart;

        $sql = "CREATE TABLE `{$table}` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) DEFAULT NULL,
            `product_id` bigint(20) DEFAULT NULL,
            `variation_id` bigint(20) DEFAULT NULL,
            `price` varchar(255) DEFAULT NULL,
            `custom_price` varchar(255) DEFAULT NULL,
            `quantity` DECIMAL(20,4) DEFAULT 1,
            `quantity_step` DECIMAL(20,4) DEFAULT 1,
            `attributes` LONGTEXT DEFAULT NULL,
            `meta` LONGTEXT DEFAULT NULL,
            `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated` datetime DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) CHARSET=UTF8MB4 COLLATE=utf8mb4_unicode_ci ENGINE=InnoDB";

        \dbDelta($sql);

        $table = $wpdb->prefix . self::$cartData;

        $sql = "CREATE TABLE `{$table}` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) DEFAULT NULL,
            `param` varchar(255) DEFAULT NULL,
            `value` varchar(255) DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) CHARSET=UTF8MB4 COLLATE=utf8mb4_unicode_ci ENGINE=InnoDB";

        \dbDelta($sql);
    }

    public static function addPostColumn($name)
    {
        global $wpdb;

        $result = array("row" => null, "isNew" => false);
        $tablePosts = $wpdb->prefix . self::$posts;
        $tableColumns = $wpdb->prefix . self::$columns;

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tableColumns} AS C WHERE C.name = %s;", $name));

        $columnsMaxId = $wpdb->get_row("SELECT MAX(id) AS maxId FROM {$tableColumns} LIMIT 1;");
        $maxId = 1;

        if ($columnsMaxId && $columnsMaxId->maxId) {
            $maxId = $columnsMaxId->maxId + 1;
        }

        if (!$row && !key_exists($name, self::$postsFields)) {
            $alterTable = null;

            try {
                $alterTable = "ALTER TABLE `{$tablePosts}` ADD `column_{$maxId}` longtext DEFAULT NULL; ";
                $alterTable = $wpdb->query($alterTable);
            } catch (\Throwable $th) {
            }

            if ($alterTable) {
                $wpdb->insert($tableColumns, array("name" => $name, "column" => "column_{$maxId}", "table" => "postmeta",), array('%s', '%s', '%s'));

                $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tableColumns} AS C WHERE C.id = %d;", $wpdb->insert_id));
                $result["isNew"] = true;

                $settings = new Settings();
                $settings->updateField("indexing", "indexed", false);
            }
        }

        $result["row"] = $row;

        return $result;
    }

    public static function escapeColumnName($name)
    {
        return preg_replace("/[^A-Za-z0-9]/", '_', $name);
    }

    public static function updatePostsTable($offset = 0, $limit = 0, $isFast = false, $isCheck = false)
    {
        global $wpdb;

        if (!$limit) {
            $limit = 50;
        }

        $settings = new Settings();
        $tablePosts = $wpdb->prefix . self::$posts;

        $debugInfo = $settings->getSettings("debugInfo");
        $debugInfo = $debugInfo !== null ? $debugInfo->value : "";
        $debugInfoStatus = $debugInfo === "on";

        if ($debugInfoStatus) Debug::addPoint("UpdatePosts->start");

        if (HPOS::getStatus()) {
            $types = array('product', 'product_variation', 'shop_order', 'shop_order_placehold');
        } else {
            $types = array('product', 'product_variation', 'shop_order');
        }

        $productsIndexation = $settings->getSettings("productsIndexation");
        $productsIndexation = $productsIndexation === null ? 'on' : $productsIndexation->value;

        $ordersIndexation = $settings->getSettings("ordersIndexation");
        $ordersIndexation = $ordersIndexation === null ? 'on' : $ordersIndexation->value;

        if ($productsIndexation != 'on') {
            $types = array_diff($types, array('product', 'product_variation'));
        }

        if ($ordersIndexation != 'on') {
            if (HPOS::getStatus()) {
                $types = array_diff($types, array('shop_order', 'shop_order_placehold'));
            } else {
                $types = array_diff($types, array('shop_order'));
            }
        }

        if (empty($types)) {
            $types[] = 'us_empty_records';
        }

        $sql = " SELECT P.ID, P.post_title, P.post_excerpt, P.post_type, P.post_status, P.post_parent, P.post_author, P.post_date FROM {$wpdb->posts} AS P ";
        $sqlCount = " SELECT COUNT(P.ID) AS 'count' FROM {$wpdb->posts} AS P ";
        $where = " WHERE P.post_type IN('" . implode("','", $types) . "') ";
        $order = " ORDER BY P.ID DESC ";
        $sqlLimit = " LIMIT {$limit} OFFSET {$offset} ";

        if ($isFast) {
            $offset = 0;
            $tablePosts = $wpdb->prefix . self::$posts;

            $dateCompare = "P.post_modified_gmt < _SP.updated";
            $dateCompare = apply_filters('scanner_search_indexation_date_compare', $dateCompare);

            $where .= " AND P.ID NOT IN(SELECT _SP.post_id FROM {$tablePosts} AS _SP WHERE _SP.post_id = P.ID AND {$dateCompare}) ";
        }

        $posts = (object) array("posts" => array(), "found_posts" => 0);

        if (HPOS::getStatus()) {
            $hposOrdersTable = "{$wpdb->prefix}wc_orders";
            $excludeOrderStatuses = SettingsHelper::$excludeOrderStatuses;

            if ($excludeOrderStatuses) {
                $excludeOrderStatuses = implode("','", $excludeOrderStatuses);
                $where .= " AND ( (SELECT _O.status FROM {$hposOrdersTable} AS _O WHERE _O.id = P.ID ) NOT IN ('{$excludeOrderStatuses}') ";
                $where .= " OR (SELECT _O.status FROM {$hposOrdersTable} AS _O WHERE _O.id = P.ID ) IS NULL ) ";
            }
        }

        $posts->posts = $wpdb->get_results($sql . $where . $order . $sqlLimit);
        $count = $wpdb->get_row($sqlCount . $where);

        $posts->found_posts = $count ? (int)$count->count : 0;
        $total = $posts->found_posts;

        if ($debugInfoStatus) Debug::addPoint("UpdatePosts->after WP_Query");

        $newIds = array();
        foreach ($posts->posts as $post) {
            $newIds[] = $post->ID;
        }

        $tableColumns = $wpdb->prefix . self::$columns;
        $additionalColumns = array();

        if ($total) {
            $additionalColumns = $wpdb->get_results("SELECT C.name, C.column FROM {$tableColumns} AS C;", ARRAY_A);
        }

        if (!$isCheck) {
            foreach ($posts->posts as $post) {
                self::updatePost($post->ID, $additionalColumns, $post, null, "updatePostsTable");
            }

            Debug::addPoint("UpdatePosts->after updating");

            if ($offset + $limit >= $total) {
                $settings->updateField("indexing", "indexed", true);
            } else if (!$isFast) {
                $settings->updateField("indexing", "indexed", false);
            }
        }

        $result = array(
            "total" => $total,
            "found" => count($posts->posts),
            "offset" => $isCheck ? 0 : $offset + $limit,
            "limit" => $limit,
            "posts" => $posts,
        );

        if ($debugInfoStatus) {
            $result['debug'] = Debug::getResult($debugInfoStatus);
        }

        return $result;
    }

    public static function updatePost($id, $additionalColumns = array(), $post = null, $indexedRecord = null, $trigger = "")
    {
        global $wpdb;

        if (in_array($id, self::$indexedPostIds)) {
            return;
        } else {
            self::$indexedPostIds[] = $id;
        }

        Debug::addPoint("> updatePost " . $id);

        $wpdb->show_errors(true);

        $post = $post ? $post : get_post($id);
        $tablePosts = $wpdb->prefix . self::$posts;
        $tableColumns = $wpdb->prefix . self::$columns;
        $prefix = self::$postMetaFieldPrefix;
        $isUpdated = false;
        $types = array("product", "product_variation", "shop_order");

        if ($trigger !== 'updatePostsTable') {
            $settings = new Settings();

            $productsIndexation = $settings->getSettings("productsIndexation");
            $productsIndexation = $productsIndexation === null ? 'on' : $productsIndexation->value;

            $ordersIndexation = $settings->getSettings("ordersIndexation");
            $ordersIndexation = $ordersIndexation === null ? 'on' : $ordersIndexation->value;

            if ($productsIndexation != 'on') {
                $types = array_diff($types, array('product', 'product_variation'));
            }

            if ($ordersIndexation != 'on') {
                $types = array_diff($types, array('shop_order', 'shop_order_placehold'));
            }
        }

        $isHPOSorder = false;

        if (HPOS::getStatus()) {
            if ($post && in_array($post->post_type, array('shop_order', 'shop_order_placehold'))) {
                $isHPOSorder = true;
            } else if (!$post) {
                $isHPOSorder = true;
            }
        }

        if ($post && in_array($post->post_type, $types) && !$isHPOSorder) {
            $isUpdated = true;
            $hwp_product_gtin = get_post_meta($id, "hwp_product_gtin", true);
            $hwp_var_gtin = get_post_meta($id, "hwp_var_gtin", true);

            $atum = self::getAtumInventoryManagementFieldValue($id);

            $clientName = get_post_meta($id, "_billing_first_name", true);
            $clientName .= " " . get_post_meta($id, "_billing_last_name", true);

            $parent = $post->post_parent ? get_post($post->post_parent) : null;

            $wcShipmentTrackingItems = get_post_meta($id, "_wc_shipment_tracking_items", true);
            $_wc_shipment_tracking_items = "";

            if ($wcShipmentTrackingItems && is_array($wcShipmentTrackingItems)) {
                foreach ($wcShipmentTrackingItems as $value) {
                    if (isset($value["tracking_number"])) $_wc_shipment_tracking_items .= " " . $value["tracking_number"];
                }
            }

            $aftershipTrackingItems = get_post_meta($id, "_aftership_tracking_items", true);
            $_aftership_tracking_items = "";

            if ($aftershipTrackingItems && is_array($aftershipTrackingItems)) {
                foreach ($aftershipTrackingItems as $value) {
                    if (isset($value["tracking_number"])) $_aftership_tracking_items .= " " . $value["tracking_number"];
                }
            }

            $_sku = get_post_meta($id, "_sku", true);
            $_variation_description = get_post_meta($id, "_variation_description", true);
            $_customer_user = get_post_meta($id, "_customer_user", true);
            $_alg_ean = get_post_meta($id, "_alg_ean", true);
            $_wpm_gtin_code = get_post_meta($id, "_wpm_gtin_code", true);
            $_ywbc_barcode_display_value = get_post_meta($id, "_ywbc_barcode_display_value", true);
            $_wepos_barcode = get_post_meta($id, "_wepos_barcode", true);
            $_ts_gtin = get_post_meta($id, "_ts_gtin", true);
            $_ts_mpn = get_post_meta($id, "_ts_mpn", true);
            $_zettle_barcode = get_post_meta($id, "_zettle_barcode", true);
            $_order_number = get_post_meta($id, "_order_number", true);
            $_billing_address_index = get_post_meta($id, "_billing_address_index", true);
            $_shipping_address_index = get_post_meta($id, "_shipping_address_index", true);
            $ywot_tracking_code = get_post_meta($id, "ywot_tracking_code", true);
            $usbs_barcode_field = get_post_meta($id, "usbs_barcode_field", true);
            $_billing_email = get_post_meta($id, "_billing_email", true);
            $post_title = htmlspecialchars_decode($post->post_title);

            $data = array(
                'post_title' => strip_tags($post_title),
                'post_excerpt' => self::removeEmoji($post->post_excerpt),
                'post_type' => $post->post_type,
                'post_status' => $post->post_status,
                'post_parent_status' => $parent ? $parent->post_status : null,
                'post_parent' => $post->post_parent,
                'post_author' => $post->post_author,
                'post_date' => $post->post_date,
                "{$prefix}_sku" => $_sku ? trim($_sku) : $_sku,
                "{$prefix}_variation_description" => $_variation_description ? trim($_variation_description) : $_variation_description,
                "{$prefix}_customer_user" => $_customer_user ? trim($_customer_user) : $_customer_user,
                "{$prefix}_alg_ean" => $_alg_ean ? trim($_alg_ean) : $_alg_ean,
                "{$prefix}_wpm_gtin_code" => $_wpm_gtin_code ? trim($_wpm_gtin_code) : $_wpm_gtin_code,
                "{$prefix}hwp_product_gtin" => $hwp_var_gtin ? trim($hwp_var_gtin) : $hwp_product_gtin,
                "{$prefix}_ywbc_barcode_display_value" => $_ywbc_barcode_display_value ? trim($_ywbc_barcode_display_value) : $_ywbc_barcode_display_value,
                "{$prefix}_wepos_barcode" => $_wepos_barcode ? trim($_wepos_barcode) : $_wepos_barcode,
                "{$prefix}_ts_gtin" => $_ts_gtin ? trim($_ts_gtin) : $_ts_gtin,
                "{$prefix}_ts_mpn" => $_ts_mpn ? trim($_ts_mpn) : $_ts_mpn,
                "{$prefix}_zettle_barcode" => $_zettle_barcode ? trim($_zettle_barcode) : $_zettle_barcode,
                "{$prefix}_order_number" => $_order_number ? trim($_order_number) : $_order_number,
                "{$prefix}_billing_address_index" => $_billing_address_index ? trim($_billing_address_index) : $_billing_address_index,
                "{$prefix}_shipping_address_index" => $_shipping_address_index ? trim($_shipping_address_index) : $_shipping_address_index,
                "{$prefix}_wc_shipment_tracking_items" => trim($_wc_shipment_tracking_items),
                "{$prefix}_aftership_tracking_items" => trim($_aftership_tracking_items),
                "{$prefix}ywot_tracking_code" => $ywot_tracking_code ? trim($ywot_tracking_code) : $ywot_tracking_code,
                "usbs_barcode_field" => $usbs_barcode_field ? trim($usbs_barcode_field) : $usbs_barcode_field,
                "atum_supplier_sku" => $atum["atum_supplier_sku"],
                "atum_barcode" => $atum["atum_barcode"],
                "atum_supplier_id" => $atum["atum_supplier_id"],
                "client_name" => $clientName ? trim($clientName) : $clientName,
                "client_email" => $_billing_email ? trim($_billing_email) : $_billing_email,
                "successful_update" => 1,
            );

            if (!$additionalColumns) {
                $additionalColumns = $wpdb->get_results("SELECT C.name, C.column FROM {$tableColumns} AS C;", ARRAY_A);
            }

            foreach ($additionalColumns as $value) {
                $column_value = get_post_meta($id, $value["name"], true);
                $data["{$value["column"]}"] = $column_value ? trim($column_value) : $column_value;
            }

            $wpdb->update($tablePosts, array("post_parent_status" => $data["post_status"]), array("post_parent" => $id));
        }
        else if (HPOS::getStatus()) {
            $order = null;

            try {
                $order = new \WC_Order($id);
            } catch (\Throwable $th) {
                $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$tablePosts} (`post_id`, `successful_update`, `updated`) VALUES (%s, %s, %s);", $id, 0, date("Y-m-d H:i:s", time() + 2)));
            }

            if ($order) {
                $isUpdated = true;

                $atum = self::getAtumInventoryManagementFieldValue($id);

                $wcShipmentTrackingItems = $order->get_meta("_wc_shipment_tracking_items", true);
                $_wc_shipment_tracking_items = "";

                if ($wcShipmentTrackingItems && is_array($wcShipmentTrackingItems)) {
                    foreach ($wcShipmentTrackingItems as $value) {
                        if (isset($value["tracking_number"])) $_wc_shipment_tracking_items .= " " . $value["tracking_number"];
                    }
                }

                $aftershipTrackingItems = $order->get_meta("_aftership_tracking_items", true);
                $_aftership_tracking_items = "";

                if ($aftershipTrackingItems && is_array($aftershipTrackingItems)) {
                    foreach ($aftershipTrackingItems as $value) {
                        if (isset($value["tracking_number"])) $_aftership_tracking_items .= " " . $value["tracking_number"];
                    }
                }

                $data = array(
                    'post_title' => $post->post_title,
                    'post_excerpt' => self::removeEmoji($post->post_excerpt),
                    'post_type' => "shop_order",
                    'post_status' => $order->get_status(),
                    'post_parent' => $order->get_parent_id(),
                    'post_author' => $post->post_author,
                    'post_date' => $post->post_date,
                    "{$prefix}_order_number" => $order->get_meta("_order_number", true),
                    "{$prefix}_billing_address_index" => str_replace("<br/>", ", ", $order->get_formatted_billing_address()),
                    "{$prefix}_shipping_address_index" => str_replace("<br/>", ", ", $order->get_formatted_shipping_address()),
                    "{$prefix}_wc_shipment_tracking_items" => trim($_wc_shipment_tracking_items),
                    "{$prefix}_aftership_tracking_items" => trim($_aftership_tracking_items),
                    "{$prefix}ywot_tracking_code" => $order->get_meta("ywot_tracking_code", true),
                    "atum_supplier_sku" => $atum["atum_supplier_sku"],
                    "atum_barcode" => $atum["atum_barcode"],
                    "atum_supplier_id" => $atum["atum_supplier_id"],
                    "client_name" => $order->get_formatted_billing_full_name(),
                    "client_email" => $order->get_billing_email(),
                    "successful_update" => 1,
                );

                if (!$additionalColumns) {
                    $additionalColumns = $wpdb->get_results("SELECT C.name, C.column FROM {$tableColumns} AS C;", ARRAY_A);
                }

                foreach ($additionalColumns as $value) {
                    $column_value = $order->get_meta($value["name"], true);
                    $data["{$value["column"]}"] = $column_value ? trim($column_value) : $column_value;
                }
            }
        }

        if ($isUpdated) {
            try {
                $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$tablePosts} (`post_id`, `successful_update`, `updated`) VALUES (%s, %s, %s);", $id, 0, date("Y-m-d H:i:s", time() + 2)));

            } catch (\Throwable $th) {
            }

            try {
                $data["updated"] = date("Y-m-d H:i:s", time() + 2);
                $updated = $wpdb->update($tablePosts, $data, array("post_id" => $id));
                Debug::addPoint("UpdatePosts->update = " . json_encode($data));

                if ($trigger == "pageIndexedData" && $wpdb->last_error) {
                    var_dump($wpdb->last_error);
                }

                if ($updated == 0 && $wpdb->last_error) {
                    $wpdb->update($tablePosts, array("successful_update" => 0, "updated" => date("Y-m-d H:i:s", time() + 2)), array("post_id" => $id));
                    Debug::addPoint("UpdatePosts->update error: " . $updated . " = " . ($wpdb->last_error));
                }
            } catch (\Throwable $th) {
            }

            if (self::$isTriggerTracked === null) {
                self::$isTriggerTracked = \get_option("usbs_index_triggers_counting", "") === "on";
            }

            if (self::$isTriggerTracked === true) {
                self::countIndexItem($trigger);
            }
        } else {
            $wpdb->update($tablePosts, array("successful_update" => 0, "updated" => date("Y-m-d H:i:s", time() + 2)), array("post_id" => $id));
            Debug::addPoint("UpdatePosts->update error: " . $id . " cant update");
        }

        Debug::addPoint("> updatePost indexed");

        self::checkOrderFulfillment($id);
        Debug::addPoint("> updatePost checkOrderFulfillment " . $id);

        $wpdb->show_errors(false);
    }

    private static function checkOrderFulfillment($orderId)
    {
        try {
            if (self::$managementActions == null) {
                self::$managementActions = new ManagementActions();
            }
            $empty = null;
            $infoData = self::$managementActions->getFulfillmentOrderData($orderId, false);

            if ($infoData) {
                update_post_meta($orderId, "usbs_order_fulfillment_data", $infoData);
            }
        } catch (\Throwable $th) {
        }
    }

    public static function countIndexItem($trigger)
    {
        $counter = \get_option("usbs_iic_" . $trigger, 0);
        \update_option("usbs_iic_" . $trigger, ++$counter);
    }

    public static function removeEmoji($string)
    {
        $regex_emoticons = '/[\x{1F600}-\x{1F64F}]/u';
        $clear_string = preg_replace($regex_emoticons, '', $string);

        $regex_symbols = '/[\x{1F300}-\x{1F5FF}]/u';
        $clear_string = preg_replace($regex_symbols, '', $clear_string);

        $regex_transport = '/[\x{1F680}-\x{1F6FF}]/u';
        $clear_string = preg_replace($regex_transport, '', $clear_string);

        $regex_misc = '/[\x{2600}-\x{26FF}]/u';
        $clear_string = preg_replace($regex_misc, '', $clear_string);

        $regex_dingbats = '/[\x{2700}-\x{27BF}]/u';
        $clear_string = preg_replace($regex_dingbats, '', $clear_string);

        $clear_string = preg_replace('/[\x00-\x1F\x7F]/u', '', $clear_string);
        $clear_string = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $clear_string);
        $clear_string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F]/u', '', $clear_string);

        try {
            $clear_string = filter_var($clear_string, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
        } catch (\Throwable $th) {
        }

        return $clear_string;
    }

    private static function getAtumInventoryManagementFieldValue($id)
    {
        $fields = array(
            "atum_supplier_sku" => '',
            "atum_barcode" => '',
            "atum_supplier_id" => ''
        );

        if (!is_plugin_active('atum-stock-manager-for-woocommerce/atum-stock-manager-for-woocommerce.php')) {
            return $fields;
        }

        try {
            $product = Helpers::get_atum_product($id);

            if ($product) {
                $fields['atum_barcode'] = $product->get_barcode();
                $fields['atum_supplier_sku'] = $product->get_supplier_sku();
                $fields['atum_supplier_id'] = $product->get_supplier_id();
            }
        } catch (\Throwable $th) {
        }

        return $fields;
    }
}
