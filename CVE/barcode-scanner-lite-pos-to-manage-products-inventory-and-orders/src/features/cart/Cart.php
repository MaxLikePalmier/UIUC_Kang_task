<?php

namespace UkrSolution\BarcodeScanner\features\cart;

use UkrSolution\BarcodeScanner\features\settings\Settings;

class Cart
{
    public $filter_cart_shipping_cost = "scanner_filter_cart_shipping_cost";

    function __construct() {}

    public function getShippingMethods($userId = null, $taxAddress = array())
    {
        $shippingZoneMethods = array();
        $shippingZoneMethods[] = array(
            "id" => 0,
            "title" => __("No shipping", "us-barcode-scanner"),
            "cost" => 0,
            "instance_id" => '',
        );

        try {
            if (!function_exists("WC")) {
                return array();
            }

            if (is_null(\WC()->cart)) {
                @wc_load_cart();
            }

            $shipping_packages = array();

            $country = isset($taxAddress["country"]) ? $taxAddress["country"] : "";
            $state = isset($taxAddress["state"]) ? $taxAddress["state"] : "";
            $city = isset($taxAddress["city"]) ? $taxAddress["city"] : "";
            $postcode = isset($taxAddress["postcode"]) ? $taxAddress["postcode"] : "";

            if (!$country && !$state && !$city && !$postcode) {
                $country = get_option('woocommerce_default_country', '');
                $countryParts = explode(':', $country);
                $country = $countryParts[0];
                $state = get_option('woocommerce_store_state', '');
                $city = get_option('woocommerce_store_city', '');
                $postcode = get_option('woocommerce_store_postcode', '');

                if (function_exists("WC")) {
                    $city = WC()->countries->get_base_city();
                    $state = WC()->countries->get_base_state();
                    $postcode = WC()->countries->get_base_postcode();
                    $country = WC()->countries->get_base_country();
                }
            }

            if ($country || $state || $city || $postcode) {
                $shipping_packages[] = array(
                    "contents" => array(),
                    "contents_cost" => 0,
                    "applied_coupons" => array(),
                    "user" => array("ID" => $userId),
                    "destination" => array(
                        "country" => $country,
                        "state" => $state,
                        "postcode" => $postcode,
                        "city" => $city,
                    ),
                    "cart_subtotal" => 0,
                );
            }

            if ($shipping_packages) {
                $shipping_zone = \wc_get_shipping_zone(reset($shipping_packages));
                $shippingMethods = $shipping_zone ? $shipping_zone->get_shipping_methods(true) : null;

                if ($shippingMethods) {
                    foreach ($shippingMethods as $method) {
                        $title = __(strip_tags($method->title), 'woocommerce');
                        $title = stripslashes($title);

                        $cost = isset($method->cost) ? apply_filters($this->filter_cart_shipping_cost, $method->cost, $method) : 0;
                        $cost = strip_tags($cost);
                        $cost = stripslashes($cost);

                        if (isset($method->cost) && $method->cost) {
                            $cost = str_replace(",", ".", $cost);
                            $title .= " - " .  strip_tags(\wc_price($cost));
                        }

                        $shippingZoneMethods[] = array(
                            "id" => $method->id,
                            "title" => $title,
                            "cost" => $cost,
                            "instance_id" => isset($method->instance_id) ? $method->instance_id : '',
                        );
                    }
                }
            }
        } catch (\Throwable $th) {
            throw $th;
        }

        return $shippingZoneMethods;
    }

    public function getAllShippingMethods()
    {
        $uniqMethods = array();
        $uniqMethods[] = array("id" => 0, "instance_id" => 0, "title" => __("No shipping", "us-barcode-scanner"));

        try {
            if (!function_exists("WC")) {
                return array();
            }

            if (is_null(\WC()->cart)) {
                @wc_load_cart();
            }

            $shipping_zones = \WC_Shipping_Zones::get_zones();

            $addedMethods = array();

            foreach ($shipping_zones as $zone) {
                $shipping_methods = $zone['shipping_methods'];

                $result[$zone['id']] = array(
                    'name' => $zone['zone_name'],
                    'methods' => array(),
                );

                foreach ($shipping_methods as $method_id =>  $method) {
                    $title = $method->get_method_title();
                    $title = __(strip_tags($title), 'woocommerce');

                    if (!in_array($method->id, $addedMethods)) {
                        $uniqMethods[] = array("id" => $method->id, "instance_id" => $method->instance_id, "title" => $title);
                        $addedMethods[] = $method->id;
                    }
                }
            }
        } catch (\Throwable $th) {
            throw $th;
        }

        return $uniqMethods;
    }

    public function getPaymentMethods()
    {
        if (!function_exists("WC")) {
            return array();
        }

        $enabledGateways = [];

        try {
            $paymentGateways = WC()->payment_gateways->get_available_payment_gateways();

            if ($paymentGateways) {
                foreach ($paymentGateways as $gateway) {
                    if ($gateway->enabled == 'yes') {
                        $title = __(strip_tags($gateway->title), 'woocommerce');
                        $title = stripslashes($title);
                        $enabledGateways[] = array(
                            "id" => $gateway->id,
                            "title" => $title
                        );
                    }
                }
            }
        } catch (\Throwable $th) {
        }

        return $enabledGateways;
    }
}
