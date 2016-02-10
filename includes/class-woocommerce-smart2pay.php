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
    const SHORTCODE_RETURN = 'woocommerce_smart2pay_return', PAGE_SLUG_RETURN = 'smart2pay_return';

    const POST_TYPE_ORDER = 'shop_order';

    const S2P_STATUS_OPEN = 1, S2P_STATUS_SUCCESS = 2, S2P_STATUS_CANCELLED = 3, S2P_STATUS_FAILED = 4, S2P_STATUS_EXPIRED = 5, S2P_STATUS_PENDING_CUSTOMER = 6,
        S2P_STATUS_PENDING_PROVIDER = 7, S2P_STATUS_SUBMITTED = 8, S2P_STATUS_AUTHORIZED = 9, S2P_STATUS_APPROVED = 10, S2P_STATUS_CAPTURED = 11, S2P_STATUS_REJECTED = 12,
        S2P_STATUS_PENDING_CAPTURE = 13, S2P_STATUS_EXCEPTION = 14, S2P_STATUS_PENDING_CANCEL = 15, S2P_STATUS_REVERSED = 16, S2P_STATUS_COMPLETED = 17, S2P_STATUS_PROCESSING = 18,
        S2P_STATUS_DISPUTED = 19, S2P_STATUS_CHARGEBACK = 20;

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

        // Order details in front
        add_action( 'woocommerce_order_details_after_order_table', array( $this, 'add_order_details' ) );

        // Order details in email
        add_action( 'woocommerce_email_after_order_table', array( $this, 'add_email_order_details' ), 10, 4 );

        // Priority 35 so it will define metabox after WC_Admin_Meta_Boxes::remove_meta_boxes(),
        // WC_Admin_Meta_Boxes::rename_meta_boxes(), WC_Admin_Meta_Boxes::add_meta_boxes()
        // Order details in admin
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 35 );

        // Notification entry point
        add_action( 'woocommerce_api_'.strtolower( WC_S2P_Helper::NOTIFICATION_ENTRY_POINT ), array( 'WC_S2P_Server_Notifications', 'notification_entry_point' ) );

        // Payment gateway is initiated after fees calculation...
        add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_cart_fees' ) );
    }

    public function add_meta_boxes()
    {
        global $post;

        /** @var WC_S2P_Transactions_Model $transactions_model */
        if( empty( $post )
         or $post->post_type != self::POST_TYPE_ORDER
         or !($order = wc_get_order( $post ))
         or !($payment_gateway_obj = WC_S2P_Helper::get_plugin_gateway_object())
         or $order->payment_method != $payment_gateway_obj->id )
            return;

        add_meta_box( 'woocommerce-order-s2p-details', $this->__( 'Payment Method Details' ), array( $this, 'order_details_meta_box' ), self::POST_TYPE_ORDER, 'normal', 'default' );
        remove_meta_box( 'submitdiv', self::POST_TYPE_ORDER, 'side' );
    }

    public function order_details_meta_box( $post )
    {
        global $post;

        if( empty( $post )
         or $post->post_type != self::POST_TYPE_ORDER
         or !($order = wc_get_order( $post )) )
            return;

        $this->add_order_details( $order );
    }

    /**
     * Show the order details table
     */
    public function add_email_order_details( $order, $sent_to_admin = false, $plain_text = false, $email = '' )
    {
        $this->add_order_details( $order, $sent_to_admin, true, $plain_text );
    }

    /**
     * Add payment method details to order template
     *
     * @param WC_Order $order
     */
    public function add_order_details( $order, $to_admin = null, $is_email = false, $plain_text = false )
    {
        /** @var WC_S2P_Transactions_Model $transactions_model */
        /** @var WC_S2P_Methods_Model $methods_model */
        if( empty( $order )
         or !($payment_gateway_obj = WC_S2P_Helper::get_plugin_gateway_object())
         or $payment_gateway_obj->id != $order->payment_method
         or !($transactions_model = WC_s2p()->get_loader()->load_model( 'WC_S2P_Transactions_Model' ))
         or !($methods_model = WC_s2p()->get_loader()->load_model( 'WC_S2P_Methods_Model' ))
         or !($transaction_arr = $transactions_model->get_details_fields( array( 'order_id' => $order->id ) ))
         or empty( $transaction_arr['method_id'] )
         or empty( $transaction_arr['environment'] )
         or !($method_arr = $methods_model->get_details_fields( array( 'method_id' => $transaction_arr['method_id'], 'environment' => $transaction_arr['environment'] ) ))
        )
            return;

        if( !$is_email )
        {
            // site front/admin
            $data_header = '<table class="shop_table">'.
                           '<tbody>';
            $rows_template = '<tr>'.
                             '<th>%s</th>'.
                             '<td>%s</td>'.
                             '</tr>';
            $data_footer = '</tbody></table>';
        } elseif( !$plain_text )
        {
            // html email
            $data_header = '<table class="td" cellspacing="0" cellpadding="6" style="width: 100%; font-family: \'Helvetica Neue\', Helvetica, Roboto, Arial, sans-serif;" border="1">'.
                           '<tbody>';
            $rows_template = '<tr>'.
                             '<td class="td" scope="row" style="text-align:left; word-wrap:break-word; vertical-align:middle; border: 1px solid #eee; font-family: \'Helvetica Neue\', Helvetica, Roboto, Arial, sans-serif;">%s</td>'.
                             '<td class="td" style="text-align:left; word-wrap:break-word; vertical-align:middle; border: 1px solid #eee; font-family: \'Helvetica Neue\', Helvetica, Roboto, Arial, sans-serif;">%s</td>'.
                             '</tr>';
            $data_footer = '</tbody></table>';
        } else
        {
            // plain text email
            $data_header = "\n\n";
            $rows_template = '%s: '."\t".'%s'."\n";
            $data_footer = "\n\n";
        }

        if( $to_admin === null )
            $to_admin = is_admin();

        // In admin interface this is content of metabox so we don't need title...
        if( !is_admin() or $is_email )
        {
            $title = '<h2>'.$this->__( 'Payment Method Details' ).'</h2>';

            if( !$is_email )
                $title = '<header>'.$title.'</header>';

            echo $title;
        }

        echo $data_header;

        if( $to_admin )
            echo sprintf( $rows_template,
                          $this->__( 'Environment' ),
                          (!empty($transaction_arr['environment']) ? ucfirst( $transaction_arr['environment'] ): $this->__( 'N/A' ))
            );

        echo sprintf( $rows_template,
                      $this->__( 'Payment Method' ),
                      (!empty($method_arr['display_name']) ? ucfirst( $method_arr['display_name'] ): $this->__( 'N/A' ))
        );
        echo sprintf( $rows_template,
                      $this->__( 'Payment ID' ),
                      (!empty($transaction_arr['payment_id']) ? ucfirst( $transaction_arr['payment_id'] ): $this->__( 'N/A' ))
        );

        if( !empty( $transaction_arr['extra_data'] )
        and ($extra_data = WC_S2P_Helper::parse_string( $transaction_arr['extra_data'] ))
        and is_array( $extra_data ) )
        {
            foreach( $extra_data as $key => $val )
            {
                if( !($key_title = WC_S2P_Helper::transaction_details_key_to_title( $key )) )
                    $key_title = $key;

                echo sprintf( $rows_template,
                              $key_title,
                              $val
                );
            }

            if( !$to_admin )
            {
                if( !$is_email )
                {
                    ?>
                    <tr>
                        <td colspan="2"><?php echo $this->__( 'In order to complete payment please use details above.' ) ?></td>
                    </tr>
                    <?php
                } elseif( !$plain_text )
                {
                    ?>
                    <tr>
                        <td colspan="2" class="td" style="text-align:left;"><?php echo $this->__( 'In order to complete payment please use details above.' ) ?></td>
                    </tr>
                    <?php
                } else
                {
                    echo "\n".$this->__( 'In order to complete payment please use details above.' )."\n";
                }
            }
        }

        echo $data_footer;
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

    public static function do_return_shortcode()
    {
        $data = PHS_params::_g( 'data', PHS_params::T_INT );
        $mtid = PHS_params::_g( 'MerchantTransactionID', PHS_params::T_ASIS );

        if( empty( $data ) )
            $data = self::S2P_STATUS_FAILED;

        if( !($mtid = WC_S2P_Helper::convert_from_demo_merchant_transaction_id( $mtid )) )
        {
            return WC_s2p()->__( 'Unknown transaction.' );
        }

        $check_arr = array();
        $check_arr['order_id'] = $mtid;

        /** @var WC_S2P_Transactions_Model $transactions_model */
        if( !($transactions_model = WC_s2p()->get_loader()->load_model( 'WC_S2P_Transactions_Model' ))
         or !($transaction_arr = $transactions_model->get_details_fields( $check_arr )) )
        {
            return WC_s2p()->__( 'Couldn\'t extract transaction details.' );
        }

        if( !($plugin_settings_arr = WC_S2P_Helper::get_plugin_settings()) )
        {
            return WC_s2p()->__( 'Couldn\'t extract Smart2Pay plugin settings.' );
        }

        if( empty( $plugin_settings_arr['message_data_'.$data] ) )
            $return_message = WC_s2p()->__( 'Unknown return status.' );
        else
            $return_message = $plugin_settings_arr['message_data_'.$data];

        ob_start();
        ?>
        <h1 class="entry-title"><?php echo WC_s2p()->__( 'Thank you for shopping with us!' )?></h1>
        <p><?php echo $return_message?></p>
        <?php

        if( !empty( $transaction_arr['extra_data'] )
        and ($extra_data = WC_S2P_Helper::parse_string( $transaction_arr['extra_data'] ))
        and is_array( $extra_data ) )
        {
            ?>
	        <p>In order to complete your transaction please use details below.</p>
	        <table>
            <thead>
            <tr>
                <th colspan="2">Transaction Extra Details</th>
            </tr>
            </thead>
            <tbody>
            <?php
            foreach( $extra_data as $key => $val )
            {
                if( !($key_title = WC_S2P_Helper::transaction_details_key_to_title( $key )) )
                    $key_title = $key;
                ?>
                <tr>
                    <td><?php echo $key_title?></td>
                    <td><?php echo $val?></td>
                </tr>
                <?php
            }
            ?></tbody></table><?php
        }

        $buf = ob_get_clean();

        return $buf;
    }

    /**
     * Add surcharges or other fees in cart
     *
     * @param WC_Cart $cart
     */
    public function add_cart_fees( $cart )
    {
        // WC_s2p()->logger()->log( 'Fee from: '.WC_S2P_Helper::debug_call_backtrace() );

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

        $cart_total = WC()->cart->cart_contents_total + WC()->cart->shipping_total;

        $percentage = (float)$method_details_arr['surcharge_percent'];
        $surcharge = $cart_total * $percentage / 100 + (float)$method_details_arr['surcharge_amount'];

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

        add_shortcode( self::SHORTCODE_RETURN, array( __CLASS__, 'do_return_shortcode' ) );
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
