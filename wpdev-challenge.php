<?php

/**
 *
 * The plugin bootstrap file
 *
 * This file is responsible for starting the plugin using the main plugin class file.
 *
 * @since 0.0.1
 * @package Wpdev_Challenge
 *
 * @wordpress-plugin
 * Plugin Name:     WP Dev Challenge
 * Description:     This is a short description of what the plugin does. It's displayed in the WordPress admin area.
 * Version:         0.0.1
 * Author:          Sebastián Mármol
 * Author URI:      https://www.sfmarmol.com
 * License:         GPL-2.0+
 * License URI:     http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:     wpdev-challenge
 * Domain Path:     /lang
 */

if (!defined('ABSPATH')) {
	die('Direct access not permitted.');
}

if (!class_exists('wpdev_challenge')) {

	/*
	 * main wpdev_challenge class
	 *
	 * @class wpdev_challenge
	 * @since 0.0.1
	 */
	class wpdev_challenge
	{

		/*
		 * wpdev_challenge plugin version
		 *
		 * @var string
		 */
		public $version = '4.7.5';

		/**
		 * The single instance of the class.
		 *
		 * @var wpdev_challenge
		 * @since 0.0.1
		 */
		protected static $instance = null;

		/**
		 * Main wpdev_challenge instance.
		 *
		 * @since 0.0.1
		 * @static
		 * @return wpdev_challenge - main instance.
		 */
		public static function instance()
		{
			if (is_null(self::$instance)) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * wpdev_challenge class constructor.
		 */
		public function __construct()
		{
			$this->load_plugin_textdomain();
			$this->define_constants();
			$this->includes();
			$this->define_actions();
		}

		function activate()
		{

			//Create table when activate plugin
			create_broken_links_list_table_wpdb();

			// make sure this event is not scheduled
			if (!wp_next_scheduled('dcms_my_cron_hook')) {
				wp_schedule_event(current_time('timestamp'), '60seconds', 'dcms_my_cron_hook');
			}
		}



		function deactivate()
		{
			echo 'The plugin was deactivated.';
			
			wp_clear_scheduled_hook( 'dcms_my_cron_hook' );
		}




		public function load_plugin_textdomain()
		{
			load_plugin_textdomain('wpdev-challenge', false, basename(dirname(__FILE__)) . '/lang/');
		}

		/**
		 * Include required core files
		 */
		public function includes()
		{
			// Example
			//require_once __DIR__ . '/includes/loader.php';

			// Load custom functions and hooks
			require_once __DIR__ . '/includes/includes.php';
		}

		/**
		 * Get the plugin path.
		 *
		 * @return string
		 */
		public function plugin_path()
		{
			return untrailingslashit(plugin_dir_path(__FILE__));
		}


		/**
		 * Define wpdev_challenge constants
		 */
		private function define_constants()
		{
			define('WPDEV_CHALLENGE_PLUGIN_FILE', __FILE__);
			define('WPDEV_CHALLENGE_PLUGIN_BASENAME', plugin_basename(__FILE__));
			define('WPDEV_CHALLENGE_VERSION', $this->version);
			define('WPDEV_CHALLENGE_PATH', $this->plugin_path());
		}

		/**
		 * Define wpdev_challenge actions
		 */
		public function define_actions()
		{
			//
		}

		/**
		 * Define wpdev_challenge menus
		 */
		public function define_menus()
		{
			//
		}


	}

	$wpdev_challenge = new wpdev_challenge();
}
//Activation
register_activation_hook(__FILE__, array($wpdev_challenge, 'activate'));

//Activation
register_deactivation_hook(__FILE__, array($wpdev_challenge, 'deactivate'));



