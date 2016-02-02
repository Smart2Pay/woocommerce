<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WC_S2P_Configured_Methods_Model extends WC_S2P_Model
{
    static $available_db_methods = array();
    static $all_db_methods = array();

    public function get_db_methods( $environment = false )
    {
        if( empty( $environment ) )
            $environment = WC_S2P_Helper::get_plugin_settings( 'environment' );

        if( !Woocommerce_Smart2pay_Environment::validEnvironment( $environment ) )
            return array();

        if( !empty( self::$all_db_methods[$environment] ) )
            return self::$all_db_methods[$environment];

        $list_arr = array();
        $list_arr['fields']['environment'] = $environment;
        $list_arr['order_by'] = 'priority DESC';

        if( !($db_methods = $this->get_list( $list_arr )) )
            $db_methods = array();

        self::$all_db_methods[$environment] = array();
        foreach( $db_methods as $db_id => $configured_method_arr )
        {
            if( empty( $configured_method_arr['method_id'] ) )
                continue;

            self::$all_db_methods[$environment][$configured_method_arr['method_id']] = $configured_method_arr;
        }

        return self::$all_db_methods[$environment];
    }

    public function get_db_available_methods( $environment = false )
    {
        if( empty( $environment ) )
            $environment = WC_S2P_Helper::get_plugin_settings( 'environment' );

        if( !Woocommerce_Smart2pay_Environment::validEnvironment( $environment ) )
            return array();

        if( !empty( self::$available_db_methods[$environment] ) )
            return self::$available_db_methods[$environment];

        $list_arr = array();
        $list_arr['fields']['enabled'] = 1;
        $list_arr['fields']['environment'] = $environment;
        $list_arr['order_by'] = 'priority DESC';

        if( !($db_methods = $this->get_list( $list_arr )) )
            $db_methods = array();

        self::$available_db_methods[$environment] = array();
        foreach( $db_methods as $db_id => $configured_method_arr )
        {
            if( empty( $configured_method_arr['method_id'] ) )
                continue;

            self::$available_db_methods[$environment][$configured_method_arr['method_id']] = $configured_method_arr;
        }

        return self::$available_db_methods[$environment];
    }

    /**
     * @param array $configured_methods_arr Array of arrays containing method settings
     * @param bool $environment For what environment we save the settings
     *
     * @return array|bool Array of saved method settings or false if an error occured
     */
    public function save_configured_methods( $configured_methods_arr, $environment = false )
    {
        global $wpdb;

        $this->reset_error();

        if( empty( $environment ) )
            $environment = WC_S2P_Helper::get_plugin_settings( 'environment' );

        if( !Woocommerce_Smart2pay_Environment::validEnvironment( $environment ) )
        {
            $this->set_error( self::ERR_GENERIC, WC_s2p()->__( 'Invalid environment.' ) );
            return false;
        }

        // invalidate caches...
        self::$available_db_methods[$environment] = array();
        self::$all_db_methods[$environment] = array();

        if( empty( $configured_methods_arr ) or !is_array( $configured_methods_arr ) )
            return ($wpdb->query( 'DELETE FROM '.$this->get_table().' WHERE environment = \''.$environment.'\'' )?true:false);

        $saved_methods = array();
        $wc_currency = get_woocommerce_currency();
        foreach( $configured_methods_arr as $method_configuration_arr )
        {
            if( empty( $method_configuration_arr ) or !is_array( $method_configuration_arr )
             or empty( $method_configuration_arr['method_id'] ) )
                continue;

            $check_arr = array();
            $check_arr['method_id'] = $method_configuration_arr['method_id'];
            $check_arr['environment'] = $environment;

            $row_method_arr = array();
            $row_method_arr['enabled'] = (!empty( $method_configuration_arr['enabled'] )?1:0);
            $row_method_arr['surcharge_percent'] = (!empty( $method_configuration_arr['surcharge_percent'] )?$method_configuration_arr['surcharge_percent']:0);
            $row_method_arr['surcharge_amount'] = (!empty( $method_configuration_arr['surcharge_amount'] )?$method_configuration_arr['surcharge_amount']:0);
            $row_method_arr['surcharge_currency'] = (!empty( $method_configuration_arr['surcharge_currency'] )?$method_configuration_arr['surcharge_currency']:$wc_currency);
            $row_method_arr['priority'] = (!empty( $method_configuration_arr['priority'] )?$method_configuration_arr['priority']:0);

            if( ($existing_method_arr = $this->get_details_fields( $check_arr )) )
            {
                // we already have this method in database... update it...
                $edit_arr = array();
                $edit_arr['fields'] = $row_method_arr;

                if( !($saved_method = $this->edit( $existing_method_arr, $edit_arr )) )
                {
                    if( !$this->has_error() )
                        $this->set_error( self::ERR_GENERIC, WC_s2p()->__( 'Error saving configured method details in database.' ) );
                    return false;
                }

            } else
            {
                $row_method_arr['method_id'] = $method_configuration_arr['method_id'];
                $row_method_arr['environment'] = $environment;

                $insert_arr = array();
                $insert_arr['fields'] = $row_method_arr;

                if( !($saved_method = $this->insert( $insert_arr )) )
                {
                    if( !$this->has_error() )
                        $this->set_error( self::ERR_GENERIC, WC_s2p()->__( 'Error adding configured method details in database.' ) );
                    return false;
                }
            }

            $saved_methods[$saved_method['id']] = $saved_method;
        }

        if( !($method_ids = array_keys( $saved_methods )) )
            $wpdb->query( 'DELETE FROM '.$this->get_table().' WHERE environment = \''.$environment.'\'' );
        else
            $wpdb->query( 'DELETE FROM '.$this->get_table().' WHERE environment = \''.$environment.'\' '.
                          ' AND id NOT IN ('.implode( ',', $method_ids ).')' );

        return $saved_methods;
    }

    /**
     * Overwrite this method if you want to change data array right before inserting it to database...
     *
     * @param array $insert_arr
     * @param array $params
     *
     * @return array|bool Changed array to be saved in database. If returns false will stop insertion.
     */
    public function insert_before( $insert_arr, $params )
    {
        $cdate = date( WC_S2P_Helper::SQL_DATETIME );

        $insert_arr['last_update'] = $cdate;
        $insert_arr['configured'] = $cdate;

        return $insert_arr;
    }

    /**
     * @param array $edit_arr What fields are to be changed in database
     * @param array $changes_arr "Old" values
     * @param array $params Edit parameters
     *
     * @return array New edit parameters (fields to update which are conditional on other fields - eg. status_date which depends on status field change)
     */
    public function edit_trace_changes( $edit_arr, $changes_arr, $params )
    {
        $edit_arr['last_update'] = date( WC_S2P_Helper::SQL_DATETIME );

        return $edit_arr;
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

        return $wpdb->prefix.'smart2pay_method_settings';
    }
}
