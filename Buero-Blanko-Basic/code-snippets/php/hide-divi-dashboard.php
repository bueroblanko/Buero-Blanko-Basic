<?php
// 
// <p>The code removes the Divi Dashboard menu from the WordPress admin panel.</p>
// Hide Dashboard code snippet
add_action( 'init', function() {
    // Restrict Page to access using direct link
    global $pagenow;
    $page = ! empty( $_GET['page'] ) ? $_GET['page'] : '';
    if (!empty($page) && $page === 'et_onboarding' && !empty($pagenow) && $pagenow === 'admin.php') {
        wp_die( esc_attr( "You don't have permission to access this page"));
    }
    // Enqueue CSS To Hide Divi Dashboard Option & Enqueue JS To Change Tab When Click Divi in Dashboard
    add_action('admin_enqueue_scripts', function() {
        // CSS
        $hideDiviDashboardCSS = "#toplevel_page_et_divi_options ul.wp-submenu li a[href='admin.php?page=et_onboarding'] {display: none!important;}";
        wp_register_style('pac-da-hide-divi-dashboard-option', false, [], '1.0.0');
        wp_enqueue_style('pac-da-hide-divi-dashboard-option');
        wp_add_inline_style('pac-da-hide-divi-dashboard-option', $hideDiviDashboardCSS);
        // JS
        ob_start();
        ?> jQuery(document).ready(function(){jQuery('a.wp-has-submenu[href="admin.php?page=et_onboarding"]').attr("href","admin.php?page=et_divi_options")}); <?php
        $hideDiviDashboardJS = ob_get_contents();
        ob_end_clean();
        wp_register_script('pac-da-change-divi-dashboard-option', '', ['jquery'], '1.0.0', true);
        wp_enqueue_script('pac-da-change-divi-dashboard-option');
        wp_add_inline_script('pac-da-change-divi-dashboard-option', $hideDiviDashboardJS);
    });
});