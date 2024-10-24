<?php
// 
// <p>This code modifies the title of the WordPress Dashboard to 'Ihre Website Übersicht'.</p>
//CHANGE DASHBOARD HEADER TITLE

    function my_custom_dashboard_name(){
        if ( $GLOBALS['title'] != 'Dashboard' ){
            return;
        }

        $GLOBALS['title'] =  __( 'Website Overview' ); 
    }

    add_action( 'admin_head', 'my_custom_dashboard_name' );