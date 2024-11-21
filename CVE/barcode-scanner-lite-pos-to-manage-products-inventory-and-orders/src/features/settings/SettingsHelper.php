<?php

namespace UkrSolution\BarcodeScanner\features\settings;

use UkrSolution\BarcodeScanner\Database;

class SettingsHelper
{
    private static $settingsFields = array();

    public static $excludeOrderStatuses = array("checkout-draft", "auto-draft");

    public static function init()
    {
    }

    public static function is_plugin_active($plugin)
    {
        if (!function_exists('is_plugin_active')) {
            return key_exists($plugin, get_plugins());
        } else {
            return is_plugin_active($plugin);
        }
    }

    public static function stripslashesDeep(&$value, $dq = true)
    {
        if (!$dq && is_string($value)) {
            $value = str_replace('"', "'", $value);
            $value = str_replace('\\', "", $value);
        } else if (!$dq && is_array($value)) {
            foreach ($value as $key => &$val) {
                if (!in_array($key, array("receipt-template"))) self::stripslashesDeep($val, $dq);
            }
        }

        return is_array($value) ? array_map('stripslashes_deep', $value) : stripslashes($value);
    }

    public static function generateRandomString($length = 10)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }

        return $randomString;
    }

    public static function getSettingsField($instant, $key = "", $asArray = false, $reSelectData = false)
    {
        global $wpdb;

        if (!self::$settingsFields || $reSelectData) {
            $table = $wpdb->prefix . Database::$settings;
            self::$settingsFields = $wpdb->get_results("SELECT * FROM {$table} AS S;");
        }

        $result = array();

        if ($key) {
            $result = null;

            foreach (self::$settingsFields as $value) {
                if ($value->field_name == $key) {
                    $result = $value;
                    break;
                }
            }
            if ($result && $result->value && $result->type === "json") {
                $jsonStr = $result->value;
                $key = $result->field_name;

                if (gettype($jsonStr) == "object") {
                    $result->value = $asArray ? (array)$jsonStr : $jsonStr;
                } else if (is_array($jsonStr)) {
                    $result->value = $asArray ? (array)$jsonStr : (object)$jsonStr;
                } else {
                    $result->value = $asArray ? @json_decode($jsonStr, true) : @json_decode($jsonStr, false);
                }
            }

            return $result;
        }
        else {
            $result = array();

            foreach (self::$settingsFields as $value) {
                if ($value->type === "json") {
                    $jsonStr = $value->value;
                    $value->value = $asArray ? @json_decode($jsonStr, true) : @json_decode($jsonStr, false);
                }

                $result[] = $value;
            }

            return $result;
        }
    }

    public static function restoreReceiptTemplate()
    {
        if (file_exists(USBS_PLUGIN_BASE_PATH . "default-receipt-template.txt")) {
            $settings = new Settings();
            $fileHandle = fopen(USBS_PLUGIN_BASE_PATH . "default-receipt-template.txt", 'r');

            if ($fileHandle) {
                $fileContents = fread($fileHandle, filesize(USBS_PLUGIN_BASE_PATH . "default-receipt-template.txt"));

                fclose($fileHandle);
                $settings->updateSettings("receipt-template", $fileContents, "text");
                $settings->updateSettings("receipt-width", 55, "text");
            }
        }
    }

    public static function getAjaxUrl()
    {
        return get_admin_url() . 'admin-ajax.php';
    }

    public static function getRequestUrl()
    {
        $ajaxUrl = self::getAjaxUrl();
        $requestUrl = plugin_dir_url(USBS_PLUGIN_BASE_PATH . 'request.php') . 'request.php';

        $ajaxUrlData = parse_url($ajaxUrl);
        $ajaxRequestUrlData = parse_url($requestUrl);

        return $ajaxRequestUrlData['scheme'] . '://' . $ajaxUrlData['host'] . $ajaxRequestUrlData['path'];
    }

    public static function getDownloadCsvUrl()
    {
        $ajaxUrl = self::getAjaxUrl();
        $requestUrl = plugin_dir_url(USBS_PLUGIN_BASE_PATH . 'download-csv.php') . 'download-csv.php';

        $ajaxUrlData = parse_url($ajaxUrl);
        $ajaxRequestUrlData = parse_url($requestUrl);

        return $ajaxRequestUrlData['scheme'] . '://' . $ajaxUrlData['host'] . $ajaxRequestUrlData['path'];
    }

    public static function dateTranslate($date)
    {
        $month = preg_replace('/([A-Za-z]+)\s.*/i', '$1', $date);

        $monthTranslated = esc_html__($month, "us-barcode-scanner");

        return str_replace($month, $monthTranslated, $date);
    }

    public static function getRolesList()
    {
        global $wp_roles, $wpdb;

        $roles = $wp_roles ? $wp_roles->roles : array();

        $table = $wpdb->prefix . Database::$interface;
        $rolesFields = $wpdb->get_results("SELECT * FROM {$table} AS I GROUP BY I.role;");

        foreach ($roles as $role => &$value) {
            foreach ($rolesFields as $field) {
                if ($field->role == $role) {
                    $value['bs_fields'] = true;
                    break;
                }
            }
        }

        return $roles;
    }

    public static function getUploadMaxFilesize()
    {
        if (!function_exists("ini_get")) return "";

        $uploadMaxFilesize = ini_get('upload_max_filesize');

        $val = trim($uploadMaxFilesize);
        $last = strtolower($val[strlen($val) - 1]);
        $number = (int)$val;

        switch ($last) {
            case 'g':
                $number *= 1024;
            case 'm':
                $number *= 1024;
            case 'k':
                $number *= 1024;
        }

        return $number;
    }
}
