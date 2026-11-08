<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'px_appointment_settings', array() );
if ( empty( $settings['delete_data_on_uninstall'] ) ) {
	return;
}

global $wpdb;
$tables = array(
	'px_appointment_locations',
	'px_appointment_service_categories',
	'px_appointment_services',
	'px_appointment_staff',
	'px_appointment_staff_services',
	'px_appointment_staff_schedule',
	'px_appointment_staff_breaks',
	'px_appointment_staff_days_off',
	'px_appointment_customers',
	'px_appointment_appointments',
	'px_appointment_customer_appointments',
	'px_appointment_extras',
	'px_appointment_coupons',
	'px_appointment_coupon_services',
	'px_appointment_payments',
	'px_appointment_custom_fields',
	'px_appointment_notifications',
);
foreach ( $tables as $table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . $table );
}
delete_option( 'px_appointment_settings' );
delete_option( 'px_appointment_db_version' );
delete_option( 'px_appointment_mail_error' );
