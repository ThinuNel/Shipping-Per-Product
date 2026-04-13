=== Shipping Per Product ===
Contributors: herastudiolk
Tags: woocommerce, shipping, custom shipping, per-product shipping
Requires at least: 5.8
Tested up to: 6.5
Stable tag: 1.0.3
Requires PHP: 7.4
WC requires at least: 6.0
License: Proprietary
License URI: https://www.herastudiolk.com

Easily set a custom shipping cost per product (or group of products) in WooCommerce using the built-in "Hera Shipping" class.

== Description ==

**Shipping Per Product** by Hera Studio LK lets store managers assign precise shipping costs to individual products or product groups — no per-zone rate math needed.

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
* GitHub auto-updater — update with one click from the WordPress Plugins page
* Branded admin interface with Hera Studio LK identity
* Fully compatible with WooCommerce shipping zones

== Installation ==

1. Upload the `shipping-per-product` folder to `/wp-content/plugins/`
2. Activate via **Plugins** in WordPress admin
3. Go to **WooCommerce → Shipping Per Product** to configure rules
4. Add the **Hera Shipping** method to your WooCommerce shipping zone

== Changelog ==

= 1.0.3 =
* New: Company logo displayed in the plugin admin header
* New: Redesigned admin UI — black header, white text, cyan-to-purple gradient accents matching Hera Studio LK brand
* New: GitHub auto-updater — WordPress shows a standard update notice and installs new releases with one click
* New: Proprietary LICENSE.txt added to protect source code

= 1.0.2 =
* Fixed: Shipping cost not appearing at checkout — WooCommerce shipping rate cache is now busted after every rule save/delete
* Fixed: Shipping method sometimes invisible at checkout — enabled state now defaults to yes correctly
* Fixed: Method title overwritten before settings loaded

= 1.0.1 =
* Fixed: Fatal error on plugin activation — class files now loaded before activation hook runs
* Added: Per-action nonces replacing the single shared nonce
* Added: $wpdb->prepare() on every database query
* Added: Server-side product ID validation before saving
* Added: Shipping cost clamping and rounding
* Added: Row existence check before update/delete
* Updated: Author name to Hera Studio LK

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.3 =
Brand refresh with Hera Studio LK identity and GitHub auto-updater added.
