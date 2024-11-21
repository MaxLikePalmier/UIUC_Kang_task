<?php

namespace UkrSolution\BarcodeScanner\API\classes;

use UkrSolution\BarcodeScanner\API\actions\CartScannerActions;
use UkrSolution\BarcodeScanner\API\actions\ManagementActions;
use UkrSolution\BarcodeScanner\API\PluginsHelper;
use UkrSolution\BarcodeScanner\features\settings\Settings;

class Integrations
{
    private $coreInstance = null;

    public function __construct($coreInstance)
    {
        try {
            $this->coreInstance = $coreInstance;

            add_action('init', array($this, "woocommerceWholesalePricing"));
            if (PluginsHelper::is_plugin_active('atum-stock-manager-for-woocommerce/atum-stock-manager-for-woocommerce.php')) {
                add_action('init', array($this, "atumStockManagerForWoocommerce"));
            }
            add_action('init', array($this, "dokan"));
            add_action('init', array($this, "checkoutFeesForWoocommerce"));

            add_action('init', array($this, "userAcfFields"));

            add_action('init', array($this, "fulfillmentStep"));
        } catch (\Throwable $th) {
        }
    }

    public function woocommerceWholesalePricing()
    {
        add_filter('barcode_scanner_wholesale_multi_user_pricing_get_after', function ($value, $field_name, $postId) {
            if (is_array($value)) {

                try {
                    $product = \wc_get_product($postId);

                    if ($product->get_type() == "simple") {
                        foreach ($value as $key => $data) {
                            if ($data && isset($data["wholesale_price"])) {
                                return $data["wholesale_price"];
                            }
                        }
                    } else if ($product->get_type() == "variable") {
                        return "";
                    } else if ($product->get_type() == "variation") {
                        foreach ($value as $key => $data) {
                            if ($data && isset($data[$postId]) && isset($data[$postId]["wholesaleprice"])) {
                                return $data[$postId]["wholesaleprice"];
                            }
                        }
                    }

                    return "";
                } catch (\Throwable $th) {
                    return "";
                }

                return "";
            }

            return $value;
        }, 10, 3);

        add_filter('barcode_scanner_wholesale_multi_user_pricing_set_after', function ($value, $field_name, $postId) {
            $currentValue = get_post_meta($postId, "wholesale_multi_user_pricing", true);

            if ($currentValue && is_array($currentValue)) {
                foreach ($currentValue as $key => &$currentValueData) {
                    if ($currentValueData && isset($currentValueData["wholesale_price"])) {
                        $currentValueData["wholesale_price"] = $value;
                    }
                    else if ($currentValueData && isset($currentValueData[$postId]) && isset($currentValueData[$postId]["wholesaleprice"])) {
                        $currentValueData[$postId]["wholesaleprice"] = $value;
                        $product = \wc_get_product($postId);

                        if ($product && $product->get_type() == "variation") {
                            $parentId = $product->get_parent_id();
                            $parentValue = get_post_meta($parentId, "wholesale_multi_user_pricing", true);

                            if ($parentValue && is_array($parentValue)) {
                                foreach ($parentValue as $key => &$data) {
                                    if ($data && isset($data[$postId]) && isset($data[$postId]["wholesaleprice"])) {
                                        $data[$postId]["wholesaleprice"] = $value;
                                    }
                                }
                                update_post_meta($parentId, "wholesale_multi_user_pricing", $parentValue);
                            }
                        }
                    }
                }

                return $currentValue;
            }

            return "";
        }, 10, 3);
    }

    public function atumStockManagerForWoocommerce()
    {
        add_filter('barcode_scanner_atum_supplier_sku_get_after', function ($value, $field_name, $post_id) {
            global $wpdb;

            try {
                $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}atum_product_data WHERE product_id = %d;", $post_id));

                if ($record) {
                    return $record->supplier_sku ? $record->supplier_sku : "";
                }
            } catch (\Throwable $th) {
            }

            return $value;
        }, 10, 3);

        add_filter('barcode_scanner_atum_supplier_sku_set_after', function ($value, $field_name, $post_id) {
            global $wpdb;

            try {
                $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}atum_product_data WHERE product_id = %d", $post_id));

                if (!$record) {
                    $wpdb->insert("{$wpdb->prefix}atum_product_data", array("product_id" => $post_id, "supplier_sku" => $value));
                }

                if ($value === "") {
                    $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}atum_product_data SET supplier_sku = null WHERE product_id = %d", $post_id));
                } else {
                    $wpdb->update("{$wpdb->prefix}atum_product_data", array("supplier_sku" => $value), array("product_id" => $post_id));
                }
            } catch (\Throwable $th) {
            }

            return $value;
        }, 10, 3);

        add_filter('barcode_scanner_atum_barcode_get_after', function ($value, $field_name, $post_id) {
            global $wpdb;

            try {
                $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}atum_product_data WHERE product_id = %d;", $post_id));

                if ($record) {
                    return $record->barcode ? $record->barcode : "";
                }
            } catch (\Throwable $th) {
            }

            return $value;
        }, 10, 3);

        add_filter('barcode_scanner_atum_barcode_set_after', function ($value, $field_name, $post_id) {
            global $wpdb;

            try {
                $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}atum_product_data WHERE product_id = %d", $post_id));

                if (!$record) {
                    $wpdb->insert("{$wpdb->prefix}atum_product_data", array("product_id" => $post_id, "supplier_sku" => $value));
                }

                if ($value === "") {
                    $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}atum_product_data SET barcode = null WHERE product_id = %d", $post_id));
                } else {
                    $wpdb->update("{$wpdb->prefix}atum_product_data", array("barcode" => $value), array("product_id" => $post_id));
                }
            } catch (\Throwable $th) {
            }

            return $value;
        }, 10, 3);

        add_filter('barcode_scanner_atum_purchase_price_get_after', function ($value, $field_name, $post_id) {
            global $wpdb;

            try {
                $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}atum_product_data WHERE product_id = %d;", $post_id));

                if ($record) {
                    return $record->purchase_price ? $record->purchase_price : "";
                }
            } catch (\Throwable $th) {
            }

            return $value;
        }, 10, 3);

        add_filter('barcode_scanner_atum_purchase_price_set_after', function ($value, $field_name, $post_id) {
            global $wpdb;

            try {
                $priceDecimalSeparator = ".";

                if (function_exists('wc_get_price_decimal_separator')) {
                    $priceDecimalSeparator = wc_get_price_decimal_separator();
                }

                $value = str_replace($priceDecimalSeparator, ".", $value);

                if ($value === "") {
                    $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}atum_product_data SET purchase_price = null WHERE product_id = %d", $post_id));
                } else {
                    $wpdb->update("{$wpdb->prefix}atum_product_data", array("purchase_price" => $value), array("product_id" => $post_id));
                }
            } catch (\Throwable $th) {
            }

            return $value;
        }, 10, 3);

        add_filter('scanner_dropdown_atum_supplier_id_options', function ($options, $field) {
            global $wpdb;

            try {
                $suppliers = $wpdb->get_results("SELECT P.ID, P.post_title FROM {$wpdb->posts} AS P WHERE P.post_type = 'atum_supplier' AND P.post_status = 'publish';");

                $options = array();

                foreach ($suppliers as $value) {
                    $options[$value->ID] = $value->post_title;
                }
            } catch (\Throwable $th) {
            }

            return $options;
        }, 10, 2);

        add_filter('barcode_scanner_atum_supplier_id_get_after', function ($value, $field_name, $post_id) {
            global $wpdb;

            try {
                $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}atum_product_data WHERE product_id = %d;", $post_id));

                if ($record) {
                    return $record->supplier_id ? $record->supplier_id : "";
                }
            } catch (\Throwable $th) {
            }

            return $value;
        }, 10, 3);

        add_filter('barcode_scanner_atum_supplier_id_set_after', function ($value, $field_name, $post_id) {
            global $wpdb;

            try {
                if ($value === "") {
                    $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}atum_product_data SET supplier_id = null WHERE product_id = %d", $post_id));
                } else {
                    $wpdb->update("{$wpdb->prefix}atum_product_data", array("supplier_id" => $value), array("product_id" => $post_id));
                }
            } catch (\Throwable $th) {
            }

            return $value;
        }, 10, 3);
    }

    public function dokan()
    {
        try {
            if (!is_plugin_active('dokan-lite/dokan.php')) {
                return;
            }

            $post = @json_decode(file_get_contents("php://input"), true);

            if (!$post || (!isset($post["bsInstanceFrontendStatus"]) || !$post["bsInstanceFrontendStatus"])) {
                return;
            }

            $currentUser = wp_get_current_user();

            if ($currentUser && $currentUser->ID) {
                $dokan_enable_selling = get_user_meta($currentUser->ID, "dokan_enable_selling", true);

                if ($dokan_enable_selling) {
                    add_filter('scanner_search_result', function ($items, $customFilter) use ($currentUser) {
                        $filtered = array_filter($items, function ($item) use ($currentUser) {
                            return $item["post_type"] == "shop_order" ? true : $item['post_author'] == $currentUser->ID;
                        });

                        return array_values($filtered);
                    }, 10, 2);
                }
            }
        } catch (\Throwable $th) {
        }
    }

    public function checkoutFeesForWoocommerce()
    {
        try {
            if (!PluginsHelper::is_plugin_active('checkout-fees-for-woocommerce/checkout-fees-for-woocommerce.php')) {
                return;
            }

            $cartScannerActions = new CartScannerActions();

            add_filter($cartScannerActions->filter_cart_additional_taxes, function ($additionalTexes, $paymentMethod, $shippingTotal, $cartSubtotal, $cartTaxTotal) {
                if (get_option("alg_woocommerce_checkout_fees_global_fee_enabled") == "yes") {
                    $value = get_option("alg_woocommerce_checkout_fees_global_fee_value");
                    $label = get_option("alg_woocommerce_checkout_fees_global_fee_title");
                    $gatewaysExcl = get_option("alg_woocommerce_checkout_fees_global_fee_gateways_excl");

                    if (!in_array($paymentMethod, $gatewaysExcl) && is_numeric($value)) {
                        $additionalTexes[] = array("label" => $label, "value" => $value, "value_c" => strip_tags(\wc_price($value)), "plugin" => "checkout-fees-for-woocommerce");
                    }
                }

                if (get_option("alg_gateways_fees_enabled_{$paymentMethod}") == "yes") {
                    $label = get_option("alg_gateways_fees_text_{$paymentMethod}");

                    $tax = $this->checkoutFeesForWoocommerceCalcPriceFee($paymentMethod, $cartSubtotal + $shippingTotal, $cartTaxTotal);

                    if ($tax) {
                        $additionalTex = null;

                        $additionalTex = array("label" => $label, "value" => $tax, "value_c" => strip_tags(\wc_price($tax)), "plugin" => "checkout-fees-for-woocommerce");

                        $wc_tax_display_cart = get_option('woocommerce_tax_display_cart');

                        if ($wc_tax_display_cart != "incl") {
                            $tax = $this->checkoutFeesForWoocommerceCalcPriceFee($paymentMethod, $tax, 0);

                            if ($tax) {
                                $additionalTex["tax"] = $tax;
                                $additionalTex["tax_c"] = strip_tags(\wc_price($tax));
                            }
                        }

                        if ($additionalTex) {
                            $additionalTexes[] = $additionalTex;
                        }
                    }
                }

                return $additionalTexes;
            }, 10, 5);
        } catch (\Throwable $th) {
        }
    }

    private function checkoutFeesForWoocommerceCalcPriceFee($paymentMethod, $price, $tax)
    {
        if (get_option("alg_gateways_fees_enabled_{$paymentMethod}") == "yes") {
            $type = get_option("alg_gateways_fees_type_{$paymentMethod}");
            $value = get_option("alg_gateways_fees_value_{$paymentMethod}");
            $label = get_option("alg_gateways_fees_text_{$paymentMethod}");
            $wc_tax_display_cart = get_option('woocommerce_tax_display_cart');

            if ($type == "fixed" && is_numeric($value)) {
                return $value;
            }
            else if ($type == "percent" && is_numeric($value)) {
                $max = get_option("alg_gateways_fees_max_fee_{$paymentMethod}");
                $min = get_option("alg_gateways_fees_min_fee_{$paymentMethod}");
                $percent = $value / 100;

                if ($wc_tax_display_cart == "incl" && $tax) {
                    $tax = ($price + $tax) * $percent;
                } else {
                    $tax = $price * $percent;
                }

                if ($min && is_numeric($min) && $tax < $min) {
                    return $min;
                }
                else if ($max && is_numeric($max) && $tax > $max) {
                    return $max;
                }
                else {
                    return $tax;
                }
            }
        }

        return 0;
    }

    public function userAcfFields()
    {
        add_filter('barcode_scanner_user_fields', function ($userFields) {
            try {
                error_reporting(0);

                if (get_current_user_id() && function_exists('acf_get_fields') && function_exists('acf_get_field_groups')) {
                    $field_groups = acf_get_field_groups(array('user_id' => 'new', 'user_form' => 'add'));


                    foreach ($field_groups as $field_group) {
                        if (!isset($field_group["active"]) || !$field_group["active"] || !isset($field_group["_valid"]) || !$field_group["_valid"]) continue;
                        $fields = acf_get_fields($field_group);

                        if (!$fields) continue;

                        foreach ($fields as $field) {
                            if (!isset($field["_valid"]) || !$field["_valid"]) continue;

                            $userFields[] = $field;
                        }
                    }
                }
            } catch (\Throwable $th) {
            }

            return $userFields;
        }, 10);
    }

    public function fulfillmentStep()
    {
        $managementActions = new ManagementActions();

        add_action($managementActions->filter_fulfillment_step, function ($step, $orderId, $productId, $itemId, $query) {
            $settings = new Settings();
            $field = $settings->getSettings("ffQtyStep");
            $field = $field === null ? "" : $field->value;

            if ($field) {
                $productStep = get_post_meta($productId, $field, true);

                if ($productStep && is_numeric($productStep) && (int)$productStep) {
                    return (int)$productStep;
                }
            }

            return $step;
        }, 10, 5);
    }
}
