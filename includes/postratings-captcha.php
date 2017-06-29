<?php

function recaptcha_is_op() {
    if (! is_plugin_active('contact-form-7/wp-contact-form-7.php')) {
        return false;
    }

    $recaptcha = WPCF7_RECAPTCHA::get_instance();
    if ( ! $recaptcha->is_active() ) {
        return false;
    }

    return $recaptcha->get_sitekey();
}

function recaptcha_is_enabled() {
    if (! ($opt = get_option('postratings_options')) ) {
        return false;
    }
    if (! isset($opt['recaptcha']) || ! $opt['recaptcha']) {
        return false;
    }

    return true;
}

function is_human() {
    $recaptcha = WPCF7_RECAPTCHA::get_instance();
    $response_token = wpcf7_recaptcha_response();
    // return true for mutants and humans
    return $recaptcha->verify( $response_token );
}


add_action( 'wp_enqueue_scripts', 'google_recaptcha' );
function google_recaptcha() {
    if ( ! recaptcha_is_enabled() ) return;
    if ( ! recaptcha_is_op() ) return;
    wp_register_script( 'google-recaptcha',
                        add_query_arg( [ 'onload' => 'recaptchaCallback', 'render' => 'explicit' ],
                                       'https://www.google.com/recaptcha/api.js' ),
                        [], '2.0', true );
}
