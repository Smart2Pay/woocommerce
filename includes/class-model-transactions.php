<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WC_S2P_Transactions_Model extends WC_S2P_Model
{
    /**
     * @param array $transaction_arr Array with details of transaction
     *
     * @return array|bool Array with details saved in database
     */
    public function save_transaction( $transaction_arr )
    {
        $this->reset_error();

        if( empty( $transaction_arr ) or !is_array( $transaction_arr )
         or empty( $transaction_arr['order_id'] ) )
        {
            $this->set_error( self::ERR_GENERIC, WC_s2p()->__( 'Bad transaction parameters.' ) );
            return false;
        }


        $check_arr = array();
        $check_arr['order_id'] = $transaction_arr['order_id'];

        if( ($existing_transaction_arr = $this->get_details_fields( $check_arr )) )
        {
            // we already have this method in database... update it...
            $edit_arr = array();
            $edit_arr['fields'] = $transaction_arr;

            if( !($saved_transaction = $this->edit( $existing_transaction_arr, $edit_arr )) )
            {
                if( !$this->has_error() )
                    $this->set_error( self::ERR_GENERIC, WC_s2p()->__( 'Error saving configured method details in database.' ) );
                return false;
            }

        } else
        {
            $insert_arr = array();
            $insert_arr['fields'] = $transaction_arr;

            if( !($saved_transaction = $this->insert( $insert_arr )) )
            {
                if( !$this->has_error() )
                    $this->set_error( self::ERR_GENERIC, WC_s2p()->__( 'Error adding configured method details in database.' ) );
                return false;
            }
        }

        return $saved_transaction;
    }

    /**
     * Overwrite this method to alter parameters sent to add method
     *
     * @param array $params Parameters passed to add method
     *
     * @return array|bool Changed add parameters. If returns false will stop insertion.
     */
    public function insert_check_parameters( $params )
    {
        if( empty( $params ) or !is_array( $params ) )
            return $params;

        if( !empty( $params['environment'] ) and !Woocommerce_Smart2pay_Environment::validEnvironment( $params['environment'] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, WC_s2p()->__( 'Invalid environment for transaction.' ) );
            return false;
        }

        // Make sure if add is called instead of edit with new_extra_data as parameters, just use it
        if( !empty( $params['new_extra_data'] ) )
        {
            $params['extra_data'] = $params['new_extra_data'];
            unset( $params['new_extra_data'] );
        }

        $extra_data_str = '';
        if( !empty( $params['extra_data'] ) )
        {
            if( is_array( $params['extra_data'] ) )
                $extra_data_str = WC_S2P_Helper::to_string( $params['extra_data'] );
            elseif( is_string( $params['extra_data'] ) )
                $extra_data_str = WC_S2P_Helper::to_string( WC_S2P_Helper::parse_string( $params['extra_data'] ) );
        }

        $params['extra_data'] = $extra_data_str;

        return $params;
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
        $insert_arr['created'] = $cdate;

        return $insert_arr;
    }

    /**
     * Overwrite this method to alter parameters sent to edit method
     *
     * @param array $existing_arr Existing data from database
     * @param array $params Parameters passed to edit method
     *
     * @return array|bool Changed edit parameters. If returns false will stop insertion.
     */
    public function edit_check_parameters( $existing_arr, $params )
    {
        if( empty( $params ) or !is_array( $params ) )
            return $params;

        if( !empty( $params['fields']['environment'] ) and !Woocommerce_Smart2pay_Environment::validEnvironment( $params['fields']['environment'] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, WC_s2p()->__( 'Invalid environment for transaction.' ) );
            return false;
        }

        $extra_data_str = false;
        if( !empty( $params['new_extra_data'] ) )
        {
            if( is_array( $params['new_extra_data'] ) )
                $extra_data_str = WC_S2P_Helper::to_string( $params['new_extra_data'] );
            elseif( is_string( $params['new_extra_data'] ) )
                $extra_data_str = WC_S2P_Helper::to_string( WC_S2P_Helper::parse_string( $params['new_extra_data'] ) );
        } elseif( !empty( $params['fields']['extra_data'] ) )
            $extra_data_str = WC_S2P_Helper::to_string( WC_S2P_Helper::update_line_params( $existing_arr['extra_data'], $params['fields']['extra_data'] ) );

        if( $extra_data_str !== false )
            $params['fields']['extra_data'] = $extra_data_str;

        return $params;
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
            'payment_id' => array(
                'type' => PHS_params::T_INT,
                'default' => 0,
                'editable' => true,
            ),
            'order_id' => array(
                'type' => PHS_params::T_INT,
                'default' => 0,
                'editable' => false,
            ),
            'site_id' => array(
                'type' => PHS_params::T_INT,
                'default' => 0,
                'editable' => true,
            ),
            'environment' => array(
                'type' => PHS_params::T_NOHTML,
                'default' => '',
                'editable' => true,
            ),
            'extra_data' => array(
                'type' => PHS_params::T_NOHTML,
                'default' => '',
                'editable' => true,
            ),
            'amount' => array(
                'type' => PHS_params::T_FLOAT,
                'type_extra' => array( 'digits' => 2 ),
                'default' => 0,
                'editable' => true,
            ),
            'currency' => array(
                'type' => PHS_params::T_ALPHANUM,
                'default' => '',
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
            'surcharge_percent' => array(
                'type' => PHS_params::T_FLOAT,
                'type_extra' => array( 'digits' => 2 ),
                'default' => 0,
                'editable' => true,
            ),
            'surcharge_amount_percent' => array(
                'type' => PHS_params::T_FLOAT,
                'type_extra' => array( 'digits' => 2 ),
                'default' => 0,
                'editable' => true,
            ),
            'surcharge_total_amount' => array(
                'type' => PHS_params::T_FLOAT,
                'type_extra' => array( 'digits' => 2 ),
                'default' => 0,
                'editable' => true,
            ),
            'payment_status' => array(
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
            'created' => array(
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

        return $wpdb->prefix.'smart2pay_transactions';
    }
}
