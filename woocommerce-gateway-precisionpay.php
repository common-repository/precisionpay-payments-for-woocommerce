<?php

/**
 * Plugin Name:          PrecisionPay Payments for WooCommerce
 * Plugin URI:           https://github.com/MakeCents-NYC/woocommerce-gateway-precisionpay
 * Description:          Accept online bank payments in your WooCommerce store with PrecisionPay.
 * Version:              3.4.0
 * Requires PHP:         7.2
 * Requires at least:    5.9
 * Tested up to:         6.6
 * WC requires at least: 3.9
 * WC tested up to:      9.3
 * Author:               PrecisionPay
 * Author URI:           https://www.myprecisionpay.com
 * License:              GPL-3.0
 * License URI:          https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:          precisionpay-payments-for-woocommerce
 * Domain Path:          /languages
 */

if (!defined('ABSPATH')) {
  exit;
}

// Make sure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
  return;
}

// Ajax call to get the merchant API Key
function prcsnpy_get_merch_nonce()
{
  check_ajax_referer('precision_pay_ajax_nonce', 'precisionPayNonce');
  if (!class_exists('PrecisionPay_Payments_For_WC')) return;

  $mcInstance = new PrecisionPay_Payments_For_WC();
  $mcInstance->get_merchant_nonce();
}

add_action('wp_ajax_prcsnpy_get_merch_nonce', 'prcsnpy_get_merch_nonce');
add_action('wp_ajax_nopriv_prcsnpy_get_merch_nonce', 'prcsnpy_get_merch_nonce');

/**
 * Add the gateway to WC Available Gateways
 * 
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + offline gateway
 */
function prcsnpy_add_to_gateways($gateways)
{
  $gateways[] = 'PrecisionPay_Payments_For_WC';
  return $gateways;
}
add_filter('woocommerce_payment_gateways', 'prcsnpy_add_to_gateways');

/**
 * PrecisionPay Payment Gateway
 *
 * Provides direct bank payments easily.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class       PrecisionPay_Payments_For_WC
 * @extends     WC_Payment_Gateway
 * @version     3.3.3
 * @package     WooCommerce/Classes/Payment
 * @author      PrecisionPay
 */
add_action('plugins_loaded', 'prcsnpy_init', 11);

function prcsnpy_init()
{
  if (!class_exists('PrecisionPay_Payments_For_WC')) :
    define('PRCSNPY_PLUGIN_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));
    define('PRCSNPY_PLUGIN_NAME', 'PrecisionPay Payments for WooCommerce');
    define('PRCSNPY_VERSION', '3.3.3');

    class PrecisionPay_Payments_For_WC extends WC_Payment_Gateway
    {
      const PRECISION_PAY_BRAND_COLOR = '#F15A29';
      const PRECISION_PAY_TITLE = 'PrecisionPay';
      const ERROR_MESSAGE_EXPIRED_PLAID_TOKEN = 'Your account authorization has expired, authorizations expire after 30 minutes';
      const ERROR_MESSAGE_NO_BANK_ACCOUNT_FOUND = 'There are no valid checking or savings account(s) associated with this Item.';

      // Session constants
      const SESSION_STORAGE_PRECISION_PAY = 'mcPrecisionPayData';
      const SESSION_STORAGE_PLAID = 'mcPlaidData';

      // Plaid Environments
      const PLAID_ENV_SANDBOX = 'sandbox';
      const PLAID_ENV_PRODUCTION = 'production';

      // PrecisionPay Environments
      const PP_ENV_SANDBOX = 'sandbox';
      const PP_ENV_PRODUCTION = 'live';

      // API URL
      const API_URL_PROD = 'https://api.myprecisionpay.com/api';

      // Checkout portal URL
      const CHECKOUT_PORTAL_URL_PROD = 'https://checkout.myprecisionpay.com';

      // Class Variables
      public $id;
      public $icon;
      public $has_fields;
      public $method_title;
      public $method_description;
      public $title;
      public $description;
      public $enabled;
      private $logo_mark;
      private $loading_icon;
      private $loading_icon_long;
      private $brand_title;
      private $button_title;
      private $enableTestMode;
      private $env;
      private $plaid_env;
      private $api_key;
      private $api_secret;
      private $hasAPIKeys;
      private $api_key_header;
      private $nonce;
      private $api_url;
      private $checkout_url;
      public $supports;

      public function __construct()
      {
        $this->id                  = 'wc_gateway_precisionpay';
        $this->icon                = PRCSNPY_PLUGIN_URL . '/assets/img/precisionpay_logo_2x.png';
        $this->logo_mark           = PRCSNPY_PLUGIN_URL . '/assets/img/logo_mark_white.svg';
        $this->loading_icon        = PRCSNPY_PLUGIN_URL . '/assets/img/pp_loading_screen_300.png';
        $this->loading_icon_long   = PRCSNPY_PLUGIN_URL . '/assets/img/pp_loading_screen_w_text.png';
        $this->has_fields          = true;
        $this->method_title        = self::PRECISION_PAY_TITLE;
        $this->method_description  = __('Welcome to PrecisionPay, the Second Amendment payments company.<br />If you already have a merchant account, enter your keys below. If not, visit myprecisionpay.com to apply for a merchant account.', 'precisionpay-payments-for-woocommerce');
        $this->brand_title         = self::PRECISION_PAY_TITLE;
        $this->title               = self::PRECISION_PAY_TITLE;
        $this->button_title        = __('Authorize Payment', 'precisionpay-payments-for-woocommerce');
        $this->description         = __("Stop being tracked! Protect your privacy and your liberty with PrecisionPay, your 2nd Amendment payment service. It's free, and no membership required!", 'precisionpay-payments-for-woocommerce');
        $this->enabled             = $this->get_option('enabled');
        $this->enableTestMode      = 'yes' === $this->get_option('enableTestMode'); // Checkbox comes in as yes if checked
        $this->env                 = $this->enableTestMode ? self::PP_ENV_SANDBOX : self::PP_ENV_PRODUCTION;
        $this->plaid_env           = $this->enableTestMode ? self::PLAID_ENV_SANDBOX : self::PLAID_ENV_PRODUCTION;
        $this->api_key             = $this->get_option('api_key');
        $this->api_secret          = $this->get_option('api_secret');
        $this->hasAPIKeys          = $this->api_key && $this->api_secret;
        $this->api_key_header      = json_encode(array('apiKey' => $this->api_key, 'apiSecret' => $this->api_secret));
        $this->api_url             = self::API_URL_PROD;
        $this->checkout_url        = self::CHECKOUT_PORTAL_URL_PROD;
        $this->supports            = array('products', 'refunds');

        // Admin actions
        add_action('admin_init', array($this, 'add_privacy_message'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        // We need custom JavaScript to obtain a token
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

        // Customer actions after checkout is complete
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
        add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();
      }

      /**
       * Gets the privacy message for merchants
       */
      public function add_privacy_message()
      {
        if (!function_exists('wp_add_privacy_policy_content')) {
          return;
        }

        $content = wpautop(
          sprintf(
            wp_kses(
              __('By using this plugin, you consent to storage and use of personal data required to send and receive payments. <a href="%s" target="_blank">View the PrecisionPay Privacy Policy to see what you may want to include in your privacy policy.</a>', 'precisionpay-payments-for-woocommerce'),
              array(
                'a' => array(
                  'href'   => array(),
                  'target' => array(),
                ),
              )
            ),
            'https://www.myprecisionpay.com/privacy-policy'
          )
        );

        wp_add_privacy_policy_content(PRCSNPY_PLUGIN_NAME, $content);
      }

      /**
       * Get the merchant API Key for use with PrecisionPay Login
       */
      public function get_merchant_nonce()
      {
        // Make sure the API Key has been added first
        $merchantApiKey = $this->api_key;

        if (!$merchantApiKey) {
          $errorNotice = __('We are unable to process your pay request at the moment. Please refresh the page and try again', 'precisionpay-payments-for-woocommerce');
          $this->ajaxFailedResponse($errorNotice);
          return;
        }

        $response = $this->api_get('/merchant-nonce');

        if (is_wp_error($response)) {
          $errorNotice = __('Error processing server request.', 'precisionpay-payments-for-woocommerce');
          $this->ajaxFailedResponse($errorNotice);
          return;
        }

        $response_body = wp_remote_retrieve_body($response);


        $response_data = json_decode($response_body);

        if (empty($response_body)) {
          $errorNotice = __('Error processing server request at this time. Please try again later.', 'precisionpay-payments-for-woocommerce');
          $this->ajaxFailedResponse($errorNotice);
          return;
        }

        if (!$response_data->merchantNonce) {
          $errorNotice = $response_body; // __('Error processing server request at this time. Please try again later.', 'precisionpay-payments-for-woocommerce');
          $this->ajaxFailedResponse($errorNotice);
          return;
        }

        $merchantNonce = $response_data->merchantNonce;


        wp_send_json(
          array(
            'result'  => 'success',
            'message' => 'nonce retrieved',
            'body'    => array(
              'merchantNonce'  => $merchantNonce
            ),
          )
        );
      }

      private function ajaxFailedResponse($message)
      {
        wp_send_json(
          array(
            'result'  => 'failed',
            'message' => $message,
          )
        );
      }

      /**
       * Renders a custom settings page
       */
      function admin_options()
      {
        $this->render_header();
        $this->render_settings();
      }

      public function render_header()
      {
        echo '
        <div class="precisionpay-settings-page-header">
            <div class="top-section">
              <img alt="PrecisionPay" class="precisionpay-logo" width="380" src="' . esc_url($this->icon) . '"/>
              <h4 class="precisionpay-tagline">' . esc_html__('Fast, Secure, Payments, for the Second Amendment Community.', 'precisionpay-payments-for-woocommerce') . '</h4>
              <a class="button" target="_blank" href="mailto:support@myprecisionpay.com">'
          . esc_html__('Get Help', 'precisionpay-payments-for-woocommerce') .
          '</a>
          </span>
            </div>
            <h3>Welcome to PrecisionPay, the Second Amendment payments company.</h3>
            <p>If you already have a merchant account, enter your keys below. If not, visit 
            <a target="_blank" href="https://www.myprecisionpay.com">myprecisionpay.com</a> 
            to apply for a merchant account.</p>
          </div>
          ';

        // TODO: add these links once we have the corresponding pages
        //     <a class="button" target="_blank" href="https://myprecisionpay.com/document/woocommerce-precisionpay/">'
        // . __('Documentation', 'precisionpay-payments-for-woocommerce') .
        // '</a>
        //     <a class="button" target="_blank" href="https://myprecisionpay.com/document/woocommerce-precisionpay/#get-help">'
        // . __('Get Help', 'precisionpay-payments-for-woocommerce') .
        // '</a>
        //     <span class="precisionpay-right-align">
        //       <a target="_blank" href="https://myprecisionpay.com/feature-requests/woocommerce-precisionpay/">'
        // . __('Request a feature', 'precisionpay-payments-for-woocommerce') .
        // '</a>
        //       <a target="_blank" href="https://github.com/MakeCents-NYC/woocommerce-gateway-precisionpay/issues/new?assignees=&labels=type%3A+bug&template=bug_report.md">'
        // . __('Submit a bug', 'precisionpay-payments-for-woocommerce') .
        // '</a>
      }

      public function render_settings()
      {
        echo '<table class="form-table">' . $this->generate_settings_html($this->get_form_fields(), false) . '</table>';
      }

      /**
       * Initialize Gateway Settings Form Fields
       */
      public function init_form_fields()
      {
        $this->form_fields = apply_filters('prcsnpy_form_fields', array(
          'enabled' => array(
            'title'   => __('Enable/Disable', 'precisionpay-payments-for-woocommerce'),
            'label'   => __('Enable PrecisionPay Payment Gateway', 'precisionpay-payments-for-woocommerce'),
            'type'    => 'checkbox',
            'default' => 'yes'
          ),
          'enableTestMode' => array(
            'title'       => __('Enable Test Mode', 'precisionpay-payments-for-woocommerce'),
            'label'       => __('Enable Test Mode', 'precisionpay-payments-for-woocommerce'),
            'type'        => 'checkbox',
            'description' => __('Place the payment gateway in test mode to test the plugin without needing to spend any money.', 'precisionpay-payments-for-woocommerce'),
            'default'     => '',
            'desc_tip'    => true,
          ),
          'api_key' => array(
            'title'       => __('API Key', 'precisionpay-payments-for-woocommerce'),
            'type'        => 'text'
          ),
          'api_secret' => array(
            'title'       => __('API Secret', 'precisionpay-payments-for-woocommerce'),
            'type'        => 'password'
          ),
        ));
      }

      /**
       * Needed for custom payment gateway form
       */
      public function payment_fields()
      {
        // Let's require SSL unless the website is in test mode
        if (!$this->enableTestMode && !is_ssl()) {
          echo '<div>
                  <p class="error" style="color: red">
                    ' . esc_html__(
            'SSL is required for the PrecisionPay payment gateway. Please enable SSL on your site to continue.',
            'precisionpay-payments-for-woocommerce'
          ) . '
                  </p>
                </div>';
          return;
        }

        // We want the business owner to know that the api key and secret are necessary so they don't go live without them
        if (!$this->hasAPIKeys) {
          echo '<p class="error" style="color: red">The ' . esc_html($this->brand_title) . ' plugin is not fully configured yet and will not work. Please complete the configuration process before going live with this plugin.</p>';
          return;
        }

        if ($this->enableTestMode) {
          $this->description .= ' TEST MODE ENABLED. Use this mode for testing purposes only. You can find the Guest/One-time-pay test credentials in this <a href="https://plaid.com/docs/quickstart/" target="_blank" rel="noopener noreferrer">documentation</a>.';
          $this->description  = trim($this->description);
        }

        if ($this->description != '') {
          // display the description with <p> tags
          echo wpautop(wp_kses_post($this->description));
        }
?>
        <fieldset id="wc-<?php echo esc_attr($this->id); ?>-mc-form" class="wc-precisionpay-form wc-payment-form" style="background:transparent;">
          <div style="display: none;">
            <input name="precisionpay_public_token" id="precisionpay_public_token" type="hidden" />
            <input name="precisionpay_account_id" id="precisionpay_account_id" type="hidden" />
            <input name="precisionpay_plaid_user_id" id="precisionpay_plaid_user_id" value="" type="hidden" />
            <input name="precisionpay_registered_user_id" id="precisionpay_registered_user_id" value="" type="hidden" />
            <input name="precisionpay_checkout_token" id="precisionpay_checkout_token" value="" type="hidden" />
            <?php wp_nonce_field('precisionpay_gateway_nonce', 'precisionpay_nonce'); ?>
          </div>
          <button id="precisionpay-link-button" class="precisionpay-plaid-link-button" style="background-color: <?php echo esc_html(self::PRECISION_PAY_BRAND_COLOR); ?>;"><img src="<?php echo esc_url($this->logo_mark); ?>" alt="PrecisionPay logo mark" /><?php echo esc_html($this->button_title); ?></button>
          <div class="clear"></div>
          <div class="clear"><img class="precisionPayLoadingFullPNG" src="<?php echo esc_url($this->loading_icon_long); ?>" /></div>
          <script type="text/javascript">
            jQuery(function() {
              var precisionPayPaymentGateway = usePrecisionPayPaymentGateway(jQuery);
              precisionPayPaymentGateway.init();
            });
          </script>
        </fieldset>
<?php
      }

      /**
       * Fields validation before process_payment() is triggered
       */
      public function validate_fields()
      {
        if (!isset($_POST['precisionpay_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['precisionpay_nonce'])), "precisionpay_gateway_nonce")) {
          $errorNotice = __('Server Error validating nonce. Please try again.');
          wc_add_notice($errorNotice, 'error');
          return false;
        }

        if (empty($_POST['precisionpay_public_token']) && empty($_POST['precisionpay_checkout_token'])) {
          wc_add_notice('You must authorize the payment before you can complete the order', 'error');
          return false;
        }
        return true;
      }

      /**
       * Custom JS for admin side of the plugin
       */
      public function admin_scripts($hook)
      {
        // Only add to the PrecisionPay woocommerce settings page
        if ('woocommerce_page_wc-settings' !== $hook) {
          return;
        }

        wp_enqueue_style('precisionpay-admin-styles', PRCSNPY_PLUGIN_URL . '/assets/css/admin.css');

        // Only add this script if SSL is not enabled
        if (!is_ssl()) {
          wp_enqueue_script('mc_admin_script_ssl', PRCSNPY_PLUGIN_URL . '/assets/js/admin-script-ssl.js');
        }
      }

      /**
       * Custom CSS and JS, in most cases required only when you decided to go with a custom form
       */
      public function payment_scripts()
      {
        // We only need to add styles and scripts on cart/checkout and pay-for-order pages
        if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
          return;
        }

        // If our payment gateway is disabled, we do not have to enqueue JS
        if ('no' === $this->enabled) {
          return;
        }

        // Add styles
        wp_enqueue_style('precisionpay-styles', PRCSNPY_PLUGIN_URL . '/assets/css/main.css');

        // Require SSL unless the website is in a test mode
        if (!$this->enableTestMode && !is_ssl()) {
          return;
        }

        // Using Plaid Link to obtain a token
        wp_enqueue_script('plaid_link', 'https://cdn.plaid.com/link/v2/stable/link-initialize.js', array(), null, true);

        // Scripts to handle authorize payment button
        global $woocommerce;

        // Let's check to see if this is an invoice (if it is then the page will have an order-pay parameter). 
        $order_amount = $woocommerce->cart->total; // In the checkout page we'll use the cart total
        if (isset($_GET['order-pay'])) {
          $order_id = sanitize_text_field($_GET['order-pay']);
          $order = wc_get_order($order_id);
          $order_amount = $order->get_total();
        }

        wp_enqueue_script(
          'pp-loader-script',
          PRCSNPY_PLUGIN_URL . '/assets/js/pp-loader.js',
          array('jquery'),
          PRCSNPY_VERSION,
          array('in_footer' => true,)
        );

        wp_enqueue_script(
          'precisionpay-script',
          PRCSNPY_PLUGIN_URL . '/assets/js/precisionpay.js',
          array('jquery'),
          PRCSNPY_VERSION,
          array('strategy' => 'defer', 'in_footer' => true,)
        );

        wp_localize_script(
          'precisionpay-script',
          'precisionpay_data',
          array(
            'precisionPayNonce' => wp_create_nonce("precision_pay_ajax_nonce"),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'orderAmount' => $order_amount,
            'errorMessageTokenExpired' => __('Your PrecisionPay token expired, please log back in again', 'precisionpay-payments-for-woocommerce'),
            'errorMessagePlaidTokenExpired' => __('Your account authorization has expired, authorizations expire after 30 minutes', 'precisionpay-payments-for-woocommerce'),
            'errorMessageNoValidAccounts' => __('There are no valid checking or savings accounts associated with this bank. Please reauthorize payment to fix this error.', 'precisionpay-payments-for-woocommerce'),
            'defaultButtonBg' => self::PRECISION_PAY_BRAND_COLOR,
            'defaultButtonTitle' => $this->button_title,
            'logoMark' => $this->logo_mark,
            'loadingImg' => $this->loading_icon,
            'loadingImgLong' => $this->loading_icon_long,
            'plaidEnv' => $this->plaid_env,
            'checkoutPortalURL' => $this->checkout_url,
          )
        );
      }

      /**
       * Once the form is validated we can process the payment.
       * First we'll process the payment with the PrecisionPay API.
       * Next we'll go through the woocommerce checkout process to finish up.
       */
      public function process_payment($order_id)
      {
        global $woocommerce;
        $order = new WC_Order($order_id);
        $payResponse_body = null;
        $precisionpayCheckoutToken = sanitize_text_field($_POST['precisionpay_checkout_token']);

        if ($precisionpayCheckoutToken) {
          $payResponse_body = $this->pay_with_precisionpay($order, $order_id, $precisionpayCheckoutToken);
        } else {
          $payResponse_body = $this->pay_with_plaid($order, $order_id);
        }

        try {
          if ($payResponse_body && isset($payResponse_body->message) && $payResponse_body->message == 'success') {
            $order->add_order_note(__('Customer successfully paid through PrecisionPay payment gateway', 'precisionpay-payments-for-woocommerce'));
            wc_reduce_stock_levels($order_id);
            $order->payment_complete();
            $woocommerce->cart->empty_cart();

            // Redirect to thank you page
            return array(
              'result'   => 'success',
              'redirect' => $this->get_return_url($order),
            );
          } else {
            // Transaction was not successful ...Add notice to the cart
            $errorNotice = __('An unknown error occured while attempting to charge your account', 'precisionpay-payments-for-woocommerce');
            $responseErrorMessage = $payResponse_body ? $payResponse_body->detail : null;
            if ($responseErrorMessage) {
              if ($responseErrorMessage === 'PrecisionPay token invalid') {
                // Handle expired token
                $errorNotice = __('Your PrecisionPay token expired, please log back in again', 'precisionpay-payments-for-woocommerce');
              } else if ($responseErrorMessage === self::ERROR_MESSAGE_EXPIRED_PLAID_TOKEN) {
                $errorNotice = __('Your account authorization has expired, authorizations expire after 30 minutes', 'precisionpay-payments-for-woocommerce');
              } else if ($responseErrorMessage === self::ERROR_MESSAGE_NO_BANK_ACCOUNT_FOUND) {
                $errorNotice = __('There are no valid checking or savings accounts associated with this bank. Please reauthorize payment to fix this error.', 'precisionpay-payments-for-woocommerce');
              } else {
                $errorNotice = '';
                if (is_array($responseErrorMessage)) {
                  $errorNotice .= $payResponse_body->detail[0];
                } else {
                  $errorNotice .= $payResponse_body->detail;
                }
              }
            }
            wc_add_notice($errorNotice, 'error');
            // Also add note to the order for wp-admin reference
            $order->add_order_note('Error: ' . $errorNotice);
          }
        } catch (Exception $e) {
          wc_add_notice($e->getMessage(), 'error');
          return;
        }
      }

      private function validate_hidden_field($field, $message = '')
      {
        if (!preg_match("/^[0-9a-zA-Z\-]+$/", $field)) {
          $error_message = __('Error processing payment.', 'precisionpay-payments-for-woocommerce') . ' ' . $message;
          throw new Exception($error_message);
        }
      }

      private function pay_with_precisionpay($order, $order_id, $precisionpayCheckoutToken)
      {
        // Validate field first
        $this->validate_hidden_field($precisionpayCheckoutToken, __('Invalid checkout token.', 'precisionpay-payments-for-woocommerce'));

        $orderNumber = $this->get_order_number($order, $order_id);
        $paymentData = array(
          'precisionPayToken' => $precisionpayCheckoutToken,
          'amount' => floatval($order->get_total()),
          'order'  => strval($orderNumber),
          'env'    => $this->env,
        );

        $payResponse = $this->api_post('/checkout/pay', $paymentData);

        if (is_wp_error($payResponse)) {
          throw new Exception(__('Error processing payment.', 'precisionpay-payments-for-woocommerce'));
        }

        if (empty($payResponse['body'])) {
          throw new Exception(__('Error processing payment at this time. Please try again later.', 'precisionpay-payments-for-woocommerce'));
        }

        // Retrieve the body's resopnse if no top level errors found
        $payResponse_body = json_decode(wp_remote_retrieve_body($payResponse));

        return $payResponse_body;
      }

      private function pay_with_plaid($order, $order_id)
      {
        $publicToken = sanitize_text_field($_POST['precisionpay_public_token']);
        $accountId = sanitize_text_field($_POST['precisionpay_account_id']);
        $precisionpay_user_id = sanitize_text_field($_POST['precisionpay_plaid_user_id']);
        $precisionpay_registered_user_id = sanitize_text_field($_POST['precisionpay_registered_user_id']);

        // Make sure the plaid link returned what we needed
        if (!$publicToken || !$accountId || !$precisionpay_user_id) {
          throw new Exception(__('Your bank account is not linked. Please link your account.', 'precisionpay-payments-for-woocommerce'));
        }

        // validate the input:
        $this->validate_hidden_field($publicToken, __('Invalid token.', 'precisionpay-payments-for-woocommerce'));
        $this->validate_hidden_field($accountId, __('Invalid id.', 'precisionpay-payments-for-woocommerce'));
        $this->validate_hidden_field($precisionpay_user_id, __('Invalid Plaid user id.', 'precisionpay-payments-for-woocommerce'));

        // This is optional and only gets set if the customer logs into our Customer Portal but does one-time-pay with Plaid
        if ($precisionpay_registered_user_id) {
          $this->validate_hidden_field($precisionpay_registered_user_id, __('Invalid PrecisionPay Account.', 'precisionpay-payments-for-woocommerce'));
        }

        $orderNumber = $this->get_order_number($order, $order_id);

        $paymentData = array(
          'public_token'     => $publicToken,
          'account_id'       => $accountId,
          'first_name'       => $order->get_billing_first_name(),
          'last_name'        => $order->get_billing_last_name(),
          'business_name'    => $order->get_billing_company(), //? $order->get_billing_company() : 'PrecisionPay Default',
          'email'            => $order->get_billing_email(),
          'one_time_user_id' => $precisionpay_user_id, // string
          'external_user_id' => $precisionpay_registered_user_id, // We need to pass this, but it will usually be an empty string
          'amount'           => floatval($order->get_total()), // number (int or decimal)
          'order'            => strval($orderNumber), // <- Needs to come in as a string
          'env'              => $this->env, // Lets API know if we are in sandbox or live mode
        );

        $payResponse = $this->api_post('/checkout/one-time-payment', $paymentData);

        if (is_wp_error($payResponse)) {
          throw new Exception(__('Error processing payment.', 'precisionpay-payments-for-woocommerce'));
        }

        if (empty($payResponse['body'])) {
          throw new Exception(__('Error processing payment at this time. Please try again later.', 'precisionpay-payments-for-woocommerce'));
        }

        // Retrieve the body's resopnse if no top level errors found
        $payResponse_body = json_decode(wp_remote_retrieve_body($payResponse));

        return $payResponse_body;
      }

      private function api_post($endpoint, $paymentData)
      {
        $referer = sanitize_url($_SERVER['HTTP_REFERER']);
        $response = wp_remote_post($this->api_url . $endpoint, array(
          'method'  => 'POST',
          'headers' => array(
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
            'X-Application-Access' => $this->api_key_header,
            'Referer' => $referer
          ),
          'body'    => json_encode($paymentData), // http_build_query($payload),
          'timeout' => 90,
        ));

        return $response;
      }

      private function api_get($endpoint)
      {
        $referer = sanitize_url($_SERVER['HTTP_REFERER']);
        $response = wp_remote_get($this->api_url . $endpoint, array(
          'headers' => array(
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
            'X-Application-Access' => $this->api_key_header,
            'Referer' => $referer
          ),
          'timeout' => 90,
        ));

        return $response;
      }

      private function get_order_number($order, $order_id)
      {
        $orderNumber = $order_id;
        $orderMetaOrderNumber = $order->get_meta('_order_number');

        // Check for custom order number
        if ($orderMetaOrderNumber) {
          $orderNumber = $orderMetaOrderNumber;
        }

        return $orderNumber;
      }

      /**
       * Output for the order received page.
       */
      public function thankyou_page()
      {

        wp_enqueue_script(
          'precisionpay-thankyou-script',
          PRCSNPY_PLUGIN_URL . '/assets/js/thankyou.js',
          array('jquery'),
          PRCSNPY_VERSION,
          array('strategy' => 'defer', 'in_footer' => true,)
        );
        wp_localize_script(
          'precisionpay-thankyou-script',
          'precisionpay_data',
          array(
            'sessionStoragePrecisionPay' => esc_js(self::SESSION_STORAGE_PRECISION_PAY),
            'sessionStoragePlaid' => esc_js(self::SESSION_STORAGE_PLAID),
          )
        );
      }

      /**
       * Add content to the WC emails.
       *
       * @access public
       * @param WC_Order $order
       * @param bool $sent_to_admin
       * @param bool $plain_text
       */
      public function email_instructions($order, $sent_to_admin, $plain_text = false)
      {
        // if ($this->instructions && !$sent_to_admin && 'precisionpay' == $order->payment_method && $order->has_status('on-hold')) {
        //   echo wpautop(wptexturize($this->instructions)) . PHP_EOL;
        // }
      }

      public function process_refund($order_id, $amount = null, $reason = '')
      {
        // Step 1: Get the order
        $order = wc_get_order($order_id);

        if (! $this->can_refund_order($order)) {
          return new WP_Error('error', __('Refund failed.', 'woocommerce'));
        }

        // if no amount set to full amount
        if ($amount == null) {
          $amount = $order->get_total();
        }

        if (floatval($amount) <= 0) {
          return new WP_Error('error', 'Please specify an amount greater than 0.');
        }

        // Step 2: Check that amount <= the order amount (This appears to be handled by WooCommerce)
        if (floatval($order->get_total()) < floatval($amount)) {
          return new WP_Error('Refund amount is greater than order amount', 'error');
        }

        // Step 3: Make a call to refund payment - PrecisionPay API
        $orderNumber = $this->get_order_number($order, $order_id);
        $refundData = array(
          'order'  => strval($orderNumber),
          'reason' => strval($reason),
          'note'   => '',
          'amount' => floatval($amount),
        );
        $refundResponse = $this->api_post('/checkout/refund', $refundData);

        if (is_wp_error($refundResponse)) {
          static::log('Refund Failed: ' . $refundResponse->get_error_message(), 'error');
          return new WP_Error('error', $refundResponse->get_error_message());
        } else if (empty($refundResponse['body'])) {
          return new WP_Error('error', __('Error processing refund at this time. Please try again later.', 'precisionpay-payments-for-woocommerce'));
        }

        $refundResponse_body = json_decode(wp_remote_retrieve_body($refundResponse));

        if (!$refundResponse_body || !isset($refundResponse_body->message) || !$refundResponse_body->message == 'success') {
          $responseErrorMessage = $refundResponse_body ? $refundResponse_body->detail : '';
          return new WP_Error('error', $responseErrorMessage);
        }

        // Step 4: If success return true
        return true;
      }

      /**
       * Logging method.
       *
       * @param string $message Log message.
       * @param string $level Optional. Default 'info'. Possible values:
       *                      emergency|alert|critical|error|warning|notice|info|debug.
       */
      public static function log($message, $level = 'info')
      {
        if (self::$log_enabled) {
          if (empty(self::$log)) {
            self::$log = wc_get_logger();
          }
          self::$log->log($level, $message, array('source' => 'precisionpay'));
        }
      }
    } // end PrecisionPay_Payments_For_WC class
  endif;
}
