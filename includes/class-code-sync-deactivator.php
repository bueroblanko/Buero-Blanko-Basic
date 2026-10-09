<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://bueroblanko.de
 * @since      1.0.0
 *
 * @package    Code_Sync
 * @subpackage Code_Sync/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Code_Sync
 * @subpackage Code_Sync/includes
 * @author     Büro Blanko Medien GmbH <info@bueroblanko.de>
 */
class Code_Sync_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		// Die Meta-Tag-Tabelle bleibt beim Deaktivieren erhalten. Geloescht wird
		// sie erst beim Loeschen des Plugins (uninstall.php).
	}

}
