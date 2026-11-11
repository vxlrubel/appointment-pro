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
			'gateway_credentials_version' => 2,
			'stripe_sandbox'           => false,
			'stripe_sandbox_publishable' => '',
			'stripe_sandbox_secret' => '',
			'stripe_sandbox_webhook_secret' => '',
			'paypal_sandbox_client_id' => '',
			'paypal_sandbox_secret' => '',
			'paypal_sandbox_webhook_id' => '',
			'notification_customer_email' => '',
			'notification_customer_admin_copy' => false,
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
			array_merge( self::defaults(), self::migrate_credentials( is_array( $current ) ? $current : array() ) )
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
		$all    = array_merge( self::defaults(), self::migrate_credentials( is_array( $stored ) ? $stored : array() ) );

		if ( null === $key ) {
			return $all;
		}

		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/** Preserve the previously active keys when separating sandbox/live storage. */
	private static function migrate_credentials( array $stored ): array {
		if ( (int) ( $stored['gateway_credentials_version'] ?? 0 ) >= 2 ) { return $stored; }
		$stripe_test = isset( $stored['stripe_sandbox'] ) ? (bool) $stored['stripe_sandbox'] : ( str_starts_with( $stored['stripe_publishable'] ?? '', 'pk_test_' ) || str_starts_with( $stored['stripe_secret'] ?? '', 'sk_test_' ) );
		$stored['stripe_sandbox'] = $stripe_test;
		foreach ( array( 'stripe' => array( 'publishable', 'secret', 'webhook_secret' ), 'paypal' => array( 'client_id', 'secret', 'webhook_id' ) ) as $provider => $keys ) {
			$test = $provider === 'stripe' ? $stripe_test : (bool) ( $stored['paypal_sandbox'] ?? true );
			if ( ! $test ) { continue; }
			foreach ( $keys as $key ) {
				$legacy = $provider . '_' . $key; $sandbox = $provider . '_sandbox_' . $key;
				if ( ! array_key_exists( $sandbox, $stored ) ) { $stored[ $sandbox ] = $stored[ $legacy ] ?? ''; $stored[ $legacy ] = ''; }
			}
		}
		$stored['gateway_credentials_version'] = 2;
		return $stored;
	}

	/** Resolve credentials exclusively from the active payment environment. */
	public static function gateway( string $provider ): array {
		$all = self::get();
		$sandbox = (bool) $all[ $provider . '_sandbox' ];
		$prefix = $provider . ( $sandbox ? '_sandbox_' : '_' );
		$keys = $provider === 'stripe' ? array( 'publishable', 'secret', 'webhook_secret' ) : array( 'client_id', 'secret', 'webhook_id' );
		$result = array( 'sandbox' => $sandbox, 'environment' => $sandbox ? 'sandbox' : 'live' );
		foreach ( $keys as $key ) { $result[ $key ] = $all[ $prefix . $key ]; }
		return $result;
	}

	/**
	 * Save settings, merging with existing values.
	 *
	 * @param array<string, mixed> $data Settings to save.
	 * @return array<string, mixed>
	 */
	public static function save( array $data ): array {
		$merged = array_merge( self::get(), $data );
		$merged['notification_customer_email'] = sanitize_email( $merged['notification_customer_email'] );
		$merged['notification_customer_admin_copy'] = (bool) $merged['notification_customer_admin_copy'];

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
		$stripe = self::gateway( 'stripe' );
		$paypal = self::gateway( 'paypal' );

		return array(
			'company_name'        => $all['company_name'],
			'timezone'            => $all['timezone'],
			'today'               => ( new \DateTimeImmutable( 'now', Mailer::timezone() ) )->format( 'Y-m-d' ),
			'currency'            => $all['currency'],
			'slot_length'         => (int) $all['slot_length'],
			'stripe_sandbox' => $stripe['sandbox'],
			'paypal_sandbox' => $paypal['sandbox'],
			'local_enabled'       => (bool) $all['local_enabled'],
			'stripe_enabled'      => (bool) ( $all['stripe_enabled'] && $stripe['publishable'] && $stripe['secret'] ),
			'stripe_publishable'  => $all['stripe_enabled'] ? $stripe['publishable'] : '',
			'paypal_enabled'      => (bool) ( $all['paypal_enabled'] && $paypal['client_id'] && $paypal['secret'] ),
			'paypal_client_id'    => $all['paypal_enabled'] ? $paypal['client_id'] : '',
			'woocommerce_enabled' => (bool) $all['woocommerce_enabled'] && class_exists( 'WooCommerce' ),
		);
	}
}
