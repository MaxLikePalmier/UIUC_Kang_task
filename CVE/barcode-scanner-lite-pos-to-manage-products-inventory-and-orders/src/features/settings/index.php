<!-- default tab -->
<?php
$tab = "search";
$url = "";
$actualLink = "";
$nonce = "";

try {
    if (isset($_POST["tab"])) {
        $tab = sanitize_text_field($_POST["tab"]);
    } else if (isset($_GET["tab"])) {
        $tab = sanitize_text_field($_GET["tab"]);
    } else if ($settings->activeTab) {
        $tab = $settings->activeTab;
    }
    $url = $_SERVER['REQUEST_URI'];
    $url = preg_replace('/(\&tab=.*)/', "", $url);
    $actualLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $url . "-update";
    $nonce = wp_create_nonce(USBS_PLUGIN_BASE_NAME . "-settings");
} catch (\Throwable $th) {
}
?>
<a href="#barcode-scanner-settings"></a>
<div id="bs-settings-page">
    <h2><?php echo esc_html__("Barcode Scanner settings", "us-barcode-scanner"); ?></h2>
    <div>
        <nav class="nav-tab-wrapper">
            <a href="#search" class="nav-tab <?php echo ($tab === "search") ? esc_attr("nav-tab-active") : "" ?>" data-tab="search"><?php echo esc_html__("Search", "us-barcode-scanner"); ?></a>
            <a href="#fields" class="nav-tab <?php echo ($tab === "fields") ? esc_attr("nav-tab-active") : "" ?>" data-tab="fields"><?php echo esc_html__("Product fields", "us-barcode-scanner"); ?></a>
            <a href="#app" class="nav-tab <?php echo ($tab === "app") ? esc_attr("nav-tab-active") : "" ?>" data-tab="app"><?php echo esc_html__("Mobile App", "us-barcode-scanner"); ?></a>
            <a href="#products" class="nav-tab <?php echo ($tab === "products") ? esc_attr("nav-tab-active") : "" ?>" data-tab="products"><?php echo esc_html__("Products", "us-barcode-scanner"); ?></a>
            <?php
            ?>

            <a href="#orders" class="nav-tab <?php echo ($tab === "orders") ? esc_attr("nav-tab-active") : "" ?>" data-tab="orders"><?php echo esc_html__("Orders", "us-barcode-scanner"); ?></a>
            <a href="#permissions" class="nav-tab <?php echo ($tab === "permissions") ? esc_attr("nav-tab-active") : "" ?>" data-tab="permissions"><?php echo esc_html__("Permissions", "us-barcode-scanner"); ?></a>
            <a href="#general" class="nav-tab <?php echo ($tab === "general") ? esc_attr("nav-tab-active") : "" ?>" data-tab="general"><?php echo esc_html__("Front-end popup", "us-barcode-scanner"); ?></a>
            <a href="#receipt-printing" class="nav-tab <?php echo ($tab === "receipt-printing") ? esc_attr("nav-tab-active") : "" ?>" data-tab="receipt-printing"><?php echo esc_html__("Receipt printing", "us-barcode-scanner"); ?></a>
            <a href="#plugins" class="nav-tab <?php echo ($tab === "plugins") ? esc_attr("nav-tab-active") : "" ?>" data-tab="plugins"><?php echo esc_html__("Direct DB requests", "us-barcode-scanner"); ?></a>
            <a href="#css" class="nav-tab <?php echo ($tab === "css") ? esc_attr("nav-tab-active") : "" ?>" data-tab="css"><?php echo esc_html__("Other", "us-barcode-scanner"); ?></a>
            <a href="#license" class="nav-tab <?php echo ($tab === "license") ? esc_attr("nav-tab-active") : "" ?>" data-tab="license"><?php echo esc_html__("License", "us-barcode-scanner"); ?></a>

            <!-- custom tabs -->
            <?php foreach ($customTabs as $index => $customTab) : ?>
                <?php $slug = isset($customTab["slug"]) && $customTab["slug"] ? $customTab["slug"] : "tab-slug-" . $index; ?>
                <?php $name = isset($customTab["name"]) && $customTab["name"] ? $customTab["name"] : "Tab name"; ?>
                <a href="#license" class="nav-tab <?php echo ($tab === $slug) ? esc_attr("nav-tab-active") : "" ?>" data-tab="<?php echo esc_attr($slug); ?>"><?php echo esc_html($name); ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="tabs">
            <!-- general -->
            <div class="settings-tab general-tab" <?php echo ($tab !== "general") ? 'style="display: none;"' : "" ?>>
                <?php require_once(__DIR__ . "/views/tab-general.php"); ?>
            </div>
            <!-- locations -->
            <?php
            ?>
            <!-- search -->
            <div class="settings-tab search-tab" <?php echo ($tab !== "search") ? 'style="display: none;"' : "" ?>>
                <?php require_once(__DIR__ . "/views/tab-search.php"); ?>
            </div>
            <!-- products -->
            <div class="settings-tab products-tab" <?php echo ($tab !== "products") ? 'style="display: none;"' : "" ?>>
                <?php require_once(__DIR__ . "/views/tab-products.php"); ?>
            </div>
            <!-- orders -->
            <div class="settings-tab orders-tab" <?php echo ($tab !== "orders") ? 'style="display: none;"' : "" ?>>
                <?php require_once(__DIR__ . "/views/tab-orders.php"); ?>
            </div>
            <!-- fields -->
            <div class="settings-tab fields-tab" <?php echo ($tab !== "fields") ? 'style="display: none;"' : "" ?>>
                <?php require_once(__DIR__ . "/views/tab-fields.php"); ?>
            </div>
            <!-- permissions -->
            <div class="settings-tab permissions-tab" <?php echo ($tab !== "permissions") ? 'style="display: none;"' : "" ?>>
                <?php require_once(__DIR__ . "/views/tab-permissions.php"); ?>
            </div>
            <!-- Receipt printing -->
            <div class="settings-tab receipt-printing-tab" <?php echo ($tab !== "receipt-printing") ? 'style="display: none;"' : "" ?>>
                <?php require_once(__DIR__ . "/views/tab-receipt-printing.php"); ?>
            </div>
            <!-- plugins -->
            <div class="settings-tab plugins-tab" <?php echo ($tab !== "plugins") ? 'style="display: none;"' : "" ?>>
                <?php require_once(__DIR__ . "/views/tab-plugins.php"); ?>
            </div>
            <!-- license -->
            <div class="settings-tab license-tab" <?php echo ($tab !== "license") ? 'style="display: none;"' : "" ?>>
                <?php require_once(__DIR__ . "/views/tab-license.php"); ?>
            </div>
            <!-- app -->
            <div class="settings-tab app-tab" <?php echo ($tab !== "app") ? 'style="display: none;"' : "" ?>>
                <?php require_once(__DIR__ . "/views/tab-app.php"); ?>
            </div>
            <!-- CSS -->
            <div class="settings-tab css-tab" <?php echo ($tab !== "css") ? 'style="display: none;"' : "" ?>>
                <?php require_once(__DIR__ . "/views/tab-other.php"); ?>
            </div>

            <!-- custom tabs -->
            <?php foreach ($customTabs as $index => $customTab) : ?>
                <?php $slug = isset($customTab["slug"]) && $customTab["slug"] ? $customTab["slug"] : "tab-slug-" . $index; ?>
                <div class="settings-tab <?php echo esc_attr($slug); ?>-tab" <?php echo ($tab !== $slug) ? 'style="display: none;"' : "" ?>>
                    <?php if (file_exists($customTab["viewPath"])) {
                        require($customTab["viewPath"]);
                    } else {
                        echo '"viewPath" ' . esc_html__("is incorrect", "us-barcode-scanner");
                    } ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div id="barcode-scanner-preloader">
    <span class="a4b-preloader-icon"></span>
    <style>
        #barcode-scanner-preloader {
            position: fixed;
            top: 0px;
            left: 0px;
            width: 100vw;
            height: 100vh;
            z-index: 9000;
            font-size: 14px;
            background: rgba(0, 0, 0, 0.3);
            transition: opacity 0.3s ease 0s;
            transform: translate3d(0px, 0px, 0px);
        }

        #barcode-scanner-preloader .a4b-preloader-icon {
            position: relative;
            top: 50%;
            left: 50%;
            color: #fff;
            border-radius: 50%;
            opacity: 1;
            width: 30px;
            height: 30px;
            border: 2px solid #f3f3f3;
            border-top: 3px solid #3498db;
            display: inline-block;
            animation: a4b-spin 1s linear infinite;
        }

        @keyframes a4b-spin {
            100% {
                -webkit-transform: rotate(360deg);
                transform: rotate(360deg);
            }
        }
    </style>
</div>

<style>
    #bs-settings-page .tabs .form-table th {
        padding: 4px 8px 4px 0;
        font-weight: normal;
    }

    #bs-settings-page .tabs .form-table td {
        padding: 4px;
        line-height: 1.2;
        vertical-align: top;
    }

    #bs-settings-page .tabs .settings-tab table.form-table tr.usbs-section-label td {
        padding-left: 0;
    }

    #bs-settings-page .tabs .settings-tab table.form-table tr h2 {
        margin: 0;
        padding: 24px 0px 4px;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const tabs = document.querySelectorAll('.nav-tab-wrapper a');
        let selectedTab = '<?php echo esc_html($tab); ?>';

        tabs.forEach((tab) => {
            tab.addEventListener('click', (event) => {
                selectedTab = event.target.getAttribute('data-tab');
                history.pushState(null, null, '?page=barcode-scanner-settings&tab=' + selectedTab);
            });
        });
    });
</script>