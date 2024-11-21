<?php

class Coupons
{
    public function createCoupon()
    {
        $couponCode = 'BARCODE_SCANNER';
        $amount = '10';
        $discountType = 'fixed_cart';

        $coupon = array('post_title' => $couponCode, 'post_content' => '', 'post_status' => 'publish', 'post_author' => 1, 'post_type' => 'shop_coupon');

        $new_coupon_id = wp_insert_post($coupon);

        update_post_meta($new_coupon_id, 'discount_type', $discountType);
        update_post_meta($new_coupon_id, 'coupon_amount', $amount);
        update_post_meta($new_coupon_id, 'individual_use', 'no');
        update_post_meta($new_coupon_id, 'product_ids', '');
        update_post_meta($new_coupon_id, 'exclude_product_ids', '');
        update_post_meta($new_coupon_id, 'usage_limit', '');
        update_post_meta($new_coupon_id, 'expiry_date', '');
        update_post_meta($new_coupon_id, 'apply_before_tax', 'yes');
        update_post_meta($new_coupon_id, 'free_shipping', 'no');

        return $couponCode;
    }
}
