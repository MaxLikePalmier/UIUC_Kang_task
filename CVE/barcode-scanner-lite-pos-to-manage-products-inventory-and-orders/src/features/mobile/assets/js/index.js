window.onerror = function myErrorHandler(errorMsg, url, lineNumber) {
  console.error("Error occurred: " + lineNumber + "-> " + errorMsg);
  return false;
};

jQuery(function () {
  window.addEventListener("message", WebbsSettingsMessages, false);
});

function WebbsSettingsMessages(event) {
  switch (event.data.message) {
    case "iframe.checkResult":
      jQuery("#bs-check-license-message").html(event.data.resultMessage);
      break;
    case "mobile.postMessage":
      BarcodeScannerMobileBridge(event.data);
      break;
  }
}

function bsMobileEmitMessages(data) {
  window.postMessage(data, "*");  
  return { accepted: true };
}

function checkWebViewConnection(data) {
  return data;
}

function checkWebViewReactConnection(data) {
  if (window.bsCheckWebViewReactConnection) return window.bsCheckWebViewReactConnection(data);
}


var BarcodeScannerMobileBridge = function (data) {
  if (navigator.share) {
    navigator
      .share(data)
      .then(() => console.log("Successful share"))
      .catch((error) => {
        console.error("Error sharing " + error);
        if (window.webkit && window.webkit.messageHandlers && window.webkit.messageHandlers.ReactNativeWebView) {
          window.webkit.messageHandlers.ReactNativeWebView.postMessage(JSON.stringify(data))
        } else if (window.ReactNativeWebView) {
          window.ReactNativeWebView.postMessage(JSON.stringify(data));
        }
      });
  } else if (window.webkit && window.webkit.messageHandlers && window.webkit.messageHandlers.ReactNativeWebView) {
    window.webkit.messageHandlers.ReactNativeWebView.postMessage(JSON.stringify(data))
  } else if (window.ReactNativeWebView) {
    window.ReactNativeWebView.postMessage(JSON.stringify(data));
  } else if (window.JSBridge && window.JSBridge.message) {
    window.JSBridge.message(JSON.stringify(data));
  } else {
    console.warn("web share not supported");
  }
};
