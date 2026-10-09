<?php
// 
// <p>This code redirects the user from the about.php page to the WordPress dashboard.</p>
function bb_redirect_about_page() {
    // Überprüfen, ob der aktuelle Benutzer auf die about.php-Seite zugreift
    if (strpos($_SERVER['REQUEST_URI'], 'about.php') !== false) {
        // Die URL des WordPress-Dashboards
        $dashboard_url = admin_url(); // Dies gibt die URL des Dashboards zurück
        
        // Führe die Umleitung durch
        wp_redirect($dashboard_url);
        exit; // Stelle sicher, dass das Skript nach der Umleitung beendet wird
    }
}
add_action('admin_init', 'bb_redirect_about_page');
