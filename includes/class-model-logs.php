<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WC_S2P_Logs extends WC_S2P_Model
{
    protected $order_id = 0;
    protected $environment = '';

    public function order_id( $order_id = null )
    {
        if( $order_id === null )
            return $this->order_id;

        $order_id = intval( $order_id );
        $this->order_id = $order_id;

        return $order_id;
    }

    public function environment( $environment = null )
    {
        if( empty( $this->environment ) )
            $this->environment = WC_S2P_Helper::get_plugin_settings( 'environment' );

        if( $environment === null )
            return $this->environment;

        $environment = trim( $environment );
        $this->environment = $environment;

        return $environment;
    }

    public function log( $params ) // $message, $type = 'info', $file = false, $line = false )
    {
        /** @var wpdb $wpdb */
        global $wpdb;

        if( empty( $params )
         or (!is_array( $params ) and !is_string( $params )) )
            return false;

        if( is_string( $params ) )
            $params = array( 'message' => $params );

        if( empty( $params['type'] ) or !is_string( $params['type'] ) )
            $params['type'] = 'info';

        if( empty( $params['file'] ) or empty( $params['line'] ) )
        {
            $backtrace = debug_backtrace();
            $params['file'] = $backtrace[0]['file'];
            $params['line'] = $backtrace[0]['line'];
        }

        $insert_arr = array();
        $insert_arr['order_id'] = (!empty( $params['order_id'] )?$params['order_id']:$this->order_id());
        $insert_arr['environment'] = (!empty( $params['environment'] )?$params['environment']:$this->environment());
        $insert_arr['log_data'] = $params['message'];
        $insert_arr['log_type'] = $params['type'];
        $insert_arr['log_source_file'] = $params['file'];
        $insert_arr['log_source_file_line'] = $params['line'];
        $insert_arr['log_created'] = date( WC_S2P_Helper::SQL_DATETIME );

        if( !($sql = $this->quick_insert( $insert_arr ))
         or !$wpdb->query( $sql )
         or !$wpdb->insert_id )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_GENERIC, WC_s2p()->__( 'Error saving log details in database.' ) );
            return false;
        }

        $insert_arr['log_id'] = $wpdb->insert_id;
        $insert_arr['<new_in_db>'] = true;

        return $insert_arr;
    }

    public function get_table_fields()
    {
        return array(
            'log_id' => array(
                'type' => PHS_params::T_INT,
                'default' => 0,
                'editable' => false,
                'primary' => true,
            ),
            'order_id' => array(
                'type' => PHS_params::T_INT,
                'default' => 0,
                'editable' => true,
            ),
            'environment' => array(
                'type' => PHS_params::T_NOHTML,
                'default' => '',
                'editable' => true,
            ),
            'log_type' => array(
                'type' => PHS_params::T_NOHTML,
                'default' => '',
                'editable' => true,
            ),
            'log_data' => array(
                'type' => PHS_params::T_ASIS,
                'default' => '',
                'editable' => true,
            ),
            'log_source_file' => array(
                'type' => PHS_params::T_NOHTML,
                'default' => '',
                'editable' => true,
            ),
            'log_source_file_line' => array(
                'type' => PHS_params::T_NOHTML,
                'default' => '',
                'editable' => true,
            ),
            'log_created' => array(
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

        return $wpdb->prefix.'smart2pay_logs';
    }
}
