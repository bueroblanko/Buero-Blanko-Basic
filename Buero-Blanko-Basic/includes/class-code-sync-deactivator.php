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
 * @author     Ilyes <test@test.com>
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
		global $wpdb;
		$table_name = $wpdb->prefix . 'code_sync_meta_tags';

		$sql = "DROP TABLE IF EXISTS $table_name;";
		$wpdb->query($sql);
	}

}
