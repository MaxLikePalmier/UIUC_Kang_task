<?php

namespace UkrSolution\BarcodeScanner\API;

use UkrSolution\BarcodeScanner\API\actions\CartScannerActions;
use UkrSolution\BarcodeScanner\API\actions\DbActions;
use UkrSolution\BarcodeScanner\API\actions\ManagementActions;
use UkrSolution\BarcodeScanner\API\actions\PostActions;
use UkrSolution\BarcodeScanner\API\actions\UsersActions;
use UkrSolution\BarcodeScanner\API\classes\BatchNumbers;
use UkrSolution\BarcodeScanner\API\classes\BatchNumbersWebis;
use UkrSolution\BarcodeScanner\API\classes\PostsList;
use UkrSolution\BarcodeScanner\API\classes\Users;
use UkrSolution\BarcodeScanner\features\Debug\Debug;
use UkrSolution\BarcodeScanner\features\history\History;
use UkrSolution\BarcodeScanner\features\logs\Logs;
use UkrSolution\BarcodeScanner\features\mobile\MobileRouter;
use UkrSolution\BarcodeScanner\features\settings\PermissionsHelper;
use UkrSolution\BarcodeScanner\features\settings\Settings;
use WP_REST_Request;


class AjaxRoutes
{
    private $coreInstance = null;

    public function __construct($post, $get, $coreInstance = null)
    {
        Debug::addPoint("AjaxRoutes->start");

        $this->coreInstance = $coreInstance;

        if (isset($post["rout"]) && $post["rout"]) {
            $rout = $post["rout"];

            add_filter('scanner_filter_cart_item_price', function ($productId, $price, $customFilter) {
                return $price;
            }, 1, 3);


            $settings = new Settings($this->coreInstance);
            $postActions = new PostActions();
            $managementActions = new ManagementActions();
            $usersActions = new UsersActions();
            $dbActions = new DbActions();
            $cartActions = new CartScannerActions();
            $request = new WP_REST_Request("", "");

            $routes = new Routes();
            $request->set_param("token", $this->getParam($get, "token", ""));

            $tokenUserId = $routes->getUserId($request);
            $request->set_param("platform", $this->getParam($get, "platform", ""));
            $checker = $routes->permissionCallback($request);
            $request->set_param("token_user_id", $tokenUserId);

            if ($tokenUserId) {
                $userLocale = get_user_meta($tokenUserId, 'locale', true);
                if ($userLocale) switch_to_locale($userLocale);

                $userRole = $tokenUserId ? Users::getUserRole($tokenUserId) : '';
                Users::setUserId($tokenUserId);
                Users::setUserRole($userRole);
            }

            if (!key_exists('woocommerce/woocommerce.php', get_plugins())) {
                $MobileRouter = new MobileRouter();
                $platform = $this->getParam($get, "platform", "");
                echo json_encode(array(
                    "errors" => array("WooCommerce is not activated"),
                    "cartErrors" => array(
                        array("notice" => "WooCommerce is not activated", "htmlMessageClass" => "err_woocommerce_is_not_activated")
                    ),
                ));
                wp_die();
            }

            if (!$checker && !in_array($rout, array(
                "recalculate",
                "backgroundIndexing",
                "checkOtherPrices",
                "checkFieldName",
                "exportLog",
                "saveLog",
                "indexingClearTable",
                "getHistory",
                "getItemsList"
            ))) {
                $MobileRouter = new MobileRouter();
                $platform = $this->getParam($get, "platform", "");
                $urlData = $MobileRouter->getParamsFromPlainUrl();
                $jsData = $this->coreInstance->adminEnqueueScripts(true, true, $urlData);
                $usbs = $jsData && isset($jsData['usbs']) ? $jsData['usbs'] : array();

                echo json_encode(array(
                    "redirect" => 0,
                    "data" => array("rout" => $rout),
                    "f" => 1,
                    "usbs" => $usbs
                ));
                exit;
            }

            PermissionsHelper::init($request);

            $keysString = array(
                "id",
                "orderId",
                "userId",
                "itemId",
                "recordId",
                "query",
                "withVariation",
                "productId",
                "quantity",
                "price",
                "title",
                "postId",
                "attachmentId",
                "field",
                "reqbs",
                "postAutoAction",
                "postAutoField",
                "ignoreStockDisabled",
                "status",
                "autoFill",
                "byId",
                "setQty",
                "orderCustomPrice",
                "orderCustomSubPrice",
                "orderCustomTax",
                "orderStatus",
                "shippingMethod",
                "paymentMethod",
                "key",
                "session",
                "sessionStamp",
                "orderUserId",
                "productQty",
                "tab",
                "param",
                "str",
                "orderAutoAction",
                "orderAutoStatus",
                "autoEnableIndexation",
                "slug",
                "country",
                "customAction",
                "userAction",
                "bsInstanceFrontendStatus",
                "isCheck",
                "isAddToList",
                "modifyAction",
                "isNew",
                "fulfillmentOrderId",
                "confirmed",
                "isUpdateShipping",
                "coupon",
                "customerId",
                "isPay",
                "fieldName",
                "parentId",
                "isMainImage",
                "withoutStatuses",
                "attributeName",
                "attributeValue",
                "loadCustomerData"
            );
            $keysArray = array(
                "filter",
                "customFilter",
                "filterExcludes",
                "products",
                "fields",
                "progress",
                "userData",
                "currentItems",
                "itemsCustomPrices",
                "cartItem",
                "extraData",
                "inputs",
                "data",
                "categories",
                "locations",
                "codes",
                "lines",
                "autoAction",
                "options",
                "items",
                "filterResult",
                "address",
                "batches",
                "batchesWebis",
                "shipmentTrackingItems",
                "currentIds",
                "postTypes",
                "value",
                "globalAttributes",
                "customAttributes",
                "globalOptions",
                "customOptions"
            );
            $response = array();

            foreach ($keysString as $key) {
                $value = $this->getParam($post, $key, "");
                $value = wp_unslash(sanitize_post_field($key, $value, 0, 'db'));
                $request->set_param($key, $value);
            }

            foreach ($keysArray as $key) {
                $request->set_param($key, $this->getParam($post, $key, array()));
            }

            $_POST["bsInstanceFrontendStatus"] = $request->get_param("bsInstanceFrontendStatus");

            switch ($rout) {
                case 'getPost':
                    PermissionsHelper::onePermRequired(['inventory', 'orders']);
                    $response = $postActions->postSearch($request);
                    break;
                case 'getProduct':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod', 'orders', 'cart']);
                    $response = $managementActions->productSearch($request);
                    break;
                case 'getProducts':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod', 'orders', 'cart']);
                    $response = $managementActions->getProducts($request);
                    break;
                case 'getOrder':
                    PermissionsHelper::onePermRequired(['orders']);
                    $response = $managementActions->orderSearch($request);
                    break;
                case 'productEnableManageStock':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod']);
                    $response = $managementActions->productEnableManageStock($request);
                    break;
                case 'updateProductQuantity':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod']);
                    $response = $managementActions->productUpdateQuantity($request);
                    break;
                case 'updateProductQuantityPlus':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod']);
                    $response = $managementActions->productUpdateQuantityPlus($request);
                    break;
                case 'updateProductQuantityMinus':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod']);
                    $response = $managementActions->productUpdateQuantityMinus($request);
                    break;
                case 'updateProductRegularPrice':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod']);
                    $response = $managementActions->productUpdateRegularPrice($request);
                    break;
                case 'updateProductSalePrice':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod']);
                    $response = $managementActions->productUpdateSalePrice($request);
                    break;
                case 'updateProductCustomPrice':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod']);
                    $response = $managementActions->updateProductCustomPrice($request);
                    break;
                case 'updateProductMeta':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod']);
                    $response = $managementActions->productUpdateMeta($request);
                    break;
                case 'updatePostStatus':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod']);
                    $response = $managementActions->productUpdateStatus($request);
                    break;
                case 'updateTitle':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod']);
                    $response = $managementActions->productUpdateTitle($request);
                    break;
                case 'setImage':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod']);
                    $response = $managementActions->productSetImage($request);
                    break;
                case 'createNew':
                    PermissionsHelper::onePermRequired(['newprod']);
                    $response = $managementActions->productCreateNew($request);
                    break;
                case 'reloadNewProduct':
                    PermissionsHelper::onePermRequired(['newprod']);
                    $response = $managementActions->reloadNewProduct($request);
                    break;
                case 'update':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod']);
                    $response = $managementActions->productUpdateFields($request);
                    break;
                case 'uploadPostImage':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod']);
                    $response = $managementActions->uploadPostImage($request);
                    break;
                case 'removePostImages':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod']);
                    $response = $managementActions->removePostImages($request);
                    break;
                case 'removePostMainImage':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod']);
                    $response = $managementActions->removePostMainImage($request);
                    break;
                case 'changeStatus':
                    PermissionsHelper::onePermRequired(['orders']);
                    $response = $managementActions->orderChangeStatus($request);
                    break;
                case 'changeCustomer':
                    PermissionsHelper::onePermRequired(['orders']);
                    $response = $managementActions->orderChangeCustomer($request);
                    break;
                case 'changeOrderAddress':
                    PermissionsHelper::onePermRequired(['orders']);
                    $response = $managementActions->changeOrderAddress($request);
                    break;
                case 'updateOrderMeta':
                    PermissionsHelper::onePermRequired(['orders']);
                    $response = $managementActions->updateOrderMeta($request);
                    break;
                case 'orderUpdateItemsMeta':
                    PermissionsHelper::onePermRequired(['orders']);
                    $response = $managementActions->orderUpdateItemsMeta($request);
                    break;
                case 'orderUpdateItemMeta':
                    PermissionsHelper::onePermRequired(['orders']);
                    $response = rest_ensure_response($managementActions->orderUpdateItemMeta($request));
                    break;
                case 'updateFoundCounter':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod', 'orders', 'cart']);
                    $response = $managementActions->updateFoundCounter($request);
                    break;
                case 'uploadPick':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod']);
                    $response = $managementActions->uploadPick($request);
                    break;
                case 'getProductCategories':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod']);
                    $response = $managementActions->getProductCategories($request);
                    break;
                case 'updateCategories':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod']);
                    $response = $managementActions->updateCategories($request);
                    break;
                case 'updateAttributes':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod']);
                    $response = $managementActions->updateAttributes($request);
                    break;
                case 'getGlobalAttributes':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod']);
                    $response = $managementActions->getGlobalAttributes($request);
                    break;
                case 'createGlobalAttributeValue':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod']);
                    $response = $managementActions->createGlobalAttributeValue($request);
                    break;
                case 'importCodes':
                    PermissionsHelper::onePermRequired(['inventory']);
                    $response = $managementActions->importCodes($request);
                    break;
                case 'getItemsList':
                    PermissionsHelper::onePermRequired(['inventory']);
                    $response = rest_ensure_response(array("productsList" => PostsList::getList(Users::getUserId($request))));
                    break;
                case 'updateItemsFromList':
                    PermissionsHelper::onePermRequired(['inventory']);
                    $response = $managementActions->updateItemsFromList($request);
                    break;
                case 'removeItemsListRecord':
                    PermissionsHelper::onePermRequired(['inventory']);
                    $response = $managementActions->removeItemsListRecord($request);
                    break;
                case 'clearItemsList':
                    PermissionsHelper::onePermRequired(['inventory']);
                    $response = $managementActions->clearItemsList($request);
                    break;
                case 'getOrdersList':
                    PermissionsHelper::onePermRequired(['orders']);
                    $response = $managementActions->getOrdersList($request);
                    break;
                case 'updateOrderFulfilledObject':
                    PermissionsHelper::onePermRequired(['orders']);
                    $response = $managementActions->updateOrderFulfilledObject($request);
                    break;
                case 'removeFulfillmentObject':
                    PermissionsHelper::onePermRequired(['orders']);
                    $response = $managementActions->removeFulfillmentObject($request);
                    break;
                case 'update_wc_shipment_tracking_item':
                    PermissionsHelper::onePermRequired(['orders']);
                    $response = $managementActions->update_wc_shipment_tracking_item($request);
                    break;
                case 'delete_wc_shipment_tracking_item':
                    PermissionsHelper::onePermRequired(['orders']);
                    $response = $managementActions->delete_wc_shipment_tracking_item($request);
                    break;
                case 'create_wc_shipment_tracking_item':
                    PermissionsHelper::onePermRequired(['orders']);
                    $response = $managementActions->create_wc_shipment_tracking_item($request);
                    break;
                case 'getItemsCustomFields':
                    PermissionsHelper::onePermRequired(['orders']);
                    $response = $managementActions->getItemsCustomFields($request);
                    break;
                case 'usersFind':
                    PermissionsHelper::onePermRequired(['linkcustomer', 'plugin_settings']);
                    $response = $usersActions->find($request);
                    break;
                case 'userCreate':
                    PermissionsHelper::onePermRequired(['orders', 'cart']);
                    PermissionsHelper::onePermRequired(['linkcustomer']);
                    $response = $usersActions->createUser($request);
                    break;
                case 'getStates':
                    PermissionsHelper::onePermRequired(['orders', 'cart']);
                    $response = $usersActions->getStates($request);
                    break;
                case 'addItem':
                    PermissionsHelper::onePermRequired(['cart']);
                    $response = $cartActions->addItem($request);
                    break;
                case 'removeItem':
                    PermissionsHelper::onePermRequired(['cart']);
                    $response = $cartActions->removeItem($request);
                    break;
                case 'updateQuantity':
                    PermissionsHelper::onePermRequired(['cart']);
                    $response = $cartActions->updateQuantity($request);
                    break;
                case 'updateCartItemAttributes':
                    PermissionsHelper::onePermRequired(['cart']);
                    $response = $cartActions->updateAttributes($request);
                    break;
                case 'clear':
                    PermissionsHelper::onePermRequired(['cart']);
                    $response = $cartActions->cartClear($request);
                    break;
                case 'orderCreate':
                    PermissionsHelper::onePermRequired(['cart']);
                    $response = $cartActions->orderCreate($request);
                    break;
                case 'getStatuses':
                    PermissionsHelper::onePermRequired(['orders', 'cart']);
                    $response = $cartActions->getStatuses($request);
                    break;
                case 'recalculate':
                    PermissionsHelper::onePermRequired(['cart']);
                    $response = $cartActions->cartRecalculate($request);
                    break;
                case 'createColumn':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod', 'orders', 'cart']);
                    $response = $dbActions->createColumn($request);
                    break;
                case 'saveSession':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod', 'orders', 'cart']);
                    $response = $dbActions->saveSession($request);
                    break;
                case 'saveSettings':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod', 'orders', 'cart']);
                    $response = $dbActions->saveSettings($request);
                    break;
                case 'backgroundIndexing':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod', 'orders', 'cart']);
                    $response = $dbActions->backgroundIndexing($request);
                    break;
                case 'indexingClearTable':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod', 'orders', 'cart']);
                    $response = $dbActions->indexingClearTable($request);
                    break;
                case 'checkOtherPrices':
                    PermissionsHelper::onePermRequired(['plugin_settings']);
                    $response = $postActions->checkOtherPrices($request);
                    break;
                case 'checkFieldName':
                    PermissionsHelper::onePermRequired(['plugin_settings']);
                    $response = $postActions->checkFieldName($request);
                    break;
                case 'updateSettings':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod', 'orders', 'cart']);
                    $response = $settings->updateSettingsArray($request);
                    break;
                case 'loadSettings':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod', 'orders', 'cart']);
                    $response = $settings->loadSettingsArray($request);
                    break;
                case 'appUsersUpdate':
                    PermissionsHelper::onePermRequired(['plugin_settings']);
                    $MobileRouter = new MobileRouter();
                    $userSessions = $this->getParam($post, "str", "");
                    $platform = $this->getParam($get, "platform", "");
                    $settings->updateSettings("userSessions", $userSessions, "text");
                    $uid = $this->getParam($post, "userId", "");
                    $userAction = $this->getParam($post, "userAction", "");
                    $password = "";
                    if ($uid && $userAction == "add") {
                        $password = $usersActions->usersGenerateOtp();
                        update_user_meta($uid, "barcode_scanner_app_otp", md5($password));
                        update_user_meta($uid, "barcode_scanner_app_otp_expired_dt", time());
                    }
                    if ($uid && $userAction == "remove") {
                        update_user_meta($uid, "barcode_scanner_app_otp", "");
                        update_user_meta($uid, "barcode_scanner_app_otp_expired_dt", "");
                    }
                    $urlData = $MobileRouter->getParamsFromPlainUrl();
                    $jsData = $this->coreInstance->adminEnqueueScripts(true, true, $urlData);
                    $usbs = $jsData && isset($jsData['usbs']) ? $jsData['usbs'] : array();
                    $response = rest_ensure_response(array("usbs" => $usbs, "password" => $password));
                    break;
                case 'appUserUpdatePassword':
                    PermissionsHelper::onePermRequired(['plugin_settings']);
                    $response = $usersActions->updatePassword($request);
                    break;
                case 'exportLog':
                    PermissionsHelper::onePermRequired(['plugin_logs']);
                    $logs = new Logs();
                    $response = $logs->export($request);
                    break;
                case 'getHistory':
                    PermissionsHelper::onePermRequired(['inventory', 'orders', 'cart']);
                    $response = rest_ensure_response(array("history" => History::getByUser()));
                    break;

                case 'usbs_find_order_save_id':
                    PermissionsHelper::onePermRequired(['plugin_settings']);
                    $response = $settings->saveTemplateOrderId($request);
                    break;
                case 'usbs_find_order':
                    PermissionsHelper::onePermRequired(['plugin_settings']);
                    $response = $settings->findOrder($request);
                    break;

                case 'batchNumbersRemoveBatch':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod']);
                    $response = BatchNumbers::removeBatch($request);
                    break;
                case 'batchNumbersAddNewBatch':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod']);
                    $response = BatchNumbers::addNewBatch($request);
                    break;
                case 'batchNumbersSaveBatchField':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod']);
                    $response = BatchNumbers::saveBatchField($request);
                    break;

                case 'batchNumbersWebisRemoveBatch':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod']);
                    $response = BatchNumbersWebis::removeBatch($request);
                    break;
                case 'batchNumbersWebisAddNewBatch':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod']);
                    $response = BatchNumbersWebis::addNewBatch($request);
                    break;
                case 'batchNumbersWebisSaveBatchField':
                    PermissionsHelper::onePermRequired(['inventory', 'newprod']);
                    $response = BatchNumbersWebis::saveBatchField($request);
                    break;
                default:
                    break;
            }

            $filter = $this->getParam($post, "filter", array());
            if ($filter && in_array($rout, array("checkCustomFields", "createColumn"))) {
                $settings = new Settings();
                $settings->updateSettings('search_filter', json_encode($filter));
            }


            if ($response && $response->data) {
                $updatedTimestamp = $settings->getSettings("updated_timestamp");
                $response->data["settings_updated_timestamp"] = $updatedTimestamp ? $updatedTimestamp->value : "";
                echo json_encode($response->data);
            } else {
                echo json_encode("error");
            }
            exit;
        }
    }

    private function getParam($post, $key, $default = null)
    {
        if (isset($post[$key])) {
            return $post[$key];
        } else {
            return $default;
        }
    }
}
