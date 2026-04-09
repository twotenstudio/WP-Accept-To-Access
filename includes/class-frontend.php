<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPATA_Frontend {

    private $option_key = 'wpata_settings';

    public function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_footer', [ $this, 'render_popup' ] );
        add_action( 'wp_ajax_wpata_accept', [ $this, 'handle_accept' ] );
        add_action( 'wp_ajax_nopriv_wpata_accept', [ $this, 'handle_accept' ] );
    }

    /**
     * Get saved settings.
     */
    private function get_settings() {
        $defaults = [
            'enabled'         => 0,
            'cookie_days'     => 30,
            'terms_page_id'   => 0,
            'bypass_loggedin' => 0,
            'title'           => '',
            'message'         => '',
            'button_text'     => 'Accept',
        ];
        return wp_parse_args( get_option( $this->option_key, [] ), $defaults );
    }

    /**
     * Should the popup be shown to the current visitor?
     */
    private function should_show() {
        $settings = $this->get_settings();

        if ( empty( $settings['enabled'] ) ) {
            return false;
        }

        // Bypass for logged-in users if configured.
        if ( ! empty( $settings['bypass_loggedin'] ) && is_user_logged_in() ) {
            return false;
        }

        // Don't show on the admin side.
        if ( is_admin() ) {
            return false;
        }

        // Don't block the T&C page itself.
        if ( ! empty( $settings['terms_page_id'] ) && is_page( $settings['terms_page_id'] ) ) {
            return false;
        }

        // Check for WPML-translated T&C page.
        if ( ! empty( $settings['terms_page_id'] ) ) {
            $current_lang  = wpata_get_current_language();
            $translated_id = apply_filters( 'wpml_object_id', $settings['terms_page_id'], 'page', false, $current_lang );
            if ( $translated_id && is_page( $translated_id ) ) {
                return false;
            }
        }

        // Already accepted (cookie check).
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

        // If WPML is active, try the current language fields first, then fall back to default.
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

        // Replace {terms_link} placeholder with actual link.
        if ( ! empty( $settings['terms_page_id'] ) ) {
            $terms_page_id = $settings['terms_page_id'];

            // Get translated T&C page if WPML is active.
            if ( $current_lang ) {
                $translated_id = apply_filters( 'wpml_object_id', $terms_page_id, 'page', true, $current_lang );
                if ( $translated_id ) {
                    $terms_page_id = $translated_id;
                }
            }

            $terms_url   = get_permalink( $terms_page_id );
            $terms_title = get_the_title( $terms_page_id );
            $terms_link  = '<a href="' . esc_url( $terms_url ) . '" target="_blank" rel="noopener">' . esc_html( $terms_title ) . '</a>';
            $message     = str_replace( '{terms_link}', $terms_link, $message );
        }

        return [
            'title'       => $title,
            'message'     => $message,
            'button_text' => $button_text ?: __( 'Accept', 'wp-accept-to-access' ),
        ];
    }

    public function enqueue_assets() {
        if ( ! $this->should_show() ) {
            return;
        }

        wp_enqueue_style( 'wpata-popup', WPATA_URL . 'assets/css/popup.css', [], WPATA_VERSION );
        wp_enqueue_script( 'wpata-popup', WPATA_URL . 'assets/js/popup.js', [], WPATA_VERSION, true );

        $settings = $this->get_settings();

        wp_localize_script( 'wpata-popup', 'wpata', [
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'wpata_accept' ),
            'cookieDays' => (int) $settings['cookie_days'],
        ] );
    }

    public function render_popup() {
        if ( ! $this->should_show() ) {
            return;
        }

        $content = $this->get_content();
        ?>
        <div id="wpata-overlay" role="dialog" aria-modal="true" aria-labelledby="wpata-title">
            <div class="wpata-popup">
                <?php if ( ! empty( $content['title'] ) ) : ?>
                    <h2 id="wpata-title" class="wpata-popup__title"><?php echo esc_html( $content['title'] ); ?></h2>
                <?php endif; ?>

                <?php if ( ! empty( $content['message'] ) ) : ?>
                    <div class="wpata-popup__message"><?php echo wp_kses_post( $content['message'] ); ?></div>
                <?php endif; ?>

                <button id="wpata-accept" class="wpata-popup__button" type="button">
                    <?php echo esc_html( $content['button_text'] ); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler — sets the cookie server-side as well.
     */
    public function handle_accept() {
        check_ajax_referer( 'wpata_accept', 'nonce' );

        $settings = $this->get_settings();
        $days     = max( 1, (int) $settings['cookie_days'] );

        setcookie( 'wpata_accepted', '1', time() + ( $days * DAY_IN_SECONDS ), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );

        wp_send_json_success();
    }
}
