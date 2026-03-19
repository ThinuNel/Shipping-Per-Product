<?php
/**
 * Matches cart items to SPP rules and calculates the shipping cost.
 *
 * @package Shipping_Per_Product
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SPP_Calculator {

	public static function init() {
		// Nothing to hook at boot; called directly by the shipping method.
	}

	/**
	 * Return the total custom shipping cost for a package.
	 *
	 * Loops over cart items that have the "hera-shipping" shipping class and
	 * sums the matching rule costs.
	 *
	 * @param  array $package WooCommerce package array.
	 * @return float|false  Total cost, or false if no rules matched.
	 */
	public static function get_shipping_cost_for_package( $package ) {
		$rules = self::get_all_rules();
		if ( empty( $rules ) ) {
			return false;
		}

		$hera_class_slug = SPP_Shipping_Class::SLUG;
		$total_cost      = 0;
		$matched         = false;

		foreach ( $package['contents'] as $item ) {
			$product_id = $item['product_id'];
			$product    = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			// Only process items that use the Hera Shipping class.
			$shipping_class = $product->get_shipping_class();
			if ( $shipping_class !== $hera_class_slug ) {
				continue;
			}

			$qty  = $item['quantity'];
			$cost = self::find_cost_for_product( $product_id, $rules );

			if ( false !== $cost ) {
				$total_cost += (float) $cost * $qty;
				$matched     = true;
			}
		}

		return $matched ? $total_cost : false;
	}

	/**
	 * Find the cost for a specific product from the rules list.
	 *
	 * @param  int   $product_id
	 * @param  array $rules
	 * @return float|false
	 */
	public static function find_cost_for_product( $product_id, $rules ) {
		foreach ( $rules as $rule ) {
			$ids = array_map( 'intval', (array) json_decode( $rule->product_ids, true ) );
			if ( in_array( (int) $product_id, $ids, true ) ) {
				return (float) $rule->cost;
			}
		}
		return false;
	}

	/**
	 * Fetch all rules from the database.
	 *
	 * @return array
	 */
	public static function get_all_rules() {
		global $wpdb;
		$table = $wpdb->prefix . 'spp_rules';
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC" ); // phpcs:ignore
	}
}
