<?php

namespace UkrSolution\BarcodeScanner\features\interfaceData;

use UkrSolution\BarcodeScanner\API\PluginsHelper;
use UkrSolution\BarcodeScanner\Database;

class InterfaceData
{
    public static $filter_dropdown_options = 'scanner_dropdown_%field_options';

    private static $allFields = array();
    private static $plugin = "";

    public static function getFields($addTranslations = false, $plugin = "", $isReload = false, $role = null, $defaultIsEmpty = true)
    {
        global $wpdb;

        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : '';
        $subTab = isset($_GET['sub']) ? sanitize_text_field($_GET['sub']) : '';
        $role = "";
        $isRemoveFields = '';
        $isLoadDefault = '';

        $table = $wpdb->prefix . Database::$interface;

        if (!isset(self::$allFields[$role])) self::$allFields[$role] = array();


        $orderKey = $plugin == "mobile" ? "order_mobile" : "order";

        if (self::$allFields[$role] && self::$plugin == $plugin && !$isReload) {
            return self::$allFields[$role];
        }

        if ($plugin == "mobile") {
            $orderField = "order_mobile";
        } else {
            $orderField = "order";
        }

        if (!$role || $role == 'default') {
            $fields = $wpdb->get_results("SELECT * FROM {$table} WHERE `role` IS NUll ORDER BY `" . $orderField . "` DESC;", ARRAY_A);
        } else {
            $fields = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE `role` = %s ORDER BY %s DESC;",
                $role,
                $orderField
            ), ARRAY_A);
        }


        if (!$fields && $defaultIsEmpty && $role && $role != 'default') {
            return self::getFields($addTranslations, $plugin, $isReload, "default", false);
        }

        foreach ($fields as &$value) {
            if ($addTranslations && isset($value["field_label"])) {
                $value["field_label"] = __($value["field_label"], 'us-barcode-scanner');
            }

            if ($value["type"] != "select") {
                continue;
            }

            $options = $value["options"] ? @json_decode($value["options"], false) : array();

            $filterName = str_replace("%field", $value["field_name"], self::$filter_dropdown_options);
            $filteredOptions = apply_filters($filterName, (array)$options, $value["field_name"]);
            $value["options"] = json_encode($filteredOptions);
        }

        self::$allFields[$role] = $fields ? $fields : array();
        self::$plugin = $plugin;

        usort(self::$allFields[$role], function ($a, $b) use ($orderKey) {
            return $a[$orderKey] && $b[$orderKey] && $a[$orderKey] < $b[$orderKey] ? 1 : 0;
        });

        return self::$allFields[$role];
    }

    public static function getField($fieldName)
    {
        global $wpdb;

        $table = $wpdb->prefix . Database::$interface;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE `field_name` = '%s';", $fieldName), ARRAY_A);
    }

    public static function saveFields($fields, $role)
    {
        global $wpdb;

        $table = $wpdb->prefix . Database::$interface;
        $dt = new \DateTime("now");
        $created = $dt->format("Y-m-d H:i:s");

        foreach ($fields as $id => $value) {
            $data = array(
                "field_label" => $value["field_label"],
                "field_name" => $value["field_name"],
                "type" => $value["type"],
                "label_position" => $value["label_position"],
                "field_height" => $value["field_height"],
                "label_width" => $value["label_width"],
                "show_in_create_order" => $value["show_in_create_order"],
                "show_in_products_list" => $value["show_in_products_list"],
                "disabled_field" => $value["disabled_field"],
                "role" => !$role || $role == 'default'  ? null : $role,
                "updated" => $created,
                "attribute_id" => $value["attribute_id"] ? $value["attribute_id"] : null,
            );

            if (isset($value['status'])) $data['status'] = $value["status"];
            if (isset($value['mobile_status'])) $data['mobile_status'] = $value["mobile_status"];

            $options = array();

            if (isset($value["options"]) && is_array($value["options"])) {
                foreach ($value["options"] as $option) {
                    $options[$option["key"]] = $option["value"];
                }

                $data["options"] = json_encode($options);
            }


            if (isset($value["order_mobile"])) {
                $data["order_mobile"] = $value["order_mobile"];
            } else {
                $data["position"] = $value["position"];
                $data["order"] = $value["order"];
            }

            if ($value["remove"] == 1) {
                $wpdb->delete($table, array("id" => $id));
            } else if (preg_match("/^[0-9]+$/", $id, $m)) {
                $wpdb->update($table, $data, array("id" => $id));
            } else {
                if (!isset($data["position"])) {
                    $data["position"] = "product-middle-left";
                }

                $wpdb->insert($table, $data);
            }
        }

        self::generateFieldsTranslationsFile($fields);
    }

    public static function getFieldForAutoAction()
    {
        global $wpdb;

        $table = $wpdb->prefix . Database::$interface;
        return $wpdb->get_row("SELECT * FROM {$table} WHERE `use_for_auto_action` = '1';");
    }

    public static function generateFieldsTranslationsFile($fields)
    {
        try {
            $filePath = USBS_PLUGIN_BASE_PATH . 'texts-for-translator-plugins.php';
            $directoryPath = dirname($filePath);

            if (is_writable($directoryPath)) {
                if (file_exists($filePath)) {
                    unlink($filePath);
                }

                $file = fopen($filePath, 'a');

                if (file_exists($filePath)) { 
                    fwrite($file, "<?php \n\n");

                    foreach ($fields as $value) {
                        $str = (isset($value["field_label"]) && $value["field_label"]) ? $value["field_label"] : "";

                        if (isset($value["field_label"]) && $value["field_label"]) {
                            $label = addslashes($value["field_label"]);
                            fwrite($file, "__('{$label}', 'us-barcode-scanner');\n");
                        }
                    }

                    fclose($file);
                }
            }
        } catch (\Throwable $th) {
        }
    }

    public static function getUserFields()
    {
        $result = array();

        $result = apply_filters('barcode_scanner_user_fields', $result);

        return $result;
    }

    public static function getOrderFields()
    {
        $result = array();

        $result = apply_filters('barcode_scanner_add_order_custom_fields', $result);

        return $result;
    }

    public static function getWooShippmentProviders()
    {
        global $wpdb;

        try {
            if (PluginsHelper::is_plugin_active('woo-advanced-shipment-tracking/woocommerce-advanced-shipment-tracking.php')) {
                $WC_Countries = new \WC_Countries();
                $countries = $WC_Countries->get_countries();


                $providers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}woo_shippment_provider;");

                if ($providers) {
                    foreach ($providers as &$value) {
                        $value->shipping_country_name = $value->shipping_country && isset($countries[$value->shipping_country]) ? $countries[$value->shipping_country] : "";
                    }
                }

                return $providers;
            } else {
                return array();
            }
        } catch (\Throwable $th) {
            return array();
        }
    }

    static function getCustomFieldsToExportImport()
    {
        $fields = self::getFields();

        $excludesList = array('usbs_categories', 'usbs_product_status', '_stock_status', '_sale_price', '_regular_price', '_sku', '_stock', 'usbs_variation_attributes');
        $fieldsToExport = array();

        if ($fields) {
            foreach ($fields as $field) {
                if ($field['status'] && $field['field_name'] && $field['position'] != 'product-middle-bottom' && !in_array($field['field_name'], $excludesList)) {
                    $fieldsToExport[$field['field_name']] = $field['field_label'];
                }
            }
        }

        return $fieldsToExport;
    }
}
