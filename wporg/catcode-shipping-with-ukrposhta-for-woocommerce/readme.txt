=== CatCode Shipping with Ukrposhta for WooCommerce ===
Contributors: catcodestudio
Tags: woocommerce, shipping, ukrposhta, ukraine, delivery
Requires at least: 5.6
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Ukrposhta (Укрпошта) shipping for WooCommerce — post office picker at checkout and live delivery tariff.

== Description ==

CatCode Shipping with Ukrposhta for WooCommerce integrates the official Ukrposhta (Укрпошта) API into your store:

* **Checkout picker** — customers choose region → city → post office, backed by the official Ukrposhta Address Classifier. The selected post index is stored on the order.
* **Live tariff** — domestic delivery price is fetched from the Ukrposhta eCom API (`/domestic/delivery-price`); a configurable flat rate is used as fallback. Optional free shipping over a threshold.
* **Encrypted credentials** — the Bearer key is stored obfuscated at rest.

To fetch the address classifier and the tariff you need a Ukrposhta eCom **Bearer** (issued by your Ukrposhta manager after signing the eCom contract). Enter it under **WooCommerce → Settings → Shipping → Ukrposhta**.

Shipment (barcode / ТТН) creation, sticker printing and cash-on-delivery ship in a later update, once verified end-to-end against a live contract.

Ukrposhta is a third-party service. This plugin is an independent integration built by CatCode; it is not affiliated with, endorsed by or sponsored by Ukrposhta.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/` and activate it.
2. Go to WooCommerce → Settings → Shipping, add the **Ukrposhta** method to a shipping zone.
3. Enter your eCom Bearer and sender postcode. Save.
4. Enable the method. The office picker appears on the checkout.

== External services ==

This plugin connects to the official Ukrposhta eCom API so the store can show real
post offices and real delivery prices at checkout. Nothing is sent anywhere else.

**Ukrposhta eCom API and Address Classifier** (https://www.ukrposhta.ua, or
https://dev.ukrposhta.ua when the Sandbox option is enabled).

* When: while a customer is choosing a post office at checkout, and whenever the
  delivery price for a cart has to be quoted.
* What is sent for the address lookup: the region, the city name being typed and
  the chosen city id. No customer name, e-mail, phone or order data.
* What is sent for the price quote: the sender post index from the settings, the
  chosen post office index, the total weight and dimensions of the cart, and -
  only when you enable them - the order total as the declared value and the
  cash-on-delivery amount.
* Authentication: the eCom Bearer key you enter in the plugin settings.
* Terms of service: https://www.ukrposhta.ua/ua/informatsiia-pro-poslugu
* Privacy policy: https://www.ukrposhta.ua/ua/polityka-konfidentsiinosti

Using this plugin therefore means the store agrees to Ukrposhta's terms; the key
is issued by Ukrposhta after an eCom contract is signed.

== Frequently Asked Questions ==

= Do I need a Ukrposhta contract? =
Yes. The Address Classifier and tariff both require a Bearer issued by Ukrposhta after signing the eCom contract.

= Does it support the block checkout? =
The office picker targets the classic checkout. Order meta is also captured on the Store API (block) checkout path.

== Changelog ==

= 1.1.1 =
* Source strings are now English with a bundled Ukrainian translation, so the plugin can be translated into any language.
* Checkout widget labels are translatable too - they used to be hard-coded in JavaScript.

= 1.1.0 =
* Fixed: the delivery price no longer sticks to the first quote — changing the post office (or the payment method) now re-rates the cart.
* Fixed: cash-on-delivery commission was added to every order, including prepaid ones. It is now charged only for the payment methods you list as COD.
* Added: order screen box with the chosen city, post office and post index — the meta was stored but invisible to the shop manager.
* Added: the chosen post office is shown on the thank-you page, in the customer account and in order e-mails.
* Added: the checkout refuses to go through with Ukrposhta selected but no post office chosen.
* Added: declared value is now optional — turn it off to quote a cheaper tariff without insurance.
* Added: city search falls back to transliteration, so "Drohobych" finds Дрогобич.
* Fixed: the picker no longer renders on the "order received" page and stays hidden until Ukrposhta is the selected shipping method.
* Fixed: the office cache compared local time against the database clock in UTC.
* Fixed: the tariff request never returned a price - measurements have to be sent inside `parcels`, and a sender index starting with 0 (01001) was cast to a number and rejected. Every quote used to fall back to the flat rate.
* Fixed: switching the payment method now refreshes the delivery price instead of leaving a stale one on screen.
* Added: the picker restores the chosen office after a page reload.

= 1.0.1 =
* Works on hosts without the mbstring extension - city search and parcel description no longer fail silently.
* Lowered requirements to WordPress 5.6 / WooCommerce 6.0 / PHP 7.4.

= 1.0.0 =
* Initial release — checkout post-office picker and live tariff.
