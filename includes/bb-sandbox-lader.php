<?php
/**
 * Plugin Name: BB Sandbox-Code
 * Description: Laedt den eigenen Code, den ein Agent mit Novamira gebaut hat (wp-content/mu-plugins/bb-sandbox/). Von BB Basic angelegt, laeuft auch ohne Novamira und ohne BB Basic.
 *
 * Vorlage in BB Basic (includes/bb-sandbox-lader.php). BB Basic kopiert sie nach
 * wp-content/mu-plugins/bb-sandbox.php, sobald es Code aus der Novamira-Sandbox umzieht.
 *
 * Schutz wie in Novamiras Sandbox: Jede Datei laedt einzeln. Bricht eine beim Laden ab,
 * wird nur sie abgeschaltet (leere Marker-Datei .<name>.php.disabled daneben, dasselbe
 * Format wie bei Novamira), die Seite laeuft ab dem naechsten Aufruf weiter.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

( static function () {
	$bb_ordner = __DIR__ . '/bb-sandbox/';
	if ( ! is_dir( $bb_ordner ) ) {
		return;
	}
	// WordPress bindet Plugins vor dem Aktivieren einmal zur Probe ein. Dort nichts laden.
	if ( defined( 'WP_SANDBOX_SCRAPING' ) && WP_SANDBOX_SCRAPING ) {
		return;
	}
	$bb_dateien = glob( $bb_ordner . '*.php' );
	if ( ! $bb_dateien ) {
		return;
	}

	$bb_abschalten = static function ( $datei, $grund ) {
		@touch( dirname( $datei ) . '/.' . basename( $datei ) . '.disabled' );
		error_log( 'BB Sandbox-Code: ' . basename( $datei ) . ' abgeschaltet: ' . $grund );
	};

	// Faengt ab, was kein try/catch abfaengt (doppelte Funktion, Speicher, Zeitlimit).
	$bb_aktuell = null;
	register_shutdown_function( static function () use ( &$bb_aktuell, $bb_abschalten ) {
		if ( null === $bb_aktuell ) {
			return;
		}
		$fehler = error_get_last();
		$bb_abschalten( $bb_aktuell, $fehler ? $fehler['message'] : 'PHP hat beim Laden abgebrochen.' );
	} );

	foreach ( $bb_dateien as $bb_datei ) {
		if ( 'index.php' === basename( $bb_datei ) || is_file( dirname( $bb_datei ) . '/.' . basename( $bb_datei ) . '.disabled' ) ) {
			continue;
		}
		$bb_aktuell = $bb_datei;
		$bb_ebene   = ob_get_level();
		ob_start();
		try {
			require_once $bb_datei;
		} catch ( \Throwable $bb_fehler ) {
			$bb_abschalten( $bb_datei, $bb_fehler->getMessage() );
		}
		// Ausgaben beim Laden verwerfen, sonst landen sie vor jeder Seite und jeder REST-Antwort.
		while ( ob_get_level() > $bb_ebene ) {
			ob_end_clean();
		}
	}
	$bb_aktuell = null;
} )();
