<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPATA_Frontend {

    private $option_key = 'wpata_settings';

    public function __construct() {
        add_action( 'template_redirect', [ $this, 'handle_accept' ], 5 );
        add_action( 'template_redirect', [ $this, 'maybe_block' ] );
    }

    /**
     * Handle the accept form POST — runs before maybe_block so the
     * cookie is set before we decide whether to block.
     */
    public function handle_accept() {
        if ( empty( $_POST['wpata_accept'] ) || ! wp_verify_nonce( $_POST['_wpata_nonce'], 'wpata_accept' ) ) {
            return;
        }

        $settings = $this->get_settings();
        $days     = max( 1, (int) $settings['cookie_days'] );

        setcookie( 'wpata_accepted', '1', time() + ( $days * DAY_IN_SECONDS ), '/', '', is_ssl(), false );

        // Redirect back to the same page (GET) to avoid form resubmission.
        wp_safe_redirect( remove_query_arg( '_wpata', wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
        exit;
    }

    private function get_settings() {
        $defaults = [
            'enabled'         => 0,
            'cookie_days'     => 30,
            'terms_page_id'   => 0,
            'bypass_loggedin' => 0,
            'button_colour'   => '#0073aa',
            'title'           => '',
            'message'         => '',
            'button_text'     => 'Accept',
        ];
        return wp_parse_args( get_option( $this->option_key, [] ), $defaults );
    }

    /**
     * Should the current request be blocked?
     */
    private function should_block() {
        $settings = $this->get_settings();

        if ( empty( $settings['enabled'] ) ) {
            return false;
        }

        if ( ! empty( $settings['bypass_loggedin'] ) && is_user_logged_in() ) {
            return false;
        }

        // Never block admin, AJAX, REST, cron, or CLI.
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
            return false;
        }

        // Don't block the T&C page itself (or its WPML translations).
        if ( ! empty( $settings['terms_page_id'] ) ) {
            if ( is_page( $settings['terms_page_id'] ) ) {
                return false;
            }

            $current_lang = wpata_get_current_language();
            if ( $current_lang ) {
                $translated_id = apply_filters( 'wpml_object_id', $settings['terms_page_id'], 'page', false, $current_lang );
                if ( $translated_id && is_page( $translated_id ) ) {
                    return false;
                }
            }
        }

        // Already accepted.
        if ( ! empty( $_COOKIE['wpata_accepted'] ) ) {
            return false;
        }

        return true;
    }

    /**
     * Get localised content for the popup.
     */
    private function get_content() {
        $settings     = $this->get_settings();
        $languages    = wpata_get_languages();
        $current_lang = wpata_get_current_language();
        $default_lang = wpata_get_default_language();

        if ( ! empty( $languages ) && $current_lang ) {
            $title = ! empty( $settings[ 'title_' . $current_lang ] )
                ? $settings[ 'title_' . $current_lang ]
                : ( ! empty( $settings[ 'title_' . $default_lang ] ) ? $settings[ 'title_' . $default_lang ] : $settings['title'] );

            $message = ! empty( $settings[ 'message_' . $current_lang ] )
                ? $settings[ 'message_' . $current_lang ]
                : ( ! empty( $settings[ 'message_' . $default_lang ] ) ? $settings[ 'message_' . $default_lang ] : $settings['message'] );

            $button_text = ! empty( $settings[ 'button_text_' . $current_lang ] )
                ? $settings[ 'button_text_' . $current_lang ]
                : ( ! empty( $settings[ 'button_text_' . $default_lang ] ) ? $settings[ 'button_text_' . $default_lang ] : $settings['button_text'] );
        } else {
            $title       = $settings['title'];
            $message     = $settings['message'];
            $button_text = $settings['button_text'];
        }

        return [
            'title'       => $title,
            'message'     => $message,
            'button_text' => $button_text ?: __( 'Accept', 'wp-accept-to-access' ),
        ];
    }

    /**
     * Intercept the request and serve the acceptance page instead.
     */
    public function maybe_block() {
        if ( ! $this->should_block() ) {
            return;
        }

        $content      = $this->get_content();
        $settings     = $this->get_settings();
        $btn_colour   = ! empty( $settings['button_colour'] ) ? $settings['button_colour'] : '#0073aa';
        $hover_colour = $this->darken_hex( $btn_colour, 20 );

        // Prevent caching of the blocked page.
        nocache_headers();

        ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html( $content['title'] ?: get_bloginfo( 'name' ) ); ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: #f0f0f0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            color: #1a1a1a;
        }

        .wpata-popup {
            background: #fff;
            border-radius: 8px;
            padding: 48px 40px;
            max-width: 520px;
            width: 90%;
            text-align: center;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.12);
        }

        .wpata-popup__title {
            margin: 0 0 16px;
            font-size: 24px;
            font-weight: 700;
            line-height: 1.3;
        }

        .wpata-popup__message {
            margin: 0 0 28px;
            font-size: 15px;
            line-height: 1.6;
            color: #444;
        }

        .wpata-popup__message a {
            color: #0073aa;
            text-decoration: underline;
        }

        .wpata-popup__message a:hover {
            color: #005177;
        }

        .wpata-popup__button {
            display: inline-block;
            padding: 12px 40px;
            font-size: 16px;
            font-weight: 600;
            color: #fff;
            background: <?php echo esc_attr( $btn_colour ); ?>;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .wpata-popup__button:hover,
        .wpata-popup__button:focus {
            background: <?php echo esc_attr( $hover_colour ); ?>;
            outline: none;
        }
    </style>
</head>
<body>
    <div class="wpata-popup" role="dialog" aria-modal="true" aria-labelledby="wpata-title">
        <?php if ( ! empty( $content['title'] ) ) : ?>
            <h2 id="wpata-title" class="wpata-popup__title"><?php echo esc_html( $content['title'] ); ?></h2>
        <?php endif; ?>

        <?php if ( ! empty( $content['message'] ) ) : ?>
            <div class="wpata-popup__message"><?php echo wp_kses_post( $content['message'] ); ?></div>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field( 'wpata_accept', '_wpata_nonce' ); ?>
            <input type="hidden" name="wpata_accept" value="1">
            <button class="wpata-popup__button" type="submit">
                <?php echo esc_html( $content['button_text'] ); ?>
            </button>
        </form>
    </div>
</body>
</html><?php
        exit;
    }

    /**
     * Darken a hex colour by a percentage.
     */
    private function darken_hex( $hex, $percent ) {
        $hex = ltrim( $hex, '#' );
        $r   = max( 0, hexdec( substr( $hex, 0, 2 ) ) - (int) ( 255 * $percent / 100 ) );
        $g   = max( 0, hexdec( substr( $hex, 2, 2 ) ) - (int) ( 255 * $percent / 100 ) );
        $b   = max( 0, hexdec( substr( $hex, 4, 2 ) ) - (int) ( 255 * $percent / 100 ) );
        return sprintf( '#%02x%02x%02x', $r, $g, $b );
    }

}
