<?php

class WC_S2P_Server_Notifications extends WC_S2P_Base
{
    const ERR_GENERIC = 1;

    public function __construct( $init_params = false )
    {
        parent::__construct( $init_params );
    }

    public static function notification_entry_point()
    {
        WC_s2p()->logger()->log( '--- Notification START --------------------' );

        if( !($plugin_settings = WC_S2P_Helper::get_plugin_settings())
         or !($sdk_interface = new WC_S2P_SDK_Interface())
         or !($api_credentials = $sdk_interface->get_api_credentials( $plugin_settings )) )
        {
            $error_msg = 'Couldn\'t load Smart2Pay plugin settings.';
            WC_s2p()->logger()->log( $error_msg );
            echo $error_msg;
            exit;
        }

        if( empty( $plugin_settings['enabled'] ) or $plugin_settings['enabled'] != 'yes' )
        {
            $error_msg = 'Smart2Pay plugin is disabled.';
            WC_s2p()->logger()->log( $error_msg );
            echo $error_msg;
            exit;
        }

        \S2P_SDK\S2P_SDK_Module::one_call_settings(
            array(
                'api_key' => $api_credentials['api_key'],
                'site_id' => $api_credentials['site_id'],
                'environment' => $api_credentials['environment'],
            ) );

        include_once( S2P_SDK_DIR_CLASSES . 'S2P_SDK_Notification.php' );
        include_once( S2P_SDK_DIR_CLASSES . 'S2P_SDK_Helper.php' );
        include_once( S2P_SDK_DIR_METHODS . 'S2P_SDK_Meth_Payments.php' );

        if( !defined( 'S2P_SDK_NOTIFICATION_IDENTIFIER' ) )
            define( 'S2P_SDK_NOTIFICATION_IDENTIFIER', microtime( true ) );

        S2P_SDK\S2P_SDK_Notification::logging_enabled( false );

        $notification_params = array();
        $notification_params['auto_extract_parameters'] = true;

        /** @var S2P_SDK\S2P_SDK_Notification $notification_obj */
        if( !($notification_obj = S2P_SDK\S2P_SDK_Module::get_instance( 'S2P_SDK_Notification', $notification_params ))
         or $notification_obj->has_error() )
        {
            if( (S2P_SDK\S2P_SDK_Module::st_has_error() and $error_arr = S2P_SDK\S2P_SDK_Module::st_get_error())
                or (!empty( $notification_obj ) and $notification_obj->has_error() and ($error_arr = $notification_obj->get_error())) )
                $error_msg = 'Error ['.$error_arr['error_no'].']: '.$error_arr['display_error'];
            else
                $error_msg = 'Error initiating notification object.';

            WC_s2p()->logger()->log( $error_msg );
            echo $error_msg;
            exit;
        }

        if( !$notification_obj->check_authentication() )
        {
            if( $notification_obj->has_error()
                and ($error_arr = $notification_obj->get_error()) )
                $error_msg = 'Error: '.$error_arr['display_error'];
            else
                $error_msg = 'Authentication failed.';

            WC_s2p()->logger()->log( $error_msg );
            echo $error_msg;
            exit;
        }

        if( !($notification_type = $notification_obj->get_type())
            or !($notification_title = $notification_obj::get_type_title( $notification_type )) )
        {
            $error_msg = 'Unknown notification type.';
            $error_msg .= 'Input buffer: '.$notification_obj->get_input_buffer();

            WC_s2p()->logger()->log( $error_msg );
            echo $error_msg;
            exit;
        }

        WC_s2p()->logger()->log( 'Received notification type ['.$notification_title.'].'  );

        switch( $notification_type )
        {
            case $notification_obj::TYPE_PAYMENT:
                if( !($result_arr = $notification_obj->get_array())
                 or empty( $result_arr['payment'] ) or !is_array( $result_arr['payment'] ) )
                {
                    $error_msg = 'Couldn\'t extract payment object.';
                    $error_msg .= 'Input buffer: '.$notification_obj->get_input_buffer();

                    WC_s2p()->logger()->log( $error_msg );
                    echo $error_msg;
                    exit;
                }

                $payment_arr = $result_arr['payment'];

                if( empty( $payment_arr['merchanttransactionid'] )
                 or empty( $payment_arr['status'] ) or empty( $payment_arr['status']['id'] ) )
                {
                    $error_msg = 'MerchantTransactionID or Status not provided.';
                    $error_msg .= 'Input buffer: '.$notification_obj->get_input_buffer();

                    WC_s2p()->logger()->log( $error_msg );
                    echo $error_msg;
                    exit;
                }

                if( !isset( $payment_arr['amount'] ) or !isset( $payment_arr['currency'] ) )
                {
                    $error_msg = 'Amount or Currency not provided.';
                    $error_msg .= 'Input buffer: '.$notification_obj->get_input_buffer();

                    WC_s2p()->logger()->log( array( 'message' => $error_msg, 'order_id' => $payment_arr['merchanttransactionid'] ) );
                    echo $error_msg;
                    exit;
                }

                /** @var WC_S2P_Transactions_Model $transactions_model */
                if( !($transactions_model = WC_s2p()->get_loader()->load_model( 'WC_S2P_Transactions_Model' )) )
                {
                    $error_msg = 'Couldn\'t obtain transactions model. Please retry.';
                    WC_s2p()->logger()->log( array( 'message' => $error_msg, 'order_id' => $payment_arr['merchanttransactionid'] ) );
                    echo $error_msg;
                    exit;
                }

                $check_arr = array();
                $check_arr['order_id'] = $payment_arr['merchanttransactionid'];

                if( !($transaction_arr = $transactions_model->get_details_fields( $check_arr )) )
                {
                    $error_msg = 'Couldn\'t obtain transaction details for id ['.$payment_arr['merchanttransactionid'].'].';
                    WC_s2p()->logger()->log( array( 'message' => $error_msg, 'order_id' => $payment_arr['merchanttransactionid'] ) );
                    echo $error_msg;
                    exit;
                }

                if( (string)($transaction_arr['amount'] * 100) !== (string)$payment_arr['amount']
                 or $transaction_arr['currency'] != $payment_arr['currency'] )
                {
                    $error_msg = 'Transaction details don\'t match ['.
                                 ($transaction_arr['amount'] * 100).' != '.$payment_arr['amount'].
                                 ' OR '.
                                 $transaction_arr['currency'].' != '.$payment_arr['currency'].']';

                    WC_s2p()->logger()->log( array( 'message' => $error_msg, 'order_id' => $payment_arr['merchanttransactionid'] ) );
                    echo $error_msg;
                    exit;
                }

                if( !($order = wc_get_order( $transaction_arr['order_id'] )) )
                {
                    $error_msg = 'Couldn\'t obtain order details [#'.$transaction_arr['order_id'].']';

                    WC_s2p()->logger()->log( array( 'message' => $error_msg, 'order_id' => $payment_arr['merchanttransactionid'] ) );
                    echo $error_msg;
                    exit;
                }

                if( !($status_title = S2P_SDK\S2P_SDK_Meth_Payments::valid_status( $payment_arr['status']['id'] )) )
                    $status_title = '(unknown)';

                $edit_arr = array();
                $edit_arr['order_id'] = $transaction_arr['order_id'];
                $edit_arr['payment_status'] = $payment_arr['status']['id'];

                if( !($new_transaction_arr = $transactions_model->save_transaction( $edit_arr )) )
                {
                    $error_msg = 'Couldn\'t save transaction details to database [#'.$transaction_arr['id'].', Order: '.$transaction_arr['order_id'].'].';
                    WC_s2p()->logger()->log( array( 'message' => $error_msg, 'order_id' => $payment_arr['merchanttransactionid'] ) );
                    echo $error_msg;
                    exit;
                }

                WC_s2p()->logger()->log( array(
                                             'message' => 'Received '.$status_title.' notification for transaction '.$payment_arr['merchanttransactionid'].'.',
                                             'order_id' => $payment_arr['merchanttransactionid'] ) );

                // Update database according to payment status
                switch( $payment_arr['status']['id'] )
                {
                    case S2P_SDK\S2P_SDK_Meth_Payments::STATUS_OPEN:
                    case S2P_SDK\S2P_SDK_Meth_Payments::STATUS_PENDING_CUSTOMER:
                    case S2P_SDK\S2P_SDK_Meth_Payments::STATUS_PENDING_PROVIDER:
                    break;

                    case S2P_SDK\S2P_SDK_Meth_Payments::STATUS_CAPTURED:
                    case S2P_SDK\S2P_SDK_Meth_Payments::STATUS_SUCCESS:
                        $order->update_status( $plugin_settings['order_status_on_2'], WC_s2p()->__( 'Payment success!' ) );
                        $order->payment_complete();

                        WC_s2p()->logger()->log( array(
                                                     'message' => 'Payment success!',
                                                     'order_id' => $payment_arr['merchanttransactionid'] ) );
                    break;

                    case S2P_SDK\S2P_SDK_Meth_Payments::STATUS_CANCELLED:
                        $order->update_status( $plugin_settings['order_status_on_3'], WC_s2p()->__( 'Payment cancelled.' ) );
                    break;

                    case S2P_SDK\S2P_SDK_Meth_Payments::STATUS_FAILED:
                        $order->update_status( $plugin_settings['order_status_on_4'], WC_s2p()->__( 'Payment failed.' ) );
                    break;

                    case S2P_SDK\S2P_SDK_Meth_Payments::STATUS_EXPIRED:
                        $order->update_status( $plugin_settings['order_status_on_5'], WC_s2p()->__( 'Payment expired.' ) );
                    break;

                    case S2P_SDK\S2P_SDK_Meth_Payments::STATUS_PROCESSING:
                    case S2P_SDK\S2P_SDK_Meth_Payments::STATUS_AUTHORIZED:
                    break;
                }
            break;

            case $notification_obj::TYPE_PREAPPROVAL:
                WC_s2p()->logger()->log( 'Preapprovals not implemented.' );
            break;
        }

        if( $notification_obj->respond_ok() )
            WC_s2p()->logger()->log( '--- Sent OK -------------------------------' );

        else
        {
            if( $notification_obj->has_error()
            and ($error_arr = $notification_obj->get_error()) )
                $error_msg = 'Error: '.$error_arr['display_error'];
            else
                $error_msg = 'Couldn\'t send ok response.';

            WC_s2p()->logger()->log( $error_msg );
            echo $error_msg;
        }

        exit;
    }
}
