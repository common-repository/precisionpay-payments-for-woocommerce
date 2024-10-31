(function ($) {
  $(document).ready(function () {
    mcAddCheckboxSSLCheck();

    function mcAddCheckboxSSLCheck() {
      var testModeCheckbox = $(
        '#woocommerce_wc_gateway_precisionpay_enableTestMode'
      );

      // Exit if the checkbox doesn't exist exit.
      if (testModeCheckbox.length === 0) {
        return;
      }

      if (!testModeCheckbox[0].checked) {
        appendWarning();
      }

      testModeCheckbox.click(function () {
        if (testModeCheckbox[0].checked) {
          removeWarning();
        } else {
          appendWarning();
        }
      });

      function appendWarning() {
        testModeCheckbox
          .parent()
          .append(
            '<span id="mc_ssl_warning" class="warning" style="color: red; display: block;">Warning: The PrecisionPay plugin will not work with SSL disabled. Please enable SSL on your site or keep Test Mode checked to test.</span>'
          );
      }

      function removeWarning() {
        $('#mc_ssl_warning').remove();
      }
    }
  });
})(jQuery);
