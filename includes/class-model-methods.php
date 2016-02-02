<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WC_S2P_Methods_Model extends WC_S2P_Model
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
        $list_arr['order_by'] = 'display_name ASC';

        if( !($db_methods = $this->get_list( $list_arr )) )
            $db_methods = array();

        self::$all_db_methods[$environment] = array();
        foreach( $db_methods as $db_id => $method_arr )
        {
            if( empty( $method_arr['method_id'] ) )
                continue;

            self::$all_db_methods[$environment][$method_arr['method_id']] = $method_arr;
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
        $list_arr['fields']['active'] = 1;
        $list_arr['fields']['environment'] = $environment;
        $list_arr['order_by'] = 'display_name ASC';

        if( !($db_methods = $this->get_list( $list_arr )) )
            $db_methods = array();

        self::$available_db_methods[$environment] = array();
        foreach( $db_methods as $db_id => $method_arr )
        {
            if( empty( $method_arr['method_id'] ) )
                continue;

            self::$available_db_methods[$environment][$method_arr['method_id']] = $method_arr;
        }

        return self::$available_db_methods[$environment];
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
                    if( !$this->has_error() )
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

    public function get_countries_per_method()
    {
        global $wpdb;

        $this->reset_error();

        $table_name = $wpdb->prefix.'smart2pay_method_countries';
        $table_index = 'id';

        $list_arr = array();
        $list_arr['table_name'] = $table_name;
        $list_arr['table_index'] = $table_index;
        // make sure we don't limit rows to 1000 (default get_list() value
        $list_arr['enregs_no'] = 1000000;

        $list_arr['db_fields'] = $table_name.'.*'.
                                ', '.$wpdb->prefix.'smart2pay_country.name as country_name ';
        $list_arr['join_sql'] = ' LEFT JOIN '.$wpdb->prefix.'smart2pay_country ON `'.$table_name.'`.country_code = '.$wpdb->prefix.'smart2pay_country.code ';

        $list_arr['order_by'] = $wpdb->prefix.'smart2pay_country.name ASC';

        if( !($country_methods_arr = $this->get_list( $list_arr ))
         or !is_array( $country_methods_arr ) )
            return array();

        $return_arr = array();
        foreach( $country_methods_arr as $linkage_arr )
        {
            if( empty( $linkage_arr['country_code'] )
             or empty( $linkage_arr['method_id'] )
             or strtoupper( $linkage_arr['country_code'] ) == 'AA' )
                continue;

            if( empty( $return_arr[$linkage_arr['method_id']] ) )
                $return_arr[$linkage_arr['method_id']] = array();

            $return_arr[$linkage_arr['method_id']][$linkage_arr['country_code']] = $linkage_arr['country_name'];
        }

        return $return_arr;
    }

    public function get_available_country_methods( $country, $environment = false )
    {
        global $wpdb;

        $this->reset_error();

        if( empty( $environment ) )
            $environment = WC_S2P_Helper::get_plugin_settings( 'environment' );

        if( empty( $country ) or strlen( $country ) != 2
         or !Woocommerce_Smart2pay_Environment::validEnvironment( $environment ) )
            return array();

        $table_name = $wpdb->prefix.'smart2pay_method_countries';
        $table_index = 'id';

        $list_arr = array();
        $list_arr['table_name'] = $table_name;
        $list_arr['table_index'] = $table_index;

        $list_arr['fields']['country_code'] = strtoupper( $country );

        if( !($country_methods_arr = $this->get_list( $list_arr ))
         or !is_array( $country_methods_arr ) )
            return array();

        $method_ids = array();
        foreach( $country_methods_arr as $linkage_arr )
        {
            if( empty( $linkage_arr['method_id'] ) )
                continue;

            $method_ids[] = $linkage_arr['method_id'];
        }

        if( empty( $method_ids ) )
            return array();

        $table_name = $wpdb->prefix.'smart2pay_method_settings';
        $table_index = 'id';

        $list_arr = array();
        $list_arr['table_name'] = $table_name;
        $list_arr['table_index'] = $table_index;

        $list_arr['fields'][$table_name.'.enabled'] = 1;
        $list_arr['fields'][$wpdb->prefix.'smart2pay_method.active'] = 1;
        $list_arr['fields'][$table_name.'.environment'] = $environment;
        $list_arr['fields'][$table_name.'.method_id'] = array( 'check' => 'IN', 'value' => '('.implode( ',', $method_ids ).')' );

        $list_arr['db_fields'] = $table_name.'.*'.
                                 ', '.$wpdb->prefix.'smart2pay_method.display_name as display_name '.
                                 ', '.$wpdb->prefix.'smart2pay_method.description as description '.
                                 ', '.$wpdb->prefix.'smart2pay_method.logo_url as logo_url ';

        $list_arr['join_sql'] = ' LEFT JOIN '.$wpdb->prefix.'smart2pay_method ON `'.$table_name.'`.method_id = '.$wpdb->prefix.'smart2pay_method.method_id ';

        $list_arr['order_by'] = $table_name.'.priority ASC';

        if( !($available_methods_arr = $this->get_list( $list_arr ))
         or !is_array( $available_methods_arr ) )
            return array();

        $return_arr = array();
        foreach( $available_methods_arr as $linkage_arr )
        {
            if( empty( $linkage_arr['method_id'] ) )
                continue;

            $return_arr[$linkage_arr['method_id']] = $linkage_arr;
        }

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
