<?php
$productId = isset($_GET["pid"]) ? sanitize_text_field($_GET["pid"]) : "";
?>
<a href="#barcode-scanner-settings"></a>
<div id="bs-settings-page">
    <h2><?php echo esc_html__("Barcode Scanner Indexed Data", "us-barcode-scanner"); ?></h2>
    <div>
        <div class="tabs">
            <form id="barcode-scan-indexed-data" method="GET">
                <input type="hidden" name="page" value="<?php echo esc_html_e($_GET["page"]); ?>" />&nbsp;
                <div>
                    <?php echo esc_html__("Enter product ID to get indexed data:", "us-barcode-scanner"); ?>
                    <input type="text" name="pid" value="<?php echo esc_attr($productId); ?>" placeholder="<?php echo esc_attr("Product ID", "us-barcode-scanner"); ?>" />
                    <input id="submit" type="submit" class="button button-primary" value="<?php echo esc_attr("Apply", "us-barcode-scanner"); ?>">
                </div>
                <?php if ($productId) : ?>
                    <?php
                    echo '<pre>';
                    print_r($indexedData->getByPostId($productId));
                    echo '</pre>';
                    $wpml = $indexedData->getWpmlData($productId);
                    if ($wpml) {
                        echo '<b>WPML</b>';
                        echo '<pre>';
                        print_r($wpml);
                        echo '</pre>';
                    }
                    ?>
                    <!-- <table class="form-table barcode-scan-logs">
                        <thead>
                            <tr>

                            </tr>
                        </thead>
                        <tbody>

                        </tbody>
                    </table> -->
                <?php endif; ?>
            </form>

            <?php if ($productId) : ?>
                <form id="barcode-scan-indexed-data" method="GET">
                    <input type="hidden" name="page" value="<?php echo esc_attr($_GET["page"]); ?>" />&nbsp;
                    <div>
                        <input type="hidden" name="index" value="<?php echo esc_attr($productId); ?>" />&nbsp;
                        <input type="hidden" name="pid" value="<?php echo esc_attr($productId); ?>" />
                        <input id="submit" type="submit" class="button button-primary" value="<?php echo esc_attr("Index product", "us-barcode-scanner"); ?>">
                    </div>
                </form>
            <?php endif; ?>
        </div>
        <br /><br />
        <hr />
        <b><?php echo esc_html__("Product fields:", "us-barcode-scanner"); ?></b>
        <ul style="columns: 3; -webkit-columns: 3; -moz-columns: 3;">
            <?php foreach ($indexedData->getAllColumns() as $value) : ?>
                <li><?php echo esc_html__($value); ?></li>
            <?php endforeach; ?>
        </ul>
        <hr />
        <form id="barcode-scan-indexed-data" method="GET" onSubmit="if(!confirm('Index will be empty, you will have to reindex all products.')){return false;}">
            <input type="hidden" name="page" value="<?php echo esc_html_e($_GET["page"]); ?>" />&nbsp;
            <div>
                <span style="display: inline-block; padding-top: 6px;"><?php echo esc_html__("Remove & create empty index table", "us-barcode-scanner"); ?></span>
                <input type="hidden" name="reCreateTable" value="1" />&nbsp;
                <input id="submit" type="submit" class="button button-primary re-create-index-table" value="<?php echo esc_attr("Re-create index table", "us-barcode-scanner"); ?>">
            </div>
        </form>
        <hr />
        <?php $itc = \get_option("usbs_index_triggers_counting", ""); ?>
        <form id="barcode-scan-indexed-data-triggers" method="GET">
            <input type="hidden" name="page" value="<?php echo esc_html_e($_GET["page"]); ?>" />&nbsp;
            <div>
                <label>
                    <input type="hidden" name="triggers" value="1" />&nbsp;
                    <input type="checkbox" name="index_triggers_counting" <?php echo $itc === "on" ? 'checked="checked"' : ''; ?> onchange="this.form.submit();" />
                    <?php echo esc_html__("Enable index triggers counting", "us-barcode-scanner") ?>
                </label>
            </div>
        </form>
        <br />
        <?php if (\get_option("usbs_index_triggers_counting", "") === "on") : ?>
            updated_post_meta_admin: <?php echo esc_html(\get_option("usbs_iic_updated_post_meta_admin", 0)); ?><br />
            updated_post_meta: <?php echo esc_html(\get_option("usbs_iic_updated_post_meta", 0)); ?><br />
            woocommerce_save_product_variation: <?php echo esc_html(\get_option("usbs_iic_woocommerce_save_product_variation", 0)); ?><br />
            transition_post_status: <?php echo esc_html(\get_option("usbs_iic_transition_post_status", 0)); ?><br />
            wp_insert_post: <?php echo esc_html(\get_option("usbs_iic_wp_insert_post", 0)); ?><br />
            pageIndexedData: <?php echo esc_html(\get_option("usbs_iic_pageIndexedData", 0)); ?><br />
            updatePostsTable: <?php echo esc_html(\get_option("usbs_iic_updatePostsTable", 0)); ?><br />
        <?php endif; ?>
    </div>
</div>