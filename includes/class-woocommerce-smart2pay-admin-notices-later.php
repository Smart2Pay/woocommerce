<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Woocommerce_Smart2pay_Admin_Notices_Later
{
    private static $notices = array();
    private static $we_can_notice_now = false;

    public static function add_notice( $notice )
    {
        if( ! is_scalar( $notice ) )
            return false;

        if( self::$we_can_notice_now )
        {
            Woocommerce_Smart2pay_Admin_Notices::add_notice( $notice );
        } else
        {
            self::$notices[$notice] = 1;
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
