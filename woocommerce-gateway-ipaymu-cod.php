<?php

/*
  Plugin Name: iPaymu Payment Gateway COD
  Plugin URI: http://ipaymu.com
  Description: iPaymu Payment Gateway COD
  Version: 1.0
  Author: iPaymu Development Team
  Author URI: http://ipaymu.com
 */

if ( ! defined( 'ABSPATH' ) ) exit; 

add_action('plugins_loaded', 'woocommerce_ipaymu_cod_init', 0);

function woocommerce_ipaymu_cod_init() {
    $ipaymucodSetting = get_option('woocommerce_ipaymucod_settings');
    
    if(strlen($ipaymucodSetting['ipaymucod_settings'])==0){
        add_action( 'admin_notices', function(){
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>Anda harus mengatur <strong>Nomor Telepon Pengirim</strong> agar dapat menggunakan iPaymu COD</p>
            </div>
            <?php
        } );
    }

    if(strlen($ipaymucodSetting['sender_district'])==0){
        add_action( 'admin_notices', function(){
            ?>
            <div class="notice notice-warning is-dismissible">
            <p>Anda harus mengatur <strong>Kelurahan Pengirim</strong> agar dapat menggunakan iPaymu COD</p>
            </div>
            <?php
        } );
    }

    if (!class_exists('WC_Payment_Gateway'))
        return;

    class WC_Gateway_iPaymu_COD extends WC_Payment_Gateway {

        public function __construct() {      
            //plugin id
            $this->id = 'ipaymucod';
            //Payment Gateway title
            $this->method_title = 'iPaymu Payment Gateway';
            //true only in case of direct payment method, false in our case
            $this->has_fields = false;
            //payment gateway logo
            $this->icon = plugins_url('/logo.png', __FILE__);
            
            //redirect URL
            $this->redirect_url = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'WC_Gateway_iPaymu_COD', home_url( '/' ) ) );
            
            //Load settings
            $this->init_form_fields();
            $this->init_settings();
            
            // Define user set variables
            $this->enabled      = @$this->settings['enabled'];
            $this->title        = "Ipaymu COD Payment";
            $this->description  = @$this->settings['description'];
            $this->apikey       = @$this->settings['apikey'];
            $this->thanks       = @$this->settings['thanks'];

            $this->sender_brand     = @$this->settings['sender_brand'];
            $this->sender_phone     = @$this->settings['sender_phone'];
            $this->sender_email     = @$this->settings['sender_email'];
            $this->sender_district  = @$this->settings['sender_district'];

            $this->password     = @$this->settings['password'];
            $this->processor_id = @$this->settings['processor_id'];
            $this->salemethod   = @$this->settings['salemethod'];
            $this->gatewayurl   = @$this->settings['gatewayurl'];
            $this->order_prefix = @$this->settings['order_prefix'];
            $this->debugon      = @$this->settings['debugon'];
            $this->debugrecip   = @$this->settings['debugrecip'];
            $this->cvv          = @$this->settings['cvv'];
            
            // Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
            add_action('woocommerce_receipt_ipaymucod', array(&$this, 'receipt_page'));
            
            // Payment listener/API hook
            add_action( 'woocommerce_api_wc_gateway_ipaymu_cod', array( $this, 'check_ipaymu_response' ) );

            // One One Product Per Transaction
            add_filter( 'woocommerce_available_payment_gateways', array( $this, 'only_one_product_per_cart' ) );

            // Checking required data
        }

        function only_one_product_per_cart($available_gateways){
            global $woocommerce;
            if($woocommerce->cart->get_cart_contents_count()>1){
                unset( $available_gateways['ipaymucod'] );
            }
            return $available_gateways;
        }

        function init_form_fields() {
            // Loop
            $thanksPages['NULL'] = "-- Default Woocommerce --";
            foreach(get_pages() as $page){
                $thanksPages[$page->ID] = $page->post_title;
            }

            $this->form_fields = array(
                'enabled' => array(
                                'title' => __( 'Enable/Disable', 'woothemes' ), 
                                'label' => __( 'Enable iPaymu COD', 'woothemes' ), 
                                'type' => 'checkbox', 
                                'description' => '', 
                                'default' => 'no'
                            ), 
                'title' => array(
                                'title' => __( 'Title', 'woothemes' ), 
                                'type' => 'text', 
                                'description' => __( '', 'woothemes' ), 
                                'default' => __( 'Pembayaran iPaymu COD', 'woothemes' )
                            ), 
                'description' => array(
                                'title' => __( 'Description', 'woothemes' ), 
                                'type' => 'textarea', 
                                'description' => __( '', 'woothemes' ), 
                                'default' => 'Sistem pembayaran menggunakan iPaymu COD.'
                            ),  
                'apikey' => array(
                                'title' => __( 'API Key', 'woothemes' ), 
                                'type' => 'text', 
                                'description' => __( ' Dapatkan API Key <a href=https://ipaymu.com/login/members/profile.htm target=_blank>di sini</a></small>.', 'woothemes' ), 
                                'default' => '',
                            ),
                'thanks' => array(
                    'title' => __( 'Thank Page', 'woothemes' ), 
                    'type' => 'select', 
                    'description' => __( 'Halaman terimakasih setelah pembeli melengkapi data transaksi COD', 'woothemes' ), 
                    'default' => '',
                    'options' => $thanksPages
                ),
                'sender_brand' => array(
                    'title' => __( 'Nama Pengirim', 'woothemes' ), 
                    'type' => 'text', 
                    'description' => __( 'Nama Pengirim untuk informasi pengambilan barang. Jika dikosongkan data default website (<strong>'.get_bloginfo().'</strong>) akan digunakan.', 'woothemes' ), 
                    'default' => '',
                ),
                'sender_phone' => array(
                    'title' => __( 'Nomor Telepon Pengirim (Required)', 'woothemes' ), 
                    'type' => 'text', 
                    'description' => __( 'Nomor Telepon Pengirim untuk informasi pengambilan barang. Jika dikosongkan website tidak akan dapat memproses transaksi COD.', 'woothemes' ), 
                    'default' => '',                    
                ),
                'sender_email' => array(
                    'title' => __( 'Email Pengirim', 'woothemes' ), 
                    'type' => 'text', 
                    'description' => __( 'Email Pengirim untuk informasi pengambilan barang. Jika dikosongkan data default website (<strong>'.get_option('admin_email').'</strong>) akan digunakan.', 'woothemes' ), 
                    'default' => '',
                ),
                'sender_district' => array(
                    'title' => __( 'Kelurahan Pengirim (Required)', 'woothemes' ), 
                    'type' => 'text', 
                    'description' => __( 'Kelurahan Pengirim untuk informasi pengambilan barang. Jika dikosongkan website tidak akan dapat memproses transaksi COD.', 'woothemes' ), 
                    'default' => '',
                )
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

        
        function receipt_page($order) {
            echo $this->generate_ipaymu_form($order);
        }

        
        public function generate_ipaymu_form($order_id) {

            global $woocommerce;
    
            $order = new WC_Order($order_id);
            $senderName     = (strlen($this->sender_brand)>0)?$this->sender_brand:get_bloginfo();
            $senderPhone    = $this->sender_phone;
            $senderEmail    = (strlen($this->sender_email)>0)?$this->sender_email:get_option('admin_email');
            $senderDistrict = $this->sender_district;

            foreach ($order->get_items() as $item_key => $item_value){
                $width  = $item_value->get_product()->get_width();
                $height = $item_value->get_product()->get_height();
                $length = $item_value->get_product()->get_length();
                $weight = $item_value->get_product()->get_weight();
            }
            // $order->get_items();
            // exit;
            
            // URL Payment IPAYMU
            $url = 'http://api.ipaymu.com/api/PaymentCOD';

            // Prepare Parameters
            $params = array(
                        'key'           => $this->apikey, // API Key Merchant / Penjual
                        'action'        => 'payment',
                        'product'       => 'Order : #'.$order_id,
                        'price'         => $order->get_total(), // Total Harga
                        'quantity'      => 1,
                        'comments'      => '', // Optional           
                        'weight'        => $weight,
                        'dimensi'       => "$length:$width:$height",
                        'postal_code'   => get_option('woocommerce_store_postcode'),
                        'address'       => get_option('woocommerce_store_address'),
                        'optparam1'     => "COD:$senderName:$senderPhone:$senderEmail:$senderDistrict",
                        'ureturn'       => $this->redirect_url.'&id_order='.$order_id,
                        'unotify'       => $this->redirect_url.'&id_order='.$order_id.'&param=notify',
                        'ucancel'       => $this->redirect_url.'&id_order='.$order_id.'&param=cancel',
                        'format'        => 'json' // Format: xml / json. Default: xml 
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
                if( isset($result["Success"]['url']) )
                    header('location: '. $result["Success"]['url']);
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
            $order = new WC_Order($_REQUEST['id_order']);
            $order->add_order_note( __( 'Pembayaran dilakukan ditempat menggunakan iPaymu CODm dengan id transaksi '.$_REQUEST['trx_id'], 'woocommerce' ) );

            if($this->thanks=="NULL"){
                $id_redirect = woocommerce_get_page_id('thanks');
            } else{
                $id_redirect = $this->thanks;
            }

            $redirect = add_query_arg('key', $order->order_key, add_query_arg('order', $_REQUEST['id_order'], get_permalink($id_redirect)));
            wp_redirect($redirect);
            exit;
        }

    }

    function add_ipaymu_gateway_cod($methods) {
        $methods[] = 'WC_Gateway_iPaymu_COD';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_ipaymu_gateway_cod');
}