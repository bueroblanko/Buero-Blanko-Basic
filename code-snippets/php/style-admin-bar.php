<?php
// 
// <p>The code customizes the WordPress admin dashboard logo, adds a 'Support' link to the admin bar, and changes the admin bar's color.</p>
function dashboard_logo() {
    echo '
        <style type="text/css">
#wpadminbar #wp-admin-bar-wp-logo>.ab-item {
    padding: 0 7px;
    background-image: url(/wp-content/plugins/buero-blanko-basic/includes/img/logo-bildmarke-weiss-freiraum.webp) !important;
    background-size: 70%;
    background-position: center;
    background-repeat: no-repeat;
    opacity: 0.8;
}
#wpadminbar #wp-admin-bar-wp-logo>.ab-item .ab-icon:before {
    content: " ";
    top: 2px;
}

#wpadminbar #wp-admin-bar-site-name>.ab-item:before {
    display: none;
}

#wp-admin-bar-et-use-visual-builder a:before {
    color: #fff !important;
}

#wp-admin-bar-about, #wp-admin-bar-contribute, #wp-admin-bar-wp-logo ul {display:none!important;}

#wpadminbar .quicklinks .ab-sub-wrapper .menupop.hover>a, #wpadminbar .quicklinks .menupop ul li a:focus, #wpadminbar .quicklinks .menupop ul li a:focus strong, #wpadminbar .quicklinks .menupop ul li a:hover, #wpadminbar .quicklinks .menupop ul li a:hover strong, #wpadminbar .quicklinks .menupop.hover ul li a:focus, #wpadminbar .quicklinks .menupop.hover ul li a:hover, #wpadminbar .quicklinks .menupop.hover ul li div[tabindex]:focus, #wpadminbar .quicklinks .menupop.hover ul li div[tabindex]:hover, #wpadminbar li #adminbarsearch.adminbar-focused:before, #wpadminbar li .ab-item:focus .ab-icon:before, #wpadminbar li .ab-item:focus:before, #wpadminbar li a:focus .ab-icon:before, #wpadminbar li.hover .ab-icon:before, #wpadminbar li.hover .ab-item:before, #wpadminbar li:hover #adminbarsearch:before, #wpadminbar li:hover .ab-icon:before, #wpadminbar li:hover .ab-item:before, #wpadminbar.nojs .quicklinks .menupop:hover ul li a:focus, #wpadminbar.nojs .quicklinks .menupop:hover ul li a:hover {
    color: #fff;
}

#wpadminbar .ab-top-menu>li.hover>.ab-item, #wpadminbar.nojq .quicklinks .ab-top-menu>li>.ab-item:focus, #wpadminbar:not(.mobile) .ab-top-menu>li:hover>.ab-item, #wpadminbar:not(.mobile) .ab-top-menu>li>.ab-item:focus {
    color: #fff;
}
        </style>
    ';
}
add_action('wp_before_admin_bar_render', 'dashboard_logo');



// customize admin bar css
function override_admin_bar_css() { 

   if ( is_admin_bar_showing() ) { ?>


      <style type="text/css">
#wpadminbar {
    background: #000 !important;
}
      </style>

   <?php }

}

// on backend area
add_action( 'admin_head', 'override_admin_bar_css' );

// on frontend area
add_action( 'wp_head', 'override_admin_bar_css' );	
