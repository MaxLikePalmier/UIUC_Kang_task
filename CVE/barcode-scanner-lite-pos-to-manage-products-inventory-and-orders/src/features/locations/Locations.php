<?php

namespace UkrSolution\BarcodeScanner\features\locations;

use UkrSolution\BarcodeScanner\Database;
use UkrSolution\BarcodeScanner\features\settings\Settings;
use UkrSolution\BarcodeScanner\features\settings\SettingsHelper;

class Locations
{
    private $metaBoxConfig = array(
        "title" => "Product location",
        "post-type" => array("product"),
        "context" => "normal",
        "priority" => "core"
    );

    public function __construct()
    {
            add_action('init', function () {
                $this->initFields();

                add_action('save_post', [$this, 'save_post']);
                add_action('woocommerce_variation_options_pricing', array($this, 'woocommerce_variation_options_pricing'), 10, 3);
                add_action('woocommerce_save_product_variation', array($this, 'woocommerce_save_product_variation'), 15, 2);
            });
    }

    public function initFields()
    {

        if (!isset($_GET["post"]) && (!isset($_GET["action"]) || $_GET["action"] !== "edit")) {
            return;
        }

        try {
            $settings = new Settings();
            $enableLocations = $settings->getSettings("enableLocations");
            $enableLocations = $enableLocations ? $enableLocations->value : "on";

            if ($enableLocations === "on") {
                add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
            }
        } catch (\Throwable $th) {
        }
    }

    public function get($isArray = false)
    {
        global $wpdb;

        $interface = $wpdb->prefix . Database::$interface;

        $rows = $wpdb->get_results("SELECT * FROM {$interface} AS I WHERE I.field_name LIKE 'usbs_stock_location_level_%' AND I.status = 1 ORDER BY I.`order` DESC;");
        $fields = array();



        foreach ($rows as $key => $value) {
            $data = array(
                "id" => $value->id,
                "slug" => $value->field_name,
                "name" => $value->field_label,
                "order" => $value->order,
                "created" => $value->created,
                "updated" => $value->updated
            );
            $fields[] = $isArray ? $data : (object)$data;
        }

        return $fields;
    }

    public function update($data)
    {
        global $wpdb;

        if (!is_array($data)) {
            return;
        }

        $table = $wpdb->prefix . Database::$locations;

        foreach ($data as $key => $value) {
            $wpdb->update($table, array('name' => trim($value)), array('slug' => $key));
        }
    }

    public function add_meta_boxes()
    {
        foreach ($this->metaBoxConfig['post-type'] as $screen) {
            add_meta_box(
                sanitize_title($this->metaBoxConfig['title']),
                $this->metaBoxConfig['title'],
                [$this, 'add_meta_box_callback'],
                $screen,
                $this->metaBoxConfig['context'],
                $this->metaBoxConfig['priority']
            );
        }
    }

    public function save_post($post_id)
    {
        $fields = $this->get();

        foreach ($fields as $key => $value) {
            if (isset($_POST[$value->slug])) {
                $sanitized = sanitize_text_field($_POST[$value->slug]);
                update_post_meta($post_id, $value->slug, $sanitized);
            }
        }
    }

    public function add_meta_box_callback($post)
    {
        $this->fields_table($post);
    }

    private function fields_table($post)
    {
        require_once USBS_PLUGIN_BASE_PATH . "src/features/locations/views/meta-box-product.php";
    }

    public function woocommerce_variation_options_pricing($loop, $variation_data, $variation)
    {
        try {
            $settings = new Settings();
            $enableLocations = $settings->getSettings("enableLocations");
            $enableLocations = $enableLocations ? $enableLocations->value : "on";

            if ($enableLocations === "on") {
                require USBS_PLUGIN_BASE_PATH . "src/features/locations/views/meta-box-variation.php";
            }
        } catch (\Throwable $th) {
        }
    }

    public function woocommerce_save_product_variation($variationId, $loop)
    {
        $fields = $this->get();

        foreach ($fields as $key => $field) {
            if (isset($_POST["v_" . $field->slug])) {
                $param = "v_" . $field->slug;
                $value = isset($_POST[$param]) && isset($_POST[$param][$loop]) ? sanitize_text_field($_POST[$param][$loop]) : "";
                update_post_meta($variationId, $field->slug, $value);
            }
        }
    }
}
