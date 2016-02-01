<?php

/**
 * Environment stuff
 *
 * @link       http://www.smart2pay.com
 * @since      1.0.0
 *
 * @package    Woocommerce_Smart2pay
 * @subpackage Woocommerce_Smart2pay/includes
 */

/**
 * Environment stuff
 *
 * @package    Woocommerce_Smart2pay
 * @subpackage Woocommerce_Smart2pay/includes
 * @author     Smart2Pay <support@smart2pay.com>
 */
class Woocommerce_Smart2pay_Displaymode
{
    const MODE_LOGO = 'logo', MODE_TEXT = 'text', MODE_BOTH = 'both';

    /**
     * {@inheritdoc}
     */
    public static function toOptionArray()
    {
        return array(
            self::MODE_LOGO => WC_s2p()->__( 'Only Logo' ),
            self::MODE_TEXT => WC_s2p()->__( 'Method Name' ),
            self::MODE_BOTH => WC_s2p()->__( 'Both' ),
       );
    }

    public static function validDisplayMode( $mode )
    {
        $mode = strtolower( trim( $mode ) );
        if( !in_array( $mode, [ self::MODE_LOGO, self::MODE_TEXT, self::MODE_BOTH ] ) )
            return false;

        return $mode;
    }
}
