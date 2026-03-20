# Shipping Per Product

A WooCommerce plugin by [Hera Studio LK](https://www.herastudiolk.com) that lets you set a flat custom shipping cost per product — or per group of products — directly from the WordPress admin.

---

## Features

- Automatically creates a **"Hera Shipping"** WooCommerce shipping class on activation
- Set a flat shipping cost for a **single product** or **multiple products** in one rule
- Optional **rule labels** for easy management
- **Edit or delete** any rule at any time
- AJAX-powered admin UI — no full page reloads
- Shipping cache is **busted automatically** when rules change, so costs update at checkout immediately
- All database queries use **prepared statements**
- Compatible with WooCommerce **shipping zones**

---

## Requirements

| Requirement | Minimum |
|---|---|
| WordPress | 5.8 |
| WooCommerce | 6.0 |
| PHP | 7.4 |

---

## Installation

1. Download the latest `.zip` from the [Releases](../../releases) page
2. In WordPress admin go to **Plugins → Add New → Upload Plugin**
3. Upload the zip and click **Activate**

---

## How It Works

```
1. Activate the plugin
   └── "Hera Shipping" class is created automatically in WooCommerce

2. Assign the class to a product
   └── WooCommerce → Products → [Edit product] → Shipping tab
       └── Set Shipping Class to "Hera Shipping"

3. Add a shipping rule
   └── WooCommerce → Shipping Per Product
       └── Select product(s), set cost, save

4. Add the method to a shipping zone
   └── WooCommerce → Settings → Shipping → [Zone] → Add shipping method
       └── Choose "Hera Shipping"

5. Done — cost is charged per-item quantity at checkout
```

---

## Admin Interface

The plugin adds a **Shipping Per Product** submenu under WooCommerce in the WordPress admin.

| Column | Description |
|---|---|
| Label | Optional name for the rule |
| Products | Products this rule applies to |
| Cost | Flat cost charged per item quantity |
| Actions | Edit or delete the rule |

---

## Changelog

### v1.0.3 — Brand Refresh, Auto-Updates & License
- **New** Company logo (Hera Studio LK favicon) displayed in the plugin header
- **New** Redesigned admin UI — black header, white primary text, cyan→purple gradient accent matching the Hera Studio brand
- **New** GitHub auto-updater — WordPress will notify you and let you update with one click whenever a new release is published on GitHub
- **New** Proprietary license (LICENSE.txt) — protects source code from unauthorised redistribution or resale

### v1.0.2 — Checkout & Availability Fixes
- **Fixed** shipping cost not appearing at checkout — WooCommerce shipping rate cache is now busted immediately after any rule is saved or deleted
- **Fixed** shipping method sometimes not appearing in the checkout shipping list — `enabled` state now defaults to `yes` correctly
- **Fixed** method title being overwritten before settings were loaded

### v1.0.1 — Security Hardening & Fatal Error Fix
- **Fixed** fatal error on plugin activation — class files are now loaded before the activation hook runs
- **Added** per-action nonces (`spp_save_rule`, `spp_delete_rule`, `spp_search_products`) replacing the single shared nonce
- **Added** `$wpdb->prepare()` on every database query
- **Added** server-side product ID validation — only real published products are accepted
- **Added** shipping cost clamping (`0` – `99,999.99`) and rounding to 2 decimal places
- **Added** existence check before update/delete operations
- **Added** `wp_unslash()` on all `$_POST` / `$_GET` reads
- **Updated** author to **Hera Studio LK** with link to [herastudiolk.com](https://www.herastudiolk.com)

### v1.0.0 — Initial Release
- Custom "Hera Shipping" WooCommerce shipping class created automatically on activation
- Admin UI to add, edit, and delete per-product shipping rules
- Multi-product rule support
- Select2 AJAX product search
- Cost applied per-item quantity at checkout

---

## License

[GPL-2.0+](https://www.gnu.org/licenses/gpl-2.0.html)

---

*Built with care by [Hera Studio LK](https://www.herastudiolk.com)*
