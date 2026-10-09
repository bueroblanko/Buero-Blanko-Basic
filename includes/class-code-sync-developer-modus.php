<?php

/**
 * Developer-Modus: installiert Novamira und Novamira Pro (AGPL-3.0-or-later, Ovation S.r.l.)
 * als ganz normale Plugins, aber nur wenn per FTP die Schalter-Datei
 * wp-content/bb-developer-modus.php liegt. Ohne Datei passiert nichts.
 *
 * Schalter-Datei (erste Zeile <?php exit; ?>, dann je Zeile schluessel=wert):
 *   stufe=build|live|sleep|clear
 *                            build = alles erlaubt, live = nur lesende Werkzeuge,
 *                            sleep = Plugins bleiben liegen, sind aber deaktiviert,
 *                            clear = Plugins werden geloescht (wie ohne Schalter-Datei)
 *   bis=JJJJ-MM-TT           optional, sonst 14 Tage nach Aenderung der Datei
 *   lizenz=...               optional, Lizenzschluessel fuer Novamira Pro
 *
 * Novamira kommt aus den GitHub-Releases des Herstellers, Pro ueber dessen
 * Lizenzserver. Danach aktualisieren sich beide selbst wie jedes andere Plugin.
 * Geloescht werden nur Plugins, die BB Basic selbst installiert hat.
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

	const NOVAMIRA      = 'novamira/novamira.php';
	const PRO           = 'novamira-pro/novamira-pro.php';
	const GITHUB_API    = 'https://api.github.com/repos/use-novamira/novamira/releases/latest';
	const PRO_API       = 'https://license.dynamic.ooo/novamira-pro/';
	const PRO_PRODUKT   = 'WP-NVP-1';

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
	 * Wird beim Laden von BB Basic aufgerufen. Haengt nur Hooks ein, die eigentliche
	 * Arbeit (Installieren, Aktivieren, Loeschen) laeuft per Cron.
	 */
	public static function start() {
		$datei = WP_CONTENT_DIR . '/' . self::SCHALTER;
		self::$zustand = file_exists( $datei ) ? self::schalter_lesen( $datei ) : array( 'stufe' => 'clear', 'lizenz' => '', 'grund' => '' );

		add_action( self::CRON, array( __CLASS__, 'abgleich' ) );
		add_filter( 'auto_update_plugin', array( __CLASS__, 'auto_update' ), 10, 2 );

		$status = self::status();
		if ( 'clear' === self::$zustand['stufe'] && empty( $status['installiert'] ) ) {
			// Normalfall auf allen Seiten: nichts zu tun.
			if ( wp_next_scheduled( self::CRON ) ) {
				wp_clear_scheduled_hook( self::CRON );
			}
			return;
		}

		// Abgleich sofort, wenn sich die Stufe geaendert hat, sonst stuendlich.
		$naechster = wp_next_scheduled( self::CRON );
		if ( $naechster && $naechster > time() + 60 && $status['stufe'] !== self::$zustand['stufe'] ) {
			wp_clear_scheduled_hook( self::CRON );
		}
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time(), 'hourly', self::CRON );
		}

		if ( in_array( self::$zustand['stufe'], array( 'build', 'live' ), true ) ) {
			// Novamiras eigener An-Schalter folgt unserer Schalter-Datei.
			add_filter( 'pre_option_novamira_ai_abilities_enabled', array( __CLASS__, 'option_an' ) );
			add_filter( 'pre_option_novamira_ai_abilities_domain', array( __CLASS__, 'option_domain' ) );
		}
		if ( 'live' === self::$zustand['stufe'] ) {
			add_action( 'wp_abilities_api_init', array( __CLASS__, 'live_sperre' ), PHP_INT_MAX );
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
		return array_merge(
			array(
				'stufe'       => 'clear',
				'installiert' => array(), // Plugin-Dateien, die BB Basic selbst installiert hat
				'geprueft'    => 0,
				'fehler'      => '',
				'lizenz'      => '',      // Lizenz, die zuletzt an Pro uebergeben wurde
			),
			$status
		);
	}

	private static function status_speichern( $status ) {
		update_option( self::OPTION, $status, false );
	}

	public static function option_an() {
		return '1';
	}

	public static function option_domain() {
		return (string) wp_parse_url( home_url(), PHP_URL_HOST );
	}

	/**
	 * Von BB Basic installierte Plugins aktualisieren sich automatisch.
	 */
	public static function auto_update( $update, $item ) {
		if ( ! isset( $item->plugin ) || ! in_array( self::$zustand['stufe'], array( 'build', 'live' ), true ) ) {
			return $update;
		}
		return in_array( $item->plugin, self::status()['installiert'], true ) ? true : $update;
	}

	/**
	 * Cron: bringt die Plugins auf den Stand der Schalter-Datei.
	 */
	public static function abgleich() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$status = self::status();
		$stufe  = self::$zustand['stufe'];
		$fehler = '';

		if ( 'clear' === $stufe ) {
			$fehler = self::entfernen( $status );
		} elseif ( 'sleep' === $stufe ) {
			deactivate_plugins( array_intersect( array( self::PRO, self::NOVAMIRA ), $status['installiert'] ), true );
		} else {
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
	 * Stufe build oder live: installieren, falls noetig, und aktivieren.
	 */
	private static function bereitstellen( &$status ) {
		if ( version_compare( PHP_VERSION, self::MIN_PHP, '<' ) ) {
			return 'PHP ' . PHP_VERSION . ' ist zu alt, Novamira braucht ' . self::MIN_PHP . '.';
		}
		if ( version_compare( $GLOBALS['wp_version'], self::MIN_WP, '<' ) ) {
			return 'WordPress ' . $GLOBALS['wp_version'] . ' ist zu alt, Novamira braucht ' . self::MIN_WP . '.';
		}

		$lizenz = self::$zustand['lizenz'];
		$plugins = get_plugins();

		if ( ! isset( $plugins[ self::NOVAMIRA ] ) ) {
			$url = self::novamira_zip();
			if ( is_wp_error( $url ) ) {
				return $url->get_error_message();
			}
			$fehler = self::installieren( $url, self::NOVAMIRA, $status );
			if ( $fehler ) {
				return $fehler;
			}
		}

		if ( '' !== $lizenz && ! isset( $plugins[ self::PRO ] ) ) {
			$url = self::pro_zip( $lizenz );
			if ( is_wp_error( $url ) ) {
				return $url->get_error_message();
			}
			$fehler = self::installieren( $url, self::PRO, $status );
			if ( $fehler ) {
				return $fehler;
			}
		}

		if ( 'live' === self::$zustand['stufe'] && self::sandbox_belegt() ) {
			deactivate_plugins( array( self::PRO, self::NOVAMIRA ), true );
			return 'Sandbox nicht leer (wp-content/novamira-sandbox). Auf Live-Seiten bleibt Novamira deshalb aus.';
		}

		foreach ( array( self::NOVAMIRA, self::PRO ) as $datei ) {
			if ( file_exists( WP_PLUGIN_DIR . '/' . $datei ) && ! is_plugin_active( $datei ) ) {
				$ergebnis = activate_plugin( $datei );
				if ( is_wp_error( $ergebnis ) ) {
					return 'Aktivieren von ' . $datei . ' fehlgeschlagen: ' . $ergebnis->get_error_message();
				}
			}
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
	 * Stufe clear: nur die Plugins loeschen, die BB Basic selbst installiert hat.
	 */
	private static function entfernen( &$status ) {
		$eigene = array_intersect( array( self::PRO, self::NOVAMIRA ), $status['installiert'] );
		if ( empty( $eigene ) ) {
			return '';
		}
		deactivate_plugins( $eigene, true );
		$vorhanden = array_values( array_filter( $eigene, array( __CLASS__, 'plugin_da' ) ) );
		if ( $vorhanden ) {
			$ergebnis = delete_plugins( $vorhanden );
			if ( is_wp_error( $ergebnis ) || ! $ergebnis ) {
				return 'Loeschen fehlgeschlagen' . ( is_wp_error( $ergebnis ) ? ': ' . $ergebnis->get_error_message() : '.' );
			}
		}
		$status['installiert'] = array();
		return '';
	}

	public static function plugin_da( $datei ) {
		return file_exists( WP_PLUGIN_DIR . '/' . $datei );
	}

	private static function installieren( $url, $datei, &$status ) {
		$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
		$ergebnis = $upgrader->install( $url );
		if ( is_wp_error( $ergebnis ) || ! $ergebnis || ! self::plugin_da( $datei ) ) {
			$text = is_wp_error( $ergebnis ) ? $ergebnis->get_error_message() : implode( ' ', (array) $upgrader->skin->get_upgrade_messages() );
			return 'Installieren von ' . $datei . ' fehlgeschlagen. ' . $text;
		}
		$status['installiert'][] = $datei;
		$status['installiert']   = array_values( array_unique( $status['installiert'] ) );
		// Sofort speichern, damit clear das Plugin auch nach einem spaeteren Fehler findet.
		self::status_speichern( $status );
		return '';
	}

	/**
	 * Download-Adresse der neuesten Novamira-Version aus den GitHub-Releases.
	 */
	private static function novamira_zip() {
		$antwort = wp_remote_get( self::GITHUB_API, array( 'timeout' => 15, 'headers' => array( 'Accept' => 'application/vnd.github+json' ) ) );
		if ( is_wp_error( $antwort ) || 200 !== wp_remote_retrieve_response_code( $antwort ) ) {
			return new WP_Error( 'bb_novamira', 'GitHub nicht erreichbar, Novamira nicht installiert.' );
		}
		$daten = json_decode( wp_remote_retrieve_body( $antwort ), true );
		foreach ( isset( $daten['assets'] ) ? (array) $daten['assets'] : array() as $datei ) {
			if ( isset( $datei['name'], $datei['browser_download_url'] )
				&& preg_match( '#^novamira-[0-9.]+\.zip$#', $datei['name'] )
				&& 0 === strpos( $datei['browser_download_url'], 'https://github.com/use-novamira/novamira/' ) ) {
				return $datei['browser_download_url'];
			}
		}
		return new WP_Error( 'bb_novamira', 'Im neuesten GitHub-Release liegt keine Novamira-ZIP.' );
	}

	/**
	 * Download-Adresse von Novamira Pro: Lizenz fuer diese Domain aktivieren, dann
	 * beim Lizenzserver nach dem Paket fragen (wie Pros eigener Updater).
	 */
	private static function pro_zip( $lizenz ) {
		$domain = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$aktiv  = wp_remote_get( add_query_arg( array(
			'woo_sl_action'     => 'activate',
			'licence_key'       => $lizenz,
			'product_unique_id' => self::PRO_PRODUKT,
			'domain'            => $domain,
			'api_version'       => '1.1',
		), self::PRO_API . 'api.php' ), array( 'timeout' => 15 ) );
		if ( is_wp_error( $aktiv ) ) {
			return new WP_Error( 'bb_novamira', 'Lizenzserver von Novamira Pro nicht erreichbar.' );
		}

		$info = wp_remote_get( add_query_arg( array(
			'domain'      => $domain,
			'version'     => '0',
			'licence_key' => $lizenz,
			'beta'        => 'false',
		), self::PRO_API . 'info.php' ), array( 'timeout' => 15, 'headers' => array( 'Accept' => 'application/json' ) ) );
		$daten = is_wp_error( $info ) ? null : json_decode( wp_remote_retrieve_body( $info ), true );
		if ( ! is_array( $daten ) || empty( $daten['download_url'] ) ) {
			return new WP_Error( 'bb_novamira', 'Kein Pro-Download erhalten. Lizenzschluessel pruefen.' );
		}
		return $daten['download_url'];
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
		self::abgleich();
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
			'sleep' => 'Sleep (Novamira deaktiviert)',
			'live'  => 'Live (nur lesend)',
			'build' => 'Build (Baustelle, alles erlaubt)',
		);
		$zeilen = array( $namen[ $zustand['stufe'] ] );
		if ( 'clear' !== $zustand['stufe'] ) {
			$zeilen[] = 'Gültig bis ' . wp_date( 'd.m.Y', $zustand['bis'] );
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = get_plugins();
		foreach ( array( self::NOVAMIRA => 'Novamira', self::PRO => 'Novamira Pro' ) as $datei => $name ) {
			if ( isset( $plugins[ $datei ] ) ) {
				$zeilen[] = $name . ' ' . $plugins[ $datei ]['Version'] . ( is_plugin_active( $datei ) ? ' (aktiv)' : ' (inaktiv)' );
			}
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
