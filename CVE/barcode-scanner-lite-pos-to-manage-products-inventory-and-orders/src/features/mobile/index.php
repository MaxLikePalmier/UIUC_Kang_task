<?php

use UkrSolution\BarcodeScanner\API\classes\Users;
use UkrSolution\BarcodeScanner\features\settings\Settings;

$settings = new Settings();


$usbs = $jsData && isset($jsData['usbs']) ? $jsData['usbs'] : array();
$usbsCustomCss = $jsData && isset($jsData['usbsCustomCss']) ? $jsData['usbsCustomCss'] : array();
$usbsHistory = $jsData && isset($jsData['usbsHistory']) ? $jsData['usbsHistory'] : array();
$usbsUserCF = $jsData && isset($jsData['usbsUserCF']) ? $jsData['usbsUserCF'] : array();
$usbsOrderCF = $jsData && isset($jsData['usbsOrderCF']) ? $jsData['usbsOrderCF'] : array();
$usbsWooShippmentProviders = $jsData && isset($jsData['usbsWooShippmentProviders']) ? $jsData['usbsWooShippmentProviders'] : array();
$usbsLangs = $jsData && isset($jsData['usbsLangs']) ? $jsData['usbsLangs'] : array();
$usbsInterface = $jsData && isset($jsData['usbsInterface']) ? $jsData['usbsInterface'] : array();
$cartExtraData = $jsData && isset($jsData['cartExtraData']) ? $jsData['cartExtraData'] : array();

$userId = $usbs && isset($usbs['userId']) ? $usbs['userId'] : "";
$userRole = $userId ? Users::getUserRole($userId) : '';
?>
<title>Barcode Scanner mobile</title>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
<a href="#barcode-scanner-mobile"></a>
<div id="ukrsolution-barcode-scanner"></div>
<div id="ukrsolution-barcode-scanner-mobile"></div>

<div id="barcode-scanner-mobile-preloader" data-role="<?php echo esc_attr($userRole) ?>">
    <div style="user-select: none;">Loading...</div>
</div>
<style class="usbs-style">
    <?php echo wp_kses_post($customCssMobile); ?>
</style>
<script>
    window.barcodeScannerStartAppAuto = true;
</script>
<script>
    window.usbsLangsMobile = <?php echo json_encode($usbsLangs); ?>;
</script>
<script>
    window.usbsInterfaceMobile = <?php echo json_encode(apply_filters("scanner_product_fields_filter", $usbsInterface)); ?>;
</script>
<script>
    window.usbsMobile = <?php echo json_encode($usbs); ?>;
</script>
<script>
    window.usbsHistory = <?php echo json_encode($usbsHistory); ?>;
</script>
<script>
    window.usbsUserCF = <?php echo json_encode($usbsUserCF); ?>;
</script>
<script>
    window.usbsOrderCF = <?php echo json_encode($usbsOrderCF); ?>;
</script>
<script>
    window.usbsWooShippmentProviders = <?php echo json_encode($usbsWooShippmentProviders); ?>;
</script>
<script>
    window.cartExtraData = <?php echo json_encode($cartExtraData); ?>;
</script>

<script>
    <?php
    $field = $settings->getSettings("modifyPreProcessSearchString");
    $fnContent = $field === null ? "" : trim($field->value);

    if ($fnContent) {
        echo wp_kses_post("window.usbsModifyPreProcessSearchString = function (bs_search_string) {" . $fnContent . " ; \n return bs_search_string; };");
    } ?>
</script>