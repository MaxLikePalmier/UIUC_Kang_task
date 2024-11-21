<?php

namespace UkrSolution\BarcodeScanner\API\routes;

use UkrSolution\BarcodeScanner\API\actions\ManagementActions;
use UkrSolution\BarcodeScanner\API\Routes;

class Management extends Routes
{
    public function __construct()
    {
        $actions = new ManagementActions();

        register_rest_route('scanner/v1', '/product/search/(?P<query>.+)', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permissionCallback'),
            'args' => array(
                'query' => $this->getArgSearchQuery(),
            ),
            'callback' => array($actions, 'productSearch'),
        ));

        register_rest_route('scanner/v1', '/product/enable-manage-stock/(?P<query>.+)', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permissionCallback'),
            'args' => array(
                'query' => $this->getArgProductId(),
            ),
            'callback' => array($actions, 'productEnableManageStock'),
        ));

        register_rest_route('scanner/v1', '/product/update-quantity/(?P<query>.+)/(?P<quantity>.+)', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permissionCallback'),
            'args' => array(
                'query' => $this->getArgProductId(),
                'quantity' => array(
                    'default' => 0,
                    'required' => true,
                    'sanitize_callback' => function ($param) {
                        return (int)$param;
                    },
                )
            ),
            'callback' => array($actions, 'productUpdateQuantity'),
        ));

        register_rest_route('scanner/v1', '/product/update-quantity-plus/(?P<query>.+)', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permissionCallback'),
            'args' => array(
                'query' => $this->getArgProductId()
            ),
            'callback' => array($actions, 'productUpdateQuantityPlus'),
        ));

        register_rest_route('scanner/v1', '/product/update-quantity-minus/(?P<query>.+)', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permissionCallback'),
            'args' => array(
                'query' => $this->getArgProductId()
            ),
            'callback' => array($actions, 'productUpdateQuantityMinus'),
        ));

        register_rest_route('scanner/v1', '/product/update-regular-price/(?P<query>.+)/(?P<price>.+)', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permissionCallback'),
            'args' => array(
                'query' => $this->getArgProductId(),
                'price' => array('default' => 0, 'required' => true)
            ),
            'callback' => array($actions, 'productUpdateRegularPrice'),
        ));

        register_rest_route('scanner/v1', '/product/update-sale-price/(?P<query>.+)/(?P<price>.+)', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permissionCallback'),
            'args' => array(
                'query' => $this->getArgProductId(),
                'price' => array('default' => "")
            ),
            'callback' => array($actions, 'productUpdateSalePrice'),
        ));

        register_rest_route('scanner/v1', '/product/update-meta', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permissionCallback'),
            'args' => array(),
            'callback' => array($actions, 'productUpdateMeta'),
        ));

        register_rest_route('scanner/v1', '/product/update-title/(?P<query>.+)', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permissionCallback'),
            'args' => array(
                'query' => $this->getArgProductId()
            ),
            'callback' => array($actions, 'productUpdateTitle'),
        ));

        register_rest_route('scanner/v1', '/product/set-image/(?P<postId>.+)/(?P<attachmentId>.+)', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permissionCallback'),
            'args' => array(
                'postId' => array('default' => ""),
                'attachmentId' => array('default' => "")
            ),
            'callback' => array($actions, 'productSetImage'),
        ));

        register_rest_route('scanner/v1', '/product/create-new', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permissionCallback'),
            'args' => array(),
            'callback' => array($actions, 'productCreateNew'),
        ));

        register_rest_route('scanner/v1', '/product/update-fields', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permissionCallback'),
            'args' => array(),
            'callback' => array($actions, 'productUpdateFields'),
        ));

        register_rest_route('scanner/v1', '/order/search/(?P<query>.+)', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permissionCallback'),
            'args' => array(
                'query' => $this->getArgSearchQuery(),
            ),
            'callback' => array($actions, 'orderSearch'),
        ));

        register_rest_route('scanner/v1', '/order/change-status/(?P<orderId>.+)/(?P<status>.+)', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permissionCallback'),
            'args' => array(
                'orderId' => array(
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return $param > 0;
                    },
                    'sanitize_callback' => function ($param) {
                        return (int)$param;
                    },
                ),
                'status' => array(
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return strlen($param) > 0;
                    },
                )
            ),
            'callback' => array($actions, 'orderChangeStatus'),
        ));
    }
}
