<?php
/*
 Plugin Name: WP-Cron Control
 Plugin URI: http://wordpress.org/extend/plugins/wp-cron-control/
 Description: get control over wp-cron execution.
 Author: Thorsten Ott, Automattic
 Version: 0.1
 Author URI: http://hitchhackerguide.com
 */

class WP_Cron_Control {

	private static $__instance = NULL;
	
	private $settings = array();
	private $default_settings = array();
	private $settings_texts = array();
	
	private $plugin_prefix = 'wpcroncontrol_';
	private $plugin_name = 'WP-Cron Control';
	private $settings_page_name ='WP-Cron Control Settings';
	private $dashed_name = 'wp-cron-control';
	private $js_version = '20110801';
	private $css_version = '20110801';
	
	private $define_global_secret = NULL;	// if this is set, it's value will be used as secret instead of the option
	
	public function __construct() {
		global $blog_id;
		
		if ( NULL <> $this->define_global_secret && !defined( 'WP_CRON_CONTROL_SECRET' ) )
			define( 'WP_CRON_CONTROL_SECRET', $this->define_global_secret );
		
		add_action( 'admin_init', array( &$this, 'register_setting' ) );
		add_action( 'admin_menu', array( &$this, 'register_settings_page' ) );
		
		$this->default_settings = (array) apply_filters( $this->plugin_prefix . 'default_settings', array(
			'enable'				=> 1,
			'secret_string'			=> md5( __FILE__ . $blog_id ),
		) );
		
		$this->settings_texts = (array) apply_filters( $this->plugin_prefix . 'settings_texts', array(
			'enable'				=> array( 'label' => 'Enable ' . $this->plugin_name, 'desc' => 'Enable this plugin and allow requests to wp-cron.php only with the appended secret parameter.', 'type' => 'yesno' ),
			'secret_string'			=> array( 'label' => 'Secret string', 'desc' => 'The secret parameter that needs to be appended to wp-cron.php requests.', 'type' => 'text' ),
		) );
					
		$user_settings = get_option( $this->plugin_prefix . 'settings' );
		if ( false === $user_settings )
			$user_settings = array();
			
		$this->settings = wp_parse_args( $user_settings, $this->default_settings );	
		if ( defined( 'WP_CRON_CONTROL_SECRET' ) ) {
			$this->settings_texts['secret_string']['type'] = 'echo';
			$this->settings_texts['secret_string']['desc'] = $this->settings_texts['secret_string']['desc'] . " Cannot be changed as it is defined via WP_CRON_CONTROL_SECRET";
			$this->settings['secret_string'] = WP_CRON_CONTROL_SECRET;
		}

	}
	
	public static function init() {	
		if ( 1 == self::instance()->settings['enable'] ) {

		}
		
		self::instance()->prepare();
	}
	
	/*
	 * Use this singleton to address methods
	 */
	public static function instance() {
		if ( self::$__instance == NULL ) 
			self::$__instance = new WP_Cron_Control;
		return self::$__instance;
	}

	public function prepare() {		
		if ( file_exists( dirname( __FILE__ ) . "/css/" . $this->dashed_name . ".css" ) )
			wp_enqueue_style( $this->dashed_name, plugins_url( "css/" . $this->dashed_name . ".css", __FILE__ ), $deps = array(), $this->css_version );
		if ( file_exists( dirname( __FILE__ ) . "/js/" . $this->dashed_name . ".js" ) )
			wp_enqueue_script( $this->dashed_name, plugins_url( "js/" . $this->dashed_name . ".js", __FILE__ ), array(), $this->js_version, true );
			
		if ( 1 == $this->settings['enable'] ) {
			remove_action( 'sanitize_comment_cookies', 'wp_cron' );
			add_action( 'init', array( &$this, 'validate_cron_request' ) );
		}
		
	}

	public function register_settings_page() {
		add_options_page( $this->settings_page_name, $this->plugin_name, 'manage_options', $this->dashed_name, array( &$this, 'settings_page' ) );
	}

	public function register_setting() {
		register_setting( $this->plugin_prefix . 'settings', $this->plugin_prefix . 'settings', array( &$this, 'validate_settings') );
	}
	
	public function validate_settings( $settings ) {
		if ( !empty( $_POST[ $this->dashed_name . '-defaults'] ) ) {
			$settings = $this->default_settings;
			$_REQUEST['_wp_http_referer'] = add_query_arg( 'defaults', 'true', $_REQUEST['_wp_http_referer'] );
		} else {
			
		}
		return $settings;
	}
	
	public function settings_page() { ?>
	<div class="wrap">
	<?php if ( function_exists('screen_icon') ) screen_icon(); ?>
		<h2><?php echo $this->settings_page_name; ?></h2>
	
		<form method="post" action="options.php">
	
		<?php settings_fields( $this->plugin_prefix . 'settings' ); ?>
	
		<table class="form-table">
			<?php foreach( $this->settings as $setting => $value): ?>
			<tr valign="top">
				<th scope="row"><label for="<?php echo $this->dashed_name . '-' . $setting; ?>"><?php if ( isset( $this->settings_texts[$setting]['label'] ) ) { echo $this->settings_texts[$setting]['label']; } else { echo $setting; } ?></label></th>
				<td>
					<?php switch( $this->settings_texts[$setting]['type'] ):
						case 'yesno': ?>
							<select name="<?php echo $this->plugin_prefix; ?>settings[<?php echo $setting; ?>]" id="<?php echo $this->dashed_name . '-' . $setting; ?>" class="postform">
								<?php 
									$yesno = array( 0 => 'No', 1 => 'Yes' ); 
									foreach ( $yesno as $val => $txt ) {
										echo '<option value="' . esc_attr( $val ) . '"' . selected( $value, $val, false ) . '>' . esc_html( $txt ) . "&nbsp;</option>\n";
									}
								?>
							</select><br />
						<?php break;
						case 'text': ?>
							<div><input type="text" name="<?php echo $this->plugin_prefix; ?>settings[<?php echo $setting; ?>]" id="<?php echo $this->dashed_name . '-' . $setting; ?>" class="postform" value="<?php echo esc_attr( $value ); ?>" /></div>
						<?php break;
						case 'echo': ?>
							<div><span id="<?php echo $this->dashed_name . '-' . $setting; ?>" class="postform"><?php echo esc_attr( $value ); ?></span></div>
						<?php break;
						default: ?>
							<?php echo $this->settings_texts[$setting]['type']; ?>
						<?php break;
					endswitch; ?>
					<?php if ( !empty( $this->settings_texts[$setting]['desc'] ) ) { echo $this->settings_texts[$setting]['desc']; } ?>
				</td>
			</tr>
			<?php endforeach; ?>
			<?php if ( 1 == $this->settings['enable'] ): ?>
				<tr>
					<td colspan="3">
						<p>You enabled wp-cron-control. To make sure that scheduled tasks are still executed correctly you will need to setup a system cron job that will call wp-cron.php with the secret parameter defined in the settings.</p>
						<p>
							You can either use the function defined in this script and setup a cron job that calls either
						</p>
						<p><code>php <?php echo __FILE__; ?> <?php echo get_site_url(); ?> <?php echo $this->settings['secret_string']; ?></code></p>
						<p>or</p>
						<p><code>wget -q "<?php echo get_site_url(); ?>/wp-cron.php?doing_wp_cron&<?php echo $this->settings['secret_string']; ?>"</code></p>
						<p>You can setup an interval as low as one minute, but should consider a reasonable value of 5-15 minutes as well.</p>
						<p>If you need help setting up a cron job please refer to the documentation that your provider offers.</p>
						<p>Anyway, chances are high that either <a href="http://docs.cpanel.net/twiki/bin/view/AllDocumentation/CpanelDocs/CronJobs#Adding a cron job" target="_blank">the CPanel</a>, <a href="http://download1.parallels.com/Plesk/PP10/10.3.1/Doc/en-US/online/plesk-administrator-guide/plesk-control-panel-user-guide/index.htm?fileName=65208.htm" target="_blank">Plesk</a> or <a href="http://www.thegeekstuff.com/2011/07/php-cron-job/" target="_blank">the crontab</a> documentation will help you.</p>
					</td>
				</tr>
			<?php endif; ?>
		</table>
		
		<p class="submit">
	<?php
			if ( function_exists( 'submit_button' ) ) {
				submit_button( null, 'primary', $this->dashed_name . '-submit', false );
				echo ' ';
				submit_button( 'Reset to Defaults', 'primary', $this->dashed_name . '-defaults', false );
			} else {
				echo '<input type="submit" name="' . $this->dashed_name . '-submit" class="button-primary" value="Save Changes" />' . "\n";
				echo '<input type="submit" name="' . $this->dashed_name . '-defaults" id="' . $this->dashed_name . '-defaults" class="button-primary" value="Reset to Defaults" />' . "\n";
			}
	?>
		</p>
	
		</form>
	</div>
	
	<?php
	}
	
	public function validate_cron_request() {
		// we're in wp-cron.php
		if ( false !== strpos( $_SERVER['REQUEST_URI'], '/wp-cron.php' ) ) {
			if ( defined( 'WP_CRON_CONTROL_SECRET' ) )
				$secret = WP_CRON_CONTROL_SECRET;
			else 
				$secret = $this->settings['secret_string'];
			if ( isset( $_GET[$secret] ) ) {
				// check if there is already a cron request running
				$local_time = time();
				$flag = get_transient('doing_cron');
				if ( $flag > $local_time + 10*60 )
					$flag = 0;
				// don't run if another process is currently running it or more than once every 60 sec.
				if ( $flag + 60 > $local_time )
					die( 'another cron process running or previous not older than 60 secs' );
				
				set_transient( 'doing_cron', $local_time );
				return true;
			}
			// something went wrong
			die( 'invalid secret string' );
		}
		
		// for all other cases disable wp-cron.php and spawn_cron() by telling the system it's already running
		if ( !defined( 'DOING_CRON' ) )
			define( 'DOING_CRON', true );
		// and also disable the wp_cron() call execution
		if ( !defined( 'DISABLE_WP_CRON' ) )
			define( 'DISABLE_WP_CRON', true );
		return false;
	}
}

/**
 * This method can be used to initiate a cron call via cli
 */
function wp_cron_control_call_cron( $blog_address, $secret ) {
 	$cron_url = $blog_address . '/wp-cron.php?doing_wp_cron&' . $secret;
 	$ch = curl_init( $cron_url );
 	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 0 );
	curl_setopt( $ch, CURLOPT_TIMEOUT, '3' );
	$result = curl_exec( $ch );
	curl_close( $ch );
	return $result;
}

if ( defined('ABSPATH') ) {
	WP_Cron_Control::init();
} else {
	// cli usage
	if ( !empty( $argv ) && $argv[0] == basename( __FILE__ ) || $argv[0] == __FILE__ ) {
		if ( isset( $argv[1] ) && isset( $argv[2] ) ) {
			wp_cron_control_call_cron( $argv[1], $argv[2] );
		} else {
			echo "Usage: php " . __FILE__ . " <blog_address> <secret_string>\n";
			echo "Example: php " . __FILE__ . " http://my.blog.com efe18b0e53498e737da9b91cf4ca3d25\n";
			exit;
		}  
	}
}

