<?php

namespace UkrSolution\BarcodeScanner\features\products;

use UkrSolution\BarcodeScanner\features\interfaceData\InterfaceData;
use UkrSolution\BarcodeScanner\features\settings\Settings;
use UkrSolution\BarcodeScanner\features\settings\SettingsHelper;

class Products
{
    public $settings;

    function __construct()
    {
        try {
            add_action('init', function () {
                $this->settings = new Settings();

                $this->initCustomFields();
            });
        } catch (\Throwable $th) {
        }
    }

    private function initCustomFields()
    {
        $status = $this->settings->getSettings("searchCF");
        $status = $status ? $status->value : $this->settings->getField("general", "searchCF", "on");

        if ($status == "on") {
            add_action('woocommerce_product_options_sku', array($this, 'woocommerce_product_options_sku'));
            add_action('woocommerce_process_product_meta', array($this, 'woocommerce_process_product_meta'));

            add_action('woocommerce_variation_options_pricing', array($this, 'woocommerce_variation_options_pricing'), 10, 3);
            add_action('woocommerce_save_product_variation', array($this, 'woocommerce_save_product_variation'), 15, 2);

            add_action('pre_get_posts', array($this, 'pre_get_posts'));
        }
    }

    public function pre_get_posts($query)
    {
        try {
            if ($query->is_main_query() && isset($query->query['post_type']) && 'product' == $query->query['post_type']) {
                $new_query = clone ($query);
                $search_term = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';

                if (empty($search_term)) {
                    return;
                }

                $new_query->query_vars['s'] = '';
                $old_product_in = $query->query_vars['post__in'];

                unset($new_query->query['post__in']);
                unset($new_query->query_vars['post__in']);

                $new_meta_query = array(
                    'key' => 'usbs_barcode_field',
                    'value' => $search_term,
                    'compare' => 'LIKE'
                );
                $old_meta_query = (isset($query->query_vars['meta_query']) ? $query->query_vars['meta_query'] : false);

                if (!empty($old_meta_query)) {
                    $meta_query = $old_meta_query;
                    array_push($meta_query, array('relation' => 'OR'));
                    array_push($meta_query, $new_meta_query);
                } else {
                    $meta_query = array($new_meta_query);
                }

                $new_query->set('meta_query', $meta_query);
                $new_query->set('fields', 'ids');

                remove_action('pre_get_posts', array($this, 'pre_get_posts'));

                $result  = get_posts($new_query->query_vars);
                $new_ids = $old_product_in;

                if ($result) {
                    $new_ids = array_merge($new_ids, $result);
                }

                $new_query->set('post_type', 'product_variation');
                $new_query->set('fields', 'id=>parent');
                $result = get_posts($new_query->query_vars);
                if ($result) {
                    $new_ids = array_merge($new_ids, $result);
                }

                $query->set('post__in', $new_ids);
            }
        } catch (\Throwable $th) {
        }
    }

    public function woocommerce_variation_options_pricing($loop, $variation_data, $variation)
    {
        $label = $this->settings->getSettings("searchCFLabel");
        $label = $label ? $label->value : $this->settings->getField("general", "searchCFLabel", "Barcode");
        $value = get_post_meta($variation->ID, 'usbs_barcode_field', true);
        $args = array(
            'class' => 'short usbs_barcode_field_text',
            'label' => $label,
            'placeholder' => __('Enter value', 'us-barcode-scanner'),
            'id' => 'usbs_barcode_field_v[' . $loop . ']',
            'desc_tip' => true,
            'description' => __('This field created by "Barcode Scanner" plugin, you can rename this field or disable it in the plugin\'s settings.', 'us-barcode-scanner'),
            'value' => $value,
            'wrapper_class' => 'form-row form-row-first'
        );
        \woocommerce_wp_text_input($args);
    }

    public function woocommerce_save_product_variation($variationId, $loop)
    {
        $value = isset($_POST['usbs_barcode_field_v']) && isset($_POST['usbs_barcode_field_v'][$loop]) ? $_POST['usbs_barcode_field_v'][$loop] : "";
        update_post_meta($variationId, 'usbs_barcode_field', $value);
    }

    public function woocommerce_product_options_sku($product)
    {
        global $post;

        if (!$post) {
            return;
        }

        $label = $this->settings->getSettings("searchCFLabel");
        $label = $label ? $label->value : $this->settings->getField("general", "searchCFLabel", "Barcode");
        $value = get_post_meta($post->ID, 'usbs_barcode_field', true);
        $args = array(
            'class' => 'usbs_barcode_field_text',
            'label' => $label,
            'placeholder' => __('Enter value', 'us-barcode-scanner'),
            'id' => 'usbs_barcode_field',
            'desc_tip' => true,
            'description' => __('This field created by "Barcode Scanner" plugin, you can rename this field or disable it in the plugin\'s settings.', 'us-barcode-scanner'),
            'value' => $value
        );
        \woocommerce_wp_text_input($args);
    }

    public function woocommerce_process_product_meta($postId)
    {
        $usbsBarcodeField = isset($_POST['usbs_barcode_field']) ? sanitize_text_field($_POST['usbs_barcode_field']) : '';

        update_post_meta($postId, "usbs_barcode_field", $usbsBarcodeField);
    }
}
