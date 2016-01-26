<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WC_S2P_Methods_Model extends WC_S2P_Model
{
    public function get_db_methods()
    {
        static $all_methods = array();

        if( !empty( $all_methods ) )
            return $all_methods;

        $list_arr = array();
        $list_arr['order_by'] = 'display_name ASC';

        if( !($all_methods = self::get_list( $list_arr )) )
            $all_methods = array();

        return $all_methods;
    }

    public function get_table_fields()
    {
        return array(
            'id' => array(
                'type' => PHS_params::T_INT,
                'default' => 0,
                'editable' => false,
                'primary' => true,
            ),
            'method_id' => array(
                'type' => PHS_params::T_INT,
                'default' => 0,
                'editable' => true,
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
