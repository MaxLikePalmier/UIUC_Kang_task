<?php

namespace UkrSolution\BarcodeScanner\API\actions;

use UkrSolution\BarcodeScanner\API\RequestHelper;
use UkrSolution\BarcodeScanner\features\interfaceData\InterfaceData;
use UkrSolution\BarcodeScanner\features\logs\LogActions;
use UkrSolution\BarcodeScanner\features\settings\Settings;
use UkrSolution\BarcodeScanner\features\settings\SettingsHelper;
use WP_REST_Request;

class UsersActions
{
    public function find(WP_REST_Request $request)
    {
        global $wpdb;

        $query = RequestHelper::getQuery($request, "user_find");
        $users = array();
        $errors = array();

        $currentIds = $request->get_param("currentIds");
        $currentIds = $currentIds && is_array($currentIds) ? array_map('intval', $currentIds) : array();

        try {
            $sql = "SELECT * FROM {$wpdb->users} as u ";
            $sql .= " WHERE u.user_nicename LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s ";

            if (count($currentIds) > 0) {
                $ids = implode(",",  $currentIds);
                $sql .= "  OR ID IN({$ids}) ";
            }

            $sql .= " LIMIT 10 ;";
            $rows = $wpdb->get_results(
                $wpdb->prepare($sql, '%' . $wpdb->esc_like($query) . '%', '%' . $wpdb->esc_like($query) . '%', '%' . $wpdb->esc_like($query) . '%')
            );

            foreach ($rows as $value) {
                $userMeta = get_userdata($value->ID);
                $roles =  array();

                if ($userMeta->roles) {
                    foreach ($userMeta->roles as $_role) {
                        if (is_string($_role)) {
                            $roles[] = $_role;
                        }
                    }
                }


                $users[] = array(
                    'ID' => $value->ID,
                    'user_login' => $value->user_login,
                    'user_nicename' => $value->user_nicename,
                    'display_name' => $value->display_name . " (" . $value->user_login . ")",
                    'full_name' => trim(get_user_meta($value->ID, 'first_name', true) . " " . get_user_meta($value->ID, 'last_name', true)),
                    'email' => $value->user_email,
                    'avatar' => get_avatar_url($value->ID),
                    'roles' => $roles,
                );
            }
        } catch (\Throwable $th) {
            $errors[] = $th->getMessage();
        }

        $result = array(
            "users" => $users,
            "usersErrors" => $errors,
            "createNew" => count($users) === 0
        );

        return rest_ensure_response($result);
    }

    public function createUser(WP_REST_Request $request)
    {
        global $wpdb;

        $userData = $request->get_param("userData");

        if (!isset($userData["username"])) {
            return rest_ensure_response(array("error" => esc_html__("Username is required", "us-barcode-scanner")));
        }


        $settings = new Settings();
        $orderCreateEmail = $settings->getField("general", "orderCreateEmail", "on");
        $shippingAsBilling = isset($userData["shipping_as_billing"]) && $userData["shipping_as_billing"] == 1;

        $userId = wp_create_user($userData["username"], md5($userData["username"]), $userData["email"]);
        if (is_wp_error($userId)) {
            return rest_ensure_response(array("error" => $userId->get_error_message()));
        } else if ($orderCreateEmail === "on") {
            wp_new_user_notification($userId, null, 'both');
        }

        if ($userId) {
            $keysList = array(
                'first_name', 'last_name',
                'billing_first_name', 'billing_last_name', 'billing_company', 'billing_phone', 'billing_address_1', 'billing_address_2',
                'billing_city', 'billing_postcode', 'billing_country', 'billing_state', 'billing_email',
                'shipping_first_name', 'shipping_last_name', 'shipping_company', 'shipping_phone', 'shipping_address_1', 'shipping_address_2',
                'shipping_city', 'shipping_postcode', 'shipping_country', 'shipping_state'
            );

            foreach (InterfaceData::getUserFields() as $value) {
                if (isset($value['_name'])) {
                    $keysList[] = $value['_name'];
                }
            }

            foreach ($userData as $key => $value) {
                if (in_array($key, $keysList)) {
                    if ($shippingAsBilling && preg_match("/shipping_.*/", $key)) {
                        continue;
                    } else {
                        \update_user_meta($userId, $key, $value);
                    }

                    if ($shippingAsBilling && preg_match("/billing_.*/", $key)) {
                        $shippingKey = str_replace("billing_", "shipping_", $key);
                        \update_user_meta($userId, $shippingKey, $value);
                    }
                }
            }
        }

        $user = array();

        try {
            $sql = "SELECT * FROM {$wpdb->users} as u WHERE u.ID = {$userId};";
            $row = $wpdb->get_row($sql);

            $user = array(
                'ID' => $row->ID,
                'user_nicename' => $row->user_nicename,
                'display_name' => $row->display_name,
            );

            LogActions::add($row->ID, LogActions::$actions["create_user"], "", "", "", "user", $request);
        } catch (\Throwable $th) {
            $errors[] = $th->getMessage();
        }

        return rest_ensure_response(array("user" => $user));
    }

    public function getStates(WP_REST_Request $request)
    {
        $country = $request->get_param("country");
        $states = array();

        try {
            $countries_obj   = new \WC_Countries();
            $states = $countries_obj->get_states($country);
        } catch (\Throwable $th) {
            $errors[] = $th->getMessage();
        }

        return rest_ensure_response(array("states" => $states));
    }

    public function updatePassword(WP_REST_Request $request)
    {
        $userId = $request->get_param("userId");
        $errors = array();
        $password = "";

        try {
            $user = get_user_by('ID', $userId);
            if (!$user) {
                return rest_ensure_response(array(
                    "states" => false,
                    "errors" => ["User not found."]
                ));
            }
            $password = $this->usersGenerateOtp();
            update_user_meta($userId, "barcode_scanner_app_otp", md5($password));
            update_user_meta($userId, "barcode_scanner_app_otp_expired_dt", time());
        } catch (\Throwable $th) {
            $errors[] = $th->getMessage();
        }

        return rest_ensure_response(array(
            "states" => true,
            "password" => $password,
            "userId" => $userId,
            "errors" => $errors,
            "users" => $this->getUsersOtpStatus()
        ));
    }

    public function usersGenerateOtp()
    {
        return strtoupper(SettingsHelper::generateRandomString(3)) . mt_rand(100, 999);
    }

    public function getUsersOtpStatus()
    {
        global $wpdb;

        $users = array();

        $usersMeta = $wpdb->get_results("SELECT * FROM {$wpdb->usermeta} AS UM WHERE UM.meta_key IN ('barcode_scanner_app_otp')");

        foreach ($usersMeta as $value) {
            $users[$value->user_id] = $value->meta_value;
        }

        return $users;
    }

    public function getUsersOtpExpired()
    {
        global $wpdb;

        $users = array();

        $usersMeta = $wpdb->get_results("SELECT * FROM {$wpdb->usermeta} AS UM WHERE UM.meta_key IN ('barcode_scanner_app_otp_expired_dt')");

        try {
            foreach ($usersMeta as $value) {
                if ($value->meta_value) {
                    $users[$value->user_id] = (time() - $value->meta_value) > 60 * 60 * 24 * 30 ? 1 : 0;
                }
            }
        } catch (\Throwable $th) {
        }

        return $users;
    }
}
