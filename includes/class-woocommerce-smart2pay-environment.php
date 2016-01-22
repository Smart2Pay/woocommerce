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
class Woocommerce_Smart2pay_Environment
{
    const ENV_DEMO = 'demo', ENV_TEST = 'test', ENV_LIVE = 'live';

    /**
     * {@inheritdoc}
     */
    public static function toOptionArray()
    {
        /** @var Woocommerce_Smart2pay $wc_s2p */
        global $wc_s2p;

        return array(
            self::ENV_DEMO => $wc_s2p->__( 'Demo' ),
            self::ENV_TEST => $wc_s2p->__( 'Test' ),
            self::ENV_LIVE => $wc_s2p->__( 'Live' ),
       );
    }

    public static function validEnvironment( $env )
    {
        $env = strtolower( trim( $env ) );
        if( !in_array( $env, [ self::ENV_DEMO, self::ENV_TEST, self::ENV_LIVE ] ) )
            return false;

        return $env;
    }
}
