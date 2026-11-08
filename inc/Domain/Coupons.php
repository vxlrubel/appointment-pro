<?php
/**
 * Coupon validation and helpers.
 *
 * @package Praxleo_Appointment
 */

namespace Praxleo\Appointment\Domain;

use WP_Error;
use Praxleo\Appointment\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Coupon helpers.
 */
class Coupons {

	/**
	 * Validate a coupon code.
	 *
	 * @param string $code Coupon code.
	 * @param int    $service_id Service ID.
	 * @param float  $amount Amount before discount.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function validate( string $code, int $service_id, float $amount ) {
		global $wpdb;

		$code = strtoupper( sanitize_text_field( $code ) );

		if ( ! $code ) {
			return new WP_Error( 'invalid', __( 'Enter a coupon code.', 'appointment-pro' ), array( 'status' => 400 ) );
		}

		$table = Schema::table( 'coupons' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE code = %s", $code ), ARRAY_A );

		if ( ! $row || ! $row['enabled'] ) {
			return new WP_Error( 'invalid', __( 'This coupon is not valid.', 'appointment-pro' ), array( 'status' => 400 ) );
		}

		$today = gmdate( 'Y-m-d' );

		if ( $row['start_date'] && $today < $row['start_date'] ) {
			return new WP_Error( 'invalid', __( 'This coupon is not active yet.', 'appointment-pro' ), array( 'status' => 400 ) );
		}

		if ( $row['end_date'] && $today > $row['end_date'] ) {
			return new WP_Error( 'invalid', __( 'This coupon has expired.', 'appointment-pro' ), array( 'status' => 400 ) );
		}

		if ( null !== $row['usage_limit'] && (int) $row['used'] >= (int) $row['usage_limit'] ) {
			return new WP_Error( 'invalid', __( 'This coupon has reached its usage limit.', 'appointment-pro' ), array( 'status' => 400 ) );
		}

		if ( $amount < (float) $row['min_amount'] ) {
			return new WP_Error( 'invalid', __( 'Order total is below the coupon minimum.', 'appointment-pro' ), array( 'status' => 400 ) );
		}

		$junction = Schema::table( 'coupon_services' );
		$allowed  = $wpdb->get_col( $wpdb->prepare( "SELECT service_id FROM $junction WHERE coupon_id = %d", $row['id'] ) );

		if ( $allowed && ! in_array( $service_id, array_map( 'intval', $allowed ), true ) ) {
			return new WP_Error( 'invalid', __( 'This coupon does not apply to the selected service.', 'appointment-pro' ), array( 'status' => 400 ) );
		}

		$discount = self::discount_amount( $row, $amount );

		return array(
			'coupon'   => $row,
			'discount' => $discount,
			'total'    => max( 0, round( $amount - $discount, 2 ) ),
		);
	}

	/**
	 * Calculate the discount for a coupon.
	 *
	 * @param array<string, mixed> $coupon Coupon row.
	 * @param float                $amount Amount before discount.
	 * @return float
	 */
	public static function discount_amount( array $coupon, float $amount ): float {
		if ( 'percent' === $coupon['discount_type'] ) {
			return round( $amount * ( (float) $coupon['discount_amount'] / 100 ), 2 );
		}

		return min( $amount, (float) $coupon['discount_amount'] );
	}

	/**
	 * Increment the usage counter for a coupon.
	 *
	 * @param int $id Coupon ID.
	 * @return void
	 */
	public static function increment( int $id ): void {
		global $wpdb;

		$table = Schema::table( 'coupons' );

		$wpdb->query( $wpdb->prepare( "UPDATE $table SET used = used + 1 WHERE id = %d", $id ) );
	}
}
