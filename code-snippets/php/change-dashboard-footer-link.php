<?php
// 
// <p>The code changes the default WordPress admin dashboard footer link and text.</p>
//CHANGE DASHBOARD FOOTER LINK
 
function bb_admin_footer_text () 
{
    echo '<span id="footer-thankyou">Developed by <a href="https://www.bueroblanko.de" target="_blank">BÜRO BLANKO</a></span>';
}
add_filter('admin_footer_text', 'bb_admin_footer_text');
