<?php
namespace Praxleo\Appointment\Database;
use WP_Error;
use Exception;
use DateTimeImmutable;
use DateTimeZone;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Transfer {
	const NULL_TOKEN = '\\N';

	public static function tables() {
		return array(
			'locations',
			'service_categories',
			'services',
			'staff',
			'staff_services',
			'staff_schedule',
			'staff_breaks',
			'staff_days_off',
			'customers',
			'extras',
			'coupons',
			'coupon_services',
			'custom_fields',
			'appointments',
			'customer_appointments',
			'payments',
			'notifications',
		);
	}

	public static function export() {
		$fh = fopen( 'php://temp', 'r+' );
		fputcsv( $fh, array( '_praxleo', PX_APPOINTMENT_VERSION, 'csv-export' ) );
		foreach ( self::tables() as $table ) {
			$rows = self::table_rows( $table );
			fputcsv( $fh, array( '_table', $table ) );
			if ( ! $rows ) {
				continue;
			}
			fputcsv( $fh, array_keys( $rows[0] ) );
			foreach ( $rows as $row ) {
				$vals = array();
				foreach ( $row as $v ) {
					$vals[] = null === $v ? self::NULL_TOKEN : $v;
				}
				fputcsv( $fh, $vals );
			}
		}
		rewind( $fh );
		$csv = stream_get_contents( $fh );
		fclose( $fh );
		return array(
			'ok'       => true,
			'filename' => 'praxleo-booking-' . gmdate( 'Y-m-d-His' ) . '.csv',
			'csv'      => $csv,
		);
	}

	public static function import( $csv ) {
		if ( ! is_string( $csv ) || '' === trim( $csv ) ) {
			return new WP_Error( 'invalid', 'CSV file is empty.', array( 'status' => 400 ) );
		}
		if ( "\xEF\xBB\xBF" === substr( $csv, 0, 3 ) ) {
			$csv = substr( $csv, 3 );
		}
		$parsed = self::parse( $csv );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}
		if ( ! $parsed ) {
			return new WP_Error( 'invalid', 'No tables found in CSV.', array( 'status' => 400 ) );
		}

		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );
		try {
			$replace = array();
			foreach ( self::tables() as $table ) {
				if ( isset( $parsed[ $table ] ) ) {
					$replace[] = $table;
				}
			}
			foreach ( array_reverse( $replace ) as $table ) {
				$wpdb->query( 'DELETE FROM ' . \Praxleo\Appointment\Database\Schema::table( $table ) );
			}
			$counts = array();
			foreach ( $replace as $table ) {
				$n = 0;
				foreach ( $parsed[ $table ] as $row ) {
					self::insert_row( $table, $row );
					$n++;
				}
				$counts[ $table ] = $n;
			}
			$wpdb->query( 'COMMIT' );
			\Praxleo\Appointment\Domain\Mailer::seed_templates();
			return array(
				'ok'     => true,
				'counts' => $counts,
			);
		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'import_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	private static function table_rows( $table ) {
		$order = 'coupon_services' === $table ? 'coupon_id ASC, service_id ASC' : 'id ASC';
		return \Praxleo\Appointment\Database\Repository::all( $table, array(), $order );
	}

	private static function insert_row( $table, $row ) {
		global $wpdb;
		\Praxleo\Appointment\Database\Repository::insert( $table, $row );
		if ( $wpdb->last_error ) {
			throw new RuntimeException( $table . ': ' . $wpdb->last_error );
		}
	}

	private static function parse( $csv ) {
		$fh = fopen( 'php://temp', 'r+' );
		fwrite( $fh, $csv );
		rewind( $fh );
		$allowed = self::tables();
		$out     = array();
		$table   = null;
		$headers = null;
		while ( ( $row = fgetcsv( $fh ) ) !== false ) {
			if ( ! $row || ( 1 === count( $row ) && '' === $row[0] ) ) {
				continue;
			}
			if ( '_praxleo' === $row[0] ) {
				continue;
			}
			if ( '_table' === $row[0] ) {
				$name = isset( $row[1] ) ? $row[1] : '';
				if ( ! in_array( $name, $allowed, true ) ) {
					fclose( $fh );
					return new WP_Error( 'invalid', 'Unknown table in CSV: ' . $name, array( 'status' => 400 ) );
				}
				$table   = $name;
				$headers = null;
				if ( ! isset( $out[ $table ] ) ) {
					$out[ $table ] = array();
				}
				continue;
			}
			if ( ! $table ) {
				continue;
			}
			if ( null === $headers ) {
				$headers = $row;
				continue;
			}
			$assoc = array();
			foreach ( $headers as $i => $col ) {
				if ( '' === $col ) {
					continue;
				}
				$val = array_key_exists( $i, $row ) ? $row[ $i ] : '';
				$assoc[ $col ] = self::NULL_TOKEN === $val ? null : $val;
			}
			if ( $assoc ) {
				$out[ $table ][] = $assoc;
			}
		}
		fclose( $fh );
		return $out;
	}
}
