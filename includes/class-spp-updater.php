<?php
/**
 * GitHub Auto-Updater for Shipping Per Product.
 *
 * Hooks into WordPress's native plugin update system and checks the GitHub
 * Releases API for new versions. When a newer release is found, WordPress
 * displays the standard "Update available" notice in the plugins list —
 * and admins can update with a single click, exactly like any repo plugin.
 *
 * Setup required (in your GitHub repository):
 *  1. Every release MUST be tagged as  vX.Y.Z  (e.g. v1.0.3).
 *  2. Each release MUST have a .zip asset attached named
 *     shipping-per-product.zip  (the installable plugin zip).
 *  3. Set SPP_GITHUB_REPO below to  "username/repo-name".
 *
 * @package Shipping_Per_Product
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SPP_Updater {

	/**
	 * Your GitHub repository in "owner/repo" format.
	 * Change this to match your actual GitHub username and repository name.
	 *
	 * @var string
	 */
	const GITHUB_REPO = 'YOUR_GITHUB_USERNAME/Shipping-Per-Product';

	/** GitHub API endpoint for the latest release. */
	const API_URL = 'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest';

	/** Transient key used to cache the API response (12-hour TTL). */
	const TRANSIENT_KEY = 'spp_github_update_data';

	/** Transient TTL in seconds (12 hours). */
	const CACHE_TTL = 43200;

	/** Basename of this plugin (folder/file.php). */
	private static $plugin_basename = '';

	// -----------------------------------------------------------------------

	public static function init() {
		self::$plugin_basename = plugin_basename( SPP_PLUGIN_FILE );

		// Intercept WordPress's plugin update check.
		add_filter( 'pre_set_site_transient_update_plugins', [ __CLASS__, 'check_for_update' ] );

		// Populate the plugin info popup (View details / View version X.Y.Z details).
		add_filter( 'plugins_api', [ __CLASS__, 'plugin_info' ], 20, 3 );

		// After a successful update, clear our cached release data.
		add_action( 'upgrader_process_complete', [ __CLASS__, 'after_update' ], 10, 2 );
	}

	// -----------------------------------------------------------------------
	// Core: check for update
	// -----------------------------------------------------------------------

	/**
	 * Called by WordPress before it decides whether to show update notices.
	 * We inject our own update data into the transient if a new version exists.
	 *
	 * @param  object $transient The update_plugins site transient.
	 * @return object
	 */
	public static function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = self::fetch_latest_release();
		if ( ! $release ) {
			return $transient;
		}

		$latest_version = self::parse_version( $release->tag_name );

		if ( version_compare( $latest_version, SPP_VERSION, '>' ) ) {
			$download_url = self::get_zip_asset_url( $release );

			if ( $download_url ) {
				$transient->response[ self::$plugin_basename ] = (object) [
					'slug'        => 'shipping-per-product',
					'plugin'      => self::$plugin_basename,
					'new_version' => $latest_version,
					'url'         => 'https://www.herastudiolk.com',
					'package'     => $download_url,
					'icons'       => [],
					'banners'     => [],
					'requires'    => '5.8',
					'tested'      => '6.5',
					'requires_php'=> '7.4',
				];
			}
		} else {
			// No update needed — tell WordPress the current version is fine.
			$transient->no_update[ self::$plugin_basename ] = (object) [
				'slug'        => 'shipping-per-product',
				'plugin'      => self::$plugin_basename,
				'new_version' => SPP_VERSION,
				'url'         => 'https://www.herastudiolk.com',
				'package'     => '',
			];
		}

		return $transient;
	}

	// -----------------------------------------------------------------------
	// Core: plugin info popup
	// -----------------------------------------------------------------------

	/**
	 * Populate the "View version details" popup in the WordPress admin.
	 *
	 * @param  false|object $result Result object or false.
	 * @param  string       $action The type of information being requested.
	 * @param  object       $args   Plugin API arguments.
	 * @return false|object
	 */
	public static function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || 'shipping-per-product' !== $args->slug ) {
			return $result;
		}

		$release = self::fetch_latest_release();
		if ( ! $release ) {
			return $result;
		}

		$latest_version = self::parse_version( $release->tag_name );
		$download_url   = self::get_zip_asset_url( $release );
		$changelog      = self::format_changelog( $release->body ?? '' );
		$published      = isset( $release->published_at )
			? date_i18n( get_option( 'date_format' ), strtotime( $release->published_at ) )
			: '';

		return (object) [
			'name'              => 'Shipping Per Product',
			'slug'              => 'shipping-per-product',
			'version'           => $latest_version,
			'author'            => '<a href="https://www.herastudiolk.com" target="_blank">Hera Studio LK</a>',
			'author_profile'    => 'https://www.herastudiolk.com',
			'homepage'          => 'https://www.herastudiolk.com',
			'requires'          => '5.8',
			'tested'            => '6.5',
			'requires_php'      => '7.4',
			'last_updated'      => $published,
			'download_link'     => $download_url,
			'sections'          => [
				'description' => 'Easily add custom shipping costs per product in WooCommerce using the "Hera Shipping" class.',
				'changelog'   => $changelog,
			],
		];
	}

	// -----------------------------------------------------------------------
	// Post-update cleanup
	// -----------------------------------------------------------------------

	/**
	 * Clear our cached release data after any plugin update completes.
	 *
	 * @param WP_Upgrader $upgrader
	 * @param array       $options
	 */
	public static function after_update( $upgrader, $options ) {
		if (
			'update' === ( $options['action'] ?? '' ) &&
			'plugin' === ( $options['type'] ?? '' )
		) {
			delete_transient( self::TRANSIENT_KEY );
		}
	}

	// -----------------------------------------------------------------------
	// GitHub API helpers
	// -----------------------------------------------------------------------

	/**
	 * Fetch the latest release from the GitHub API.
	 * Result is cached in a transient for 12 hours to avoid rate-limiting.
	 *
	 * @return object|false  Decoded JSON object, or false on failure.
	 */
	private static function fetch_latest_release() {
		$cached = get_transient( self::TRANSIENT_KEY );
		if ( false !== $cached ) {
			return $cached;
		}

		$response = wp_remote_get( self::API_URL, [
			'timeout'    => 10,
			'user-agent' => 'ShippingPerProduct/' . SPP_VERSION . '; WordPress/' . get_bloginfo( 'version' ),
			'headers'    => [
				'Accept' => 'application/vnd.github+json',
			],
		] );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );
		if ( ! is_object( $body ) || empty( $body->tag_name ) ) {
			return false;
		}

		set_transient( self::TRANSIENT_KEY, $body, self::CACHE_TTL );

		return $body;
	}

	/**
	 * Strip a leading "v" from a GitHub tag name to get a plain version string.
	 *
	 * @param  string $tag  e.g. "v1.0.3"
	 * @return string       e.g. "1.0.3"
	 */
	private static function parse_version( $tag ) {
		return ltrim( $tag, 'v' );
	}

	/**
	 * Find the URL of the first .zip release asset.
	 * Falls back to the source-code zip GitHub generates automatically
	 * (zipball_url) only as a last resort — your own asset is preferred
	 * because it matches the plugin folder structure WordPress expects.
	 *
	 * @param  object $release  GitHub release object.
	 * @return string|false
	 */
	private static function get_zip_asset_url( $release ) {
		if ( ! empty( $release->assets ) && is_array( $release->assets ) ) {
			foreach ( $release->assets as $asset ) {
				if ( isset( $asset->content_type ) && 'application/zip' === $asset->content_type ) {
					return $asset->browser_download_url;
				}
				// Fallback: match by filename extension.
				if ( isset( $asset->name ) && str_ends_with( strtolower( $asset->name ), '.zip' ) ) {
					return $asset->browser_download_url;
				}
			}
		}

		// Last resort: GitHub's auto-generated source zip.
		return $release->zipball_url ?? false;
	}

	/**
	 * Convert GitHub Markdown release notes to basic HTML for the WP popup.
	 *
	 * @param  string $markdown
	 * @return string HTML
	 */
	private static function format_changelog( $markdown ) {
		if ( empty( $markdown ) ) {
			return '<p>See the <a href="https://github.com/' . esc_attr( self::GITHUB_REPO ) . '/releases" target="_blank">GitHub Releases page</a> for full changelog.</p>';
		}

		$html = esc_html( $markdown );
		// Headers  ## → <h4>
		$html = preg_replace( '/^##\s+(.+)$/m',        '<h4>$1</h4>',    $html );
		// Bold **text**
		$html = preg_replace( '/\*\*(.+?)\*\*/',        '<strong>$1</strong>', $html );
		// List items  - text
		$html = preg_replace( '/^\-\s+(.+)$/m',         '<li>$1</li>',    $html );
		// Wrap li groups in ul
		$html = preg_replace( '/(<li>.*?<\/li>(\n|$))+/s', '<ul>$0</ul>', $html );
		// Line breaks
		$html = nl2br( $html );

		return $html;
	}
}
