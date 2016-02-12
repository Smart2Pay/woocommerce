<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}


class WC_Gateway_Smart2Pay extends WC_Payment_Gateway
{
    const ORDER_PAYMENT_META_KEY = 's2p_payment_method';

    private $has_errors = false;

    private $payment_flow = array(
        'in_payment_flow' => false,
        'passed_validation' => false,
        'flow_parameters' => array(),
    );

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
        $this->id                 = 'smart2pay';
        $this->icon               = ''; // If you want to show an image next to the gateway’s name on the frontend, enter a URL to an image.
        $this->has_fields         = true;
        $this->method_title       = WC_s2p()->__( 'Smart2Pay' );
        $this->method_description = WC_s2p()->__( 'Secure payments through 100+ alternative payment options.' );

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title        = $this->get_option( 'title', WC_s2p()->__( 'Smart2Pay - Alternative payment methods' ) );
        $this->description  = $this->method_description;

        $this->has_errors = false;

        // Actions
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'save_payment_details' ) );

        // Displaying Payment Methods in Plugin settings page
        add_action( 'woocommerce_admin_field_smart2pay_methods', array( $this, 'smart2pay_methods_settings' ) );

        //add_action( 'woocommerce_thankyou_cheque', array( $this, 'thankyou_page' ) );

        // Customer Emails
        //add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
    }

    public function flow_parameters( $key, $val = null )
    {
        if( $key === null and $val === null )
            return $this->payment_flow['flow_parameters'];

        if( !is_string( $key ) )
            return null;

        if( $val === null )
        {
            if( isset( $this->payment_flow['flow_parameters'][$key] ) )
                return $this->payment_flow['flow_parameters'][$key];

            return null;
        }

        $this->payment_flow['flow_parameters'][$key] = $val;
        return $val;
    }

    //
    //  Begin Admin stuff
    //
    public function save_payment_details()
    {
        $this->errors = array();

        $this->validate_settings_fields();

        /** @var WC_S2P_Configured_Methods_Model $configured_methods_model */
        // validate $this->sanitized_fields array (WC validation depending on fields type)
        if( empty( $this->sanitized_fields ) or !is_array( $this->sanitized_fields ) )
            $this->add_error_message( WC_s2p()->__( 'Nothing to save...' ) );

        elseif( !($configured_methods_model = WC_s2p()->get_loader()->load_model( 'WC_S2P_Configured_Methods_Model' )) )
            $this->add_error_message( WC_s2p()->__( 'Couldn\'t obtain configured methods model. Please retry or re-install plugin.' ) );

        else
        {
            if( empty( $this->sanitized_fields['title'] ) )
                $this->add_error_message( WC_s2p()->__( 'Please provide a Title.' ) );

            if( empty( $this->sanitized_fields['environment'] )
             or !Woocommerce_Smart2pay_Environment::validEnvironment( $this->sanitized_fields['environment'] ) )
                $this->add_error_message( WC_s2p()->__( 'Please provide valid value for Environment.' ) );

            if( empty( $this->sanitized_fields['return_url'] )
             or !PHS_params::check_type( $this->sanitized_fields['return_url'], PHS_params::T_URL ) )
                $this->add_error_message( WC_s2p()->__( 'Please provide a valid Return URL.' ) );

            if( !$this->has_errors() )
            {
                switch( $this->sanitized_fields['environment'] )
                {
                    case Woocommerce_Smart2pay_Environment::ENV_TEST:
                        if( empty( $this->sanitized_fields['site_id_test'] ) )
                            $this->sanitized_fields['site_id_test'] = 0;
                        else
                            $this->sanitized_fields['site_id_test'] = intval( $this->sanitized_fields['site_id_test'] );

                        if( empty( $this->sanitized_fields['skin_id_test'] ) )
                            $this->sanitized_fields['skin_id_test'] = 0;
                        else
                            $this->sanitized_fields['skin_id_test'] = intval( $this->sanitized_fields['skin_id_test'] );

                        if( empty( $this->sanitized_fields['api_key_test'] ) )
                            $this->add_error_message( WC_s2p()->__( 'Please provide a TEST API Key.' ) );
                        if( empty( $this->sanitized_fields['site_id_test'] ) )
                            $this->add_error_message( WC_s2p()->__( 'Please provide a TEST Site ID.' ) );
                    break;

                    case Woocommerce_Smart2pay_Environment::ENV_LIVE:
                        if( empty( $this->sanitized_fields['site_id_live'] ) )
                            $this->sanitized_fields['site_id_live'] = 0;
                        else
                            $this->sanitized_fields['site_id_live'] = intval( $this->sanitized_fields['site_id_live'] );

                        if( empty( $this->sanitized_fields['skin_id_live'] ) )
                            $this->sanitized_fields['skin_id_live'] = 0;
                        else
                            $this->sanitized_fields['skin_id_live'] = intval( $this->sanitized_fields['skin_id_live'] );

                        if( empty( $this->sanitized_fields['api_key_live'] ) )
                            $this->add_error_message( WC_s2p()->__( 'Please provide a LIVE API Key.' ) );
                        if( empty( $this->sanitized_fields['site_id_live'] ) )
                            $this->add_error_message( WC_s2p()->__( 'Please provide a LIVE Site ID.' ) );
                    break;
                }
            }

            if( empty( $this->sanitized_fields['methods_display_mode'] )
             or !Woocommerce_Smart2pay_Displaymode::validDisplayMode( $this->sanitized_fields['methods_display_mode'] ) )
                $this->add_error_message( WC_s2p()->__( 'Please provide valid value for Methods display mode.' ) );

            if( !WC_S2P_Helper::check_checkbox_value( $this->sanitized_fields['product_description_ref'] )
            and empty( $this->sanitized_fields['product_description_custom'] ) )
                $this->add_error_message( WC_s2p()->__( 'Please provide a Custom product description.' ) );

            if( empty( $this->sanitized_fields['grid_column_number'] ) )
                $this->sanitized_fields['grid_column_number'] = 0;
            else
                $this->sanitized_fields['grid_column_number'] = intval( $this->sanitized_fields['grid_column_number'] );
        }

        $s2p_we_have_methods = PHS_params::_p( 's2p_we_have_methods', PHS_params::T_INT );

        $configured_methods_error = '';
        if( !empty( $s2p_we_have_methods )
        and Woocommerce_Smart2pay_Environment::validEnvironment( $this->sanitized_fields['environment'] ) )
        {
            /** @var WC_S2P_Methods_Model $methods_model */
            $methods_list_arr = array();
            if( ($methods_model = WC_s2p()->get_loader()->load_model( 'WC_S2P_Methods_Model' )) )
                $methods_list_arr = $methods_model->get_db_available_methods( $this->sanitized_fields['environment'] );

            $enabled_methods_arr = PHS_params::_p( 's2p_enabled_methods', PHS_params::T_ARRAY, array( 'type' => PHS_params::T_INT ) );
            $s2p_priority_arr = PHS_params::_p( 's2p_priority', PHS_params::T_ARRAY, array( 'type' => PHS_params::T_INT ) );
            $s2p_surcharge_arr = PHS_params::_p( 's2p_surcharge', PHS_params::T_ARRAY, array( 'type' => PHS_params::T_FLOAT, 'digits' => 2 ) );
            $s2p_fixed_amount_arr = PHS_params::_p( 's2p_fixed_amount', PHS_params::T_ARRAY, array( 'type' => PHS_params::T_FLOAT, 'digits' => 2 ) );

            $configured_methods_arr = array();
            foreach( $methods_list_arr as $method_db_id => $method_arr )
            {
                $method_id = $method_arr['method_id'];

                $configured_method_arr = array();
                $configured_method_arr['method_id'] = $method_id;
                $configured_method_arr['environment'] = $this->sanitized_fields['environment'];
                $configured_method_arr['enabled'] = (!empty( $enabled_methods_arr[$method_id] )?1:0);
                $configured_method_arr['surcharge_percent'] = (!empty( $s2p_surcharge_arr[$method_id] )?$s2p_surcharge_arr[$method_id]:0);
                $configured_method_arr['surcharge_amount'] = (!empty( $s2p_fixed_amount_arr[$method_id] )?$s2p_fixed_amount_arr[$method_id]:0);
                $configured_method_arr['priority'] = (!empty( $s2p_priority_arr[$method_id] )?$s2p_priority_arr[$method_id]:0);

                $configured_methods_arr[] = $configured_method_arr;
            }

            if( !$configured_methods_model->save_configured_methods( $configured_methods_arr, $this->sanitized_fields['environment'] ) )
            {
                if( $configured_methods_model->has_error() )
                    $configured_methods_error = $configured_methods_model->get_error_message();
                else
                    $configured_methods_error = WC_s2p()->__( 'Couldn\'t save payment methods\' settings.' );
            }
        }

        $return_val = $this->process_admin_options();

        // Add methods settings errors after saving plugin settings (if they are ok)
        if( !empty( $configured_methods_error ) )
            $this->add_error_message( $configured_methods_error );

        return $return_val;
    }

    /**
     * Admin Panel Options Processing.
     * - Saves the options to the DB.
     *
     * @since 1.0.0
     * @return bool
     */
    public function process_admin_options()
    {
        //if( count( $this->errors ) > 0 )
        //{
        //    $this->display_errors();
        //
        //    if( $this->has_errors() )
        //        return false;
        //}

        if( $this->has_errors() )
            return false;

        update_option( $this->plugin_id . $this->id . '_settings', $this->sanitized_fields );
        $this->init_settings();

        return true;
    }

    public function add_error_message( $msg )
    {
        WC_Admin_Settings::add_error( $msg );
        $this->has_errors = true;

        //if( empty( $this->errors ) or !is_array( $this->errors ) )
        //    $this->errors = array();
        //
        //if( empty( $this->errors[Woocommerce_Smart2pay_Admin_Notices::TYPE_ERROR] ) or !is_array( $this->errors[Woocommerce_Smart2pay_Admin_Notices::TYPE_ERROR] ) )
        //    $this->errors[Woocommerce_Smart2pay_Admin_Notices::TYPE_ERROR] = array();
        //
        //$this->errors[Woocommerce_Smart2pay_Admin_Notices::TYPE_ERROR][] = $msg;
    }

    public function add_success_message( $msg )
    {
        WC_Admin_Settings::add_message( $msg );

        //if( empty( $this->errors ) or !is_array( $this->errors ) )
        //    $this->errors = array();
        //
        //if( empty( $this->errors[Woocommerce_Smart2pay_Admin_Notices::TYPE_SUCCESS] ) or !is_array( $this->errors[Woocommerce_Smart2pay_Admin_Notices::TYPE_SUCCESS] ) )
        //    $this->errors[Woocommerce_Smart2pay_Admin_Notices::TYPE_SUCCESS] = array();
        //
        //$this->errors[Woocommerce_Smart2pay_Admin_Notices::TYPE_SUCCESS][] = $msg;
    }

    public function has_errors()
    {
        //return (!empty( $this->errors ) and !empty( $this->errors[Woocommerce_Smart2pay_Admin_Notices::TYPE_ERROR]));
        return $this->has_errors;
    }

    /**
     * Display admin error messages.
     *
     * @since 1.0.0
     */
    public function display_errors()
    {
        //if( empty( $this->errors ) or !is_array( $this->errors ) )
        //    return;
        //
        //$notices_arr = array();
        //foreach( $this->errors as $error_type => $errors_arr )
        //{
        //    if( empty( $errors_arr ) or !is_array( $errors_arr ) )
        //        continue;
        //
        //    if( !($notice_type = Woocommerce_Smart2pay_Admin_Notices::valid_notice_type( $error_type )) )
        //        $notice_type = Woocommerce_Smart2pay_Admin_Notices::TYPE_ERROR;
        //
        //    foreach( $errors_arr as $error_msg )
        //    {
        //        $notice_arr = Woocommerce_Smart2pay_Admin_Notices::default_custom_notification_fields();
        //
        //        $notice_arr['notice_type'] = $notice_type;
        //        $notice_arr['message']     = $error_msg;
        //
        //        $notices_arr[] = $notice_arr;
        //    }
        //}
        //
        //Woocommerce_Smart2pay_Admin_Notices_Later::add_notice( Woocommerce_Smart2pay_Admin_Notices::CUSTOM_NOTICE, $notices_arr, true );
    }

    /**
     * If There are no payment fields show the description if set.
     * Override this in your gateway if you have some.
     */
    public function payment_fields()
    {
        if( empty( WC()->customer ) or empty( WC()->customer->country ) )
        {
            echo WC_s2p()->__( 'Please select a country first.' );
            return;
        }

        /** @var WC_S2P_Methods_Model $methods_model */
        if( !($methods_model = WC_s2p()->get_loader()->load_model( 'WC_S2P_Methods_Model' )) )
        {
            echo WC_s2p()->__( 'Couldn\'t initialize Methods model.' );
            return;
        }

        // Check method from what is posted...
        if( !($s2p_method = PHS_params::_p( 's2p_method', PHS_params::T_INT ))
        and !empty( $_POST['post_data'] ) )
        {
            parse_str( $_POST['post_data'], $posted_arr );

            if( !empty( $posted_arr )
            and !empty( $posted_arr['s2p_method'] ) )
                $s2p_method = intval( $posted_arr['s2p_method'] );
        }

        // Check method in session
        if( empty( $s2p_method )
        and !($s2p_method = WC_s2p()->session_s2p_method()) )
            $s2p_method = 0;

        if( !($methods_list_arr = $methods_model->get_available_country_methods( WC()->customer->country )) )
            $methods_list_arr = array();

        if( empty( $methods_list_arr ) )
        {
            echo WC_s2p()->__( 'No payment methods available currently for selected country.' );
            return;
        }

        $show_in_grid = $this->check_settings_checkbox_value( 'show_methods_in_grid' );
        $display_surcharge = $this->check_settings_checkbox_value( 'display_surcharge' );
        $grid_column_number = ((empty( $this->settings['grid_column_number'] ) or $this->settings['grid_column_number'] > 3)?3:$this->settings['grid_column_number']);

        ?>
        <style>
        .smart2pay_payment_table, .smart2pay_payment_table td { border: 0 !important; }
        .smart2pay_payment_table td { border-bottom: 1px solid #909090 !important; }
        .s2p-method-logo-name { width: <?php echo ($show_in_grid?'100%':'130px')?>; margin: 0 5px 5px 0 !important; text-align: center !important; vertical-align: top !important; float: left; display: table; }
        .s2p-method-img { padding: 2px !important; border: 1px solid black !important; max-width: 120px; }
        .s2p-method-name { font-weight: bold; }
        .s2p-method-description { padding: 2px !important; }
        </style>
        <script type="text/javascript">
        function s2p_refresh_checkout( elem )
        {
            var jq_elem = jQuery(elem);

            if( !jq_elem || !jq_elem.is(':checked' ) )
                return;

            jQuery( document.body ).trigger( 'update_checkout' );
        }
        </script>
        <table class="smart2pay_payment_table"><?php
        $methods_in_row = 0;
        foreach( $methods_list_arr as $method_id => $method_arr )
        {
            // id, method_id, environment, enabled, surcharge_percent, surcharge_amount, surcharge_currency,
            // priority, last_update, configured, display_name, description, logo_url
            $surcharge_explained_str = '';
            if( $display_surcharge
            and ((float) $method_arr['surcharge_percent'] != 0 or (float) $method_arr['surcharge_amount'] != 0) )
            {
                $surcharge_explained_str = ' (';

                if( (float) $method_arr['surcharge_percent'] != 0 )
                    $surcharge_explained_str .= ($method_arr['surcharge_percent'] > 0 ? '+' : '') . $method_arr['surcharge_percent'] . '%';
                if( (float) $method_arr['surcharge_amount'] != 0 )
                    $surcharge_explained_str .= ((float) $method_arr['surcharge_amount'] != 0 ? ' + ' : '') . wc_price( $method_arr['surcharge_amount'], array( 'currency' => $method_arr['surcharge_currency'] ) );

                $surcharge_explained_str .= ')';
            }

            if( $show_in_grid )
            {
                if( !$methods_in_row )
                    echo '<tr>';

                ?>
                <td style="vertical-align: top !important;"><label style="width: 100%;" for="s2p-method-chck-<?php echo $method_arr['method_id'] ?>">
                    <div class="s2p-method-logo-name">
                    <input type="radio" name="s2p_method" id="s2p-method-chck-<?php echo $method_arr['method_id'] ?>" value="<?php echo $method_arr['method_id'] ?>" onfocus="this.blur()" <?php echo ($s2p_method==$method_arr['method_id']?'checked="checked"':'')?> onchange="s2p_refresh_checkout( this )" /><br/>
                    <?php

                        if( $this->settings['methods_display_mode'] == Woocommerce_Smart2pay_Displaymode::MODE_LOGO
                            or $this->settings['methods_display_mode'] == Woocommerce_Smart2pay_Displaymode::MODE_BOTH )
                        {
                            ?><img class="s2p-method-img" style=" margin: 0 auto !important;float: none;" alt="<?php echo $method_arr['display_name'] ?>" src="<?php echo $method_arr['logo_url'] ?>"/><div style="clear:both"></div><?php
                        }

                        if( $this->settings['methods_display_mode'] == Woocommerce_Smart2pay_Displaymode::MODE_TEXT
                            or $this->settings['methods_display_mode'] == Woocommerce_Smart2pay_Displaymode::MODE_BOTH )
                        {
                            if( $this->settings['methods_display_mode'] == Woocommerce_Smart2pay_Displaymode::MODE_BOTH )
                                echo '<br/>';

                            ?><span class="s2p-method-name"><?php echo $method_arr['display_name'] ?></span><div style="clear:both"></div><?php
                        }

                        if( $surcharge_explained_str != '' )
                            echo '<br/><small>' . $surcharge_explained_str . '</small><div style="clear:both"></div>';

                    ?>
                    </div>

                </label></td>
                <?php

                if( $methods_in_row+1 == $grid_column_number )
                {
                    echo '</tr>';
                    $methods_in_row = -1;
                }

                $methods_in_row++;
            } else
            {
                ?>
                <tr>
                    <td style="width:25px; vertical-align: middle;">
                        <input type="radio" name="s2p_method" id="s2p-method-chck-<?php echo $method_arr['method_id'] ?>" value="<?php echo $method_arr['method_id'] ?>" onfocus="this.blur()" <?php echo ($s2p_method==$method_arr['method_id']?'checked="checked"':'')?> onchange="s2p_refresh_checkout( this )" />
                    </td>
                    <td><label style="width: 100%;" for="s2p-method-chck-<?php echo $method_arr['method_id'] ?>">
                        <div class="s2p-method-logo-name">
                        <?php
                        if( $this->settings['methods_display_mode'] == Woocommerce_Smart2pay_Displaymode::MODE_LOGO
                         or $this->settings['methods_display_mode'] == Woocommerce_Smart2pay_Displaymode::MODE_BOTH )
                        {
                            ?><img class="s2p-method-img" style=" margin: 2px !important;" alt="<?php echo $method_arr['display_name'] ?>" src="<?php echo $method_arr['logo_url'] ?>"/><div style="clear:both"></div><?php
                        }

                        if( $this->settings['methods_display_mode'] == Woocommerce_Smart2pay_Displaymode::MODE_TEXT
                         or $this->settings['methods_display_mode'] == Woocommerce_Smart2pay_Displaymode::MODE_BOTH )
                        {
                            if( $this->settings['methods_display_mode'] == Woocommerce_Smart2pay_Displaymode::MODE_BOTH )
                                echo '<br/>';

                            ?><span class="s2p-method-name"><?php echo $method_arr['display_name'] ?></span><div style="clear:both"></div><?php
                        }

                        if( $surcharge_explained_str != '' )
                            echo '<br/><small>' . $surcharge_explained_str . '</small>';

                        ?>
                        </div>
                        <div class="s2p-method-description"><?php echo $method_arr['description'] ?></div><div style="clear:both"></div>
                    </label></td>
                </tr>
                <?php
            }
        }

        if( $show_in_grid
        and $methods_in_row )
        {
            while( $methods_in_row < $grid_column_number )
            {
                echo '<td>&nbsp;</td>';
                $methods_in_row++;
            }
        }

        ?></table><?php
    }

    /**
     * Admin Options.
     *
     * Setup the gateway settings screen.
     * Override this in your gateway.
     *
     * @since 1.0.0
     */
    public function admin_options()
    {
        ?><small>
        Smart2Pay plugin v<?php echo WC_SMART2PAY_VERSION?>,
        Smart2Pay SDK
        <?php
        if( !defined( 'S2P_SDK_VERSION' ) )
        {
            ?><span style="color:red;">NOT INSTALLED</span><?php
        } elseif( version_compare( S2P_SDK_VERSION, '1.0.29', '<' ))
        {
            ?><span style="color:red;">NOT COMPATIBLE (1.0.29 or higher required)</span><?php
        } else
        {
            echo 'v'.S2P_SDK_VERSION;
        }
        ?>
        </small><?php

        parent::admin_options();

        $this->smart2pay_methods_settings();
    }

    public function smart2pay_methods_settings()
    {
        $wc_s2p = WC_s2p();

        if( !defined( 'S2P_SDK_VERSION' ) )
        {
            ?>
            <p><span style="color:red">Smart2Pay SDK is not currently installed</span>. Smart2Pay WooCommerce plugin cannot function properly without SDK.</p>
            <p>In order to install please make sure you install it in <strong><?php echo $wc_s2p->plugin_path().'includes/sdk'?></strong> directory.</p>
            <p>SDK doesn't need any configuration, it only has to be copied to the provided path.</p>
            <?php
        } else
        {
            ?>
            <p class="submit"><input name="save" class="button-primary" type="submit" value="Save changes" /></p>

            <div id="s2p_sync_protection" style="display: none; position: absolute; top: 0px; left: 0px; width: 100%; height: 100%; z-index: 10000;">
                <div style="position: relative; width: 100%; height: 100%;">
                    <div style="position: absolute; top: 0px; left: 0px; width: 100%; height: 100%; background: #333; opacity: 0.5; filter:alpha(opacity=50)"></div>
                    <div style="position: absolute; top: 0px; left: 0px; width: 100%; height: 100%;">
                        <div id="iframe-wrapper" style="position: fixed; display: table; margin: 0px auto; margin-top: 50px; width: 100%">
                            <div style="margin: 0px auto; display: table;">

                                <div id="s2p_loading_content" style="margin: 20% auto 0 auto; width:80%; background-color: white;border: 2px solid lightgrey; text-align: center; padding: 40px;">
                                    <div class="ajax-loader" title="Loading..."></div>
                                    <p style="margin: 20px auto;" id="s2p_protection_message">Syncronizing. Please wait...</p>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <a name="smart2pay_methods"></a>
            <h3>Payment Methods</h3>
            <?php
            if( PHS_params::_g( 'sync_methods' )
            and !PHS_params::_p( 'save' )
            and ($sdk_interface = new WC_S2P_SDK_Interface()) )
            {
                if( !$sdk_interface->refresh_available_methods( $this->settings ) )
                {
                    $error_msg = 'Couldn\'t syncronize payment methods with Smart2Pay servers. Please try again later.';
                    if( $sdk_interface->has_error() )
                        $error_msg = $sdk_interface->get_error_message();

                    ?>
                    <div style="border-left: 4px solid #dc3232;background: #fff;margin 15px 0;padding: 1px 12px;">
                    <p><strong><?php echo $error_msg?></strong></p>
                    </div>
                    <?php
                } else
                {
                    ?>
                    <div style="border-left: 4px solid green;background: #fff;margin 15px 0;padding: 1px 12px;">
                    <p><strong>Payment methods syncronized with success.</strong></p>
                    </div>
                    <script type="text/javascript">
                    jQuery(document).ready(function() {
                        //clear_sync_parameters();
                    });
                    </script>
                    <?php
                }

                ?>
                <script type="text/javascript">
                 jQuery(document).ready(function() {
                     hide_protection();
                 });
                </script>
                <?php
            }

            $methods_list_arr = array();
            $methods_countries_arr = array();
            $methods_configured_arr = array();

            /** @var WC_S2P_Methods_Model $methods_model */
            if( ($methods_model = WC_s2p()->get_loader()->load_model( 'WC_S2P_Methods_Model' )) )
            {
                if( !($methods_list_arr = $methods_model->get_db_available_methods( $this->settings['environment'] )) )
                    $methods_list_arr = array();

                if( !($methods_countries_arr = $methods_model->get_countries_per_method()) )
                    $methods_countries_arr = array();
            }

            /** @var WC_S2P_Configured_Methods_Model $configured_methods_model */
            if( ($configured_methods_model = WC_s2p()->get_loader()->load_model( 'WC_S2P_Configured_Methods_Model' )) )
                $methods_configured_arr = $configured_methods_model->get_db_methods( $this->settings['environment'] );

            if( !($last_sync_date = WC_S2P_SDK_Interface::last_methods_sync_option()) )
                $last_sync_date = false;
            ?>
            <p>
                Currently displaying payment methods for <strong><?php echo $this->settings['environment'] ?></strong> environment.
                In order to update payment methods for other environments please select desired environment from <em>Environment</em> drop-down option and then save settings.
            </p>

            <p><small>Methods syncronised for <strong><?php echo $this->settings['environment'] ?></strong> environment:
                    <?php echo (empty( $last_sync_date )?'Never':WC_S2P_Helper::pretty_date_display( $last_sync_date ))?></small></p>

            <?php
            if( empty( $methods_list_arr ) )
            {
                ?>
                <p>
                It appears that you don't have any payment methods currently in database for <strong><?php echo $this->settings['environment'] ?></strong> environment.
                In order to obtain available payment methods for current plugin setup you will have to syncronize your database with our servers.
                </p>
                <p>
                    <a href="javascript:start_syncronization()" class="button-primary">Syncronize Now</a>
                </p>
                <?php
            } else
            {
                ?>
                <input type="hidden" name="s2p_we_have_methods" value="1" />
                <p>
                    <a href="javascript:start_syncronization()" class="button-primary">Re-Syncronize Methods</a>
                </p>

                <p style="margin-bottom: 0 !important;"><?php echo count( $methods_list_arr )?> payment methods currently available.</p>

                <style>
                .s2p_section_togglable { cursor: pointer; }
                .s2p-method-img { vertical-align: middle; max-height: 40px; max-width: 130px; }
                .sp2-middle-all { text-align: center; vertical-align: middle; }
                .s2p-method-img-td { height: 50px; width: 134px; text-align: center; }
                </style>

                <small>Higher priority means method will be displayed upper in the list.</small><br/>
                NOTE: Surcharges will be calculated from cart subtotal + shipping price. Additional taxes and fees will not be included in surcharge calculation.<br/>
                <div style="clear: both;"></div>
                <table class="form-table">
                <thead>
                <tr>
                    <th style="width: 60px;">Enabled?</th>
                    <th style="width: 90px;">Priority</th>
                    <th style="width: 90px;">Surcharge</th>
                    <th colspan="2">Method</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td colspan="5">
                        <a href="javascript:void(0);" onclick="s2p_config_js_select_all()">Select all</a>
                        |
                        <a href="javascript:void(0);" onclick="s2p_config_js_invert()">Invert</a>
                        |
                        <a href="javascript:void(0);" onclick="s2p_config_js_deselect_all()">Select none</a>

                    </td>
                </tr>
                <?php
                $wc_currency = get_woocommerce_currency();
                foreach( $methods_list_arr as $db_id => $method_arr )
                {
                    $method_settings = false;
                    if( !empty( $methods_configured_arr[$method_arr['method_id']] ) and is_array( $methods_configured_arr[$method_arr['method_id']] ) )
                        $method_settings = $methods_configured_arr[$method_arr['method_id']];

                    ?>
                    <tr>
                        <td class="sp2-middle-all"><input type="checkbox" name="s2p_enabled_methods[<?php echo $method_arr['method_id']?>]" id="s2p_enabled_method_<?php echo $method_arr['method_id']?>" value="<?php echo $method_arr['method_id']?>" <?php echo (!empty( $method_settings['enabled'] )?'checked="checked"':'')?> /></td>
                        <td>
                            <div style="padding:2px; clear:both;">
                                <input type="text" class="input-text" style="width: 50px !important; text-align: right;" name="s2p_priority[<?php echo $method_arr['method_id']?>]" id="s2p_priority_<?php echo $method_arr['method_id']?>" value="<?php echo ((!empty( $method_settings ) and isset( $method_settings['priority'] ))?$method_settings['priority']:0)?>" />
                            </div>
                        </td>
                        <td>
                            <div style="padding:2px; clear:both;">
                                <input type="text" class="input-text" style="width: 50px !important; text-align: right;" name="s2p_surcharge[<?php echo $method_arr['method_id']?>]" id="s2p_surcharge_<?php echo $method_arr['method_id']?>" value="<?php echo ((!empty( $method_settings ) and isset( $method_settings['surcharge_percent'] ))?$method_settings['surcharge_percent']:0)?>" />%
                            </div>

                            <div style="padding:2px; clear:both;">
                                <input type="text" class="input-text" style="width: 50px !important; text-align: right;" name="s2p_fixed_amount[<?php echo $method_arr['method_id']?>]" id="s2p_fixed_amount_<?php echo $method_arr['method_id']?>" value="<?php echo ((!empty( $method_settings ) and isset( $method_settings['surcharge_amount'] ))?$method_settings['surcharge_amount']:0)?>" /> <?php echo $wc_currency?>
                            </div>

                        </td>
                        <td class="s2p-method-img-td"><img src="<?php echo $method_arr['logo_url']?>" class="s2p-method-img" /></td>
                        <td>
                            <strong><?php echo $method_arr['display_name']?></strong> (#<?php echo $method_arr['method_id']?>)<br/>
                            <?php echo $method_arr['description']?><br/>

                            <?php
                            if( !empty( $methods_countries_arr[$method_arr['method_id']] ) )
                            {
                                ?><strong>Available in following countries</strong>: <?php
                                $first_country = true;
                                foreach( $methods_countries_arr[$method_arr['method_id']] as $country_code => $country_name )
                                {
                                    echo ($first_country?'':', ').$country_name.' ('.$country_code.')';

                                    $first_country = false;
                                }
                                ?>.<?php
                            }
                            ?>
                        </td>
                    </tr>
                    <?php
                }
                ?></tbody></table><?php
            }

            ?>
            <script type="text/javascript">
            jQuery(document).on( 'change', '#woocommerce_smart2pay_environment', function(e)
            {
                refresh_fields();
            });

            jQuery(document).ready(function() {
                refresh_fields();

                jQuery(".s2p_section_togglable").on( 'click', function(e)
                {
                    var next_table = jQuery(this).next('.form-table');
                    if( next_table )
                    {
                        if( next_table.is( ':visible' ) )
                        {
                            next_table.hide();
                            jQuery(this).find('span').removeClass( 'dashicons-arrow-up-alt2' ).addClass( 'dashicons-arrow-down-alt2' );
                        } else
                        {
                            next_table.show();
                            jQuery(this).find('span').removeClass( 'dashicons-arrow-down-alt2' ).addClass( 'dashicons-arrow-up-alt2' );
                        }
                    }
                });
            });

            function hide_protection()
            {
                var protection_container_obj = jQuery("#s2p_sync_protection");
                if( protection_container_obj )
                {
                    protection_container_obj.hide();
                }
            }

            function show_protection( msg )
            {
                var protection_container_obj = jQuery("#s2p_sync_protection");
                if( protection_container_obj )
                {
                    protection_container_obj.appendTo('body');
                    protection_container_obj.show();
                    protection_container_obj.css({height: document.getElementsByTagName('html')[0].scrollHeight});
                }

                var protection_message_obj = jQuery("#s2p_protection_message");
                if( protection_message_obj )
                {
                    protection_message_obj.html( msg );
                }
            }

            function start_syncronization()
            {
                show_protection( 'Syncronizing. Please wait...' );

                document.location = '<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_smart2pay&sync_methods=1' )?>&_r=' + Math.random() + '#smart2pay_methods';
            }

            function clear_sync_parameters()
            {
                show_protection( 'Reloading page. Please wait...' );

                document.location = '<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_smart2pay' )?>&_r=' + Math.random() + '#smart2pay_methods';
            }

            function refresh_fields()
            {
                var current_val = jQuery('#woocommerce_smart2pay_environment').val();

                if( current_val == '<?php echo Woocommerce_Smart2pay_Environment::ENV_TEST ?>' )
                {
                    s2p_test_elements( true );
                    s2p_live_elements( false );
                } else if( current_val == '<?php echo Woocommerce_Smart2pay_Environment::ENV_LIVE ?>' )
                {
                    s2p_test_elements( false );
                    s2p_live_elements( true );
                } else
                {
                    s2p_test_elements( false );
                    s2p_live_elements( false );
                }
            }

            function s2p_live_elements( show )
            {
                var apikey_obj = jQuery('#woocommerce_smart2pay_api_key_live');
                if( apikey_obj )
                {
                    if( show )
                        apikey_obj.parent().parent().parent().show();
                    else
                        apikey_obj.parent().parent().parent().hide();
                }
                var site_id_obj = jQuery('#woocommerce_smart2pay_site_id_live');
                if( site_id_obj )
                {
                    if( show )
                        site_id_obj.parent().parent().parent().show();
                    else
                        site_id_obj.parent().parent().parent().hide();
                }
                var skin_id_obj = jQuery('#woocommerce_smart2pay_skin_id_live');
                if( skin_id_obj )
                {
                    if( show )
                        skin_id_obj.parent().parent().parent().show();
                    else
                        skin_id_obj.parent().parent().parent().hide();
                }
            }

            function s2p_test_elements( show )
            {
                var apikey_obj = jQuery('#woocommerce_smart2pay_api_key_test');
                if( apikey_obj )
                {
                    if( show )
                        apikey_obj.parent().parent().parent().show();
                    else
                        apikey_obj.parent().parent().parent().hide();
                }
                var site_id_obj = jQuery('#woocommerce_smart2pay_site_id_test');
                if( site_id_obj )
                {
                    if( show )
                        site_id_obj.parent().parent().parent().show();
                    else
                        site_id_obj.parent().parent().parent().hide();
                }
                var skin_id_obj = jQuery('#woocommerce_smart2pay_skin_id_test');
                if( skin_id_obj )
                {
                    if( show )
                        skin_id_obj.parent().parent().parent().show();
                    else
                        skin_id_obj.parent().parent().parent().hide();
                }
            }

            function s2p_config_js_select_all()
            {
                form_obj = document.getElementById( 'mainform' );
                if( form_obj && form_obj.elements && form_obj.elements.length )
                {
                    for( i = 0; i < form_obj.elements.length; i++ )
                    {
                        if( form_obj.elements[i].type == 'checkbox' && form_obj.elements[i].name.substring( 0, 20 ) == 's2p_enabled_methods[' )
                        {
                            if( !form_obj.elements[i].checked )
                                form_obj.elements[i].click();
                        }
                    }
                }
            }
            function s2p_config_js_deselect_all()
            {
                form_obj = document.getElementById( 'mainform' );
                if( form_obj && form_obj.elements && form_obj.elements.length )
                {
                    for( i = 0; i < form_obj.elements.length; i++ )
                    {
                        if( form_obj.elements[i].type == 'checkbox' && form_obj.elements[i].name.substring( 0, 20 ) == 's2p_enabled_methods[' )
                        {
                            if( form_obj.elements[i].checked )
                                form_obj.elements[i].click();
                        }
                    }
                }
            }
            function s2p_config_js_invert()
            {
                form_obj = document.getElementById( 'mainform' );
                if( form_obj && form_obj.elements && form_obj.elements.length )
                {
                    for( i = 0; i < form_obj.elements.length; i++ )
                    {
                        if( form_obj.elements[i].type == 'checkbox' && form_obj.elements[i].name.substring( 0, 20 ) == 's2p_enabled_methods[' )
                        {
                            form_obj.elements[i].click();
                        }
                    }
                }
            }
            </script>
            <?php
        }
    }

    public function check_settings_checkbox_value( $field )
    {
        return (!empty( $this->settings[$field] ) and $this->settings[$field] == 'yes');
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields()
    {
        $wc_s2p = WC_s2p();

        $this->form_fields = array(
            'section_general' => array(
                'title'   => WC_s2p()->__( 'General Settings' ),
                'type'    => 'title',
            ),
            'enabled' => array(
                'title'   => WC_s2p()->__( 'Enabled' ),
                'type'    => 'checkbox',
                'label'   => WC_s2p()->__( 'Enable Smart2Pay payment gateway' ),
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => WC_s2p()->__( 'Method Title' ),
                'type'        => 'text',
                'description' => WC_s2p()->__( 'This controls the title which the user sees during checkout.' ),
                'default'     => 'Smart2Pay - Alternative payment methods',
                'desc_tip'    => true,
            ),
            'environment' => array(
                'title'       => WC_s2p()->__( 'Environment' ),
                'type'        => 'select',
                'description' => WC_s2p()->__( 'To obtain your credentials for live and test environments, please contact us at <a href="mailto:support@smart2pay.com">support@smart2pay.com</a>.' ),
                'options'     => Woocommerce_Smart2pay_Environment::toOptionArray(),
                'default'     => Woocommerce_Smart2pay_Environment::ENV_DEMO,
            ),
            'api_key_live' => array(
                'title'       => WC_s2p()->__( 'LIVE API Key' ),
                'type'        => 'text',
                'default'     => '',
                'description' => WC_s2p()->__( 'API Key is required when communicating with Smart2Pay servers.' ),
            ),
            'site_id_live' => array(
                'title'       => WC_s2p()->__( 'LIVE Site ID' ),
                'type'        => 'text',
                'default'     => 0,
            ),
            'skin_id_live' => array(
                'title'       => WC_s2p()->__( 'LIVE Skin ID' ),
                'type'        => 'text',
                'default'     => 0,
            ),
            'api_key_test' => array(
                'title'       => WC_s2p()->__( 'TEST API Key' ),
                'type'        => 'text',
                'default'     => '',
                'description' => WC_s2p()->__( 'API Key is required when communicating with Smart2Pay servers.' ),
            ),
            'site_id_test' => array(
                'title'       => WC_s2p()->__( 'TEST Site ID' ),
                'type'        => 'text',
                'default'     => 0,
            ),
            'skin_id_test' => array(
                'title'       => WC_s2p()->__( 'TEST Skin ID' ),
                'type'        => 'text',
                'default'     => 0,
            ),
            'return_url' => array(
                'title'       => WC_s2p()->__( 'Return URL' ),
                'type'        => 'text',
                'default'     => '',
                'description' => WC_s2p()->__( 'Default' ).': '.WC_S2P_Helper::get_slug_internal_page_url( $wc_s2p::PAGE_SLUG_RETURN ).'<br/>'.
                                 '!!! '.WC_s2p()->__( 'Please set Notification URL in Smart2Pay Dashboard to: ' ).WC_S2P_Helper::notification_url(),
            ),

            'section_display' => array(
                'title' => WC_s2p()->__( 'Display Settings' ),
                'type' => 'title',
            ),
            'display_surcharge' => array(
                'title' => WC_s2p()->__( 'Display Surcharge' ),
                'type' => 'checkbox',
                'label' => WC_s2p()->__( 'Display surcharge amounts to client?' ),
                'default' => 'no',
            ),
            'methods_display_mode' => array(
                'title' => WC_s2p()->__( 'Methods display mode' ),
                'type' => 'select',
                'description' => WC_s2p()->__( 'This controls the way payment methods will be presented to client' ),
                'options' => Woocommerce_Smart2pay_Displaymode::toOptionArray(),
                'default' => Woocommerce_Smart2pay_Displaymode::MODE_BOTH,
            ),
            'show_methods_in_grid' => array(
                'title' => WC_s2p()->__( 'Show methods in grid' ),
                'type' => 'checkbox',
                'description' => WC_s2p()->__( 'By default, methods will be displayed as a two columns table, having payment method\'s logo or name and description. When checked, description is omitted, and columns number can be specified bellow.' ),
                'default' => 'no',
            ),
            'grid_column_number' => array(
                'title' => WC_s2p()->__( 'Grid column number' ),
                'type' => 'text',
                'description' => WC_s2p()->__( 'Please provide a number, if left blank, the default value is 3 (This value is used only if above option is checked)' ),
                'default' => 3,
            ),
            'product_description_ref' => array(
                'title' => WC_s2p()->__( 'Send order number as product description' ),
                'type' => 'checkbox',
                'description' => WC_s2p()->__( 'If not checked, system will send below custom description' ),
                'default' => 'yes',
            ),
            'product_description_custom' => array(
                'title' => WC_s2p()->__( 'Custom product description' ),
                'type' => 'textarea',
                'default' => '',
            ),


            'section_orders' => array(
                'title' => WC_s2p()->__( 'Order Related Settings' ),
                'type' => 'title',
            ),
            'order_status' => array(
                'title' => WC_s2p()->__( 'New order status' ),
                'type' => 'select',
                'description' => WC_s2p()->__( 'Status of order right before redirecting to payment page.' ),
                'options' => wc_get_order_statuses(),
                'default' => 'wc-on-hold',
            ),
            'order_status_on_2' => array(
                'title' => WC_s2p()->__( 'Order satus on SUCCESS' ),
                'type' => 'select',
                'description' => WC_s2p()->__( 'Status of order when payment is with success.' ),
                'options' => wc_get_order_statuses(),
                'default' => 'wc-processing',
            ),
            'order_status_on_3' => array(
                'title' => WC_s2p()->__( 'Order satus on CANCEL' ),
                'type' => 'select',
                'description' => WC_s2p()->__( 'Status of order when transaction is cancelled.' ),
                'options' => wc_get_order_statuses(),
                'default' => 'wc-cancelled',
            ),
            'order_status_on_4' => array(
                'title' => WC_s2p()->__( 'Order satus on FAIL' ),
                'type' => 'select',
                'description' => WC_s2p()->__( 'Status of order when transaction fails.' ),
                'options' => wc_get_order_statuses(),
                'default' => 'wc-failed',
            ),
            'order_status_on_5' => array(
                'title' => WC_s2p()->__( 'Order satus on EXPIRED' ),
                'type' => 'select',
                'description' => WC_s2p()->__( 'Status of order when transaction expires.' ),
                'options' => wc_get_order_statuses(),
                'default' => 'wc-cancelled',
            ),

            'section_payment_flow' => array(
                'title' => WC_s2p()->__( 'Payment Flow Settings' ),
                'type' => 'title',
            ),
            'message_data_2' => array(
                'title' => WC_s2p()->__( 'Success message' ),
                'type' => 'textarea',
                'default' => WC_s2p()->__( 'Thank you, the transaction has been processed successfuly. After we receive the final confirmation, we will release the goods.' ),
            ),
            'message_data_4' => array(
                'title' => WC_s2p()->__( 'Failed message' ),
                'type' => 'textarea',
                'default' => WC_s2p()->__( 'There was a problem processing your payment. Please try again.' ),
            ),
            'message_data_3' => array(
                'title' => WC_s2p()->__( 'Cancelled message' ),
                'type' => 'textarea',
                'default' => WC_s2p()->__( 'You have canceled the payment.' ),
            ),
            'message_data_7' => array(
                'title' => WC_s2p()->__( 'Pending message' ),
                'type' => 'textarea',
                'default' => WC_s2p()->__( 'Thank you, the transaction is pending. After we receive the final confirmation, we will release the goods.' ),
            ),
        );

        foreach( $this->form_fields as $field_name => $field_arr )
        {
            if( $field_arr['type'] != 'title' )
                continue;

            $this->form_fields[$field_name]['title'] .= ' <span class="dashicons dashicons-arrow-up-alt2"></span>';

            if( empty( $this->form_fields[$field_name]['class'] ) )
                $this->form_fields[$field_name]['class'] = 's2p_section_togglable';
            else
                $this->form_fields[$field_name]['class'] .= ' s2p_section_togglable';
        }
    }

    /**
     * Output for the order received page.
     */
    public function thankyou_page() {
        if ( $this->instructions )
            echo wpautop( wptexturize( $this->instructions ) );
    }

    /**
     * Add content to the WC emails.
     *
     * @access public
     * @param WC_Order $order
     * @param bool $sent_to_admin
     * @param bool $plain_text
     */
    public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
        if ( $this->instructions && ! $sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'on-hold' ) ) {
            echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
        }
    }

    /**
     * Validate frontend fields.
     *
     * Validate payment fields on the frontend.
     *
     * @return bool
     */
    public function validate_fields()
    {
        if( defined( 'WOOCOMMERCE_CHECKOUT' ) and constant( 'WOOCOMMERCE_CHECKOUT' ) )
            $this->payment_flow['in_payment_flow'] = true;

        if( empty( WC()->checkout()->posted )
         or empty( WC()->checkout()->posted['payment_method'] )
         or WC()->checkout()->posted['payment_method'] != $this->id )
            return false;

        if( empty( $this->settings['environment'] )
         or !Woocommerce_Smart2pay_Environment::validEnvironment( $this->settings['environment'] ) )
        {
            wc_add_notice( WC_s2p()->__( 'Invalid environment setup for Smart2Pay plugin.' ), 'error' );
            return false;
        }

        $s2p_method = PHS_params::_p( 's2p_method', PHS_params::T_INT );
        if( empty( $s2p_method ) )
        {
            wc_add_notice( WC_s2p()->__( 'Please select a payment method first.' ), 'error' );
            return false;
        }

        if( empty( WC()->customer )
         or empty( WC()->customer->country ) )
        {
            wc_add_notice( WC_s2p()->__( 'Please select a country first.' ), 'error' );
            return false;
        }

        /** @var WC_S2P_Methods_Model $methods_model */
        $country = WC()->customer->country;
        if( !($methods_model = WC_s2p()->get_loader()->load_model( 'WC_S2P_Methods_Model' ))
         or !($method_details_arr = $methods_model->get_method_details_for_country( $s2p_method, $country, $this->settings['environment'] )) )
        {
            wc_add_notice( WC_s2p()->__( 'Couldn\'t get payment method details.' ), 'error' );
            return false;
        }

        $surcharge_amount_percent = 0;
        $surcharge_total_amount = 0;
        if(
            (!empty( $method_details_arr['surcharge_percent'] ) and (float)$method_details_arr['surcharge_percent'])
            or
            (!empty( $method_details_arr['surcharge_amount'] ) and (float)$method_details_arr['surcharge_amount'])
        )
        {
            // Apply surcharge
            $percentage = (float)$method_details_arr['surcharge_percent'];
            $surcharge_amount_percent = ((WC()->cart->cart_contents_total + WC()->cart->shipping_total) * $percentage / 100);
            $surcharge_total_amount = $surcharge_amount_percent + (float)$method_details_arr['surcharge_amount'];
        }

        $method_details_arr['surcharge_amount_percent'] = $surcharge_amount_percent;
        $method_details_arr['surcharge_total_amount'] = $surcharge_total_amount;

        if( defined( 'WOOCOMMERCE_CHECKOUT' ) and constant( 'WOOCOMMERCE_CHECKOUT' ) )
        {
            $this->payment_flow['passed_validation'] = true;
            $this->flow_parameters( 's2p_method_details', $method_details_arr );
        }

        return true;
    }

    /**
     * Process the payment and return the result
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment( $order_id )
    {
        if( !($order = wc_get_order( $order_id )) )
        {
            wc_add_notice( WC_s2p()->__( 'Couldn\'t obtain order details.' ), 'error' );
            return array();
        }

        if( empty( $this->payment_flow['passed_validation'] )
         or !($method_details = $this->flow_parameters( 's2p_method_details' )) )
        {
            wc_add_notice( WC_s2p()->__( 'Couldn\'t obtain payment method details.' ), 'error' );
            return array();
        }

        /** @var WC_S2P_Transactions_Model $transactions_model */
        if( !($transactions_model = WC_s2p()->get_loader()->load_model( 'WC_S2P_Transactions_Model' )) )
        {
            wc_add_notice( WC_s2p()->__( 'Couldn\'t obtain transactions model. Please retry.' ) );
            return array();
        }

        if( !($sdk_interface = new WC_S2P_SDK_Interface()) )
        {
            wc_add_notice( WC_s2p()->__( 'Couldn\'t initiate SDK instance.' ), 'error' );
            return array();
        }

        $order_amount = number_format( $order->order_total, 2, '.', '' );
        $order_centimes = $order_amount * 100;

        $fields_meta_data = array( 'method_id', 'environment', 'surcharge_percent', 'surcharge_amount', 'surcharge_currency',
                                   'surcharge_amount_percent', 'surcharge_total_amount' );
        $transaction_arr = array();
        foreach( $fields_meta_data as $field )
        {
            if( array_key_exists( $field, $method_details ) )
                $transaction_arr[$field] = $method_details[$field];
        }

        $site_id = 0;
        if( ($api_credentials = $sdk_interface->get_api_credentials( $this->settings )) )
        {
            $site_id = $api_credentials['site_id'];
        }

        $transaction_arr['site_id'] = $site_id;
        $transaction_arr['order_id'] = $order->id;
        $transaction_arr['amount'] = $order_amount;
        $transaction_arr['currency'] = $order->get_order_currency();

        if( !($transaction_db_arr = $transactions_model->save_transaction( $transaction_arr )) )
        {
            wc_add_notice( WC_s2p()->__( 'Couldn\'t save transaction details to database.' ), 'error' );
            return array();
        }

        if( $this->settings['environment'] != Woocommerce_Smart2pay_Environment::ENV_DEMO )
            $merchant_transaction_id = $order->id;
        else
            $merchant_transaction_id = WC_S2P_Helper::convert_to_demo_merchant_transaction_id( $order->id );

        if( $this->check_settings_checkbox_value( 'product_description_ref' ) )
            $description = WC_s2p()->__( 'Ref. no.: ' ).$order->id;
        else
            $description = $this->settings['product_description_custom'];

        $payment_arr = array();
        $payment_arr['merchanttransactionid'] = $merchant_transaction_id;
        $payment_arr['amount'] = $order_centimes;
        $payment_arr['currency'] = $order->get_order_currency();
        $payment_arr['methodid'] = $method_details['method_id'];
        $payment_arr['description'] = $description;
        $payment_arr['customer'] = array(
            'email' => $order->billing_email,
            'firstname' => $order->shipping_first_name,
            'lastname' => $order->shipping_last_name,
            'phone' => $order->billing_phone,
            'company' => $order->billing_company,
        );

        $street_str = '';
        $street_number_str = '';
        if( strlen( $order->billing_address_1 ) > 100 )
        {
            $street_str = WC_S2P_Helper::mb_substr( $order->billing_address_1, 0, 100 );
            $street_number_str = WC_S2P_Helper::mb_substr( $order->billing_address_1, 100 );
        }

        $street_number_str .= $order->billing_address_2;

        $payment_arr['billingaddress'] = array(
            'country' => $order->billing_country, // ISO 2 chars country code
            'city' => $order->billing_city,
            'zipcode' => $order->billing_postcode,
            'state' => $order->billing_state,
            'street' => $street_str,
            'streetnumber' => $street_number_str,
            //'housenumber' => '',
            //'houseextension' => '',
        );

        $street_str = '';
        $street_number_str = '';
        if( strlen( $order->shipping_address_1 ) > 100 )
        {
            $street_str = WC_S2P_Helper::mb_substr( $order->shipping_address_1, 0, 100 );
            $street_number_str = WC_S2P_Helper::mb_substr( $order->shipping_address_1, 100 );
        }

        $street_number_str .= $order->shipping_address_2;

        $payment_arr['shippingaddress'] = array(
            'country' => $order->shipping_country,
            'city' => $order->shipping_city,
            'zipcode' => $order->shipping_postcode,
            'state' => $order->shipping_state,
            'street' => $street_str,
            'streetnumber' => $street_number_str,
            //'housenumber' => '',
            //'houseextension' => '',
        );

        // Get order items and send it as articles...
        if( ($products_arr = WC_S2P_Helper::get_order_products( $order ))
        and is_array( $products_arr ) )
            $payment_arr['articles'] = $products_arr;

        if( !($payment_request = $sdk_interface->init_payment( $payment_arr, $this->settings )) )
        {
            if( !$sdk_interface->has_error() )
                wc_add_notice( WC_s2p()->__( 'Couldn\'t initiate request to server.' ), 'error' );
            else
                wc_add_notice( $sdk_interface->get_error_message(), 'error' );
            return array();
        }

        $transaction_arr = array();
        $transaction_arr['order_id'] = $order->id;
        $transaction_arr['payment_id'] = (!empty( $payment_request['id'] )?$payment_request['id']:0);
        $transaction_arr['payment_status'] = ((!empty( $payment_request['status'] ) and !empty( $payment_request['status']['id'] ))?$payment_request['status']['id']:0);

        $extra_data_arr = array();
        if( !empty( $payment_request['referencedetails'] ) and is_array( $payment_request['referencedetails'] ) )
        {
            foreach( $payment_request['referencedetails'] as $key => $val )
            {
                if( is_null( $val ) )
                    continue;

                $extra_data_arr[$key] = $val;
            }
        }

        if( !empty( $extra_data_arr ) )
            $transaction_arr['extra_data'] = $extra_data_arr;

        if( !($transaction_db_arr = $transactions_model->save_transaction( $transaction_arr )) )
        {
            // Just log the error and don't break payment flow...
            WC_s2p()->logger()->log( 'Error updating transaction for order ['.$order->id.'].' );
        }

        // Mark as on-hold (we're awaiting the cheque)
        $order->update_status( $this->settings['order_status'], WC_s2p()->__( 'Initiating payment...' ) );

        // Reduce stock levels
        $order->reduce_order_stock();

        // Remove cart
        WC()->cart->empty_cart();

        // Return thankyou redirect
        return array(
            'result' 	=> 'success',
            'redirect'	=> (!empty( $payment_request['redirecturl'] )?$payment_request['redirecturl']:$this->get_return_url( $order )),
        );
    }
}
