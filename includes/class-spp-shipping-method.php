<?php
/**
 * Registers "Hera Shipping" as a selectable shipping method in WooCommerce zones.
 *
 * @package Shipping_Per_Product
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SPP_Shipping_Method extends WC_Shipping_Method {

	public function __construct( $instance_id = 0 ) {
		$this->id                 = 'spp_hera_shipping';
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( 'Hera Shipping', 'shipping-per-product' );
		$this->method_description = __( 'Charges a custom shipping cost per product set in the Shipping Per Product plugin.', 'shipping-per-product' );
		$this->supports           = [ 'shipping-zones', 'instance-settings' ];

		$this->init();

		// Read persisted settings AFTER init_settings() has been called inside init().
		// Defaulting enabled to 'yes' ensures the method is available as soon as it
		// is added to a shipping zone, before any settings have been saved.
		$this->enabled = $this->get_option( 'enabled', 'yes' );
		$this->title   = $this->get_option( 'title', __( 'Hera Shipping', 'shipping-per-product' ) );
	}

	public function init() {
		$this->init_form_fields();
		$this->init_settings();
		add_action( 'woocommerce_update_options_shipping_' . $this->id, [ $this, 'process_admin_options' ] );
	}

	public function init_form_fields() {
		$this->instance_form_fields = [
			'title' => [
				'title'   => __( 'Method Title', 'shipping-per-product' ),
				'type'    => 'text',
				'default' => __( 'Hera Shipping', 'shipping-per-product' ),
			],
		];
	}

	/**
	 * Calculate shipping cost for the cart.
	 */
	public function calculate_shipping( $package = [] ) {
		$cost = SPP_Calculator::get_shipping_cost_for_package( $package );

		if ( false === $cost ) {
			return;
		}

		$this->add_rate( [
			'id'    => $this->get_rate_id(),
			'label' => $this->title,
			'cost'  => $cost,
		] );
	}
}
