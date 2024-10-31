function usePrecisionPayPaymentGateway($) {
  // ONLY RUN IF PLAID IS AVAILABLE
  if (typeof Plaid === 'undefined') {
    console.log('...MISSING PLAID...');
    return;
  }

  var SESSION_STORAGE_PLAID = 'mcPlaidData';
  var SESSION_STORAGE_PRECISION_PAY = 'mcPrecisionPayData';

  // From woocommerce-gateway-precisionpay.php
  var precisionPayNonce = precisionpay_data.precisionPayNonce;
  var ajaxUrl = precisionpay_data.ajaxUrl;
  var orderAmount = precisionpay_data.orderAmount;
  var errorMessageTokenExpired = precisionpay_data.errorMessageTokenExpired;
  var errorMessagePlaidTokenExpired = precisionpay_data.errorMessagePlaidTokenExpired;
  var errorMessageNoValidAccounts = precisionpay_data.errorMessageNoValidAccounts;
  var defaultButtonBg = precisionpay_data.defaultButtonBg;
  var defaultButtonTitle = precisionpay_data.defaultButtonTitle;
  var logoMark = precisionpay_data.logoMark;
  var loadingImg = precisionpay_data.loadingImg;
  var loadingImgLong = precisionpay_data.loadingImgLong;
  var plaidEnv = precisionpay_data.plaidEnv;
  var checkoutPortalURL = precisionpay_data.checkoutPortalURL;

  var mc_merchantNonce = '';

  function init() {
    // IF ALREADY REGISTERED OR LINKED BUT NOT YET REGISTERED SET BUTTON AS LINKED
    if (sessionStorage.getItem(SESSION_STORAGE_PLAID)) {
      // If the user already linked but hasn't registered yet, this saves the data on refresh
      var mcPlaidData = JSON.parse(sessionStorage.getItem(SESSION_STORAGE_PLAID));
      addDataToHiddenFields(mcPlaidData);
      updateUIToSuccess();
    } else if (sessionStorage.getItem(SESSION_STORAGE_PRECISION_PAY)) {
      var precisionPayToken = JSON.parse(sessionStorage.getItem(SESSION_STORAGE_PRECISION_PAY));
      addPPDataToHiddenField(precisionPayToken);
      updateUIToSuccess();
    }

    // We are now listening for submit of the form to add our own loader if user is using PrecisionPay
    $('.woocommerce-checkout').on('submit', function () {
      setPrecisionPayLoader($, loadingImg, loadingImgLong);
    }); // .addEventListener('submit', setLoader);

    // launch checkout portal on PP button click
    $('#precisionpay-link-button').click(authorizePayment);

    // keep an eye out for certain errors we need to handle
    $(document.body).on('checkout_error', function () {
      var errorText = $('.woocommerce-error')
        .find('li')
        .first()
        .text()
        .replace(/(\n|\t)/gm, ''); // For Older WooCommerce
      if (!errorText) {
        errorText = $('.is-error .wc-block-components-notice-banner__content')
          .text()
          .replace(/(\n|\t)/gm, ''); // For WooCommerce v8+
      }
      if (
        errorText &&
        (errorText === errorMessageTokenExpired ||
          errorText === errorMessagePlaidTokenExpired ||
          errorText === errorMessageNoValidAccounts)
      ) {
        resetButtonUI();
        removePPDataFromHiddenField();
        sessionStorage.removeItem(SESSION_STORAGE_PRECISION_PAY);
      }
    });
  }

  // Handle button and link updates for One Time Payments and PrecisionPay payments
  function updateUIToSuccess() {
    $('#precisionpay-link-button')
      .html('✓ Payment Authorized')
      .css({
        backgroundColor: '#00cc00',
        color: 'white',
      })
      .prop('disabled', true);
  }

  function resetButtonUI() {
    $('#precisionpay-link-button')
      .html('<img src="' + logoMark + '" alt="PrecisionPay logo mark"></img>' + defaultButtonTitle)
      .css({
        backgroundColor: defaultButtonBg,
      })
      .prop('disabled', false);
  }

  function addPPDataToHiddenField(precisionPayToken) {
    $('#precisionpay_checkout_token').val(precisionPayToken);
  }

  function removePPDataFromHiddenField() {
    $('#precisionpay_checkout_token').val('');
  }

  function handlePPData(precisionPayToken) {
    addPPDataToHiddenField(precisionPayToken);
    sessionStorage.setItem(SESSION_STORAGE_PRECISION_PAY, JSON.stringify(precisionPayToken));
    updateUIToSuccess();
  }

  function handlePlaidData(plaidData) {
    // Add data to hidden fields
    addDataToHiddenFields(plaidData);
    // Also add to session storage in case we get "refreshed"
    setSessionStorage(plaidData);
    updateUIToSuccess();
  }

  function addDataToHiddenFields(pd) {
    $('#precisionpay_public_token').val(pd.public_token);
    $('#precisionpay_account_id').val(pd.accountId);
    $('#precisionpay_plaid_user_id').val(pd.precisionPayPlaidUserId);
    $('#precisionpay_registered_user_id').val(pd.precisionPayRegisteredUserId); // Used if a user does one time payment after logging in
  }

  function setSessionStorage(pd) {
    sessionStorage.setItem(SESSION_STORAGE_PLAID, JSON.stringify(pd));
  }

  function authorizePayment(e) {
    e.preventDefault();

    let data = {
      precisionPayNonce: precisionPayNonce,
      action: 'prcsnpy_get_merch_nonce',
    };

    $('#payment').block({
      message: null,
      overlayCSS: {
        background: '#fff',
        opacity: 0.6,
      },
    });

    $.ajax({
      type: 'POST',
      url: ajaxUrl,
      data: data,
      success: function (data) {
        if (data && data.body) {
          // Remove any errors
          $('.payment_box.payment_method_wc_gateway_precisionpay .error').remove();
          mc_merchantNonce = data.body.merchantNonce;
          $('#payment').unblock();
          openPrecisionPay(mc_merchantNonce, orderAmount);
        } else {
          if (data.result === 'failed') {
            var mcErrorMessage = '<p class="error" style="color: red">' + data.message + '</p>';
            $('.payment_box.payment_method_wc_gateway_precisionpay').prepend(mcErrorMessage);
          } else {
            console.log('Whoops. Error.', data);
          }
          $('#payment').unblock();
        }
      },
      error: function (err) {
        console.log(err);
        if (err && err.message) {
          var mcErrorMessage = '<p class="error" style="color: red">' + err.message + '</p>';
          $('.payment_box.payment_method_wc_gateway_precisionpay').prepend(mcErrorMessage);
        }
        $('#payment').unblock();
      },
    });
  }

  function openPrecisionPay(merchantNonce, amount) {
    var mcPaymentWindow = $('.mc-payment-portal');

    function removePPEventListener() {
      window.removeEventListener('message', handleCompletedLogin);
    }

    function handleCompletedLogin(event) {
      var message = event.data.message;

      switch (message) {
        case 'PrecisionPay::success':
          var precisionPayToken = event.data.precisionPayToken;
          if (precisionPayToken) {
            handlePPData(precisionPayToken);
          } else {
            var plaidData = event.data.plaidData;
            if (plaidData) {
              handlePlaidData(plaidData);
            }
          }

          mcPaymentWindow.remove();
          removePPEventListener();
          break;
        case 'PrecisionPay::failed':
          var error = event.data.error_message;
          var mcErrorMessage = '<p class="error" style="color: red">' + error + '</p>';
          $('.payment_box.payment_method_wc_gateway_precisionpay').prepend(mcErrorMessage);
          removePPEventListener();
          break;
        case 'PrecisionPay::canceled':
          mcPaymentWindow.hide();
          removePPEventListener();
          break;
      }
    }

    if (mcPaymentWindow.length) {
      mcPaymentWindow.show();
      removePPEventListener(); // clear any existing listeners first
      window.addEventListener('message', handleCompletedLogin);
    } else {
      mcPaymentWindow = $(`
      <div class="mc-payment-portal mc-overlay">
        <iframe class="mc-payment-window" src="${checkoutPortalURL}/checkout-login/${encodeURI(
        merchantNonce
      )}/amount/${encodeURI(amount)}/env/${plaidEnv}" title="Log in to PrecisionPay"></iframe>
      </div>
      `);
      var mcPaymentStyles = `
      <style>
        .mc-overlay {
          display:none;
          background: rgba(255,255,255,0.5);
          position: fixed;
          top: 0vh;
          left: 0vw;
          width: 100vw;
          height: 100vh;
          z-index: 524287;
          transition: 0.5s;
        }
        iframe.mc-payment-window { 
          position: fixed;
          top: 0vh;
          left: 0vw;
          border-width: 0px;
          width: 100vw;
          height: 100vh;
        }
      </style>
      `;
      $('body').append(mcPaymentStyles);
      $('body').append(mcPaymentWindow);
      $(mcPaymentWindow).show();

      window.addEventListener('message', handleCompletedLogin);
    }
  }

  return {
    init: init,
  };
}
