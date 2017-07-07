<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WC_S2P_Base
{
    const ERR_OK = 0;

    private $error_code = 0;
    private $error_msg = '';

    public function __construct( $init_params = false )
    {
        $this->reset_error();
    }

    public function reset_error()
    {
        $this->error_code = self::ERR_OK;
        $this->error_msg = '';
    }

    public function set_error( $code, $msg )
    {
        $this->error_code = $code;
        $this->error_msg = $msg;
    }

    public function has_error()
    {
        return ($this->error_code != self::ERR_OK);
    }

    public function get_error()
    {
        return array(
            'code' => $this->error_code,
            'message' => $this->error_msg,
        );
    }

    public function get_error_message()
    {
        return $this->error_msg;
    }

    public function get_error_code()
    {
        return $this->error_code;
    }

    public function copy_error_from_array( $error_arr )
    {
        if( empty( $error_arr ) or !is_array( $error_arr )
         or !isset( $error_arr['code'] ) or !isset( $error_arr['message'] ) )
            return false;

        $this->error_code = $error_arr['code'];
        $this->error_msg = $error_arr['message'];

        return true;
    }
}
