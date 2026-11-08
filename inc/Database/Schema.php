<?php
/**
 * Database schema for the Appointment Pro plugin.
 *
 * @package Praxleo_Appointment
 */

namespace Praxleo\Appointment\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades the plugin database tables.
 */
class Schema {

	/**
	 * Current database version.
	 */
	public const DB_VERSION = '1.1.0';

	/**
	 * Return a prefixed table name.
	 *
	 * @param string $name Table suffix.
	 * @return string
	 */
	public static function table( string $name ): string {
		global $wpdb;

		return $wpdb->prefix . 'px_appointment_' . $name;
	}

	/**
	 * Install or upgrade the database tables.
	 *
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = array();
		$p   = $wpdb->prefix . 'px_appointment_';

		$sql[] = "CREATE TABLE {$p}locations (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL,
			info text NULL,
			address text NULL,
			phone varchar(64) NULL,
			position int(11) NOT NULL DEFAULT 0,
			enabled tinyint(1) NOT NULL DEFAULT 1,
			PRIMARY KEY  (id)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}service_categories (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL,
			position int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}services (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			category_id bigint(20) unsigned NULL,
			name varchar(191) NOT NULL,
			duration_sec int(11) NOT NULL DEFAULT 3600,
			price decimal(12,2) NOT NULL DEFAULT 0,
			padding_left_sec int(11) NOT NULL DEFAULT 0,
			padding_right_sec int(11) NOT NULL DEFAULT 0,
			color varchar(16) NOT NULL DEFAULT '#0d6efd',
			info text NULL,
			enabled tinyint(1) NOT NULL DEFAULT 1,
			position int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY category_id (category_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}staff (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			wp_user_id bigint(20) unsigned NULL,
			full_name varchar(191) NOT NULL,
			email varchar(191) NULL,
			phone varchar(64) NULL,
			info text NULL,
			color varchar(16) NOT NULL DEFAULT '#359FA0',
			enabled tinyint(1) NOT NULL DEFAULT 1,
			position int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}staff_services (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			staff_id bigint(20) unsigned NOT NULL,
			service_id bigint(20) unsigned NOT NULL,
			location_id bigint(20) unsigned NOT NULL,
			price decimal(12,2) NULL,
			enabled tinyint(1) NOT NULL DEFAULT 1,
			PRIMARY KEY  (id),
			UNIQUE KEY staff_service_loc (staff_id, service_id, location_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}staff_schedule (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			staff_id bigint(20) unsigned NOT NULL,
			location_id bigint(20) unsigned NULL,
			weekday tinyint(1) NOT NULL,
			start_time time NOT NULL,
			end_time time NOT NULL,
			PRIMARY KEY  (id),
			KEY staff_day (staff_id, weekday)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}staff_breaks (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			schedule_id bigint(20) unsigned NOT NULL,
			start_time time NOT NULL,
			end_time time NOT NULL,
			PRIMARY KEY  (id),
			KEY schedule_id (schedule_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}staff_days_off (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			staff_id bigint(20) unsigned NOT NULL,
			start_datetime datetime NOT NULL,
			end_datetime datetime NOT NULL,
			all_day tinyint(1) NOT NULL DEFAULT 1,
			reason varchar(191) NULL,
			PRIMARY KEY  (id),
			KEY staff_id (staff_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}customers (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			wp_user_id bigint(20) unsigned NULL,
			full_name varchar(191) NOT NULL,
			email varchar(191) NOT NULL,
			phone varchar(64) NULL,
			notes text NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY email (email)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}appointments (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			location_id bigint(20) unsigned NOT NULL,
			service_id bigint(20) unsigned NOT NULL,
			staff_id bigint(20) unsigned NOT NULL,
			start_datetime datetime NOT NULL,
			end_datetime datetime NOT NULL,
			status varchar(32) NOT NULL DEFAULT 'pending',
			hold_until datetime NULL,
			idempotency_key varchar(64) NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY idempotency_key (idempotency_key),
			KEY staff_start (staff_id, start_datetime, end_datetime),
			KEY status (status)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}customer_appointments (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			appointment_id bigint(20) unsigned NOT NULL,
			customer_id bigint(20) unsigned NOT NULL,
			amount decimal(12,2) NULL,
			number_of_persons int(11) NOT NULL DEFAULT 1,
			extras_json longtext NULL,
			custom_fields_json longtext NULL,
			coupon_id bigint(20) unsigned NULL,
			notes text NULL,
			token varchar(64) NOT NULL,
			reminder_sent_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token (token),
			KEY appointment_id (appointment_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}extras (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			service_id bigint(20) unsigned NOT NULL,
			title varchar(191) NOT NULL,
			duration_sec int(11) NOT NULL DEFAULT 0,
			price decimal(12,2) NOT NULL DEFAULT 0,
			max_quantity int(11) NOT NULL DEFAULT 1,
			enabled tinyint(1) NOT NULL DEFAULT 1,
			position int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY service_id (service_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}coupons (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			code varchar(64) NOT NULL,
			discount_type varchar(16) NOT NULL DEFAULT 'percent',
			discount_amount decimal(12,2) NOT NULL DEFAULT 0,
			usage_limit int(11) NULL,
			used int(11) NOT NULL DEFAULT 0,
			min_amount decimal(12,2) NOT NULL DEFAULT 0,
			start_date date NULL,
			end_date date NULL,
			enabled tinyint(1) NOT NULL DEFAULT 1,
			PRIMARY KEY  (id),
			UNIQUE KEY code (code)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}coupon_services (
			coupon_id bigint(20) unsigned NOT NULL,
			service_id bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (coupon_id, service_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}payments (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			customer_appointment_id bigint(20) unsigned NOT NULL,
			type varchar(32) NOT NULL,
			status varchar(32) NOT NULL DEFAULT 'pending',
			amount decimal(12,2) NOT NULL DEFAULT 0,
			currency varchar(8) NOT NULL DEFAULT 'USD',
			details_json longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY ca_id (customer_appointment_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}custom_fields (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			label varchar(191) NOT NULL,
			type varchar(32) NOT NULL DEFAULT 'text',
			required tinyint(1) NOT NULL DEFAULT 0,
			options_json longtext NULL,
			services_json longtext NULL,
			position int(11) NOT NULL DEFAULT 0,
			enabled tinyint(1) NOT NULL DEFAULT 1,
			PRIMARY KEY  (id)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}notifications (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event varchar(64) NOT NULL,
			enabled tinyint(1) NOT NULL DEFAULT 1,
			subject varchar(191) NOT NULL,
			body_html longtext NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event (event)
		) $charset;";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		update_option( 'px_appointment_db_version', self::DB_VERSION );
	}
}
