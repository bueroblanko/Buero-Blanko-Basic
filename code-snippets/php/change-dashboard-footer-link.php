<?php
// 
// <p>The code changes the default WordPress admin dashboard footer link and text.</p>
//CHANGE DASHBOARD FOOTER LINK
 
function remove_footer_admin () 
{
    echo '<span id="footer-thankyou">Developed by <a href="https://www.bueroblanko.de" target="_blank">BÜRO BLANKO</a></span>';
}
add_filter('admin_footer_text', 'remove_footer_admin');
