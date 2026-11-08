<?php

namespace Praxleo\Appointment\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the Praxleo Booking admin menu.
 *
 * @package Praxleo_Booking
 */
class AdminMenu {

	/**
	 * URL of the Vite dev server.
	 *
	 * Must match the host and port in src/admin/vite.config.ts.
	 */
	private const VITE_DEV_SERVER = 'http://127.0.0.1:5173';

	/**
	 * Script handles that must be printed as ES modules.
	 *
	 * @var string[]
	 */
	private const MODULE_HANDLES = array( 'appointment-pro-admin', 'appointment-pro-vite-client' );

	/**
	 * Initialize the admin menu.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Register the plugin's admin menu and submenu pages.
	 *
	 * @return void
	 */
	public function register_admin_menu(): void {
		add_menu_page(
			__( 'Appointment Pro', 'appointment-pro' ),
			__( 'Appointment Pro', 'appointment-pro' ),
			'manage_options',
			'appointment-pro',
			array( $this, 'view_main_page' ),
			'dashicons-schedule',
			15
		);
	}

	/**
	 * Render the plugin dashboard page.
	 *
	 * @return void
	 */
	public function view_main_page(): void {
		?>
		<div class="wrap" id="appointment-pro"></div>
		<?php
	}

	/**
	 * Enqueue admin assets for the plugin.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( 'toplevel_page_appointment-pro' !== $hook ) {
			return;
		}

		// When `npm run dev` is running, load the app from the Vite dev server
		// so changes are hot-reloaded without rebuilding.
		if ( $this->is_dev_server_running() ) {
			$this->enqueue_dev_assets();

			return;
		}

		wp_enqueue_style(
			'appointment-pro-admin',
			plugins_url( 'lib/admin/main.css', PX_APPOINTMENT_FILE ),
			array(),
			filemtime( plugin_dir_path( PX_APPOINTMENT_FILE ) . 'lib/admin/main.css' )
		);

		wp_enqueue_script(
			'appointment-pro-admin',
			plugins_url( 'lib/admin/main.js', PX_APPOINTMENT_FILE ),
			array(),
			filemtime( plugin_dir_path( PX_APPOINTMENT_FILE ) . 'lib/admin/main.js' ),
			true
		);

		add_filter( 'script_loader_tag', array( $this, 'add_module_type' ), 10, 2 );
	}

	/**
	 * Enqueue the admin app from the running Vite dev server.
	 *
	 * Vite serves the ES modules and injects CSS / hot updates at runtime.
	 *
	 * @return void
	 */
	private function enqueue_dev_assets(): void {
		wp_enqueue_script(
			'appointment-pro-vite-client',
			self::VITE_DEV_SERVER . '/@vite/client',
			array(),
			null,
			true
		);

		wp_enqueue_script(
			'appointment-pro-admin',
			self::VITE_DEV_SERVER . '/src/main.ts',
			array( 'appointment-pro-vite-client' ),
			null,
			true
		);

		add_filter( 'script_loader_tag', array( $this, 'add_module_type' ), 10, 2 );
	}

	/**
	 * Add `type="module"` to the plugin's script tags.
	 *
	 * @param string $tag    The script tag markup.
	 * @param string $handle The script handle.
	 * @return string Modified script tag.
	 */
	public function add_module_type( string $tag, string $handle ): string {
		if ( ! in_array( $handle, self::MODULE_HANDLES, true ) ) {
			return $tag;
		}

		return str_replace( '<script ', '<script type="module" ', $tag );
	}

	/**
	 * Check whether the Vite dev server is reachable.
	 *
	 * Results are cached briefly to avoid an HTTP request on every page load.
	 * Define `PX_APPOINTMENT_VITE_DEV` in wp-config.php to force a value.
	 *
	 * @return bool
	 */
	private function is_dev_server_running(): bool {
		if ( defined( 'PX_APPOINTMENT_VITE_DEV' ) ) {
			return (bool) PX_APPOINTMENT_VITE_DEV;
		}

		$cached = get_transient( 'px_appointment_vite_dev' );

		if ( false !== $cached ) {
			return 'yes' === $cached;
		}

		$response = wp_remote_get(
			self::VITE_DEV_SERVER . '/@vite/client',
			array(
				'timeout'   => 0.5,
				'sslverify' => false,
			)
		);

		$running = ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response );

		set_transient( 'px_appointment_vite_dev', $running ? 'yes' : 'no', 10 );

		return $running;
	}

}
