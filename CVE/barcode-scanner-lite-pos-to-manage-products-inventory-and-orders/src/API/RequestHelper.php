<?php

namespace UkrSolution\BarcodeScanner\API;

use WP_REST_Request;

class RequestHelper
{
    public static $scanner_search_query = 'barcode_scanner_search_query';

    public static function getQuery(WP_REST_Request $request, $type = "")
    {
        $query = $request->get_param("query");

        $query = apply_filters(self::$scanner_search_query, $query, $type);

        return trim($query);
    }
}
