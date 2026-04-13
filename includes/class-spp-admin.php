<?php
/**
 * Admin UI — unified shipping rules manager.
 *
 * Tab 1: Per-Product Rules  — fixed cost override per specific product(s)
 * Tab 2: Weight-Based Rules — tiered cost by total cart weight
 *
 * @package Shipping_Per_Product
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SPP_Admin {

	const MAX_COST   = 99999.99;
	const MAX_WEIGHT = 99999.999;

	public static function init() {
		add_action( 'admin_menu',            [ __CLASS__, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );

		// Per-product rule AJAX.
		add_action( 'wp_ajax_spp_save_rule',        [ __CLASS__, 'ajax_save_rule' ] );
		add_action( 'wp_ajax_spp_delete_rule',      [ __CLASS__, 'ajax_delete_rule' ] );
		add_action( 'wp_ajax_spp_search_products',  [ __CLASS__, 'ajax_search_products' ] );

		// Weight rule AJAX.
		add_action( 'wp_ajax_spp_save_weight_rules',   [ __CLASS__, 'ajax_save_weight_rules' ] );
		add_action( 'wp_ajax_spp_delete_weight_rule',  [ __CLASS__, 'ajax_delete_weight_rule' ] );
	}

	// ── Menu ────────────────────────────────────────────────────────────────

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

	// ── Assets ──────────────────────────────────────────────────────────────

	public static function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'shipping-per-product' ) === false ) {
			return;
		}

		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script( 'select2' );

		wp_enqueue_style( 'spp-admin', SPP_PLUGIN_URL . 'assets/css/admin.css',
			[ 'woocommerce_admin_styles' ], SPP_VERSION );

		wp_enqueue_script( 'spp-admin', SPP_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery', 'select2' ], SPP_VERSION, true );

		wp_localize_script( 'spp-admin', 'sppData', [
			'ajaxUrl'      => esc_url( admin_url( 'admin-ajax.php' ) ),
			'currency'     => get_woocommerce_currency_symbol(),
			'weightUnit'   => get_option( 'woocommerce_weight_unit', 'kg' ),
			'nonces'       => [
				'save'              => wp_create_nonce( 'spp_save_rule' ),
				'delete'            => wp_create_nonce( 'spp_delete_rule' ),
				'search'            => wp_create_nonce( 'spp_search_products' ),
				'saveWeightRules'   => wp_create_nonce( 'spp_save_weight_rules' ),
				'deleteWeightRule'  => wp_create_nonce( 'spp_delete_weight_rule' ),
			],
			'i18n' => [
				'confirmDelete'     => __( 'Are you sure you want to remove this rule?', 'shipping-per-product' ),
				'saving'            => __( 'Saving…', 'shipping-per-product' ),
				'save'              => __( 'Save Rule', 'shipping-per-product' ),
				'saveWeightRules'   => __( 'Save Weight Rules', 'shipping-per-product' ),
				'networkError'      => __( 'Network error. Please try again.', 'shipping-per-product' ),
				'selectProduct'     => __( 'Please select at least one product.', 'shipping-per-product' ),
				'invalidCost'       => __( 'Please enter a valid shipping cost (0 or more).', 'shipping-per-product' ),
				'invalidWeight'     => __( 'Max weight must be greater than min weight (or 0 for unlimited).', 'shipping-per-product' ),
				'weightRulesSaved'  => __( 'Weight rules saved.', 'shipping-per-product' ),
				'unlimited'         => __( 'Unlimited', 'shipping-per-product' ),
			],
		] );
	}

	// ── Page render ─────────────────────────────────────────────────────────

	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'shipping-per-product' ) );
		}

		$per_product_rules = self::get_per_product_rules();
		$weight_rules      = SPP_Calculator::get_weight_rules();
		$active_tab        = isset( $_GET['tab'] ) && 'weight' === $_GET['tab'] ? 'weight' : 'product';
		$weight_unit       = esc_html( get_option( 'woocommerce_weight_unit', 'kg' ) );
		$currency          = get_woocommerce_currency_symbol();
		?>
		<div class="spp-wrap">

			<!-- ── Header ── -->
			<div class="spp-header">
				<div class="spp-header-inner">
					<a href="https://www.herastudiolk.com" target="_blank" rel="noopener noreferrer" class="spp-logo">
						<img src="<?php echo esc_url( SPP_PLUGIN_URL . 'assets/images/hera-logo.png' ); ?>"
							alt="Hera Studio LK" class="spp-logo-icon">
						<div class="spp-logo-text">
							<span class="spp-logo-name"><?php esc_html_e( 'Shipping Per Product', 'shipping-per-product' ); ?></span>
							<span class="spp-logo-sub">by Hera Studio LK</span>
						</div>
					</a>
					<a href="https://www.herastudiolk.com" target="_blank" rel="noopener noreferrer" class="spp-badge">
						herastudiolk.com <span class="spp-version">v<?php echo esc_html( SPP_VERSION ); ?></span>
					</a>
				</div>
			</div>

			<!-- ── Tab nav ── -->
			<div class="spp-tab-nav-wrap">
				<div class="spp-tab-nav">
					<a href="?page=shipping-per-product&tab=product"
						class="spp-tab <?php echo $active_tab === 'product' ? 'spp-tab--active' : ''; ?>">
						<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
						<?php esc_html_e( 'Per-Product Rules', 'shipping-per-product' ); ?>
						<span class="spp-tab-count"><?php echo count( $per_product_rules ); ?></span>
					</a>
					<a href="?page=shipping-per-product&tab=weight"
						class="spp-tab <?php echo $active_tab === 'weight' ? 'spp-tab--active' : ''; ?>">
						<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
						<?php esc_html_e( 'Weight-Based Rules', 'shipping-per-product' ); ?>
						<span class="spp-tab-count"><?php echo count( $weight_rules ); ?></span>
					</a>
				</div>
			</div>

			<div class="spp-body">

			<?php if ( $active_tab === 'product' ) : ?>

				<!-- ══════════════════════════════════════════════════
				     TAB 1 — Per-Product Rules
				     ══════════════════════════════════════════════════ -->

				<!-- Add / Edit form -->
				<div class="spp-card spp-card--form">
					<div class="spp-card-header">
						<h2><?php esc_html_e( 'Add Per-Product Rule', 'shipping-per-product' ); ?></h2>
						<p class="spp-card-desc">
							<?php esc_html_e( 'Select one or more products and set a fixed shipping cost. Assign the "Hera Shipping" class to each product so this rule takes priority over the weight calculation.', 'shipping-per-product' ); ?>
						</p>
					</div>

					<form id="spp-rule-form" class="spp-form" autocomplete="off">
						<input type="hidden" id="spp-rule-id" name="rule_id" value="">

						<div class="spp-field">
							<label for="spp-products"><?php esc_html_e( 'Products', 'shipping-per-product' ); ?></label>
							<select id="spp-products" name="product_ids[]" multiple="multiple" class="spp-select2" style="width:100%"></select>
							<span class="spp-hint"><?php esc_html_e( 'Products must have the "Hera Shipping" class assigned (product edit → Shipping tab).', 'shipping-per-product' ); ?></span>
						</div>

						<div class="spp-row-2col">
							<div class="spp-field">
								<label for="spp-label"><?php esc_html_e( 'Rule Label (optional)', 'shipping-per-product' ); ?></label>
								<input type="text" id="spp-label" name="label" maxlength="255"
									placeholder="<?php esc_attr_e( 'e.g. Fragile Items', 'shipping-per-product' ); ?>">
							</div>
							<div class="spp-field">
								<label for="spp-cost"><?php esc_html_e( 'Shipping Cost', 'shipping-per-product' ); ?></label>
								<div class="spp-cost-input">
									<span class="spp-currency"><?php echo esc_html( $currency ); ?></span>
									<input type="number" id="spp-cost" name="cost"
										min="0" max="<?php echo esc_attr( self::MAX_COST ); ?>" step="0.01" placeholder="0.00">
								</div>
								<span class="spp-hint"><?php esc_html_e( 'Flat cost per item quantity.', 'shipping-per-product' ); ?></span>
							</div>
						</div>

						<div class="spp-actions">
							<button type="submit" id="spp-submit" class="spp-btn spp-btn--primary">
								<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
								<?php esc_html_e( 'Save Rule', 'shipping-per-product' ); ?>
							</button>
							<button type="button" id="spp-cancel-edit" class="spp-btn spp-btn--ghost" style="display:none">
								<?php esc_html_e( 'Cancel', 'shipping-per-product' ); ?>
							</button>
						</div>

						<div id="spp-msg" class="spp-msg" style="display:none" role="alert" aria-live="polite"></div>
					</form>
				</div>

				<!-- Rules table -->
				<div class="spp-card spp-card--table">
					<div class="spp-card-header">
						<h2><?php esc_html_e( 'Per-Product Rules', 'shipping-per-product' ); ?></h2>
						<span class="spp-count"><?php printf(
							esc_html( _n( '%d rule', '%d rules', count( $per_product_rules ), 'shipping-per-product' ) ),
							count( $per_product_rules )
						); ?></span>
					</div>

					<?php if ( empty( $per_product_rules ) ) : ?>
						<div class="spp-empty">
							<svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
							<p><?php esc_html_e( 'No per-product rules yet. Add your first rule above.', 'shipping-per-product' ); ?></p>
						</div>
					<?php else : ?>
						<div class="spp-table-wrap">
							<table class="spp-table" id="spp-rules-table">
								<thead><tr>
									<th><?php esc_html_e( 'Label', 'shipping-per-product' ); ?></th>
									<th><?php esc_html_e( 'Products', 'shipping-per-product' ); ?></th>
									<th><?php esc_html_e( 'Cost / item', 'shipping-per-product' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'shipping-per-product' ); ?></th>
								</tr></thead>
								<tbody>
								<?php foreach ( $per_product_rules as $rule ) :
									$ids   = self::decode_product_ids( $rule->product_ids );
									$names = self::get_product_names( $ids );
								?>
									<tr data-rule-id="<?php echo esc_attr( $rule->id ); ?>"
										data-product-ids="<?php echo esc_attr( wp_json_encode( $ids ) ); ?>"
										data-cost="<?php echo esc_attr( $rule->cost ); ?>"
										data-label="<?php echo esc_attr( $rule->label ); ?>">
										<td><?php echo $rule->label
											? '<span class="spp-rule-label">' . esc_html( $rule->label ) . '</span>'
											: '<span class="spp-rule-label spp-rule-label--empty">&mdash;</span>'; ?></td>
										<td><div class="spp-product-tags">
											<?php if ( ! empty( $names ) ) :
												foreach ( $names as $n ) echo '<span class="spp-tag">' . esc_html( $n ) . '</span>';
											else : ?>
												<span class="spp-tag spp-tag--missing"><?php esc_html_e( 'Not found', 'shipping-per-product' ); ?></span>
											<?php endif; ?>
										</div></td>
										<td><strong><?php echo esc_html( $currency . number_format( (float) $rule->cost, 2 ) ); ?></strong></td>
										<td class="spp-td-actions">
											<button class="spp-btn spp-btn--edit spp-edit-rule" type="button">
												<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
												<?php esc_html_e( 'Edit', 'shipping-per-product' ); ?>
											</button>
											<button class="spp-btn spp-btn--danger spp-delete-rule" type="button">
												<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
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

				<!-- How it works -->
				<div class="spp-card spp-card--info">
					<div class="spp-info-icon">
						<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
					</div>
					<div>
						<strong><?php esc_html_e( 'Per-product override', 'shipping-per-product' ); ?></strong>
						<p><?php esc_html_e( 'Products in this list use a fixed shipping cost regardless of weight. All other products fall through to the Weight-Based Rules. Set the "Hera Shipping" class on a product in its Shipping tab to enable overrides.', 'shipping-per-product' ); ?></p>
					</div>
				</div>

			<?php else : ?>

				<!-- ══════════════════════════════════════════════════
				     TAB 2 — Weight-Based Rules
				     ══════════════════════════════════════════════════ -->

				<div class="spp-card spp-card--form">
					<div class="spp-card-header">
						<h2><?php esc_html_e( 'Weight-Based Shipping Rules', 'shipping-per-product' ); ?></h2>
						<p class="spp-card-desc">
							<?php printf(
								esc_html__( 'Define weight ranges and their shipping costs. Weights are in %s. The first matching range is used. Set max to 0 for an unlimited upper bound (catch-all tier).', 'shipping-per-product' ),
								'<strong>' . esc_html( $weight_unit ) . '</strong>'
							); ?>
						</p>
					</div>

					<div class="spp-weight-editor" id="spp-weight-editor">

						<div class="spp-weight-header">
							<span><?php esc_html_e( 'Label', 'shipping-per-product' ); ?></span>
							<span><?php printf( esc_html__( 'Min Weight (%s)', 'shipping-per-product' ), esc_html( $weight_unit ) ); ?></span>
							<span><?php printf( esc_html__( 'Max Weight (%s)', 'shipping-per-product' ), esc_html( $weight_unit ) ); ?></span>
							<span><?php esc_html_e( 'Shipping Cost', 'shipping-per-product' ); ?></span>
							<span><?php esc_html_e( 'Actions', 'shipping-per-product' ); ?></span>
						</div>

						<div id="spp-weight-rows">
							<?php foreach ( $weight_rules as $wr ) : ?>
								<?php self::render_weight_row( $wr, $currency ); ?>
							<?php endforeach; ?>
						</div>

						<div class="spp-weight-footer">
							<button type="button" id="spp-add-weight-row" class="spp-btn spp-btn--ghost spp-btn--sm">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
								<?php esc_html_e( 'Add Rule', 'shipping-per-product' ); ?>
							</button>
						</div>
					</div>

					<div class="spp-weight-actions">
						<button type="button" id="spp-save-weight-rules" class="spp-btn spp-btn--primary">
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
							<?php esc_html_e( 'Save Weight Rules', 'shipping-per-product' ); ?>
						</button>
						<div id="spp-weight-msg" class="spp-msg" style="display:none" role="alert" aria-live="polite"></div>
					</div>
				</div>

				<!-- How it works -->
				<div class="spp-card spp-card--info">
					<div class="spp-info-icon">
						<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
					</div>
					<div>
						<strong><?php esc_html_e( 'How weight rules work', 'shipping-per-product' ); ?></strong>
						<p><?php esc_html_e( 'The total weight of all products without a per-product override is calculated. The first matching range in this table determines the shipping cost. Products with a per-product rule are excluded from the weight total and charged their fixed cost instead. Set a product\'s weight in WooCommerce → Products → Shipping tab.', 'shipping-per-product' ); ?></p>
					</div>
				</div>

			<?php endif; ?>
			</div><!-- .spp-body -->
		</div><!-- .spp-wrap -->

		<!-- Hidden template for new weight rows (cloned by JS) -->
		<script type="text/html" id="spp-weight-row-template">
			<?php self::render_weight_row( null, $currency ); ?>
		</script>
		<?php
	}

	/**
	 * Render a single weight rule row (PHP side).
	 * Passing null for $rule renders a blank template row used by JS.
	 */
	private static function render_weight_row( $rule, $currency ) {
		$id    = $rule ? (int) $rule->id    : 0;
		$label = $rule ? esc_attr( $rule->label )      : '';
		$min   = $rule ? esc_attr( $rule->min_weight )  : '';
		$max   = $rule ? esc_attr( $rule->max_weight )  : '';
		$cost  = $rule ? esc_attr( number_format( (float) $rule->cost, 2 ) ) : '';
		?>
		<div class="spp-weight-row" data-id="<?php echo esc_attr( $id ); ?>">
			<input type="hidden" class="spp-wr-id" value="<?php echo esc_attr( $id ); ?>">
			<input type="text"   class="spp-wr-label"  placeholder="<?php esc_attr_e( 'e.g. Standard', 'shipping-per-product' ); ?>" value="<?php echo $label; ?>" maxlength="255">
			<input type="number" class="spp-wr-min"    placeholder="0" min="0" step="0.001" value="<?php echo $min; ?>">
			<div class="spp-wr-max-wrap">
				<input type="number" class="spp-wr-max" placeholder="<?php esc_attr_e( '0 = unlimited', 'shipping-per-product' ); ?>" min="0" step="0.001" value="<?php echo $max; ?>">
			</div>
			<div class="spp-cost-input spp-wr-cost-wrap">
				<span class="spp-currency"><?php echo esc_html( $currency ); ?></span>
				<input type="number" class="spp-wr-cost" placeholder="0.00" min="0" max="<?php echo esc_attr( self::MAX_COST ); ?>" step="0.01" value="<?php echo $cost; ?>">
			</div>
			<div class="spp-wr-actions">
				<button type="button" class="spp-btn spp-btn--icon spp-wr-duplicate" title="<?php esc_attr_e( 'Duplicate', 'shipping-per-product' ); ?>">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
				</button>
				<button type="button" class="spp-btn spp-btn--icon spp-btn--icon-danger spp-wr-delete" title="<?php esc_attr_e( 'Delete', 'shipping-per-product' ); ?>">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
				</button>
			</div>
		</div>
		<?php
	}

	// ── AJAX — Per-product save ──────────────────────────────────────────────

	public static function ajax_save_rule() {
		check_ajax_referer( 'spp_save_rule', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'shipping-per-product' ) ], 403 );
		}

		$raw_ids     = isset( $_POST['product_ids'] ) ? (array) $_POST['product_ids'] : [];
		$product_ids = array_values( array_filter( array_map( 'absint', $raw_ids ) ) );
		if ( empty( $product_ids ) ) {
			wp_send_json_error( [ 'message' => __( 'Please select at least one product.', 'shipping-per-product' ) ], 422 );
		}

		$product_ids = self::filter_valid_product_ids( $product_ids );
		if ( empty( $product_ids ) ) {
			wp_send_json_error( [ 'message' => __( 'None of the selected products are valid.', 'shipping-per-product' ) ], 422 );
		}

		$raw_cost = isset( $_POST['cost'] ) ? sanitize_text_field( wp_unslash( $_POST['cost'] ) ) : '0';
		$cost     = round( min( max( (float) $raw_cost, 0.0 ), self::MAX_COST ), 2 );
		$label    = isset( $_POST['label'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['label'] ) ), 0, 255 ) : '';
		$rule_id  = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;

		global $wpdb;
		$table = $wpdb->prefix . 'spp_rules';
		$json  = wp_json_encode( $product_ids );

		if ( $rule_id ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d LIMIT 1", $rule_id ) ) ) {
				wp_send_json_error( [ 'message' => __( 'Rule not found.', 'shipping-per-product' ) ], 404 );
			}
			$wpdb->update( $table,
				[ 'product_ids' => $json, 'cost' => $cost, 'label' => $label ],
				[ 'id' => $rule_id ],
				[ '%s', '%f', '%s' ], [ '%d' ]
			);
		} else {
			$wpdb->insert( $table,
				[ 'product_ids' => $json, 'cost' => $cost, 'label' => $label ],
				[ '%s', '%f', '%s' ]
			);
			$rule_id = (int) $wpdb->insert_id;
		}

		self::flush_shipping_cache();

		wp_send_json_success( [
			'rule_id'       => $rule_id,
			'product_ids'   => $product_ids,
			'product_names' => self::get_product_names( $product_ids ),
			'cost'          => $cost,
			'label'         => $label,
			'currency'      => get_woocommerce_currency_symbol(),
			'message'       => __( 'Rule saved.', 'shipping-per-product' ),
		] );
	}

	// ── AJAX — Per-product delete ────────────────────────────────────────────

	public static function ajax_delete_rule() {
		check_ajax_referer( 'spp_delete_rule', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'shipping-per-product' ) ], 403 );
		}

		$rule_id = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;
		if ( ! $rule_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid rule ID.', 'shipping-per-product' ) ], 422 );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'spp_rules';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d LIMIT 1", $rule_id ) ) ) {
			wp_send_json_error( [ 'message' => __( 'Rule not found.', 'shipping-per-product' ) ], 404 );
		}

		$wpdb->delete( $table, [ 'id' => $rule_id ], [ '%d' ] );
		self::flush_shipping_cache();
		wp_send_json_success( [ 'message' => __( 'Rule deleted.', 'shipping-per-product' ) ] );
	}

	// ── AJAX — Weight rules bulk save ────────────────────────────────────────

	/**
	 * Receives the full ordered list of weight rows from JS and replaces the
	 * DB contents atomically (delete-all then re-insert). This makes row
	 * reordering, inline editing, and deletion all handled in one request.
	 */
	public static function ajax_save_weight_rules() {
		check_ajax_referer( 'spp_save_weight_rules', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'shipping-per-product' ) ], 403 );
		}

		$raw_rows = isset( $_POST['rows'] ) ? (array) $_POST['rows'] : [];
		$rows     = [];

		foreach ( $raw_rows as $i => $row ) {
			$min   = round( max( 0.0, (float) sanitize_text_field( wp_unslash( $row['min'] ?? '0' ) ) ), 3 );
			$max   = round( max( 0.0, (float) sanitize_text_field( wp_unslash( $row['max'] ?? '0' ) ) ), 3 );
			$cost  = round( min( max( 0.0, (float) sanitize_text_field( wp_unslash( $row['cost'] ?? '0' ) ) ), self::MAX_COST ), 2 );
			$label = mb_substr( sanitize_text_field( wp_unslash( $row['label'] ?? '' ) ), 0, 255 );

			// Validate: if max > 0 it must be > min.
			if ( $max > 0 && $max <= $min ) {
				wp_send_json_error( [
					'message' => sprintf(
						__( 'Row %d: Max weight must be greater than min weight (or 0 for unlimited).', 'shipping-per-product' ),
						$i + 1
					),
				], 422 );
			}

			$rows[] = compact( 'min', 'max', 'cost', 'label' );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'spp_weight_rules';

		// Atomic replacement: truncate then re-insert in displayed order.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "TRUNCATE TABLE {$table}" );

		foreach ( $rows as $sort => $r ) {
			$wpdb->insert( $table, [
				'min_weight' => $r['min'],
				'max_weight' => $r['max'],
				'cost'       => $r['cost'],
				'label'      => $r['label'],
				'sort_order' => $sort,
			], [ '%f', '%f', '%f', '%s', '%d' ] );
		}

		self::flush_shipping_cache();
		wp_send_json_success( [ 'message' => __( 'Weight rules saved.', 'shipping-per-product' ) ] );
	}

	// ── AJAX — Product search ────────────────────────────────────────────────

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
		foreach ( $query->posts as $pid ) {
			$p = wc_get_product( $pid );
			if ( $p ) {
				$results[] = [ 'id' => (int) $pid, 'text' => $p->get_name() . ' (#' . (int) $pid . ')' ];
			}
		}

		wp_send_json( [
			'results'    => $results,
			'pagination' => [ 'more' => $paged < (int) $query->max_num_pages ],
		] );
	}

	// ── Private helpers ──────────────────────────────────────────────────────

	private static function get_per_product_rules() {
		global $wpdb;
		$table = $wpdb->prefix . 'spp_rules';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", 500 ) );
	}

	private static function decode_product_ids( $json ) {
		$d = json_decode( $json, true );
		return is_array( $d ) ? array_values( array_filter( array_map( 'absint', $d ) ) ) : [];
	}

	private static function get_product_names( array $ids ) {
		$names = [];
		foreach ( $ids as $id ) {
			$p = wc_get_product( $id );
			if ( $p ) $names[] = $p->get_name();
		}
		return $names;
	}

	private static function filter_valid_product_ids( array $ids ) {
		if ( empty( $ids ) ) return [];
		global $wpdb;
		$ph  = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$sql = $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE ID IN ( {$ph} ) AND post_type='product' AND post_status='publish'",
			...$ids
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array_values( array_map( 'absint', $wpdb->get_col( $sql ) ) );
	}

	private static function flush_shipping_cache() {
		WC_Cache_Helper::get_transient_version( 'shipping', true );
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( 'shipping_for_package', null );
		}
	}
}
