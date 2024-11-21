<form class="bs-settings-input-conditions" id="bs-settings-orders-tab" method="POST" action="<?php echo esc_url($actualLink); ?>">
    <input type="hidden" name="tab" value="orders" />
    <input type="hidden" name="storage" value="table" />
    <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>" />
    <table class="form-table">
        <tbody>
            <tr class="usbs-section-label">
                <td>
                    <h2><?php echo esc_html__("Order Status", "us-barcode-scanner"); ?></h2>
                </td>
            </tr>
            <!-- Default order status -->
            <tr>
                <th scope="row" style="width: 240px;">
                    <?php echo esc_html__("Default order status", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <?php
                    $defaultValue = $settings->getSettings("defaultOrderStatus");
                    $defaultValue = $defaultValue === null ? $settings->getField("general", "defaultOrderStatus", "wc-processing") : $defaultValue->value;
                    ?>
                    <select name="defaultOrderStatus">
                        <?php
                        foreach ($settings->getOrderStatuses() as $key => $value) {
                            $selected = "";
                            if ($defaultValue === $key) {
                                $selected = ' selected=selected ';
                            }
                        ?>
                            <option value="<?php esc_html_e($key, 'us-barcode-scanner'); ?>" <?php esc_html_e($selected, 'us-barcode-scanner'); ?>><?php esc_html_e($value, 'us-barcode-scanner'); ?></option>
                            <?php
                        }
                        ?>
                    </select>
                </td>
            </tr>
            <!-- Change order status automatically if all items picked/fulfilled. -->
            <tr>
                <th scope="row" style="width: 240px;">
                    <?php echo esc_html__("Change order status automatically if all items picked/fulfilled.", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <?php
                    $defaultValue = $settings->getSettings("autoStatusFulfilled");
                    $defaultValue = $defaultValue === null ? "" : $defaultValue->value;
                    ?>
                    <select name="autoStatusFulfilled">
                        <option value=""><?php echo esc_html__('Not selected', 'us-barcode-scanner'); ?></option>
                        <?php
                        foreach ($settings->getOrderStatuses() as $key => $value) {
                            $selected = "";
                            if ($defaultValue === $key) {
                                $selected = ' selected=selected ';
                            }
                        ?>
                            <option value="<?php esc_html_e($key, 'us-barcode-scanner'); ?>" <?php esc_html_e($selected, 'us-barcode-scanner'); ?>><?php esc_html_e($value, 'us-barcode-scanner'); ?></option>
                            <?php
                        }
                        ?>
                    </select>
                </td>
            </tr>
            <!-- List of order statuses which considered as still not completed -->
            <tr>
                <th scope="row" style="width: 240px;">
                    <?php echo esc_html__("List of order statuses which considered as still not completed", "us-barcode-scanner"); ?>
                </th>
                <td class="statuses_still_not_completed">
                    <?php
                    $field = $settings->getSettings("orderStatusesAreStillNotCompleted");
                    $orderStatusesAreStillNotCompletedValue = $field === null ? "wc-pending,wc-processing,wc-on-hold" : $field->value;
                    ?>
                    <select name="orderStatusesAreStillNotCompleted[]" class="usbs_order_statuses_are_still_not_complected" multiple="true" style="width:300px;">
                        <?php foreach ($settings->getOrderStatuses() as $key => $value) : ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($value); ?></option>
                        <?php endforeach; ?>
                        <option value="trash"><?php echo esc_html__("Trash", "us-barcode-scanner"); ?></option>
                    </select>
                    <br />
                    <i><?php echo esc_html__("These statuses are used to determine how much orders are not completed for the same customer", "us-barcode-scanner"); ?></i>
                </td>
            </tr>
            <tr class="usbs-section-label">
                <td>
                    <h2><?php echo esc_html__("Payment method", "us-barcode-scanner"); ?></h2>
                </td>
            </tr>
            <!-- Default payment method -->
            <tr>
                <th scope="row" style="width: 240px;">
                    <?php echo esc_html__("Default payment method", "us-barcode-scanner"); ?>
                </th>
                <td class="payment-methods">
                    <?php
                    $defaultValue = $settings->getSettings("defaultPaymentMethod");
                    $defaultValue = $defaultValue === null ? $settings->getField("general", "defaultPaymentMethod", "wc-processing") : $defaultValue->value;
                    ?>
                    <select name="defaultPaymentMethod">
                        <option value=""><?php echo esc_html__('Not selected', 'us-barcode-scanner'); ?></option>
                        <?php foreach ($cart->getPaymentMethods() as $value) : ?>
                            <?php $selected = $defaultValue === $value['id'] ? ' selected=selected ' : ""; ?>
                            <option value="<?php esc_html_e($value['id'], 'us-barcode-scanner'); ?>" <?php esc_html_e($selected, 'us-barcode-scanner'); ?>><?php esc_html_e($value['title'], 'us-barcode-scanner'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <!-- Require Payment Method -->
            <tr id="bs_payment_required">
                <th scope="row">
                    <?php echo esc_html__("Payment method is required", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <?php
                    $defaultValue = $settings->getSettings("paymentRequired");
                    $defaultValue = $defaultValue === null ? 'off' : $defaultValue->value;
                    ?>
                    <label>
                        <?php $checked = $defaultValue !== "off" ? ' checked=checked ' : ''; ?>
                        <input type="checkbox" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> onchange="WebbsSettingsCheckboxChange(`#bs_payment_required input[name='paymentRequired']`,this.checked ? 'on' : 'off')" />
                        <input type="hidden" name="paymentRequired" value="<?php echo $checked ? "on" : "off"; ?>" />
                        <?php echo esc_html__("Enable", "us-barcode-scanner"); ?>
                    </label>
                </td>
            </tr>
            <tr class="usbs-section-label">
                <td>
                    <h2><?php echo esc_html__("Shipping method", "us-barcode-scanner"); ?></h2>
                </td>
            </tr>
            <!-- Default shipping method -->
            <tr>
                <th scope="row" style="width: 240px;">
                    <?php echo esc_html__("Default shipping method", "us-barcode-scanner"); ?>
                </th>
                <td class="shipping-methods">
                    <?php
                    $shippingsMethods = $settings->getAllShippingMethod();

                    $field = $settings->getSettings("defaultShippingMethods");
                    $shippingMethodsValue = $field === null ? "" : $field->value;
                    $shippingMethodsValueArr = $shippingMethodsValue ? explode(",", $shippingMethodsValue) : array();

                    usort($shippingsMethods, function ($a, $b) use ($shippingMethodsValueArr) {
                        $aIndex = array_search($a['id'], $shippingMethodsValueArr);
                        $bIndex = array_search($b['id'], $shippingMethodsValueArr);

                        $aIndex = ($aIndex === false) ? PHP_INT_MAX : $aIndex;
                        $bIndex = ($bIndex === false) ? PHP_INT_MAX : $bIndex;

                        return $aIndex - $bIndex;
                    });
                    ?>
                    <input type="hidden" name='defaultShippingMethods[]' value="<?php echo esc_attr($shippingMethodsValue); ?>" />
                    <select multiple data-placeholder='Choose a shipping...' multiple class='chosen-select-shipping-methods' style="width:300px;">
                        <?php foreach ($shippingsMethods as $method) : ?>
                            <option value="<?php echo esc_attr($method['id']); ?>"><?php echo esc_html($method['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <!-- Shipping method is required -->
            <tr id="bs_shipping_required">
                <th scope="row">
                    <?php echo esc_html__("Shipping method is required", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <?php
                    $defaultValue = $settings->getSettings("shippingRequired");
                    $defaultValue = $defaultValue === null ? 'off' : $defaultValue->value;
                    ?>
                    <label>
                        <?php $checked = $defaultValue !== "off" ? ' checked=checked ' : ''; ?>
                        <input type="checkbox" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> onchange="WebbsSettingsCheckboxChange(`#bs_shipping_required input[name='shippingRequired']`,this.checked ? 'on' : 'off')" />
                        <input type="hidden" name="shippingRequired" value="<?php echo esc_attr($checked ? "on" : "off"); ?>" />
                        <?php echo esc_html__("Enable", "us-barcode-scanner"); ?>
                    </label>
                </td>
            </tr>
            <tr class="usbs-section-label">
                <td>
                    <h2><?php echo esc_html__("Order fulfillment", "us-barcode-scanner"); ?></h2>
                </td>
            </tr>
            <!-- Enable Order fulfillment -->
            <tr id="bs_enable_order_fulfillment">
                <th scope="row">
                    <?php echo esc_html__("Enable Order fulfillment", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <?php
                    $defaultValue = $settings->getSettings("orderFulfillmentEnabled");
                    $defaultValue = $defaultValue === null ? 'on' : $defaultValue->value;
                    ?>
                    <label>
                        <?php $checked = $defaultValue !== "off" ? ' checked=checked ' : ''; ?>
                        <input type="checkbox" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> onchange="WebbsSettingsCheckboxChange(`#bs_enable_order_fulfillment input[name='orderFulfillmentEnabled']`,this.checked ? 'on' : 'off')" />
                        <input type="hidden" name="orderFulfillmentEnabled" value="<?php echo $checked ? "on" : "off"; ?>" />
                        <?php echo esc_html__("Enable", "us-barcode-scanner"); ?>
                    </label>
                </td>
            </tr>
            <!-- Order fulfillment enabled by default - Disable by default -->
            <tr id="bs_order_fulfillment_enabled">
                <th scope="row">
                    <?php echo esc_html__("Enabled by default (no need to press button)", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <?php
                    $defaultValue = $settings->getSettings("orderFulfillmentByDefault");
                    $defaultValue = $defaultValue === null ? 'off' : $defaultValue->value;
                    ?>
                    <label>
                        <?php $checked = $defaultValue !== "off" ? ' checked=checked ' : ''; ?>
                        <input type="checkbox" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> onchange="WebbsSettingsCheckboxChange(`#bs_order_fulfillment_enabled input[name='orderFulfillmentByDefault']`,this.checked ? 'on' : 'off')" />
                        <input type="hidden" name="orderFulfillmentByDefault" value="<?php echo $checked ? "on" : "off"; ?>" />
                        <?php echo esc_html__("Enable", "us-barcode-scanner"); ?>
                    </label><br />
                    <i><?php echo esc_html__('If this option enabled then "fulfillment" mode will be active by default.', "us-barcode-scanner"); ?></i>
                </td>
            </tr>
            <!-- Order fulfillment -->
            <tr id="fulfillment_scan_item_qty">
                <th scope="row">
                    <?php echo esc_html__("Take into account item's quantity", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <?php
                    $defaultValue = $settings->getSettings("fulfillmentScanItemQty");
                    $defaultValue = $defaultValue === null ? 'on' : $defaultValue->value;
                    ?>
                    <label>
                        <?php $checked = $defaultValue !== "off" ? ' checked=checked ' : ''; ?>
                        <input type="checkbox" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> onchange="WebbsSettingsCheckboxChange(`#fulfillment_scan_item_qty input[name='fulfillmentScanItemQty']`,this.checked ? 'on' : 'off')" />
                        <input type="hidden" name="fulfillmentScanItemQty" value="<?php echo $checked ? "on" : "off"; ?>" />
                        <?php echo esc_html__("Enable", "us-barcode-scanner"); ?>
                    </label><br />
                    <i><?php echo esc_html__("In order fulfillment mode, this option will take into account amount of the purchased items (qty). So, order item will be  fulfilled (marked with green arrow) as soon as product is scanned in the same amount as was purchased. E.g. if 10 the same items were purchased - you will have to scan the barcode 10 times.", "us-barcode-scanner"); ?></i>
                </td>
            </tr>
            <!-- Order fulfillment -->
            <tr id="fulfilled_not_allow_status">
                <th scope="row">
                    <?php echo esc_html__("Do not allow to change order status after order is fulfilled", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <?php
                    $defaultValue = $settings->getSettings("fulfilledNotAllowStatus");
                    $defaultValue = $defaultValue === null ? 'off' : $defaultValue->value;
                    ?>
                    <label>
                        <?php $checked = $defaultValue !== "off" ? ' checked=checked ' : ''; ?>
                        <input type="checkbox" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> onchange="WebbsSettingsCheckboxChange(`#fulfilled_not_allow_status input[name='fulfilledNotAllowStatus']`,this.checked ? 'on' : 'off')" />
                        <input type="hidden" name="fulfilledNotAllowStatus" value="<?php echo $checked ? "on" : "off"; ?>" />
                        <?php echo esc_html__("Enable", "us-barcode-scanner"); ?>
                    </label>
                </td>
            </tr>
            <!-- Order fulfillment -->
            <tr id="dont_allow_to_switch_order">
                <th scope="row">
                    <?php echo esc_html__("Don't allow to switch order until fulfillment is completed.", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <?php
                    $defaultValue = $settings->getSettings("dontAllowSwitchOrder");
                    $defaultValue = $defaultValue === null ? 'off' : $defaultValue->value;
                    ?>
                    <label>
                        <?php $checked = $defaultValue !== "off" ? ' checked=checked ' : ''; ?>
                        <input type="checkbox" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> onchange="WebbsSettingsCheckboxChange(`#dont_allow_to_switch_order input[name='dontAllowSwitchOrder']`,this.checked ? 'on' : 'off')" />
                        <input type="hidden" name="dontAllowSwitchOrder" value="<?php echo $checked ? "on" : "off"; ?>" />
                        <?php echo esc_html__("Enable", "us-barcode-scanner"); ?>
                    </label>
                </td>
            </tr>
            <!-- Allow user to mark product as fulfilled by pressing on "Picked X" text. -->
            <tr id="allow_mark_fulfilled">
                <th scope="row">
                    <?php echo esc_html__('Allow user to mark product as fulfilled by pressing on "Picked X" text.', "us-barcode-scanner"); ?>
                </th>
                <td>
                    <?php
                    $defaultValue = $settings->getSettings("allowMarkFulfilled");
                    $defaultValue = $defaultValue === null ? 'on' : $defaultValue->value;
                    ?>
                    <label>
                        <?php $checked = $defaultValue !== "off" ? ' checked=checked ' : ''; ?>
                        <input type="checkbox" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> onchange="WebbsSettingsCheckboxChange(`#allow_mark_fulfilled input[name='allowMarkFulfilled']`,this.checked ? 'on' : 'off')" />
                        <input type="hidden" name="allowMarkFulfilled" value="<?php echo $checked ? "on" : "off"; ?>" />
                        <?php echo esc_html__("Enable", "us-barcode-scanner"); ?>
                    </label>
                </td>
            </tr>
            <!-- Order fulfillment -->
            <tr id="fulfilled_close_order_after">
                <th scope="row">
                    <?php echo esc_html__("Close order after order is fulfilled", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <?php
                    $defaultValue = $settings->getSettings("fulfilledCloseOrderAfter");
                    $defaultValue = $defaultValue === null ? 'off' : $defaultValue->value;
                    ?>
                    <label>
                        <?php $checked = $defaultValue !== "off" ? ' checked=checked ' : ''; ?>
                        <input type="checkbox" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> onchange="WebbsSettingsCheckboxChange(`#fulfilled_close_order_after input[name='fulfilledCloseOrderAfter']`,this.checked ? 'on' : 'off')" />
                        <input type="hidden" name="fulfilledCloseOrderAfter" value="<?php echo $checked ? "on" : "off"; ?>" />
                        <?php echo esc_html__("Enable", "us-barcode-scanner"); ?>
                    </label>
                </td>
            </tr>
            <!-- Reset all items if fulfillment process is not completed -->
            <tr id="reset_fulfillment_by_close_order">
                <th scope="row">
                    <?php echo esc_html__("Reset all items if fulfillment process is not completed", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <?php
                    $defaultValue = $settings->getSettings("resetFulfillmentByCloseOrder");
                    $defaultValue = $defaultValue === null ? 'off' : $defaultValue->value;
                    ?>
                    <label>
                        <?php $checked = $defaultValue !== "off" ? ' checked=checked ' : ''; ?>
                        <input type="checkbox" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> onchange="WebbsSettingsCheckboxChange(`#reset_fulfillment_by_close_order input[name='resetFulfillmentByCloseOrder']`,this.checked ? 'on' : 'off')" />
                        <input type="hidden" name="resetFulfillmentByCloseOrder" value="<?php echo $checked ? "on" : "off"; ?>" />
                        <?php echo esc_html__("Enable", "us-barcode-scanner"); ?>
                    </label>
                    <br />
                    <i><?php echo esc_html__("If order is closed but fulfillment is not finished then all fulfillment progress will be lost.", "us-barcode-scanner"); ?></i>
                </td>
            </tr>
            <!-- Use tracking number for fulfillment -->
            <tr>
                <th scope="row">
                    <?php echo esc_html__("Use tracking number for fulfillment", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <?php
                    $defaultValue = $settings->getSettings("orderFulFillmentField");
                    $defaultValue = $defaultValue === null ? "" : $defaultValue->value;
                    ?>
                    <span>
                        <input type="text" name="orderFulFillmentField" value="<?php esc_html_e($defaultValue); ?>" placeholder="<?php echo esc_html__("Field name", "us-barcode-scanner"); ?>" />
                        <div>
                            <i><?php echo esc_html__("Specify meta custom field name of the tracking number (you may need help of web-developer to find it)", "us-barcode-scanner"); ?></i>
                        </div>
                    </span>
                </td>
            </tr>
            <!-- Fulfillment product qty step -->
            <tr>
                <th scope="row">
                    <?php echo esc_html__("Fulfillment product qty step", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <?php
                    $defaultValue = $settings->getSettings("ffQtyStep");
                    $defaultValue = $defaultValue === null ? "" : $defaultValue->value;
                    ?>
                    <span>
                        <input type="text" name="ffQtyStep" value="<?php esc_html_e($defaultValue); ?>" placeholder="<?php echo esc_html__("Product custom meta field", "us-barcode-scanner"); ?>" />
                        <div>
                            <i><?php echo esc_html__('Take into account "qty step" product field in the fulfillment process. It may be helpful if you sell items in boxes (e.g. products 10 per box) and you want to scan the box barcode and to fulfill item by 10. Specify here the product meta field where "qty step"/"amount in the box" is specified.', "us-barcode-scanner"); ?></i>
                        </div>
                    </span>
                </td>
            </tr>
            <!-- sort order items by categories -->
            <tr id="reset_fulfillment_by_close_order">
                <th scope="row">
                    <?php echo esc_html__("Sort order items by categories", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <?php
                    $defaultValue = $settings->getSettings("sortOrderItemsByCategories");
                    $defaultValue = $defaultValue === null ? 'off' : $defaultValue->value;
                    ?>
                    <label>
                        <?php $checked = $defaultValue !== "off" ? ' checked=checked ' : ''; ?>
                        <input type="checkbox" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> onchange="WebbsSettingsCheckboxChange(`#reset_fulfillment_by_close_order input[name='sortOrderItemsByCategories']`,this.checked ? 'on' : 'off')" />
                        <input type="hidden" name="sortOrderItemsByCategories" value="<?php echo $checked ? "on" : "off"; ?>" />
                        <?php echo esc_html__("Enable", "us-barcode-scanner"); ?>
                    </label>
                </td>
            </tr>
            <tr class="usbs-section-label">
                <td>
                    <h2><?php echo esc_html__("Email notifications", "us-barcode-scanner"); ?></h2>
                </td>
            </tr>
            <!-- Send new order email to admin - Disable by default -->
            <tr id="bs_send_email_for_created_order">
                <th scope="row">
                    <?php echo esc_html__("Send new order email to admin", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <?php
                    $defaultValue = $settings->getSettings("sendAdminEmailCreatedOrder");
                    $defaultValue = $defaultValue === null ? 'off' : $defaultValue->value;
                    ?>
                    <label>
                        <?php $checked = $defaultValue !== "off" ? ' checked=checked ' : ''; ?>
                        <input type="checkbox" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> onchange="WebbsSettingsCheckboxChange(`#bs_send_email_for_created_order input[name='sendAdminEmailCreatedOrder']`,this.checked ? 'on' : 'off')" />
                        <input type="hidden" name="sendAdminEmailCreatedOrder" value="<?php echo $checked ? "on" : "off"; ?>" />
                        <?php echo esc_html__("Enable", "us-barcode-scanner"); ?>
                    </label>
                </td>
            </tr>
            <!-- Send new order email to client - Enable by default -->
            <tr id="bs_send_email_for_created_order">
                <th scope="row">
                    <?php echo esc_html__("Send new order email to client", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <?php
                    $defaultValue = $settings->getSettings("sendClientEmailCreatedOrder");
                    $defaultValue = $defaultValue === null ? 'on' : $defaultValue->value;
                    ?>
                    <label>
                        <?php $checked = $defaultValue !== "off" ? ' checked=checked ' : ''; ?>
                        <input type="checkbox" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> onchange="WebbsSettingsCheckboxChange(`#bs_send_email_for_created_order input[name='sendClientEmailCreatedOrder']`,this.checked ? 'on' : 'off')" />
                        <input type="hidden" name="sendClientEmailCreatedOrder" value="<?php echo $checked ? "on" : "off"; ?>" />
                        <?php echo esc_html__("Enable", "us-barcode-scanner"); ?>
                    </label>
                </td>
            </tr>
            <tr class="usbs-section-label">
                <td>
                    <h2><?php echo esc_html__("Display / hide fields", "us-barcode-scanner"); ?></h2>
                </td>
            </tr>
            <!-- Display "Coupon" field -->
            <tr id="display_coupon_field">
                <th scope="row">
                    <?php echo esc_html__('Display "Coupon" field', "us-barcode-scanner"); ?>
                </th>
                <td>
                    <?php
                    $defaultValue = $settings->getSettings("displayCouponField");
                    $defaultValue = $defaultValue === null ? 'on' : $defaultValue->value;
                    ?>
                    <label>
                        <?php $checked = $defaultValue !== "off" ? ' checked=checked ' : ''; ?>
                        <input type="checkbox" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> onchange="WebbsSettingsCheckboxChange(`#display_coupon_field input[name='displayCouponField']`,this.checked ? 'on' : 'off')" />
                        <input type="hidden" name="displayCouponField" value="<?php echo $checked ? "on" : "off"; ?>" />
                        <?php echo esc_html__("Enable", "us-barcode-scanner"); ?>
                    </label>
                </td>
            </tr>
            <!-- Display "Customer provided note" field -->
            <tr id="display_note_field">
                <th scope="row">
                    <?php echo esc_html__('Display "Customer provided note" field', "us-barcode-scanner"); ?>
                </th>
                <td>
                    <?php
                    $defaultValue = $settings->getSettings("displayNoteField");
                    $defaultValue = $defaultValue === null ? 'on' : $defaultValue->value;
                    ?>
                    <label>
                        <?php $checked = $defaultValue !== "off" ? ' checked=checked ' : ''; ?>
                        <input type="checkbox" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> onchange="WebbsSettingsCheckboxChange(`#display_note_field input[name='displayNoteField']`,this.checked ? 'on' : 'off')" />
                        <input type="hidden" name="displayNoteField" value="<?php echo $checked ? "on" : "off"; ?>" />
                        <?php echo esc_html__("Enable", "us-barcode-scanner"); ?>
                    </label>
                </td>
            </tr>
            <!-- Display "PAY" buttons -->
            <tr id="displayPayButton">
                <th scope="row">
                    <?php echo esc_html__('Display "PAY" buttons', "us-barcode-scanner"); ?>
                </th>
                <td>
                    <?php
                    $defaultValue = $settings->getSettings("displayPayButton");
                    $defaultValue = $defaultValue === null ? 'on' : $defaultValue->value;
                    ?>
                    <label>
                        <?php $checked = $defaultValue !== "off" ? ' checked=checked ' : ''; ?>
                        <input type="checkbox" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> onchange="WebbsSettingsCheckboxChange(`#displayPayButton input[name='displayPayButton']`,this.checked ? 'on' : 'off')" />
                        <input type="hidden" name="displayPayButton" value="<?php echo $checked ? "on" : "off"; ?>" />
                        <?php echo esc_html__("Enable", "us-barcode-scanner"); ?>
                    </label>
                </td>
            </tr>
            <tr class="usbs-section-label">
                <td>
                    <h2><?php echo esc_html__("New order", "us-barcode-scanner"); ?></h2>
                </td>
            </tr>
            <!-- New order default user -->
            <tr>
                <th scope="row" style="width: 240px;">
                    <?php echo esc_html__("New order default user", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <?php
                    $defaultValue = $settings->getSettings("nowOrderDefaultUser");
                    $defaultValue = $defaultValue === null ? "" : $defaultValue->value;
                    $userName = "";
                    if ($defaultValue) {
                        $user = get_user_by("ID", $defaultValue);
                        if ($user) {
                            $userName = $user->display_name . " (" . $user->user_login . ") - " . $user->user_email;
                        }
                    }
                    ?>
                    <span style="position: relative;">
                        <input type="text" value="<?php esc_html_e($userName); ?>" placeholder="<?php echo esc_html__("Find user", "us-barcode-scanner"); ?>" class="order-default-user-search-input" />
                        <input type="hidden" name="nowOrderDefaultUser" value="<?php esc_html_e($defaultValue); ?>" class="order-default-user-id-search-input" />
                        <span style="position: relative;">
                            <span style="position: absolute; top: -5px; left: 0; display: none;" id="order-default-user-search-preloader">
                                <span id="barcode-scanner-action-preloader">
                                    <span class="a4b-action-preloader-icon"></span>
                                </span>
                            </span>
                        </span>
                        <ul class="order-default-users-search-list"></ul>
                        <div>
                            <i><?php echo esc_html__("Link this user (by default) to all newly created orders via Barcode Scanner popup.", "us-barcode-scanner"); ?></i>
                        </div>
                    </span>
                </td>
            </tr>
            <!-- Require to select user -->
            <tr id="bs_new_order_user_required">
                <th scope="row">
                    <?php echo esc_html__("Require to select user", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <?php
                    $defaultValue = $settings->getSettings("newOrderUserRequired");
                    $defaultValue = $defaultValue === null ? 'off' : $defaultValue->value;
                    ?>
                    <label>
                        <?php $checked = $defaultValue !== "off" ? ' checked=checked ' : ''; ?>
                        <input type="checkbox" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> onchange="WebbsSettingsCheckboxChange(`#bs_new_order_user_required input[name='newOrderUserRequired']`,this.checked ? 'on' : 'off')" />
                        <input type="hidden" name="newOrderUserRequired" value="<?php echo $checked ? "on" : "off"; ?>" />
                        <?php echo esc_html__("Enable", "us-barcode-scanner"); ?>
                    </label>
                </td>
            </tr>
            <!-- Open order after creation -->
            <tr id="bs_open_order_after_creation">
                <th scope="row">
                    <?php echo esc_html__("Open order after creation", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <?php
                    $defaultValue = $settings->getSettings("openOrderAfterCreation");
                    $defaultValue = $defaultValue === null ? 'off' : $defaultValue->value;
                    ?>
                    <label>
                        <?php $checked = $defaultValue !== "off" ? ' checked=checked ' : ''; ?>
                        <input type="checkbox" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> onchange="WebbsSettingsCheckboxChange(`#bs_open_order_after_creation input[name='openOrderAfterCreation']`,this.checked ? 'on' : 'off')" />
                        <input type="hidden" name="openOrderAfterCreation" value="<?php echo esc_attr($checked ? "on" : "off"); ?>" />
                        <?php echo esc_html__("Enable", "us-barcode-scanner"); ?>
                    </label>
                </td>
            </tr>
            <!-- Default product qty -->
            <tr>
                <th scope="row" style="width: 240px;">
                    <?php echo esc_html__("Default product qty", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <?php
                    $defaultValue = $settings->getSettings("defaultProductQty");
                    $defaultValue = $defaultValue === null ? "1" : $defaultValue->value;
                    ?>
                    <span>
                        <input type="number" min="1" name="defaultProductQty" value="<?php echo ($defaultValue); ?>" placeholder="<?php echo esc_attr("1"); ?>" style="width: 70px; height: 30px;" />
                        <div>
                            <i><?php echo esc_html__("Any added product (to the new order) will have the QTY equal to this setting by default.", "us-barcode-scanner"); ?></i>
                        </div>
                    </span>
                </td>
            </tr>
            <!-- Use price to create order -->
            <tr>
                <th scope="row" style="width: 240px;">
                    <?php echo esc_html__("Use price to create order", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <?php
                    $defaultValue = $settings->getSettings("defaultPriceField");
                    $defaultValue = $defaultValue === null ? $settings->getField("prices", "defaultPriceField") : $defaultValue->value;
                    ?>
                    <select name="defaultPriceField" style="max-width: 175px;">
                        <?php $selected = $defaultValue === "wc_default" || $settings->getField("prices", "defaultPriceField", "wc_default") ? 'selected="selected"' : ""; ?>
                        <option value="wc_default" <?php esc_html_e($selected, 'us-barcode-scanner'); ?>><?php esc_html_e("WooCommerce default", 'us-barcode-scanner'); ?></option>

                        <?php foreach ($interfaceData::getFields(true) as $field) : ?>
                            <?php if ($field["type"] == "price" && $field["status"] == 1) : ?>
                                <?php $selected = $defaultValue === $field["field_name"] ? 'selected="selected"' : ""; ?>
                                <option value="<?php echo esc_attr($field["field_name"]); ?>" <?php esc_html_e($selected, 'us-barcode-scanner'); ?>><?php esc_html_e("Always use " . $field["field_label"], 'us-barcode-scanner'); ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <!-- Default order tax -->
            <?php
            $woocommerce_calc_taxes = get_option("woocommerce_calc_taxes");
            ?>
            <?php if ($woocommerce_calc_taxes == "yes") : ?>
                <tr>
                    <th scope="row" style="width: 240px;">
                        <?php echo esc_html__("Default order tax", "us-barcode-scanner"); ?>
                    </th>
                    <td>
                        <?php
                        $defaultValue = $settings->getSettings("defaultOrderTax");
                        $defaultValue = $defaultValue === null ? 'based_on_store' : $defaultValue->value;
                        ?>
                        <select name="defaultOrderTax" style="max-width: 175px;">
                            <?php $selected = $defaultValue === "based_on_store" ? 'selected="selected"' : ""; ?>
                            <option value="based_on_store" <?php esc_html_e($selected, 'us-barcode-scanner'); ?>><?php esc_html_e("Based on the store location", 'us-barcode-scanner'); ?></option>
                            <?php $selected = $defaultValue === "no_tax" ? 'selected="selected"' : ""; ?>
                            <option value="no_tax" <?php esc_html_e($selected, 'us-barcode-scanner'); ?>><?php esc_html_e("No Tax", 'us-barcode-scanner'); ?></option>
                        </select>
                        <div>
                            <i><?php echo esc_html__("This tax will be used for new order which doesn't have billing address information yet.", "us-barcode-scanner"); ?></i>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            <?php  ?>
        </tbody>
    </table>
    <div class="submit">
        <input type="submit" class="button button-primary" value="<?php echo esc_html__("Save Changes", "us-barcode-scanner"); ?>">
    </div>
</form>
<style>
    .order-default-user-search-input {
        min-width: 250px;
    }
</style>
<script>
    jQuery(document).ready(() => {
        const chosenOption = {
            width: "300px",
            no_results_text: "Loading:"
        };
        jQuery(".chosen-select-shipping-methods").chosen(chosenOption);

        const listUpdated = () => {
            const selectedListEl = jQuery('.shipping-methods .chosen-container .chosen-choices');
            const optionsEl = selectedListEl.find('.search-choice');
            let values = '';
            let orderIndex = [];
            let options = [];

            for (let index = 0; index < optionsEl.length; index++) {
                const element = jQuery(optionsEl[index]).find('a[data-option-array-index]');
                const optionIndex = element.attr('data-option-array-index');
                orderIndex.push(optionIndex)
            }

            var allOptions = jQuery(".chosen-select-shipping-methods option");
            for (let index = 0; index < allOptions.length; index++) {
                const id = jQuery(allOptions[index]).attr('value');
                options.push(id);
            }

            const sortedOptions = orderIndex.map(index => options[index]);

            const selectedItems = jQuery(".chosen-select-shipping-methods").val();

            jQuery('input[name="defaultShippingMethods[]"]').val(sortedOptions.join(','))
        };

        let timer = null;
        jQuery(".chosen-select-shipping-methods").on('change', (event, params) => {
            if (timer) clearTimeout(timer);
            timer = setTimeout(listUpdated, 50);
        });

        var defaultValue = '<?php echo wp_kses_post($shippingMethodsValue); ?>'.split(',');
        jQuery(".chosen-select-shipping-methods").val(defaultValue).trigger("chosen:updated");
    });
</script>
<script>
    jQuery(document).ready(function() {
        jQuery(".usbs_order_statuses_are_still_not_complected").chosen({
            search_contains: true,
            no_results_text: "<?php echo esc_html__("Nothing found for:", "us-barcode-scanner"); ?> ",
            width: "300px"
        });
        var defaultValue = '<?php echo esc_html($orderStatusesAreStillNotCompletedValue); ?>'.split(',');
        jQuery(".usbs_order_statuses_are_still_not_complected").val(defaultValue).trigger("chosen:updated");
    });
</script>