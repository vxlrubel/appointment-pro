<?php
/**
 * Plugin settings.
 *
 * @package Praxleo_Appointment
 */

namespace Praxleo\Appointment\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the plugin settings option.
 */
class Settings {

	/**
	 * Option name where settings are stored.
	 */
	private const OPTION = 'px_appointment_settings';

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'company_name'             => get_bloginfo( 'name' ),
			'company_email'            => get_option( 'admin_email' ),
			'timezone'                 => wp_timezone_string(),
			'slot_length'              => 15,
			'auto_approve'             => true,
			'currency'                 => 'USD',
			'hold_ttl'                 => 600,
			'min_lead_hours'           => 1,
			'max_lead_days'            => 60,
			'stripe_enabled'           => false,
			'stripe_publishable'       => '',
			'stripe_secret'            => '',
			'stripe_webhook_secret'    => '',
			'paypal_enabled'           => false,
			'paypal_client_id'         => '',
			'paypal_secret'            => '',
			'paypal_webhook_id'        => '',
			'paypal_sandbox'           => true,
			'woocommerce_enabled'      => false,
			'local_enabled'            => true,
			'delete_data_on_uninstall' => false,
			'reminder_hours'           => 24,
		);
	}

	/**
	 * Ensure the option exists with all default keys.
	 *
	 * @return void
	 */
	public static function seed(): void {
		$current = get_option( self::OPTION, array() );

		update_option(
			self::OPTION,
			array_merge( self::defaults(), is_array( $current ) ? $current : array() )
		);
	}

	/**
	 * Get all settings or a single key.
	 *
	 * @param string|null $key Setting key.
	 * @param mixed       $default Fallback value.
	 * @return mixed
	 */
	public static function get( ?string $key = null, $default = null ) {
		$stored = get_option( self::OPTION, array() );
		$all    = array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );

		if ( null === $key ) {
			return $all;
		}

		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Save settings, merging with existing values.
	 *
	 * @param array<string, mixed> $data Settings to save.
	 * @return array<string, mixed>
	 */
	public static function save( array $data ): array {
		$merged = array_merge( self::get(), $data );

		update_option( self::OPTION, $merged );

		return $merged;
	}

	/**
	 * Return the subset of settings that is safe to expose publicly.
	 *
	 * @return array<string, mixed>
	 */
	public static function public_subset(): array {
		$all = self::get();

		return array(
			'company_name'        => $all['company_name'],
			'currency'            => $all['currency'],
			'slot_length'         => (int) $all['slot_length'],
			'local_enabled'       => (bool) $all['local_enabled'],
			'stripe_enabled'      => (bool) $all['stripe_enabled'] && $all['stripe_publishable'],
			'stripe_publishable'  => $all['stripe_enabled'] ? $all['stripe_publishable'] : '',
			'paypal_enabled'      => (bool) $all['paypal_enabled'] && $all['paypal_client_id'],
			'paypal_client_id'    => $all['paypal_enabled'] ? $all['paypal_client_id'] : '',
			'woocommerce_enabled' => (bool) $all['woocommerce_enabled'] && class_exists( 'WooCommerce' ),
		);
	}
}
