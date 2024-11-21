<?php

namespace UkrSolution\BarcodeScanner\API\classes;

use UkrSolution\BarcodeScanner\API\PluginsHelper;
use UkrSolution\BarcodeScanner\Database;
use UkrSolution\BarcodeScanner\features\Debug\Debug;
use UkrSolution\BarcodeScanner\features\settings\Settings;

class Post
{
    private $limit = 20;
    private $onlyById = false;
    private $searchType = "";
    private $filter_search_query = "scanner_search_query";
    private $filterExcludes = array();

    public function find(
        $queryRequest,
        $filter,
        $onlyById = false,
        $autoFill = false,
        $limit = null,
        $searchType = "",
        $filterExcludes = array(),
        $withoutStatuses = false,
        $postTypes = array(),
        $tab = ""
    ) {
        global $wpdb;

        $settings = new Settings();

        $field = $settings->getSettings("searchResultsLimit");
        $searchResultsLimit = $field === null ? 20 : (int)$field->value;
        $searchResultsLimit = $searchResultsLimit ? $searchResultsLimit : 20;

        $isDisabledVariations = false;

        if ($tab == "products") {
            $disabledVariationsProducts = $settings->getSettings("disabledVariationsProducts");
            $disabledVariationsProducts = $disabledVariationsProducts === null ? 'off' : $disabledVariationsProducts->value;

            if ($disabledVariationsProducts == 'on') $isDisabledVariations = true;
        }

        if ($tab == "cart" || $tab == "orders") {
            $disabledVariationsOrders = $settings->getSettings("disabledVariationsOrders");
            $disabledVariationsOrders = $disabledVariationsOrders === null ? 'on' : $disabledVariationsOrders->value;

            if ($disabledVariationsOrders == 'on') $isDisabledVariations = true;
        }

        $queryRequest = apply_filters($this->filter_search_query, trim($queryRequest));

        if ($searchResultsLimit != $this->limit && $searchResultsLimit > 0) {
            $this->limit = $searchResultsLimit;
        }

        if ($limit && $limit < 10) {
            $this->limit = $limit;
        }

        $this->searchType = $searchType;
        $this->onlyById = $onlyById;
        $this->filterExcludes = $filterExcludes;

        $excludeStatuses = "";
        $excludeProductsCatalogVisibility = "";

        if ($searchType === "product" && $withoutStatuses === false) {
            $field = $settings->getSettings("productStatuses");
            $excludeStatuses = $field === null ? "trash" : $field->value;

            $field = $settings->getSettings("productsCatalogVisibility");
            $excludeProductsCatalogVisibility = $field === null ? "" : $field->value;
        } else if ($searchType === "order" && $withoutStatuses === false) {
            $field = $settings->getSettings("orderStatuses");
            $excludeStatuses = $field === null ? "wc-checkout-draft,trash" : $field->value;
        }

        $filterWithoutTitle = $filter;
        $findByTitle = false;

        if ($this->isUrl($queryRequest)) {
            $queryRequest = urldecode($queryRequest);
        }

        $query = $queryRequest;
        $query = trim($query);
        $query = str_replace("'", "\'", $query);

        $queryFromUrl = $this->checkUrlInQuery($query, $filterWithoutTitle);

        if ($query != $queryFromUrl && is_numeric($queryFromUrl)) {
            if (isset($filterWithoutTitle["products"]) && $filterWithoutTitle["products"]["prod_link"]) {
                $filterWithoutTitle["products"]["ID"] = 1;
            }

            $query = $queryFromUrl;
        }

        $sql = $this->ownSqlBuilder(
            $query,
            $filterWithoutTitle,
            $excludeStatuses,
            $excludeProductsCatalogVisibility,
            $postTypes,
            $isDisabledVariations
        );

        $postsSearchData = $wpdb->get_results($sql);

        Debug::addPoint("1. sql");
        Debug::addPoint($sql);
        Debug::addPoint("1. posts = " . count($postsSearchData));

        if (count($postsSearchData) > 1) {
            $types = "'shop_order', 'product', 'product_variation'";
            $ids = implode(",", array_column($postsSearchData, 'ID'));

            $sql =  "SELECT P.* FROM {$wpdb->posts} AS P WHERE P.ID IN( {$ids} ) AND P.post_type IN( {$types} ) LIMIT {$this->limit}; ";
            $posts = $wpdb->get_results($sql);

            Debug::addPoint("1. wpml status = " . (WPML::status() ? 1 : 0) . ', by id ' . ($onlyById ? 1 : 0));

            if (WPML::status() || Polylang::status()) {
                $posts = WPML::postsFilter($posts, $filter);
            }

            $count = $posts ? count($posts) : 0;
            Debug::addPoint("1. after wpml posts = " . $count);

            return array(
                "posts" => $posts,
                "postsSearchData" => $postsSearchData,
                "filter" => $this->getFilterParams($filterWithoutTitle),
                "findByTitle" => $findByTitle,
                "query" => stripslashes($query)
            );
        } elseif (count($postsSearchData)) {
            $post = get_post($postsSearchData[0]->ID);
            $posts = ($post) ? array($post) : null;

            Debug::addPoint("1. wpml status = " . (WPML::status() ? 1 : 0) . ', by id ' . ($onlyById ? 1 : 0));

            if ((WPML::status() || Polylang::status()) && !$onlyById) {
                $posts = WPML::postsFilter($posts, $filter);
            }

            $count = $posts ? count($posts) : 0;
            Debug::addPoint("2. after wpml posts = " . $count);

            return array(
                "posts" => $posts,
                "postsSearchData" => $postsSearchData,
                "filter" => $this->getFilterParams($filterWithoutTitle),
                "findByTitle" => $findByTitle,
                "query" => stripslashes($query)
            );
        }

        return array(
            "posts" => null,
            "findByTitle" => null,
            "query" => stripslashes($query)
        );
    }

    private function ownSqlBuilder(
        $query,
        $filter,
        $excludeStatuses,
        $excludeProductsCatalogVisibility = '',
        $postTypes = array(),
        $isDisabledVariations = false
    ) {
        global $wpdb;

        $queryLike = str_replace(" ", "%", $query);

        $posts = $wpdb->prefix . Database::$posts;
        $columns = $wpdb->prefix . Database::$columns;
        $metaFieldPrefix = Database::$postMetaFieldPrefix;

        $tableColumns = $wpdb->get_results("SELECT * FROM {$columns} AS C;");

        $statuses = explode(",", $excludeStatuses);
        $catalogVisibility = explode(",", $excludeProductsCatalogVisibility);

        if (!count($statuses)) $statuses[] = "trash";

        $filterSql = "";
        $selectColumns = array();
        $params = $this->getFilterParams($filter);
        $isNumeric = (preg_match('/^[0-9]{0,20}$/', trim($query), $m)) ? true : false;

        $sql = "SELECT P.post_id AS ID %SELECT_COLUMNS% FROM {$posts} AS P "
            . " WHERE "
            . " ( P.post_status NOT IN('" . implode("','", $statuses) . "') OR P.post_status IS NULL )";


        if ($params["post_author"] && $params["post_author"] > 0 && $params["post_types"] && in_array('shop_order', $params["post_types"])) {
            $sql .= " AND (P.post_author = " . $params["post_author"] . " OR P.postmeta__customer_user = " . $params["post_author"] . ") ";
        }

        if ($isDisabledVariations && $params["post_types"] && in_array('product_variation', $params["post_types"])) {
            $sql .= " AND ( (P.post_status != 'private' AND post_type = 'product_variation') OR post_type != 'product_variation') ";
        }

        $sql .= " AND ( P.post_parent = 0 OR P.post_parent IS NULL OR P.post_parent_status IS NUll OR P.post_parent_status NOT IN('" . implode("','", $statuses) . "')  ) ";

        if (is_array($catalogVisibility)) {
            $notIn = array();

            if (in_array('catalog', $catalogVisibility)) {
                $notIn[] = 'exclude-from-search';
                $notIn[] = 'exclude-from-search,featured';
            }
            if (in_array('search', $catalogVisibility)) {
                $notIn[] = 'exclude-from-catalog';
                $notIn[] = 'exclude-from-catalog,featured';
            }
            if (in_array('hidden', $catalogVisibility)) {
                $notIn[] = 'exclude-from-search,exclude-from-catalog';
                $notIn[] = 'exclude-from-search,exclude-from-catalog,featured';
            }

            if ($notIn) {
                $sql .= " AND (
                    ( SELECT GROUP_CONCAT(t.slug) FROM {$wpdb->prefix}term_relationships AS tr, {$wpdb->prefix}term_taxonomy AS tt, {$wpdb->prefix}terms AS t 
                        WHERE tr.object_id = P.post_id AND tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_visibility' AND t.term_id = tt.term_id ) NOT IN('" . implode("','", $notIn) . "')
                    OR ( SELECT GROUP_CONCAT(t.slug) FROM {$wpdb->prefix}term_relationships AS tr, {$wpdb->prefix}term_taxonomy AS tt, {$wpdb->prefix}terms AS t 
                        WHERE tr.object_id = P.post_id AND tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_visibility' AND t.term_id = tt.term_id ) IS NULL 
                ) ";
            }

            if (in_array('visible', $catalogVisibility)) {
                $sql .= " AND (
                    ( SELECT GROUP_CONCAT(t.slug) FROM {$wpdb->prefix}term_relationships AS tr, {$wpdb->prefix}term_taxonomy AS tt, {$wpdb->prefix}terms AS t 
                        WHERE tr.object_id = P.post_id AND tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_visibility' AND t.term_id = tt.term_id ) != 'featured'
                    AND ( SELECT GROUP_CONCAT(t.slug) FROM {$wpdb->prefix}term_relationships AS tr, {$wpdb->prefix}term_taxonomy AS tt, {$wpdb->prefix}terms AS t 
                        WHERE tr.object_id = P.post_id AND tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_visibility' AND t.term_id = tt.term_id ) IS NOT NULL 
                ) ";
            }
        }

        if ($postTypes && in_array('variable', $postTypes)) {
            $sql .= " AND (SELECT COUNT(_T.term_id) FROM {$wpdb->prefix}term_relationships AS _TR, {$wpdb->prefix}term_taxonomy AS _TT, {$wpdb->prefix}terms AS _T WHERE _TR.object_id = P.post_ID AND _TT.term_taxonomy_id = _TR.term_taxonomy_id AND _TT.taxonomy = 'product_type' AND _T.term_id = _TT.term_id AND _T.slug = 'variable') ";
        }

        $sql .= " AND ( ";

        if ($params["postID"] && $isNumeric) {
            if ($params["post_types"]) {
                $types = "'" . implode("', '", $params["post_types"]) . "'";

                if ($params["postID"] == 2) {
                    $filterSql .= " (P.post_id LIKE '%{$query}%' AND P.post_type IN({$types})) ";
                } else {
                    $filterSql .= " (P.post_id = '{$query}' AND P.post_type IN({$types})) ";
                }
            } else {
                $filterSql .= " P.post_id = '{$query}' ";
            }
        }

        if ($params["post_title"] != 0) {
            $filterSql .= (strlen($filterSql)) ? " OR " : "";
            $types = "'product', 'product_variation'";

            if ($params["post_title"] != 2) {
                $_sql = " P.post_type IN( {$types} ) AND P.post_title = '{$query}' ";
            } else {
                $_sql = " P.post_type IN( {$types} ) AND P.post_title LIKE '%{$queryLike}%' ";
            }

            $filterSql .= $_sql;
            $selectColumns[] = "({$_sql}) AS 'post_title'";
        }

        if ($params["_sku"] != 0) {
            $types = "'product', 'product_variation'";
            $filterSql .= (strlen($filterSql)) ? " OR " : "";
            $_sql = $this->sqlComp($params["_sku"], $types, $query, "P.{$metaFieldPrefix}_sku");

            $filterSql .= $_sql;
            $selectColumns[] = "{$_sql} AS '_sku'";
        }

        if ($params["post_excerpt"] != 0) {
            $filterSql .= (strlen($filterSql)) ? " OR " : "";
            $_types = "'" . implode("', '", $params["post_types"]) . "'";
            $_sql = $this->sqlComp($params["post_excerpt"], $_types, $query, "P.post_excerpt");
            $filterSql .= $_sql;
            $selectColumns[] = "{$_sql} AS 'post_excerpt'";
        }

        if ($params["_variation_description"] != 0) {
            $types = "'product', 'product_variation'";
            $filterSql .= (strlen($filterSql)) ? " OR " : "";
            $_sql = $this->sqlComp($params["_variation_description"], $types, $query, "P.{$metaFieldPrefix}_variation_description");

            $filterSql .= $_sql;
            $selectColumns[] = "{$_sql} AS '_variation_description'";
        }

        if ($params["productCF"] && $params["statusProductCF"] != 0) {
            $types = "'product', 'product_variation'";
            $field = trim($params["productCF"]);

            foreach ($tableColumns as $value) {
                if ($value->name === $field) {
                    $field = $value->column;
                    $filterSql .= (strlen($filterSql)) ? " OR " : "";
                    $_sql = $this->sqlComp($params["statusProductCF"], $types, $query, "P.{$field}");

                    $filterSql .= $_sql;
                    $selectColumns[] = "{$_sql} AS '{$field}'";
                    break;
                }
            }

            if (key_exists($field, Database::$postsFields)) {
                $filterSql .= (strlen($filterSql)) ? " OR " : "";
                $_sql = $params["statusProductCF"] == 2 ? " ( P.post_type IN( {$types} ) AND  P.`{$field}` LIKE '%{$query}%' ) " :  " ( P.post_type IN( {$types} ) AND  P.`{$field}` = '{$query}' ) ";

                $filterSql .= $_sql;
                $selectColumns[] = "{$_sql} AS '{$field}'";
            }
        }

        if ($params["orderCF"] && $params["statusOrderCF"] != 0) {
            $types = "'shop_order'";
            $field = trim($params["orderCF"]);

            foreach ($tableColumns as $value) {
                if ($value->name === $field) {
                    $field = $value->column;
                    $filterSql .= (strlen($filterSql)) ? " OR " : "";
                    $_sql = $this->sqlComp($params["statusOrderCF"], $types, $query, "P.{$field}");

                    $filterSql .= $_sql;
                    $selectColumns[] = "{$_sql} AS '{$field}'";
                    break;
                }
            }

            if (key_exists($field, Database::$postsFields)) {
                $filterSql .= (strlen($filterSql)) ? " OR " : "";
                $_sql = Database::$postsFields[$field] === "like" || $params["statusProductCF"] == 2 ? " ( P.post_type IN( {$types} ) AND  P.`{$field}` LIKE '%{$query}%' ) " :  " ( P.post_type IN( {$types} ) AND  P.`{$field}` = '{$query}' ) ";

                $filterSql .= $_sql;
                $selectColumns[] = "{$_sql} AS '{$field}'";
            }
        }

        if ($params["_alg_ean"] != 0) {
            $filterSql .= (strlen($filterSql)) ? " OR " : "";
            $types = "'product', 'product_variation'";
            $_sql = $this->sqlComp($params["_alg_ean"], $types, $query, "P.{$metaFieldPrefix}_alg_ean");

            $filterSql .= $_sql;
            $selectColumns[] = "{$_sql} AS '_alg_ean'";
        }

        if ($params["_wpm_gtin_code"] != 0) {
            $filterSql .= (strlen($filterSql)) ? " OR " : "";
            $types = "'product', 'product_variation'";
            $_sql = $this->sqlComp($params["_wpm_gtin_code"], $types, $query, "P.{$metaFieldPrefix}_wpm_gtin_code");

            $filterSql .= $_sql;
            $selectColumns[] = "{$_sql} AS '_wpm_gtin_code'";
        }

        if ($params["hwp_product_gtin"] != 0) {
            $filterSql .= (strlen($filterSql)) ? " OR " : "";
            $types = "'product', 'product_variation'";
            $_sql = $this->sqlComp($params["hwp_product_gtin"], $types, $query, "P.{$metaFieldPrefix}hwp_product_gtin");

            $filterSql .= $_sql;
            $selectColumns[] = "{$_sql} AS 'hwp_product_gtin'";
        }

        if ($params["_ywbc_barcode_display_value"] != 0) {
            $filterSql .= (strlen($filterSql)) ? " OR " : "";
            $types = "'product', 'product_variation'";
            $_sql = $this->sqlComp($params["_ywbc_barcode_display_value"], $types, $query, "P.{$metaFieldPrefix}_ywbc_barcode_display_value");

            $filterSql .= $_sql;
            $selectColumns[] = "{$_sql} AS '_ywbc_barcode_display_value'";
        }

        if ($params["hwp_var_gtin"] != 0) {
            $filterSql .= (strlen($filterSql)) ? " OR " : "";
            $types = "'product', 'product_variation'";
            $_sql = $this->sqlComp($params["hwp_var_gtin"], $types, $query, "P.{$metaFieldPrefix}hwp_product_gtin");

            $filterSql .= $_sql;
            $selectColumns[] = "{$_sql} AS 'hwp_var_gtin'";
        }

        if ($params["_wepos_barcode"] != 0) {
            $filterSql .= (strlen($filterSql)) ? " OR " : "";
            $types = "'product', 'product_variation'";
            $_sql = $this->sqlComp($params["_wepos_barcode"], $types, $query, "P.{$metaFieldPrefix}_wepos_barcode");

            $filterSql .= $_sql;
            $selectColumns[] = "{$_sql} AS '_wepos_barcode'";
        }

        if ($params["_ts_gtin"] != 0) {
            $filterSql .= (strlen($filterSql)) ? " OR " : "";
            $types = "'product', 'product_variation'";
            $_sql = $this->sqlComp($params["_ts_gtin"], $types, $query, "P.{$metaFieldPrefix}_ts_gtin");

            $filterSql .= $_sql;
            $selectColumns[] = "{$_sql} AS '_ts_gtin'";
        }

        if ($params["_ts_mpn"] != 0) {
            $filterSql .= (strlen($filterSql)) ? " OR " : "";
            $types = "'product', 'product_variation'";
            $_sql = $this->sqlComp($params["_ts_mpn"], $types, $query, "P.{$metaFieldPrefix}_ts_mpn");

            $filterSql .= $_sql;
            $selectColumns[] = "{$_sql} AS '_ts_mpn'";
        }

        if ($params["_zettle_barcode"] != 0) {
            $filterSql .= (strlen($filterSql)) ? " OR " : "";
            $types = "'product', 'product_variation'";
            $_sql = $this->sqlComp($params["_zettle_barcode"], $types, $query, "P.{$metaFieldPrefix}_zettle_barcode");

            $filterSql .= $_sql;
            $selectColumns[] = "{$_sql} AS '_zettle_barcode'";
        }

        if ($params["_order_number"] != 0) {
            $filterSql .= (strlen($filterSql)) ? " OR " : "";
            $types = "'shop_order'";
            $_sql = $this->sqlComp($params["_order_number"], $types, $query, "P.{$metaFieldPrefix}_order_number");
            $filterSql .= $_sql;
            $selectColumns[] = "{$_sql} AS '_order_number'";
        }

        if ($params["_billing_address_index"] != 0) {
            $filterSql .= (strlen($filterSql)) ? " OR " : "";
            $types = "'shop_order'";
            $_sql = $this->sqlComp($params["_billing_address_index"], $types, $query, "P.{$metaFieldPrefix}_billing_address_index");
            $filterSql .= $_sql;
            $selectColumns[] = "{$_sql} AS '_billing_address_index'";
        }

        if ($params["_shipping_address_index"] != 0) {
            $filterSql .= (strlen($filterSql)) ? " OR " : "";
            $types = "'shop_order'";
            $_sql = $this->sqlComp($params["_shipping_address_index"], $types, $query, "P.{$metaFieldPrefix}_shipping_address_index");
            $filterSql .= $_sql;
            $selectColumns[] = "{$_sql} AS '_shipping_address_index'";
        }

        if ($params["ywot_tracking_code"] != 0) {
            $filterSql .= (strlen($filterSql)) ? " OR " : "";
            $types = "'shop_order'";
            $_sql = $this->sqlComp($params["ywot_tracking_code"], $types, $query, "P.{$metaFieldPrefix}ywot_tracking_code");
            $filterSql .= $_sql;
            $selectColumns[] = "{$_sql} AS 'ywot_tracking_code'";
        }
        if ($params["_wc_shipment_tracking_items"] != 0) {
            $filterSql .= (strlen($filterSql)) ? " OR " : "";
            $types = "'shop_order'";
            $_sql = $this->sqlComp($params["_wc_shipment_tracking_items"], $types, $query, "P.{$metaFieldPrefix}_wc_shipment_tracking_items");
            $filterSql .= $_sql;
            $selectColumns[] = "{$_sql} AS '_wc_shipment_tracking_items'";
        }
        if ($params["_aftership_tracking_items"] != 0) {
            $filterSql .= (strlen($filterSql)) ? " OR " : "";
            $types = "'shop_order'";
            $_sql = $this->sqlComp($params["_aftership_tracking_items"], $types, $query, "P.{$metaFieldPrefix}_aftership_tracking_items");
            $filterSql .= $_sql;
            $selectColumns[] = "{$_sql} AS '_aftership_tracking_items'";
        }

        if ($params["atum_supplier_sku"] != 0) {
            $filterSql .= (strlen($filterSql)) ? " OR " : "";
            $types = "'product', 'product_variation'";
            $_sql = $this->sqlComp($params["atum_supplier_sku"], $types, $query, "P.atum_supplier_sku");

            $filterSql .= $_sql;
            $selectColumns[] = "{$_sql} AS 'atum_supplier_sku'";
        }

        if ($params["atum_barcode"] != 0) {
            $filterSql .= (strlen($filterSql)) ? " OR " : "";
            $types = "'product', 'product_variation'";
            $_sql = $this->sqlComp($params["atum_barcode"], $types, $query, "P.atum_barcode");

            $filterSql .= $_sql;
            $selectColumns[] = "{$_sql} AS 'atum_barcode'";
        }

        if ($params["usbs_barcode_field"] != 0) {
            $filterSql .= (strlen($filterSql)) ? " OR " : "";
            $types = "'product', 'product_variation'";
            $_sql = $this->sqlComp($params["usbs_barcode_field"], $types, $query, "P.usbs_barcode_field");

            $filterSql .= $_sql;
            $selectColumns[] = "{$_sql} AS 'usbs_barcode_field'";
        }

        foreach ($params as $_key => $_fieldName) {
            if (preg_match("/^custom-\d+$/", $_key, $m)) {
                $_status = isset($params[$m[0] . "-status"]) ? $params[$m[0] . "-status"] : 1;

                foreach ($tableColumns as $value) {
                    if ($value->name === $_fieldName) {
                        $field = $value->column;
                        $filterSql .= (strlen($filterSql)) ? " OR " : "";
                        $_sql = $this->sqlComp($_status, $types, $query, "P.{$field}");

                        $filterSql .= $_sql;
                        $selectColumns[] = "{$_sql} AS '{$field}'";
                        break;
                    }
                }
            }
        }

        if ($params["client_name"] != 0) {
            $filterSql .= (strlen($filterSql)) ? " OR " : "";
            $types = "'shop_order'";
            $_sql = $this->sqlComp($params["client_name"], $types, $query, "P.client_name");

            $filterSql .= $_sql;
            $selectColumns[] = "{$_sql} AS 'client_name'";
        }

        if ($params["client_email"] != 0) {
            $filterSql .= (strlen($filterSql)) ? " OR " : "";
            $types = "'shop_order'";
            $_sql = $this->sqlComp($params["client_email"], $types, $query, "P.client_email");

            $filterSql .= $_sql;
            $selectColumns[] = "{$_sql} AS 'client_email'";
        }

        $sql .= ($filterSql) ? $filterSql : " 1 != 1 ";

        $sql .= " ) GROUP BY P.ID LIMIT {$this->limit} ";

        if ($selectColumns) {
            $sql = str_replace("%SELECT_COLUMNS%", ", " . implode(", ", $selectColumns), $sql);
        } else {
            $sql = str_replace("%SELECT_COLUMNS%", " ", $sql);
        }

        return $sql;
    }

    private function sqlComp($status, $types, $query, $field)
    {
        $like = str_replace("\'", "%", $query);

        return $status == 1
            ? " ( P.post_type IN( {$types} ) AND {$field} = '{$query}' ) "
            : " ( P.post_type IN( {$types} ) AND {$field} LIKE '%{$like}%' ) ";
    }

    private function sqlMetaComp($status, $types, $query, $field)
    {
        $like = str_replace("\'", "%", $query);

        if ($types) {
            return $status == 1
                ? " ( P.post_type IN( {$types} ) AND PM.meta_key = '{$field}' AND PM.meta_value = '{$query}' ) "
                : " ( P.post_type IN( {$types} ) AND PM.meta_key = '{$field}' AND PM.meta_value LIKE '%{$like}%' ) ";
        } else {
            return $status == 1
                ? " ( PM.meta_key = '{$field}' AND PM.meta_value = '{$query}' ) "
                : " ( PM.meta_key = '{$field}' AND PM.meta_value LIKE '%{$like}%' ) ";
        }
    }

    public function getFilterParams($filter)
    {
        $isAtum = is_plugin_active('atum-stock-manager-for-woocommerce/atum-stock-manager-for-woocommerce.php');

        $params = array(
            "postID" => "1",
            "post_title" => "2",
            "post_excerpt" => "0",
            "_sku" => "1",
            "productCF" => "",
            "statusProductCF" => "0",
            "orderCF" => "",
            "statusOrderCF" => "0",
            "_variation_description" => "0",
            "prod_link" => "1",
            "_alg_ean" => function_exists('alg_wc_ean') ? "1" : "0",
            "_wpm_gtin_code" => function_exists('wpm_product_gtin_wc') ? "1" : "0",
            "hwp_product_gtin" => class_exists('Woo_GTIN') ? "1" : "0",
            "_ywbc_barcode_display_value" => PluginsHelper::is_plugin_active('yith-woocommerce-barcodes-premium/init.php') ? "1" : "0",
            "hwp_var_gtin" => class_exists('Woo_GTIN') ? "1" : "0",
            "_wepos_barcode" => is_plugin_active('wepos/wepos.php') ? "1" : "0",
            "_ts_gtin" => is_plugin_active('woocommerce-germanized/woocommerce-germanized.php') ? "1" : "0",
            "_ts_mpn" => is_plugin_active('woocommerce-germanized/woocommerce-germanized.php') ? "1" : "0",
            "_zettle_barcode" => is_plugin_active('zettle-pos-integration/zettle-pos-integration.php') ? "1" : "0",
            "atum_supplier_sku" => $isAtum ? "1" : "0",
            "atum_barcode" => $isAtum ? "1" : "0",
            "post_types" => array(),
            "usbs_barcode_field" => "0",
            "client_name" => "1",
            "client_email" => "1",
            "_order_number" => defined('WT_SEQUENCIAL_ORDNUMBER_VERSION') || is_plugin_active("woocommerce-sequential-order-numbers-pro/woocommerce-sequential-order-numbers-pro.php") ? "1" : "0",
            "_billing_address_index" => "2",
            "_shipping_address_index" => "2",
            "ywot_tracking_code" => PluginsHelper::is_plugin_active('yith-woocommerce-order-tracking/init.php') ? "2" : "0",
            "_wc_shipment_tracking_items" => PluginsHelper::is_plugin_active('woo-advanced-shipment-tracking/woocommerce-advanced-shipment-tracking.php') ? "2" : "0",
            "_aftership_tracking_items" => PluginsHelper::is_plugin_active('aftership-woocommerce-tracking/aftership-woocommerce-tracking.php') ? "2" : "0",
            "post_author" => "0",
        );

        if (!$filter || (!$filter["products"] && !$filter["orders"])) {
            if ($this->filterExcludes && is_array($this->filterExcludes) && count($this->filterExcludes) > 0) {
                if (in_array("products", $this->filterExcludes)) {
                    $params["post_types"] = array("shop_order");
                }
                else if (in_array("orders", $this->filterExcludes)) {
                    $params["post_types"] = array("product", "product_variation");
                }
            }

            return $params;
        }

        $this->disableFilterParams($params);

        if ($this->filterExcludes && is_array($this->filterExcludes) && count($this->filterExcludes) > 0) {
            if (in_array("products", $this->filterExcludes)) {
                $this->searchType = "order";
            }
            else if (in_array("orders", $this->filterExcludes)) {
                $this->searchType = "product";
            }
        }

        if ($this->searchType !== "order" && $filter["products"] && is_array($filter["products"])) {
            $this->addPostType($params, "product");
            $this->addPostType($params, "product_variation");

            foreach ($filter["products"] as $key => $value) {
                if ($key == 'ID' && $value != "0") {
                    $params["postID"] = $filter["products"]["ID"];
                } else if ($key == 'customStatus' && $value && isset($filter["products"]["custom"]) && $filter["products"]["custom"]) {
                    $params["productCF"] = trim($filter["products"]["custom"]);
                    $params["statusProductCF"] = $value;
                } else if ($key == 'hwp_product_gtin') {
                    $params["hwp_product_gtin"] = $filter["products"]["hwp_product_gtin"];
                    $params["hwp_var_gtin"] = $filter["products"]["hwp_product_gtin"];
                } else {
                    $params[$key] = $value;
                }
            }
        }

        if ($this->searchType !== "product" && isset($filter["orders"]) && $filter["orders"] && is_array($filter["orders"])) {
            $this->addPostType($params, "shop_order");

            foreach ($filter["orders"] as $key => $value) {
                if ($key == 'ID' && $value != "0") {
                    $params["postID"] = (int)$filter["orders"]["ID"];
                } else if ($key == 'customStatus' && $value && isset($filter["orders"]["custom"]) && $filter["orders"]["custom"]) {
                    $params["orderCF"] = trim($filter["orders"]["custom"]);
                    $params["statusOrderCF"] = $value;
                } else {
                    $params[$key] = $value;
                }
            }
        }

        if ($this->onlyById) {
            $this->filterById($params);
        }

        return $params;
    }

    private function filterById(&$params)
    {
        foreach ($params as $key => &$value) {
            if (in_array($key, array('post_author'))) continue;

            switch ($key) {
                case 'postID':
                    $value = 1;
                    break;
                case 'post_types':
                case 'custom':
                    break;
                default:
                    $value = 0;
                    break;
            }
        }
    }

    private function addPostType(&$params, $type)
    {
        if (!$params["post_types"]) {
            $params["post_types"] = array();
        }

        if (!in_array($type, $params["post_types"])) {
            $params["post_types"][] = $type;
        }
    }

    private function disableFilterParams(&$params)
    {
        foreach ($params as $key => &$value) {
            $value = $key == "post_types" ? array() : "0";
        }
    }

    private function isUrl($query)
    {
        try {
            return preg_match("/.*?post=([\d]+).*?/", $query, $m) || (function_exists("wp_http_validate_url") && \wp_http_validate_url($query)) ? true : false;
        } catch (\Throwable $th) {
            return false;
        }
    }

    private function checkUrlInQuery($query, $filter)
    {
        try {
            if (!$filter || !isset($filter["products"])) return $query;

            if (!isset($filter["products"]["prod_link"]) || $filter["products"]["prod_link"] != "1") return $query;

            $id = $this->isUrl($query) ? url_to_postid($query) : "";
            $id = $this->tryToGetVariationId($id, $query);

            if ($id) {
                return $id;
            } else if (preg_match("/.*?post=([\d]+).*?/", $query, $m)) {
                if (count($m) == 2 && is_numeric($m[1])) {
                    return $m[1];
                } else {
                    return $query;
                }
            } else {
                return $query;
            }
        } catch (\Throwable $th) {
            return $query;
        }
    }

    private function tryToGetVariationId($id, $url)
    {
        try {
            if (!$id || !$url) return $id;

            $parsedUrl = wp_parse_url($url);

            if (!$parsedUrl || !isset($parsedUrl["query"]) || !$parsedUrl["query"]) return $id;

            $params = explode("&", $parsedUrl["query"]);
            $urlAttrs = array();

            foreach ($params as $value) {
                $param = explode("=", $value);

                if ($param && count($param) == 2) {
                    $urlAttrs[$param[0]] = trim($param[1]);
                }
            }

            if (!$urlAttrs || count($urlAttrs) < 1) return $id;

            $product = \wc_get_product($id);
            if (!$product || !$product->is_type('variable')) return $id;

            $variations = $product->get_available_variations();

            foreach ($variations as $variation) {
                $attributes = $variation['attributes'];
                $counter = 0;

                foreach ($attributes as $key => $value) {
                    if (isset($urlAttrs[$key]) && ($urlAttrs[$key] == $value || $value == "")) {
                        $counter++;
                    }
                }

                if ($counter == count($attributes)) return $variation['variation_id'];
            }

            return $id;
        } catch (\Throwable $th) {
            return $id;
        }
    }
}
