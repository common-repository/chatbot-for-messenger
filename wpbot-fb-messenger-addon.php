<?php
/*		
* Plugin Name: ChatBot for Social Media
* Plugin URI: https://wordpress.org/plugins/chatbot-for-messenger
* Description: Facebook Messenger Addon for WPBot
* Version: 0.9.9
* Author: QuantumCloud
* Author URI: https://www.quantumcloud.com/
* Requires at least: 4.6
* Tested up to: 5.8
* Text Domain: qc-opd
* Domain Path: /lang/
* License: GPL2
*/

defined('ABSPATH') or die("No direct script access!");

if( !defined('WBFB_PATH') )
	define( 'WBFB_PATH', plugin_dir_path(__FILE__) );
if( !defined('WBFB_URL') )
	define( 'WBFB_URL', plugin_dir_url(__FILE__ ) );

require_once WBFB_PATH.'/vendor/autoload.php';
require_once 'wpbot-fb-messenger-functions.php';
require_once 'wpbot-fb-api.php';
require_once 'wpbot-fb-custom-posts.php';
require_once 'wpbot-fb-ajax-handler.php';

require_once 'admin/wpbot-fb-admin-page.php';


add_action('init', 'qcpd_wpfb_messenger_checking_dependencies');
function qcpd_wpfb_messenger_checking_dependencies(){
	include_once(ABSPATH.'wp-admin/includes/plugin.php');
	
	if ( !class_exists('qcld_wb_Chatbot') ) {
		add_action('admin_notices', 'qcpd_wpfb_require_notice');
	}
}


function qcpd_wpfb_require_notice()
{
?>
	<div id="message" class="error">
		<p>
			<?php echo esc_html__('Please install & activate the WPBot plugin and configure the Artificial Intelligence properly to get the WPBot Facebook Messenger Addon to work.', 'wpfb'); ?>
		</p>
	</div>
<?php
}

function qcpd_wpfb_activation_redirect( $plugin ) {
    if( $plugin == plugin_basename( __FILE__ ) ) {
        exit( wp_redirect( admin_url( 'admin.php?page=wbfb-botsetting-page') ) );
    }
}
add_action( 'activated_plugin', 'qcpd_wpfb_activation_redirect' );

/**
 *
 * Function to load translation files.
 *
 */
function qcpd_wpfb_addon_lang_init() {
    load_plugin_textdomain( 'wpfb', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

add_action( 'plugins_loaded', 'qcpd_wpfb_addon_lang_init');

//Let's go
add_action('init', 'qcpd_wpfb_messenger_callback');

register_activation_hook(__FILE__, 'qcld_wbfb_messenger_defualt_options');
function qcld_wbfb_messenger_defualt_options(){
	
	global $wpdb;
	$collate = '';

	if ( $wpdb->has_cap( 'collation' ) ) {

		if ( ! empty( $wpdb->charset ) ) {

			$collate .= "DEFAULT CHARACTER SET $wpdb->charset";
		}
		if ( ! empty( $wpdb->collate ) ) {

			$collate .= " COLLATE $wpdb->collate";

		}
	}
	$table    = $wpdb->prefix.'wpbot_fb_pages';
	$sql_sliders_Table = "
		CREATE TABLE IF NOT EXISTS `$table` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `page_name` varchar(256) NOT NULL,
		  `page_id` varchar(256) NOT NULL,
		  `page_access_token` text NOT NULL,
		  `cover` text NOT NULL,
		  `picture` text NOT NULL,
		  PRIMARY KEY (`id`)
		)  $collate AUTO_INCREMENT=1 ";
		
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql_sliders_Table );
	
	$table1    = $wpdb->prefix.'wpbot_fb_subscribers';
	$sql_sliders_Table1 = "
		CREATE TABLE IF NOT EXISTS `$table1` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `page_id` varchar(256) NOT NULL,
		  `subscriber_id` varchar(256) NOT NULL,
		  `name` varchar(256) NOT NULL,		  
		  `is_subscribed` int(11) NOT NULL,		  
		  PRIMARY KEY (`id`)
		)  $collate AUTO_INCREMENT=1 ";
		
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql_sliders_Table1 );
	
	
	$table2    = $wpdb->prefix.'wpbot_fb_broadcasts';
	$sql_sliders_Table2 = "
		CREATE TABLE IF NOT EXISTS `$table2` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `page_id` varchar(256) NOT NULL,
		  `date` datetime NOT NULL,
		  `message` text NOT NULL,		  	  
		  PRIMARY KEY (`id`)
		)  $collate AUTO_INCREMENT=1 ";
		
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql_sliders_Table2 );
	
	if ( ! qcwpfb_isset_table_column( $table, 'cover' ) ) {
		$sql_wpfb_Table_update_1 = "ALTER TABLE `$table` ADD `cover` TEXT NOT NULL;";
		$wpdb->query( $sql_wpfb_Table_update_1 );
	}
	if ( ! qcwpfb_isset_table_column( $table, 'picture' ) ) {
		$sql_wpfb_Table_update_1 = "ALTER TABLE `$table` ADD `picture` TEXT NOT NULL;";
		$wpdb->query( $sql_wpfb_Table_update_1 );
	}

}

if(!function_exists('qcwpfb_isset_table_column')) {
	function qcwpfb_isset_table_column($table_name, $column_name)
	{
		global $wpdb;
		$columns = $wpdb->get_results("SHOW COLUMNS FROM  " . $table_name, ARRAY_A);
		foreach ($columns as $column) {
			if ($column['Field'] == $column_name) {
				return true;
			}
		}
	}
}


