<?php

namespace UkrSolution\BarcodeScanner\API\routes;

use UkrSolution\BarcodeScanner\API\actions\DbActions;
use UkrSolution\BarcodeScanner\API\Routes;

class DB extends Routes
{
    public function __construct()
    {
        $actions = new DbActions();

        register_rest_route('scanner/v1', '/db/create-column', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permissionCallback'),
            'args' => array(),
            'callback' => array($actions, 'createColumn'),
        ));

        register_rest_route('scanner/v1', '/db/posts-initialization', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permissionCallback'),
            'args' => array(),
            'callback' => array($actions, 'postsInitialization'),
        ));
    }
}
