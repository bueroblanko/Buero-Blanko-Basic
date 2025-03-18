<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://bueroblanko.de
 * @since      1.0.0
 *
 * @package    Code_Sync
 * @subpackage Code_Sync/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Code_Sync
 * @subpackage Code_Sync/public
 * @author     Ilyes <test@test.com>
 */

if (! defined('CODE_SYNC_ALLOWED_MAIL')) {
	die('Bruh!');
}
class Code_Sync_Public {

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
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
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
		// add_action('wp_head', 'my_custom_wp_head_function');

		// function my_custom_wp_head_function() {
		// 	$f  =  plugin_dir_path(__FILE__) . 'code-snippets/';
		// 	$snippets_dir =  plugin_dir_path(__FILE__) . 'code-snippets/';
		// 	foreach (glob($snippets_dir . "*.php") as $file) {
		// 			$f.=  $file . "\n";
		// 		}
		// 	echo '<!-- ' . $f . ' -->';
		// }
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/code-sync-public.css', array(), $this->version, 'all' );
		$email = $this->get_current_user_email();
		if (empty ($email) || strpos($email, CODE_SYNC_ALLOWED_MAIL) === false) {
			wp_enqueue_style( 'code-sync-public-disable-divi-layouts', plugin_dir_url( __FILE__ ) . 'css/code-sync-public-disable-divi-layouts.css', array( ), $this->version, 'all' );
		}
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
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

		//wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/code-sync-public.js', array( 'jquery' ), $this->version, false );
		// check if the user email ends with buerobronko , if not enqueue a script
		if (defined('ADMIN_MAIL'))
		$email = $this->get_current_user_email();
		if (empty ($email) || strpos($email, CODE_SYNC_ALLOWED_MAIL) === false) {
			wp_enqueue_script( 'code-sync-public-disable-divi-layouts', plugin_dir_url( __FILE__ ) . 'js/code-sync-public-disable-divi-layouts.js', array( 'jquery' ), $this->version, true );
		}
	
	}

	public function get_current_user_email() {
		$current_user = wp_get_current_user(); // Retrieve the current user object
		if ($current_user && $current_user->exists()) {
			return $current_user->get('user_email'); // Return the user's email
		}
		return null; // No user is logged in
	}

	public function execute_code_snippets() {
		$snippets_dir = plugin_dir_path(__FILE__) . '../code-snippets/php/';
		
		// Check if directory exists and is readable
		if (is_dir($snippets_dir) && is_readable($snippets_dir)) {
			$snippets = glob($snippets_dir . "*.php");
			
			if (!empty($snippets)) {
				foreach ($snippets as $file) {
					// Error handling to avoid fatal errors if a snippet has an issue
					try {
						$code = file_get_contents($file);
						$l = 1;
						//$code = str_replace('<?php', "", $code, $l);
						$code = preg_replace('/^<\?php/', '', $code);
						ob_start();

						try {
							$result = eval( $code );
						} catch ( ParseError $parse_error ) {
							$result = $parse_error;
						}

						ob_end_clean();
					} catch (Exception $e) {
						//error_log('Error executing snippet: ' . $file . ' - ' . $e->getMessage());
					}
				}
			} else {
				//error_log('No PHP snippets found in ' . $snippets_dir);
			}
		} else {
			//error_log('Snippets directory not found or not readable: ' . $snippets_dir);
		}

	}
	
	public function add_meta_tags () {
		if (!defined('CODE_SYNC_ADD_META_TAGS') || !CODE_SYNC_ADD_META_TAGS){
			return;
		}
		
		global $wpdb;
		$table_name = $wpdb->prefix . 'code_sync_meta_tags';
		$old_row = $wpdb->get_row("SELECT * FROM $table_name ORDER BY id DESC LIMIT 1");
		$site_url = get_site_url();
		$domain = str_replace( 'http://', '', $site_url);
		$domain = str_replace( 'https://', '', $domain);

		$title = isset($old_row->title) ? esc_attr($old_row->title) : '';
		$keywords = isset($old_row->keywords) ? esc_attr($old_row->keywords) : '';
		$description = isset($old_row->description) ? esc_attr($old_row->description) : '';
		$og_image = isset($old_row->og_image) ? esc_attr($old_row->og_image) : '';

		$result = "";

		if (!empty($description)){
			$result .= '<meta name="description" content="'. $description. '">';
		}
		if (!empty($keywords)){
			$result .= '<meta name="keywords" content="'. $keywords. '">';
		}
		
		$result .= <<<EOT
<!-- Facebook Meta Tags -->
<meta property="og:url" content="$site_url">
<meta property="og:type" content="website">
EOT;
		if (!empty($title)) {
			$result .= '<meta property="og:title" content="'.$title.'">';
		}
		if (!empty($description)) {
			$result .= '<meta property="og:description" content="'.$description.'">';
		
		}
		if (!empty($og_image)){
			$result .= '<meta property="og:image" content="'.$og_image.'">';
		}

		$result .= <<<EOT
<!-- Twitter Meta Tags -->
<meta name="twitter:card" content="summary_large_image">
<meta property="twitter:url" content="$site_url">
<meta property="twitter:domain" content="$domain">
EOT;
		if (!empty($title)) {
			$result .= '<meta name="twitter:title" content="'.$title.'">';
		}
		if (!empty($description)) {
			$result .= '<meta name="twitter:description" content="'.$description.'">';
		
		}
		if (!empty($og_image)){
			$result .= '<meta name="twitter:image" content="'.$og_image.'">';
		}


		echo $result;
	}

	public function load_js_snippets(){
		

		$snippets_dir = plugin_dir_url(__FILE__) . 'js/';
			
			if (is_dir(plugin_dir_path(__FILE__) . 'js/')) {
				$snippets = glob(plugin_dir_path(__FILE__) . 'js/*.js');
				
				if (!empty($snippets)) {
					foreach ($snippets as $file) {
						$filename = basename($file);
						wp_enqueue_script($filename, $snippets_dir . $filename, array('jquery'), null, true);
					}
				}
			}
	}

	public function add_body_class ($classes) {
		$classes[] = 'no-et-layouts';

		return $classes;

		
	}
}
