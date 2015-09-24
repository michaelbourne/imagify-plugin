<?php
defined( 'ABSPATH' ) or die( 'Cheatin\' uh?' );

/**
 * Fix the capability for our capacity filter hook
 *
 * @since 1.0
 */
add_filter( 'option_page_capability_imagify', '_imagify_correct_capability_for_options_page' );
function _imagify_correct_capability_for_options_page( $capability ) {
	$cap = ( imagify_is_active_for_network() ) ? 'manage_network_options' : 'manage_options';
	return apply_filters( 'imagify_capacity', $cap );
}

/**
 * Tell to WordPress to be confident with our setting, we are clean!
 *
 * @since 1.0
 */
add_action( 'admin_init', '_imagify_register_setting' );
function _imagify_register_setting()
{
	register_setting( 'imagify', 'imagify_settings' );
}

/**
 * Filter specific options before its value is (maybe) serialized and updated.
 *
 * @since 1.0
 */
add_filter( 'pre_update_option_' . IMAGIFY_SETTINGS_SLUG, '_imagify_pre_update_option', 10, 2 );
function _imagify_pre_update_option( $value, $old_value ) {
	// Store all sizes even if one of them isn't checked
	$value['disallowed-sizes'] = array();
	
	if ( isset( $value['sizes'] ) ) {
		foreach( $value['sizes'] as $size_key => $size_value ) {
			if ( strpos( $size_key , '-hidden' ) ) {
				$key = str_replace( '-hidden', '', $size_key );
				if ( ! isset( $value['sizes'][ $key ] ) ) {
					$value['disallowed-sizes'][ $key ] = '1';
				}
			}
		}
	}
	
	unset( $value['sizes'] );
	return $value;
}

/**
 * Used to launch some actions after saving the options
 *
 * @since 1.0
 */
add_action( 'update_option_' . IMAGIFY_SETTINGS_SLUG, '_imagify_after_save_options', 10, 2 );
add_action( 'update_site_option_' . IMAGIFY_SETTINGS_SLUG, '_imagify_after_save_options', 10, 2 );
function _imagify_after_save_options( $oldvalue, $value )
{
	if ( ! ( (bool) $oldvalue && (bool) $value ) ) {
		return;
	}

	if ( ! isset( $oldvalue['api_key'] ) || $oldvalue['api_key'] != $value['api_key'] )  {
		$api  = new Imagify();

		if ( is_wp_error( $api->getUser() ) ) {
			imagify_renew_notice( 'wrong-api-key' );
			delete_site_transient( 'imagify_check_licence_1' );
		} else {
			imagify_dismiss_notice( 'wrong-api-key' );
		}
	}
}


if ( imagify_is_active_for_network() ) :

/**
 * !options.php do not handle site options. Let's use admin-post.php for multisite installations.
 *
 * @since 1.0
 */
add_action( 'admin_post_update', '_imagify_update_site_option_on_network' );
function _imagify_update_site_option_on_network() {
	$option_group = IMAGIFY_SLUG;

	if ( ! isset( $_POST['option_page'] ) || $_POST['option_page'] !== $option_group ) {
		return;
	}

	$capability = apply_filters( "option_page_capability_{$option_group}", 'manage_network_options' );

	if ( ! current_user_can( $capability ) ) {
		wp_die( __( 'Cheatin&#8217; uh?' ), 403 );
	}

	check_admin_referer( $option_group . '-options' );

	$whitelist_options = apply_filters( 'whitelist_options', array() );

	if ( ! isset( $whitelist_options[ $option_group ] ) ) {
		wp_die( __( '<strong>ERROR</strong>: options page not found.' ) );
	}

	$options = $whitelist_options[ $option_group ];

	if ( $options ) {

		foreach ( $options as $option ) {
			$option = trim( $option );
			$value  = null;

			if ( isset( $_POST[ $option ] ) ) {
				$value = $_POST[ $option ];
				if ( ! is_array( $value ) ) {
					$value = trim( $value );
				}
				$value = wp_unslash( $value );
			}

			update_site_option( $option, $value );
		}

	}
	
	/**
	 * Redirect back to the settings page that was submitted
	 */
	$goback = add_query_arg( 'settings-updated', 'true',  wp_get_referer() );
	wp_redirect( $goback );	
	exit;
}

endif;