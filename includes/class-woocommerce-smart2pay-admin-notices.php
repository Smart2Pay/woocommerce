<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
if( !class_exists( 'Woocommerce_Smart2pay_Admin_Notices', false ) )
{
    class Woocommerce_Smart2pay_Admin_Notices
    {
        const WC_S2P_NOTICES_OPTION_NAME = 'wc_s2p_admin_notices';

        const CUSTOM_NOTICE = 'custom_notice';

        const TYPE_ERROR = 'error', TYPE_SUCCESS = 'update';

        /**
         * Array of notices - name => callback.
         * @var array
         */
        private $core_notices = array(
            'install_failed' => 'install_failed',
            'install_success' => 'install_success',
            self::CUSTOM_NOTICE => 'custom_notice',
        );

        public function __construct()
        {
            add_action( 'admin_print_styles', array( $this, 'add_notices' ) );

            if( ($later_notices = Woocommerce_Smart2pay_Admin_Notices_Later::get_notices()) and is_array( $later_notices ) )
            {
                $notices = array_merge( get_option( self::WC_S2P_NOTICES_OPTION_NAME, array() ), $later_notices );
                update_option( self::WC_S2P_NOTICES_OPTION_NAME, $notices );

                Woocommerce_Smart2pay_Admin_Notices_Later::reset_notices();
            }

            Woocommerce_Smart2pay_Admin_Notices_Later::we_can_notice_now();
        }

        public static function add_notice( $name, $notice_params = false, $bulk_notifications = false )
        {
            $current_notices = get_option( self::WC_S2P_NOTICES_OPTION_NAME, array() );

            $notice_body = array();
            $merge_with_current = true;
            if( $name == self::CUSTOM_NOTICE )
            {
                if( empty( $bulk_notifications ) )
                    $notice_body[$name][] = $notice_params;

                else
                {
                    if( !is_array( $notice_params ) )
                        return;

                    if( empty( $current_notices[$name] ) )
                        $current_notices[$name] = $notice_params;

                    else
                    {
                        foreach( $notice_params as $notice_arr )
                            $current_notices[$name][] = $notice_arr;
                    }

                    $merge_with_current = false;
                }
            } else
                $notice_body[$name] = $notice_params;

            if( $merge_with_current )
                $current_notices = array_merge( $current_notices, $notice_body );

            update_option( self::WC_S2P_NOTICES_OPTION_NAME, $current_notices );
        }

        /**
         * Add notices + styles if needed.
         */
        public function add_notices()
        {
            if( !($notices = get_option( self::WC_S2P_NOTICES_OPTION_NAME, array() ))
             or !is_array( $notices ) )
                return;

            wp_enqueue_style( 'woocommerce-activation', plugins_url(  '/assets/css/activation.css', WC_PLUGIN_FILE ) );
            foreach ( $notices as $notice => $notice_params )
            {
                if ( ! empty( $this->core_notices[ $notice ] ) )// && apply_filters( 'woocommerce_show_admin_notice', true, $notice ) )
                {
                    add_action( 'admin_notices', array( $this, $this->core_notices[ $notice ] ) );
                }
            }
        }

        public static function remove_all_notices()
        {
            delete_option( self::WC_S2P_NOTICES_OPTION_NAME );
        }

        public static function remove_notice( $name )
        {
            $notices = get_option( self::WC_S2P_NOTICES_OPTION_NAME, array() );

            if( !empty( $notices ) and isset( $notices[$name] ) )
            {
                unset( $notices[$name] );

                if( !empty( $notices ) and is_array( $notices ) )
                    update_option( self::WC_S2P_NOTICES_OPTION_NAME, $notices );
                else
                    self::remove_all_notices();
            }
        }

        public static function default_custom_notification_fields()
        {
            return array(
                'notice_type' => 'error', // error or update
                'message_id' => 0, // unique identifier (div will be assigned this id) (optional)
                'message' => '',
            );
        }

        public static function valid_notice_type( $type )
        {
            $type = strtolower( trim( $type ) );
            if( !in_array( $type, array( self::TYPE_ERROR, self::TYPE_SUCCESS ) ) )
                return false;

            return $type;
        }

        public static function validate_notification_fields( $notice_arr )
        {
            if( empty( $notice_arr ) or !is_array( $notice_arr ) )
                $notice_arr = array();

            $default_fields = self::default_custom_notification_fields();
            foreach( $default_fields as $key => $def_value )
            {
                if( !array_key_exists( $key, $notice_arr ) )
                    $notice_arr[$key] = $def_value;
            }

            if( !($notice_arr['notice_type'] = self::valid_notice_type( $notice_arr['notice_type'] )) )
                $notice_arr['notice_type'] = self::TYPE_ERROR;

            if( empty( $notice_arr['message_id'] ) )
                $notice_arr['message_id'] = str_replace( '.', '', microtime( true ) ).'_'.rand( 1000, 9999 );

            return $notice_arr;
        }

        public function custom_notice()
        {
            $notices = get_option( self::WC_S2P_NOTICES_OPTION_NAME, array() );

            if( empty( $notices[self::CUSTOM_NOTICE] ) or !is_array( $notices[self::CUSTOM_NOTICE] ) )
                return;

            foreach( $notices[self::CUSTOM_NOTICE] as $notice_arr )
            {
                $notice_arr = self::validate_notification_fields( $notice_arr );

                include( 'notices/html-notice-custom.php' );
            }

            self::remove_notice( self::CUSTOM_NOTICE );
        }

        public function install_failed()
        {
            include( 'notices/html-notice-install-failed.php' );
            self::remove_notice( 'install_failed' );
        }

        public function install_success()
        {
            include( 'notices/html-notice-install-success.php' );
            self::remove_notice( 'install_success' );
        }

    }

    new Woocommerce_Smart2pay_Admin_Notices();
}
