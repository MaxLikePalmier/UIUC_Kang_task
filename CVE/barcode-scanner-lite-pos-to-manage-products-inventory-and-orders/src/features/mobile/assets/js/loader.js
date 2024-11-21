if (!window.usbs && window.usbsMobile) window.usbs = window.usbsMobile;
var WebBarcodeScannerOpen = function (event) {
  const href = event.target.getAttribute("href");
  const postId = event.target.getAttribute("usbs-order-open-post");

  window.postMessage(JSON.stringify({ message: "element-click", href, postId }), "*");

  if (!window.usbsMobile || !window.usbsMobile.platform) {
    const bodyEl = document.querySelector("body");
    bodyEl.classList.add("barcode-scanner-shows");
  }
};

var WebBarcodeScannerScripts = function () {
  try {
    
    var appJs = document.createElement("script"); appJs.type = "text/javascript"; appJs.src = window.usbsMobile.appJsPath;
    document.body.appendChild(appJs);
    
  } catch (error) {
    console.error("3. " + error.message);
  }
}

jQuery(document).ready(function () {
  jQuery("link, style:not(.usbs-style)").remove();

  const link1 = jQuery('<link rel="preconnect" href="https://fonts.googleapis.com">');
  const link2 = jQuery('<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>');
  const link3 = jQuery('<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">');
  jQuery("body").append(link1);
  jQuery("body").append(link2);
  jQuery("body").append(link3);

  const css = `
    *, body * { user-select: none; }
    .ukrsolution-barcode-scanner-frame, .ukrsolution-barcode-scanner-frame{ position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; border: none; }
    .ukrsolution-barcode-scanner-frame.closed, .ukrsolution-barcode-scanner-frame.closed{ display: none; }
    body.barcode-scanner-shows{ overflow: hidden; }
    #barcode-scanner-mobile-preloader { background: white; height: 100vh; width: 100vw; position: fixed; top: 0; left: 0; display: flex; justify-content: center; align-items: center; }
    `;
  const style = document.createElement("style");

  if (style.styleSheet) {
    style.styleSheet.cssText = css;
  } else {
    style.appendChild(document.createTextNode(css));
  }
  document.body.appendChild(style);

  let s = 'a[href="#barcode-scanner-mobile"]';
  let menu = jQuery(s);

  const WebstartLoading = function (e) {
    try {
      e.preventDefault();
      e.stopPropagation();

      menu.off("click").click(function (e) {
        e.preventDefault();
        e.stopPropagation();
        WebBarcodeScannerOpen(e);
      });

      var ls = localStorage.getItem("barcode-scanner-v1");
      window.serializedData = ls ? ls : "{}";
      window.addEventListener(
        "message",
        function (event) {
          switch (event.data.message) {
            case "localStorage.setItem":
              localStorage.setItem(event.data.storageKey, event.data.serializedData);
              break;
            case "iframe.onload":
              jQuery(e.target).click();
              jQuery("#barcode-scanner-mobile-preloader").hide();
              break;
          }
        },
        false
      );
    } catch (error) {
      console.error("1. " + error.message);
    }

    return false;

  };

  WebBarcodeScannerScripts();

  menu.off("click").click(WebstartLoading);
  menu.click();
});