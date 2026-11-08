<?php
/**
 * Plugin installer.
 *
 * @package Praxleo_Appointment
 */

namespace Praxleo\Appointment;

use Praxleo\Appointment\Database\Schema;
use Praxleo\Appointment\Domain\Mailer;
use Praxleo\Appointment\Domain\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Runs installation and upgrade routines.
 */
class Installer {

	/**
	 * Run on plugin activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::install();
		self::schedule_events();
	}

	/**
	 * Run on plugin deactivation.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		$timestamp = wp_next_scheduled( 'px_appointment_hourly' );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'px_appointment_hourly' );
		}
	}

	/**
	 * Install tables and seed defaults when the version changes.
	 *
	 * @return void
	 */
	public static function maybe_install(): void {
		if ( get_option( 'px_appointment_db_version' ) !== Schema::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Install tables and seed defaults.
	 *
	 * @return void
	 */
	public static function install(): void {
		Schema::install();
		Settings::seed();
		Mailer::seed_templates();
		self::schedule_events();
	}

	/**
	 * Schedule the hourly maintenance event.
	 *
	 * @return void
	 */
	private static function schedule_events(): void {
		if ( ! wp_next_scheduled( 'px_appointment_hourly' ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', 'px_appointment_hourly' );
		}
	}
}
