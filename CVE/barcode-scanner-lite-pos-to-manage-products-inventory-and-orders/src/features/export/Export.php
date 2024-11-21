<?php

namespace UkrSolution\BarcodeScanner\features\export;

use UkrSolution\BarcodeScanner\features\interfaceData\InterfaceData;
use UkrSolution\BarcodeScanner\features\settings\Settings;

class Export
{
    function __construct()
    {
        add_filter('woocommerce_product_export_column_names', array($this, 'woocommerce_product_export_product_default_columns'));
        add_filter('woocommerce_product_export_product_default_columns', array($this, 'woocommerce_product_export_product_default_columns'));
        add_filter('woocommerce_product_export_product_column_usbs_barcode_field', array($this, 'woocommerce_product_export_product_column_usbs_barcode_field'), 10, 2);

        add_action('init', function () {
            $this->addCustomFieldsToExport();
        });
    }

    function woocommerce_product_export_product_default_columns($columns)
    {
        $settings = new Settings();
        $label = $settings->getSettings("searchCFLabel");
        $label = $label ? $label->value : $settings->getField("general", "searchCFLabel", "Barcode");
        $columns['usbs_barcode_field'] = $label;

        return $columns;
    }

    function woocommerce_product_export_product_column_usbs_barcode_field($value, $product)
    {
        $value = get_post_meta($product->get_id(), 'usbs_barcode_field', true);
        return $value;
    }

    private function addCustomFieldsToExport()
    {
        $fieldsToExport = InterfaceData::getCustomFieldsToExportImport();

        add_filter('woocommerce_product_export_product_default_columns', function ($columns) use ($fieldsToExport) {
            if ($fieldsToExport) {
                foreach ($fieldsToExport as $key => $value) {
                    if (!in_array($key, $columns)) {
                        $columns[$key] = $value;
                    }
                }
            }

            return $columns;
        });

        foreach ($fieldsToExport as $key => $value) {
            add_filter('woocommerce_product_export_product_column_' . $key, function ($value, $product) use ($key) {
                $value = $product->get_meta($key, true, 'edit');
                return $value;
            }, 10, 2);
        }
    }
}
