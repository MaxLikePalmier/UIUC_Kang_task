<?php

namespace UkrSolution\BarcodeScanner\API\classes;

use UkrSolution\BarcodeScanner\features\settings\Settings;

class Emails
{
    static public function sendLowStock($productId, $qty, $productName, $thershold)
    {
        try {

            $settings = new Settings();
            $notifyUsersStock = $settings->getSettings("notifyUsersStock");
            $usersIds = $notifyUsersStock === null ? "" : $notifyUsersStock->value;
            $usersIds = $usersIds ? explode(",", $usersIds) : array();

            if ($usersIds) {
                $subject = __("Low stock inventory", "us-barcode-scanner") . "({$qty}) - {$productName}";

                $linkFront = '<a href="' . get_permalink($productId) . '">' . $productName . '</a>';
                $message = sprintf(__('Product "%s" has reached', "us-barcode-scanner"), $linkFront);

                $urlBack = get_edit_post_link($productId);
                $message .= ' <a href="' . $urlBack . '">' . sprintf(__('stock quantity %s', "us-barcode-scanner"), $qty) . '</a>';

                $message .= sprintf(__('<br/><i>The stock threshold for this product is %s</i>', "us-barcode-scanner"), $thershold);

                foreach ($usersIds as $userId) {
                    if ($userId) {
                        $user = get_userdata($userId);
                        if ($user) {

                            $headers = array('Content-Type: text/html; charset=UTF-8');
                            wp_mail($user->user_email,  $subject, $message, $headers);
                        }
                    }
                }
            }
        } catch (\Throwable $th) {
        }
    }

    static public function mailerTrigger($orderId, $status)
    {
        return;
        try {
            $mailer = \WC()->mailer();
            $emails = $mailer->get_emails();

                        foreach ($emails as $trigger => $email) {
                $statusToCheck = str_replace("wc-", "", $status);
                $statusToCheck = str_replace("-", "_", $statusToCheck);

                if (stripos($trigger, $statusToCheck) !== false) {
                    var_dump('Checking email: ' . $email->id . ' for order ' . $orderId);
                    if ($email->trigger($orderId)) {
                        var_dump('Email sent: ' . $email->id . ' for order ' . $orderId);
                    } else {
                        var_dump('Failed to send email: ' . $email->id . ' for order ' . $orderId);
                    }
                }
            }
        } catch (\Throwable $th) {
        }
    }
}
