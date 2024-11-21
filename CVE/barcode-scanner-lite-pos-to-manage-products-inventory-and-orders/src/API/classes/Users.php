<?php

namespace UkrSolution\BarcodeScanner\API\classes;

class Users
{
    private static $userId = 0;
    private static $userRole = '';

    public static function setUserId($userId)
    {
        self::$userId = $userId;
    }

    public static function userId()
    {
        return self::$userId ? self::$userId : get_current_user_id();
    }

    public static function setUserRole($userRole)
    {
        self::$userRole = $userRole;
    }

    public static function userRole()
    {
        $role = self::userId() ? Users::getUserRole(self::userId()) : '';
        return self::$userRole ? self::$userRole : $role;
    }

    public static function getUserId($request)
    {
        global $wpdb;

        $userId = get_current_user_id();
        $token = $request->get_param("token");

        if (!$userId && $token) {

            try {
                if (preg_match("/^([0-9]+)/", @base64_decode($token), $m)) {
                    if ($m && count($m) > 0 && is_numeric($m[0])) {
                        $userId = $m[0];
                    }
                } else {
                    $meta = $wpdb->get_row("SELECT * FROM {$wpdb->usermeta} WHERE meta_key = 'barcode_scanner_app_otp' AND meta_value = '{$token}';");
                    $userId = $meta ? $meta->user_id : $userId;
                }
            } catch (\Throwable $th) {
            }
        }

        return $userId;
    }

    public static function getUserRole($userId)
    {
        global $wpdb;

        $user = get_user_by('ID', $userId);
        $roles = $user && isset($user->roles) ? (array)$user->roles : array();
        return count($roles) ? $roles[0] : '';
    }

    public static function getUToken($userId)
    {
        try {
            $token = get_user_meta($userId, 'barcode_scanner_web_otp', true);

            if ($token) {
                return $token;
            }
            else {
                $token = md5(time());
                update_user_meta($userId, 'barcode_scanner_web_otp', $token);
                return $token;
            }
        } catch (\Throwable $th) {
            return "";
        }
    }
}
