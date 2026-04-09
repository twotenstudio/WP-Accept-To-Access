<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPATA_Admin {

    private $option_key = 'wpata_settings';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    public function add_settings_page() {
        add_options_page(
            __( 'Accept to Access', 'wp-accept-to-access' ),
            __( 'Accept to Access', 'wp-accept-to-access' ),
            'manage_options',
            'wp-accept-to-access',
            [ $this, 'render_settings_page' ]
        );
    }

    public function register_settings() {
        register_setting( 'wpata_settings_group', $this->option_key, [
            'sanitize_callback' => [ $this, 'sanitize_settings' ],
        ] );
    }

    public function enqueue_admin_assets( $hook ) {
        if ( $hook !== 'settings_page_wp-accept-to-access' ) {
            return;
        }
        wp_enqueue_style( 'wpata-admin', WPATA_URL . 'assets/css/admin.css', [], WPATA_VERSION );
    }

    public function sanitize_settings( $input ) {
        $clean = [];

        $clean['enabled']         = ! empty( $input['enabled'] ) ? 1 : 0;
        $clean['cookie_days']     = isset( $input['cookie_days'] ) ? absint( $input['cookie_days'] ) : 30;
        $clean['terms_page_id']   = isset( $input['terms_page_id'] ) ? absint( $input['terms_page_id'] ) : 0;
        $clean['bypass_loggedin'] = ! empty( $input['bypass_loggedin'] ) ? 1 : 0;

        // Default language fields.
        $clean['title']       = isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : '';
        $clean['message']     = isset( $input['message'] ) ? wp_kses_post( $input['message'] ) : '';
        $clean['button_text'] = isset( $input['button_text'] ) ? sanitize_text_field( $input['button_text'] ) : '';

        // Per-language fields.
        $languages = wpata_get_languages();
        if ( ! empty( $languages ) ) {
            foreach ( $languages as $code => $lang ) {
                $clean['title_' . $code]       = isset( $input[ 'title_' . $code ] ) ? sanitize_text_field( $input[ 'title_' . $code ] ) : '';
                $clean['message_' . $code]     = isset( $input[ 'message_' . $code ] ) ? wp_kses_post( $input[ 'message_' . $code ] ) : '';
                $clean['button_text_' . $code] = isset( $input[ 'button_text_' . $code ] ) ? sanitize_text_field( $input[ 'button_text_' . $code ] ) : '';
            }
        }

        return $clean;
    }

    /**
     * Get saved settings with defaults.
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

    public function render_settings_page() {
        $settings  = $this->get_settings();
        $languages = wpata_get_languages();
        $default_lang = wpata_get_default_language();
        ?>
        <div class="wrap wpata-admin">
            <h1><?php esc_html_e( 'Accept to Access', 'wp-accept-to-access' ); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields( 'wpata_settings_group' ); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Enable', 'wp-accept-to-access' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( $this->option_key ); ?>[enabled]" value="1" <?php checked( $settings['enabled'], 1 ); ?>>
                                <?php esc_html_e( 'Show the accept popup to visitors', 'wp-accept-to-access' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Bypass for logged-in users', 'wp-accept-to-access' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( $this->option_key ); ?>[bypass_loggedin]" value="1" <?php checked( $settings['bypass_loggedin'], 1 ); ?>>
                                <?php esc_html_e( 'Skip the popup for logged-in users', 'wp-accept-to-access' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="wpata_cookie_days"><?php esc_html_e( 'Cookie duration (days)', 'wp-accept-to-access' ); ?></label>
                        </th>
                        <td>
                            <input id="wpata_cookie_days" type="number" min="1" name="<?php echo esc_attr( $this->option_key ); ?>[cookie_days]" value="<?php echo esc_attr( $settings['cookie_days'] ); ?>" class="small-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="wpata_terms_page"><?php esc_html_e( 'Terms & Conditions page', 'wp-accept-to-access' ); ?></label>
                        </th>
                        <td>
                            <?php
                            wp_dropdown_pages( [
                                'name'             => $this->option_key . '[terms_page_id]',
                                'id'               => 'wpata_terms_page',
                                'selected'         => $settings['terms_page_id'],
                                'show_option_none' => __( '— Select —', 'wp-accept-to-access' ),
                                'option_none_value' => 0,
                            ] );
                            ?>
                            <p class="description"><?php esc_html_e( 'Visitors can access this page without accepting. Use {terms_link} in your message to insert a link to it.', 'wp-accept-to-access' ); ?></p>
                        </td>
                    </tr>
                </table>

                <hr>

                <?php if ( ! empty( $languages ) ) : ?>
                    <h2><?php esc_html_e( 'Popup Content', 'wp-accept-to-access' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Configure the popup text for each language. If a translation is left blank, the default language will be used as fallback.', 'wp-accept-to-access' ); ?></p>

                    <div class="wpata-language-tabs">
                        <nav class="wpata-tab-nav">
                            <?php foreach ( $languages as $code => $lang ) : ?>
                                <a href="#wpata-lang-<?php echo esc_attr( $code ); ?>"
                                   class="wpata-tab-link <?php echo $code === $default_lang ? 'active' : ''; ?>">
                                    <?php echo esc_html( $lang['native_name'] ); ?>
                                    <?php if ( $code === $default_lang ) : ?>
                                        <span class="wpata-default-badge"><?php esc_html_e( 'default', 'wp-accept-to-access' ); ?></span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </nav>

                        <?php foreach ( $languages as $code => $lang ) :
                            $suffix    = '_' . $code;
                            $title_val = isset( $settings[ 'title' . $suffix ] ) ? $settings[ 'title' . $suffix ] : '';
                            $msg_val   = isset( $settings[ 'message' . $suffix ] ) ? $settings[ 'message' . $suffix ] : '';
                            $btn_val   = isset( $settings[ 'button_text' . $suffix ] ) ? $settings[ 'button_text' . $suffix ] : '';
                        ?>
                            <div id="wpata-lang-<?php echo esc_attr( $code ); ?>"
                                 class="wpata-tab-panel <?php echo $code === $default_lang ? 'active' : ''; ?>">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">
                                            <label><?php esc_html_e( 'Title', 'wp-accept-to-access' ); ?></label>
                                        </th>
                                        <td>
                                            <input type="text" class="regular-text"
                                                   name="<?php echo esc_attr( $this->option_key . '[title' . $suffix . ']' ); ?>"
                                                   value="<?php echo esc_attr( $title_val ); ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label><?php esc_html_e( 'Message', 'wp-accept-to-access' ); ?></label>
                                        </th>
                                        <td>
                                            <textarea rows="4" class="large-text"
                                                      name="<?php echo esc_attr( $this->option_key . '[message' . $suffix . ']' ); ?>"><?php echo esc_textarea( $msg_val ); ?></textarea>
                                            <p class="description"><?php esc_html_e( 'Use {terms_link} to insert a link to the Terms & Conditions page. HTML is allowed.', 'wp-accept-to-access' ); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label><?php esc_html_e( 'Button text', 'wp-accept-to-access' ); ?></label>
                                        </th>
                                        <td>
                                            <input type="text" class="regular-text"
                                                   name="<?php echo esc_attr( $this->option_key . '[button_text' . $suffix . ']' ); ?>"
                                                   value="<?php echo esc_attr( $btn_val ); ?>">
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php else : ?>
                    <h2><?php esc_html_e( 'Popup Content', 'wp-accept-to-access' ); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label><?php esc_html_e( 'Title', 'wp-accept-to-access' ); ?></label></th>
                            <td>
                                <input type="text" class="regular-text"
                                       name="<?php echo esc_attr( $this->option_key ); ?>[title]"
                                       value="<?php echo esc_attr( $settings['title'] ); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label><?php esc_html_e( 'Message', 'wp-accept-to-access' ); ?></label></th>
                            <td>
                                <textarea rows="4" class="large-text"
                                          name="<?php echo esc_attr( $this->option_key ); ?>[message]"><?php echo esc_textarea( $settings['message'] ); ?></textarea>
                                <p class="description"><?php esc_html_e( 'Use {terms_link} to insert a link to the Terms & Conditions page. HTML is allowed.', 'wp-accept-to-access' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label><?php esc_html_e( 'Button text', 'wp-accept-to-access' ); ?></label></th>
                            <td>
                                <input type="text" class="regular-text"
                                       name="<?php echo esc_attr( $this->option_key ); ?>[button_text]"
                                       value="<?php echo esc_attr( $settings['button_text'] ); ?>">
                            </td>
                        </tr>
                    </table>
                <?php endif; ?>

                <?php submit_button(); ?>
            </form>
        </div>

        <script>
        (function() {
            const links = document.querySelectorAll('.wpata-tab-link');
            const panels = document.querySelectorAll('.wpata-tab-panel');

            links.forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    links.forEach(function(l) { l.classList.remove('active'); });
                    panels.forEach(function(p) { p.classList.remove('active'); });
                    link.classList.add('active');
                    document.querySelector(link.getAttribute('href')).classList.add('active');
                });
            });
        })();
        </script>
        <?php
    }
}
