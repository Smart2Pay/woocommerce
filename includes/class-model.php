<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

abstract class WC_S2P_Model extends WC_S2P_Base
{
    const ERR_GENERIC = 1, ERR_PARAMETERS = 2;

    private $last_result = false;

    abstract public function get_table();
    abstract public function get_table_fields();

    public function __construct( $init_params = false )
    {
        parent::__construct( $init_params );

        $this->last_result( false );
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
        return $insert_arr;
    }

    /**
     * Overwrite this method if you want to change data array right after inserting it to database...
     *
     * @param array $insert_arr
     * @param array $params
     *
     * @return array|bool Changed array to be returned by add method. If returns false will delete inserted row.
     */
    public function insert_after( $insert_arr, $params )
    {
        return $insert_arr;
    }

    /**
     * Overwrite this method to alter parameters sent to edit method
     *
     * @param array $params Parameters passed to edit method
     *
     * @return array|bool Changed edit parameters. If returns false will stop insertion.
     */
    public function edit_check_parameters( $params )
    {
        return $params;
    }

    /**
     * Overwrite this method if you want to change data array right before saving it to database...
     *
     * @param array $edit_arr
     * @param array $params
     *
     * @return array|bool Changed array to be saved in database. If returns false will stop editing.
     */
    public function edit_before( $edit_arr, $params )
    {
        return $edit_arr;
    }

    /**
     * Overwrite this method if you want to change data array right after saving it to database...
     * This function is called only if some fields were saved in database.
     *
     * @param array $edit_arr
     * @param array $params
     *
     * @return array|bool Changed array to be returned by add method. If returns false will delete inserted row.
     */
    public function edit_after( $edit_arr, $params )
    {
        return $edit_arr;
    }

    /**
     * Overwrite this method if you want to change data array after edit method finishes.
     * This function is called nomatter if database was updates or not
     *
     * @param array $existing_arr Array with updated data after edit (if there were any changes from $edit_arr)
     * @param array $edit_arr Array which holds fields which were changed. Holds NEW data
     * @param array $changes_arr Array which holds fields which were changed. Holds OLD data
     * @param array $params Array with all parameters sent to edit method
     *
     * @return array|bool Changed array to be returned by edit method. If returns false changes will not be rolled back.
     */
    public function edit_after_action( $existing_arr, $edit_arr, $changes_arr, $params )
    {
        return $existing_arr;
    }

    /**
     * Parses flow parameters if anything special should be done for count query and returns modified parameters array
     *
     * @param array|false $params Parameters in the flow
     *
     * @return array Flow parameters array
     */
    public function get_count_prepare_params( $params = false )
    {
        return $params;
    }

    /**
     * Parses flow parameters if anything special should be done for listing records query and returns modified parameters array
     *
     * @param array|false $params Parameters in the flow
     *
     * @return array Flow parameters array
     */
    public function get_list_prepare_params( $params = false )
    {
        return $params;
    }

    public function add( $params )
    {
        global $wpdb;

        if( empty( $params ) or !is_array( $params )
         or empty( $params['fields'] ) or !is_array( $params['fields'] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, WC_s2p()->__( 'Bad parameters.' ) );
            return false;
        }

        if( !($primary_field = $this->get_primary()) )
        {
            $this->set_error( self::ERR_PARAMETERS, WC_s2p()->__( 'Table doesn\'t have a primary field defined.' ) );
            return false;
        }

        if( !($params = $this->insert_check_parameters( $params )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_PARAMETERS, 'Invalid parameters passed to add method.' );

            return false;
        }

        if( !($insert_arr = $this->validate_fields( $params['fields'], array( 'in_edit' => false ) )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_PARAMETERS, 'Table fields failed validation.' );

            return false;
        }

        if( !($insert_arr = $this->insert_before( $insert_arr, $params )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_PARAMETERS, 'Table fields are invalid before insert.' );

            return false;
        }

        if( isset( $insert_arr[$primary_field] ) )
            unset( $insert_arr[$primary_field] );

        if( !($sql = $this->quick_insert( $insert_arr ))
         or !$wpdb->query( $sql )
         or !$wpdb->insert_id )
        {
            $this->set_error( self::ERR_PARAMETERS, WC_s2p()->__( 'Error saving details in database.' ) );
            return false;
        }

        $insert_arr[$primary_field] = $wpdb->insert_id;
        $insert_arr['<new_in_db>'] = true;

        if( !($insert_arr = $this->insert_after( $insert_arr, $params )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_PARAMETERS, 'Table fields are invalid before insert.' );

            return false;
        }

        $this->last_result( $insert_arr );

        return $insert_arr;
    }

    public function edit( $existing_data, $params )
    {
        global $wpdb;

        if( !($existing_arr = $this->data_to_array( $existing_data )) )
        {
            $this->set_error( self::ERR_PARAMETERS, WC_s2p()->__( 'Editing row doesn\'t exist in database.' ) );
            return false;
        }

        if( empty( $params ) or !is_array( $params )
         or empty( $params['fields'] ) or !is_array( $params['fields'] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, WC_s2p()->__( 'Bad parameters.' ) );
            return false;
        }

        if( !($primary_field = $this->get_primary()) )
        {
            $this->set_error( self::ERR_PARAMETERS, WC_s2p()->__( 'Table doesn\'t have a primary field defined.' ) );
            return false;
        }

        if( !($params = $this->edit_check_parameters( $params )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_PARAMETERS, 'Invalid parameters passed to edit method.' );

            return false;
        }

        if( !($edit_arr = $this->validate_fields( $params['fields'], array( 'in_edit' => true ) ))
         or !($edit_arr = $this->edit_before( $edit_arr, $params )) )
        {
            if( $this->has_error() )
                return false;

            $edit_arr = array();
        }

        $changes_arr = array();
        foreach( $edit_arr as $field_name => $field_value )
        {
            if( !array_key_exists( $field_name, $existing_arr )
             or (string)$field_value === (string)$existing_arr[$field_name] )
            {
                unset( $edit_arr[$field_name] );
                continue;
            }

            $changes_arr[$field_name] = $existing_arr[$field_name];
        }

        if( isset( $edit_arr[$primary_field] ) )
            unset( $edit_arr[$primary_field] );

        if( !empty( $edit_arr ) )
        {
            if( !($sql = $this->quick_edit( $edit_arr ))
             or !$wpdb->query( $sql . ' WHERE `' . $primary_field . '` = \'' . $existing_arr[$primary_field] . '\'' ) )
            {
                $this->set_error( self::ERR_PARAMETERS, WC_s2p()->__( 'Error saving details in database.' ) );
                return false;
            }

            foreach( $edit_arr as $field_name => $field_value )
                $existing_arr[$field_name] = $field_value;
        }

        if( !($existing_arr = $this->edit_after_action( $existing_arr, $edit_arr, $changes_arr, $params )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_PARAMETERS, 'Table fields are invalid before insert.' );

            return false;
        }

        $this->last_result( $existing_arr );

        return $existing_arr;
    }

    static function linkage_db_functions()
    {
        return array( 'and', 'or' );
    }

    public function get_query_fields( $params )
    {
        global $wpdb;

        if( empty( $params['fields'] ) or !is_array( $params['fields'] ) )
            return $params;

        if( empty( $params['table_name'] ) )
            $params['table_name'] = $this->get_table();

        if( empty( $params['table_name'] ) )
            return $params;

        if( empty( $params['extra_sql'] ) )
            $params['extra_sql'] = '';

        // Params used for <linkage> parameter (recurring)...
        if( empty( $params['recurring_level'] ) )
            $params['recurring_level'] = 0;

        $linkage_func = 'AND';
        if( !empty( $params['fields']['<linkage_func>'] )
        and in_array( strtolower( $params['fields']['<linkage_func>'] ), self::linkage_db_functions() ) )
            $linkage_func = strtoupper( $params['fields']['<linkage_func>'] );

        if( isset( $params['fields']['<linkage_func>'] ) )
            unset( $params['fields']['<linkage_func>'] );

        foreach( $params['fields'] as $field_name => $field_val )
        {
            $field_name = trim( $field_name );
            if( empty( $field_name ) )
                continue;

            if( $field_name == '<linkage>' )
            {
                if( empty( $field_val ) or !is_array( $field_val )
                 or empty( $field_val['fields'] ) or !is_array( $field_val['fields'] ) )
                    continue;

                $recurring_params = $params;
                $recurring_params['fields'] = $field_val['fields'];
                $recurring_params['extra_sql'] = '';
                $recurring_params['recurring_level']++;

                if( ($recurring_result = $this->get_query_fields( $recurring_params ))
                and is_array( $recurring_result ) and !empty( $recurring_result['extra_sql'] ) )
                {
                    $params['extra_sql'] .= (!empty( $params['extra_sql'] )?' '.$linkage_func.' ':'').' ('.$recurring_result['extra_sql'].') ';
                }

                continue;
            }

            if( strstr( $field_name, '.' ) === false )
                $field_name = '`'.$params['table_name'].'`.`'.$field_name.'`';

            if( !is_array( $field_val ) )
            {
                if( $field_val !== false )
                    $params['extra_sql'] .= (!empty( $params['extra_sql'] )?' '.$linkage_func.' ':'').' '.$field_name.' = \''.$wpdb->_escape( $field_val ).'\' ';
            } else
            {
                if( empty( $field_val['field'] ) )
                    $field_val['field'] = $field_name;
                if( empty( $field_val['check'] ) )
                    $field_val['check'] = '=';
                if( !isset( $field_val['value'] ) )
                    $field_val['value'] = false;

                if( $field_val['value'] !== false )
                {
                    $field_val['check'] = trim( $field_val['check'] );
                    if( in_array( strtolower( $field_val['check'] ), array( 'in', 'is', 'between' ) ) )
                        $check_value = $field_val['value'];
                    else
                        $check_value = '\''.$wpdb->_escape( $field_val['value'] ).'\'';

                    $params['extra_sql'] .= (!empty( $params['extra_sql'] )?' '.$linkage_func.' ':'').' '.$field_val['field'].' '.$field_val['check'].' '.$check_value.' ';
                }
            }
        }

        return $params;
    }

    public function get_count( $params = false )
    {
        global $wpdb;

        $this->reset_error();

        if( empty( $params['table_name'] ) )
            $params['table_name'] = $this->get_table();

        if( empty( $params['table_name'] ) )
            return 0;

        if( empty( $params['count_field'] ) )
            $params['count_field'] = '*';

        if( empty( $params['extra_sql'] ) )
            $params['extra_sql'] = '';
        if( empty( $params['join_sql'] ) )
            $params['join_sql'] = '';
        if( empty( $params['group_by'] ) )
            $params['group_by'] = '';

        if( empty( $params['fields'] ) )
            $params['fields'] = array();

        if( ($params = $this->get_count_prepare_params( $params )) === false
         or ($params = $this->get_query_fields( $params )) === false )
            return 0;

        if( empty( $params['extra_sql'] ) )
            $params['extra_sql'] = '';

        $ret = 0;
        if( ($result = $wpdb->get_row( 'SELECT COUNT('.$params['count_field'].') AS total_enregs '.
                              ' FROM `'.$params['table_name'].'` '.
                              $params['join_sql'].
                              (!empty( $params['extra_sql'] )?' WHERE '.$params['extra_sql']:'').
                              (!empty( $params['group_by'] )?' GROUP BY '.$params['group_by']:''), ARRAY_A
            )) )
        {
            $ret = $result['total_enregs'];
        }

        return $ret;
    }

    public function get_list( $params = false )
    {
        global $wpdb;

        $this->reset_error();

        if( empty( $params['table_name'] ) )
            $params['table_name'] = $this->get_table();
        if( empty( $params['table_index'] ) )
            $params['table_index'] = $this->get_primary();

        if( empty( $params['table_name'] ) or empty( $params['table_index'] ) )
            return false;

        if( empty( $params['get_query_id'] ) )
            $params['get_query_id'] = false;
        // Field which will be used as key in result array (be sure is unique)
        if( empty( $params['arr_index_field'] ) )
            $params['arr_index_field'] = $params['table_index'];

        if( empty( $params['extra_sql'] ) )
            $params['extra_sql'] = '';
        if( empty( $params['join_sql'] ) )
            $params['join_sql'] = '';
        if( empty( $params['db_fields'] ) )
            $params['db_fields'] = '`'.$params['table_name'].'`.*';
        if( empty( $params['offset'] ) )
            $params['offset'] = 0;
        if( empty( $params['enregs_no'] ) )
            $params['enregs_no'] = 1000;
        if( empty( $params['order_by'] ) )
            $params['order_by'] = '`'.$params['table_name'].'`.`'.$params['table_index'].'` DESC';
        if( empty( $params['group_by'] ) )
            $params['group_by'] = '`'.$params['table_name'].'`.`'.$params['table_index'].'`';

        if( empty( $params['fields'] ) )
            $params['fields'] = array();

        if( ($params = $this->get_list_prepare_params( $params )) === false
         or ($params = $this->get_query_fields( $params )) === false
         or !($rows_arr = $wpdb->get_results( 'SELECT '.$params['db_fields'].' '.
                              ' FROM `'.$params['table_name'].'` '.
                              $params['join_sql'].
                              (!empty( $params['extra_sql'] )?' WHERE '.$params['extra_sql']:'').
                              (!empty( $params['group_by'] )?' GROUP BY '.$params['group_by']:'').
                              (!empty( $params['order_by'] )?' ORDER BY '.$params['order_by']:'').
                              ' LIMIT '.$params['offset'].', '.$params['enregs_no'], ARRAY_A
                ))
         or !is_array( $rows_arr ) )
            return false;

        $ret_arr = array();
        foreach( $rows_arr as $item_arr )
        {
            $key = $params['table_index'];
            if( isset( $item_arr[$params['arr_index_field']] ) )
                $key = $params['arr_index_field'];

            $ret_arr[$item_arr[$key]] = $item_arr;
        }

        return $ret_arr;
    }

    public function get_primary()
    {
        static $primary_field = false;

        if( $primary_field !== false )
            return $primary_field;

        if( !($table_fields = $this->get_table_fields())
         or !is_array( $table_fields ) )
        {
            $this->set_error( self::ERR_GENERIC, 'Table fields not defined.' );
            return false;
        }

        $primary_field = '';
        foreach( $table_fields as $field_name => $field_structure )
        {
            if( empty( $field_structure ) or !is_array( $field_structure )
             or empty( $field_structure['primary'] ) )
                continue;

            $primary_field = $field_name;
            break;
        }

        return $primary_field;
    }

    private function last_result( $last_result = null )
    {
        if( $last_result === null )
            return $this->last_result;

        $this->last_result = $last_result;
        return $this->last_result;
    }

    public function get_details_fields( $constrain_arr, $params = false )
    {
        global $wpdb;

        $this->reset_error();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['table_name'] ) )
            $table_name = $this->get_table();
        else
            $table_name = $params['table_name'];

        if( empty( $constrain_arr ) or !is_array( $constrain_arr )
         or empty( $table_name )
         or !($table_index = $this->get_primary()) )
            return false;

        if( empty( $params['details'] ) )
            $params['details'] = '*';
        if( !isset( $params['result_type'] ) )
            $params['result_type'] = 'single';
        if( !isset( $params['result_key'] ) )
            $params['result_key'] = $table_index;
        if( empty( $params['extra_sql'] ) )
            $params['extra_sql'] = '';
        if( empty( $params['order_by'] ) )
            $params['order_by'] = '';

        if( !isset( $params['limit'] ) )
            $params['limit'] = 1;

        else
        {
            $params['limit'] = intval( $params['limit'] );
            $params['result_type'] = 'list';
        }

        foreach( $constrain_arr as $field_name => $field_val )
        {
            $field_name = trim( $field_name );
            if( empty( $field_name ) )
                continue;

            if( strstr( $field_name, '.' ) === false )
                $field_name = '`'.$table_name.'`.`'.$field_name.'`';

            if( !is_array( $field_val ) )
            {
                if( $field_val !== false )
                    $params['extra_sql'] .= (!empty( $params['extra_sql'] )?' AND ':'').' '.$field_name.' = \''.$wpdb->_escape( $field_val ).'\' ';
            } else
            {
                if( empty( $field_val['field'] ) )
                    $field_val['field'] = $field_name;
                if( empty( $field_val['check'] ) )
                    $field_val['check'] = '=';
                if( !isset( $field_val['value'] ) )
                    $field_val['value'] = false;

                if( $field_val['value'] !== false )
                {
                    $field_val['check'] = trim( $field_val['check'] );
                    if( in_array( strtolower( $field_val['check'] ), array( 'in', 'is' ) ) )
                        $check_value = $field_val['value'];
                    else
                        $check_value = '\''.$wpdb->_escape( $field_val['value'] ).'\'';

                    $params['extra_sql'] .= (!empty( $params['extra_sql'] )?' AND ':'').' '.$field_val['field'].' '.$field_val['check'].' '.$check_value.' ';
                }
            }
        }

        if( !($results = $wpdb->get_results( 'SELECT '.$params['details'].' FROM '.$table_name.' WHERE '.$params['extra_sql'].
                               (!empty( $params['order_by'] )?' ORDER BY '.$params['order_by']:'').
                               (isset( $params['limit'] )?' LIMIT 0, '.$params['limit']:''), ARRAY_A ))
         or !is_array( $results ) )
            return false;

        if( $params['result_type'] == 'single' )
            $item_arr = array_pop( $results );

        else
        {
            $item_arr = array();
            foreach( $results as $row_arr )
                $item_arr[$row_arr[$params['result_key']]] = $row_arr;
        }

        return $item_arr;
    }

    public function get_details( $id, $params = false )
    {
        global $wpdb;

        if( !($table_name = $this->get_table())
         or !($primary_field = $this->get_primary()) )
            return false;

        if( empty( $params['details'] ) )
            $params['details'] = '*';

        $id = intval( $id );
        if( empty( $id )
         or !($item_arr = $wpdb->get_row( 'SELECT '.$params['details'].' FROM `'.$table_name.'` WHERE `'.$primary_field.'` = \''.$wpdb->_escape( $id ).'\'', ARRAY_A )) )
            return false;

        return $item_arr;
    }

    public function data_to_array( $item_data, $params = false )
    {
        if( !($primary_field = $this->get_primary()) )
            return false;

        $id = 0;
        $item_arr = false;
        if( is_array( $item_data ) )
        {
            if( !empty( $item_data[$primary_field] ) )
                $id = intval( $item_data[$primary_field] );
            $item_arr = $item_data;
        } else
            $id = intval( $item_data );

        if( empty( $id ) and (!is_array( $item_arr ) or empty( $item_arr[$primary_field] )) )
            return false;

        if( empty( $item_arr ) )
            $item_arr = $this->get_details( $id, $params );

        if( empty( $item_arr ) or !is_array( $item_arr ) )
            return false;

        return $item_arr;
    }

    public static function default_fields_structure()
    {
        return array(
            'type' => PHS_params::T_ASIS,
            'type_extra' => false,
            // Tells if edit method should work on this field
            'default' => '',
            'editable' => true,
            'primary' => false,
        );
    }

    public function validate_fields( $fields, $params = false )
    {
        if( !($table_name = $this->get_table()) )
        {
            $this->set_error( self::ERR_PARAMETERS, 'Table name not defined.' );
            return false;
        }

        if( !($default_fields = $this->get_table_fields())
         or !is_array( $default_fields ) )
        {
            $this->set_error( self::ERR_PARAMETERS, 'Default fields not defined for table ['.$table_name.'].' );
            return false;
        }

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !isset( $params['in_edit'] ) )
            $params['in_edit'] = false;

        $fields_structure = self::default_fields_structure();

        $fields = apply_filters( 'wc_s2p_model_validate_fields', $fields, array(), $table_name );

        if( empty( $fields ) or !is_array( $fields ) )
        {
            $this->set_error( self::ERR_PARAMETERS, WC_s2p()->__( 'Table fields not provided.' ) );
            return false;
        }

        $validated_fields_arr = array();
        foreach( $default_fields as $field_name => $field_structure )
        {
            $field_structure = array_merge( $fields_structure, $field_structure );

            if( isset( $fields[$field_name] )
            and (empty( $params['in_edit'] ) or !empty( $field_structure['editable'] )) )
                $validated_fields_arr[$field_name] = PHS_params::set_type( $fields[$field_name], $field_structure['type'], $field_structure['type_extra'] );

            elseif( empty( $params['in_edit'] ) )
                $validated_fields_arr[$field_name] = $field_structure['default'];
        }

        return $validated_fields_arr;
    }

    // Returns an INSERT query string for model table using $insert_arr data
    public function quick_insert( $insert_arr, $params = false )
    {
        global $wpdb;

        if( !is_array( $insert_arr ) or !count( $insert_arr ) )
            return '';

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !isset( $params['escape'] ) )
            $params['escape'] = true;

        $return = '';
        foreach( $insert_arr as $key => $val )
        {
            if( is_array( $val ) )
            {
                if( !isset( $val['value'] ) )
                    continue;

                if( empty( $val['raw_field'] ) )
                    $val['raw_field'] = false;

                $field_value = $val['value'];

                if( empty( $val['raw_field'] ) )
                {
                    if( !empty( $params['secure'] ) )
                        $field_value = self::prepare_data( $wpdb->_escape( $field_value ) );

                    $field_value = '\''.$field_value.'\'';
                }
            } else
                $field_value = '\''.(!empty( $params['secure'] )?self::prepare_data( $wpdb->_escape( $val ) ):$val).'\'';

            $return .= '`'.$key.'`='.$field_value.', ';
        }

        if( $return == '' )
            return '';

        return 'INSERT INTO `'.$this->get_table().'` SET '.substr( $return, 0, -2 );
    }

    // Returns an EDIT query string for model table using $edit_arr data conditions added outside this method
    public function quick_edit( $edit_arr, $params = false )
    {
        global $wpdb;

        if( !is_array( $edit_arr ) or !count( $edit_arr ) )
            return '';

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !isset( $params['escape'] ) )
            $params['escape'] = true;

        $return = '';
        foreach( $edit_arr as $key => $val )
        {
            if( is_array( $val ) )
            {
                if( !isset( $val['value'] ) )
                    continue;

                if( empty( $val['raw_field'] ) )
                    $val['raw_field'] = false;

                $field_value = $val['value'];

                if( empty( $val['raw_field'] ) )
                {
                    if( !empty( $params['secure'] ) )
                        $field_value = self::prepare_data( $wpdb->_escape( $field_value ) );

                    $field_value = '\''.$field_value.'\'';
                }
            } else
                $field_value = '\''.(!empty( $params['secure'] )?self::prepare_data( $wpdb->_escape( $val ) ):$val).'\'';

            $return .= '`'.$key.'`='.$field_value.', ';
        }

        if( $return == '' )
            return '';

        return 'UPDATE `'.$this->get_table().'` SET '.substr( $return, 0, -2 );
    }

    static function prepare_data( $data )
    {
        $data = str_replace( '\'', '\\\'', str_replace( '\\\'', '\'', $data ) );

        return $data;
    }
}
