<?php

namespace UkrSolution\BarcodeScanner\API;

use UkrSolution\BarcodeScanner\API\classes\BatchNumbers;
use UkrSolution\BarcodeScanner\API\classes\BatchNumbersWebis;
use UkrSolution\BarcodeScanner\features\settings\Settings;

class PluginsHelper
{
    public static $postsForUpdate = array();

    public static function checkExternalPlugins()
    {
        $alg_wc_ean_title = get_option('alg_wc_ean_title', __('EAN', 'ean-for-woocommerce'));

        $wpm_pgw_label = get_option('wpm_pgw_label', __('EAN', 'product-gtin-ean-upc-isbn-for-woocommerce'));
        $wpm_pgw_label = sprintf(__('%s Code:', 'product-gtin-ean-upc-isbn-for-woocommerce'), $wpm_pgw_label);

        $hwp_gtin_text = get_option('hwp_gtin_text');
        $hwp_gtin_text = (!empty($hwp_gtin_text) ? $hwp_gtin_text : 'GTIN');

        $plugins = array(
            array('key' => 'us_print_labels', 'status' => class_exists('UkrSolution\ProductLabelsPrinting\Helpers\Variables'), 'label' => 'Barcode Printing'),
            "wc_appointments" => array('status' => self::is_plugin_active('woocommerce-appointments/woocommerce-appointments.php'), 'label' => 'WooCommerce Appointments'),
            array('key' => '_alg_ean', 'status' => function_exists('alg_wc_ean'), 'label' => 'EAN for WooCommerce', 'fieldLabel' => $alg_wc_ean_title . ' <sup>(EAN for WooCommerce)</sup>', 'title' => 'EAN for WooCommerce'),
            array('key' => '_wpm_gtin_code', 'status' => function_exists('wpm_product_gtin_wc'), 'label' => 'Product GTIN (EAN, UPC, ISBN) for WooCommerce', 'fieldLabel' => $wpm_pgw_label . ' <sup>(Product GTIN (EAN, UPC, ISBN) for WooCommerce)</sup>', 'title' => 'Product GTIN (EAN, UPC, ISBN)'),
            array('key' => 'hwp_product_gtin', 'status' => class_exists('Woo_GTIN'), 'label' => 'WooCommerce UPC, EAN, and ISBN', 'fieldLabel' => $hwp_gtin_text . ' <sup>(WooCommerce UPC, EAN, and ISBN)</sup>', 'title' => 'WooCommerce UPC, EAN, and ISBN'),
            array('key' => '_ywbc_barcode_display_value', 'status' => self::is_plugin_active('yith-woocommerce-barcodes-premium/init.php'), 'label' => 'YITH WooCommerce Barcodes and QR Codes', 'fieldLabel' => 'YITH WooCommerce Barcodes and QR Codes', 'title' => 'YITH WooCommerce Barcodes and QR Codes'),
            array('key' => '_wepos_barcode', 'status' => self::is_plugin_active('wepos/wepos.php'), 'label' => 'WePOS', 'fieldLabel' => 'Barcode <sup>(WePOS)</sup>', 'title' => 'WePOS'),
            array('key' => '_ts_gtin', 'status' => self::is_plugin_active('woocommerce-germanized/woocommerce-germanized.php'), 'label' => 'GTIN - Germanized for WooCommerce', 'fieldLabel' => 'GTIN <sup>(Germanized for WooCommerce)</sup>', 'title' => 'Germanized for WooCommerce'),
            array('key' => '_ts_mpn', 'status' => self::is_plugin_active('woocommerce-germanized/woocommerce-germanized.php'), 'label' => 'MPN - Germanized for WooCommerce', 'fieldLabel' => 'MPN <sup>(Germanized for WooCommerce)</sup>', 'title' => 'Germanized for WooCommerce'),

            array('key' => '_zettle_barcode', 'status' => self::is_plugin_active('zettle-pos-integration/zettle-pos-integration.php'), 'label' => 'PayPal Zettle', 'fieldLabel' => 'PayPal Zettle', 'title' => 'PayPal Zettle POS for WooCommerce'),
            array('key' => 'atum_supplier_sku', 'status' => is_plugin_active('atum-stock-manager-for-woocommerce/atum-stock-manager-for-woocommerce.php'), 'label' => 'ATUM SKU', 'fieldLabel' => 'ATUM SKU', 'title' => 'ATUM Inventory Management for WooCommerce'),
            array('key' => 'atum_barcode', 'status' => is_plugin_active('atum-stock-manager-for-woocommerce/atum-stock-manager-for-woocommerce.php'), 'label' => 'ATUM Barcode', 'fieldLabel' => 'ATUM Barcode', 'title' => 'ATUM Inventory Management for WooCommerce'),
            array('key' => 'atum_supplier_id', 'status' => is_plugin_active('atum-stock-manager-for-woocommerce/atum-stock-manager-for-woocommerce.php'), 'label' => 'ATUM Supplier', 'fieldLabel' => 'ATUM Supplier', 'title' => 'ATUM Inventory Management for WooCommerce'),
            array('key' => 'usbs_barcode_field', 'status' => 1, 'label' => '', 'title' => 'Field created by Barcode Scanner plugin'),
            array('key' => '_order_number', 'status' => defined('WT_SEQUENCIAL_ORDNUMBER_VERSION') || is_plugin_active("woocommerce-sequential-order-numbers-pro/woocommerce-sequential-order-numbers-pro.php"), 'label' => 'Sequential Order Number for WooCommerce', 'fieldLabel' => 'Sequential Order Number<sup>(for WooCommerce)</sup>', 'title' => 'Sequential Order Numbers for WooCommerce'),
            array('key' => '_billing_address_index', 'status' => true, 'label' => 'Billing address', 'fieldLabel' => 'Billing address'),
            array('key' => '_shipping_address_index', 'status' => true, 'label' => 'Shipping address', 'fieldLabel' => 'Shipping address'),
            array('key' => 'ywot_tracking_code', 'status' => self::is_plugin_active('yith-woocommerce-order-tracking/init.php'), 'label' => 'Order Tracking', 'fieldLabel' => 'Order Tracking', 'title' => 'YITH WooCommerce Order & Shipment Tracking'),
            array('key' => '_wc_shipment_tracking_items', 'status' => self::is_plugin_active('woo-advanced-shipment-tracking/woocommerce-advanced-shipment-tracking.php'), 'label' => 'Order Tracking', 'fieldLabel' => 'Order Tracking', 'title' => 'Advanced Shipment Tracking for WooCommerce'),
            array('key' => '_aftership_tracking_items', 'status' => self::is_plugin_active('aftership-woocommerce-tracking/aftership-woocommerce-tracking.php'), 'label' => 'Order Tracking', 'fieldLabel' => 'Order Tracking', 'title' => 'AfterShip Tracking – All-In-One WooCommerce Order Tracking'),
        );

        $settings = new Settings();

        $searchCF = $settings->getSettings("searchCF");
        $searchCF = $searchCF ? $searchCF->value : $settings->getField("general", "searchCF", "on");

        if ($searchCF == "on") {
            $label = $settings->getSettings("searchCFLabel");
            $label = $label ? $label->value : $settings->getField("general", "searchCFLabel", "Barcode");
            $plugins[] = array('key' => 'usbs_barcode_field', 'status' => 1, 'label' => $label, 'fieldLabel' => $label);
        }

        foreach ($plugins as &$plugin) {
            if (isset($plugin["fieldLabel"])) {
                $plugin["filter"] = 1;
            }
        }

        return $plugins;
    }

    public static function customPluginFields()
    {
        $settings = new Settings();

        $searchCF = $settings->getSettings("searchCF");
        $searchCF = $searchCF ? $searchCF->value : $settings->getField("general", "searchCF", "on");
        $usbs_barcode_field_label = $settings->getSettings("searchCFLabel");
        $usbs_barcode_field_label = $usbs_barcode_field_label ? $usbs_barcode_field_label->value : $settings->getField("general", "searchCFLabel", "Barcode");

        $alg_wc_ean_title = get_option('alg_wc_ean_title', __('EAN', 'ean-for-woocommerce'));

        $wpm_pgw_label = get_option('wpm_pgw_label', __('EAN', 'product-gtin-ean-upc-isbn-for-woocommerce'));
        $wpm_pgw_label = sprintf(__('%s Code:', 'product-gtin-ean-upc-isbn-for-woocommerce'), $wpm_pgw_label);

        $hwp_gtin_text = get_option('hwp_gtin_text');
        $hwp_gtin_text = (!empty($hwp_gtin_text) ? $hwp_gtin_text : 'GTIN');

        return array(
            "usbs_barcode_field" => array("status" => $searchCF == "on", "label" => $usbs_barcode_field_label),
            "_alg_ean" => array("status" => function_exists('alg_wc_ean'), "label" =>  $alg_wc_ean_title),
            "_wpm_gtin_code" => array("status" => function_exists('wpm_product_gtin_wc'), "label" => $wpm_pgw_label),
            "hwp_product_gtin" => array("status" => class_exists('Woo_GTIN'), "label" =>  $hwp_gtin_text),
            "_ywbc_barcode_display_value" => array("status" => self::is_plugin_active('yith-woocommerce-barcodes-premium/init.php'), "label" =>  'YITH Barcodes'),
            "_wepos_barcode" => array("status" => self::is_plugin_active('wepos/wepos.php'), "label" => "WePOS Code"),
            "_ts_gtin" => array("status" => self::is_plugin_active('woocommerce-germanized/woocommerce-germanized.php'), "label" => "GTIN"),
            "_ts_mpn" => array("status" => self::is_plugin_active('woocommerce-germanized/woocommerce-germanized.php'), "label" => "MPN"),
            "_zettle_barcode" => array("status" => self::is_plugin_active('zettle-pos-integration/zettle-pos-integration.php'), "label" => "PayPal Zettle"),
            "atum_supplier_sku" => array("status" => self::is_plugin_active('atum-stock-manager-for-woocommerce/atum-stock-manager-for-woocommerce.php'), "label" => "ATUM SKU"),
            "atum_supplier_id" => array("status" => self::is_plugin_active('atum-stock-manager-for-woocommerce/atum-stock-manager-for-woocommerce.php'), "label" => "ATUM Supplier", "type" => "select"),
            "atum_barcode" => array("status" => self::is_plugin_active('atum-stock-manager-for-woocommerce/atum-stock-manager-for-woocommerce.php'), "label" => "ATUM Barcode"),
            "atum_purchase_price" => array("status" => self::is_plugin_active('atum-stock-manager-for-woocommerce/atum-stock-manager-for-woocommerce.php'), "label" => "ATUM Purchase price", "type" => "price", "order" => 960),
            "woo_expiry_date" => array("status" => self::is_plugin_active('product-expiry-for-woocommerce/product-expiry-for-woocommerce.php'), "label" => "Product Expiry", "type" => "ExpiryDate"),
            "wholesale_multi_user_pricing" => array("status" => self::is_plugin_active('woocommerce-wholesale-pricing/woocommerce-wholesale-pricing.php'), "label" => "Wholesale Price", "type" => "price", "order" => 950),
            "usbs_wcpbn" => array("status" => BatchNumbers::status(), "label" => "Product Batch Numbers", "label_position" => "top", "position" => "product-middle-bottom", "type" => "usbs_wcpbn", "order" => 3000),
            "usbs_webis" => array("status" => BatchNumbersWebis::status(), "label" => "Product Batch & Tracking", "label_position" => "top", "position" => "product-middle-bottom", "type" => "usbs_webis", "order" => 3000),
        );
    }

    public static function is_plugin_active($plugin)
    {
        if (!function_exists('is_plugin_active')) {
            return key_exists($plugin, get_plugins());
        } else {
            return is_plugin_active($plugin);
        }
    }
}
