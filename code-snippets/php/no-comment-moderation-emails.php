<?php
// 
// <p>This code customizes WordPress to remove the admin email from the list of recipients for comment moderation notifications.</p>
add_filter( 'comment_moderation_recipients', function ( $emails, $cid ) {
	if ( ( $key = array_search( get_bloginfo( 'admin_email' ), $emails) ) !== false ) {
		unset( $emails[ $key ] );
	}
	return $emails;
}, 10, 2 );