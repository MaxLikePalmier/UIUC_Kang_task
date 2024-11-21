if (!window.usbs && window.usbsMobile) window.usbs = window.usbsMobile;
var WebBarcodeScannerPreloader = function (status) {
  jQuery("#barcode-scanner-preloader").remove();

  if (status) {
    let css = '#barcode-scanner-preloader { position: fixed;top: 0px;left: 0px;width: 100vw;height: 100vh;z-index: 9000;font-size: 14px;background: rgba(0, 0, 0, 0.3);transition: opacity 0.3s ease 0s;transform: translate3d(0px, 0px, 0px); }';
    css += '#barcode-scanner-preloader .a4b-preloader-icon {position: relative;top: 50%;left: 50%;color: #fff;border-radius: 50%;opacity: 1;width: 30px;height: 30px;border: 2px solid #f3f3f3;border-top: 3px solid #3498db;display: inline-block;animation: a4b-spin 1s linear infinite; }';
    css += '@keyframes a4b-spin { 100% { -webkit-transform: rotate(360deg); transform:rotate(360deg); } }';

    let preloader = jQuery('<div id="barcode-scanner-preloader"><span class="a4b-preloader-icon"></span></div>');

    jQuery("#wpbody-content").append('<style>' + css + '</style>');
    jQuery("#wpbody-content").append(preloader);
  }
};

var WebBarcodeScannerAdminMenuList = function () {
  try {
    let wpVersion = window.usbs.wp_version;
    let wpKey = window.usbs.settings && window.usbs.settings.license ? window.usbs.settings.license.key : "";
    jQuery("#adminmenu span.barcode_scanner_faq")
      .closest("a")
      .attr("target", "_blank")
      .attr("href", "https://www.ukrsolution.com/ExtensionsSupport/Support?extension=25&version=1.6.4&pversion=");
    jQuery("#adminmenu span.barcode_scanner_faq")
      .closest("a")
      .attr("target", "_blank")
      .attr("href", "https://www.ukrsolution.com/Wordpress/WooCommerce-Barcode-QRCode-Scanner-Reader#faq");
    jQuery("#adminmenu span.barcode_scanner_support")
      .closest("a")
      .attr("target", "_blank")
      .attr(
        "href",
        "https://www.ukrsolution.com/ExtensionsSupport/Support?extension=24&version=1.6.4&pversion=" + wpVersion + "&d=" + btoa(wpKey)
      );
  } catch (error) {
    console.error(error.message);
  }
};

var WebBarcodeScannerShortcut = function () {
  try {
    document.addEventListener("keydown", function (event) {
      if (event.altKey && (event.key == "b" || event.code == "KeyB")) {
        const iframe = jQuery(".ukrsolution-barcode-scanner-frame");

        if (!iframe || !iframe.length || (iframe && iframe.hasClass("closed"))) {
          const _link = jQuery('a[href="admin.php?page=barcode-scanner"]');
          if (_link) _link.click();
        }
        else WebBarcodeScannerClose();
      }
    });
  } catch (error) {
    console.error(error.message);
  }
};

var WebBarcodeScannerOpen = function (event) {
  let iframe;
  iframe = window.frames.ukrsolutionBarcodeScannerFrame;
  const href = event.target.getAttribute("href");
  const postId = event.target.getAttribute("usbs-order-open-post");
  let excludes = ["#barcode-scanner-settings"];

  if (iframe) {
    iframe.postMessage(JSON.stringify({ message: "element-click", href: href, postId }), "*");

    const iframeEl = document.querySelector("iframe.ukrsolution-barcode-scanner-frame");
    if (iframeEl && !excludes.includes(href)) iframeEl.classList.remove("closed");

    const bodyEl = document.querySelector("body");
    bodyEl.classList.add("barcode-scanner-shows");
  }
};

var WebBarcodeScannerClose = function () {
  const iframeEl = document.querySelector("iframe.ukrsolution-barcode-scanner-frame");
  if (iframeEl) iframeEl.classList.add("closed");

  const bodyEl = document.querySelector("body");
  bodyEl.classList.remove("barcode-scanner-shows");
};

var WebBarcodeScannerDisableEvents = function () {
  const callback = function (event) {
    if (event.keyCode == 13) {
      event.preventDefault();
      return false;
    }
  };

  const callbackVariable = function (event) {
    if (event.keyCode == 13) {
      const name = "" + jQuery(event.target).attr("name");
      if (
        name.search("variable_sku") >= 0
        || name.search("variable_alg_ean") >= 0
        || name.search("_wpm_gtin_code_variable") >= 0
        || name.search("variation_barcode") >= 0
        || name.search("usbs_barcode_field_v") >= 0
        || name.search("variation_supplier_sku") >= 0
        || name.search("hwp_var_gtin") >= 0
        || name.search("variable_gtin") >= 0
        || name.search("variable_mpn") >= 0
        || name.search("ean_generator_code") >= 0
      ) {
        event.preventDefault();
        return false;
      }
    }
  };

  jQuery('body').on("keydown", '.usbs_barcode_field_text', callback);
  jQuery('body').on("keydown", '#woocommerce-product-data input[name="_alg_ean"]', callback);
  jQuery('body').on("keydown", '#woocommerce-product-data input[name="_supplier_sku"]', callback);
  jQuery('body').on("keydown", '#woocommerce-product-data input[name="_barcode"]', callback);
  jQuery('body').on("keydown", '#inventory_product_data input[name="_sku"]', callback);
  jQuery('body').on("keydown", '#inventory_product_data input[name="_wpm_gtin_code"]', callback);
  jQuery('body').on("keydown", '#inventory_product_data input[name="hwp_product_gtin"]', callback);
  jQuery('body').on("keydown", '#inventory_product_data input[name="_ean_generator_code"]', callback);
  jQuery('body').on("keydown", '#general_product_data input[name="_wepos_barcode"]', callback);
  jQuery('body').on("keydown", '#general_product_data input[name="_ts_gtin"]', callback);
  jQuery('body').on("keydown", '#general_product_data input[name="_gtin"]', callback);
  jQuery('body').on("keydown", '#general_product_data input[name="_ts_mpn"]', callback);
  jQuery('body').on("keydown", '#general_product_data input[name="_mpn"]', callback);
  jQuery('body').on("keydown", '.woocommerce_variation input[type="text"]', callbackVariable);
};

var WebBarcodeScannerWpMedia = function (postId) {
  var frame;

  if (frame) {
    frame.open();
    return;
  }

  frame = wp.media({
    title: "Select or Upload Media Of Your Chosen Persuasion",
    button: {
      text: "Use this media",
    },
    multiple: false, 
  });

  frame.on("select", function () {
    try {
      var attachment = frame.state().get("selection").first().toJSON();
      let iframe;
      iframe = window.frames.ukrsolutionBarcodeScannerFrame;
      iframe.postMessage(JSON.stringify({ message: "wp-media-attachment", attachment: attachment, postId: postId }), "*");
    } catch (error) {
      console.error(error);
    }
  });

  frame.open();
};

jQuery(document).ready(function () {
  WebBarcodeScannerAdminMenuList();
  WebBarcodeScannerShortcut();

  window.addEventListener("mousedown", function (e) {
    const href = jQuery(e.target).attr("href");
    if (document.body.classList.contains("barcode-scanner-shows") && href) {
      location.href = href;
    }
  },
    false
  );

  let s = 'a[href="admin.php?page=barcode-scanner"], a[href="#barcode-scanner-settings"], a[href*="barcode-scanner-admin-bar"], a[href*="barcode-scanner-frontend"], a[href*="#barcode-scanner-products-indexation"], a[href*="#barcode-scanner-search-filter"], span[usbs-order-open-post]';
  let menu = jQuery(s);
  let WebstartLoading = function (e) {
    e.preventDefault();
    e.stopPropagation();

    menu.off("click");
    menu.click(function (e) {
      e.preventDefault();
      e.stopPropagation();
      if (jQuery(e.target).attr("href") !== "#barcode-scanner-settings") WebBarcodeScannerOpen(e);
    });

    WebBarcodeScannerPreloader(true);

    let css = '.ukrsolution-barcode-scanner-frame{ position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 1290; border: none; }';
    css += '.ukrsolution-barcode-scanner-frame.closed{ display: none; }';
    css += 'body.barcode-scanner-shows{ overflow: hidden; }';

    const style = document.createElement("style");
    if (style.styleSheet) {
      style.styleSheet.cssText = css;
    } else {
      style.appendChild(document.createTextNode(css));
    }
    document.body.appendChild(style);

    let styleCustomCss = '<style data-name="usbsCustomCss">';
    styleCustomCss += window.usbsCustomCss ? window.usbsCustomCss.css : "";
    styleCustomCss += '</style>';

    var ls = localStorage.getItem("barcode-scanner-v1");
    if (!ls) ls = "{}";
    var scripLS = document.createElement("script");
    scripLS.type = "text/javascript";
    scripLS.text = "var serializedData = '" + ls + "';"
    let fnContent = window.usbs.settings && window.usbs.settings.modifyPreProcessSearchString ? window.usbs.settings.modifyPreProcessSearchString : "";
    scripLS.text = "window.usbsModifyPreProcessSearchString = function (bs_search_string) {" + fnContent + " ; return bs_search_string; };"

    window.addEventListener(
      "message",
      function (event) {
        switch (event.data.message) {
          case "localStorage.setItem":
            localStorage.setItem(event.data.storageKey, event.data.serializedData);
            break;
          case "iframe.onload":
            WebBarcodeScannerPreloader(false);
            jQuery(e.target).click();
            break;
          case "iframe.close":
            WebBarcodeScannerClose();
            break;
          case "iframe.wpMedia":
            WebBarcodeScannerWpMedia(event.data.postId);
            break;
          case "iframe.importLabels":
            if (event.data.products) {
              const importType = event.data.types && event.data.types.length && event.data.types[0] === "product_variation" ? "variation" : "simple";
              jQuery('.usbs-label-import').remove();
              const wrapper = jQuery('<span class="usbs-label-import" style="overflow: hidden; width: 0; height: 0;"></span>');
              let btnHtml = '<button type="button" class="barcodes-external-import" ';
              btnHtml += 'onclick="window.barcodesImportIdsType=\'' + importType + '\'; ';
              btnHtml += 'window.barcodesImportIds=[' + event.data.products + ']; ';
              btnHtml += 'window.usplOpenedFrom=\'scanner\'; ';
              btnHtml += '"></button>';
              const btn = jQuery(btnHtml);
              wrapper.append(btn);
              jQuery('body').append(wrapper);
              btn.click();
              WebBarcodeScannerClose();
            } else {
              console.warn("Incorrect data", event.data)
            }
            break;
          case "iframe.openScanner":
            let _link = jQuery('a[href="admin.php?page=barcode-scanner"]');

            if (window.BarcodeScannerFront) _link = jQuery('a[href="#?p=barcode-scanner-frontend"]');

            if (_link) _link.click();
            break;
        }
      },
      false
    );

    const iframe = document.createElement("iframe");
    iframe.className = "ukrsolution-barcode-scanner-frame closed";
    iframe.name = "ukrsolutionBarcodeScannerFrame";
    document.body.appendChild(iframe);

    let fonts = '<link rel="preconnect" href="https://fonts.googleapis.com">';
    fonts = '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    fonts = '<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">';

    let iframeCss = '<style>';
    iframeCss += '.not-number input::-webkit-outer-spin-button, .not-number input::-webkit-inner-spin-button { -webkit-appearance: none !important; margin: 0; }';
    iframeCss += '.not-number input[type=number] { -moz-appearance: textfield !important; }';
    iframeCss += '</style>';

    var iframeDocument = iframe.contentDocument || iframe.contentWindow.document;
    iframeDocument.open();
    iframeDocument.write(
      iframeCss +
      fonts +
      styleCustomCss +
      '<div id="ukrsolution-barcode-scanner"></div>' +
      '<div id="ukrsolution-barcode-scanner-modal"></div>' +
      '<div id="ukrsolution-barcode-scanner-alert"></div>' +
      '<div id="ukrsolution-barcode-scanner-settings"></div>'
    );
    iframeDocument.body.appendChild(scripLS);

    (async () => {
      try {
        const scriptContent = `
            const configsLoader = fetch("`+ window.usbs.ajaxUrl + `" + "?action=barcodeScannerConfigs" + "&token=barcode-scanner-configs&nonce=` + window.usbs.nonce + `").then(response => response.json());

            const coreLoader = new Promise((resolve, reject) => {

              {%iframeScripts%}

              // Resolve the promise when the script is successfully loaded
              appJs.onload = () => resolve('Script loaded successfully');

              // Reject the promise if there's an error loading the script
              appJs.onerror = () => reject(new Error('Error loading script'));
            });

            Promise.all([configsLoader, coreLoader]).then(results => {
              if (results && results[0]) {
                const response = results[0];
                if (response.usbs) window.usbs = response.usbs;
                if (response.usbs) window.parent.usbs = response.usbs;
                if (response.usbsCustomCss) window.usbsCustomCss = response.usbsCustomCss;
                if (response.usbsCustomCss) window.parent.usbsCustomCss = response.usbsCustomCss;
                if (response.usbsHistory) window.usbsHistory = response.usbsHistory;
                if (response.usbsHistory) window.parent.usbsHistory = response.usbsHistory;
                if (response.usbsInterface) window.usbsInterface = response.usbsInterface;
                if (response.usbsInterface) window.parent.usbsInterface = response.usbsInterface;
                if (response.usbsLangs) window.usbsLangs = response.usbsLangs;
                if (response.usbsLangs) window.parent.usbsLangs = response.usbsLangs;
                if (response.usbsOrderCF) window.usbsOrderCF = response.usbsOrderCF;
                if (response.usbsOrderCF) window.parent.usbsOrderCF = response.usbsOrderCF;
                if (response.usbsUserCF) window.usbsUserCF = response.usbsUserCF;
                if (response.usbsUserCF) window.parent.usbsUserCF = response.usbsUserCF;
                if (response.usbsWooShippmentProviders) window.usbsWooShippmentProviders = response.usbsWooShippmentProviders;
                if (response.usbsWooShippmentProviders) window.parent.usbsWooShippmentProviders = response.usbsWooShippmentProviders;
                if (response.cartExtraData) window.cartExtraData = response.cartExtraData;
                if (response.cartExtraData) window.parent.cartExtraData = response.cartExtraData;

                window.barcodeScannerStartApp();
              }
            }).catch(console.error);
        `;
        const handleFunction = iframeDocument.createElement('script');
        handleFunction.type = 'text/javascript';
        handleFunction.text = scriptContent;
        iframeDocument.body.appendChild(handleFunction);

        iframeDocument.close();

      } catch (error) {
        console.error('Error loading script:', error);
      }
    })();

    return false;
  };

  menu.off("click");
  menu.click(WebstartLoading);

  if (window.BarcodeScannerAutoShow) {
    try {
      document.getElementById("barcode-scanner-auto-show").click();
    } catch (error) {
      console.warn("element #barcode-scanner-auto-show not found", error.message);
    }
  }

  WebBarcodeScannerDisableEvents();

  const modalEl = document.querySelector('a.usbs-auto-start-modal');
  if (modalEl) modalEl.click();
});

const linkPageBarcodeScanner = document.querySelector('a[href="admin.php?page=barcode-scanner"]');
if (linkPageBarcodeScanner)
  linkPageBarcodeScanner.onclick = function (e) {
    e.preventDefault();
  };
