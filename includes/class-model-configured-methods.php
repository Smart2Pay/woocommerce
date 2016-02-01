<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WC_S2P_Configured_Methods_Model extends WC_S2P_Model
{
    public function get_db_methods( $environment = false )
    {
        static $all_methods = array();

        if( empty( $environment ) )
            $environment = WC_S2P_Helper::get_plugin_settings( 'environment' );

        if( !Woocommerce_Smart2pay_Environment::validEnvironment( $environment ) )
            return array();

        if( !empty( $all_methods ) )
            return $all_methods;

        $list_arr = array();
        $list_arr['fields']['environment'] = $environment;
        $list_arr['order_by'] = 'display_name ASC';

        if( !($all_methods = self::get_list( $list_arr )) )
            $all_methods = array();

        return $all_methods;
    }

    public function get_db_available_methods( $environment = false )
    {
        static $all_methods = array();

        if( empty( $environment ) )
            $environment = WC_S2P_Helper::get_plugin_settings( 'environment' );

        if( !Woocommerce_Smart2pay_Environment::validEnvironment( $environment ) )
            return array();

        if( !empty( $all_methods ) )
            return $all_methods;

        $list_arr = array();
        $list_arr['fields']['enabled'] = 1;
        $list_arr['fields']['environment'] = $environment;
        $list_arr['order_by'] = 'priority DESC';

        if( !($all_methods = self::get_list( $list_arr )) )
            $all_methods = array();

        return $all_methods;
    }

    public function update_method_countries( $method_data, $countries_arr )
    {
        global $wpdb;

        $this->reset_error();

        if( !is_array( $countries_arr ) )
        {
            $this->set_error( self::ERR_GENERIC, WC_s2p()->__( 'Countries codes required.' ) );
            return false;
        }

        if( !($method_arr = $this->data_to_array( $method_data )) )
        {
            $this->set_error( self::ERR_GENERIC, WC_s2p()->__( 'Couldn\'t obtain method details.' ) );
            return false;
        }

        $table_name = $wpdb->prefix.'smart2pay_method_countries';

        $return_arr = array();
        $country_codes = array();
        foreach( $countries_arr as $country_code )
        {
            $country_code = strtoupper( trim( $country_code ) );
            if( empty( $country_code ) or strlen( $country_code ) != 2 )
                continue;

            $country_codes[] = $country_code;

            if( !($db_method_country = $wpdb->get_row( 'SELECT * FROM '.$table_name.' WHERE country_code = \''.$country_code.'\' AND method_id = \''.$method_arr['method_id'].'\' LIMIT 0, 1', ARRAY_A )) )
            {
                $db_method_country = array();
                $db_method_country['country_code'] = $country_code;
                $db_method_country['method_id'] = $method_arr['method_id'];

                if( !($sql = $this->quick_insert( $db_method_country, array( 'table_name' => $table_name ) ))
                 or !$wpdb->query( $sql )
                 or !$wpdb->insert_id )
                {
                    $this->set_error( self::ERR_GENERIC, WC_s2p()->__( 'Error saving method countries.' ) );
                    return false;
                }

                $db_method_country['id'] = $wpdb->insert_id;
                $db_method_country['<new_in_db>'] = true;

                $return_arr[$db_method_country['id']] = $db_method_country;
            }
        }

        if( empty( $country_codes ) )
            $wpdb->query( 'DELETE FROM '.$table_name.' WHERE method_id = \''.$method_arr['method_id'].'\'' );
        else
            $wpdb->query( 'DELETE FROM '.$table_name.' WHERE method_id = \'' . $method_arr['method_id'] . '\'' .
                                    ' AND country_code NOT IN ('.implode( ',', $country_codes ).')' );

        return $return_arr;
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
            'enabled' => array(
                'type' => PHS_params::T_NUMERIC_BOOL,
                'default' => 0,
                'editable' => true,
            ),
            'surcharge_percent' => array(
                'type' => PHS_params::T_FLOAT,
                'type_extra' => array( 'digits' => 2 ),
                'default' => 0,
                'editable' => true,
            ),
            'surcharge_amount' => array(
                'type' => PHS_params::T_FLOAT,
                'type_extra' => array( 'digits' => 2 ),
                'default' => 0,
                'editable' => true,
            ),
            'surcharge_currency' => array(
                'type' => PHS_params::T_ALPHANUM,
                'default' => '',
                'editable' => true,
            ),
            'priority' => array(
                'type' => PHS_params::T_INT,
                'default' => 0,
                'editable' => true,
            ),
            'last_update' => array(
                'type' => PHS_params::T_DATE,
                'type_extra' => array( 'format' => WC_S2P_Helper::SQL_DATETIME ),
                'default' => WC_S2P_Helper::EMPTY_DATETIME,
                'editable' => true,
            ),
            'configured' => array(
                'type' => PHS_params::T_DATE,
                'type_extra' => array( 'format' => WC_S2P_Helper::SQL_DATETIME ),
                'default' => WC_S2P_Helper::EMPTY_DATETIME,
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
