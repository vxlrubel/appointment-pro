<?php
namespace Praxleo\Appointment\Database;
use WP_Error;
use Exception;
use DateTimeImmutable;
use DateTimeZone;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Demo {
	public static function data_tables() {
		return array(
			'payments',
			'customer_appointments',
			'appointments',
			'coupon_services',
			'coupons',
			'extras',
			'custom_fields',
			'staff_breaks',
			'staff_days_off',
			'staff_schedule',
			'staff_services',
			'staff',
			'services',
			'service_categories',
			'locations',
			'customers',
		);
	}

	public static function reset() {
		global $wpdb;
		self::empty_tables();
		foreach ( self::data_tables() as $table ) {
			$wpdb->query( 'ALTER TABLE ' . \Praxleo\Appointment\Database\Schema::table( $table ) . ' AUTO_INCREMENT = 1' );
		}
		return array( 'ok' => true );
	}

	public static function insert() {
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );
		try {
			self::empty_tables();
			$counts = self::seed();
			$wpdb->query( 'COMMIT' );
			\Praxleo\Appointment\Domain\Mailer::seed_templates();
			return array_merge( array( 'ok' => true ), $counts );
		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'demo_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	private static function empty_tables() {
		global $wpdb;
		foreach ( self::data_tables() as $table ) {
			$wpdb->query( 'DELETE FROM ' . \Praxleo\Appointment\Database\Schema::table( $table ) );
		}
	}

	private static function seed() {
		$now = \Praxleo\Appointment\Database\Repository::now();
		$tz  = self::timezone();

		$loc_ids = self::seed_locations();
		$cat_ids = self::seed_categories();
		$svc     = self::seed_services( $cat_ids );
		$staff   = self::seed_staff();
		self::seed_staff_links( $staff, $svc, $loc_ids );
		$extra_ids = self::seed_extras( $svc );
		$coupon_ids = self::seed_coupons();
		$field_ids  = self::seed_custom_fields();
		$cust_ids   = self::seed_customers( $now );
		$appt_ids   = self::seed_appointments( $now, $tz, $loc_ids, $svc, $staff, $cust_ids, $extra_ids, $coupon_ids );

		return array(
			'locations'     => count( $loc_ids ),
			'categories'    => count( $cat_ids ),
			'services'      => count( $svc ),
			'staff'         => count( $staff ),
			'extras'        => count( $extra_ids ),
			'coupons'       => count( $coupon_ids ),
			'custom_fields' => count( $field_ids ),
			'customers'     => count( $cust_ids ),
			'appointments'  => count( $appt_ids ),
		);
	}

	private static function timezone() {
		$name = \Praxleo\Appointment\Domain\Settings::get( 'timezone', 'UTC' );
		try {
			return new DateTimeZone( $name ? $name : 'UTC' );
		} catch ( Exception $e ) {
			return new DateTimeZone( 'UTC' );
		}
	}

	private static function add( $table, $row ) {
		$id = \Praxleo\Appointment\Database\Repository::insert( $table, $row );
		if ( ! $id ) {
			throw new RuntimeException( 'Could not insert into ' . $table );
		}
		return $id;
	}

	private static function seed_locations() {
		$ids  = array();
		$rows = array(
			array( 'name' => 'Downtown Studio', 'address' => '100 Market Street', 'phone' => '555-0100', 'info' => 'Main studio in the city center.', 'position' => 1 ),
			array( 'name' => 'Uptown Clinic', 'address' => '42 Oak Avenue', 'phone' => '555-0101', 'info' => 'Quiet clinic with parking.', 'position' => 2 ),
			array( 'name' => 'Harbor Wellness', 'address' => '8 Pier Road', 'phone' => '555-0102', 'info' => 'Waterfront wellness space.', 'position' => 3 ),
		);
		foreach ( $rows as $row ) {
			$row['enabled'] = 1;
			$ids[]          = self::add( 'locations', $row );
		}
		return $ids;
	}

	private static function seed_categories() {
		$ids = array();
		foreach ( array( 'Hair', 'Nails', 'Skin', 'Massage', 'Wellness' ) as $i => $name ) {
			$ids[] = self::add(
				'service_categories',
				array(
					'name'     => $name,
					'position' => $i + 1,
				)
			);
		}
		return $ids;
	}

	private static function seed_services( $cat_ids ) {
		$defs = array(
			'haircut'      => array( 'cat' => 0, 'name' => 'Haircut', 'duration_sec' => 3600, 'price' => 45, 'color' => '#0f766e' ),
			'color'        => array( 'cat' => 0, 'name' => 'Hair Color', 'duration_sec' => 5400, 'price' => 90, 'color' => '#7c3aed' ),
			'manicure'     => array( 'cat' => 1, 'name' => 'Manicure', 'duration_sec' => 2700, 'price' => 35, 'color' => '#be185d' ),
			'pedicure'     => array( 'cat' => 1, 'name' => 'Pedicure', 'duration_sec' => 3600, 'price' => 45, 'color' => '#db2777' ),
			'facial'       => array( 'cat' => 2, 'name' => 'Classic Facial', 'duration_sec' => 3600, 'price' => 70, 'color' => '#0369a1' ),
			'peel'         => array( 'cat' => 2, 'name' => 'Chemical Peel', 'duration_sec' => 2700, 'price' => 95, 'color' => '#0e7490' ),
			'swedish'      => array( 'cat' => 3, 'name' => 'Swedish Massage', 'duration_sec' => 3600, 'price' => 80, 'color' => '#15803d' ),
			'deep_tissue'  => array( 'cat' => 3, 'name' => 'Deep Tissue Massage', 'duration_sec' => 4500, 'price' => 95, 'color' => '#166534' ),
			'consult'      => array( 'cat' => 4, 'name' => 'Wellness Consultation', 'duration_sec' => 1800, 'price' => 40, 'color' => '#b45309' ),
			'nutrition'    => array( 'cat' => 4, 'name' => 'Nutrition Session', 'duration_sec' => 2700, 'price' => 55, 'color' => '#a16207' ),
		);
		$out = array();
		$i   = 0;
		foreach ( $defs as $key => $row ) {
			$i++;
			$out[ $key ] = array(
				'id'            => self::add(
					'services',
					array(
						'category_id'        => $cat_ids[ $row['cat'] ],
						'name'               => $row['name'],
						'duration_sec'       => $row['duration_sec'],
						'price'              => $row['price'],
						'padding_left_sec'   => 0,
						'padding_right_sec'  => 0,
						'color'              => $row['color'],
						'info'               => $row['name'] . ' with a licensed specialist.',
						'enabled'            => 1,
						'position'           => $i,
					)
				),
				'duration_sec' => $row['duration_sec'],
				'price'        => $row['price'],
			);
		}
		return $out;
	}

	private static function seed_staff() {
		$defs = array(
			'alex'   => array( 'full_name' => 'Alex Rivera', 'email' => 'alex@example.com', 'phone' => '555-0200', 'color' => '#0f766e', 'info' => 'Senior stylist and colorist.' ),
			'jordan' => array( 'full_name' => 'Jordan Lee', 'email' => 'jordan@example.com', 'phone' => '555-0201', 'color' => '#7c3aed', 'info' => 'Massage and skin specialist.' ),
			'sam'    => array( 'full_name' => 'Sam Chen', 'email' => 'sam@example.com', 'phone' => '555-0202', 'color' => '#b45309', 'info' => 'Nails and wellness specialist.' ),
		);
		$out = array();
		$i   = 0;
		foreach ( $defs as $key => $row ) {
			$i++;
			$row['wp_user_id'] = null;
			$row['enabled']    = 1;
			$row['position']   = $i;
			$out[ $key ]       = self::add( 'staff', $row );
		}
		return $out;
	}

	private static function seed_staff_links( $staff, $svc, $loc_ids ) {
		$map = array(
			'alex'   => array( 'services' => array( 'haircut', 'color' ), 'locations' => array( 0, 1 ) ),
			'jordan' => array( 'services' => array( 'facial', 'peel', 'swedish', 'deep_tissue' ), 'locations' => array( 0, 1, 2 ) ),
			'sam'    => array( 'services' => array( 'manicure', 'pedicure', 'consult', 'nutrition' ), 'locations' => array( 1, 2 ) ),
		);
		foreach ( $map as $key => $cfg ) {
			$sid = $staff[ $key ];
			foreach ( $cfg['services'] as $svc_key ) {
				foreach ( $cfg['locations'] as $loc_i ) {
					self::add(
						'staff_services',
						array(
							'staff_id'    => $sid,
							'service_id'  => $svc[ $svc_key ]['id'],
							'location_id' => $loc_ids[ $loc_i ],
							'price'       => null,
							'enabled'     => 1,
						)
					);
				}
			}
			for ( $d = 1; $d <= 5; $d++ ) {
				self::add(
					'staff_schedule',
					array(
						'staff_id'    => $sid,
						'location_id' => null,
						'weekday'     => $d,
						'start_time'  => '09:00:00',
						'end_time'    => '17:00:00',
					)
				);
			}
		}
	}

	private static function seed_extras( $svc ) {
		$defs = array(
			array( 'svc' => 'haircut', 'title' => 'Hair wash', 'duration_sec' => 600, 'price' => 10 ),
			array( 'svc' => 'swedish', 'title' => 'Hot stone add-on', 'duration_sec' => 900, 'price' => 20 ),
			array( 'svc' => 'facial', 'title' => 'Extra 15 minutes', 'duration_sec' => 900, 'price' => 15 ),
		);
		$ids = array();
		foreach ( $defs as $i => $row ) {
			$ids[] = self::add(
				'extras',
				array(
					'service_id'   => $svc[ $row['svc'] ]['id'],
					'title'        => $row['title'],
					'duration_sec' => $row['duration_sec'],
					'price'        => $row['price'],
					'max_quantity' => 1,
					'enabled'      => 1,
					'position'     => $i + 1,
				)
			);
		}
		return $ids;
	}

	private static function seed_coupons() {
		return array(
			self::add(
				'coupons',
				array(
					'code'            => 'SAVE10',
					'discount_type'   => 'percent',
					'discount_amount' => 10,
					'usage_limit'     => 100,
					'used'            => 0,
					'min_amount'      => 0,
					'start_date'      => null,
					'end_date'        => null,
					'enabled'         => 1,
				)
			),
			self::add(
				'coupons',
				array(
					'code'            => 'WELCOME20',
					'discount_type'   => 'percent',
					'discount_amount' => 20,
					'usage_limit'     => 50,
					'used'            => 0,
					'min_amount'      => 40,
					'start_date'      => null,
					'end_date'        => null,
					'enabled'         => 1,
				)
			),
		);
	}

	private static function seed_custom_fields() {
		return array(
			self::add(
				'custom_fields',
				array(
					'label'         => 'How did you hear about us?',
					'type'          => 'text',
					'required'      => 0,
					'options_json'  => null,
					'services_json' => null,
					'position'      => 1,
					'enabled'       => 1,
				)
			),
			self::add(
				'custom_fields',
				array(
					'label'         => 'Any allergies or notes?',
					'type'          => 'textarea',
					'required'      => 0,
					'options_json'  => null,
					'services_json' => null,
					'position'      => 2,
					'enabled'       => 1,
				)
			),
			self::add(
				'custom_fields',
				array(
					'label'         => 'Preferred contact',
					'type'          => 'select',
					'required'      => 1,
					'options_json'  => wp_json_encode( array( 'Email', 'Phone', 'SMS' ) ),
					'services_json' => null,
					'position'      => 3,
					'enabled'       => 1,
				)
			),
		);
	}

	private static function seed_customers( $now ) {
		$ids  = array();
		$rows = array(
			'maya'   => array( 'full_name' => 'Maya Patel', 'email' => 'maya@example.com', 'phone' => '555-1001' ),
			'chris'  => array( 'full_name' => 'Chris Nguyen', 'email' => 'chris@example.com', 'phone' => '555-1002' ),
			'riley'  => array( 'full_name' => 'Riley Brooks', 'email' => 'riley@example.com', 'phone' => '555-1003' ),
			'taylor' => array( 'full_name' => 'Taylor Kim', 'email' => 'taylor@example.com', 'phone' => '555-1004' ),
			'morgan' => array( 'full_name' => 'Morgan Blake', 'email' => 'morgan@example.com', 'phone' => '555-1005' ),
		);
		foreach ( $rows as $key => $row ) {
			$row['wp_user_id'] = null;
			$row['notes']      = '';
			$row['created_at'] = $now;
			$ids[ $key ]       = self::add( 'customers', $row );
		}
		return $ids;
	}

	private static function local_utc( DateTimeImmutable $day, $hour, $minute, DateTimeZone $tz ) {
		$local = $day->setTimezone( $tz )->setTime( $hour, $minute, 0 );
		return $local->setTimezone( new DateTimeZone( 'UTC' ) );
	}

	private static function seed_appointments( $now, DateTimeZone $tz, $loc_ids, $svc, $staff, $cust_ids, $extra_ids, $coupon_ids ) {
		$today  = new DateTimeImmutable( 'now', $tz );
		$mon    = self::next_weekday( $today, 1 );
		$tue    = self::next_weekday( $today, 2 );
		$thu    = self::next_weekday( $today, 4 );
		$last_fri = self::prev_weekday( $today, 5 );

		$defs = array(
			array(
				'day' => $mon, 'hour' => 10, 'status' => 'approved',
				'svc' => 'haircut', 'staff' => 'alex', 'loc' => 0, 'cust' => 'maya',
				'extra' => 0, 'coupon' => null, 'fields' => array( 'Preferred contact' => 'Email' ),
			),
			array(
				'day' => $mon, 'hour' => 13, 'status' => 'pending',
				'svc' => 'manicure', 'staff' => 'sam', 'loc' => 1, 'cust' => 'chris',
				'extra' => null, 'coupon' => 0, 'fields' => array( 'Preferred contact' => 'Phone' ),
			),
			array(
				'day' => $tue, 'hour' => 11, 'status' => 'approved',
				'svc' => 'swedish', 'staff' => 'jordan', 'loc' => 2, 'cust' => 'riley',
				'extra' => 1, 'coupon' => null, 'fields' => array( 'Preferred contact' => 'Email' ),
			),
			array(
				'day' => $last_fri, 'hour' => 9, 'status' => 'done',
				'svc' => 'facial', 'staff' => 'jordan', 'loc' => 0, 'cust' => 'taylor',
				'extra' => 2, 'coupon' => null, 'fields' => array( 'Preferred contact' => 'SMS' ),
			),
			array(
				'day' => $thu, 'hour' => 15, 'status' => 'cancelled',
				'svc' => 'consult', 'staff' => 'sam', 'loc' => 1, 'cust' => 'morgan',
				'extra' => null, 'coupon' => null, 'fields' => array( 'Preferred contact' => 'Email' ),
			),
		);

		$ids = array();
		foreach ( $defs as $a ) {
			$service = $svc[ $a['svc'] ];
			$start   = self::local_utc( $a['day'], $a['hour'], 0, $tz );
			$extra_rows = array();
			$extra_sec  = 0;
			$extra_amt  = 0;
			if ( null !== $a['extra'] ) {
				$ex = \Praxleo\Appointment\Database\Repository::find( 'extras', $extra_ids[ $a['extra'] ] );
				if ( $ex ) {
					$extra_sec  = (int) $ex['duration_sec'];
					$extra_amt  = (float) $ex['price'];
					$extra_rows[] = array(
						'id'    => (int) $ex['id'],
						'title' => $ex['title'],
						'qty'   => 1,
						'price' => $extra_amt,
					);
				}
			}
			$end = $start->modify( '+' . ( (int) $service['duration_sec'] + $extra_sec ) . ' seconds' );
			$aid = self::add(
				'appointments',
				array(
					'location_id'     => $loc_ids[ $a['loc'] ],
					'service_id'      => $service['id'],
					'staff_id'        => $staff[ $a['staff'] ],
					'start_datetime'  => $start->format( 'Y-m-d H:i:s' ),
					'end_datetime'    => $end->format( 'Y-m-d H:i:s' ),
					'status'          => $a['status'],
					'hold_until'      => null,
					'idempotency_key' => null,
					'created_at'      => $now,
					'updated_at'      => $now,
				)
			);
			$amount = (float) $service['price'] + $extra_amt;
			$coupon_id = null;
			if ( null !== $a['coupon'] ) {
				$coupon_id = $coupon_ids[ $a['coupon'] ];
				$amount    = round( $amount * 0.9, 2 );
			}
			$ca_id = self::add(
				'customer_appointments',
				array(
					'appointment_id'     => $aid,
					'customer_id'        => $cust_ids[ $a['cust'] ],
					'number_of_persons'  => 1,
					'extras_json'        => wp_json_encode( $extra_rows ),
					'custom_fields_json' => wp_json_encode( $a['fields'] ),
					'coupon_id'          => $coupon_id,
					'notes'              => '',
					'token'              => bin2hex( random_bytes( 16 ) ),
					'reminder_sent_at'   => null,
				)
			);
			if ( in_array( $a['status'], array( 'approved', 'done' ), true ) ) {
				self::add(
					'payments',
					array(
						'customer_appointment_id' => $ca_id,
						'type'                    => 'local',
						'status'                  => 'completed',
						'amount'                  => $amount,
						'currency'                => \Praxleo\Appointment\Domain\Settings::get( 'currency', 'USD' ),
						'details_json'            => wp_json_encode( array( 'source' => 'demo' ) ),
						'created_at'              => $now,
					)
				);
			}
			$ids[] = $aid;
		}
		return $ids;
	}

	private static function next_weekday( DateTimeImmutable $from, $iso_day ) {
		$current = (int) $from->format( 'N' );
		$delta   = ( $iso_day - $current + 7 ) % 7;
		if ( 0 === $delta ) {
			$delta = 7;
		}
		return $from->modify( '+' . $delta . ' days' )->setTime( 0, 0, 0 );
	}

	private static function prev_weekday( DateTimeImmutable $from, $iso_day ) {
		$current = (int) $from->format( 'N' );
		$delta   = ( $current - $iso_day + 7 ) % 7;
		if ( 0 === $delta ) {
			$delta = 7;
		}
		return $from->modify( '-' . $delta . ' days' )->setTime( 0, 0, 0 );
	}
}
