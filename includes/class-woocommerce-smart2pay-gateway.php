<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}


class WC_Gateway_Smart2Pay extends WC_Payment_Gateway
{
    const ENV_DEMO = 'demo', ENV_TEST = 'test', ENV_LIVE = 'live';

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
        /** @var Woocommerce_Smart2pay $wc_s2p */
        global $wc_s2p;

        $this->id                 = 'smart2pay';
        $this->icon               = ''; // If you want to show an image next to the gateway’s name on the frontend, enter a URL to an image.
        $this->has_fields         = true;
        $this->method_title       = $wc_s2p->__( 'Smart2Pay' );
        $this->method_description = $wc_s2p->__( 'Secure payments through 100+ alternative payment options.' );

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title        = $this->get_option( 'title', $wc_s2p->__( 'Alternative payment methods' ) );
        $this->description  = 'Bubu description'; // $this->get_option( 'description' );
        $this->instructions = 'Some instructions for bubu'; // $this->get_option( 'instructions', $this->description );

        // Actions
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'save_payment_details' ) );
        //add_action( 'woocommerce_thankyou_cheque', array( $this, 'thankyou_page' ) );

        // Customer Emails
        //add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
    }

    public function save_payment_details()
    {
        $this->process_admin_options();
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
        /** @var Woocommerce_Smart2pay $wc_s2p */
        global $wc_s2p;

        ob_start();
        parent::admin_options();
        $parent_buffer = ob_get_clean();

        ob_start();
        ?>
        <h3><?php echo $wc_s2p->__( 'Methods Settings' ) ; ?></h3>

        <table class="form-table">
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="testing">Bubu</label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span>Vasile</span></legend>
                    <input class="input-text regular-input" type="text" name="testing" id="testing" value="1" placeholder="blabla" />
                    Description
                </fieldset>
            </td>
        </tr>
        </table>
        <?php
        $my_buffer = ob_get_clean();

        $order = new WC_Order();
        var_dump( $order->get_status() );

        echo $parent_buffer.$my_buffer;
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields()
    {
        /** @var Woocommerce_Smart2pay $wc_s2p */
        global $wc_s2p;

        $this->form_fields = array(
            'section_general' => array(
                'title'   => $wc_s2p->__( 'General Settings' ),
                'type'    => 'title',
            ),
            'enabled' => array(
                'title'   => $wc_s2p->__( 'Enabled' ),
                'type'    => 'checkbox',
                'label'   => $wc_s2p->__( 'Enable Smart2Pay payment gateway' ),
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => $wc_s2p->__( 'Method Title' ),
                'type'        => 'text',
                'description' => $wc_s2p->__( 'This controls the title which the user sees during checkout.' ),
                'default'     => 'Smart2Pay - Alternative payment methods',
                'desc_tip'    => true,
            ),
            'environment' => array(
                'title'       => $wc_s2p->__( 'Environment' ),
                'type'        => 'select',
                'description' => $wc_s2p->__( 'To obtain your credentials for live and test environments, please contact us at <a href="mailto:support@smart2pay.com">support@smart2pay.com</a>.' ),
                'options'     => Woocommerce_Smart2pay_Displaymode::toOptionArray(),
                'default'     => Woocommerce_Smart2pay_Displaymode::MODE_BOTH,
            ),
            'site_id' => array(
                'title'       => $wc_s2p->__( 'Site ID' ),
                'type'        => 'text',
                'default'     => 0,
            ),
            'skin_id' => array(
                'title'       => $wc_s2p->__( 'Skin ID' ),
                'type'        => 'text',
                'default'     => 0,
            ),
            'return_url' => array(
                'title'       => $wc_s2p->__( 'Return URL' ),
                'type'        => 'text',
                'default'     => 0,
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
