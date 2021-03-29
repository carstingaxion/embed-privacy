<?php
namespace epiphyt\Embed_Privacy;
use function __;
use function add_filter;
use function add_settings_field;
use function add_settings_section;
use function add_submenu_page;
use function admin_url;
use function current_user_can;
use function do_settings_sections;
use function esc_html__;
use function esc_html_e;
use function esc_url;
use function register_setting;
use function settings_errors;use function settings_fields;
use function submit_button;

/**
 * Admin related methods for Embed Privacy.
 * 
 * @since	1.2.0
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 */
class Admin {
	/**
	 * @var		array Admin to output
	 */
	public $fields = [];
	
	/**
	 * @var		\epiphyt\Embed_Privacy\Admin
	 */
	private static $instance;
	
	/**
	 * Post Type constructor.
	 */
	public function __construct() {
		self::$instance = $this;
	}
	
	/**
	 * Initialize functions.
	 */
	public function init() {
		add_action( 'admin_init', [ $this, 'init_settings' ] );
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
	}
	
	/**
	 * Wrapping function to get a field HTML.
	 * 
	 * @param	array	$attributes Field attributes
	 */
	public function get_field( array $attributes ) {
		Fields::get_instance()->get_the_input_field_html( 0, $attributes );
	}
	
	/**
	 * Get a unique instance of the class.
	 * 
	 * @return	\epiphyt\Embed_Privacy\Admin The single instance of this class
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		
		return self::$instance;
	}
	
	/**
	 * Initialize the settings page.
	 */
	public function init_settings() {
		add_settings_section(
			'embed_privacy_general',
			null,
			null,
			'embed_privacy'
		);
		add_settings_field(
			'embed_privacy_javascript_detection',
			__( 'JavaScript detection', 'embed-privacy' ),
			[ $this, 'get_field' ],
			'embed_privacy',
			'embed_privacy_general',
			[
				'description' => __( 'By enabling this option, checks for embed providers are made via JavaScript on the client-side rather than on your server. Enabling this option is recommended when using a caching plugin.', 'embed-privacy' ),
				'name' => 'embed_privacy_javascript_detection',
				'option_type' => 'option',
				'title' => __( 'JavaScript detection for active providers', 'embed-privacy' ),
				'type' => 'checkbox',
			]
		);
		register_setting( 'embed_privacy', 'embed_privacy_javascript_detection' );
		add_settings_field(
			'embed_privacy_local_tweets',
			__( 'Embeds', 'embed-privacy' ),
			[ $this, 'get_field' ],
			'embed_privacy',
			'embed_privacy_general',
			[
				'description' => __( 'By enabling this option, tweets are embedded locally as text without any connection to Twitter, and no privacy overlay is required.', 'embed-privacy' ),
				'name' => 'embed_privacy_local_tweets',
				'option_type' => 'option',
				'title' => __( 'Local tweets', 'embed-privacy' ),
				'type' => 'checkbox',
			]
		);
		register_setting( 'embed_privacy', 'embed_privacy_local_tweets' );
		add_settings_field(
			'embed_privacy_preserve_data_on_uninstall',
			__( 'Data handling', 'embed-privacy' ),
			[ $this, 'get_field' ],
			'embed_privacy',
			'embed_privacy_general',
			[
				'description' => __( 'By enabling this option, all plugin data is preserved on uninstall.', 'embed-privacy' ),
				'name' => 'embed_privacy_preserve_data_on_uninstall',
				'option_type' => 'option',
				'title' => __( 'Preserve data on uninstall', 'embed-privacy' ),
				'type' => 'checkbox',
			]
		);
		register_setting( 'embed_privacy', 'embed_privacy_preserve_data_on_uninstall' );
	}
	
	/**
	 * Output the options HTML.
	 */
	public function options_html() {
		// check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		// show error/update messages
		settings_errors( 'embed_privacy_messages' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Embed Privacy', 'embed-privacy' ); ?> <a href="<?php echo esc_url( admin_url() . 'edit.php?post_type=epi_embed' ); ?>" class="page-title-action"><?php esc_html_e( 'Manage embeds', 'embed-privacy' ); ?></a></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'embed_privacy' );
				do_settings_sections( 'embed_privacy' );
				submit_button( esc_html__( 'Save Settings', 'embed-privacy' ) );
				?>
			</form>
		</div>
		<?php
	}
	
	/**
	 * Register menu entries.
	 */
	public function register_menu() {
		add_submenu_page(
			'options-general.php',
			__( 'Embed Privacy', 'embed-privacy' ),
			__( 'Embed Privacy', 'embed-privacy' ),
			'manage_options',
			'embed_privacy',
			[ $this, 'options_html' ]
		);
	}
}