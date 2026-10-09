<?php

/**
 * Freigabeweg (Fahrplan Schritt 3): Auf Live-Seiten darf Novamira veroeffentlichte
 * Inhalte nicht direkt aendern. Claude legt eine Entwurfskopie an (mit allen Divi-,
 * SEO- und CSS-Einstellungen), bearbeitet nur diese und bittet um Freigabe. Ein
 * Admin-Konto @bueroblanko.de gibt unter Werkzeuge → Freigaben frei. Dabei:
 * Konfliktpruefung gegen die Live-Seite, Sicherung der alten Fassung, Rueckgaengig
 * mit einem Klick, Caches leeren, Protokoll.
 *
 * Die Sperre greift nur in Stufe live und nur waehrend Werkzeug-Aufrufen (Abilities).
 * Wer im Backend von Hand arbeitet, merkt davon nichts.
 *
 * @package    Code_Sync
 * @subpackage Code_Sync/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Code_Sync_Freigabe {

	const ENTWURF_VON   = '_bb_entwurf_von';      // am Entwurf: ID der Live-Seite
	const BASIS         = '_bb_entwurf_basis';    // am Entwurf: Pruefsumme der Live-Seite beim Anlegen
	const ANGEFRAGT     = '_bb_entwurf_angefragt'; // am Entwurf: Zeitpunkt der Freigabe-Anfrage
	const NOTIZ         = '_bb_entwurf_notiz';
	const SICHERUNG_VON = '_bb_sicherung_von';    // an der Sicherung: ID der Live-Seite
	const SICHERUNG     = 'bb_sicherung';         // Post-Status der Sicherungen
	const PROTOKOLL     = 'code_sync_freigabe_protokoll';
	const SEITE         = 'bb-freigaben';
	const MAX_SICHERUNG = 30;
	const MAX_PROTOKOLL = 200;

	// Werkzeuge, die in Stufe live zusaetzlich erlaubt sind, aber nur auf Entwuerfen.
	// Globale Divi-Werkzeuge (Presets, Farben, Schriften, Variablen, Theme Builder,
	// Bibliothek anlegen) bleiben gesperrt.
	public static $entwurf_werkzeuge = array(
		'novamira/divi-add-module',
		'novamira/divi-edit-module',
		'novamira/divi-delete-module',
		'novamira/divi-move-module',
		'novamira/divi-set-content',
		'novamira/divi-apply-dynamic-content',
		'novamira/divi-clear-dynamic-content',
		'novamira/divi-apply-library-item',
		'novamira/divi-apply-global-preset',
		'novamira/divi-set-display-conditions',
		'novamira/divi-set-interactions',
		'novamira/divi-enable-loop',
		'novamira/divi-disable-loop',
		'novamira/divi-edit-loop',
	);

	// Diese Meta-Felder gehoeren zur Seite selbst und werden nie kopiert.
	private static $meta_aus = array( '_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date', '_wp_trash_meta_status', '_wp_trash_meta_time', '_wp_desired_post_slug', '_encloseme', '_pingme' );

	private static $stufe   = 'clear';
	private static $schutz  = false; // true, sobald in Stufe live ein Werkzeug laeuft
	private static $intern  = false; // true, waehrend BB Basic selbst kopiert
	private static $mcp     = false; // true in einem MCP-Werkzeugaufruf, der Ausnahmen abfaengt

	public static function start( $stufe ) {
		self::$stufe = $stufe;
		if ( 'clear' === $stufe ) {
			return;
		}
		add_action( 'init', array( __CLASS__, 'status_anmelden' ) );
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'kategorie_anmelden' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'werkzeuge_anmelden' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menue' ) );
		add_action( 'admin_post_bb_freigabe', array( __CLASS__, 'aktion' ) );
		add_action( 'admin_post_bb_entwurf', array( __CLASS__, 'entwurf_aktion' ) );
		add_filter( 'display_post_states', array( __CLASS__, 'kennzeichnen' ), 10, 2 );
		add_filter( 'page_row_actions', array( __CLASS__, 'zeilen_aktion' ), 10, 2 );
		add_filter( 'post_row_actions', array( __CLASS__, 'zeilen_aktion' ), 10, 2 );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'post_schutz' ), PHP_INT_MAX, 2 );

		if ( 'live' === $stufe ) {
			add_action( 'wp_before_execute_ability', array( __CLASS__, 'schutz_an' ), 1 );
			add_filter( 'novamira_mcp_adapter_pre_tool_call', array( __CLASS__, 'vorpruefung' ), 10, 2 );
			add_filter( 'wp_insert_post_empty_content', array( __CLASS__, 'post_sperre' ), PHP_INT_MAX, 2 );
			add_filter( 'pre_delete_post', array( __CLASS__, 'loesch_schutz' ), PHP_INT_MAX, 2 );
			add_filter( 'pre_trash_post', array( __CLASS__, 'loesch_schutz' ), PHP_INT_MAX, 2 );
			foreach ( array( 'add', 'update', 'delete' ) as $art ) {
				add_filter( $art . '_post_metadata', array( __CLASS__, 'meta_schutz' ), PHP_INT_MAX, 2 );
			}
			add_filter( 'novamira_discover_abilities_instructions', array( __CLASS__, 'anleitung' ) );
		}
	}

	/** Ist ein Werkzeug in Stufe live erlaubt? (Ergaenzt die Liste im Developer-Modus.) */
	public static function live_erlaubt( $name ) {
		return 0 === strpos( $name, 'bb-basic/' ) || in_array( $name, self::$entwurf_werkzeuge, true );
	}

	/* ------------------------------------------------------------------ */
	/* Sperre waehrend Werkzeug-Aufrufen (nur Stufe live)                  */
	/* ------------------------------------------------------------------ */

	public static function schutz_an() {
		self::$schutz = true;
	}

	/**
	 * Klare Fehlermeldung, bevor ein Entwurfs-Werkzeug auf eine Live-Seite zielt.
	 * Die eigentliche Sperre sitzt in post_schutz und meta_schutz.
	 */
	public static function vorpruefung( $args, $werkzeug ) {
		self::$mcp = true;
		if ( ! is_array( $args ) || empty( $args['ability_name'] ) || ! in_array( $args['ability_name'], self::$entwurf_werkzeuge, true ) ) {
			return $args;
		}
		$p  = isset( $args['parameters'] ) && is_array( $args['parameters'] ) ? $args['parameters'] : array();
		$id = 0;
		foreach ( array( 'post', 'post_id', 'target_id' ) as $schluessel ) {
			if ( isset( $p[ $schluessel ] ) && is_numeric( $p[ $schluessel ] ) ) {
				$id = (int) $p[ $schluessel ];
				break;
			}
		}
		if ( $id && ! self::ist_entwurf( $id ) ) {
			return new WP_Error( 'bb_gesperrt', self::sperr_text( $id ) );
		}
		return $args;
	}

	private static function sperr_text( $id ) {
		return sprintf(
			'BB Basic: Post %d is locked on this live site. Call bb-basic/entwurf-anlegen with post_id %d, edit the returned draft (entwurf_id) instead, then call bb-basic/freigabe-anfragen. A Buero Blanko admin approves it in wp-admin.',
			$id,
			$id
		);
	}

	private static function gesperrt( $id ) {
		if ( ! self::$schutz || self::$intern || $id <= 0 ) {
			return false;
		}
		$post = get_post( $id );
		if ( ! $post ) {
			return false;
		}
		if ( 'revision' === $post->post_type ) {
			return self::gesperrt( (int) $post->post_parent );
		}
		return ! self::ist_entwurf( $id );
	}

	/**
	 * Sperre ausloesen. Im MCP-Aufruf mit Ausnahme, die Novamira als klare Fehlermeldung
	 * an Claude zurueckgibt. Andere Wege (Novamira-Chat, REST) fangen Ausnahmen nicht ab,
	 * dort scheitert der Schreibvorgang still, damit kein Fatal Error entsteht.
	 */
	private static function blockieren( $text ) {
		if ( self::$mcp ) {
			throw new RuntimeException( $text );
		}
		return true;
	}

	/** Bricht wp_insert_post/wp_update_post ab, bevor etwas geschrieben wird. */
	public static function post_sperre( $leer, $postarr ) {
		if ( ! self::$schutz || self::$intern ) {
			return $leer;
		}
		$id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		if ( $id > 0 && self::gesperrt( $id ) ) {
			return self::blockieren( self::sperr_text( $id ) );
		}
		$status = isset( $postarr['post_status'] ) ? $postarr['post_status'] : 'draft';
		if ( ! $id && ! in_array( $status, array( 'draft', 'auto-draft', 'pending', 'inherit' ), true ) ) {
			return self::blockieren( 'BB Basic: On this live site new content can only be created as a draft.' );
		}
		return $leer;
	}

	/**
	 * Entwuerfe bleiben Entwuerfe, auch wenn jemand im Builder auf Veroeffentlichen klickt.
	 * Veroeffentlicht wird nur ueber die Freigabe. In den Papierkorb duerfen sie.
	 */
	public static function post_schutz( $daten, $postarr ) {
		$id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		if ( ! self::$intern && $id > 0 && isset( $daten['post_status'] ) && in_array( $daten['post_status'], array( 'publish', 'future', 'private' ), true ) && self::ist_entwurf( $id ) ) {
			$daten['post_status'] = get_post_status( $id );
		}
		return $daten;
	}

	public static function meta_schutz( $weiter, $id ) {
		if ( null === $weiter && self::gesperrt( (int) $id ) ) {
			return self::blockieren( self::sperr_text( (int) $id ) ) ? false : $weiter;
		}
		return $weiter;
	}

	public static function loesch_schutz( $weiter, $post ) {
		if ( null === $weiter && $post && self::gesperrt( (int) $post->ID ) ) {
			return self::blockieren( self::sperr_text( (int) $post->ID ) ) ? false : $weiter;
		}
		return $weiter;
	}

	public static function anleitung( $text ) {
		return $text . "\n\nBB Basic live site rules: Published content is locked. To change a page or post, call bb-basic/entwurf-anlegen (post_id) to get a draft copy with all Divi, SEO and CSS settings, edit only that draft with the novamira/divi-* tools, then call bb-basic/freigabe-anfragen (entwurf_id, notiz). A Buero Blanko admin reviews and approves it in wp-admin. Global Divi changes (presets, colors, fonts, variables, theme builder) are not available on live sites.";
	}

	/* ------------------------------------------------------------------ */
	/* Entwuerfe                                                           */
	/* ------------------------------------------------------------------ */

	public static function ist_entwurf( $id ) {
		return (int) get_post_meta( $id, self::ENTWURF_VON, true ) > 0;
	}

	private static function entwurf_zu( $original ) {
		$treffer = get_posts( array(
			'post_type'        => self::typen(),
			'post_status'      => array( 'draft', 'pending' ),
			'meta_key'         => self::ENTWURF_VON,
			'meta_value'       => (int) $original,
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'suppress_filters' => true,
		) );
		return $treffer ? (int) $treffer[0] : 0;
	}

	/**
	 * Pruefsumme der Live-Seite. Jede Bearbeitung ueber WordPress oder Divi aendert
	 * Inhalt oder Aenderungszeit. Meta-Felder bleiben aussen vor, weil Divi dort beim
	 * Anzeigen eigene Zwischenstaende ablegt.
	 */
	private static function pruefsumme( $id ) {
		$post = get_post( $id );
		if ( ! $post ) {
			return '';
		}
		return md5( $post->post_title . "\0" . $post->post_content . "\0" . $post->post_excerpt . "\0" . $post->post_modified_gmt );
	}

	/** Inhaltstypen, fuer die es Entwuerfe geben kann. */
	private static function typen() {
		return array_values( array_diff( get_post_types( array( 'show_ui' => true ) ), array( 'attachment', 'wp_block', 'wp_navigation', 'wp_template', 'wp_template_part', 'wp_font_family', 'wp_font_face', 'wp_global_styles', 'user_request' ) ) );
	}

	/**
	 * Entwurfskopie anlegen oder die vorhandene zurueckgeben.
	 *
	 * @return array|WP_Error
	 */
	public static function entwurf_anlegen( $original ) {
		$post = get_post( $original );
		if ( ! $post || ! in_array( $post->post_type, self::typen(), true ) ) {
			return new WP_Error( 'bb_freigabe', 'Post not found or not supported.' );
		}
		if ( self::ist_entwurf( $original ) ) {
			return new WP_Error( 'bb_freigabe', 'This post is already a BB draft (entwurf_id ' . (int) $original . '). Edit it directly.' );
		}
		if ( self::SICHERUNG === $post->post_status ) {
			return new WP_Error( 'bb_freigabe', 'This post is a backup and cannot be edited.' );
		}
		$vorhanden = self::entwurf_zu( $original );
		if ( $vorhanden ) {
			return self::entwurf_daten( $vorhanden, false );
		}

		$neu = self::kopieren( $post, 0, 'draft' );
		if ( is_wp_error( $neu ) ) {
			return $neu;
		}
		self::$intern = true;
		update_post_meta( $neu, self::ENTWURF_VON, (int) $original );
		update_post_meta( $neu, self::BASIS, self::pruefsumme( $original ) );
		self::$intern = false;
		self::protokoll( 'Entwurf angelegt', $original, $neu );
		return self::entwurf_daten( $neu, true );
	}

	private static function entwurf_daten( $entwurf, $neu ) {
		$original = (int) get_post_meta( $entwurf, self::ENTWURF_VON, true );
		return array(
			'entwurf_id'    => (int) $entwurf,
			'original_id'   => $original,
			'neu'           => (bool) $neu,
			'titel'         => get_the_title( $original ),
			'vorschau_url'  => get_preview_post_link( $entwurf ),
			'live_url'      => get_permalink( $original ),
			'angefragt'     => (bool) get_post_meta( $entwurf, self::ANGEFRAGT, true ),
			'live_geaendert' => self::konflikt( $entwurf ),
		);
	}

	/** Wurde die Live-Seite geaendert, seit der Entwurf angelegt wurde? */
	public static function konflikt( $entwurf ) {
		$original = (int) get_post_meta( $entwurf, self::ENTWURF_VON, true );
		return get_post_meta( $entwurf, self::BASIS, true ) !== self::pruefsumme( $original );
	}

	public static function entwuerfe() {
		return get_posts( array(
			'post_type'        => self::typen(),
			'post_status'      => array( 'draft', 'pending' ),
			'meta_key'         => self::ENTWURF_VON,
			'posts_per_page'   => 100,
			'orderby'          => 'modified',
			'suppress_filters' => true,
		) );
	}

	/* ------------------------------------------------------------------ */
	/* Kopieren (Entwurf, Freigabe, Sicherung, Rueckgaengig)               */
	/* ------------------------------------------------------------------ */

	private static function meta_lesen( $id ) {
		$meta = array();
		foreach ( (array) get_post_meta( $id ) as $schluessel => $werte ) {
			if ( in_array( $schluessel, self::$meta_aus, true ) || 0 === strpos( $schluessel, '_bb_entwurf' ) || 0 === strpos( $schluessel, '_bb_sicherung' ) ) {
				continue;
			}
			$meta[ $schluessel ] = array_map( 'maybe_unserialize', (array) $werte );
		}
		return $meta;
	}

	/**
	 * Inhalt, Meta-Felder und Begriffe von $quelle auf $ziel uebertragen. Ist $ziel 0,
	 * wird ein neuer Beitrag mit $status angelegt. Gibt die Ziel-ID zurueck.
	 */
	private static function kopieren( $quelle, $ziel, $status = '' ) {
		$quelle = get_post( $quelle );
		self::$intern = true;
		try {
			$daten = array(
				'post_title'   => $quelle->post_title,
				'post_content' => $quelle->post_content,
				'post_excerpt' => $quelle->post_excerpt,
			);
			if ( $ziel ) {
				$daten['ID'] = (int) $ziel;
				$ergebnis    = wp_update_post( wp_slash( $daten ), true );
			} else {
				$daten += array(
					'post_type'      => $quelle->post_type,
					'post_status'    => $status,
					'post_author'    => get_current_user_id() ? get_current_user_id() : $quelle->post_author,
					'post_parent'    => $quelle->post_parent,
					'menu_order'     => $quelle->menu_order,
					'comment_status' => $quelle->comment_status,
					'ping_status'    => $quelle->ping_status,
					'post_password'  => $quelle->post_password,
				);
				$ergebnis = wp_insert_post( wp_slash( $daten ), true );
			}
			if ( is_wp_error( $ergebnis ) ) {
				return $ergebnis;
			}
			$ziel = (int) $ergebnis;

			// Meta-Felder abgleichen: was die Quelle nicht hat, kommt am Ziel weg.
			$neu = self::meta_lesen( $quelle->ID );
			foreach ( array_keys( self::meta_lesen( $ziel ) ) as $schluessel ) {
				if ( ! isset( $neu[ $schluessel ] ) ) {
					delete_post_meta( $ziel, $schluessel );
				}
			}
			foreach ( $neu as $schluessel => $werte ) {
				delete_post_meta( $ziel, $schluessel );
				foreach ( $werte as $wert ) {
					add_post_meta( $ziel, $schluessel, wp_slash( $wert ) );
				}
			}

			foreach ( get_object_taxonomies( $quelle->post_type ) as $taxonomie ) {
				$begriffe = wp_get_object_terms( $quelle->ID, $taxonomie, array( 'fields' => 'ids' ) );
				if ( ! is_wp_error( $begriffe ) ) {
					wp_set_object_terms( $ziel, $begriffe, $taxonomie );
				}
			}
			return $ziel;
		} finally {
			self::$intern = false;
		}
	}

	private static function sichern( $original ) {
		$sicherung = self::kopieren( $original, 0, self::SICHERUNG );
		if ( is_wp_error( $sicherung ) ) {
			return $sicherung;
		}
		update_post_meta( $sicherung, self::SICHERUNG_VON, (int) $original );

		// Aeltere Sicherungen aufraeumen.
		$alle = get_posts( array(
			'post_type'        => self::typen(),
			'post_status'      => self::SICHERUNG,
			'meta_key'         => self::SICHERUNG_VON,
			'posts_per_page'   => -1,
			'orderby'          => 'ID',
			'order'            => 'DESC',
			'fields'           => 'ids',
			'suppress_filters' => true,
		) );
		foreach ( array_slice( $alle, self::MAX_SICHERUNG ) as $alt ) {
			wp_delete_post( $alt, true );
		}
		return $sicherung;
	}

	/**
	 * Entwurf auf die Live-Seite uebernehmen.
	 *
	 * @return string Fehlermeldung oder ''
	 */
	public static function freigeben( $entwurf, $trotz_konflikt = false ) {
		$original = (int) get_post_meta( $entwurf, self::ENTWURF_VON, true );
		if ( ! $original || ! get_post( $original ) ) {
			return 'Die Live-Seite zu diesem Entwurf gibt es nicht mehr.';
		}
		if ( ! $trotz_konflikt && self::konflikt( $entwurf ) ) {
			return 'Die Live-Seite wurde geändert, seit der Entwurf angelegt wurde. Bitte prüfen und dann „Trotzdem freigeben“ wählen.';
		}
		$sicherung = self::sichern( $original );
		if ( is_wp_error( $sicherung ) ) {
			return 'Sicherung fehlgeschlagen: ' . $sicherung->get_error_message();
		}
		$ergebnis = self::kopieren( $entwurf, $original );
		if ( is_wp_error( $ergebnis ) ) {
			return 'Übernehmen fehlgeschlagen: ' . $ergebnis->get_error_message();
		}
		wp_delete_post( $entwurf, true );
		self::caches_leeren( $original );
		self::protokoll( $trotz_konflikt ? 'Freigegeben (trotz Änderung an der Live-Seite)' : 'Freigegeben', $original, $entwurf, $sicherung );
		return '';
	}

	public static function verwerfen( $entwurf ) {
		$original = (int) get_post_meta( $entwurf, self::ENTWURF_VON, true );
		wp_delete_post( $entwurf, true );
		self::protokoll( 'Entwurf verworfen', $original, $entwurf );
		return '';
	}

	/**
	 * Live-Seite auf eine Sicherung zuruecksetzen. Der aktuelle Stand wird vorher
	 * selbst gesichert, damit auch das Rueckgaengig umkehrbar bleibt.
	 */
	public static function zuruecksetzen( $sicherung ) {
		$post     = get_post( $sicherung );
		$original = (int) get_post_meta( $sicherung, self::SICHERUNG_VON, true );
		if ( ! $post || self::SICHERUNG !== $post->post_status || ! get_post( $original ) ) {
			return 'Diese Sicherung gibt es nicht mehr.';
		}
		$neu = self::sichern( $original );
		if ( is_wp_error( $neu ) ) {
			return 'Sicherung fehlgeschlagen: ' . $neu->get_error_message();
		}
		$ergebnis = self::kopieren( $sicherung, $original );
		if ( is_wp_error( $ergebnis ) ) {
			return 'Zurücksetzen fehlgeschlagen: ' . $ergebnis->get_error_message();
		}
		self::caches_leeren( $original );
		self::protokoll( 'Rückgängig gemacht', $original, 0, $neu );
		return '';
	}

	private static function caches_leeren( $id ) {
		clean_post_cache( $id );
		// Zwischengespeicherte Divi-Auswertungen der alten Fassung.
		foreach ( array_keys( (array) get_post_meta( $id ) ) as $schluessel ) {
			if ( 0 === strpos( $schluessel, '_et_dynamic_cached_' ) ) {
				delete_post_meta( $id, $schluessel );
			}
		}
		if ( class_exists( 'ET_Core_PageResource' ) && method_exists( 'ET_Core_PageResource', 'remove_static_resources' ) ) {
			ET_Core_PageResource::remove_static_resources( $id, 'all' );
		}
		if ( function_exists( 'et_core_clear_wp_cache' ) ) {
			et_core_clear_wp_cache( $id );
		}
		$url = get_permalink( $id );
		if ( $url && function_exists( 'Novamira\\CachePurge\\purge_urls' ) ) {
			\Novamira\CachePurge\purge_urls( array( $url ) );
		} else {
			do_action( 'litespeed_purge_post', $id );
			if ( function_exists( 'rocket_clean_post' ) ) {
				rocket_clean_post( $id );
			}
			if ( function_exists( 'w3tc_flush_post' ) ) {
				w3tc_flush_post( $id );
			}
			if ( function_exists( 'wpsc_delete_post_cache' ) ) {
				wpsc_delete_post_cache( $id );
			}
		}
	}

	/* ------------------------------------------------------------------ */
	/* Protokoll                                                           */
	/* ------------------------------------------------------------------ */

	private static function protokoll( $aktion, $original, $entwurf = 0, $sicherung = 0 ) {
		$eintraege = get_option( self::PROTOKOLL, array() );
		if ( ! is_array( $eintraege ) ) {
			$eintraege = array();
		}
		$nutzer = wp_get_current_user();
		array_unshift( $eintraege, array(
			'zeit'      => time(),
			'aktion'    => $aktion,
			'original'  => (int) $original,
			'titel'     => $original ? get_the_title( $original ) : '',
			'entwurf'   => (int) $entwurf,
			'sicherung' => (int) $sicherung,
			'wer'       => $nutzer->exists() ? $nutzer->user_login : 'System',
		) );
		update_option( self::PROTOKOLL, array_slice( $eintraege, 0, self::MAX_PROTOKOLL ), false );
	}

	public static function protokoll_lesen() {
		$eintraege = get_option( self::PROTOKOLL, array() );
		return is_array( $eintraege ) ? $eintraege : array();
	}

	/* ------------------------------------------------------------------ */
	/* Werkzeuge fuer Novamira (Abilities API)                             */
	/* ------------------------------------------------------------------ */

	public static function status_anmelden() {
		register_post_status( self::SICHERUNG, array(
			'label'                     => 'BB-Sicherung',
			'public'                    => false,
			'internal'                  => true,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => false,
			'show_in_admin_status_list' => false,
		) );
	}

	public static function kategorie_anmelden() {
		if ( function_exists( 'wp_register_ability_category' ) && ! wp_has_ability_category( 'bb-basic' ) ) {
			wp_register_ability_category( 'bb-basic', array(
				'label'       => 'BB Basic',
				'description' => 'Draft and approval workflow of Buero Blanko Basic.',
			) );
		}
	}

	public static function darf() {
		return current_user_can( 'manage_options' );
	}

	public static function werkzeuge_anmelden() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		$meta = function ( $nur_lesen ) {
			return array(
				'show_in_rest' => true,
				'annotations'  => array( 'readonly' => $nur_lesen, 'destructive' => false, 'idempotent' => $nur_lesen ),
				'mcp'          => array( 'public' => true, 'type' => 'tool' ),
			);
		};
		$entwurf_ausgabe = array(
			'type'       => 'object',
			'properties' => array(
				'entwurf_id'     => array( 'type' => 'integer' ),
				'original_id'    => array( 'type' => 'integer' ),
				'neu'            => array( 'type' => 'boolean' ),
				'titel'          => array( 'type' => 'string' ),
				'vorschau_url'   => array( 'type' => 'string' ),
				'live_url'       => array( 'type' => 'string' ),
				'angefragt'      => array( 'type' => 'boolean' ),
				'live_geaendert' => array( 'type' => 'boolean' ),
			),
		);

		wp_register_ability( 'bb-basic/entwurf-anlegen', array(
			'label'               => 'Entwurf anlegen',
			'description'         => 'Create a draft copy of a published page or post (content, all Divi, SEO and CSS settings, terms). Returns entwurf_id. Edit only the draft; the live page stays untouched until a Buero Blanko admin approves it. If a draft for this post already exists, it is returned instead.',
			'category'            => 'bb-basic',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array( 'post_id' => array( 'type' => 'integer', 'description' => 'ID of the live page or post.' ) ),
				'required'   => array( 'post_id' ),
			),
			'output_schema'       => $entwurf_ausgabe,
			'execute_callback'    => function ( $input ) {
				return self::entwurf_anlegen( (int) $input['post_id'] );
			},
			'permission_callback' => array( __CLASS__, 'darf' ),
			'meta'                => $meta( false ),
		) );

		wp_register_ability( 'bb-basic/entwuerfe-auflisten', array(
			'label'               => 'Entwürfe auflisten',
			'description'         => 'List all open BB drafts with their live page, preview link and whether approval was requested.',
			'category'            => 'bb-basic',
			'output_schema'       => array( 'type' => 'array', 'items' => $entwurf_ausgabe ),
			'execute_callback'    => function () {
				$liste = array();
				foreach ( self::entwuerfe() as $entwurf ) {
					$liste[] = self::entwurf_daten( $entwurf->ID, false );
				}
				return $liste;
			},
			'permission_callback' => array( __CLASS__, 'darf' ),
			'meta'                => $meta( true ),
		) );

		wp_register_ability( 'bb-basic/freigabe-anfragen', array(
			'label'               => 'Freigabe anfragen',
			'description'         => 'Mark a BB draft as ready for approval, with a short note (German) describing the change for the reviewer. A Buero Blanko admin then approves or discards it under Tools > Freigaben.',
			'category'            => 'bb-basic',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'entwurf_id' => array( 'type' => 'integer' ),
					'notiz'      => array( 'type' => 'string', 'description' => 'What was changed, in one or two sentences.' ),
				),
				'required'   => array( 'entwurf_id' ),
			),
			'output_schema'       => $entwurf_ausgabe,
			'execute_callback'    => function ( $input ) {
				$id = (int) $input['entwurf_id'];
				if ( ! self::ist_entwurf( $id ) ) {
					return new WP_Error( 'bb_freigabe', 'Not a BB draft.' );
				}
				update_post_meta( $id, self::ANGEFRAGT, time() );
				update_post_meta( $id, self::NOTIZ, sanitize_textarea_field( isset( $input['notiz'] ) ? (string) $input['notiz'] : '' ) );
				self::protokoll( 'Freigabe angefragt', (int) get_post_meta( $id, self::ENTWURF_VON, true ), $id );
				return self::entwurf_daten( $id, false );
			},
			'permission_callback' => array( __CLASS__, 'darf' ),
			'meta'                => $meta( false ),
		) );
	}

	/* ------------------------------------------------------------------ */
	/* Backend: Werkzeuge → Freigaben                                      */
	/* ------------------------------------------------------------------ */

	public static function bueroblanko_konto() {
		$mail = strtolower( (string) wp_get_current_user()->user_email );
		$ende = '@' . CODE_SYNC_ALLOWED_MAIL;
		return current_user_can( 'manage_options' ) && substr( $mail, -strlen( $ende ) ) === $ende;
	}

	public static function menue() {
		if ( ! self::bueroblanko_konto() ) {
			return;
		}
		$offen = 0;
		foreach ( self::entwuerfe() as $entwurf ) {
			if ( get_post_meta( $entwurf->ID, self::ANGEFRAGT, true ) ) {
				$offen++;
			}
		}
		$titel = 'Freigaben' . ( $offen ? ' <span class="awaiting-mod">' . $offen . '</span>' : '' );
		add_management_page( 'Freigaben', $titel, 'manage_options', self::SEITE, array( __CLASS__, 'seite' ) );
	}

	public static function kennzeichnen( $zustaende, $post ) {
		if ( self::ist_entwurf( $post->ID ) ) {
			$zustaende['bb_entwurf'] = 'BB-Entwurf zu „' . get_the_title( (int) get_post_meta( $post->ID, self::ENTWURF_VON, true ) ) . '“';
		}
		return $zustaende;
	}

	/** Zeilenaktion in der Seiten- und Beitragsliste: BB-Entwurf anlegen oder bearbeiten. */
	public static function zeilen_aktion( $aktionen, $post ) {
		if ( ! self::bueroblanko_konto() || ! in_array( $post->post_type, self::typen(), true ) || ! in_array( $post->post_status, array( 'publish', 'future', 'private' ), true ) || self::ist_entwurf( $post->ID ) ) {
			return $aktionen;
		}
		$vorhanden = self::entwurf_zu( $post->ID );
		if ( $vorhanden ) {
			$aktionen['bb_entwurf'] = '<a href="' . esc_url( get_edit_post_link( $vorhanden ) ) . '">BB-Entwurf bearbeiten</a>';
		} else {
			$link                   = wp_nonce_url( add_query_arg( array( 'action' => 'bb_entwurf', 'id' => $post->ID ), admin_url( 'admin-post.php' ) ), 'bb_entwurf_' . $post->ID );
			$aktionen['bb_entwurf'] = '<a href="' . esc_url( $link ) . '">BB-Entwurf anlegen</a>';
		}
		return $aktionen;
	}

	public static function entwurf_aktion() {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( ! self::bueroblanko_konto() || ! $id || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'bb_entwurf_' . $id ) ) {
			wp_die( 'Nicht erlaubt.' );
		}
		$ergebnis = self::entwurf_anlegen( $id );
		if ( is_wp_error( $ergebnis ) ) {
			wp_die( esc_html( $ergebnis->get_error_message() ) );
		}
		wp_safe_redirect( admin_url( 'post.php?post=' . (int) $ergebnis['entwurf_id'] . '&action=edit' ) );
		exit;
	}

	public static function aktion() {
		if ( ! self::bueroblanko_konto() || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'bb_freigabe' ) ) {
			wp_die( 'Nicht erlaubt.' );
		}
		$was    = isset( $_POST['was'] ) ? sanitize_key( $_POST['was'] ) : '';
		$id     = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$fehler = 'Unbekannte Aktion.';
		if ( in_array( $was, array( 'freigeben', 'trotzdem', 'verwerfen' ), true ) && ! self::ist_entwurf( $id ) ) {
			$fehler = 'Dieser Entwurf existiert nicht mehr.';
		} elseif ( 'freigeben' === $was || 'trotzdem' === $was ) {
			$fehler = self::freigeben( $id, 'trotzdem' === $was );
		} elseif ( 'verwerfen' === $was ) {
			$fehler = self::verwerfen( $id );
		} elseif ( 'zuruecksetzen' === $was ) {
			$fehler = self::zuruecksetzen( $id );
		}
		$ziel = add_query_arg( array( 'page' => self::SEITE, 'bb_ok' => $fehler ? 0 : 1 ), admin_url( 'tools.php' ) );
		if ( $fehler ) {
			set_transient( 'bb_freigabe_fehler_' . get_current_user_id(), $fehler, 60 );
		}
		wp_safe_redirect( $ziel );
		exit;
	}

	private static function knopf( $was, $id, $text, $klasse = 'button', $frage = '' ) {
		$html  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
		$html .= wp_nonce_field( 'bb_freigabe', '_wpnonce', true, false );
		$html .= '<input type="hidden" name="action" value="bb_freigabe">';
		$html .= '<input type="hidden" name="was" value="' . esc_attr( $was ) . '">';
		$html .= '<input type="hidden" name="id" value="' . (int) $id . '">';
		$html .= '<button type="submit" class="' . esc_attr( $klasse ) . '"' . ( $frage ? ' onclick="return confirm(' . esc_attr( wp_json_encode( $frage ) ) . ');"' : '' ) . '>' . esc_html( $text ) . '</button>';
		return $html . '</form> ';
	}

	public static function seite() {
		if ( ! self::bueroblanko_konto() ) {
			return;
		}
		$fehler = get_transient( 'bb_freigabe_fehler_' . get_current_user_id() );
		delete_transient( 'bb_freigabe_fehler_' . get_current_user_id() );
		echo '<div class="wrap"><h1>Freigaben</h1>';
		if ( $fehler ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $fehler ) . '</p></div>';
		} elseif ( ! empty( $_GET['bb_ok'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>Erledigt.</p></div>';
		}
		if ( 'live' !== self::$stufe ) {
			echo '<p class="description">Developer-Modus steht auf „' . esc_html( self::$stufe ) . '“. Die Sperre für veröffentlichte Seiten gilt nur in Stufe Live.</p>';
		}

		echo '<h2>Offene Entwürfe</h2>';
		$entwuerfe = self::entwuerfe();
		if ( ! $entwuerfe ) {
			echo '<p>Keine offenen Entwürfe.</p>';
		} else {
			echo '<table class="widefat striped"><thead><tr><th>Seite</th><th>Stand</th><th>Änderung</th><th>Aktionen</th></tr></thead><tbody>';
			foreach ( $entwuerfe as $entwurf ) {
				$original  = (int) get_post_meta( $entwurf->ID, self::ENTWURF_VON, true );
				$angefragt = (int) get_post_meta( $entwurf->ID, self::ANGEFRAGT, true );
				$konflikt  = self::konflikt( $entwurf->ID );
				echo '<tr><td><strong>' . esc_html( get_the_title( $original ) ) . '</strong><br><a href="' . esc_url( get_permalink( $original ) ) . '" target="_blank">Live-Seite</a></td>';
				echo '<td>' . ( $angefragt ? 'Zur Freigabe seit ' . esc_html( wp_date( 'd.m.Y H:i', $angefragt ) ) : 'In Arbeit' ) . '<br>Zuletzt geändert ' . esc_html( get_the_modified_date( 'd.m.Y H:i', $entwurf ) );
				if ( $konflikt ) {
					echo '<br><span style="color:#b32d2e">Live-Seite wurde seitdem geändert. Beim Freigeben gehen diese Änderungen verloren.</span>';
				}
				echo '</td><td>' . esc_html( (string) get_post_meta( $entwurf->ID, self::NOTIZ, true ) ) . '</td><td>';
				echo '<a class="button" href="' . esc_url( get_preview_post_link( $entwurf ) ) . '" target="_blank">Vorschau</a> ';
				echo '<a class="button" href="' . esc_url( get_edit_post_link( $entwurf->ID ) ) . '">Bearbeiten</a> ';
				if ( $konflikt ) {
					echo self::knopf( 'trotzdem', $entwurf->ID, 'Trotzdem freigeben', 'button button-primary', 'Die Live-Seite wurde seit dem Entwurf geändert. Trotzdem überschreiben?' ); // phpcs:ignore WordPress.Security.EscapeOutput
				} else {
					echo self::knopf( 'freigeben', $entwurf->ID, 'Freigeben', 'button button-primary', 'Entwurf auf die Live-Seite übernehmen?' ); // phpcs:ignore WordPress.Security.EscapeOutput
				}
				echo self::knopf( 'verwerfen', $entwurf->ID, 'Verwerfen', 'button', 'Entwurf löschen? Die Live-Seite bleibt unverändert.' ); // phpcs:ignore WordPress.Security.EscapeOutput
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}

		echo '<h2>Protokoll</h2>';
		$eintraege = self::protokoll_lesen();
		if ( ! $eintraege ) {
			echo '<p>Noch nichts passiert.</p>';
		} else {
			echo '<table class="widefat striped"><thead><tr><th>Zeit</th><th>Seite</th><th>Vorgang</th><th>Wer</th><th></th></tr></thead><tbody>';
			foreach ( array_slice( $eintraege, 0, 50 ) as $eintrag ) {
				echo '<tr><td>' . esc_html( wp_date( 'd.m.Y H:i', $eintrag['zeit'] ) ) . '</td><td>' . esc_html( $eintrag['titel'] ) . '</td><td>' . esc_html( $eintrag['aktion'] ) . '</td><td>' . esc_html( $eintrag['wer'] ) . '</td><td>';
				if ( $eintrag['sicherung'] && self::SICHERUNG === get_post_status( $eintrag['sicherung'] ) ) {
					echo self::knopf( 'zuruecksetzen', $eintrag['sicherung'], 'Rückgängig', 'button', 'Live-Seite auf den Stand vor diesem Vorgang zurücksetzen?' ); // phpcs:ignore WordPress.Security.EscapeOutput
				}
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';
	}
}
