<?php

namespace UkrSolution\BarcodeScanner\features\locations;

use UkrSolution\BarcodeScanner\Database;

class LocationsData
{
    private static $alreadyGet = false;
    private static $locationsFormatted = array();

    public static function getLocations()
    {
        global $wpdb;

        if (self::$alreadyGet) {
            return self::$locationsFormatted;
        }

        $table = $wpdb->prefix . Database::$locationsTree;
        $data = $wpdb->get_results("SELECT * FROM {$table} AS L WHERE L.is_removed IS NULL ORDER BY L.order ASC;");
        $locations = array();

        foreach ($data as $value) {
            $locations[$value->id] = array("id" => $value->id, "name" => $value->name, "parent" => $value->parent_id);
        }

        self::$locationsFormatted = $locations;
        self::$alreadyGet = true;

        return self::$locationsFormatted;
    }

    public static function saveLocations($options)
    {
        global $wpdb;

        if (!$options || !is_array($options)) {
            return;
        }

        $table = $wpdb->prefix . Database::$locationsTree;
        $order = 0;

        $parentIds = array();

        $wpdb->update($table, array("is_removed" => 1), array("is_removed" => null));

        foreach ($options as $id => $option) {
            $data = array("name" => $option["name"], "order" => $order += 1, "is_removed" => null, "updated" => date("Y-m-d H:i:s"));

            if ($option["parent"]) {
                $data["parent_id"] = isset($parentIds[$option["parent"]]) ? $parentIds[$option["parent"]] : $option["parent"];
            } else {
                $data["parent_id"] = null;
            }

            if ($id > 100000000) {
                $wpdb->insert($table, $data);
                $parentIds[$id] = $wpdb->insert_id;
            }
            else {
                $wpdb->update($table, $data, array("id" => $id));
            }
        }
    }

    public static function displaySettingsAdminList($locations, $options, $viewPath)
    {
        require $viewPath;
    }
}
