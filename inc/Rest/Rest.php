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
	public function register(): void {
		$admin = array( $this, 'admin_perm' );

		$this->crud( 'locations', 'locations', $admin );
		$this->crud( 'services', 'services', $admin );
		$this->crud( 'categories', 'service_categories', $admin );
		$this->crud( 'staff', 'staff', $admin );
		$this->crud( 'extras', 'extras', $admin );
		$this->crud( 'coupons', 'coupons', $admin );
		$this->crud( 'customers', 'customers', $admin );
		$this->crud( 'custom-fields', 'custom_fields', $admin );
		$this->crud( 'notifications', 'notifications', $admin );

		register_rest_route(
			self::NS,
			'/staff',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'candidate_staff' ),
				'permission_callback' => $admin,
			)
		);

		register_rest_route(
			self::NS,
			'/availability',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'availability' ),
				'permission_callback' => $admin,
			)
		);

		register_rest_route(
			self::NS,
			'/appointments',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_appointments' ),
					'permission_callback' => $admin,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_appointment' ),
					'permission_callback' => $admin,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/appointments/(?P<id>\d+)',
			array(
				'methods'             => 'PATCH',
				'callback'            => array( $this, 'patch_appointment' ),
				'permission_callback' => $admin,
			)
		);

		register_rest_route(
			self::NS,
			'/payments',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_payments' ),
				'permission_callback' => $admin,
			)
		);

		register_rest_route(
			self::NS,
			'/payments/local',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'pay_local' ),
				'permission_callback' => $admin,
			)
		);

		register_rest_route(
			self::NS,
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => $admin,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save_settings' ),
					'permission_callback' => $admin,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/dashboard',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'dashboard' ),
				'permission_callback' => $admin,
			)
		);

		register_rest_route(
			self::NS,
			'/staff/(?P<id>\d+)/schedule',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_schedule' ),
					'permission_callback' => $admin,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save_schedule' ),
					'permission_callback' => $admin,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/staff/(?P<id>\d+)/days-off',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_days_off' ),
					'permission_callback' => $admin,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'add_day_off' ),
					'permission_callback' => $admin,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/staff-days-off/(?P<id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_day_off' ),
				'permission_callback' => $admin,
			)
		);

		register_rest_route(
			self::NS,
			'/staff/(?P<id>\d+)/services',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_staff_services' ),
					'permission_callback' => $admin,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save_staff_services' ),
					'permission_callback' => $admin,
				),
			)
		);
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
	private function crud( string $route, string $table, callable $permission ): void {
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
					'callback'            => function ( $request ) use ( $table ) {
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

		$hydrated['amount'] = (float) ( $payload['amount'] ?? 0 );

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
		return rest_ensure_response(
			Repository::all( 'staff_schedule', array( 'staff_id' => (int) $request['id'] ) )
		);
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

		return rest_ensure_response(
			Repository::all( 'staff_schedule', array( 'staff_id' => $staff_id ) )
		);
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
}
