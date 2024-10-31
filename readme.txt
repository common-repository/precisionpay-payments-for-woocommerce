=== PrecisionPay Payments for WooCommerce ===
Contributors: daveprecisionpay
Tags: woocommerce, precisionpay, checkout, payments, ecommerce
Requires at least: 5.9
Tested up to: 6.6
Stable tag: 3.4.0
Requires PHP: 7.2
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Accept online bank payments in your WooCommerce store using PrecisionPay - the firearms friendly payments processor.

== Description ==

PrecisionPay is *the* payment solution for the firearms industry. We are a staunch supporter of the 2nd Amendment and will never cancel you for exercising your constitutional rights. Download and install our plugin and then visit our website to complete your application. Soon after that, you’ll be able to process payments for guns and ammunition without having to pay the exorbitant fees associated with “high risk” e-commerce categories.

This plugin uses Plaid ([https://plaid.com/](https://plaid.com/)) along with the PrecisionPay checkout portal to allow your customers to pay with PrecisionPay as a guest (using Plaid) or as a PrecisionPay user (if they already have an account at [myprecisionpay.com](myprecisionpay.com)). View the PrecisionPay privacy policy [here](https://www.myprecisionpay.com/privacy-policy). View Plaid's privacy policy [here](https://plaid.com/legal/).

= The benefits of using PrecisionPay =

- **It's easy for your customers**: There is a built in, fast, and easy to use guest checkout if the user isn't already using PrecisionPay
- **2nd Amendment Friendly**: PrecisionPay is *the* WooCommerce solution entirely dedicated to supporting the sale of firearms and firearm related products.
- **Private**: We care about privacy as much as you do. We are transparent about what we store and we don't sell personal user data. Ever.
- **Secure**: We use industry standards, and even go beyond industry standards where possible to keep all your payment processing secure.

== Installation ==

= Minimum Requirements =

* WordPress 5.9 or greater
* WooCommerce 3.9 or greater

= Prerequisites =

- Make sure you have [WooCommerce](https://wordpress.org/plugins/woocommerce/ "WooCommerce Plugin") installed.
- You will need a PrecisionPay Merchant account. Don't have an account? Contact us: [support@myprecisionpay.com](mailto:support@myprecisionpay.com)
- You will also need to have a connected bank account in the PrecisionPay Merchant portal. The plugin will not work if your bank account isn't connected. (You can't accept payments if you don't have a place for them to go!)

= There are a few steps you are going to need to follow to use this plugin properly =

1. Log into your PrecisionPay Merchant account.
2. In the top navigation, click on API Keys.
3. Create a new API key. DON'T FORGET TO COPY THE SECRET AND SAVE IT IN A SAFE PLACE as you will not be able to retrieve it from the site later.

If you haven't added your bank account yet, here's how you do it:

1. Log into your PrecisionPay Merchant account.
2. In the top navigation, click on Account Settings.
3. Click the Add Bank Account button.
4. Choose Add Bank Account with Plaid or Manually Add Bank Account.
5. Complete the steps on screen.

Once you've installed and activated the PrecisionPay plugin, do the following in your Wordpress admin panel:

1. Make sure you have WooCommerce installed.
2. Go to the WooCommerce Settings page.
3. Click the Payments tab.
4. Activate the PrecisionPay Payment Gateway.
5. Click Manage.
6. Add your PrecisionPay Merchant API key and secret into the API key and secret fields.
7. Check "Enable Test Mode" if you want to test your ability to make a purchase without spending any money. This puts Plaid in sandbox mode. At checkout, click the PrecisionPay "Authorize Payment" button and use guest checkout to authorize the payment. When Plaid prompts you for the username type: "user_good", and for the password type: "pass_good". Once you are satisfied everything is working, uncheck "Enable Test Mode" and you will be ready to accept payments with PrecisionPay!

== Frequently Asked Questions ==

= Do I need to register with PrecisionPay to use this plugin? =

Yes, you will need to register as a merchant and connect your bank account before you will be able to use this plugin properly. 

= Do my customers need to be registered? =

No, however many of them will already have customer accounts. For those who don't, there is an easy to use guest payment option.

== Screenshots ==

1. This is how the payment gateway looks on your checkout page.
2. This is what the login screen looks like when a user clicks "authorize payment".
3. This is the payment approval page.
4. This is the wordpress admin screen found in woocommerce->settings->payments.

== Changelog ==

= 3.4.0 =
* New Feature: Refunds - Refunds can now be initiated through WooCommerce order page.

== Upgrade Notice ==

= 3.4.0 =
New Feature: Refunds - From the WooCommerce order page, submitting a refund with precisionpay will automatically refund your customer. Select any amount up to the total of the order.

= 3.3.3 =
This version has some minor bugfixes and UI improvements.

= 3.3.2 =
Bugfix for edge case where a user who gets automatically logged in after placing the order would have to hit the place order button a second time.

= 3.3.0 =
This is the first public version of PrecisionPay for WooCommerce and is the minimum requred to start accepting payments with the PrecisionPay network. (v1, v2, v3.0, v3.1, and v3.2 were were still in an MVP stage and were private).