<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WC_S2P_Methods_Model extends WC_S2P_Model
{
    public function get_table_fields()
    {
        return array(
            'method_id' => array(
                'type' => PHS_params::T_INT,
                'default' => 0,
                'editable' => false,
                'primary' => true,
            ),
            'environment' => array(
                'type' => PHS_params::T_NOHTML,
                'default' => '',
                'editable' => true,
            ),
            'display_name' => array(
                'type' => PHS_params::T_NOHTML,
                'default' => '',
                'editable' => true,
            ),
            'description' => array(
                'type' => PHS_params::T_ASIS,
                'default' => '',
                'editable' => true,
            ),
            'logo_url' => array(
                'type' => PHS_params::T_NOHTML,
                'default' => '',
                'editable' => true,
            ),
            'guaranteed' => array(
                'type' => PHS_params::T_NUMERIC_BOOL,
                'default' => 0,
                'editable' => true,
            ),
            'active' => array(
                'type' => PHS_params::T_NUMERIC_BOOL,
                'default' => 0,
                'editable' => true,
            ),
        );
    }

    public function get_table()
    {
        global $wpdb;

        return $wpdb->prefix.'smart2pay_method';
    }
}
