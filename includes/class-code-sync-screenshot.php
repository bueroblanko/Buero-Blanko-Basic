<?php

/**
 * Cookie-Banner für Screenshots ausblenden.
 *
 * Das BB Cockpit nimmt von jeder Kundenseite eine Startseiten-Vorschau auf
 * (siehe /sites). Auf den meisten Seiten liegt darüber ein Cookie-Banner —
 * die Vorschau zeigt dann eine graue Fläche statt der Arbeit.
 *
 * Ruft das Cockpit die Seite mit `?bbshot=1` auf, versteckt dieses Modul die
 * bekannten Banner UND Marketing-Popups (Divi Popup, Popup Maker, Hustle) per
 * CSS. Cookie-Dialoge allein nimmt schon der Screenshot-Dienst weg — dieses
 * Modul ist für alles andere und für die Fälle, die er nicht kennt.
 *
 * WICHTIG — hier wird NUR versteckt, NICHT zugestimmt:
 * Borlabs & Co. blockieren Skripte bis zur Einwilligung. Da wir keine
 * Einwilligung setzen, bleibt alles blockiert; es wird also kein Tracking
 * ausgelöst, nur das Fenster unsichtbar gemacht. Ein „Alles akzeptieren" per
 * Parameter wäre rechtlich etwas ganz anderes und ist bewusst nicht gebaut.
 *
 * Der Parameter braucht kein Geheimnis: Wer ihn kennt, sieht die Seite ohne
 * Banner — mehr passiert nicht. Ein Geheimnis hier wäre sogar schlechter,
 * weil die Adresse beim Screenshot-Dienst im Protokoll landet.
 *
 * Damit aus dem Parameter keine zweite, indexierbare Fassung der Seite wird,
 * setzt das Modul zusätzlich `noindex`.
 *
 * @package    Code_Sync
 * @subpackage Code_Sync/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Code_Sync_Screenshot {

	const PARAM = 'bbshot';

	/** Wird die Seite gerade für eine Vorschau geladen? */
	public static function aktiv() {
		return isset( $_GET[ self::PARAM ] ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	public static function kopf() {
		if ( ! self::aktiv() ) {
			return;
		}

		echo '<meta name="robots" content="noindex,nofollow">' . "\n";
		echo '<style id="bb-screenshot">'
			// Borlabs Cookie (auf den meisten unserer Seiten im Einsatz)
			. '#BorlabsCookieBox,#BorlabsCookieWidget,.BorlabsCookie,'
			// Cookiebot, Usercentrics, Complianz
			. '#CybotCookiebotDialog,#CybotCookiebotDialogBodyUnderlay,'
			. '#usercentrics-root,#cmplz-cookiebanner-container,.cmplz-cookiebanner,'
			// Cookie Notice, GDPR Cookie Compliance, Real Cookie Banner
			. '#cookie-law-info-bar,#cookie-notice,.cookie-notice-container,'
			. '.cli-modal-backdrop,.rcb-banner,[id^="rcb-banner"],'
			// Marketing-Popups: Divi Popup, Popup Maker, Hustle (WPMU DEV)
			. '.et_pb_popup,.et-popup,.dipl-popup,.pum-overlay,.pum-container,'
			. '.hustle-ui,.hustle-popup,.hustle-slidein,.hustle-info,'
			. '.wpforms-modal,.mailerlite-form-overlay,'
			// generische Klassiker
			. '.cc-window,#CookieConsent,.cookie-consent,.cookieconsent'
			. '{display:none !important;visibility:hidden !important}'
			// manche Banner sperren das Scrollen und dunkeln die Seite ab
			. 'html,body{overflow:visible !important;position:static !important}'
			. '</style>' . "\n";
	}
}
