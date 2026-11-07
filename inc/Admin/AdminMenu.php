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


		add_filter( 'script_loader_tag', function ( $tag, $handle ) {
			if ( 'appointment-pro-admin' !== $handle ) {
				return $tag;
			}

			return str_replace( '<script ', '<script type="module" ', $tag );
		}, 10, 2 );

	}

}
