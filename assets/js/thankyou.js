function completePrecisionPayExperience($) {
  var sessionStoragePrecisionPay = precisionpay_data.sessionStoragePrecisionPay;
  var sessionStoragePlaid = precisionpay_data.sessionStoragePlaid;
  // Remove Plaid & PrecisionPay session storage data
  sessionStorage.removeItem(sessionStoragePrecisionPay);
  sessionStorage.removeItem(sessionStoragePlaid);
}

jQuery(document).ready(function completePrecisionPay() {
  completePrecisionPayExperience(jQuery);
});
