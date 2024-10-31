(function ($) {
  'use strict';

  $(document).ready(function () {
    videoModal();
    feedbackForm();
  });

  function videoModal() {
    if ($('.venobox').length) {
      $('.venobox').venobox();
    }

    $(document).on('click', '.banner-info button.notice-dismiss', function (e) {
      e.preventDefault();

      var days = -1;
      var data = {
        action: 'rapidsec_banner_time',
        days: days,
      };

      jQuery(this).closest('.notice').slideUp();

      jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: data,
        success: function (data, textStatus, xhr) {
          return;
        },
        error: function (error) {
          console.log(error);
        },
      });
    });
  }

  function feedbackForm() {
    $("#the-list [data-slug='rapidsec-csp-and-security-headers'] .deactivate>a").on('click', function (event) {
      rapidsec_run_on_deactivate(event);
    });

    function rapidsec_run_on_deactivate(event) {
      event.preventDefault();

      var deactivateURL = event.target.href;
      var reasons = window.deactivate_feedback_form_reasons;
      var texts = window.deactivate_feedback_form_text;

      var element = $(
        '\
        <div id="file-editor-warning" class="notification-dialog-wrap file-editor-warning">\
          <div class="notification-dialog-background"></div>\
          <div class="notification-dialog">\
            <form class="file-editor-warning-content">\
              <div class="file-editor-warning-message">\
                <h1>' +
          texts['quick_feedback'] +
          '</h1>\
                <h3 style="font-weight: unset;">' +
          texts['foreword'] +
          '</h3>\
                <div class="reasons-list"></div>\
              </div>\
              <p class="file-editor-buttons">\
                <a class="button file-editor-warning-go-back" href="' +
          deactivateURL +
          '">' +
          texts['skip_and_deactivate'] +
          '</a>\
                <button type="submit" class="file-editor-warning-dismiss button button-primary">' +
          texts['submit_and_deactivate'] +
          '</button>\
              </p>\
            </form>\
          </div>\
        </div>\
        '
      );

      var reasonsList = $(element).find('div.reasons-list');
      for (var key in reasons) {
        reasonsList.append('\
        <div>\
          <input type="radio" id="' + key + '" name="reason" value="' + key + '" >\
          <label for="' + key + '">' + reasons[key] + '</label>\
        </div>\
        ');
      }

      $(element).find('form').on('submit', rapidsec_feedback_on_submit);

      $('body').append(element);

      function rapidsec_feedback_on_submit(event) {
        var strings = deactivate_feedback_form_text;
        var value = $(element).find('form').serializeArray();

        event.preventDefault();

        $(element).find("button[type='submit']").prop('disabled', true);

        if (!$(element).find("input[name='reason']:checked").length) {
          $(element).find("button[type='submit']").val(strings.please_wait);
          window.location.href = deactivateURL;

          return false;
        }

        $(element).find("button[type='submit']").val(strings.thank_you);

        var data = {
          action: 'rapidsec_plugin_deactivate',
          form_data: value,
        };

        $.ajax({
          type: 'POST',
          url: ajaxurl,
          data: data,
          dataType: 'json',
          beforeSend: function () {
            $("<p class='loading'>Sending info..</p>").insertAfter('.file-editor-buttons');
          },
          success: function (data) {
            $('p.loading').html('Data Sent, Thank You!');
            window.location.href = deactivateURL;
          },
        });

        return false;
      }
    }
  }
})(jQuery);
