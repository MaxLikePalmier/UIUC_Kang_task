<form id="bs-settings-permissions-tab" method="POST" action="<?php echo esc_url($actualLink); ?>">
    <input type="hidden" name="tab" value="permissions" />
    <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>" />
    <table class="form-table">
        <tbody>
            <tr>
                <th scope="row" colspan="2" style="padding-bottom: 0;">
                    <b><?php echo esc_html__("Tabs permissions:", "us-barcode-scanner"); ?></b>
                <th>
            </tr>
            <tr>
                <td colspan="2">
                    <!-- roles -->
                    <table class="bs-settings-roles-list">
                        <tr>
                            <td><?php echo esc_html__("Role", "us-barcode-scanner"); ?></td>
                            <td><?php echo esc_html__("Products tab", "us-barcode-scanner"); ?></td>
                            <td>
                                <div style="height: 100%; width: 1px; border-left: 1px solid;">&nbsp;</div>
                            </td>
                            <td><?php echo esc_html__("New product", "us-barcode-scanner"); ?></td>
                            <td>
                                <div style="height: 100%; width: 1px; border-left: 1px solid;">&nbsp;</div>
                            </td>
                            <td><?php echo esc_html__("Orders tab", "us-barcode-scanner"); ?></td>
                            <td>
                                <?php echo esc_html__('Only "My Orders"', "us-barcode-scanner"); ?>
                                <span style="position: relative; display: inline-block; width: 20px; height: 13px;">
                                    <span style="font-size: 16px; position: absolute;" class="dashicons dashicons-info-outline" title="<?php echo esc_attr__("If enabled, user will manage orders only created by the user itself.", "us-barcode-scanner"); ?>"></span>
                                </span>
                            </td>
                            <td><?php echo esc_html__("Show prices", "us-barcode-scanner"); ?></td>
                            <td>
                                <div style="height: 100%; width: 1px; border-left: 1px solid;">&nbsp;</div>
                            </td>
                            <td><?php echo esc_html__("New order", "us-barcode-scanner"); ?></td>
                            <td><?php echo esc_html__("Allow to link customer", "us-barcode-scanner"); ?></td>
                            <td>
                                <div style="height: 100%; width: 1px; border-left: 1px solid;">&nbsp;</div>
                            </td>
                            <td>
                                <?php echo esc_html__("Frontend popup", "us-barcode-scanner"); ?>
                                <span style="position: relative; display: inline-block; width: 20px; height: 13px;">
                                    <span style="font-size: 16px; position: absolute;" class="dashicons dashicons-info-outline" title="<?php echo esc_attr__("Allows to display search popup for users on frontend/website.", "us-barcode-scanner"); ?>"></span>
                                </span>
                            </td>
                            <td>
                                <div style="height: 100%; width: 1px; border-left: 1px solid;">&nbsp;</div>
                            </td>
                            <td><?php echo esc_html__("Settings", "us-barcode-scanner"); ?></td>
                            <td><?php echo esc_html__("Logs", "us-barcode-scanner"); ?></td>
                        </tr>
                        <?php foreach ($settings->getRoles() as $key => $role) : ?>
                            <tr>
                                <!-- Role -->
                                <td><?php echo esc_html($role["name"]); ?></td>
                                <!-- Products tab -->
                                <?php $permissions = $settings->getRolePermissions($key); ?>
                                <td style="text-align: center;">
                                    <?php
                                    if ($permissions && isset($permissions["inventory"]) && $permissions["inventory"]) $checked = ' checked=checked ';
                                    else $checked = '';
                                    $parentProduct = $checked;
                                    ?>
                                    <input type="hidden" name="rolesPermissions[<?php echo esc_attr($key); ?>][inventory]" value="0" />
                                    <input type="checkbox" name="rolesPermissions[<?php echo esc_attr($key); ?>][inventory]" value="1" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> />
                                </td>
                                <td>&nbsp;</td>
                                <!-- New product -->
                                <td style="text-align: center;">
                                    <?php
                                    if ($permissions && isset($permissions["newprod"]) && $permissions["newprod"]) $checked = ' checked=checked ';
                                    else $checked = '';
                                    ?>
                                    <input type="hidden" name="rolesPermissions[<?php echo esc_attr($key); ?>][newprod]" value="0" />
                                    <input type="checkbox" name="rolesPermissions[<?php echo esc_attr($key); ?>][newprod]" value="1" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> />
                                </td>
                                <td>&nbsp;</td>
                                <!-- Orders tab -->
                                <td style="text-align: center;">
                                    <?php
                                    if ($permissions && isset($permissions["orders"]) && $permissions["orders"]) $checked = ' checked=checked ';
                                    else $checked = '';
                                    ?>
                                    <input type="hidden" name="rolesPermissions[<?php echo esc_attr($key); ?>][orders]" value="0" />
                                    <input type="checkbox" name="rolesPermissions[<?php echo esc_attr($key); ?>][orders]" value="1" parent="orders" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> />
                                </td>
                                <!-- Only "My Orders" -->
                                <td style="text-align: center;">
                                    <?php
                                    if ($permissions && isset($permissions["onlymy"]) && $permissions["onlymy"]) $checked = ' checked=checked ';
                                    else $checked = '';
                                    $parentNewOrder = $checked;
                                    ?>
                                    <input type="hidden" name="rolesPermissions[<?php echo esc_attr($key); ?>][onlymy]" value="0" />
                                    <input type="checkbox" name="rolesPermissions[<?php echo esc_attr($key); ?>][onlymy]" value="1" group="orders" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> />
                                </td>
                                <!-- Show prices -->
                                <td style="text-align: center;">
                                    <?php
                                    if ($permissions && isset($permissions["show_prices"]) && $permissions["show_prices"]) $checked = ' checked=checked ';
                                    else $checked = '';
                                    $parentNewOrder = $checked;
                                    ?>
                                    <input type="hidden" name="rolesPermissions[<?php echo esc_attr($key); ?>][show_prices]" value="0" />
                                    <input type="checkbox" name="rolesPermissions[<?php echo esc_attr($key); ?>][show_prices]" value="1" group="orders" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> />
                                </td>
                                <td>&nbsp;</td>
                                <!-- New order -->
                                <td style="text-align: center;">
                                    <?php
                                    if ($permissions && isset($permissions["cart"]) && $permissions["cart"]) $checked = ' checked=checked ';
                                    else $checked = '';
                                    $parentNewOrder = $checked;
                                    ?>
                                    <input type="hidden" name="rolesPermissions[<?php echo esc_attr($key); ?>][cart]" value="0" />
                                    <input type="checkbox" name="rolesPermissions[<?php echo esc_attr($key); ?>][cart]" value="1" parent="order" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> />
                                </td>
                                <!-- Allow to link customer -->
                                <td style="text-align: center;">
                                    <?php
                                    if ($permissions && isset($permissions["linkcustomer"]) && $permissions["linkcustomer"]) $checked = ' checked=checked ';
                                    else $checked = '';
                                    ?>
                                    <input type="hidden" name="rolesPermissions[<?php echo esc_attr($key); ?>][linkcustomer]" value="0" />
                                    <input type="checkbox" name="rolesPermissions[<?php echo esc_attr($key); ?>][linkcustomer]" value="1" group="order" <?php echo $parentNewOrder ? '' : 'disabled="disabled"'; ?> <?php esc_html_e($checked, 'us-barcode-scanner'); ?> />
                                </td>
                                <td>&nbsp;</td>
                                <!-- Frontend popup -->
                                <?php
                                ?>
                                <td style="text-align: center;">
                                    <?php
                                    if ($permissions && isset($permissions["frontend"]) && $permissions["frontend"]) $checked = ' checked=checked ';
                                    else $checked = '';
                                    ?>
                                    <input type="hidden" name="rolesPermissions[<?php echo esc_attr($key); ?>][frontend]" value="0" />
                                    <input type="checkbox" name="rolesPermissions[<?php echo esc_attr($key); ?>][frontend]" value="1" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> />
                                </td>
                                <?php
                                ?>
                                <td>&nbsp;</td>
                                <!-- Settings -->
                                <td style="text-align: center;">
                                    <?php
                                    if ($permissions && isset($permissions["plugin_settings"]) && $permissions["plugin_settings"]) $checked = ' checked=checked ';
                                    else $checked = '';
                                    ?>
                                    <input type="hidden" name="rolesPermissions[<?php echo esc_attr($key); ?>][plugin_settings]" value="<?php echo $key == "administrator" ? 1 : 0 ?>" />
                                    <input type="checkbox" name="rolesPermissions[<?php echo esc_attr($key); ?>][plugin_settings]" value="1" <?php if ($key == "administrator") echo 'disabled="disabled"' ?> <?php esc_html_e($checked, 'us-barcode-scanner'); ?> />
                                </td>
                                <!-- Logs -->
                                <td style="text-align: center;">
                                    <?php
                                    if ($permissions && isset($permissions["plugin_logs"]) && $permissions["plugin_logs"]) $checked = ' checked=checked ';
                                    else $checked = '';
                                    ?>
                                    <input type="hidden" name="rolesPermissions[<?php echo esc_attr($key); ?>][plugin_logs]" value="0" />
                                    <input type="checkbox" name="rolesPermissions[<?php echo esc_attr($key); ?>][plugin_logs]" value="1" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> />
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>
    <div class="submit">
        <input type="submit" class="button button-primary" value="<?php echo esc_html__("Save Changes", "us-barcode-scanner"); ?>">
    </div>
</form>

<script>
    jQuery(document).ready(() => {
        jQuery(".bs-settings-roles-list tr input[type='checkbox']").change((e) => {
            const parent = jQuery(e.target).attr("parent");
            const group = jQuery(e.target).attr("group");
            const status = jQuery(e.target).is(":checked");

            if (parent && status) {
                jQuery(e.target).closest("tr").find("input[type='checkbox'][group='" + parent + "']").removeAttr("disabled");
            } else {
                jQuery(e.target).closest("tr").find("input[type='checkbox'][group='" + parent + "']").prop("checked", false);
                jQuery(e.target).closest("tr").find("input[type='checkbox'][group='" + parent + "']").attr("disabled", "disabled");
            }
        });

        jQuery(".bs-settings-roles-list tr input[type='checkbox']:not([data-need-permissions])").change();

        <?php
        ?>
    });
</script>