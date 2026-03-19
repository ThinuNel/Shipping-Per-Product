=== Shipping Per Product ===
Contributors: herastudiolk
Tags: woocommerce, shipping, custom shipping, per-product shipping
Requires at least: 5.8
Tested up to: 6.5
Stable tag: 1.0.1
Requires PHP: 7.4
WC requires at least: 6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Easily set a custom shipping cost per product (or group of products) in WooCommerce using the built-in "Hera Shipping" class.

== Description ==

**Shipping Per Product** by Hera Studio lets store managers assign precise shipping costs to individual products or product groups — no per-zone rate math needed.

**How it works**

1. The plugin automatically creates a **"Hera Shipping"** shipping class in WooCommerce.
2. In WooCommerce → Products, assign the *Hera Shipping* class to any product (Shipping tab).
3. Go to **WooCommerce → Shipping Per Product** and create a rule: pick one or more products and enter the flat shipping cost.
4. Add the *Hera Shipping* method to the applicable shipping zone.
5. At checkout, the custom cost is applied per-item quantity for matched products.

**Features**

* Automatic creation of the "Hera Shipping" shipping class
* Custom shipping cost rules per product or per group of products
* Multi-product rules (share one cost across several products)
* Rule labels for easy management
* Edit or delete rules at any time from the backend
* AJAX-powered admin UI — no page reloads
* Fully compatible with WooCommerce shipping zones

== Installation ==

1. Upload the `shipping-per-product` folder to `/wp-content/plugins/`
2. Activate via **Plugins** in WordPress admin
3. Go to **WooCommerce → Shipping Per Product** to configure rules
4. Add the **Hera Shipping** method to your WooCommerce shipping zone

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
First public release.
