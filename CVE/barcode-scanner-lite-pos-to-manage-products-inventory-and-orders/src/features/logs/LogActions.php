<?php

namespace UkrSolution\BarcodeScanner\features\logs;

use UkrSolution\BarcodeScanner\Database;

class LogActions
{
    static public $actions = array(
        "sku" => "sku",
        "enable_stock" => "enable_stock",
        "quantity_plus" => "quantity_plus",
        "quantity_minus" => "quantity_minus",
        "order_quantity_minus" => "order_quantity_minus",
        "update_qty" => "update_qty",
        "update_cart_qty" => "update_cart_qty",
        "update_regular_price" => "update_regular_price",
        "update_sale_price" => "update_sale_price",
        "update_custom_field" => "update_custom_field",
        "update_title" => "update_title",
        "update_product_status" => "update_product_status",
        "update_product_shipping" => "update_product_shipping",
        "create_product" => "create_product",
        "set_product_image" => "set_product_image",
        "update_meta_field" => "update_meta_field",
        "update_order_status" => "update_order_status",
        "update_order_customer" => "update_order_customer",
        "create_user" => "create_user",
        "create_order" => "create_order",
        "open_product" => "open_product",
        "open_order" => "open_order",
        "update_order_item_meta" => "update_order_item_meta",
        "update_order_fulfillment" => "update_order_fulfillment",
    );

    static public function add($itemId, $action, $field, $value, $oldValue, $type, $request, $customAction = "", $parentId = null)
    {
        global $wpdb;

        $table = $wpdb->prefix . Database::$logs;
        $utc = new \DateTime("now", new \DateTimeZone("UTC"));

        $token = $request->get_param("token");
        $userToken = $request->get_param("userToken");
        $userId = get_current_user_id();

        if ($userToken) {
            $users = get_users(array('meta_key' => 'scanner-app-token', 'meta_value' => $userToken));
            if ($users && count($users) > 0) {
                $userId = $users[0]->ID;
            }
        }

        if (!$userId && $token) {
            try {
                if (preg_match("/^([0-9]+)/", @base64_decode($token), $m)) {
                    if ($m && count($m) > 0 && is_numeric($m[0])) {
                        $userId = $m[0];
                    }
                } else {
                    $users = get_users(array('meta_key' => 'barcode_scanner_app_otp', 'meta_value' => $token));
                    $userId = count($users) > 0 ? $users[0]->ID : $userId;
                }
            } catch (\Throwable $th) {
            }
        }

        $wpdb->insert($table, array(
            "user_id" => $userId,
            "post_id" => $itemId,
            "parent_post_id" => $parentId,
            "datetime" => $utc->format("Y-m-d H:i:s"),
            "action" => $action,
            "custom_action" => $customAction,
            "field" => $field,
            "value" => $value,
            "old_value" => $oldValue,
            "type" => $type
        ));

        return $wpdb->insert_id;
    }
}
