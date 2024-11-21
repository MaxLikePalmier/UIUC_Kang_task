<?php

namespace UkrSolution\BarcodeScanner\API;

use UkrSolution\BarcodeScanner\API\classes\Auth;
use UkrSolution\BarcodeScanner\API\routes\Cart;
use UkrSolution\BarcodeScanner\API\routes\DB;
use UkrSolution\BarcodeScanner\API\routes\Management;
use UkrSolution\BarcodeScanner\API\routes\Post;
use UkrSolution\BarcodeScanner\API\routes\Users;
use WP_REST_Request;


class Routes
{
    public function __construct()
    {
    }

    public function registerRoutes()
    {
        new Cart();
        new Post();
        new Management();
        new Users();
        new DB();
    }

    public function getArgSearchQuery()
    {
        return array(
            'default' => "",
            'required' => true,
            'validate_callback' => function ($param) {
                return strlen($param) > 0;
            },
            'sanitize_callback' => function ($param) {
                return trim($param);
            },
        );
    }

    public function getArgProductId()
    {
        return array(
            'default' => "",
            'required' => true,
            'validate_callback' => function ($param) {
                return $param > 0;
            },
            'sanitize_callback' => function ($param) {
                return (int)$param;
            },
        );
    }

    public function permissionCallback(WP_REST_Request $request)
    {
        $token = $request->get_param("token");
        $userToken = $request->get_param("userToken");
        $userSession = $request->get_param("userSession");

        $auth = new Auth();

        return $auth->check($token, $userToken, $userSession) ? true : false;
    }

    public function getUserId(WP_REST_Request $request)
    {
        $token = $request->get_param("token");
        $userToken = $request->get_param("userToken");
        $userSession = $request->get_param("userSession");
        $auth = new Auth();

        return $auth->getUserId($token, $userToken, $userSession);
    }
}
