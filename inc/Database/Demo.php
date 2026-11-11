<?php
namespace Praxleo\Appointment\Database;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;
use WP_Error;
use Praxleo\Appointment\Domain\Mailer;
use Praxleo\Appointment\Domain\Holidays;
use Praxleo\Appointment\Domain\Settings;

defined( 'ABSPATH' ) || exit;

class Demo {
	public static function data_tables(): array {
		return array( 'payments', 'customer_appointments', 'appointments', 'coupon_services', 'coupons', 'extras', 'custom_fields', 'staff_breaks', 'staff_days_off', 'staff_schedule', 'staff_services', 'staff', 'services', 'service_categories', 'locations', 'customers', 'holidays', 'business_hours', 'affiliates' );
	}

	private static function empty_tables(): void {
		global $wpdb;
		foreach ( self::data_tables() as $table ) {
			if ( false === $wpdb->query( 'DELETE FROM ' . Schema::table( $table ) ) ) { throw new RuntimeException( 'Could not clear ' . $table ); }
		}
		Holidays::clear_cache();
	}

	public static function reset(): array {
		global $wpdb;
		self::empty_tables();
		foreach ( self::data_tables() as $table ) { $wpdb->query( 'ALTER TABLE ' . Schema::table( $table ) . ' AUTO_INCREMENT = 1' ); }
		return array( 'ok' => true );
	}

	public static function insert() {
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );
		try {
			self::empty_tables();
			$counts = self::seed();
			$wpdb->query( 'COMMIT' );
			Mailer::seed_templates();
			return array_merge( array( 'ok' => true ), $counts );
		} catch ( Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'demo_failed', $error->getMessage(), array( 'status' => 500 ) );
		}
	}

	private static function add( string $table, array $row ): int {
		$id = Repository::insert( $table, $row );
		if ( ! $id ) { throw new RuntimeException( 'Could not insert into ' . $table ); }
		return $id;
	}

	private static function seed(): array {
		$categories = array( 'Primary Care', 'Cardiology', 'Dermatology', 'Pediatrics', 'Diagnostic Medicine' );
		$names = array(
			array( 'General Medical Consultation', 'Annual Health Examination', 'Blood Pressure Review', 'Diabetes Follow-up', 'Preventive Health Screening', 'Chronic Care Review' ),
			array( 'Cardiology Consultation', 'ECG Assessment', 'Heart Health Review', 'Cholesterol Consultation', 'Cardiac Risk Assessment', 'Cardiology Follow-up' ),
			array( 'Dermatology Consultation', 'Acne Assessment', 'Skin Lesion Assessment', 'Eczema Consultation', 'Psoriasis Review', 'Dermatology Follow-up' ),
			array( 'Pediatric Consultation', 'Well-child Examination', 'Child Growth Assessment', 'Childhood Asthma Review', 'Pediatric Allergy Review', 'Pediatric Follow-up' ),
			array( 'Diagnostic Consultation', 'Blood Test Review', 'Ultrasound Consultation', 'Thyroid Function Review', 'Laboratory Results Consultation', 'Diagnostic Follow-up' ),
		);
		$doctor_names = array( 'Dr. Maya Rahman', 'Dr. Adam Chen', 'Dr. Sara Patel', 'Dr. Omar Hassan', 'Dr. Elena Ruiz' );
		$clinic_names = array( 'Central Medical Center', 'Heart Care Clinic', 'City Dermatology Clinic', 'Children Medical Center', 'Diagnostic Medical Center' );
		$colors = array( '#0d6efd', '#359FA0', '#8b5cf6', '#F70063', '#d97706' );
		$services = array(); $staff = array(); $locations = array(); $category_ids = array();
		foreach ( $categories as $index => $category ) {
			$category_ids[] = self::add( 'service_categories', array( 'name' => $category, 'position' => $index + 1 ) );
			$locations[] = self::add( 'locations', array( 'name' => $clinic_names[ $index ], 'info' => $category . ' appointments.', 'address' => ( 10 + $index ) . ' Medical Avenue', 'phone' => '555-010' . $index, 'position' => $index + 1, 'enabled' => 1 ) );
			$staff[] = self::add( 'staff', array( 'full_name' => $doctor_names[ $index ], 'email' => 'doctor' . ( $index + 1 ) . '@example.test', 'phone' => '555-020' . $index, 'info' => $category . ' specialist.', 'color' => $colors[ $index ], 'position' => $index + 1, 'enabled' => 1 ) );
			for ( $weekday = 1; $weekday <= 5; $weekday++ ) {
				$schedule = self::add( 'staff_schedule', array( 'staff_id' => $staff[ $index ], 'location_id' => $locations[ $index ], 'weekday' => $weekday, 'start_time' => '09:00:00', 'end_time' => '17:00:00' ) );
				self::add( 'staff_breaks', array( 'schedule_id' => $schedule, 'start_time' => '12:00:00', 'end_time' => '13:00:00' ) );
			}
			foreach ( $names[ $index ] as $number => $name ) {
				$duration = array( 1800, 3600, 1800, 2700, 3600, 1800 )[ $number ]; $price = 40 + $index * 15 + $number * 5;
				$id = self::add( 'services', array( 'category_id' => $category_ids[ $index ], 'name' => $name, 'duration_sec' => $duration, 'price' => $price, 'padding_left_sec' => 0, 'padding_right_sec' => 0, 'color' => $colors[ $index ], 'info' => $name . ' with a medical professional.', 'enabled' => 1, 'position' => $index * 6 + $number + 1 ) );
				self::add( 'staff_services', array( 'staff_id' => $staff[ $index ], 'service_id' => $id, 'location_id' => $locations[ $index ], 'price' => null, 'enabled' => 1 ) );
				$services[] = array( 'id' => $id, 'duration' => $duration, 'price' => $price );
			}
		}
		$extra_ids = array();
		foreach ( array( array( 0, 'Extended consultation', 900, 15 ), array( 6, 'Additional cardiac review', 900, 20 ) ) as $index => $extra ) {
			$extra_ids[] = self::add( 'extras', array( 'service_id' => $services[ $extra[0] ]['id'], 'title' => $extra[1], 'duration_sec' => $extra[2], 'price' => $extra[3], 'max_quantity' => 1, 'enabled' => 1, 'position' => $index + 1 ) );
		}
		foreach ( array( 'HEALTH10' => 10, 'PATIENT20' => 20 ) as $code => $discount ) { self::add( 'coupons', array( 'code' => $code, 'discount_type' => 'percent', 'discount_amount' => $discount, 'usage_limit' => 100, 'used' => 0, 'min_amount' => 0, 'enabled' => 1 ) ); }
		$field_ids = array();
		foreach ( array( array( 'Reason for visit', 'text', 0, null ), array( 'Allergies or medical notes', 'textarea', 0, null ), array( 'Preferred contact', 'select', 1, wp_json_encode( array( 'Email', 'Phone', 'SMS' ) ) ) ) as $index => $field ) {
			$field_ids[] = self::add( 'custom_fields', array( 'label' => $field[0], 'type' => $field[1], 'required' => $field[2], 'options_json' => $field[3], 'services_json' => null, 'position' => $index + 1, 'enabled' => 1 ) );
		}
		$now = Repository::now(); $timezone = Mailer::timezone();
		$day = ( new DateTimeImmutable( 'now', $timezone ) )->modify( '-3 days' )->setTime( 10, 0 );
		while ( (int) $day->format( 'N' ) > 5 ) { $day = $day->modify( '+1 day' ); }
		for ( $index = 0; $index < 30; $index++ ) {
			$doctor = $index % 5; $service = $services[ $doctor * 6 + intdiv( $index, 5 ) ];
			$customer = self::add( 'customers', array( 'full_name' => 'Demo Patient ' . ( $index + 1 ), 'email' => 'patient' . ( $index + 1 ) . '@example.test', 'phone' => '555-10' . str_pad( (string) $index, 2, '0', STR_PAD_LEFT ), 'notes' => 'Synthetic medical booking demonstration.', 'created_at' => $now ) );
			$status = array( 'approved', 'pending', 'done', 'cancelled', 'approved' )[ $doctor ];
			$start = $day->setTimezone( new DateTimeZone( 'UTC' ) );
			$appointment = self::add( 'appointments', array( 'location_id' => $locations[ $doctor ], 'service_id' => $service['id'], 'staff_id' => $staff[ $doctor ], 'start_datetime' => $start->format( 'Y-m-d H:i:s' ), 'end_datetime' => $start->modify( '+' . $service['duration'] . ' seconds' )->format( 'Y-m-d H:i:s' ), 'status' => $status, 'hold_until' => null, 'idempotency_key' => null, 'created_at' => $now, 'updated_at' => $now ) );
			$ca = self::add( 'customer_appointments', array( 'appointment_id' => $appointment, 'customer_id' => $customer, 'number_of_persons' => 1, 'amount' => $service['price'], 'extras_json' => '[]', 'custom_fields_json' => wp_json_encode( array( $field_ids[0] => 'Routine medical consultation', $field_ids[2] => 'Email' ) ), 'notes' => '', 'token' => bin2hex( random_bytes( 16 ) ), 'reminder_sent_at' => null ) );
			if ( in_array( $status, array( 'approved', 'done' ), true ) ) { self::add( 'payments', array( 'customer_appointment_id' => $ca, 'type' => 'local', 'status' => 'completed', 'amount' => $service['price'], 'currency' => Settings::get( 'currency' ), 'details_json' => '{"source":"demo"}', 'created_at' => $now ) ); }
			if ( $doctor === 4 ) { do { $day = $day->modify( '+1 day' ); } while ( (int) $day->format( 'N' ) > 5 ); }
		}
		return array( 'locations' => 5, 'categories' => 5, 'services' => 30, 'staff' => 5, 'extras' => 2, 'coupons' => 2, 'custom_fields' => 3, 'customers' => 30, 'appointments' => 30 );
	}
}
