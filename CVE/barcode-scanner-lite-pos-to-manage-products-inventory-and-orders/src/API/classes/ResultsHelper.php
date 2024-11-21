<?php

namespace UkrSolution\BarcodeScanner\API\classes;

use UkrSolution\BarcodeScanner\Database;
use UkrSolution\BarcodeScanner\features\locations\Locations;

class ResultsHelper
{
    private static $locationsList = array();

    public static function getLocationsList()
    {
        if (!self::$locationsList) {
            $Locations = new Locations();
            self::$locationsList = $Locations->get();
        }

        return self::$locationsList;
    }

    public static function getStoreData()
    {
        $baseLocation = \wc_get_base_location();
        $_country = "";
        $_state = "";
        if ($baseLocation && isset($baseLocation["country"]) && $baseLocation["country"]) {
            $_country = \WC()->countries->countries[$baseLocation["country"]];
            $states = \WC()->countries->get_states($baseLocation["country"]);

            if ($states && isset($baseLocation["state"]) && $baseLocation["state"]) {
                $_state = isset($states[$baseLocation["state"]]) ? $states[$baseLocation["state"]] : "";
            }
        }
        return array(
            "name" => get_bloginfo("name"),
            "address" => array(
                "address" => get_option('woocommerce_store_address'),
                "address_2" => get_option('woocommerce_store_address_2'),
                "country" => $_country,
                "state" => $_state,
                "city" => get_option('woocommerce_store_city'),
                "postcode" => get_option('woocommerce_store_postcode'),
            ),
        );

        return self::$locationsList;
    }

    public static function getReceiptShortcodes($settings, $orderId)
    {
        $receiptTemplate = $settings->getSettings("receipt-template");
        $receiptTemplate = $receiptTemplate ? $receiptTemplate->value : "";
        $receiptShortcodesValue = array();

        if ($receiptTemplate) {
            $pattern = '/\[barcode\s+id=.*\s+shortcode=(\d+)\]/';

            preg_match_all($pattern, $receiptTemplate, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $shortcode = $match[0];
                $shortcodeWithId = $shortcode;

                $attributes = shortcode_parse_atts($shortcode);

                $id = isset($attributes["id"]) ? $attributes["id"] : null;

                if ($id !== null) $shortcodeWithId = str_replace($id, $orderId, $shortcode);

                if ($match && count($match) === 2) $receiptShortcodesValue[$shortcode] = array("html" => do_shortcode($shortcodeWithId), "shortcode_id" => $match[1], "shortcode" => $shortcodeWithId);
            }
        }

        return $receiptShortcodesValue;
    }

    public static function get_user_pending_orders_count($user_id, $order_id, $statuses)
    {
        if ($user_id && $statuses) {
            if (is_string($statuses)) {
                $statuses = explode(',', $statuses);
                $statuses = array_map('trim', $statuses);
            }

            $customer_orders = wc_get_orders(['status' => $statuses, 'customer_id' => $user_id, 'limit' => -1, 'exclude' => array($order_id)]);

            return count($customer_orders);
        }

        return 0;
    }

    public static function getExtraData($userId)
    {
        global $wpdb;

        if (!$userId) return array();

        $table = $wpdb->prefix . Database::$cartData;
        $data = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE `user_id` = %d;", $userId)
        );
        $extraData = array();

        foreach ($data as $value) {
            $extraData[$value->param] = $value->value;
        }

        return $extraData;
    }
}
