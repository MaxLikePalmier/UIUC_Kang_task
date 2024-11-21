<?php

namespace UkrSolution\BarcodeScanner\API\actions;

use UkrSolution\BarcodeScanner\API\classes\Post;
use UkrSolution\BarcodeScanner\API\classes\Results;
use UkrSolution\BarcodeScanner\Database;
use UkrSolution\BarcodeScanner\features\debug\Debug;
use UkrSolution\BarcodeScanner\features\settings\Settings;
use WP_REST_Request;

class DbActions
{
    public function createColumn(WP_REST_Request $request)
    {
        global $wpdb;
        global $wp_version;

        Debug::addPoint("indexation->start");

        $fields = $request->get_param("fields");
        $autoEnableIndexation = trim($request->get_param("autoEnableIndexation"));
        $progress = $request->get_param("progress");
        $isFast = false;

        if ($fields && count($fields) === 1 && $fields[0]["type"] === "indexing") {
            $indexing = true;
        } else {
            $indexing = false;
        }

        if (isset($progress['fast'])) {
            $isFast = $progress['fast'];
        }

        $settings = new Settings();

        if ($progress) {
            $data = Database::updatePostsTable($progress["offset"], $progress["limit"], $isFast);

            if ($indexing) {
                $data["indexing"] = $indexing;
            }

            if ($isFast) {
                $data['fast'] = $isFast;
            }

            $result = array(
                "initialization" => $data["offset"] < $data["total"],
                'progress' => $data
            );

            if (Debug::$status) {
                $result['debug'] = Debug::getResult();
            }

            return rest_ensure_response($result);
        } else if (is_array($fields)) {
            $isNewFields = false;

            foreach ($fields as $field) {
                $type = $field["type"];
                $name = trim($field["field"]);

                if (!$name) {
                    continue;
                }

                $customField = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->postmeta} AS pm WHERE pm.meta_key = %s LIMIT 1;", $name));

                if ($type === "order" && HPOS::getStatus() && !$customField) {
                    $customField = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}wc_orders_meta AS om WHERE om.meta_key = %s LIMIT 1;", $name));
                }

                if (!$customField && !key_exists($name, Database::$postsFields)) {
                    $key = $settings->getField("license", "key", "");
                    $url = "https://www.ukrsolution.com/ExtensionsSupport/Support?extension=24&version=1.6.4&pversion=" . $wp_version . "&d=" . base64_encode($key);
                    if ($type === "order") {
                        $message = __("Order's custom field \"{$name}\" not found. Please make sure you entered a correct database value or <a href='{$url}' target='_blank'>contact us</a> for help.", "us-barcode-scanner");
                    } else {
                        $message = __("Product's custom field \"{$name}\" not found. Please make sure you entered a correct database value or <a href='{$url}' target='_blank'>contact us</a> for help.", "us-barcode-scanner");
                    }

                    $result = array("error" => $message);

                    if (Debug::$status) {
                        $result['debug'] = Debug::getResult();
                    }

                    return rest_ensure_response($result);
                }

                $column = Database::addPostColumn($name);

                $isIndexed = $settings->getField("indexing", "indexed", false);

                if ($column["isNew"] || !$isIndexed) {
                    $isNewFields = true;
                }
            }

            if ($isNewFields) {
                $table = $wpdb->prefix . Database::$posts;
                $wpdb->query("UPDATE {$table} SET `updated` = '0000-00-00 00:00:00';");
                return rest_ensure_response(array("success" => 1, "isNewField" => 1));





                return rest_ensure_response($result);
            }
        }

        $result = array(
            "status" => "success",
        );

        if (Debug::$status) {
            $result['debug'] = Debug::getResult();
        }

        return rest_ensure_response($result);
    }

    public function saveSession(WP_REST_Request $request)
    {
        $session = $request->get_param("session");
        $sessionStamp = $request->get_param("sessionStamp");

        if (!$session || !$sessionStamp) {
            return rest_ensure_response(array("success" => false));
        }

        $settings = new Settings();

        $settings->updateField("session", "session", null);
        $settings->updateField("sessionStamp", "sessionStamp", null);
        $settings->updateSettings("session", $session, "text");
        $settings->updateSettings("sessionStamp", $sessionStamp, "text");

        $result = array("status" => "success");

        return rest_ensure_response($result);
    }

    public function saveSettings(WP_REST_Request $request)
    {
        $param = $request->get_param("param");

        $_POST["tab"] = $request->get_param("tab");
        $_POST[$param] = $request->get_param("value");

        $settings = new Settings();
        $settings->formListener();

        $result = array("status" => "success");

        return rest_ensure_response($result);
    }

    public function postsInitialization(WP_REST_Request $request)
    {
        global $wpdb;

        $progress = $request->get_param("progress");

        if ($progress) {
            $data = Database::updatePostsTable($progress["offset"], $progress["limit"]);

            return rest_ensure_response(array(
                "initialization" => $data["offset"] < $data["total"],
                'progress' => $data
            ));
        }

        $result = array(
            "status" => "success",
        );

        return rest_ensure_response($result);
    }

    public function backgroundIndexing(WP_REST_Request $request)
    {
        global $wpdb;

        if (mt_rand(0, 100) === 100) {
            $wpdb->query("DELETE FROM {$wpdb->prefix}barcode_scanner_posts WHERE post_id NOT IN(SELECT ID FROM {$wpdb->prefix}posts) LIMIT 1000;");
        }

                $settings = new Settings();
        $indexationStep = $settings->getSettings("indexationStep");
        $limit = $indexationStep && (int)$indexationStep->value ? (int)$indexationStep->value : 50;
        $result = Database::updatePostsTable(0, $limit, true);

        return rest_ensure_response($result);
    }

    public function indexingClearTable(WP_REST_Request $request)
    {
        Database::clearTableColumns();
        Database::initDataTableColumns();
        Database::removeTableProducts();
        Database::setupTableProducts(true, true);

        return rest_ensure_response(array("success" => 1));
    }
}
