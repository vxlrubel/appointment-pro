<?php
/**
 * Notification e-mails.
 *
 * @package Praxleo_Appointment
 */

namespace Praxleo\Appointment\Domain;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Throwable;
use Praxleo\Appointment\Database\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Sends booking notifications and reminders.
 */
class Mailer {

	/**
	 * Create the default notification templates if missing.
	 *
	 * @return void
	 */
	public static function seed_templates(): void {
		$defaults = array(
			'booking_created_customer'   => array(
				'Your booking request',
				'<p>Hi {customer_name},</p><p>We received your booking for {service} with {staff} at {location} on {date} at {time}.</p><p>Total: {price}</p>',
			),
			'booking_created_admin'      => array(
				'New booking',
				'<p>New booking: {customer_name} — {service} with {staff} at {location} on {date} {time}.</p>',
			),
			'booking_approved_customer'  => array(
				'Booking confirmed',
				'<p>Hi {customer_name},</p><p>Your booking is confirmed: {service} with {staff} at {location} on {date} at {time}.</p>',
			),
			'booking_cancelled_customer' => array(
				'Booking cancelled',
				'<p>Hi {customer_name},</p><p>Your booking for {service} on {date} at {time} was cancelled.</p>',
			),
			'booking_cancelled_admin'    => array(
				'Booking cancelled',
				'<p>{customer_name} booking for {service} on {date} {time} was cancelled.</p>',
			),
			'reminder_customer'          => array(
				'Appointment reminder',
				'<p>Hi {customer_name},</p><p>Reminder: {service} with {staff} at {location} on {date} at {time}.</p>',
			),
		);

		foreach ( array( 'staff', 'admin' ) as $audience ) {
			foreach ( array( 'booking_created' => 'New appointment', 'booking_approved' => 'Appointment approved', 'booking_cancelled' => 'Appointment cancelled', 'reminder' => 'Appointment reminder' ) as $event => $subject ) {
				$key = $event . '_' . $audience;
				if ( ! isset( $defaults[ $key ] ) ) { $defaults[ $key ] = array( $subject, '<p>{customer_name}: {service} with {staff} at {location} on {date} at {time}.</p>' ); }
			}
		}

		foreach ( $defaults as $event => $pair ) {
			$existing = Repository::all( 'notifications', array( 'event' => $event ) );

			if ( $existing ) {
				continue;
			}

			Repository::insert(
				'notifications',
				array(
					'event'     => $event,
					'enabled'   => 1,
					'subject'   => $pair[0],
					'body_html' => $pair[1],
				)
			);
		}
	}

	/**
	 * Send a notification for an event.
	 *
	 * @param string               $event Event key.
	 * @param array<string, mixed> $hydrated Hydrated appointment.
	 * @return void
	 */
	public static function send_event( string $event, array $hydrated ): void {
		try {
			$rows = Repository::all( 'notifications', array( 'event' => $event ) );

			if ( ! $rows || empty( $rows[0]['enabled'] ) ) {
				return;
			}

			$template = $rows[0];
			$replace  = self::placeholders( $hydrated );
			$subject  = strtr( $template['subject'], $replace );
			$body     = strtr( $template['body_html'], $replace );
			$recipients = self::recipients( $event, $hydrated );

			if ( ! $recipients ) {
				return;
			}

			$from_name  = sanitize_text_field( (string) Settings::get( 'company_name' ) );
			$from_email = sanitize_email( (string) Settings::get( 'company_email' ) );

			if ( ! $from_email ) {
				$from_email = get_option( 'admin_email' );
			}

			$headers = array( 'Content-Type: text/html; charset=UTF-8' );

			if ( $from_email ) {
				$headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
			}

			$sent = true;
			foreach ( $recipients as $to ) { $sent = wp_mail( $to, $subject, $body, $headers ) && $sent; }

			if ( ! $sent ) {
				update_option( 'px_appointment_mail_error', gmdate( 'c' ) . ' failed ' . $event );
			}
		} catch ( Throwable $e ) {
			update_option( 'px_appointment_mail_error', gmdate( 'c' ) . ' ' . $event . ' ' . $e->getMessage() );
		}
	}

	/**
	 * Determine the recipients for an event.
	 *
	 * @param string               $event Event key.
	 * @param array<string, mixed> $hydrated Hydrated appointment.
	 * @return string[]
	 */
	private static function recipients( string $event, array $hydrated ): array {
		if ( str_ends_with( $event, '_admin' ) ) { $emails = array( Settings::get( 'company_email' ) ); }
		elseif ( str_ends_with( $event, '_staff' ) ) { $emails = array( $hydrated['staff']['email'] ?? '' ); }
		else {
			$emails = array( $hydrated['customer']['email'] ?? '', Settings::get( 'notification_customer_email', '' ) );
			if ( Settings::get( 'notification_customer_admin_copy', false ) ) { $emails[] = Settings::get( 'company_email' ); }
		}
		return array_values( array_unique( array_filter( array_map( 'sanitize_email', $emails ), 'is_email' ) ) );
	}

	public static function send_group( string $event, array $hydrated ): void {
		foreach ( array( 'customer', 'staff', 'admin' ) as $audience ) { self::send_event( $event . '_' . $audience, $hydrated ); }
	}

	/**
	 * Return the configured timezone.
	 *
	 * @return DateTimeZone
	 */
	public static function timezone(): DateTimeZone {
		$timezone = (string) Settings::get( 'timezone', 'UTC' );

		if ( ! $timezone ) {
			$timezone = wp_timezone_string();
		}

		try {
			return new DateTimeZone( $timezone );
		} catch ( Exception $e ) {
			return new DateTimeZone( 'UTC' );
		}
	}

	/**
	 * Build the placeholder replacement map.
	 *
	 * @param array<string, mixed> $hydrated Hydrated appointment.
	 * @return array<string, string>
	 */
	public static function placeholders( array $hydrated ): array {
		$timezone = self::timezone();
		$start    = new DateTimeImmutable( $hydrated['appointment']['start_datetime'] ?? 'now', new DateTimeZone( 'UTC' ) );
		$local    = $start->setTimezone( $timezone );
		$token    = $hydrated['customer_appointment']['token'] ?? '';
		$cancel   = home_url( '/?px_appointment_cancel=' . rawurlencode( $token ) );
		$amount   = '0';

		if ( ! empty( $hydrated['payments'][0]['amount'] ) ) {
			$amount = $hydrated['payments'][0]['amount'];
		} elseif ( isset( $hydrated['amount'] ) ) {
			$amount = $hydrated['amount'];
		}

		return array(
			'{customer_name}' => $hydrated['customer']['full_name'] ?? '',
			'{service}'       => $hydrated['service']['name'] ?? '',
			'{staff}'         => $hydrated['staff']['full_name'] ?? '',
			'{location}'      => $hydrated['location']['name'] ?? '',
			'{date}'          => $local->format( 'Y-m-d' ),
			'{time}'          => $local->format( 'H:i' ),
			'{price}'         => Settings::get( 'currency' ) . ' ' . $amount,
			'{cancel_url}'    => $cancel,
		);
	}

	/**
	 * Send appointment reminders.
	 *
	 * @return void
	 */
	public static function send_reminders(): void {
		global $wpdb;

		$hours = (int) Settings::get( 'reminder_hours', 24 );
		$now   = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		$from  = $now->modify( "+{$hours} hours" )->modify( '-30 minutes' )->format( 'Y-m-d H:i:s' );
		$to    = $now->modify( "+{$hours} hours" )->modify( '+30 minutes' )->format( 'Y-m-d H:i:s' );

		$appointments = \Praxleo\Appointment\Database\Schema::table( 'appointments' );
		$customers    = \Praxleo\Appointment\Database\Schema::table( 'customer_appointments' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ca.id AS ca_id, a.id AS appt_id FROM $appointments a
				INNER JOIN $customers ca ON ca.appointment_id = a.id
				WHERE a.status = 'approved' AND ca.reminder_sent_at IS NULL
				AND a.start_datetime BETWEEN %s AND %s",
				$from,
				$to
			),
			ARRAY_A
		);

		foreach ( (array) $rows as $row ) {
			$hydrated = Appointments::hydrate( (int) $row['appt_id'] );

			if ( ! $hydrated ) {
				continue;
			}

			self::send_group( 'reminder', $hydrated );

			Repository::update(
				'customer_appointments',
				array( 'reminder_sent_at' => Repository::now() ),
				array( 'id' => (int) $row['ca_id'] )
			);
		}
	}
}
