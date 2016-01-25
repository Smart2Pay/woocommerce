<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
if( !class_exists( 'Woocommerce_Smart2pay_Admin_Notices', false ) )
{
    class Woocommerce_Smart2pay_Admin_Notices extends WC_Admin_Notices
    {
        /**
         * Array of notices - name => callback.
         * @var array
         */
        private $core_notices = array(
            'install_failed' => 'install_failed',
            'install_success' => 'install_success',
        );

        public function __construct()
        {
            parent::__construct();

            if( ($later_notices = Woocommerce_Smart2pay_Admin_Notices_Later::get_notices()) and is_array( $later_notices ) )
            {
                foreach( $later_notices as $notice => $junk )
                    self::add_notice( $notice );

                Woocommerce_Smart2pay_Admin_Notices_Later::reset_notices();
            }

            Woocommerce_Smart2pay_Admin_Notices_Later::we_can_notice_now();
        }

        /**
         * Add notices + styles if needed.
         */
        public function add_notices()
        {
            $notices = get_option( 'woocommerce_admin_notices', array() );

            if ( $notices )
            {
                wp_enqueue_style( 'woocommerce-activation', plugins_url(  '/assets/css/activation.css', WC_PLUGIN_FILE ) );
                foreach ( $notices as $notice )
                {
                    if ( ! empty( $this->core_notices[ $notice ] ) )// && apply_filters( 'woocommerce_show_admin_notice', true, $notice ) )
                    {
                        add_action( 'admin_notices', array( $this, $this->core_notices[ $notice ] ) );
                    }
                }
            }
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
