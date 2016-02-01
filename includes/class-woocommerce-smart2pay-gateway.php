<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}


class WC_Gateway_Smart2Pay extends WC_Payment_Gateway
{
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

        // Actions
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'save_payment_details' ) );

        // Displaying Payment Methods in Plugin settings page
        add_action( 'woocommerce_admin_field_smart2pay_methods', array( $this, 'smart2pay_methods_settings' ) );

        //add_action( 'woocommerce_thankyou_cheque', array( $this, 'thankyou_page' ) );

        // Customer Emails
        //add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
    }

    public function save_payment_details()
    {
        $this->errors = array();

        $this->validate_settings_fields();

        // validate $this->sanitized_fields array (WC validation depending on fields type)
        if( empty( $this->sanitized_fields ) or !is_array( $this->sanitized_fields ) )
            $this->add_error_message( WC_s2p()->__( 'Nothing to save...' ) );

        else
        {
            // transform checkboxes from "yes" / "no" to 0 / 1
            if( !empty( $this->form_fields ) and is_array( $this->form_fields ) )
            {
                foreach( $this->form_fields as $field_name => $field_arr )
                {
                    // skip "enabled" checkbox as it has special treatment by WooCommerce
                    if( empty( $field_arr ) or !is_array( $field_arr )
                     or empty( $field_arr['type'] )
                     or $field_name == 'enabled'
                     or $field_arr['type'] != 'checkbox' )
                        continue;

                    if( empty( $this->sanitized_fields[$field_name] ) )
                        $this->sanitized_fields[$field_name] = 0;
                    elseif( $this->sanitized_fields[$field_name] == 'yes' )
                        $this->sanitized_fields[$field_name] = 1;
                    else
                        $this->sanitized_fields[$field_name] = 0;
                }
            }

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

            if( !empty( $this->sanitized_fields['product_description_ref'] )
            and empty( $this->sanitized_fields['product_description_custom'] ) )
                $this->add_error_message( WC_s2p()->__( 'Please provide a Custom product description.' ) );

            if( empty( $this->sanitized_fields['grid_column_number'] ) )
                $this->sanitized_fields['grid_column_number'] = 0;
            else
                $this->sanitized_fields['grid_column_number'] = intval( $this->sanitized_fields['grid_column_number'] );
        }

        return $this->process_admin_options();
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
        if ( count( $this->errors ) > 0 )
        {
            $this->display_errors();
            return false;
        }

        update_option( $this->plugin_id . $this->id . '_settings', $this->sanitized_fields );
        $this->init_settings();

        return true;
    }

    public function add_error_message( $msg )
    {
        if( empty( $this->errors ) or !is_array( $this->errors ) )
            $this->errors = array();

        if( empty( $this->errors[Woocommerce_Smart2pay_Admin_Notices::TYPE_ERROR] ) or !is_array( $this->errors[Woocommerce_Smart2pay_Admin_Notices::TYPE_ERROR] ) )
            $this->errors[Woocommerce_Smart2pay_Admin_Notices::TYPE_ERROR] = array();

        $this->errors[Woocommerce_Smart2pay_Admin_Notices::TYPE_ERROR][] = $msg;
    }

    public function add_success_message( $msg )
    {
        if( empty( $this->errors ) or !is_array( $this->errors ) )
            $this->errors = array();

        if( empty( $this->errors[Woocommerce_Smart2pay_Admin_Notices::TYPE_SUCCESS] ) or !is_array( $this->errors[Woocommerce_Smart2pay_Admin_Notices::TYPE_SUCCESS] ) )
            $this->errors[Woocommerce_Smart2pay_Admin_Notices::TYPE_SUCCESS] = array();

        $this->errors[Woocommerce_Smart2pay_Admin_Notices::TYPE_SUCCESS][] = $msg;
    }

    public function has_errors()
    {
        return (!empty( $this->errors ) and !empty( $this->errors[Woocommerce_Smart2pay_Admin_Notices::TYPE_ERROR]));
    }

    /**
     * Display admin error messages.
     *
     * @since 1.0.0
     */
    public function display_errors()
    {
        if( empty( $this->errors ) or !is_array( $this->errors ) )
            return;

        $notices_arr = array();
        foreach( $this->errors as $error_type => $errors_arr )
        {
            if( empty( $errors_arr ) or !is_array( $errors_arr ) )
                continue;

            if( !($notice_type = Woocommerce_Smart2pay_Admin_Notices::valid_notice_type( $error_type )) )
                $notice_type = Woocommerce_Smart2pay_Admin_Notices::TYPE_ERROR;

            foreach( $errors_arr as $error_msg )
            {
                $notice_arr = Woocommerce_Smart2pay_Admin_Notices::default_custom_notification_fields();

                $notice_arr['notice_type'] = $notice_type;
                $notice_arr['message']     = $error_msg;

                $notices_arr[] = $notice_arr;
            }
        }

        Woocommerce_Smart2pay_Admin_Notices_Later::add_notice( Woocommerce_Smart2pay_Admin_Notices::CUSTOM_NOTICE, $notices_arr, true );
    }

    /**
     * If There are no payment fields show the description if set.
     * Override this in your gateway if you have some.
     */
    public function payment_fields()
    {
        ?>Vasilica...<?php
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
            $methods_configured_arr = array();
            /** @var WC_S2P_Methods_Model $methods_model */
            if( ($methods_model = WC_s2p()->get_loader()->load_model( 'WC_S2P_Methods_Model' )) )
            {
                $methods_list_arr = $methods_model->get_db_available_methods();
            }

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
                <p>
                    <a href="javascript:start_syncronization()" class="button-primary">Re-Syncronize Methods</a>
                </p>

                <p style="margin-bottom: 0 !important;"><?php echo count( $methods_list_arr )?> payment methods currently available.</p>

                <style>
                .s2p-method-img { vertical-align: middle; max-height: 40px; max-width: 130px; }
                .sp2-middle-all { text-align: center; vertical-align: middle; }
                .s2p-method-img-td { height: 50px; width: 134px; text-align: center; }
                </style>

                <small>Higher priority means method will be displayed upper in the list.</small>
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
                foreach( $methods_list_arr as $method_id => $method_arr )
                {
                    $method_settings = false;
                    if( !empty( $methods_configured_arr[$method_arr['method_id']] ) and is_array( $methods_configured_arr[$method_arr['method_id']] ) )
                        $method_settings = $methods_configured_arr[$method_arr['method_id']];

                    ?>
                    <tr>
                        <td class="sp2-middle-all"><input type="checkbox" name="s2p_enabled_methods[]" id="s2p_enabled_method_<?php echo $method_arr['method_id']?>" value="<?php echo $method_arr['method_id']?>" <?php (!empty( $method_settings )?'checked="checked"':'')?> /></td>
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
                                <input type="text" class="input-text" style="width: 50px !important; text-align: right;" name="s2p_fixed_amount[<?php echo $method_arr['method_id']?>]" id="s2p_fixed_amount_<?php echo $method_arr['method_id']?>" value="<?php echo ((!empty( $method_settings ) and isset( $method_settings['surcharge_percent'] ))?$method_settings['surcharge_percent']:0)?>" /> <?php echo $wc_currency?>
                            </div>

                        </td>
                        <td class="s2p-method-img-td"><img src="<?php echo $method_arr['logo_url']?>" class="s2p-method-img" /></td>
                        <td>
                            <strong><?php echo $method_arr['display_name']?></strong> (#<?php echo $method_arr['method_id']?>)<br/>
                            <?php echo $method_arr['description']?>
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
                            if( form_obj.elements[i].type == 'checkbox' && form_obj.elements[i].name == 's2p_enabled_methods[]' )
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
                            if( form_obj.elements[i].type == 'checkbox' && form_obj.elements[i].name == 's2p_enabled_methods[]' )
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
                            if( form_obj.elements[i].type == 'checkbox' && form_obj.elements[i].name == 's2p_enabled_methods[]' )
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
                'description' => WC_s2p()->__( 'Default' ).': '.WC_S2P_Helper::get_slug_page_url( $wc_s2p::SHORTCODE_RETURN ),
            ),

            'section_display' => array(
                'title' => WC_s2p()->__( 'Display Settings' ),
                'type' => 'title',
            ),
            'display_surcharge' => array(
                'title' => WC_s2p()->__( 'Display Surcharge' ),
                'type' => 'checkbox',
                'label' => WC_s2p()->__( 'Display surcharge amounts to client?' ),
                'default' => 0,
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
                'default' => 0,
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
                'default' => 1,
            ),
            'product_description_custom' => array(
                'title' => WC_s2p()->__( 'Custom product description' ),
                'type' => 'textarea',
                'default' => '',
            ),

        );
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
     * Process the payment and return the result
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment( $order_id ) {

        $order = wc_get_order( $order_id );

        // Mark as on-hold (we're awaiting the cheque)
        $order->update_status( 'on-hold', __( 'Awaiting cheque payment', 'woocommerce' ) );

        // Reduce stock levels
        $order->reduce_order_stock();

        // Remove cart
        WC()->cart->empty_cart();

        // Return thankyou redirect
        return array(
            'result' 	=> 'success',
            'redirect'	=> $this->get_return_url( $order )
        );
    }
}
