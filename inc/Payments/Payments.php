<?php
/**
 * Local payment handling.
 *
 * @package Praxleo_Appointment
 */

namespace Praxleo\Appointment\Payments;

use Throwable;
use WP_Error;
use Praxleo\Appointment\Database\Repository;
use Praxleo\Appointment\Domain\Appointments;
use Praxleo\Appointment\Domain\Mailer;
use Praxleo\Appointment\Domain\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Records payments for appointments.
 */
class Payments {

	/**
	 * Record a payment row.
	 *
	 * @param int                  $customer_appointment_id Customer appointment ID.
	 * @param string               $type Payment type.
	 * @param string               $status Payment status.
	 * @param float                $amount Amount.
	 * @param array<string, mixed> $details Extra details.
	 * @return int
	 */
	public static function record( int $customer_appointment_id, string $type, string $status, float $amount, array $details = array() ): int {
		if ( $details ) {
			foreach ( Repository::all( 'payments', array( 'customer_appointment_id' => $customer_appointment_id, 'type' => $type ) ) as $existing ) {
				if ( json_decode( $existing['details_json'] ?: '{}', true ) === $details ) { return (int) $existing['id']; }
			}
		}
		return Repository::insert(
			'payments',
			array(
				'customer_appointment_id' => $customer_appointment_id,
				'type'                    => $type,
				'status'                  => $status,
				'amount'                  => $amount,
				'currency'                => Settings::get( 'currency' ),
				'details_json'            => wp_json_encode( $details ),
				'created_at'              => Repository::now(),
			)
		);
	}

	/**
	 * Complete a local (offline) payment for an appointment.
	 *
	 * @param array<string, mixed> $hydrated Hydrated appointment.
	 * @param float                $amount Amount.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function complete_local( array $hydrated, float $amount ) {
		if ( empty( $hydrated['appointment']['id'] ) || empty( $hydrated['customer_appointment']['id'] ) ) {
			return new WP_Error( 'not_found', __( 'Appointment not found.', 'appointment-pro' ), array( 'status' => 404 ) );
		}

		if ( in_array( $hydrated['appointment']['status'], array( 'cancelled', 'rejected' ), true ) ) { return new WP_Error( 'invalid', 'This booking is cancelled.', array( 'status' => 400 ) ); }

		$customer_appointment_id = (int) $hydrated['customer_appointment']['id'];
		$appointment_id          = (int) $hydrated['appointment']['id'];

		try {
			$existing = Repository::all( 'payments', array( 'customer_appointment_id' => $customer_appointment_id ) );
			$already  = false;

			foreach ( $existing as $payment ) {
				if ( isset( $payment['status'] ) && 'completed' === $payment['status'] ) {
					$already = true;
					break;
				}
			}

			if ( ! $already ) {
				self::record( $customer_appointment_id, 'local', 'completed', $amount );
			}
		} catch ( Throwable $e ) {
			update_option( 'px_appointment_mail_error', gmdate( 'c' ) . ' payment record: ' . $e->getMessage() );
		}

		if ( ! empty( $already ) ) { return Appointments::hydrate( $appointment_id ); }

		$auto_approve = (bool) Settings::get( 'auto_approve', true );

		Appointments::set_status( $appointment_id, $auto_approve ? 'approved' : 'pending', false );

		$fresh = Appointments::hydrate( $appointment_id );

		if ( ! $fresh ) {
			$hydrated['amount'] = $amount;

			return $hydrated;
		}

		$fresh['amount'] = $amount;

		Mailer::send_event( 'booking_created_customer', $fresh );
		Mailer::send_event( 'booking_created_admin', $fresh );

		return $fresh;
	}

	public function boot() {
		add_action( 'woocommerce_payment_complete', array( $this, 'woo_complete' ) );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'woo_cancel' ) );
		add_action( 'woocommerce_order_status_failed', array( $this, 'woo_cancel' ) );
	}

	public static function mark_paid( $appointment_id, $type, $details = array() ) {
		$hydrated = \Praxleo\Appointment\Domain\Appointments::hydrate( $appointment_id );
		if ( ! $hydrated ) {
			return;
		}
		if ( in_array( $hydrated['appointment']['status'], array( 'cancelled', 'rejected' ), true ) ) { return; }
		$ca_id = $hydrated['customer_appointment']['id'];
		$existing = \Praxleo\Appointment\Database\Repository::all( 'payments', array( 'customer_appointment_id' => $ca_id ) );
		foreach ( $existing as $payment ) { if ( $payment['status'] === 'completed' ) { return; } }
		$existing = array_values( array_filter( $existing, static function( $payment ) use ( $type, $details ) {
			if ( $payment['type'] !== $type ) { return false; }
			$stored = json_decode( $payment['details_json'] ?: '{}', true ) ?: array();
			foreach ( array( 'payment_intent', 'order_id' ) as $key ) { if ( isset( $details[ $key ] ) && (string) ( $stored[ $key ] ?? '' ) !== (string) $details[ $key ] ) { return false; } }
			return true;
		} ) );
		if ( $existing ) {
			\Praxleo\Appointment\Database\Repository::update(
				'payments',
				array(
					'status'       => 'completed',
					'details_json' => wp_json_encode( array_merge( json_decode( $existing[0]['details_json'] ?: '{}', true ) ?: array(), $details ) ),
				),
				array( 'id' => $existing[0]['id'] )
			);
		} else {
			self::record( $ca_id, $type, 'completed', $hydrated['amount'] ?? 0, $details );
		}
		$auto = \Praxleo\Appointment\Domain\Settings::get( 'auto_approve', true );
		\Praxleo\Appointment\Domain\Appointments::set_status( $appointment_id, $auto ? 'approved' : 'pending', false );
		$hydrated = Appointments::hydrate( $appointment_id );
		\Praxleo\Appointment\Domain\Mailer::send_event( 'booking_created_customer', $hydrated );
		\Praxleo\Appointment\Domain\Mailer::send_event( 'booking_created_admin', $hydrated );
		if ( $auto ) {
			\Praxleo\Appointment\Domain\Mailer::send_event( 'booking_approved_customer', $hydrated );
		}
	}

	public static function stripe_intent( $hydrated, $amount ) {
		$secret = \Praxleo\Appointment\Domain\Settings::get( 'stripe_secret' );
		if ( ! $secret ) {
			return new WP_Error( 'stripe', __( 'Stripe is not configured.', 'appointment-pro' ), array( 'status' => 400 ) );
		}
		$cents = (int) round( $amount * 100 );
		$body  = http_build_query(
			array(
				'amount'      => $cents,
				'currency'    => strtolower( \Praxleo\Appointment\Domain\Settings::get( 'currency' ) ),
				'metadata[appointment_id]' => $hydrated['appointment']['id'],
			)
		);
		$res = wp_remote_post(
			'https://api.stripe.com/v1/payment_intents',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $secret,
					'Idempotency-Key' => 'px-appointment-' . $hydrated['customer_appointment']['id'],
				),
				'body'    => $body,
				'timeout' => 20,
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( empty( $data['client_secret'] ) ) {
			return new WP_Error( 'stripe', $data['error']['message'] ?? 'Stripe error', array( 'status' => 400 ) );
		}
		self::record(
			$hydrated['customer_appointment']['id'],
			'stripe',
			'pending',
			$amount,
			array( 'payment_intent' => $data['id'] )
		);
		return array(
			'client_secret' => $data['client_secret'],
			'id'            => $data['id'],
		);
	}

	public static function handle_stripe_webhook( $payload, $sig ) {
		$secret = Settings::get( 'stripe_webhook_secret' );
		$parts = array();
		foreach ( explode( ',', (string) $sig ) as $part ) { $pair = explode( '=', $part, 2 ); if ( count( $pair ) === 2 ) { $parts[ $pair[0] ][] = $pair[1]; } }
		$timestamp = (int) ( $parts['t'][0] ?? 0 );
		$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, (string) $secret );
		if ( ! $secret || abs( time() - $timestamp ) > 300 || ! array_filter( $parts['v1'] ?? array(), static fn( $v ) => hash_equals( $expected, $v ) ) ) { return new WP_Error( 'signature', 'Invalid Stripe signature.', array( 'status' => 400 ) ); }
		$data = json_decode( $payload, true );
		if ( ! $data ) {
			return new WP_Error( 'bad', 'Invalid payload', array( 'status' => 400 ) );
		}
		$type = $data['type'] ?? '';
		$obj  = $data['data']['object'] ?? array();
		$appt = (int) ( $obj['metadata']['appointment_id'] ?? 0 );
		if ( ! $appt ) {
			return true;
		}
		$hydrated = Appointments::hydrate( $appt );
		$matched = false;
		foreach ( $hydrated['payments'] ?? array() as $payment ) {
			$details = json_decode( $payment['details_json'] ?: '{}', true );
			if ( $payment['type'] === 'stripe' && ( $details['payment_intent'] ?? '' ) === ( $obj['id'] ?? '' ) && (int) round( (float) $payment['amount'] * 100 ) === (int) ( $obj['amount'] ?? -1 ) && strtolower( $payment['currency'] ) === strtolower( $obj['currency'] ?? '' ) ) { $matched = true; break; }
		}
		if ( ! $matched ) { return new WP_Error( 'invalid', 'Unknown payment or amount mismatch.', array( 'status' => 400 ) ); }
		if ( 'payment_intent.succeeded' === $type ) {
			self::mark_paid( $appt, 'stripe', array( 'payment_intent' => $obj['id'] ?? '' ) );
		}
		if ( 'payment_intent.payment_failed' === $type ) {
			$hydrated = \Praxleo\Appointment\Domain\Appointments::hydrate( $appt );
			if ( $hydrated ) {
				$pays = $hydrated['payments'];
				if ( $pays ) {
					\Praxleo\Appointment\Database\Repository::update( 'payments', array( 'status' => 'failed' ), array( 'id' => $pays[0]['id'] ) );
				}
			}
		}
		return true;
	}

	public static function paypal_order( $hydrated, $amount ) {
		$token = self::paypal_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$base = \Praxleo\Appointment\Domain\Settings::get( 'paypal_sandbox', true )
			? 'https://api-m.sandbox.paypal.com'
			: 'https://api-m.paypal.com';
		$res  = wp_remote_post(
			$base . '/v2/checkout/orders',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
					'PayPal-Request-Id' => 'px-appointment-' . $hydrated['customer_appointment']['id'],
				),
				'body'    => wp_json_encode(
					array(
						'intent'         => 'CAPTURE',
						'purchase_units' => array(
							array(
								'amount'   => array(
									'currency_code' => \Praxleo\Appointment\Domain\Settings::get( 'currency' ),
									'value'         => number_format( $amount, 2, '.', '' ),
								),
								'custom_id' => (string) $hydrated['appointment']['id'],
							),
						),
					)
				),
				'timeout' => 20,
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( empty( $data['id'] ) ) {
			return new WP_Error( 'paypal', 'PayPal order failed', array( 'status' => 400 ) );
		}
		self::record(
			$hydrated['customer_appointment']['id'],
			'paypal',
			'pending',
			$amount,
			array( 'order_id' => $data['id'] )
		);
		return $data;
	}

	public static function paypal_capture( $order_id ) {
		$token = self::paypal_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$base = \Praxleo\Appointment\Domain\Settings::get( 'paypal_sandbox', true )
			? 'https://api-m.sandbox.paypal.com'
			: 'https://api-m.paypal.com';
		$res  = wp_remote_post(
			$base . '/v2/checkout/orders/' . rawurlencode( $order_id ) . '/capture',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'timeout' => 20,
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		$custom = $data['purchase_units'][0]['payments']['captures'][0]['custom_id']
			?? $data['purchase_units'][0]['custom_id']
			?? 0;
		if ( ( $data['status'] ?? '' ) !== 'COMPLETED' || ( $data['purchase_units'][0]['payments']['captures'][0]['status'] ?? '' ) !== 'COMPLETED' ) { return new WP_Error( 'paypal', 'Payment was not completed.', array( 'status' => 400 ) ); }
		if ( $custom ) { self::mark_paid( (int) $custom, 'paypal', array( 'order_id' => $order_id ) ); }
		return $data;
	}

	private static function paypal_token() {
		$id     = \Praxleo\Appointment\Domain\Settings::get( 'paypal_client_id' );
		$secret = \Praxleo\Appointment\Domain\Settings::get( 'paypal_secret' );
		if ( ! $id || ! $secret ) {
			return new WP_Error( 'paypal', __( 'PayPal is not configured.', 'appointment-pro' ), array( 'status' => 400 ) );
		}
		$base = \Praxleo\Appointment\Domain\Settings::get( 'paypal_sandbox', true )
			? 'https://api-m.sandbox.paypal.com'
			: 'https://api-m.paypal.com';
		$res  = wp_remote_post(
			$base . '/v1/oauth2/token',
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $id . ':' . $secret ),
				),
				'body'    => array( 'grant_type' => 'client_credentials' ),
				'timeout' => 20,
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( empty( $data['access_token'] ) ) {
			return new WP_Error( 'paypal', 'PayPal auth failed', array( 'status' => 400 ) );
		}
		return $data['access_token'];
	}

	public static function handle_paypal_webhook( $payload, $headers = array() ) {
		$data = json_decode( $payload, true );
		if ( ! $data ) {
			return new WP_Error( 'bad', 'Invalid payload', array( 'status' => 400 ) );
		}
		$webhook_id = Settings::get( 'paypal_webhook_id' );
		$token = self::paypal_token();
		if ( ! $webhook_id || is_wp_error( $token ) ) { return new WP_Error( 'signature', 'PayPal webhook verification is not configured.', array( 'status' => 400 ) ); }
		$verification = array( 'webhook_id' => $webhook_id, 'webhook_event' => $data );
		foreach ( array( 'auth_algo', 'cert_url', 'transmission_id', 'transmission_sig', 'transmission_time' ) as $key ) { $verification[ $key ] = $headers[ $key ] ?? ''; }
		$base = Settings::get( 'paypal_sandbox', true ) ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
		$res = wp_remote_post( $base . '/v1/notifications/verify-webhook-signature', array( 'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( $verification ), 'timeout' => 20 ) );
		if ( is_wp_error( $res ) || ( json_decode( wp_remote_retrieve_body( $res ), true )['verification_status'] ?? '' ) !== 'SUCCESS' ) { return new WP_Error( 'signature', 'Invalid PayPal signature.', array( 'status' => 400 ) ); }
		$event = $data['event_type'] ?? '';
		if ( 'PAYMENT.CAPTURE.COMPLETED' === $event ) {
			$custom = $data['resource']['custom_id'] ?? 0;
			if ( $custom ) {
				self::mark_paid( (int) $custom, 'paypal', $data['resource'] );
			}
		}
		return true;
	}

	public static function woo_order( $hydrated, $amount ) {
		if ( ! function_exists( 'wc_create_order' ) ) {
			return new WP_Error( 'woo', __( 'WooCommerce is not active.', 'appointment-pro' ), array( 'status' => 400 ) );
		}
		$order = wc_create_order();
		$order->add_product( self::woo_product(), 1, array( 'subtotal' => $amount, 'total' => $amount ) );
		$order->set_total( $amount );
		$order->update_meta_data( '_px_appointment_id', $hydrated['appointment']['id'] );
		$order->save();
		self::record(
			$hydrated['customer_appointment']['id'],
			'woocommerce',
			'pending',
			$amount,
			array( 'order_id' => $order->get_id() )
		);
		return array(
			'order_id'     => $order->get_id(),
			'checkout_url' => $order->get_checkout_payment_url(),
		);
	}

	private static function woo_product() {
		$id = (int) get_option( 'px_appointment_woo_product', 0 );
		if ( $id && get_post( $id ) ) {
			return wc_get_product( $id );
		}
		$post_id = wp_insert_post(
			array(
				'post_title'  => 'Praxleo Booking',
				'post_status' => 'private',
				'post_type'   => 'product',
			)
		);
		update_post_meta( $post_id, '_virtual', 'yes' );
		update_post_meta( $post_id, '_price', 0 );
		update_option( 'px_appointment_woo_product', $post_id );
		return wc_get_product( $post_id );
	}

	public function woo_complete( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$appt = (int) $order->get_meta( '_px_appointment_id' );
		if ( $appt ) {
			self::mark_paid( $appt, 'woocommerce', array( 'order_id' => $order_id ) );
		}
	}

	public function woo_cancel( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$appt = (int) $order->get_meta( '_px_appointment_id' );
		if ( $appt ) {
			\Praxleo\Appointment\Domain\Appointments::set_status( $appt, 'cancelled' );
		}
	}
}
