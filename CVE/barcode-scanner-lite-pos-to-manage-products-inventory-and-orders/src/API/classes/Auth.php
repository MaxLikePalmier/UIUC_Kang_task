<?php

namespace UkrSolution\BarcodeScanner\API\classes;

use UkrSolution\BarcodeScanner\features\settings\Settings;
use UkrSolution\BarcodeScanner\features\settings\SettingsHelper;

class Auth
{
    public function login()
    {
        $data = array();

        try {
            $username = isset($_POST["username"]) ? $_POST["username"] : (isset($_GET["username"]) ? $_GET["username"] : "");
            $password = isset($_POST["password"]) ? $_POST["password"] : (isset($_GET["password"]) ? $_GET["password"] : "");

            $user = get_user_by('login', $username);

            if (!$user) {
                $user = get_user_by('email', $username);
            }


            if ($user && !is_wp_error($user) && isset($user->data) && wp_check_password($password, $user->data->user_pass, $user->ID)) {
                $settings = new Settings();

                $userSessions = $settings->getSettings("userSessions");
                if ($userSessions) {
                    $userSessions = $userSessions->value;
                }

                $siteUrl = get_site_url();

                $data["user"] = $user;
                $data["siteUrl"] = $siteUrl;
                $data["token"] = base64_encode("{$user->ID}{$siteUrl}");
                $data["userSessions"] = $userSessions;
                $data["systemInfo"] = $this->getSystemInfo($user);

            }
        } catch (\Throwable $th) {
            $data = array("error" => $th->getMessage());
        }

        (new Results())->jsonResponse($data);
    }

    public function loginOtp()
    {
        global $wpdb;

        $data = array();

        try {
            $password = isset($_POST["password"]) ? $_POST["password"] : (isset($_GET["password"]) ? trim($_GET["password"]) : "");
            $user = null;

            if ($password) {
                $p = strlen($password) < 40 ? md5(strtoupper($password)) : $password;
                $userMeta = $wpdb->get_row("SELECT UM.meta_key, UM.meta_value, UM.user_id FROM {$wpdb->usermeta} AS UM WHERE UM.meta_key IN ('barcode_scanner_app_otp') AND UM.meta_value = '{$p}'");

                if ($userMeta && $userMeta->user_id) {
                    $user = get_user_by("ID", $userMeta->user_id);
                }


                $expired = $user ? get_user_meta($user->ID, "barcode_scanner_app_otp_expired_dt", true) : null;

                if ($expired && (time() - $expired) > 60 * 60 * 24 * 30) {
                    $user = null;
                }
            }

            if ($user && !is_wp_error($user) && isset($user->data)) {
                $settings = new Settings();

                $userSessions = $settings->getSettings("userSessions");
                if ($userSessions) {
                    $userSessions = $userSessions->value;
                }

                $siteUrl = get_site_url();

                $data["user"] = $user;
                $data["siteUrl"] = $siteUrl;
                $data["token"] = $password;
                $data["userSessions"] = $userSessions;
                $data["systemInfo"] = $this->getSystemInfo($user);

                if (strlen($password) < 10) {
                    $password = md5(SettingsHelper::generateRandomString(20)) . md5(mt_rand(1000000, 9999999));
                    update_user_meta($user->ID, "barcode_scanner_app_otp", $password);
                    update_user_meta($user->ID, "barcode_scanner_app_otp_expired_dt", "");
                    $data["password"] = $password;
                    $data["token"] = $password;
                }
            } else if (!$user) {
                $data["securityIssue"] = 1;
            }
        } catch (\Throwable $th) {
            $data = array("error" => $th->getMessage());
        }

        (new Results())->jsonResponse($data);
    }

    public function loginLink()
    {
        $data = array();

        try {
            $userToken = isset($_POST["userToken"]) ? $_POST["userToken"] : (isset($_GET["userToken"]) ? $_GET["userToken"] : "");
            $user = null;

            if ($userToken) {
                $users = get_users(array('meta_key' => 'scanner-app-token', 'meta_value' => $userToken));

                if ($users) {
                    $user = $users[0];
                }
            }

            if ($user && $user->ID) {
                $siteUrl = get_site_url();

                $data["user"] = $user;
                $data["token"] = base64_encode("{$user->ID}{$siteUrl}");
                $data["settings"] = array(
                    "rest_root" => \rest_url(),
                );
                $data["systemInfo"] = $this->getSystemInfo($user);
            } else {
                $data["code"] = 503;
            }
        } catch (\Throwable $th) {
            $data = array("error" => $th->getMessage());
        }

        (new Results())->jsonResponse($data);
    }

    public function check($token, $userToken = "", $userSession = "")
    {
        global $wpdb;
        $settings = new Settings();

        if ($userToken) {
            $user = null;
            $userMeta = $wpdb->get_row(
                $wpdb->prepare("SELECT UM.meta_key, UM.meta_value, UM.user_id FROM {$wpdb->usermeta} AS UM WHERE UM.meta_key IN ('barcode_scanner_app_otp', 'barcode_scanner_web_otp') AND UM.meta_value = '%s';", $userToken)
            );

            if ($userMeta && $userMeta->user_id) {
                $user = get_user_by("ID", $userMeta->user_id);
            }

            return $userMeta && $userMeta->user_id && $user;
        } else if ($token && !in_array($token, array("web", "usersFind"))) {
            $userMeta = $wpdb->get_row(
                $wpdb->prepare("SELECT UM.meta_key, UM.meta_value, UM.user_id FROM {$wpdb->usermeta} AS UM WHERE UM.meta_key IN ('barcode_scanner_app_otp', 'barcode_scanner_web_otp') AND UM.meta_value = '%s';", $token)
            );

            if ($userMeta && $userMeta->user_id) {
                return true;
            }

            $data = @base64_decode($token);

            if (preg_match('/([\d]+).*/', $data, $m)) {
                $userId = count($m) === 2 && (int)$m[1] ? $m[1] : 0;

                if ($userSession && (int)$userSession === (int)$userId) {
                    return true;
                }

                $users = $settings->getAppUsersPermissions();

                foreach ($users as $user) {
                    if ($user->ID == $userId && $user->get($settings->userAppPermissionKey)) return true;
                }

                if (!$users) {
                    $users = get_users(array('meta_key' => 'barcode_scanner_app_otp', 'meta_value' => $token));
                    return count($users) > 0 && $users[0]->ID;
                }
            }

            return false;
        }

        return false;
    }

    public function getUserId($token, $userToken = "", $userSession = "")
    {
        global $wpdb;
        $settings = new Settings();

        if ($userToken) {
            $user = null;
            $userMeta = $wpdb->get_row("SELECT UM.meta_key, UM.meta_value, UM.user_id FROM {$wpdb->usermeta} AS UM WHERE UM.meta_key IN ('barcode_scanner_app_otp') AND UM.meta_value = '{$userToken}'");

            if ($userMeta && $userMeta->user_id) {
                $user = get_user_by("ID", $userMeta->user_id);
            }

            return $userMeta && $userMeta->user_id && $user ? $userMeta->user_id : null;

        } else if ($token && !in_array($token, array("web", "usersFind"))) {
            $userMeta = $wpdb->get_row("SELECT UM.meta_key, UM.meta_value, UM.user_id FROM {$wpdb->usermeta} AS UM WHERE UM.meta_key IN ('barcode_scanner_app_otp') AND UM.meta_value = '{$token}'");

            if ($userMeta && $userMeta->user_id) {
                return $userMeta->user_id;
            }

            $data = @base64_decode($token);

            if (preg_match('/([\d]+).*/', $data, $m)) {
                $userId = count($m) === 2 && (int)$m[1] ? $m[1] : 0;


                if ($userSession && (int)$userSession === (int)$userId) {
                    return $userId;
                }

                $users = $settings->getAppUsersPermissions();

                foreach ($users as $user) {
                    if ($user->ID == $userId && $user->get($settings->userAppPermissionKey)) return true;
                }

                if (!$users) {
                    $users = get_users(array('meta_key' => 'barcode_scanner_app_otp', 'meta_value' => $token));
                    return count($users) > 0 && $users[0]->ID ? $users[0]->ID : null;
                }
            }

            return null;
        }

        return null;
    }

    private function getSystemInfo($user)
    {
        global $wp_version;

        $result = array();

        $siteUrl = \get_site_url();
        $protocol = preg_replace('/:\/\/(.*)/', '', \get_option('siteurl'));
        $domain = preg_replace('/(.*)\/\//', '', $siteUrl);

        try {
            $result = array(
                "username" => $user->display_name ? $user->display_name : $user->user_nicename,
                "website" => $domain,
                "protocol" => $protocol,
                "pluginVersion" => "1.6.4",
                "wpVersion" => $wp_version,
                "wooVersion" => $this->getWooVersion(),
                "phpVersion" => phpversion(),
                "pluginBuild" => 1,
            );
        } catch (\Throwable $th) {
            throw $th;
        }

        return $result;
    }

    private function getWooVersion()
    {
        if (!function_exists('get_plugins'))
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');

        $plugin_folder = get_plugins('/' . 'woocommerce');
        $plugin_file = 'woocommerce.php';

        if (isset($plugin_folder[$plugin_file]['Version'])) {
            return $plugin_folder[$plugin_file]['Version'];
        } else {
            return NULL;
        }
    }
}
