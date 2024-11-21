<?php

use UkrSolution\BarcodeScanner\Core;

if (!defined('WP_USE_THEMES')) {
    define('WP_USE_THEMES', false);
}

if (!defined('DISABLE_WP_CRON')) {
    define('DISABLE_WP_CRON', true);
}

global $wp, $wp_query, $wp_the_query, $wp_rewrite, $wp_did_header;

$root = isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : "../../..";

if (file_exists($root . "/wp-includes/plugin.php")) {
    require($root . "/wp-includes/plugin.php");
} else {
    $root = __DIR__ . "/../../..";

    if (file_exists($root . "/wp-includes/plugin.php")) {
        require($root . "/wp-includes/plugin.php");
    } else {
        echo "/wp-includes/plugin.php is not fond!";
    }
}

require($root . "/wp-load.php");

$dt = new \DateTime("now");
$tempFileName = isset($_GET['fn']) ? sanitize_text_field($_GET['fn']) : null;

if ($tempFileName && current_user_can('administrator')) {
    $wp_upload_dir = wp_upload_dir();
    $upload_dir = $wp_upload_dir['basedir'] . '/barcode-scanner/';
    $csvFileName = "Barcode_scanner_logs_" . $dt->format("d-m-Y_h-i-s") . ".csv";
    $csvFilePath = $upload_dir . 'logs/' . $tempFileName;

    $files = glob($upload_dir . 'logs/' . "*.csv");

    $now = time();

    foreach ($files as $file) {
        if (is_file($file)) {
            if ($now - filemtime($file) >= 60 * 60 * 24 * 5) {
                unlink($file);
            }
        }
    }

    if (file_exists($csvFilePath)) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $csvFileName . '"');

        readfile($csvFilePath);
    } else {
        wp_redirect(admin_url('/admin.php?page=barcode-scanner-logs'));
    }
} else {
    wp_redirect(admin_url('/admin.php?page=barcode-scanner-logs'));
}

exit;
