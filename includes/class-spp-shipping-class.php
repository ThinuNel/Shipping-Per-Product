<?php
/**
 * Creates and manages the "Hera Shipping" WooCommerce shipping class.
 *
 * @package Shipping_Per_Product
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SPP_Shipping_Class {

	const SLUG = 'hera-shipping';
	const NAME = 'Hera Shipping';

	public static function init() {
		// On every load, ensure the class exists (e.g. after import/reset).
		add_action( 'woocommerce_init', [ __CLASS__, 'maybe_create' ] );
	}

	/**
	 * Create the "Hera Shipping" shipping class if it does not already exist.
	 */
	public static function maybe_create() {
		$term = get_term_by( 'slug', self::SLUG, 'product_shipping_class' );
		if ( $term ) {
			return $term->term_id;
		}

		$result = wp_insert_term(
			self::NAME,
			'product_shipping_class',
			[
				'slug'        => self::SLUG,
				'description' => 'Custom per-product shipping cost managed by Shipping Per Product plugin.',
			]
		);

		if ( is_wp_error( $result ) ) {
			return false;
		}

		return $result['term_id'];
	}

	/**
	 * Return the term ID of the Hera Shipping class.
	 */
	public static function get_term_id() {
		$term = get_term_by( 'slug', self::SLUG, 'product_shipping_class' );
		return $term ? (int) $term->term_id : 0;
	}
}
