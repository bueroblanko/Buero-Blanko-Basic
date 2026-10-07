<?php
//
// <p>Versteckt das Respira-Plugin (sofern installiert) für alle Benutzer außer "bueroblanko": Eintrag in der Plugin-Liste, Update-Hinweise und Menüeinträge im Backend.</p>
// Hide Respira plugin code snippet
//
// Erkannt wird jedes Plugin, dessen Ordner bzw. Datei mit "respira" beginnt.
// Nur im Backend für angemeldete Nutzer aktiv. WP-Cron, WP-CLI und Ajax bleiben
// unberührt, damit die automatischen Updates von Respira weiterlaufen.
// Bewusst nur Closures und keine benannten Funktionen: ein doppelt deklarierter
// Funktionsname wäre im eval() ein Fatal Error auf jeder Seite.

$bb_hide_respira_active = function () {
    if ( ! is_admin() || wp_doing_cron() || wp_doing_ajax() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
        return false;
    }
    $user = wp_get_current_user();
    return $user->exists() && $user->user_login !== 'bueroblanko';
};

$bb_is_respira = function ( $slug ) {
    return stripos( (string) $slug, 'respira' ) === 0;
};

// Plugin-Liste (inkl. Zähler "Alle/Aktiv") ohne Respira
add_filter( 'all_plugins', function ( $plugins ) use ( $bb_hide_respira_active, $bb_is_respira ) {
    if ( ! $bb_hide_respira_active() ) {
        return $plugins;
    }
    foreach ( array_keys( $plugins ) as $plugin_file ) {
        if ( $bb_is_respira( $plugin_file ) ) {
            unset( $plugins[ $plugin_file ] );
        }
    }
    return $plugins;
} );

// Update-Hinweise und Update-Zähler für Respira ausblenden
add_filter( 'site_transient_update_plugins', function ( $value ) use ( $bb_hide_respira_active, $bb_is_respira ) {
    if ( ! is_object( $value ) || empty( $value->response ) || ! $bb_hide_respira_active() ) {
        return $value;
    }
    foreach ( array_keys( $value->response ) as $plugin_file ) {
        if ( $bb_is_respira( $plugin_file ) ) {
            unset( $value->response[ $plugin_file ] );
        }
    }
    return $value;
} );

// Menüeinträge von Respira entfernen (Hauptmenü und Untermenüs)
add_action( 'admin_menu', function () use ( $bb_hide_respira_active, $bb_is_respira ) {
    global $menu, $submenu;
    if ( ! $bb_hide_respira_active() ) {
        return;
    }
    foreach ( (array) $menu as $item ) {
        if ( isset( $item[2] ) && $bb_is_respira( $item[2] ) ) {
            remove_menu_page( $item[2] );
        }
    }
    foreach ( (array) $submenu as $parent => $items ) {
        foreach ( (array) $items as $item ) {
            if ( isset( $item[2] ) && $bb_is_respira( $item[2] ) ) {
                remove_submenu_page( $parent, $item[2] );
            }
        }
    }
}, 999 );
