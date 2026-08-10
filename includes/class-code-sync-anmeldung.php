<?php

/**
 * Anmeldung beim BB Cockpit.
 *
 * Meldet einmal täglich, dass dieses Plugin hier läuft — damit im Cockpit
 * unter /sites automatisch steht, welche Kundenseiten BB Basic einsetzen
 * und in welcher Version. Ohne diese Meldung gibt es keine Liste: das
 * Plugin holt sich Updates von GitHub, aber GitHub weiß nicht, wo es läuft.
 *
 * Übertragen wird ausschließlich Technisches:
 *   Adresse der Seite, Seitentitel, WordPress-, PHP- und Plugin-Version,
 *   aktives Theme.
 * KEINE Nutzer-, Kunden- oder Inhaltsdaten.
 *
 * Der Token unten steht im Klartext in jeder Installation. Er darf deshalb
 * bewusst nur eines: eine Seite melden. Das Cockpit prüft zusätzlich beim
 * ersten Mal selbst nach, ob unter der gemeldeten Adresse wirklich BB Basic
 * läuft — mit dem Token allein lässt sich also nichts Fremdes eintragen.
 *
 * @package    Code_Sync
 * @subpackage Code_Sync/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Code_Sync_Anmeldung {

	const ENDPUNKT = 'https://cockpit.bueroblanko.de/api/sites/anmelden';
	const HOOK     = 'code_sync_anmeldung';

	/** Täglichen Lauf einplanen (bei Aktivierung und zur Sicherheit bei jedem Start). */
	public static function planen() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 60, 'daily', self::HOOK );
		}
	}

	/** Beim Deaktivieren wieder abmelden, sonst bleibt ein toter Cron-Eintrag. */
	public static function abmelden() {
		$next = wp_next_scheduled( self::HOOK );
		if ( $next ) {
			wp_unschedule_event( $next, self::HOOK );
		}
	}

	/** Die eigentliche Meldung. Fehler bleiben folgenlos — die Seite darf
	 *  davon niemals ausgebremst oder gar kaputtgemacht werden. */
	public static function melden() {
		$token = defined( 'CODE_SYNC_COCKPIT_TOKEN' ) ? CODE_SYNC_COCKPIT_TOKEN : '';
		if ( ! $token ) {
			return;
		}

		$theme = wp_get_theme();

		$antwort = wp_remote_post(
			self::ENDPUNKT,
			array(
				'timeout'  => 15,
				'blocking' => false, // nicht auf die Antwort warten
				'headers'  => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'     => wp_json_encode(
					array(
						'url'     => home_url(),
						'name'    => get_bloginfo( 'name' ),
						'wp'      => get_bloginfo( 'version' ),
						'php'     => PHP_VERSION,
						'version' => defined( 'CODE_SYNC_PLUGIN_VERSION' ) ? CODE_SYNC_PLUGIN_VERSION : '',
						'theme'   => is_object( $theme ) ? $theme->get( 'Name' ) : '',
					)
				),
			)
		);

		// nur fürs Protokoll, wenn WP_DEBUG an ist
		if ( is_wp_error( $antwort ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'BB Basic: Anmeldung fehlgeschlagen — ' . $antwort->get_error_message() );
		}
	}
}
