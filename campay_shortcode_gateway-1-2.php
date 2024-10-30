<?php

/*
 * Plugin Name: CamPay Shortcode Payment Gateway
 * Plugin URI: https://campay.net/wordpress/campay-shortcode-payment-gateway/
 * Description: Accept Mobile Money Payment using CamPay API Services.
 * Author: Gabinho
 * Author URI: https://campay.net/
 * Version: 1.2
 */

define("CAMPAY_SHORTCODE_GATEWAY_PLUGIN_URL", plugin_dir_url(__FILE__));
define("CAMPAY_SHORTCODE_GATEWAY_PLUGIN_DIR", dirname(__FILE__));
define("CAMPAY_SHORTCODE_GATEWAY_PLUGIN_PATH", plugin_dir_path(__FILE__));

class CamPay_Shortcode_Gateway {
    
    private $app_username;
    private $app_password;
    private $testmode;
    
    public function __construct() {
        add_action("init", array($this, "create_post_campay_shortcodes"), 0);
        add_action("init", array($this, "create_post_campay_payments"), 0);
        add_filter("manage_edit-campay_payments_columns", array($this, "campay_payments_column"), 11);
        add_action("manage_campay_payments_posts_custom_column", array($this, "campay_payments_column_values"));        
        add_action( 'admin_init', array($this, "campay_shortcode_gateway_settings") );
        add_action('admin_menu', array($this, "campay_shortcode_gateway_settings_menu"));
        add_action( 'wp_enqueue_scripts', array( $this, 'shortcode_payment_js' ) );
        add_filter( 'post_row_actions', array($this, "remove_row_actions"), 10, 1 ); 
        add_shortcode("campay", array($this, "campay_shortcode"));
        
        add_action("wp_ajax_do_campay_payment", array($this, "do_payment"));
        add_action("wp_ajax_nopriv_do_campay_payment", array($this, "do_payment"));
		
		add_action("wp_head", array($this, "validate_payment"), 1, 0);
		
    }

    public function create_post_campay_shortcodes()
    {
        $labels = array(
            "name" => _x("Campay Shortcode", "Post Type General Name", "twentythirteen"),
            "singular_name" => _x("Campay Shortcode", "Post Type Singular Name", "twentythirteen"),
            "menu_name" => _x("Campay Shortcodes", "twentythirteen"),
            'all_items' => __('All Shortcodes', 'twentythirteen'),
            'view_item' => __('View Shortcode', 'twentythirteen'),
            'search_items' => __('Search', 'twentythirteen'),
            'not_found' => __('Not found', 'twentythirteen'),
            'not_found_in_trash' => __('Non found in trash', 'twentythirteen'),
        );

        $args = array(
            'label' => __('Campay buttons', 'twentythirteen'),
            'description' => __('New Campay button', 'twentythirteen'),
            'labels' => $labels,
            'supports' => array('title'),
            'hierarchical' => false,
            'public' => true,
            'show_ui' => false,
            'show_in_menu' => false,
            'show_in_nav_menus' => false,
            'show_in_admin_bar' => false,
            'menu_position' => 5,
            'can_export' => false,
            'has_archive' => false,
            'exclude_from_search' => true,
            'publicly_queryable' => true,
            'capability_type' => 'page',
        );

        register_post_type("campay_buttons", $args);        
    }
    
    public function create_post_campay_payments()
    {
        $labels = array(
            "name" => _x("Campay Shortcode Payment", "Post Type General Name", "twentythirteen"),
            "singular_name" => _x("Campay Payment", "Post Type Singular Name", "twentythirteen"),
            "menu_name" => _x("Campay Payments", "twentythirteen"),
            'all_items' => __('All payments', 'twentythirteen'),
            'view_item' => __('View payment', 'twentythirteen'),
            'add_new_item' => __('Add Payment', 'twentythirteen'),
            'add_new' => __('Add', 'twentythirteen'),            
            'edit_item' => __('Edit', 'twentythirteen'),
            'update_item' => __('Update', 'twentythirteen'),
            'search_items' => __('Search', 'twentythirteen'),
            'not_found' => __('Not found', 'twentythirteen'),
            'not_found_in_trash' => __('Non found in trash', 'twentythirteen'),
        );

        $args = array(
            'label' => __('Campay Payments', 'twentythirteen'),
            'labels' => $labels,
            'supports' => array('title'),
            'hierarchical' => false,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_nav_menus' => true,
            'show_in_admin_bar' => true,
            'menu_position' => 5,
            'can_export' => true,
            'has_archive' => true,
            'exclude_from_search' => true,
            'publicly_queryable' => true,
            'capability_type' => 'page',
            'capabilities' => array(
                'create_posts' => false, // Removes support for the "Add New" function ( use 'do_not_allow' instead of false for multisite set ups )
              ),
            'map_meta_cap' => false, // Set to `false`, if users are not allowed to edit/delete existing posts            
        );

        register_post_type("campay_payments", $args);        
    }
    
    public  function remove_row_actions( $actions ) 
    {
        if( get_post_type() === 'campay_payments' )
            unset( $actions['view'] );
        return $actions;
    }
    
    public function campay_shortcode_gateway_settings_menu() {
        add_options_page('Campay Gateway Settings', 'Campay Settings', 'manage_options', 'campay_shortcode_gateway', array($this, "create_settings_page"));
    }
    
    /**
     * Register and add settings
     */
    public function campay_shortcode_gateway_settings() {
        register_setting(
                'campay_shortcode_gateway', // Option group
                'campay_app_username', // Option name
                array("type"=>"string") // Sanitize
        );
        register_setting(
                'campay_shortcode_gateway', // Option group
                'campay_app_password', // Option name
                array("type"=>"string") // Sanitize
        ); 
        register_setting(
                'campay_shortcode_gateway', // Option group
                'campay_testmode', // Option name
                array("default"=>0) // Sanitize
        );         

        add_settings_section(
                'campay_shortcode_id', // ID
                'Campay App Credentials', // Title
                array($this, 'print_section_info'), // Callback
                'campay_shortcode_gateway' // Page
        );

        add_settings_field(
            'campay_testmode', 
            'Active demo mode', 
            array($this, 'campay_testmode'), 
            'campay_shortcode_gateway', 
            'campay_shortcode_id'
        ); 
        
        add_settings_field(
                'campay_app_username', // ID
                'App Username', // Title 
                array($this, 'campay_appusername'), // Callback
                'campay_shortcode_gateway', // Page
                'campay_shortcode_id' // Section           
        );

        add_settings_field(
                'campay_app_password', 
                'App Password', 
                array($this, 'campay_apppassword'), 
                'campay_shortcode_gateway', 
                'campay_shortcode_id'
        );
               
    }
    
    /**
     * Print the Section text
     */
    public function print_section_info() {
        print 'Enter your App credentials below:';
    }

    /**
     * Get the settings option array and print one of its values
     */
    public function campay_testmode() {
        
        if($this->testmode)
            $checked = "checked";
        else
            $checked = "";
		
        echo '<input type="checkbox" id="campay_testmode" name="campay_testmode" '.esc_html($checked).' />';
        
    }

    /**
        * Get the settings option array and print one of its values
     */
    public function campay_appusername() {
        printf(
                '<input type="text" id="campay_app_username" name="campay_app_username" value="%s" />', isset($this->app_username) ? esc_attr($this->app_username) : ''
        );
    }

    /**
     * Get the settings option array and print one of its values
     */
    public function campay_apppassword() {
        printf(
                '<input type="password" id="campay_app_password" name="campay_app_password" value="%s" />', isset($this->app_password) ? esc_attr($this->app_password) : ''
        );
    }

    /**
     * Options page callback
     */
    public function create_settings_page() {
        // Set class property
        $this->app_password = get_option('campay_app_password');
        $this->app_username = get_option('campay_app_username');
        $this->testmode = get_option('campay_testmode');
        
?>
                <div class="wrap">
                    <h1>Campay Shortocde Payment Gateway Settings</h1>
                    <form method="post" action="options.php">
        <?php
        // This prints out all hidden setting fields
        settings_fields('campay_shortcode_gateway');
        do_settings_sections('campay_shortcode_gateway');
        submit_button();
        ?>
                    </form>
                </div>
        <?php
    }

    public function campay_shortcode($atts)
    {
        ob_start();
       extract(shortcode_atts(array(
            "success_url"=>"/thank-you/",
            "failure_url"=>"/failed/",
            "amount"=>0,
            "product_title"=>"Test",
            "product_ref"=>time(),
            "currency"=>"XAF",
            "button_text"=>"Donate",
            "create_date" => new DateTime("NOW"),
        ), $atts));
        
        global $pagenow;
        if ($pagenow == "post.php")
            echo "";
        else
        {
            $instance = uniqid();        
            ?>
<div id="campay_<?php echo esc_html($instance) ?>">
<?php
    if (is_array($atts)) {

    $amount = (int) $atts['amount'];
    $success_url = (string) $atts['success_url'];
    $failure_url = (string) $atts['failure_url'];
    $product_title = (string) $atts['product_title'];
    $product_ref = (string) $atts['product_ref'];
    $currency = (string) $atts['currency'];
    $button_text = (string) $atts['button_text'];
    
    if(!empty($amount))
    {
        $args = array(
          "post_type"=>"campay_buttons",
           "post_status"=>"publish",
            "post_title"=>$product_title
        );
        $button_id = wp_insert_post($args);

    if(!is_wp_error($button_id))
    {
        update_post_meta($button_id, "button_amount", $amount);
        update_post_meta($button_id, "button_currency", $currency);
        update_post_meta($button_id, "button_ref", $product_ref);
        update_post_meta($button_id, "button_title", $product_title);
        update_post_meta($button_id, "button_created_date", new DateTime("NOW"));
        update_post_meta($button_id, "button_success_url", $success_url);
        update_post_meta($button_id, "button_failure_url", $failure_url);
    ?>

    <button data-amount="<?php echo $amount ?>" data-buttonid="<?php echo esc_html($button_id) ?>" id="<?php echo uniqid() ?>" class="open_payment_modal"> <?php echo esc_html($button_text)." ".esc_html($amount)." ".  strtoupper(esc_html($currency)); ?> <span  style="background: url(<?php echo plugins_url( 'assets/img/momo-om.png', __FILE__ ); ?>); width: 50px;height: 20px;display: inline-block;background-size: contain;background-repeat: no-repeat;background-color: #fff;background-position: center center;"></span> </button>
    <button data-amount="<?php echo $amount ?>" data-buttonid="<?php echo esc_html($button_id) ?>" id="<?php echo uniqid() ?>" class="pay-using-card" value="campay-card"> <?php echo esc_html($button_text)." ".esc_html($amount)." ".  strtoupper(esc_html($currency)); ?> <span  style="background: url(<?php echo plugins_url( 'assets/img/credit-card.png', __FILE__ ); ?>); width: 50px;height: 20px;display: inline-block;background-size: contain;background-repeat: no-repeat;background-color: #fff;background-position: center center;"></span> </button>
    <?php
    }
    }
    else
    { 
      
        ?>
        <button data-amount="0" id="<?php echo uniqid() ?>" class="open_payment_modal"><?php echo esc_html($button_text); ?> <span  style="background: url(<?php echo plugins_url( 'assets/img/momo-om.png', __FILE__ ); ?>); width: 50px;height: 20px;display: inline-block;background-size: contain;background-repeat: no-repeat;background-color: #fff;background-position: center center;"></span></button>
        <button data-amount="0" id="<?php echo uniqid() ?>" class="pay-using-card" value="campay-card" data-buttonid="<?php echo esc_html($button_id) ?>"><?php echo esc_html($button_text)." ".esc_html($amount)." ".  strtoupper(esc_html($currency)); ?> <span  style="background: url(<?php echo plugins_url( 'assets/img/credit-card.png', __FILE__ ); ?>); width: 50px;height: 20px;display: inline-block;background-size: contain;background-repeat: no-repeat;background-color: #fff;background-position: center center;"></span></button>
        <?php
    }
}
?>
</div>
<!-- The Modal -->
<div id="payment_modal" class="modal">

  <!-- Modal content -->
  <div class="modal-content">
    <span class="close">&times;</span>
    <h3 style="text-align:center; text-decoration: underline">PAYMENT WINDOW</h3>
    <form id="campay_shortcode_payment_form" method="post" action="">
        <?php 
            if(empty($amount))
            {

                ?> <div class="form-row form-row-wide" id="row_amount">
                        <label>Enter amount <span class="required">*</span></label>
                        <input type="text" name="amount" oninput="this.value = this.value.replace(/[^0-9]/g, '').replace(/(\.*?)\.*/g, '$1');" /> 
                        </div>
                <?php
            }
            ?>
        <fieldset class="wc-campay-gateway" style="background:transparent;">
          
            <span id="campay-number-error">The number entered is not a valid MTN or ORANGE number</span>
            
            <div class="form-row form-row-wide"><label>Enter a valid MTN or ORANGE Money number <span class="required">*</span></label>
                <input id="campay_transaction_number" name="campay_transaction_number" required="" type="text" pattern="[1-9]{1}[0-9]{8}" onchange="validate_number(this)" oninput="this.value = this.value.replace(/[^0-9]/g, '').replace(/(\.*?)\.*/g, '$1');" >
            </div>
            <div class="clear"></div>
        </fieldset>
        <input type="hidden" name="success_url" value="<?php echo esc_html($success_url) ?>" />
        <input type="hidden" name="failure_url" value="<?php echo esc_html($failure_url) ?>" />
        <input type="hidden" name="product_ref" value="<?php echo esc_html($product_ref) ?>" />
        <input type="hidden" name="product_title" value="<?php echo esc_html($product_title) ?>" />
        <input type="hidden" name="currency" value="<?php echo esc_html($currency) ?>" />
        <input type="hidden" name="button_id" id="buttonid"/>
        <input type="hidden" name="action" value="do_campay_payment" />
    
        
        <button type="submit" id="campay_pay_now" class="submit-button"><?php echo esc_html($button_text); ?></button>
 
        
    </form>
  </div>

</div>
            <?php
            //include_once CAMPAY_SHORTCODE_GATEWAY_PLUGIN_PATH."/html/shortcode.php";
        }
        
        
        
        return ob_get_clean();
    }
    
    public function process_payment($button, $wp_payment, $number) {



        if (get_option("campay_testmode"))
            $server_uri = "https://demo.campay.net";
        else
            $server_uri = "https://campay.net";

        $amount  = (int) get_post_meta($button, "button_amount", true);
        $ref  = strip_tags(get_post_meta($button, "button_ref", true));
        $currency  = strtoupper(get_post_meta($button, "button_currency", true));
        $created_date  = get_post_meta($button, "button_created_date", true);
        $success_url = get_post_meta($button, "button_success_url", true);
        $failure_url = get_post_meta($button, "button_failure_url", true);
        // we need it to get any order detailes
        $trans_number = $number;
        $trans_number = "237" . $trans_number;
        $trans_number = intval($trans_number);
        $order_created_date = $created_date;
        $payment_timeout = 15;
        $order_expiry_time = $order_created_date;
        $order_expiry_time->add(new DateInterval("PT5M"));
        $price = $amount;
        $description = "Payment from : " . site_url() . " for item : " . $ref;
        $external_reference = $this->guidv4();


        $token = $this->get_token($server_uri);

        $params = array(
            "amount" => $price,
            "currency" => $currency,
            "from" => $trans_number,
            "description" => $description,
            "external_reference" => $external_reference
        );

        $params = json_encode($params);

        $today = strtotime("now");

        $expiry = strtotime("+" . $payment_timeout . " minutes", $today);
        
        $trans = $this->execute_payment($token, $params, $server_uri);



        if (!empty($trans) && !is_object($trans)) {
            $payment_completed = false;

            while (strtotime("now") <= $expiry) {

                $payment = $this->check_payment($token, $trans, $server_uri);

                if (!empty($payment)) {
                    if (strtoupper($payment->status) == "SUCCESSFUL") {
                        $payment_completed = true;
                        //$order->update_status('completed', __('Payment received', 'campay'));
                        //$order->add_order_note('Transaction complete with ref : ' . $payment->reference . PHP_EOL . "Operator Ref : " . $payment->operator_reference . PHP_EOL . "Operator : " . $payment->operator, true);
                        // Reduce stock levels
                        //$order->reduce_order_stock();
                        // Remove cart
                        //WC()->cart->empty_cart();

                        break;
                    }
                    if (strtoupper($payment->status) == "FAILED") {
                        break;
                    }
                }
            }

            if ($payment_completed && strtoupper($payment->status) == "SUCCESSFUL") {
                update_post_meta($wp_payment, "payment_status", 1);
                update_post_meta($wp_payment, "payment_completed_date", new DateTime("NOW"));
                update_post_meta($wp_payment, "payment_details", 'Transaction successfull with ref : ' . $payment->reference . PHP_EOL . "Operator : " . $payment->operator);
                update_post_meta($wp_payment, "transaction_ref", $payment->reference);
                update_post_meta($wp_payment, "operator", $payment->operator);
                $return = array("url"=>site_url($success_url), "success"=>1);
            } elseif (!$payment_completed && strtoupper($payment->status) == "PENDING") {
                 update_post_meta($wp_payment, "payment_status", 0);
                 update_post_meta($wp_payment, "payment_details", 'Transaction failed with ref : ' . $payment->reference . PHP_EOL . "Operator : " . $payment->operator);
                 update_post_meta($wp_payment, "transaction_ref", $payment->reference);
                 update_post_meta($wp_payment, "operator", $payment->operator);
                //update_post_meta($wp_payment, "payment_completed_date", new DateTime("NOW"));
                 $return = array("url"=>site_url($failure_url), "success"=>0);
            } else {
                 update_post_meta($wp_payment, "payment_status", 0);
                 update_post_meta($wp_payment, "payment_details", 'Transaction failed with ref : ' . $payment->reference . PHP_EOL . "Operator : " . $payment->operator);
                 update_post_meta($wp_payment, "transaction_ref", $payment->reference);
                 update_post_meta($wp_payment, "operator", $payment->operator);
                //update_post_meta($wp_payment, "payment_completed_date", new DateTime("NOW"));
                $return = array("url"=>site_url($failure_url), "success"=>0);                
            }
        } else {
           $return = array("msg"=>"failed to initiate transaction", "success"=>2);
        }
        
        echo json_encode($return); //failed transaction not initiated
    }


    /*
     * Get token from campay
     */

	public function get_token($server_uri)
	{
		
        $user = get_option("campay_app_username");
        $pass = get_option("campay_app_password");
		
		$params = array("username"=>$user, "password"=>$pass);
		//$params = json_encode($params);
		
		$headers = array('Content-Type: application/json');
		
		$response = wp_remote_post($server_uri."/api/token/", array(
			"method"=>"POST",
			"sslverify"=>true,
			"headers"=>$headers,
			"body"=>$params
		));
		if(!is_wp_error($response))
		{
			$response_body = wp_remote_retrieve_body($response);
			$resp_array = json_decode($response_body);
			if(isset($resp_array->token))
				return $resp_array->token;
			else
				wc_add_notice(  'Unable to get access token', 'error' );
		}
		else
			wc_add_notice(  'Failed to initiate transaction please try again later', 'error' );
		
		
		
	}
	
	public function execute_payment($token, $params, $server_uri)
	{
		
		$headers = array(
			'Authorization' => 'Token '.$token,
			'Content-Type' => 'application/json'
			);
			
		$response = wp_remote_post($server_uri."/api/collect/", array(
			"method"=>"POST",
			"sslverify"=>true,
			"body"=>$params,				
			"headers"=>$headers,
			"data_format"=>"body"
		));			

		if(!is_wp_error($response))
		{
			$response_body = wp_remote_retrieve_body($response);
			$resp_array = json_decode($response_body);
			if(isset($resp_array->reference))
				return $resp_array->reference;
			if(!isset($resp_array->reference) && isset($resp_array->message))
				wc_add_notice(  $resp_array->message, 'error' );
		}
		else
			wc_add_notice(  'Failed to initiate the transaction please try again later', 'error' );
		
	}
	
	public function check_payment($token, $trans, $server_uri)
	{
		
		$headers = array(
			'Authorization' => 'Token '.$token,
			'Content-Type' => 'application/json'
		);
		
		$response = wp_remote_get($server_uri."/api/transaction/".$trans."/", array(
			"sslverify"=>true,				
			"headers"=>$headers,
		));
		
		if(!is_wp_error($response))
		{
			$response_body = wp_remote_retrieve_body($response);
			$resp_array = json_decode($response_body);
			
			if(isset($resp_array->status))
				return $resp_array;
			else
				wc_add_notice(  'Invalid Transaction Reference', 'error' );
		}
		else
			wc_add_notice(  'Failed to initiate the transaction please try again later', 'error' );			
		
	
	}

    public function guidv4($data = null) {
        // Generate 16 bytes (128 bits) of random data or use the data passed into the function.
        $data = $data ?? random_bytes(16);
        assert(strlen($data) == 16);

        // Set version to 0100
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set bits 6-7 to 10
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        // Output the 36 character UUID.
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    
    public function shortcode_payment_js() {
        wp_enqueue_script('shortcode_campay_js', plugins_url('assets/js/campay.js', __FILE__), array('jquery'), false, true);
        wp_enqueue_style('shortcode_campay_css', plugins_url('assets/css/campay.css', __FILE__), array(), '1.0.0', 'all');
            wp_localize_script( 'shortcode_campay_js', 'my_ajax_object',
            array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
    }
    
    public function do_payment()
    {
       
		$request_type = sanitize_text_field($_POST['request_type']);
		
		if(strtolower($request_type)=="campay_card_payment")
		{
			
			if (get_option("campay_testmode"))
				$server_uri = "https://demo.campay.net";
			else
				$server_uri = "https://campay.net";
			
			
			$amount = (int)sanitize_text_field($_POST['amount']);
			$button = sanitize_text_field($_POST['button_id']);
			$button = intval($button);
			$ref  = strip_tags(get_post_meta($button, "button_ref", true));
			
			
			$success_url = get_post_meta($button, "button_success_url", true);
			$failure_url = get_post_meta($button, "button_failure_url", true);
			
			$title = get_post_meta($button, "button_title", true); 
			$args = array(
			  "post_type"=>"campay_payments",
			   "post_status"=>"publish",
			   "post_title"=>"Payment - ".$title
			);
			
			$wp_payment = wp_insert_post($args);
			
			if(!is_wp_error($wp_payment))
			{
				update_post_meta($wp_payment, "payment_amount", $amount);
				update_post_meta($wp_payment, "payment_number", "CARD");
				update_post_meta($wp_payment, "payment_currency", "XAF");
				update_post_meta($wp_payment, "payment_created_date", new DateTime("NOW"));
				
				$description = "Payment from : ".site_url()." Item Ref: ".$ref;
				
				$token = $this->get_token($server_uri);
				
											
				$params = array(
						"amount"=>$amount,
						"currency"=>"XAF",
						"description"=>$description,
						"external_reference"=>$wp_payment."|campay_req",
						"payment_options"=>"CARD",
						"redirect_url"=>site_url($success_url),
						"failure_redirect_url"=>site_url($failure_url)
				);
				
				$params = json_encode($params);
				
				$link = $this->do_cards_payment($token, $params, $server_uri);
				
				if(strlen($link)>1){
					
					$return = array("url"=>$link, "success"=>1);
					echo json_encode($return);
				}
				else{
					
					$return = array("msg"=>"Failed to initiate the transaction", "success"=>2);
					echo json_encode($return); //failed transaction not initiated                
				}				
				
				
			}


            
			die();
		}
		else
		{
		   if (isset($_POST['campay_transaction_number']) && !empty($_POST['button_id']) && empty($_POST['amount']))
			{
				$button = sanitize_text_field($_POST['button_id']);
				$button = intval($button);
				
				$number = sanitize_text_field($_POST['campay_transaction_number']);
				
				$amount  = (int) get_post_meta($button, "button_amount", true);
				$ref  = strip_tags(get_post_meta($button, "button_ref", true));
				$currency  = strtoupper(get_post_meta($button, "button_currency", true));
				$order_created_date  = get_post_meta($button, "button_created_date", true);
				$success_url = get_post_meta($button, "button_success_url", true);
				$failure_url = get_post_meta($button, "button_failure_url", true); 
				$title = get_post_meta($button, "button_title", true); 
				$args = array(
				  "post_type"=>"campay_payments",
				   "post_status"=>"publish",
				   "post_title"=>"Payment - ".$title
				);
				
				$wp_payment = wp_insert_post($args);
				
				if(!is_wp_error($wp_payment))
				{
					update_post_meta($wp_payment, "payment_amount", $amount);
					update_post_meta($wp_payment, "payment_number", $number);
					update_post_meta($wp_payment, "payment_currency", $currency);
					update_post_meta($wp_payment, "payment_created_date", new DateTime("NOW"));
					
					$this->process_payment($button, $wp_payment, $number);
				}
				
			}
			elseif(isset($_POST['campay_transaction_number']) && empty($_POST['button_id']) && !empty($_POST['amount']))
			{
				$amount = sanitize_text_field($_POST['amount']);
				$amount = intval($amount);
				
				$number = sanitize_text_field($_POST['campay_transaction_number']);
				$currency = sanitize_text_field($_POST['currency']);
				$success_url = sanitize_text_field($_POST['success_url']);
				$failure_url = sanitize_text_field($_POST['failure_url']);
				$product_ref = sanitize_text_field($_POST['product_ref']);
				$product_title = sanitize_text_field($_POST['product_title']);
				
				
				if(!empty($amount))
				{
					$args = array(
					  "post_type"=>"campay_buttons",
					   "post_status"=>"publish",
						"post_title"=>$product_title
					);
					
					$button_id = wp_insert_post($args);

					if(!is_wp_error($button_id))
					{
						update_post_meta($button_id, "button_amount", $amount);
						update_post_meta($button_id, "button_currency", $currency);
						update_post_meta($button_id, "button_ref", $product_ref);
						update_post_meta($button_id, "button_title", $product_title);
						update_post_meta($button_id, "button_created_date", new DateTime("NOW"));
						update_post_meta($button_id, "button_success_url", $success_url);
						update_post_meta($button_id, "button_failure_url", $failure_url);
						
						$args1 = array(
							"post_type"=>"campay_payments",
							 "post_status"=>"publish",
							 "post_title"=>"Payment - ".$title
						  );

						$wp_payment = wp_insert_post($args1);

						if(!is_wp_error($wp_payment))
						{
						   
							update_post_meta($wp_payment, "payment_amount", $amount);
							update_post_meta($wp_payment, "payment_number", $number);
							update_post_meta($wp_payment, "payment_currency", $currency);
							update_post_meta($wp_payment, "payment_created_date", new DateTime("NOW"));

						   $this->process_payment($button_id, $wp_payment, $number);
							
						}  
						
						
					}
				}
				 
			}
			else
			{
				$return = array("msg"=>"Please input a transaction number", "success"=>2);
				echo json_encode($return); //failed transaction not initiated
			}
        }
		
        die();
    }
	
	public function do_cards_payment($token, $params, $server_uri){
		
		$headers = array(
			'Authorization' => 'Token '.$token,
			'Content-Type' => 'application/json'
		);
					
		$response = wp_remote_post($server_uri."/api/get_payment_link/", array(
			"method"=>"POST",
			"sslverify"=>true,
			"body"=>$params,				
			"headers"=>$headers,
			"data_format"=>"body"
		));
					
		if(!is_wp_error($response))
		{
			$response_body = wp_remote_retrieve_body($response);
			$resp_array = json_decode($response_body);
			
			if(isset($resp_array->link))
			{
				return $resp_array->link;	
			}
			else
			{
				
				return 0;			
				
			}
		
		}
		else
			return 2;
	
	}
	
	public function validate_payment()
	{
		if(is_page())
		{
			global $post;
			
			$slug = $post->post_name;
			
			if(isset($_GET['external_reference']) && !empty($_GET['external_reference']))
			{
			
				$external_ref = sanitize_text_field($_GET['external_reference']);
				
				$array_ref = explode("|", $external_ref);
				
				
				if(in_array("campay_req", $array_ref)){
					
					$wp_payment = (int)$array_ref[0];
					$status = sanitize_text_field($_GET['status']);
					$operator = sanitize_text_field($_GET['operator']);
					$operator_ref = sanitize_text_field($_GET['operator_reference']);
					
					
					if(strtolower($status)=="successful"){
						
						update_post_meta($wp_payment, "payment_status", 1);
						update_post_meta($wp_payment, "payment_completed_date", new DateTime("NOW"));
						update_post_meta($wp_payment, "payment_details", 'Transaction successfull with ref : ' . $operator_ref . PHP_EOL . "Operator : " . $operator);
						update_post_meta($wp_payment, "transaction_ref", $operator_ref);
						update_post_meta($wp_payment, "operator", $operator);	
						
						wp_redirect(site_url($slug));
						
						
					}
					elseif(strtolower($status)=="failed"){
						
					 update_post_meta($wp_payment, "payment_status", 0);
					 update_post_meta($wp_payment, "payment_details", 'Transaction failed with ref : ' . $operator_ref . PHP_EOL . "Operator : " . $operator);
					 update_post_meta($wp_payment, "transaction_ref", $operator_ref);
					 update_post_meta($wp_payment, "operator", $operator);	

						wp_redirect(site_url($slug));
						
					}
					else{
					 
					 update_post_meta($wp_payment, "payment_status", 2);
					 update_post_meta($wp_payment, "payment_status_returned", $status);
					 update_post_meta($wp_payment, "payment_details", 'Transaction failed with ref : ' . $operator_ref . PHP_EOL . "Operator : " . $operator);
					 update_post_meta($wp_payment, "transaction_ref", $operator_ref);
					 update_post_meta($wp_payment, "operator", $operator);

						wp_redirect(site_url($slug));
						
					}
					
				}
				
				
				
			}
		
		}
		
		
	}
    
    public function campay_payments_column($columns)
    {
	$columns['status'] = "Status";
        $columns['initiator'] = "Number";
        $columns['amount'] = "Amount";
        $columns['trans_ref'] = "Transaction Ref";
        $columns['operator'] = "Operator";
        $columns['completed_date'] = "Completed Date";
	
	return $columns;
    }

    public function campay_payments_column_values($column)
    {
        global $post;
	
	
	if($column=="status")
	{
            $status = get_post_meta($post->ID, "payment_status", true);
            echo $status ? "SUCCESSFULL" : "FAILED";
	}
	if($column=="initiator")
	{
            $number = get_post_meta($post->ID, "payment_number", true);
            echo esc_html($number);
	} 
	if($column=="amount")
	{
            $amount = get_post_meta($post->ID, "payment_amount", true);
            echo esc_html($amount);
	}        
	if($column=="trans_ref")
	{
            $ref = get_post_meta($post->ID, "transaction_ref", true);
            echo esc_html($ref);
	}
	if($column=="operator")
	{
            $operator = get_post_meta($post->ID, "operator", true);
            echo esc_html($operator);
	}        
	if($column=="completed_date")
	{
            $date = get_post_meta($post->ID, "payment_completed_date", true);
            if(!empty($date) && is_object($date))
                echo $date->format('Y-m-d\TH:i:s.u');
	}        
    }    

    public static function run()
    {
        static $instance = NULL;
        if(is_null($instance))
            $instance = new CamPay_Shortcode_Gateway();
        return $instance;
    }

}

CamPay_Shortcode_Gateway::run();