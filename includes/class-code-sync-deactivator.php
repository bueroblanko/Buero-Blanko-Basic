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
		// Meta-Tags bleiben beim Deaktivieren erhalten (Entscheidung Philipp, 12.10.2026),
		// damit beim Debuggen nichts unbemerkt verloren geht.
	}

}
