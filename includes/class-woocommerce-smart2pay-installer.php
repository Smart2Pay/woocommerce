<?php

/**
 * Checks if plugin is up-to-date
 *
 * @link       http://www.smart2pay.com
 * @since      1.0.0
 *
 * @package    Woocommerce_Smart2pay
 * @subpackage Woocommerce_Smart2pay/includes
 */

/**
 * Checks if plugin is up-to-date
 *
 * This class defines all code necessary to check plugin versioning
 *
 * @since      1.0.0
 * @package    Woocommerce_Smart2pay
 * @subpackage Woocommerce_Smart2pay/includes
 * @author     Smart2Pay <support@smart2pay.com>
 */
class Woocommerce_Smart2pay_Installer
{
    const VERSION_OPTION = 'wc_smart2pay_version';

    /** @var array DB updates that need to be run */
    private static $db_updates = array(
        // '1.0.1' => 'updates/update-1.0.1.php',
    );

    public static function init()
    {
        add_action( 'admin_init', array( __CLASS__, 'check_version' ), 5 );
    }

    public static function check_version()
    {
        // Make sure we can save notices as install action is executed before we have all things inited in plugin
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-woocommerce-smart2pay-admin-notices-later.php';

        if( !($current_version = get_option( self::VERSION_OPTION, false )) )
        {
            $install_failed = false;
            if( !self::install() )
                $install_failed = true;

            if( $install_failed )
                Woocommerce_Smart2pay_Admin_Notices_Later::add_notice( 'install_failed' );
            else
                Woocommerce_Smart2pay_Admin_Notices_Later::add_notice( 'install_success' );
        }

        if( $current_version == WC_s2p()->get_version() )
            return true;

        foreach( self::$db_updates as $version => $updater )
        {
            if( empty( $current_version )
             or version_compare( $current_version, $version, '<' ) )
            {
                include( $updater );
            }
        }

        self::update_version();

        return true;
    }

    /**
     * Update WC version to current.
     */
    private static function update_version()
    {
        delete_option( self::VERSION_OPTION );
        add_option( self::VERSION_OPTION, WC_s2p()->get_version() );
    }

    public static function install()
    {
        if( !self::create_tables()
         or !self::create_pages() )
            return false;

        // Flush rules after install
        flush_rewrite_rules();

        return true;
    }

    /**
     * Create pages that the plugin relies on, storing page id's in variables.
     */
    public static function create_pages()
    {
        include_once( untrailingslashit( plugin_dir_path( WC_PLUGIN_FILE ) ) . '/includes/admin/wc-admin-functions.php' );

        $pages = apply_filters( 'woocommerce_smart2pay_create_pages', array(
            'smart2pay_pay' => array(
                'name'    => WC_s2p()->_x( 'smart2pay_pay', 'Page slug' ),
                'title'   => WC_s2p()->_x( 'Smart2Pay Payment', 'Page title' ),
                'content' => '[woocommerce_smart2pay_pay]'
            ),
            'smart2pay_return' => array(
                'name'    => WC_s2p()->_x( 'smart2pay_return', 'Page slug' ),
                'title'   => WC_s2p()->_x( 'Smart2Pay Return Page', 'Page title' ),
                'content' => '[woocommerce_smart2pay_return]'
            ),
        ) );

        foreach ( $pages as $key => $page ) {
            if( !wc_create_page( esc_sql( $page['name'] ), 'woocommerce_' . $key . '_page_id', $page['title'], $page['content'], ! empty( $page['parent'] ) ? wc_get_page_id( $page['parent'] ) : '' ) )
                return false;
        }

        delete_transient( 'woocommerce_cache_excluded_uris' );

        return true;
    }

    public static function delete_tables()
    {
        global $wpdb;

        $wpdb->query( "DELETE TABLE IF EXISTS {$wpdb->prefix}smart2pay_transactions;" );
        $wpdb->query( "DELETE TABLE IF EXISTS {$wpdb->prefix}smart2pay_method_settings;" );
        $wpdb->query( "DELETE TABLE IF EXISTS {$wpdb->prefix}smart2pay_method;" );
        $wpdb->query( "DELETE TABLE IF EXISTS {$wpdb->prefix}smart2pay_logs;" );
        $wpdb->query( "DELETE TABLE IF EXISTS {$wpdb->prefix}smart2pay_country;" );
    }

    public static function create_tables()
    {
        global $wpdb;

        $wpdb->hide_errors();

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $collate = '';

        if( $wpdb->has_cap( 'collation' ) )
        {
            if( ! empty($wpdb->charset) )
                $collate .= "DEFAULT CHARACTER SET $wpdb->charset";
            if( ! empty($wpdb->collate) )
                $collate .= " COLLATE $wpdb->collate";
        }

        if( !($wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}smart2pay_transactions (
                        id int(11) NOT NULL AUTO_INCREMENT,
                        method_id int(11) NOT NULL DEFAULT '0',
                        payment_id int(11) NOT NULL DEFAULT '0',
                        order_id int(11) NOT NULL DEFAULT '0',
                        site_id int(11) NOT NULL DEFAULT '0',
                        environment varchar(20) DEFAULT NULL,
                        extra_data text,
                        surcharge_amount decimal(6,2) NOT NULL,
                        surcharge_currency varchar(3) DEFAULT NULL COMMENT 'Currency ISO 3',
                        surcharge_percent decimal(6,2) NOT NULL,
                        surcharge_order_amount decimal(6,2) NOT NULL,
                        surcharge_order_percent decimal(6,2) NOT NULL,
                        surcharge_order_currency varchar(3) DEFAULT NULL COMMENT 'Currency ISO 3',
                        payment_status tinyint(2) NOT NULL DEFAULT '0' COMMENT 'Status received from server',
                        last_update timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
                        created timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
                        PRIMARY KEY (id),
                        KEY method_id (method_id),
                        KEY payment_id (payment_id),
                        KEY order_id (order_id)
                    ) $collate COMMENT='Transactions run trough Smart2Pay';" )) )
        {
            self::delete_tables();
            return false;
        }

        if( !($wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}smart2pay_method_settings (
                        id int(11) NOT NULL AUTO_INCREMENT,
                        method_id int(11) NOT NULL DEFAULT '0',
                        enabled tinyint(2) NOT NULL DEFAULT '0',
                        surcharge_percent decimal(6,2) NOT NULL DEFAULT '0.00',
                        surcharge_amount decimal(6,2) NOT NULL DEFAULT '0.00' COMMENT 'Amount of surcharge',
                        surcharge_currency varchar(3) DEFAULT NULL COMMENT 'ISO 3 currency code of fixed surcharge amount',
                        priority tinyint(4) NOT NULL DEFAULT '10' COMMENT '1 means first',
                        last_update timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
                        configured timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
                        PRIMARY KEY (id),
                        KEY method_id (method_id),
                        KEY enabled (enabled)
                    ) $collate COMMENT='Smart2Pay method configurations';" )) )
        {
            self::delete_tables();
            return false;
        }

        if( !($wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}smart2pay_method (
                        method_id int(11) NOT NULL AUTO_INCREMENT,
                        environment varchar(50) default NULL,
                        display_name varchar(255) default NULL,
                        description text,
                        logo_url varchar(255) default NULL,
                        guaranteed tinyint(2) default 0,
                        active tinyint(2) default 0,
                        PRIMARY KEY (method_id),
                        KEY active (active),
                        KEY environment (environment)
                    ) $collate;" )) )
        {
            self::delete_tables();
            return false;
        }

        if( !($wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}smart2pay_logs (
                        log_id int(11) NOT NULL AUTO_INCREMENT,
                        order_id int(11) NOT NULL default '0',
                        log_type varchar(255) default NULL,
                        log_data text default NULL,
                        log_source_file varchar(255) default NULL,
                        log_source_file_line varchar(255) default NULL,
                        log_created timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (log_id),
                        KEY order_id (order_id),
                        KEY log_type (log_type)
                    ) $collate;" )) )
        {
            self::delete_tables();
            return false;
        }

        if( !($wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}smart2pay_country (
                        country_id int(11) NOT NULL AUTO_INCREMENT,
                        code varchar(3) default NULL,
                        name varchar(100) default NULL,
                        PRIMARY KEY (country_id),
                        KEY code (code)
                    ) $collate;" )) )
        {
            self::delete_tables();
            return false;
        }

        return true;
    }

}
