<?php
/**
 * Plugin Name:       Praxleo Appointment Pro
 * Plugin URI:        https://praxleo.com
 * Description:       Appointment and scheduling for WordPress.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Praxleo LTD
 * Author URI:        https://praxleo.com
 * License:           GPL-2.0-or-later
 * Text Domain:       appointment-pro
 * Domain Path:       /lang
 *
 * @package Praxleo_Booking
 */

use Praxleo\Appointment\AppointmentPro;

defined( 'ABSPATH' ) || exit;

define( 'PX_APPOINTMENT_VERSION', '1.0.0' );
define( 'PX_APPOINTMENT_DB_VERSION', '1.0.0' );
define( 'PX_APPOINTMENT_FILE', __FILE__ );
define( 'PX_APPOINTMENT_PATH', plugin_dir_path( __FILE__ ) );
define( 'PX_APPOINTMENT_URL', plugin_dir_url( __FILE__ ) );
define( 'PX_LIB_GLOBAL', trailingslashit( PX_APPOINTMENT_URL . 'lib/global' ) );
define( 'PX_CSS_URL', trailingslashit( PX_APPOINTMENT_URL . 'lib/frontend/css' ) );
define( 'PX_JS_URL', trailingslashit( PX_APPOINTMENT_URL . 'lib/frontend/js' ) );
define( 'PX_ADMIN_CSS_URL', trailingslashit( PX_APPOINTMENT_URL . 'lib/admin/css' ) );
define( 'PX_ADMIN_JS_URL', trailingslashit( PX_APPOINTMENT_URL . 'lib/admin/js' ) );

$appointment_pro_autoloader = PX_APPOINTMENT_PATH . 'vendor/autoload.php';

if ( file_exists( $appointment_pro_autoloader ) ) {
	require_once $appointment_pro_autoloader;
}

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'Praxleo\\Appointment\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative_class = substr( $class_name, strlen( $prefix ) );
		$class_file     = PX_APPOINTMENT_PATH . 'inc/' . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $class_file ) ) {
			require_once $class_file;
		}
	}
);


register_activation_hook( __FILE__, array( \Praxleo\Appointment\Installer::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \Praxleo\Appointment\Installer::class, 'deactivate' ) );

if ( ! function_exists( 'px_appointment_pro' ) ) {
	/**
	 * Return the main plugin instance.
	 *
	 * @return AppointmentPro
	 */
	function px_appointment_pro(): AppointmentPro {
		return AppointmentPro::init();
	}
}

px_appointment_pro();
