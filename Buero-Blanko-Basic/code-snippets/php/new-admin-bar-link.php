<?php
// 
// <p>The code adds a 'Support' link to the WordPress admin toolbar that opens in a new tab.</p>
add_action( 'admin_bar_menu', 'toolbar_link_to_mypage', 999 );

function toolbar_link_to_mypage( $wp_admin_bar ) {
    $args = array(
        'id'    => 'my_page',
        'title' => 'Support',
        'href'  => 'http://bueroblanko.de/support',
        'meta'  => array( 
            'class' => 'my-toolbar-page',
            'target' => '_blank' // Link in neuem Tab öffnen
        )
    );
    $wp_admin_bar->add_node( $args );
}
