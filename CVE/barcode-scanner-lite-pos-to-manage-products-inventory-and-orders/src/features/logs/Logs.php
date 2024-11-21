<?php

namespace UkrSolution\BarcodeScanner\features\logs;

use UkrSolution\BarcodeScanner\Database;
use UkrSolution\BarcodeScanner\features\interfaceData\InterfaceData;
use UkrSolution\BarcodeScanner\features\settings\SettingsHelper;
use WP_REST_Request;

class Logs
{
    private $page = 1;
    public $recordsPerPage = 10;
    public $recordsTotal = 0;
    public $records = array();
    public $actions = array();
    public $users = array();

    public function __construct()
    {

        if (isset($_GET["p"])) {
            $this->page = (int)sanitize_text_field($_GET["p"]);
        }

        if (isset($_GET["ipp"])) {
            $this->recordsPerPage = (int)sanitize_text_field($_GET["ipp"]);
        }

        $this->getRecords();
        $this->getActions();
        $this->getUsers();
    }

    private function getRecords()
    {
        global $wpdb;

        $dt = new \DateTime("now");
        $table = $wpdb->prefix . Database::$logs;
        $offset = $this->recordsPerPage * ($this->page - 1);
        $limit = $this->recordsPerPage;
        $where = " WHERE 1=1 ";
        $groupBy = " ";

        $filterAction = isset($_GET["action"]) ? sanitize_text_field($_GET["action"]) : "";
        $filterUser = isset($_GET["user"]) ? sanitize_text_field($_GET["user"]) : "";
        $filterDtFrom = isset($_GET["dt-from"]) ? sanitize_text_field($_GET["dt-from"]) : $dt->format("Y-m-d");
        $filterDtTo = isset($_GET["dt-to"]) ? sanitize_text_field($_GET["dt-to"]) : $dt->format("Y-m-d");
        $filterType = isset($_GET["type"]) ? sanitize_text_field($_GET["type"]) : "";
        $filterUp = isset($_GET["up"]) ? sanitize_text_field($_GET["up"]) : "";

        if ($filterAction) {
            $where .= " AND L.action = '{$filterAction}' ";
        }

        if ($filterUser) {
            $where .= " AND L.user_id = '{$filterUser}' ";
        }

        if ($filterDtFrom && $filterDtTo) {
            $where .= " AND (L.datetime >= '{$filterDtFrom} 00:00:00' AND L.datetime <= '{$filterDtTo} 23:59:59') ";
        }

        if ($filterType) {
            $where .= " AND L.type = '{$filterType}' ";
        }


        $this->records = $wpdb->get_results(
            "SELECT SQL_CALC_FOUND_ROWS * FROM {$table} AS L {$where} {$groupBy} ORDER BY L.id DESC LIMIT {$offset}, {$limit};"
        );

        $total = $wpdb->get_row("SELECT FOUND_ROWS() as `total`");
        $this->recordsTotal = $total->total;
    }

    private function getActions()
    {
        $this->actions = array(
            "sku" =>  __("SKU", "us-barcode-scanner"),
            "enable_stock" =>  __("Enable stock", "us-barcode-scanner"),
            "quantity_plus" => __("Stock (+1)", "us-barcode-scanner"),
            "quantity_minus" => __("Stock (-1)", "us-barcode-scanner"),
            "order_quantity_minus" => __("Stock updated (Order creation)", "us-barcode-scanner"),
            "update_qty" => __("Stock updated", "us-barcode-scanner"),
            "update_cart_qty" => __("Qty increased before order created", "us-barcode-scanner"),
            "update_regular_price" => __("Regular Price", "us-barcode-scanner"),
            "update_sale_price" => __("Sale Price", "us-barcode-scanner"),
            "update_custom_field" => __("Custom Field", "us-barcode-scanner"),
            "update_title" => __("Product name", "us-barcode-scanner"),
            "update_product_status" => __("Update product status", "us-barcode-scanner"),
            "update_product_shipping" => __("Update shipping class", "us-barcode-scanner"),
            "create_product" => __("New product", "us-barcode-scanner"),
            "set_product_image" => __("Set product image", "us-barcode-scanner"),
            "update_meta_field" => __("Update meta field", "us-barcode-scanner"),
            "update_order_status" => __("Update order status", "us-barcode-scanner"),
            "update_order_customer" => __("Update order customer", "us-barcode-scanner"),
            "create_user" => __("Create user", "us-barcode-scanner"),
            "create_order" => __("Create order", "us-barcode-scanner"),
            "open_product" => __("Product open", "us-barcode-scanner"),
            "open_order" => __("Order open", "us-barcode-scanner"),
            "update_order_fulfillment" => __("Order fulfillment", "us-barcode-scanner"),
        );
    }

    private function getUsers()
    {
        global $wpdb;

        $this->users = array();
        $table = $wpdb->prefix . Database::$logs;

        $users = $wpdb->get_results(
            "SELECT L.user_id FROM {$table} AS L GROUP BY L.user_id;"
        );

        foreach ($users as $user) {
            $this->users[] = $this->getUserById($user->user_id);
        }
    }

    private function getUserById($id)
    {
        global $wpdb;

        $sql = "SELECT * FROM {$wpdb->users} as u WHERE u.ID = {$id};";
        $row = $wpdb->get_row($sql);

        return array(
            'ID' => $row ? $row->ID : 0,
            'name' => $row ? ($row->display_name ? $row->display_name : $row->user_nicename) : __("not found", "us-barcode-scanner"),
        );
    }

    public function export(WP_REST_Request $request)
    {
        $inputs = $request->get_param("inputs");
        $folder = $this->initLogsFolder();

        $dt = new \DateTime("now");
        $fname = $inputs["fname"] ? $inputs["fname"] : md5("Barcode_scanner_logs_" . $dt->format("d-m-Y_h-i-s")) . ".csv";
        $tmpFname = $inputs["tmpFname"] ? $inputs["tmpFname"] : md5("file_" . $dt->format("d-m-Y_h-i-s")) . ".csv";
        $csv_separator = ',';

        $file = $folder["upload_dir"] . 'logs/' . $tmpFname;

        $isNewFile = file_exists($file);

        $fp = fopen($file, 'a+');

        if (!$isNewFile) {
            fputcsv($fp, array(
                __("User Id", "us-barcode-scanner"),
                __("Login", "us-barcode-scanner"),
                __("User Name", "us-barcode-scanner"),
                __("Date", "us-barcode-scanner"),
                __("Time", "us-barcode-scanner"),
                __("Product Id", "us-barcode-scanner"),
                __("Item", "us-barcode-scanner"),
                __("Action", "us-barcode-scanner"),
                __("New value", "us-barcode-scanner"),
                __("Old value", "us-barcode-scanner"),
            ), $csv_separator);
        }

        foreach ($this->records as $record) {
            $user = get_userdata($record->user_id);

            $userId = $record->user_id ? $record->user_id : "";
            $login = $user ? $user->user_login : "";
            $fullName = "";

            if ($user) {
                $fullName = trim($user->first_name . " " . $user->last_name);
                if (!$fullName) {
                    $fullName = $user->data->user_nicename;
                }
            }
            $date = get_date_from_gmt($record->datetime, "Y-m-d");
            $time = get_date_from_gmt($record->datetime, "H:i:s");
            $productId = $record->post_id ? $record->post_id : "";
            if ($record->type == "order_item" && $record->parent_post_id) {
                $item = __("For order", "us-barcode-scanner") . " #" . $record->parent_post_id . ",";
                $item .= __("fulfilled item", "us-barcode-scanner") . " #" . $record->post_id;
            } else {
                $item = "Product";
                if (in_array($record->action, array("create_order", "update_order_status")) || $record->type === "order") {
                    $item = "Order";
                    $productId = "";
                } elseif (in_array($record->action, array("create_user")) || $record->type === "user") {
                    $item = "User";
                    $productId = "";
                }
                $item .= " #" . $record->post_id;
                if (!in_array($record->action, array("create_order", "update_order_status", "create_user")) || $record->type === "product") {
                    $item .= " " . get_the_title($record->post_id);
                }
            }

            $newValue = explode(": ", $record->value);
            $label = $record->field ? $this->getFieldLabel($record->field) : $this->getFieldLabel($newValue[0]);

            if ($record->action == "update_order_item_meta") {
                $action = __("Product fulfillment check", "us-barcode-scanner");
            } else if ($record->custom_action) {
                $action = $record->custom_action;
            } else if ($label != $record->value && $record->value != 0) {
                $action =  __("Changed", "us-barcode-scanner");
                $action .=  ' "' . $label . '"';
            } else {
                $action = isset($this->actions[$record->action]) ? $this->actions[$record->action] : $record->action;
            }
            if ($record->action == "update_order_item_meta") {
                $newValue = $record->value == 1 ? __("Item Found", "us-barcode-scanner") : __("Uncheck", "us-barcode-scanner");
            } else {
                $newValue = count($newValue) === 2 ?  $newValue[1] : $record->value;
            }
            $oldValue = $record->old_value;
            fputcsv($fp, array($userId, $login, $fullName, $date, $time, $productId, $item, $action, $newValue, $oldValue), $csv_separator);
        }

        fclose($fp);

        $exported = (int)$inputs["exported"] + count($this->records);

        if ((int)$this->recordsTotal == $exported) {
            $path = $folder["upload_dir"] . "logs/";
            $url = $folder["upload_dir_url"] . "logs/";
            rename($path . $tmpFname, $path . $fname);
        }

        $result = array(
            "total" => $this->recordsTotal,
            "exported" => $exported,
            "nextPage" => (int)$inputs["page"] + 1,
            "fname" => $fname,
            "fpath" => SettingsHelper::getDownloadCsvUrl() . "?fn=" . $fname,
            "tmpFname" => $tmpFname,
        );

        return rest_ensure_response($result);
    }

    public function downloadFile()
    {
        $dt = new \DateTime("now");
        $folder = $this->initLogsFolder();
        $tempFileName = isset($_GET['fn']) ? sanitize_text_field($_GET['fn']) : null;

        if ($tempFileName) {
            $csvFileName = "Barcode_scanner_logs_" . $dt->format("d-m-Y_h-i-s") . ".csv";
            $csvFilePath = $folder["upload_dir"] . 'logs/' . $tempFileName;

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $csvFileName . '"');

            readfile($csvFilePath . $csvFileName);
        }
        else {
            wp_redirect(admin_url('/admin.php?page=barcode-scanner-logs'));
        }

        exit;
    }

    private function initLogsFolder()
    {
        $wp_upload_dir = wp_upload_dir();
        $upload_dir = $wp_upload_dir['basedir'] . '/barcode-scanner/';
        $upload_dir_url = $wp_upload_dir['baseurl'] . '/barcode-scanner/';

        if (!file_exists($upload_dir)) {
            wp_mkdir_p($upload_dir);
        }
        if (!file_exists($upload_dir . 'logs')) {
            wp_mkdir_p($upload_dir . 'logs');
        }

        $this->clearLogsFolder($upload_dir);

        return array(
            "upload_dir" => $upload_dir,
            "upload_dir_url" => $upload_dir_url,
        );
    }

    private function clearLogsFolder($upload_dir)
    {

        $files = glob($upload_dir . 'logs/*');
        $now = time();

        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= 60 * 60 * 24 * 5) {
                    unlink($file);
                }
            }
        }
    }

    public function getFieldLabel($fieldName)
    {
        if (!$fieldName) return "";

        $field = InterfaceData::getField($fieldName);
        return $field ? $field["field_label"] : $fieldName;
    }
}
