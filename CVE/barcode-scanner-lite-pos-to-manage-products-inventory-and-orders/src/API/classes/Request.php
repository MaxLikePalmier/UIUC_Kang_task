<?php

namespace UkrSolution\BarcodeScanner\API\classes;

use WP_REST_Request;

class Request
{
    private $requestsIdsKey = "requestsIds";
    public function validate(WP_REST_Request $request)
    {
        @session_start();

        $id = $request->get_param("id");

        if (!$id) {
            return true;
        }

        $ids = (isset($_SESSION[$this->requestsIdsKey]) && $_SESSION[$this->requestsIdsKey]) ? $_SESSION[$this->requestsIdsKey] : array();

        if (in_array($id, $ids)) {
            return rest_ensure_response(array(
                "id" => $id,
                "repeatRequest" => true,
            ));
        } else {
            $ids[] = $id;
            $slice = count($ids) > 100 ? count($ids) - 100 : 0;
            $ids = array_slice($ids, $slice);
            $_SESSION[$this->requestsIdsKey] = $ids;
        }

        return true;
    }
}
