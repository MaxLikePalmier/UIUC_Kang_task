jQuery(function () {
  setTimeout(() => {
    jQuery('a[href="#barcode-scanner-settings"]').click();
  }, 100);

  window.addEventListener("message", WebbsSettingsMessages, false);

  if (window.location.hash) {
    const hash = window.location.hash;
    const selector = "#bs-settings-page .nav-tab-wrapper a[href='" + hash + "']";

    if (jQuery(selector).length) {
      jQuery("#bs-settings-page .nav-tab-wrapper a").removeClass("nav-tab-active");
      jQuery(selector).addClass("nav-tab-active");

      jQuery("#bs-settings-page .tabs .settings-tab").attr("style", "display: none");
      jQuery("#bs-settings-page .tabs .settings-tab." + hash.replace("#", "") + "-tab").attr("style", "display: block");
    }
  }

  WebbsSettingsLicenseTab();
  WebbsSettingsTabs();
  WebbsSettingsOptions();
  WebbsPermissionsTabs();
  WebbsAppPermissionsTabs();
  WebbsOrdersTabs();
  WebbsPricesTab();
  WebbsFieldsTab();
  WebbsProdLocations();
});

function WebbsSettingsMessages(event) {
  switch (event.data.message) {
    case "iframe.checkResult":
      jQuery("#bs-check-license-message").html(event.data.resultMessage);
      const check = jQuery("form#bs-settings-license-tab #bs-check-license");
      check.removeAttr("disabled");

      if (event.data.resultMessage) jQuery("#usbs-lic-preloader").css("opacity", 0);


      break;
    case "iframe.appUsersLoader":
      if (event.data.loader) {
        jQuery(".app-users-loader").css("display", "inline-block");
      } else {
        jQuery(".app-users-loader").css("display", "none");
      }

      break;
    case "iframe.appUsers":
      jQuery(".bs-settings-app-users tbody").text("");
      const users = window.usbsMobile && window.usbsMobile.m_otp_status ? window.usbsMobile.m_otp_status : {};
      const usersExpired = window.usbsMobile && window.usbsMobile.m_otp_expired ? window.usbsMobile.m_otp_expired : {};
      event.data.users.forEach((user) => {
        let status = !users[user.id] ? "" : users[user.id].length < 40 ? "Password not used yet" : "Password already used";
        if (usersExpired[user.id] && usersExpired[user.id] == 1) status = "Expired password";
        let tr = jQuery("<tr></tr>");
        tr.append(jQuery("<td>" + user.id + "</td>"));
        tr.append(jQuery("<td>" + user.name + "</td>"));
        tr.append(jQuery("<td>" + status + "</td>"));
        tr.append(jQuery('<button type="button" data-id="' + user.id + '" class="button new-pass-user-p" style="margin-right: 10px;">New password</button>'));
        tr.append(jQuery('<button type="button" data-id="' + user.id + '" class="button remove-app-user-p">Remove</button>'));
        jQuery(".bs-settings-app-users tbody").append(tr);
      });

      WebbsAppUsersActions();

      break;

    case "iframe.modal":
      jQuery("#barcode-scanner-modal").remove();

      let css = `
    #barcode-scanner-modal { position: fixed;top: 0px;left: 0px;width: 100vw;height: 100vh;z-index: 9000;font-size: 14px;background: rgba(0, 0, 0, 0.3);transition: opacity 0.3s ease 0s;transform: translate3d(0px, 0px, 0px); }
    #barcode-scanner-modal .bsm-body {box-sizing: border-box; background:#fff; padding:25px; position: relative; width: 450px; top: calc(50% - 225px); left: calc(50% - 225px); color: #fff; border-radius: 8px; border: 1px solid #f3f3f3; }
    #barcode-scanner-modal .bsm-title {color: #333; font-size: 21px; margin-bottom: 15px;}
    #barcode-scanner-modal .bsm-text {color: #333; font-size: 14px;}
    #barcode-scanner-modal .bsm-close {    position: absolute; top: 0; right: 0; width: 28px; cursor: pointer; padding: 6px; box-sizing: border-box; opacity: 0.6;}
    #barcode-scanner-modal .bsm-close img {width: 100%}
    @keyframes a4b-spin { 100% { -webkit-transform: rotate(360deg); transform:rotate(360deg); } }
    `;
      let modal = jQuery(
        `<div id="barcode-scanner-modal"><div class="bsm-body"><div class="bsm-close"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAAAXNSR0IArs4c6QAAAjdJREFUWEftljtPVUEUhT/gFxAUeRRqADVBAz0VIrEksRVjqYm/R4UGW2JDQsuzJxYEhRAVgyT4oJAeNWYl+5h7J3se54LBgtOc5JyZvb5Ze2bvaeOcn7Zz1ucCIOZAB/AcGAMeAO9bTNUtYAFYB54Bv8M4HkA7MAc8tsHfgAngXU2Im8Aq0Gfz5oFp4FdjHA/gJfA0EPtiEDuFEMPACnAlGP/CnPj72QP4AAw4QqVO3AGWgW4nhlJ5I+eA8ibrep0AR+bEVsSJEWAJuOz8/25z3+YA9F8QsrDKX+OcH8Ak8CYQkbhWfqmOe6k6EG6iEOI+sGEfR23lnvhXW/m251quEClfSke/M/kYEMSJiXc5YyR+F4hu3hyAYqYglA49nY74ATAOfEydnBIAzb8GrNm75CR+tpUnxRWoFEBjrxrE9QyBxLXyvRLSOgCKJ3E5IRjv+WTi+yXidR2o9oMAvOOp/zrr2nTFZbuOA9qMKfFq0aqYgnCPXehMKUCpeG2IEoBUQdJq9YRNR98OzYnd0xzDIbPdK0RVbVchUmq83pFNR8qBEvGqscilFISOpVsNYwApcXVEbbKmrgakIKIl2QMYtCuUZ3uuHadauQvhAajX33Y2Tmzl4VDNVSv3LiSqD02xPYAZ4EkQNbfyUojZ8LrnAehG/Ap4ZFFjF5BctVU6tDF7bOBr4CHws3Fi6lou2nvAFLCZU4v81/1w0VIiV5tuxK30ghY54tNKKuGZi5ak4J+K/lcAfwBZG3QhFnA8UwAAAABJRU5ErkJggg=="/></div><div class="bsm-title">${event.data.title}</div><div class="bsm-text">${event.data.text}</div></div></div>`
      );

      jQuery("#wpbody-content").append(`<style>${css}</style>`);
      jQuery("#wpbody-content").append(modal);
      jQuery("#barcode-scanner-modal .bsm-close").click(() => {
        jQuery("#barcode-scanner-modal").remove();
      });

      break;
  }
}

function WebbsSettingsLicenseTab() {
  const check = jQuery("form#bs-settings-license-tab #bs-check-license");
  check.off("click").click(function (e) {
    e.preventDefault();
    let iframe;
    iframe = window.frames.ukrsolutionBarcodeScannerFrame;
    const data = jQuery("form#bs-settings-license-tab").serializeArray();
    iframe.postMessage(JSON.stringify({ message: "settings-check", data }), "*");
    check.attr("disabled", "disabled");
  });

  const submit = jQuery("form#bs-settings-license-tab");
  submit.submit(function (e) { });
}

function WebbsPricesTab() {
  WebbsPricesTabEvents({
    chSelector: "form #settings_show_price_1 input[type='checkbox']",
    rowId: "tr#settings_price_1_fields",
    resultId: "price_1_field_check_result",
    checkId: "price_1_field_check"
  });
  WebbsPricesTabEvents({
    chSelector: "form #settings_show_price_2 input[type='checkbox']",
    rowId: "tr#settings_price_2_fields",
    resultId: "price_2_field_check_result",
    checkId: "price_2_field_check"
  });
  WebbsPricesTabEvents({
    chSelector: "form #settings_show_price_3 input[type='checkbox']",
    rowId: "tr#settings_price_3_fields",
    resultId: "price_3_field_check_result",
    checkId: "price_3_field_check"
  });
}

function WebbsFieldsTab() {
  const fields = jQuery("form#bs-settings-fields-tab input[type='checkbox'][data-fid]");

  jQuery("body").on("focus", "input.usbs_field_name", function (e) {
    if (!jQuery(e.target).val()) {
      const parent = jQuery(e.target).closest("table");
      let newVal = parent.find(".usbs_field_label").val();
      newVal = newVal.toLowerCase().trim().replaceAll(" ", "_").replace(/[^a-zA-Z0-9_]/g, "");
      jQuery(e.target).val(newVal)
    }
  });

  jQuery("body").on("click", ".type_select_option_remove", function (e) {
    jQuery(e.target).closest(".type_select_option").remove();
  });

  jQuery("body").on("click", ".type_select_option_add", function (e) {
    const fieldEl = jQuery(e.target).closest(".settings_field_body");
    const fid = fieldEl.attr("data-fid");
    const rid = Webmakeid(10);

    const option = jQuery('<div class="type_select_option"></div>');
    option.append('<input type="text" name="fields[' + fid + '][options][' + rid + '][key]" value="option_name" />')
    option.append('<input type="text" name="fields[' + fid + '][options][' + rid + '][value]" value="Option label" />')
    option.append('<span class="type_select_option_remove">✖</span>')

    fieldEl.find(".type_select_options").append(option);
  });

  fields.each((index, obj) => {
    const fid = jQuery(obj).attr("data-fid");

    WebbsPricesTabEvents({
      chSelector: "form#bs-settings-fields-tab input[type='checkbox'][data-fid='" + fid + "']",
      rowId: "div#settings_field[data-fid='" + fid + "']",
      resultId: "settings_field[data-fid='" + fid + "'] .cf_check_name_result",
      checkId: "settings_field[data-fid='" + fid + "'] .cf_check_name",
      rout: "checkFieldName",
      isShowHide: false
    });
  });

  jQuery(".settings_field_add_new").click((e) => {
    const rid = Webmakeid(10);
    const position = jQuery(e.target).attr("data-position");
    const template = jQuery(".new_field_template").clone();
    template.removeClass("new_field_template");
    template.find(".usbs_field_position").val(position);
    template.find("[data-fid]").each(function (idx, obj) {
      jQuery(obj).attr("data-fid", rid);
    });
    template.find("[name]").each(function (idx, obj) {
      const name = jQuery(obj).attr("name");
      jQuery(obj).attr("name", name.replace("[0]", "[" + rid + "]"));
    });
    template.find("[onchange]").each(function (idx, obj) {
      const name = jQuery(obj).attr("onchange");
      jQuery(obj).attr("onchange", name.replace("data-fid='0'", "data-fid='" + rid + "'"));
    });
    jQuery("table.wrapper[data-position='" + position + "']>tbody").append(template);

    WebbsFieldsInitAfterChanges();
    WebbsPricesTabEvents({
      chSelector: "form#bs-settings-fields-tab input[type='checkbox'][data-fid='" + rid + "']",
      rowId: "div#settings_field[data-fid='" + rid + "']",
      resultId: "settings_field[data-fid='" + rid + "'] .cf_check_name_result",
      checkId: "settings_field[data-fid='" + rid + "'] .cf_check_name",
      rout: "checkFieldName",
      isShowHide: false
    });
    WebbsFieldsRemove();
    WebbsFieldsChanges();
    template.find(".settings_field_block_label").click();

    if (position === "product-middle-left") {
      template.find(".usbs_field_label_position").val("left");
      template.find(".usbs_field_label_position").change();
      template.find(".usbs_label_width").val("40");
    } else if (position === "product-middle-right") {
      template.find(".usbs_label_width").val("50");
    } else if (position === "product-left-sidebar") {
      template.find(".usbs_label_width").val("50");
    } else if (position === "product-middle-bottom") {
      template.find(".usbs_label_width").val("100");
    }
  });

  WebbsFieldsSortable();
  WebbsFieldsInitAfterChanges();
  WebbsFieldsRemove();

  WebbsFieldsChanges();
  jQuery("div#settings_field .usbs_field_type").change();
  jQuery("div#settings_field .usbs_field_label_position").change();
}

function WebbsFieldsChanges(e) {
  const fieldsChecker = (e, field) => {
    const type = jQuery(e.target).closest(".settings_field_body ").find(".usbs_field_type").val();
    const positionEl = jQuery(e.target).closest(".settings_field_body ").find(".usbs_field_label_position");
    const nameEl = jQuery(e.target).closest(".settings_field_body ").find(".usbs_field_name");
    const labelEl = jQuery(e.target).closest(".settings_field_body ").find(".usbs_field_label");
    const heightEl = jQuery(e.target).closest(".settings_field_body ").find(".usbs_field_height");
    const labelWidthEl = jQuery(e.target).closest(".settings_field_body ").find(".usbs_label_width");
    const showInCreateOrderEl = jQuery(e.target).closest(".settings_field_body ").find(".show_in_create_order");
    const disabledFieldEl = jQuery(e.target).closest(".settings_field_body ").find(".disabled_field");
    const typeSelectEl = jQuery(e.target).closest(".settings_field_body ").find(".type_select");
    const globalAttributeEl = jQuery(e.target).closest(".settings_field_body ").find(".global_attribute");


    if (positionEl && type == "white_space") {
      heightEl.closest("tr").removeAttr("style");
      nameEl.closest("tr").css("display", "none");
      positionEl.closest("tr").css("display", "none");
      showInCreateOrderEl.closest("tr").css("display", "none");
      disabledFieldEl.closest("tr").css("display", "none");
      labelEl.closest("tr").css("display", "none");
    } else {
      heightEl.closest("tr").css("display", "none");
      nameEl.closest("tr").removeAttr("style");
      positionEl.closest("tr").removeAttr("style");
      showInCreateOrderEl.closest("tr").removeAttr("style");
      disabledFieldEl.closest("tr").removeAttr("style");
      labelEl.closest("tr").removeAttr("style");
      disabledFieldEl.find('.usbs_field_disabled_field').prop('readonly', false);
    }

    if (type == "white_space" || !["left", "right"].includes(positionEl.val())) {
      labelWidthEl.closest("tr").css("display", "none");
    } else {
      labelWidthEl.closest("tr").removeAttr("style");
    }

    if (positionEl && type == "number_plus_minus") {
      positionEl.val("top");
      positionEl.attr("disabled", "disabled");
      if (!["position"].includes(field)) positionEl.change();
    } else {
      positionEl.removeAttr("disabled");
    }

    if (type == "categories") {
      nameEl.closest("tr").css("display", "none");
      showInCreateOrderEl.closest("tr").css("display", "none");
    }

    if (type == "tags") {
      nameEl.closest("tr").css("display", "none");
      showInCreateOrderEl.closest("tr").css("display", "none");
      disabledFieldEl.find('.usbs_field_disabled_field').prop('checked', true);
      disabledFieldEl.find('.usbs_field_disabled_field').prop('readonly', true);
    }

    if (type == "variation_attributes") {
      nameEl.closest("tr").css("display", "none");
      showInCreateOrderEl.closest("tr").css("display", "none");
      disabledFieldEl.closest("tr").css("display", "none");
    }

    if (type == "global_attribute") {
      nameEl.closest("tr").css("display", "none");
      showInCreateOrderEl.closest("tr").css("display", "none");
      disabledFieldEl.closest("tr").css("display", "none");
      globalAttributeEl.closest("tr").removeAttr("style");
    } else {
      globalAttributeEl.closest("tr").css("display", "none");
    }

    if (type == "locations") {
      nameEl.closest("tr").css("display", "none");
      positionEl.closest("tr").css("display", "none");
      showInCreateOrderEl.closest("tr").css("display", "none");
      disabledFieldEl.closest("tr").css("display", "none");
    }

    if (typeSelectEl && type == "select") {
      typeSelectEl.removeAttr("style");
    } else {
      typeSelectEl.css("display", "none");
    }
  };

  jQuery("div#settings_field .usbs_field_label_position").off("change").change((e) => { fieldsChecker(e, "position") });
  jQuery("div#settings_field .usbs_field_type").off("change").change((e) => { fieldsChecker(e, "type") });
}

function WebbsFieldsChToggle(e, className = "") {
  const fid = jQuery(e).attr("data-fid");

  if (className) {
    jQuery("div#settings_field ." + className + "[data-fid='" + fid + "']").click();
  } else {
    jQuery("div#settings_field[data-fid='" + fid + "'] .usbs_field_status").click();
  }
}

function WebbsFieldsRemove(e) {
  jQuery(".settings_field_remove").off("click").click((e) => {
    const fid = jQuery(e.target).attr("data-fid");
    const label = jQuery("#settings_field[data-fid='" + fid + "'] .usbs_field_label").val();
    const name = jQuery("#settings_field[data-fid='" + fid + "'] .usbs_field_name").val();
    if (confirm(`Are you sure delete field "${label ? label : name}"`)) {
      jQuery(e.target).closest(".settings_field_section").addClass("removed");
      jQuery(e.target).closest(".settings_field_section").find(".usbs_field_remove").val(1);
    }
  });
}

function WebbsFieldsInitAfterChanges() {
  const ordersEl = jQuery(".usbs_fields_list_sortable input.usbs_field_order, .usbs_fields_list_sortable_prices input.usbs_field_order");

  ordersEl.each(function (idx, obj) {
    const orderEl = jQuery(obj);
    orderEl.val(ordersEl.length - idx);

    const position = orderEl.closest("[data-position]").attr("data-position");
    orderEl.parent().find(".usbs_field_position").val(position);
  });

  jQuery(".settings_field_block_label").off("click").click((e) => {
    const targetEl = jQuery(e.target).hasClass("settings_field_block_label") ? jQuery(e.target) : jQuery(e.target).closest(".settings_field_block_label");
    let fid = targetEl.attr("data-fid");

    jQuery(".settings_field_body#settings_field[data-fid='" + fid + "']").toggleClass("active");
    targetEl.find(".dashicons").removeClass("active");

    if (jQuery(".settings_field_body#settings_field[data-fid='" + fid + "']").hasClass("active")) {
      targetEl.find(".dashicons-arrow-up-alt2").addClass("active");
    } else {
      targetEl.find(".dashicons-arrow-down-alt2").addClass("active");
    }
  });
}

function WebbsFieldsSortable() {
  jQuery(".usbs_fields_list_sortable").sortable({
    helper: "clone",
    connectWith: ".usbs_fields_list_sortable",
    stop: WebbsFieldsInitAfterChanges,
    start: function (event, ui) {
      jQuery(ui.item).show();
      clone = jQuery(ui.item).clone();
      before = jQuery(ui.item).prev();
      parent = jQuery(ui.item).parent();

    },
    receive: function (event, ui) { 
    }
  });
  jQuery(".usbs_fields_list_sortable_prices").sortable({
    helper: "clone",
    connectWith: ".usbs_fields_list_sortable_prices",
    stop: WebbsFieldsInitAfterChanges,
    start: function (event, ui) {
      jQuery(ui.item).show();
      clone = jQuery(ui.item).clone();
      before = jQuery(ui.item).prev();
      parent = jQuery(ui.item).parent();

    },
    receive: function (event, ui) { 
    }
  });
}

function WebbsPricesTabEvents({ chSelector, rowId, resultId, checkId, rout = "checkOtherPrices", isShowHide = true }) {
  const ch = jQuery(chSelector);

  if (isShowHide) ch.change(function (e) {
    jQuery(rowId).removeAttr("style");
    if (jQuery(this).prop("checked")) {
      jQuery(rowId).css("display", "auto");
    } else {
      jQuery(rowId).css("display", "none");
    }
  });

  const check = jQuery("form #" + checkId);
  check.off("click").click(function (e) {
    e.preventDefault();

    if (window['usbs_temp_timer_' + resultId]) clearTimeout(window['usbs_temp_timer_' + resultId]);

    jQuery("#" + resultId).html("");
    jQuery("#" + resultId).removeClass("active");
    check.attr("disabled", "disabled");

    const inputs = jQuery(rowId + " input").serializeArray();
    const data = { action: "barcodeScannerAction", rout, inputs: {} };

    jQuery.each(inputs, function () {
      data.inputs[this.name] = this.value;
    });

    jQuery.post(
      window.usbs.ajaxUrl + "?token=" + rout,
      data,
      function (result) {
        if (result.success) {
          jQuery("#" + resultId).html(" " + result.success);
        } else if (result.error) {
          jQuery("#" + resultId).html(" " + result.error);
        }

        jQuery("#" + resultId).addClass("active");

        window['usbs_temp_timer_' + resultId] = setTimeout(() => {
          jQuery("#" + resultId).html("");
          jQuery("#" + resultId).removeClass("active");
        }, 5000);

        check.removeAttr("disabled");
      },
      "json"
    );
  });
}

function WebbsSettingsTabs() {
  jQuery("#bs-settings-page .nav-tab-wrapper a").click(function (e) {
    e.preventDefault();

    jQuery("#bs-settings-page .nav-tab-wrapper a").removeClass("nav-tab-active");
    jQuery(this).addClass("nav-tab-active");

    const tab = jQuery(this).attr("data-tab");

    jQuery("#bs-settings-page .tabs .settings-tab").attr("style", "display: none");
    jQuery("#bs-settings-page .tabs .settings-tab." + tab + "-tab").attr("style", "display: block");
  });
}

function WebbsProdLocations() {

}

function WebbsSettingsOptions() {
  jQuery("#bs_allow_frontend_integration input[type='checkbox']").change(function (e) {
    if (jQuery(this).is(":checked")) {
      jQuery("#bs_frontend_url").css("display", "table-row");
      jQuery("#bs_frontend_integration").css("display", "table-row");
      jQuery("#bs_frontend_shortcodes_integration").css("display", "table-row");

      if (jQuery("#bs_frontend_shortcodes_integration input[type='checkbox']").is(":checked")) {
        jQuery("#bs_frontend_shortcodes_docs").css("display", "table-row");
      }
    } else {
      jQuery("#bs_frontend_url").css("display", "none");
      jQuery("#bs_frontend_integration").css("display", "none");
      jQuery("#bs_frontend_shortcodes_integration").css("display", "none");
      jQuery("#bs_frontend_shortcodes_docs").css("display", "none");
    }
  });

  jQuery("#bs_frontend_shortcodes_integration input[type='checkbox']").change(function (e) {
    if (jQuery(this).is(":checked")) {
      jQuery("#bs_frontend_shortcodes_docs").css("display", "table-row");
    } else {
      jQuery("#bs_frontend_shortcodes_docs").css("display", "none");
    }
  });

  jQuery(".bs-settings-input-conditions input[data-main]").change(function (e) {
    const slug = jQuery(this).attr("data-main");

    if (!slug) return;

    if (jQuery(this).is(":checked")) {
      jQuery(".bs-settings-input-conditions [data-parent='" + slug + "']").css("display", "table-row");
    } else {
      jQuery(".bs-settings-input-conditions [data-parent='" + slug + "']").css("display", "none");
    }
  });
  jQuery(".bs-settings-input-conditions input[data-main]").change();

  jQuery("#bs_enable_locations input[type='checkbox']").change(function (e) {
    if (jQuery(this).is(":checked")) {
      jQuery("#bs-settings-locations-tab .bs_enabled_stock_locations").css("display", "table-row");
    } else {
      jQuery("#bs-settings-locations-tab .bs_enabled_stock_locations").css("display", "none");
    }
  });

  jQuery(".bs-settings-input-conditions select[data-main]").change(function (e) {
    const slug = jQuery(this).attr("data-main");
    console.log(slug)
    if (!slug) return;
    console.log(jQuery(this).val())
    if (jQuery(this).val() == "custom_field") {
      jQuery(".bs-settings-input-conditions [data-parent='" + slug + "']").css("display", "table-row");
    } else {
      jQuery(".bs-settings-input-conditions [data-parent='" + slug + "']").css("display", "none");
    }
  });
  jQuery(".bs-settings-input-conditions select[data-main]").change();
}

function WebbsPermissionsTabs() {
  jQuery(".bs-settings-chosen-select").chosen({});
}

function WebbsAppUsersActions() {
  jQuery("#bs-settings-app-tab .remove-app-user-p").click(function (e) {
    if (confirm("Do you really want to forbid this user to use Mobile Barcode Scanner ?")) {
      let iframe;
      iframe = window.frames.ukrsolutionBarcodeScannerFrame;
      const data = { message: "remove-app-user", id: jQuery(this).attr("data-id") };
      iframe.postMessage(JSON.stringify(data), "*");
    }
  });

  jQuery("#bs-settings-app-tab .new-pass-user-p").click(function (e) {
    if (confirm("Do you really want to re-generate one time password?")) {
      let iframe;
      iframe = window.frames.ukrsolutionBarcodeScannerFrame;
      const data = { message: "new-pass-app-user", id: jQuery(this).attr("data-id") };
      iframe.postMessage(JSON.stringify(data), "*");
    }
  });

  jQuery("#bs-settings-app-tab .show-app-user-i").click(function (e) {
    const fname = jQuery(this).closest("tr").find("#user-full-name").html();
    jQuery("#bs-settings-app-tab #app-user-full-name").text(fname);
    jQuery("#bs-settings-app-tab .app-device").attr("data-id", jQuery(this).attr("data-id"));
    jQuery("#bs-settings-app-tab #app-user-instructions").css("display", "block");
    const activeDevice = jQuery("#bs-settings-app-tab .app-device.active");
    if (activeDevice) activeDevice.click();
  });
}

function WebbsAppPermissionsTabs() {
  let request = null;

  const inputChange = function (e) {
    if (request) request.abort();
    if (!jQuery(this).val()) return;

    jQuery("#app-users-search-preloader").css("display", "inline-block");

    let action = "barcodeScannerAction";
    request = jQuery.post(
      window.usbs.ajaxUrl + "?token=" + window.usbs.utoken,
      { action, query: jQuery(this).val(), rout: "usersFind" },
      function (data) {
        const list = jQuery("ul.app-users-search-list");
        list.text("");
        if (data.users) {
          jQuery.each(data.users, function (idx, obj) {
            const name = obj.display_name ? obj.display_name : obj.user_nicename;
            list.append(`<li data-id="${obj.ID}" data-name="${name}">${name}</li>`);
          });
          jQuery("ul.app-users-search-list li").click(function (e) {
            let iframe;
            iframe = window.frames.ukrsolutionBarcodeScannerFrame;
            const data = { message: "add-app-user", id: jQuery(this).attr("data-id"), name: jQuery(this).attr("data-name") };
            iframe.postMessage(JSON.stringify(data), "*");
          });
        }

        jQuery("#app-users-search-preloader").css("display", "none");
      },
      "json"
    );
  };

  jQuery("input.app-users-search-input").keyup(inputChange);
  jQuery(document).click(function (e) {
    if (!jQuery(e.target).closest(".app-users-search-list").length) {
      jQuery("ul.app-users-search-list").text("");
    }
  });

  jQuery("#bs-settings-app-tab .app-device").click(function (e) {
    jQuery("#bs-settings-app-tab .app-device").removeClass("active");
    jQuery(this).addClass("active");
    const device = jQuery(this).attr("data-device");
    const authLink = jQuery("#app-auth-link-" + jQuery(this).attr("data-id")).val();

    let message = "1. Install Mobile App: " + device + "\n";
    message += "2. Login by following link: " + authLink + "\n";
    message += "Note: Login link created for your account only, do not share your account with any other person.";
    jQuery("#bs-settings-app-tab #next-instructions-message").val(message);

    jQuery("#bs-settings-app-tab #next-instructions").css("display", "block");
  });

  WebbsAppUsersActions();
}

function WebbsOrdersTabs() {
  let request = null;

  const inputChange = function (e) {
    if (request) request.abort();
    if (!jQuery(this).val()) {
      jQuery("input.order-default-user-id-search-input").val("");
      return;
    }

    jQuery("#order-default-user-search-preloader").css("display", "inline-block");

    let action = "barcodeScannerAction";

    request = jQuery.post(
      window.usbs.ajaxUrl + "?token=" + window.usbs.utoken,
      { action, query: jQuery(this).val(), rout: "usersFind" },
      function (data) {
        const list = jQuery("ul.order-default-users-search-list");
        list.text("");
        jQuery("input.order-default-user-id-search-input").val("");
        if (data.users) {
          jQuery.each(data.users, function (idx, obj) {
            const name = obj.display_name ? obj.display_name : obj.user_nicename;
            list.append(`<li data-id="${obj.ID}" data-name="${name}" data-email="${obj.email}">${name} - ${obj.email}</li>`);
          });
          jQuery("ul.order-default-users-search-list li").click(function (e) {
            jQuery("input.order-default-user-id-search-input").val(jQuery(this).attr("data-id"));
            jQuery("input.order-default-user-search-input").val(jQuery(this).attr("data-name") + " - " + jQuery(this).attr("data-email"));
            list.text("");
          });
        }

        jQuery("#order-default-user-search-preloader").css("display", "none");
      },
      "json"
    );
  };

  jQuery("input.order-default-user-search-input").keyup(inputChange);
  jQuery(document).click(function (e) {
    if (!jQuery(e.target).closest(".order-default-users-search-list").length) {
      jQuery("ul.order-default-users-search-list").text("");
    }
  });
}

function WebbsSettingsCheckboxChange(el, value) {
  const input = jQuery(el);
  const root = input.closest("tr");

  if (root && root.hasClass('disabled_field') && value != 1 && input) {
    const type = input.closest(".settings_field_body ").find(".usbs_field_type").val();
    const disabledFieldEl = input.closest(".settings_field_body ").find(".disabled_field");

    if (type == "tags" && input && disabledFieldEl) {
      input.val("1");
      disabledFieldEl.find('.usbs_field_disabled_field').prop('checked', true);
      return;
    }
  }

  if (input) input.val(value);
}

function Webmakeid(length) {
  var result = '';
  var characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
  var charactersLength = characters.length;
  for (var i = 0; i < length; i++) {
    result += characters.charAt(Math.floor(Math.random() * charactersLength));
  }
  return result;
}