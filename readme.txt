=== Ukrposhta Shipping for WooCommerce ===
Contributors: catcodestudio
Tags: woocommerce, shipping, ukrposhta, ukraine, delivery
Requires at least: 5.6
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Ukrposhta (Укрпошта) shipping for WooCommerce — post office picker at checkout and live delivery tariff.

== Description ==

Ukrposhta Shipping for WooCommerce integrates the official Ukrposhta (Укрпошта) API into your store:

* **Checkout picker** — customers choose region → city → post office, backed by the official Ukrposhta Address Classifier. The selected post index is stored on the order.
* **Live tariff** — domestic delivery price is fetched from the Ukrposhta eCom API (`/domestic/delivery-price`); a configurable flat rate is used as fallback. Optional free shipping over a threshold.
* **Encrypted credentials** — the Bearer key is stored obfuscated at rest.

To fetch the address classifier and the tariff you need a Ukrposhta eCom **Bearer** (issued by your Ukrposhta manager after signing the eCom contract). Enter it under **WooCommerce → Settings → Shipping → Ukrposhta**.

Shipment (barcode / ТТН) creation, sticker printing and cash-on-delivery ship in a later update, once verified end-to-end against a live contract.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/` and activate it.
2. Go to WooCommerce → Settings → Shipping, add the **Ukrposhta** method to a shipping zone.
3. Enter your eCom Bearer and sender postcode. Save.
4. Enable the method. The office picker appears on the checkout.

== Frequently Asked Questions ==

= Do I need a Ukrposhta contract? =
Yes. The Address Classifier and tariff both require a Bearer issued by Ukrposhta after signing the eCom contract.

= Does it support the block checkout? =
The office picker targets the classic checkout. Order meta is also captured on the Store API (block) checkout path.

== Changelog ==

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

= 1.0.1 =
* Works on hosts without the mbstring extension - city search and parcel description no longer fail silently.
* Lowered requirements to WordPress 5.6 / WooCommerce 6.0 / PHP 7.4.

= 1.0.0 =
* Initial release — checkout post-office picker and live tariff.
