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
}
