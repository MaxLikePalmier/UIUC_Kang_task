<?php

namespace UkrSolution\BarcodeScanner\API\classes;

use UkrSolution\BarcodeScanner\Database;
use UkrSolution\BarcodeScanner\features\interfaceData\InterfaceData;
use UkrSolution\BarcodeScanner\features\locations\LocationsData;
use UkrSolution\BarcodeScanner\features\settings\Settings;

class PostsList
{
    public static function addToList($userId, $post, $counter = 1, $modifyAction = "")
    {
        global $wpdb;

        if (!$post || !isset($post["ID"]) || !$userId) return null;

        $table = $wpdb->prefix . Database::$postsList;

        $record = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} AS PL WHERE PL.user_id = '%s' AND PL.post_id = '%s';", $userId, $post["ID"])
        );

        if ($record) {
            $result = $wpdb->update($table, array(
                "updated" => date("Y-m-d H:i:s", time()),
            ), array("id" => $record->id));
            $result = $wpdb->get_row("SELECT PS.* FROM {$table} AS PS WHERE PS.id = '{$record->id}';");

            return $result ? $post["ID"] : null;
        }
        else {
            $result = $wpdb->insert($table, array(
                "user_id" => $userId,
                "post_id" => $post["ID"],
                "counter" => 0, 
                "updated" => date("Y-m-d H:i:s", time()),
            ));

            return $result ? $wpdb->insert_id : null;
        }
    }

    public static function getList($userId, $isReturnPosts = false)
    {
        global $wpdb;

        if (!$userId) return null;

        $userRole = Users::getUserRole($userId);

        $table = $wpdb->prefix . Database::$postsList;
        $list = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} AS PL WHERE PL.user_id = '%s' ORDER BY PL.updated DESC;", $userId)
        );

        if ($isReturnPosts) {
            return $list;
        }

        $cartDecimalQuantity = false;

        try {
            $settings = new Settings();
            $field = $settings->getSettings("cartDecimalQuantity");
            $value = $field === null ? "off" : $field->value;
            $cartDecimalQuantity = $value === "on";
        } catch (\Throwable $th) {
        }

        foreach ($list as $value) {
            $product = \wc_get_product($value->post_id);
            $product_thumbnail_url = (new Results())->getThumbnailUrl($value->post_id);
            $product_large_thumbnail_url = (new Results())->getThumbnailUrl($value->post_id, 'large');

            if ($product) {
                $post_title = $product->get_name();
                $post_title = @htmlspecialchars_decode($post_title);

                $value->post_title = $post_title ? $post_title : $product->get_name();
                $value->product_thumbnail_url = $product_thumbnail_url;
                $value->product_large_thumbnail_url = $product_large_thumbnail_url;
                $value->counter = $cartDecimalQuantity ? $value->counter : (int)$value->counter;
                $value->_stock = $cartDecimalQuantity ? get_post_meta($value->post_id, "_stock", true) : (int)get_post_meta($value->post_id, "_stock", true);
                $value->_stock_status = get_post_meta($value->post_id, "_stock_status", true);
                $value->_manage_stock = get_post_meta($value->post_id, "_manage_stock", true);

                $value->fields = array();

                $interfaceData = new InterfaceData();
                $results = new Results();

                foreach ($interfaceData::getFields(true, "mobile", false, $userRole) as $field) {
                    if (!$field['field_name']) continue;

                    if (isset($field["show_in_products_list"]) && $field["show_in_products_list"] == 1) {

                        $filterName = str_replace("%field", $field['field_name'], $results->filter_get_after);
                        $defaultValue = \get_post_meta($value->post_id, $field['field_name'], true);
                        $filteredValue = apply_filters($filterName, $defaultValue, $field['field_name'], $value->post_id);
                        $filteredValue = $field['field_name'] == "_stock" && $filteredValue ? sprintf('%g', $filteredValue) :  $filteredValue;

                        if (!$filteredValue && $field['field_name'] == "usbs_categories") {
                            $terms = get_the_terms($value->post_id, 'product_cat');

                            if ($terms) {
                                $cats = array();

                                foreach ($terms as $term) {
                                    $cats[] = $term->name;
                                }

                                $filteredValue = implode(", ", $cats);
                            }
                        }
                        if (!$filteredValue && $field['field_name'] == "usbs_variation_attributes") {
                            $filteredValue = "";
                        }
                        else if ($filteredValue && in_array($field['field_name'], array("_regular_price", "_sale_price"))) {
                            $filteredValue = strip_tags(wc_price($filteredValue));
                        }

                        $value->fields[] = array("f" => $field['field_name'], "l" => $field["field_label"], "v" => $filteredValue);
                    }
                }
            }
        }

        return $list;
    }

    public static function resetCounter($userId)
    {
        global $wpdb;
        $wpdb->update($wpdb->prefix . Database::$postsList, array("counter" => 0), array("user_id" => $userId));
    }

    public static function removeRecord($recordId)
    {
        global $wpdb;
        $wpdb->delete($wpdb->prefix . Database::$postsList, array("id" => $recordId));
    }

    public static function clear($userId)
    {
        global $wpdb;
        $wpdb->delete($wpdb->prefix . Database::$postsList, array("user_id" => $userId));
    }
}
