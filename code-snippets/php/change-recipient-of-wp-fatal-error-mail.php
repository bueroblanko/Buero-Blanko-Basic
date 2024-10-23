<?php
// 
// <p>This code changes the email address that WordPress sends recovery mode emails to.</p>
add_filter( 'recovery_mode_email', 'send_sumun_the_recovery_mode_email', 10, 2 );
function send_sumun_the_recovery_mode_email( $email, $url ) {
$email['to'] = 'info@bueroblanko.de';  // Your own email
return $email;
}