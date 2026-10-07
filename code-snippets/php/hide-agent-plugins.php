<?php
//
// <p>Versteckt Respira und Novamira (sofern installiert) im Backend vor allen außer der Person, die das Plugin installiert oder aktiviert hat, und Nutzern mit einer @bueroblanko.de-Adresse: Plugin-Liste, Update-Hinweise und Menüeinträge.</p>
// Hide agent plugins (Respira, Novamira) code snippet
//
// Erkannt wird ein Plugin, wenn sein Ordner bzw. seine Datei mit "respira" oder
// "novamira" beginnt oder sein Plugin-Name mit einem dieser Begriffe beginnt.
// Ist keins davon installiert, tut das Snippet nichts. Es hängt nicht von Divi ab.
//
// Wer das Plugin installiert oder aktiviert, wird als Besitzer gespeichert
// (Option "bb_agent_plugin_owners"), aber nur beim ersten Mal.
// Für Seiten, auf denen das Plugin schon vorher lief, gibt es keinen Besitzer;
// dort sehen es nur Nutzer mit @bueroblanko.de-Adresse.
//
// Das Plugin selbst läuft unverändert weiter, es wird nur in der Oberfläche
// ausgeblendet. Nur im Backend aktiv; WP-Cron, WP-CLI, Ajax und REST bleiben
// unberührt, damit automatische Updates und die Agent-Verbindungen weiterlaufen.
// Bewusst nur Closures und keine benannten Funktionen: ein doppelt deklarierter
// Funktionsname wäre im eval() ein Fatal Error auf jeder Seite.

$bb_agent_keywords = array( 'respira', 'novamira' );
$bb_agent_option   = 'bb_agent_plugin_owners';

$bb_is_agent_plugin = function ( $plugin_file, $name = '' ) use ( $bb_agent_keywords ) {
    foreach ( $bb_agent_keywords as $keyword ) {
        if ( stripos( (string) $plugin_file, $keyword ) === 0 || stripos( ltrim( (string) $name ), $keyword ) === 0 ) {
            return true;
        }
    }
    return false;
};

// Darf der aktuelle Nutzer das Plugin sehen? Null-Plugin = irgendeins der Agent-Plugins.
$bb_may_see = function ( $plugin_file = null ) use ( $bb_agent_option ) {
    $user = wp_get_current_user();
    if ( ! $user->exists() ) {
        return false;
    }
    $email = strtolower( (string) $user->user_email );
    if ( substr( $email, -strlen( '@bueroblanko.de' ) ) === '@bueroblanko.de' ) {
        return true;
    }
    $owners = get_option( $bb_agent_option, array() );
    if ( ! is_array( $owners ) ) {
        return false;
    }
    if ( $plugin_file === null ) {
        return in_array( (int) $user->ID, array_map( 'intval', $owners ), true );
    }
    return isset( $owners[ $plugin_file ] ) && (int) $owners[ $plugin_file ] === (int) $user->ID;
};

// Ausblenden nur im Backend für angemeldete Nutzer
$bb_hide_context = function () {
    if ( ! is_admin() || wp_doing_cron() || wp_doing_ajax() || ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
        return false;
    }
    return is_user_logged_in();
};

// Besitzer merken (nur beim ersten Mal)
$bb_remember_owner = function ( $plugin_file ) use ( $bb_agent_option ) {
    $user_id = get_current_user_id();
    if ( ! $user_id || ! $plugin_file ) {
        return;
    }
    $owners = get_option( $bb_agent_option, array() );
    if ( ! is_array( $owners ) ) {
        $owners = array();
    }
    if ( ! isset( $owners[ $plugin_file ] ) ) {
        $owners[ $plugin_file ] = $user_id;
        update_option( $bb_agent_option, $owners, false );
    }
};

add_action( 'upgrader_process_complete', function ( $upgrader, $hook_extra ) use ( $bb_is_agent_plugin, $bb_remember_owner ) {
    if ( ! is_array( $hook_extra ) || ( $hook_extra['type'] ?? '' ) !== 'plugin' || ( $hook_extra['action'] ?? '' ) !== 'install' ) {
        return;
    }
    if ( ! is_object( $upgrader ) || ! method_exists( $upgrader, 'plugin_info' ) ) {
        return;
    }
    $plugin_file = $upgrader->plugin_info();
    if ( $plugin_file && $bb_is_agent_plugin( $plugin_file ) ) {
        $bb_remember_owner( $plugin_file );
    }
}, 10, 2 );

add_action( 'activated_plugin', function ( $plugin_file ) use ( $bb_is_agent_plugin, $bb_remember_owner ) {
    if ( $bb_is_agent_plugin( $plugin_file ) ) {
        $bb_remember_owner( $plugin_file );
    }
} );

// Plugin-Liste (inkl. Zähler "Alle/Aktiv")
add_filter( 'all_plugins', function ( $plugins ) use ( $bb_hide_context, $bb_is_agent_plugin, $bb_may_see ) {
    if ( ! is_array( $plugins ) || ! $bb_hide_context() ) {
        return $plugins;
    }
    foreach ( $plugins as $plugin_file => $data ) {
        $name = is_array( $data ) && isset( $data['Name'] ) ? $data['Name'] : '';
        if ( $bb_is_agent_plugin( $plugin_file, $name ) && ! $bb_may_see( $plugin_file ) ) {
            unset( $plugins[ $plugin_file ] );
        }
    }
    return $plugins;
} );

// Update-Hinweise und Update-Zähler
add_filter( 'site_transient_update_plugins', function ( $value ) use ( $bb_hide_context, $bb_is_agent_plugin, $bb_may_see ) {
    if ( ! is_object( $value ) || empty( $value->response ) || ! is_array( $value->response ) || ! $bb_hide_context() ) {
        return $value;
    }
    foreach ( $value->response as $plugin_file => $update ) {
        $name = is_object( $update ) && isset( $update->slug ) ? $update->slug : '';
        if ( $bb_is_agent_plugin( $plugin_file, $name ) && ! $bb_may_see( $plugin_file ) ) {
            unset( $value->response[ $plugin_file ] );
        }
    }
    return $value;
} );

// Menüeinträge (Haupt- und Untermenüs), deren Slug mit "respira" oder "novamira" beginnt
add_action( 'admin_menu', function () use ( $bb_hide_context, $bb_is_agent_plugin, $bb_may_see ) {
    global $menu, $submenu;
    if ( ! $bb_hide_context() || $bb_may_see() ) {
        return;
    }
    foreach ( (array) $menu as $item ) {
        if ( isset( $item[2] ) && $bb_is_agent_plugin( $item[2] ) ) {
            remove_menu_page( $item[2] );
        }
    }
    foreach ( (array) $submenu as $parent => $items ) {
        foreach ( (array) $items as $item ) {
            if ( isset( $item[2] ) && $bb_is_agent_plugin( $item[2] ) ) {
                remove_submenu_page( $parent, $item[2] );
            }
        }
    }
}, 999 );
