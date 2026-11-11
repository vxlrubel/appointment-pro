<?php
namespace Praxleo\Appointment\PublicSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Frontend {
	public function boot() {
		add_shortcode( 'px-appointment', array( $this, 'shortcode' ) );
		add_shortcode( 'px-appointment-service', array( $this, 'service_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_assets' ) );
		add_action( 'init', array( $this, 'handle_cancel' ) );
	}

	public function maybe_assets() {
		global $post;
		$need = false;
		if ( $post && ( has_shortcode( $post->post_content, 'px-appointment' ) || has_shortcode( $post->post_content, 'px-appointment-service' ) ) ) {
			$need = true;
		}
		if ( $post && function_exists( 'has_block' ) && has_block( 'px-appointment/booking', $post ) ) {
			$need = true;
		}
		if ( isset( $_GET['px_appointment_cancel'] ) ) {
			$need = true;
		}
		if ( $need ) {
			self::enqueue();
		}
	}

	public static function enqueue() {
		$js_path  = PX_APPOINTMENT_PATH . 'lib/public/public.js';
		$css_path = PX_APPOINTMENT_PATH . 'lib/public/public.css';
		$js_ver   = file_exists( $js_path ) ? (string) filemtime( $js_path ) : PX_APPOINTMENT_VERSION;
		$css_ver  = file_exists( $css_path ) ? (string) filemtime( $css_path ) : PX_APPOINTMENT_VERSION;
		wp_enqueue_style( 'px-appointment-public', PX_APPOINTMENT_URL . 'lib/public/public.css', array(), $css_ver );
		wp_enqueue_script( 'px-appointment-public', PX_APPOINTMENT_URL . 'lib/public/public.js', array(), $js_ver, true );
		wp_localize_script(
			'px-appointment-public',
			'pxAppointmentPublic',
			array(
				'root'  => esc_url_raw( rest_url( 'px-appointment/v1/' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			)
		);
		add_filter( 'script_loader_tag', array( __CLASS__, 'classic_script_tag' ), 10, 2 );
	}

	public static function classic_script_tag( $tag, $handle ) {
		if ( 'px-appointment-public' !== $handle ) {
			return $tag;
		}
		$tag = str_replace( array( ' type="module"', " type='module'" ), '', $tag );
		if ( false === strpos( $tag, 'defer' ) ) {
			$tag = str_replace( ' src=', ' defer src=', $tag );
		}
		return $tag;
	}

	public function shortcode( $atts ) {
		self::enqueue();
		$atts = shortcode_atts(
			array(
				'location_id' => 0,
				'service_id'  => 0,
				'staff_id'    => 0,
			),
			$atts,
			'px-appointment'
		);
		return self::mount( $atts );
	}

    /** Render an independently mounted service booking button; never emit unescaped attributes. */
    public function service_shortcode( $atts ) {
        $atts = shortcode_atts( array( 'id' => 0, 'text' => '' ), $atts, 'px-appointment-service' );
        $id = absint( $atts['id'] );
        $service = $id ? \Praxleo\Appointment\Database\Repository::find( 'services', $id ) : null;
        if ( ! $service || empty( $service['enabled'] ) ) { return ''; }
        self::enqueue();
        $text = trim( (string) $atts['text'] );
        if ( '' === $text ) { $text = $service['name']; }
        return sprintf(
            '<button type="button" class="pxb-btn pxb-service-booking-button" data-px-service-booking="%d" aria-haspopup="dialog" aria-expanded="false">%s</button>',
            $id,
            esc_html( $text )
        );
    }

	public static function render_block( $attrs ) {
		self::enqueue();
		return self::mount(
			array(
				'location_id' => (int) ( $attrs['locationId'] ?? 0 ),
				'service_id'  => (int) ( $attrs['serviceId'] ?? 0 ),
				'staff_id'    => (int) ( $attrs['staffId'] ?? 0 ),
			)
		);
	}

	public static function mount( $atts ) {
		return sprintf(
			'<div class="px-appointment-booking" id="px-appointment-booking-app" data-location-id="%d" data-service-id="%d" data-staff-id="%d"></div>',
			(int) $atts['location_id'],
			(int) $atts['service_id'],
			(int) $atts['staff_id']
		);
	}

	public function handle_cancel() {
		if ( empty( $_GET['px_appointment_cancel'] ) ) {
			return;
		}
		$token = sanitize_text_field( wp_unslash( $_GET['px_appointment_cancel'] ) );
		$row   = \Praxleo\Appointment\Domain\Appointments::by_token( $token );
		if ( $row ) {
			\Praxleo\Appointment\Domain\Appointments::set_status( $row['appointment']['id'], 'cancelled' );
		}
	}
}
