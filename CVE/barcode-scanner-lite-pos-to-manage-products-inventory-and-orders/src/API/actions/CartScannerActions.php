<?php

namespace UkrSolution\BarcodeScanner\API\actions;

use UkrSolution\BarcodeScanner\API\classes\Emails;
use UkrSolution\BarcodeScanner\API\classes\Results;
use UkrSolution\BarcodeScanner\API\classes\Users;
use UkrSolution\BarcodeScanner\API\RequestHelper;
use UkrSolution\BarcodeScanner\Database;
use UkrSolution\BarcodeScanner\features\cart\Cart;
use UkrSolution\BarcodeScanner\features\logs\LogActions;
use UkrSolution\BarcodeScanner\features\settings\Settings;
use WP_REST_Request;

class CartScannerActions
{
    private $priceField = "_sale_price";
    private $filter_cart_item_price = "scanner_filter_cart_item_price";
    private $filter_cart_item_meta_data = "scanner_filter_cart_item_meta_data";
    private $filter_cart_item_price_format = "scanner_filter_cart_item_price_format";
    public $filter_cart_additional_taxes = "scanner_filter_cart_additional_taxes";
    public $filter_cart_price_for_taxes = "scanner_filter_cart_price_for_taxes";
    private $cartErrors = array();
    private $cartErrorsVariations = array();

    private $availableDiscount = 0;
    private $usedDiscount = 0;
    private $percentDiscount = 0;
    private $prodDiscount = 0;

    public function addItem(WP_REST_Request $request)
    {
        $autoFill = (bool)$request->get_param("autoFill");
        $customFilter = $request->get_param("customFilter");
        $orderUserId = $request->get_param("orderUserId");
        $itemsCustomPrices = $request->get_param("itemsCustomPrices");
        $byId = (bool)$request->get_param("byId");
        $query = RequestHelper::getQuery($request, "cart_add_item");
        $setQty = (float)$request->get_param("setQty");

        $this->initFieldPrice($orderUserId);
        $this->updateOrderCustomPrices($itemsCustomPrices);

        $settings = new Settings();

        $searchResult = (new ManagementActions())->productSearch($request, false, $byId, "", false, "cart");
        $products = $searchResult->data["products"];
        $findByTitle = $searchResult->data["findByTitle"];

        $managementActions = new ManagementActions();
        $products = apply_filters($managementActions->filter_search_result, $products, array("searchQuery" => $query, 'tab' => 'cart'));

        $total = count($products);

        $defaultQuantity = $settings->getSettings("defaultProductQty");
        $defaultQuantity = $defaultQuantity === null ? 1 : (int)$defaultQuantity->value;

        foreach ($products as &$product) {
            $product["post_type"] = "product_for_cart";

            $qtyStep = isset($product["number_field_step"]) && $product["number_field_step"] ? (float)$product["number_field_step"] : $defaultQuantity;
            $qtyStep = is_numeric($qtyStep) && $qtyStep > 0 ? $qtyStep : $defaultQuantity;

            if ($qtyStep == 1) {
                $number_field_step = \get_post_meta($product["ID"], "number_field_step", true);
                if ($number_field_step && is_numeric($number_field_step)) {
                    $qtyStep = (float)$number_field_step;
                }
            }

            if ($total === 1 && $findByTitle == false && !$autoFill) {
                $productCart = $this->findProductInCart($request, $product);

                $currQty = $productCart ? $productCart->quantity : 0;

                if ($setQty) {
                    $managementActions = new ManagementActions();
                    $filteredData = apply_filters($managementActions->filter_quantity_update, $product["ID"], $setQty, $customFilter);
                    if ($filteredData !== null) {
                        $managementActions->setQuantity($product["ID"], $setQty);
                        update_post_meta($product["ID"], "_stock", $setQty);
                    }
                    $product["_stock"] = $setQty;
                    if ($setQty > 0) $product["_stock_status"] = "instock";
                    LogActions::add($product["ID"], LogActions::$actions["update_cart_qty"], "", $setQty, $currQty, "product", $request);
                }

                if (isset($product["product_manage_stock"]) && $product["product_manage_stock"] == 1) {
                    if ($product["_stock_status"] == "outofstock") {
                        $this->cartErrors[] = array("notice" => __("Product is out of stock", "us-barcode-scanner"), "htmlMessageClass" => "err_product_is_out_of_stock");
                    }
                }

                if (!isset($product["_stock"]) || (float)$product["_stock"] < $currQty + $qtyStep) {
                    $_backorders = \get_post_meta($product["ID"], "_backorders", true);

                    if ($product["product_manage_stock"] && !in_array($_backorders, array("notify", "yes"))) {
                        return rest_ensure_response(array("increase_qty" => $qtyStep, "item" => $product));
                    }
                }

                if (!isset($product["_stock"]) || (float)$product["_stock"] < $currQty + $qtyStep) {
                    $_backorders = \get_post_meta($product["ID"], "_backorders", true);

                    if ($product["product_manage_stock"] && !in_array($_backorders, array("notify", "yes"))) {
                        return rest_ensure_response(array("increase_qty" => $qtyStep, "item" => $product));
                    }
                }

                if (isset($product["attributes"]) && $product["attributes"] && isset($product["requiredAttributes"])) {
                    foreach ($product["attributes"] as $attr => $value) {
                        if (isset($product["requiredAttributes"][$attr]) && $value == "") {
                            $this->cartErrors[] = array("notice" => "attributes error {$attr}", "htmlMessageClass" => "err_attributes_error");
                            break;
                        }
                    }
                }

                if (count($this->cartErrors) == 0) {
                    $addedResult = $this->addItemToCart($request, $product, $qtyStep, true, $customFilter, $orderUserId);

                    if ($addedResult && isset($addedResult["notice"])) {
                        $this->cartErrors[] = $addedResult;
                    }
                }
            }
        }

        $this->setOrderTotal($request);

        $result = array(
            "cartItems" => $this->getCartItems($request),
            "cartDetails" => $this->getCartDetails($request),
            "foundProducts" => $products,
            "findByTitle" => $findByTitle,
            'findByWords' => explode(" ", $query),
            "cartErrors" => $this->getWcErrors(),
            "cartErrorsVariations" => $this->cartErrorsVariations,
            "foundBy" => isset($searchResult->data["foundBy"]) ? $searchResult->data["foundBy"] : "",
        );

        return rest_ensure_response($result);
    }

    public function removeItem(WP_REST_Request $request)
    {
        global $wpdb;

        $cartItemId = $request->get_param("cartItem");
        $customerUserId = $request ? $request->get_param("orderUserId") : null;

        $tableCart = $wpdb->prefix . Database::$cart;
        $wpdb->delete($tableCart, array("id" => $cartItemId));

        $this->initFieldPrice($customerUserId);
        $this->setOrderTotal($request);

        $result = array(
            "cartItems" => $this->getCartItems($request),
            "cartDetails" => $this->getCartDetails($request),
            "cartErrors" => $this->getWcErrors()
        );

        return rest_ensure_response($result);
    }

    public function updateQuantity(WP_REST_Request $request)
    {
        global $wpdb;

        $settings = new Settings();

        $customerUserId = $request ? $request->get_param("orderUserId") : null;

        $this->initFieldPrice($customerUserId);

        $tableCart = $wpdb->prefix . Database::$cart;

        $isProductQty = $request->get_param("productQty");
        $customFilter = $request->get_param("customFilter");

        $items = $this->getCartRecords($request);

        $currentItems = $request->get_param("currentItems");

        $decimalQuantity = $settings->getSettings("cartDecimalQuantity");
        $decimalQuantity = $decimalQuantity === null ? "off" : $decimalQuantity->value;

        foreach ($currentItems as $currentItem) {
            $cartKey = isset($currentItem["cartKey"]) ? $currentItem["cartKey"] : "";

            if (!$cartKey) {
                continue;
            }

            foreach ($items as $cartItem) {
                if ($cartItem->id === $cartKey) {
                    if ($decimalQuantity == "on") {
                        $newQty = isset($currentItem["quantity"]) ? (float)$currentItem["quantity"] : 1;
                    } else {
                        $newQty = isset($currentItem["quantity"]) ? (int)$currentItem["quantity"] : 1;
                    }

                    $id = $cartItem->product_id;
                    $variation_id = $cartItem->variation_id;

                    $_product = $variation_id ? \wc_get_product($variation_id) : \wc_get_product($id);
                    $sQty = $_product->get_stock_quantity();

                    $sQty = apply_filters("scanner_filter_get_item_total_stock", $sQty, "_stock", $variation_id ? $variation_id : $id, $customFilter);

                    if ($isProductQty && $sQty < $newQty) {
                        $managementActions = new ManagementActions();

                        $filteredData = apply_filters($managementActions->filter_quantity_update, $variation_id ? $variation_id : $id, $newQty, $customFilter);

                        if ($filteredData !== null) {
                            $managementActions->setQuantity($filteredData, $newQty);
                        }
                        LogActions::add($_product->get_id(), LogActions::$actions["update_cart_qty"], "", $newQty, $sQty, "product", $request);
                    }

                    $_product = $variation_id ? \wc_get_product($variation_id) : \wc_get_product($id);
                    $sQty = get_post_meta($_product->get_id(), "_stock", true);
                    $sQty = apply_filters("scanner_filter_get_item_total_stock", $sQty, "_stock", $variation_id ? $variation_id : $id, $customFilter);

                    if ($sQty && $sQty < $newQty) {
                        $postTitle = $variation_id ? get_the_title($variation_id) : get_the_title($id);

                        $_backorders = \get_post_meta($id, "_backorders", true);

                        if (!in_array($_backorders, array("notify", "yes"))) {
                            return rest_ensure_response(array("increase_qty" => 1, "item" => array(
                                "ID" => $variation_id ? $variation_id : $id,
                                "product_id" => $variation_id ? $variation_id : $id,
                                "_stock" => $sQty,
                                "post_title" => $postTitle,
                            )));
                        }
                    }

                    if ($newQty > 0) {
                        $wpdb->update($tableCart, array("quantity" => $newQty), array("id" => $cartItem->id));
                    } else {
                        $wpdb->delete($tableCart, array("id" => $cartItem->id));
                    }
                }
            }
        }

        $this->setOrderTotal($request);

        $result = array(
            "cartItems" => $this->getCartItems($request),
            "cartDetails" => $this->getCartDetails($request),
            "cartErrors" => $this->getWcErrors()
        );

        return rest_ensure_response($result);
    }

    public function updateAttributes(WP_REST_Request $request)
    {
        $customerUserId = $request ? $request->get_param("orderUserId") : null;

        $this->initFieldPrice($customerUserId);

        $items = $request->get_param("currentItems");
        $customFilter = $request->get_param("customFilter");
        $orderUserId = $request->get_param("orderUserId");
        $errors = null;

        if ($items) {

            foreach ($items as $item) {
                if (!isset($item["updatedAction"]) || $item["updatedAction"] !== "new") {
                    continue;
                }

                $item["post_type"] = "product_for_cart";

                $addedResult = $this->addItemToCart($request, $item, 1, true, $customFilter, $orderUserId);

                if ($addedResult && is_array($addedResult) && isset($addedResult["notice"])) {
                    $errors = array();
                    $errors[] = $addedResult;
                }
            }
        }

                $this->setOrderTotal($request);

        $result = array(
            "cartItems" => $this->getCartItems($request),
            "cartDetails" => $this->getCartDetails($request),
            "cartErrors" => $errors ? $errors : $this->getWcErrors()
        );

        return rest_ensure_response($result);
    }

    public function getStatuses(WP_REST_Request $request, $userId = null)
    {
        $statuses = \wc_get_order_statuses();
        $currentLocale = \get_locale();

        if (!$userId) {
            $userId = get_current_user_id();
        }

        if ($userId) {
            $userLocale = get_user_locale($userId);

            if ($userLocale && $currentLocale != $userLocale) {
                switch_to_locale($userLocale);
            }
        }

        if ($statuses) {
            foreach ($statuses as $key => $value) {
                $statuses[$key] = __($value, "woocommerce");
            }
        }

        $result = array(
            "statuses" => $statuses,
        );

        return rest_ensure_response($result);
    }

    private function getTaxAddress($userExtraData)
    {
        if ($userExtraData && isset($userExtraData['address'])) $userExtraData = $userExtraData['address'];

        $tax_based_on = get_option('woocommerce_tax_based_on', 'shipping');
        $address = array("tax_based_on" => $tax_based_on, "country" => "", "state" => "", "city" => "", "postcode" => "");

        $shipping_as_billing = isset($userExtraData['shipping_as_billing']) ? $userExtraData['shipping_as_billing'] : 0;

        if ($tax_based_on == "billing" || ($tax_based_on == "shipping" && $shipping_as_billing == 1)) {
            $address["country"] = isset($userExtraData['billing_country']) ? trim($userExtraData['billing_country']) : "";
            $address["state"] = isset($userExtraData['billing_state']) ? trim($userExtraData['billing_state']) : "";
            $address["city"] = isset($userExtraData['billing_city']) ? trim($userExtraData['billing_city']) : "";
            $address["postcode"] = isset($userExtraData['billing_postcode']) ? trim($userExtraData['billing_postcode']) : "";
        }
        else if ($tax_based_on == "shipping") {
            $address["country"] = isset($userExtraData['shipping_country']) ? trim($userExtraData['shipping_country']) : "";
            $address["state"] = isset($userExtraData['shipping_state']) ? trim($userExtraData['shipping_state']) : "";
            $address["city"] = isset($userExtraData['shipping_city']) ? trim($userExtraData['shipping_city']) : "";
            $address["postcode"] = isset($userExtraData['shipping_postcode']) ? trim($userExtraData['shipping_postcode']) : "";
        }

        if (!$address["country"] && !$address["state"] && !$address["city"] && !$address["postcode"]) {
            $address["tax_based_on"] = "base";
            $country = get_option('woocommerce_default_country', '');
            $countryParts = explode(':', $country);
            $address["country"] = $countryParts[0];
            $address["state"] = get_option('woocommerce_store_state', '');
            $address["city"] = get_option('woocommerce_store_city', '');
            $address["postcode"] = get_option('woocommerce_store_postcode', '');

            if (function_exists("WC")) {
                $address["city"] = WC()->countries->get_base_city();
                $address["state"] = WC()->countries->get_base_state();
                $address["postcode"] = WC()->countries->get_base_postcode();
                $address["country"] = WC()->countries->get_base_country();
            }
        }

        return $address;
    }

    private function getCartDetails(WP_REST_Request $request = null)
    {
        $cart = new Cart();

        $userExtraData = $request ? $request->get_param("extraData") : null;

        $customerUserId = $request ? $request->get_param("orderUserId") : null;

        $isUpdateShipping = $request ? $request->get_param("isUpdateShipping") : null;
        $shippingMethod = $request ? trim($request->get_param("shippingMethod")) : null;
        if (!$shippingMethod && $userExtraData && isset($userExtraData['shippingMethod'])) $shippingMethod = $userExtraData['shippingMethod'];

        $paymentMethod = $request ? $request->get_param("paymentMethod") : "";
        if (!$paymentMethod && $userExtraData && isset($userExtraData['paymentMethod'])) $paymentMethod = $userExtraData['paymentMethod'];

        $newOrderStatus = $request ? $request->get_param("newOrderStatus") : "";

        $taxAddress = $this->getTaxAddress($userExtraData);

        $userId = get_current_user_id();

        $tokenUserId = $request ? $request->get_param("token_user_id") : null;
        if ($tokenUserId) $userId = $tokenUserId;

        if ($request) {
            $this->updateOrderExtraDataField("paymentMethod", $paymentMethod, $userId);
            $this->updateOrderExtraDataField("shippingMethod", $shippingMethod, $userId);
        }

        if ($customerUserId) {
            $shippingMethods = $cart->getShippingMethods($customerUserId, $taxAddress);
        }
        else {
            $shippingMethods = $cart->getShippingMethods($userId, $taxAddress);
        }

        $items = $this->getCartRecords($request);

        $activeShippingMethod = get_user_meta($userId, "scanner_active_shipping_method", true);

        $activePaymentMethod = get_user_meta($userId, "scanner_active_payment_method", true);

        if ($shippingMethod) {
            update_user_meta($userId, "scanner_active_shipping_method", $shippingMethod);
            $activeShippingMethod = $shippingMethod;
        }
        else if ($isUpdateShipping) {
            update_user_meta($userId, "scanner_active_shipping_method", "");
            $activeShippingMethod = "";
        }

        $isShippingInList = false;

        if (count(explode(":", $activeShippingMethod)) == 1) {
            foreach ($shippingMethods as $key => $value) {
                if ($activeShippingMethod == $value["id"]) {
                    $activeShippingMethod = $value["id"] . ":" . $value["instance_id"];
                    $isShippingInList = true;
                    break;
                }
            }
        }
        else {
            foreach ($shippingMethods as $key => $value) {
                if ($activeShippingMethod == $value["id"] . ":" . $value["instance_id"]) {
                    $isShippingInList = true;
                    break;
                }
            }
        }

        if (!$isShippingInList) $activeShippingMethod = "0:";

        if ($paymentMethod) {
            update_user_meta($userId, "scanner_active_payment_method", $paymentMethod);
            $activePaymentMethod = $paymentMethod;
        }

        $coupon = $request ? $this->initCoupon($request, $items) : null;

        $couponError = $coupon && isset($coupon["error"]) ? $coupon["error"] : null;

        if ($couponError) $coupon = null;

        $settings = new Settings();
        $defaultOrderTax = $settings->getSettings("defaultOrderTax");
        $defaultOrderTax = $defaultOrderTax === null ? 'based_on_store' : $defaultOrderTax->value;

        $woocommerce_calc_taxes = get_option("woocommerce_calc_taxes");
        if ($woocommerce_calc_taxes != "yes") $defaultOrderTax = "no_tax";

        $isPricesIncludeTax = \wc_prices_include_tax();

        $cartSubtotal = 0;
        $cartSubtotalTax = 0;
        $cartTotal = 0;
        $cartTaxTotal = 0;
        $cartShippingTotal = 0;
        $cartShippingTotalTax = 0;
        $cartShippingTax = 0;
        $additionalTaxes = array();
        $itemsForOrder = array();
        $orderTaxDetails = array();

        if ($activeShippingMethod) {
            $method_key_id = str_replace(':', '_', $activeShippingMethod);

            $option_name = 'woocommerce_' . $method_key_id . '_settings';
            $shipping = get_option($option_name, true);

            if ($shipping && isset($shipping["cost"])) {
                $shipping["cost"] = str_replace(",", ".", $shipping["cost"]);
                $shipping["cost"] = apply_filters($cart->filter_cart_shipping_cost, $shipping["cost"], $shipping);

                if (!is_numeric($shipping["cost"]) && is_string($shipping["cost"])) {
                    $shipping["cost"] = 0;
                }

                $cartShippingTotal = $shipping["cost"];

                if ($defaultOrderTax != "no_tax") {
                    $cartShippingTax = 0;

                    if ($taxAddress["tax_based_on"] == 'base') {
                        $cartShippingTax = (new Results)->getAddressShippingPriceTax($cartShippingTotal, $taxAddress);
                    }
                    else {
                        $cartShippingTax = (new Results)->getAddressShippingPriceTax($cartShippingTotal, $taxAddress);
                    }

                    $cartShippingTotalTax = $cartShippingTotal + $cartShippingTax;
                }

                if (!$isPricesIncludeTax) {
                    $cartTotal += $cartShippingTotalTax;
                } else {
                    $cartTotal += $cartShippingTotal;
                }
            }
        }

        foreach ($items as $item) {
            $itemId = $item->id;
            $itemSubtotal = 0;
            $itemPrice = 0;
            $line_subtotal_tax = 0;

            if ($item->custom_price || $item->custom_price == "0") {
                $customPrice = $this->formatPriceForUpdate($item->custom_price);
                $itemPrice = (float)($customPrice);
                $itemSubtotal = (float)($customPrice * $item->quantity);
            } else {
                $itemPrice = (float)$item->price;
                $itemSubtotal = (float)$item->price * $item->quantity;
            }

            $discountPrice = $this->getDiscountPrice($itemSubtotal, $item->quantity, $items, $coupon);

            $cartSubtotal += $itemSubtotal;
            $cartTotal += $discountPrice;

            $productId = $item->variation_id ? $item->variation_id  : $item->product_id;
            $product = \wc_get_product($productId);
            $tax_info = array();
            $line_subtotal_taxes = array();
            $_rate = array();

            if ($product && $defaultOrderTax != "no_tax") {
                if (($taxAddress["tax_based_on"] == 'base' || count($taxAddress) > 2) && $product->get_tax_status() == 'taxable') {
                    $priceForTaxes = apply_filters($this->filter_cart_price_for_taxes, $discountPrice, $productId);

                    if ($priceForTaxes != $discountPrice && $item->quantity) {
                        $priceForTaxes *= $item->quantity;
                    }

                    $line_subtotal_taxes = (new Results)->getUserProductTax($priceForTaxes, $product->get_tax_class(), $taxAddress, false);

                    $line_subtotal_tax = array_sum($line_subtotal_taxes);

                    $cartSubtotalTax += $line_subtotal_tax;
                    $cartTaxTotal += $line_subtotal_tax;

                    $tax_info['discountPrice'] = $discountPrice;
                    $tax_info['priceForTaxes'] = $priceForTaxes;
                    $tax_info['line_subtotal_tax'] = $line_subtotal_tax;
                    $tax_info['isPricesIncludeTax'] = $isPricesIncludeTax;
                    $tax_info['tax_class'] = $product->get_tax_class();

                    if (!$isPricesIncludeTax) {
                        $cartTotal += $line_subtotal_tax;
                    }
                }

                $_rate = count($taxAddress) > 2 ? (new Results)->getUserProductTaxRates($product->get_tax_class(), $taxAddress) : null;
            }

            $_line_tax_data = array("total" => array(), "subtotal" => array());

            if ($_rate && $defaultOrderTax != "no_tax") {
                foreach ($_rate as $_rate_id => &$_rate_data) {
                    $_tax_cost = isset($line_subtotal_taxes[$_rate_id]) ? $line_subtotal_taxes[$_rate_id] : 0;
                    $_rate_data["subtotal_tax"] = $_tax_cost;

                    if (isset($orderTaxDetails[$_rate_id])) $orderTaxDetails[$_rate_id]["subtotal_tax"] += $_tax_cost;
                    else $orderTaxDetails[$_rate_id] = $_rate_data;

                    $orderTaxDetails[$_rate_id]["subtotal_tax_c"] = strip_tags(wc_price($orderTaxDetails[$_rate_id]["subtotal_tax"]));

                    $_line_tax_data["total"][$_rate_id] = $_tax_cost;
                    $_line_tax_data["subtotal"][$_rate_id] = $_tax_cost;
                }
            }

            $itemsForOrder[] = array(
                "product_id" => $item->product_id,
                "variation_id" => $item->variation_id,
                "meta" => @json_decode($item->meta, false),
                "quantity" => $item->quantity,
                "price" => $discountPrice,
                "subtotal" => $itemSubtotal,
                "total" => $discountPrice,
                "tax" => $line_subtotal_tax,
                "_line_tax_data" => $_line_tax_data,
                "_rate" => $_rate,
                "tax_info" => $tax_info,
            );
        }

        $orderCustomPrice = get_user_meta($userId, "scanner_custom_order_total", true);

        if ($orderCustomPrice) {
            $cartSubtotal = $orderCustomPrice;
            $cartTotal = $orderCustomPrice;
        }
        else {
        }

        $additionalTaxes = apply_filters($this->filter_cart_additional_taxes, $additionalTaxes, $activePaymentMethod, $cartShippingTotal, $cartSubtotal, $cartTaxTotal);

        foreach ($additionalTaxes as $key => $tax) {
            $cartTotal += $tax["value"];

            if (isset($tax["tax"])) {
                $cartTotal += $tax["tax"];
            }
        }

        $cart_total = strip_tags(wc_price($cartTotal, array("currency" => " ",)));
        $cart_total = trim(str_replace("&nbsp;", "", $cart_total));

        $cart_subtotal = strip_tags(wc_price($cartSubtotal, array("currency" => " ",)));
        $cart_subtotal = trim(str_replace("&nbsp;", "", $cart_subtotal));

        $total_tax = strip_tags(wc_price($cartTaxTotal, array("currency" => " ",)));
        $total_tax = trim(str_replace("&nbsp;", "", $total_tax));

        return array(
            "additionalTaxes" => $additionalTaxes,
            "cart_total" => $cart_total,
            "cart_total_c" => strip_tags($cartTotal),
            "cart_subtotal" => $cart_subtotal,
            "cart_subtotal_c" => strip_tags(wc_price($cartSubtotal)), 
            "cart_subtotal_tax" => $cartSubtotalTax,
            "cart_subtotal_tax_c" => strip_tags(wc_price($cartSubtotalTax)),
            "total_tax" => $total_tax,
            "total_tax_c" => strip_tags(wc_price($cartTaxTotal)),
            "shipping" => strip_tags($cartShippingTotal),
            "shipping_c" => strip_tags(wc_price($cartShippingTotal)),
            "shipping_total_tax" => strip_tags(wc_price($cartShippingTotalTax)),
            "shipping_tax" => $cartShippingTax,
            "shipping_tax_c" => strip_tags(wc_price($cartShippingTax)),
            "shippingMethods" => $shippingMethods,
            "timestamp" => time(),
            "itemsForOrder" => $itemsForOrder,
            "orderTaxDetails" => $orderTaxDetails,
            "coupon" => $coupon,
            "couponError" => $couponError,
            "cid" => $customerUserId,
            "taxAddress" => $taxAddress,
        );
    }

    private function initCoupon(WP_REST_Request $request, $items)
    {
        global $wpdb;

        $coupon = $request ? $request->get_param("coupon") : "";

        if (!$coupon) return null;

        $couponData = $coupon ? new \WC_Coupon(trim($coupon)) : null;

        if (!$couponData || !$couponData->get_id()) return array("error" => __("Coupon not found.", "us-barcode-scanner"));

        if ($couponData->get_date_expires()) {
            $now = new \DateTime("now");

            if ($couponData->get_date_expires() < $now) {
                return array("error" => __("This coupon has been expired, you can't apply it.", "us-barcode-scanner"));
            }
        }









        $totalItemsPrice = 0;
        $totalItemsQty = 0;

        foreach ($items as $item) {
            if ($item->custom_price || $item->custom_price == "0") {
                $customPrice = $this->formatPriceForUpdate($item->custom_price);
                $totalItemsPrice += (float)($customPrice * $item->quantity);
            } else {
                $totalItemsPrice += (float)$item->price * $item->quantity;
            }

            $totalItemsQty += $item->quantity;
        }

        $minimum_amount = $couponData->get_minimum_amount();
        $maximum_amount = $couponData->get_maximum_amount();
        $discount_type = $couponData->get_discount_type();
        $amount = $couponData->get_amount();

        if ($minimum_amount && $minimum_amount > $totalItemsPrice) {
            $minAmount = strip_tags(wc_price($minimum_amount));
            return array("error" => __("Coupon requires minimal order price", "us-barcode-scanner")  . " " . $minAmount);
        }

        if ($maximum_amount && $maximum_amount < $totalItemsPrice) {
            $maxAmount = strip_tags(wc_price($maximum_amount));
            return array("error" => __("Coupon requires maximum order price", "us-barcode-scanner") . " " . $maxAmount);
        }

        if ($discount_type == "percent") {
            $this->percentDiscount = $amount;
        }
        else if ($discount_type == "fixed_product") {
            $this->prodDiscount = $amount;
        }
        else if ($amount) {
            $this->availableDiscount = $amount;
        }

        $result = $couponData->get_data();

        if ($result) {
            $result["amount_discount"] = 0;

            if ($result["discount_type"] == "percent") {
                $result["amount_c"] = "";
            }
            else if ($result["discount_type"] == "fixed_product") {
                $discount = $result["amount"] && $totalItemsQty ? $result["amount"] * $totalItemsQty : 0;
                $result["amount_c"] = $discount ? "-" . strip_tags(wc_price($discount)) : "";
            }
            else {
                $result["amount_c"] = $result["amount"] ? "-" . strip_tags(wc_price($result["amount"])) : "";
            }
        }

        $userId =  get_current_user_id();
        $tokenUserId = $request ? $request->get_param("token_user_id") : null;
        if ($tokenUserId) $userId = $tokenUserId;
        $this->updateOrderExtraDataField("coupon", $coupon, $userId);

        return $result;
    }

    private function updateOrderExtraDataField($field, $value, $userId)
    {
        global $wpdb;

        $table = $wpdb->prefix . Database::$cartData;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} AS CD WHERE CD.user_id = %d AND CD.param = %s;", $userId, $field));

        if ($row) {
            $wpdb->update($table, array("value" =>  $value), array("id" => $row->id));
        } else {
            $wpdb->insert($table, array("user_id" => $userId, "param" => $field, "value" =>  $value));
        }
    }

    private function getDiscountPrice($price, $quantity, $items, &$coupon)
    {
        $discountPrice = $price;

        if (!$coupon) return $discountPrice;

        $totalQuantities = array_reduce($items, function ($carry, $obj) {
            return $carry + $obj->quantity;
        }, 0);

        if ($this->percentDiscount) {
            $discount = $price * ($this->percentDiscount / 100);

            $discountPrice = $price - $discount;

            $coupon["amount_discount"] += $discount;
            if ($coupon["amount_discount"]) {
                $coupon["amount_c"] = $discount ? "-" . strip_tags(wc_price($coupon["amount_discount"])) : "";
            } else {
                $coupon["amount_c"] = $discount ? "-" . strip_tags(wc_price($discount)) : "";
            }
        }
        else if ($this->prodDiscount) {
            $discount = 0;
            $maxDiscount = $this->prodDiscount && $quantity ? $this->prodDiscount * $quantity : 0;

            if ($maxDiscount >= $price) {
                $discount = $price;
            } else {
                $discountPrice = $price - $maxDiscount;
                $this->usedDiscount += $maxDiscount;

                $discount = $maxDiscount;
            }

            $coupon["amount_discount"] += $discount;
            $coupon["amount_c"] = $discount ? "-" . strip_tags(wc_price($discount)) : "";
        }
        else {
            $discount = 0;

            $maxDiscount = $this->availableDiscount ? $this->availableDiscount / $totalQuantities : $this->availableDiscount;
            $maxDiscount *= $quantity;

            if ($maxDiscount >= $price) {
                $discountPrice = 0;
                $this->usedDiscount += $price;

                $discount = $price;
            } else {
                $discountPrice = $price - $maxDiscount;
                $this->usedDiscount += $maxDiscount;

                $discount = $maxDiscount;
            }

            $coupon["amount_discount"] += $discount;
            $coupon["amount_c"] = $discount ? "-" . strip_tags(wc_price($discount)) : "";
        }

        return $discountPrice;
    }

    private function getCartItems($request)
    {
        $items = $this->getCartRecords($request);

        $cartItems = array();

        foreach ($items as $item) {
            if (isset($item->variation_id) && $item->variation_id) {
                $post = get_post($item->variation_id);
            } else {
                $post = get_post($item->product_id);
            }

            if ($post) {
                $cartItem = (new Results)->formatCartScannerItem($item, $post, $this->priceField, $request);
                $cartItem["cart_key"] = $item->id;
                $cartItem["custom_price"] = $item->custom_price;
                $cartItems[] = $cartItem;
            }
        }

        return $cartItems;
    }

    private function  wcSession(WP_REST_Request $request)
    {


    }

    private function addItemToCart($request, $product, $quantity = 1, $repeat = true, $customFilter = array(), $orderUserId = "")
    {
        global $wpdb;

        $tableCart = $wpdb->prefix . Database::$cart;

        $userId =  get_current_user_id();
        $tokenUserId = $request ? $request->get_param("token_user_id") : null;
        if ($tokenUserId) $userId = $tokenUserId;

        if (isset($product["quantity"])) {
            if ((float)$product["quantity"] < 1) {
                return;
            }

            $quantity = ($product["quantity"]) ? $product["quantity"] : $quantity;
        }

        $attributes = (isset($product["attributes"]) && $product["attributes"]) ? $product["attributes"] : array();
        $cartItemData = array();

        $cartItemData = apply_filters($this->filter_cart_item_meta_data, $product, $customFilter);

        if (!$cartItemData || isset($cartItemData["ID"])) {
            $cartItemData = array();
        }

        $quantity_step = ($cartItemData && isset($cartItemData["number_field_step"]) && $cartItemData["number_field_step"]) ? (float)$cartItemData["number_field_step"] : 1;
        if (!$quantity_step || $quantity_step == 0) $quantity_step = 1;

        $priceField = (new Results())->getFieldPrice($orderUserId);

        if ($product["product_type"] === "variation") {
            $productCart = $this->findProductInCart($request, $product);

            if (!$productCart) {
                $_product = \wc_get_product($product["variation_id"]);
                $productPrice = (new Results())->getProductPrice($_product, $priceField, null, $orderUserId, $quantity);

                if (!$productPrice && $productPrice !== 0 && $productPrice !== '0') {
                    return array(
                        "notice" => __("This product doesn't have a price, you can't sell it.", "us-barcode-scanner"),
                        "htmlMessageClass" => "err_item_dont_have_price"
                    );
                }

                else if ($_product->get_type() == "variable") {
                    $this->cartErrorsVariations = $this->getVariations($_product);
                    return array(
                        "notice" => __("You can't sell parent product, you need to", "us-barcode-scanner") . " <span>" . __("select one of its variations.", "us-barcode-scanner") . "</span>",
                        "htmlMessageClass" => "err_parent_cant_sell"
                    );
                }

                else if ($priceField) {
                    $price = \get_post_meta($product["variation_id"], $priceField, true);

                    if ((!$price || empty($price) || !is_numeric($price)) && $productPrice !== 0 && $productPrice !== '0') {
                        return array(
                            "notice" => __("This product doesn't have a price, you can't sell it.", "us-barcode-scanner"),
                            "htmlMessageClass" => "err_item_dont_have_price"
                        );
                    }
                }

                $isInserted = $wpdb->insert($tableCart, array(
                    "user_id" => $userId,
                    "product_id" => $product["post_parent"],
                    "variation_id" => $product["variation_id"],
                    "price" => $productPrice,
                    "quantity" => $quantity,
                    "quantity_step" => $quantity_step,
                    "attributes" => json_encode($attributes),
                    "meta" => json_encode($cartItemData),
                    "updated" => date("Y-m-d H:i:s", time()),
                ));
            } else {
                $wpdb->update($tableCart, array(
                    "quantity_step" => $quantity_step,
                    "updated" => date("Y-m-d H:i:s", time()),
                ), array("id" => $productCart->id));

                $isChanged = $this->changeQuantityInCart($productCart, $quantity);
            }
        } else {
            $productCart = $this->findProductInCart($request, $product);

            if (!$productCart) {
                $_product = \wc_get_product($product["ID"]);
                $productPrice = (new Results())->getProductPrice($_product, $priceField, null, $orderUserId, $quantity);

                if (!$productPrice && $productPrice !== 0 && $productPrice !== '0') {
                    return array(
                        "notice" => __("This product doesn't have a price, you can't sell it.", "us-barcode-scanner"),
                        "htmlMessageClass" => "err_item_dont_have_price"
                    );
                }

                else if ($_product->get_type() == "variable") {
                    $this->cartErrorsVariations = $this->getVariations($_product);
                    return array(
                        "notice" => __("You can't sell parent product, you need to", "us-barcode-scanner") . " <span>" . __("select one of its variations.", "us-barcode-scanner") . "</span>",
                        "htmlMessageClass" => "err_parent_cant_sell"
                    );
                }

                else if ($priceField) {
                    $price = \get_post_meta($product["ID"], $priceField, true);

                    if ((!$price || empty($price) || !is_numeric($price)) && $productPrice !== 0 && $productPrice !== '0') {
                        return array(
                            "notice" => __("This product doesn't have a price, you can't sell it.", "us-barcode-scanner"),
                            "htmlMessageClass" => "err_item_dont_have_price"
                        );
                    }
                }

                $isInserted = $wpdb->insert($tableCart, array(
                    "user_id" => $userId,
                    "product_id" => $product["ID"],
                    "price" => $productPrice,
                    "quantity" => $quantity,
                    "quantity_step" => $quantity_step,
                    "meta" => json_encode($cartItemData),
                    "updated" => date("Y-m-d H:i:s", time()),
                ));
            } else {
                $wpdb->update($tableCart, array(
                    "quantity_step" => $quantity_step,
                    "updated" => date("Y-m-d H:i:s", time()),
                ), array("id" => $productCart->id));

                $isChanged = $this->changeQuantityInCart($productCart, $quantity);
            }
        }
    }

    private function getVariations($product)
    {
        return (new Results)->getChildren($product);
    }

    private function findProductInCart($request, $product)
    {
        $items = $this->getCartRecords($request);

        foreach ($items as $item) {
            if ($item->product_id == $product["ID"] && $item->product_id === $product["post_parent"]) {
                if ($item->variation_id == $product["variation_id"] || $item->product_id == $product["variation_id"]) {
                    return $item;
                }
            } else if ($item->variation_id == $product["ID"]) {
                if (isset($product["attributes"]) && $product["attributes"] && isset($product["requiredAttributes"])) {
                    foreach ($product["attributes"] as $attr => $value) {
                        if (isset($product["requiredAttributes"][$attr]) && $value == "") {
                            return false;
                        }
                    }
                }

                if ($item->attributes) {
                    $invalidValues = count($product["attributes"]);
                    $itemAttributes = @json_decode($item->attributes, false);
                    $itemAttributes = $itemAttributes ? (array)$itemAttributes : array();

                    foreach ($product["attributes"] as $attr => $value) {
                        if (
                            (isset($itemAttributes[$attr]) && $value == $itemAttributes[$attr])
                            || (isset($itemAttributes["attribute_{$attr}"]) && $value == $itemAttributes["attribute_{$attr}"])
                        ) {
                            $invalidValues--;
                        }
                    }

                    if ($invalidValues !== 0) {
                        continue;
                    }
                }

                return $item;
            } else if (isset($product["product_type"]) && $product["product_type"] == "simple") {
                if ($item->product_id == $product["ID"]) {
                    return $item;
                }
            }
        }

        return false;
    }

    private function changeQuantityInCart($productCart, $step = 1)
    {
        global $wpdb;

        $tableCart = $wpdb->prefix . Database::$cart;
        $quantity = $productCart->quantity + $step;
        return $wpdb->update($tableCart, array("quantity" => $quantity), array("id" => $productCart->id));
    }

    public function conditionally_send_wc_email($value)
    {
        if ($value) {
            $value["enabled"] = "no";
            $value["recipient"] = "";
        } else {
            $value = array("enabled" => "no", "recipient" => "");
        }

        return $value;
    }

    public function orderCreate(WP_REST_Request $request)
    {
        global $wpdb;

        @ini_set('memory_limit', '1024M');

        error_reporting(0);

        $settings = new Settings();

        $customerUserId = $request ? $request->get_param("orderUserId") : null;
        $shipmentTrackingItems = $request ? $request->get_param("shipmentTrackingItems") : array();

        $this->initFieldPrice($customerUserId);

        $clearCart = $request->get_param("clearCart");
        $orderStatus = $request->get_param("orderStatus");
        $shippingMethod = $request->get_param("shippingMethod");
        $paymentMethod = $request->get_param("paymentMethod");
        $userId = $request->get_param("userId");
        $extraData = $request->get_param("extraData");
        $confirmed = $request->get_param("confirmed");
        $isPay = $request->get_param("isPay");

        $taxAddress = $this->getTaxAddress($extraData);

        if ($isPay) $orderStatus = "wc-pending";

        $currentUserId = get_current_user_id();
        $tokenUserId = $request ? $request->get_param("token_user_id") : null;
        if ($tokenUserId) $currentUserId = $tokenUserId;

        try {
            $sendAdminEmailCreatedOrder = $settings->getSettings("sendAdminEmailCreatedOrder");
            $sendAdminEmailCreatedOrder = $sendAdminEmailCreatedOrder === null ? 'off' : $sendAdminEmailCreatedOrder->value;

            if ($sendAdminEmailCreatedOrder === "off") {
                add_filter('option_woocommerce_new_order_settings', array($this, 'conditionally_send_wc_email'), 10000);
                add_filter('option_woocommerce_cancelled_order_settings', array($this, 'conditionally_send_wc_email'), 10000);
                add_filter('option_woocommerce_failed_order_settings', array($this, 'conditionally_send_wc_email'), 10000);
                add_filter('option_woocommerce_dokan_vendor_new_order_settings', array($this, 'conditionally_send_wc_email'), 10000);
                add_filter('option_woocommerce_dokan_vendor_completed_order_settings', array($this, 'conditionally_send_wc_email'), 10000);
            }
        } catch (\Throwable $th) {
        }

        try {
            $sendClientEmailCreatedOrder = $settings->getSettings("sendClientEmailCreatedOrder");
            $sendClientEmailCreatedOrder = $sendClientEmailCreatedOrder === null ? 'on' : $sendClientEmailCreatedOrder->value;

            if ($sendClientEmailCreatedOrder === "off") {
                add_filter('option_woocommerce_customer_processing_order_settings', array($this, 'conditionally_send_wc_email'), 10000);
                add_filter('option_woocommerce_customer_completed_order_settings', array($this, 'conditionally_send_wc_email'), 10000);
                add_filter('option_woocommerce_customer_on_hold_order_settings', array($this, 'conditionally_send_wc_email'), 10000);
                add_filter('option_woocommerce_customer_refunded_order_settings', array($this, 'conditionally_send_wc_email'), 10000);
                add_filter('option_woocommerce_customer_note_settings', array($this, 'conditionally_send_wc_email'), 10000);
                add_filter('option_woocommerce_customer_reset_password_settings', array($this, 'conditionally_send_wc_email'), 10000);
                add_filter('option_woocommerce_customer_new_account_settings', array($this, 'conditionally_send_wc_email'), 10000);
                add_filter('option_woocommerce_lmfwc_email_customer_deliver_license_keys_settings', array($this, 'conditionally_send_wc_email'), 10000);
                add_filter('option_woocommerce_customer_paid_for_order_settings', array($this, 'conditionally_send_wc_email'), 10000);
            }
        } catch (\Throwable $th) {
        }


        $data = array(
            'status' => 'wc-pending',
            'line_items' => array(),
        );

        if ($userId) {
        } else {
            $userId = $currentUserId;
        }

        $items = $this->getCartRecords($request, $currentUserId);

        $quantities = array();

        foreach ($items as $item) {
            $itemId = $item->variation_id ? $item->variation_id : $item->product_id;
            $quantities[$itemId] = get_post_meta($itemId, "_stock", true);

            $customPrice = null;

            if (!$confirmed) {
                if ($item->custom_price || $item->custom_price == "0") {
                    $customPrice = $this->formatPriceForUpdate($item->custom_price);
                }

                $price = $customPrice || $customPrice == "0" ? $customPrice : $item->price;

                if ($price <= 0) {
                    $confirmation = __('Some products have "0" price, do you want to create such order?', 'us-barcode-scanner');
                    return $this->cartRecalculate($request, $confirmation);
                }
            }
        }

        $details = $this->getCartDetails($request);

        $order = \wc_create_order($data);

        if ($order && $customerUserId) {
            $order->set_customer_id($customerUserId);
        }

        $addr = isset($extraData["address"]) ? $extraData["address"] : array();

        if ($order) {
            $first_name = isset($addr["billing_first_name"]) ? $addr["billing_first_name"] : get_user_meta($customerUserId, "billing_first_name", true);
            $last_name = isset($addr["billing_last_name"]) ? $addr["billing_last_name"] : get_user_meta($customerUserId, "billing_last_name", true);
            $company = isset($addr["billing_company"]) ? $addr["billing_company"] : get_user_meta($customerUserId, "billing_company", true);
            $email = isset($addr["billing_email"]) ? $addr["billing_email"] : get_user_meta($customerUserId, "billing_email", true);
            $phone = isset($addr["billing_phone"]) ? $addr["billing_phone"] : get_user_meta($customerUserId, "billing_phone", true);
            $address_1 = isset($addr["billing_address_1"]) ? $addr["billing_address_1"] : get_user_meta($customerUserId, "billing_address_1", true);
            $address_2 = isset($addr["billing_address_2"]) ? $addr["billing_address_2"] : get_user_meta($customerUserId, "billing_address_2", true);
            $city = isset($addr["billing_city"]) ? $addr["billing_city"] : get_user_meta($customerUserId, "billing_city", true);
            $state = isset($addr["billing_state"]) ? $addr["billing_state"] : get_user_meta($customerUserId, "billing_state", true);
            $postcode = isset($addr["billing_postcode"]) ? $addr["billing_postcode"] : get_user_meta($customerUserId, "billing_postcode", true);
            $country = isset($addr["billing_country"]) ? $addr["billing_country"] : get_user_meta($customerUserId, "billing_country", true);

            $address = array(
                'first_name' => $first_name,
                'last_name' =>  $last_name,
                'company' => $company,
                'email' => is_email($email) ? $email : "",
                'phone' => $phone,
                'address_1' => $address_1,
                'address_2' => $address_2,
                'city' => $city,
                'state' =>  $state,
                'postcode' => $postcode,
                'country' => $country,
            );
            $order->set_address($address, 'billing');

            $prefix = isset($addr["shipping_as_billing"]) && $addr["shipping_as_billing"] == 1 ? "billing" : "shipping";
            $address = array(
                'first_name' => isset($addr[$prefix . "_first_name"]) ? $addr[$prefix . "_first_name"] : get_user_meta($customerUserId, $prefix . "_first_name", true),
                'last_name'  => isset($addr[$prefix . "_last_name"]) ? $addr[$prefix . "_last_name"] : get_user_meta($customerUserId, $prefix . "_last_name", true),
                'company'    => isset($addr[$prefix . "_company"]) ? $addr[$prefix . "_company"] : get_user_meta($customerUserId, $prefix . "_company", true),
                'phone'      => isset($addr[$prefix . "_phone"]) ? $addr[$prefix . "_phone"] : get_user_meta($customerUserId, $prefix . "_phone", true),
                'address_1'  => isset($addr[$prefix . "_address_1"]) ? $addr[$prefix . "_address_1"] : get_user_meta($customerUserId, $prefix . "_address_1", true),
                'address_2'  => isset($addr[$prefix . "_address_2"]) ? $addr[$prefix . "_address_2"] : get_user_meta($customerUserId, $prefix . "_address_2", true),
                'city'       => isset($addr[$prefix . "_city"]) ? $addr[$prefix . "_city"] : get_user_meta($customerUserId, $prefix . "_city", true),
                'state'      => isset($addr[$prefix . "_state"]) ? $addr[$prefix . "_state"] : get_user_meta($customerUserId, $prefix . "_state", true),
                'postcode'   => isset($addr[$prefix . "_postcode"]) ? $addr[$prefix . "_postcode"] : get_user_meta($customerUserId, $prefix . "_postcode", true),
                'country'    => isset($addr[$prefix . "_country"]) ? $addr[$prefix . "_country"] : get_user_meta($customerUserId, $prefix . "_country", true),
            );
            $order->set_address($address, 'shipping');

            $order->save();
        }

        if ($order) {
            $isPricesIncludeTax = \wc_prices_include_tax();

            if ($taxAddress["tax_based_on"] == 'base') {
                $taxDetails = (new Results)->getAddressTaxClass($taxAddress);
            }
            else if (count($taxAddress) > 2) {
                $taxDetails = (new Results)->getAddressTaxClass($taxAddress);
            }
            else {
                $taxDetails = null;
            }

            $tax_amount = 0;
            $shipping_tax_amount = 0;

            $orderCustomPrice = get_user_meta($currentUserId, "scanner_custom_order_total", true);
            $details = $this->getCartDetails($request);

            foreach ($details["itemsForOrder"] as $value) {
                $product = $value["variation_id"] ? \wc_get_product($value["variation_id"]) : \wc_get_product($value["product_id"]);

                $options = array(
                    "price" => $isPricesIncludeTax && $value["tax"] ? $value["price"] - $value["tax"] : $value["price"],
                    "subtotal" => $isPricesIncludeTax && $value["tax"] ? $value["price"] - $value["tax"] : $value["subtotal"],
                    "total" => $isPricesIncludeTax && $value["tax"] ? $value["price"] - $value["tax"] : $value["total"],
                );

                $orderItemId = $order->add_product($product, $value["quantity"], $options);

                if ($value["tax"] && $value["_line_tax_data"]) {
                    \wc_update_order_item_meta($orderItemId, '_line_tax_data', $value["_line_tax_data"]);

                    $tax_amount += $value["tax"];
                }
            }

            $orderTaxDetails = $details["orderTaxDetails"];

            if (isset($details["additionalTaxes"]) && $details["additionalTaxes"]) {
                foreach ($details["additionalTaxes"] as $key => $value) {
                    $item_fee = new \WC_Order_Item_Fee();

                    $item_fee->set_name($value["label"]);
                    $item_fee->set_amount($value["value"]);
                    $item_fee->set_tax_class('');
                    $item_fee->set_tax_status('taxable');
                    $item_fee->set_total($value["value"]);

                    if (isset($value["tax"]) && $value["tax"]) {
                        $item_fee->set_total_tax($value["tax"]);
                        $tax_amount += $value["tax"];

                        if ($taxDetails && is_array($taxDetails)) {
                            foreach ($taxDetails as $key => $_value) {
                                $item_fee->set_taxes(array("total" => array($key => $value["tax"] . "")));
                                break;
                            }
                        }
                    }

                    $order->add_item($item_fee);
                }
            }

            if ($details && $shippingMethod) {
                $activeShippingMethod = get_user_meta($userId, "scanner_active_shipping_method", true);
                $shippingLabel = __("Shipping", "us-barcode-scanner");

                $cart = new Cart();
                $allShippings = $cart->getShippingMethods($customerUserId, $taxAddress);

                foreach ($allShippings as $_shipping) {
                    if ($activeShippingMethod == $_shipping["id"] . ":" . $_shipping["instance_id"]) {
                        $shippingLabel = $_shipping["title"];
                        break;
                    }
                }

                $shipping_method = new \WC_Shipping_Rate($activeShippingMethod, $shippingLabel, $details["shipping"], 0);

                $orderItemId = $order->add_shipping($shipping_method);
                $order->set_shipping_total($details["shipping"]);

                \wc_update_order_item_meta($orderItemId, 'cost', $details["shipping"]);

                if (isset($details["shipping_tax"]) && $details["shipping_tax"]) {
                    $order->set_shipping_tax($details["shipping_tax"]);
                    $shipping_data = @explode(":", $shipping_method->id);
                    $shipping_tax_amount = $details["shipping_tax"];

                    if ($shipping_data && is_array($shipping_data) && count($shipping_data) == 2) {
                        \wc_update_order_item_meta($orderItemId, 'method_id', $shipping_data[0]);
                        \wc_update_order_item_meta($orderItemId, 'instance_id', $shipping_data[1]);
                    }

                    \wc_update_order_item_meta($orderItemId, 'total_tax', $details["shipping_tax"]);

                    if ($taxDetails && is_array($taxDetails)) {
                        foreach ($taxDetails as $key => $value) {
                            \wc_update_order_item_meta($orderItemId, 'taxes', array("total" => array($key => $details["shipping_tax"] . "")));

                            if (isset($orderTaxDetails[$key])) {
                                if (!isset($orderTaxDetails[$key]["shipping_tax"])) {
                                    $orderTaxDetails[$key]["shipping_tax"] = 0;
                                }

                                $orderTaxDetails[$key]["shipping_tax"] += $shipping_tax_amount;
                            }
                            else {
                                $orderTaxDetails[$key] = array("shipping_tax" => $shipping_tax_amount);
                            }
                        }
                    }
                } else {
                    $order->set_shipping_tax(0);
                }
            }

            foreach ($orderTaxDetails as $key => $value) {
                $_tax = isset($value["subtotal_tax"]) ? $value["subtotal_tax"] : 0;
                $_shipping_tax = isset($value["shipping_tax"]) ? $value["shipping_tax"] : 0;
                $order->add_tax($key, $_tax, $_shipping_tax);
            }

            if ($details && $details["coupon"]) {
                $couponAmount = isset($details["coupon"]["amount_discount"]) && $details["coupon"]["amount_discount"] ? $details["coupon"]["amount_discount"] : $details["coupon"]["amount"];

                if ($details["coupon"]["discount_type"] == "fixed_product" && $this->usedDiscount) {
                    $couponAmount = $this->usedDiscount;
                }

                $order->set_discount_total($couponAmount);

                $itemMetaId = $order->add_coupon($details["coupon"]["code"], $couponAmount);
                \wc_update_order_item_meta($itemMetaId, 'coupon_data', $details["coupon"]);
                \wc_update_order_item_meta($itemMetaId, 'discount_amount', $couponAmount);
            }


            if ($orderCustomPrice) {
                $order->set_total($orderCustomPrice);
            }
            else {
                $items = $order->get_items();
                $orderTotal = $details["cart_total_c"];
                $order->set_total($orderTotal);
            }
        }

        $order->set_cart_tax($tax_amount);
        $order->save();

        $orderId = $order->get_id();
        $checkoutErrors = $this->getWcCheckoutErrors($orderId);

        if ($checkoutErrors) {
            $orderId = "";
        } else {
            if ($clearCart) {
                $this->cartClear($request);
            }

            if ($paymentMethod) {
                $paymentGateways = WC()->payment_gateways->payment_gateways();

                if ($paymentGateways && $orderId) {
                    foreach ($paymentGateways as $id => $payment) {
                        if ($id === $paymentMethod) {
                            $_order = \wc_get_order($orderId);
                            $_order->set_payment_method($payment);
                            $_order->save();
                        }
                    }
                }
            }

            if ($orderId && $extraData && isset($extraData["note"])) {
                if (HPOS::getStatus()) {
                    $order->set_customer_note($extraData["note"]);
                } else {
                    $postData = array('ID' => $orderId, 'post_excerpt' => $extraData["note"]);
                    wp_update_post($postData);
                    $order->set_customer_note($extraData["note"]);
                    $order->save();
                }
            }

            ob_start();

            if ($orderStatus) {
                try {
                    $order->update_status(str_replace("wc-", "", $orderStatus));
                    $order->save();
                } catch (\Throwable $th) {
                }
            }

            ob_end_clean();

            $this->cleanObOutput();

            $items = $order->get_items();

            foreach ($items as $item) {
                $variationId = $item->get_variation_id();
                $productId = $variationId;

                if (!$productId) {
                    $productId = $item->get_product_id();
                }

                $_manage_stock = get_post_meta($productId, "_manage_stock", true);

                if ($_manage_stock === "yes" && isset($quantities[$productId])) {
                    $_stock = get_post_meta($productId, "_stock", true);

                    if ($quantities[$productId] != $_stock) {
                        LogActions::add($productId, LogActions::$actions["order_quantity_minus"], "", $_stock, "", "product", $request);
                    }
                }
            }

            if ($orderId) {
                $orderCustomFields = array();

                foreach ($addr as $key => $value) {
                    if (strpos($key, 'uscf_') === 0) {
                        $orderCustomFields[str_replace('uscf_', '', $key)] = $value;
                    }
                }

                $result = apply_filters('barcode_scanner_save_order_custom_fields_data', $orderId, $orderCustomFields);

                apply_filters("scanner_save_post_shop_order", $orderId, null, null);

                $this->cartClear($request);
            }

            LogActions::add($orderId, LogActions::$actions["create_order"], "", "", "", "order", $request);
        }

        if ($orderId && $currentUserId) {
            $wpdb->update($wpdb->posts, array('post_author' => $currentUserId), array('ID' => $orderId));
        }

        if ($orderId && $shipmentTrackingItems) {
            $items = array();

            foreach ($shipmentTrackingItems as $value) {
                if ($value['tracking_provider'] && trim($value['tracking_number'])) {
                    $items[] = array(
                        'tracking_provider' => $value['tracking_provider'],
                        'custom_tracking_provider' => '',
                        'custom_tracking_link' => '',
                        'tracking_number' => trim($value['tracking_number']),
                        'tracking_product_code' => '',
                        'date_shipped' => $value['date_shipped'],
                        'products_list' => '',
                        'status_shipped' => $value['status_shipped'],
                        'tracking_id' => md5(microtime()),
                    );
                }
            }

            update_post_meta($orderId, "_wc_shipment_tracking_items", $items);
        }

        if ($orderId) {
            (new ManagementActions())->productIndexation($orderId, "orderCreated");
        }

        $settings = new Settings();

        $orderRequest = new WP_REST_Request("", "");
        $orderRequest->set_param("query", $orderId);
        $orderRequest->set_param("filterExcludes", array("products"));

        $result = array(
            "orderId" => $orderId,
            "orderAdminUrl" => admin_url('post.php?post=' . $orderId) . '&action=edit',
            "order" => $order ? (new ManagementActions())->orderSearch($orderRequest, false, true) : null,
            "cartItems" => $this->getCartItems($request),
            "cartDetails" => $this->getCartDetails(null),
            "cartErrors" => ($checkoutErrors) ? $checkoutErrors : $this->getWcErrors(),
            'tabsPermissions' => $settings->getUserRolePermissions(Users::getUserId($request)),
        );

        return rest_ensure_response($result);
    }

    private function cleanObOutput()
    {
        $levels = ob_get_level();

        for ($i = 0; $i < $levels; $i++) {
            ob_get_clean();
        }
    }

    public function cartClear($request, $isReturn = true)
    {
        global $wpdb;

        $userId = get_current_user_id();
        $tableCart = $wpdb->prefix . Database::$cart;

        $tokenUserId = $request ? $request->get_param("token_user_id") : null;
        if ($tokenUserId) $userId = $tokenUserId;

        $wpdb->delete($tableCart, array("user_id" => $userId));

        update_user_meta($userId, "scanner_custom_order_total", "");
        update_user_meta($userId, "scanner_active_shipping_method", "");
        update_user_meta($userId, "scanner_active_payment_method", "");

        $this->updateOrderExtraData(array("clear" => 1), $userId);

        if ($isReturn) {
            $result = array(
                "cartItems" => $this->getCartItems(null),
                "cartDetails" => $this->getCartDetails(null),
            );

            return rest_ensure_response($result);
        }
    }

    public function cartRecalculate(WP_REST_Request $request, $confirmation = "")
    {
        $itemsCustomPrices = $request->get_param("itemsCustomPrices");
        $orderUserId = $request->get_param("orderUserId");
        $extraData = $request->get_param("extraData");
        $customerUserId = $request ? $request->get_param("orderUserId") : null;
        $loadCustomerData = $request->get_param("loadCustomerData");

        $userId = get_current_user_id();
        $tokenUserId = $request ? $request->get_param("token_user_id") : null;
        if ($tokenUserId) $userId = $tokenUserId;

        $this->initFieldPrice($customerUserId);
        $this->setOrderTotal($request);

        $userData = array();

        try {
            if ($loadCustomerData == 1 && $orderUserId && class_exists("WC_Customer")) {

                $customer = new \WC_Customer($orderUserId);

                $meta = get_userdata($orderUserId);

                if ($customer && $meta) {
                    $bStates = WC()->countries->get_states($customer->get_billing_country());
                    $bState  = !empty($bStates[$customer->get_billing_state()]) ? $bStates[$customer->get_billing_state()] : '';

                    $sStates = WC()->countries->get_states($customer->get_shipping_country());
                    $sState  = !empty($sStates[$customer->get_shipping_state()]) ? $sStates[$customer->get_shipping_state()] : '';

                    $userData = array(
                        "ID" => $orderUserId,
                        "username" => $customer->get_username(),
                        "phone" => $customer->get_billing_phone(),
                        "email" => $meta->user_email,
                        "first_name" => $customer->get_first_name(),
                        "last_name" => $customer->get_last_name(),
                        "display_name" => $customer->get_first_name(),
                        "billing_first_name" => $customer->get_billing_first_name(),
                        "billing_last_name" => $customer->get_billing_last_name(),
                        "billing_company" => $customer->get_billing_company(),
                        "billing_email" => $customer->get_billing_email(),
                        "billing_phone" => $customer->get_billing_phone(),
                        "billing_address_1" => $customer->get_billing_address_1(),
                        "billing_address_2" => $customer->get_billing_address_2(),
                        "billing_city" => $customer->get_billing_city(),
                        "billing_state" => $customer->get_billing_state(),
                        "billing_state_name" => $bState,
                        "billing_postcode" => $customer->get_billing_postcode(),
                        "billing_country" => $customer->get_billing_country(),
                        "billing_country_name" => $customer->get_billing_country() ? WC()->countries->countries[$customer->get_billing_country()] : "",
                        "shipping_first_name" => $customer->get_shipping_first_name(),
                        "shipping_last_name" => $customer->get_shipping_last_name(),
                        "shipping_company" => $customer->get_shipping_company(),
                        "shipping_phone" => $customer->get_shipping_phone(),
                        "shipping_address_1" => $customer->get_shipping_address_1(),
                        "shipping_address_2" => $customer->get_shipping_address_2(),
                        "shipping_city" => $customer->get_shipping_city(),
                        "shipping_state" => $customer->get_shipping_state(),
                        "shipping_state_name" => $sState,
                        "shipping_postcode" => $customer->get_shipping_postcode(),
                        "shipping_country" => $customer->get_shipping_country(),
                        "shipping_country_name" => $customer->get_shipping_country() ? WC()->countries->countries[$customer->get_shipping_country()] : "",
                        "shipping_as_billing" => isset($extraData['shipping_as_billing']) ? $extraData['shipping_as_billing'] : 0,
                    );

                    $customFields = array();
                    $customFields = apply_filters('barcode_scanner_load_order_custom_fields_data', $customFields, $orderUserId);

                    if ($customFields) {
                        foreach ($customFields as $key => $value) {
                            $userData["uscf_" . $key] = $value;
                        }
                    }

                    if ($userId) {
                        foreach ($userData as $key => $value) {
                            $this->updateOrderExtraDataField($key, $value, $userId);
                            $extraData[$key] = $value;
                        }

                        $request->set_param("extraData", $extraData);
                    }
                }
            } else {
                $userData = $extraData;
            }
        } catch (\Throwable $th) {
        }

        $this->updateOrderExtraData($extraData, $userId);
        $this->updateOrderCustomPrices($itemsCustomPrices);

        $result = array(
            "cartItems" => $this->getCartItems($request),
            "cartDetails" => $this->getCartDetails($request),
            "cartErrors" => $this->getWcErrors(),
            "userData" => $userData,
            "confirmation" => $confirmation,
        );

        return rest_ensure_response($result);
    }

    private function getWcErrors()
    {
        return $this->cartErrors;
    }

    private function getWcCheckoutErrors($createResult)
    {
        $errors = array();

        if (is_object($createResult) && isset($createResult->errors)) {
            $list = $createResult->errors;

            if (is_array($list) && isset($list["checkout-error"]) && is_array($list["checkout-error"])) {
                foreach ($list["checkout-error"] as $value) {
                    $notice = $value;

                    if (is_string($value)) {
                        $notice = strip_tags($value);
                    }

                    $errors[] = array(
                        "notice" => $notice
                    );
                }
            }
        }

        return array_unique($errors);
    }

    private function initFieldPrice($orderUserId)
    {
        $this->priceField = (new Results())->getFieldPrice($orderUserId);
    }

    private function setOrderTotal(WP_REST_Request $request)
    {
        $orderCustomPrice = $this->formatPriceForUpdate($request->get_param("orderCustomPrice"));
        $orderCustomTax = $request->get_param("orderCustomTax");

        $userId = get_current_user_id();

        $tokenUserId = $request ? $request->get_param("token_user_id") : null;
        if ($tokenUserId) $userId = $tokenUserId;

        if ($orderCustomTax && (float)$orderCustomTax) {
        }

        if (is_numeric($orderCustomPrice) && $orderCustomPrice >= 0 && (float)$orderCustomPrice >= 0) {
            update_user_meta($userId, "scanner_custom_order_total", $orderCustomPrice);
        }
    }

    private function updateOrderExtraData($data, $userId)
    {
        global $wpdb;

        $excludes = array("clear");
        $table = $wpdb->prefix . Database::$cartData;

        if (!$data || !$userId) return;

        $wpdb->delete($table, array("user_id" => $userId));

        foreach ($data as $key => $value) {
            if (!in_array($key, $excludes)) {
                $wpdb->insert($table, array("user_id" => $userId, "param" => $key, "value" => $value));
            }
        }
    }

    private function updateOrderCustomPrices($itemsCustomPrices)
    {
        global $wpdb;

        if ($itemsCustomPrices) {
            foreach ($itemsCustomPrices as $cartId => $price) {
                $table = $wpdb->prefix . Database::$cart;
                $wpdb->update($table, array("custom_price" => $price), array("id" => $cartId));
            }
        }
    }

    public function formatPriceForUpdate($price)
    {

        try {
            if (!$price) return $price;

            $priceThousandSeparator = "";
            $priceDecimalSeparator = ".";

            if (function_exists('wc_get_price_thousand_separator')) {
                $priceThousandSeparator = \wc_get_price_thousand_separator();
            }

            if (function_exists('wc_get_price_decimal_separator')) {
                $priceDecimalSeparator = \wc_get_price_decimal_separator();
            }

            $p = str_replace($priceThousandSeparator, "", $price);

            $p = str_replace($priceDecimalSeparator, ".", $p);

            $p = apply_filters($this->filter_cart_item_price_format, $p, $price);

            return $p;
        } catch (\Throwable $th) {
            return $price;
        }
    }

    private function getCartRecords(WP_REST_Request $request = null, $userId = null)
    {
        global $wpdb;

        $tableCart = $wpdb->prefix . Database::$cart;

        if (!$userId) {
            $tokenUserId = $request ? $request->get_param("token_user_id") : null;
            $userId = get_current_user_id();

            if ($tokenUserId) $userId = $tokenUserId;
        }

        $items = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$tableCart} WHERE user_id = '%d';", $userId)
        );

        $customerUserId = $request ? $request->get_param("orderUserId") : null;

        $priceField = (new Results())->getFieldPrice($customerUserId);

        $newList = array();

        foreach ($items as &$item) {
            $productId = $item->variation_id ? $item->variation_id  : $item->product_id;
            $product = \wc_get_product($productId);
            $price = (new Results())->getProductPrice($product, $priceField, null, $customerUserId, $item->quantity);

            $item->price = $price;
            $newList[] = $item;
        }

        $items = null;

        return $newList;
    }
}
