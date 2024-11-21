<?php

namespace UkrSolution\BarcodeScanner\features\history;

use UkrSolution\BarcodeScanner\API\actions\HPOS;
use UkrSolution\BarcodeScanner\API\classes\Results;
use UkrSolution\BarcodeScanner\Database;
use UkrSolution\BarcodeScanner\features\Debug\Debug;
use UkrSolution\BarcodeScanner\features\settings\SettingsHelper;

class History
{
    public static function add($postId, $userId = null)
    {
        global $wpdb;

        try {
            $uid = $userId ? $userId : \get_current_user_id();

            if (!$uid) return;

            $table = $wpdb->prefix . Database::$history;

            $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE `user_id` = %d AND `post_id` = %d;", $uid, $postId));

            if ($record) {
                $wpdb->update($table, array('counter' => $record->counter + 1, 'updated' => date("Y-m-d H:i:s")), array('id' => $record->id));
            } else {
                $wpdb->insert($table, array('user_id' => $uid, 'post_id' => $postId, 'updated' => date("Y-m-d H:i:s")), array('%d', '%s', '%s'));
            }
        } catch (\Throwable $th) {
            Debug::addPoint($th->getMessage());
        }
    }

    public static function getByUser($userId = null)
    {
        global $wpdb;

        $list = array();

        try {
            $uid = $userId ? $userId : \get_current_user_id();

            if (!$uid) return array();

            Debug::addPoint("--- history start");

            $table = $wpdb->prefix . Database::$history;

            $products = $wpdb->get_results($wpdb->prepare(
                "SELECT H.* FROM {$table} AS H, {$wpdb->posts} AS P WHERE P.ID = H.post_id AND P.post_type IN('product','product_variation') AND H.user_id = %d ORDER BY H.updated DESC LIMIT 15;",
                $uid
            ));

            Debug::addPoint("--- history: products");

            if (HPOS::getStatus()) {
                $orders = $wpdb->get_results($wpdb->prepare(
                    "SELECT H.*, 
                    (SELECT COUNT(order_item_id) FROM {$wpdb->prefix}woocommerce_order_items WHERE order_id = O.id AND order_item_type = 'line_item') AS 'items_count'
                    FROM {$table} AS H, {$wpdb->prefix}wc_orders AS O 
                    WHERE O.id = H.post_id AND H.user_id = %d ORDER BY H.updated DESC LIMIT 15;",
                    $uid
                ));
            } else {
                $orders = $wpdb->get_results($wpdb->prepare(
                    "SELECT H.*, 
                    (SELECT COUNT(order_item_id) FROM {$wpdb->prefix}woocommerce_order_items WHERE order_id = P.ID AND order_item_type = 'line_item') AS 'items_count'
                    FROM {$table} AS H, {$wpdb->posts} AS P 
                    WHERE P.ID = H.post_id AND P.post_type = 'shop_order' AND H.user_id = %d ORDER BY H.updated DESC LIMIT 15;",
                    $uid
                ));
            }

            Debug::addPoint("--- history: orders");

            $resultsClass = new Results();

            foreach ($products as $value) {
                if (!$value->post_id) continue;

                $post = \get_post($value->post_id);
                if (!$post) continue;

                if ($post->post_type == "product" || $post->post_type == "product_variation") {
                    $translation = array();

                    if (isset($post->translation)) {
                        $translation = $post->translation;
                    }

                    $product_thumbnail_url = $resultsClass->getThumbnailUrl($post->ID);
                    $product_large_thumbnail_url = $resultsClass->getThumbnailUrl($post->ID, 'large');
                    $product_parent_thumbnail_url = "";
                    $product_parent_large_thumbnail_url = "";

                    if ($post->post_parent) {
                        $product_parent_thumbnail_url = $resultsClass->getThumbnailUrl($post->post_parent);
                        $product_parent_large_thumbnail_url = $resultsClass->getThumbnailUrl($post->post_parent, 'large');
                    }

                    $list[] = array(
                        "ID" => $post->ID,
                        "post_type" => $post->post_type,
                        "post_title" => base64_encode($post->post_title),
                        "product_sku" => get_post_meta($post->ID, "_sku", true),
                        "translation" => array("language_code" => $translation && isset($translation->language_code) ? $translation->language_code : ""),
                        "product_thumbnail_url" => $product_thumbnail_url ? $product_thumbnail_url : "",
                        "product_large_thumbnail_url" => $product_large_thumbnail_url ? $product_large_thumbnail_url : "",
                        "product_parent_thumbnail_url" => $product_parent_thumbnail_url ? $product_parent_thumbnail_url : "",
                        "product_parent_large_thumbnail_url" => $product_parent_large_thumbnail_url ? $product_parent_large_thumbnail_url : "",
                        "_source" => "history"
                    );
                }
                $post = null;
            }
            unset($products);

            Debug::addPoint("--- history: products collected");

            foreach ($orders as $value) {
                if (!$value->post_id) continue;

                if (HPOS::getStatus()) {
                    $record = $wpdb->get_row($wpdb->prepare("SELECT O.* FROM {$wpdb->prefix}wc_orders AS O WHERE O.id = %d", $value->post_id));

                    if ($record) {
                        $order = new \WC_Order($value->post_id);

                        if ($order && $record) {
                            $customerId = $order->get_customer_id();

                            $wpFormat = get_option("date_format", "F j, Y") . " " . get_option("time_format", "g:i a");
                            $orderDate = new \DateTime($order->get_date_created());
                            $date_format = $orderDate->format($wpFormat);
                            $date_format = SettingsHelper::dateTranslate($date_format);

                            $previewDateFormat = $orderDate->format("M j, Y");
                            $previewDateFormat = SettingsHelper::dateTranslate($previewDateFormat);

                            $customerCountry = $order->get_billing_country();
                            $customerName = $order->get_billing_first_name() . " " . $order->get_billing_last_name();
                            $customerName = trim($customerName);

                            if (!$customerName && $customerId) {
                                $customerName = get_user_meta($customerId, 'first_name', true) . ' ' . get_user_meta($customerId, 'last_name', true);
                            }

                            $avatar = $customerId ? get_avatar($customerId) : "";

                            if ($avatar) {
                                preg_match('/src=["\']([^"\']+)["\']/', $avatar, $matches);
                                $avatar = $matches && count($matches) > 1 ? $matches[1] : "";
                            }

                            $orderHistoryData = array(
                                "ID" => $order->get_id(),
                                "post_type" => $order->get_type(),
                                "post_title" => base64_encode($order->get_title()),
                                "date_format" => $date_format,
                                "customer_name" => $customerName,
                                "customer_country" => $customerCountry,
                                "order_total_c" => strip_tags(wc_price($order->get_total())),
                                "preview_date_format" => $previewDateFormat,
                                "user" => array("avatar" => $avatar),
                                "_source" => "history",
                                "products" => array(),
                            );

                            $list[] = $orderHistoryData;
                        }
                    }
                } else {
                    $post = \get_post($value->post_id);
                    $order = new \WC_Order($value->post_id);

                    if ($post && $post->post_type == "shop_order") {
                        $customerId = $order->get_customer_id();

                        $wpFormat = get_option("date_format", "F j, Y") . " " . get_option("time_format", "g:i a");
                        $orderDate = new \DateTime($order->get_date_created());
                        $date_format = $orderDate->format($wpFormat);
                        $date_format = SettingsHelper::dateTranslate($date_format);

                        $previewDateFormat = $orderDate->format("M j, Y");
                        $previewDateFormat = SettingsHelper::dateTranslate($previewDateFormat);

                        $customerCountry = $order->get_billing_country();
                        $customerName = $order->get_billing_first_name() . " " . $order->get_billing_last_name();
                        $customerName = trim($customerName);

                        if (!$customerName && $customerId) {
                            $customerName = get_user_meta($customerId, 'first_name', true) . ' ' . get_user_meta($customerId, 'last_name', true);
                        }

                        $avatar = $customerId ? get_avatar($customerId) : "";

                        if ($avatar) {
                            preg_match('/src=["\']([^"\']+)["\']/', $avatar, $matches);
                            $avatar = $matches && count($matches) > 1 ? $matches[1] : "";
                        }

                        $orderHistoryData = array(
                            "ID" => $post->ID,
                            "post_type" => $post->post_type,
                            "post_title" => base64_encode($post->post_title),
                            "date_format" => $date_format,
                            "customer_name" => $customerName,
                            "customer_country" => $customerCountry,
                            "order_total_c" => strip_tags(wc_price($order->get_total())),
                            "preview_date_format" => $previewDateFormat,
                            "user" => array("avatar" => $avatar),
                            "_source" => "history",
                            "products" => array(),
                        );

                        if ($value->items_count) {
                            for ($i = 0; $i < $value->items_count; $i++) {
                                $orderHistoryData["products"][] = $i;
                            }
                        }

                        $list[] = $orderHistoryData;
                    }
                }

                $post = null;
            }

            unset($orders);

            Debug::addPoint("--- history: orders collected");
        } catch (\Throwable $th) {
            Debug::addPoint($th->getMessage());
        }

        return $list;
    }
}
