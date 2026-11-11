<?php
/**
 * REST API endpoints used by the admin application.
 *
 * @package Praxleo_Appointment
 */

namespace Praxleo\Appointment\Rest;

use Throwable;
use WP_Error;
use Praxleo\Appointment\Database\Repository;
use Praxleo\Appointment\Database\Schema;
use Praxleo\Appointment\Domain\Appointments;
use Praxleo\Appointment\Domain\Availability;
use Praxleo\Appointment\Domain\Settings;
use Praxleo\Appointment\Payments\Payments;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the plugin REST routes.
 */
class Rest {

	/**
	 * REST namespace.
	 */
	public const NS = 'px-appointment/v1';

	/**
	 * Register all routes.
	 *
	 * @return void
	 */
	public function register() {
		$pub  = array( $this, 'public_perm' );
		$adm  = array( $this, 'admin_perm' );

        register_rest_route( self::NS, '/admin/business-hours', array(
            array( 'methods' => 'GET', 'permission_callback' => $adm, 'callback' => static fn() => \Praxleo\Appointment\Domain\BusinessHours::all() ),
            array( 'methods' => 'POST', 'permission_callback' => $adm, 'callback' => static fn( $req ) => \Praxleo\Appointment\Domain\BusinessHours::save( $req->get_json_params() ) ),
        ) );
        register_rest_route( self::NS, '/admin/affiliates', array(
            array( 'methods' => 'GET', 'permission_callback' => $adm, 'callback' => static fn() => \Praxleo\Appointment\Domain\Affiliates::all() ),
            array( 'methods' => 'POST', 'permission_callback' => $adm, 'callback' => static fn( $req ) => \Praxleo\Appointment\Domain\Affiliates::save( (array) $req->get_json_params() ) ),
        ) );
        register_rest_route( self::NS, '/admin/affiliates/bulk-delete', array( 'methods' => 'POST', 'permission_callback' => $adm, 'callback' => static function( $req ) {
            $ids = $req->get_param( 'ids' );
            if ( ! is_array( $ids ) || ! $ids || array_filter( $ids, static fn( $id ) => ! is_scalar( $id ) || is_bool( $id ) || ! ctype_digit( (string) $id ) || (int) $id < 1 ) ) { return new WP_Error( 'invalid', 'Select valid affiliate IDs.', array( 'status' => 400 ) ); }
            return \Praxleo\Appointment\Domain\Affiliates::delete( $ids );
        } ) );
        register_rest_route( self::NS, '/admin/affiliates/(?P<id>\d+)', array(
            array( 'methods' => 'PUT', 'permission_callback' => $adm, 'callback' => static fn( $req ) => \Praxleo\Appointment\Domain\Affiliates::save( (array) $req->get_json_params(), (int) $req['id'] ) ),
            array( 'methods' => 'DELETE', 'permission_callback' => $adm, 'callback' => static fn( $req ) => \Praxleo\Appointment\Domain\Affiliates::delete( array( (int) $req['id'] ) ) ),
        ) );
        register_rest_route( self::NS, '/admin/affiliates/(?P<id>\d+)/bookings', array( 'methods' => 'GET', 'permission_callback' => $adm, 'callback' => static fn( $req ) => \Praxleo\Appointment\Domain\Affiliates::bookings( (int) $req['id'] ) ) );
		$this->crud( 'locations', 'locations', $adm );
		$this->crud( 'services', 'services', $adm );
		$this->crud( 'categories', 'service_categories', $adm );
		$this->crud( 'staff', 'staff', $adm );
		$this->crud( 'extras', 'extras', $adm );
		$this->crud( 'coupons', 'coupons', $adm );
		$this->crud( 'customers', 'customers', $adm );
		$this->crud( 'custom-fields', 'custom_fields', $adm );
		$this->crud( 'notifications', 'notifications', $adm, false );

		register_rest_route( self::NS, '/admin/holidays', array(
			array( 'methods' => 'GET', 'permission_callback' => $adm, 'callback' => static fn() => rest_ensure_response( Repository::all( 'holidays', array(), 'holiday_date ASC, id ASC' ) ) ),
			array( 'methods' => 'POST', 'permission_callback' => $adm, 'callback' => static function( $req ) { $result = \Praxleo\Appointment\Domain\Holidays::save( (array) $req->get_json_params() ); return is_wp_error( $result ) ? $result : rest_ensure_response( $result ); } ),
		) );
		register_rest_route( self::NS, '/admin/holidays/(?P<id>\d+)', array(
			array( 'methods' => 'PUT', 'permission_callback' => $adm, 'callback' => static function( $req ) { $result = \Praxleo\Appointment\Domain\Holidays::save( (array) $req->get_json_params(), (int) $req['id'] ); return is_wp_error( $result ) ? $result : rest_ensure_response( $result ); } ),
			array( 'methods' => 'DELETE', 'permission_callback' => $adm, 'callback' => static function( $req ) { $result = \Praxleo\Appointment\Domain\Holidays::delete( (int) $req['id'] ); return false === $result ? new WP_Error( 'delete_failed', 'Could not delete holiday.', array( 'status' => 500 ) ) : rest_ensure_response( array( 'ok' => true ) ); } ),
		) );

		register_rest_route( self::NS, '/locations', array(
			'methods' => 'GET', 'callback' => array( $this, 'public_locations' ), 'permission_callback' => $pub,
		) );
		register_rest_route( self::NS, '/services', array(
			'methods' => 'GET', 'callback' => array( $this, 'public_services' ), 'permission_callback' => $pub,
		) );
		register_rest_route( self::NS, '/staff', array(
			'methods' => 'GET', 'callback' => array( $this, 'public_staff' ), 'permission_callback' => $pub,
		) );
		register_rest_route( self::NS, '/availability', array(
			'methods' => 'GET', 'callback' => array( $this, 'availability' ), 'permission_callback' => $pub,
		) );
		register_rest_route( self::NS, '/availability/dates', array(
			'methods' => 'GET', 'callback' => array( $this, 'availability_dates' ), 'permission_callback' => $pub,
		) );
		register_rest_route( self::NS, '/extras', array(
			'methods' => 'GET', 'callback' => array( $this, 'public_extras' ), 'permission_callback' => $pub,
		) );
		register_rest_route( self::NS, '/custom-fields', array(
			'methods' => 'GET', 'callback' => array( $this, 'public_fields' ), 'permission_callback' => $pub,
		) );
		register_rest_route( self::NS, '/coupons/validate', array(
			'methods' => 'POST', 'callback' => array( $this, 'validate_coupon' ), 'permission_callback' => $pub,
		) );
		register_rest_route( self::NS, '/appointments', array(
			array( 'methods' => 'POST', 'callback' => array( $this, 'create_appointment' ), 'permission_callback' => $pub ),
			array( 'methods' => 'GET', 'callback' => array( $this, 'list_appointments' ), 'permission_callback' => $adm ),
		) );
		register_rest_route( self::NS, '/appointments/(?P<id>\d+)', array(
			array( 'methods' => 'PATCH', 'callback' => array( $this, 'patch_appointment' ), 'permission_callback' => $adm ),
			array( 'methods' => 'DELETE', 'callback' => array( $this, 'delete_appointment' ), 'permission_callback' => $adm ),
		) );
		register_rest_route( self::NS, '/appointments/bulk-delete', array(
			'methods' => 'POST', 'callback' => array( $this, 'bulk_delete_appointments' ), 'permission_callback' => $adm,
		) );
		register_rest_route( self::NS, '/appointments/(?P<token>[a-f0-9]{32})', array(
			'methods' => 'GET', 'callback' => array( $this, 'get_by_token' ), 'permission_callback' => $pub,
		) );
		register_rest_route( self::NS, '/appointments/(?P<token>[a-f0-9]{32})/cancel', array(
			'methods' => 'POST', 'callback' => array( $this, 'cancel_by_token' ), 'permission_callback' => $pub,
		) );
		register_rest_route( self::NS, '/payments/local', array(
			'methods' => 'POST', 'callback' => array( $this, 'pay_local' ), 'permission_callback' => $pub,
		) );
		register_rest_route( self::NS, '/payments/stripe/intent', array(
			'methods' => 'POST', 'callback' => array( $this, 'stripe_intent' ), 'permission_callback' => $pub,
		) );
		register_rest_route( self::NS, '/payments/paypal/order', array(
			'methods' => 'POST', 'callback' => array( $this, 'paypal_order' ), 'permission_callback' => $pub,
		) );
		register_rest_route( self::NS, '/payments/paypal/capture', array(
			'methods' => 'POST', 'callback' => array( $this, 'paypal_capture' ), 'permission_callback' => $pub,
		) );
		register_rest_route( self::NS, '/payments/woocommerce/order', array(
			'methods' => 'POST', 'callback' => array( $this, 'woo_order' ), 'permission_callback' => $pub,
		) );
		register_rest_route( self::NS, '/webhooks/stripe', array(
			'methods' => 'POST', 'callback' => array( $this, 'stripe_hook' ), 'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/webhooks/paypal', array(
			'methods' => 'POST', 'callback' => array( $this, 'paypal_hook' ), 'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/settings', array(
			array( 'methods' => 'GET', 'callback' => array( $this, 'get_settings' ), 'permission_callback' => $adm ),
			array( 'methods' => 'POST', 'callback' => array( $this, 'save_settings' ), 'permission_callback' => $adm ),
		) );
		register_rest_route( self::NS, '/dashboard', array(
			'methods' => 'GET', 'callback' => array( $this, 'dashboard' ), 'permission_callback' => $adm,
		) );
		register_rest_route( self::NS, '/staff/(?P<id>\d+)/schedule', array(
			array( 'methods' => 'GET', 'callback' => array( $this, 'get_schedule' ), 'permission_callback' => $adm ),
			array( 'methods' => 'POST', 'callback' => array( $this, 'save_schedule' ), 'permission_callback' => $adm ),
		) );
		register_rest_route( self::NS, '/staff/(?P<id>\d+)/days-off', array(
			array( 'methods' => 'GET', 'callback' => array( $this, 'get_days_off' ), 'permission_callback' => $adm ),
			array( 'methods' => 'POST', 'callback' => array( $this, 'add_day_off' ), 'permission_callback' => $adm ),
		) );
		register_rest_route( self::NS, '/staff-days-off/(?P<id>\d+)', array(
			'methods' => 'DELETE', 'callback' => array( $this, 'delete_day_off' ), 'permission_callback' => $adm,
		) );
		register_rest_route( self::NS, '/staff/(?P<id>\d+)/services', array(
			array( 'methods' => 'GET', 'callback' => array( $this, 'get_staff_services' ), 'permission_callback' => $adm ),
			array( 'methods' => 'POST', 'callback' => array( $this, 'save_staff_services' ), 'permission_callback' => $adm ),
		) );
		register_rest_route( self::NS, '/payments', array(
			'methods' => 'GET', 'callback' => array( $this, 'list_payments' ), 'permission_callback' => $adm,
		) );
		register_rest_route( self::NS, '/payments/bulk-delete', array(
			'methods' => 'POST', 'callback' => array( $this, 'bulk_delete_payments' ), 'permission_callback' => $adm,
		) );
		register_rest_route( self::NS, '/config', array(
			'methods' => 'GET', 'callback' => array( $this, 'public_config' ), 'permission_callback' => $pub,
		) );
		register_rest_route( self::NS, '/admin/database/reset', array(
			'methods' => 'POST', 'callback' => array( $this, 'reset_database' ), 'permission_callback' => $adm,
		) );
		register_rest_route( self::NS, '/admin/database/demo', array(
			'methods' => 'POST', 'callback' => array( $this, 'insert_demo' ), 'permission_callback' => $adm,
		) );
		register_rest_route( self::NS, '/admin/database/export', array(
			'methods' => 'POST', 'callback' => array( $this, 'export_database' ), 'permission_callback' => $adm,
		) );
		register_rest_route( self::NS, '/admin/database/import', array(
			'methods' => 'POST', 'callback' => array( $this, 'import_database' ), 'permission_callback' => $adm,
		) );
	}

	/**
	 * Admin permission callback.
	 *
	 * @return bool
	 */
	public function admin_perm(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Register CRUD routes for a table.
	 *
	 * @param string   $route Route slug.
	 * @param string   $table Table suffix.
	 * @param callable $permission Permission callback.
	 * @return void
	 */
	private function crud( string $route, string $table, callable $permission, bool $allow_delete = true ): void {
		register_rest_route(
			self::NS,
			"/admin/$route",
			array(
				array(
					'methods'             => 'GET',
					'callback'            => function () use ( $table ) {
						return rest_ensure_response( Repository::all( $table ) );
					},
					'permission_callback' => $permission,
				),
				array(
					'methods'             => 'POST',
					'callback'            => function ( $request ) use ( $table ) {
						$data = $this->sanitize_row( $table, $request->get_json_params() );

						if ( 'customers' === $table && empty( $data['created_at'] ) ) {
							$data['created_at'] = Repository::now();
						}

						$id = Repository::insert( $table, $data );

						if ( ! $id ) {
							return new WP_Error( 'create_failed', __( 'Could not save the record.', 'appointment-pro' ), array( 'status' => 500 ) );
						}

						return rest_ensure_response( Repository::find( $table, $id ) );
					},
					'permission_callback' => $permission,
				),
			)
		);

		if ( $allow_delete ) {
			register_rest_route( self::NS, "/admin/$route/bulk-delete", array(
				'methods' => 'POST', 'permission_callback' => $permission,
				'callback' => function ( $request ) use ( $table ) {
					foreach ( array_unique( array_map( 'absint', (array) ( $this->json_body( $request )['ids'] ?? array() ) ) ) as $id ) {
						if ( $id ) { Repository::delete( $table, array( 'id' => $id ) ); }
					}
					return rest_ensure_response( array( 'ok' => true ) );
				},
			) );
		}

		register_rest_route(
			self::NS,
			"/admin/$route/(?P<id>\\d+)",
			array(
				array(
					'methods'             => 'GET',
					'callback'            => function ( $request ) use ( $table ) {
						return rest_ensure_response( Repository::find( $table, (int) $request['id'] ) );
					},
					'permission_callback' => $permission,
				),
				array(
					'methods'             => 'PUT',
					'callback'            => function ( $request ) use ( $table ) {
						$id   = (int) $request['id'];
						$data = $this->sanitize_row( $table, $request->get_json_params() );

						if ( $data ) {
							Repository::update( $table, $data, array( 'id' => $id ) );
						}

						return rest_ensure_response( Repository::find( $table, $id ) );
					},
					'permission_callback' => $permission,
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => function ( $request ) use ( $table, $allow_delete ) {
						if ( ! $allow_delete ) { return new WP_Error( 'forbidden', 'Notification templates cannot be deleted.', array( 'status' => 403 ) ); }
						Repository::delete( $table, array( 'id' => (int) $request['id'] ) );

						return rest_ensure_response( array( 'ok' => true ) );
					},
					'permission_callback' => $permission,
				),
			)
		);
	}

	/**
	 * Whitelisted columns per table.
	 *
	 * @param string $table Table suffix.
	 * @return array<int, string>
	 */
	private function columns( string $table ): array {
		$map = array(
			'locations'          => array( 'name', 'info', 'address', 'phone', 'position', 'enabled' ),
			'service_categories' => array( 'name', 'position' ),
			'services'           => array( 'category_id', 'name', 'duration_sec', 'price', 'padding_left_sec', 'padding_right_sec', 'color', 'info', 'enabled', 'position' ),
			'staff'              => array( 'wp_user_id', 'full_name', 'email', 'phone', 'info', 'color', 'enabled', 'position' ),
			'extras'             => array( 'service_id', 'title', 'duration_sec', 'price', 'max_quantity', 'enabled', 'position' ),
			'coupons'            => array( 'code', 'discount_type', 'discount_amount', 'usage_limit', 'used', 'min_amount', 'start_date', 'end_date', 'enabled' ),
			'customers'          => array( 'wp_user_id', 'full_name', 'email', 'phone', 'notes', 'created_at' ),
			'custom_fields'      => array( 'label', 'type', 'required', 'options_json', 'services_json', 'position', 'enabled' ),
			'notifications'      => array( 'event', 'enabled', 'subject', 'body_html' ),
		);

		return $map[ $table ] ?? array();
	}

	/**
	 * Sanitize a row before writing it to the database.
	 *
	 * @param string               $table Table suffix.
	 * @param array<string, mixed>|null $data Raw row data.
	 * @return array<string, mixed>
	 */
	private function sanitize_row( string $table, $data ): array {
		if ( ! is_array( $data ) ) {
			return array();
		}

		$allowed = $this->columns( $table );
		$ints    = array( 'service_id', 'category_id', 'staff_id', 'location_id', 'duration_sec', 'padding_left_sec', 'padding_right_sec', 'max_quantity', 'position', 'enabled', 'required', 'usage_limit', 'used', 'wp_user_id', 'weekday' );
		$floats  = array( 'price', 'discount_amount', 'min_amount' );
		$nullable = array( 'category_id', 'usage_limit', 'wp_user_id' );
		$out     = array();

		unset( $data['id'] );

		foreach ( $data as $key => $value ) {
			$key = sanitize_key( $key );

			if ( $allowed && ! in_array( $key, $allowed, true ) ) {
				continue;
			}

			if ( is_bool( $value ) ) {
				$out[ $key ] = $value ? 1 : 0;
			} elseif ( is_array( $value ) ) {
				$out[ $key ] = wp_json_encode( $value );
			} elseif ( in_array( $key, $ints, true ) ) {
				if ( '' === $value || null === $value ) {
					$out[ $key ] = in_array( $key, $nullable, true ) ? null : 0;
				} else {
					$out[ $key ] = (int) $value;
				}
			} elseif ( in_array( $key, array( 'start_date', 'end_date' ), true ) && ( '' === $value || null === $value ) ) {
				$out[ $key ] = null;
			} elseif ( in_array( $key, $floats, true ) ) {
				$out[ $key ] = ( '' === $value || null === $value ) ? 0 : (float) $value;
			} else {
				$out[ $key ] = is_string( $value ) ? wp_kses_post( $value ) : $value;
			}
		}

		return $out;
	}

	/**
	 * Return candidate staff for a service at a location.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function candidate_staff( $request ) {
		$rows = Availability::candidate_staff(
			(int) $request->get_param( 'location_id' ),
			(int) $request->get_param( 'service_id' ),
			(int) $request->get_param( 'staff_id' )
		);

		return rest_ensure_response( $rows );
	}

	/**
	 * Return available slots.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function availability( $request ) {
		$extras = $request->get_param( 'extras' );

		if ( is_string( $extras ) ) {
			$decoded = json_decode( $extras, true );
			$extras  = is_array( $decoded ) ? $decoded : array();
		}

		return rest_ensure_response(
			Availability::slots(
				array(
					'location_id' => (int) $request->get_param( 'location_id' ),
					'service_id'  => (int) $request->get_param( 'service_id' ),
					'staff_id'    => (int) $request->get_param( 'staff_id' ),
					'date'        => sanitize_text_field( (string) $request->get_param( 'date' ) ),
					'extras'      => is_array( $extras ) ? $extras : array(),
				)
			)
		);
	}

	/**
	 * List appointments.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_appointments( $request ) {
		return rest_ensure_response(
			Appointments::list(
				array(
					'from'     => $request->get_param( 'from' ),
					'to'       => $request->get_param( 'to' ),
					'staff_id' => $request->get_param( 'staff_id' ),
					'status'   => $request->get_param( 'status' ),
				)
			)
		);
	}

	/**
	 * Create an appointment.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function create_appointment( $request ) {
		$payload = $this->json_body( $request );
		$method = $payload['payment_method'] ?? '';
		if ( ! current_user_can( 'manage_options' ) && ( ! in_array( $method, array( 'local', 'stripe', 'paypal', 'woocommerce' ), true ) || ! Settings::get( $method . '_enabled' ) ) ) { return new WP_Error( 'disabled', 'Payment method unavailable.', array( 'status' => 400 ) ); }

		if ( empty( $payload['idempotency_key'] ) ) {
			$payload['idempotency_key'] = $request->get_header( 'Idempotency-Key' );
		}

		try {
			$result = Appointments::create( $payload, current_user_can( 'manage_options' ) );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$method = sanitize_text_field( $payload['payment_method'] ?? '' );

			if ( 'local' === $method ) {
				$paid = Payments::complete_local( $result, (float) ( $result['amount'] ?? 0 ) );

				if ( is_wp_error( $paid ) ) {
					return $paid;
				}

				return rest_ensure_response( $paid );
			}

			return rest_ensure_response( $result );
		} catch ( Throwable $e ) {
			return new WP_Error( 'create_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Update an appointment status or start time.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function patch_appointment( $request ) {
		$id      = (int) $request['id'];
		$payload = $request->get_json_params();

		if ( ! is_array( $payload ) ) {
			$payload = array();
		}

		if ( isset( $payload['status'] ) ) {
			$result = Appointments::set_status( $id, sanitize_text_field( $payload['status'] ) );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		if ( isset( $payload['start'] ) ) {
			$result = Appointments::reschedule(
				$id,
				sanitize_text_field( $payload['start'] ),
				(int) ( $payload['staff_id'] ?? 0 )
			);

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return rest_ensure_response( Appointments::hydrate( $id ) );
	}

	/**
	 * Complete a local payment.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function pay_local( $request ) {
		if ( ! current_user_can( 'manage_options' ) && ! Settings::get( 'local_enabled' ) ) { return new WP_Error( 'disabled', 'Payment method unavailable.', array( 'status' => 400 ) ); }
		try {
			$hydrated = $this->load_appointment_amount( $request );

			if ( is_wp_error( $hydrated ) ) {
				return $hydrated;
			}

			$result = Payments::complete_local( $hydrated, (float) $hydrated['amount'] );

			return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
		} catch ( Throwable $e ) {
			return new WP_Error( 'payment_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Build a hydrated appointment with an amount from the request body.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array<string, mixed>|WP_Error
	 */
	private function load_appointment_amount( $request ) {
		$payload        = $this->json_body( $request );
		$appointment_id = (int) ( $payload['appointment_id'] ?? 0 );

		if ( ! $appointment_id ) {
			return new WP_Error( 'invalid', __( 'Missing appointment.', 'appointment-pro' ), array( 'status' => 400 ) );
		}

		$hydrated = Appointments::hydrate( $appointment_id );

		if ( ! $hydrated ) {
			return new WP_Error( 'not_found', __( 'Appointment not found.', 'appointment-pro' ), array( 'status' => 404 ) );
		}

		if ( ! current_user_can( 'manage_options' ) && ! hash_equals( (string) ( $hydrated['customer_appointment']['token'] ?? '' ), (string) ( $payload['token'] ?? '' ) ) ) { return new WP_Error( 'forbidden', 'Invalid booking token.', array( 'status' => 403 ) ); }

		return $hydrated;
	}

	/**
	 * Parse the request body as an array.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	private function json_body( $request ): array {
		$payload = $request->get_json_params();

		if ( is_array( $payload ) && $payload ) {
			return $payload;
		}

		$raw = $request->get_body();

		if ( $raw ) {
			$decoded = json_decode( $raw, true );

			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		$params = $request->get_params();

		return is_array( $params ) ? $params : array();
	}

	/**
	 * Return dashboard totals and upcoming appointments.
	 *
	 * @return \WP_REST_Response
	 */
	public function dashboard() {
		global $wpdb;

		$appointments = Schema::table( 'appointments' );
		$payments     = Schema::table( 'payments' );
		$today        = gmdate( 'Y-m-d' );

		$counts = array(
			'today'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $appointments WHERE DATE(start_datetime) = %s AND status IN ('approved','pending','done')", $today ) ),
			'pending'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $appointments WHERE status = 'pending'" ),
			'approved' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $appointments WHERE status = 'approved'" ),
			'revenue'  => (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount),0) FROM $payments WHERE status = 'completed'" ),
		);

		$upcoming = Appointments::list(
			array(
				'from'   => gmdate( 'Y-m-d H:i:s' ),
				'status' => 'approved',
			)
		);

		return rest_ensure_response(
			array(
				'counts'     => $counts,
				'upcoming'   => array_slice( $upcoming, 0, 10 ),
				'mail_error' => get_option( 'px_appointment_mail_error', '' ),
			)
		);
	}

	/**
	 * Get a staff member's weekly schedule.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_schedule( $request ) {
		$rows = Repository::all( 'staff_schedule', array( 'staff_id' => (int) $request['id'] ) );
		foreach ( $rows as &$row ) { $row['breaks'] = Repository::all( 'staff_breaks', array( 'schedule_id' => (int) $row['id'] ) ); }
		return rest_ensure_response( $rows );
	}
	/**
	 * Replace a staff member's weekly schedule.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function save_schedule( $request ) {
		$staff_id = (int) $request['id'];
		$rows     = $request->get_json_params();

		foreach ( Repository::all( 'staff_schedule', array( 'staff_id' => $staff_id ) ) as $old ) { Repository::delete( 'staff_breaks', array( 'schedule_id' => (int) $old['id'] ) ); }
		Repository::delete( 'staff_schedule', array( 'staff_id' => $staff_id ) );

		foreach ( (array) $rows as $row ) {
			$schedule_id = Repository::insert(
				'staff_schedule',
				array(
					'staff_id'    => $staff_id,
					'location_id' => ! empty( $row['location_id'] ) ? (int) $row['location_id'] : null,
					'weekday'     => (int) $row['weekday'],
					'start_time'  => sanitize_text_field( $row['start_time'] ),
					'end_time'    => sanitize_text_field( $row['end_time'] ),
				)
			);

			foreach ( (array) ( $row['breaks'] ?? array() ) as $break ) {
				Repository::insert(
					'staff_breaks',
					array(
						'schedule_id' => $schedule_id,
						'start_time'  => sanitize_text_field( $break['start_time'] ),
						'end_time'    => sanitize_text_field( $break['end_time'] ),
					)
				);
			}
		}

		return $this->get_schedule( $request );
	}

	/**
	 * Get a staff member's days off.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_days_off( $request ) {
		return rest_ensure_response(
			Repository::all( 'staff_days_off', array( 'staff_id' => (int) $request['id'] ) )
		);
	}

	/**
	 * Add a day off for a staff member.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function add_day_off( $request ) {
		$payload = $request->get_json_params();

		$id = Repository::insert(
			'staff_days_off',
			array(
				'staff_id'       => (int) $request['id'],
				'start_datetime' => sanitize_text_field( $payload['start_datetime'] ?? '' ),
				'end_datetime'   => sanitize_text_field( $payload['end_datetime'] ?? '' ),
				'all_day'        => ! empty( $payload['all_day'] ) ? 1 : 0,
				'reason'         => sanitize_text_field( $payload['reason'] ?? '' ),
			)
		);

		return rest_ensure_response( Repository::find( 'staff_days_off', $id ) );
	}

	/**
	 * Delete a day off.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function delete_day_off( $request ) {
		Repository::delete( 'staff_days_off', array( 'id' => (int) $request['id'] ) );

		return rest_ensure_response( array( 'ok' => true ) );
	}

	/**
	 * Get the services assigned to a staff member.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_staff_services( $request ) {
		return rest_ensure_response(
			Repository::all( 'staff_services', array( 'staff_id' => (int) $request['id'] ) )
		);
	}

	/**
	 * Replace the services assigned to a staff member.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function save_staff_services( $request ) {
		$staff_id = (int) $request['id'];

		Repository::delete( 'staff_services', array( 'staff_id' => $staff_id ) );

		foreach ( (array) $request->get_json_params() as $row ) {
			Repository::insert(
				'staff_services',
				array(
					'staff_id'    => $staff_id,
					'service_id'  => (int) $row['service_id'],
					'location_id' => (int) $row['location_id'],
					'price'       => isset( $row['price'] ) && '' !== $row['price'] ? $row['price'] : null,
					'enabled'     => 1,
				)
			);
		}

		return rest_ensure_response(
			Repository::all( 'staff_services', array( 'staff_id' => $staff_id ) )
		);
	}

	/**
	 * List payments.
	 *
	 * @return \WP_REST_Response
	 */
	public function list_payments() {
		return rest_ensure_response( Repository::all( 'payments', array(), 'id DESC' ) );
	}

	/** Delete payment history records without deleting their appointments. */
	public function bulk_delete_payments( $request ) {
		global $wpdb;
		$payload = $this->json_body( $request );
		$ids = $payload['ids'] ?? null;
		if ( ! is_array( $ids ) || ! $ids ) {
			return new WP_Error( 'invalid_ids', 'Select at least one payment record.', array( 'status' => 400 ) );
		}
		$validated = array();
		foreach ( $ids as $id ) {
			$value = is_bool( $id ) ? false : filter_var( $id, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
			if ( false === $value ) { return new WP_Error( 'invalid_ids', 'Payment IDs must be positive integers.', array( 'status' => 400 ) ); }
			$validated[] = $value;
		}
		$ids = array_values( array_unique( $validated ) );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$table = Schema::table( 'payments' );
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE id IN ($placeholders)", $ids ) );
		if ( false === $deleted ) { return new WP_Error( 'delete_failed', 'Could not delete the selected payments.', array( 'status' => 500 ) ); }
		return rest_ensure_response( array( 'ok' => true, 'deleted' => (int) $deleted ) );
	}

	/**
	 * Return plugin settings.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_settings() {
		return rest_ensure_response( Settings::get() );
	}

	/**
	 * Save plugin settings.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function save_settings( $request ) {
		$payload = $request->get_json_params();

		return rest_ensure_response( Settings::save( is_array( $payload ) ? $payload : array() ) );
	}

	public function public_perm() {
		return true;
	}

	public function public_config() {
		return rest_ensure_response( \Praxleo\Appointment\Domain\Settings::public_subset() );
	}

	public function reset_database() {
		return rest_ensure_response( \Praxleo\Appointment\Database\Demo::reset() );
	}

	public function insert_demo() {
		$res = \Praxleo\Appointment\Database\Demo::insert();
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return rest_ensure_response( $res );
	}

	public function export_database() {
		return rest_ensure_response( \Praxleo\Appointment\Database\Transfer::export() );
	}

	public function import_database( $req ) {
		$p   = $this->json_body( $req );
		$csv = isset( $p['csv'] ) ? (string) $p['csv'] : '';
		$res = \Praxleo\Appointment\Database\Transfer::import( $csv );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return rest_ensure_response( $res );
	}

	/** Return actual slot counts for each date in a single calendar month. */
	public function availability_dates( $request ) {
		$timezone = \Praxleo\Appointment\Domain\Mailer::timezone();
		$now = new \DateTimeImmutable( 'now', $timezone );
		$month = (string) ( $request->get_param( 'month' ) ?: $now->format( 'Y-m' ) );
		$start = \DateTimeImmutable::createFromFormat( '!Y-m', $month, $timezone );
		if ( ! $start || $start->format( 'Y-m' ) !== $month ) {
			return new WP_Error( 'invalid_month', 'Use a valid month in YYYY-MM format.', array( 'status' => 400 ) );
		}
		$minimum = $now->modify( '+' . max( 0, (int) Settings::get( 'min_lead_hours', 1 ) ) . ' hours' )->format( 'Y-m-d' );
		$maximum = $now->modify( '+' . max( 0, (int) Settings::get( 'max_lead_days', 60 ) ) . ' days' )->format( 'Y-m-d' );
		$extras = $request->get_param( 'extras' );
		if ( is_string( $extras ) ) { $extras = json_decode( $extras, true ); }
		$args = array( 'location_id' => absint( $request->get_param( 'location_id' ) ), 'service_id' => absint( $request->get_param( 'service_id' ) ), 'staff_id' => absint( $request->get_param( 'staff_id' ) ), 'extras' => is_array( $extras ) ? $extras : array() );
		$dates = array();
		for ( $day = $start; $day->format( 'Y-m' ) === $month; $day = $day->modify( '+1 day' ) ) {
			$date = $day->format( 'Y-m-d' );
			$dates[ $date ] = ( $date < $minimum || $date > $maximum ) ? 0 : count( Availability::slots( array_merge( $args, array( 'date' => $date ) ) ) );
		}
		return rest_ensure_response( array( 'month' => $month, 'dates' => $dates, 'today' => $now->format( 'Y-m-d' ), 'min_date' => $minimum, 'max_date' => $maximum ) );
	}

	public function public_locations() {
		return rest_ensure_response( \Praxleo\Appointment\Database\Repository::all( 'locations', array( 'enabled' => 1 ), 'position ASC, id ASC' ) );
	}

	public function public_services( $req ) {
		global $wpdb;
		$location_id = absint( $req->get_param( 'location_id' ) );
		$services = Repository::all( 'services', array( 'enabled' => 1 ), 'position ASC, id ASC' );
		$categories = Repository::all( 'service_categories', array(), 'position ASC, id ASC' );
		$links = Schema::table( 'staff_services' );
		$staff = Schema::table( 'staff' );
		$locations = Schema::table( 'locations' );
		$assignments = $wpdb->get_results( "SELECT DISTINCT ss.service_id, ss.location_id FROM $links ss INNER JOIN $staff s ON s.id = ss.staff_id AND s.enabled = 1 INNER JOIN $locations l ON l.id = ss.location_id AND l.enabled = 1 WHERE ss.enabled = 1", ARRAY_A );
		$location_map = array();
		foreach ( $assignments as $assignment ) {
			$location_map[ (int) $assignment['service_id'] ][] = (int) $assignment['location_id'];
		}
		foreach ( $services as &$service ) {
			$service['location_ids'] = $location_map[ (int) $service['id'] ] ?? array();
		}
		unset( $service );
		if ( $location_id ) {
			$services = array_values( array_filter( $services, static fn( $service ) => in_array( $location_id, $service['location_ids'], true ) ) );
		}
		return rest_ensure_response( array( 'services' => $services, 'categories' => $categories ) );
	}

	public function public_staff( $req ) {
		$location_id = (int) $req->get_param( 'location_id' );
		$service_id  = (int) $req->get_param( 'service_id' );
		$rows        = \Praxleo\Appointment\Domain\Availability::candidate_staff( $location_id, $service_id, 0 );
		return rest_ensure_response( $rows );
	}

	public function public_extras( $req ) {
		$sid = (int) $req->get_param( 'service_id' );
		return rest_ensure_response( \Praxleo\Appointment\Database\Repository::all( 'extras', array( 'service_id' => $sid, 'enabled' => 1 ), 'position ASC' ) );
	}

	public function public_fields( $req ) {
		$sid   = (int) $req->get_param( 'service_id' );
		$all   = \Praxleo\Appointment\Database\Repository::all( 'custom_fields', array( 'enabled' => 1 ), 'position ASC' );
		$out   = array();
		foreach ( $all as $f ) {
			$services = json_decode( $f['services_json'] ?: '[]', true );
			if ( ! $services || in_array( $sid, array_map( 'intval', $services ), true ) ) {
				$out[] = $f;
			}
		}
		return rest_ensure_response( $out );
	}

	public function validate_coupon( $req ) {
		$p   = $req->get_json_params();
		$res = \Praxleo\Appointment\Domain\Coupons::validate( $p['code'] ?? '', (int) ( $p['service_id'] ?? 0 ), (float) ( $p['amount'] ?? 0 ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return rest_ensure_response( $res );
	}

	public function delete_appointment( $req ) {
		\Praxleo\Appointment\Domain\Appointments::delete( (int) $req['id'] );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	public function bulk_delete_appointments( $req ) {
		$p   = $this->json_body( $req );
		$ids = isset( $p['ids'] ) ? (array) $p['ids'] : array();
		$n   = 0;
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( $id ) {
				\Praxleo\Appointment\Domain\Appointments::delete( $id );
				$n++;
			}
		}
		return rest_ensure_response( array( 'ok' => true, 'deleted' => $n ) );
	}

	public function get_by_token( $req ) {
		$row = \Praxleo\Appointment\Domain\Appointments::by_token( $req['token'] );
		if ( ! $row ) {
			return new WP_Error( 'not_found', 'Not found', array( 'status' => 404 ) );
		}
		return rest_ensure_response( $row );
	}

	public function cancel_by_token( $req ) {
		$row = \Praxleo\Appointment\Domain\Appointments::by_token( $req['token'] );
		if ( ! $row ) {
			return new WP_Error( 'not_found', 'Not found', array( 'status' => 404 ) );
		}
		return rest_ensure_response( \Praxleo\Appointment\Domain\Appointments::set_status( $row['appointment']['id'], 'cancelled' ) );
	}


	public function stripe_intent( $req ) {
		if ( ! current_user_can( 'manage_options' ) && ! Settings::get( 'stripe_enabled' ) ) { return new WP_Error( 'disabled', 'Payment method unavailable.', array( 'status' => 400 ) ); }
		$hydr = $this->load_appointment_amount( $req );
		if ( is_wp_error( $hydr ) ) {
			return $hydr;
		}
		$res = \Praxleo\Appointment\Payments\Payments::stripe_intent( $hydr, $hydr['amount'] );
		return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
	}

	public function paypal_order( $req ) {
		if ( ! current_user_can( 'manage_options' ) && ! Settings::get( 'paypal_enabled' ) ) { return new WP_Error( 'disabled', 'Payment method unavailable.', array( 'status' => 400 ) ); }
		$hydr = $this->load_appointment_amount( $req );
		if ( is_wp_error( $hydr ) ) {
			return $hydr;
		}
		$res = \Praxleo\Appointment\Payments\Payments::paypal_order( $hydr, $hydr['amount'] );
		return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
	}

	public function paypal_capture( $req ) {
		$hydrated = $this->load_appointment_amount( $req );
		if ( is_wp_error( $hydrated ) ) { return $hydrated; }
		$order_id = sanitize_text_field( $this->json_body( $req )['order_id'] ?? '' );
		$matches = array_filter( $hydrated['payments'], static function ( $payment ) use ( $order_id ) { return $payment['type'] === 'paypal' && ( json_decode( $payment['details_json'] ?: '{}', true )['order_id'] ?? '' ) === $order_id; } );
		if ( ! $order_id || ! $matches ) { return new WP_Error( 'invalid', 'Invalid payment order.', array( 'status' => 400 ) ); }
		$res = Payments::paypal_capture( $order_id );
		return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
	}
	public function woo_order( $req ) {
		if ( ! current_user_can( 'manage_options' ) && ! Settings::get( 'woocommerce_enabled' ) ) { return new WP_Error( 'disabled', 'Payment method unavailable.', array( 'status' => 400 ) ); }
		$hydr = $this->load_appointment_amount( $req );
		if ( is_wp_error( $hydr ) ) {
			return $hydr;
		}
		$res = \Praxleo\Appointment\Payments\Payments::woo_order( $hydr, $hydr['amount'] );
		return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
	}

	public function stripe_hook( $req ) {
		$res = \Praxleo\Appointment\Payments\Payments::handle_stripe_webhook( $req->get_body(), $req->get_header( 'stripe-signature' ) );
		return is_wp_error( $res ) ? $res : rest_ensure_response( array( 'ok' => true ) );
	}

	public function paypal_hook( $req ) {
		$res = \Praxleo\Appointment\Payments\Payments::handle_paypal_webhook( $req->get_body(), array_combine( array( 'auth_algo', 'cert_url', 'transmission_id', 'transmission_sig', 'transmission_time' ), array_map( static fn( $key ) => $req->get_header( 'paypal-' . str_replace( '_', '-', $key ) ), array( 'auth_algo', 'cert_url', 'transmission_id', 'transmission_sig', 'transmission_time' ) ) ) );
		return is_wp_error( $res ) ? $res : rest_ensure_response( array( 'ok' => true ) );
	}
}
