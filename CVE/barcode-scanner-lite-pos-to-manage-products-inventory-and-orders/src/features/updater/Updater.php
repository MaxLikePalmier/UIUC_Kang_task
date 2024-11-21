<?php

namespace UkrSolution\BarcodeScanner\features\updater;

use UkrSolution\BarcodeScanner\features\settings\Settings;

class Updater
{
    public function __construct()
    {
        $this->initServer();
    }

    private function initServer()
    {
        add_action('init', function () {
            try {
                $prefix = "";
                $settings = new Settings();

                $pluginData = \get_plugin_data(plugin_dir_path(dirname(__FILE__, 3)) . "/barcode-scanner.php");
                $pluginVersion = $pluginData['Version'];
                $pluginRemotePath = 'https://www.ukrsolution.com/CheckUpdates/BarcodesForWordpressV3.json';
                $pluginSlug = basename(plugin_dir_path(dirname(__FILE__, 3))) . "/barcode-scanner.php";
                $licenseUser = 'b75366e2da25ff8ad2a22f0b1c1739f9';
                $licenseKey = $settings->getField("license", $prefix . "key");

                new WpAutoUpdate($pluginVersion, $pluginRemotePath, $pluginSlug, $licenseUser, $licenseKey);
            } catch (\Throwable $th) {
            }
        }, 1);
    }
}
