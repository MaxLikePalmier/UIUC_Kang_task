<?php

namespace UkrSolution\BarcodeScanner\API\classes;

class OrdersHelper
{
    public static function getCustomerName($order)
    {
        $name = "";

        if ($order) {
            $name = $order->get_billing_first_name() . " " . $order->get_billing_last_name();
            $name = trim($name);

            if (!$name && $order->get_customer_id()) {

                $user = get_user_by("ID", $order->get_customer_id());

                if ($user) {
                    $name = $user->display_name ? $user->display_name : $user->user_nicename;
                }
            }
        }

        return $name;
    }
}
