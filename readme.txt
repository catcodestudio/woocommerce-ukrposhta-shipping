=== Ukrposhta Shipping for WooCommerce ===
Contributors: catcodestudio
Tags: woocommerce, shipping, ukrposhta, ukraine, delivery
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
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

= 1.0.0 =
* Initial release — checkout post-office picker and live tariff.
