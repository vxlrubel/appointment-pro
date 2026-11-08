<?php
/**
 * Availability calculations.
 *
 * @package Praxleo_Appointment
 */

namespace Praxleo\Appointment\Domain;

use DateTimeImmutable;
use DateTimeZone;
use Praxleo\Appointment\Database\Repository;
use Praxleo\Appointment\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Calculates available booking slots.
 */
class Availability {

	/**
	 * Return available slots for a service and date.
	 *
	 * @param array<string, mixed> $args location_id, service_id, staff_id, date, extras.
	 * @return array<int, array<string, mixed>>
	 */
	public static function slots( array $args ): array {
		$location_id = (int) ( $args['location_id'] ?? 0 );
		$service_id  = (int) ( $args['service_id'] ?? 0 );
		$staff_id    = (int) ( $args['staff_id'] ?? 0 );
		$date        = sanitize_text_field( $args['date'] ?? '' );
		$extras      = isset( $args['extras'] ) && is_array( $args['extras'] ) ? $args['extras'] : array();

		$service = Repository::find( 'services', $service_id );

		if ( ! $service || ! $service['enabled'] ) {
			return array();
		}

		$duration = (int) $service['duration_sec'];

		foreach ( $extras as $extra ) {
			$row = Repository::find( 'extras', (int) ( $extra['id'] ?? 0 ) );

			if ( $row && (int) $row['service_id'] === $service_id ) {
				$quantity  = max( 1, (int) ( $extra['qty'] ?? 1 ) );
				$duration += (int) $row['duration_sec'] * $quantity;
			}
		}

		$staff_rows = self::candidate_staff( $location_id, $service_id, $staff_id );
		$slot_len   = max( 5, (int) Settings::get( 'slot_length', 15 ) ) * 60;
		$timezone   = new DateTimeZone( Settings::get( 'timezone', 'UTC' ) );
		$day        = DateTimeImmutable::createFromFormat( 'Y-m-d', $date, $timezone );

		if ( ! $day ) {
			return array();
		}

		$min_lead = (int) Settings::get( 'min_lead_hours', 1 );
		$max_lead = (int) Settings::get( 'max_lead_days', 60 );
		$now      = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		$min_at   = $now->modify( "+{$min_lead} hours" );
		$max_at   = $now->modify( "+{$max_lead} days" );

		$out = array();

		foreach ( $staff_rows as $staff ) {
			foreach ( self::staff_day_slots( $staff, $service, $location_id, $day, $duration, $slot_len, $timezone ) as $slot ) {
				$start = new DateTimeImmutable( $slot['start'], new DateTimeZone( 'UTC' ) );

				if ( $start < $min_at || $start > $max_at ) {
					continue;
				}

				$out[] = $slot;
			}
		}

		usort(
			$out,
			static function ( $a, $b ) {
				return strcmp( $a['start'], $b['start'] );
			}
		);

		return $out;
	}

	/**
	 * Return staff members that can perform a service at a location.
	 *
	 * @param int $location_id Location ID.
	 * @param int $service_id Service ID.
	 * @param int $staff_id Optional staff filter.
	 * @return array<int, array<string, mixed>>
	 */
	public static function candidate_staff( int $location_id, int $service_id, int $staff_id = 0 ): array {
		global $wpdb;

		$staff_services = Schema::table( 'staff_services' );
		$staff_table    = Schema::table( 'staff' );

		$sql = "SELECT s.*, ss.price AS override_price FROM $staff_table s
			INNER JOIN $staff_services ss ON ss.staff_id = s.id
			WHERE s.enabled = 1 AND ss.enabled = 1 AND ss.service_id = %d AND ss.location_id = %d";

		$params = array( $service_id, $location_id );

		if ( $staff_id ) {
			$sql     .= ' AND s.id = %d';
			$params[] = $staff_id;
		}

		$sql .= ' ORDER BY s.position ASC, s.id ASC';

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Build slots for one staff member on one day.
	 *
	 * @param array<string, mixed> $staff Staff row.
	 * @param array<string, mixed> $service Service row.
	 * @param int                  $location_id Location ID.
	 * @param DateTimeImmutable    $day Day in the configured timezone.
	 * @param int                  $duration Appointment duration in seconds.
	 * @param int                  $slot_len Slot interval in seconds.
	 * @param DateTimeZone         $timezone Configured timezone.
	 * @return array<int, array<string, mixed>>
	 */
	private static function staff_day_slots( array $staff, array $service, int $location_id, DateTimeImmutable $day, int $duration, int $slot_len, DateTimeZone $timezone ): array {
		$weekday   = (int) $day->format( 'w' );
		$schedules = Repository::all(
			'staff_schedule',
			array(
				'staff_id' => (int) $staff['id'],
				'weekday'  => $weekday,
			)
		);

		$schedules = array_values(
			array_filter(
				$schedules,
				static function ( $row ) use ( $location_id ) {
					return empty( $row['location_id'] ) || (int) $row['location_id'] === $location_id;
				}
			)
		);

		if ( ! $schedules ) {
			return array();
		}

		$busy = self::busy_intervals( (int) $staff['id'], $day, $service, $timezone );
		$busy = array_merge( $busy, self::days_off( (int) $staff['id'], $day, $timezone ) );

		$slots = array();

		foreach ( $schedules as $schedule ) {
			$start   = self::combine( $day, $schedule['start_time'], $timezone );
			$end     = self::combine( $day, $schedule['end_time'], $timezone );
			$breaks  = Repository::all( 'staff_breaks', array( 'schedule_id' => (int) $schedule['id'] ) );
			$breaks_iv = array();

			foreach ( $breaks as $break ) {
				$breaks_iv[] = array(
					self::combine( $day, $break['start_time'], $timezone ),
					self::combine( $day, $break['end_time'], $timezone ),
				);
			}

			$cursor = $start;

			while ( $cursor->getTimestamp() + $duration <= $end->getTimestamp() ) {
				$slot_end = $cursor->modify( "+{$duration} seconds" );

				if ( ! self::overlaps_any( $cursor, $slot_end, $busy ) && ! self::overlaps_any( $cursor, $slot_end, $breaks_iv ) ) {
					$slots[] = array(
						'start'    => $cursor->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
						'end'      => $slot_end->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
						'staff_id' => (int) $staff['id'],
						'display'  => $cursor->setTimezone( $timezone )->format( 'H:i' ),
					);
				}

				$cursor = $cursor->modify( "+{$slot_len} seconds" );
			}
		}

		return $slots;
	}

	/**
	 * Busy intervals from existing appointments.
	 *
	 * @param int                  $staff_id Staff ID.
	 * @param DateTimeImmutable    $day Day.
	 * @param array<string, mixed> $service Service row.
	 * @param DateTimeZone         $timezone Configured timezone.
	 * @return array<int, array{0: DateTimeImmutable, 1: DateTimeImmutable}>
	 */
	private static function busy_intervals( int $staff_id, DateTimeImmutable $day, array $service, DateTimeZone $timezone ): array {
		global $wpdb;

		$table = Schema::table( 'appointments' );
		$start = $day->setTime( 0, 0 )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		$end   = $day->modify( '+1 day' )->setTime( 0, 0 )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		$now   = Repository::now();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT start_datetime, end_datetime FROM $table
				WHERE staff_id = %d
				AND start_datetime < %s AND end_datetime > %s
				AND (status IN ('approved','done') OR (status = 'pending' AND (hold_until IS NULL OR hold_until > %s)))",
				$staff_id,
				$end,
				$start,
				$now
			),
			ARRAY_A
		);

		$padding_left  = (int) $service['padding_left_sec'];
		$padding_right = (int) $service['padding_right_sec'];
		$out           = array();

		foreach ( (array) $rows as $row ) {
			$from = new DateTimeImmutable( $row['start_datetime'], new DateTimeZone( 'UTC' ) );
			$to   = new DateTimeImmutable( $row['end_datetime'], new DateTimeZone( 'UTC' ) );

			if ( $padding_left ) {
				$from = $from->modify( "-{$padding_left} seconds" );
			}

			if ( $padding_right ) {
				$to = $to->modify( "+{$padding_right} seconds" );
			}

			$out[] = array( $from, $to );
		}

		return $out;
	}

	/**
	 * Return day-off intervals for a staff member.
	 *
	 * @param int               $staff_id Staff ID.
	 * @param DateTimeImmutable $day Day.
	 * @param DateTimeZone      $timezone Configured timezone.
	 * @return array<int, array{0: DateTimeImmutable, 1: DateTimeImmutable}>
	 */
	private static function days_off( int $staff_id, DateTimeImmutable $day, DateTimeZone $timezone ): array {
		global $wpdb;

		$table = Schema::table( 'staff_days_off' );
		$start = $day->setTime( 0, 0 )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		$end   = $day->modify( '+1 day' )->setTime( 0, 0 )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT start_datetime, end_datetime FROM $table WHERE staff_id = %d AND start_datetime < %s AND end_datetime > %s",
				$staff_id,
				$end,
				$start
			),
			ARRAY_A
		);

		$out = array();

		foreach ( (array) $rows as $row ) {
			$out[] = array(
				new DateTimeImmutable( $row['start_datetime'], new DateTimeZone( 'UTC' ) ),
				new DateTimeImmutable( $row['end_datetime'], new DateTimeZone( 'UTC' ) ),
			);
		}

		return $out;
	}

	/**
	 * Combine a day with a time string.
	 *
	 * @param DateTimeImmutable $day Day.
	 * @param string            $time Time string.
	 * @param DateTimeZone      $timezone Configured timezone.
	 * @return DateTimeImmutable
	 */
	private static function combine( DateTimeImmutable $day, string $time, DateTimeZone $timezone ): DateTimeImmutable {
		$parts = explode( ':', $time );

		return $day->setTime( (int) ( $parts[0] ?? 0 ), (int) ( $parts[1] ?? 0 ), (int) ( $parts[2] ?? 0 ) );
	}

	/**
	 * Check whether a span overlaps any interval.
	 *
	 * @param DateTimeImmutable $start Span start.
	 * @param DateTimeImmutable $end Span end.
	 * @param array<int, array{0: DateTimeImmutable, 1: DateTimeImmutable}> $intervals Intervals.
	 * @return bool
	 */
	private static function overlaps_any( DateTimeImmutable $start, DateTimeImmutable $end, array $intervals ): bool {
		foreach ( $intervals as $interval ) {
			if ( $start < $interval[1] && $end > $interval[0] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether a staff member already has an appointment for a span.
	 *
	 * @param int    $staff_id Staff ID.
	 * @param string $start Start (UTC MySQL).
	 * @param string $end End (UTC MySQL).
	 * @param int    $exclude_id Optional appointment to exclude.
	 * @return bool
	 */
	public static function has_conflict( int $staff_id, string $start, string $end, int $exclude_id = 0 ): bool {
		global $wpdb;

		$table = Schema::table( 'appointments' );
		$now   = Repository::now();
		$sql   = "SELECT id FROM $table WHERE staff_id = %d AND start_datetime < %s AND end_datetime > %s
			AND (status IN ('approved','done') OR (status = 'pending' AND (hold_until IS NULL OR hold_until > %s)))";
		$params = array( $staff_id, $end, $start, $now );

		if ( $exclude_id ) {
			$sql     .= ' AND id != %d';
			$params[] = $exclude_id;
		}

		return (bool) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
	}
}
