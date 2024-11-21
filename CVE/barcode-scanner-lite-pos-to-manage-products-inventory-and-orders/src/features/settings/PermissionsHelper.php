<?php

namespace UkrSolution\BarcodeScanner\features\settings;

use UkrSolution\BarcodeScanner\API\classes\Users;
use WP_REST_Request;

class PermissionsHelper
{
    private static $userId = null;
    private static $userPermissions = array();

    public static function init(WP_REST_Request $request)
    {
        $settings = new Settings();
        self::$userId = Users::getUserId($request);
        self::$userPermissions = $settings->getUserRolePermissions(self::$userId);
    }

    public static function setUser($userId)
    {
        if ($userId) {
            $settings = new Settings();
            self::$userId = $userId;
            self::$userPermissions = $settings->getUserRolePermissions(self::$userId);
        }
    }

    public static function onePermRequired($permissions, $isReturn = false)
    {
        foreach ($permissions as $item) {
            if (isset(self::$userPermissions[$item]) && self::$userPermissions[$item] == 1) {
                return true;
            }
        }

        return self::permissionDenied($isReturn);
    }

    public static function allPermsRequired($permissions)
    {
        foreach ($permissions as $item) {
            if (!isset(self::$userPermissions[$item]) || self::$userPermissions[$item] != 1) {
                self::permissionDenied();
            }
        }

        return true;
    }

    public static function roleRequired($roles, $isReturn = false)
    {
        $userMeta = get_userdata(self::$userId);

        foreach ($roles as $item) {
            if ($userMeta && in_array($item, $userMeta->roles)) {
                return true;
            }
        }

        if ($isReturn) {
            return array("message" => esc_html__("Access denied, please ask your administrator to grant access in the Barcode Scanner -> Settings -> Permission tab.", "us-barcode-scanner"));
        } else {
            self::permissionDenied();
        }
    }

    private static function permissionDenied($isReturn = false)
    {
        if (function_exists("http_response_code")) {
            http_response_code(403);
        } else {
            header("HTTP/1.1 403 Access denied");
        }

        if ($isReturn) {
            return array("message" => esc_html__("Access denied, please ask your administrator to grant access in the Barcode Scanner -> Settings -> Permission tab.", "us-barcode-scanner"));
        } else {
            echo json_encode(array("message" => esc_html__("Access denied, please ask your administrator to grant access in the Barcode Scanner -> Settings -> Permission tab.", "us-barcode-scanner")));
            exit;
        }
    }
}
