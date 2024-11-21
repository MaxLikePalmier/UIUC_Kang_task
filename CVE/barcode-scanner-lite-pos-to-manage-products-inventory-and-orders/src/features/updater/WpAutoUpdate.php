<?php

namespace UkrSolution\BarcodeScanner\features\updater;

if (!class_exists("WpAutoUpdate")) {

    class WpAutoUpdate
    {
        private $current_version;
        private $update_path;
        private $plugin_path;
        private $plugin_slug;
        private $slug;
        private $session_id;
        private $license_key;
        private $loaded = false;
        private $loadedData = null;

        public function __construct($current_version, $update_path, $plugin_slug, $session_id = '', $license_key = '')
        {
            $this->current_version = $current_version;
            $this->update_path = $update_path;
            $this->loaded = false;
            $this->plugin_path = plugin_dir_path(dirname(__FILE__, 3));

            $this->session_id = $session_id;
            $this->license_key = $license_key;

            $this->plugin_slug = $plugin_slug;
            list($t1, $t2) = explode('/', $plugin_slug);
            $this->slug = str_replace('.php', '', $t2);

            if (is_admin()) {
                add_filter('site_transient_update_plugins', array(&$this, 'checkUpdate'));
            }

            add_filter('plugins_api', array(&$this, 'checkInfo'), 10, 3);

            add_action('admin_init', function () {
                $user_id = get_current_user_id();

                if (isset($_GET['usbs-plugin-dismissed'])) {
                    update_option($user_id . '_' . basename($this->plugin_path) . '_notice_dismissed', 'true', true);
                }
            });
        }

        public function checkUpdate($transient)
        {
            $isNewVersion = false;

            try {
                if (empty($transient->checked)) {
                    return $transient;
                } 
                else if (isset($transient->checked[$this->plugin_slug])) {
                    unset($transient->checked[$this->plugin_slug]);
                }

                if ($this->loaded === false) {
                    $remoteCache = get_transient('ukrsolution_upgrade_scanner_' . $this->current_version);

                    if ($remoteCache) {
                        $remoteData = $remoteCache;
                    } else {
                        $remoteData = $this->getRemote('version');

                        if ($remoteData && !is_wp_error($remoteData) && isset($remoteData->new_version)) {
                            set_transient('ukrsolution_upgrade_scanner_' . $this->current_version, $remoteData, 86400);
                        }
                    }

                    if ($remoteData && version_compare($this->current_version, $remoteData->new_version, '<')) {
                        $obj = new \stdClass();
                        $obj->slug = basename($this->plugin_path); 
                        $obj->plugin_name = basename($this->plugin_path);
                        $obj->name = 'Barcode Scanner with Inventory & Order Manager - (business)';
                        $obj->new_version = $remoteData->new_version;
                        $obj->version = $remoteData->new_version;
                        $obj->url = $remoteData->url;
                        $obj->plugin = basename($this->plugin_path); 
                        $obj->package = strlen($remoteData->package) < 50 ? "" : $remoteData->package;
                        $obj->tested = $remoteData->tested;

                        if (isset($remoteData->icons)) {
                            $obj->icons = $remoteData->icons;
                        }

                        $transient->response[$this->plugin_slug] = $obj;
                        $this->loadedData = $obj;
                        $isNewVersion = true;
                    }

                    if (isset($remoteData->update_error_message) && $remoteData->update_error_message) {
                        add_action('admin_notices', function () use ($remoteData) {
                            $user_id = get_current_user_id();

                            if (basename($this->plugin_path) === $remoteData->plugin && get_option($user_id . '_' . basename($this->plugin_path) . '_notice_dismissed') !== "true") {
                                $class = 'notice notice-error ';
                                $url = admin_url("admin.php?page=bs-redirect&usbs-plugin-dismissed");
                                printf('<div style="position: relative;" class="%1$s"><p>Barcode Scanner Plugin: %2$s</p><a href="' . esc_url($url) . '"><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></a></div>', esc_attr($class), wp_kses_post($remoteData->update_error_message));
                            }
                        });
                    }

                    $this->loaded = true;
                } elseif ($this->loadedData) {
                    $transient->response[$this->plugin_slug] = $this->loadedData;
                }

                if (!$isNewVersion && isset($transient->response[$this->plugin_slug])) {
                    unset($transient->response[$this->plugin_slug]);
                }

                return $transient;
            } catch (\Throwable $th) {
                return $transient;
            }
        }

        public function checkInfo($obj, $action, $arg)
        {
            if (isset($arg->slug) && $arg->slug !== basename($this->plugin_path)) {
                return $obj;
            }

            if ($action == 'plugin_information') {
                return $this->getRemote('info');
            }

            return $obj;
        }

        public function getRemote($action = '')
        {
            $params = array(
                'timeout' => 10,
                'body' => array(
                    'action' => $action,
                    'session_id' => $this->session_id,
                    'license_key' => $this->license_key,
                    'domain' => get_site_url(),
                ),
            );

            $request = wp_remote_post($this->update_path, $params);

            if (!is_wp_error($request) && wp_remote_retrieve_response_code($request) === 200) {
                $serverData = @unserialize($request['body']);
                $serverData->slug = basename($this->plugin_path);
                $serverData->plugin = basename($this->plugin_path);
                $serverData->plugin_name = basename($this->plugin_path);
                return $serverData;
            } else {
                $folder = basename($this->plugin_path);
                $obj = new \stdClass();
                $obj->slug = $folder;
                $obj->name = 'Barcode Scanner with Inventory & Order Manager - (business)';
                $obj->plugin_name = $folder;
                $obj->new_version = '';
                $obj->tested = '';
                $obj->url = 'https://www.ukrsolution.com/Wordpress/WooCommerce-Barcode-QRCode-Scanner-Reader';
                $obj->package = '';
                $obj->requires = '5.0';
                $obj->downloaded = 0;
                $obj->last_updated = "";
                $obj->sections = array(
                    'description' => 'To upgrade plugin:<br><br>
                    1. Go to your account on <a href="https://www.ukrsolution.com/Wordpress/WooCommerce-Barcode-QRCode-Scanner-Reader" target="_blank">www.ukrsolution.com</a><br>
                    2. Download new version.<br>
                    3. Remove old one from your wordpress.<br>
                    4. Install new version.<br>
                    5. All setting from old to new version will be transferred automatically.',
                );
                $obj->download_link = $obj->package;
                return $obj;
            }

            return false;
        }
    }
}
