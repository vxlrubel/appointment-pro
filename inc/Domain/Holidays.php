<?php
/**
 * Company-wide holiday closures, evaluated in the configured booking timezone.
 *
 * @package Praxleo_Appointment
 */
namespace Praxleo\Appointment\Domain;

use DateTimeImmutable;
use WP_Error;
use Praxleo\Appointment\Database\Repository;
use Praxleo\Appointment\Database\Schema;

defined( 'ABSPATH' ) || exit;

class Holidays {
	private static array $cache = array();

	public static function clear_cache(): void {
		self::$cache = array();
	}

	public static function is_closed( string $date ): bool {
		$table = Schema::table( 'holidays' );
		if ( ! isset( self::$cache[ $table ] ) ) {
			self::$cache[ $table ] = Repository::all( 'holidays', array( 'enabled' => 1 ) );
		}
		foreach ( self::$cache[ $table ] as $holiday ) {
			if ( $holiday['holiday_date'] === $date || ( ! empty( $holiday['repeats_yearly'] ) && substr( $holiday['holiday_date'], 5 ) === substr( $date, 5 ) ) ) {
				return true;
			}
		}
		return false;
	}

	public static function save( array $data, int $id = 0 ) {
		if ( $id && ! Repository::find( 'holidays', $id ) ) {
			return new WP_Error( 'not_found', 'Holiday not found.', array( 'status' => 404 ) );
		}
		$date = sanitize_text_field( $data['holiday_date'] ?? '' );
		$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
		$name = sanitize_text_field( $data['name'] ?? '' );
		$description = sanitize_textarea_field( $data['description'] ?? '' );
		if ( ! $name || ! $description || ! $parsed || $parsed->format( 'Y-m-d' ) !== $date ) {
			return new WP_Error( 'invalid', 'Name, description, and a valid holiday date are required.', array( 'status' => 400 ) );
		}
		$row = array(
			'name'           => $name,
			'description'    => $description,
			'holiday_date'   => $date,
			'repeats_yearly' => ! array_key_exists( 'repeats_yearly', $data ) || ! empty( $data['repeats_yearly'] ) ? 1 : 0,
			'enabled'        => ! array_key_exists( 'enabled', $data ) || ! empty( $data['enabled'] ) ? 1 : 0,
		);
		$result = $id ? Repository::update( 'holidays', $row, array( 'id' => $id ) ) : Repository::insert( 'holidays', $row );
		if ( false === $result || ( ! $id && ! $result ) ) {
			return new WP_Error( 'save_failed', 'Could not save the holiday.', array( 'status' => 500 ) );
		}
		self::clear_cache();
		return Repository::find( 'holidays', $id ?: $result );
	}

	public static function delete( int $id ) {
		$result = Repository::delete( 'holidays', array( 'id' => $id ) );
		self::clear_cache();
		return $result;
	}
}
