<?php
/**
 * Appointment domain logic.
 *
 * @package Praxleo_Appointment
 */

namespace Praxleo\Appointment\Domain;

use DateTimeImmutable;
use DateTimeZone;
use WP_Error;
use Praxleo\Appointment\Database\Repository;
use Praxleo\Appointment\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Appointment CRUD and status handling.
 */
class Appointments {

	/**
	 * Create an appointment.
	 *
	 * @param array<string, mixed> $payload Appointment payload.
	 * @param bool                 $is_admin Whether the request comes from an administrator.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function create( array $payload, bool $is_admin = false ) {
		global $wpdb;

		$key = sanitize_text_field( $payload['idempotency_key'] ?? '' );

		if ( $key ) {
			$existing = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT * FROM ' . Schema::table( 'appointments' ) . ' WHERE idempotency_key = %s',
					$key
				),
				ARRAY_A
			);

			if ( $existing ) {
				return self::hydrate( (int) $existing['id'] );
			}
		}

		$location_id = (int) ( $payload['location_id'] ?? 0 );
		$service_id  = (int) ( $payload['service_id'] ?? 0 );
		$staff_id    = (int) ( $payload['staff_id'] ?? 0 );
		$start       = sanitize_text_field( $payload['start'] ?? '' );
		$extras      = isset( $payload['extras'] ) && is_array( $payload['extras'] ) ? $payload['extras'] : array();

		$service = Repository::find( 'services', $service_id );
		$staff   = Repository::find( 'staff', $staff_id );
		$location = Repository::find( 'locations', $location_id );

		if ( ! $service || ! $staff || ! $location ) {
			return new WP_Error( 'invalid', __( 'Invalid location, service, or staff.', 'appointment-pro' ), array( 'status' => 400 ) );
		}

		if ( ! $is_admin && ( empty( $service['enabled'] ) || empty( $staff['enabled'] ) || empty( $location['enabled'] ) || ! Availability::candidate_staff( $location_id, $service_id, $staff_id ) ) ) { return new WP_Error( 'invalid', 'This service is unavailable.', array( 'status' => 400 ) ); }

		$duration   = (int) $service['duration_sec'];
		$price      = self::unit_price( $staff_id, $service_id, $location_id, $service );
		$extra_rows = array();

		foreach ( $extras as $extra ) {
			$row = Repository::find( 'extras', (int) ( $extra['id'] ?? 0 ) );

			if ( ! $row || (int) $row['service_id'] !== $service_id ) {
				return new WP_Error( 'invalid', __( 'Invalid extra.', 'appointment-pro' ), array( 'status' => 400 ) );
			}

			if ( empty( $row['enabled'] ) ) { return new WP_Error( 'invalid', 'This extra is unavailable.', array( 'status' => 400 ) ); }
			$quantity   = max( 1, min( (int) $row['max_quantity'], (int) ( $extra['qty'] ?? 1 ) ) );
			$duration  += (int) $row['duration_sec'] * $quantity;
			$price     += (float) $row['price'] * $quantity;
			$extra_rows[] = array(
				'id'    => (int) $row['id'],
				'title' => $row['title'],
				'qty'   => $quantity,
				'price' => (float) $row['price'],
			);
		}

		$start_dt = new DateTimeImmutable( $start, new DateTimeZone( 'UTC' ) );
		$end_dt   = $start_dt->modify( "+{$duration} seconds" );
		$start_s  = $start_dt->format( 'Y-m-d H:i:s' );
		$end_s    = $end_dt->format( 'Y-m-d H:i:s' );

		if ( ! $is_admin ) {
			$date = $start_dt->setTimezone( new DateTimeZone( Settings::get( 'timezone', 'UTC' ) ) )->format( 'Y-m-d' );
			$slots = Availability::slots( array( 'location_id' => $location_id, 'service_id' => $service_id, 'staff_id' => $staff_id, 'date' => $date, 'extras' => $extras ) );
			if ( ! array_filter( $slots, static fn( $slot ) => $slot['start'] === $start_s ) ) { return new WP_Error( 'conflict', 'This time is no longer available.', array( 'status' => 409 ) ); }
		}
		$coupon_id = null;
		$discount  = 0;

		if ( ! empty( $payload['coupon'] ) ) {
			$valid = Coupons::validate( (string) $payload['coupon'], $service_id, $price );

			if ( is_wp_error( $valid ) ) {
				return $valid;
			}

			$coupon_id = (int) $valid['coupon']['id'];
			$discount  = $valid['discount'];
			$price     = $valid['total'];
		}

		foreach ( ( $is_admin ? array() : Repository::all( 'custom_fields', array( 'enabled' => 1 ) ) ) as $field ) {
			$services = json_decode( $field['services_json'] ?: '[]', true ) ?: array();
			if ( $services && ! in_array( $service_id, array_map( 'intval', $services ), true ) ) { continue; }
			$value = $payload['custom_fields'][ $field['id'] ] ?? '';
			if ( ! empty( $field['required'] ) && ( $value === '' || $value === null || $value === false ) ) { return new WP_Error( 'invalid', 'Please complete: ' . $field['label'], array( 'status' => 400 ) ); }
			if ( $value !== '' && $field['type'] === 'select' && ! in_array( $value, json_decode( $field['options_json'] ?: '[]', true ) ?: array(), true ) ) { return new WP_Error( 'invalid', 'Invalid selection: ' . $field['label'], array( 'status' => 400 ) ); }
		}
		$customer_id = Customers::upsert(
			is_array( $payload['customer'] ?? null ) ? $payload['customer'] : array(),
			! empty( $payload['create_account'] )
		);

		if ( is_wp_error( $customer_id ) ) {
			return $customer_id;
		}

		$ttl  = (int) Settings::get( 'hold_ttl', 600 );
		$now  = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		$hold = $now->modify( "+{$ttl} seconds" )->format( 'Y-m-d H:i:s' );

		$wpdb->query( 'START TRANSACTION' );

		if ( Availability::has_conflict( $staff_id, $start_s, $end_s ) ) {
			$wpdb->query( 'ROLLBACK' );

			return new WP_Error( 'conflict', __( 'This time is no longer available.', 'appointment-pro' ), array( 'status' => 409 ) );
		}

		$appointment_id = Repository::insert(
			'appointments',
			array(
				'location_id'     => $location_id,
				'service_id'      => $service_id,
				'staff_id'        => $staff_id,
				'start_datetime'  => $start_s,
				'end_datetime'    => $end_s,
				'status'          => 'pending',
				'hold_until'      => $hold,
				'idempotency_key' => $key ?: null,
				'created_at'      => $now->format( 'Y-m-d H:i:s' ),
				'updated_at'      => $now->format( 'Y-m-d H:i:s' ),
			)
		);

		if ( ! $appointment_id ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'create_failed', 'Could not create appointment.', array( 'status' => 500 ) ); }

		$customer_appointment_id = Repository::insert(
			'customer_appointments',
			array(
				'appointment_id'     => $appointment_id,
				'customer_id'        => $customer_id,
				'number_of_persons'  => 1,
				'amount'             => $price,
				'extras_json'        => wp_json_encode( $extra_rows ),
				'custom_fields_json' => wp_json_encode( $payload['custom_fields'] ?? array() ),
				'coupon_id'          => $coupon_id,
				'notes'              => sanitize_textarea_field( $payload['notes'] ?? '' ),
				'token'              => bin2hex( random_bytes( 16 ) ),
				'reminder_sent_at'   => null,
			)
		);

		if ( ! $customer_appointment_id ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'create_failed', 'Could not save booking details.', array( 'status' => 500 ) ); }
		$wpdb->query( 'COMMIT' );

		if ( $coupon_id ) {
			Coupons::increment( $coupon_id );
		}

		$hydrated = self::hydrate( $appointment_id );

		if ( ! $hydrated ) {
			return new WP_Error( 'create_failed', __( 'Could not create the appointment.', 'appointment-pro' ), array( 'status' => 500 ) );
		}

		$hydrated['amount']                  = $price;
		$hydrated['customer_appointment_id'] = $customer_appointment_id;

		return $hydrated;
	}

	/**
	 * Resolve the price for a staff/service/location combination.
	 *
	 * @param int                  $staff_id Staff ID.
	 * @param int                  $service_id Service ID.
	 * @param int                  $location_id Location ID.
	 * @param array<string, mixed> $service Service row.
	 * @return float
	 */
	public static function unit_price( int $staff_id, int $service_id, int $location_id, array $service ): float {
		global $wpdb;

		$table    = Schema::table( 'staff_services' );
		$override = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT price FROM $table WHERE staff_id = %d AND service_id = %d AND location_id = %d",
				$staff_id,
				$service_id,
				$location_id
			)
		);

		if ( null !== $override && '' !== $override ) {
			return (float) $override;
		}

		return (float) $service['price'];
	}

	/**
	 * Hydrate an appointment with related records.
	 *
	 * @param int $id Appointment ID.
	 * @return array<string, mixed>|null
	 */
	public static function hydrate( int $id ): ?array {
		$appointment = Repository::find( 'appointments', $id );

		if ( ! $appointment ) {
			return null;
		}

		$customer_appointments = Repository::all( 'customer_appointments', array( 'appointment_id' => $id ) );
		$customer_appointment  = $customer_appointments ? $customer_appointments[0] : null;
		$customer              = $customer_appointment ? Repository::find( 'customers', (int) $customer_appointment['customer_id'] ) : null;
		$payments              = $customer_appointment ? Repository::all( 'payments', array( 'customer_appointment_id' => (int) $customer_appointment['id'] ) ) : array();

		return array(
			'appointment'          => $appointment,
			'customer_appointment' => $customer_appointment,
			'customer'             => $customer,
			'service'              => Repository::find( 'services', (int) $appointment['service_id'] ),
			'staff'                => Repository::find( 'staff', (int) $appointment['staff_id'] ),
			'location'             => Repository::find( 'locations', (int) $appointment['location_id'] ),
			'payments'             => $payments,
			'amount'               => (float) ( $customer_appointment['amount'] ?? ( $payments[0]['amount'] ?? self::unit_price( (int) $appointment['staff_id'], (int) $appointment['service_id'], (int) $appointment['location_id'], Repository::find( 'services', (int) $appointment['service_id'] ) ?: array( 'price' => 0 ) ) ) ),
		);
	}

	/**
	 * Change an appointment status.
	 *
	 * @param int    $id Appointment ID.
	 * @param string $status New status.
	 * @param bool   $notify Whether to send notification e-mails.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function set_status( int $id, string $status, bool $notify = true ) {
		$allowed = array( 'pending', 'approved', 'cancelled', 'rejected', 'done' );

		if ( ! in_array( $status, $allowed, true ) ) {
			return new WP_Error( 'invalid', __( 'Invalid status.', 'appointment-pro' ), array( 'status' => 400 ) );
		}

		Repository::update(
			'appointments',
			array(
				'status'     => $status,
				'hold_until' => null,
				'updated_at' => Repository::now(),
			),
			array( 'id' => $id )
		);

		$hydrated = self::hydrate( $id );

		if ( ! $hydrated ) {
			return new WP_Error( 'not_found', __( 'Appointment not found.', 'appointment-pro' ), array( 'status' => 404 ) );
		}

		if ( $notify && 'approved' === $status ) {
			Mailer::send_event( 'booking_approved_customer', $hydrated );
		}

		if ( $notify && ( 'cancelled' === $status || 'rejected' === $status ) ) {
			Mailer::send_event( 'booking_cancelled_customer', $hydrated );
			Mailer::send_event( 'booking_cancelled_admin', $hydrated );
		}

		return $hydrated;
	}

	/**
	 * Reschedule an appointment.
	 *
	 * @param int    $id Appointment ID.
	 * @param string $start New start time (UTC MySQL).
	 * @param int    $staff_id Optional staff ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function reschedule( int $id, string $start, int $staff_id = 0 ) {
		$appointment = Repository::find( 'appointments', $id );

		if ( ! $appointment ) {
			return new WP_Error( 'not_found', __( 'Appointment not found.', 'appointment-pro' ), array( 'status' => 404 ) );
		}

		$staff_id = $staff_id ?: (int) $appointment['staff_id'];
		$duration = ( new DateTimeImmutable( $appointment['end_datetime'] ) )->getTimestamp() - ( new DateTimeImmutable( $appointment['start_datetime'] ) )->getTimestamp();
		$start_dt = new DateTimeImmutable( $start, new DateTimeZone( 'UTC' ) );
		$end_s    = $start_dt->modify( "+{$duration} seconds" )->format( 'Y-m-d H:i:s' );
		$start_s  = $start_dt->format( 'Y-m-d H:i:s' );

		if ( Availability::has_conflict( $staff_id, $start_s, $end_s, $id ) ) {
			return new WP_Error( 'conflict', __( 'This time is no longer available.', 'appointment-pro' ), array( 'status' => 409 ) );
		}

		Repository::update(
			'appointments',
			array(
				'staff_id'       => $staff_id,
				'start_datetime' => $start_s,
				'end_datetime'   => $end_s,
				'updated_at'     => Repository::now(),
			),
			array( 'id' => $id )
		);

		return self::hydrate( $id );
	}

	/**
	 * Find an appointment by its customer token.
	 *
	 * @param string $token Customer token.
	 * @return array<string, mixed>|null
	 */
	public static function by_token( string $token ): ?array {
		global $wpdb;

		$table = Schema::table( 'customer_appointments' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE token = %s", sanitize_text_field( $token ) ), ARRAY_A );

		if ( ! $row ) {
			return null;
		}

		return self::hydrate( (int) $row['appointment_id'] );
	}

	/**
	 * Expire unpaid appointment holds.
	 *
	 * @return void
	 */
	public static function expire_holds(): void {
		global $wpdb;

		$appointments = Schema::table( 'appointments' );
		$payments     = Schema::table( 'payments' );
		$customers    = Schema::table( 'customer_appointments' );
		$now          = Repository::now();

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT a.id FROM $appointments a
				LEFT JOIN $payments p
				ON p.customer_appointment_id = (SELECT id FROM $customers WHERE appointment_id = a.id LIMIT 1)
				WHERE a.status = 'pending' AND a.hold_until IS NOT NULL AND a.hold_until < %s
				AND (p.id IS NULL OR p.status != 'completed')",
				$now
			)
		);

		foreach ( (array) $ids as $id ) {
			Repository::update(
				'appointments',
				array(
					'status'     => 'cancelled',
					'updated_at' => $now,
				),
				array( 'id' => (int) $id )
			);
		}
	}

	/**
	 * List appointments with optional filters.
	 *
	 * @param array<string, mixed> $args Filters: from, to, staff_id, status.
	 * @return array<int, array<string, mixed>>
	 */
	public static function list( array $args = array() ): array {
		global $wpdb;

		$table  = Schema::table( 'appointments' );
		$sql    = "SELECT * FROM $table WHERE 1=1";
		$params = array();

		if ( ! empty( $args['from'] ) ) {
			$sql     .= ' AND end_datetime >= %s';
			$params[] = $args['from'];
		}

		if ( ! empty( $args['to'] ) ) {
			$sql     .= ' AND start_datetime <= %s';
			$params[] = $args['to'];
		}

		if ( ! empty( $args['staff_id'] ) ) {
			$sql     .= ' AND staff_id = %d';
			$params[] = (int) $args['staff_id'];
		}

		if ( ! empty( $args['status'] ) ) {
			$sql     .= ' AND status = %s';
			$params[] = $args['status'];
		}

		$sql .= ' ORDER BY start_datetime ASC LIMIT 500';

		$rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );
		$out  = array();

		foreach ( (array) $rows as $row ) {
			$hydrated = self::hydrate( (int) $row['id'] );

			if ( $hydrated ) {
				$out[] = $hydrated;
			}
		}

		return $out;
	}

	public static function delete( $id ) {
		$id = (int) $id;
		if ( ! $id ) {
			return;
		}
		$cas = \Praxleo\Appointment\Database\Repository::all( 'customer_appointments', array( 'appointment_id' => $id ) );
		foreach ( $cas as $ca ) {
			\Praxleo\Appointment\Database\Repository::delete( 'payments', array( 'customer_appointment_id' => $ca['id'] ) );
			\Praxleo\Appointment\Database\Repository::delete( 'customer_appointments', array( 'id' => $ca['id'] ) );
		}
		\Praxleo\Appointment\Database\Repository::delete( 'appointments', array( 'id' => $id ) );
	}
}
