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
		<div class="wrap">
			<h1><?php esc_html_e( 'Appointment Pro', 'appointment-pro' ); ?></h1>
			<div id="appointment-pro"></div>
		</div>
		<?php
	}

}
