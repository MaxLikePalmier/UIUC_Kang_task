<?php
?>
<?php
$dt = new \DateTime("now");
$filterAction = isset($_GET["action"]) ? sanitize_text_field($_GET["action"]) : "";
$filterUser = isset($_GET["user"]) ? sanitize_text_field($_GET["user"]) : "";
$filterDatetimeFrom = isset($_GET["dt-from"]) ? sanitize_text_field($_GET["dt-from"]) : $dt->format("Y-m-d");
$filterDatetimeTo = isset($_GET["dt-to"]) ? sanitize_text_field($_GET["dt-to"]) : $dt->format("Y-m-d");
$filterPage = isset($_GET["p"]) ? (int)sanitize_text_field($_GET["p"]) : 1;
$filterIPP = isset($_GET["ipp"]) ? (int)sanitize_text_field($_GET["ipp"]) : 10;
$filterType = isset($_GET["type"]) ? sanitize_text_field($_GET["type"]) : "";
$filterUp = isset($_GET["up"]) ? sanitize_text_field($_GET["up"]) : "";
?>
<a href="#barcode-scanner-settings"></a>
<div id="bs-settings-page">
    <h2><?php echo esc_html__("Barcode Scanner logs", "us-barcode-scanner"); ?></h2>
    <div>
        <div class="tabs">
            <form id="barcode-scan-logs" method="GET">
                <input type="hidden" name="page" value="<?php echo esc_html_e($_GET["page"]); ?>" />
                <div>
                    Filter:
                    <select name="action">
                        <option value=""><?php echo esc_html__("Any action", "us-barcode-scanner"); ?></option>
                        <?php foreach ($logs->actions as $action => $label) : ?>
                            <?php $selected = $filterAction === $action ? "selected=" . $filterAction : ""; ?>
                            <option value="<?php echo esc_html_e($action); ?>" <?php echo esc_html_e($selected); ?>><?php echo esc_html_e($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="user">
                        <option value=""><?php echo esc_html__("Any user", "us-barcode-scanner"); ?></option>
                        <?php foreach ($logs->users as $user) : ?>
                            <?php $selected = $filterUser == $user["ID"] ? "selected=" . $filterUser : ""; ?>
                            <option value="<?php echo esc_html_e($user["ID"]); ?>" <?php echo esc_html_e($selected); ?>><?php echo esc_html_e($user["name"]); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="type">
                        <option value=""><?php echo esc_html__("Any type", "us-barcode-scanner"); ?></option>
                        <option value="product" <?php echo $filterType === "product" ? "selected='selected'" : ""; ?>><?php echo esc_html__("Products", "us-barcode-scanner"); ?></option>
                        <option value="order" <?php echo $filterType === "order" ? "selected='selected'" : ""; ?>><?php echo esc_html__("Orders", "us-barcode-scanner"); ?></option>
                        <option value="user" <?php echo $filterType === "user" ? "selected='selected'" : ""; ?>><?php echo esc_html__("Users", "us-barcode-scanner"); ?></option>
                    </select>
                    <input class="bs-datepicker-from" style="width: 96px; vertical-align: middle;" type="text" name="dt-from" value="<?php echo esc_attr($filterDatetimeFrom); ?>" title="<?php echo esc_attr("From date", "us-barcode-scanner"); ?>" placeholder="<?php echo esc_attr("From date", "us-barcode-scanner"); ?>" autocomplete="off" />
                    <input class="bs-datepicker-to" style="width: 96px; vertical-align: middle;" type="text" name="dt-to" value="<?php echo esc_attr($filterDatetimeTo); ?>" title="<?php echo esc_attr("To date", "us-barcode-scanner"); ?>" placeholder="<?php echo esc_attr("To date", "us-barcode-scanner"); ?>" autocomplete="off" />
                    <select name="ipp" style="width: 65px;">
                        <option value="" disabled><?php echo esc_html__("Items per page", "us-barcode-scanner"); ?> &nbsp;</option>
                        <option value="10" <?php echo $filterIPP === 10 ? wp_kses_post("selected='selected'") : ""; ?>>10</option>
                        <option value="20" <?php echo $filterIPP === 20 ? wp_kses_post("selected='selected'") : ""; ?>>20</option>
                        <option value="50" <?php echo $filterIPP === 50 ? wp_kses_post("selected='selected'") : ""; ?>>50</option>
                        <option value="100" <?php echo $filterIPP === 100 ? wp_kses_post("selected='selected'") : ""; ?>>100</option>
                    </select>
                    <!-- <label>
                        <input type="checkbox" name="up" <?php echo $filterUp === "" ? "" : "checked='checked'"; ?> /> uniq items
                    </label> &nbsp; -->
                    <input id="submit" type="submit" class="button button-primary" value="<?php echo esc_attr("Apply", "us-barcode-scanner"); ?>">
                    &nbsp;&nbsp;
                    <input id="usbs_export_log" type="button" class="button button-general" onclick="WebBarcodeScannerExportLog()" value="<?php echo esc_attr("Export", "us-barcode-scanner"); ?>">
                    <span id="usbs_exporting_log" style="padding-left: 10px;"></span>
                </div>
                <table class="form-table barcode-scan-logs">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__("User", "us-barcode-scanner"); ?></th>
                            <th><?php echo esc_html__("Date", "us-barcode-scanner"); ?></th>
                            <th><?php echo esc_html__("Time", "us-barcode-scanner"); ?></th>
                            <th><?php echo esc_html__("Item", "us-barcode-scanner"); ?></th>
                            <th><?php echo esc_html__("Action", "us-barcode-scanner"); ?></th>
                            <th><?php echo esc_html__("New value", "us-barcode-scanner"); ?></th>
                            <th><?php echo esc_html__("Old value", "us-barcode-scanner"); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs->records as $record) : ?>
                            <?php
                            $post = get_post($record->post_id);
                            $postId = $record->post_id;

                            if ($post && $post->post_parent) {
                                $postId = $post->post_parent;
                            }
                            ?>
                            <tr>
                                <td>
                                    <?php $user = get_userdata($record->user_id); ?>
                                    <?php if ($user) : ?>
                                        <?php $fullName = trim($user->first_name . " " . $user->last_name); ?>
                                        <a href="<?php echo esc_url(get_edit_user_link($record->user_id)); ?>" target="_blank">
                                            <?php
                                            if ($fullName) {
                                                echo esc_html($fullName) . " (" . esc_html($user->data->user_nicename) . ")";
                                            } else {
                                                echo esc_html($user->data->user_nicename);
                                            }
                                            ?>
                                        </a>
                                    <?php else : echo esc_html__("not found", "us-barcode-scanner");
                                    endif; ?>
                                </td>
                                <td><?php echo esc_html(get_date_from_gmt($record->datetime, "Y-m-d")); ?></td>
                                <td><?php echo esc_html(get_date_from_gmt($record->datetime, "H:i:s")); ?></td>
                                <td>
                                    <div class="item">
                                        <?php if ($record->type == "order_item" && $record->parent_post_id) : ?>
                                            <?php echo esc_html__("For order", "us-barcode-scanner"); ?> <a href="<?php echo esc_url(get_edit_post_link($record->parent_post_id)); ?>" target="_blank">#<?php echo esc_html($record->parent_post_id); ?></a>,
                                            <?php echo esc_html__("fulfilled item", "us-barcode-scanner"); ?> <a href="<?php echo esc_url(get_edit_post_link($postId)); ?>" target="_blank">#<?php echo esc_html($record->post_id); ?></a>
                                        <?php else : ?>
                                            <?php if (in_array($record->action, array("create_order", "update_order_status")) || $record->type === "order") : ?>
                                                Order
                                            <?php elseif (in_array($record->action, array("create_user")) || $record->type === "user") : ?>
                                                User
                                            <?php else : ?>
                                                Product
                                            <?php endif; ?>

                                            <?php if ($record->post_id) : ?>
                                                <?php if ($record->action === "create_user") : ?>
                                                    <a href="<?php echo esc_url(get_edit_user_link($record->post_id)); ?>" target="_blank">#<?php echo esc_html($record->post_id); ?></a>
                                                <?php else : ?>
                                                    <a href="<?php echo esc_url(get_edit_post_link($postId)); ?>" target="_blank">#<?php echo esc_html($record->post_id); ?></a>
                                                <?php endif ?>
                                            <?php else : ?>
                                                #<?php echo esc_html($record->post_id); ?>
                                            <?php endif ?>

                                            <?php if (!in_array($record->action, array("create_order", "update_order_status", "create_user")) || $record->type === "product") : ?>
                                                <?php $title = get_the_title($record->post_id); ?>
                                                <span title="<?php echo esc_attr($title); ?>"><?php echo esc_html($title); ?></span>
                                            <?php endif ?>
                                        <?php endif; ?>

                                    </div>
                                </td>
                                <?php
                                $newValue = explode(": ", $record->value);
                                $label = $record->field ? $logs->getFieldLabel($record->field) : $logs->getFieldLabel($newValue[0]);
                                ?>
                                <td>
                                    <?php
                                    if ($record->action == "update_order_item_meta") {
                                        echo esc_html__("Product fulfillment check", "us-barcode-scanner");
                                    } else if ($record->custom_action) {
                                        echo esc_html($record->custom_action);
                                    } else if ($label != $record->value && $record->value != 0) {
                                        echo esc_html__("Changed", "us-barcode-scanner");
                                        echo ' "' . esc_html($label) . '"';
                                    } else {
                                        echo isset($logs->actions[$record->action]) ? esc_html($logs->actions[$record->action]) : esc_html($record->action);
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="value">
                                        <?php
                                        if ($record->action == "update_order_item_meta") {
                                            echo $record->value == 1 ? esc_html__("Item Found", "us-barcode-scanner") : esc_html__("Uncheck", "us-barcode-scanner");
                                        } else {
                                            echo count($newValue) === 2 ?  esc_html($newValue[1]) : esc_html($record->value);
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="value">
                                        <?php echo esc_html($record->old_value) ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="barcode-scan-logs-paging">
                    <ul>
                        <?php if ($logs->recordsTotal > $logs->recordsPerPage) : ?>
                            <?php $pages = (int)ceil($logs->recordsTotal / $logs->recordsPerPage); ?>
                            <?php if ($pages > 1) : ?>
                                <?php $action = $filterAction ? "&action=" . $filterAction : ""; ?>
                                <?php $user = $filterUser ? "&user=" . $filterUser : ""; ?>
                                <?php $dtFrom = $filterDatetimeFrom ? "&dt-from=" . $filterDatetimeFrom : ""; ?>
                                <?php $dtTo = $filterDatetimeTo ? "&dt-to=" . $filterDatetimeTo : ""; ?>
                                <?php $itemsPerPage = $filterIPP ? "&ipp=" . $filterIPP : ""; ?>
                                <?php $type = $filterType ? "&type=" . $filterType : ""; ?>
                                <?php $up = $filterUp ? "&up=" . $filterUp : ""; ?>

                                <?php for ($i = 1; $i <= $pages; $i++) : ?>
                                    <?php if ($i === 1 || $i === $pages || ($i < $filterPage + 5 && $i > $filterPage - 5)) : ?>
                                        <li>
                                            <?php $page = "&p=" . $i; ?>
                                            <?php
                                            $link = "admin.php?page=barcode-scanner-logs";
                                            ?>
                                            <a class="button <?php echo $i === $filterPage ? 'button-primary' : '' ?>" href="<?php echo esc_url(get_admin_url() . $link . $page . $action . $user . $dtFrom . $dtTo . $itemsPerPage . $type . $up); ?>"><?php echo esc_html($i); ?></a>
                                        </li>
                                    <?php endif; ?>

                                    <?php if ($filterPage > 6 && $i === 1) : ?>
                                        <li>&nbsp;&nbsp;</li>
                                    <?php endif; ?>

                                    <?php if ($filterPage < $pages - 5 && $i === $pages - 1) : ?>
                                        <li>&nbsp;&nbsp;</li>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="usbs-modal usbs-modal-export-log">
    <div class="usbs-modal-container">
        <div class="usbs-modal-body">
            <?php echo wp_kses_post("Logs will be exported as CSV file.<br/>Selected filters will be applied to the final result.<br/>Do you want to proceed?", "us-barcode-scanner"); ?>
            <div class="usbs-modal-info"></div>
        </div>
        <div class="usbs-modal-actions">
            <button class="button button-general" type="button" onclick="WebBarcodeScannerExportClose()"><?php echo esc_html__("Cancel", "us-barcode-scanner"); ?></button>
            <button class="button button-primary" type="button" onclick="WebBarcodeScannerExportStart()"><?php echo esc_html__("Download", "us-barcode-scanner"); ?></button>
        </div>
    </div>
</div>
<?php
?>