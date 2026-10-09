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
		// Beim Deaktivieren werden die Meta-Tags bewusst geloescht (Entscheidung
		// Philipp, 09.10.2026). Beim Aktivieren wird die Tabelle neu angelegt.
		global $wpdb;
		$table_name = $wpdb->prefix . 'code_sync_meta_tags';
		$wpdb->query( "DROP TABLE IF EXISTS $table_name" );
		delete_option( 'code_sync_db_version' );
	}

}
