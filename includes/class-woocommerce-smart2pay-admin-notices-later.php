<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Woocommerce_Smart2pay_Admin_Notices_Later
{
    private static $notices = array();
    private static $we_can_notice_now = false;

    public static function add_notice( $notice, $notice_params = false, $bulk_notifications = false )
    {
        if( !is_scalar( $notice ) )
            return false;

        if( self::$we_can_notice_now )
        {
            Woocommerce_Smart2pay_Admin_Notices::add_notice( $notice, $notice_params, $bulk_notifications );
        } else
        {
            if( $notice == Woocommerce_Smart2pay_Admin_Notices::CUSTOM_NOTICE )
            {
                if( empty( self::$notices[$notice] ) )
                    self::$notices[$notice] = array();

                if( empty( $bulk_notifications ) )
                    self::$notices[$notice][] = $notice_params;

                else
                {
                    if( !is_array( $notice_params ) )
                        return false;

                    if( empty( self::$notices[$notice] ) )
                        self::$notices[$notice] = $notice_params;

                    else
                    {
                        foreach( $notice_params as $notice_arr )
                            self::$notices[$notice][] = $notice_arr;
                    }
                }
            } else
                self::$notices[$notice] = $notice_params;
        }

        return true;
    }

    public static function we_can_notice_now()
    {
        self::$we_can_notice_now = true;
    }

    public static function get_notices()
    {
        return self::$notices;
    }

    public static function reset_notices()
    {
        self::$notices = array();
    }

}
