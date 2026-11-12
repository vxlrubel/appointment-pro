<?php
/** Admin cache preferences and revision tracking. */
namespace Praxleo\Appointment\Admin;

use WP_Error;
use Praxleo\Appointment\Rest\Rest;

defined( 'ABSPATH' ) || exit;

class Cache {

	public const ENABLED_OPTION = 'px_appointment_admin_cache_enabled';
	public const VERSION_OPTION = 'px_appointment_admin_cache_version';

	public static function state(): array {
		return array(
			'enabled' => (bool) get_option( self::ENABLED_OPTION, false ),
			'version' => (string) get_option( self::VERSION_OPTION, '0' ),
		);
	}

	public static function purge(): void {
		update_option( self::VERSION_OPTION, wp_generate_uuid4(), false );
	}

	public static function register( callable $permission ): void {
		register_rest_route( Rest::NS, '/admin/cache', array(
			array( 'methods' => 'GET', 'permission_callback' => $permission, 'callback' => static fn() => self::state() ),
			array( 'methods' => 'POST', 'permission_callback' => $permission, 'callback' => static function( $request ) {
				$body = $request->get_json_params();
				if ( ! is_array( $body ) || ! isset( $body['enabled'] ) || ! is_bool( $body['enabled'] ) ) {
					return new WP_Error( 'invalid_cache_setting', 'Enable cache must be true or false.', array( 'status' => 400 ) );
				}
				update_option( self::ENABLED_OPTION, $body['enabled'], false );
				self::purge();
				return self::state();
			} ),
			array( 'methods' => 'DELETE', 'permission_callback' => $permission, 'callback' => static function() {
				self::purge();
				return self::state();
			} ),
		) );
		add_filter( 'rest_request_after_callbacks', array( self::class, 'after_request' ), 10, 3 );
	}

	/** Includes changes from public bookings, cancellations and payment webhooks. */
	public static function after_request( $response, $handler, $request ) {
		$prefix = '/' . Rest::NS . '/';
		if ( ! str_starts_with( $request->get_route(), $prefix ) ) {
			return $response;
		}
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$response = rest_ensure_response( $response );
		$path = substr( $request->get_route(), strlen( $prefix ) );
		if ( ! in_array( $request->get_method(), array( 'GET', 'HEAD', 'OPTIONS' ), true )
			&& $response->get_status() < 400
			&& ! in_array( $path, array( 'admin/cache', 'coupons/validate', 'admin/database/export' ), true ) ) {
			self::purge();
		}
		// Caching is controlled in admin memory. Never cache booking responses in HTTP caches.
		$response->header( 'Cache-Control', 'no-store, private' );
		$response->header( 'X-PX-Cache-Version', self::state()['version'] );
		return $response;
	}
}
