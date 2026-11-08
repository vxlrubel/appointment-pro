<?php
/**
 * Customer helpers.
 *
 * @package Praxleo_Appointment
 */

namespace Praxleo\Appointment\Domain;

use WP_Error;
use Praxleo\Appointment\Database\Repository;
use Praxleo\Appointment\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Customer helpers.
 */
class Customers {

	/**
	 * Create or update a customer by e-mail address.
	 *
	 * @param array<string, mixed> $data Customer data.
	 * @param bool                 $create_account Whether to create a WordPress user.
	 * @return int|WP_Error Customer ID.
	 */
	public static function upsert( array $data, bool $create_account = false ) {
		global $wpdb;

		$email = sanitize_email( $data['email'] ?? '' );
		$name  = sanitize_text_field( $data['full_name'] ?? '' );
		$phone = sanitize_text_field( $data['phone'] ?? '' );

		if ( ! $email || ! $name ) {
			return new WP_Error( 'invalid', __( 'Name and email are required.', 'appointment-pro' ), array( 'status' => 400 ) );
		}

		$table      = Schema::table( 'customers' );
		$row        = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE email = %s", $email ), ARRAY_A );
		$wp_user_id = null;

		if ( $create_account ) {
			$existing = get_user_by( 'email', $email );

			if ( $existing ) {
				$wp_user_id = (int) $existing->ID;
			} else {
				$user_id = wp_insert_user(
					array(
						'user_login'   => sanitize_user( current( explode( '@', $email ) ) . '_' . wp_generate_password( 4, false ), true ),
						'user_email'   => $email,
						'user_pass'    => wp_generate_password( 20 ),
						'display_name' => $name,
						'role'         => 'subscriber',
					)
				);

				if ( ! is_wp_error( $user_id ) ) {
					$wp_user_id = (int) $user_id;
				}
			}
		}

		if ( $row ) {
			Repository::update(
				'customers',
				array(
					'full_name'  => $name,
					'phone'      => $phone,
					'wp_user_id' => $wp_user_id ?: $row['wp_user_id'],
				),
				array( 'id' => $row['id'] )
			);

			return (int) $row['id'];
		}

		return Repository::insert(
			'customers',
			array(
				'wp_user_id' => $wp_user_id,
				'full_name'  => $name,
				'email'      => $email,
				'phone'      => $phone,
				'notes'      => '',
				'created_at' => Repository::now(),
			)
		);
	}
}
