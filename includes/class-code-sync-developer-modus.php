<?php

/**
 * Developer-Modus: laedt Novamira und Novamira Pro (AGPL-3.0-or-later, Ovation S.r.l.)
 * als Teil von BB Basic, aber nur wenn per FTP die Schalter-Datei
 * wp-content/bb-developer-modus.php liegt. Ohne Datei passiert nichts.
 *
 * Schalter-Datei (erste Zeile <?php exit; ?>, dann je Zeile schluessel=wert):
 *   stufe=build|live|sleep|clear
 *                            build = alles erlaubt, live = nur lesende Werkzeuge,
 *                            sleep = Dateien bleiben liegen, werden aber nicht geladen,
 *                            clear = Dateien werden geloescht (wie ohne Schalter-Datei)
 *   bis=JJJJ-MM-TT           optional, sonst 14 Tage nach Aenderung der Datei
 *   lizenz=...               optional, Lizenzschluessel fuer Novamira Pro
 *
 * Die Dateien liegen in wp-content/plugins/bb-basic-module/<name>/. Das ist eine
 * Ebene tiefer als normale Plugins, deshalb stehen sie nicht in der Plugin-Liste.
 * BB Basic laedt sie selbst und haelt sie aktuell: Novamira aus den
 * GitHub-Releases des Herstellers, Pro ueber dessen Lizenzserver.
 *
 * @package    Code_Sync
 * @subpackage Code_Sync/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Code_Sync_Developer_Modus {

	const SCHALTER      = 'bb-developer-modus.php';
	const OPTION        = 'code_sync_devmodus';
	const CRON          = 'code_sync_devmodus_abgleich';
	const LAUFZEIT_TAGE = 14;
	const MIN_PHP       = '8.0';
	const MIN_WP        = '6.9';
	const UPDATE_ALLE   = 12 * HOUR_IN_SECONDS;

	const ORDNER        = 'bb-basic-module';
	const NOVAMIRA      = 'novamira';
	const PRO           = 'novamira-pro';
	const GITHUB_API    = 'https://api.github.com/repos/use-novamira/novamira/releases/latest';
	const PRO_API       = 'https://license.dynamic.ooo/novamira-pro/';
	const PRO_PRODUKT   = 'WP-NVP-1';

	// Bis 0.0.16-Test als normale Plugins installiert, werden beim Abgleich entfernt.
	private static $alt = array( 'novamira/novamira.php', 'novamira-pro/novamira-pro.php' );

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

	/** Zustand laut Schalter-Datei. */
	private static $zustand = null;

	/** Was in diesem Aufruf geladen wurde, und warum nicht. */
	private static $geladen = array();
	private static $hinweis = '';

	/**
	 * Wird beim Laden von BB Basic aufgerufen, also waehrend WordPress die Plugins
	 * laedt. Laedt Novamira in build/live, die uebrige Arbeit laeuft per Cron.
	 */
	public static function start() {
		$datei = WP_CONTENT_DIR . '/' . self::SCHALTER;
		self::$zustand = file_exists( $datei ) ? self::schalter_lesen( $datei ) : array( 'stufe' => 'clear', 'lizenz' => '', 'grund' => '' );

		add_action( self::CRON, array( __CLASS__, 'abgleich' ) );

		$status = self::status();
		if ( 'clear' === self::$zustand['stufe'] && ! $status['aufraeumen'] && ! is_dir( self::modul_dir() ) ) {
			// Normalfall auf allen Seiten: nichts zu tun.
			if ( wp_next_scheduled( self::CRON ) ) {
				wp_clear_scheduled_hook( self::CRON );
			}
			return;
		}

		// Abgleich sofort, wenn sich die Stufe geaendert hat oder Novamira noch fehlt
		// (dann hoechstens alle 5 Minuten), sonst stuendlich.
		$naechster = wp_next_scheduled( self::CRON );
		$an      = in_array( self::$zustand['stufe'], array( 'build', 'live' ), true );
		$fehlt   = $status['aufraeumen'] || ( $an && ! file_exists( self::hauptdatei( self::NOVAMIRA ) ) );
		$eilig   = $status['stufe'] !== self::$zustand['stufe'] || ( $fehlt && time() - $status['geprueft'] > 5 * MINUTE_IN_SECONDS );
		if ( $naechster && $naechster > time() + 60 && $eilig ) {
			wp_clear_scheduled_hook( self::CRON );
		}
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time(), 'hourly', self::CRON );
		}

		if ( in_array( self::$zustand['stufe'], array( 'build', 'live' ), true ) ) {
			self::laden();
		}
	}

	public static function modul_dir() {
		return WP_PLUGIN_DIR . '/' . self::ORDNER;
	}

	private static function hauptdatei( $name ) {
		return self::modul_dir() . '/' . $name . '/' . $name . '.php';
	}

	/**
	 * Novamira (und Pro) aus dem Modul-Ordner laden, so wie WordPress ein Plugin laedt.
	 */
	private static function laden() {
		if ( ! file_exists( self::hauptdatei( self::NOVAMIRA ) ) ) {
			return;
		}
		if ( version_compare( PHP_VERSION, self::MIN_PHP, '<' ) || version_compare( $GLOBALS['wp_version'], self::MIN_WP, '<' ) ) {
			self::$hinweis = 'PHP oder WordPress zu alt fuer Novamira, nicht geladen.';
			return;
		}
		if ( 'live' === self::$zustand['stufe'] && self::sandbox_belegt() ) {
			self::$hinweis = 'Sandbox nicht leer (wp-content/novamira-sandbox). Auf Live-Seiten bleibt Novamira deshalb aus.';
			return;
		}
		// Ist Novamira zusaetzlich als normales Plugin aktiv, laden wir unsere Kopie nicht.
		foreach ( (array) get_option( 'active_plugins', array() ) as $plugin ) {
			if ( in_array( basename( $plugin ), array( 'novamira.php', 'novamira-pro.php' ), true ) && 0 !== strpos( $plugin, self::ORDNER . '/' ) ) {
				self::$hinweis = 'Novamira ist zusaetzlich als normales Plugin aktiv (' . $plugin . '). Die Kopie in BB Basic wird deshalb nicht geladen.';
				return;
			}
		}

		// Novamiras eigener An-Schalter folgt unserer Schalter-Datei.
		add_filter( 'pre_option_novamira_ai_abilities_enabled', array( __CLASS__, 'option_an' ) );
		add_filter( 'pre_option_novamira_ai_abilities_domain', array( __CLASS__, 'option_domain' ) );
		if ( 'live' === self::$zustand['stufe'] ) {
			add_action( 'wp_abilities_api_init', array( __CLASS__, 'live_sperre' ), PHP_INT_MAX );
		}

		foreach ( array( self::NOVAMIRA, self::PRO ) as $name ) {
			$datei = self::hauptdatei( $name );
			if ( ! file_exists( $datei ) ) {
				continue;
			}
			try {
				include_once $datei;
				self::$geladen[ $name ] = $datei;
			} catch ( Throwable $e ) {
				self::$hinweis = $name . ' konnte nicht geladen werden: ' . $e->getMessage();
				break;
			}
		}
		if ( empty( self::$geladen ) ) {
			return;
		}

		// Updates holt BB Basic selbst. Die Updater von Novamira und Pro wuerden sonst
		// ein Update fuer ein Plugin melden, das nicht in der Plugin-Liste steht.
		self::updater_abhaengen();

		add_action( 'admin_menu', array( __CLASS__, 'menue' ), PHP_INT_MAX );
		add_action( 'wp_loaded', array( __CLASS__, 'aktivieren' ) );
	}

	private static function updater_abhaengen() {
		global $wp_filter;
		foreach ( array( 'site_transient_update_plugins', 'pre_set_site_transient_update_plugins', 'plugins_api' ) as $hook ) {
			if ( empty( $wp_filter[ $hook ] ) ) {
				continue;
			}
			foreach ( $wp_filter[ $hook ]->callbacks as $prio => $eintraege ) {
				foreach ( $eintraege as $eintrag ) {
					if ( is_string( $eintrag['function'] ) && preg_match( '#^\\\\?novamira#i', $eintrag['function'] ) ) {
						remove_filter( $hook, $eintrag['function'], $prio );
					}
				}
			}
		}
	}

	/**
	 * Novamira-Menue im Backend nur fuer Konten @bueroblanko.de.
	 */
	public static function menue() {
		$mail = strtolower( (string) wp_get_current_user()->user_email );
		$ende = '@' . CODE_SYNC_ALLOWED_MAIL;
		if ( substr( $mail, -strlen( $ende ) ) !== $ende ) {
			remove_menu_page( 'novamira-connect' );
		}
	}

	/**
	 * Aktivierungs-Hooks (Tabellen, Cron) einmal je Version ausfuehren, wie beim
	 * Aktivieren eines Plugins.
	 */
	public static function aktivieren() {
		$status = self::status();
		$neu    = false;
		foreach ( self::$geladen as $name => $datei ) {
			$version = self::version( $name );
			if ( ! isset( $status['aktiviert'][ $name ] ) || $status['aktiviert'][ $name ] !== $version ) {
				do_action( 'activate_' . plugin_basename( $datei ), false );
				$status['aktiviert'][ $name ] = $version;
				$neu = true;
			}
		}
		if ( $neu ) {
			self::status_speichern( $status );
		}
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

	private static function status() {
		$status = get_option( self::OPTION );
		if ( ! is_array( $status ) ) {
			$status = array();
		}
		$status = array_merge(
			array(
				'stufe'       => 'clear',
				'installiert' => array(), // alte, sichtbar installierte Plugins (bis 0.0.16-Test)
				'aktiviert'   => array(), // Name => Version, fuer die die Aktivierungs-Hooks liefen
				'geprueft'    => 0,
				'update'      => 0,       // letzte Suche nach neuen Versionen
				'fehler'      => '',
				'lizenz'      => '',      // Lizenz, die zuletzt an Pro uebergeben wurde
			),
			$status
		);
		$status['aufraeumen'] = ! empty( $status['installiert'] );
		return $status;
	}

	private static function status_speichern( $status ) {
		unset( $status['aufraeumen'] );
		update_option( self::OPTION, $status, false );
	}

	public static function option_an() {
		return '1';
	}

	public static function option_domain() {
		return (string) wp_parse_url( home_url(), PHP_URL_HOST );
	}

	private static function version( $name ) {
		$datei = self::hauptdatei( $name );
		if ( ! file_exists( $datei ) ) {
			return '';
		}
		$daten = get_file_data( $datei, array( 'Version' => 'Version' ) );
		return (string) $daten['Version'];
	}

	/**
	 * Cron: bringt den Modul-Ordner auf den Stand der Schalter-Datei.
	 */
	public static function abgleich() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$status = self::status();
		$stufe  = self::$zustand['stufe'];
		$fehler = self::alte_plugins_entfernen( $status );

		if ( 'clear' === $stufe ) {
			$fehler = $fehler ? $fehler : self::entfernen( $status );
		} elseif ( 'sleep' === $stufe ) {
			self::cron_aufraeumen();
		} elseif ( ! $fehler ) {
			$fehler = self::bereitstellen( $status );
		}

		$status['stufe']    = $stufe;
		$status['fehler']   = $fehler;
		$status['geprueft'] = time();
		self::status_speichern( $status );

		if ( 'clear' === $stufe && '' === $fehler ) {
			delete_option( self::OPTION );
			wp_clear_scheduled_hook( self::CRON );
		}
	}

	/**
	 * Uebergang: Bis zum 0.0.16-Test hat BB Basic Novamira als sichtbares Plugin
	 * installiert. Diese Kopien kommen weg, Einstellungen und Daten bleiben.
	 */
	private static function alte_plugins_entfernen( &$status ) {
		$alte = array_values( array_intersect( self::$alt, $status['installiert'] ) );
		if ( empty( $alte ) ) {
			$status['installiert'] = array();
			return '';
		}
		deactivate_plugins( $alte, true );
		foreach ( $alte as $plugin ) {
			$ordner = WP_PLUGIN_DIR . '/' . dirname( $plugin );
			if ( is_dir( $ordner ) && ! self::ordner_loeschen( $ordner ) ) {
				return 'Altes Plugin ' . $plugin . ' konnte nicht geloescht werden.';
			}
		}
		$status['installiert'] = array();
		return '';
	}

	/**
	 * Stufe build oder live: fehlende Teile holen und alle 12 Stunden nach Updates sehen.
	 */
	private static function bereitstellen( &$status ) {
		if ( version_compare( PHP_VERSION, self::MIN_PHP, '<' ) ) {
			return 'PHP ' . PHP_VERSION . ' ist zu alt, Novamira braucht ' . self::MIN_PHP . '.';
		}
		if ( version_compare( $GLOBALS['wp_version'], self::MIN_WP, '<' ) ) {
			return 'WordPress ' . $GLOBALS['wp_version'] . ' ist zu alt, Novamira braucht ' . self::MIN_WP . '.';
		}

		$lizenz = self::$zustand['lizenz'];
		$suchen = time() - $status['update'] > self::UPDATE_ALLE;

		if ( $suchen || '' === self::version( self::NOVAMIRA ) ) {
			$neueste = self::novamira_zip();
			if ( is_wp_error( $neueste ) ) {
				return $neueste->get_error_message();
			}
			if ( version_compare( self::version( self::NOVAMIRA ), $neueste['version'], '<' ) ) {
				$fehler = self::installieren( $neueste['url'], self::NOVAMIRA );
				if ( $fehler ) {
					return $fehler;
				}
			}
		}

		if ( '' !== $lizenz && ( $suchen || '' === self::version( self::PRO ) ) ) {
			$neueste = self::pro_zip( $lizenz, self::version( self::PRO ) );
			if ( is_wp_error( $neueste ) ) {
				return $neueste->get_error_message();
			}
			if ( $neueste && version_compare( self::version( self::PRO ), $neueste['version'], '<' ) ) {
				$fehler = self::installieren( $neueste['url'], self::PRO );
				if ( $fehler ) {
					return $fehler;
				}
			}
		}
		if ( $suchen ) {
			$status['update'] = time();
		}

		// Pro bekommt die Lizenz einmal, und wieder, wenn sie sich in der Schalter-Datei aendert.
		if ( '' !== $lizenz && $lizenz !== $status['lizenz'] && function_exists( 'Novamira\\Pro\\activate_new_license_key' ) ) {
			try {
				\Novamira\Pro\activate_new_license_key( $lizenz );
				$status['lizenz'] = $lizenz;
			} catch ( Throwable $e ) {
				return 'Pro-Lizenz konnte nicht aktiviert werden: ' . $e->getMessage();
			}
		}
		return '';
	}

	/**
	 * Stufe clear: Novamira-Daten aufraeumen und den Modul-Ordner loeschen.
	 */
	private static function entfernen( &$status ) {
		self::cron_aufraeumen();
		// Wie beim Loeschen eines Plugins: deren uninstall.php raeumt Daten auf,
		// Pro meldet dabei auch die Lizenz fuer diese Domain ab. Nur wenn die
		// Dateien in diesem Aufruf nicht geladen sind (Clear wirkt im naechsten Aufruf).
		if ( empty( self::$geladen ) ) {
			foreach ( array( self::PRO, self::NOVAMIRA ) as $name ) {
				$uninstall = self::modul_dir() . '/' . $name . '/uninstall.php';
				if ( ! file_exists( $uninstall ) ) {
					continue;
				}
				if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
					define( 'WP_UNINSTALL_PLUGIN', self::ORDNER . '/' . $name . '/' . $name . '.php' );
				}
				try {
					include $uninstall;
				} catch ( Throwable $e ) {
					// Daten bleiben dann liegen, der Ordner kommt trotzdem weg.
					unset( $e );
				}
			}
		}
		if ( is_dir( self::modul_dir() ) && ! self::ordner_loeschen( self::modul_dir() ) ) {
			return 'Ordner ' . self::ORDNER . ' konnte nicht geloescht werden.';
		}
		$status['aktiviert'] = array();
		$status['lizenz']    = '';
		return '';
	}

	/**
	 * Geplante Novamira-Aufgaben abmelden (das, was sonst beim Deaktivieren passiert).
	 */
	private static function cron_aufraeumen() {
		foreach ( (array) _get_cron_array() as $termine ) {
			foreach ( array_keys( (array) $termine ) as $hook ) {
				if ( 0 === strpos( $hook, 'novamira' ) || 0 === strpos( $hook, 'nvp' ) ) {
					wp_clear_scheduled_hook( $hook );
				}
			}
		}
	}

	private static function dateisystem() {
		global $wp_filesystem;
		if ( ! $wp_filesystem && ! WP_Filesystem() ) {
			return null;
		}
		return $wp_filesystem;
	}

	private static function ordner_loeschen( $ordner ) {
		$fs = self::dateisystem();
		return $fs && $fs->delete( $ordner, true );
	}

	/**
	 * ZIP laden, in einen Zwischenordner entpacken und den alten Stand ersetzen.
	 */
	private static function installieren( $url, $name ) {
		$fs = self::dateisystem();
		if ( ! $fs ) {
			return 'Kein Schreibzugriff auf wp-content/plugins.';
		}
		$zip = download_url( $url, 120 );
		if ( is_wp_error( $zip ) ) {
			return 'Download von ' . $name . ' fehlgeschlagen: ' . $zip->get_error_message();
		}

		$basis = self::modul_dir();
		$neu   = $basis . '/.neu-' . $name;
		if ( ! is_dir( $basis ) ) {
			wp_mkdir_p( $basis );
		}
		$fs->delete( $neu, true );
		$ergebnis = unzip_file( $zip, $neu );
		@unlink( $zip );
		if ( is_wp_error( $ergebnis ) || ! file_exists( $neu . '/' . $name . '/' . $name . '.php' ) ) {
			$fs->delete( $neu, true );
			return 'Paket von ' . $name . ' ist unvollstaendig' . ( is_wp_error( $ergebnis ) ? ': ' . $ergebnis->get_error_message() : '.' );
		}

		$ziel = $basis . '/' . $name;
		$fs->delete( $ziel, true );
		$ok = $fs->move( $neu . '/' . $name, $ziel, true );
		$fs->delete( $neu, true );
		return $ok ? '' : 'Ersetzen von ' . $name . ' fehlgeschlagen.';
	}

	/**
	 * Neueste Novamira-Version aus den GitHub-Releases: array( version, url ).
	 */
	private static function novamira_zip() {
		$antwort = wp_remote_get( self::GITHUB_API, array( 'timeout' => 15, 'headers' => array( 'Accept' => 'application/vnd.github+json' ) ) );
		if ( is_wp_error( $antwort ) || 200 !== wp_remote_retrieve_response_code( $antwort ) ) {
			// Die GitHub-API erlaubt je Server-IP nur 60 Abfragen pro Stunde, auf
			// geteilten Servern ist das schnell aufgebraucht. Dann die Weiterleitung
			// der Release-Seite auf die neueste Version lesen.
			$weiter = self::novamira_zip_ohne_api();
			if ( $weiter ) {
				return $weiter;
			}
			$code = is_wp_error( $antwort ) ? $antwort->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $antwort );
			return new WP_Error( 'bb_novamira', 'GitHub nicht erreichbar (' . $code . '), Novamira nicht geprueft.' );
		}
		$daten = json_decode( wp_remote_retrieve_body( $antwort ), true );
		foreach ( isset( $daten['assets'] ) ? (array) $daten['assets'] : array() as $datei ) {
			if ( isset( $datei['name'], $datei['browser_download_url'] )
				&& preg_match( '#^novamira-([0-9.]+)\.zip$#', $datei['name'], $treffer )
				&& 0 === strpos( $datei['browser_download_url'], 'https://github.com/use-novamira/novamira/' ) ) {
				return array( 'version' => $treffer[1], 'url' => $datei['browser_download_url'] );
			}
		}
		return new WP_Error( 'bb_novamira', 'Im neuesten GitHub-Release liegt keine Novamira-ZIP.' );
	}

	private static function novamira_zip_ohne_api() {
		$antwort = wp_remote_head( 'https://github.com/use-novamira/novamira/releases/latest', array( 'timeout' => 15, 'redirection' => 0 ) );
		$ziel    = is_wp_error( $antwort ) ? '' : (string) wp_remote_retrieve_header( $antwort, 'location' );
		if ( ! preg_match( '#^https://github\.com/use-novamira/novamira/releases/tag/(v?([0-9.]+))$#', $ziel, $treffer ) ) {
			return null;
		}
		return array(
			'version' => $treffer[2],
			'url'     => 'https://github.com/use-novamira/novamira/releases/download/' . $treffer[1] . '/novamira-' . $treffer[2] . '.zip',
		);
	}

	/**
	 * Neueste Version von Novamira Pro: Lizenz fuer diese Domain aktivieren, dann
	 * beim Lizenzserver nach dem Paket fragen (wie Pros eigener Updater).
	 * Gibt array( version, url ) zurueck, oder null, wenn nichts Neueres da ist.
	 */
	private static function pro_zip( $lizenz, $installiert ) {
		$domain = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		if ( '' === $installiert ) {
			$aktiv = wp_remote_get( add_query_arg( array(
				'woo_sl_action'     => 'activate',
				'licence_key'       => $lizenz,
				'product_unique_id' => self::PRO_PRODUKT,
				'domain'            => $domain,
				'api_version'       => '1.1',
			), self::PRO_API . 'api.php' ), array( 'timeout' => 15 ) );
			if ( is_wp_error( $aktiv ) ) {
				return new WP_Error( 'bb_novamira', 'Lizenzserver von Novamira Pro nicht erreichbar.' );
			}
		}

		$info = wp_remote_get( add_query_arg( array(
			'domain'      => $domain,
			'version'     => '' === $installiert ? '0' : $installiert,
			'licence_key' => $lizenz,
			'beta'        => 'false',
		), self::PRO_API . 'info.php' ), array( 'timeout' => 15, 'headers' => array( 'Accept' => 'application/json' ) ) );
		$daten = is_wp_error( $info ) ? null : json_decode( wp_remote_retrieve_body( $info ), true );
		if ( ! is_array( $daten ) ) {
			return new WP_Error( 'bb_novamira', 'Lizenzserver von Novamira Pro nicht erreichbar.' );
		}
		if ( empty( $daten['download_url'] ) || empty( $daten['version'] ) ) {
			return '' === $installiert ? new WP_Error( 'bb_novamira', 'Kein Pro-Download erhalten. Lizenzschluessel pruefen.' ) : null;
		}
		return array( 'version' => (string) $daten['version'], 'url' => $daten['download_url'] );
	}

	private static function sandbox_belegt() {
		$dateien = glob( WP_CONTENT_DIR . '/novamira-sandbox/*.php' );
		return ! empty( $dateien );
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

	public static function stufe() {
		return self::$zustand ? self::$zustand['stufe'] : 'clear';
	}

	/** Stufen, auf die im Backend heruntergeschaltet werden darf (Hochschalten nur per FTP). */
	public static function niedrigere_stufen() {
		$reihe = array( 'clear', 'sleep', 'live', 'build' );
		$pos   = array_search( self::stufe(), $reihe, true );
		return array_reverse( array_slice( $reihe, 0, (int) $pos ) );
	}

	/**
	 * Im Backend herunterschalten. Hochschalten geht absichtlich nur per FTP,
	 * damit ein fremdes Admin-Konto den Developer-Modus nicht einschalten kann.
	 */
	public static function herunterschalten( $neu ) {
		if ( ! in_array( $neu, self::niedrigere_stufen(), true ) ) {
			return 'Diese Stufe ist von hier aus nicht erlaubt.';
		}
		$datei = WP_CONTENT_DIR . '/' . self::SCHALTER;
		if ( 'clear' === $neu ) {
			// Datei samt Lizenzschluessel entfernen.
			if ( file_exists( $datei ) && ! @unlink( $datei ) ) {
				return 'Schalter-Datei konnte nicht geloescht werden.';
			}
			self::$zustand = array( 'stufe' => 'clear', 'lizenz' => '', 'grund' => '' );
		} else {
			$zeilen = array( '<?php exit; ?>', 'stufe=' . $neu, 'bis=' . gmdate( 'Y-m-d', self::$zustand['bis'] ) );
			if ( '' !== self::$zustand['lizenz'] ) {
				$zeilen[] = 'lizenz=' . self::$zustand['lizenz'];
			}
			if ( false === @file_put_contents( $datei, implode( "\n", $zeilen ) . "\n" ) ) {
				return 'Schalter-Datei konnte nicht geschrieben werden.';
			}
			self::$zustand = self::schalter_lesen( $datei );
		}
		// Der Abgleich laeuft im naechsten Aufruf, in dem Novamira schon nicht mehr geladen ist.
		wp_clear_scheduled_hook( self::CRON );
		wp_schedule_event( time(), 'hourly', self::CRON );
		spawn_cron();
		return '';
	}

	/**
	 * Daten fuer die Anzeige unter Werkzeuge → BB Basic.
	 */
	public static function anzeige() {
		$zustand = self::$zustand ? self::$zustand : array( 'stufe' => 'clear' );
		$status  = self::status();
		$namen   = array(
			'clear' => 'Clear (kein Novamira von BB Basic auf der Seite)',
			'sleep' => 'Sleep (Novamira liegt bereit, wird nicht geladen)',
			'live'  => 'Live (nur lesend)',
			'build' => 'Build (Baustelle, alles erlaubt)',
		);
		if ( 'sleep' === $zustand['stufe'] && '' === self::version( self::NOVAMIRA ) ) {
			$namen['sleep'] = 'Sleep (Novamira ist nicht auf der Seite, wird erst bei Build oder Live geholt)';
		}
		$zeilen = array( $namen[ $zustand['stufe'] ] );
		if ( 'clear' !== $zustand['stufe'] ) {
			$zeilen[] = 'Gültig bis ' . wp_date( 'd.m.Y', $zustand['bis'] );
		}
		foreach ( array( self::NOVAMIRA => 'Novamira', self::PRO => 'Novamira Pro' ) as $name => $titel ) {
			$version = self::version( $name );
			if ( '' !== $version ) {
				$zeilen[] = $titel . ' ' . $version . ( isset( self::$geladen[ $name ] ) ? ' (geladen)' : ' (nicht geladen)' );
			}
		}
		if ( self::$hinweis ) {
			$zeilen[] = 'Hinweis: ' . self::$hinweis;
		}
		if ( $status['geprueft'] ) {
			$zeilen[] = 'Zuletzt abgeglichen am ' . wp_date( 'd.m.Y H:i', $status['geprueft'] ) . ( $status['fehler'] ? ': ' . $status['fehler'] : '' );
		}
		if ( ! empty( $zustand['grund'] ) ) {
			$zeilen[] = 'Hinweis: ' . $zustand['grund'];
		}
		return $zeilen;
	}
}
