<?php
/*
* Messenger settings area
*/
class wbfb_Admin_Area_Controller {
	
	function __construct(){
		add_action( 'admin_menu', array($this,'wbfb_admin_menu') );
		add_action( 'admin_init', array($this, 'wpfb_register_plugin_settings') );
		add_action('admin_enqueue_scripts', array($this, 'qcld_wpfb_admin_scripts'));
		add_action('init', array($this, 'wpfb_check_fb_session'));

        add_action( 'admin_post_enable_bot', array(&$this, 'enable_bot') );
        add_action( 'admin_post_disable_bot', array(&$this, 'disable_bot') );
	}

	public function qcld_wpfb_admin_scripts(){
		wp_enqueue_media();
		
		wp_register_script('qcld-wpfb-chatbot-datetime-jquery', WBFB_URL . '/assets/js/jquery.datetimepicker.full.min.js', array('jquery'));
        wp_enqueue_script('qcld-wpfb-chatbot-datetime-jquery');
		
		wp_register_style('qcld-wpfb-chatbot-datetime-style', WBFB_URL . '/assets/css/jquery.datetimepicker.min.css');
        wp_enqueue_style('qcld-wpfb-chatbot-datetime-style');

		wp_enqueue_script('qcfb-wpfb-chatbot-adminapi-js', WBFB_URL . '/assets/js/admin_script.js', array('jquery'));
        wp_enqueue_script('qcfb-wpfb-chatbot-adminapi-js');
		wp_localize_script( 'qcfb-wpfb-chatbot-adminapi-js', 'object',
            array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 'ajax_nonce' => wp_create_nonce('wpbotfbreload123pages') ) );
		wp_enqueue_style('qc_messenger_chatbot_admin_styles', WBFB_URL . '/assets/css/style.css');	
		wp_enqueue_style('qc_messenger_chatbot_admin_fonts_styles', WBFB_URL . '/assets/css/font-awesome.min.css');	
	}

	public function account_import_success_message(){
		?>
		<div id="message" class="updated notice is-dismissible rlrsssl-success">
                <p>Facebook pages imported successfully!</p>
            <button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
		</div>
		<?php
	}
	
	public function page_delete_success_message(){
		?>
		<div id="message" class="updated notice is-dismissible rlrsssl-success">
                <p>Facebook page has been deleted successfully!</p>
            <button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
		</div>
		<?php
	}
	
	public function bot_enabled_success_message(){
		?>
		<div id="message" class="updated notice is-dismissible rlrsssl-success">
                <p>Bot has been enabled successfully!</p>
            <button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
		</div>
		<?php
	}
	public function bot_disabled_success_message(){
		?>
		<div id="message" class="updated notice is-dismissible rlrsssl-success">
                <p>Bot has been disabled successfully!</p>
            <button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
		</div>
		<?php
	}

	public function wbfb_admin_menu(){

        if ( current_user_can( 'publish_posts' ) ){
			
			
			
			add_menu_page( 'Messenger Chatbot', 'Messenger Chatbot', 'publish_posts', 'wbfb-botsetting-page', array( $this, 'wbfb_setting_page' ), 'dashicons-facebook', '9' );

			add_submenu_page( 'wbfb-botsetting-page', 'Manage FB Pages', 'Manage FB Pages', 'manage_options','wpbot-fb-private-replies', array($this, 'fb_private_replies') );
			
			
        }
		
    }
	
	public function wpfb_register_plugin_settings(){
		register_setting( 'qc-wpfb-plugin-settings-group', 'wpfb_enable_fbbot' );
		register_setting( 'qc-wpfb-plugin-settings-group', 'wpfb_verify_token' );
		register_setting( 'qc-wpfb-plugin-settings-group', 'wpfb_app_id' );
		register_setting( 'qc-wpfb-plugin-settings-group', 'wpfb_app_secret' );
		register_setting( 'qc-wpfb-plugin-settings-group', 'wpfb_user_access_token' );
		register_setting( 'qc-wpfb-plugin-settings-group', 'wpfb_page_access_token' );
		register_setting( 'qc-wpfb-plugin-settings-group', 'wpfb_default_instruction' );
		register_setting( 'qc-wpfb-plugin-settings-group', 'wpfb_default_no_match' );
		register_setting( 'qc-wpfb-plugin-settings-group', 'wpfb_command_live_agent' );
		register_setting( 'qc-wpfb-plugin-settings-group', 'wpfb_contact_admin_text' );
		
	}

	public function enable_bot(){
		
		$pageid = sanitize_text_field($_GET['pageid']);
		$access_token = qcpd_wpfb_get_accesstoken_from_id($pageid);
		
		$params = array("messages", "messaging_optins", "messaging_postbacks", "messaging_referrals", "message_deliveries", "message_reads");

		$postfields = "subscribed_fields=".implode(',', $params)."&access_token=$access_token";
		$url = "https://graph.facebook.com/v11.0/$pageid/subscribed_apps";
		
		$res = qcwpbot_send_response($postfields, $url);
		$res = json_decode($res, true);
		
		if(isset($res['success']) && $res['success']=='1'){
			update_option('bot_'.$pageid, 'on');
		}
		
		ob_start();
		qcpd_wpfb_get_started_button($access_token);
		ob_end_clean();

		wp_redirect(admin_url('admin.php?page=wpbot-fb-private-replies&success=bot_enabled'));exit;
		
	}
	public function disable_bot(){
		
		$pageid = sanitize_text_field($_GET['pageid']);
		$access_token = qcpd_wpfb_get_accesstoken_from_id($pageid);
		$url = "https://graph.facebook.com/v11.0/$pageid/subscribed_apps?access_token=$access_token";

		$res = qcwpbot_delete_response($url);
		$res = json_decode($res, true);
		
		update_option('bot_'.$pageid, 'off');

		wp_redirect(admin_url('admin.php?page=wpbot-fb-private-replies&success=bot_disabled'));exit;
		
	}
	
	public function wpfb_check_fb_session(){
		if(isset($_GET['page']) && $_GET['page']=='wpbot-fb-private-replies'){
			
			if (session_status() == PHP_SESSION_NONE) {
				session_start();
			}
			
			if(get_option('wpfb_app_id')!='' && get_option('wpfb_app_secret')!=''){
				
				$fb = new Facebook\Facebook([
					'app_id' => get_option('wpfb_app_id'),
					'app_secret' => get_option('wpfb_app_secret'),
					'default_graph_version' => 'v4.0',
				 ]);
				 
				$helper = $fb->getRedirectLoginHelper();
				
				$_SESSION['FBRLH_state']=$_GET['state'];
				try {
					if (isset($_SESSION['facebook_access_token'])) {
						$accessToken = $_SESSION['facebook_access_token'];
					} else {
						
						$accessToken = $helper->getAccessToken();
						
					}
				} catch(Facebook\Exceptions\FacebookResponseException $e) {
					// When Graph returns an error
					echo 'Graph returned an error: ' . $e->getMessage();

					exit;
				} catch(Facebook\Exceptions\FacebookSDKException $e) {
					// When validation fails or other local issues
					echo 'Facebook SDK returned an error: ' . $e->getMessage();
					exit;
				}
				
				if (isset($accessToken)) {
					
					if (isset($_SESSION['facebook_access_token'])) {
						$fb->setDefaultAccessToken($_SESSION['facebook_access_token']);
					} else {
						// getting short-lived access token
						$_SESSION['facebook_access_token'] = (string) $accessToken;

						// OAuth 2.0 client handler
						$oAuth2Client = $fb->getOAuth2Client();

						// Exchanges a short-lived access token for a long-lived one
						$longLivedAccessToken = $oAuth2Client->getLongLivedAccessToken($_SESSION['facebook_access_token']);

						$_SESSION['facebook_access_token'] = (string) $longLivedAccessToken;

						// setting default access token to be used in script
						$fb->setDefaultAccessToken($_SESSION['facebook_access_token']);
						update_option('wpfb_user_access_token', $_SESSION['facebook_access_token']);
					}
					unset($_SESSION['facebook_access_token']);
					
					
					if(get_option('wpfb_user_access_token')!=''){
		
						$user_access_token = get_option('wpfb_user_access_token');
							
						$url2 = "https://graph.facebook.com/v3.3/me/accounts?fields=cover,emails,picture,id,name,url,username,access_token&limit=400&access_token=$user_access_token";
						$pages = qcwpfb_get_fbpost_content($url2);

						if(isset($pages['data']) && !empty($pages['data'])){
							
							foreach($pages['data'] as $page){
								
								wpfb_insert_into_database($page);
								break;
								
							}

						}
							
						
						add_action('wpbot_fb_success_msg', array($this, 'account_import_success_message') );
					}
					
				}
				
				if(isset($_GET['success']) && $_GET['success']=='bot_enabled'){
					add_action('wpbot_fb_bot_enable_success_msg', array($this, 'bot_enabled_success_message') );
				}
				if(isset($_GET['success']) && $_GET['success']=='bot_disabled'){
					add_action('wpbot_fb_bot_disable_success_msg', array($this, 'bot_disabled_success_message') );
				}
				
			}
			
			if(isset($_GET['action']) && $_GET['action']=='delete' && isset($_GET['fbpage']) && $_GET['fbpage']!=''){
				global $wpdb;
				$table    = $wpdb->prefix.'wpbot_fb_pages';
				$fbpage = sanitize_text_field($_GET['fbpage']);
				
				$allposts = get_posts(array(
					'numberposts'	=> -1,
					'post_type'		=> 'wpfbposts',
					'meta_key'		=> 'fb_page_id',
					'meta_value'	=> $fbpage
				));
				foreach($allposts as $post){
					wp_delete_post($post->ID);
				}
				
				$wpdb->delete(
					$table,
					array( 'page_id' => $fbpage ),
					array( '%s' )
				);
				delete_option('bot_'.$pageid);
				
				add_action('wpbot_fb_page_delete_msg', array($this, 'page_delete_success_message') );
				
			}
			
		}
	}

	public function fb_private_replies(){
		global $wpdb;
		$table = $wpdb->prefix.'wpbot_fb_pages';

		if(get_option('wpfb_app_id')!='' && get_option('wpfb_app_secret')!=''){
			
			$fb = new Facebook\Facebook([
				'app_id' => get_option('wpfb_app_id'),
				'app_secret' => get_option('wpfb_app_secret'),
				'default_graph_version' => 'v4.0',
			 ]);

			$helper = $fb->getRedirectLoginHelper();

			//$permissions = ['email','manage_pages','publish_pages','pages_show_list','pages_messaging','public_profile','read_insights']; // 
			$permissions = ['email','pages_read_engagement','pages_manage_engagement','pages_show_list','pages_messaging','public_profile']; // 
			$loginUrl = $helper->getLoginUrl(admin_url('admin.php?page=wpbot-fb-private-replies'), $permissions);
			//$loginUrl = $helper->getLoginUrl('https://2059b75fdbe2.ngrok.io/wpbot-free/wp-admin/admin.php?page=wpbot-fb-private-replies', $permissions);
		}else{
			$loginUrl = '';
		}
		
		?>
		<?php do_action('wpbot_fb_success_msg'); ?>
		<?php do_action('wpbot_fb_page_delete_msg'); ?>
		<?php do_action('wpbot_fb_bot_enable_success_msg'); ?>
		<?php do_action('wpbot_fb_bot_disable_success_msg'); ?>
		<div class="wrap swpm-admin-menu-wrap">
			<h1><?php echo esc_html__('Manage FB Pages', 'wpfb'); ?></h1>
			<div class="wpfb_pages_header">
				<a href="<?php echo esc_url($loginUrl); ?>" class="button button-primary" ><i class="fa fa-facebook-official" aria-hidden="true"></i> &nbsp;Login with Facebook</a>
				<!--<button class="button button-primary" id="wpfb_reload_pages">Reload Pages</button>-->
				<span class="wpfb_pages_loading" style="display:none"> Loading... </span>
				
			</div>
			
					
				<?php 
					$pages = $wpdb->get_results("SELECT * FROM {$table} where 1 ");
					if(!empty($pages)){
						?>
						<div class="wpfb_pages_content_area">
						<h2>Your Facebook pages</h2>
							<table class="form-table">
						<?php
						foreach($pages as $page){
							?>

							<tr valign="top">
								<th scope="row" class="wpfb_page_name"><img src="<?php echo esc_url($page->picture); ?>" /><span><?php echo esc_html($page->page_name); ?></span></th>
								<td>
									<?php if(get_option('bot_'.$page->page_id)=='on'): ?>
										<a class="button-primary" href="<?php echo admin_url( 'admin-post.php?action=disable_bot&pageid='.$page->page_id ); ?>" title="The bot is enabled currently. Click to disable bot."><i class="fa fa-minus-square" aria-hidden="true"></i> Disable Bot</a>
									<?php else: ?>
										<a class="button-primary" href="<?php echo admin_url( 'admin-post.php?action=enable_bot&pageid='.$page->page_id ); ?>" title="The bot is not enabled now. Click to enable bot."> <i class="fa fa-check" aria-hidden="true"></i> Enable Bot</a>
									<?php endif; ?>

									
									<a href="<?php echo admin_url('edit.php?post_type=wpfbposts&fbpage='.$page->page_id.'&filter_action=Filter&paged=1') ?>" class="button button-primary"><i class="fa fa-reply-all" aria-hidden="true"></i> Manage Posts Reply</a>
									<a href="<?php echo admin_url('admin.php?page=wpbot-fb-private-replies&action=delete&fbpage='.$page->page_id) ?>" class="button button-primary" onclick="return confirm('Are you sure you want to delete this page?');"><i class="fa fa-trash" aria-hidden="true"></i> Delete Page</a>
									
								</td>
							</tr>
							
							<?php
						}
						?>
							</table>
						</div>
						<?php
					}else{
						?>
						<p><?php echo esc_html('You do not have any facebook pages to manage. Please login with facebook to import your pages.'); ?></p>
						<?php
					}

				?>
		</div>
		<?php
		
	}
	
	public function wbfb_setting_page(){
		wp_enqueue_style('qc_messenger_chatbot_admin_styles', WBFB_URL . '/assets/css/style.css');
		?>
	<div class="wrap swpm-admin-menu-wrap">
		<h1><?php echo esc_html__('Facebook Bot Settings Page', 'wpfb'); ?></h1>
	
		<h2 class="nav-tab-wrapper sld_nav_container">
			<a class="nav-tab sld_click_handle nav-tab-active" href="#general_settings"><?php echo esc_html__('General Settings', 'wpfb'); ?></a>
		</h2>
		
		<h2 class="qcfb_msg_heading" ><?php echo esc_html__('You can follow the step by step instructions for setting up FaceBook App and other settings in our KnowledgeBase.', 'wpfb'); ?> <a href="<?php echo esc_url('https://dev.quantumcloud.com/wpbot-pro/facebook-app-setup-for-messenger-chatbot-addon/'); ?>" class="button button-primary" target="_blank"><?php echo esc_html__('View KnowledgeBase', 'wpfb'); ?></a></h2>
		
		<form method="post" action="options.php">
			<?php settings_fields( 'qc-wpfb-plugin-settings-group' ); ?>
			<?php do_settings_sections( 'qc-wpfb-plugin-settings-group' ); ?>
			<div id="general_settings">
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php echo esc_html__('Enable Facebook Bot', 'wpfb'); ?></th>
						<td>
							<input type="checkbox" name="wpfb_enable_fbbot" value="on" <?php echo (esc_attr( get_option('wpfb_enable_fbbot') )=='on'?'checked="checked"':''); ?> />
							<i><?php echo esc_html__('Turn ON to enable facebook bot on top of WPBot.', 'wpfb'); ?></i>
						</td>
					</tr>
					
					<tr valign="top">
						<th scope="row"><?php echo esc_html__('Facebook App ID', 'wpfb'); ?></th>
						<td>
							<input type="text" name="wpfb_app_id" size="100" value="<?php echo esc_attr( get_option('wpfb_app_id') ); ?>"  />
							<i><?php echo esc_html__('Please add your App ID from Facebook App Dashboard.', 'wpfb'); ?></i>
						</td>
					</tr>
					
					<tr valign="top">
						<th scope="row"><?php echo esc_html__('Facebook App Secret', 'wpfb'); ?></th>
						<td>
							<input type="text" name="wpfb_app_secret" size="100" value="<?php echo esc_attr( get_option('wpfb_app_secret') ); ?>"  />
							<i><?php echo esc_html__('Please add your App Secret from Facebook App Dashboard.', 'wpfb'); ?></i>
						</td>
					</tr>

					<tr valign="top">
						<th scope="row"><?php echo esc_html__('Facebook Verify Token', 'wpfb'); ?></th>
						<td>
							<input type="text" name="wpfb_verify_token" size="100" value="<?php echo esc_attr( get_option('wpfb_verify_token') ); ?>"  />
							<i><?php echo esc_html__('Please add a verify token and also you have to put the same token in facebook messenger app settings. The token could be anything random unique character. Ex: sdf343sdfaewrf2343234ff.', 'wpfb'); ?></i>
						</td>
					</tr>

					<tr valign="top" style="display:none">
						<th scope="row"><?php echo esc_html__('User Access Token', 'wpfb'); ?></th>
						<td>
							<input type="text" name="wpfb_user_access_token" size="100" value="<?php echo esc_attr( get_option('wpfb_user_access_token') ); ?>"  />
							<i><?php echo esc_html__('Please add a User Access Token which you can find in your Facebook App Dashboard.', 'wpfb'); ?></i>
						</td>
					</tr>
					
					<tr valign="top" style="display:none">
						<th scope="row"><?php echo esc_html__('Page Access Token', 'wpfb'); ?></th>
						<td>
							<input type="text" name="wpfb_page_access_token" size="100" value="<?php echo esc_attr( get_option('wpfb_page_access_token') ); ?>"  />
							<i><?php echo esc_html__('Please add a Page Access Token which you can find in Messenger Settings page in Access Tokens section.', 'wpfb'); ?></i>
						</td>
					</tr>
					
					<tr valign="top">
						<th scope="row"><?php echo esc_html__('Facebook OAuth Redirect URIs', 'wpfb'); ?></th>
						<td>
							
							<input type="text" name="wpfb_oauth_redirect_url" size="100" value="<?php echo esc_url(admin_url('admin.php?page=wpbot-fb-private-replies')); ?>" readonly />
							<i><?php echo esc_html__('Please copy the url and paste it to the Valid OAuth Redirect URIs field in Facebook Login > Settings from facebook developer dashboard.', 'wpfb'); ?></i>
						</td>
					</tr>
					
					<?php if(qcpdmca_is_wpbot_active()): ?>
					<tr valign="top">
						<th scope="row"><?php echo esc_html__('Callback URL', 'wpfb'); ?> <?php echo esc_html(mca_wpbot_text()); ?></th>
						<td>
							
							<input type="text" name="wpfb_callback_url" size="100" value="<?php echo esc_url(get_site_url().'/?action=fbinteraction'); ?>" readonly />
							<i><?php echo esc_html__('Please copy the url and add it to the Callback URL field in Webhooks section in Facebook App.', 'wpfb'); ?></i>
						</td>
					</tr>
					
					<?php endif; ?>
					
					<?php if(qcpdmca_is_woowbot_active()): ?>
					<tr valign="top">
						<th scope="row"><?php echo esc_html__('Callback URL', 'wpfb'); ?> <?php echo esc_html(mca_woowbot_text()); ?></th>
						<td>
							
							<input type="text" name="wpfb_callback_url_wow" size="100" value="<?php echo esc_url(get_site_url().'/?action=fbinteractionwow'); ?>" readonly />
							<i><?php echo esc_html__('Please copy the url and add it to the Callback URL field in Webhooks section in Facebook App.', 'wpfb'); ?></i>
						</td>
					</tr>
					<?php endif; ?>
					
					<tr valign="top">
						<th scope="row"><?php echo esc_html__('Default Instruction Message', 'wpfb'); ?></th>
						<td>
							
							<input type="text" name="wpfb_default_instruction" size="100" value="<?php echo (get_option('wpfb_default_instruction')!=''?get_option('wpfb_default_instruction'):esc_html__('For main menu type Start and hit enter. Or type anything related to our services.', 'wpfb')); ?>"  />
							
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php echo esc_html__('Default No Match Reply', 'wpfb'); ?></th>
						<td>
							
							<input type="text" name="wpfb_default_no_match" size="100" value="<?php echo (get_option('wpfb_default_no_match')!=''?get_option('wpfb_default_no_match'):esc_html__('Sorry, nothing matched your query. Please select from Start menu below.', 'wpfb')); ?>"  />
							
						</td>
					</tr>
					
					

				</table>
			</div>
			
			
			

			
			<?php submit_button(); ?>

		</form>
		
	</div>

	<?php
	}
	

	
}
new wbfb_Admin_Area_Controller();
