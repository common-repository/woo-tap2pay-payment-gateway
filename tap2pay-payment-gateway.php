<?php
/**
 * Plugin Name:       WooCommerce Tap2pay Payment Gateway
 * Plugin URI:        http://gitlab.com/tap2pay/woocommerce_gateway
 * Description:       Tap2pay Payment Gateway allows you to accept payments on your Woocommerce store.
 * Version:           1
 * Author:            Tap2pay
 * Author URI:        https://tap2pay.me/
 * License:           MIT
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Github URI:        http://gitlab.com/tap2pay/woocommerce_gateway
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die();
}

require_once dirname(__FILE__) . "/tap2pay-api/client.php";

/**
 * Begins execution of the plugin.
 */
add_action('plugins_loaded', 'run_WC_tap2pay_payment_gateway' );

function run_WC_tap2pay_payment_gateway() {
    /**
     * Tell WooCommerce that Tap2pay class exists
     */
    function add_WC_tap2pay_payment_gateway( $methods ) {
        $methods[] = 'WC_tap2pay_payment_gateway';
        return $methods;
    }
    add_filter('woocommerce_payment_gateways', 'add_WC_tap2pay_payment_gateway');

    if (!class_exists('WC_Payment_Gateway')) return;

    class WC_tap2pay_payment_gateway extends WC_Payment_Gateway {
        public function __construct() {
            $this->log = wc_get_logger();

            $this->id = 'tap2pay';
            // $this->icon = apply_filters('woocommerce_tap2pay_icon',
                                        // plugins_url('public/images/logo.svg' , __FILE__));
            $this->has_fields = true;
            $this->method_title = 'Tap2pay';
            $this->method_description = 'Tap2pay Payment Gateway authorizes credit card payments and processes them securely with your merchant account.';

            $this->init_form_fields();
            $this->init_settings();

            // Get setting values
            $this->title       	= $this->get_option('title');
            $this->description 	= $this->get_option('description');
            $this->enabled     	= $this->get_option('enabled');
            $this->sandbox     	= $this->get_option('sandbox');
            $this->api_key      = $this->get_option('api_key');
            $this->api_host     = $this->get_option('api_host');
            $this->merchant_id  = $this->get_option('merchant_id');

            // Hooks
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
            add_action('woocommerce_receipt_tap2pay', array($this, 'receipt_page'));
            add_action('woocommerce_api_wc_tap2pay_payment_gateway',
                       array($this, 'webhook_handler'));

        }

        public function webhook_handler() {
            $raw_post = file_get_contents('php://input');
            $decoded  = json_decode($raw_post, true);
            if(!$decoded) {
                $this->log->error("Tap2pay: no post body");

                echo "no post body";
                die();
            }

            if($decoded['type'] == 'invoice.succeeded') {
                $data = $decoded['data'];
                if(!$data) {
                    $this->log->error("Tap2pay: data not found");

                    echo "data not found";
                    die();
                }

                $custom = $data['custom'];
                if (preg_match('/^woocommerce_(.+)/', $custom, $matches)) {
                    $order_id = wc_get_order_id_by_order_key($matches[1]);
                    if(!$order_id) {
                        $this->log->error("Tap2pay: order id not found");

                        echo "order id not found";
                        die();
                    }

                    $order = new WC_Order($order_id);
                    if(!$order) {
                        $this->log->error("Tap2pay: order not found");

                        echo "order not found";
                        die();
                    }

                    $id = $data['id'];
                    $order->payment_complete($id);
                    $order->add_order_note(sprintf(
                        __('%s payment approved! Transaction ID: %s', 'woocommerce'),
                        $this->title, $id)
                    );

                    echo "order completed!";
                } else {
                    echo "not woocommerce invoice";
                }
            }

            die();
        }

        /**
         * Initialise Gateway Settings Form Fields
         */
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'       => __('Enable/Disable', 'woocommerce'),
                    'label'       => __('Enable Tap2pay Payment Gateway', 'woocommerce'),
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => __('Title', 'woocommerce'),
                    'type'        => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                    'default'     => __('Tap2pay', 'woocommerce'),
                    'desc_tip'    => true
                ),
                'description' => array(
                    'title'       => __('Description', 'woocommerce'),
                    'type'        => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
                    'default'     => 'Pay securely with Tap2pay.',
                    'desc_tip'    => true
                ),
                'api_host' => array(
                    'title'       => __('Api host', 'woocommerce'),
                    'type'        => 'text',
                    'description' => __('Payment server address. Normally, leave as is.', 'woocommerce'),
                    'default'     => "https://secure.tap2pay.me",
                    'desc_tip'    => true
                ),
                'merchant_id' => array(
                    'title'       => __('Merchant ID', 'woocommerce'),
                    'type'        => 'text',
                    'description' => __('Get your API keys from your Tap2pay account.', 'woocommerce'),
                    'default'     => '',
                    'desc_tip'    => true
                ),
                'api_key' => array(
                    'title'       => __('Api key', 'woocommerce'),
                    'type'        => 'text',
                    'description' => __('Get your API keys from your Tap2pay account.', 'woocommerce'),
                    'default'     => '',
                    'desc_tip'    => true
                ),
            );
        }

        /**
         * Check if this gateway is enabled
         */
        public function is_available() {
            if ('yes' != $this->enabled) {
                return false;
            }

            // if (!is_ssl()) {
            // 	return false;
            // }

            return true;
        }

        /**
         * Outputs style used for Braintree Payment fields
         * Outputs scripts used for Braintree Payment
         */
        public function payment_scripts() {
            if (!is_checkout() || ! $this->is_available()) {
                return;
            }

            wp_register_style('wc-tap2pay-style',
                              plugins_url('public/css/tap2pay.css' , __FILE__),
                              array(), '20180226', 'all');
            wp_enqueue_style('wc-tap2pay-style');
            wp_enqueue_script('wc-tap2pay-checkout',
                              $this->api_host.'/checkout.v1.js',
                              array('jquery'), WC_VERSION, true);
            wp_enqueue_script('wc-tap2pay-payment-gateway',
                              plugins_url('public/js/tap2pay.js' , __FILE__),
                              array('jquery', 'wc-tap2pay-checkout'), WC_VERSION, true);
        }

        /**
         * Process the payment
         */
        public function process_payment($order_id) {
            global $woocommerce;

            $order = new WC_Order($order_id);

            return array(
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url(true)
            );
        }

        /**
         * Receipt page
         */
        public function receipt_page($order_id) {
            global $woocommerce;

            $order = new WC_Order($order_id);

            $client = new \Tap2pay\Client($this->api_key, $this->api_host);
            $money = new \Tap2pay\Money($order->get_total(),
                                        get_woocommerce_currency());

            $invoice_params = array(
                "custom" => "woocommerce_".$order->get_order_key(),
                "items" => array(array(
                    "price_value" => $money->get_cents(),
                    "price_currency" => $money->get_currency(),
                    "name" => __('Order', 'woocommerce') . ' # ' .$order->get_order_number(),
                )),
            );

            $id = null;
            if($order->meta_exists("tap2pay_invoice_id")) {
                $id = $order->get_meta("tap2pay_invoice_id");
                $client->update_invoice($id, $invoice_params);
            } else {
                $resp = $client->create_invoice($invoice_params);
                $id = $resp['id'];
                if($id) {
                    $order->add_meta_data("tap2pay_invoice_id", $id);
                    $order->save_meta_data();
                }
            }

            $success_url = $order->get_view_order_url();

            ?>
            <div id='tap2pay-payment-widget' data-merchant-id="<?= $this->merchant_id ?>" data-invoice-id="<?= $id ?>" data-success-url="<?= $success_url ?>">
            <button type='button' class='tap2pay-pay-btn'>Pay using <i class="tap2pay-logo-icon"></i></button>
            </div>
            <?PHP
        }
    }
}
