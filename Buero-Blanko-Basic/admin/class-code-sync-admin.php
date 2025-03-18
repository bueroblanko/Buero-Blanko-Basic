<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://bueroblanko.de
 * @since      1.0.0
 *
 * @package    Code_Sync
 * @subpackage Code_Sync/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Code_Sync
 * @subpackage Code_Sync/admin
 * @author     Büro Blanko Medien GmbH <info@bueroblanko.de>
 */

 if (! defined('CODE_SYNC_ALLOWED_MAIL')) {
	die('Bruh!');
}


class Code_Sync_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Code_Sync_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Code_Sync_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/code-sync-admin.css', array(), $this->version, 'all' );
		// check if the user email ends with buerobronko , if not enqueue a script
		$email = $this->get_current_user_email();
		if (empty ($email) || strpos($email, CODE_SYNC_ALLOWED_MAIL) === false) {
			wp_enqueue_style( 'code-sync-admin-disable-divi-layouts', plugin_dir_url( __FILE__ ) . 'css/code-sync-admin-disable-divi-layouts.css', array( ), $this->version, 'all' );
		}
	}

	public function get_current_user_email() {
		$current_user = wp_get_current_user(); // Retrieve the current user object
		if ($current_user && $current_user->exists()) {
			return $current_user->get('user_email'); // Return the user's email
		}
		return null; // No user is logged in
	}
	
	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Code_Sync_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Code_Sync_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/code-sync-admin.js', array( 'jquery' ), $this->version, false );

		// check if the user email ends with buerobronko , if not enqueue a script
		$email = $this->get_current_user_email();
		if (empty ($email) || strpos($email, CODE_SYNC_ALLOWED_MAIL) === false) {
			wp_enqueue_script( 'code-sync-admin-disable-divi-layouts', plugin_dir_url( __FILE__ ) . 'js/code-sync-admin-disable-divi-layouts.js', array( 'jquery' ), $this->version, true );
		}

	}
	
	public function register_admin_page() {
		add_submenu_page(
			'tools.php',          // Parent menu slug (Tools menu)
			'BB Basic',        // Page title
			'BB Basic',        // Submenu title
			'manage_options',     // Capability
			'plugin-info',        // Menu slug
			[$this, 'code_sync_settings_page'], // Callback function
		);
	}

	function get_theme_version($theme_slug = '') {
		$theme = wp_get_theme($theme_slug);
		if ($theme->exists()) {
			return  $theme->get('Version');
		}
		return 'Theme not found';
	}

	function code_sync_settings_page() {
		$theme_version =  $this->get_theme_version('Divi');
		global $wpdb;
		$table_name = $wpdb->prefix . 'code_sync_meta_tags';

		
		if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['meta_tags_nonce']) && wp_verify_nonce($_POST['meta_tags_nonce'], 'save_meta_tags')) {
			
			$title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
			$keywords = isset($_POST['keywords']) ? sanitize_text_field($_POST['keywords']) : '';
			$description =  isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
			$og_image = isset($_POST['og_image']) ? esc_url_raw($_POST['og_image']) : '' ;

			$wpdb->replace(
				$table_name,
				[
					'id' => 1,
					'title' => $title,
					'keywords' => $keywords,
					'description' => $description,
					'og_image' => $og_image,
				
				],
				['%s', '%s', '%s', '%s']
			);
			
			

		}
		$old_row = $wpdb->get_row("SELECT * FROM $table_name ORDER BY id DESC LIMIT 1");
		$title = isset($old_row->title) ? esc_attr($old_row->title) : '';
		$keywords = isset($old_row->keywords) ? esc_attr($old_row->keywords) : '';
		$description = isset($old_row->description) ? esc_attr($old_row->description) : '';
		$og_image = isset($old_row->og_image) ? esc_attr($old_row->og_image) : '';



		include plugin_dir_path(__FILE__) . 'partials/code-sync-admin-display-versions.php';
		include plugin_dir_path(__FILE__) . 'partials/code-sync-admin-display-meta-fields.php';
		
	}


	public function add_admin_body_class () {

		return 'no-et-layouts';

				
	}

}
