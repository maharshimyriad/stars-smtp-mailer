jQuery(document).ready(function () {
  if (jQuery("#send_test_form").length > 0) {
    jQuery.validator.setDefaults({
      ignore: [],
    });
    jQuery("#send_test_form").validate({
      ignore: "",
    });
  }
  if (jQuery("#stars-add-new-account").length > 0) {
    jQuery("#stars-add-new-account").validate();
  }
  if (jQuery(".tooltip-toggle").length > 0) {
    jQuery(".tooltip-toggle").tooltip({
      content: function () {
        return jQuery(this).prop("title");
      },
    });
  }
  // Check server port
  if (jQuery("#smtp_port").length > 0 && jQuery("#smtp_host").length > 0) {
    jQuery("#smtp_port, #smtp_host").focusout(function () {
      var host = jQuery("#smtp_host").val();
      var port = jQuery("#smtp_port").val();
      jQuery(".check_error").text("");
      if (jQuery.trim(host) != "" && jQuery.trim(port) != "") {
        jQuery.ajax({
          type: "POST",
          url: ajaxurl,
          data: {
            check_host: host,
            check_port: port,
            action: "stars_smtpm_check_host_server",
            nonce:
              typeof starsSmtpNotify !== "undefined"
                ? starsSmtpNotify.check_host_nonce
                : "",
          },
          success: function (response) {
            if (response) {
              var data = jQuery.parseJSON(response);
              if (data.error) {
                jQuery(".check_error")
                  .text(data.error)
                  .removeClass("none")
                  .css("color", "red")
                  .css("width", "100%");
              }
              if (data.valid) {
                jQuery(".check_error")
                  .text(data.valid)
                  .removeClass("none")
                  .css("color", "green");
              }
            }
          },
        });
      }
    });
  }
  // check user
  if (jQuery("#username").length > 0) {
    jQuery("#username").focusout(function () {
      var username = jQuery("#username").val();
      jQuery(".user_error").addClass("none");
      jQuery("#submit").removeAttr("disabled");
      jQuery.ajax({
        type: "POST",
        url: ajaxurl,
        data: {
          uname: username,
          action: "stars_smtpm_check_user",
          id: getParameterByName("id"),
          nonce:
            typeof starsSmtpNotify !== "undefined"
              ? starsSmtpNotify.check_user_nonce
              : "",
        },
        success: function (response) {
          if (response != 0) {
            jQuery(".user_error")
              .text(response)
              .removeClass("none")
              .css("color", "red");
            jQuery("#submit").attr("disabled", "disabled");
          }
        },
      });
    });
  }
  // confirm delete
  jQuery(".confirm-delete").click(function () {
    if (jQuery("#check_admin").val() == 1) {
      if (
        jQuery(this).attr("data-value") == "account" &&
        jQuery(".smtp-activation#" + jQuery(this).attr("data-id")).hasClass(
          "deactivate",
        )
      ) {
        OpenPopup("Action Restricted", "You can not delete activated account!");
        return false;
      } else {
        if (
          confirm(
            "Are you sure you want to delete this " +
              jQuery(this).attr("data-value") +
              "?",
          )
        )
          return true;
        else return false;
      }
    } else {
      OpenPopup(
        "Access Restricted",
        "This feature is available in PRO version!",
      );
      return false;
    }
  });
  jQuery('form[name="smtp_accounts_list"] .button.action').click(function () {
    if (
      jQuery(this).prev().find("option:selected").val() == "delete" &&
      jQuery(".stars-smtp-account-list").length > 0
    ) {
      var stop = 0;
      jQuery(".stars-smtp-account-list input[type='checkbox']").each(
        function (e) {
          if (
            jQuery(this).prop("checked") &&
            jQuery(".smtp-activation#" + jQuery(this).val()).hasClass(
              "deactivate",
            )
          )
            stop = 1;
        },
      );
      if (stop == 1) {
        OpenPopup("Action Restricted", "You can not delete activated account!");
        return false;
      }
    }
  });
});
function SetEmailBody() {
  // Body is now a plain textarea — nothing extra needed
  return true;
}
function getParameterByName(name, url) {
  if (!url) {
    url = window.location.href;
  }
  name = name.replace(/[\[\]]/g, "\\$&");
  var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
    results = regex.exec(url);
  if (!results) return null;
  if (!results[2]) return "";
  return decodeURIComponent(results[2].replace(/\+/g, " "));
}
function OpenPopup(Title, Message) {
  jQuery("<div></div>")
    .appendTo("body")
    .append(jQuery("<p></p>").text(Message))
    .dialog({
      modal: true,
      title: Title,
      zIndex: 10000,
      autoOpen: true,
      width: "400",
      resizable: false,
      buttons: {
        Close: function () {
          jQuery(this).remove();
        },
      },
      close: function (event, ui) {
        jQuery(this).remove();
        return false;
      },
    });
}

jQuery(document).ready(function ($) {
  $("#starsopenModal").on("click", function (e) {
    e.preventDefault();
    $("#stars-pro-notification-modal").css("display", "flex");
    $(".notify-success, .notify-error").remove();
  });

  $(".close-modal").on("click", function () {
    $("#stars-pro-notification-modal").hide();
  });

  $('.stars-pro-notification-modal-box button[type="submit"]').on(
    "click",
    function () {
      var $emailInput = $(
        '.stars-pro-notification-modal-box input[type="email"]',
      );
      var email = $emailInput.val().trim();
      $(".notify-success, .notify-error").remove();

      var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

      if (email === "") {
        $(
          '<div class="notify-error" style="margin-top:15px; color: red; font-size:16px;">Please enter your email.</div>',
        ).insertAfter($(this));
        return;
      }
      if (!emailPattern.test(email)) {
        $(
          '<div class="notify-error" style="margin-top:15px; color: red; font-size:16px;">Please enter a valid email address.</div>',
        ).insertAfter($(this));
        return;
      }
      $.post(starsSmtpNotify.ajax_url, {
        action: "stars_smtp_save_mailer_email",
        email: email,
        nonce: starsSmtpNotify.nonce,
      })
        .done(function (response) {
          if (response.success) {
            $(
              '<div class="notify-success" style="margin-bottom:15px; color: green; font-size:16px;"></div>',
            )
              .text(response.data.message)
              .insertAfter($emailInput);
            $emailInput.val("");
          } else {
            $(
              '<div class="notify-error" style="margin-top:15px; color: red;"></div>',
            )
              .text(response.data.message)
              .insertAfter($emailInput);
          }
        })
        .fail(function () {
          $(
            '<div class="notify-error" style="margin-top:15px; color: red;">Something went wrong. Please try again later.</div>',
          ).insertAfter($emailInput);
        });
    },
  );
});
