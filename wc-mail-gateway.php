<?php

/**
 * Plugin Name: Mail Payment Gateway
 * Text Domain: wc-mail-gateway
 * Description: Extends "Cheque" gateway to create a mail payment gateway.
 * Version: 2.0.0
 * This extends the WC core "Cheque" gateway to create the Mail payment method.
 */

/* Protect php code */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/* Make sure WC is loaded */
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
   return;
}

/* Add plugin page links */
function wc_mail_gateway_plugin_links( $links ) {
    $plugin_links = array(
        '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=mail' ) . '">' . __( 'Configure', 'wc-mail-gateway' ) . '</a>'
    );
    return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_mail_gateway_plugin_links' );

/* Creates the Mail gateway */
add_action( 'plugins_loaded', 'init_mail_payment_gateway', 11 );

function init_mail_payment_gateway() {
    class WC_Mail_Gateway extends WC_Gateway_Cheque {

        /**
         * Gateway instructions that will be added to the thank you page and emails.
         *
         * @var string
         */
        // public $instructions;

        /* Gateway Constructor */
        public function __construct() {
            $this->id                   = 'mail';
            $this->icon                 = plugins_url( 'assets/MailBox_icon.png', __FILE__ );
            $this->has_fields           = false;
            $this->method_title         = _x( 'Mail Payments', 'Mail Payment Method', 'wc-mail-gateway' );
            $this->method_description   = __( 'Custom payment gateway to fascilitate Mail transactions.', 'wc-mail-gateway' );

            // Load the settings. 
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables (for the settings page).
            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->instructions = $this->get_option( 'instructions' );


            // Actions
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );

            // Customer Emails.
	        add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
        }


        /* Initialize gateway settings form fields */
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'         => __( 'Enable/Disable', 'wc-mail-gateway' ), 
                    'type'          => 'checkbox',
                    'label'         => __( 'Enable Mail Payment', 'wc-mail-gateway' ),
                    'default'       => 'yes'
                ),
                'title' => array(
                    'title'         => __('Title', 'wc-mail-gateway' ),
                    'type'          => 'text', 
                    'description'   => __('This controls the title which the user sees during checkout.', 'wc-mail-gateway' ), 
                    'default'       => _x('Mail', 'Mail Payment Method', 'wc-mail-gateway' ), 
                    'desc_tip'      => true,
                ),
                'description' => array(
                    'title'         => __( 'Description', 'wc-mail-gateway' ),
                    'type'          => 'textarea', 
                    'description'   => __( 'Payment method description that the customer will see on your checkout.', 'wc-mail-gateway' ),
                    'default'       => __( '', 'wc-mail-gateway' ),
                    'desc_tip'      => true,
                ),
                'instructions' => array(
                    'title'         => __( 'Instructions', 'wc-mail-gateway' ),
                    'type'          => 'textarea',
                    'description'   => __( 'Instructions that will be added to the thank you page and emails.', 'wc-mail-gateway' ),
                    'default'       => __( '', 'wc-mail-gateway' ),
                    'desc_tip'      => true,
                )
            );
        }

        /**
         * Output for the order received page.
         */
        public function thankyou_page() {
            if ( $this->instructions ) {
                echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) );
            }
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
            /**
             * Filter the email instructions order status.
             *
             * @since 7.4
             * @param string $terms The order status.
             * @param object $order The order object.
             */
            if ( $this->instructions && ! $sent_to_admin && 'mail' === $order->get_payment_method() && $order->has_status( apply_filters( 'woocommerce_mail_email_instructions_order_status', 'on-hold', $order ) ) ) {
                echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) . PHP_EOL );
            }
        }

        /**
         * Process the payment and return the result.
         *
         * @param int $order_id Order ID.
         * @return array
         */
        public function process_payment( $order_id ) {

            $order = wc_get_order( $order_id );

            if ( $order->get_total() > 0 ) {
                // Mark as on-hold (we're awaiting the mail).
                $order->update_status( apply_filters( 'woocommerce_mail_process_payment_order_status', 'on-hold', $order ), _x( 'Awaiting mail payment', 'Mail Payment Method', 'wc-mail-gateway' ) );
            } else {
                $order->payment_complete();
            }

            // Remove cart.
            WC()->cart->empty_cart();

            // Return thankyou redirect.
            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url( $order ),
            );
        }

        // May be useful if other stops working. Pretty sure WC_Order() is preferrable to wc_get_order()
        /* function process_payment( $order_id ) {
            global $woocommerce;
            $order = new WC_Order( $order_id );
            
            // Mark as pending-payment (until manual verification)
            $order->update_status('pending', __( 'Awaiting Mail payment', 'wc-mail-gateway' ));

            // Remove cart
            $woocommerce->cart->empty_cart();

            // Return thankyou redirect
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url( $order )
            );
        } */
    }
}

/* Declare function to pull gateways into WC */
function woo_add_mail_gateway_class( $methods ) {
    $methods[] = 'WC_Mail_Gateway';
    return $methods;
}


/* Append gateway to woocommerce_payment_gateways list */
add_filter( 'woocommerce_payment_gateways', 'woo_add_mail_gateway_class' );


