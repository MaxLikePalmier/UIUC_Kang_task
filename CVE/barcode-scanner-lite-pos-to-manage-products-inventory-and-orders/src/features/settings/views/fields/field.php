<?php

use UkrSolution\BarcodeScanner\API\classes\BatchNumbers;
use UkrSolution\BarcodeScanner\API\classes\BatchNumbersWebis;

?>
<tr class="settings_field_section <?php echo (isset($rootClass) && $rootClass) ? esc_attr($rootClass) : "" ?>">
    <td style="padding: 0;">
        <div style="padding: 14px 10px 10px; background: #fff; margin-bottom: 10px; position: relative; width: 360px; box-shadow: 0 0 8px 1px #c7c7c7; border-radius: 4px;">
            <input type="hidden" class="usbs_field_order" name="fields[<?php echo esc_attr($field["id"]); ?>][<?php echo esc_attr($orderField); ?>]" value="<?php echo esc_attr($field[$orderField]); ?>" />
            <input type="hidden" class="usbs_field_position" name="fields[<?php echo esc_attr($field["id"]); ?>][position]" value="<?php echo esc_attr($field["position"]); ?>" />
            <input type="hidden" class="usbs_field_remove" name="fields[<?php echo esc_attr($field["id"]); ?>][remove]" value="0" />

            <span class="dashicons dashicons-move" title="<?php echo esc_html__("Move", "us-barcode-scanner"); ?>"></span>

            <div class="settings_field_block_label" data-fid="<?php echo esc_attr($field["id"]); ?>">
                <span class="dashicons dashicons-arrow-up-alt2"></span>
                <span class="dashicons dashicons-arrow-down-alt2 active"></span>
                <?php 
                ?> <?php echo esc_html($field["field_label"]); ?>
                <?php if ($field[$statusField] == 0) : ?>
                    <span style="color: #f00; position: relative; top: -4px;}"><?php echo esc_html__("(disabled)", "us-barcode-scanner"); ?></span>
                <?php endif; ?>
            </div>
            <!-- settings -->
            <div id="settings_field" class="settings_field_body" data-fid="<?php echo esc_attr($field["id"]); ?>">
                <div colspan="2" style="padding: 0;">
                    <table>
                        <tr class="usbs_field_status">
                            <td scope="row" style="text-align:left; padding: 0 10px 0 10px; width: 110px; box-sizing: border-box;">
                                <label onclick="WebbsFieldsChToggle(this, 'usbs_field_status')" data-fid="<?php echo esc_attr($field["id"]); ?>">
                                    <?php echo esc_html__("Enable", "us-barcode-scanner"); ?>
                                </label>
                            </td>
                            <td style="padding: 0 0 5px;">
                                <!-- checkbox -->
                                <?php $checked = $field[$statusField] == 1 ? ' ' . wp_kses_post('checked=checked') . ' ' : ''; ?>
                                <input type="checkbox" class="usbs_field_status" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> data-fid="<?php echo esc_attr($field["id"]); ?>" onchange="WebbsSettingsCheckboxChange(`#bs-settings-fields-tab .usbs_field_status input[data-fid='<?php echo esc_attr($field['id']); ?>']`, this.checked ? '1' : '0')" />
                                <input type="hidden" name="fields[<?php echo esc_attr($field["id"]); ?>][<?php echo esc_attr($statusField); ?>]" value="<?php echo $checked ? "1" : "0"; ?>" data-fid="<?php echo esc_attr($field["id"]); ?>" />
                            </td>
                        </tr>

                        <tr class="show_in_create_order">
                            <td scope="row" style="text-align:left; padding: 0 10px 0 10px; width: 110px; box-sizing: border-box;">
                                <label onclick="WebbsFieldsChToggle(this, 'usbs_field_show_in_create_order')" data-fid="<?php echo esc_attr($field["id"]); ?>">
                                    <?php echo esc_html__("Show in order", "us-barcode-scanner"); ?>
                                </label>
                            </td>
                            <td style="padding: 0 0 5px;">
                                <!-- checkbox -->
                                <?php $checked = $field["show_in_create_order"] == 1 ? ' checked=checked ' : ''; ?>
                                <input type="checkbox" class="usbs_field_show_in_create_order" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> data-fid="<?php echo esc_attr($field["id"]); ?>" onchange="WebbsSettingsCheckboxChange(`#bs-settings-fields-tab .show_in_create_order input[data-fid='<?php echo esc_attr($field['id']); ?>']`, this.checked ? '1' : '0')" />
                                <input type="hidden" name="fields[<?php echo esc_attr($field["id"]); ?>][show_in_create_order]" value="<?php echo $checked ? esc_attr("1") : esc_attr("0"); ?>" data-fid="<?php echo esc_attr($field["id"]); ?>" />
                            </td>
                        </tr>

                        <tr class="disabled_field">
                            <td scope="row" style="text-align:left; padding: 0 10px 0 10px; width: 110px; box-sizing: border-box;">
                                <label onclick="WebbsFieldsChToggle(this, 'usbs_field_disabled_field')" data-fid="<?php echo esc_attr($field["id"]); ?>">
                                    <?php echo esc_html__("Read-only", "us-barcode-scanner"); ?>
                                </label>
                            </td>
                            <td style="padding: 0 0 5px;">
                                <!-- checkbox -->
                                <?php $checked = $field["disabled_field"] == 1 ? ' checked=checked ' : ''; ?>
                                <input type="checkbox" class="usbs_field_disabled_field" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> data-fid="<?php echo esc_attr($field["id"]); ?>" onchange="WebbsSettingsCheckboxChange(`#bs-settings-fields-tab .disabled_field input[data-fid='<?php echo esc_attr($field['id']); ?>']`, this.checked ? '1' : '0')" />
                                <input type="hidden" name="fields[<?php echo esc_attr($field["id"]); ?>][disabled_field]" value="<?php echo $checked ? esc_attr("1") : esc_attr("0"); ?>" data-fid="<?php echo esc_attr($field["id"]); ?>" />
                            </td>
                        </tr>

                        <tr>
                            <td scope="row" style="text-align:left; padding: 0 10px 0 10px; width: 110px; box-sizing: border-box;">
                                <?php echo esc_html__("Field type", "us-barcode-scanner"); ?>
                            </td>
                            <td style="padding: 0;">
                                <select class="usbs_field_type" name="fields[<?php echo esc_attr($field["id"]); ?>][type]" style="width: 177px;">
                                    <option value="text" <?php echo $field["type"] == "text" ? wp_kses_post("selected='selected'") : ""; ?>><?php echo  esc_html__("Text", "us-barcode-scanner"); ?></option>
                                    <option value="price" <?php echo $field["type"] == "price" ? wp_kses_post("selected='selected'") : ""; ?>><?php echo  esc_html__("Price", "us-barcode-scanner"); ?></option>
                                    <option value="number_plus_minus" <?php echo $field["type"] == "number_plus_minus" ? wp_kses_post("selected='selected'") : ""; ?>><?php echo  esc_html__("Number (plus/minus)", "us-barcode-scanner"); ?></option>
                                    <option value="select" <?php echo $field["type"] == "select" ? wp_kses_post("selected='selected'") : ""; ?>><?php echo  esc_html__("Dropdown", "us-barcode-scanner"); ?></option>
                                    <?php if ($settingsHelper::is_plugin_active('product-expiry-for-woocommerce/product-expiry-for-woocommerce.php')) : ?>
                                        <option value="ExpiryDate" <?php echo $field["type"] == "ExpiryDate" ? wp_kses_post("selected='selected'") : ""; ?>><?php echo  esc_html__("ExpiryDate", "us-barcode-scanner"); ?></option>
                                    <?php endif; ?>
                                    <option value="white_space" <?php echo $field["type"] == "white_space" ? wp_kses_post("selected='selected'") : ""; ?>><?php echo  esc_html__("White space", "us-barcode-scanner"); ?></option>
                                    <option value="categories" <?php echo $field["type"] == "categories" ? wp_kses_post("selected='selected'") : ""; ?>><?php echo  esc_html__("Categories", "us-barcode-scanner"); ?></option>
                                    <option value="tags" <?php echo $field["type"] == "tags" ? wp_kses_post("selected='selected'") : ""; ?>><?php echo  esc_html__("Tags", "us-barcode-scanner"); ?></option>
                                    <option value="variation_attributes" <?php echo $field["type"] == "variation_attributes" ? wp_kses_post("selected='selected'") : ""; ?>><?php echo  esc_html__("Variation attributes", "us-barcode-scanner"); ?></option>
                                    <option value="global_attribute" <?php echo $field["type"] == "global_attribute" ? wp_kses_post("selected='selected'") : ""; ?>><?php echo  esc_html__("Global attribute", "us-barcode-scanner"); ?></option>
                                    <!-- <option value="locations" <?php echo $field["type"] == "locations" ? wp_kses_post("selected='selected'") : ""; ?>><?php echo  esc_html__("Locations", "us-barcode-scanner"); ?></option> -->
                                    <option value="usbs_date" <?php echo $field["type"] == "usbs_date" ? wp_kses_post("selected='selected'") : ""; ?>><?php echo  esc_html__("Date", "us-barcode-scanner"); ?></option>
                                    <!-- integrations -->
                                    <?php if (BatchNumbers::status()) : ?>
                                        <option value="usbs_wcpbn" <?php echo $field["type"] == "usbs_wcpbn" ? wp_kses_post("selected='selected'") : ""; ?>><?php echo  esc_html__("Product Batch Numbers", "us-barcode-scanner"); ?></option>
                                    <?php endif; ?>
                                    <?php if (BatchNumbersWebis::status()) : ?>
                                        <option value="usbs_webis" <?php echo $field["type"] == "usbs_webis" ? wp_kses_post("selected='selected'") : ""; ?>><?php echo  esc_html__("Product Batch & Tracking", "us-barcode-scanner"); ?></option>
                                    <?php endif; ?>
                                </select>
                            </td>
                        </tr>

                        <tr class="show_in_products_list" style="<?php echo !$isMobile ? "display: none;" : ""; ?>">
                            <td scope="row" style="text-align:left; padding: 0 10px 0 10px; width: 110px; box-sizing: border-box;">
                                <label onclick="WebbsFieldsChToggle(this, 'usbs_field_show_in_products_list')" data-fid="<?php echo esc_attr($field["id"]); ?>">
                                    <?php echo esc_html__("Show in mobile list", "us-barcode-scanner"); ?>
                                </label>
                            </td>
                            <td style="padding: 0 0 5px;">
                                <!-- checkbox -->
                                <?php $checked = $field["show_in_products_list"] == 1 ? ' checked=checked ' : ''; ?>
                                <input type="checkbox" class="usbs_field_show_in_products_list" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> data-fid="<?php echo esc_attr($field["id"]); ?>" onchange="WebbsSettingsCheckboxChange(`#bs-settings-fields-tab .show_in_products_list input[data-fid='<?php echo esc_attr($field['id']); ?>']`, this.checked ? '1' : '0')" />
                                <input type="hidden" name="fields[<?php echo esc_attr($field["id"]); ?>][show_in_products_list]" value="<?php echo $checked ? esc_attr("1") : esc_attr("0"); ?>" data-fid="<?php echo esc_attr($field["id"]); ?>" />
                            </td>
                        </tr>

                        <tr>
                            <td scope="row" style="text-align:left; padding: 0 10px 0 10px; width: 110px; box-sizing: border-box;">
                                <?php echo esc_html__("Field label", "us-barcode-scanner"); ?>
                            </td>
                            <td style="padding: 0;">
                                <input type="text" class="usbs_field_label" name="fields[<?php echo esc_attr($field["id"]); ?>][field_label]" value="<?php echo esc_attr($field["field_label"]); ?>" style="width: 177px;" />
                            </td>
                        </tr>
                        <tr>
                            <td scope="row" style="text-align:left; padding: 0 10px 0 10px; width: 110px; box-sizing: border-box;">
                                <?php echo esc_html__("Meta name", "us-barcode-scanner"); ?>
                            </td>
                            <td style="padding: 0;">
                                <input type="text" class="usbs_field_name" name="fields[<?php echo esc_attr($field["id"]); ?>][field_name]" value="<?php echo esc_attr($field["field_name"]); ?>" style="width: 177px;" />
                                <button type="button" class="cf_check_name">Check</button>
                                <div style="display: inline-block; position: relative; width: 1px;">
                                    <span class="cf_check_name_result"></span>
                                </div>
                            </td>
                        </tr>
                        <tr class="global_attribute">
                            <td scope="row" style="text-align:left; padding: 0 10px 0 10px; width: 110px; box-sizing: border-box;">
                                <?php echo esc_html__("Global attribute", "us-barcode-scanner"); ?>
                            </td>
                            <td style="padding: 0;">
                                <select class="usbs_field_global_attribute" name="fields[<?php echo esc_attr($field["id"]); ?>][attribute_id]" style="width: 177px;">
                                    <?php foreach ($globalAttributes as $key => $value): ?>
                                        <option value="<?php echo esc_attr($value->attribute_id) ?>" <?php echo $field["attribute_id"] == $value->attribute_id ? wp_kses_post("selected='selected'") : ""; ?>><?php echo  esc_html($value->attribute_label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr class="type_select">
                            <td scope="row" style="text-align:left; padding: 0 10px 0 10px; width: 110px; box-sizing: border-box;">
                                <?php echo esc_html__("Options", "us-barcode-scanner"); ?>
                            </td>
                            <td style="padding: 5px 0;">
                                <div class="type_select_options">
                                    <?php $options = isset($field["options"]) && $field["options"] ? @json_decode($field["options"], false) : null; ?>
                                    <?php if ($options) : ?>
                                        <?php $optionIndex = 0; ?>
                                        <?php foreach ($options as $key => $value) : ?>
                                            <div class="type_select_option">
                                                <input type="text" name="fields[<?php echo esc_attr($field["id"]); ?>][options][<?php echo esc_attr($optionIndex); ?>][key]" value="<?php echo esc_attr($key); ?>" />
                                                <input type="text" name="fields[<?php echo esc_attr($field["id"]); ?>][options][<?php echo esc_attr($optionIndex); ?>][value]" value="<?php echo esc_attr($value); ?>" />
                                                <span class="type_select_option_remove">✖</span>
                                            </div>
                                            <?php $optionIndex++; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <span class="type_select_option_add">+ <?php echo esc_html__("Add new", "us-barcode-scanner"); ?></span>
                            </td>
                        </tr>
                        <?php  ?>
                        <tr style="<?php echo $isMobile ? wp_kses_post("display: none;") : ""; ?>">
                            <td scope="row" style="text-align:left; padding: 0 10px 0 10px; width: 110px; box-sizing: border-box;">
                                <?php echo esc_html__("Label position", "us-barcode-scanner"); ?>
                            </td>
                            <td style="padding: 0;">
                                <select class="usbs_field_label_position" name="fields[<?php echo esc_attr($field["id"]); ?>][label_position]" style="width: 177px;">
                                    <option value="left" <?php echo $field["label_position"] == "left" ? wp_kses_post("selected='selected'") : ""; ?>><?php echo  esc_html__("Left", "us-barcode-scanner"); ?></option>
                                    <option value="right" <?php echo $field["label_position"] == "right" ? wp_kses_post("selected='selected'") : ""; ?>><?php echo  esc_html__("Right", "us-barcode-scanner"); ?></option>
                                    <option value="top" <?php echo $field["label_position"] == "top" ? wp_kses_post("selected='selected'") : ""; ?>><?php echo  esc_html__("Top", "us-barcode-scanner"); ?></option>
                                    <option value="bottom" <?php echo $field["label_position"] == "bottom" ? wp_kses_post("selected='selected'") : ""; ?>><?php echo  esc_html__("Bottom", "us-barcode-scanner"); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td scope="row" style="text-align:left; padding: 0 10px 0 10px; width: 110px; box-sizing: border-box;">
                                <?php echo esc_html__("Height", "us-barcode-scanner"); ?>
                            </td>
                            <td style="padding: 0;">
                                <input type="text" class="usbs_field_height" name="fields[<?php echo esc_attr($field["id"]); ?>][field_height]" value="<?php echo esc_attr($field["field_height"]); ?>" style="width: 100px;" /> px
                            </td>
                        </tr>
                        <tr>
                            <td scope="row" style="text-align:left; padding: 0 10px 0 10px; width: 110px; box-sizing: border-box;">
                                <?php echo esc_html__("Label width", "us-barcode-scanner"); ?>
                            </td>
                            <td style="padding: 0;">
                                <input type="number" class="usbs_label_width" name="fields[<?php echo esc_attr($field["id"]); ?>][label_width]" value="<?php echo esc_attr($field["label_width"]); ?>" style="width: 100px" /> %
                            </td>
                        </tr>
                    </table>

                    <span class="dashicons dashicons-trash settings_field_remove" title="<?php echo  esc_html__("Remove field", "us-barcode-scanner"); ?>" data-fid="<?php echo esc_attr($field["id"]); ?>"></span>
                </div>
            </div>
        </div>
    </td>
</tr>