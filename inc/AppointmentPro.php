<?php

namespace Praxleo\Appointment;

use Praxleo\Appointment\Admin\AdminMenu;

defined( 'ABSPATH' ) || exit;
/**
 * Main Praxleo Booking plugin class.
 *
 * Manages plugin initialization, dependency loading, and WordPress hooks.
 *
 * @package Praxleo_Booking
 */
final class AppointmentPro {

	/**
	 * The single instance of the class.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Initialize the plugin.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->register_hooks();

		add_filter( 'plugin_action_links_' . plugin_basename( PX_APPOINTMENT_FILE ), array( $this, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'add_row_meta' ), 10, 2 );

		$this->register_admin_menu();
	}

	/**
	 * Add plugin action links.
	 *
	 * @param array $links Existing plugin action links.
	 * @return array Modified plugin action links.
	 */
	public function action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( admin_url( 'appointment-pro.php' ) ),
			esc_html__( 'Settings', 'appointment-pro' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Add metadata links to the plugin row.
	 *
	 * @param array  $plugin_meta Existing plugin metadata.
	 * @param string $plugin_file Plugin file path.
	 * @return array Modified plugin metadata.
	 */
	public function add_row_meta( array $plugin_meta, string $plugin_file ): array {
		if ( plugin_basename( PX_APPOINTMENT_FILE ) !== $plugin_file ) {
			return $plugin_meta;
		}

		$plugin_meta[] = sprintf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-media-document" aria-hidden="true"></span> %2$s</a>',
			esc_url( admin_url( 'praxleo-booking.php#/document' ) ),
			esc_html__( 'Documentation', 'praxleo-booking' )
		);

		return $plugin_meta;
	}

	public function register_admin_menu() {
		if ( is_admin() ) {
			new AdminMenu();
		}
	}

	/**
	 * Retrieve the main plugin instance.
	 *
	 * @return self
	 */
	public static function init(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Load plugin dependencies.
	 *
	 * @return void
	 */
	private function load_dependencies(): void {
		// Load additional classes or files here.
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load the plugin text domain.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'appointment-pro',
			false,
			dirname( plugin_basename( PX_APPOINTMENT_FILE ) ) . '/lang'
		);
	}
}
