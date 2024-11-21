<?php

namespace UkrSolution\BarcodeScanner\API\classes;

use DateTime;
use UkrSolution\BarcodeScanner\API\actions\ManagementActions;
use UkrSolution\BarcodeScanner\API\PluginsHelper;
use WP_REST_Request;

class BatchNumbers
{
    static private $hook_update_batch_fields = 'usbs_batch_numbers_update_batch_fields';

    static public function status()
    {
        return PluginsHelper::is_plugin_active('woocommerce-product-batch-numbers/woocommerce-product-batch-numbers.php');
    }

    static public function addProductProps(&$fields)
    {
        global $wpdb;

        if (!$fields || !isset($fields['ID'])) return;

        $batchNumbers = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wpo_wcpbn_batch_numbers AS BN, {$wpdb->prefix}wpo_wcpbn_shared_products AS SP 
            WHERE BN.id = SP.batch_id AND SP.product_id = %d;",
            $fields['ID']
        ));

        if ($batchNumbers) {
            foreach ($batchNumbers as &$value) {
                $value->editUrl = admin_url('edit.php?post_type=product&page=wpo-batch-numbers&tab=tools&batch=' . $value->id);
            }
        }

        $fields['batchNumbers'] = $batchNumbers ? $batchNumbers : array();
    }

    static public function removeBatch(WP_REST_Request $request)
    {
        global $wpdb;

        $batchId = (int)$request->get_param("id");
        $postId = (int)$request->get_param("postId");

        if ($batchId && $postId) {
            $wpdb->delete("{$wpdb->prefix}wpo_wcpbn_batch_numbers", array("id" => $batchId));

            $wpdb->delete("{$wpdb->prefix}wpo_wcpbn_shared_products", array("batch_id" => $batchId, "product_id" => $postId));
        }

        $managementActions = new ManagementActions();
        $productRequest = new WP_REST_Request("", "");
        $productRequest->set_param("query", $postId);

        return $managementActions->productSearch($productRequest, false, true);
    }

    static public function addNewBatch(WP_REST_Request $request)
    {
        global $wpdb;

        $postId = (int)$request->get_param("postId");

        if ($postId) {
            $dt = new DateTime('now');
            $userId = Users::getUserId($request);

            $wpdb->insert("{$wpdb->prefix}wpo_wcpbn_batch_numbers", array(
                "date_created" => $dt->format('Y-m-d H:i:s'),
                "date_expiry" => null,
                "user_id" => $userId,
            ));
            $batchId = $wpdb->insert_id;

            if ($batchId) {
                $wpdb->insert("{$wpdb->prefix}wpo_wcpbn_shared_products", array(
                    "date_created" => $dt->format('Y-m-d H:i:s'),
                    "batch_id" => $batchId,
                    "product_id" => $postId,
                ));
            }
        }

        $managementActions = new ManagementActions();
        $productRequest = new WP_REST_Request("", "");
        $productRequest->set_param("query", $postId);

        return $managementActions->productSearch($productRequest, false, true);
    }

    static public function saveBatchField(WP_REST_Request $request)
    {
        global $wpdb;

        $data = $request->get_param("data");
        $postId = (int)$request->get_param("postId");
        $batchId = isset($data['batchId']) ? (int)$data['batchId'] : null;
        $field = isset($data['field']) ? $data['field'] : null;
        $value = isset($data['value']) ? $data['value'] : null;

        if ($batchId && $field) {
            $fields = array($field => $value);
            if ($field === 'quantity') $fields['quantity_available'] = $value;

            $fields = apply_filters(self::$hook_update_batch_fields, $fields, $batchId);

            $wpdb->update("{$wpdb->prefix}wpo_wcpbn_batch_numbers", $fields, array("id" => $batchId));
        }

        $managementActions = new ManagementActions();
        $productRequest = new WP_REST_Request("", "");
        $productRequest->set_param("query", $postId);

        return $managementActions->productSearch($productRequest, false, true);
    }

    static public function updateBatches($batches)
    {
        global $wpdb;

        if (!$batches) return;

        try {
            foreach ($batches as $key => $value) {
                $data = array(
                    'date_expiry' => $value['date_expiry'],
                    'batch_number' => $value['batch_number'],
                    'supplier' => $value['supplier'],
                    'quantity' => $value['quantity'],
                    'quantity_available' => $value['quantity'],
                    'status' => $value['status'],
                );

                $fields = apply_filters(self::$hook_update_batch_fields, $data, $value['id']);

                $wpdb->update("{$wpdb->prefix}wpo_wcpbn_batch_numbers", $data, array("id" => $value['id']));
            }
        } catch (\Throwable $th) {
            throw $th;
        }
    }
}
