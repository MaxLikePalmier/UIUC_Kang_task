<style>
    #barcode-scanner-action-preloader {
        width: 20px;
        height: 20px;
        font-size: 14px;
        transition: opacity 0.3s ease 0s;
        transform: translate3d(0px, 0px, 0px);
        display: inline-block;
        position: absolute;
        padding-left: 10px;
    }

    #barcode-scanner-action-preloader .a4b-action-preloader-icon {
        position: relative;
        color: #fff;
        border-radius: 50%;
        opacity: 1;
        width: 20px;
        height: 20px;
        border: 2px solid #f3f3f3;
        border-top: 3px solid #3498db;
        display: inline-block;
        animation: a4b-action-spin 1s linear infinite;
    }

    @keyframes a4b-action-spin {
        100% {
            -webkit-transform: rotate(360deg);
            transform: rotate(360deg);
        }
    }
</style>
<form id="bs-settings-license-tab" method="POST" action="<?php echo esc_url($actualLink); ?>">
    <input type="hidden" name="tab" value="license" />
    <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>" />
    <table class="form-table">
        <tbody>
            <tr>
                <th scope="row">
                    <?php echo esc_html__("License key", "us-barcode-scanner"); ?>
                </th>
                <td>
                    <?php
                    echo '<input type="text" style="width: 310px;" name="key" placeholder="Enter license key" value="' . esc_attr($settings->getField("license", "key")) . '">';
                    ?>
                    <span style="display: inline-block; width: 0; height: 0; overflow: hidden;">
                        <button id="bs-check-license" style="margin-left: 10px;"><?php echo esc_html__("Check", "us-barcode-scanner"); ?></button>
                    </span>
                    <span style="position: relative;" id="usbs-lic-preloader">
                        <span id="barcode-scanner-action-preloader">
                            <span class="a4b-action-preloader-icon"></span>
                        </span>
                    </span>
                    <span id="bs-check-license-message"></span>
                </td>
            </tr>
        </tbody>
    </table>
    <div class="submit">
        <input type="submit" class="button button-primary" value="<?php echo esc_html__("Save Changes", "us-barcode-scanner"); ?>">
    </div>
</form>