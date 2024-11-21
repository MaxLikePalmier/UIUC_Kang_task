let dateFormat = "yy-mm-dd";

(function () {
  let from = jQuery("input.bs-datepicker-from").datepicker({
    changeMonth: true,
    numberOfMonths: 1,
    dateFormat,
    onClose: function (date) { },
  }).on("change", function () {
    to.datepicker("option", "minDate", WebBarcodeScannerGetDate(this));
  });

  let to = jQuery("input.bs-datepicker-to").datepicker({
    changeMonth: true,
    numberOfMonths: 1,
    dateFormat,
    onClose: function (date) { },
  }).on("change", function () {
    from.datepicker("option", "maxDate", WebBarcodeScannerGetDate(this));
  });

  from.trigger("change");
  to.trigger("change");
})();

function WebBarcodeScannerGetDate(element) {
  var date;
  try {
    date = jQuery.datepicker.parseDate(dateFormat, element.value);
  } catch (error) {
    date = null;
  }

  return date;
}

function WebBarcodeScannerExportLog() {
  jQuery(".usbs-modal-export-log").css("display", "flex");
}

function WebBarcodeScannerExportStart() {
  WebBarcodeScannerRunExportLog({ page: 1, exported: 0, fname: "", tmpFname: "" });
}

function WebBarcodeScannerExportClose() {
  jQuery(".usbs-modal-export-log").css("display", "none");
}

function WebBarcodeScannerRunExportLog({ page = 1, exported = 0, fname = "", tmpFname = "" }) {
  if (page == 1) {
    jQuery(".usbs-modal-info").text("Exporting...");
    jQuery(".usbs-modal-export-log button").attr("disabled", "disabled");
  }

  jQuery.post(
    window.usbs.ajaxUrl + "?token=exportLog&p=" + page + "&" + jQuery("#barcode-scan-logs").serialize(),
    { action: "barcodeScannerAction", rout: "exportLog", inputs: { page, exported, fname, tmpFname } },
    function (result) {
      jQuery(".usbs-modal-info").text("Exporting... (" + result.exported + "/" + result.total + ")");

      if (parseInt(result.exported) < parseInt(result.total)) {
        WebBarcodeScannerRunExportLog({ page: page + 1, exported: result.exported, fname: result.fname, tmpFname: result.tmpFname });
      } else if (result.fname && result.fpath) {
        jQuery(".usbs-modal-info").html(`<a href="${result.fpath}" download="${result.fname}" targe="_blank">download file</a>`);
        jQuery(".usbs-modal-export-log button").removeAttr("disabled");
        document.location.href = result.fpath;

      }
    },
    "json"
  ).fail(function (xhr, status, error) {
    jQuery(".usbs-modal-info").text(error);
    jQuery(".usbs-modal-export-log button").removeAttr("disabled");
  });
}
