<?php
namespace Praxleo\Appointment\Domain;
use Praxleo\Appointment\Database\Repository;
use Praxleo\Appointment\Database\Schema;
use WP_Error;
defined( 'ABSPATH' ) || exit;
class BusinessHours {
 public static function all(): array {
  $rows = Repository::all( 'business_hours', array(), 'weekday ASC, start_time ASC, id ASC' );
  foreach ( $rows as &$row ) { $row['breaks'] = json_decode( $row['breaks_json'] ?: '[]', true ) ?: array(); unset( $row['breaks_json'] ); }
  return $rows;
 }
 public static function save( $rows ) {
  global $wpdb;
  if ( ! is_array( $rows ) || ! array_is_list( $rows ) ) { return new WP_Error( 'invalid', 'Weekly hours must be a list.', array( 'status' => 400 ) ); }
  $clean = array();
  $time = static function( $value ) { return is_string( $value ) && preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/D', $value ) ? substr( $value . ':00', 0, 8 ) : false; };
  foreach ( $rows as $row ) {
   if ( ! is_array( $row ) ) { return new WP_Error( 'invalid', 'Invalid weekly shift.', array( 'status' => 400 ) ); }
   $start = $time( $row['start_time'] ?? '' ); $end = $time( $row['end_time'] ?? '' );
   $weekday = filter_var( $row['weekday'] ?? -1, FILTER_VALIDATE_INT );
   $location = (int) ( $row['location_id'] ?? 0 );
   if ( false === $weekday || $weekday < 0 || $weekday > 6 || ! $start || ! $end || $end <= $start || ( $location && ! Repository::find( 'locations', $location ) ) ) { return new WP_Error( 'invalid', 'Choose a valid day, location, and opening time before closing time.', array( 'status' => 400 ) ); }
   $breaks = array();
   if ( isset( $row['breaks'] ) && ! is_array( $row['breaks'] ) ) { return new WP_Error( 'invalid', 'Breaks must be a list.', array( 'status' => 400 ) ); }
   foreach ( $row['breaks'] ?? array() as $pause ) {
    $bs = $time( $pause['start_time'] ?? '' ); $be = $time( $pause['end_time'] ?? '' );
    if ( ! $bs || ! $be || $be <= $bs || $bs < $start || $be > $end ) { return new WP_Error( 'invalid', 'Breaks must start and end within their shift.', array( 'status' => 400 ) ); }
    $breaks[] = array( 'start_time' => $bs, 'end_time' => $be );
   }
   foreach ( $clean as $other ) {
    if ( $other['weekday'] === $weekday && ( ! $location || ! $other['location_id'] || $other['location_id'] === $location ) && $start < $other['end_time'] && $end > $other['start_time'] ) { return new WP_Error( 'invalid', 'Business shifts at the same location cannot overlap.', array( 'status' => 400 ) ); }
   }
   $clean[] = array( 'weekday' => $weekday, 'location_id' => $location ?: null, 'start_time' => $start, 'end_time' => $end, 'breaks_json' => wp_json_encode( $breaks ) );
  }
  $wpdb->query( 'START TRANSACTION' );
  if ( false === $wpdb->query( 'DELETE FROM ' . Schema::table( 'business_hours' ) ) ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'save_failed', 'Could not save business hours.', array( 'status' => 500 ) ); }
  foreach ( $clean as $row ) { if ( ! Repository::insert( 'business_hours', $row ) ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'save_failed', 'Could not save business hours.', array( 'status' => 500 ) ); } }
  $wpdb->query( 'COMMIT' );
  return self::all();
 }
}
