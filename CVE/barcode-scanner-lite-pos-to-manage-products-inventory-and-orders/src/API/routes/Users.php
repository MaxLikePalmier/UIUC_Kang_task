<?php

namespace UkrSolution\BarcodeScanner\API\routes;

use UkrSolution\BarcodeScanner\API\actions\UsersActions;
use UkrSolution\BarcodeScanner\API\Routes;

class Users extends Routes
{
    public function __construct()
    {
        $actions = new UsersActions();

        register_rest_route('scanner/v1', '/users/find', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permissionCallback'),
            'callback' => array($actions, 'find'),
        ));

        register_rest_route('scanner/v1', '/user/create', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permissionCallback'),
            'callback' => array($actions, 'createUser'),
        ));
    }
}
