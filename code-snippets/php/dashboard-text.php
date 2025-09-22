<?php
// 
// <p>This code adds a custom text widget to the WordPress admin dashboard.</p>
//ADD TEXT WIDGET TO ADMIN DASHBOARD

add_action('wp_dashboard_setup', 'my_custom_dashboard_widgets');
function my_custom_dashboard_widgets() {
wp_add_dashboard_widget('custom_help_widget', 'Ansprechpartner bei Fragen', 'custom_dashboard_help');
}

function custom_dashboard_help() {
echo '  <img src="' .  plugin_dir_url( plugin_dir_path( __FILE__ ) ).'includes/img/bb-template-logo.svg" alt="Philipp Ludwig" style="max-width: 50px; border-radius: 100%; height: auto; margin-bottom: 20px;"></br><p><span style="color: black;font-size: 14px;"><strong>&copy; Büro Blanko Medien GmbH</strong></span><br>Telefon: <a style="text-decoration:none;color:#000;" href="tel:004962249877666">+49 6224 9877 666</a><br><a href="https://www.bueroblanko.de">www.bueroblanko.de</a> | <a href="mailto:info@bueroblanko.de">info@bueroblanko.de</a></p>';
}
