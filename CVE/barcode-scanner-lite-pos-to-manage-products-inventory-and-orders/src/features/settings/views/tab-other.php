<?php

use UkrSolution\BarcodeScanner\features\sounds\Sounds;
?>
<form id="bs-settings-css-tab" method="POST" action="<?php echo esc_url($actualLink); ?>" enctype="multipart/form-data">
    <input type="hidden" name="tab" value="css" />
    <input type="hidden" name="storage" value="table" />
    <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>" />
    <table class="form-table">
        <tbody>
            <tr>
                <th scope="row" style="width: 240px;">
                    <?php echo esc_html__("Custom CSS", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <?php $customCss = $settings->getSettings("customCss"); ?>
                    <textarea name="customCss" rows="6" cols="60"><?php echo $customCss ? wp_kses_post(stripslashes($customCss->value)) : ""; ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row" style="width: 240px;">
                    <?php echo esc_html__("Custom CSS for Mobile app", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <?php $customCssMobile = $settings->getSettings("customCssMobile"); ?>
                    <textarea name="customCssMobile" rows="6" cols="60"><?php echo $customCssMobile ? wp_kses_post(stripslashes($customCssMobile->value)) : ""; ?></textarea>
                </td>
            </tr>

            <?php
            ?>
            <tr id="bs_debug">
                <th scope="row">
                    <?php echo esc_html__("Sound effects", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <?php
                    $sounds = new Sounds();
                    $list = $sounds->getList();
                    ?>
                    <!-- Increase -->
                    <div class="sound-block">
                        <!-- preview -->
                        <div style="display: flex; align-items: center;">
                            <b><?php echo esc_html__("Success", "us-barcode-scanner"); ?></b>
                            <audio controls>
                                <source src="<?php echo wp_kses_post($list["increase"]); ?>" type="audio/mpeg">
                                <?php echo esc_html__("Your browser does not support the audio element.", "us-barcode-scanner"); ?>
                            </audio>
                        </div>
                        <!-- upload -->
                        <div style="display: flex; align-items: center;">
                            <b><?php echo esc_html__("Upload new", "us-barcode-scanner"); ?></b> &nbsp;
                            <input type="file" accept=".mp3" name="increaseFile" />
                        </div>
                    </div><br />

                    <!-- Decrease -->
                    <div class="sound-block">
                        <!-- preview -->
                        <div style="display: flex; align-items: center;">
                            <b><?php echo esc_html__("Decrease value", "us-barcode-scanner"); ?></b>
                            <audio controls>
                                <source src="<?php echo wp_kses_post($list["decrease"]); ?>" type="audio/mpeg">
                                <?php echo esc_html__("Your browser does not support the audio element.", "us-barcode-scanner"); ?>
                            </audio>
                        </div>
                        <!-- upload -->
                        <div style="display: flex; align-items: center;">
                            <b><?php echo esc_html__("Upload new", "us-barcode-scanner"); ?></b> &nbsp;
                            <input type="file" accept=".mp3" name="decreaseFile" />
                        </div>
                    </div><br />

                    <!-- Fail -->
                    <div class="sound-block">
                        <!-- preview -->
                        <div style="display: flex; align-items: center;">
                            <b><?php echo esc_html__("Fail", "us-barcode-scanner"); ?></b>
                            <audio controls>
                                <source src="<?php echo wp_kses_post($list["fail"]); ?>" type="audio/mpeg">
                                <?php echo esc_html__("Your browser does not support the audio element.", "us-barcode-scanner"); ?>
                            </audio>
                        </div>
                        <!-- upload -->
                        <div style="display: flex; align-items: center;">
                            <b><?php echo esc_html__("Upload new", "us-barcode-scanner"); ?></b> &nbsp;
                            <input type="file" accept=".mp3" name="failFile" />
                        </div>
                    </div>
                </td>
            </tr>
            <tr class="usbs-section-label">
                <td>
                    <h2><?php echo esc_html__("Debug", "us-barcode-scanner"); ?></h2>
                </td>
            </tr>
            <tr id="bs_debug">
                <th scope="row">
                    <?php echo esc_html__("Debug information", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <label>
                        <?php
                        $field = $settings->getSettings("debugInfo");
                        $value = $field === null ? $settings->getField("general", "debugInfo", "") : $field->value;

                        if ($value === "on") {
                            $checked = ' checked=checked ';
                        } else {
                            $checked = '';
                        }
                        ?>
                        <input type="checkbox" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> onchange="WebbsSettingsCheckboxChange(`#bs_debug input[name='debugInfo']`,this.checked ? 'on' : 'off')" />
                        <input type="hidden" name="debugInfo" value="<?php echo esc_attr($checked ? "on" : "off"); ?>" />
                        <?php echo esc_html__("Enable", "us-barcode-scanner"); ?>
                    </label>
                </td>
            </tr>
            <tr id="bs_debug">
                <th scope="row">
                    <?php echo esc_html__("Reset all settings to default", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <?php
                    $link = admin_url("/admin.php?page=barcode-scanner-settings-reset&tab=css");
                    ?>
                    <a href="<?php echo esc_url($link); ?>" class="usbs-btn" id="reset-all-settings-to-default" onclick="return confirm('<?php echo esc_html__("All current configuration will be ERASED and replaced with the default one, proceed?", "us-barcode-scanner"); ?>');"><?php echo esc_html__("Reset", "us-barcode-scanner"); ?></a>

                </td>
            </tr>
            <?php
            ?>
            <?php if ($wpml) : ?>
                <tr id="bs_wpml">
                    <th scope="row">
                        <?php echo esc_html__("wpml languages", "us-barcode-scanner"); ?>
                    </th>
                    <td>
                        <label>
                            <?php
                            $field = $settings->getSettings("wpmlUpdateProductsTree");
                            $value = $field === null ? $settings->getField("general", "wpmlUpdateProductsTree", "") : $field->value;

                            if ($value === "on") {
                                $checked = ' checked=checked ';
                            } else {
                                $checked = '';
                            }
                            ?>
                            <input type="checkbox" <?php esc_html_e($checked, 'us-barcode-scanner'); ?> onchange="WebbsSettingsCheckboxChange(`#bs_wpml input[name='wpmlUpdateProductsTree']`,this.checked ? 'on' : 'off')" />
                            <input type="hidden" name="wpmlUpdateProductsTree" value="<?php echo esc_attr($checked ? "on" : "off"); ?>" />
                            <?php echo esc_html__("Update all products", "us-barcode-scanner"); ?>
                        </label><br />
                        <i><?php echo esc_html__("If your wpml already configured to sync data between product translations,<br/>then you DON'T need to enable this option as it may cause double increase of qty.", "us-barcode-scanner"); ?></i>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <div class="submit">
        <input type="submit" class="button button-primary" value="<?php echo esc_html__("Save Changes", "us-barcode-scanner"); ?>">
    </div>
</form>