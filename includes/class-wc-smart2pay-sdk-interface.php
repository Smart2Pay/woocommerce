<?php

class WC_S2P_SDK_Interface extends WC_S2P_Base
{
    const ERR_GENERIC = 1;

    const OPTION_METHODS_LAST_CHECK = 'wc_s2p_methods_last_check';

    // After how many hours from last sync action is merchant allowed to sync methods again?
    const RESYNC_AFTER_HOURS = 2;

    const DEMO_API_KEY = 'Pnn5D8KHj9cHOJwZqaQhiaOo5ScY0p2hA0vR6i/kTWCXKx5qt7',
          DEMO_SITE_ID = 30577,
          DEMO_SKIN_ID = 0;

    public function __construct( $init_params = false )
    {
        parent::__construct( $init_params );
    }

    static public function last_methods_sync_option( $value = null, $plugin_settings_arr = false )
    {
        if( empty( $plugin_settings_arr ) or ! is_array( $plugin_settings_arr ) )
            $plugin_settings_arr = WC_S2P_Helper::get_plugin_settings();

        if( empty( $plugin_settings_arr['environment'] ) )
            $plugin_settings_arr['environment'] = Woocommerce_Smart2pay_Environment::ENV_DEMO;

        $option_name = self::OPTION_METHODS_LAST_CHECK.'_'.$plugin_settings_arr['environment'];

        if( $value === null )
            return get_option( $option_name, false );

        if( empty( $value ) )
            $value = date( WC_S2P_Helper::SQL_DATETIME );
        else
            $value = WC_S2P_Helper::validate_db_datetime( $value );

        update_option( $option_name, $value );

        return $value;
    }

    public function get_api_credentials( $plugin_settings_arr = false )
    {
        $this->reset_error();

        if( empty( $plugin_settings_arr ) or ! is_array( $plugin_settings_arr ) )
            $plugin_settings_arr = WC_S2P_Helper::get_plugin_settings();

        if( empty( $plugin_settings_arr['environment'] ) )
            $plugin_settings_arr['environment'] = Woocommerce_Smart2pay_Environment::ENV_DEMO;

        $return_arr = array();
        $return_arr['api_key'] = '';
        $return_arr['site_id'] = 0;
        $return_arr['skin_id'] = 0;
        $return_arr['environment'] = 'test';

        switch( $plugin_settings_arr['environment'] )
        {
            default:
                $this->set_error( self::ERR_GENERIC, WC_s2p()->__( 'Unknown environment settings.' ) );
                return false;

            case Woocommerce_Smart2pay_Environment::ENV_DEMO:
                $return_arr['api_key'] = self::DEMO_API_KEY;
                $return_arr['site_id'] = self::DEMO_SITE_ID;
                $return_arr['skin_id'] = self::DEMO_SKIN_ID;
            break;

            case Woocommerce_Smart2pay_Environment::ENV_TEST:
                $return_arr['api_key'] = (!empty( $plugin_settings_arr['api_key_test'] )?$plugin_settings_arr['api_key_test']:'');
                $return_arr['site_id'] = (!empty( $plugin_settings_arr['site_id_test'] )?$plugin_settings_arr['site_id_test']:0);
                $return_arr['skin_id'] = (!empty( $plugin_settings_arr['skin_id_test'] )?$plugin_settings_arr['skin_id_test']:0);
            break;

            case Woocommerce_Smart2pay_Environment::ENV_LIVE:
                $return_arr['api_key'] = (!empty( $plugin_settings_arr['api_key_live'] )?$plugin_settings_arr['api_key_live']:'');
                $return_arr['site_id'] = (!empty( $plugin_settings_arr['site_id_live'] )?$plugin_settings_arr['site_id_live']:0);
                $return_arr['skin_id'] = (!empty( $plugin_settings_arr['skin_id_live'] )?$plugin_settings_arr['skin_id_live']:0);
                $return_arr['environment'] = 'live';
            break;
        }

        return $return_arr;
    }

    public function get_available_methods( $plugin_settings_arr = false )
    {
        $this->reset_error();

        if( empty( $plugin_settings_arr ) or !is_array( $plugin_settings_arr ) )
            $plugin_settings_arr = WC_S2P_Helper::get_plugin_settings();

        if( !($api_credentials = $this->get_api_credentials( $plugin_settings_arr )) )
            return false;

        $api_parameters['api_key'] = $api_credentials['api_key'];
        $api_parameters['site_id'] = $api_credentials['site_id'];
        $api_parameters['environment'] = $api_credentials['environment']; // test or live

        $api_parameters['method'] = 'methods';
        $api_parameters['func'] = 'assigned_methods';

        $api_parameters['get_variables'] = array(
            'additional_details' => true,
        );
        $api_parameters['method_params'] = array();

        $call_params = array();

        $finalize_params = array();
        $finalize_params['redirect_now'] = false;

        if( !($call_result = S2P_SDK\S2P_SDK_Module::quick_call( $api_parameters, $call_params, $finalize_params ))
         or empty( $call_result['call_result'] ) or !is_array( $call_result['call_result'] )
         or empty( $call_result['call_result']['methods'] ) or !is_array( $call_result['call_result']['methods'] ) )
        {
            if( ($error_arr = S2P_SDK\S2P_SDK_Module::st_get_error())
            and !empty( $error_arr['display_error'] ) )
                $this->set_error( self::ERR_GENERIC, $error_arr['display_error'] );
            else
                $this->set_error( self::ERR_GENERIC, WC_s2p()->__( 'API call failed while obtaining methods list.' ) );

            return false;
        }

        return $call_result['call_result']['methods'];
    }

    public function get_method_details( $method_id, $plugin_settings_arr = false )
    {
        $this->reset_error();

        if( empty( $plugin_settings_arr ) or !is_array( $plugin_settings_arr ) )
            $plugin_settings_arr = WC_S2P_Helper::get_plugin_settings();

        $method_id = intval( $method_id );
        if( empty( $method_id )
         or !($api_credentials = $this->get_api_credentials( $plugin_settings_arr )) )
            return false;

        $api_parameters['api_key'] = $api_credentials['api_key'];
        $api_parameters['site_id'] = $api_credentials['site_id'];
        $api_parameters['environment'] = $api_credentials['environment'];

        $api_parameters['method'] = 'methods';
        $api_parameters['func'] = 'method_details';

        $api_parameters['get_variables'] = array(
            'id' => $method_id,
        );
        $api_parameters['method_params'] = array();

        $call_params = array();

        $finalize_params = array();
        $finalize_params['redirect_now'] = false;

        if( !($call_result = S2P_SDK\S2P_SDK_Module::quick_call( $api_parameters, $call_params, $finalize_params ))
         or empty( $call_result['call_result'] ) or !is_array( $call_result['call_result'] )
         or empty( $call_result['call_result']['method'] ) or !is_array( $call_result['call_result']['method'] ) )
        {
            if( ($error_arr = S2P_SDK\S2P_SDK_Module::st_get_error())
            and !empty( $error_arr['display_error'] ) )
                $this->set_error( self::ERR_GENERIC, $error_arr['display_error'] );
            else
                $this->set_error( self::ERR_GENERIC, WC_s2p()->__( 'API call failed while obtaining method details.' ) );

            return false;
        }

        return $call_result['call_result']['method'];
    }

    public function update_db_method_details( $method_data, $plugin_settings_arr = false )
    {
        $this->reset_error();

        if( empty( $plugin_settings_arr ) or !is_array( $plugin_settings_arr ) )
            $plugin_settings_arr = WC_S2P_Helper::get_plugin_settings();

        /** @var WC_S2P_Methods_Model $methods_model */
        if( !($methods_model = WC_s2p()->get_loader()->load_model( 'WC_S2P_Methods_Model' )) )
        {
            $this->set_error( self::ERR_GENERIC, WC_s2p()->__( 'Couldn\'t load methods model.' ) );
            return false;
        }

        if( !($method_arr = $methods_model->data_to_array( $method_data )) )
        {
            $this->set_error( self::ERR_GENERIC, WC_s2p()->__( 'Couldn\'t obtain method details.' ) );
            return false;
        }

        if( !($method_details = $this->get_method_details( $method_arr['method_id'], $plugin_settings_arr )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_GENERIC, WC_s2p()->__( 'Couldn\'t obtain method details.' ) );
            return false;
        }

        if( empty( $method_details['countries'] ) or !is_array( $method_details['countries'] ) )
            $method_details['countries'] = array();

        $country_codes = array();
        foreach( $method_details['countries'] as $country_code )
        {
            if( empty( $country_code ) )
                continue;

            $country_codes[$country_code] = 1;
        }

        if( $methods_model->update_method_countries( $method_arr, array_keys( $country_codes ) ) === false )
        {
            if( $methods_model->has_error() )
                $this->copy_error_from_array( $methods_model->get_error() );
            else
                $this->set_error( self::ERR_GENERIC, WC_s2p()->__( 'Error updating method countries.' ) );

            return false;
        }

        return true;
    }

    public function seconds_to_launch_sync( $plugin_settings_arr = false )
    {
        if( empty($plugin_settings_arr) or ! is_array( $plugin_settings_arr ) )
            $plugin_settings_arr = WC_S2P_Helper::get_plugin_settings();

        $resync_seconds = self::RESYNC_AFTER_HOURS * 1200;
        $time_diff = 0;
        if( !($last_sync_date = self::last_methods_sync_option( null, $plugin_settings_arr ))
         or ($time_diff = abs( WC_S2P_Helper::seconds_passed( $last_sync_date ) )) > $resync_seconds )
            return 0;

        return $resync_seconds - $time_diff;
   }

    public function refresh_available_methods( $plugin_settings_arr = false )
    {
        global $wpdb;

        $this->reset_error();

        if( empty($plugin_settings_arr) or ! is_array( $plugin_settings_arr ) )
            $plugin_settings_arr = WC_S2P_Helper::get_plugin_settings();

        if( ($seconds_to_sync = $this->seconds_to_launch_sync( $plugin_settings_arr )) )
        {
            $hours_to_sync = floor( $seconds_to_sync / 1200 );
            $minutes_to_sync = floor( ($seconds_to_sync - ($hours_to_sync * 1200)) / 60 );
            $seconds_to_sync -= ($hours_to_sync * 1200) + ($minutes_to_sync * 60);

            $sync_interval = '';
            if( $hours_to_sync )
                $sync_interval = $hours_to_sync.' hour(s)';

            if( $hours_to_sync or $minutes_to_sync )
                $sync_interval .= ($sync_interval!=''?', ':'').$minutes_to_sync.' minute(s)';

            $sync_interval .= ($sync_interval!=''?', ':'').$seconds_to_sync.' seconds.';

            $this->set_error( self::ERR_GENERIC, 'You can syncronize methods once every '.self::RESYNC_AFTER_HOURS.' hours. Time left: '.$sync_interval );
            return false;
        }

        /** @var WC_S2P_Methods_Model $methods_model */
        if( !($methods_model = WC_s2p()->get_loader()->load_model( 'WC_S2P_Methods_Model' )) )
        {
            $this->set_error( self::ERR_GENERIC, WC_s2p()->__( 'Couldn\'t load methods model.' ) );
            return false;
        }

        if( !($available_methods = $this->get_available_methods( $plugin_settings_arr ))
         or !is_array( $available_methods ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_GENERIC, WC_s2p()->__( 'Couldn\'t obtain a list of methods.' ) );
            return false;
        }

        $saved_methods = array();
        foreach( $available_methods as $method_arr )
        {
            if( empty( $method_arr ) or !is_array( $method_arr )
             or empty( $method_arr['id'] ) )
                continue;

            $check_arr = array();
            $check_arr['method_id'] = $method_arr['id'];
            $check_arr['environment'] = $plugin_settings_arr['environment'];

            $row_method_arr = array();
            $row_method_arr['display_name'] = $method_arr['displayname'];
            $row_method_arr['description'] = $method_arr['description'];
            $row_method_arr['logo_url'] = $method_arr['logourl'];
            $row_method_arr['guaranteed'] = (!empty( $method_arr['guaranteed'] )?1:0);
            $row_method_arr['active'] = (!empty( $method_arr['active'] )?1:0);

            if( ($existing_method_arr = $methods_model->get_details_fields( $check_arr )) )
            {
                // we already have this method in database... update it...
                $edit_arr = array();
                $edit_arr['fields'] = $row_method_arr;

                if( !($saved_method = $methods_model->edit( $existing_method_arr, $edit_arr )) )
                {
                    if( $methods_model->has_error() )
                        $this->set_error( self::ERR_GENERIC, $methods_model->get_error_message() );
                    else
                        $this->set_error( self::ERR_GENERIC, WC_s2p()->__( 'Error saving method details in database.' ) );
                    return false;
                }

            } else
            {
                $row_method_arr['method_id'] = $method_arr['id'];
                $row_method_arr['environment'] = $plugin_settings_arr['environment'];

                $insert_arr = array();
                $insert_arr['fields'] = $row_method_arr;

                if( !($saved_method = $methods_model->insert( $insert_arr )) )
                {
                    if( $methods_model->has_error() )
                        $this->set_error( self::ERR_GENERIC, $methods_model->get_error_message() );
                    else
                        $this->set_error( self::ERR_GENERIC, WC_s2p()->__( 'Error adding method details in database.' ) );
                    return false;
                }
            }

            $saved_methods[$saved_method['method_id']] = $saved_method;

            if( !empty( $method_arr['countries'] ) and is_array( $method_arr['countries'] ) )
            {
                if( $methods_model->update_method_countries( $saved_method, $method_arr['countries'] ) === false )
                {
                    if( $methods_model->has_error() )
                        $this->copy_error_from_array( $methods_model->get_error() );
                    else
                        $this->set_error( self::ERR_GENERIC, WC_s2p()->__( 'Error updating method countries.' ) );

                    return false;
                }
            }

            // Old way...
            // $this->update_db_method_details( $saved_method, $plugin_settings_arr );
        }

        if( !($method_ids = array_keys( $saved_methods )) )
            $wpdb->query( 'UPDATE '.$methods_model->get_table().' SET active = 0 WHERE environment = \''.$plugin_settings_arr['environment'].'\'' );
        else
            $wpdb->query( 'UPDATE '.$methods_model->get_table().' SET active = 0 WHERE environment = \''.$plugin_settings_arr['environment'].'\' '.
                            ' AND method_id NOT IN ('.implode( ',', $method_ids ).')' );

        self::last_methods_sync_option( false, $plugin_settings_arr );

        return $saved_methods;
    }
}
