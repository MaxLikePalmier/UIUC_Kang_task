<?php

namespace UkrSolution\BarcodeScanner\features\import;

use UkrSolution\BarcodeScanner\features\interfaceData\InterfaceData;
use UkrSolution\BarcodeScanner\features\settings\Settings;

class Import
{
    private $fieldsToImport = array();

    function __construct()
    {
        add_filter('woocommerce_csv_product_import_mapping_options', array($this, 'woocommerce_csv_product_import_mapping_options'));
        add_filter('woocommerce_csv_product_import_mapping_default_columns', array($this, 'woocommerce_csv_product_import_mapping_default_columns'));
        add_filter('woocommerce_product_import_inserted_product_object', array($this, 'woocommerce_product_import_inserted_product_object'), 10, 2);
    }

    function woocommerce_csv_product_import_mapping_options($columns)
    {
        $settings = new Settings();
        $label = $settings->getSettings("searchCFLabel");
        $label = $label ? $label->value : $settings->getField("general", "searchCFLabel", "Barcode");
        $columns['usbs_barcode_field'] = $label;

        if (!$this->fieldsToImport) {
            $this->fieldsToImport = InterfaceData::getCustomFieldsToExportImport();
        }

        if ($this->fieldsToImport) {
            foreach ($this->fieldsToImport as $key => $value) {
                if (!in_array($key, $columns)) {
                    $columns[$key] = $value;
                }
            }
        }

        return $columns;
    }

    function woocommerce_csv_product_import_mapping_default_columns($columns)
    {
        $settings = new Settings();
        $label = $settings->getSettings("searchCFLabel");
        $label = $label ? $label->value : $settings->getField("general", "searchCFLabel", "Barcode");
        $columns[$label] = 'usbs_barcode_field';

        if (!$this->fieldsToImport) {
            $this->fieldsToImport = InterfaceData::getCustomFieldsToExportImport();
        }

        if ($this->fieldsToImport) {
            foreach ($this->fieldsToImport as $key => $value) {
                if (!in_array($key, $columns)) {
                    $columns[$value] = $key;
                }
            }
        }

        return $columns;
    }

    function woocommerce_product_import_inserted_product_object($object, $data)
    {
        if ($data && isset($data["usbs_barcode_field"])) {
            update_post_meta($object->get_id(), "usbs_barcode_field", $data["usbs_barcode_field"]);
        }

        if (!$this->fieldsToImport) {
            $this->fieldsToImport = InterfaceData::getCustomFieldsToExportImport();
        }

        if ($data && $this->fieldsToImport) {
            foreach ($this->fieldsToImport as $key => $value) {
                if (isset($data[$key])) {
                    update_post_meta($object->get_id(), $key, $data[$key]);
                }
            }
        }
    }
}
