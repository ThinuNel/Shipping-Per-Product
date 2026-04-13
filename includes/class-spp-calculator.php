<?php
/**
 * Unified shipping calculator.
 *
 * Logic:
 *  1. Walk every cart item.
 *  2. If the item's product has the "Hera Shipping" class AND a per-product
 *     rule exists → apply the fixed custom cost × qty for that item.
 *  3. All remaining items (any class, no matching rule) → sum their weights.
 *  4. Look up the total remaining weight against the weight-tier table.
 *  5. Return: sum of custom costs + weight-tier cost.
 *
 * This means weight-based shipping covers everything by default, and the
 * per-product price is a per-item override — both handled by a single
 * WooCommerce shipping method, so there are no zone conflicts.
 *
 * @package Shipping_Per_Product
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SPP_Calculator {

	public static function init() {
		// Intentionally empty — called directly from the shipping method.
	}

	// -----------------------------------------------------------------------
	// Main entry point
	// -----------------------------------------------------------------------

	/**
	 * Calculate the total shipping cost for a WooCommerce package.
	 *
	 * @param  array $package WooCommerce package array (contains 'contents').
	 * @return float|false  Total cost, or false if nothing could be calculated.
	 */
	public static function get_shipping_cost_for_package( $package ) {
		$per_product_rules = self::get_all_per_product_rules();
		$weight_rules      = self::get_weight_rules();
		$hera_slug         = SPP_Shipping_Class::SLUG;

		$custom_cost   = 0.0;   // Sum of fixed costs for overridden items.
		$weight_total  = 0.0;   // Total weight of non-overridden items.
		$any_item      = false; // Did we process at least one item?

		foreach ( $package['contents'] as $item ) {
			$product_id = (int) $item['product_id'];
			$qty        = (int) $item['quantity'];
			$product    = wc_get_product( $product_id );

			if ( ! $product || $qty <= 0 ) {
				continue;
			}

			$any_item = true;

			// Check for a per-product price override.
			$has_hera_class = ( $product->get_shipping_class() === $hera_slug );
			$rule_cost      = false;

			if ( $has_hera_class && ! empty( $per_product_rules ) ) {
				$rule_cost = self::find_cost_for_product( $product_id, $per_product_rules );
			}

			if ( false !== $rule_cost ) {
				// ── Per-product override ───────────────────────────────────
				$custom_cost += (float) $rule_cost * $qty;
			} else {
				// ── Weight-based fallback ──────────────────────────────────
				// Product weight in WooCommerce's configured weight unit.
				$item_weight = (float) $product->get_weight();
				$weight_total += $item_weight * $qty;
			}
		}

		if ( ! $any_item ) {
			return false;
		}

		// Calculate weight-based cost only if there are non-overridden items
		// that actually have weight data, or if there are no weight rules (fall
		// through to 0 so the method still appears at checkout).
		$weight_cost = 0.0;
		if ( $weight_total > 0 && ! empty( $weight_rules ) ) {
			$weight_cost = self::find_cost_for_weight( $weight_total, $weight_rules );
			// find_cost_for_weight returns false when no tier matches (e.g. weight
			// exceeds all defined ranges). Treat as 0 so the method still appears.
			if ( false === $weight_cost ) {
				$weight_cost = 0.0;
			}
		}

		return $custom_cost + $weight_cost;
	}

	// -----------------------------------------------------------------------
	// Per-product helpers
	// -----------------------------------------------------------------------

	/**
	 * Find the first per-product rule that covers $product_id.
	 *
	 * @param  int   $product_id
	 * @param  array $rules  Rows from spp_rules.
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
	 * Fetch all per-product rules ordered newest-first.
	 *
	 * @return array
	 */
	public static function get_all_per_product_rules() {
		global $wpdb;
		$table = $wpdb->prefix . 'spp_rules';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", 500 ) );
	}

	// -----------------------------------------------------------------------
	// Weight-based helpers
	// -----------------------------------------------------------------------

	/**
	 * Find the shipping cost for a given total weight.
	 * Rules are matched in ascending min_weight order; the first range that
	 * contains $weight wins.
	 *
	 * max_weight = 0 is treated as "no upper limit" (catch-all tier).
	 *
	 * @param  float $weight  Total cart weight.
	 * @param  array $rules   Rows from spp_weight_rules.
	 * @return float|false
	 */
	public static function find_cost_for_weight( $weight, $rules ) {
		// Sort ascending by min_weight so tiers are evaluated in order.
		usort( $rules, static function( $a, $b ) {
			return (float) $a->min_weight <=> (float) $b->min_weight;
		} );

		foreach ( $rules as $rule ) {
			$min = (float) $rule->min_weight;
			$max = (float) $rule->max_weight;

			$above_min = ( $weight >= $min );
			$below_max = ( $max <= 0 ) || ( $weight <= $max ); // 0 = unlimited

			if ( $above_min && $below_max ) {
				return (float) $rule->cost;
			}
		}

		return false; // No matching tier found.
	}

	/**
	 * Fetch all weight rules ordered by sort_order ascending.
	 *
	 * @return array
	 */
	public static function get_weight_rules() {
		global $wpdb;
		$table = $wpdb->prefix . 'spp_weight_rules';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY sort_order ASC, id ASC LIMIT %d", 500 ) );
	}
}
