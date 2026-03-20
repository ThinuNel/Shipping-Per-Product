<?php
/**
 * Plugin Name: Shipping Per Product
 * Plugin URI:  https://www.herastudiolk.com
 * Description: Easily add custom shipping costs per product in WooCommerce using the "Hera Shipping" class.
 * Version:     1.0.3
 * Author:      Hera Studio LK
 * Author URI:  https://www.herastudiolk.com
 * License:     Proprietary
 * Text Domain: shipping-per-product
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 */

// Prevent direct file access — must be the very first executable line.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SPP_VERSION',     '1.0.3' );
define( 'SPP_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'SPP_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'SPP_PLUGIN_FILE', __FILE__ );

// -------------------------------------------------------------------------
// Helpers
// -------------------------------------------------------------------------

/**
 * Load all class files. Safe to call multiple times (require_once).
 */
function spp_load_classes() {
	require_once SPP_PLUGIN_DIR . 'includes/class-spp-shipping-class.php';
	require_once SPP_PLUGIN_DIR . 'includes/class-spp-shipping-method.php';
	require_once SPP_PLUGIN_DIR . 'includes/class-spp-admin.php';
	require_once SPP_PLUGIN_DIR . 'includes/class-spp-calculator.php';
	require_once SPP_PLUGIN_DIR . 'includes/class-spp-updater.php';
}

/**
 * Return true when WooCommerce is active (single-site and multisite aware).
 */
function spp_is_woocommerce_active() {
	$active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins', [] ) );
	if ( in_array( 'woocommerce/woocommerce.php', $active_plugins, true ) ) {
		return true;
	}
	if ( is_multisite() ) {
		$network_plugins = get_site_option( 'active_sitewide_plugins', [] );
		return array_key_exists( 'woocommerce/woocommerce.php', $network_plugins );
	}
	return false;
}

// -------------------------------------------------------------------------
// Boot
// -------------------------------------------------------------------------

/**
 * Main boot function — runs on plugins_loaded so WooCommerce is available.
 */
function spp_init() {
	if ( ! spp_is_woocommerce_active() ) {
		add_action( 'admin_notices', 'spp_woocommerce_missing_notice' );
		return;
	}

	spp_load_classes();

	SPP_Shipping_Class::init();
	SPP_Admin::init();
	SPP_Updater::init();

	// Register custom shipping method with WooCommerce.
	add_filter( 'woocommerce_shipping_methods', 'spp_register_shipping_method' );
}
add_action( 'plugins_loaded', 'spp_init' );

/** Admin notice displayed when WooCommerce is missing. */
function spp_woocommerce_missing_notice() {
	echo '<div class="notice notice-error"><p>'
		. '<strong>Shipping Per Product</strong> requires WooCommerce to be installed and active.'
		. '</p></div>';
}

/** Register SPP_Shipping_Method with WooCommerce. */
function spp_register_shipping_method( $methods ) {
	$methods['spp_hera_shipping'] = 'SPP_Shipping_Method';
	return $methods;
}

// -------------------------------------------------------------------------
// Activation
// -------------------------------------------------------------------------

/**
 * Plugin activation:
 *  1. Create the custom DB table with dbDelta (idempotent).
 *  2. Ensure the "Hera Shipping" WooCommerce shipping class exists.
 *
 * IMPORTANT: activation hooks fire before plugins_loaded, so we must
 * manually require_once every class we intend to use here.
 */
function spp_activate() {
	// Guard: WooCommerce must be present.
	if ( ! spp_is_woocommerce_active() ) {
		deactivate_plugins( plugin_basename( SPP_PLUGIN_FILE ) );
		wp_die(
			'<p><strong>Shipping Per Product</strong> requires WooCommerce. Please install and activate WooCommerce first.</p>',
			'Plugin Activation Error',
			[ 'back_link' => true ]
		);
	}

	// Load classes manually — plugins_loaded has NOT fired yet at activation time.
	spp_load_classes();

	// 1. Create DB table.
	global $wpdb;
	$table   = $wpdb->prefix . 'spp_rules';
	$charset = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS {$table} (
		id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		product_ids LONGTEXT            NOT NULL,
		cost        DECIMAL(10,2)       NOT NULL DEFAULT 0.00,
		label       VARCHAR(255)        NOT NULL DEFAULT '',
		created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id)
	) {$charset};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	// 2. Create the "Hera Shipping" shipping class (term) if absent.
	// The product_shipping_class taxonomy is registered by WooCommerce at
	// init priority 1, which has already fired by the time a user clicks
	// "Activate" in the admin.
	SPP_Shipping_Class::maybe_create();

	// Store the installed version for future upgrade routines.
	update_option( 'spp_version', SPP_VERSION );
}
register_activation_hook( __FILE__, 'spp_activate' );

// -------------------------------------------------------------------------
// Deactivation
// -------------------------------------------------------------------------

/**
 * Deactivation — intentionally light.
 * Rules and the DB table are preserved so re-activation is non-destructive.
 */
function spp_deactivate() {
	delete_option( 'spp_version' );
}
register_deactivation_hook( __FILE__, 'spp_deactivate' );
