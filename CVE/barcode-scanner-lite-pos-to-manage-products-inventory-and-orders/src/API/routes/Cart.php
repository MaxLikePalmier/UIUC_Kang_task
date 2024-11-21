<?php

namespace UkrSolution\BarcodeScanner\API\routes;

use UkrSolution\BarcodeScanner\API\actions\CartScannerActions;
use UkrSolution\BarcodeScanner\API\Routes;

class Cart extends Routes
{
    public function __construct()
    {
        $actions = new CartScannerActions();

        register_rest_route('scanner/v1', '/cart/add/(?P<query>.+)/(?P<orderId>.+)', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permissionCallback'),
            'args' => array(
                'query' => $this->getArgSearchQuery(),
                'orderId' => array(
                    'default' => null,
                    'required' => false,
                    'sanitize_callback' => function ($param) {
                        return (int) $param;
                    },
                )
            ),
            'callback' => array($actions, 'addItem'),
        ));

        register_rest_route('scanner/v1', '/cart/remove/(?P<cartItem>.+)/(?P<orderId>.+)', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permissionCallback'),
            'args' => array(
                'cartItem' => array(
                    'required' => true
                ),
                'orderId' => array(
                    'default' => null,
                    'required' => false,
                    'sanitize_callback' => function ($param) {
                        return (int) $param;
                    },
                )
            ),
            'callback' => array($actions, 'removeItem'),
        ));

        register_rest_route('scanner/v1', '/cart/update-quantity', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permissionCallback'),
            'callback' => array($actions, 'updateQuantity'),
        ));

        register_rest_route('scanner/v1', '/cart/update-attributes', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permissionCallback'),
            'callback' => array($actions, 'updateAttributes'),
        ));

        register_rest_route('scanner/v1', '/cart/get-statuses', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permissionCallback'),
            'callback' => array($actions, 'getStatuses'),
        ));

        register_rest_route('scanner/v1', '/cart/order/create/(?P<orderId>.+)', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permissionCallback'),
            'args' => array(
                'orderId' => array(
                    'default' => null,
                    'required' => false,
                    'sanitize_callback' => function ($param) {
                        return (int) $param;
                    },
                )
            ),
            'callback' => array($actions, 'orderCreate'),
        ));

        register_rest_route('scanner/v1', '/cart/order/clear/(?P<orderId>.+)', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permissionCallback'),
            'args' => array(
                'orderId' => array(
                    'default' => null,
                    'required' => false,
                    'sanitize_callback' => function ($param) {
                        return (int) $param;
                    },
                )
            ),
            'callback' => array($actions, 'cartClear'),
        ));

        register_rest_route('scanner/v1', '/cart/recalculate', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permissionCallback'),
            'args' => array(),
            'callback' => array($actions, 'cartRecalculate'),
        ));
    }
}
