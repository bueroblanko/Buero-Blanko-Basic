<?php
/**
 * Update-Quelle ohne GitHub-API.
 *
 * Der plugin-update-checker fragt ab Werk die GitHub-API (api.github.com) ab. Ohne
 * Anmeldung erlaubt GitHub dort nur 60 Abfragen pro Stunde je Server-IP. Auf
 * geteilten Servern teilen sich viele fremde Seiten eine IP, dann antwortet GitHub
 * mit 403 und die Seite sieht kein Update.
 *
 * Diese Klasse liest stattdessen die Hauptdatei ueber raw.githubusercontent.com und
 * laedt die ZIP ueber codeload.github.com. Beide fallen nicht unter das API-Limit.
 * Kein Token, kein eigener Server. Klappt raw nicht, fragt sie wie bisher die API.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'Code_Sync_Update_Quelle', false ) && class_exists( 'Puc_v4p11_Vcs_GitHubApi' ) ) :

	class Code_Sync_Update_Quelle extends Puc_v4p11_Vcs_GitHubApi {

		/**
		 * Branch ohne API-Abfrage. Ob es ihn gibt, zeigt erst getRemoteFile().
		 */
		public function getBranch( $branchName ) {
			return new Puc_v4p11_Vcs_Reference( array(
				'name'        => $branchName,
				'downloadUrl' => $this->buildArchiveDownloadUrl( $branchName ),
			) );
		}

		/**
		 * Datei ueber raw.githubusercontent.com, bei Fehler ueber die API.
		 */
		public function getRemoteFile( $path, $ref = 'master' ) {
			$antwort = wp_remote_get( $this->rawUrl( $path, $ref ), array( 'timeout' => 10 ) );
			if ( ! is_wp_error( $antwort ) && 200 === wp_remote_retrieve_response_code( $antwort ) ) {
				return wp_remote_retrieve_body( $antwort );
			}
			return parent::getRemoteFile( $path, $ref );
		}

		/**
		 * Nur fuer die Anzeige "Zuletzt aktualisiert". Kostet eine API-Abfrage, daher aus.
		 */
		public function getLatestCommitTime( $ref ) {
			return null;
		}

		/**
		 * ZIP direkt von codeload.github.com statt ueber api.github.com/zipball.
		 * Der Ordner in der ZIP wird vom Update-Checker auf den installierten
		 * Ordnernamen umbenannt, wie bisher.
		 */
		public function buildArchiveDownloadUrl( $ref = 'master' ) {
			return sprintf(
				'https://codeload.github.com/%1$s/%2$s/zip/refs/heads/%3$s',
				rawurlencode( $this->userName ),
				rawurlencode( $this->repositoryName ),
				$this->refPfad( $ref )
			);
		}

		private function rawUrl( $path, $ref ) {
			return sprintf(
				'https://raw.githubusercontent.com/%1$s/%2$s/refs/heads/%3$s/%4$s',
				rawurlencode( $this->userName ),
				rawurlencode( $this->repositoryName ),
				$this->refPfad( $ref ),
				implode( '/', array_map( 'rawurlencode', explode( '/', ltrim( $path, '/' ) ) ) )
			);
		}

		/**
		 * Branch-Namen mit Schraegstrich (claude/...) bleiben als Pfad erhalten.
		 */
		private function refPfad( $ref ) {
			return implode( '/', array_map( 'rawurlencode', explode( '/', $ref ) ) );
		}
	}

endif;
