<?php

use UkrSolution\BarcodeScanner\API\actions\ManagementActions;
?>
<form class="bs-settings-input-conditions" id="bs-settings-receipt-printing-tab" method="POST" action="<?php echo esc_url($actualLink); ?>">
    <input type="hidden" name="tab" value="receipt-printing" />
    <input type="hidden" name="storage" value="table" />
    <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>" />

    <table class="form-table" style="width: initial;">
        <tbody>
            <!-- receipt-width -->
            <?php
            $receiptWidth = $settings->getSettings("receipt-width");
            $receiptWidth = $receiptWidth ? $receiptWidth->value : "";
            ?>
            <tr>
                <th scope="row" style="width: 100px;">
                    <?php echo esc_html__("Receipt width", "us-barcode-scanner"); ?>
                </th>
                <td colspan="2">
                    <input type="number" name="receipt-width" value="<?php echo esc_attr($receiptWidth) ?>" placeholder="55" style="width: 100px; height: 32px;" /> <?php echo esc_html__("mm", "us-barcode-scanner"); ?>
                </td>
            </tr>
        </tbody>
    </table>

    <table class="form-table" style="width: initial;">
        <tbody>
            <!-- receipt-template -->
            <?php
            $receiptTemplate = $settings->getSettings("receipt-template");
            $receiptTemplate = $receiptTemplate ? $receiptTemplate->value : "";

            $allowed_tags = wp_kses_allowed_html('post');
            $allowed_tags["link"] = array("href" => 1, "rel" => 1);
            ?>
            <tr>
                <td style="padding: 0; vertical-align: top;">
                    <b><?php echo esc_html__("Template", "us-barcode-scanner"); ?></b><br /><br>
                    <textarea name="receipt-template" id="usbs-template-editor" resize="both" rows="15" cols="70"><?php echo wp_kses($receiptTemplate, $allowed_tags) ?></textarea>
                    <div style="padding-top: 15px;">
                        <a class="usbs-restore-receipt-template" href="<?php echo esc_url(admin_url("admin.php?page=barcode-scanner-settings&tab=receipt-printing&usbsRestoreTpl=1")); ?>"><?php echo esc_html__("Restore default template", "us-barcode-scanner"); ?></a>
                    </div>
                </td>
                <td style="vertical-align: top;">
                    <b>&nbsp;</b><br /><br>
                    <div class="usbs-template-preview-wrapper">
                        <div id="usbs-template-preview"></div>
                        <b><?php echo esc_html__("Preview", "us-barcode-scanner"); ?></b>
                        <div id="usbs-template-preview-loader"><?php echo esc_html__("updating...", "us-barcode-scanner"); ?></div>
                    </div>
                    <div>
                        <?php
                        $field = $settings->getSettings("receiptOrderPreview");
                        $receiptOrderPreview = $field === null ? "" : $field->value;
                        $receiptOrderPreviewTitle = "";
                        $previewOrder = null;

                        if (!$receiptOrderPreview) {
                            try {
                                $args = array('limit' => 1, 'orderby' => 'date', 'order' => 'DESC', 'status' => array('wc-on-hold', 'wc-processing', 'wc-completed', 'wc-pending'));

                                $orders = \wc_get_orders($args);

                                if ($orders) $receiptOrderPreview = $orders[0]->get_id();
                            } catch (\Throwable $th) {
                            }
                        }

                        if ($receiptOrderPreview) {
                            try {
                                $post = get_post($receiptOrderPreview);

                                if ($post) {
                                    $managementActions = new ManagementActions();
                                    $orderRequest = new WP_REST_Request("", "");
                                    $orderRequest->set_param("query", $post->ID);
                                    $result = $managementActions->orderSearch($orderRequest, false, true);
                                    if ($result->data && isset($result->data["orders"]) && count($result->data["orders"])) {
                                        $previewOrder = $result->data["orders"][0];
                                        $receiptOrderPreviewTitle = "#" . $previewOrder["ID"] . " " . $previewOrder["preview_date_format"];
                                    }
                                }
                            } catch (\Throwable $th) {
                            }
                        }
                        ?>
                        <input type="hidden" name="receiptOrderPreview" value="" />
                        <select name='receiptOrderPreview' data-placeholder='Choose a order...' class='chosen-select-order' style="width: 200px;">
                            <option value=""></option>
                            <?php if ($receiptOrderPreview) {
                                echo '<option value="' . esc_attr($receiptOrderPreview) . '">' . esc_html($receiptOrderPreviewTitle) . '</option>';
                            } ?>
                        </select>
                    </div>
                    <div>
                        <div style="height: 15px;"></div>
                        <button type="button" class="button" id="receipt-documentation-toggle"><?php echo esc_html__("Documentation", "us-barcode-scanner"); ?></button>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>

    <div class="submit">
        <input type="submit" class="button button-primary" value="<?php echo esc_html__("Save Changes", "us-barcode-scanner"); ?>">
    </div>
</form>

<?php require_once(__DIR__ . "/tab-receipt-documentation.php"); ?>

<script>
    var restoreTemplate = document.querySelector(".usbs-restore-receipt-template");

    restoreTemplate.addEventListener("click", function(event) {

        if (!confirm("Do you really want to cancel all your template changes and restore the default receipt template ?")) {
            event.preventDefault();
            return false;
        }

    });
</script>
<script>
    let editor = null;
    let initialized = false;

    jQuery(document).ready(function() {
        jQuery(".nav-tab-wrapper .nav-tab[data-tab='receipt-printing']").click(() => {
            setTimeout(() => {
                usbsTemplateEditor();
            }, 100);

        });

        <?php if (isset($tab) && $tab == "receipt-printing") : ?>
            usbsTemplateEditor();
        <?php endif; ?>
    });

    const usbsTemplateEditor = () => {
        if (initialized) return;

        const el = document.querySelector("textarea#usbs-template-editor");
        const widthEl = jQuery("#bs-settings-receipt-printing-tab input[name='receipt-width']");
        let timer = null;

        if (!el || !CodeMirror) return;

        editor = CodeMirror.fromTextArea(el, {
            mode: "htmlmixed",
            lineNumbers: true,
            htmlMode: true,
            allowedTags: false,
            allowedAttributes: false
        });

        editor.on("change", (cm, change) => {
            try {
                const editedTemplate = cm.getValue();
                if (window.updateTemplatePreview) {
                    if (timer) clearTimeout(timer);
                    preloader(true);
                    timer = setTimeout(() => {
                        window.updateTemplatePreview(editedTemplate, widthEl.val());
                        preloader(false);
                    }, 500);
                }
            } catch (error) {
                console.warn(error.message);
            }
        });

        const editorResizer = document.querySelector(".CodeMirror-scrollbar-filler");
        const editorEl = document.querySelector(".CodeMirror");
        let startX, startY, startWidth, startHeight;

        const initDrag = (e) => {
            e.preventDefault();
            startX = e.clientX;
            startY = e.clientY;
            startWidth = parseInt(document.defaultView.getComputedStyle(editorEl).width, 10);
            startHeight = parseInt(document.defaultView.getComputedStyle(editorEl).height, 10);
            document.documentElement.addEventListener("mousemove", doDrag, false);
            document.documentElement.addEventListener("mouseup", stopDrag, false);
        };

        const doDrag = (e) => {
            editorEl.style.width = startWidth + e.clientX - startX + "px";
            editorEl.style.height = startHeight + e.clientY - startY + "px";
        };

        const stopDrag = () => {
            document.documentElement.removeEventListener("mousemove", doDrag, false);
            document.documentElement.removeEventListener("mouseup", stopDrag, false);
        };

        editorResizer.addEventListener("click", function init() {
            editorResizer.removeEventListener("click", init, false);
            editorResizer.className = editorResizer.className + " resizable";
            editorResizer.addEventListener("mousedown", initDrag, false);
        }, false);
        editorResizer.click();

        const handleWidth = (e) => {
            if (editor && window.updateTemplatePreview) {
                if (timer) clearTimeout(timer);

                preloader(true);
                timer = setTimeout(() => {
                    window.updateTemplatePreview(editor.getValue(), widthEl.val());
                    preloader(false);
                }, 500);
            }
        }
        jQuery("#bs-settings-receipt-printing-tab input[name='receipt-width']").change(handleWidth);
        jQuery("#bs-settings-receipt-printing-tab input[name='receipt-width']").keyup(handleWidth);

        let intervalChecker = setInterval(() => {
            if (jQuery("#usbs-template-preview iframe").length && editor && widthEl) {
                window.updateTemplatePreview(editor.getValue(), widthEl.val());
                preloader(false);
                clearInterval(intervalChecker);
            }
        }, 1000);

        const preloader = (status) => {
            const el = jQuery("#usbs-template-preview-loader");
            if (!el) return;

            if (status) el.removeAttr("style");
            else el.css("display", "none");
        }

        initialized = true;
    };
</script>
<script>
    window.usbsReceiptPreviewOrder = <?php echo $previewOrder ? json_encode($previewOrder) : "null"; ?>

    jQuery(document).ready(() => {
        const chosenOption = {
            width: "200px",
            no_results_text: "Loading:",
            search_contains: true,
        };
        jQuery(".chosen-select-order").chosen(chosenOption);

        let request = null;
        let lastOrders = null;
        jQuery('.chosen-select-order').on('chosen:no_results', function(evt, params) {
            const query = jQuery(params.chosen.search_field).val();
            const currentIds = jQuery(evt.target).val();
            if (request) request.abort();
            request = jQuery.post(window.usbs.ajaxUrl + "?token=" + window.usbs.utoken, {
                action: "barcodeScannerAction",
                rout: "usbs_find_order",
                query,
                currentIds
            }, function(data) {
                const selectElem = jQuery(evt.target);
                const selectedIds = selectElem.val();
                selectElem.empty();

                if (data.orders && data.orders.length) {
                    lastOrders = data.orders;
                    jQuery.each(data.orders, function(idx, obj) {
                        const selected = currentIds && currentIds.includes(obj.ID) ? "selected='selected'" : "";
                        selectElem.append('<option value="' + obj.ID + '" ' + selected + '>#' + obj.ID + ' ' + obj.preview_date_format + '</option>');
                    });
                } else selectElem.append('<option value=""></option>');

                jQuery(evt.target).trigger('chosen:updated');
                jQuery(".chosen-select-order").val(selectedIds).trigger("chosen:updated");
            }, "json");
        });

        jQuery('.chosen-select-order').on('change', function(evt, params) {
            if (params.selected) {
                request = jQuery.post(window.usbs.ajaxUrl + "?token=" + window.usbs.utoken, {
                    action: "barcodeScannerAction",
                    rout: "usbs_find_order_save_id",
                    orderId: params.selected,
                }, (data) => {});

                const order = lastOrders ? lastOrders.find(o => o.ID == params.selected) : null;
                if (order) window.usbsReceiptPreviewOrder = order;

                const widthEl = jQuery("#bs-settings-receipt-printing-tab input[name='receipt-width']");
                window.updateTemplatePreview(editor.getValue(), widthEl.val());
            }
        });

        var defaultValue = '<?php echo esc_html($receiptOrderPreview); ?>';
        jQuery(".chosen-select-order").val(defaultValue).trigger("chosen:updated");
    });
</script>
<style>
    #bs-settings-receipt-printing-tab .CodeMirror {
        height: 400px;
        min-width: 400px;
        max-width: 800px;
    }

    .usbs-template-preview-wrapper {
        position: relative;
    }

    #usbs-template-preview-loader {
        background: #ffffffd1;
        width: calc(100% - 15px);
        height: calc(100% - 20px);
        position: absolute;
        top: 0;
        left: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-style: italic;
    }

    #usbs-template-preview {
        max-height: 400px;
        overflow-y: auto;
        overflow-x: hidden;
        padding-right: 15px;
    }

    #usbs-template-preview iframe {
        background: #fff;
        overflow: hidden;
    }

    #bs-settings-receipt-printing-tab .CodeMirror-vscrollbar {
        display: block;
    }

    #bs-settings-receipt-printing-tab .CodeMirror-hscrollbar {
        display: block;
        left: 0 !important;
    }

    #bs-settings-receipt-printing-tab .CodeMirror-scrollbar-filler {
        cursor: se-resize;
        background: repeating-linear-gradient(125deg, #ffffff, #ffffff 1px, #adadad 1px, #adadad 2px);
        display: block;
        width: 17px;
        height: 17px;
    }

    #bs-settings-receipt-printing-tab .CodeMirror-scroll {
        padding-bottom: 10px;
    }
</style>