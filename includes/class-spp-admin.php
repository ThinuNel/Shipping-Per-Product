<?php
/**
 * Admin UI — manage shipping rules.
 *
 * Security hardening applied:
 *  - Every AJAX handler verifies a nonce AND checks capabilities before doing anything.
 *  - All inputs are sanitised with the narrowest-possible function.
 *  - Every DB query uses $wpdb->prepare() with typed placeholders.
 *  - Submitted product IDs are validated against the posts table — no phantom IDs saved.
 *  - Shipping cost is capped and normalised to 2 decimal places.
 *  - All HTML output is escaped at the point of echo.
 *
 * @package Shipping_Per_Product
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SPP_Admin {

	/** Maximum allowed shipping cost (prevents runaway values from typos). */
	const MAX_COST = 99999.99;

	public static function init() {
		add_action( 'admin_menu',            [ __CLASS__, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'wp_ajax_spp_save_rule',       [ __CLASS__, 'ajax_save_rule' ] );
		add_action( 'wp_ajax_spp_delete_rule',     [ __CLASS__, 'ajax_delete_rule' ] );
		add_action( 'wp_ajax_spp_search_products', [ __CLASS__, 'ajax_search_products' ] );
	}

	// -----------------------------------------------------------------------
	// Menu
	// -----------------------------------------------------------------------

	public static function add_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Shipping Per Product', 'shipping-per-product' ),
			__( 'Shipping Per Product', 'shipping-per-product' ),
			'manage_woocommerce',
			'shipping-per-product',
			[ __CLASS__, 'render_page' ]
		);
	}

	// -----------------------------------------------------------------------
	// Assets
	// -----------------------------------------------------------------------

	public static function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'shipping-per-product' ) === false ) {
			return;
		}

		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script( 'select2' );

		wp_enqueue_style(
			'spp-admin',
			SPP_PLUGIN_URL . 'assets/css/admin.css',
			[ 'woocommerce_admin_styles' ],
			SPP_VERSION
		);

		wp_enqueue_script(
			'spp-admin',
			SPP_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery', 'select2' ],
			SPP_VERSION,
			true
		);

		// Pass separate nonces per action so a leaked nonce for one action
		// cannot be replayed against a different action.
		wp_localize_script( 'spp-admin', 'sppData', [
			'ajaxUrl' => esc_url( admin_url( 'admin-ajax.php' ) ),
			'nonces'  => [
				'save'   => wp_create_nonce( 'spp_save_rule' ),
				'delete' => wp_create_nonce( 'spp_delete_rule' ),
				'search' => wp_create_nonce( 'spp_search_products' ),
			],
			'i18n'    => [
				'confirmDelete' => __( 'Are you sure you want to remove this rule?', 'shipping-per-product' ),
				'saving'        => __( 'Saving…', 'shipping-per-product' ),
				'save'          => __( 'Save Rule', 'shipping-per-product' ),
				'networkError'  => __( 'Network error. Please try again.', 'shipping-per-product' ),
				'selectProduct' => __( 'Please select at least one product.', 'shipping-per-product' ),
				'invalidCost'   => __( 'Please enter a valid shipping cost (0 or more).', 'shipping-per-product' ),
			],
		] );
	}

	// -----------------------------------------------------------------------
	// Page render
	// -----------------------------------------------------------------------

	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'shipping-per-product' ) );
		}

		$rules = self::get_rules();
		?>
		<div class="spp-wrap">
			<div class="spp-header">
				<div class="spp-header-inner">
					<div class="spp-logo">
						<svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
							<rect width="32" height="32" rx="8" fill="#1a1a2e"/>
							<path d="M8 10h16M8 16h10M8 22h13" stroke="#e8b86d" stroke-width="2.5" stroke-linecap="round"/>
							<circle cx="24" cy="22" r="3.5" stroke="#e8b86d" stroke-width="2"/>
						</svg>
						<span><?php esc_html_e( 'Shipping Per Product', 'shipping-per-product' ); ?></span>
					</div>
					<a href="https://www.herastudiolk.com" target="_blank" rel="noopener noreferrer" class="spp-badge">
						Hera Studio LK
					</a>
				</div>
			</div>

			<div class="spp-body">
				<div class="spp-card spp-card--form">
					<div class="spp-card-header">
						<h2><?php esc_html_e( 'Add Shipping Rule', 'shipping-per-product' ); ?></h2>
						<p class="spp-card-desc">
							<?php esc_html_e( 'Select one or more products and set a flat custom shipping cost. Products must be assigned the "Hera Shipping" class for the cost to apply at checkout.', 'shipping-per-product' ); ?>
						</p>
					</div>

					<form id="spp-rule-form" class="spp-form" autocomplete="off">
						<input type="hidden" id="spp-rule-id" name="rule_id" value="">

						<div class="spp-field">
							<label for="spp-products"><?php esc_html_e( 'Products', 'shipping-per-product' ); ?></label>
							<select id="spp-products" name="product_ids[]" multiple="multiple" class="spp-select2" style="width:100%"></select>
							<span class="spp-hint">
								<?php esc_html_e( 'Search and select one or more products. Products must have the "Hera Shipping" class assigned.', 'shipping-per-product' ); ?>
							</span>
						</div>

						<div class="spp-field">
							<label for="spp-label"><?php esc_html_e( 'Rule Label (optional)', 'shipping-per-product' ); ?></label>
							<input type="text" id="spp-label" name="label" maxlength="255"
								placeholder="<?php esc_attr_e( 'e.g. Heavy Furniture', 'shipping-per-product' ); ?>">
						</div>

						<div class="spp-field spp-field--cost">
							<label for="spp-cost"><?php esc_html_e( 'Shipping Cost', 'shipping-per-product' ); ?></label>
							<div class="spp-cost-input">
								<span class="spp-currency"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
								<input type="number" id="spp-cost" name="cost"
									min="0" max="<?php echo esc_attr( self::MAX_COST ); ?>" step="0.01"
									placeholder="0.00">
							</div>
							<span class="spp-hint"><?php esc_html_e( 'Flat cost charged per item at checkout.', 'shipping-per-product' ); ?></span>
						</div>

						<div class="spp-actions">
							<button type="submit" id="spp-submit" class="spp-btn spp-btn--primary">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
								<?php esc_html_e( 'Save Rule', 'shipping-per-product' ); ?>
							</button>
							<button type="button" id="spp-cancel-edit" class="spp-btn spp-btn--ghost" style="display:none">
								<?php esc_html_e( 'Cancel', 'shipping-per-product' ); ?>
							</button>
						</div>

						<div id="spp-msg" class="spp-msg" style="display:none" role="alert" aria-live="polite"></div>
					</form>
				</div>

				<div class="spp-card spp-card--table">
					<div class="spp-card-header">
						<h2><?php esc_html_e( 'Shipping Rules', 'shipping-per-product' ); ?></h2>
						<span class="spp-count">
							<?php printf(
								esc_html( _n( '%d rule', '%d rules', count( $rules ), 'shipping-per-product' ) ),
								count( $rules )
							); ?>
						</span>
					</div>

					<?php if ( empty( $rules ) ) : ?>
						<div class="spp-empty">
							<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
							<p><?php esc_html_e( 'No shipping rules yet. Add your first rule above.', 'shipping-per-product' ); ?></p>
						</div>
					<?php else : ?>
						<div class="spp-table-wrap">
							<table class="spp-table" id="spp-rules-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Label', 'shipping-per-product' ); ?></th>
										<th><?php esc_html_e( 'Products', 'shipping-per-product' ); ?></th>
										<th><?php esc_html_e( 'Cost', 'shipping-per-product' ); ?></th>
										<th><?php esc_html_e( 'Actions', 'shipping-per-product' ); ?></th>
									</tr>
								</thead>
								<tbody>
								<?php foreach ( $rules as $rule ) :
									$ids   = self::decode_product_ids( $rule->product_ids );
									$names = self::get_product_names( $ids );
								?>
									<tr data-rule-id="<?php echo esc_attr( $rule->id ); ?>"
										data-product-ids="<?php echo esc_attr( wp_json_encode( $ids ) ); ?>"
										data-cost="<?php echo esc_attr( $rule->cost ); ?>"
										data-label="<?php echo esc_attr( $rule->label ); ?>">

										<td class="spp-td-label">
											<?php if ( $rule->label ) : ?>
												<span class="spp-rule-label"><?php echo esc_html( $rule->label ); ?></span>
											<?php else : ?>
												<span class="spp-rule-label spp-rule-label--empty">&mdash;</span>
											<?php endif; ?>
										</td>

										<td class="spp-td-products">
											<div class="spp-product-tags">
												<?php if ( ! empty( $names ) ) :
													foreach ( $names as $name ) : ?>
														<span class="spp-tag"><?php echo esc_html( $name ); ?></span>
												<?php endforeach;
												else : ?>
													<span class="spp-tag spp-tag--missing">
														<?php esc_html_e( 'Product not found', 'shipping-per-product' ); ?>
													</span>
												<?php endif; ?>
											</div>
										</td>

										<td class="spp-td-cost">
											<strong>
												<?php echo esc_html( get_woocommerce_currency_symbol() . number_format( (float) $rule->cost, 2 ) ); ?>
											</strong>
										</td>

										<td class="spp-td-actions">
											<button class="spp-btn spp-btn--edit spp-edit-rule" type="button"
													title="<?php esc_attr_e( 'Edit rule', 'shipping-per-product' ); ?>">
												<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
												<?php esc_html_e( 'Edit', 'shipping-per-product' ); ?>
											</button>
											<button class="spp-btn spp-btn--danger spp-delete-rule" type="button"
													title="<?php esc_attr_e( 'Delete rule', 'shipping-per-product' ); ?>">
												<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
												<?php esc_html_e( 'Delete', 'shipping-per-product' ); ?>
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</div>

				<div class="spp-card spp-card--info">
					<div class="spp-info-icon">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
					</div>
					<div>
						<strong><?php esc_html_e( 'How it works', 'shipping-per-product' ); ?></strong>
						<p>
							<?php esc_html_e( 'Assign the "Hera Shipping" class to any product (WooCommerce → Products → Shipping tab). Then create a rule here with the flat cost. Finally, add the "Hera Shipping" method to the relevant shipping zone. The cost is charged per-item quantity at checkout for every matched product.', 'shipping-per-product' ); ?>
						</p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	// -----------------------------------------------------------------------
	// AJAX — Save Rule
	// -----------------------------------------------------------------------

	public static function ajax_save_rule() {
		// 1. Nonce verification (action-specific).
		check_ajax_referer( 'spp_save_rule', 'nonce' );

		// 2. Capability check.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to perform this action.', 'shipping-per-product' ) ], 403 );
		}

		// 3. Sanitise product IDs — cast to positive integers, remove zeros.
		$raw_ids     = isset( $_POST['product_ids'] ) ? (array) $_POST['product_ids'] : [];
		$product_ids = array_values( array_filter( array_map( 'absint', $raw_ids ) ) );

		if ( empty( $product_ids ) ) {
			wp_send_json_error( [ 'message' => __( 'Please select at least one product.', 'shipping-per-product' ) ], 422 );
		}

		// 4. Validate IDs against the DB — reject phantom / non-product IDs.
		$product_ids = self::filter_valid_product_ids( $product_ids );
		if ( empty( $product_ids ) ) {
			wp_send_json_error( [ 'message' => __( 'None of the selected products are valid published products.', 'shipping-per-product' ) ], 422 );
		}

		// 5. Sanitise cost — clamp 0…MAX_COST, round to 2 decimal places.
		$raw_cost = isset( $_POST['cost'] ) ? sanitize_text_field( wp_unslash( $_POST['cost'] ) ) : '0';
		$cost     = round( min( max( (float) $raw_cost, 0.0 ), self::MAX_COST ), 2 );

		// 6. Sanitise label — plain text, hard limit to column length.
		$label = isset( $_POST['label'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['label'] ) ), 0, 255 ) : '';

		// 7. Sanitise rule ID (0 = new insert).
		$rule_id = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;

		// 8. Write to DB.
		global $wpdb;
		$table        = $wpdb->prefix . 'spp_rules';
		$product_json = wp_json_encode( $product_ids );

		if ( $rule_id ) {
			// Confirm the row exists before updating.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d LIMIT 1", $rule_id ) );
			if ( ! $exists ) {
				wp_send_json_error( [ 'message' => __( 'Rule not found.', 'shipping-per-product' ) ], 404 );
			}
			$wpdb->update(
				$table,
				[ 'product_ids' => $product_json, 'cost' => $cost, 'label' => $label ],
				[ 'id' => $rule_id ],
				[ '%s', '%f', '%s' ],
				[ '%d' ]
			);
		} else {
			$wpdb->insert(
				$table,
				[ 'product_ids' => $product_json, 'cost' => $cost, 'label' => $label ],
				[ '%s', '%f', '%s' ]
			);
			$rule_id = (int) $wpdb->insert_id;
		}

		// Bust the WooCommerce shipping rate cache so the new/updated rule is
		// reflected at checkout immediately, without waiting for session expiry.
		self::flush_shipping_cache();

		wp_send_json_success( [
			'rule_id'       => $rule_id,
			'product_ids'   => $product_ids,
			'product_names' => self::get_product_names( $product_ids ),
			'cost'          => $cost,
			'label'         => $label,
			'currency'      => get_woocommerce_currency_symbol(),
			'message'       => __( 'Rule saved successfully.', 'shipping-per-product' ),
		] );
	}

	// -----------------------------------------------------------------------
	// AJAX — Delete Rule
	// -----------------------------------------------------------------------

	public static function ajax_delete_rule() {
		check_ajax_referer( 'spp_delete_rule', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to perform this action.', 'shipping-per-product' ) ], 403 );
		}

		$rule_id = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;
		if ( ! $rule_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid rule ID.', 'shipping-per-product' ) ], 422 );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'spp_rules';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d LIMIT 1", $rule_id ) );
		if ( ! $exists ) {
			wp_send_json_error( [ 'message' => __( 'Rule not found.', 'shipping-per-product' ) ], 404 );
		}

		$wpdb->delete( $table, [ 'id' => $rule_id ], [ '%d' ] );

		// Bust the cache so deleted rules stop appearing at checkout immediately.
		self::flush_shipping_cache();

		wp_send_json_success( [ 'message' => __( 'Rule deleted.', 'shipping-per-product' ) ] );
	}

	// -----------------------------------------------------------------------
	// AJAX — Product Search (Select2 source)
	// -----------------------------------------------------------------------

	public static function ajax_search_products() {
		check_ajax_referer( 'spp_search_products', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [], 403 );
		}

		$term  = isset( $_GET['q'] )    ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		$paged = isset( $_GET['page'] ) ? max( 1, absint( $_GET['page'] ) )               : 1;

		$query = new WP_Query( [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'paged'          => $paged,
			's'              => $term,
			'fields'         => 'ids',
			'no_found_rows'  => false,
		] );

		$results = [];
		foreach ( $query->posts as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$results[] = [
					'id'   => (int) $product_id,
					'text' => $product->get_name() . ' (#' . (int) $product_id . ')',
				];
			}
		}

		wp_send_json( [
			'results'    => $results,
			'pagination' => [ 'more' => $paged < (int) $query->max_num_pages ],
		] );
	}

	// -----------------------------------------------------------------------
	// Private helpers
	// -----------------------------------------------------------------------

	/** Fetch all rules, newest first, capped at 500. */
	private static function get_rules() {
		global $wpdb;
		$table = $wpdb->prefix . 'spp_rules';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", 500 ) );
	}

	/**
	 * Decode a JSON product-IDs blob to an array of positive ints.
	 *
	 * @param  string $json
	 * @return int[]
	 */
	private static function decode_product_ids( $json ) {
		$decoded = json_decode( $json, true );
		if ( ! is_array( $decoded ) ) {
			return [];
		}
		return array_values( array_filter( array_map( 'absint', $decoded ) ) );
	}

	/**
	 * Return display names for an array of product IDs.
	 *
	 * @param  int[] $ids
	 * @return string[]
	 */
	private static function get_product_names( array $ids ) {
		$names = [];
		foreach ( $ids as $id ) {
			$p = wc_get_product( $id );
			if ( $p ) {
				$names[] = $p->get_name();
			}
		}
		return $names;
	}

	/**
	 * Keep only IDs that correspond to a published 'product' post.
	 *
	 * Uses a single prepared query rather than N individual lookups.
	 *
	 * @param  int[] $ids
	 * @return int[]
	 */
	private static function filter_valid_product_ids( array $ids ) {
		if ( empty( $ids ) ) {
			return [];
		}

		global $wpdb;
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$sql = $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts}
			 WHERE ID IN ( {$placeholders} )
			   AND post_type   = 'product'
			   AND post_status = 'publish'",
			...$ids
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$valid = $wpdb->get_col( $sql );

		return array_values( array_map( 'absint', $valid ) );
	}

	/**
	 * Bust the WooCommerce shipping rate cache.
	 *
	 * WooCommerce stores computed shipping rates in transients keyed by a
	 * "shipping transient version". Incrementing that version immediately
	 * invalidates every cached rate site-wide, so the next cart/checkout
	 * request forces a fresh calculation using the updated rules.
	 */
	private static function flush_shipping_cache() {
		// Canonical WC API — increments the shipping transient version,
		// busting all cached shipping rates for every customer session.
		WC_Cache_Helper::get_transient_version( 'shipping', true );

		// Also clear shipping data stored in the current session so that
		// any admin who is also browsing the shop sees the change immediately.
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( 'shipping_for_package', null );
		}
	}
}
