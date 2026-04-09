<?php
/**
 * Plugin Name: WP Accept to Access
 * Description: Blocks site access until the user accepts terms via a popup overlay.
 * Version: 1.0.0
 * Author: TwoTen Studio
 * Text Domain: wp-accept-to-access
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WPATA_VERSION', '1.0.0' );
define( 'WPATA_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPATA_URL', plugin_dir_url( __FILE__ ) );

require_once WPATA_PATH . 'includes/class-admin.php';
require_once WPATA_PATH . 'includes/class-frontend.php';

/**
 * Get all active WPML languages, or an empty array if WPML is not active.
 */
function wpata_get_languages() {
    $languages = apply_filters( 'wpml_active_languages', null, [] );
    return is_array( $languages ) ? $languages : [];
}

/**
 * Get the current WPML language code, or empty string.
 */
function wpata_get_current_language() {
    return (string) apply_filters( 'wpml_current_language', '' );
}

/**
 * Get the default WPML language code, or empty string.
 */
function wpata_get_default_language() {
    return (string) apply_filters( 'wpml_default_language', '' );
}

new WPATA_Admin();
new WPATA_Frontend();
