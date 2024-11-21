<?php

namespace UkrSolution\BarcodeScanner\features\orders;

use UkrSolution\BarcodeScanner\features\settings\Settings;

class Orders
{
    public $settings;

    function __construct()
    {
        try {
            add_action('init', function () {
                $this->settings = new Settings();

                $defaultValue = $this->settings->getSettings("orderFulfillmentEnabled");
                $defaultValue = $defaultValue === null ? 'on' : $defaultValue->value;

                if ($defaultValue == 'on') {
                    add_action('admin_head', array($this, 'manage_shop_order_posts_custom_column_style'));
                    add_action('manage_shop_order_posts_custom_column', array($this, 'manage_shop_order_posts_custom_column'), 100);
                }
            });
        } catch (\Throwable $th) {
        }
    }

    public function manage_shop_order_posts_custom_column($column)
    {
        global $post;

        try {
            if ($column == 'order_status') {
                $data = get_post_meta($post->ID, "usbs_order_fulfillment_data", true);

                if ($data && isset($data["totalQty"]) && isset($data["totalScanned"]) && isset($data["items"]) && isset($data["codes"])) {
                    $picked = 0;
                    $total = 0;

                    foreach ($data["items"] as $value) {
                        $picked += ($value["qty"] == $value["scanned"] ? 1 : 0);
                        $total++;
                    }

                    foreach ($data["codes"] as $value) {
                        $picked += ($value["qty"] == $value["scanned"] ? 1 : 0);
                        $total++;
                    }

                    if ($data["totalQty"] == $data["totalScanned"]) {
                        echo '<div class="usbs-order-picked filled" title="' . esc_attr__('All order items have been picked (barcode scanner plugin)', 'us-barcode-scanner') . '">';
                    } else if ($data["totalScanned"] > 0) {
                        echo '<div class="usbs-order-picked parts" title="' . esc_attr__('Some order items have been picked (barcode scanner plugin)', 'us-barcode-scanner') . '">';
                    } else {
                        echo '<div class="usbs-order-picked" title="' . esc_attr__('No items have been picked for this order (barcode scanner plugin)', 'us-barcode-scanner') . '">';
                    }

                    if ($data["totalQty"] == $data["totalScanned"]) {
                        echo '<svg class="usbs-orders-check-icon" xmlns="http://www.w3.org/2000/svg"  viewBox="0 0 50 50" width="50px" height="50px"><path d="M 41.9375 8.625 C 41.273438 8.648438 40.664063 9 40.3125 9.5625 L 21.5 38.34375 L 9.3125 27.8125 C 8.789063 27.269531 8.003906 27.066406 7.28125 27.292969 C 6.5625 27.515625 6.027344 28.125 5.902344 28.867188 C 5.777344 29.613281 6.078125 30.363281 6.6875 30.8125 L 20.625 42.875 C 21.0625 43.246094 21.640625 43.410156 22.207031 43.328125 C 22.777344 43.242188 23.28125 42.917969 23.59375 42.4375 L 43.6875 11.75 C 44.117188 11.121094 44.152344 10.308594 43.78125 9.644531 C 43.410156 8.984375 42.695313 8.589844 41.9375 8.625 Z"/></svg>';
                    } else if ($data["totalScanned"] > 0) {
                        echo '<svg class="usbs-orders-check-icon" xmlns="http://www.w3.org/2000/svg"  viewBox="0 0 50 50" width="50px" height="50px"><path d="M 41.9375 8.625 C 41.273438 8.648438 40.664063 9 40.3125 9.5625 L 21.5 38.34375 L 9.3125 27.8125 C 8.789063 27.269531 8.003906 27.066406 7.28125 27.292969 C 6.5625 27.515625 6.027344 28.125 5.902344 28.867188 C 5.777344 29.613281 6.078125 30.363281 6.6875 30.8125 L 20.625 42.875 C 21.0625 43.246094 21.640625 43.410156 22.207031 43.328125 C 22.777344 43.242188 23.28125 42.917969 23.59375 42.4375 L 43.6875 11.75 C 44.117188 11.121094 44.152344 10.308594 43.78125 9.644531 C 43.410156 8.984375 42.695313 8.589844 41.9375 8.625 Z"/></svg>';
                    }

                    echo '<span usbs-order-open-post="' . esc_html($post->ID) . '">';
                    echo sprintf(esc_html__('(%s of %s picked)', 'us-barcode-scanner'), esc_html($picked), esc_html($total));
                    echo '</span></div>';
                }
            }
        } catch (\Throwable $th) {
        }
    }

    public function manage_shop_order_posts_custom_column_style()
    {
        echo '<style type="text/css">';
        echo 'table.wp-list-table svg.usbs-orders-check-icon { position: relative; width: 20px; height: 20px; top: 5px; }';
        echo 'table.wp-list-table .usbs-order-picked, table.wp-list-table .usbs-order-picked svg { fill: #747474; color: #747474; }';
        echo 'table.wp-list-table .usbs-order-picked.parts, table.wp-list-table .usbs-order-picked.parts svg { fill: #BC5F09; color: #BC5F09; }';
        echo 'table.wp-list-table .usbs-order-picked.filled, table.wp-list-table .usbs-order-picked.filled svg { fill: #0B8141; color: #0B8141; }';
        echo 'table.wp-list-table span[usbs-order-open-post]:hover { text-decoration: underline; }';
        echo '</style>';
    }

    public function manage_edit_shop_order_columns($columns)
    {
        $columns["usbs_fulfillment"] = esc_html__('Fulfillment', 'us-barcode-scanner');
        return $columns;
    }
}
