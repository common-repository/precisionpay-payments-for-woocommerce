function setPrecisionPayLoader($, loadingImg, loadingImgLong) {
  const ppRadioButton = document.getElementById('payment_method_wc_gateway_precisionpay');

  if (ppRadioButton.checked === true) {
    // check if spinner style doesn't exist
    if ($('.precisionPayCustomLoader').length === 0) {
      const spinnerStyles =
        `
        .woocommerce .blockUI.blockOverlay:before,
        .woocommerce .loader:before {
          height: 200px;
          width: 200px;
          position: absolute;
          top: 50%;
          left: 50%;
          margin-left: -100px;
          margin-top: -100px;
          display: block;
          content: "";
          -webkit-animation: none;
          -moz-animation: none;
          animation: none;
          background-image: url('` +
        loadingImg +
        `') !important;
          background-position: center center;
          background-size: cover;
          line-height: 1;
          text-align: center;
          font-size: 2em;
        }
        .woocommerce .blockUI.blockOverlay {
          opacity: 0.9 !important;
        }`;
      const customLoaderBlock = document.createElement('div');
      customLoaderBlock.className = 'precisionPayCustomLoader';
      // const spinnerStyle = document.createElement('style');
      // spinnerStyle.innerText = spinnerStyles;
      customLoaderBlock.innerHTML = '<style type="text/css">' + spinnerStyles + '</style>';
      document.body.append(customLoaderBlock);
    }
    setTimeout(() => {
      $('.woocommerce .blockUI.blockOverlay').append(
        `
            <style type="text/css">
            body .woocommerce .blockUI.blockOverlay:before,
            body .woocommerce .loader:before {
              background-image: url('` +
          loadingImgLong +
          `') !important;
            }
            .woocommerce .blockUI.blockOverlay .precisionPayLoadingFullPNG {
              width: 350px;
              height: 350px;
              position: absolute;
              top: 50%;
              left: 50%;
              margin-left: -175px;
              margin-top: -175px;
              display: block;
            }
            .woocommerce .blockUI.blockOverlay {
              position: fixed !important;
            }
            </style>
            `
      );
    }, 100);
  } else {
    // if using another payment method after attempting and failing with
    // PrecisionPay, we want to remove the added styles
    $('.precisionPayCustomLoader').remove();
  }
}
