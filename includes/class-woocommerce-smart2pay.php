<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       http://www.smart2pay.com
 * @since      1.0.0
 *
 * @package    Woocommerce_Smart2pay
 * @subpackage Woocommerce_Smart2pay/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Woocommerce_Smart2pay
 * @subpackage Woocommerce_Smart2pay/includes
 * @author     Smart2Pay <support@smart2pay.com>
 */
class Woocommerce_Smart2pay
{
    // woocommerce_smart2pay_notification
    const SHORTCODE_PAYMENT = 'woocommerce_smart2pay_pay', SHORTCODE_RETURN = 'woocommerce_smart2pay_return';

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Woocommerce_Smart2pay_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * Language object which will handle translations of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Woocommerce_Smart2pay_i18n    $lang    Maintains translations for the plugin.
	 */
	protected $lang;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

    /** @var WC_S2P_Logs */
    protected $logger = null;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {

		$this->plugin_name = 'woocommerce-smart2pay';
		$this->version = WC_SMART2PAY_VERSION;

        // Add admin notices (depends on WC_Admin_Notices class)
        if( is_admin() )
            add_action( 'init', array( $this, 'load_dependencies_after_init' ), 11 );

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Get the plugin url.
	 * @return string
	 */
	public function plugin_url()
    {
		static $plugin_url = '';

        if( !empty( $plugin_url ) )
            return $plugin_url;

        $plugin_url = untrailingslashit( plugins_url( '/', dirname( __FILE__ ) ) ).'/';
        return $plugin_url;
	}

	/**
	 * Get the plugin path.
	 * @return string
	 */
	public function plugin_path()
    {
        static $plugin_path = '';

        if( !empty( $plugin_path ) )
            return $plugin_path;

        $plugin_path = untrailingslashit( WC_S2P_PLUGIN_DIR ).'/';
        return $plugin_path;
	}

    public function load_dependencies_after_init()
    {
        require_once $this->plugin_path() . 'includes/class-woocommerce-smart2pay-admin-notices.php';
    }

    /**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Woocommerce_Smart2pay_Loader. Orchestrates the hooks of the plugin.
	 * - Woocommerce_Smart2pay_i18n. Defines internationalization functionality.
	 * - Woocommerce_Smart2pay_Admin. Defines all hooks for the admin area.
	 * - Woocommerce_Smart2pay_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies()
    {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once $this->plugin_path() . 'includes/class-woocommerce-smart2pay-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once $this->plugin_path() . 'includes/class-woocommerce-smart2pay-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once $this->plugin_path() . 'admin/class-woocommerce-smart2pay-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once $this->plugin_path() . 'public/class-woocommerce-smart2pay-public.php';

		require_once $this->plugin_path() . 'includes/class-woocommerce-smart2pay-environment.php';
		require_once $this->plugin_path() . 'includes/class-woocommerce-smart2pay-displaymode.php';

        require_once $this->plugin_path() . 'includes/class-woocommerce-smart2pay-admin-notices-later.php';

		require_once $this->plugin_path() . 'includes/class-woocommerce-smart2pay-installer.php';

		require_once $this->plugin_path() . 'includes/class-model.php';

		require_once $this->plugin_path() . 'includes/class-wc-smart2pay-sdk-interface.php';

		require_once $this->plugin_path() . 'includes/class-wc-smart2pay-server-notifications.php';

		Woocommerce_Smart2pay_Installer::init();

		$this->loader = new Woocommerce_Smart2pay_Loader();

		$this->loader->add_filter( 'woocommerce_payment_gateways', $this, 'register_smart2pay_gateway' );
		$this->loader->add_action( 'plugins_loaded', $this, 'init_smart2pay_gateway' );

        add_action( 'woocommerce_api_'.strtolower( WC_S2P_Helper::NOTIFICATION_ENTRY_POINT ), array( 'WC_S2P_Server_Notifications', 'notification_entry_point' ) );

        // Payment gateway is initiated after fees calculation...
        add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_cart_fees' ) );
	}

    public function init_smart2pay_gateway()
    {
        require_once $this->plugin_path() . 'includes/class-woocommerce-smart2pay-gateway.php';
    }

    public function session_s2p_method( $method_id = null )
    {
        if( $method_id === null )
            return WC()->session->get( 's2p_method', 0 );

        $method_id = intval( $method_id );
        WC()->session->set( 's2p_method', $method_id );

        return $method_id;
    }

    /**
     * Add surcharges or other fees in cart
     *
     * @param WC_Cart $cart
     */
    public function add_cart_fees( $cart )
    {
        WC_s2p()->logger()->log( 'Fee from: '.WC_S2P_Helper::debug_call_backtrace() );

        if( !is_checkout() )
            return;

        $posted_arr = array();
        if( !empty( $_POST['post_data'] ) )
            parse_str( $_POST['post_data'], $posted_arr );

        $s2p_method = 0;
        if( !empty( $posted_arr['s2p_method'] ) )
            $s2p_method = intval( $posted_arr['s2p_method'] );
        elseif( !($s2p_method = $this->session_s2p_method()) )
            $s2p_method = 0;

        /** @var WC_S2P_Methods_Model $methods_model */
        if( !($payment_method = PHS_params::_p( 'payment_method', PHS_params::T_NOHTML ))
         or !($wc_s2p_gateway = WC_S2P_Helper::get_plugin_gateway_object())
         or $payment_method != $wc_s2p_gateway->id
         or empty( $wc_s2p_gateway->settings['environment'] )
         or !Woocommerce_Smart2pay_Environment::validEnvironment( $wc_s2p_gateway->settings['environment'] )
         or !$s2p_method
         or empty( WC()->customer )
         or empty( WC()->customer->country )
         or !($country = WC()->customer->country)
         or !($methods_model = WC_s2p()->get_loader()->load_model( 'WC_S2P_Methods_Model' ))
         or !($method_details_arr = $methods_model->get_method_details_for_country( $s2p_method, $country, $wc_s2p_gateway->settings['environment'] ))
         or (
             (empty( $method_details_arr['surcharge_percent'] ) or !(float)$method_details_arr['surcharge_percent'])
             and
             (empty( $method_details_arr['surcharge_amount'] ) or !(float)$method_details_arr['surcharge_amount'])
         )
        )
        {
            $this->session_s2p_method( 0 );
            return;
        }

        $this->session_s2p_method( $s2p_method );

        $percentage = (float)$method_details_arr['surcharge_percent'];
        $surcharge = (WC()->cart->cart_contents_total + WC()->cart->shipping_total) * $percentage / 100 + (float)$method_details_arr['surcharge_amount'];

        WC()->cart->add_fee( 'Payment Method Surcharge', $surcharge, false, '' );
    }

    public function register_smart2pay_gateway( $load_gateways )
    {
        if( !is_array( $load_gateways ) )
            return $load_gateways;

        $load_gateways[] = 'WC_Gateway_Smart2Pay';
        return $load_gateways;
    }

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Woocommerce_Smart2pay_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Woocommerce_Smart2pay_i18n();
		$plugin_i18n->set_domain( $this->get_plugin_name() );

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

        $this->lang = &$plugin_i18n;
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Woocommerce_Smart2pay_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Woocommerce_Smart2pay_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

	}

    public function logger()
    {
        if( !empty( $this->logger ) )
            return $this->logger;

        require_once $this->plugin_path() . 'includes/class-model-logs.php';

        if( !($this->logger = new WC_S2P_Logs()) )
            $this->logger = null;

        return $this->logger;
    }

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Woocommerce_Smart2pay_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

    public function __( $str )
    {
        if( !$this->lang )
            return $str;

        return $this->lang->__( $str );
    }

    public function _x( $str, $context )
    {
        if( !$this->lang )
            return $str;

        return $this->lang->_x( $str, $context );
    }

}
