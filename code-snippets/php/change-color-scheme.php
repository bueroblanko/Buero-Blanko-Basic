<?php
// 
// <p>This code adds a new color scheme to the WordPress admin area.</p>
// 1. Farb-Schema erstellen
function wpacg_bb_admin_color_scheme() {
    // Setze den absoluten Link zur CSS-Datei
    $css_url = '/wp-content/plugins/buero-blanko-basic/includes/bb.css'; // Ändere dies entsprechend

    // BB
    wp_admin_css_color('bb', __('BB'), $css_url, array('#1c1c1c', '#fff', '#7535e0', '#000000'));
}
add_action('admin_init', 'wpacg_bb_admin_color_scheme');

add_filter( 'get_user_option_admin_color', function($result) { return 'bb'; }, 99); add_action( 'user_register', function($user_id) { wp_update_user( array ('ID' => $user_id, 'admin_color' => 'bb') ); }, 10, 1);
