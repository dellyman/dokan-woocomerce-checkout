<?php
/*
Plugin Name:    Dellyman Checkout Shipping
Plugin URI:		https://www.dellyman.com
Description:	Calculate Shipping Cost and book order immediately and order has been paid for
Version:		1.0.0
Author:			Dellyman
Author URI:		https://www.dellyman.com
*/

defined( 'ABSPATH' ) or die("You are not allowed to access this page");

class DellymanShipping
{
    // All methods
        function __construct( ) {
               add_action( 'admin_menu', array($this,'addMenu') );
        }

        function activatePlugin(){
            $this->addMenu();
            $this->createDB();
            flush_rewrite_rules(); 
        }
        function deactivatePlugin(){
            flush_rewrite_rules(); 
        }
        function addMenu(){
            add_menu_page('Dellyman Orders', 'Dellyman Orders', 'manage_options', 'dellyman-orders', 'index_page',plugins_url(basename(__DIR__).'/assets/svg/icon.svg'),60);
            add_submenu_page('dellyman-orders', 'Request Delivery', 'Request Delivery', 'manage_options', 'request-delivery', 'requestDelivery');
            add_submenu_page('dellyman-orders', 'Connect to Dellyman', 'Connect to Dellyman', 'manage_options', 'connect-to-dellyman', 'login_page');
        }

        function createDB(){
            //Creating table that store credentails
            global $wpdb;
            $table_name = $wpdb->prefix . "woocommerce_dellyman_credentials"; 
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int(10) NOT NULL AUTO_INCREMENT,
            time datetime DEFAULT CURRENT_TIMESTAMP,
            API_KEY varchar(255)NOT NULL,
            Web_HookSecret varchar(255) DEFAULT '' NOT NULL,
            webhook_url varchar(255) DEFAULT '' NOT NULL,
            PRIMARY KEY  (id)
            ) $charset_collate;";
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );    
            dbDelta($sql);

            //Creating table that store tables after shipping
            global $wpdb;
            $table_name = $wpdb->prefix . "woocommerce_dellyman_products"; 
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int(10) NOT NULL AUTO_INCREMENT,
            order_id varchar(255) DEFAULT '' NOT NULL,
            product_id int(10) NOT NULL,
            user_id int(10) NOT NULL,
            product_name varchar(255) DEFAULT '' NOT NULL,
            sku varchar(255) DEFAULT NULL,
            price varchar(255) DEFAULT '' NOT NULL,
            quantity int(10) DEFAULT 0 NOT NULL,
            shipquantity int(10) DEFAULT 0 NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
            ) $charset_collate";
            require_once( ABSPATH .'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );
  
            //Creating table that order status  
            global $wpdb;
            $table_name = $wpdb->prefix . "woocommerce_dellyman_orders"; 
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int(10) NOT NULL AUTO_INCREMENT,
            time datetime DEFAULT CURRENT_TIMESTAMP,
            order_id varchar(255) DEFAULT '' NOT NULL,
            user_id int(10) NOT NULL,
            dellyman_order_id int(10) NOT NULL,
            is_TrackBack boolean DEFAULT 0 NOT NULL,
            dellyman_status varchar(255) DEFAULT 'PENDING' NOT NULL,
            reference_id varchar(255) DEFAULT '' NOT NULL,
            PRIMARY KEY  (id)
            ) $charset_collate;";
            require_once( ABSPATH .'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );   



            //Creating table that store tables after shipping
            global $wpdb;
            $table_name = $wpdb->prefix . "woocommerce_dellyman_shipped_products"; 
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int(10) NOT NULL AUTO_INCREMENT,
            order_id varchar(255) DEFAULT '' NOT NULL,
            dellyman_order_id int(10) NOT NULL,
            sku varchar(255) DEFAULT NULL,
            product_id int(10) NOT NULL,
            product_name varchar(255) DEFAULT '' NOT NULL,
            price varchar(255) DEFAULT '' NOT NULL,
            quantity varchar(255) DEFAULT '' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
            ) $charset_collate";
            require_once( ABSPATH .'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );
        }
}
if ( class_exists('DellymanShipping')  ) {
    $dellymanShipping =  new DellymanShipping();
}
//Wordpress Activation Hook
register_activation_hook(__FILE__, array(  $dellymanShipping , 'activatePlugin' ));
//Wordpress Deactivation Hook
register_deactivation_hook(__FILE__, array(  $dellymanShipping , 'deactivatePlugin' ));


if( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}
// Extending class
class DellymanOrders extends WP_List_Table
{
      private $orders;

      private function get_dellyman_orders($search = "")
      {
            global $wpdb;

            if (!empty($search)) {
                  return $wpdb->get_results(
                        "SELECT * from {$wpdb->prefix}woocommerce_dellyman_orders WHERE order_id Like '%{$search}%' OR reference_id Like '%{$search}%'",
                        ARRAY_A
                  );
            }else{
                  return $wpdb->get_results(
                        "SELECT * from {$wpdb->prefix}woocommerce_dellyman_orders ORDER BY time DESC",
                        ARRAY_A
                  );
            }
      }

      // Define table columns
      function get_columns()
      {
            $columns = array(
                  'cb'            => '<input type="checkbox" />',
                  'order_id' => 'Order id',
                  'reference_id'    => 'Dellyman order id',
                  'store_name'    => 'Store',
                  'item'      => 'Items',
                  'status' => 'Status',
                  'time' => 'Created'
            );
            return $columns;
      }

      // Bind table with columns, data and all
      function prepare_items()
      {
            if (isset($_POST['page']) && isset($_POST['s'])) {
                  $this->orders = $this->get_dellyman_orders($_POST['s']);
            } else {
                  $this->orders = $this->get_dellyman_orders();
            }

            $columns = $this->get_columns();
            $hidden = array();
            $sortable = $this->get_sortable_columns();
            $this->_column_headers = array($columns, $hidden, $sortable);

            /* pagination */
            $per_page = 10;
            $current_page = $this->get_pagenum();
            $total_items = count($this->orders);

            $this->orders = array_slice($this->orders, (($current_page - 1) * $per_page), $per_page);

            $this->set_pagination_args(array(
                  'total_items' => $total_items, // total number of items
                  'per_page'    => $per_page // items to show on a page
            ));

            usort($this->orders, array(&$this, 'usort_reorder'));

            $this->items = $this->orders;
      }

      // bind data with column
      function column_default($item, $column_name)
      {
            switch ($column_name) {
                  case 'order_id':
                        return '#'. $item['order_id'];
                  case 'reference_id':
                        return $item[$column_name];
                  case  'store_name';
                        $dokan_id = $item['user_id'];
                        $store_info = dokan_get_store_info($dokan_id);
                        return $store_info['store_name'];
                  case 'item':
                        $order = new WC_Order($item['order_id']); // Order id
                        //Get product Names
                        $allProductNames = "";
                        foreach ($order->get_items() as $key => $item) {
                            if ($key == 0) {
                                $allProductNames = preg_replace("/\'s+/", "", $item->get_name())."(". round($item->get_quantity())  .")";
                            }else{
                                $allProductNames = $allProductNames .",". preg_replace("/\'s+/", "", $item->get_name())."(". round($item->get_quantity())  .")";
                            }
                        }
                        $productNames = "Total item(s)-". count($order->get_items()) ." Products - " .$allProductNames;
                        return $productNames; 
                    case 'status':        
                        global $wpdb;
                        $table_name = $wpdb->prefix . "woocommerce_dellyman_credentials"; 
                        $user = $wpdb->get_row("SELECT * FROM $table_name WHERE id = 1");
                        $ApiKey =  (!empty($user->API_KEY)) ? $user->API_KEY : '';                                        
                        $response = wp_remote_post( 'https://dev.dellyman.com/api/v3.0/TrackOrder', array(
                            'body'    => json_encode([
                                'OrderID' => intval($item['dellyman_order_id'])
                            ]),
                            'headers' => [
                                'Authorization' => 'Bearer '. $ApiKey,
                                'Content-Type' =>  'application/json'
                            ]
                        ));
                        $status = json_decode(wp_remote_retrieve_body($response),true);
                        return $status['OrderStatus'];
                    case 'time':
                        return date("F j, Y, g:i a", strtotime($item['time'])); 
                  default:
                        return print_r($item, true); //Show the whole array for troubleshooting purposes
            }
      }

      // To show checkbox with each row
      function column_cb($item)
      {
            return sprintf(
                  '<input type="checkbox" name="user[]" value="%s" />',
                  $item['order_id']
            );
      }

      // Add sorting to columns
      protected function get_sortable_columns()
      {
            $sortable_columns = array(
                  'order_id'  => array('order_id', false),
                  'dellyman_order_id' => array('dellyman_order_id', false),
                  'reference_id'   => array('reference_id', true),
                  'time'   => array('time', true)
            );
            return $sortable_columns;
      }

      // Sorting function
      function usort_reorder($a, $b)
      {
            // If no sort, default to user_login
            $orderby = (!empty($_GET['orderby'])) ? $_GET['orderby'] : 'time';
            // If no order, default to asc
            $order = (!empty($_GET['order'])) ? $_GET['order'] : 'desc';
            // Determine sort order
            $result = strcmp($a[$orderby], $b[$orderby]);
            // Send final sort direction to usort
            return ($order != 'desc') ? $result : -$result;
      }
}

function index_page(){
 
      // Creating an instance
      $orderTable = new DellymanOrders();

      echo '<div class="wrap"><h2>Dellyman orders</h2>';
      // Prepare table
      $orderTable->prepare_items();
      ?>
            <form method="post">
                  <input type="hidden" name="page" value="employees_list_table" />
                  <?php $orderTable->search_box('search', 'search_id'); ?>
            </form>
      <?php
      // Display table
      $orderTable->display();
      echo '</div>';
}
function login_page() {
    include_once('includes/login.php');
}
function status_page(){
    include_once('includes/status.php');  
}

function requestDelivery(){
    include_once('includes/admin-request.php');
}

add_action('admin_post_login_credentials','save_crendentials');

function save_crendentials(){;
    extract($_REQUEST);
    global $wpdb;
    $table_name = $wpdb->prefix . "woocommerce_dellyman_credentials"; 
    $details = $wpdb->get_row("SELECT * FROM $table_name WHERE id = 1 ",OBJECT);
    if (empty($details)) {
        //Insert in the database
        $wpdb->insert( 
            $table_name, 
            array( 
                'time' => current_time( 'mysql' ), 
                'API_KEY' => $apiKey, 
                'Web_HookSecret' => $webhookSecret, 
                'webhook_url' => get_site_url().'/wp-json/api/dellyman-webhook', 
            ) 
        );
    }else{
        //Update
        $dbData = array(
            'time' => current_time( 'mysql' ), 
            'API_KEY' => $apiKey, 
            'Web_HookSecret' => $webhookSecret, 
            'webhook_url' => get_site_url().'/wp-json/api/dellyman-webhook',
        );
        $wpdb->update($table_name, $dbData, array('id' => 1)); 
    }
    $redirect = add_query_arg( 'status', 'success', 'admin.php?page=connect-to-dellyman');
    wp_redirect($redirect);
    exit;
}

function bookOrder($carrier,$store_name,$shipping_address, $productNames,$pickupAddress,$vendorphone,$custPhone, $store_city,$customer_city){
    $deliveredName = $shipping_address['first_name'] ." ". $shipping_address['last_name'];
    //booking order
    $date =  date("m/d/Y");
    $postdata = array( 
        'CustomerID' => 0,
        'PaymentMode' => 'online',
        'FixedDeliveryCharge' => 10,
        'Vehicle' => $carrier,
        'IsProductOrder' => 0,
        'BankCode' => "",
        'AccountNumber' => "",
        'IsProductInsurance' => 0,
        'InsuranceAmount' => 0,
        'PickUpContactName' =>$store_name,
        'PickUpContactNumber' => $vendorphone,
        'PickUpGooglePlaceAddress' => $pickupAddress,
        'PickUpLandmark' => "Mobile",	
        'PickUpRequestedTime' => "06 AM to 09 PM",
        'PickUpRequestedDate' => $date,
        'DeliveryRequestedTime' => "06 AM to 09 PM",
        'Packages' => [
            array(
            'DeliveryContactName' =>$deliveredName ,
            'DeliveryContactNumber' => $custPhone ,
            'DeliveryGooglePlaceAddress' =>$shipping_address['address_1']." ,".$shipping_address['city'],
            'DeliveryLandmark' => "",
            'PackageDescription' => $productNames,
            'ProductAmount' => "2000",
            "PickUpCity" =>  $store_city,
            "DeliveryCity" => $customer_city,
            "PickUpState" => "Lagos",
            "DeliveryState" => "Lagos"
            )
        ],
    );
    $jsonPostData = json_encode($postdata);
    global $wpdb;
    $table_name = $wpdb->prefix . "woocommerce_dellyman_credentials"; 
    $user = $wpdb->get_row("SELECT * FROM $table_name WHERE id = 1");
    $ApiKey =  (!empty($user->API_KEY)) ? ($user->API_KEY) : ('');

    $curl = curl_init();
    curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://dev.dellyman.com/api/v3.0/BookOrder',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $jsonPostData,
    CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $ApiKey
    ),
    ));
    $responseJson = curl_exec($curl);
    curl_close($curl);
    $NoTags = strip_tags(preg_replace(array('~<br(.*?)</br>~Usi','~<b(.*?)</b>~Usi'), "", $responseJson));
    return json_decode($NoTags,true);
}


function post_products_dellyman_request($order_id){

    if(!dokan_get_seller_id_by_order( $order_id )):
        $sub_orders = dokan_get_suborder_ids_by($order_id);
        foreach($sub_orders as $sub_order) {
            $child_order = wc_get_order($sub_order);
            $vendor_id = dokan_get_seller_id_by_order($child_order);
            sendOrderToDellyman($child_order , $vendor_id);
        }

    else:
        $vendor_id = dokan_get_seller_id_by_order( $order_id );
        sendOrderToDellyman($order_id , $vendor_id);
    endif;
}
add_action( 'woocommerce_payment_complete', 'post_products_dellyman_request' );

function sendOrderToDellyman($order_id , $vendor_id){

    //Get Order Addresss 
    $order = new WC_Order($order_id); // Order id
    $shipping_address = $order->get_address('billing'); 

    //Get product Names
    $allProductNames = "";
    foreach ($order->get_items() as $key => $item) {
        if ($key == 0) {
            $allProductNames = preg_replace("/\'s+/", "", $item->get_name())."(". round($item->get_quantity())  .")";
        }else{
            $allProductNames = $allProductNames .",". preg_replace("/\'s+/", "", $item->get_name())."(". round($item->get_quantity())  .")";
        }
    }
    $productNames = "Total item(s)-". count($order->get_items()) ." Products - " .$allProductNames;
        
    //Get Authentciation
   
    $store_info = dokan_get_store_info($vendor_id);
    $store_name = $store_info['store_name'];
    $store_address = $store_info['address']['street_1'];
    $store_city = $store_info['address']['city'];
    $pickupAddress = $store_address .', '. $store_city;
    $vendorphone = $store_info['phone'];
    $custPhone =  $order->get_billing_phone();
    $customer_city = $order->get_billing_city();
    $carrier = "bike";
    //send order
    $feedback = bookOrder($carrier,$store_name,$shipping_address, $productNames,$pickupAddress,$vendorphone,$custPhone, $store_city,$customer_city );
   
    if ($feedback['ResponseCode'] == 100) {
        $dellyman_orderid = $feedback['OrderID'];
        $Reference = $feedback['Reference'];
        //Insert into delivery orders in table
        global $wpdb;
        $table_name = $wpdb->prefix . "woocommerce_dellyman_orders";
        $wpdb->insert( 
            $table_name, 
            array( 
                'time' => current_time('mysql'), 
                'order_id' => $order_id,
                'reference_id' => $Reference,
                'dellyman_order_id' =>$dellyman_orderid,
                'user_id' => $vendor_id, 
            ) 
        );
        
        $order = new WC_Order($order_id);
        $order->update_status("wc-fully-shipped", 'Order moved to fully shipped by delivery', FALSE); 
    }

}

function custom_dellyman_post_order_status() {
  register_post_status( 'wc-ready-to-ship', array(
      'label'                     => 'Ready to ship',
      'public'                    => true,
      'show_in_admin_status_list' => true,
      'show_in_admin_all_list'    => true,
      'exclude_from_search'       => false,
      'label_count'               => _n_noop( 'Ready to ship <span class="count">(%s)</span>', 'Ready to ship <span class="count">(%s)</span>' )
  ) );
  register_post_status( 'wc-fully-shipped', array(
    'label'                     => 'Shipped',
    'public'                    => true,
    'show_in_admin_status_list' => true,
    'show_in_admin_all_list'    => true,
    'exclude_from_search'       => false,
    'label_count'               => _n_noop( 'Shipped <span class="count">(%s)</span>', 'Shipped <span class="count">(%s)</span>')
) );
register_post_status( 'wc-fully-delivered', array(
    'label'                     => 'Delivered',
    'public'                    => true,
    'show_in_admin_status_list' => true,
    'show_in_admin_all_list'    => true,
    'exclude_from_search'       => false,
    'label_count'               => _n_noop( 'Delivered <span class="count">(%s)</span>', 'Delivered <span class="count">(%s)</span>')
) );

}
add_action( 'init', 'custom_dellyman_post_order_status' );

function add_dellyman_custom_order_statuses($order_statuses) {

  $new_order_statuses = array();

  foreach ( $order_statuses as $key => $status ) {

      $new_order_statuses[ $key ] = $status;

      if ('wc-completed' === $key ) {
          $new_order_statuses['wc-ready-to-ship'] = 'Ready to ship';
          $new_order_statuses['wc-fully-shipped'] = 'Shipped';
          $new_order_statuses['wc-fully-delivered'] = 'Delivered';
      }
    
  }

  return $new_order_statuses;
}
add_filter( 'wc_order_statuses', 'add_dellyman_custom_order_statuses' );


// Webhook
function change_status_order(WP_REST_Request $request) {
    // In practice this function would fetch the desired data. Here we are just making stuff up.
    $key  = $request->get_header('X-Dellyman-Signature');
    error_log($key);
    global $wpdb;
    $table_name = $wpdb->prefix . "woocommerce_dellyman_credentials"; 
    $user = $wpdb->get_row("SELECT * FROM $table_name WHERE id = 1");
    $Web_HookSecret =  (!empty($user->Web_HookSecret)) ? $user->Web_HookSecret : ''; 
    $myKey = hash_hmac('sha256', urldecode($request->get_body()), $Web_HookSecret);
  
     
    if($key == $myKey){
        //Move order to deliver
        global $wpdb;
        $table_name = $wpdb->prefix . "woocommerce_dellyman_orders"; 
        $body = json_decode(urldecode($request->get_body()),true);
        error_log(urldecode($request->get_body()));
        $orderID = $body['order']['OrderID'];
        $order = $wpdb->get_row("SELECT * FROM $table_name WHERE dellyman_order_id = ". $orderID);

        if($body['order']['OrderStatus'] == "COMPLETED"){
            $order = new WC_Order($order->order_id);
            $order->update_status("wc-fully-delivered", 'Order moveed to fully delivered', FALSE); 
        }elseif($body['order']['OrderStatus'] == "CANCELLED"){
             
        }
    }else{
        //echo "Not from dellyman";
    }

}
// * This function is where we register our routes for our example endpoint.
// */
function prefix_register_product_routes() {
   //Here we are registering our route for a collection of products and creation of products.
   register_rest_route( 'api', '/dellyman-webhook', array(
           'methods'  => WP_REST_Server::CREATABLE,
           'callback' => 'change_status_order',
           'permission_callback' => '__return_true'
    ));
}
add_action( 'rest_api_init', 'prefix_register_product_routes' );

//For Dokan

    /**
 * Add new custom status for WC order statuses
 *
 * @param array $order_statuses
 *
 * @return array $order_statuses
 */
function dokan_add_new_custom_order_status( $order_statuses ) {
    $order_statuses[ 'wc-ready-to-ship' ] = _x( 'Ready to ship', 'Order status', 'text_domain' );
    $order_statuses[ 'wc-fully-shipped' ] = _x( 'Shipped', 'Order status', 'text_domain' );
    $order_statuses[ 'wc-fully-delivered' ] = _x( 'Delivered', 'Order status', 'text_domain' );
    return $order_statuses;
}

add_filter( 'wc_order_statuses', 'dokan_add_new_custom_order_status', 12, 1 );

/**
 * Add new custom status button class on order status
 *
 * @param string $text
 * @param string $status
 *
 * @return string $text
 */
function dokan_add_custom_order_status_button_class( $text, $status ) {
    switch ( $status ) {
        case 'wc-ready-to-ship':
        case 'ready-to-ship':
        case 'wc-fully-shipped':
        case 'fully-shipped':
        case 'wc-fully-delivered':
        case 'fully-delivered':
            $text = 'success';
        break;        
    }    
    return $text;
}
add_filter( 'dokan_get_order_status_class', 'dokan_add_custom_order_status_button_class', 10, 2 );


/**
 * Custom order status translated
 *
 * @param string $text
 * @param string $status
 *
 * @return string $text
 */
function dokan_add_custom_order_status_translated( $text, $status ) {
    switch ( $status ) {
        case 'wc-ready-to-ship':
        case 'ready-to-ship':
            $text = __( 'Ready to Ship', 'text_domain' );
            break;
        case 'wc-fully-shipped':
        case 'fully-shipped':
            $text = __( 'Shipped', 'text_domain' );
            break; 
        case 'wc-fully-delivered':
        case 'fully-delivered':
            $text = __( 'Delivered', 'text_domain' );
            break;         
    }    
    return $text;
}
add_filter( 'dokan_get_order_status_translated', 'dokan_add_custom_order_status_translated', 10, 2 );

/*
 * Check if WooCommerce is active
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
 
  function dellyman_shipping_method() {
      if ( ! class_exists( 'Dellyman_Shipping_Method' ) ) {
          class Dellyman_Shipping_Method extends WC_Shipping_Method {
              /**
               * Constructor for your shipping class
               *
               * @access public
               * @return void
               */
              public function __construct() {
                  $this->id                 = 'dellyman'; 
                  $this->method_title       = __( 'Dellyman Shipping', 'nv-dellyman' );  
                  $this->method_description = __( 'Custom Shipping Method for Dellyman', 'nv-dellyman' ); 
                 
                  $this->init();

                  $this->enabled = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'yes';
                  $this->title = isset( $this->settings['title'] ) ? $this->settings['title'] : __( 'Dellyman Shipping', 'nv-dellyman' );
              }

              /**
               * Init your settings
               *
               * @access public
               * @return void
               */
              function init() {
                  // Load the settings API
                  $this->init_form_fields(); 
                  $this->init_settings(); 

                  // Save settings in admin if you have any defined
                  add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
              }

              /**
               * Define settings field for this shipping
               * @return void 
               */
              function init_form_fields() { 

                $this->form_fields = array(
 
                  'enabled' => array(
                       'title' => __( 'Enable', 'nv-dellyman' ),
                       'type' => 'checkbox',
                       'description' => __( 'Enable this shipping.', 'nv-dellyman' ),
                       'default' => 'yes'
                       ),
          
                  'title' => array(
                     'title' => __( 'Title', 'nv-dellyman' ),
                       'type' => 'text',
                       'description' => __( 'Title to be display on site', 'nv-dellyman' ),
                       'default' => __( 'Dellyman Shipping', 'nv-dellyman' )
                       ),
          
                  );

              }


              /**
               * This function is used to fetch the dellyman shipping quotes.
               *
               * @access public
               * @param mixed $package
               * @return void
               */
              public function get_dellyman_customer_getquotes($PaymentMode = "pickup",$VehicleID = 1,$PickupRequestedTime,$PickupRequestedDate,$DeliveryAddress,$IsProductOrder = 0,$IsProductInsurance = 0,$InsuranceAmount = 0,$IsInstantDelivery = 1)
              {
                global $wpdb;
                $table_name = $wpdb->prefix . "woocommerce_dellyman_credentials"; 
                $user = $wpdb->get_row("SELECT * FROM $table_name WHERE id = 1");
                $ApiKey =  (!empty($user->API_KEY)) ? ($user->API_KEY) : ('');

                $vendors = array(); // Initializing

                // Loop through cart items
                foreach ( WC()->cart->cart_contents as $item_key => $cart_item ) {
                    $vendor_id  = get_post_field( 'post_author', $cart_item['product_id']);
                    array_push($vendors, $vendor_id);
                }
            
                $vendors = array_unique($vendors);
                
                $shippingFee = 0;
                $distance = [];
                // Loop through sorted items key
                foreach($vendors as $key => $vendor_id ) {
                    $store_info = dokan_get_store_info($vendor_id);
                    $store_address = $store_info['address']['street_1'];
                    $store_city = $store_info['address']['city'];
                    $pickupAddress = $store_address .', '. $store_city;

                               
                    $argdata = array(
                        "PaymentMode"                     => $PaymentMode,
                        "VehicleID"                       => $VehicleID,
                        "PickupRequestedTime"             => $PickupRequestedTime,
                        "PickupRequestedDate"             => $PickupRequestedDate,
                        "PickupAddress"                   => $pickupAddress,
                        "DeliveryAddress"                 => $DeliveryAddress,
                        "ProductAmount"                   => strval(WC()->cart->get_cart_total()),
                        "PackageWeight"                   => "1kg",
                        "IsProductOrder"                  => $IsProductOrder,
                        "IsProductInsurance"              => $IsProductInsurance,
                        "InsuranceAmount"                 => $InsuranceAmount,
                        "IsInstantDelivery"               => $IsInstantDelivery
                    );
                    $data = json_encode($argdata);
                    
                    $response = wp_remote_post("https://dev.dellyman.com/api/v3.0/GetQuotes", array(
                        'method' => 'POST',
                        'timeout' => 30,
                        'redirection' => 10,
                        'httpversion' => '1.1',
                        'blocking' => true,
                        'headers' => array(
                        'Authorization' => 'Bearer ' . $ApiKey,
                        'Cache-Control' => 'no-cache',
                        'Content-Type' => 'application/json',
                        ),
                        'body' => $data,
                        'cookies' => array()
                    )
                    );

                    if ( is_wp_error( $response ) ) {
                        $error_message = $response->get_error_message();
                        return array('status' => 'error', 'message' => __( 'Something Went Wrong : ', 'nv-dellyman' ), 'value' => $error_message);
                    }else {
                        $response = $response['body'];
                        $response = json_decode($response, true);
                        if($response['ResponseCode'] == '100' && $response['ResponseMessage'] == 'Success')
                        {

                            $shippingFee = $shippingFee + $response['Companies'][0]['PayablePrice'];
                            array_push($distance, ['distance' => $response['Distance']]);
                        }
                        else
                        {
                            return array('status' => 'error', 'message' => __( 'Error : ', 'nv-dellyman' ), 'value' => $response['ResponseMessage']);
                        }
                    }

                }

                return array('status' => 'success', 'message' => __( 'Get Quote Successfull : ', 'nv-dellyman' ), 'PayablePrice' => $shippingFee , 'distance' => $distance ,);
                


     
              }

              /**
               * This function is used to calculate the shipping cost. Within this function we can check for weights, dimensions and other parameters.
               *
               * @access public
               * @param mixed $package
               * @return void
               */
              public function calculate_shipping( $package = array() ) {
     
                $state    = $package["destination"]["state"];
                $city     = $package["destination"]["city"];  
                $country  = $package["destination"]["country"];
    
                $DeliveryAddress        = array($city.','.$state.','.$country);
                $ProductAmount          = array();
                $PickupRequestedTime    = '06 AM to 09 PM';
                $PickupRequestedDate    = date("d/m/Y");
                $quotes                 = $this->get_dellyman_customer_getquotes($PaymentMode = "pickup",$VehicleID = 1,$PickupRequestedTime,$PickupRequestedDate,$DeliveryAddress,$IsProductOrder = 0,$IsProductInsurance = 0,$InsuranceAmount = 0,$IsInstantDelivery = 1);
                    
                if($quotes['status'] == "success")
                    {
                    
                        $cost = $quotes['PayablePrice'];

                        $rate = array(
                          'id' => $this->id,
                          'label' => $this->title,
                          'cost' => $cost
                        );
                    
                        $this->add_rate( $rate );

                        
                        //wc_add_notice( $quotes['message'].$quotes['PayablePrice'], 'notice');
                    }
                    else
                    {
                        wc_add_notice( $quotes['message'].$quotes['value'], 'notice');
                    }                
              }
          }
      }
  }

  add_action( 'woocommerce_shipping_init', 'dellyman_shipping_method' );

  function add_dellyman_shipping_method( $methods ) {
      $methods[] = 'Dellyman_Shipping_Method';
      return $methods;
  }

  add_filter( 'woocommerce_shipping_methods', 'add_dellyman_shipping_method' );
}
    
?>