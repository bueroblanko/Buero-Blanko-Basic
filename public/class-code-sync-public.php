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
}
