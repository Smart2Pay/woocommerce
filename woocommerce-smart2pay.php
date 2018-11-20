<?php

define( 'WC_SMART2PAY_VERSION', '1.2.2' );

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              http://www.smart2pay.com
 * @since             1.0.0
 * @package           Woocommerce_Smart2pay
 *
 * @wordpress-plugin
 * Plugin Name:       WooCommerce Smart2Pay
 * Plugin URI:        http://www.smart2pay.com/?woocommerce
 * Description:       Secure payments through 100+ alternative payment options.
 * Version:           1.2.2
 * Author:            Smart2Pay
 * Author URI:        http://www.smart2pay.com
 * Developer:         Smart2Pay
 * Developer URI:     http://www.smart2pay.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       woocommerce-smart2pay
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Check if WooCommerce is active
 **/
if( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) )
{
    define( 'WC_S2P_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

    /**
     * The code that runs during plugin activation.
     * This action is documented in includes/class-woocommerce-smart2pay-activator.php
     */
    function activate_woocommerce_smart2pay()
    {
        require_once WC_S2P_PLUGIN_DIR . 'includes/class-woocommerce-smart2pay-activator.php';
        Woocommerce_Smart2pay_Activator::activate();
    }

    /**
     * The code that runs during plugin deactivation.
     * This action is documented in includes/class-woocommerce-smart2pay-deactivator.php
     */
    function deactivate_woocommerce_smart2pay()
    {
        require_once WC_S2P_PLUGIN_DIR . 'includes/class-woocommerce-smart2pay-deactivator.php';
        Woocommerce_Smart2pay_Deactivator::deactivate();
    }

    function woocommerce_smart2pay_action_links( $links )
    {
        $settings_title = WC_s2p()->__( 'Settings' );

        $plugin_links = array(
            '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_smart2pay' ) . '">' . $settings_title . '</a>',
        );

        // Merge our new link with the default ones
        return array_merge( $plugin_links, $links );
    }

    // Add custom action links
    add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'woocommerce_smart2pay_action_links' );

    register_activation_hook( __FILE__, 'activate_woocommerce_smart2pay' );
    register_deactivation_hook( __FILE__, 'deactivate_woocommerce_smart2pay' );

    /**
     * The core plugin class that is used to define internationalization,
     * admin-specific hooks, and public-facing site hooks.
     */
    if( @is_dir( WC_S2P_PLUGIN_DIR.'includes/sdk' )
    and @file_exists( WC_S2P_PLUGIN_DIR.'includes/sdk/bootstrap.php' ) )
    {
        include_once( WC_S2P_PLUGIN_DIR.'includes/sdk/bootstrap.php' );

        S2P_SDK\S2P_SDK_Module::st_debugging_mode( false );
        S2P_SDK\S2P_SDK_Module::st_detailed_errors( false );
        S2P_SDK\S2P_SDK_Module::st_throw_errors( false );
    }

    require_once WC_S2P_PLUGIN_DIR . 'includes/phs_params.php';
    require_once WC_S2P_PLUGIN_DIR . 'includes/class-base.php';
    require_once WC_S2P_PLUGIN_DIR . 'includes/class-woocommerce-smart2pay.php';
    require_once WC_S2P_PLUGIN_DIR . 'includes/class-wc-smart2pay-sdk-interface.php';
    require_once WC_S2P_PLUGIN_DIR . 'includes/class-wc-smart2pay-helper.php';

    /**
     * Begins execution of the plugin.
     * Since everything within the plugin is registered via hooks,
     * then kicking off the plugin from this point in the file does
     * not affect the page life cycle.
     * @since    1.0.0
     */
    function run_woocommerce_smart2pay()
    {
        /** @var Woocommerce_Smart2pay $wc_s2p */
        global $wc_s2p;

        $plugin = new Woocommerce_Smart2pay();
        $plugin->run();

        $wc_s2p = $plugin;
    }

    /**
     * @return Woocommerce_Smart2pay
     */
    function WC_s2p()
    {
        /** @var Woocommerce_Smart2pay $wc_s2p */
        global $wc_s2p;

        return $wc_s2p;
    }

    run_woocommerce_smart2pay();
}
