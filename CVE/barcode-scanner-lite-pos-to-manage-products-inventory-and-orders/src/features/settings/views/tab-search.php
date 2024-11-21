<?php

use UkrSolution\BarcodeScanner\API\classes\SearchFilter;
use UkrSolution\BarcodeScanner\API\classes\WPML;
?>
<form class="bs-settings-input-conditions" id="bs-settings-search-tab" method="POST" action="<?php echo esc_url($actualLink); ?>">
    <input type="hidden" name="tab" value="search" />
    <input type="hidden" name="storage" value="table" />
    <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>" />
    <table class="form-table">
        <tbody>
            <tr class="usbs-section-label">
                <td>
                    <h2><?php echo esc_html__("Exclude from search", "us-barcode-scanner"); ?></h2>
                </td>
            </tr>
            <!--  -->
            <tr>
                <th scope="row">
                    <?php echo esc_html__("Exclude product statuses from search", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <?php
                    $field = $settings->getSettings("productStatuses");
                    $productStatusesValue = $field === null ? "trash" : $field->value;
                    ?>
                    <select name="productStatuses[]" class="usbs_product_statuses" multiple="true" style="width:300px;">
                        <option value="publish"><?php echo esc_html__("Publish", "us-barcode-scanner"); ?></option>
                        <option value="future"><?php echo esc_html__("Future", "us-barcode-scanner"); ?></option>
                        <option value="draft"><?php echo esc_html__("Draft", "us-barcode-scanner"); ?></option>
                        <option value="pending"><?php echo esc_html__("Pending", "us-barcode-scanner"); ?></option>
                        <option value="private"><?php echo esc_html__("Private", "us-barcode-scanner"); ?></option>
                        <option value="auto-draft"><?php echo esc_html__("Auto-Draft", "us-barcode-scanner"); ?></option>
                        <option value="inherit"><?php echo esc_html__("Inherit", "us-barcode-scanner"); ?></option>
                        <option value="trash"><?php echo esc_html__("Trash", "us-barcode-scanner"); ?></option>
                    </select>
                </td>
            </tr>
            <!-- Exclude products from search with "Catalog visibility" -->
            <tr>
                <th scope="row">
                    <?php echo esc_html__('Exclude products from search with "Catalog visibility"', "us-barcode-scanner"); ?>
                </th>
                <td>
                    <?php
                    $field = $settings->getSettings("productsCatalogVisibility");
                    $productsCatalogVisibilityValue = $field === null ? "" : $field->value;
                    ?>
                    <input type="hidden" name="productsCatalogVisibility" value="" />
                    <select name="productsCatalogVisibility[]" class="usbs_products_catalog_visibility" multiple="true" style="width:300px;">
                        <?php foreach ($settings->getCatalogVisibility() as $key => $value) : ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($value); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <!-- Exclude order statuses from search -->
            <tr>
                <th scope="row">
                    <?php echo esc_html__("Exclude order statuses from search", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <?php
                    $field = $settings->getSettings("orderStatuses");
                    $orderStatusesValue = $field === null ? "wc-checkout-draft,trash" : $field->value;
                    ?>
                    <select name="orderStatuses[]" class="usbs_order_statuses" multiple="true" style="width:300px;">
                        <?php foreach ($settings->getOrderStatuses() as $key => $value) : ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($value); ?></option>
                        <?php endforeach; ?>
                        <option value="trash"><?php echo esc_html__("Trash", "us-barcode-scanner"); ?></option>
                    </select>
                </td>
            </tr>
            <!-- Exclude disabled variations for "Products" -->
            <tr id="disabled_variations_products">
                <th scope="row">
                    <?php echo esc_html__('Exclude disabled variations for "Products" tab', "us-barcode-scanner"); ?>
                </th>
                <td>
                    <label>
                        <?php
                        $defaultValue = $settings->getSettings("disabledVariationsProducts");
                        $defaultValue = $defaultValue === null ? 'off' : $defaultValue->value;
                        $checked = $defaultValue !== "off" ? ' checked=checked ' : '';
                        ?>
                        <input type="checkbox" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> data-main="disabledVariationsProducts" onchange="WebbsSettingsCheckboxChange(`#disabled_variations_products input[name='disabledVariationsProducts']`,this.checked ? 'on' : 'off')" />
                        <input type="hidden" name="disabledVariationsProducts" value="<?php echo $checked ? "on" : "off"; ?>" />
                        <?php echo esc_html__("Enable", "us-barcode-scanner"); ?> <span class="usbs-option-notice"></span>
                    </label>
                </td>
            </tr>
            <!-- Exclude disabled variations for "Orders" & "New Order" -->
            <tr id="disabled_variations_orders">
                <th scope="row">
                    <?php echo esc_html__('Exclude disabled variations for "Orders" & "New Order" tabs', "us-barcode-scanner"); ?>
                </th>
                <td>
                    <label>
                        <?php
                        $defaultValue = $settings->getSettings("disabledVariationsOrders");
                        $defaultValue = $defaultValue === null ? 'on' : $defaultValue->value;
                        $checked = $defaultValue !== "off" ? ' checked=checked ' : '';
                        ?>
                        <input type="checkbox" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> data-main="disabledVariationsOrders" onchange="WebbsSettingsCheckboxChange(`#disabled_variations_orders input[name='disabledVariationsOrders']`,this.checked ? 'on' : 'off')" />
                        <input type="hidden" name="disabledVariationsOrders" value="<?php echo $checked ? "on" : "off"; ?>" />
                        <?php echo esc_html__("Enable", "us-barcode-scanner"); ?> <span class="usbs-option-notice"></span>
                    </label>
                </td>
            </tr>
            <!-- WPML languages -->
            <?php if (WPML::status()) : ?>
                <?php
                $searchFilter = SearchFilter::get();
                $translations = null;

                if (WPML::status()) {
                    $translations = WPML::getTranslations();
                }
                ?>
                <?php if ($translations) : ?>
                    <tr id="wpml_translations">
                        <th scope="row">
                            <?php echo esc_html__('Include product languages in the search', "us-barcode-scanner"); ?>
                        </th>
                        <td>
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <?php foreach ($translations as $key => $value) : ?>
                                    <label style="display: flex; align-items: center; gap: 5px;">
                                        <?php
                                        $checked = isset($searchFilter['wpml']) && isset($searchFilter['wpml'][$key]) && $searchFilter['wpml'][$key] == 1 ? ' checked=checked ' : '';
                                        ?>
                                        <input type="checkbox" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> name="wpml[languages][<?php echo esc_attr($key) ?>]" value="1" style="margin-top: 2px;" />
                                        <?php if (isset($value["country_flag_url"])) : ?>
                                            <img src="<?php echo esc_url($value["country_flag_url"]) ?>" height="15" />
                                        <?php endif; ?>
                                        <?php echo $value["native_name"]; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endif; ?>
            <tr class="usbs-section-label">
                <td>
                    <h2><?php echo esc_html__("Indexation", "us-barcode-scanner"); ?></h2>
                </td>
            </tr>
            <!-- Search fields: [Open] -->
            <tr>
                <th scope="row">
                    <?php echo esc_html__("Search fields", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <?php
                    $link = '#barcode-scanner-search-filter';
                    ?>
                    <a href="<?php echo esc_url($link); ?>" class="usbs-btn"><?php echo esc_html__("Open", "us-barcode-scanner"); ?></a>
                    <i style="padding-left: 10px;"><?php echo esc_html__("Select which product/order fields should be used by the search to find the item.", "us-barcode-scanner"); ?></i>
                </td>
            </tr>
            <tr id="bs_search_indexation">
                <th scope="row">
                    <?php echo esc_html__("Start indexation", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <div>
                        <?php if (!$settings->getField("indexing", "indexed", false)) : ?>
                            <div id="bs_search_indexation_notice">
                                <div class="notice-error notice" style="margin: 0 0 10px; display: inline-block;">
                                    <p>Please start indexation to speed up search</p>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php
                        $link = '#barcode-scanner-products-indexation';
                        ?>
                        <a href="<?php echo esc_url($link); ?>"><?php echo esc_html__("Start indexation", "us-barcode-scanner"); ?></a>
                        <i style="padding-left: 10px;"><?php echo esc_html__("Re-create index tables and make full indexation of products and orders.", "us-barcode-scanner"); ?></i>
                    </div>
                    <div style="padding-top: 5px;">
                        <?php
                        $indexed = $settings->getTotalIndexedRecords();
                        $total = $settings->getTotalPosts();
                        $cannotIndexed = $settings->getTotalCantIndexedRecords();
                        ?>
                        <span id="barcode-scanner-products-total-indexed" style="color: #008b00;"><?php echo esc_html($indexed < $total ? $indexed : $total); ?></span>
                        <?php echo esc_html__("successfully indexed of", "us-barcode-scanner"); ?> <span id="barcode-scanner-products-total"><?php echo esc_html($total); ?></span>
                        <?php if ($cannotIndexed) : ?>
                            <?php echo esc_html__("Can't index", "us-barcode-scanner"); ?> <span style="color: #ff0000;" id="barcode-scanner-products-fail-indexed"><?php echo esc_html($cannotIndexed); ?></span> <?php echo esc_html__("items", "us-barcode-scanner"); ?>.
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <!-- Indexation step (items per request) -->
            <tr>
                <th scope="row">
                    <?php echo esc_html__("Indexation step (items per request)", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <label>
                        <?php
                        $field = $settings->getSettings("indexationStep");
                        $value = $field === null ? 50 : (int)$field->value;
                        $value = $value ? $value : 50;
                        ?>
                        <input type="number" name="indexationStep" value="<?php echo esc_html($value); ?>" placeholder="50" min="1" max="1000" />
                    </label>
                </td>
            </tr>
            <!-- Enable products indexation (enabled by default) -->
            <tr id="products_indexation">
                <th scope="row">
                    <?php echo esc_html__("Enable products indexation", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <label>
                        <?php
                        $defaultValue = $settings->getSettings("productsIndexation");
                        $defaultValue = $defaultValue === null ? 'on' : $defaultValue->value;
                        $checked = $defaultValue !== "off" ? ' checked=checked ' : '';
                        ?>
                        <input type="checkbox" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> data-main="productsIndexation" onchange="WebbsSettingsCheckboxChange(`#products_indexation input[name='productsIndexation']`,this.checked ? 'on' : 'off')" />
                        <input type="hidden" name="productsIndexation" value="<?php echo $checked ? "on" : "off"; ?>" />
                        <?php echo esc_html__("Enable", "us-barcode-scanner"); ?> <span class="usbs-option-notice"></span>
                    </label>
                </td>
            </tr>
            <!-- Enable orders indexation (enabled by default) -->
            <tr id="orders_indexation">
                <th scope="row">
                    <?php echo esc_html__("Enable orders indexation", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <label>
                        <?php
                        $defaultValue = $settings->getSettings("ordersIndexation");
                        $defaultValue = $defaultValue === null ? 'on' : $defaultValue->value;
                        $checked = $defaultValue !== "off" ? ' checked=checked ' : '';
                        ?>
                        <input type="checkbox" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> data-main="ordersIndexation" onchange="WebbsSettingsCheckboxChange(`#orders_indexation input[name='ordersIndexation']`,this.checked ? 'on' : 'off')" />
                        <input type="hidden" name="ordersIndexation" value="<?php echo $checked ? "on" : "off"; ?>" />
                        <?php echo esc_html__("Enable", "us-barcode-scanner"); ?> <span class="usbs-option-notice"></span>
                    </label>
                </td>
            </tr>
            <tr class="usbs-section-label">
                <td>
                    <h2><?php echo esc_html__("Other", "us-barcode-scanner"); ?></h2>
                </td>
            </tr>
            <tr id="bs_display_search_counter">
                <th scope="row">
                    <?php echo esc_html__("Display search counter", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <label>
                        <?php
                        $field = $settings->getSettings("displaySearchCounter");
                        $value = $field === null ? $settings->getField("general", "displaySearchCounter", "") : $field->value;
                        $checked = $value === "on" ? ' checked=checked ' : '';
                        ?>
                        <input type="checkbox" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> data-main="displaySearchCounter" onchange="WebbsSettingsCheckboxChange(`#bs_display_search_counter input[name='displaySearchCounter']`,this.checked ? 'on' : 'off')" />
                        <input type="hidden" name="displaySearchCounter" value="<?php echo $checked ? "on" : "off"; ?>" />
                        <?php echo esc_html__("Enable", "us-barcode-scanner"); ?> <span class="usbs-option-notice"></span>
                    </label><br />
                    <i>
                        <?php echo esc_html__("Displays the counter of how much times the product/order has been opened using barcode scanner."); ?>
                    </i>
                </td>
            </tr>
            <!-- Search results max limit -->
            <tr>
                <th scope="row">
                    <?php echo esc_html__("Search results max limit", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <label>
                        <?php
                        $field = $settings->getSettings("searchResultsLimit");
                        $value = $field === null ? 20 : (int)$field->value;
                        $value = $value ? $value : 20;
                        ?>
                        <input type="number" name="searchResultsLimit" value="<?php echo esc_html($value); ?>" placeholder="20" min="1" max="200" />
                    </label>
                    <br />
                    <i><?php echo esc_html__("Specify how much maximum search result you would like to see in search suggestion dropdown.", "us-barcode-scanner"); ?></i>

                </td>
            </tr>
            <!-- Delay between scanning and ajax request -->
            <tr>
                <th scope="row">
                    <?php echo esc_html__("Delay between scanning and ajax request", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <label>
                        <?php
                        $field = $settings->getSettings("delayBetweenScanning");
                        $value = $field === null ? 300 : (int)$field->value;
                        $value = $value ? $value : 300;
                        ?>
                        <input type="number" name="delayBetweenScanning" value="<?php echo esc_html($value); ?>" placeholder="300" min="50" max="1000" />
                    </label>
                    <br />
                    <i><?php echo esc_html__('Plugin waits for "X" ms while barcode scanner is filling the search input with code.', "us-barcode-scanner"); ?></i>

                </td>
            </tr>
            <tr id="bs_direct_db_search">
                <th scope="row">
                    <?php echo esc_html__("Enable direct DB requests", "us-barcode-scanner"); ?>
                    <div style="font-weight: 400; padding-top: 5px;">
                        <a href="<?php echo esc_url(admin_url('/admin.php?page=barcode-scanner-settings&tab=plugins')); ?>"><?php echo esc_html__("Allow plugins", "us-barcode-scanner"); ?></a>
                        <?php echo esc_html__("which should interfere into \"Barcode Scanner\" plugin's work", "us-barcode-scanner"); ?>
                    </div>
                </th>
                <td>
                    <label>
                        <?php
                        $field = $settings->getSettings("directDbSearch");
                        $value = $field === null ? $settings->getField("general", "directDbSearch", "on") : $field->value;
                        ?>
                        <?php
                        if ($value === "on") {
                            $checked = ' checked=checked ';
                        } else {
                            $checked = '';
                        }
                        ?>
                        <input type="checkbox" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> data-main="directDbSearch" onchange="WebbsSettingsCheckboxChange(`#bs_direct_db_search input[name='directDbSearch']`,this.checked ? 'on' : 'off')" />
                        <input type="hidden" name="directDbSearch" value="<?php echo $checked ? "on" : "off"; ?>" />
                        <?php echo esc_html__("Enable", "us-barcode-scanner"); ?> <span class="usbs-option-notice"></span>
                    </label><br />
                    <i>
                        <?php echo esc_html__("This option may speed up barcode scanner work dramatically as it avoids some third-party plugin initialization."); ?><br />
                        <?php echo esc_html__("However, third party plugins won't be able to hook/interact with this plugin (won't be able to catch events triggered by this plugin)."); ?>
                    </i>
                </td>
            </tr>
            <!-- Modify (pre-process) search string -->
            <tr>
                <th scope="row">
                    <?php echo esc_html__("Modify (pre-process) search string", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <label>
                        <?php
                        $field = $settings->getSettings("modifyPreProcessSearchString");
                        $value = $field === null ? "" : trim($field->value);
                        ?>
                        <textarea type="number" name="modifyPreProcessSearchString" value="<?php echo esc_html($value); ?>" rows="5" cols="55"><?php echo wp_kses_post(stripslashes($value)); ?></textarea>
                    </label>
                    <br />
                    <i><?php echo esc_html__('Use JavaScript to edit search string (use JS variable "bs_search_string" for work).', "us-barcode-scanner"); ?></i>
                </td>
            </tr>
            <?php  ?>
        </tbody>
    </table>
    <div class="submit">
        <input type="submit" class="button button-primary" value="<?php echo esc_html__("Save Changes", "us-barcode-scanner"); ?>">
    </div>
</form>
<script>
    jQuery(document).ready(function() {
        jQuery(".usbs_product_statuses").chosen({
            search_contains: true,
            no_results_text: "<?php echo esc_html__("Nothing found for:", "us-barcode-scanner"); ?> ",
            width: "300px"
        });
        var defaultValue = '<?php echo esc_html($productStatusesValue); ?>'.split(',');
        jQuery(".usbs_product_statuses").val(defaultValue).trigger("chosen:updated");

        jQuery(".usbs_order_statuses").chosen({
            search_contains: true,
            no_results_text: "<?php echo esc_html__("Nothing found for:", "us-barcode-scanner"); ?> ",
            width: "300px"
        });
        var defaultValue = '<?php echo esc_html($orderStatusesValue); ?>'.split(',');
        jQuery(".usbs_order_statuses").val(defaultValue).trigger("chosen:updated");

        jQuery(".usbs_products_catalog_visibility").chosen({
            search_contains: true,
            no_results_text: "<?php echo esc_html__("Nothing found for:", "us-barcode-scanner"); ?> ",
            width: "300px"
        });
        var defaultValue = '<?php echo esc_html($productsCatalogVisibilityValue); ?>'.split(',');
        jQuery(".usbs_products_catalog_visibility").val(defaultValue).trigger("chosen:updated");
    });
</script>