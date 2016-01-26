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
        if( $this->process_admin_options() )
        {
            // do extra shit here...
        }
    }

    public function process_admin_options()
    {
        $this->validate_settings_fields();

        // validate $this->sanitized_fields array (WC validation depending on fields type)

        update_option( $this->plugin_id . $this->id . '_settings', apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->id, $this->sanitized_fields ) );
        $this->init_settings();
    }

    public function payment_fields()
    {
        ?>Vasilica...<?php
    }

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
            $methods_list_arr = array();
            /** @var WC_S2P_Methods_Model $methods_model */
            if( ($methods_model = WC_s2p()->get_loader()->load_model( 'WC_S2P_Methods_Model' )) )
            {
                $methods_list_arr = $methods_model->get_db_methods();
            }

            ?>
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
                    <div id="message" class="error">
                    <p><strong><?php echo $error_msg?></strong></p>
                    </div>
                    <?php
                } else
                {
                    ?>
                    <div id="message" class="updated">
                    <p><strong>Payment methods syncronized with success.</strong></p>
                    </div>
                    <?php
                }
            }

            //var_dump( $sdk_interface->get_available_methods( $this->settings ) );

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
                It appears that you don't have any payment methods currently in database.
                In order to obtain available payment methods for current plugin setup you will have to syncronize your database with our servers.
                <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_smart2pay&sync_methods=1' )?>#smart2pay_methods" class="button-primary">Syncronize Now</a>
                </p>
                <?php
            } else
            {
                ?>
                <p>
                    <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_smart2pay&sync_methods=1' )?>#smart2pay_methods" class="button-primary">Re-Syncronize Methods</a>
                </p>
                <?php
            }
            ?>


            <table class="form-table">
                <tr valign="top">
                    <th scope="row" class="titledesc">
                        <label for="testing">Bubu</label>
                    </th>
                    <td class="forminp">
                        <fieldset>
                            <legend class="screen-reader-text"><span>Vasile</span></legend>
                            <input class="input-text regular-input" type="text" name="testing" id="testing" value="1" placeholder="blabla"/>
                            Description
                        </fieldset>
                    </td>
                </tr>
            </table>

            <script type="text/javascript">
                jQuery(document).on( 'change', '#woocommerce_smart2pay_environment', function(e)
                {
                    refresh_fields();
                });

                jQuery(document).ready(function() {
                    refresh_fields();
                });

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
