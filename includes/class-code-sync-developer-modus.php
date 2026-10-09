<?php

/**
 * Developer-Modus: laedt Novamira und Novamira Pro (AGPL-3.0-or-later, Ovation S.r.l.)
 * als nachgeladenes Modul, aber nur wenn per FTP die Schalter-Datei
 * wp-content/bb-developer-modus.php liegt. Ohne Datei passiert nichts.
 *
 * Schalter-Datei (erste Zeile <?php exit; ?>, dann je Zeile schluessel=wert):
 *   stufe=build|live|sleep|clear
 *                            build = alles erlaubt, live = nur lesende Werkzeuge,
 *                            sleep = Modul bleibt liegen, wird aber nicht geladen,
 *                            clear = Modul wird geloescht (wie ohne Schalter-Datei)
 *   bis=JJJJ-MM-TT           optional, sonst 14 Tage nach Aenderung der Datei
 *   lizenz=...               optional, Lizenzschluessel fuer Novamira Pro
 *
 * Das Modul kommt als ZIP mit signiertem Manifest vom Update-Server und liegt
 * unter wp-content/plugins/bb-basic-modul/<nummer>/. Novamiras Code bleibt unveraendert.
 *
 * @package    Code_Sync
 * @subpackage Code_Sync/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Code_Sync_Developer_Modus {

	const SCHALTER      = 'bb-developer-modus.php';
	const MODUL_ORDNER  = 'bb-basic-modul';
	const MANIFEST_URL  = 'https://bbkd.de/module/novamira/manifest.json';
	// Oeffentliche Ed25519-Schluessel (base64). Eine Signatur mit einem davon reicht.
	// Erster: Update-Job auf dem VPS. Zweiter: Notfallschluessel auf Philipps Mac.
	// Leer = es wird nichts nachgeladen.
	const PUBLIC_KEYS   = array(
		'/JMncVpXYlj2eRvvNfMkVU+L0q8yIK2hg7pu3i4LFDM=',
		'L1UT2jysxP1ulgzkHe0flHBt37sR3vZvs+HQl8az8s4=',
	);
	const OPTION        = 'code_sync_devmodus';
	const CRON          = 'code_sync_devmodus_holen';
	const CRON_WEG      = 'code_sync_devmodus_aufraeumen';
	const LAUFZEIT_TAGE = 14;
	const MIN_PHP       = '8.0';
	const MIN_WP        = '6.9';

	// In Stufe live erlaubte Werkzeuge (nur Lesen). Alles andere wird abgemeldet.
	private static $live_erlaubt = array(
		'novamira-mcp-adapter/discover-abilities',
		'novamira-mcp-adapter/get-ability-info',
		'novamira-mcp-adapter/execute-ability',
		'novamira/agent-context',
		'novamira/divi-check-setup',
		'novamira/gutenberg-get-content',
		'novamira/gutenberg-list-block-types',
		'novamira/gutenberg-get-block-type',
	);
	private static $live_muster = array(
		'#^novamira/divi-(get|list)-#',
	);

	/** Zustand fuer die Anzeige. */
	private static $zustand = null;

	/**
	 * Wird beim Laden von BB Basic aufgerufen, also noch waehrend WordPress die Plugins laedt.
	 */
	public static function start() {
		$datei = WP_CONTENT_DIR . '/' . self::SCHALTER;
		$clear = ! file_exists( $datei );
		if ( ! $clear ) {
			self::$zustand = self::schalter_lesen( $datei );
			$clear = 'clear' === self::$zustand['stufe'];
		}
		if ( $clear ) {
			// Normalfall auf allen Seiten. Liegt noch ein Modul von frueher, wird es geloescht.
			self::$zustand = array( 'stufe' => 'clear' );
			if ( is_dir( self::modul_pfad() ) ) {
				add_action( self::CRON_WEG, array( __CLASS__, 'modul_loeschen' ) );
				if ( ! wp_next_scheduled( self::CRON_WEG ) ) {
					wp_schedule_single_event( time(), self::CRON_WEG );
				}
			}
			if ( wp_next_scheduled( self::CRON ) ) {
				wp_clear_scheduled_hook( self::CRON );
			}
			return;
		}

		add_action( self::CRON, array( __CLASS__, 'modul_holen' ) );

		if ( 'sleep' === self::$zustand['stufe'] ) {
			return;
		}

		if ( version_compare( PHP_VERSION, self::MIN_PHP, '<' ) ) {
			return self::grund( 'PHP ' . PHP_VERSION . ' ist zu alt, Novamira braucht ' . self::MIN_PHP . '.' );
		}
		if ( version_compare( $GLOBALS['wp_version'], self::MIN_WP, '<' ) ) {
			return self::grund( 'WordPress ' . $GLOBALS['wp_version'] . ' ist zu alt, Novamira braucht ' . self::MIN_WP . '.' );
		}
		if ( defined( 'NOVAMIRA_VERSION' ) || function_exists( 'novamira_is_enabled' ) ) {
			return self::grund( 'Novamira ist hier schon als eigenes Plugin aktiv. Das Modul wird nicht geladen.' );
		}

		$status = self::status();
		if ( ! wp_next_scheduled( self::CRON ) ) {
			// Sofort einmal holen, danach taeglich.
			wp_schedule_event( time(), 'daily', self::CRON );
		}
		if ( empty( $status['aktiv'] ) ) {
			return self::grund( 'Modul ist noch nicht heruntergeladen.' . ( empty( $status['fehler'] ) ? '' : ' Letzter Fehler: ' . $status['fehler'] ) );
		}
		if ( ! empty( $status['defekt'][ $status['aktiv'] ] ) ) {
			return self::grund( 'Modul ' . $status['aktiv'] . ' ist als defekt markiert: ' . $status['defekt'][ $status['aktiv'] ] );
		}
		if ( 'live' === self::$zustand['stufe'] && self::sandbox_belegt() ) {
			return self::grund( 'Sandbox nicht leer (wp-content/novamira-sandbox). Auf Live-Seiten wird Novamira dann nicht geladen.' );
		}

		self::modul_laden( $status );
	}

	/**
	 * Liest die Schalter-Datei. Unbekannte oder fehlende Stufe = sleep.
	 */
	private static function schalter_lesen( $datei ) {
		$werte = array();
		$zeilen = @file( $datei, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		foreach ( (array) $zeilen as $zeile ) {
			$teile = explode( '=', trim( $zeile ), 2 );
			if ( 2 === count( $teile ) ) {
				$werte[ strtolower( trim( $teile[0] ) ) ] = trim( $teile[1] );
			}
		}

		$stufe = isset( $werte['stufe'] ) ? strtolower( $werte['stufe'] ) : 'sleep';
		if ( ! in_array( $stufe, array( 'build', 'live', 'sleep', 'clear' ), true ) ) {
			$stufe = 'sleep';
		}

		$bis = 0;
		if ( ! empty( $werte['bis'] ) ) {
			$bis = strtotime( $werte['bis'] . ' 23:59:59' );
		}
		if ( ! $bis ) {
			$bis = (int) filemtime( $datei ) + self::LAUFZEIT_TAGE * DAY_IN_SECONDS;
		}

		$zustand = array(
			'stufe'  => $stufe,
			'bis'    => $bis,
			'lizenz' => isset( $werte['lizenz'] ) ? $werte['lizenz'] : '',
			'grund'  => '',
		);
		if ( in_array( $stufe, array( 'build', 'live' ), true ) && time() > $bis ) {
			$zustand['stufe'] = 'sleep';
			$zustand['grund'] = 'Abgelaufen am ' . gmdate( 'd.m.Y', $bis ) . '. Zum Verlaengern die Schalter-Datei neu hochladen.';
		}
		return $zustand;
	}

	private static function grund( $text ) {
		self::$zustand['grund'] = $text;
	}

	private static function status() {
		$status = get_option( self::OPTION );
		if ( ! is_array( $status ) ) {
			$status = array();
		}
		return array_merge(
			array(
				'nummer'    => 0,
				'aktiv'     => '',
				'vorher'    => '',
				'novamira'  => '',
				'pro'       => '',
				'geprueft'  => 0,
				'fehler'    => '',
				'defekt'    => array(),
				'aktiviert' => '',
			),
			$status
		);
	}

	private static function status_speichern( $status ) {
		update_option( self::OPTION, $status, false );
	}

	private static function modul_pfad( $nummer = '' ) {
		return WP_PLUGIN_DIR . '/' . self::MODUL_ORDNER . ( '' === $nummer ? '' : '/' . $nummer );
	}

	private static function sandbox_belegt() {
		$dateien = glob( WP_CONTENT_DIR . '/novamira-sandbox/*.php' );
		return ! empty( $dateien );
	}

	/**
	 * Bindet Novamira und Novamira Pro ein und setzt die Sperren.
	 */
	private static function modul_laden( $status ) {
		$pfad = self::modul_pfad( $status['aktiv'] );
		if ( ! file_exists( $pfad . '/novamira/novamira.php' ) ) {
			return self::grund( 'Modulordner fehlt: ' . $pfad );
		}

		// Novamiras eigener An-Schalter folgt unserer Schalter-Datei.
		add_filter( 'pre_option_novamira_ai_abilities_enabled', array( __CLASS__, 'option_an' ) );
		add_filter( 'pre_option_novamira_ai_abilities_domain', array( __CLASS__, 'option_domain' ) );

		register_shutdown_function( array( __CLASS__, 'absturz_pruefen' ), $status['aktiv'] );

		try {
			include_once $pfad . '/novamira/novamira.php';
			if ( file_exists( $pfad . '/novamira-pro/novamira-pro.php' ) ) {
				include_once $pfad . '/novamira-pro/novamira-pro.php';
			}
		} catch ( Throwable $e ) {
			self::defekt_markieren( $status['aktiv'], $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')' );
			return self::grund( 'Fehler beim Laden: ' . $e->getMessage() );
		}

		self::$zustand['geladen'] = true;

		// Updates kommen nur ueber unser Manifest, nicht ueber Novamiras eigene Pruefer.
		add_action( 'init', array( __CLASS__, 'fremde_updates_aus' ), 99 );
		add_action( 'init', array( __CLASS__, 'einrichten' ), 20 );

		if ( 'live' === self::$zustand['stufe'] ) {
			// Nach Novamiras eigener Sperrlogik (gleiche Prioritaet, spaeter angemeldet).
			add_action( 'wp_abilities_api_init', array( __CLASS__, 'live_sperre' ), PHP_INT_MAX );
		}
	}

	public static function option_an() {
		return '1';
	}

	public static function option_domain() {
		return (string) wp_parse_url( home_url(), PHP_URL_HOST );
	}

	public static function fremde_updates_aus() {
		remove_filter( 'site_transient_update_plugins', 'novamira_check_for_updates' );
		remove_filter( 'plugins_api', 'novamira_plugins_api', 10 );
		remove_filter( 'site_transient_update_plugins', 'Novamira\\Pro\\check_update_availability' );
		remove_filter( 'plugins_api', 'Novamira\\Pro\\plugins_api', 20 );
	}

	/**
	 * Holt nach, was sonst der Aktivierungs-Hook von Novamira erledigt, und aktiviert die Pro-Lizenz.
	 */
	public static function einrichten() {
		$status = self::status();
		if ( $status['aktiviert'] !== $status['aktiv'] ) {
			if ( function_exists( 'novamira_chat_schema_install' ) ) {
				novamira_chat_schema_install();
			}
			if ( function_exists( 'novamira_schedule_specializations_refresh' ) ) {
				novamira_schedule_specializations_refresh();
			}
			$status['aktiviert'] = $status['aktiv'];
			self::status_speichern( $status );
		}

		$lizenz = self::$zustand['lizenz'];
		if ( '' === $lizenz || ! function_exists( 'Novamira\\Pro\\is_license_active' ) || \Novamira\Pro\is_license_active() ) {
			return;
		}
		// Hoechstens einmal am Tag versuchen, damit kein Aufruf an Novamira haengt.
		if ( get_transient( 'code_sync_devmodus_lizenz' ) ) {
			return;
		}
		set_transient( 'code_sync_devmodus_lizenz', 1, DAY_IN_SECONDS );
		try {
			\Novamira\Pro\activate_new_license_key( $lizenz );
		} catch ( Throwable $e ) {
			self::grund( 'Pro-Lizenz konnte nicht aktiviert werden: ' . $e->getMessage() );
		}
	}

	/**
	 * Stufe live: alle Werkzeuge abmelden, die nicht auf der Erlaubt-Liste stehen.
	 */
	public static function live_sperre() {
		if ( ! function_exists( 'wp_get_abilities' ) || ! function_exists( 'wp_unregister_ability' ) ) {
			return;
		}
		foreach ( wp_get_abilities() as $ability ) {
			$name = $ability->get_name();
			if ( ! self::live_erlaubt( $name ) ) {
				wp_unregister_ability( $name );
			}
		}
	}

	private static function live_erlaubt( $name ) {
		if ( in_array( $name, self::$live_erlaubt, true ) ) {
			return true;
		}
		foreach ( self::$live_muster as $muster ) {
			if ( preg_match( $muster, $name ) ) {
				return true;
			}
		}
		// Lesende Werkzeuge aus dem WordPress-Kern. Werkzeuge anderer Plugins sind
		// ebenfalls gesperrt, weil Novamira sie sonst ueber MCP ausfuehren koennte.
		return 0 === strpos( $name, 'core/get-' );
	}

	/**
	 * Fataler Fehler aus dem Modulordner: Version sperren, ab dem naechsten Aufruf laeuft die Seite ohne Modul.
	 */
	public static function absturz_pruefen( $nummer ) {
		$fehler = error_get_last();
		if ( ! $fehler || ! in_array( $fehler['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
			return;
		}
		$ordner = wp_normalize_path( self::modul_pfad( $nummer ) );
		if ( 0 !== strpos( wp_normalize_path( $fehler['file'] ), $ordner ) ) {
			return;
		}
		self::defekt_markieren( $nummer, $fehler['message'] . ' (' . basename( $fehler['file'] ) . ':' . $fehler['line'] . ')' );
	}

	private static function defekt_markieren( $nummer, $text ) {
		$status = self::status();
		$status['defekt'][ $nummer ] = substr( $text, 0, 300 );
		// Rueckfall auf die vorherige Version, falls vorhanden und nicht ebenfalls defekt.
		if ( $status['vorher'] && empty( $status['defekt'][ $status['vorher'] ] ) && is_dir( self::modul_pfad( $status['vorher'] ) ) ) {
			$status['aktiv']  = $status['vorher'];
			$status['vorher'] = '';
		}
		self::status_speichern( $status );
	}

	/**
	 * Cron: Schalter-Datei ist weg, also Modul und Stand loeschen. Danach liegt kein Novamira-Code mehr auf der Seite.
	 */
	public static function modul_loeschen() {
		$datei = WP_CONTENT_DIR . '/' . self::SCHALTER;
		if ( file_exists( $datei ) && 'clear' !== self::schalter_lesen( $datei )['stufe'] ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		global $wp_filesystem;
		if ( WP_Filesystem() && $wp_filesystem ) {
			$wp_filesystem->delete( self::modul_pfad(), true );
		}
		delete_option( self::OPTION );
	}

	/**
	 * Cron: Manifest holen, Signatur und Pruefsumme pruefen, neue Version entpacken.
	 */
	public static function modul_holen() {
		$status = self::status();
		$fehler = self::modul_holen_intern( $status );
		$status = self::status();
		$status['fehler']   = $fehler;
		$status['geprueft'] = time();
		self::status_speichern( $status );
	}

	/**
	 * Testseiten (Update-Kanal „test“ in der wp-config.php) bekommen neue
	 * Novamira-Versionen zuerst, ueber manifest-test.json.
	 */
	private static function manifest_url() {
		if ( defined( 'BB_BASIC_UPDATE_KANAL' ) && 'test' === BB_BASIC_UPDATE_KANAL ) {
			return str_replace( 'manifest.json', 'manifest-test.json', self::MANIFEST_URL );
		}
		return self::MANIFEST_URL;
	}

	private static function modul_holen_intern( $status ) {
		if ( empty( self::PUBLIC_KEYS ) ) {
			return 'Kein Signaturschluessel eingetragen.';
		}

		$url     = self::manifest_url();
		$antwort = wp_remote_get( $url, array( 'timeout' => 15 ) );
		$sig     = wp_remote_get( $url . '.sig', array( 'timeout' => 15 ) );
		if ( is_wp_error( $antwort ) || is_wp_error( $sig ) || 200 !== wp_remote_retrieve_response_code( $antwort ) || 200 !== wp_remote_retrieve_response_code( $sig ) ) {
			return 'Manifest nicht erreichbar.';
		}
		$inhalt = wp_remote_retrieve_body( $antwort );

		if ( ! self::signatur_ok( $inhalt, trim( wp_remote_retrieve_body( $sig ) ) ) ) {
			return 'Signatur des Manifests ist falsch. Nichts geaendert.';
		}

		$manifest = json_decode( $inhalt, true );
		if ( ! is_array( $manifest ) || empty( $manifest['nummer'] ) || empty( $manifest['zip'] ) || empty( $manifest['sha256'] ) ) {
			return 'Manifest unvollstaendig.';
		}
		$nummer = (int) $manifest['nummer'];
		if ( $nummer < (int) $status['nummer'] ) {
			return 'Manifest ist aelter als die installierte Version. Abgelehnt.';
		}
		if ( $nummer === (int) $status['nummer'] && is_dir( self::modul_pfad( (string) $nummer ) ) ) {
			return '';
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		$zip = download_url( $manifest['zip'], 120 );
		if ( is_wp_error( $zip ) ) {
			return 'Download fehlgeschlagen: ' . $zip->get_error_message();
		}
		if ( ! hash_equals( strtolower( $manifest['sha256'] ), hash_file( 'sha256', $zip ) ) ) {
			@unlink( $zip );
			return 'Pruefsumme der ZIP stimmt nicht. Nichts geaendert.';
		}

		global $wp_filesystem;
		if ( ! WP_Filesystem() || ! $wp_filesystem ) {
			@unlink( $zip );
			return 'Dateisystem nicht beschreibbar.';
		}
		$ziel = self::modul_pfad( (string) $nummer );
		$tmp  = $ziel . '-neu';
		if ( $wp_filesystem->is_dir( $tmp ) ) {
			$wp_filesystem->delete( $tmp, true );
		}
		$ergebnis = unzip_file( $zip, $tmp );
		@unlink( $zip );
		if ( is_wp_error( $ergebnis ) || ! file_exists( $tmp . '/novamira/novamira.php' ) ) {
			$wp_filesystem->delete( $tmp, true );
			return 'ZIP konnte nicht entpackt werden oder enthaelt kein Novamira.';
		}
		if ( $wp_filesystem->is_dir( $ziel ) ) {
			$wp_filesystem->delete( $ziel, true );
		}
		if ( ! @rename( $tmp, $ziel ) ) {
			$wp_filesystem->delete( $tmp, true );
			return 'Modulordner konnte nicht angelegt werden.';
		}

		// Erst jetzt umschalten. Die bisherige Version bleibt als Rueckfall liegen, aeltere werden geloescht.
		$alt = $status['aktiv'];
		foreach ( (array) glob( self::modul_pfad() . '/*', GLOB_ONLYDIR ) as $ordner ) {
			$name = basename( $ordner );
			if ( $name !== (string) $nummer && $name !== $alt ) {
				$wp_filesystem->delete( $ordner, true );
			}
		}

		$status = self::status();
		$status['nummer']   = $nummer;
		$status['vorher']   = $alt;
		$status['aktiv']    = (string) $nummer;
		$status['novamira'] = isset( $manifest['novamira'] ) ? (string) $manifest['novamira'] : '';
		$status['pro']      = isset( $manifest['pro'] ) ? (string) $manifest['pro'] : '';
		unset( $status['defekt'][ (string) $nummer ] );
		self::status_speichern( $status );
		return '';
	}

	private static function signatur_ok( $inhalt, $signatur_b64 ) {
		if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) && file_exists( ABSPATH . WPINC . '/sodium_compat/autoload.php' ) ) {
			require_once ABSPATH . WPINC . '/sodium_compat/autoload.php';
		}
		if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
			return false;
		}
		$signatur = base64_decode( $signatur_b64, true );
		if ( false === $signatur || 64 !== strlen( $signatur ) ) {
			return false;
		}
		foreach ( self::PUBLIC_KEYS as $schluessel_b64 ) {
			$schluessel = base64_decode( $schluessel_b64, true );
			if ( false === $schluessel || 32 !== strlen( $schluessel ) ) {
				continue;
			}
			try {
				if ( sodium_crypto_sign_verify_detached( $signatur, $inhalt, $schluessel ) ) {
					return true;
				}
			} catch ( Throwable $e ) {
				continue;
			}
		}
		return false;
	}

	/**
	 * Daten fuer die Karte unter Werkzeuge → BB Basic.
	 */
	public static function anzeige() {
		$zustand = self::$zustand ? self::$zustand : array( 'stufe' => 'clear' );
		$status  = self::status();
		$namen   = array(
			'clear' => 'Clear (kein Novamira auf der Seite)',
			'sleep' => 'Sleep (Modul nicht geladen)',
			'live'  => 'Live (nur lesend)',
			'build' => 'Build (Baustelle, alles erlaubt)',
		);
		$zeilen = array( $namen[ $zustand['stufe'] ] );
		if ( 'clear' !== $zustand['stufe'] ) {
			$zeilen[] = 'Gültig bis ' . wp_date( 'd.m.Y', $zustand['bis'] );
			if ( $status['aktiv'] ) {
				$zeilen[] = 'Novamira ' . $status['novamira'] . ', Pro ' . $status['pro'] . ( empty( $zustand['geladen'] ) ? ' (nicht geladen)' : ' (geladen)' );
			}
			if ( $status['geprueft'] ) {
				$zeilen[] = 'Update-Server geprüft am ' . wp_date( 'd.m.Y H:i', $status['geprueft'] ) . ( $status['fehler'] ? ': ' . $status['fehler'] : '' );
			}
		}
		if ( ! empty( $zustand['grund'] ) ) {
			$zeilen[] = 'Hinweis: ' . $zustand['grund'];
		}
		return $zeilen;
	}
}
