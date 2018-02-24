<?php
/*
 * Plugin Name: Woocomerce Payment Gateway - iPaymu
 * Plugin URL: https://ipaymu.com
 * Description: iPaymu payment gateway plugin for WooCommerce
 * Version: 1.0
 * Author: Franky So
 */

add_action('plugins_loaded', 'woocommerce_ipaymu_init', 0);

function woocommerce_ipaymu_init() {

    if (!class_exists('WC_Payment_Gateway'))
        return;

    class WC_Gateway_iPaymu extends WC_Payment_Gateway {

        public function __construct() {
            $this->id = 'ipaymu';
            $this->method_title = 'iPaymu Payment Gateway';
            $this->has_fields = false;
            $this->icon = plugins_url('/logo.png', __FILE__);
            
            //redirect URL
            $this->redirect_url = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'WC_Gateway_iPaymu', home_url( '/' ) ) );
        
            $this->init_form_fields();
            $this->init_settings();
            
            // Define user set variables
            $this->enabled      = $this->settings['enabled'];
            $this->title        = "Ipaymu Express Checkout";
            $this->description  = $this->settings['description'];
            $this->apikey       = $this->settings['apikey'];
            $this->password     = $this->settings['password'];
            $this->processor_id = $this->settings['processor_id'];
            $this->salemethod   = $this->settings['salemethod'];
            $this->gatewayurl   = $this->settings['gatewayurl'];
            $this->order_prefix = $this->settings['order_prefix'];
            $this->debugon      = $this->settings['debugon'];
            $this->debugrecip   = $this->settings['debugrecip'];
            $this->cvv          = $this->settings['cvv'];
            
            // Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
            add_action('woocommerce_receipt_ipaymu', array(&$this, 'payment_redirect'));
            
            // Payment listener/API hook
            add_action( 'woocommerce_api_wc_gateway_ipaymu', array( $this, 'check_ipaymu_response' ) );
        }

        function init_form_fields() {

            $this->form_fields = array(
                'enabled' => array(
                                'title' => __( 'Enable/Disable', 'woothemes' ), 
                                'label' => __( 'Enable iPaymu', 'woothemes' ), 
                                'type' => 'checkbox', 
                                'description' => '', 
                                'default' => 'no'
                            ), 
                'title' => array(
                                'title' => __( 'Title', 'woothemes' ), 
                                'type' => 'text', 
                                'description' => __( '', 'woothemes' ), 
                                'default' => __( 'Pembayaran iPaymu', 'woothemes' )
                            ), 
                'description' => array(
                                'title' => __( 'Description', 'woothemes' ), 
                                'type' => 'textarea', 
                                'description' => __( '', 'woothemes' ), 
                                'default' => 'Bayar dengan ATM Transfer, Credit Card, menerima lebih dari 137 Bank di Indonesia.'
                            ),  
                'apikey' => array(
                                'title' => __( 'API Key', 'woothemes' ), 
                                'type' => 'text', 
                                'description' => __( 'Belum memiliki kode API? <a target="_blank" href="https://ipaymu.com/dokumentasi-api-ipaymu-perkenalan">Pelajari cara Mendapatkan API Key</a></small>.', 'woothemes' ), 
                                'default' => ''
                            ),
                /*'debugrecip' => array(
                                'title' => __( 'Debugging Email', 'woothemes' ), 
                                'type' => 'text', 
                                'description' => __( 'Who should receive the debugging emails.', 'woothemes' ), 
                                'default' =>  get_option('admin_email')
                            ),*/
            );
        }

        public function admin_options() {
            echo '<table class="form-table">';
            $this->generate_settings_html();
            echo '</table>';
        }

        function payment_fields() {
            if ($this->description)
                echo wpautop(wptexturize($this->description));
		}
		
		function payment_redirect($order_id){
			global $woocommerce;
			
			$wc_order 	=	wc_get_order($order_id);
            
            $order = new WC_Order($order_id);
            
            // URL Payment IPAYMU
            $url = 'https://my.ipaymu.com/payment.htm';

            // Prepare Parameters
            $params = array(
                        'key'      => $this->apikey, // API Key Merchant / Penjual
                        'action'   => 'payment',
                        'product'  => 'Order : #'.$order_id,
                        'price'    => $order->order_total, // Total Harga
                        'quantity' => 1,
                        'comments' => '', // Optional           
                        'ureturn'  => $this->get_return_url($wc_order),
                        'unotify'  => $this->get_ipaymu_notify_url($order_id),
                        'ucancel'  => $this->redirect_url.'&id_order='.$order_id.'&param=cancel',
                        'format'   => 'json' // Format: xml / json. Default: xml 
                    );

            $params_string = http_build_query($params);

            //open connection
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, count($params));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

            //execute post
            $request = curl_exec($ch);

            if ( $request === false ) {
                echo 'Curl Error: ' . curl_error($ch);
            } else {
                
                $result = json_decode($request, true);

                if( isset($result['url']) )
                    header('location: '. $result['url']);
                else {
                    echo "Request Error ". $result['Status'] .": ". $result['Keterangan'];
                }
            }

            //close connection
            curl_close($ch);
		}
        
        function process_payment($order_id) {
            global $woocommerce;
            $order = new WC_Order($order_id);
			$order->reduce_order_stock();
			WC()->cart->empty_cart();

            return array(
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url( true ));
        }

  
        function check_ipaymu_response() {
            global $woocommerce;
			$redirect = add_query_arg('key', $order->order_key, add_query_arg('order', $_GET['id_order'], get_permalink(woocommerce_get_page_id('thanks'))));
            wp_redirect($redirect);
            exit;
        }
        
        function get_ipaymu_notify_url($order_id) {
            $callbackurl = get_option('siteurl');

            $params = array('ipaymu_callback_woocomerce' => '1', 'order_id' => $order_id);
            return add_query_arg($params, $callbackurl);
        }

    }

    function add_ipaymu_gateway($methods) {
        $methods[] = 'WC_Gateway_iPaymu';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_ipaymu_gateway');

	
	// Register Callback for Notify
    function ipaymu_callback_woocomerce() {
        global $woocommerce;
        if(isset($_POST['trx_id']) && isset($_POST['status']))
        {
			$order = new WC_Order($_REQUEST['id_order']);
			update_post_meta( $order->id, '_ipaymu', $_POST['trx_id']);

            if($_POST['status'] == 'berhasil') {
            	$order->add_order_note( __( 'Pembayaran telah dilakukan melalui ipaymu dengan id transaksi '.$_POST['trx_id'], 'woocommerce' ) );
				$order->payment_complete();
            } elseif($_POST['status']=="pending") {
				$order->add_order_note( __( 'Menunggu pembayaran melalui non-member ipaymu dengan id transaksi '.$_POST['trx_id'], 'woocommerce' ) );
			}

            exit();
        }
    }
    
    add_action('init', 'ipaymu_callback_woocomerce');
}

// Adding Cronjob
register_activation_hook( __FILE__, 'plugin_install' );

function plugin_install() {
    /*Register WP CRON*/
    if ( ! wp_next_scheduled( 'ipaymu_woocommerce_cronjob' ) ) {
      wp_schedule_event( time(), 'hourly', 'ipaymu_woocommerce_cronjob' );
    }

    add_action( 'ipaymu_woocommerce_cronjob', 'ipaymu_woocommerce_cronjob' );
}

function ipaymu_woocommerce_cronjob() {
	global $woocommerce;
	$orders = wc_get_orders( 
		[
			'payment_method' => 'ipaymu',
			'date_created' => '>' . strtotime('-2 day', strtotime(date("Y-m-d"))),
			'status' => 'pending'
		]
	);

	foreach($orders as $order)
	{
		
		$ipaymu 	=	get_post_meta( $order->id, '_ipaymu', true );
		$content 	=	json_decode(file_get_contents("https://my.ipaymu.com/api/CekTransaksi.php?format=json&key=".$this->settings['apikey']."&id=".$ipaymu), true);

		if($_POST['status'] == 'berhasil') {
			$order->add_order_note( __( 'Pembayaran telah dilakukan melalui ipaymu dengan id transaksi '.$content['id'], 'woocommerce' ) );
			$order->payment_complete();
		}
	}
}

// Deactivate Cronjob when plugin deactivated
function plugin_deactivation() {
    wp_clear_scheduled_hook("ipaymu_woocommerce_cronjob");
}

register_deactivation_hook( __FILE__, 'plugin_deactivation' );