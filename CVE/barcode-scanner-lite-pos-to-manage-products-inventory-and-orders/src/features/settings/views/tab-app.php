<div style="display: flex; flex-wrap: wrap; align-items: flex-start;">
    <form id="bs-settings-app-tab" method="POST" action="<?php echo esc_attr($actualLink); ?>">
        <input type="hidden" name="tab" value="app" />

        <!-- search -->
        <?php if (get_option('permalink_structure') == "") : ?>
            <div style="font-weight: bold; padding: 15px 0 0;">
                <?php $message = __('To connect app to the  your WordPress you need to set Permalink  to any option except "Plan" (%s). Usually, for SEO optimization, most of the WP users prefer option "Post name". Or %s for more details.', "us-barcode-scanner"); ?>
                <?php printf(
                    wp_kses_post($message),
                    '<a href="https://www.ukrsolution.com/images/Barcode-Scanner/permalink-setting.png" target="_blank">' . esc_html__("see screenshot", "us-barcode-scanner") . '</a>',
                    '<a href="https://www.ukrsolution.com/ContactUs" target="_blank">' . esc_html__("contact us", "us-barcode-scanner") . '</a>'
                );
                ?>
            </div>
        <?php endif; ?>

        <div style="padding: 25px 0 0;">
            <div style="font-size: 20px; margin-bottom: 25px;"><?php echo esc_html__("Get one time password here:", "us-barcode-scanner"); ?></div>
            <div style="padding-bottom: 20px;">
                <?php echo wp_kses_post('1. All app users must be registered on your website.', "us-barcode-scanner"); ?><br />
                <?php echo wp_kses_post('2. Even low privilege account is enough (e.g. user with "Customer" role).', "us-barcode-scanner"); ?><br />
                <?php echo wp_kses_post('3. Find & add users below to generate the one time passwords.', "us-barcode-scanner"); ?><br />
            </div>
            <label>
                <b><?php echo esc_html__("Find user:", "us-barcode-scanner"); ?></b>
                <span style="position: relative;">
                    <input type="text" placeholder="<?php echo esc_html__("Type username, email or name", "us-barcode-scanner"); ?>" class="app-users-search-input" style="width: 250px;" />
                    <span style="position: relative;">
                        <span style="position: absolute; top: -5px; left: 0; display: none;" id="app-users-search-preloader">
                            <span id="barcode-scanner-action-preloader">
                                <span class="a4b-action-preloader-icon"></span>
                            </span>
                        </span>
                    </span>
                    <input type="hidden" name="addAppUsersPermissions" id="add-app-user-permissions" />
                    <input type="hidden" name="removeAppUsersPermissions" id="remove-app-user-permissions" />
                    <ul class="app-users-search-list"></ul>
                </span>
                <span class="app-users-loader">Updating</span>
            </label>
        </div>
        <div style="display: flex;">
            <div>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <td style="padding-top: 5px;">
                                <!-- roles -->
                                <table class="bs-settings-app-users">
                                    <thead>
                                        <tr>
                                            <td><?php echo esc_html__("ID", "us-barcode-scanner"); ?></td>
                                            <td><?php echo esc_html__("Name", "us-barcode-scanner"); ?></td>
                                            <td><?php echo esc_html__("Status", "us-barcode-scanner"); ?></td>
                                            <td style="display: none;"><?php echo esc_html__("Instructions", "us-barcode-scanner"); ?></td>
                                            <td><?php echo esc_html__("Actions", "us-barcode-scanner"); ?></td>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $users = $settings->getAppUsersPermissions(); ?>
                                        <?php foreach ($users as $key => $user) : ?>
                                            <?php $token = $user->get($settings->userAppPermissionKey); ?>
                                            <?php if (!$token) continue; ?>
                                            <tr>
                                                <td><?php echo esc_attr($user->ID); ?></td>
                                                <?php $fullName = trim($user->first_name . " " . $user->last_name); ?>
                                                <td style="white-space: nowrap;" id="user-full-name"><?php echo $fullName ? esc_html($fullName . " (" . $user->user_login . ")") : esc_html($user->user_login); ?></td>
                                                <td style="display: none;">
                                                    <?php $url =  get_site_url() . "/usbs-mobile?u=" . $token; ?>
                                                    <input type="hidden" value="<?php echo esc_url($url); ?>" id="app-auth-link-<?php echo esc_attr($user->ID); ?>" />
                                                    <button type="button" data-id="<?php echo esc_attr($user->ID); ?>" class="button show-app-user-i"><?php echo esc_html__("App installation & Login instructions", "us-barcode-scanner"); ?></button>
                                                </td>
                                                <td>
                                                    <button type="button" data-id="<?php echo esc_attr($user->ID); ?>" class="button remove-app-user-p"><?php echo esc_html__("New password", "us-barcode-scanner"); ?></button>
                                                    <button type="button" data-id="<?php echo esc_attr($user->ID); ?>" class="button remove-app-user-p"><?php echo esc_html__("Remove", "us-barcode-scanner"); ?></button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (!$users) : ?>
                                            <tr>
                                                <td colspan="4"><?php echo esc_html__("Empty list", "us-barcode-scanner"); ?></td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div style="padding-top: 43px; display: none;" id="app-user-instructions">
                <?php echo wp_sprintf(wp_kses_post("What smartphone %s has ?", "us-barcode-scanner"), '<span id="app-user-full-name"></span>'); ?>
                <span class="app-device" data-device="android">Android</span> / <span class="app-device" data-device="iphone">iPhone</span>
                <br />
                <br />
                <div style="display: none;" id="next-instructions">
                    <b><?php echo esc_html__("Send next instructions to user by email or messenger:", "us-barcode-scanner"); ?></b>
                    <br />
                    <textarea rows="5" cols="70" id="next-instructions-message" style="margin-top: 5px;"></textarea>
                    <!-- < ?php echo esc_html__("1. Install Mobile App:", "us-barcode-scanner"); ?>
                <span class="app-store-link"></span>
                <br />
                < ?php echo esc_html__("2. Login by following link:", "us-barcode-scanner"); ?>
                <span class="app-auth-link"></span>
                <br />
                <br />
                <i>
                    < ?php echo esc_html__("Note:", "us-barcode-scanner"); ?>
                    < ?php echo esc_html__("Login link created for your account only, do not share your account with any other person.", "us-barcode-scanner"); ?>
                </i> -->
                </div>
            </div>
        </div>

        <div class="submit" style="width: 0px; height: 0px; overflow: hidden;">
            <input type="submit" class="button button-primary" value="<?php echo esc_html__("Save Changes", "us-barcode-scanner"); ?>">
        </div>
    </form>
    <div style="padding: 25px 0 0 40px;">
        <div style="padding-left: 13px; font-size: 20px; margin-bottom: 25px;"><?php echo esc_html__("Get mobile app here:", "us-barcode-scanner"); ?></div>
        <div style="display: flex; flex-wrap: wrap; align-items: flex-start;">
            <div style="padding: 0 10px;">
                <a href='https://play.google.com/store/apps/details?id=com.ukrsolution.barcodescanner&pcampaignid=pcampaignidMKT-Other-global-all-co-prtnr-py-PartBadge-Mar2515-1' target="_blank">
                    <img alt='Get it on Google Play' src='https://play.google.com/intl/en_us/badges/static/images/badges/en_badge_web_generic.png' width="150" />
                </a>
                <br /><img src="<?php echo esc_url(USBS_PLUGIN_BASE_URL) ?>/src/features/settings/assets/images/google-play-link.png" alt="Inventory Manager for WP & Woo" width="150" />
            </div>
            <div style="padding: 0 10px;">
                <a href="https://apps.apple.com/us/app/inventory-manager-for-wp-woo/id1628782104?itsct=apps_box_badge&amp;itscg=30200" style="display: inline-block; overflow: hidden; border-radius: 13px; width: 135px; height: 45px; margin: 6px 0;" target="_blank">
                    <img src="https://tools.applemediaservices.com/api/badges/download-on-the-app-store/black/en-us?size=250x83&amp;releaseDate=1655856000" alt="Download on the App Store" style="border-radius: 13px; width: 135px; height: 45px;" width="135">
                </a>
                <br /><img src="<?php echo esc_url(USBS_PLUGIN_BASE_URL) ?>/src/features/settings/assets/images/app-store-link.png" alt="Inventory Manager for WP & Woo" width="150" />
            </div>
        </div>
    </div>
</div>