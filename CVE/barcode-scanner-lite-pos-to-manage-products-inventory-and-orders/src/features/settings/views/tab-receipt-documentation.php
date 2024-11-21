<?php
$tabs = array(
    'store-tab' => array(
        'name' => __('Store tab', "us-barcode-scanner"),
        'shortcodes' => array(
            __('Name of the store', "us-barcode-scanner") => '[store-name]',
            __('Postcode of the store', "us-barcode-scanner") => '[store-postcode]',
            __('Address of the store', "us-barcode-scanner") => '[store-address]',
            __('Address 2 of the store', "us-barcode-scanner") => '[store-address-2]',
            __('Country of the store', "us-barcode-scanner") => '[store-country]',
            __('State of the store', "us-barcode-scanner") => '[store-state]',
            __('City of the store', "us-barcode-scanner") => '[store-city]',
        ),
        'blocks' => array()
    ),
    'products-tab' => array(
        'name' => __('Products tab', "us-barcode-scanner"),
        'shortcodes' => array(
            __('Product name', "us-barcode-scanner") => '[product-name]',
            __('Product SKU', "us-barcode-scanner") => '[product-sku]',
            __('Purchased product quantity', "us-barcode-scanner") => '[order-product-qty]',
            __('Product price for 1 item', "us-barcode-scanner") => '[item-price]',
            __('Product price for 1 item with tax', "us-barcode-scanner") => '[item-price+tax]',
            __('Product tax for 1 item', "us-barcode-scanner") => '[item-tax]',
            __('Product price for all the same items', "us-barcode-scanner") => '[item-price-total]',
            __('Product price for all the same items', "us-barcode-scanner") => '[item-price+tax-total]',
            __('Product tax for all the same items', "us-barcode-scanner") => '[item-tax-total]',
            __('Pull data from the product custom/meta field', "us-barcode-scanner") => '[custom-field=XXXX]',
            __('Pull data from the main product custom/meta field', "us-barcode-scanner") => '[custom-field-parent=XXXX]',
        ),
        'blocks' => array(
            array(
                'name' => __('List of the purchased items:', "us-barcode-scanner"),
                'text' => "<table style='width: 100%;font-size: 12px'>
                [product-list-start test-products=10]
                    <tr>
                        <td style='width: 100%'>[product-name] | [product-sku]</td>
                        <td style='padding-right: 1mm'>[order-product-qty] x [item-price]</td>
                        <td>[item-price-total]</td>
                    </tr>
                [product-list-end]
                </table>"
            )
        ),
    ),
    'order-tab' => array(
        'name' => __('Order tab', "us-barcode-scanner"),
        'shortcodes' => array(
            __('Order Id', "us-barcode-scanner") => '[order-id]',
            __('Order shipping price', "us-barcode-scanner") => '[order-shipping]',
            __('Order shipping tax', "us-barcode-scanner") => '[order-shipping-tax]',
            __('Order subtotal price', "us-barcode-scanner") => '[order-subtotal]',
            __('Order subtotal tax', "us-barcode-scanner") => '[order-subtotal-tax]',
            __('Order tax', "us-barcode-scanner") => '[order-tax]',
            __('Order total price', "us-barcode-scanner") => '[order-total]',
            __('Order date', "us-barcode-scanner") => '[order-date]',
            __('Pull data from the order custom/meta field', "us-barcode-scanner") => '[custom-field=XXXX]',
        ),
        'blocks' => array(
            array(
                'name' => __('List of the applied taxes:', "us-barcode-scanner"),
                'text' => "<table style='width: 100%;font-size: 12px'>
                [order-taxes-list-start test-taxes=3]
                    <tr>
                        <td>[tax-label]</td>
                        <td style='text-align: right'>[tax-cost]</td>
                    </tr>
                [order-taxes-list-end]
                </table>"
            )
        ),
    ),
)
?>
<div class="receipt-documentation-modal" data-rdm-wrapper="1" style="display: none;">
    <div class="rdm-content">
        <div class="rdm-header">
            <div></div>
            <span class="rdm-close">
                <svg class="MuiSvgIcon-root" focusable="false" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"></path>
                </svg>
            </span>
        </div>
        <div class="rdm-body">
            <div class="rdm-tabs">
                <?php foreach ($tabs as $key => $tabValue) : ?>
                    <div data-rdm-tab="<?php echo esc_attr($key); ?>" class="<?php echo $key === "store-tab" ? esc_html('active') : '' ?>"><?php echo esc_html($tabValue['name']); ?></div>
                <?php endforeach; ?>
            </div>
            <div class="rdm-tabs-content">
                <?php foreach ($tabs as $key => $tabValue) : ?>
                    <div data-rdm-tab="<?php echo esc_attr($key); ?>" class="<?php echo $key === "store-tab" ? esc_html('active') : '' ?>">
                        <?php foreach ($tabValue['shortcodes'] as $shortcode => $label) : ?>
                            <div>
                                <div><?php echo esc_html($shortcode) ?></div>
                                <div><?php echo esc_html($label) ?></div>
                            </div>
                        <?php endforeach; ?>
                        <?php foreach ($tabValue['blocks'] as $blockValue) : ?>
                            <div>
                                <div><?php echo esc_html($blockValue['name']) ?></div>
                                <div><?php echo str_replace("\n", "<br/>", esc_html($blockValue['text'])) ?></div>
                            </div>
                        <?php endforeach; ?>

                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<style>
    .receipt-documentation-modal {
        display: none;
        position: fixed;
        width: 100vw;
        height: 100vh;
        background: #00000024;
        top: 0;
        left: 0;
        z-index: 999999999;
        align-items: center;
        justify-content: center;
    }

    .receipt-documentation-modal.show {
        display: flex;
    }

    .receipt-documentation-modal .rdm-content {
        background: #fff;
        position: relative;
        padding: 15px 20px;
        margin: 15px;
        border-radius: 4px;
        width: 100%;
        min-width: 400px;
        max-width: 700px;
    }

    .rdm-header {
        display: flex;
        justify-content: space-between;
    }

    .rdm-body {
        display: flex;
        flex-direction: column;
    }

    .rdm-close {
        display: inline-block;
        width: 20px;
        height: 20px;
        cursor: pointer;
        position: absolute;
        top: 5px;
        right: 5px;
    }

    .rdm-tabs {
        display: flex;
        align-items: center;
        border-bottom: 1px solid #e7e7e7;
    }

    .rdm-tabs>div {
        background: #dcdcde;
        cursor: pointer;
        padding: 5px 10px;
        margin-right: 2px;
    }

    .rdm-tabs>div.active {
        background: #f0f0f1;
    }

    .rdm-tabs-content>div {
        display: none;
    }

    .rdm-tabs-content div[data-rdm-tab] {
        display: none;
        flex-direction: column;
    }

    .rdm-tabs-content div[data-rdm-tab].active {
        display: flex;
    }

    .rdm-tabs-content div[data-rdm-tab]>div {
        display: flex;
        align-items: flex-start;
        justify-content: flex-start;
        padding: 2px 5px;
    }

    .rdm-tabs-content div[data-rdm-tab]>div>div:first-child {
        width: 45%;
        min-width: 45%;
    }

    .rdm-block {
        display: flex;
    }
</style>
<script>
    let rdmStatus = false;
    let rdmActiveTab = 'store-tab';

    const modalToggle = (e) => {
        if (e) e.preventDefault();

        jQuery('.receipt-documentation-modal').removeAttr("style");

        if (rdmStatus) {
            jQuery('.receipt-documentation-modal').removeClass("show");
            jQuery('body').css("overflow-y", 'initial');
        } else {
            jQuery('.receipt-documentation-modal').addClass("show");
            jQuery('body').css("overflow-y", 'hidden');
        }

        rdmStatus = !rdmStatus;
    }

    jQuery("#receipt-documentation-toggle").click(modalToggle);
    jQuery(".rdm-close").click(modalToggle);

    jQuery(".receipt-documentation-modal").click((e => {
        if (jQuery(e.target).attr('data-rdm-wrapper')) modalToggle();
    }));

    jQuery(".rdm-tabs>div").click((e) => {
        e.preventDefault();

        const tab = jQuery(e.target).attr('data-rdm-tab');
        rdmActiveTab = tab;

        jQuery(".rdm-tabs>div").removeClass('active');
        jQuery(e.target).addClass('active');

        jQuery(".rdm-tabs-content>div").removeClass('active');
        jQuery(".rdm-tabs-content>div[data-rdm-tab='" + tab + "']").addClass('active');
    });
</script>