<?php

namespace UkrSolution\BarcodeScanner\API\actions;

use UkrSolution\BarcodeScanner\API\classes\BatchNumbers;
use UkrSolution\BarcodeScanner\API\classes\BatchNumbersWebis;
use UkrSolution\BarcodeScanner\API\classes\Emails;
use UkrSolution\BarcodeScanner\API\classes\OrdersHelper;
use UkrSolution\BarcodeScanner\API\classes\Post;
use UkrSolution\BarcodeScanner\API\classes\PostsList;
use UkrSolution\BarcodeScanner\API\classes\Results;
use UkrSolution\BarcodeScanner\API\classes\Request;
use UkrSolution\BarcodeScanner\API\classes\SearchFilter;
use UkrSolution\BarcodeScanner\API\classes\Users;
use UkrSolution\BarcodeScanner\API\classes\WPML;
use UkrSolution\BarcodeScanner\API\RequestHelper;
use UkrSolution\BarcodeScanner\Database;
use UkrSolution\BarcodeScanner\features\debug\Debug;
use UkrSolution\BarcodeScanner\features\history\History;
use UkrSolution\BarcodeScanner\features\logs\LogActions;
use UkrSolution\BarcodeScanner\features\settings\Settings;
use UkrSolution\BarcodeScanner\features\settings\SettingsHelper;
use WP_REST_Request;

class ManagementActions
{
    private $postAutoAction = array(
        "AUTO_INCREASING" => "AUTO_INCREASING",
        "AUTO_DECREASING" => "AUTO_DECREASING"
    );
    private $orderAutoAction = array(
        "ORDER_STATUS" => "ORDER_STATUS"
    );
    private $qtyBeforeUpdate = array();
    private $tableColumns = null;

    public $filter_search_result = 'scanner_search_result';
    public $filter_quantity_plus = 'scanner_quantity_plus';
    public $filter_quantity_minus = 'scanner_quantity_minus';
    public $filter_quantity_update = 'scanner_quantity_update';
    public $filter_set_after = "barcode_scanner_%field_set_after";
    public $filter_auto_action_step = 'scanner_auto_action_step';
    public $scanner_created_product = 'scanner_created_product';
    public $filter_fulfillment_step = 'scanner_fulfillment_step';

    public function productSearch(WP_REST_Request $request, $actions = true, $findById = false, $actionError = "", $withoutStatuses = false, $tab = "products")
    {
        $autoFill = $request->get_param("autoFill");
        $filter = SearchFilter::get();

        $customFilter = $request->get_param("customFilter");
        $filterExcludes = $request->get_param("filterExcludes");
        $query = RequestHelper::getQuery($request, "product");
        $postAutoAction = $request->get_param("postAutoAction");
        $platform = $request->get_param("platform");
        $limit = null;

        $postAutoField = $request->get_param("postAutoField");
        $postAutoField = $postAutoField ? $postAutoField : "_stock";

        $byId = $request->get_param("byId");
        $onlyById = $byId || $findById ? true : false;

        $isAddToList = $request->get_param("isAddToList");
        $modifyAction = $request->get_param("modifyAction");
        $fulfillmentOrderId = $request->get_param("fulfillmentOrderId");
        $filterResult = $request->get_param("filterResult");

        if ($filterResult && isset($filterResult["postId"])) {
            $findById = true;
        }

        if (is_array($filterExcludes) && in_array("orders", $filterExcludes)) {
            $findById = true;
        }

        if ($findById === true && isset($filter["products"])) {
            $filter["products"]["ID"] = 1;
        }

        $result = array(
            "products" => array(),
            "findByTitle" => null,
            "qtyBeforeUpdate" => $this->qtyBeforeUpdate,
            "actionError" => $actionError,
            "fulfillment" => $fulfillmentOrderId ? 1 : 0,
        );

        if ($fulfillmentOrderId) {
            $tab = "orders";
        }



        Debug::addPoint("start Post()->find");
        $data = (new Post())->find($query, $filter, $onlyById, $autoFill, $limit, "product", $filterExcludes, $withoutStatuses, array(), $tab);
        Debug::addPoint("end Post()->find");

        $postsCount = $data && isset($data["posts"]) ? count($data["posts"]) : 0;

        if ($fulfillmentOrderId && !$autoFill && $postsCount == 1) {
            $_post = $data && isset($data["posts"]) && count($data["posts"]) > 0 ? $data["posts"][0] : null;

            if ($_post) {
                $variationId = $_post->post_parent ? $_post->ID : 0;
                $productData  = array(
                    "ID" => $_post->ID,
                    "variation_id" => $variationId,
                    "number_field_step" => get_post_meta($_post->ID, "number_field_step", true)
                );
            } else {
                $productData = null;
            }

            $fulfillmentResult = $this->applyFulfillment($request, $fulfillmentOrderId, $productData);

            if ($fulfillmentResult) {
                $fulfillmentResult["fulfillmentResult"] = true;
                return rest_ensure_response($fulfillmentResult);
            } else {
                return rest_ensure_response(array(
                    "success" => true,
                    "fulfillmentResult" => true,
                    "fulfillment" => 1,
                    "updatedOrder" => array("ID" => $fulfillmentOrderId),
                    "error" => __("Scanned product is not from this order!", "us-barcode-scanner"),
                    "htmlMessageClass" => "ff_product_is_not_from_order",
                    "debug" => Debug::getResult(true)
                ));
            }
        }

        if ($filterResult && $data && isset($filterResult["postId"]) && is_array($data["posts"]) && count($data["posts"]) > 1) {
            $data["posts"] = array_values(array_filter(
                $data["posts"],
                function ($_post) use ($filterResult) {
                    return $_post->ID == $filterResult["postId"];
                }
            ));
        }

        Debug::addPoint("start Results()->productsPrepare");

        $postCounter = $data["posts"] ? count($data["posts"]) : 0;
        $products = (new Results())->productsPrepare(
            $data["posts"],
            array(
                "useAction" => $postAutoAction && $postAutoAction != "empty" ? $postAutoField : false,
                "isAddToList" => $isAddToList,
                "modifyAction" => $modifyAction,
                "isAutoFill" => $autoFill,
            ),
        );
        Debug::addPoint("end Results()->productsPrepare");

        $this->itemsLevenshtein($products, $query, $data);

        if ($products) {
            $customFilter["searchQuery"] = $query;

            $products = apply_filters($this->filter_search_result, $products, $customFilter);

            $userId = Users::getUserId($request);

            if ($isAddToList && !$autoFill) {
                $actions = false;
                $product = count($products) == 1 ? $products[0] : null;
                PostsList::addToList($userId, $product, 1, $modifyAction);
                $result['productsList'] = PostsList::getList($userId);
                if (isset($result['productsList']) && count($result['productsList']) > 0 && $product) {
                    $_id = $product["variation_id"] ? $product["variation_id"] : $product["ID"];

                    foreach ($result['productsList'] as &$value) {
                        if ($value->post_id == $_id) {
                            $value->isUpdated = true;
                        }
                    }
                }
            }
            else {
                $result['productsList'] = PostsList::getList($userId);
            }

            if ($actions) {
                $actionResult = $this->checkPostAutoAction($request, $postAutoAction, $products, $data["findByTitle"]);

                if ($actionResult !== false) {
                    return $actionResult;
                }
            }

            $result['products'] = $products;
            $result['findByTitle'] = count($products) > 1 ? true : $data["findByTitle"];
            $result['findByWords'] = explode(" ", $query);
        } else {
            $requestName = $request->get_param("reqbs");

            if ($requestName === "post-search" && $fulfillmentOrderId) {

                return $this->orderSearch($request);


            }
        }

        if (isset($data["query"])) {
            $result["foundBy"] = $data["query"];
        }

        if (Debug::$status) {
            $result['debug'] = Debug::getResult();
        }

        if (count($result["products"]) === 1 && !$autoFill && !$findById && !$fulfillmentOrderId) {
            LogActions::add($result["products"][0]["ID"], LogActions::$actions["open_product"], "", "", "", "product", $request);
        }

        return rest_ensure_response($result);
    }

    public function getProducts(WP_REST_Request $request)
    {
        $filter = SearchFilter::get();

        $query = RequestHelper::getQuery($request, "product");
        $customFilter = $request->get_param("customFilter");
        $postTypes = $request->get_param("postTypes");

        $result = array(
            "products" => array()
        );

        $filterExcludes = array();
        if ($postTypes && in_array('variable', $postTypes)) $filterExcludes[] = 'orders';

        Debug::addPoint("start Post()->find");
        $data = (new Post())->find($query, $filter, false, false, null, "product", $filterExcludes, false, $postTypes);
        Debug::addPoint("end Post()->find");

        Debug::addPoint("start Results()->productsPrepare");
        $products = (new Results())->productsPrepare($data["posts"]);
        Debug::addPoint("end Results()->productsPrepare");

        $this->itemsLevenshtein($products, $query, $data);

        if ($products) {
            $customFilter["searchQuery"] = $query;

            $products = apply_filters($this->filter_search_result, $products, $customFilter);

            $result['products'] = $products;
        }

        if (Debug::$status) {
            $result['debug'] = Debug::getResult();
        }

        return rest_ensure_response($result);
    }

    private function applyFulfillment(WP_REST_Request $request, $orderId, $product)
    {
        $order = new \WC_Order($orderId);
        $result = false;

        if (!$order) {
            return $result;
        }

        $itemIdToFulfillment = null;
        $alreadyFulfillment = false;

        foreach ($order->get_items() as $itemId => $value) {
            $pid = $value->get_variation_id() ? $value->get_variation_id() : $value->get_product_id();

            if (!isset($infoData[$pid])) $infoData[$pid] = array();

            if ($pid == $product["ID"] && ($value->get_variation_id() == $product["variation_id"] || $value->get_variation_id() == 0)) {
                $qty = (float)\wc_get_order_item_meta($itemId, '_qty', true);

                $scanned = (float)\wc_get_order_item_meta($itemId, 'usbs_check_product_scanned', true);

                if ($qty && $scanned < $qty) {
                    $result = $this->orderUpdateItemMeta($request, $orderId, $itemId, array(array("key" => "usbs_check_product", "value" => time())), $product, $order);
                    $alreadyFulfillment = true;
                    break;
                }
                else {
                    $itemIdToFulfillment = $itemId;
                }
            }
        }

        if ($itemIdToFulfillment && $alreadyFulfillment === false) {
            $result = $this->orderUpdateItemMeta($request, $orderId, $itemIdToFulfillment, array(array("key" => "usbs_check_product", "value" => time())), $product, $order);
        }

        $this->getFulfillmentOrderData($orderId);

        return $result;
    }

    public function getFulfillmentOrderData($orderId, $isCheckFulfilledStatus = true)
    {
        $order = wc_get_order($orderId);
        $items = $order->get_items("line_item");
        $products = array();

        foreach ($items as $item) {
            $id = $item->get_variation_id();
            $id = !$id ? $item->get_product_id() : $id;
            $usbs_check_product_scanned = \wc_get_order_item_meta($item->get_id(), 'usbs_check_product_scanned', true);

            $products[] = array(
                "ID" => $id,
                "quantity" => \wc_get_order_item_meta($item->get_id(), '_qty', true),
                "usbs_check_product_scanned" => $usbs_check_product_scanned == "" ? 0 : $usbs_check_product_scanned
            );
        }

        $orderData = array(
            "ID" => $orderId,
            "products" => $products,
            "post_type" => $order->get_type(),
            "usbs_fulfillment_objects" => get_post_meta($orderId, "usbs_fulfillment_objects", true),
            "data" => array(
                "billing" => array(
                    "country" => $order->get_billing_country(),
                    "state" => $order->get_billing_state(),
                    "postcode" => $order->get_billing_postcode(),
                ),
                "shipping" => array(
                    "country" => $order->get_billing_country(),
                    "state" => $order->get_shipping_state(),
                    "postcode" => $order->get_shipping_postcode(),
                ),
            ),
        );

        $orders = apply_filters($this->filter_search_result, array($orderData), array());
        $orderData = $orders && count($orders) ? $orders[0] : $orderData;

        $infoData = $this->isOrderFulfillment($orderData);

        if ($infoData) {
            update_post_meta($orderId, "usbs_order_fulfillment_data", $infoData);

            if ($infoData["totalScanned"] && $infoData["totalQty"] == $infoData["totalScanned"] && $isCheckFulfilledStatus == true) {
                $this->updateOrderStatusByFulFillment($orderData, $result, $order, null);
            }
        }

        return $infoData;
    }

    private function updateOrderStatusByFulFillment($orderData, &$result = null, $order = null, $request = null)
    {
        try {
            $settings = new Settings();
            $autoStatusFulfilled = $settings->getSettings("autoStatusFulfilled");
            $autoStatusFulfilled = $autoStatusFulfilled === null ? "" : $autoStatusFulfilled->value;

            if (!$orderData || !$autoStatusFulfilled) {
                return;
            }

            $orderId = $orderData["ID"];
            $order = $order ? $order : new \WC_Order($orderId);

            if ($order && $autoStatusFulfilled != $order->get_status() && $autoStatusFulfilled != "wc-" . $order->get_status()) {
                $oldValue = $order->get_status();

                $order->update_status($autoStatusFulfilled);
                $order->save();


                if ($result && $result->data && isset($result->data["orders"])) {
                    $result->data["orders"][0]["data"]["status"] = $order->get_status();
                }

                if ($request) {
                    LogActions::add($orderId, LogActions::$actions["update_order_status"], "post_status", $autoStatusFulfilled, $oldValue, "order", $request);
                }
            }
        } catch (\Throwable $th) {
        }
    }

    private function isOrderFulfillment($order)
    {
        $data = array("items" => array(), "codes" => array(), "totalQty" => 0, "totalScanned" => 0);

        if (!isset($order["products"])) return $data;

        foreach ($order["products"] as $value) {
            $qty = isset($value["quantity"]) ? $value["quantity"] : 1;
            $scanned = isset($value["usbs_check_product_scanned"]) ? $value["usbs_check_product_scanned"] : 1;
            $data["items"][$value["ID"]] = array("qty" => $qty, "scanned" => $scanned);

            $data["totalQty"] += $qty;
            $data["totalScanned"] += $scanned;
        }

        $settings = new Settings();

        $fulfillmentField = $settings->getSettings("orderFulFillmentField");
        $fulfillmentField = $fulfillmentField === null ? "" : $fulfillmentField->value;

        if ($fulfillmentField && isset($order[$fulfillmentField]) && $order[$fulfillmentField]) {
            $data["codes"][$fulfillmentField] = array("qty" => 1, "scanned" => 0);

            $data["totalQty"] += 1;

            if (isset($order["usbs_fulfillment_objects"]) && isset($order["usbs_fulfillment_objects"][$fulfillmentField])) {
                $data["totalScanned"] += 1;
                $data["codes"][$fulfillmentField]["scanned"] = 1;
            }
        }

        if (isset($order["custom_tracking_code_fields"]) && is_array($order["custom_tracking_code_fields"])) {
            foreach ($order["custom_tracking_code_fields"] as $fulfillmentField) {
                if ($fulfillmentField && isset($order[$fulfillmentField]) && $order[$fulfillmentField]) {
                    $data["codes"][$fulfillmentField] = array("qty" => 1, "scanned" => 0);

                    $data["totalQty"] += 1;

                    if (isset($order["usbs_fulfillment_objects"]) && isset($order["usbs_fulfillment_objects"][$fulfillmentField])) {
                        $data["totalScanned"] += 1;
                        $data["codes"][$fulfillmentField]["scanned"] = 1;
                    }
                }
            }
        }

        return $data;
    }

    private function itemsLevenshtein(&$items, $query, $data)
    {
        try {
            if (count($items) >= 1) {
                $postsSearchData = array();
                if (isset($data["postsSearchData"])) {
                    foreach ($data["postsSearchData"] as $key => &$value) {
                        $postsSearchData[$value->ID] = $value;
                    }
                }

                $_q = trim($query);
                $_q = str_replace(array(":", "/"), "", $_q);

                usort($items, function ($a, $b) use ($_q, $postsSearchData) {
                    $valueA = $a["post_title"];
                    $valueB = $a["post_title"];

                    if (key_exists($a["ID"], $postsSearchData)) {
                        $valueA = $this->findValueByField($postsSearchData, $a);
                    }

                    if (key_exists($b["ID"], $postsSearchData)) {
                        $valueB = $this->findValueByField($postsSearchData, $b);
                    }

                    return levenshtein($_q, $valueA) - levenshtein($_q, $valueB);
                });

                foreach ($items as $key => $value) {
                    if (!key_exists($value["ID"], $postsSearchData)) {
                        continue;
                    }

                    $field = array_search("1", (array)$postsSearchData[$value["ID"]]);
                    $field = $this->getColumnName($field);
                    $items[$key]["found_by_field"] = $field;
                }

                if (count($items) > 1) {
                    foreach ($items as $key => $value) {
                        $str = $value["post_title"];

                        if (key_exists($value["ID"], $postsSearchData)) {
                            $str = $this->findValueByField($postsSearchData, $value);
                        }

                        if (strtolower($str) == strtolower($_q)) {
                            $items[$key]["rs"] = 1000;
                        } else {
                            foreach (explode(" ", $_q) as $word) {
                                $word = preg_quote($word, '/');

                                if (!preg_match('/(?<=[\s,.:;"\']|^)' . $word . '(?=[\s,.:;"\']|$)/', strtolower($str))) {
                                    if (isset($items[$key]["rs"])) $items[$key]["rs"]--;
                                    else $items[$key]["rs"] = -1;
                                } else {
                                    if (isset($items[$key]["rs"])) $items[$key]["rs"]++;
                                    else $items[$key]["rs"] = 0;
                                }
                            }
                        }
                    }

                    array_multisort(array_column($items, 'rs'), SORT_DESC, $items);
                }
            }
        } catch (\Throwable $th) {
        }
    }

    private function findValueByField($postsSearchData, $item)
    {
        $field = array_search("1", (array)$postsSearchData[$item["ID"]]);
        $field = $this->getColumnName($field);

        if ($field) {
            return ($field && isset($item[$field])) ? $item[$field] : \get_post_meta($item["ID"], $field, true);
        } else {
            return "";
        }
    }

    private function getColumnName($name)
    {
        global $wpdb;

        if ($this->tableColumns == null) {
            $tableColumns = $wpdb->prefix . Database::$columns;
            $this->tableColumns = $wpdb->get_results("SELECT * FROM {$tableColumns}");
        }

        if (preg_match("/^column_.*?/", $name)) {
            foreach ($this->tableColumns as $value) {
                if ($value->column == $name) {
                    return $value->name;
                }
            }
        }

        return $name;
    }

    private function checkPostAutoAction($request, $action, &$products, $foundBy)
    {
        if (!$action || $foundBy || count($products) !== 1) {
            return false;
        }

        $ignoreStockDisabled = $request ? $request->get_param("ignoreStockDisabled") : true;

        $product = &$products[0];

        if (!in_array($product["post_type"], array("product", "product_variation"))) {
            return false;
        }

        if (in_array($product["product_type"], array("external"))) {
            return false;
        }

        if (!$product['product_manage_stock'] && $ignoreStockDisabled == true) {
            return false;
        }

        $actionError = false;

        $field = $request->get_param("postAutoField");
        $fieldName = $field ? $field : "_stock";

        switch ($action) {
            case $this->postAutoAction["AUTO_INCREASING"]:
                if ($fieldName == "_stock") {
                    if (isset($product["product_manage_stock"]) && isset($product["post_parent"]) && $product["product_manage_stock"] === "parent" && $product["post_parent"]) {
                        $ms = get_post_meta($product["post_parent"], "_manage_stock", true);
                        if ($ms == "yes" || true) $this->productUpdateQuantityPlus($request, $product["post_parent"]);
                        else $actionError = $this->postAutoAction["AUTO_INCREASING"];
                    } else {
                        $ms = get_post_meta($product["ID"], "_manage_stock", true);
                        if ($ms == "yes" || true) $this->productUpdateQuantityPlus($request, $product["ID"]);
                        else $actionError = $this->postAutoAction["AUTO_INCREASING"];
                    }
                }
                else {
                    $value = get_post_meta($product["ID"], $fieldName, true);
                    $value = $value && is_numeric($value) ? $value : 0;
                    update_post_meta($product["ID"], $fieldName, $value + 1);

                    $filterName = str_replace("%field", $fieldName, $this->filter_set_after);
                    apply_filters($filterName, $value + 1, $fieldName, $product["ID"]);

                    LogActions::add($product["ID"], LogActions::$actions["quantity_plus"], $fieldName, $value + 1, $value, "product", $request);
                }

                return $this->productSearch($request, false, false, $actionError);
            case $this->postAutoAction["AUTO_DECREASING"]:
                if ($fieldName == "_stock") {
                    if (isset($product["product_manage_stock"]) && isset($product["post_parent"]) && $product["product_manage_stock"] === "parent" && $product["post_parent"]) {
                        $ms = get_post_meta($product["post_parent"], "_manage_stock", true);
                        if ($ms == "yes") $this->productUpdateQuantityMinus($request, $product["post_parent"]);
                        else $actionError = $this->postAutoAction["AUTO_DECREASING"];
                    } else {
                        $ms = get_post_meta($product["ID"], "_manage_stock", true);
                        if ($ms == "yes") $this->productUpdateQuantityMinus($request, $product["ID"]);
                        else $actionError = $this->postAutoAction["AUTO_DECREASING"];
                    }
                }
                else {
                    $settings = new Settings();
                    $allowNegativeStock = $settings->getSettings("allowNegativeStock");
                    $allowNegativeStock = $allowNegativeStock ? $allowNegativeStock->value : "";

                    $value = get_post_meta($product["ID"], $fieldName, true);
                    $value = $value && is_numeric($value) ? $value : 0;

                    if ($value > 0 || $allowNegativeStock == "on") {
                        update_post_meta($product["ID"], $fieldName, $value - 1);

                        $filterName = str_replace("%field", $fieldName, $this->filter_set_after);
                        apply_filters($filterName, $value - 1, $fieldName, $product["ID"]);

                        LogActions::add($product["ID"], LogActions::$actions["quantity_minus"], $fieldName, $value - 1, $value, "product", $request);
                    }
                }

                return $this->productSearch($request, false, false, $actionError);
        }

        return false;
    }

    private function checkOrderAutoAction($request, $orderAutoAction, $orderAutoStatus, &$orders, $foundBy)
    {
        if (!$orderAutoAction || $foundBy || count($orders) !== 1) {
            return false;
        }

        $order = &$orders[0];

        if (!in_array($order["post_type"], array("shop_order"))) {
            return false;
        }

        if (!$orderAutoStatus) {
            return false;
        }

        $settings = new Settings();

        $fulfilledNotAllowStatus = $settings->getSettings("fulfilledNotAllowStatus");
        $fulfilledNotAllowStatus = $fulfilledNotAllowStatus === null ? 'off' : $fulfilledNotAllowStatus->value;

        if ($fulfilledNotAllowStatus == 'on') {
            if (isset($order['usbs_order_fulfillment_data']) && isset($order['usbs_order_fulfillment_data']['totalQty']) && isset($order['usbs_order_fulfillment_data']['totalScanned'])) {
                if ($order['usbs_order_fulfillment_data']['totalQty'] == $order['usbs_order_fulfillment_data']['totalScanned']) {
                    return false;
                }
            }
        }

        $orderId = $order["ID"];

        switch ($orderAutoAction) {
            case $this->orderAutoAction["ORDER_STATUS"]: {
                    $order = new \WC_Order($orderId);

                    if ($order) {
                        $oldValue = $order->get_status();
                        $order->update_status($orderAutoStatus);
                        $this->productIndexation($orderId, "orderChangeStatus");
                        LogActions::add($orderId, LogActions::$actions["update_order_status"], "post_status", $orderAutoStatus, $oldValue, "order", $request);
                    }


                    if ($request && $orderId) {
                        $request->set_param("query", $orderId);
                    }

                    return $this->orderSearch($request, false, true, true);
                    break;
                }
        }

        return false;
    }

    public function productEnableManageStock(WP_REST_Request $request)
    {
        $query = RequestHelper::getQuery($request, "product");
        $productId = $query;

        $settings = new Settings();
        $wpmlRow = $settings->getSettings("wpmlUpdateProductsTree");
        $isUpdateAllProds = $wpmlRow === null ? $settings->getField("general", "wpmlUpdateProductsTree", "") : $wpmlRow->value;

        $result = true;

        if ($isUpdateAllProds === "on" || true) {
            try {
                $productsIds = (array)$request->get_param("products");

                if (count($productsIds) === 0) {
                    $productsIds = array($productId);
                }

                if (count($productsIds) > 0) {
                    foreach ($productsIds as $id) {
                        $result = $this->setManageStock($id);
                        LogActions::add($id, LogActions::$actions["enable_stock"], "_stock_status", "on", "", "product", $request);
                    }
                }
            } catch (\Throwable $th) {
            }
        } else {
            $result = $this->setManageStock($productId);
            LogActions::add($productId, LogActions::$actions["enable_stock"], "_stock_status", "on", "", "product", $request);
        }

        if ($result === true) {
            return $this->productSearch($request, true, true);
        } else {
            return $result;
        }
    }

    private function setManageStock($productId)
    {
        $product = \wc_get_product($productId);

        if ($product) {
            $product->set_manage_stock(true);
            $product->save();

            Debug::addPoint("setManageStock true for productId: {$productId}");

            return true;
        } else {
            return array(
                "errors" => array("Product not found")
            );
        }
    }

    public function productUpdateQuantity(WP_REST_Request $request, $postId = null, $quantity = null)
    {
        $customFilter = $request->get_param("customFilter");
        $query = RequestHelper::getQuery($request, "product");
        $productId = $postId ? $postId : $query;

        if (!$quantity && (int)$quantity != 0) {
            $quantity = $request->get_param("quantity");
        }

        $filteredData = 1;
        $filteredData = apply_filters($this->filter_quantity_update, $productId, $quantity, $customFilter);

        $settings = new Settings();
        $wpmlRow = $settings->getSettings("wpmlUpdateProductsTree");
        $isUpdateAllProds = $wpmlRow === null ? $settings->getField("general", "wpmlUpdateProductsTree", "") : $wpmlRow->value;

        $result = true;

        if (($isUpdateAllProds === "on" || true) && $filteredData !== null) {
            try {
                $productsIds = (array)$request->get_param("products");

                if (count($productsIds) === 0) {
                    $productsIds = array($productId);
                }

                if (count($productsIds) > 0) {
                    foreach ($productsIds as $id) {
                        $oldValue = get_post_meta($id, '_stock', true);
                        $result = $this->setQuantity($id, $quantity, null, true);
                        LogActions::add($id, LogActions::$actions["update_qty"], "_stock", $quantity, $oldValue, "product", $request);
                    }
                }
            } catch (\Throwable $th) {
            }
        } else if ($filteredData !== null) {
            $oldValue = get_post_meta($productId, '_stock', true);
            $result = $this->setQuantity($productId, $quantity, null, true);
            LogActions::add($productId, LogActions::$actions["update_qty"], "_stock", $quantity, $oldValue, "product", $request);
        }

        if ($result === true) {
            return $this->productSearch($request, true, true);
        } else {
            return $result;
        }
    }

    public function setQuantity($productId, $quantity, $product = null, $checkHershold = false)
    {
        if ($quantity !== "") $this->setManageStock($productId);

        if (!$product) {
            $product = \wc_get_product($productId);
        }

        if ($product) {
            Debug::addPoint("setQuantity productId: {$productId}, quantity: {$quantity}");

            $product->set_stock_quantity($quantity);
            $product->save();

            if ($quantity !== "") {
                update_post_meta($product->get_id(), "_stock", $quantity);
            }

            $filterName = str_replace("%field", "_stock", $this->filter_set_after);
            apply_filters($filterName, $quantity, "_stock", $product->get_id());

            if ($checkHershold) {
                $manageStock = get_post_meta($productId, "_manage_stock", true);
                $lowStockHershold = \wc_get_low_stock_amount($product);

                if (
                    $lowStockHershold != null
                    && $lowStockHershold != ""
                    && is_numeric($quantity)
                    && $quantity <= $lowStockHershold
                    && $manageStock == "yes"
                    && $product->get_manage_stock()
                ) {
                    Emails::sendLowStock($productId, $quantity, $product->get_name(), $lowStockHershold);
                }
            }

            $this->productIndexation($productId, "setQuantity");
            return true;
        } else {
            return rest_ensure_response(array(
                "errors" => array("Product not found")
            ));
        }
    }

    public function productUpdateQuantityPlus(WP_REST_Request $request, $productId = null)
    {
        $valid = (new Request())->validate($request);

        if ($valid !== true) {
            return $valid;
        }

        $customFilter = $request->get_param("customFilter");
        $query = RequestHelper::getQuery($request, "product");
        $productId = ($productId) ? $productId : $query;

        $filteredData = 1;
        $filteredData = apply_filters($this->filter_quantity_plus, $productId, $customFilter);

        $settings = new Settings();
        $wpmlRow = $settings->getSettings("wpmlUpdateProductsTree");
        $isUpdateAllProds = $wpmlRow === null ? $settings->getField("general", "wpmlUpdateProductsTree", "") : $wpmlRow->value;

        $result = true;

        if (($isUpdateAllProds === "on" || true) && $filteredData !== null) {

            try {
                $productsIds = (array)$request->get_param("products");

                if (count($productsIds) === 0) {
                    $translations = WPML::getProductTranslations($productId);

                    if ($translations) {
                        $productsIds = array_column($translations["translations"], 'element_id');
                    }
                }

                if (count($productsIds) > 0) {
                    $curQuantity = null;
                    $step = null;

                    foreach ($productsIds as $id) {

                        if ($curQuantity === null) {
                            $product = \wc_get_product($id);

                            $step = apply_filters($this->filter_auto_action_step, 1, $id);

                            if ($step == 1) {
                                $curQuantity = (float)$product->get_stock_quantity();
                            } else {
                                $curQuantity = (float)get_post_meta($productId, "_stock", true);
                            }
                        }

                        $this->qtyBeforeUpdate[$productId] = $curQuantity;

                        $result = $this->setQuantityPlus($request, $id, $curQuantity, $step);
                    }
                } else if ($productId) {
                    $result = $this->setQuantityPlus($request, $productId);
                }
            } catch (\Throwable $th) {
            }
        } else if ($filteredData !== null) {
            $result = $this->setQuantityPlus($request, $productId);
        }

        if ($result === true) {
            return $this->productSearch($request, false, true);
        } else {
            return $result;
        }
    }

    public function setQuantityPlus($request, $productId = null, $curQty = null, $step = null)
    {
        $this->setManageStock($productId);

        $product = \wc_get_product($productId);

        if ($product) {
            if ($curQty === null && $step === null) {
                $step = apply_filters($this->filter_auto_action_step, 1, $productId);

                if ($step == 1) {
                    $qty = (float)$product->get_stock_quantity();
                } else {
                    $qty = (float)get_post_meta($productId, "_stock", true);
                }
            } else {
                $step = 1;
                $qty = $curQty;
            }

            if ($qty == 0) {
                $this->setQuantity($productId, $step, null, true);
                LogActions::add($productId, LogActions::$actions["quantity_plus"], "_stock", $step, 0, "product", $request);
            } else {
                $this->setQuantity($productId, $qty + $step, null, true);
                LogActions::add($productId, LogActions::$actions["quantity_plus"], "_stock", $qty + $step, $qty, "product", $request);
            }

            return true;
        } else {
            return rest_ensure_response(array(
                "errors" => array("Product not found")
            ));
        }
    }

    public function productUpdateQuantityMinus(WP_REST_Request $request, $productId = null)
    {
        global $wpdb;

        $valid = (new Request())->validate($request);

        if ($valid !== true) {
            return $valid;
        }

        $customFilter = $request->get_param("customFilter");
        $query = RequestHelper::getQuery($request, "product");
        $productId = ($productId) ? $productId : $query;

        $filteredData = 1;
        $filteredData = apply_filters($this->filter_quantity_minus, $productId, $customFilter);

        $settings = new Settings();
        $wpmlRow = $settings->getSettings("wpmlUpdateProductsTree");
        $isUpdateAllProds = $wpmlRow === null ? $settings->getField("general", "wpmlUpdateProductsTree", "") : $wpmlRow->value;

        $result = true;

        Debug::addPoint("isUpdateAllProds: {$isUpdateAllProds}");

        if (($isUpdateAllProds === "on" || true) && $filteredData !== null) {
            try {
                $productsIds = (array)$request->get_param("products");

                if (count($productsIds) === 0) {
                    $translations = WPML::getProductTranslations($productId);

                    Debug::addPoint("productUpdateQuantityMinus translations: " . json_encode($translations));

                    if ($translations) {
                        $productsIds = array_column($translations["translations"], 'element_id');
                    }
                }

                if (count($productsIds) > 0) {
                    $curQuantity = null;
                    $step = null;

                    foreach ($productsIds as $id) {

                        if ($curQuantity === null) {
                            $product = \wc_get_product($id);

                            $step = apply_filters($this->filter_auto_action_step, 1, $id);

                            if ($step == 1) {
                                $curQuantity = (float)$product->get_stock_quantity();
                            } else {
                                $curQuantity = (float)get_post_meta($productId, "_stock", true);
                            }
                        }

                        $this->qtyBeforeUpdate[$productId] = $curQuantity;

                        $result = $this->setQuantityMinus($request, $id, $curQuantity, $step);
                    }
                } else if ($productId) {
                    $result = $this->setQuantityMinus($request, $productId);
                }
            } catch (\Throwable $th) {
            }
        } else if ($filteredData !== null) {
            $result = $this->setQuantityMinus($request, $productId);
        }

        if ($result === true) {
            return $this->productSearch($request, false, true);
        } else {
            return $result;
        }
    }

    private function setQuantityMinus($request, $productId = null, $curQty = null, $step = null)
    {
        $this->setManageStock($productId);

        $settings = new Settings();
        $allowNegativeStock = $settings->getSettings("allowNegativeStock");
        $allowNegativeStock = $allowNegativeStock ? $allowNegativeStock->value : "";
        $product = \wc_get_product($productId);

        if ($product) {
            if ($curQty === null && $step === null) {
                $step = apply_filters($this->filter_auto_action_step, 1, $productId);

                if ($step == 1) {
                    $qty = (float)$product->get_stock_quantity();
                } else {
                    $qty = (float)get_post_meta($productId, "_stock", true);
                }

                $this->qtyBeforeUpdate[$productId] = $qty;
            } else {
                $step = 1;
                $qty = $curQty;
            }

            if ($qty > 0 || $allowNegativeStock === "on") {
                $this->setQuantity($productId, $qty - $step, null, true);
                LogActions::add($productId, LogActions::$actions["quantity_minus"], "_stock", $qty - $step, $qty, "product", $request);
            }

            return true;
        } else {
            return rest_ensure_response(array(
                "errors" => array("Product not found")
            ));
        }
    }

    public function productUpdateRegularPrice(WP_REST_Request $request, $postId = null, $regularPrice = null)
    {
        $query = RequestHelper::getQuery($request, "product");
        $price = $request->get_param("price");
        $productId = $postId ? $postId : $query;
        $price = $regularPrice ? $regularPrice : $price;

        if ($price == 0) {
            $price = "";
        }

        $settings = new Settings();
        $wpmlRow = $settings->getSettings("wpmlUpdateProductsTree");
        $isUpdateAllProds = $wpmlRow === null ? $settings->getField("general", "wpmlUpdateProductsTree", "") : $wpmlRow->value;

        $result = true;

        if ($isUpdateAllProds === "on" || true) {
            try {
                $productsIds = (array)$request->get_param("products");

                if (count($productsIds) === 0) {
                    $productsIds = array($productId);
                }

                if (count($productsIds) > 0) {
                    foreach ($productsIds as $id) {
                        $result = $this->setRegularPrice($request, $id, $price);
                    }
                }
            } catch (\Throwable $th) {
            }
        } else {
            $result = $this->setRegularPrice($request, $productId, $price);
        }

        if ($result === true) {
            return $this->productSearch($request, false, true);
        } else {
            return $result;
        }
    }

    private function setRegularPrice($request, $productId, $price)
    {
        $product = \wc_get_product($productId);

        if ($product ) {
            $oldValue = $product->get_regular_price();
            $product->set_regular_price($price);
            $product->save();

            $this->clearProductCache($productId);
            $this->productIndexation($productId, "setRegularPrice");
            LogActions::add($productId, LogActions::$actions["update_regular_price"], "_regular_price", $price, $oldValue, "product", $request);

            return true;
        } else {
            return rest_ensure_response(array(
                "errors" => array("Product not found")
            ));
        }
    }

    public function productUpdateSalePrice(WP_REST_Request $request, $postId = null, $salePrice = null)
    {
        $query = RequestHelper::getQuery($request, "product");
        $price = $request->get_param("price");
        $productId = $postId ? $postId : $query;
        $price = $salePrice ? $salePrice : $price;

        if ($price == 0) {
            $price = "";
        }

        $settings = new Settings();
        $wpmlRow = $settings->getSettings("wpmlUpdateProductsTree");
        $isUpdateAllProds = $wpmlRow === null ? $settings->getField("general", "wpmlUpdateProductsTree", "") : $wpmlRow->value;

        $result = true;

        if ($isUpdateAllProds === "on" || true) {
            try {
                $productsIds = (array)$request->get_param("products");

                if (count($productsIds) === 0) {
                    $productsIds = array($productId);
                }

                if (count($productsIds) > 0) {
                    foreach ($productsIds as $id) {
                        $result = $this->setSalePrice($request, $id, $price);
                    }
                }
            } catch (\Throwable $th) {
            }
        } else {
            $result = $this->setSalePrice($request, $productId, $price);
        }

        if ($result === true) {
            return $this->productSearch($request, false, true);
        } else {
            return $result;
        }
    }

    public function updateProductCustomPrice(WP_REST_Request $request, $postId = null, $salePrice = null)
    {
        $query = RequestHelper::getQuery($request, "product");
        $price = $request->get_param("price");
        $field = $request->get_param("field");
        $productId = $postId ? $postId : $query;
        $price = $salePrice ? $salePrice : $price;

        $productsIds = (array)$request->get_param("products");


        $settings = new Settings();
        $wpmlRow = $settings->getSettings("wpmlUpdateProductsTree");
        $isUpdateAllProds = $wpmlRow === null ? $settings->getField("general", "wpmlUpdateProductsTree", "") : $wpmlRow->value;

        $result = true;

        if ($isUpdateAllProds === "on" || true) {
            try {
                if (count($productsIds) === 0) {
                    $productsIds = array($productId);
                }

                if (count($productsIds) > 0) {
                    foreach ($productsIds as $id) {
                        $filterName = str_replace("%field", $field, $this->filter_set_after);
                        $filteredValue = apply_filters($filterName, $price, $field, $id);

                        if ($field === "_regular_price") {
                            $this->setRegularPrice($request, $id, $filteredValue);
                        } else if ($field === "_sale_price") {
                            $this->setSalePrice($request, $id, $filteredValue);
                        } else {
                            $oldValue = \get_post_meta($id, $field, true);
                            $result = $this->updateCustomField($id, $field, $filteredValue);
                            $customACtion = $this->getPriceFieldLabel($field);
                            LogActions::add($id, LogActions::$actions["update_custom_field"], $field, $filteredValue, $oldValue, "product", $request, $customACtion);
                        }

                        $this->clearProductCache($id);
                    }
                }
            } catch (\Throwable $th) {
            }
        } else {
            $filterName = str_replace("%field", $field, $this->filter_set_after);
            $filteredValue = apply_filters($filterName, $price, $field, $productId);

            if ($field === "_regular_price") {
                $this->setRegularPrice($request, $productId, $filteredValue);
            } else if ($field === "_sale_price") {
                $this->setSalePrice($request, $productId, $filteredValue);
            } else {
                $oldValue = \get_post_meta($productId, $field, true);
                $result = $this->updateCustomField($productId, $field, $filteredValue);
                $customACtion = $this->getPriceFieldLabel($field);
                LogActions::add($productId, LogActions::$actions["update_custom_field"], $field, $filteredValue, $oldValue, "product", $request, $customACtion);
            }

            $this->clearProductCache($productId);
        }

        if ($result === true) {
            return $this->productSearch($request, false, true);
        } else {
            return $result;
        }
    }

    public function productUpdateMeta(WP_REST_Request $request, $productId = null, $key = null, $value = null)
    {
        $customAction = $request->get_param("customAction");
        $withoutStatuses = $request->get_param("withoutStatuses");

        if ($key == null || ($value == null && $value !== "") || $productId == null) {
            $key = $request->get_param("key");
            $value = $request->get_param("value");
            $value = is_array($value) && empty($value) ? "" : $value;
            $productId = RequestHelper::getQuery($request, "product");
        }

        if ($value) $value = trim($value);

        $settings = new Settings();
        $wpmlRow = $settings->getSettings("wpmlUpdateProductsTree");
        $isUpdateAllProds = $wpmlRow === null ? $settings->getField("general", "wpmlUpdateProductsTree", "") : $wpmlRow->value;

        $result = true;

        if ($isUpdateAllProds === "on" || true) {
            try {
                $productsIds = (array)$request->get_param("products");

                if (count($productsIds) === 0) {
                    $productsIds = array($productId);
                }

                if (count($productsIds) > 0) {
                    foreach ($productsIds as $id) {
                        $filterName = str_replace("%field", $key, $this->filter_set_after);
                        $filteredValue = apply_filters($filterName, $value, $key, $id);

                        if ($key === "_sku") {
                            $oldValue = \get_post_meta($id, "_sku", true);
                            $result = $this->setSKU($id, $filteredValue);
                            LogActions::add($id, LogActions::$actions["sku"], "_sku", $filteredValue, $oldValue, "product", $request);
                        } else if ($key === "_stock") {
                            if ($filteredValue === "") {
                                $this->productUpdateMeta($request, $productId, "_manage_stock", "no");
                            }
                            $result = $this->productUpdateQuantity($request, $id, $filteredValue);
                        } else if ($key === "usbs_product_status") {
                            $result = $this->productUpdateStatus($request, $productId, $filteredValue);
                        } else if ($key === "_shipping_class") {
                            $result = $this->productUpdateShippingClass($request, $productId, $filteredValue);
                        } else if ($key === "usbs_variation_attributes") {
                            $result = $this->variationUpdateAttributes($request, $productId, $filteredValue);
                        } else {
                            $oldValue = \get_post_meta($id, $key, true);
                            update_post_meta($id, $key, $filteredValue);
                            LogActions::add($id, LogActions::$actions["update_meta_field"], $key, $filteredValue, $oldValue, "product", $request, $customAction);
                        }

                        $this->productIndexation($id, "productUpdateMeta");
                    }
                }
            } catch (\Throwable $th) {
            }
        }

        if ($result === true) {
            return $this->productSearch($request, false, true, "", $withoutStatuses == "1");
        } else {
            return $result;
        }
    }

    private function setSKU($productId, $sku)
    {
        try {
            $product = \wc_get_product($productId);

            if ($product) {
                $product->set_sku($sku);
                $product->save();

                $this->productIndexation($productId, "setSKU");

                return true;
            } else {
                return rest_ensure_response(array(
                    "errors" => array("Product not found")
                ));
            }
        } catch (\Throwable $th) {
            return rest_ensure_response(array(
                "errors" => array($th->getMessage())
            ));
        }
    }

    public function productUpdateStatus(WP_REST_Request $request, $postId = null, $status = null)
    {
        try {
            $postId = $postId ? $postId : $request->get_param("query");
            $status = $status ? $status : $request->get_param("status");

            if (!$postId || !$status) {
                return rest_ensure_response(array("errors" => array("Something wrong")));
            }
            $oldValue = get_post_status($postId);
            wp_update_post(array('ID' => $postId, 'post_status' => $status));
            $this->productIndexation($postId, "setSalePrice");

            LogActions::add($postId, LogActions::$actions["update_product_status"], "post_status", $status, $oldValue, "product", $request);

            return $this->productSearch($request, false, true);
        } catch (\Throwable $th) {
            return rest_ensure_response(array("errors" => array($th->getMessage())));
        }
    }

    public function productUpdateShippingClass(WP_REST_Request $request, $postId = null, $value = null)
    {
        try {
            wp_set_object_terms($postId, $value, 'product_shipping_class');
            LogActions::add($postId, LogActions::$actions["update_product_shipping"], "shipping class", $value, "", "product", $request);

            return $this->productSearch($request, false, true);
        } catch (\Throwable $th) {
            return rest_ensure_response(array("errors" => array($th->getMessage())));
        }
    }

    public function variationUpdateAttributes(WP_REST_Request $request, $postId = null, $value = null)
    {
        try {
            if ($postId && $value && isset($value['attribute']) && isset($value['value'])) {
                $variation = new \WC_Product_Variation($postId);

                if ($variation->get_name()) {
                    $attributes = $variation->get_attributes();

                    $attributes[$value['attribute']] = $value['value'];

                    $variation->set_attributes($attributes);

                    $variation->save();
                }
            }

            return $this->productSearch($request, false, true);
        } catch (\Throwable $th) {
            return rest_ensure_response(array("errors" => array($th->getMessage())));
        }
    }

    private function setSalePrice($request, $productId, $price)
    {
        $product = \wc_get_product($productId);

        if ($product && !$product->is_type('variable')) {
            $oldValue = $product->get_sale_price();
            $product->set_sale_price($price);
            $product->save();

            $this->clearProductCache($productId);
            $this->productIndexation($productId, "setSalePrice");
            LogActions::add($productId, LogActions::$actions["update_sale_price"], "_sale_price", $price, $oldValue, "product", $request);

            return true;
        } else {
            return rest_ensure_response(array(
                "errors" => array("Product not found")
            ));
        }
    }

    private function updateCustomField($postId, $field, $value)
    {
        update_post_meta($postId, $field, $value);

        $this->productIndexation($postId, "updateCustomField");

        return true;
    }

    public function orderSearch(WP_REST_Request $request, $actions = true, $findById = false, $afterActions = false)
    {
        $userId = Users::getUserId($request);

        $autoFill = $request->get_param("autoFill");
        $query = RequestHelper::getQuery($request, "order");
        $filter = SearchFilter::get($userId);
        $customFilter = $request->get_param("customFilter");
        $filterExcludes = $afterActions ? array() : $request->get_param("filterExcludes");
        $platform = $request->get_param("platform");
        $orderAutoAction = $request->get_param("orderAutoAction");
        $orderAutoStatus = $request->get_param("orderAutoStatus");
        $postAutoField = $request->get_param("postAutoField");
        $postAutoField = $postAutoField ? $postAutoField : "_stock";
        $isNew = $request->get_param("isNew");
        $filterResult = $request->get_param("filterResult");
        $page = $request->get_param("page");
        $fulfillmentOrderId = $request->get_param("fulfillmentOrderId");

        $byId = $request->get_param("byId");

        if ($filterResult && isset($filterResult["postId"])) {
            $byId = true;
        }

        $onlyById = $byId || $findById ? true : false;

        $result = array(
            "orders" => null,
            "findByTitle" => null,
            "isNew" => $isNew ? 1 : 0
        );

        if (HPOS::getStatus()) {
            Debug::addPoint("start HPOS()->findOrders");
            $data = HPOS::findOrders($query, $filter, $onlyById, $autoFill, $filterExcludes);
            Debug::addPoint("end HPOS()->findOrders");

            if ($filterResult && $data && isset($filterResult["postId"]) && is_array($data["posts"]) && count($data["posts"]) > 1) {
                $data["posts"] = array_values(array_filter(
                    $data["posts"],
                    function ($_post) use ($filterResult) {
                        return $_post->id == $filterResult["postId"];
                    }
                ));
            }

            if ($fulfillmentOrderId && !$autoFill) {
                $ifOrderAlreadyFulfilled = $this->checkIfOrderAlreadyFulfilled($fulfillmentOrderId);
                $ifCanSwitchOrder = $this->checkIfCanSwitchOrder($fulfillmentOrderId);

                if ($ifOrderAlreadyFulfilled && is_array($data["posts"]) && count($data["posts"]) == 1 && $data["posts"][0]->type != 'shop_order') {
                    return $ifOrderAlreadyFulfilled;
                }

                if ($ifCanSwitchOrder && is_array($data["posts"]) && count($data["posts"]) == 1 && $data["posts"][0]->type == 'shop_order') {
                    return $ifCanSwitchOrder;
                }
            }

            Debug::addPoint("start HPOS()->ordersPrepare");
            $postCounter = $data["posts"] ? count($data["posts"]) : 0;
            $orders = HPOS::ordersPrepare($data["posts"], array(
                "useAction" => $orderAutoAction && $orderAutoAction != "empty" ? $postAutoField : false,
                "autoStatus" => $orderAutoStatus,
                "isNew" => $isNew ? 1 : 0,
                "isAutoFill" => $autoFill
            ), $autoFill || $postCounter > 1);
            Debug::addPoint("end HPOS()->ordersPrepare");
        }
        else {
            Debug::addPoint("start Post()->find");
            $data = (new Post())->find($query, $filter, $onlyById, $autoFill, null, "order", $filterExcludes);
            Debug::addPoint("end Post()->find");

            if ($filterResult && $data && isset($filterResult["postId"]) && is_array($data["posts"]) && count($data["posts"]) > 1) {
                $data["posts"] = array_values(array_filter(
                    $data["posts"],
                    function ($_post) use ($filterResult) {
                        return $_post->ID == $filterResult["postId"];
                    }
                ));
            }

            if ($fulfillmentOrderId && !$autoFill) {
                $ifOrderAlreadyFulfilled = $this->checkIfOrderAlreadyFulfilled($fulfillmentOrderId);
                $ifCanSwitchOrder = $this->checkIfCanSwitchOrder($fulfillmentOrderId);

                if ($ifOrderAlreadyFulfilled && is_array($data["posts"]) && count($data["posts"]) == 1 && $data["posts"][0]->type != 'shop_order') {
                    return $ifOrderAlreadyFulfilled;
                }

                if ($ifCanSwitchOrder && is_array($data["posts"]) && count($data["posts"]) == 1 && $data["posts"][0]->type == 'shop_order') {
                    return $ifCanSwitchOrder;
                }
            }

            Debug::addPoint("start Results()->ordersPrepare");
            $postCounter = $data["posts"] ? count($data["posts"]) : 0;
            $orders = (new Results())->ordersPrepare($data["posts"], array(
                "useAction" => $orderAutoAction && $orderAutoAction != "empty" ? $postAutoField : false,
                "autoStatus" => $orderAutoStatus,
                "isNew" => $isNew ? 1 : 0,
                "isAutoFill" => $autoFill
            ), $autoFill || $postCounter > 1, $page);
            Debug::addPoint("end Results()->ordersPrepare");
        }

        if ($orders) {
            if ($actions === true) {
                $actionResult = $this->checkOrderAutoAction($request, $orderAutoAction, $orderAutoStatus, $orders, $data["findByTitle"]);
                if ($actionResult !== false) {
                    return $actionResult;
                }
            }

            $orders = apply_filters($this->filter_search_result, $orders, $customFilter);

            $result['orders'] = $orders;
            $result['findByTitle'] = $data["findByTitle"];
        }
        else {
            $requestName = $request->get_param("reqbs");

            if ($requestName === "order-search") {
                return $this->productSearch($request, false);
            }
        }


        if (isset($data["query"])) {
            $result["foundBy"] = $data["query"];
        }

        if (isset($result["orders"]) && count($result["orders"]) === 1 && !$autoFill && !$findById) {
            if (is_object($result["orders"][0])) {
                LogActions::add($result["orders"][0]->get_ID(), LogActions::$actions["open_order"], "", "", "", "order", $request);
            } else {
                LogActions::add($result["orders"][0]["ID"], LogActions::$actions["open_order"], "", "", "", "order", $request);
            }
        }

        $result['debug'] = Debug::getResult(true);

        return rest_ensure_response($result);
    }

    private function checkIfOrderAlreadyFulfilled($fulfillmentOrderId)
    {
        if ($fulfillmentOrderId) {
            $orderFulfillmentData = get_post_meta($fulfillmentOrderId, "usbs_order_fulfillment_data", true);

            if ($orderFulfillmentData && $orderFulfillmentData["totalQty"] && $orderFulfillmentData["totalScanned"] && $orderFulfillmentData["totalQty"] == $orderFulfillmentData["totalScanned"]) {
                return rest_ensure_response(array(
                    "success" => true,
                    "fulfillmentResult" => true,
                    "fulfillment" => 1,
                    "updatedOrder" => array("ID" => $fulfillmentOrderId),
                    "error" => __("This order is already fulfilled!!", "us-barcode-scanner"),
                    "htmlMessageClass" => "ff_order_already_fulfilled",
                    "data" => $orderFulfillmentData,
                    "debug" => Debug::getResult(true)
                ));
            }
        }

        return null;
    }

    private function checkIfCanSwitchOrder($fulfillmentOrderId)
    {
        if ($fulfillmentOrderId) {
            $orderFulfillmentData = get_post_meta($fulfillmentOrderId, "usbs_order_fulfillment_data", true);

            $settings = new Settings();
            $dontAllowSwitchOrder = $settings->getSettings("dontAllowSwitchOrder");
            $dontAllowSwitchOrder = $dontAllowSwitchOrder === null ? "" : $dontAllowSwitchOrder->value;

            if ($dontAllowSwitchOrder == "on") {
                if ($orderFulfillmentData && isset($orderFulfillmentData['totalQty']) && isset($orderFulfillmentData['totalScanned'])) {
                    if ($orderFulfillmentData['totalQty'] == $orderFulfillmentData['totalScanned']) {
                    } else {
                        return rest_ensure_response(array(
                            "success" => true,
                            "fulfillmentResult" => true,
                            "fulfillment" => 1,
                            "updatedOrder" => array("ID" => $fulfillmentOrderId),
                            "error" => __("You cannot switch order until fulfillment is completed.", "us-barcode-scanner"),
                            "htmlMessageClass" => "ff_order_not_completed",
                            "data" => $orderFulfillmentData,
                            "debug" => Debug::getResult(true)
                        ));
                    }
                }
            }
        }

        return null;
    }

    public function orderChangeStatus(WP_REST_Request $request)
    {
        Debug::addPoint(" - orderChangeStatus");

        $orderId = $request->get_param("orderId");
        $status = $request->get_param("status");


        $order = new \WC_Order($orderId);
        Debug::addPoint(" - orderChangeStatus WC_Order");

        if ($order) {
            $oldValue = $order->get_status();
            Debug::addPoint(" - orderChangeStatus get current status");

            $order->update_status($status);
            Debug::addPoint(" - orderChangeStatus status changed");

            $this->productIndexation($orderId, "orderChangeStatus");
            Debug::addPoint(" - orderChangeStatus indexation");

            LogActions::add($orderId, LogActions::$actions["update_order_status"], "post_status", $status, $oldValue, "order", $request);
            Debug::addPoint(" - orderChangeStatus logged");
        }

        $result = array(
            "orders" => null,
            "findByTitle" => null,
            "post_status" => $order->get_status($orderId),
            "debug" => Debug::getResult(true),
        );

        return rest_ensure_response($result);
    }

    public function orderChangeCustomer(WP_REST_Request $request)
    {
        global $wpdb;

        $orderId = $request->get_param("orderId");
        $customerId = $request->get_param("customerId");
        $customFilter = $request->get_param("customFilter");
        $filterExcludes = $request->get_param("filterExcludes");

        $result = array(
            "orders" => null,
            "findByTitle" => null,
        );

        $order = new \WC_Order($orderId);

        if ($order) {
            $oldValue = $order->get_customer_id();

            $order->set_customer_id($customerId);

            $address = array(
                'first_name' => get_user_meta($customerId, "billing_first_name", true),
                'last_name'  => get_user_meta($customerId, "billing_last_name", true),
                'company'    => get_user_meta($customerId, "billing_company", true),
                'email'      => get_user_meta($customerId, "billing_email", true),
                'phone'      => get_user_meta($customerId, "billing_phone", true),
                'address_1'  => get_user_meta($customerId, "billing_address_1", true),
                'address_2'  => get_user_meta($customerId, "billing_address_2", true),
                'city'       => get_user_meta($customerId, "billing_city", true),
                'state'      => get_user_meta($customerId, "billing_state", true),
                'postcode'   => get_user_meta($customerId, "billing_postcode", true),
                'country'    => get_user_meta($customerId, "billing_country", true),
            );
            $order->set_address($address, 'billing');

            $prefix = true ? "billing" : "shipping";
            $address = array(
                'first_name' => get_user_meta($customerId, $prefix . "_first_name", true),
                'last_name'  => get_user_meta($customerId, $prefix . "_last_name", true),
                'company'    => get_user_meta($customerId, $prefix . "_company", true),
                'phone'      => get_user_meta($customerId, $prefix . "_phone", true),
                'address_1'  => get_user_meta($customerId, $prefix . "_address_1", true),
                'address_2'  => get_user_meta($customerId, $prefix . "_address_2", true),
                'city'       => get_user_meta($customerId, $prefix . "_city", true),
                'state'      => get_user_meta($customerId, $prefix . "_state", true),
                'postcode'   => get_user_meta($customerId, $prefix . "_postcode", true),
                'country'    => get_user_meta($customerId, $prefix . "_country", true),
            );
            $order->set_address($address, 'shipping');


            $order->save();

            LogActions::add($orderId, LogActions::$actions["update_order_customer"], "_customer_user", $customerId, $oldValue, "order", $request);

            $this->productIndexation($orderId, "orderChangeCustomer");
        }




        $bStates = WC()->countries->get_states($order->get_billing_country());
        $bState  = !empty($bStates[$order->get_billing_state()]) ? $bStates[$order->get_billing_state()] : '';

        $sStates = WC()->countries->get_states($order->get_shipping_country());
        $sState  = !empty($sStates[$order->get_shipping_state()]) ? $sStates[$order->get_shipping_state()] : '';

        $customerName = $order->get_billing_first_name() . " " . $order->get_billing_last_name();
        $customerName = trim($customerName);

        if (!$customerName && $customerId) {
            $customerName = get_user_meta($customerId, 'first_name', true) . ' ' . get_user_meta($customerId, 'last_name', true);
        }

        $updatedOrder = array(
            "ID" => $orderId,
            "customer_id" => $order->get_customer_id(),
            "customer_name" => $customerName,
            "data" =>  array(
                "billing" => array(
                    'first_name' => $order->get_billing_first_name(),
                    'last_name' => $order->get_billing_last_name(),
                    'company' => $order->get_billing_company(),
                    'email' => $order->get_billing_email(),
                    'phone' => $order->get_billing_phone(),
                    'address_1' => $order->get_billing_address_1(),
                    'address_2' => $order->get_billing_address_2(),
                    'postcode' => $order->get_billing_postcode(),
                    'city' => $order->get_billing_city(),
                    'state' => $order->get_billing_state(),
                    'state_name' => $bState,
                    'country' => $order->get_billing_country(),
                    'country_name' => $order->get_billing_country() ? WC()->countries->countries[$order->get_billing_country()] : "",
                ),
                "shipping" => array(
                    'first_name' => $order->get_shipping_first_name(),
                    'last_name' => $order->get_shipping_last_name(),
                    'company' => $order->get_shipping_company(),
                    'address_1' => $order->get_shipping_address_1(),
                    'address_2' => $order->get_shipping_address_2(),
                    'postcode' => $order->get_shipping_postcode(),
                    'city' => $order->get_shipping_city(),
                    'state' => $order->get_shipping_state(),
                    'state_name' => $sState,
                    'country' => $order->get_shipping_country(),
                    'country_name' => $order->get_shipping_country() ? WC()->countries->countries[$order->get_shipping_country()] : "",
                ),
                "customer_note" => (new Results())->getNotes($order),
                "total_tax" => $order->get_total_tax(),
                "status" => $order->get_status(),
                "customFields" => (new Results())->getOrderCustomFields($order->get_id())
            ),
        );

        return rest_ensure_response(array("updatedOrder" => $updatedOrder));
    }

    public function changeOrderAddress(WP_REST_Request $request)
    {
        global $wpdb;

        $orderId = $request->get_param("orderId");
        $address = $request->get_param("address");
        $customFilter = $request->get_param("customFilter");
        $filterExcludes = $request->get_param("filterExcludes");

        $result = array(
            "orders" => null,
            "findByTitle" => null,
        );

        $order = new \WC_Order($orderId);

        if ($order && $address) {
            $shippAsBill = isset($address['shipping_as_billing']) && $address['shipping_as_billing'] ? true : false;
            $bAddress = array(
                'first_name' => isset($address['billing_first_name']) ? $address['billing_first_name'] : "",
                'last_name'  => isset($address['billing_last_name']) ? $address['billing_last_name'] : "",
                'company'    => isset($address['billing_company']) ? $address['billing_company'] : "",
                'email'      => isset($address['billing_email']) ? $address['billing_email'] : "",
                'phone'      => isset($address['billing_phone']) ? $address['billing_phone'] : "",
                'address_1'  => isset($address['billing_address_1']) ? $address['billing_address_1'] : "",
                'address_2'  => isset($address['billing_address_2']) ? $address['billing_address_2'] : "",
                'city'       => isset($address['billing_city']) ? $address['billing_city'] : "",
                'state'      => isset($address['billing_state']) ? $address['billing_state'] : "",
                'postcode'   => isset($address['billing_postcode']) ? $address['billing_postcode'] : "",
                'country'    => isset($address['billing_country']) ? $address['billing_country'] : ""
            );
            $order->set_address($bAddress, 'billing');

            $prefix = $shippAsBill ? "billing" : "shipping";
            $sAddress = array(
                'first_name' => isset($address[$prefix . '_first_name']) ? $address[$prefix . '_first_name'] : "",
                'last_name'  => isset($address[$prefix . '_last_name']) ? $address[$prefix . '_last_name'] : "",
                'company'    => isset($address[$prefix . '_company']) ? $address[$prefix . '_company'] : "",
                'phone'      => isset($address[$prefix . '_phone']) ? $address[$prefix . '_phone'] : "",
                'address_1'  => isset($address[$prefix . '_address_1']) ? $address[$prefix . '_address_1'] : "",
                'address_2'  => isset($address[$prefix . '_address_2']) ? $address[$prefix . '_address_2'] : "",
                'city'       => isset($address[$prefix . '_city']) ? $address[$prefix . '_city'] : "",
                'state'      => isset($address[$prefix . '_state']) ? $address[$prefix . '_state'] : "",
                'postcode'   => isset($address[$prefix . '_postcode']) ? $address[$prefix . '_postcode'] : "",
                'country'    => isset($address[$prefix . '_country']) ? $address[$prefix . '_country'] : ""
            );

            $order->set_address($sAddress, 'shipping');


            if ($address) {
                $orderCustomFields = array();

                foreach ($address as $key => $value) {
                    if (strpos($key, 'uscf_') === 0) {
                        $orderCustomFields[str_replace('uscf_', '', $key)] = $value;
                    }
                }

                apply_filters('barcode_scanner_save_order_custom_fields_data', $orderId, $orderCustomFields);
            }

            $order->save();


            $this->productIndexation($orderId, "orderChangeCustomer");
        }




        $bStates = WC()->countries->get_states($order->get_billing_country());
        $bState  = !empty($bStates[$order->get_billing_state()]) ? $bStates[$order->get_billing_state()] : '';

        $sStates = WC()->countries->get_states($order->get_shipping_country());
        $sState  = !empty($sStates[$order->get_shipping_state()]) ? $sStates[$order->get_shipping_state()] : '';

        $updatedOrder = array(
            "ID" => $orderId,
            "data" =>  array(
                "billing" => array(
                    'first_name' => $order->get_billing_first_name(),
                    'last_name' => $order->get_billing_last_name(),
                    'company' => $order->get_billing_company(),
                    'email' => $order->get_billing_email(),
                    'phone' => $order->get_billing_phone(),
                    'address_1' => $order->get_billing_address_1(),
                    'address_2' => $order->get_billing_address_2(),
                    'postcode' => $order->get_billing_postcode(),
                    'city' => $order->get_billing_city(),
                    'state' => $order->get_billing_state(),
                    'state_name' => $bState,
                    'country' => $order->get_billing_country(),
                    'country_name' => $order->get_billing_country() ? WC()->countries->countries[$order->get_billing_country()] : "",
                ),
                "shipping" => array(
                    'first_name' => $order->get_shipping_first_name(),
                    'last_name' => $order->get_shipping_last_name(),
                    'company' => $order->get_shipping_company(),
                    'address_1' => $order->get_shipping_address_1(),
                    'address_2' => $order->get_shipping_address_2(),
                    'postcode' => $order->get_shipping_postcode(),
                    'city' => $order->get_shipping_city(),
                    'state' => $order->get_shipping_state(),
                    'state_name' => $sState,
                    'country' => $order->get_shipping_country(),
                    'country_name' => $order->get_shipping_country() ? WC()->countries->countries[$order->get_shipping_country()] : "",
                ),
                "customer_note" => (new Results())->getNotes($order),
                "total_tax" => $order->get_total_tax(),
                "status" => $order->get_status(),
                "customFields" => (new Results())->getOrderCustomFields($order->get_id())
            ),
        );

        return rest_ensure_response(array("updatedOrder" => $updatedOrder));
    }

    public function orderUpdateItemsMeta(WP_REST_Request $request)
    {
        $orderId = $request->get_param("orderId");
        $fields = $request->get_param("fields");
        $customFilter = $request->get_param("customFilter");

        if (!$orderId || !$fields) {
            return rest_ensure_response(array("error" => "Incorrect data."));
        }

        $order = wc_get_order($orderId);

        if (!$order) {
            return rest_ensure_response(array("error" => "Order not found."));
        }

        $items = $order->get_items();
        $isOrderFulfillmentReset = false;
        $isOrderFulfillmentObjectsReset = false;

        foreach ($items as $key => $item) {
            foreach ($fields as $field) {
                \wc_update_order_item_meta($key, $field["key"], $field["value"]);

                if ($field["key"] == "usbs_check_product") {
                    if ($field["value"] == "") {
                        if (!$isOrderFulfillmentReset) {
                            LogActions::add($orderId, LogActions::$actions["update_order_fulfillment"], "", 0, "", "order", $request);
                            $isOrderFulfillmentReset = true;
                        }
                    }

                    $isOrderFulfillmentObjectsReset = true;
                }
            }
        }

        if ($isOrderFulfillmentObjectsReset) {
            update_post_meta($orderId, "usbs_fulfillment_objects", "");
            update_post_meta($orderId, "usbs_order_fulfillment_data", "");
        }

        $updatedItems = array();

        foreach ($items as $key => $item) {
            $usbs_check_product_scanned = \wc_get_order_item_meta($itemId, 'usbs_check_product_scanned', true);
            $usbs_check_product_scanned = $usbs_check_product_scanned == "" ? 0 : $usbs_check_product_scanned;
            $qty = (float)\wc_get_order_item_meta($itemId, '_qty', true);

            $updatedItems[] = array(
                "item_id" => $key,
                "usbs_check_product" => \wc_get_order_item_meta($key, 'usbs_check_product', true),
                "usbs_check_product_scanned" => $usbs_check_product_scanned,
                "quantity" => $qty,
                "updatedAction" => "checked-" . time()
            );
        }

        $order = wc_get_order($orderId);

        $updatedOrder = array(
            "ID" => $orderId,
            "usbs_fulfillment_objects" => get_post_meta($orderId, "usbs_fulfillment_objects", true),
            "usbs_order_fulfillment_data" => get_post_meta($orderId, "usbs_order_fulfillment_data", true),
            "fulfillment_user_name" => OrdersHelper::getCustomerName($order),
            "order_status" => $order->get_status(),
        );

        $result = array("success" => true, "orders" => null, "updatedItems" => $updatedItems, "updatedOrder" => $updatedOrder);

        return rest_ensure_response($result);
    }

    public function orderUpdateItemMeta(WP_REST_Request $request, $orderId = null, $itemId = null, $fields = null, $product = null, $order = null)
    {
        Debug::addPoint(" - orderUpdateItemMeta");

        $orderId = $orderId ? $orderId : $request->get_param("orderId");
        $itemId = $itemId ? $itemId : $request->get_param("itemId");
        $fields = $fields ? $fields :  $request->get_param("fields");
        $customFilter = $request->get_param("customFilter");

        if (!$orderId || !$itemId || !$fields) {
            return array("error" => "Incorrect data.", "fulfillment" => 1);
        }

        $order = $order ? $order : wc_get_order($orderId);

        if (!$order) {
            return array("error" => "Order not found.", "fulfillment" => 1);
        }

        $settings = new Settings();
        $fulfillmentScanItemQty = $settings->getSettings("fulfillmentScanItemQty");
        $fulfillmentScanItemQty = $fulfillmentScanItemQty ? $fulfillmentScanItemQty->value == "on" : true;

        $items = $order->get_items();
        $isUpdated = false;
        $isFulfillmentChanged = false;
        $isFulfillmentAlready = false;

        $orderItemsFulfillmentSuccess = 0;
        $isOrderFulfillmentReset = false;

        $query = RequestHelper::getQuery($request, "product");

        Debug::addPoint(" - orderUpdateItemMeta get settings");

        foreach ($items as $item) {
            if ($item->get_id() == $itemId) {
                $step = 1;

                $vid = $item->get_variation_id();
                $pid = $vid ? $vid : $item->get_product_id();

                $usbs_qty_step = ($product && isset($product["number_field_step"])) ? $product["number_field_step"] : 0;

                if ($usbs_qty_step && is_numeric($usbs_qty_step)) {
                    $step = (float)$usbs_qty_step;
                }

                $step = apply_filters($this->filter_fulfillment_step, $step, $orderId, $pid, $itemId, $query);

                foreach ($fields as $key => $field) {
                    if ($field["key"] == "usbs_check_product") {
                        if ($field["value"] == "") {
                            if (!$isOrderFulfillmentReset) {
                                \wc_update_order_item_meta($itemId, "usbs_check_product_scanned", "");
                                \wc_update_order_item_meta($itemId, "usbs_check_product", "");
                                $isFulfillmentChanged = true;
                                LogActions::add($orderId, LogActions::$actions["update_order_fulfillment"], "", 0, "", "order", $request);
                                $isOrderFulfillmentReset = true;
                            }
                        }
                        else if ($field["value"] == "max") {
                            $_qty = \wc_get_order_item_meta($itemId, '_qty', true);

                            if (is_numeric($_qty)) {
                                $logId = LogActions::add($pid, LogActions::$actions["update_order_item_meta"], $field["key"], $_qty, "", "order_item", $request, "", $orderId);
                                \wc_update_order_item_meta($itemId, "usbs_check_product_scanned", $_qty);
                                \wc_update_order_item_meta($itemId, "usbs_check_product", $logId);
                                $isFulfillmentChanged = true;
                            }
                        }
                        else if ($fulfillmentScanItemQty) {
                            $qty = (float)\wc_get_order_item_meta($itemId, '_qty', true);
                            $scanned = (float)\wc_get_order_item_meta($itemId, 'usbs_check_product_scanned', true);

                            if ($qty && $scanned + $step < $qty) {
                                \wc_update_order_item_meta($itemId, "usbs_check_product_scanned", $scanned + $step);
                                $isFulfillmentChanged = true;
                            }
                            else if ($qty == $scanned) {
                                $scanned = \wc_get_order_item_meta($itemId, 'usbs_check_product', true);
                                if (!$scanned) {
                                    \wc_update_order_item_meta($itemId, $field["key"], time());
                                }
                                $isFulfillmentAlready = true;
                            }
                            else if ($qty && $scanned + $step > $qty) {
                                return array(
                                    "error" => __("You cannot add more quantity than in the order", "us-barcode-scanner"),
                                    "htmlMessageClass" => "ff_cannot_add_more_quantity",
                                    "fulfillment" => 1
                                );
                            }
                            else if ($qty && $scanned < $qty) {
                                \wc_update_order_item_meta($itemId, "usbs_check_product_scanned", $scanned + $step);
                                $logId = LogActions::add($pid, LogActions::$actions["update_order_item_meta"], $field["key"], $field["value"] ? $step : 0, "", "order_item", $request, "", $orderId);
                                \wc_update_order_item_meta($itemId, $field["key"], $field["value"] ? $logId : "");
                                $isFulfillmentChanged = true;
                            }
                            else {
                                $scanned = \wc_get_order_item_meta($itemId, 'usbs_check_product', true);
                                if (!$scanned) {
                                    \wc_update_order_item_meta($itemId, $field["key"], time());
                                }
                                $isFulfillmentAlready = true;
                            }
                        }
                        else {
                            $scanned = \wc_get_order_item_meta($itemId, 'usbs_check_product', true);

                            if (!$scanned) {
                                $logId = LogActions::add($pid, LogActions::$actions["update_order_item_meta"], $field["key"], $field["value"] ? 1 : 0, "", "order_item", $request, "", $orderId);
                                \wc_update_order_item_meta($itemId, $field["key"], $field["value"] ? $logId : "");
                                $isFulfillmentChanged = true;
                            }
                        }
                    } else {
                        \wc_update_order_item_meta($itemId, $field["key"], $field["value"]);
                    }
                }

                $isUpdated = true;
            }

            $usbs_check_product = \wc_get_order_item_meta($item->get_id(), 'usbs_check_product', true);

            if ($usbs_check_product && $usbs_check_product != "") {
                $orderItemsFulfillmentSuccess += 1;
            }
        }
        Debug::addPoint(" - orderUpdateItemMeta items");

        $checker = $this->getFulfillmentOrderData($orderId);
        Debug::addPoint(" - orderUpdateItemMeta checker");

        if ($checker && $checker["totalQty"] == $checker["totalScanned"]) {
            $logId = LogActions::add($orderId, LogActions::$actions["update_order_fulfillment"], "", 1, "", "order", $request);
        }



        $updatedItems = array();
        $updatedOrder = array();
        $fulfillmentResult = false;
        $error = "";
        $htmlMessageClass = "";

        if ($isFulfillmentChanged || $isFulfillmentAlready) {
            $usbs_check_product_scanned = \wc_get_order_item_meta($itemId, 'usbs_check_product_scanned', true);
            $usbs_check_product_scanned = $usbs_check_product_scanned == "" ? 0 : $usbs_check_product_scanned;
            $qty = (float)\wc_get_order_item_meta($itemId, '_qty', true);

            if ($isFulfillmentChanged) {
                $updatedItems[] = array(
                    "item_id" => $itemId,
                    "usbs_check_product" => \wc_get_order_item_meta($itemId, 'usbs_check_product', true),
                    "usbs_check_product_scanned" => $usbs_check_product_scanned,
                    "quantity" => $qty,
                    "updatedAction" => "checked-" . time()
                );
            } else if ($isFulfillmentAlready) {
                $updatedItems[] = array(
                    "item_id" => $itemId,
                    "usbs_check_product" => \wc_get_order_item_meta($itemId, 'usbs_check_product', true),
                    "usbs_check_product_scanned" => $usbs_check_product_scanned,
                    "quantity" => $qty,
                    "updatedAction" => "already-" . time()
                );
                $error = __("This product is already scanned in a needed quantity!", "us-barcode-scanner");
                $htmlMessageClass = "ff_product_is_already_scanned";
            }

            $order = wc_get_order($orderId);

            $updatedOrder = array(
                "ID" => $orderId,
                "usbs_fulfillment_objects" => get_post_meta($orderId, "usbs_fulfillment_objects", true),
                "usbs_order_fulfillment_data" => get_post_meta($orderId, "usbs_order_fulfillment_data", true),
                "fulfillment_user_name" => OrdersHelper::getCustomerName($order),
                "order_status" => $order->get_status(),
            );

            $fulfillmentResult = true;
        }

        Debug::addPoint(" - orderUpdateItemMeta product checked");

        $result = array(
            "success" => true,
            "isUpdated" => $isUpdated,
            "orders" => null,
            "updatedItems" => $updatedItems,
            "updatedOrder" => $updatedOrder,
            "fulfillmentResult" => $fulfillmentResult,
            "fulfillment" => 1,
            "error" => $error,
            "htmlMessageClass" => $htmlMessageClass,
            "debug" => Debug::getResult(true)
        );

        return $result;
    }

    public function productUpdateTitle(WP_REST_Request $request)
    {
        $query = RequestHelper::getQuery($request, "product");
        $title = $request->get_param("title");
        $customFilter = $request->get_param("customFilter");
        $filterExcludes = $request->get_param("filterExcludes");
        $withoutStatuses = $request->get_param("withoutStatuses");

        $productId = $query;
        $title = trim($title);

        if ($productId && $title) {
            $oldValue = get_the_title($productId);
            $my_post = array(
                'ID' => $productId,
                'post_title' => $title,
            );

            wp_update_post($my_post);

            LogActions::add($productId, LogActions::$actions["update_title"], "post_title", $title, $oldValue, "product", $request);
        }

        $result = array(
            "products" => null,
            "findByTitle" => null,
        );

        $data = (new Post())->find($query, array("products" => array("ID" => true)), true, false, null, "product", $filterExcludes, $withoutStatuses == "1");
        $products = (new Results())->productsPrepare($data["posts"], array("useAction" => false));
        WPML::addTranslations($products);
        $products = apply_filters($this->filter_search_result, $products, $customFilter);
        $result["products"] = $products;

        if (isset($data["query"])) {
            $result["foundBy"] = $data["query"];
        }

        return rest_ensure_response($result);
    }

    public function productSetImage(WP_REST_Request $request)
    {
        $postId = $request->get_param("postId");
        $attachmentId = $request->get_param("attachmentId");
        $customFilter = $request->get_param("customFilter");
        $filterExcludes = $request->get_param("filterExcludes");
        $withoutStatuses = $request->get_param("withoutStatuses");

        $oldValue = "";

        set_post_thumbnail($postId, $attachmentId);

        LogActions::add($postId, LogActions::$actions["set_product_image"], "", $attachmentId, $oldValue, "product", $request);

        $result = array(
            "products" => null,
            "findByTitle" => null,
        );

        $data = (new Post())->find($postId, array("products" => array("ID" => true)), true, false, null, "product", $filterExcludes, $withoutStatuses == "1");
        $products = (new Results())->productsPrepare($data["posts"], array("useAction" => false));
        WPML::addTranslations($products);
        $products = apply_filters($this->filter_search_result, $products, $customFilter);
        $result["products"] = $products;

        if (isset($data["query"])) {
            $result["foundBy"] = $data["query"];
        }

        return rest_ensure_response($result);
    }

    public function productCreateNew(WP_REST_Request $request, $query = "", $meta = array())
    {
        $query = $query ? $query : RequestHelper::getQuery($request, "product");
        $filterExcludes = $request->get_param("filterExcludes");
        $status = trim($request->get_param("status"));
        $parentId = trim($request->get_param("parentId"));
        $query = trim($query);
        $productId = null;

        $settings = new Settings();

        if (!$status) {
            $field = $settings->getSettings("newProductStatus");
            $status = $field ? $field->value : "";
        }

        $field = $settings->getSettings("fieldForNewProduct");
        $fieldNameValue = $field === null ? $settings->getField("general", "fieldForNewProduct", "_sku") : $field->value;

        if ($fieldNameValue == "custom_field") {
            $field = $settings->getSettings("cfForNewProduct");
            $fieldNameValue = $field === null ? "_sku" : $field->value;
        }

        $field = $settings->getSettings("newProductQty");
        $qty = $field === null ? $settings->getField("general", "newProductQty", "") : $field->value;

        if ($parentId) {
            $product = \wc_get_product($parentId);

            $variation_post = array(
                'post_title' => $product->get_name(),
                'post_name' => 'product-' . $parentId . '-variation',
                'post_status' => 'publish',
                'post_parent' => $parentId,
                'post_type' => 'product_variation',
                'guid' => $product->get_permalink()
            );

            $variation_id = wp_insert_post($variation_post);

            $variation = new \WC_Product_Variation($variation_id);
            $variation->save();

            $productId = $variation_id;

            if ($query) {
                $this->productUpdateMeta($request, $variation_id, $fieldNameValue, $query);
            }

            $this->setManageStock($variation_id);

            if ($qty !== "" && (int)$qty) {
                $this->setQuantity($variation_id, $qty, null, true);
            } else {
                $this->setQuantity($variation_id, 0, null, true);
            }

            if ($meta && isset($meta["_manage_stock"])) {
                $this->setManageStock($product->id);
            }

            apply_filters($this->scanner_created_product, $variation, $product);

            $this->productIndexation($variation_id, "productCreateNew");

            LogActions::add($variation_id, LogActions::$actions["create_product"], "", "", "", "product", $request);
        }

        else {
            $post_id = wp_insert_post(array(
                'post_title' => 'Product name',
                'post_type' => 'product',
                'post_status' => $status ? $status : 'draft',
                'post_content' => '',
            ));
            $product = \wc_get_product($post_id);
            $product->save();

            if ($product->id) {
                $productId = $product->id;

                if ($query) {
                    $this->productUpdateMeta($request, $productId, $fieldNameValue, $query);
                }

                if ($qty !== "" && (int)$qty) {
                    $this->setQuantity($product->id, $qty, null, true);
                } else {
                    $this->setQuantity($product->id, 0, null, true);
                }

                if ($meta && isset($meta["_manage_stock"])) {
                    $this->setManageStock($product->id);
                }

                apply_filters($this->scanner_created_product, $product, null);

                $this->productIndexation($product->id, "productCreateNew");

                LogActions::add($product->id, LogActions::$actions["create_product"], "", "", "", "product", $request);
            }
        }

        $result = array(
            "products" => null,
            "findByTitle" => null,
        );

        if ($productId) {
            $data = (new Post())->find($productId, array("products" => array("ID" => true)), true, false, null, "product", $filterExcludes, true);
            $products = (new Results())->productsPrepare($data["posts"], array("useAction" => false));

            if (count($products)) {
                $products[0]["newProduct"] = true;
            }

            WPML::addTranslations($products);
            $result["products"] = $products;

            if (isset($data["query"])) {
                $result["foundBy"] = $data["query"];
            }
        }

        return rest_ensure_response($result);
    }


    public function reloadNewProduct(WP_REST_Request $request)
    {
        $postId = $request->get_param("postId");
        $filterExcludes = $request->get_param("filterExcludes");

        $result = array(
            "products" => null,
            "findByTitle" => null,
        );

        if ($postId) {
            $data = (new Post())->find($postId, array("products" => array("ID" => true)), true, false, null, "product", $filterExcludes);
            $products = (new Results())->productsPrepare($data["posts"], array("useAction" => false));

            WPML::addTranslations($products);

            $result["products"] = $products;

            if (isset($data["query"])) {
                $result["foundBy"] = $data["query"];
            }
        }

        return rest_ensure_response($result);
    }

    public function productUpdateFields(WP_REST_Request $request)
    {
        $customFilter = $request->get_param("customFilter");
        $filterExcludes = $request->get_param("filterExcludes");
        $fields = $request->get_param("fields");

        if ($fields) {
            $fields = json_decode(stripslashes($fields), true);
        }

        if (!isset($fields["postId"]) || !$fields["postId"]) {
            return rest_ensure_response(array(
                "errors" => array("Product ID is required.")
            ));
        }

        $postId = $fields["postId"];
        $errors = array();

        $batches = $request->get_param("batches");
        if ($batches) {
            $batches = json_decode(stripslashes($batches), true);
            BatchNumbers::updateBatches($batches);
        }

        $batches = $request->get_param("batchesWebis");
        if ($batches) {
            $batches = json_decode(stripslashes($batches), true);
            BatchNumbersWebis::updateBatches($batches);
        }

        $this->uploadPick($request, $postId);

        if ($fields && is_array($fields)) {
            foreach ($fields as $key => $value) {
                switch ($key) {
                    case 'postId':
                        break;
                    case '_regular_price':
                    case 'regularPrice':
                        $this->productUpdateRegularPrice($request, $postId, $value);
                        break;
                    case '_sale_price':
                    case 'salePrice':
                        $this->productUpdateSalePrice($request, $postId, $value);
                        break;
                    case '_stock':
                    case 'quantity':
                        $filterName = str_replace("%field", $key, $this->filter_set_after);
                        $filteredValue = apply_filters($filterName, $value, $key, $postId);

                        if (!$filteredValue) {
                            $this->productUpdateMeta($request, $postId, "_manage_stock", "on");
                        }
                        $this->productUpdateQuantity($request, $postId, $value);
                        break;
                    case 'post_title':
                    case 'postTitle':
                        $post = array('ID' => $postId, 'post_title' => $value,);
                        wp_update_post($post);
                        break;
                    default:
                        $response = $this->productUpdateMeta($request, $postId, $key, $value);

                        if ($response && isset($response->data) && isset($response->data["errors"]) && $response->data["errors"]) {
                            $errors = $response->data["errors"];
                        }
                        break;
                }
            }
        }

        $result = array(
            "products" => null,
            "findByTitle" => null,
            "errors" => $errors,
        );

        $data = (new Post())->find($fields["postId"], array("products" => array("ID" => true)), true, false, null, "product", $filterExcludes);
        $products = (new Results())->productsPrepare($data["posts"], array("useAction" => false));
        WPML::addTranslations($products);
        $products = apply_filters($this->filter_search_result, $products, $customFilter);
        $result["products"] = $products;

        $this->productIndexation($fields["postId"], "productUpdateFields");

        if (isset($data["query"])) {
            $result["foundBy"] = $data["query"];
        }

        $result["productsList"] = PostsList::getList(Users::getUserId($request));

        return rest_ensure_response($result);
    }

    public function uploadPostImage(WP_REST_Request $request)
    {
        $postId = $request->get_param("postId");
        $parentId = (int)$request->get_param("parentId");
        $str = $request->get_param("str");
        $isMainImage = $request->get_param("isMainImage");
        $attachmentId = $request->get_param("id");

        if (!$postId) {
            return rest_ensure_response(array(
                "errors" => array("Product ID is required.")
            ));
        }

        $currentAttachmentId = null;

        if ($isMainImage && $attachmentId) {
            $galleryPostId = $parentId ? $parentId : $postId;

            $currentAttachmentId = get_post_thumbnail_id($galleryPostId);

            set_post_thumbnail($galleryPostId, $attachmentId);

            if ($currentAttachmentId) {
                $this->addAttachmentToGallery($galleryPostId, $currentAttachmentId);
            }
        }
        else {
            $galleryPostId = $parentId ? $parentId : $postId;
            $newAttachmentId = $this->uploadImageFile($galleryPostId);

            if (is_wp_error($newAttachmentId)) {
            }
            else if ($isMainImage && $newAttachmentId) {
                $currentAttachmentId = get_post_thumbnail_id($galleryPostId);

                set_post_thumbnail($galleryPostId, $newAttachmentId);

                if ($currentAttachmentId) {
                    $this->addAttachmentToGallery($galleryPostId, $currentAttachmentId);
                }
            }
            else {
                $this->addAttachmentToGallery($galleryPostId, $newAttachmentId);
            }
        }

        $result = array(
            "products" => null,
            "findByTitle" => null,
        );

        $data = (new Post())->find($postId, array("products" => array("ID" => true)), true, false, null, "product", array());
        $products = (new Results())->productsPrepare($data["posts"], array("useAction" => false));
        WPML::addTranslations($products);
        $products = apply_filters($this->filter_search_result, $products, array());
        $result["products"] = $products;

        return rest_ensure_response($result);
    }

    public function addAttachmentToGallery($galleryPostId, $newAttachmentId)
    {
        $product = \wc_get_product($galleryPostId);

        if ($product->get_parent_id()) {
            $galleryPostId = $product->get_parent_id();
            $product = \wc_get_product($galleryPostId);
        }

        if ($product) {
            $gallery_image_ids = $product->get_gallery_image_ids();
            $gallery_image_ids[] = $newAttachmentId;

            update_post_meta($galleryPostId, '_product_image_gallery', implode(',', $gallery_image_ids));
        }
    }

    public function removePostImages(WP_REST_Request $request)
    {
        $currentId = $request->get_param("postId");
        $postId = $currentId;
        $ids = $request->get_param("data");

        if (!$postId) {
            return rest_ensure_response(array(
                "errors" => array("Product ID is required.")
            ));
        }

        $product = \wc_get_product($postId);

        if ($product->get_parent_id()) {
            $postId = $product->get_parent_id();
            $product = \wc_get_product($postId);
        }

        if ($product && $ids) {
            $gallery_image_ids = $product->get_gallery_image_ids();

            if ($gallery_image_ids) {
                $gallery_image_ids = array_filter($gallery_image_ids, function ($id) use ($ids) {
                    return in_array($id, $ids) == false;
                });

                update_post_meta($postId, '_product_image_gallery', implode(',', $gallery_image_ids));
            }

        }

        $result = array(
            "products" => null,
            "findByTitle" => null,
        );

        $data = (new Post())->find($currentId, array("products" => array("ID" => true)), true, false, null, "product", array());
        $products = (new Results())->productsPrepare($data["posts"], array("useAction" => false));
        WPML::addTranslations($products);
        $products = apply_filters($this->filter_search_result, $products, array());
        $result["products"] = $products;

        return rest_ensure_response($result);
    }

    public function removePostMainImage(WP_REST_Request $request)
    {
        ini_set('max_execution_time', 300);

        $postId = $request->get_param("postId");
        $parentId = $request->get_param("parentId");
        $isVariation = $request->get_param("isVariation");
        $ids = $request->get_param("data");

        if (!$postId) {
            return rest_ensure_response(array(
                "errors" => array("Product ID is required.")
            ));
        }

        if ($parentId) {
            \delete_post_thumbnail($parentId);
        } else {
            \delete_post_thumbnail($postId);
        }

        $result = array(
            "products" => null,
            "findByTitle" => null,
        );

        $data = (new Post())->find($postId, array("products" => array("ID" => true)), true, false, null, "product", array());
        $products = (new Results())->productsPrepare($data["posts"], array("useAction" => false));
        WPML::addTranslations($products);
        $products = apply_filters($this->filter_search_result, $products, array());
        $result["products"] = $products;

        return rest_ensure_response($result);
    }

    private function uploadImageFile($postId = null)
    {
        ini_set('max_execution_time', 300);
        ini_set('upload_max_filesize', '10M');

        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
        }

        if (!$postId) return;
        if (!isset($_FILES["image"])) return;

        return \media_handle_upload('image', $postId);
    }

    private function getPriceFieldLabel($field)
    {
        $settings = new Settings();

        if ($settings->getField("prices", "price_1_field", "_regular_price") === $field) {
            return $settings->getField("prices", "price_1_label", "Regular price");
        } else if ($settings->getField("prices", "price_2_field", "_sale_price") === $field) {
            return $settings->getField("prices", "price_2_label", "Sale price");
        } else if ($settings->getField("prices", "price_3_field", "_purchase_price") === $field) {
            return $settings->getField("prices", "price_3_label", "Purchase price");
        }
    }

    public function updateFoundCounter(WP_REST_Request $request)
    {
        try {
            $postId = $request->get_param("postId");
            $result = array();

            if ($postId) {
                $newCount = 1;
                $isOrder = false;

                if (HPOS::getStatus()) {
                    $order = null;

                    try {
                        $order = new \WC_Order($postId);
                    } catch (\Throwable $th) {
                    }

                    if ($order && $order->get_id()) {
                        $count = $order->get_meta("usbs_found_counter", true);
                        $newCount = $count ? (int)$count + 1 : 1;


                        \update_post_meta($order->get_id(), "usbs_found_counter", $newCount);

                        $isOrder = true;
                    }
                }

                if (!$isOrder) {
                    $count = \get_post_meta($postId, "usbs_found_counter", true);
                    $newCount = $count ? (int)$count + 1 : 1;
                    \update_post_meta($postId, "usbs_found_counter", $newCount);
                }

                $result["success"] = 1;
                $result["newCount"] = $newCount;

                $userId = Users::getUserId($request);

                History::add($postId, $userId);

                $result["settings"] = array("searchHistory" => History::getByUser($userId));
                $result["debug"] = Debug::getResult(true);
            }

            return rest_ensure_response($result);
        } catch (\Throwable $th) {
            return rest_ensure_response(array("error" => $th->getMessage()));
        }
    }

    public function saveLog(WP_REST_Request $request)
    {
        try {
            $postId = $request->get_param("postId");
            $slug = $request->get_param("slug");
            $result = array();

            if ($postId && $slug && isset(LogActions::$actions[$slug])) {
                LogActions::add($postId, LogActions::$actions[$slug], "", "", "", "url", $request);
                $result["success"] = 1;
            }

            return rest_ensure_response($result);
        } catch (\Throwable $th) {
            return rest_ensure_response(array("error" => $th->getMessage()));
        }
    }

    public function uploadPick(WP_REST_Request $request, $postId = null)
    {
        $filterExcludes = $request->get_param("filterExcludes");

        if ($postId) {
            $id = $postId;
        } else {
            $id = $request->get_param("postId");
        }

        if (!$id) return;
        if (!isset($_FILES["image"])) return;

        $attachmentId = $this->uploadImageFile($id);

        if (is_wp_error($attachmentId)) {
        } else {
            set_post_thumbnail($id, $attachmentId);
        }

        if ($postId) {
            $result = array(
                "products" => null,
                "findByTitle" => null,
            );

            $data = (new Post())->find($id, array("products" => array("ID" => true)), true, false, null, "product", $filterExcludes);
            $products = (new Results())->productsPrepare($data["posts"], array("useAction" => false));
            $result["products"] = $products;

            if (isset($data["query"])) {
                $result["foundBy"] = $data["query"];
            }

            return rest_ensure_response($result);
        } else {
            return true;
        }
    }

    public function getProductCategories(WP_REST_Request $request)
    {
        $categories = get_terms('product_cat', array('orderby' => "name", 'order' => "ASC", 'hide_empty' => false));

        foreach ($categories as $value) {
            unset($value->slug);
            unset($value->term_group);
            unset($value->term_taxonomy_id);
            unset($value->taxonomy);
            unset($value->description);
            unset($value->count);
            unset($value->filter);
        }

        return rest_ensure_response(array("categories" => $categories));
    }

    public function updateCategories(WP_REST_Request $request, $postId = null)
    {
        $postId = $request->get_param("postId");
        $categories = $request->get_param("categories");


        $settings = new Settings();
        $wpmlRow = $settings->getSettings("wpmlUpdateProductsTree");
        $isUpdateAllProds = $wpmlRow === null ? $settings->getField("general", "wpmlUpdateProductsTree", "") : $wpmlRow->value;

        $result = true;

        if ($isUpdateAllProds === "on" || true) {
            try {
                $productsIds = (array)$request->get_param("products");

                if (count($productsIds) === 0) {
                    $productsIds = array($postId);
                }

                if (count($productsIds) > 0) {
                    foreach ($productsIds as $id) {
                        $product = \wc_get_product($id);

                        if (!$product) {
                            return rest_ensure_response(array("error" => "Product not found"));
                        }

                        $product->set_category_ids($categories);
                        $product->save();
                    }
                }
            } catch (\Throwable $th) {
            }
        }
        else {
            $product = \wc_get_product($postId);

            if (!$product) {
                return rest_ensure_response(array("error" => "Product not found"));
            }

            $product->set_category_ids($categories);
            $product->save();
        }

        $result = array(
            "products" => null,
            "findByTitle" => null,
        );

        $data = (new Post())->find($postId, array("products" => array("ID" => true)), true, false, null, "product", array());
        $products = (new Results())->productsPrepare($data["posts"], array("useAction" => false));
        $result["products"] = $products;

        if (isset($data["query"])) {
            $result["foundBy"] = $data["query"];
        }

        return rest_ensure_response($result);
    }

    public function updateAttributes(WP_REST_Request $request, $postId = null)
    {
        $postId = $request->get_param("postId");
        $globalAttributes = $request->get_param("globalAttributes");
        $customAttributes = $request->get_param("customAttributes");
        $globalOptions = $request->get_param("globalOptions");
        $customOptions = $request->get_param("customOptions");

        try {
            $productsIds = (array)$request->get_param("products");

            if (count($productsIds) === 0) {
                $productsIds = array($postId);
            }

            if (count($productsIds) > 0) {
                foreach ($productsIds as $id) {
                    $product = \wc_get_product($id);

                    if (!$product) {
                        return rest_ensure_response(array("cartErrors" => "Product not found"));
                    }

                    $attributes = [];

                    foreach ($globalAttributes as $attribute_name => $options) {
                        $attribute_id = wc_attribute_taxonomy_id_by_name($attribute_name);

                        if ($options) {
                            foreach ($options as &$value) {
                                $term = get_term_by('slug', $value, $attribute_name);

                                if ($term) {
                                    $value = $term->name;
                                }
                            }
                        }

                        if ($attribute_id) {
                            $visible = isset($globalOptions[$attribute_name]) && isset($globalOptions[$attribute_name]['visible']) ? $globalOptions[$attribute_name]['visible'] == 1 : false;
                            $variations = isset($globalOptions[$attribute_name]) && isset($globalOptions[$attribute_name]['variations']) ? $globalOptions[$attribute_name]['variations'] == 1 : false;

                            $attribute = new \WC_Product_Attribute();
                            $attribute->set_id($attribute_id);
                            $attribute->set_name($attribute_name);
                            $attribute->set_options($options);
                            $attribute->set_position(0);
                            $attribute->set_visible($visible);
                            $attribute->set_variation($variations);

                            $attributes[] = $attribute;
                        }
                    }

                    foreach ($customAttributes as $attribute_name => $options) {
                        $visible = isset($customOptions[$attribute_name]) && isset($customOptions[$attribute_name]['visible']) ? $customOptions[$attribute_name]['visible'] == 1 : false;
                        $variations = isset($customOptions[$attribute_name]) && isset($customOptions[$attribute_name]['variations']) ? $customOptions[$attribute_name]['variations'] == 1 : false;

                        $attribute = new \WC_Product_Attribute();
                        $attribute->set_name($attribute_name);
                        $attribute->set_options($options);
                        $attribute->set_position(0);
                        $attribute->set_visible($visible);
                        $attribute->set_variation($variations);

                        $attributes[] = $attribute;
                    }

                    $product->set_attributes($attributes);
                    $product->save();
                }
            }
        } catch (\Throwable $th) {
        }

        $result = array(
            "products" => null,
            "findByTitle" => null,
        );

        $data = (new Post())->find($postId, array("products" => array("ID" => true)), true, false, null, "product", array());
        $products = (new Results())->productsPrepare($data["posts"], array("useAction" => false));
        $result["products"] = $products;

        if (isset($data["query"])) {
            $result["foundBy"] = $data["query"];
        }

        return rest_ensure_response($result);
    }

    public function importCodes(WP_REST_Request $request, $postId = null)
    {
        $isCheck = $request->get_param("isCheck");
        $codes = $request->get_param("codes");
        $importData = $request->get_param("lines");
        $autoActionData = $request->get_param("autoAction");

        if (!$codes || !is_array($codes)) {
            return rest_ensure_response(array("error" => "Empty list"));
        }

        $autoAction = isset($autoActionData["action"]) ? trim($autoActionData["action"]) : "";
        $autoActionField = isset($autoActionData["field"]) ? trim($autoActionData["field"]) : "";
        $filter = SearchFilter::get();
        $lines = array();

        if ($isCheck) {
            foreach ($codes as $key => $value) {
                $query = trim($value);

                if ($query) {
                    $data = (new Post())->find($query, $filter, false, true, 2, "product", array());
                    $products = (new Results())->productsPrepare($data["posts"], array("useAction" => false));

                    if ($products && count($products) == 1) {
                        $product = $products[0];
                        $pid = $product["ID"];

                        $ms = get_post_meta($pid, "_manage_stock", true);

                        if ($ms == "yes" || $autoActionField != "_stock") {
                            $lines[$key] = array("code" => $query, "id" => $pid, "name" => $data["posts"][0]->post_title, "success" => 1, "isImport" => 1);
                        } else {
                            $lines[$key] = array("code" => $query, "id" => $pid, "error" => __('"Manage stock" option is disabled for this product. Enable checkbox to enable stock and increase ' . $autoActionField, "us-barcode-scanner"));
                        }
                    }
                    else if ($data && $data["posts"] && count($data["posts"]) > 1) {
                        $ids = array_column($data["posts"], 'ID');
                        $lines[$key] = array("code" => $query, "id" => $ids, "error" => __("Found more than 1 product", "us-barcode-scanner"));
                    }
                    else {
                        $lines[$key] = array("code" => $query, "error" => __("Product not found, enable checkbox to create such product with this code.", "us-barcode-scanner"));
                    }
                }
                else {
                    $lines[$key] = array("code" => $query, "error" => __("Empty line", "us-barcode-scanner"));
                }
            }
        }
        else if (!$importData || !is_array($importData)) {
            return rest_ensure_response(array("error" => "Wrong import data"));
        }
        else {
            if (!$autoAction || !$autoActionField) {
                return rest_ensure_response(array("error" => "Wrong auto action data"));
            }


            $updatedProducts = 0;
            $createdProducts = 0;
            $createdCodes = array();

            foreach ($importData as $key => $value) {
                if (!isset($value["isImport"]) || $value["isImport"] != 1) continue;

                $id = isset($value["id"]) ? $value["id"] : null;
                $code = isset($value["code"]) ? $value["code"] : null;

                if (is_array($id)) {
                    foreach ($id as $pid) {
                        $this->importCodesAction($request, $pid, $autoAction, $autoActionField);
                        $updatedProducts++;
                    }
                }
                else if ($id) {
                    $this->importCodesAction($request, $id, $autoAction, $autoActionField);
                    $updatedProducts++;
                }
                else if ($code) {
                    $meta = $autoActionField == "_stock" ? array("_manage_stock" => "on") : array();

                    if (isset($createdCodes[$code])) {
                        $pid = $createdCodes[$code];
                        $updatedProducts++;
                    }
                    else {
                        $create = $this->productCreateNew($request, $code, $meta);
                        $pid = $create->data["products"][0]["ID"];
                        $createdCodes[$code] = $pid;
                        $createdProducts++;
                    }

                    if ($create && $create->data && isset($create->data["products"])) {
                        @$this->importCodesAction($request, $pid, $autoAction, $autoActionField);
                    }
                }
                else {
                    continue;
                }
            }

            return rest_ensure_response(array(
                "success" => 1,
                "lines" => $lines,
                "info" => array("updatedProducts" => $updatedProducts, "createdProducts" => $createdProducts)
            ));
        }

        return rest_ensure_response(array("lines" => $lines));
    }

    public function updateItemsFromList(WP_REST_Request $request)
    {
        $items = $request->get_param("items");

        if (!$items || !is_array($items)) {
            return rest_ensure_response(array("error" => "Incorrect data"));
        }

        $cartDecimalQuantity = false;

        try {
            $settings = new Settings();
            $field = $settings->getSettings("cartDecimalQuantity");
            $value = $field === null ? "off" : $field->value;
            $cartDecimalQuantity = $value === "on";
        } catch (\Throwable $th) {
        }

        foreach ($items as $value) {
            if ($value["post_id"]) {
                if ($cartDecimalQuantity) {
                    $quantity = (float)$value["quantity"];
                    $increase = isset($value["_stock"]) ? (float)$value["_stock"] : 0;
                }
                else {
                    $quantity = (int)$value["quantity"];
                    $increase = isset($value["_stock"]) ? (int)$value["_stock"] : 0;
                }

                $this->setManageStock($value["post_id"]);

                if ($cartDecimalQuantity) {
                    $currentQty = (float)get_post_meta($value["post_id"], '_stock', true);
                }
                else {
                    $currentQty = (int)get_post_meta($value["post_id"], '_stock', true);
                }
                $qty = $currentQty;

                if (is_numeric($quantity) && $qty != $quantity) {
                    $qty = $quantity;
                }
                else if ($qty != $quantity) {
                    $qty = 0;
                }

                if ($increase != 0) {
                    $this->productUpdateQuantity($request, $value["post_id"], $qty + $increase);
                }
                else if ($qty != $currentQty) {
                    $this->productUpdateQuantity($request, $value["post_id"], $qty);
                }
            }
        }

        $userId = Users::getUserId($request);

        PostsList::resetCounter($userId);

        return rest_ensure_response(array("success" => 1, "productsList" => PostsList::getList($userId), "uid" => $userId));
    }

    public function removeItemsListRecord(WP_REST_Request $request)
    {
        $recordId = (int)$request->get_param("recordId");

        if (!$recordId) {
            return rest_ensure_response(array("error" => "Incorrect data"));
        }

        $userId = Users::getUserId($request);

        PostsList::removeRecord($recordId);

        return rest_ensure_response(array("success" => 1, "productsList" => PostsList::getList($userId), "uid" => $userId));
    }

    public function clearItemsList(WP_REST_Request $request)
    {
        $userId = Users::getUserId($request);

        if ($userId) {
            PostsList::clear($userId);
        }

        return rest_ensure_response(array("success" => 1, "productsList" => PostsList::getList($userId), "uid" => $userId));
    }

    public function getOrdersList(WP_REST_Request $request)
    {
        global $wpdb;

        $settings = new Settings();

        $userId = Users::getUserId($request);

        $filter = $request->get_param("filter");

        $type = isset($filter["type"]) ? $filter["type"] : "";
        $status = isset($filter["status"]) ? $filter["status"] : "";
        $statuses = isset($filter["statuses"]) ? $filter["statuses"] : array();
        $from = isset($filter["from"]) ? $filter["from"] : "";
        $to = isset($filter["to"]) ? $filter["to"] : "";
        $page = isset($filter["page"]) ? (int)$filter["page"] : "";
        $perPage = isset($filter["perPage"]) ? (int)$filter["perPage"] : 10;
        $customerId = isset($filter["customerId"]) ? (int)$filter["customerId"] : "";

        $excludeOrderStatuses = $settings->getSettings("orderStatuses");
        $excludeOrderStatuses = $excludeOrderStatuses === null ? "wc-checkout-draft,trash" : $excludeOrderStatuses->value;
        $excludeOrderStatuses = $excludeOrderStatuses ? explode(",", $excludeOrderStatuses) : array();
        $excludeOrderStatuses = array_merge($excludeOrderStatuses, SettingsHelper::$excludeOrderStatuses);

        if ($statuses) $statuses = array_filter($statuses);

        if ($userId) {
            $permissions = $settings->getUserRolePermissions($userId);

            if ($permissions && isset($permissions['onlymy']) && $permissions['onlymy'] == 1) {
                $type = "my";
            }
        }

        $orders = array();

        $hposOrdersTable = "{$wpdb->prefix}wc_orders";

        $paged = $page ? (int)$page : 1;
        $offset = ($paged - 1) * $perPage;
        $fromTables = '';
        $where = '';
        $limit = " LIMIT {$offset}, {$perPage} ";

        if ($type == "my" && $userId) {
            $where .= " AND (P.post_author = {$userId} OR P.postmeta__customer_user = {$userId}) ";
        }

        if ($status) {
            if (HPOS::getStatus()) {
                $where .= " AND P.post_status = '{$status}' ";
                $where .= " AND (SELECT _O.id FROM {$hposOrdersTable} AS _O WHERE _O.id = P.post_id AND _O.status = '{$status}') IS NOT NULL ";
            } else {
                $where .= " AND P.post_status = '{$status}' ";
            }
        }

        if ($statuses && count($statuses)) {
            $statusesList = implode("','", $statuses);

            if (HPOS::getStatus()) {
                $where .= " AND (SELECT _O.id FROM {$hposOrdersTable} AS _O WHERE _O.id = P.post_id AND _O.status IN ('{$statusesList}')) IS NOT NULL ";
            } else {
                $where .= " AND P.post_status IN ('{$statusesList}') ";
            }
        }
        else if ($excludeOrderStatuses && count($excludeOrderStatuses)) {
            $statusesList = implode("','", $excludeOrderStatuses);

            if (HPOS::getStatus()) {
                $where .= " AND (SELECT _O.id FROM {$hposOrdersTable} AS _O WHERE _O.id = P.post_id AND _O.status NOT IN ('{$statusesList}')) IS NOT NULL ";
            } else {
                $where .= " AND P.post_status NOT IN ('{$statusesList}') ";
            }
        }

        if ($from) {
            $where .= " AND P.post_date >= '{$from} 00:00:00' ";

            if ($to) {
                $where .= " AND P.post_date <= '{$to} 23:59:59' ";
            }
        } else if ($to) {
            $where .= " AND P.post_date <= '{$to} 23:59:59' ";
        }

        if (trim($customerId) && is_numeric($customerId)) {
            if (HPOS::getStatus()) {
                $hposOrdersTable = "{$wpdb->prefix}wc_orders";
                $where .= " AND (SELECT _O.id FROM {$hposOrdersTable} AS _O WHERE _O.id = P.post_id AND _O.customer_id = {$customerId}) IS NOT NULL ";
            } else {
                $where .= " AND (SELECT _PM.meta_id FROM {$wpdb->postmeta} AS _PM WHERE _PM.post_id = P.post_id AND _PM.meta_key = '_customer_user' AND _PM.meta_value = {$customerId}) IS NOT NULL ";
            }
        }

        $order = " ORDER BY P.post_date DESC, P.post_id DESC ";
        $sql = "SELECT SQL_CALC_FOUND_ROWS P.post_id FROM {$wpdb->prefix}barcode_scanner_posts AS P {$fromTables} WHERE P.post_type = 'shop_order' {$where} {$order} {$limit};";

        $orders = $wpdb->get_results($sql);
        $total = $wpdb->get_row("SELECT FOUND_ROWS() as `total`");

        $ids = array_map(function ($object) {
            return $object->post_id;
        }, $orders);

        if (HPOS::getStatus()) {
            $post_type = array('shop_order', 'shop_order_placehold');
        } else {
            $post_type = 'shop_order';
        }

        if ($ids) {
            $orders = get_posts(array(
                'post_type' => $post_type,
                'post__in' => $ids,
                'post_status' => 'any',
                'numberposts' => 9999,
                'orderby' => 'date',
                'order' => 'DESC',
            ));
        } else {
            $orders = array();
        }

        if ($orders && count($orders)) {
            if (HPOS::getStatus()) {
                $orders = HPOS::ordersPrepare($orders, array(), true, "orders_list");
            } else {
                $orders = (new Results())->ordersPrepare($orders, array(), true, "orders_list");
            }
        }

        $groups = array();

        foreach ($orders as $order) {
            $date = $order["order_date"]->format("Y-m-d");

            if (!isset($groups[$date])) $groups[$date] = array();

            if (!in_array($order["ID"], $groups[$date])) $groups[$date][] = $order["ID"];
        }

        return rest_ensure_response(array(
            "orders" => $orders,
            "groups" => $groups,
            "total" => $total ? $total->total : 0
        ));
    }

    private function importCodesAction($request, $productId, $autoAction, $autoActionField)
    {
        $data = (new Post())->find($productId, array("products" => array("ID" => true)), true, false, null, "product", array());
        $products = (new Results())->productsPrepare($data["posts"], array("useAction" => false));

        if (!$products || count($products) > 1) return;

        $product = $products[0];

        switch ($autoAction) {
            case $this->postAutoAction["AUTO_INCREASING"]: { 
                    if ($autoActionField == "_stock") {
                        if (isset($product["product_manage_stock"]) && isset($product["post_parent"]) && $product["product_manage_stock"] === "parent" && $product["post_parent"]) {
                            if ($autoActionField == "_stock") {
                                $this->setManageStock($product["post_parent"]);
                            }
                            $this->productUpdateQuantityPlus($request, $product["post_parent"]);
                        } else {
                            if ($autoActionField == "_stock") {
                                $this->setManageStock($product["ID"]);
                            }
                            $this->productUpdateQuantityPlus($request, $product["ID"]);
                        }
                    }
                    else {
                        $value = get_post_meta($product["ID"], $autoActionField, true);
                        $value = $value && is_numeric($value) ? $value : 0;
                        update_post_meta($product["ID"], $autoActionField, $value + 1);
                        LogActions::add($product["ID"], LogActions::$actions["quantity_plus"], $autoActionField, $value + 1, $value, "product", $request);
                    }
                    break;
                }
            case $this->postAutoAction["AUTO_DECREASING"]: { 
                    if ($autoActionField == "_stock") {
                        if (isset($product["product_manage_stock"]) && isset($product["post_parent"]) && $product["product_manage_stock"] === "parent" && $product["post_parent"]) {
                            if ($autoActionField == "_stock") {
                                $this->setManageStock($product["post_parent"]);
                            }
                            $this->productUpdateQuantityMinus($request, $product["post_parent"]);
                        } else {
                            if ($autoActionField == "_stock") {
                                $this->setManageStock($product["ID"]);
                            }
                            $this->productUpdateQuantityMinus($request, $product["ID"]);
                        }
                    }
                    else {
                        $settings = new Settings();
                        $allowNegativeStock = $settings->getSettings("allowNegativeStock");
                        $allowNegativeStock = $allowNegativeStock ? $allowNegativeStock->value : "";

                        $value = get_post_meta($product["ID"], $autoActionField, true);
                        $value = $value && is_numeric($value) ? $value : 0;

                        if ($value > 0 || $allowNegativeStock == "on") {
                            update_post_meta($product["ID"], $autoActionField, $value - 1);
                            LogActions::add($product["ID"], LogActions::$actions["quantity_minus"], $autoActionField, $value - 1, $value, "product", $request);
                        }
                    }
                    break;
                }
        }
    }

    public function productIndexation($id, $trigger)
    {
        try {
            Database::updatePost($id, array(), null, null, $trigger);
        } catch (\Throwable $th) {
        }
    }

    public function updateOrderMeta(WP_REST_Request $request)
    {
        $id = $request->get_param("id");
        $key = $request->get_param("key");
        $value = $request->get_param("value");

        if ($id && $key) {
            update_post_meta($id, $key, $value);
        }

        $orderRequest = new WP_REST_Request("", "");
        $orderRequest->set_param("query", $id);

        return $this->orderSearch($orderRequest, false, true);
    }

    public function updateOrderFulfilledObject(WP_REST_Request $request)
    {
        $id = $request->get_param("id");
        $fieldData = $request->get_param("field");

        $data = get_post_meta($id, "usbs_fulfillment_objects", true);

        if (!$data) $data = array();

        $field = isset($fieldData["field"]) ? trim($fieldData["field"]) : "";
        $value = isset($fieldData["value"]) ? trim($fieldData["value"]) : "";
        $type = isset($fieldData["type"]) ? trim($fieldData["type"]) : "";

        if ($field) {
            $data[$field] = array("value" => $value, "type" => $type);
            update_post_meta($id, "usbs_fulfillment_objects", $data);

            $usbs_order_fulfillment_data = get_post_meta($id, "usbs_order_fulfillment_data", true);

            if ($usbs_order_fulfillment_data && isset($usbs_order_fulfillment_data['codes']) && is_array($usbs_order_fulfillment_data['codes'])) {
                $usbs_order_fulfillment_data_updated = false;

                foreach ($usbs_order_fulfillment_data['codes'] as $code_field => &$code_data) {
                    if ($code_field == $field) {
                        $code_data['scanned'] = 1;
                        $usbs_order_fulfillment_data_updated = true;
                    }
                }

                if ($usbs_order_fulfillment_data_updated) {
                    update_post_meta($id, "usbs_order_fulfillment_data", $usbs_order_fulfillment_data);
                }
            }
        }

        $orderRequest = new WP_REST_Request("", "");
        $orderRequest->set_param("query", $id);

        $empty = null;
        $this->getFulfillmentOrderData($id);

        return $this->orderSearch($orderRequest, false, true);
    }

    public function removeFulfillmentObject(WP_REST_Request $request)
    {
        $id = $request->get_param("id");
        $fieldName = $request->get_param("fieldName");

        $data = get_post_meta($id, "usbs_fulfillment_objects", true);

        if (!$data) $data = array();

        if ($id && $fieldName && isset($data[$fieldName])) {
            unset($data[$fieldName]);

            update_post_meta($id, "usbs_fulfillment_objects", $data);

            $usbs_order_fulfillment_data = get_post_meta($id, "usbs_order_fulfillment_data", true);

            if ($usbs_order_fulfillment_data && isset($usbs_order_fulfillment_data['codes']) && is_array($usbs_order_fulfillment_data['codes'])) {
                $usbs_order_fulfillment_data_updated = false;

                foreach ($usbs_order_fulfillment_data['codes'] as $code_field => &$code_data) {
                    if ($code_field == $fieldName) {
                        $code_data['scanned'] = 0;
                        $usbs_order_fulfillment_data_updated = true;
                    }
                }

                if ($usbs_order_fulfillment_data_updated) {
                    update_post_meta($id, "usbs_order_fulfillment_data", $usbs_order_fulfillment_data);
                }
            }
        }

        $orderRequest = new WP_REST_Request("", "");
        $orderRequest->set_param("query", $id);

        $empty = array();
        $this->getFulfillmentOrderData($id);

        return $this->orderSearch($orderRequest, false, true);
    }

    public function update_wc_shipment_tracking_item(WP_REST_Request $request)
    {
        $orderId = $request->get_param("orderId");
        $options = $request->get_param("options");


        if ($orderId && isset($options['fieldId']) && isset($options['fieldName']) && isset($options['fieldValue'])) {
            $wcShipmentTrackingItems = get_post_meta($orderId, "_wc_shipment_tracking_items", true);

            if ($wcShipmentTrackingItems) {
                $isUpdated = false;

                foreach ($wcShipmentTrackingItems as $index => &$value) {
                    if ($index == $options['fieldId']) {
                        $value[$options['fieldName']] = $options['fieldValue'];
                        $isUpdated = true;
                    }
                }

                if ($isUpdated) {
                    update_post_meta($orderId, "_wc_shipment_tracking_items", $wcShipmentTrackingItems);
                }
            }
        }

        $orderRequest = new WP_REST_Request("", "");
        $orderRequest->set_param("query", $orderId);

        return $this->orderSearch($orderRequest, false, true);
    }

    public function delete_wc_shipment_tracking_item(WP_REST_Request $request)
    {
        $orderId = $request->get_param("orderId");
        $options = $request->get_param("options");


        if ($orderId && isset($options['fieldId'])) {
            $wcShipmentTrackingItems = get_post_meta($orderId, "_wc_shipment_tracking_items", true);
            $newList = array();

            if ($wcShipmentTrackingItems) {
                $isUpdated = false;

                foreach ($wcShipmentTrackingItems as $index => $value) {
                    if ($index == $options['fieldId']) {
                        $isUpdated = true;
                        continue;
                    }

                    $newList[] = $value;
                }


                if ($isUpdated) {
                    update_post_meta($orderId, "_wc_shipment_tracking_items", $newList);
                }
            }
        }

        $orderRequest = new WP_REST_Request("", "");
        $orderRequest->set_param("query", $orderId);

        return $this->orderSearch($orderRequest, false, true);
    }

    public function create_wc_shipment_tracking_item(WP_REST_Request $request)
    {
        $orderId = $request->get_param("orderId");


        if ($orderId) {
            $wcShipmentTrackingItems = get_post_meta($orderId, "_wc_shipment_tracking_items", true);

            if (!$wcShipmentTrackingItems) $wcShipmentTrackingItems = array();

            $wcShipmentTrackingItems[] = array(
                'tracking_provider' => '',
                'custom_tracking_provider' => '',
                'custom_tracking_link' => '',
                'tracking_number' => '',
                'tracking_product_code' => '',
                'date_shipped' => time(),
                'products_list' => '',
                'status_shipped' => '',
                'tracking_id' => md5(microtime()),
            );

            update_post_meta($orderId, "_wc_shipment_tracking_items", $wcShipmentTrackingItems);
        }

        $orderRequest = new WP_REST_Request("", "");
        $orderRequest->set_param("query", $orderId);

        return $this->orderSearch($orderRequest, false, true);
    }

    public function getItemsCustomFields(WP_REST_Request $request)
    {
        $orderId = $request->get_param("orderId");
        $fields = $request->get_param("fields");


        if ($orderId && $fields && is_array($fields)) {
            $order = wc_get_order($orderId);

            if ($order) {
                $itemsCF = array();

                foreach ($order->get_items() as $itemId => $value) {
                    $product_id = $value->get_product_id();
                    $variation_id = $value->get_variation_id();

                    if ($variation_id) {
                        $variation = wc_get_product($variation_id);

                        if ($variation) {
                            $product_id = $variation->get_parent_id();
                        }
                    }

                    if ($product_id) {
                        foreach ($fields as $field) {
                            $itemsCF[] = array("item_id" => $itemId, $field => get_post_meta($product_id, $field, true));
                        }
                    }
                }

                $orderCF = array();

                foreach ($fields as $field) {
                    $orderCF[] = array($field => get_post_meta($orderId, $field, true));
                }

                return rest_ensure_response(array("items" => $itemsCF, "order" => $orderCF));
            }
        }

        return rest_ensure_response(array("ID" => $orderId, "fields" => $fields));
    }

    public function getGlobalAttributes($request, $isReturn = false)
    {
        $attributes = \wc_get_attribute_taxonomies();

        foreach ($attributes as $attribute) {
            $taxonomy = 'pa_' . $attribute->attribute_name; 
            $terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false));
            $attribute->values = array();

            if (!empty($terms) && !is_wp_error($terms)) {

                foreach ($terms as $term) {
                    $attribute->values[] = $term;
                }
            }
        }

        $result = array(
            "attributes" => $attributes
        );

        return $isReturn ? $result : rest_ensure_response($result);
    }

    public function createGlobalAttributeValue(WP_REST_Request $request)
    {
        $attributeName = $request->get_param("attributeName");
        $attributeValue = $request->get_param("attributeValue");

        if ($attributeName && $attributeValue) {
            if (!term_exists($attributeValue, $attributeName)) {
                wp_insert_term($attributeValue, $attributeName);
            }
        }

        return $this->getGlobalAttributes($request);
    }

    private function clearProductCache($productId)
    {
        try {
            @wc_delete_product_transients($productId);
        } catch (\Throwable $th) {
        }
    }
}
