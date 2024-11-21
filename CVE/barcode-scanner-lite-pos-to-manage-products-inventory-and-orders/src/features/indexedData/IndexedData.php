<?php

namespace UkrSolution\BarcodeScanner\features\indexedData;

use UkrSolution\BarcodeScanner\API\classes\WPML;
use UkrSolution\BarcodeScanner\Database;

class IndexedData
{
    public function __construct()
    {
        $msg = get_option("usbs_reCreateTable_msg", false);
        if ($msg) {
            add_action('admin_notices', function () {
                $class = 'notice notice-warning';
                $message = __('Index table has been re-created and now it is empty, please', 'us-barcode-scanner');
                $message .= " <a href='#barcode-scanner-products-indexation'>" . esc_html__('run indexation', 'us-barcode-scanner') . "</a> ";
                $message .= __('again in order to find products.', 'us-barcode-scanner');
                printf('<div class="%1$s" style="%2$s"><p>%3$s</p></div>', esc_attr($class), "margin-left: 2px;", wp_kses_post($message));
            });
            update_option("usbs_reCreateTable_msg", false);
        }
    }

    public function getColumns()
    {
        global $wpdb;

        $columnsTable = $wpdb->prefix . Database::$columns;
        $columns = $wpdb->get_results("SELECT * FROM {$columnsTable};");

        return $columns;
    }

    public function getByPostId($postId)
    {
        global $wpdb;

        $postsTable = $wpdb->prefix . Database::$posts;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$postsTable} AS P WHERE P.post_id = '%d';", trim($postId))
        );

        return $row;
    }

    public function getWpmlData($postId)
    {
        global $wpdb;

        if (!WPML::status()) {
            return null;
        }

        $result = array(
            "trid" => null,
            "translations" => array()
        );

        try {
            global $sitepress;

            if (!isset($sitepress)) return $result;

            $trid = $sitepress->get_element_trid($postId, 'post_product');

            $translations = $sitepress->get_element_translations($trid, 'product');

            $result["trid"] = $trid;
            $result["translations"] = $translations;

            $table = $wpdb->prefix . "icl_translations";
            $row = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$table} AS P WHERE P.element_id = '%d';", trim($postId))
            );
            $result["wpml_row"] = $row;
            $result["wpml_rows"] = $row ? $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM {$table} AS P WHERE P.trid = '%d';", trim($row->trid))
            ) : "";
        } catch (\Throwable $th) {
            $result["error"] = $th->getMessage();
        }

        return $result;
    }

    public function getAllColumns()
    {
        global $wpdb;

        $columns = array();
        $postsTable = $wpdb->prefix . Database::$posts;
        $columnsTable = $wpdb->prefix . Database::$columns;
        $posts = $wpdb->get_results("DESCRIBE $postsTable");

        foreach ($posts as $value) {
            if (!in_array($value->Field, ["id", "created", "updated"]) && !preg_match("/column_[\d]{1,3}/", $value->Field)) {
                $columns[] = str_replace("postmeta_", "", $value->Field);
            }
        }

        $cf = $wpdb->get_results("SELECT * FROM {$columnsTable};");

        if ($cf) {
            foreach ($cf as $value) {
                $columns[] = $value->name;
            }
        }

        return $columns;
    }
}
