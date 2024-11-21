<?php

namespace UkrSolution\BarcodeScanner\API\classes;

use UkrSolution\BarcodeScanner\API\PluginsHelper;
use UkrSolution\BarcodeScanner\features\settings\Settings;

class SearchFilter
{
    private static $defaultFilter = array(
        "products" => array(
            "ID" => "1",
            "customStatus" => "1",
            "post_title" => "2",
            "_sku" => "1",
            "custom" => "",
            "_variation_description" => "0",
            "_alg_ean" => "1",
            "_wpm_gtin_code" => "1",
            "hwp_product_gtin" => "1",
            "_ywbc_barcode_display_value" => "1",
            "hwp_var_gtin" => "1",
            "_wepos_barcode" => "1",
            "_ts_gtin" => "1",
            "_ts_mpn" => "1",
            "_zettle_barcode" => "1",
            "usbs_barcode_field" => "1",
            "prod_link" => "1",
            "post_excerpt" => "0",
            "atum_supplier_sku" => "1",
            "atum_barcode" => "1",
        ),
        "orders" => array(
            "ID" => "1",
            "customStatus" => "1",
            "client_name" => "1",
            "client_email" => "1",
            "custom" => "",
            "_order_number" => "1",
            "_billing_address_index" => "2",
            "_shipping_address_index" => "2",
            "ywot_tracking_code" => "2",
            "_wc_shipment_tracking_items" => "2",
            "_aftership_tracking_items" => "2",
            "post_excerpt" => "0",
            "post_author" => "0",
        ),
        "wpml" => array()
    );

    static public function get($userId = null)
    {
        try {
            $settings = new Settings();
            $filterRecord = $settings->getSettings("search_filter", true);

            if ($filterRecord) {
                $filter = (array)$filterRecord->value;

                if ($filter && isset($filter["products"])) $filter["products"] = (array)$filter["products"];
                if ($filter && isset($filter["orders"])) $filter["orders"] = (array)$filter["orders"];
            } else {
                $filter = self::$defaultFilter;
                $langs = WPML::getTranslations();

                if ($langs) {
                    foreach ($langs as $key => $value) {
                        $filter["wpml"][$key] = 1;
                    }
                }
            }

            $plugins = PluginsHelper::checkExternalPlugins();

            if (is_array($filter) && isset($filter['products'])) {
                $filter['products'] = array_merge(self::$defaultFilter["products"], $filter['products']);
            }
            if (is_array($filter) && isset($filter['orders'])) {
                $filter['orders'] = array_merge(self::$defaultFilter["orders"], $filter['orders']);
            }

            if (is_array($filter) && isset($filter['products'])) {
                foreach ($filter['products'] as $key => &$value) {
                    foreach ($plugins as $plugin) {
                        if (isset($plugin["key"]) && $plugin["key"] == $key && !$plugin["status"]) {
                            $value = 0;
                        }
                    }
                }
            }

            if (is_array($filter) && isset($filter['orders'])) {
                foreach ($filter['orders'] as $key => &$value) {
                    foreach ($plugins as $plugin) {
                        if (isset($plugin["key"]) && $plugin["key"] == $key && !$plugin["status"]) {
                            $value = 0;
                        }
                    }
                }
            }

            if ($userId) {
                $permissions = $settings->getUserRolePermissions($userId);

                if ($permissions && isset($permissions['onlymy']) && $permissions['onlymy'] == 1) {
                    $filter['orders']['post_author'] = $userId;
                }
            }

            return $filter;
        } catch (\Throwable $th) {
        }
    }
}
