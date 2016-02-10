<?php

class WC_S2P_Helper
{
    const SQL_DATETIME = 'Y-m-d H:i:s', EMPTY_DATETIME = '0000-00-00 00:00:00';
    const SQL_DATE = 'Y-m-d', EMPTY_DATE = '0000-00-00';
    const NOTIFICATION_ENTRY_POINT = 'WC_Gateway_Smart2Pay';

    public static function notification_url()
    {
        return str_replace( 'https:', 'http:', add_query_arg( 'wc-api', self::NOTIFICATION_ENTRY_POINT, home_url( '/' ) ) );
    }

    public static function check_checkbox_value( $value )
    {
        return (!empty( $value ) and $value == 'yes');
    }

    public static function transaction_details_titles()
    {
        return array(
            'bankcode' => WC_s2p()->__( 'Bank Code' ),
            'bankname' => WC_s2p()->__( 'Bank Name' ),
            'entityid' => WC_s2p()->__( 'Entity ID' ),
            'entitynumber' => WC_s2p()->__( 'Entity Number' ),
            'referenceid' => WC_s2p()->__( 'Reference ID' ),
            'referencenumber' => WC_s2p()->__( 'Reference Number' ),
            'swift_bic' => WC_s2p()->__( 'Swift / BIC' ),
            'accountcurrency' => WC_s2p()->__( 'Account Currency' ),
            'accountnumber' => WC_s2p()->__( 'Account Number' ),
            'accountholder' => WC_s2p()->__( 'Account Holder' ),
            'iban' => WC_s2p()->__( 'IBAN' ),
        );
    }

    public static function transaction_details_key_to_title( $key )
    {
        $all_titles = self::transaction_details_titles();

        return (!empty( $all_titles[$key] )?$all_titles[$key]:$key);
    }

    public static function convert_to_demo_merchant_transaction_id( $mt_id )
    {
        return 'DEMO_'.base_convert( time(), 10, 36 ).'_'.$mt_id;
    }

    public static function convert_from_demo_merchant_transaction_id( $mt_id )
    {
        if( strstr( $mt_id, '_' ) !== false
        and strtoupper( substr( $mt_id, 0, 4 ) ) == 'DEMO'
        and ($mtid_arr = explode( '_', $mt_id, 3 ))
        and !empty( $mtid_arr[2] ) )
            $mt_id = $mtid_arr[2];

        return intval( $mt_id );
    }

    public static function get_order_products( $order_data )
    {
        if( !function_exists( 'wc_get_order' ) )
            return false;

        /** @var WC_Order $order_obj */
        $order_obj = false;
        if( is_int( $order_data ) )
            $order_obj = wc_get_order( $order_data );
        elseif( is_object( $order_data )
            and $order_data instanceof WC_Order )
            $order_obj = $order_data;

        if( empty( $order_obj )
         or !($products_arr = $order_obj->get_items( array( 'line_item', 'shipping', 'fee', 'coupon', 'tax' ) ))
         or !is_array( $products_arr ) )
            return false;

        $articles_arr = array();
        $knti = 0;
        $fees_indexes = array();
        foreach( $products_arr as $product_arr )
        {
            if( $product_arr['type'] == 'coupon' )
                continue;

            $article = array();
            $article['merchantarticleid'] = 1;
            $article['name'] = $product_arr['name'];
            $article['quantity'] = 1;
            $article['price'] = 0;
            $article['vat'] = 0;
            $article['discount'] = 0;
            $article['type'] = 1;

            switch( $product_arr['type'] )
            {
                default:
                    continue;
                break;

                case 'fee':
                    $article['price'] = $product_arr['line_total'];
                    $fees_indexes[] = $knti;
                break;

                case 'line_item':
                    $article['quantity'] = (empty( $product_arr['qty'] )?1:$product_arr['qty']);
                    $article['price'] = $product_arr['line_total'] / $product_arr['qty'];
                    $article['merchantarticleid'] = $product_arr['product_id'];
                break;

                case 'shipping':
                    $article['price'] = $product_arr['cost'];
                    $article['type'] = 2;
                break;

                case 'tax':
                    $article['name'] = $product_arr['label'];
                    $article['price'] = $product_arr['tax_amount'] + $product_arr['shipping_tax_amount'];
                break;
            }

            if( $article['price'] <= 0 )
                continue;

            $articles_arr[$knti] = $article;
            $knti++;
        }

        // Prepare centimes...
        foreach( $articles_arr as $knti => $article_arr )
        {
            $articles_arr[$knti]['price']    = number_format( $articles_arr[$knti]['price'] * 100, 0, '.', '' );
            $articles_arr[$knti]['vat']      = number_format( $articles_arr[$knti]['vat'] * 100, 0, '.', '' );
            $articles_arr[$knti]['discount'] = number_format( $articles_arr[$knti]['discount'] * 100, 0, '.', '' );
        }

        return $articles_arr;
    }

    public static function mb_substr( $message, $start, $length = null )
    {
        if( function_exists( 'mb_substr' ) )
            return mb_substr( $message, $start, $length, 'UTF-8' );

        return ($length===null?substr( $message, $start ):substr( $message, $start, $length ));
    }

    public static function get_plugin_gateway_object()
    {
        WC_s2p()->init_smart2pay_gateway();

        return new WC_Gateway_Smart2Pay();
    }

    public static function get_plugin_settings( $key = false )
    {
        static $settings_arr = array();

        if( !empty( $settings_arr ) )
        {
            if( !empty( $key ) )
            {
                if( isset( $settings_arr[$key] ) )
                    return $settings_arr[$key];

                return null;
            }

            return $settings_arr;
        }

        if( !($wc_s2p_gateway = self::get_plugin_gateway_object()) )
            return null;

        $settings_arr = $wc_s2p_gateway->settings;

        if( !empty( $key ) )
        {
            if( isset( $settings_arr[$key] ) )
                return $settings_arr[$key];

            return null;
        }

        return $settings_arr;
    }

    public static function pretty_date_display( $mysql_date )
    {
        $t_time = mysql2date( 'Y/m/d g:i:s A', $mysql_date, true );
        $m_time = $mysql_date;
        $time = mysql2date( 'G', $mysql_date ) - get_option( 'gmt_offset' ) * 3600;

        $time_diff = time() - $time;

        if ( $time_diff >= 0 && $time_diff < 24*60*60 )
            $h_time = sprintf( WC_s2p()->__( '%s ago' ), human_time_diff( $time ) );
        else
            $h_time = mysql2date( 'Y/m/d', $m_time );

        return '<abbr title="' . $t_time . '">' . $h_time . '</abbr>';
    }

    /**
     * Returns WP_User object based on $user_data parameter $user_data is found in database (if int) or validates that $user_data is a WP_User object
     *
     * @param int|WP_User $user_data User ID or User object (WP_User)
     *
     * @return bool|WP_User Returns WP_User object if $user_data is found in database (if int) or validates that $user_data is a WP_User object
     */
    public static function get_user_data( $user_data )
    {
        $user_obj = false;
        /** @var WP_User $user_obj */
        if( is_numeric( $user_data )
        and !($user_obj = get_userdata( (int)$user_data )) )
            $user_obj = false;

        elseif( is_object( $user_data )
            and $user_data instanceof WP_User )
            $user_obj = $user_data;

        if( empty( $user_obj )
         or empty( $user_obj->ID ) )
            return false;

        return $user_obj;
    }

    /**
     * @param array $params Parameters which will be passed to WP_Query object
     * @param bool|false $extra Parameters which change behaviour of this method
     *
     * @return array|WP_Query|bool Returns search results as WP_Query object, array of posts with their links or false on no post/page found
     */
    public static function find_articles( $params, $extra = false )
    {
        /** @var $post WP_Post */
        global $post;

        if( empty( $params ) or !is_array( $params ) )
            return false;

        if( empty( $extra ) or !is_array( $extra ) )
            $extra = array();

        // post or page slug
        if( empty( $params['name'] ) )
            $params['name'] = '';
        if( empty( $params['post_type'] ) )
            $params['post_type'] = 'post';
        if( empty( $params['post_status'] ) )
            $params['post_status'] = 'publish';
        if( !isset( $params['posts_per_page'] ) )
            $params['posts_per_page'] = 1;
        if( !isset( $params['ignore_sticky_posts'] ) )
            $params['ignore_sticky_posts'] = 1;
        if( !isset( $params['suppress_filters'] ) )
            $params['suppress_filters'] = 1;

        if( empty( $extra['return_query'] ) )
            $extra['return_query'] = false;

        $old_post = $post;

        $my_query = new WP_Query( $params );
        if( !$my_query->have_posts() )
        {
            wp_reset_query();
            $post = $old_post;

            return false;
        }

        // When returning WP_Query object you will have to call wp_reset_query(); to reset query results.
        if( !empty( $extra['return_query'] ) )
        {
            wp_reset_query();
            $post = $old_post;
            return $my_query;
        }

        $return_arr = array();
        $return_arr['posts'] = array();
        $return_arr['links'] = array();
        $return_arr['title'] = array();
        $return_arr['body'] = array();
        $return_arr['first_post'] = null;
        $return_arr['first_link'] = '';

        while( $my_query->have_posts() )
        {
            $my_query->the_post();

            if( empty( $post ) )
                continue;

            $return_arr['posts'][$post->ID] = $post;
            $return_arr['links'][$post->ID] = get_permalink( $post );
            $return_arr['title'][$post->ID] = (isset( $post->post_title ) ? $post->post_title : '');
            $return_arr['body'][$post->ID] = (isset( $post->post_content ) ? $post->post_content : '');

            if( empty( $return_arr['first_post'] ) )
            {
                $return_arr['first_post'] = $post;
                $return_arr['first_link'] = $return_arr['links'][$post->ID];
            }
        }

        wp_reset_query();
        $post = $old_post;

        return $return_arr;
    }

    public static function get_slug_page_url( $slug, $args = false )
    {
        if( empty( $args ) or !is_array( $args ) )
            $args = array();

        $args = array_merge( $args, array(
                                'name' => $slug,
                                'post_type' => 'page',
                            ) );

        if( ($posts_arr = self::find_articles( $args ))
        and !empty( $posts_arr['first_link'] ) )
            return $posts_arr['first_link'];

        return '';
    }

    public static function get_slug_internal_page_url( $slug, $args = false )
    {
        if( empty( $args ) or !is_array( $args ) )
            $args = array();

        $args = array_merge( $args, array(
            'post_status' => 'private',
        ) );

        return self::get_slug_page_url( $slug, $args );
    }

    public static function form_str( $str )
    {
        return str_replace( '"', '&quot;', $str );
    }

    public static function db_esc( $str )
    {
        return str_replace( '\'', '\\\'', str_replace( '\\\'', '\'', $str ) );
    }

    static public function nice_json_string( $str )
    {
        return str_replace( array( ',', '{', '}', '[', ']', "\n\n", "}\n,", "]\n," ), array( ",\n", "{\n", "\n}\n", "[\n", "]\n", "\n", '},', '],' ), $str );
    }


    public static function validate_db_date( $str )
    {
        return date( self::SQL_DATE, self::parse_db_date( $str ) );
    }

    public static function validate_db_datetime( $str )
    {
        return date( self::SQL_DATETIME, self::parse_db_date( $str ) );
    }

    static function parse_db_date( $str )
    {
        $str = trim( $str );
        if( strstr( $str, ' ' ) )
        {
            $d = explode( ' ', $str );
            $date_ = explode( '-', $d[0] );
            $time_ = explode( ':', $d[1] );
        } else
            $date_ = explode( '-', $str );

        for( $i = 0; $i < 3; $i++ )
        {
            if( !isset( $date_[$i] ) )
                $date_[$i] = 0;
            if( isset( $time_ ) and !isset( $time_[$i] ) )
                $time_[$i] = 0;
        }

        if( !empty( $date_ ) and is_array( $date_ ) )
            foreach( $date_ as $key => $val )
                $date_[$key] = intval( $val );
        if( !empty( $time_ ) and is_array( $time_ ) )
            foreach( $time_ as $key => $val )
                $time_[$key] = intval( $val );

        if( isset( $time_ ) )
            return mktime( $time_[0], $time_[1], $time_[2], $date_[1], $date_[2], $date_[0] );
        else
            return mktime( 0, 0, 0, $date_[1], $date_[2], $date_[0] );
    }

    static function seconds_passed( $str )
    {
        return time() - self::parse_db_date( $str );
    }

    static function to_api_date( $timestamp )
    {
        if( empty( $timestamp )
         or !is_int( $timestamp ) )
            return '';

        return date( 'Y', $timestamp ).date( 'm', $timestamp ).date( 'd', $timestamp ).date( 'H', $timestamp ).date( 'i', $timestamp ).date( 's', $timestamp );
    }

    static function parse_api_date( $date )
    {
        if( empty( $date )
         or strlen( $date ) != 14 )
            return 0;

        $year = (int)@substr( $date, 0, 4 );
        $month = (int)@substr( $date, 4, 2 );
        $day = (int)@substr( $date, 6, 2 );
        $hour = (int)@substr( $date, 8, 2 );
        $minute = (int)@substr( $date, 10, 2 );
        $second = (int)@substr( $date, 12, 2 );

        // get a good year margin...
        if( $year > 1000 and $year < 10000
        and $month >= 1 and $month <= 12
        and $day >= 1 and $day <= 31
        and $hour >= 0 and $hour < 24
        and $minute >= 0 and $minute < 60
        and $second >= 0 and $second < 60 )
            return mktime( $hour, $minute, $second, $month, $day, $year );

        return 0;
    }

    static public function value_to_string( $val )
    {
        if( is_object( $val ) or is_resource( $val ) )
            return false;

        if( is_array( $val ) )
            return json_encode( $val );

        if( is_string( $val ) )
            return '\''.$val.'\'';

        if( is_bool( $val ) )
            return (!empty( $val )?'true':'false');

        if( is_null( $val ) )
            return 'null';

        if( is_numeric( $val ) )
            return $val;

        return false;
    }

    static public function string_to_value( $str )
    {
        if( !is_string( $str ) )
            return null;

        if( ($val = @json_decode( $str, true )) !== null )
            return $val;

        if( is_numeric( $str ) )
            return $str;

        if( ($tch = substr( $str, 0, 1 )) == '\'' or $tch = '"' )
            $str = substr( $str, 1 );
        if( ($tch = substr( $str, -1 )) == '\'' or $tch = '"' )
            $str = substr( $str, 0, -1 );

        $str_lower = strtolower( $str );
        if( $str_lower == 'null' )
            return null;

        if( $str_lower == 'false' )
            return false;

        if( $str_lower == 'true' )
            return true;

        return $str;
    }

    static public function to_string( $lines_data )
    {
        if( empty( $lines_data ) or !is_array( $lines_data ) )
            return '';

        $lines_str = '';
        $first_line = true;
        foreach( $lines_data as $key => $val )
        {
            if( !$first_line )
                $lines_str .= "\r\n";

            $first_line = false;

            // In normal cases there cannot be '=' char in key so we interpret that value should just be passed as-it-is
            if( substr( $key, 0, 1 ) == '=' )
            {
                $lines_str .= $val;
                continue;
            }

            // Don't save if error converting to string
            if( ($line_val = self::value_to_string( $val )) === false )
                continue;

            $lines_str .= $key.'='.$line_val;
        }

        return $lines_str;
    }

    static public function parse_string_line( $line_str, $comment_no = 0 )
    {
        if( !is_string( $line_str ) )
            $line_str = '';

        // allow empty lines (keeps file 'styling' same)
        if( trim( $line_str ) == '' )
            $line_str = '';

        $return_arr = array();
        $return_arr['key'] = '';
        $return_arr['val'] = '';
        $return_arr['comment_no'] = $comment_no;

        $first_char = substr( $line_str, 0, 1 );
        if( $line_str == '' or $first_char == '#' or $first_char == ';' )
        {
            $comment_no++;

            $return_arr['key'] = '='.$comment_no.'='; // comment count added to avoid comment key overwrite
            $return_arr['val'] = $line_str;
            $return_arr['comment_no'] = $comment_no;

            return $return_arr;
        }

        $line_details = explode( '=', $line_str, 2 );
        $key = trim( $line_details[0] );

        if( $key == '' )
            return false;

        if( !isset( $line_details[1] ) )
        {
            $return_arr['key'] = $key;
            $return_arr['val'] = '';

            return $return_arr;
        }

        $return_arr['key'] = $key;
        $return_arr['val'] = self::string_to_value( $line_details[1] );

        return $return_arr;
    }

    static public function parse_string( $string )
    {
        if( empty( $string )
         or (!is_array( $string ) and !is_string( $string )) )
            return array();

        if( is_array( $string ) )
            return $string;

        $string = str_replace( "\r", "\n", str_replace( array( "\r\n", "\n\r" ), "\n", $string ) );
        $lines_arr = explode( "\n", $string );

        $return_arr = array();
        $comment_no = 1;
        foreach( $lines_arr as $line_nr => $line_str )
        {
            if( !($line_data = self::parse_string_line( $line_str, $comment_no ))
                or !is_array( $line_data ) or !isset( $line_data['key'] ) or $line_data['key'] == '' )
                continue;

            $return_arr[$line_data['key']] = $line_data['val'];
            $comment_no = $line_data['comment_no'];
        }

        return $return_arr;
    }

    static public function update_line_params( $current_data, $append_data )
    {
        if( empty( $append_data ) or (!is_array( $append_data ) and !is_string( $append_data )) )
            $append_data = array();
        if( empty( $current_data ) or (!is_array( $current_data ) and !is_string( $current_data )) )
            $current_data = array();

        if( !is_array( $append_data ) )
            $append_arr = self::parse_string( $append_data );
        else
            $append_arr = $append_data;

        if( !is_array( $current_data ) )
            $current_arr = self::parse_string( $current_data );
        else
            $current_arr = $current_data;

        if( !empty( $append_arr ) )
        {
            foreach( $append_arr as $key => $val )
                $current_arr[$key] = $val;
        }

        return $current_arr;
    }

    public static function generate_unique_id()
    {
        return str_replace( '.', '_', microtime( true ) ).'_'.rand( 1000, 9999 );
    }

    public static function rebuild_url( $url_parts )
    {
        if( empty( $url_parts ) or !is_array( $url_parts ) )
            return '';

        $parts_arr = array( 'scheme', 'user', 'pass', 'host', 'port', 'path', 'query', 'anchor' );
        foreach( $parts_arr as $part_field )
        {
            if( !isset( $url_parts[$part_field] ) )
                $url_parts[$part_field] = '';
        }

        $final_url = $url_parts['scheme'].(!empty( $url_parts['scheme'] )?':':'').'//';
        $final_url .= $url_parts['user'];
        $final_url .= (!empty( $url_parts['pass'] )?':':'').$url_parts['pass'].((!empty( $url_parts['user'] ) or !empty( $url_parts['pass'] ))?'@':'');
        $final_url .= $url_parts['host'];
        $final_url .= (!empty( $url_parts['port'] )?':':'').$url_parts['port'];
        $final_url .= $url_parts['path'];
        $final_url .= (!empty( $url_parts['query'] )?'?':'').$url_parts['query'];
        $final_url .= (!empty( $url_parts['anchor'] )?'#':'').$url_parts['anchor'];

        return $final_url;
    }

    public static function parse_url( $str )
    {
        $ret = array();
        $ret['scheme'] = '';
        $ret['user'] = '';
        $ret['pass'] = '';
        $ret['host'] = '';
        $ret['port'] = '';
        $ret['path'] = '';
        $ret['query'] = '';
        $ret['anchor'] = '';

        $mystr = $str;

        $res = explode( '#', $mystr, 2 );
        if( isset( $res[1] ) )
            $ret['anchor'] = $res[1];
        else
            $ret['anchor'] = '';
        $mystr = $res[0];

        $res = explode( '?', $mystr, 2 );
        if( isset( $res[1] ) )
            $ret['query'] = $res[1];
        else
            $ret['query'] = '';
        $mystr = $res[0];

        $res = explode( '://', $mystr, 2 );
        if( isset( $res[1] ) )
        {
            $ret['scheme'] = $res[0];
            $mystr = $res[1];
        } else
        {
            $mystr = $res[0];

            if( substr( $mystr, 0, 2 ) == '//' )
                $ret['scheme'] = '//';
            else
                $ret['scheme'] = '';
        }

        $path_present = true;
        $host_present = true;
        if( ($dotpos = strpos( $mystr , '.' )) === false
            and (!isset( $ret['scheme'] ) or $ret['scheme'] == '') ) // host is not present - only the path might be present
            $host_present = false;

        if( ($slashpos = strpos( $mystr , '/' )) === false
            and isset( $ret['scheme'] ) and $ret['scheme'] == '' ) // no path is present or only a directory name is present
        {
            $host_present = true;
            $path_present = false;
        }

        if( $host_present and $dotpos !== false  )
        {
            if( $slashpos !== false )
            {
                if( $dotpos > $slashpos )
                    $host_present = false;
                elseif( $ret['scheme'] == '' )
                    $host_present = false;
            } elseif( $ret['scheme'] == '' )
                $host_present = false;
        }

        if( $path_present )
        {
            if( !$host_present )
            {
                $ret['path'] = $mystr;
            } else
            {
                $res = explode( '/', $mystr, 2 );
                if( isset( $res[1] ) and $res[1] != '' )
                    $ret['path'] = '/'.$res[1];
                else
                    $ret['path'] = '';
                $mystr = $res[0];
            }
        }

        $host_port = '';
        $user_pass = '';
        if( $host_present )
        {
            if( strstr( $mystr, '@' ) )
            {
                $res = explode( '@', $mystr, 2 );
                $user_pass = $res[0];
                $host_port = $res[1];
            } else
            {
                $host_port = $mystr;
                $user_pass = '';
            }
        }

        if( strstr( $host_port, ':' ) )
        {
            $res = explode( ':', $host_port, 2 );
            $ret['host'] = $res[0];
            $ret['port'] = $res[1];
        } else
        {
            $ret['host'] = $host_port;
            $ret['port'] = '';
        }

        if( $user_pass != '' )
        {
            $res = explode( ':', $user_pass, 2 );
            if( isset( $res[1] ) and $res[1] != '' )
                $ret['pass'] = $res[1];
            else
                $ret['pass'] = '';
            $ret['user'] = $res[0];
        } else
        {
            $ret['user'] = '';
            $ret['pass'] = '';
        }

        return $ret;
    }

    public static function urltrans( $url )
    {
        return str_replace( array( '?', '&', '#' ), array( '%3F', '%26', '%23' ), $url );
    }

    public static function strtrans( $str )
    {
        return str_replace( array( '%3F', '%26', '%23' ), array( '?', '&', '#' ), $str );
    }

    //! \brief Returns function/method call backtrace
    /**
      *  Used for debugging calls to functions or methods.
      *  \return Method will return a string representing function/method calls.
      */
    static function debug_call_backtrace()
    {
         $backtrace = '';
         if( is_array( ($err_info = debug_backtrace()) ) )
         {
             $err_info = array_reverse( $err_info );
             foreach( $err_info as $i => $trace_data )
             {
                 if( !isset( $trace_data['args'] ) )
                     $trace_data['args'] = '';
                 if( !isset( $trace_data['class'] ) )
                     $trace_data['class'] = '';
                 if( !isset( $trace_data['type'] ) )
                     $trace_data['type'] = '';
                 if( !isset( $trace_data['function'] ) )
                     $trace_data['function'] = '';
                 if( !isset( $trace_data['file'] ) )
                     $trace_data['file'] = '(unknown)';
                 if( !isset( $trace_data['line'] ) )
                     $trace_data['line'] = 0;

                 $args_str = '';
                 if( is_array( $trace_data['args'] ) )
                 {
                     foreach( $trace_data['args'] as $key => $val )
                     {
                         if( is_bool( $val ) )
                             $args_str .= '('.gettype( $val ).') ['.($val?'true':'false').'], ';
                         elseif( is_resource( $val ) )
                             $args_str .= '('.@get_resource_type( $val ).'), ';
                         elseif( is_array( $val ) )
                             $args_str .= '(array) ['.count( $val ).'], ';
                         elseif( !is_object( $val ) )
                             $args_str .= '('.gettype( $val ).') ['.$val.'], ';
                         else
                             $args_str .= '('.get_class( $val ).'), ';
                     }

                     $args_str = substr( $args_str, 0, -2 );
                 } else
                     $args_str = $trace_data['args'];

                 $backtrace .= '#'.($i+1).'. '.$trace_data['class'].$trace_data['type'].$trace_data['function'].'( '.$args_str.' ) - '.
                               $trace_data['file'].':'.$trace_data['line']."\n";
             }
         }

         return $backtrace;
    }
}
