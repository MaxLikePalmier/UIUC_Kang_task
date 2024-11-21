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
<title>Barcode Scanner</title>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
<a href="#?p=barcode-scanner-frontend" data-plugin="barcode-scanner-frontend" id="barcode-scanner-auto-show" style="display:none;">Barcode scanner</a>
<link href="<?php echo esc_url(USBS_PLUGIN_BASE_URL); ?>/assets/css/style.css" />

<!-- label printing integration - HTML -->
<?php echo $printingInitHTML; ?>

<!-- label printing integration - DOM data -->
<?php if ($printingDOMData) : ?>
    <?php foreach ($printingDOMData as $key => $value) : ?>
        <script>
            window.<?php echo $key; ?> = <?php echo json_encode($value); ?>;
        </script>
    <?php endforeach; ?>
<?php endif; ?>

<!-- label printing integration - scrips -->
<?php if ($printingScripts) : ?>
    <?php $jQuery_url = includes_url('/js/jquery/jquery.js'); ?>
    <script src="<?php echo esc_url($jQuery_url); ?>"></script>

    <?php foreach ($printingScripts as $value) : ?>
        <?php
        $fileInfo = pathinfo($value);
        $extension = isset($fileInfo['extension']) ? strtolower($fileInfo['extension']) : '';
        ?>
        <?php if ($extension == "js") : ?>
            <script src="<?php echo esc_url($value); ?>"></script>
        <?php elseif ($extension == "css") : ?>
            <link rel="stylesheet" href="<?php echo esc_url($value); ?>">
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>

<!-- barcode scanner scripts -->
<script>
    window.BarcodeScannerFront = true;
    window.BarcodeScannerAutoShow = true;
    window.BarcodeScannerDisableClose = true;
    window.BarcodeScannerDisableResize = true;
</script>
<script>
    window.usbsAccessDenied = <?php echo $accessDenied ? 1 : 0; ?>;
</script>
<script>
    window.usbsLangs = <?php echo json_encode($usbsLangs); ?>;
</script>
<script>
    window.usbsInterface = <?php echo json_encode(apply_filters("scanner_product_fields_filter", $usbsInterface)); ?>;
</script>
<script>
    window.usbs = <?php echo json_encode($usbs); ?>;
</script>
<script>
    window.usbsCustomCss = <?php echo json_encode($usbsCustomCss); ?>;
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
<script src="<?php echo esc_url(home_url()); ?>/wp-includes/js/jquery/jquery.min.js"></script>
<script src="<?php echo esc_url(home_url()); ?>/wp-includes/js/jquery/jquery-migrate.min.js"></script>

<script src="<?php echo esc_url($path); ?>assets/js/index-business-1.6.4-1727186327459.js"></script>

<?php
