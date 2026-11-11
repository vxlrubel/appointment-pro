<?php
namespace Praxleo\Appointment\Domain;
use Praxleo\Appointment\Database\Repository;
use Praxleo\Appointment\Database\Schema;
use WP_Error;
defined( 'ABSPATH' ) || exit;
class Affiliates {
 public static function resolve( string $code ): ?array {
  if ( ! preg_match( '/^[a-f0-9]{32}$/D', $code ) ) { return null; }
  $rows = Repository::all( 'affiliates', array( 'code' => $code, 'enabled' => 1 ) );
  return $rows[0] ?? null;
 }
 public static function all(): array {
  global $wpdb;
  $a = Schema::table( 'affiliates' ); $b = Schema::table( 'appointments' );
  $rows = $wpdb->get_results( "SELECT a.*, (SELECT COUNT(*) FROM $b b WHERE b.affiliate_id = a.id) AS booking_count FROM $a a ORDER BY a.id DESC", ARRAY_A );
  foreach ( $rows as &$row ) { $row['link'] = add_query_arg( 'px_aff', $row['code'], $row['booking_url'] ); }
  return $rows;
 }
 public static function save( array $data, int $id = 0 ) {
  $existing = $id ? Repository::find( 'affiliates', $id ) : null;
  if ( $id && ! $existing ) { return new WP_Error( 'not_found', 'Affiliate not found.', array( 'status' => 404 ) ); }
  $name = sanitize_text_field( $data['name'] ?? '' ); $url = esc_url_raw( $data['booking_url'] ?? '', array( 'http', 'https' ) );
  if ( ! $name || strlen( $name ) > 191 || ! $url || strlen( $url ) > 2000 || strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) !== strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) || wp_parse_url( $url, PHP_URL_USER ) || wp_parse_url( $url, PHP_URL_PASS ) ) { return new WP_Error( 'invalid', 'Enter a name and an HTTP or HTTPS booking page URL on this site.', array( 'status' => 400 ) ); }
  $url = remove_query_arg( 'px_aff', $url );
  $row = array( 'name' => $name, 'booking_url' => $url, 'enabled' => ! array_key_exists( 'enabled', $data ) || ! empty( $data['enabled'] ) ? 1 : 0 );
  if ( ! $id ) { $row['code'] = bin2hex( random_bytes( 16 ) ); $row['created_at'] = Repository::now(); }
  $result = $id ? Repository::update( 'affiliates', $row, array( 'id' => $id ) ) : Repository::insert( 'affiliates', $row );
  if ( false === $result || ( ! $id && ! $result ) ) { return new WP_Error( 'save_failed', 'Could not save affiliate.', array( 'status' => 500 ) ); }
  return Repository::find( 'affiliates', $id ?: $result );
 }
 public static function bookings( int $id ): array {
  return array_map( static fn( $row ) => Appointments::hydrate( (int) $row['id'] ), Repository::all( 'appointments', array( 'affiliate_id' => $id ), 'start_datetime DESC, id DESC' ) );
 }
 public static function delete( array $ids ) {
  global $wpdb;
  $ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
  if ( ! $ids ) { return new WP_Error( 'invalid', 'Select affiliate links to delete.', array( 'status' => 400 ) ); }
  $in = implode( ',', $ids );
  $wpdb->query( 'START TRANSACTION' );
  if ( false === $wpdb->query( 'UPDATE ' . Schema::table( 'appointments' ) . " SET affiliate_id = NULL WHERE affiliate_id IN ($in)" ) || false === $wpdb->query( 'DELETE FROM ' . Schema::table( 'affiliates' ) . " WHERE id IN ($in)" ) ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'delete_failed', 'Could not delete affiliates.', array( 'status' => 500 ) ); }
  $wpdb->query( 'COMMIT' );
  return array( 'ok' => true );
 }
}
