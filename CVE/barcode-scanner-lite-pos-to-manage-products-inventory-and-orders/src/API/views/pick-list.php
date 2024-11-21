<?php
$customLogoId = get_theme_mod('custom_logo');
$logoUrl = "";
$logo = wp_get_attachment_image_src($customLogoId, 'medium');

if ($logo && count($logo)) $logoUrl = $logo[0];

$totalQuantity = 0;
?>
<div style="padding: 0; margin: 0;">
    <!-- header -->
    <div style="display: flex; padding-bottom: 16px">
        <!-- logo -->
        <?php if ($logoUrl) : ?>
            <div style="padding-right: 22px;"><img src="<?php echo esc_url($logoUrl); ?>" style="max-width: 160px; max-height: 48px;" /></div>
        <?php endif; ?>
        <!-- order data  -->
        <div>
            <div style="font-size: 20px;"><?php echo esc_html__("Order", "us-barcode-scanner"); ?>: #<?php echo esc_html($post->ID); ?></div>
            <div style="font-size: 13px;"><?php echo $order ? esc_html(date('d F Y', strtotime($order->get_date_created()))) : ""; ?></div>
        </div>
        <!-- barcode -->
        <div style="margin-left: auto;"></div>
    </div>
    <!-- list -->
    <table style="width: 100%; font-size: 13px;">
        <tr>
            <td colspan="2"><?php esc_html__("Products to pick:", "us-barcode-scanner"); ?></td>
            <td width="36" style="text-align: right;"><?php esc_html__("Qty", "us-barcode-scanner"); ?></td>
            <td width="24" style="text-align: right;">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <g clip-path="url(#clip0_467_2423)">
                        <path d="M6 10.7999L3.66666 8.46655C3.40666 8.20655 2.99333 8.20655 2.73333 8.46655C2.47333 8.72655 2.47333 9.13988 2.73333 9.39988L5.52666 12.1932C5.78666 12.4532 6.20666 12.4532 6.46666 12.1932L13.5333 5.13322C13.7933 4.87322 13.7933 4.45988 13.5333 4.19988C13.2733 3.93988 12.86 3.93988 12.6 4.19988L6 10.7999Z" fill="black" />
                    </g>
                    <defs>
                        <clipPath id="clip0_467_2423">
                            <rect width="16" height="16" fill="white" />
                        </clipPath>
                    </defs>
                </svg>
            </td>
        </tr>
        <tr>
            <td colspan="4">
                <div style=" height: 1px; border-bottom: 1px solid #D9D9D9; margin: 6px 0;"></div>
            </td>
        </tr>
        <?php
        $productNumber = 1;
        ?>
        <?php foreach ($order->get_items("line_item") as $item) : ?>
            <?php
            $productId = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();
            $product = wc_get_product($productId);
            $imageId = get_post_thumbnail_id($productId);
            $imageUrl = '';

            if ($imageId) {
                $imageUrl = wp_get_attachment_url($imageId);
            } else if ($product && $product->get_parent_id()) {
                $imageId = get_post_thumbnail_id($product->get_parent_id());
                $imageUrl = $imageId ? wp_get_attachment_url($imageId) : "";
            }

            $productAttributes = $item->get_formatted_meta_data('_product_attributes');

            $totalQuantity += $item->get_quantity();

            $location = get_post_meta($productId, 'usbs_stock_location_level_1', true);
            $location .= " " . get_post_meta($productId, 'usbs_stock_location_level_2', true);
            $location .= " " . get_post_meta($productId, 'usbs_stock_location_level_3', true);
            ?>
            <tr style="margin-top: 2px; margin-bottom: 2px;">
                <td width="80" style="padding-right: 8px; text-align: center; vertical-align: center;">
                    <?php if ($imageUrl) : ?>
                        <img src="<?php echo esc_url($imageUrl); ?>" style="max-width: 80px; max-height: 80px;" />
                    <?php endif; ?>
                </td>
                <td style="vertical-align: top;">
                    <div>
                        <?php echo esc_html($productNumber++); ?>. <?php echo $product ? esc_html($product->get_name()) : ""; ?>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <div><i>
                                <?php
                                $attributes = $product ? $product->get_attributes() : null;
                                $variation_names = array();
                                if ($attributes) {
                                    foreach ($attributes as $key => $value) {
                                        if (gettype($value) === "string") {
                                            $variation_key =  end(explode('-', $key));
                                            $variation_key = str_replace("pa_", "", $variation_key);
                                            $attrValue = trim($value);
                                            $attrName = ucfirst($variation_key);
                                            if ($attrValue) $variation_names[] = trim($attrName) . ': ' . $attrValue;
                                        }
                                    }
                                }

                                if ($variation_names) echo wp_kses_post(implode(", ", $variation_names));
                                ?>
                            </i></div>
                        <div style="text-align: right;"><?php echo $location ? esc_html(trim($location)) : ''; ?></div>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <!-- barcode -->
                        <div></div>
                        <!-- code -->
                        <div style="text-align: right;"><?php echo $pickListProductCode ? esc_html(get_post_meta($productId, $pickListProductCode, true)) : ''; ?></div>
                    </div>
                </td>
                <td style="font-size: 14px; text-align: right;"><?php echo esc_html($item->get_quantity()); ?></td>
                <td style="text-align: right;">
                    <svg width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3.33333 14.5C2.96667 14.5 2.65278 14.3694 2.39167 14.1083C2.13056 13.8472 2 13.5333 2 13.1667V3.83333C2 3.46667 2.13056 3.15278 2.39167 2.89167C2.65278 2.63056 2.96667 2.5 3.33333 2.5H12.6667C13.0333 2.5 13.3472 2.63056 13.6083 2.89167C13.8694 3.15278 14 3.46667 14 3.83333V13.1667C14 13.5333 13.8694 13.8472 13.6083 14.1083C13.3472 14.3694 13.0333 14.5 12.6667 14.5H3.33333ZM3.33333 13.1667H12.6667V3.83333H3.33333V13.1667Z" fill="black" />
                    </svg>
                </td>
            </tr>
            <tr>
                <td colspan="4">
                    <div style=" height: 1px; border-bottom: 1px solid #D9D9D9; margin: 6px 0;"></div>
                </td>
            </tr>
        <?php endforeach; ?>
        <tr style="font-size: 16px;">
            <td colspan="3" style="text-align: right;">
                <?php esc_html__("Total items to pick:", "us-barcode-scanner"); ?> <b><?php echo esc_html($totalQuantity); ?></b>
            </td>
            <td style="padding-top: 2px; text-align: right;">
                <svg width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M3.33333 14.5C2.96667 14.5 2.65278 14.3694 2.39167 14.1083C2.13056 13.8472 2 13.5333 2 13.1667V3.83333C2 3.46667 2.13056 3.15278 2.39167 2.89167C2.65278 2.63056 2.96667 2.5 3.33333 2.5H12.6667C13.0333 2.5 13.3472 2.63056 13.6083 2.89167C13.8694 3.15278 14 3.46667 14 3.83333V13.1667C14 13.5333 13.8694 13.8472 13.6083 14.1083C13.3472 14.3694 13.0333 14.5 12.6667 14.5H3.33333ZM3.33333 13.1667H12.6667V3.83333H3.33333V13.1667Z" fill="black" />
                </svg>
            </td>
        </tr>
    </table>
</div>