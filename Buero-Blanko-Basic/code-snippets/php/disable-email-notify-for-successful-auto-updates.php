<?php
// Disables email notifications for automatic updates of themes and plugins. Also disables email notifications for successful core updates.
// Source: https://webberzone.com/disable-wordpress-auto-update-emails/#method-1-disable-autoupdate-emails-using-code
// Disable auto-update emails for WordPress core updates.
add_filter( 'auto_core_update_send_email', 'wz_stop_core_update_emails', 10, 4 );

function wz_stop_core_update_emails( $send, $type, $core_update, $result ) {
    if ( ! empty( $type ) && 'success' === $type ) {
        return false;
    }

    return true;
}

// Disable auto-update emails for WordPress themes and plugins.
add_filter( 'auto_theme_update_send_email', '__return_false' );
add_filter( 'auto_plugin_update_send_email', '__return_false' );