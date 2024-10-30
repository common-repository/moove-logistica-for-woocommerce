<?php
/**
 * Plugin Name: Moove Logistica for Woocommerce
 * Plugin URI: http://woocommerce.com/products/woocommerce-extension/
 * Description: Your shipping, tracking and delivery in a linear journey.
 * Version: 1.0.0
 * Author: Moove Logistica
 * Author URI: https://moovelogistica.pt/
 * Developer: Moove Logística
 * Developer URI: https://moovelogistica.pt/
 * Text Domain: moove-logistica-for-woocommerce
 * Domain Path: /languages
 *  *
 * Woo: 
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package WooCommerce\Admin
 */


if( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

$API_MOOVE_SHOPIFY_TOKEN = "d41d8cd98f00b204e9800998ecf8427e";
$API_MOOVE_SHOPIFY = "https://mooveshopifapi.herokuapp.com/moove_shopify";
$API_KEY = "";
$API_MOOVE = "https://api.moovelogistica.pt/moove_api/v1";
$RESULT_SHIPMENTS = array();
$RESULT_ORDERS = array();
$edit = false;
$order_edit;

function moove_registerSubmenu() {
    add_submenu_page( 'woocommerce', 
					'Moove Logística', 
					'Moove Logística', 
					'manage_options', 
					'moove-logistica', 
					'moove_submenuCallback' ); 
}
function moove_submenuCallback() {
	?>
	<?php  
        if( isset( $_GET[ 'tab' ] ) ) {  
            $active_tab = sanitize_key($_GET[ 'tab' ]);  
            $edit = false;
        } 
        elseif( isset( $_GET[ 'action' ] ) ) {  
            $order_id = sanitize_key($_GET[ 'order_id' ]);  
            $GLOBALS['edit'] = true;
            $GLOBALS['order_edit'] = wc_get_order( $order_id );
            $active_tab = 'tab_2';
        }
        else {
            $GLOBALS['RESULT_SHIPMENTS'] = array();
            $active_tab = 'tab_1';
            $GLOBALS['API_KEY'] = moove_getAPIKey();
        }
        if(isset($_POST['submit_settings']))
        {
            moove_saveKey();
            $message = "API Key " .sanitize_key($_POST["txtApiKey"]) ." saved!";
            $message_esc = esc_html($message);
            echo "<script type='text/javascript'>alert('$message_esc');</script>";
        } 
        if(isset($_POST['submit_shipment']))
        {
            moove_createShipment();
            $message = "Your shipment was sent to Moove!";
            $message_esc = esc_html($message);
            echo "<script type='text/javascript'>alert('$message_esc');</script>";
        } 
        if(isset($_POST['search_shipments']))
        {
            $GLOBALS['RESULT_SHIPMENTS'] = array();
            $active_tab = 'tab_4';
            $GLOBALS['RESULT_SHIPMENTS'] = moove_getShipments();
        } 
	?>  
	<div class="wrap">
		<h2>Moove®'s options for your shipping management</h2>
		<div class="description"></div>
		<?php settings_errors(); ?> 

		<h2 class="nav-tab-wrapper">  
			<a href="?page=moove-logistica&tab=tab_1" class="nav-tab <?php echo $active_tab == 'tab_1' ? 'nav-tab-active' : ''; ?>">Edit settings</a>  
			<a href="?page=moove-logistica&tab=tab_2" class="nav-tab <?php echo $active_tab == 'tab_2' ? 'nav-tab-active' : ''; ?>">Create manual shipment</a>  
			<a href="?page=moove-logistica&tab=tab_3" class="nav-tab <?php echo $active_tab == 'tab_3' ? 'nav-tab-active' : ''; ?>">Create shipments from orders</a>  
			<a href="?page=moove-logistica&tab=tab_4" class="nav-tab <?php echo $active_tab == 'tab_4' ? 'nav-tab-active' : ''; ?>">Manage shipments</a> 
			<a href="?page=moove-logistica&tab=tab_6" class="nav-tab <?php echo $active_tab == 'tab_6' ? 'nav-tab-active' : ''; ?>">Moove®'s Support</a>  
		</h2>  
		
		<form method="post" action="?page=moove-logistica"> 
		<?php
			if( $active_tab == 'tab_1' ) {  
				$GLOBALS['API_KEY'] = moove_getAPIKey();
				settings_fields( 'setting-group-1' );
				do_settings_sections( 'my-menu-slug-1' );
				submit_button('Save settings', 'primary', 'submit_settings', false);
			} else if( $active_tab == 'tab_2' )  {
				settings_fields( 'setting-group-2' );
				do_settings_sections( 'my-menu-slug-2' );
				submit_button('Send shipment', 'primary', 'submit_shipment', false);
			} else if( $active_tab == 'tab_3' )  {
				settings_fields( 'setting-group-3' );
				do_settings_sections( 'my-menu-slug-3' );
                $GLOBALS['RESULT_ORDERS'] = moove_getOrders();
                $myListTable = new moove_OrdersLT();
                echo '</pre><div class="wrap">'; 
                $myListTable->prepare_items(); 
                $myListTable->display(); 
                echo '</div>'; 
			}else if( $active_tab == 'tab_4' )  {
				settings_fields( 'setting-group-4' );
				do_settings_sections( 'my-menu-slug-4' );
                submit_button('Search', 'primary', 'search_shipments', false);
                $myListTable = new moove_ShipmentsLT();
                echo '</pre><div class="wrap">'; 
                $myListTable->prepare_items(); 
                $myListTable->display(); 
                echo '</div>'; 
			}else if( $active_tab == 'tab_6' )  {
				settings_fields( 'setting-group-6' );
				do_settings_sections( 'my-menu-slug-6' );
			}
		?>
	 </form> 
	</div>
	<?php
}


function moove_addScript() {
	if ( ! class_exists( 'Automattic\WooCommerce\Admin\Loader' ) || ! \Automattic\WooCommerce\Admin\Loader::is_admin_or_embed_page() ) {
		return;
	}
	
	$script_path       = '/build/index.js';
	$script_asset_path = dirname( __FILE__ ) . '/build/index.asset.php';
	$script_asset      = file_exists( $script_asset_path )
		? require( $script_asset_path )
		: array( 'dependencies' => array(), 'version' => filemtime( $script_path ) );
	$script_url = plugins_url( $script_path, __FILE__ );

	wp_register_script(
		'moove-logistica-for-woocommerce',
		$script_url,
		$script_asset['dependencies'],
		$script_asset['version'],
		true
	);

	wp_register_style(
		'moove-logistica-for-woocommerce',
		plugins_url( '/build/index.css', __FILE__ ),
		// Add any dependencies styles may have, such as wp-components.
		array(),
		filemtime( dirname( __FILE__ ) . '/build/index.css' )
	);

	wp_enqueue_script( 'moove-logistica-for-woocommerce' );
	wp_enqueue_style( 'moove-logistica-for-woocommerce' );
}

function moove_initializeTheme() {  
    add_settings_section(  
        'page_1_section',       
        'To use Moove® Shipping Plugin, you have to be a Moove® Partner',            
        'moove_pgSettingsCallback', 
        'my-menu-slug-1'           

    );

	add_settings_section(  
        'page_2_section',         
        'Create your shipment at Moove®',              
        'moove_pgCreateCallback', 
        'my-menu-slug-2'           
    );

	add_settings_section(  
        'page_3_section',         
        'Manage orders - Create Shipment',              
        'moove_pgOrdersCallback', 
        'my-menu-slug-3'           
    );

	add_settings_section(  
        'page_4_section',         
        'Manage shipments',              
        'moove_pgMngShipCallback', 
        'my-menu-slug-4'           
    );

    add_settings_section(  
        'page_6_section',         
        'Moove® Partner Help Session',              
        'moove_pgHelpCallback', 
        'my-menu-slug-6'           
    );

 	/* ----------------------------------------------------------------------------- */
    /* Option Create Manual Shipment */
    /* ----------------------------------------------------------------------------- */ 
    add_settings_field (   
        'txtClientNumber',                    
        'Client number',                    
        'moove_txtClientNumberCallback',   
        'my-menu-slug-2',      
        'page_2_section',      
        array(                 
            'Insert the request identification.',
        )  
    );  
    register_setting(  
        'setting-group-2',  
        'txtClientNumber'  
    );
	add_settings_field (   
        'txtDispatchDate',                    
        'Dispatch date',                    
        'moove_txtDispatchDateCallback',   
        'my-menu-slug-2',      
        'page_2_section',      
        array(                 
            'Insert the object shipping date',
        )  
    );  
    register_setting(  
        'setting-group-2',  
        'txtDispatchDate'  
    );
	add_settings_field (   
        'txtWeightInGrams',                    
        'Weight',                    
        'moove_txtWeightInGramsCallback',   
        'my-menu-slug-2',      
        'page_2_section',      
        array(                 
            ' Insert the object weight in grams',
        )  
    );  
    register_setting(  
        'setting-group-2',  
        'txtWeightInGrams'  
    );
	add_settings_field (   
        'txtWidthInCentimeters',                    
        'Width (optional)',                    
        'moove_txtWidthInCentimetersCallback',   
        'my-menu-slug-2',      
        'page_2_section',      
        array(                 
            'Insert the object width in centimeters',
        )  
    );  
    register_setting(  
        'setting-group-2',  
        'txtWidthInCentimeters'  
    );
	add_settings_field (   
        'txtLengthInCentimeters',                    
        'Length (optional)',                    
        'moove_txtLengthInCentimetersCallback',   
        'my-menu-slug-2',      
        'page_2_section',      
        array(                 
            'Insert the object length in centimeters',
        )  
    );  
    register_setting(  
        'setting-group-2',  
        'txtLengthInCentimeters'  
    );
	add_settings_field (   
        'txtHeightInCentimeters',                    
        'Height (optional)',                    
        'moove_txtHeightInCentimetersCallback',   
        'my-menu-slug-2',      
        'page_2_section',      
        array(                 
            'Insert the object height in centimeters',
        )  
    );  
    register_setting(  
        'setting-group-2',  
        'txtHeightInCentimeters'  
    );
	add_settings_field (   
        'txtInvoiceNumber',                    
        'Invoice number (optional)',                    
        'moove_txtInvoiceNumberCallback',   
        'my-menu-slug-2',      
        'page_2_section',      
        array(                 
            'Insert the document number',
        )  
    );  
    register_setting(  
        'setting-group-2',  
        'txtNoteOne'  
    );
	add_settings_field (   
        'txtNoteOne',                    
        'Notes (optional)',                    
        'moove_txtNoteOneCallback',   
        'my-menu-slug-2',      
        'page_2_section',      
        array(                 
            'Insert the post notes',
        )  
    );  
    register_setting(  
        'setting-group-2',  
        'txtNoteOne'  
    );
	add_settings_field (   
        'txtNoteTwo',                    
        'Additional notes (optional)',                    
        'moove_txtNoteTwoCallback',   
        'my-menu-slug-2',      
        'page_2_section',      
        array(                 
            'Insert the additional internal notes about posting',
        )  
    );  
    register_setting(  
        'setting-group-2',  
        'txtNoteTwo'  
    );
	add_settings_field (   
        'txtRecipientCharge',                    
        'Recipient charge (optional)',                    
        'moove_txtRecipientChargeCallback',   
        'my-menu-slug-2',      
        'page_2_section',      
        array(                 
            'Insert the Amount to be charged at destination, if applicable. Can only be completed if "bill at destination" has been selected as an additional service',
        )  
    );  
    register_setting(  
        'setting-group-2',  
        'txtRecipientCharge'  
    );
	add_settings_field (   
        'txtVolumesNumber',                    
        'Number of post volumes (optional)',                    
        'moove_txtVolumesNumberCallback',   
        'my-menu-slug-2',      
        'page_2_section',      
        array(                 
            'Insert the number of post volumes',
        )  
    );  
    register_setting(  
        'setting-group-2',  
        'txtVolumesNumber'  
    );
	add_settings_field (   
        'txtCountry',                    
        'Recipient country',                    
        'moove_txtCountryCallback',   
        'my-menu-slug-2',      
        'page_2_section',      
        array(                 
            'Insert the recipient country',
        )  
    );  
    register_setting(  
        'setting-group-2',  
        'txtCountry'  
    );
	add_settings_field (   
        'txtZipCode',                    
        'Recipient ZIP code',                    
        'moove_txtZipCodeCallback',   
        'my-menu-slug-2',      
        'page_2_section',      
        array(                 
            'Insert the recipient ZIP code',
        )  
    );  
    register_setting(  
        'setting-group-2',  
        'txtZipCode'  
    );
	add_settings_field (   
        'txtAddress',                    
        'Recipient address',                    
        'moove_txtAddressCallback',   
        'my-menu-slug-2',      
        'page_2_section',      
        array(                 
            'Insert the recipient address',
        )  
    );  
    register_setting(  
        'setting-group-2',  
        'txtAddress'  
    );
	add_settings_field (   
        'txtCity',                    
        'Recipient city',                    
        'moove_txtCityCallback',   
        'my-menu-slug-2',      
        'page_2_section',      
        array(                 
            'Insert the recipient city',
        )  
    );  
    register_setting(  
        'setting-group-2',  
        'txtCity'  
    );
	add_settings_field (   
        'txtState',                    
        'Recipient state',                    
        'moove_txtStateCallback',   
        'my-menu-slug-2',      
        'page_2_section',      
        array(                 
            'Insert the recipient state',
        )  
    );  
    register_setting(  
        'setting-group-2',  
        'txtState'  
    );
	add_settings_field (   
        'txtContactName',                    
        'Recipient contact name',                    
        'moove_txtContactNameCallback',   
        'my-menu-slug-2',      
        'page_2_section',      
        array(                 
            'Insert the recipient contact name',
        )  
    );  
    register_setting(  
        'setting-group-2',  
        'txtContactName'  
    );
	add_settings_field (   
        'txtPhone',                    
        'Recipient phone number (optional)',                    
        'moove_txtPhoneCallback',   
        'my-menu-slug-2',      
        'page_2_section',      
        array(                 
            'Insert the recipient phone number',
        )  
    );  
    register_setting(  
        'setting-group-2',  
        'txtPhone'  
    );
	add_settings_field (   
        'txtEmail',                    
        'Recipient email address (optional)',                    
        'moove_txtEmailCallback',   
        'my-menu-slug-2',      
        'page_2_section',      
        array(                 
            'Insert the recipient email address',
        )  
    );  
    register_setting(  
        'setting-group-2',  
        'txtEmail'  
    );
	add_settings_field (   
        'txtAdditionalServices',                    
        'Additional services (optional)',                    
        'moove_txtAdditionalServicesCallback',   
        'my-menu-slug-2',      
        'page_2_section',      
        array(                 
            'Insert additional services requested by the customer for posting',
        )  
    );  
    register_setting(  
        'setting-group-2',  
        'txtAdditionalServices'  
    );
	
	/* ----------------------------------------------------------------------------- */
    /* Option Manage Shipments */
    /* ----------------------------------------------------------------------------- */ 
	add_settings_field (   
        'txtStartDate',                    
        'Start date',                    
        'moove_txtStartDateCallback',   
        'my-menu-slug-4',      
        'page_4_section',      
        array(                 
            'Insert start date filter',
        )  
    );  
    register_setting(  
        'setting-group-4',  
        'txtStartDate'  
    );
	add_settings_field (   
        'txtEndDate',                    
        'End date',                    
        'moove_txtEndDateCallback',   
        'my-menu-slug-4',      
        'page_4_section',      
        array(                 
            'Insert end date filter',
        )  
    );  
    register_setting(  
        'setting-group-4',  
        'txtEndDate'  
    );
	add_settings_field (   
        'ddlStatus',                    
        'Status (Optional)',                    
        'moove_ddlStatusCallback',   
        'my-menu-slug-4',      
        'page_4_section',      
    );  
    register_setting(  
        'setting-group-4',  
        'ddlStatus'  
    );

    /* ----------------------------------------------------------------------------- */
    /* Option Settings */
    /* ----------------------------------------------------------------------------- */ 
    add_settings_field (   
        'txtApiKey',                    
        'API Key',                    
        'moove_txtApiKeyCallback',   
        'my-menu-slug-1',      
        'page_1_section',      
        array(                 
            'Insert the API Key we sent you',
        )  
    );  
    register_setting(  
        'setting-group-1',  
        'txtApiKey'  
    );
} 

add_action('admin_enqueue_scripts', 'moove_addScript' );
add_action('admin_menu', 'moove_registerSubmenu',99);
add_action('admin_init', 'moove_initializeTheme');

function moove_pgSettingsCallback() {  
    echo '<p>Insert bellow the informations we sent you</p>';  
	$url = get_permalink(wc_get_page_id('shop'));
	$store_name = parse_url($url, PHP_URL_HOST);
    $store_name_esc = esc_html($store_name);
	echo "Your Store Name is <b> $store_name_esc <b>"; 
}
function moove_pgCreateCallback() {  
    echo '<p>Insert bellow the informations about your shipment.</p>';  
    echo '<p><b>Importante: fulfil all obligatory fields </b></p>';  
} 
function moove_pgOrdersCallback() {  
    echo '<p>Select the order you want to create a Moove Shipment. </p>';
    echo '<p>Point your cursor to row the order you want to create.</p>';  
} 
function moove_pgMngShipCallback() {  
    echo '<p>Filter</p>';  
} 
function moove_pgHelpCallback() {  
	echo '<br>';  
	echo '<p><b>1- Settings</b></p>';  
	echo '<p>First of all, to use Moove Shipping App you have to be a Moove Partner.</p>';  
	echo '<p>The first step you have to do is setup the API Key we sent you at the time of onboarding.</p>';  
	echo '<p>Click in the "Edit Settings" tab to store the API Key.</p>';  
	echo '<p>PS: You have to do this one single time, the key is stored at Shopify database and you will have to update the key only if a Moove is required.</p>';  
	echo '<br><br>';


	echo '<p><b>2- Creating your Shipment</b></p>';  
	echo '<p>Once you setup the API Key at Settings session, you are able to create a shipment</p>';  
	echo '<p>You have two ways to send your shipments to Moove®:</p>';  
	echo '<p><b> &#8226; Create from Woocommerce Orders: </b><p>';
	echo '<p>If you access the menu "Create shipments from orders"</p>';  
	echo '<p>You will find out all your orders filtered by "Paid" and "Unfulfilled"</p>';  
	echo '<p>Select the order you want to create a Moove® shipment and click at "Create" button</p>';  
	echo '<p>You will be redirect to a check-out informations page.</p>';  
	echo '<p>Please check if all informations are correct, select de dispatch date (this is an obligatory information) and send us your shipment.</p>';  
	echo '<p><b>IMPORANT!</b></p>';  
	echo '<p><b> Update your order status and other informations provided by Moove® at your Store !!!</b></p>';  
	echo '<p><b> &#8226; Create blank Moove®s Shipment: </b><p>';
	echo '<p> You can create a shipment at Moove® fulfilling all the informations you have</p>';  
	echo '<p><b>IMPORANT!</b></p>';  
	echo '<p> We strongly recommend you to use this option as a contingency</p>';  
	echo '<p> Click at the "Create manual shipment" tab, fulfill all the obligatories fields and click in the "Send" button to send us your request</p>';  
	echo '<br><br>';

	echo '<p><b>3-  Checking your Shipments</b></p>';  
	echo '<p>Once you create a shipment, you are able to check and manage this shipments acording to Moove systems</p>';  
	echo '<p> Click at the "Manage shipments" tab, fulfill the period you want to search (these are obligatories fields)</p>';  
	echo '<p> If you want to, you can filter by status too.</p>';  
	echo '<br><br>';

	echo '<p><b>If you have additional doubts or problems, we will be glad to keep in touch !</b></p>';  
	echo '<p> Click at link bellow and go to our contact session.</p>';  
	echo '<p><a href="https://moovelogistica.pt/">Moove Logistica e Transporte</a></p>';  
} 

/* ----------------------------------------------------------------------------- */
/* Option Settings */
/* ----------------------------------------------------------------------------- */ 
function moove_txtApiKeyCallback($args) {  
    ?>
    <input type="text" id="txtApiKey" style="width:400px;" name="txtApiKey" value="<?php echo esc_html($GLOBALS['API_KEY']) ?>">
    <p class="description txtApiKey"> <?php echo esc_html($args[0]) ?> </p>
    <?php      
} 
function moove_getAPIKey(){
	$url = get_permalink(wc_get_page_id('shop'));
	$store_name = parse_url($url, PHP_URL_HOST);

	$moove_getAPIKeyEndpoint = $GLOBALS['API_MOOVE_SHOPIFY'] . "/" . $store_name . "/";

	$args = array(
	 	'headers' => array(
	 		'Authorization' => 'Bearer ' . $GLOBALS['API_MOOVE_SHOPIFY_TOKEN']
	 	)
	);

	$response = wp_remote_get( $moove_getAPIKeyEndpoint, $args );

	$http_code = wp_remote_retrieve_response_code( $response );
	$body = wp_remote_retrieve_body($response);

	if($http_code == 200){
		if($body == null){
		 	return "";
		}
		else{
			$data = json_decode($body);
		 	return $data->apiKey;
		}
	}
	elseif($http_code != null){
		printf("An error ocurred when call Moove API. Error Code: " . esc_html($http_code));
		return "";
	}
    else{
        return "";
    }
}
function moove_saveKey(){
	$url = get_permalink(wc_get_page_id('shop'));
	$store_name = parse_url($url, PHP_URL_HOST);

    $valueTxtApiKey = sanitize_text_field($_POST["txtApiKey"]);

    if ( empty( $valueTxtApiKey ) ) {
        return false;
    }

	$moove_getAPIKeyEndpoint = $GLOBALS['API_MOOVE_SHOPIFY'] . "/" . $store_name . "/" . $valueTxtApiKey;

	$args = array(
	 	'headers' => array(
	 		'Authorization' => 'Bearer ' . $GLOBALS['API_MOOVE_SHOPIFY_TOKEN']
	 	)
	);

	$response = wp_remote_post( $moove_getAPIKeyEndpoint, $args );
}

/* ----------------------------------------------------------------------------- */
/* Option Create Manual Shipment */
/* ----------------------------------------------------------------------------- */ 
function moove_txtClientNumberCallback($args) {
    if($GLOBALS['edit'] == false){ 
        ?>
        <input type="text" id="txtClientNumber" style="width:400px;" name="txtClientNumber" value="<?php echo get_option(esc_html('txtClientNumber')) ?>">
        <p class="description txtClientNumber"> <?php echo esc_html($args[0]) ?> </p>
        <?php 
    }else{
        $client_number = $GLOBALS['order_edit']->get_customer_id();
        ?>
        <input type="text" id="txtClientNumber" style="width:400px;" name="txtClientNumber" readonly=true value="<?php echo esc_html($client_number) ?>">
        <p class="description txtClientNumber"> <?php echo esc_html($args[0]) ?> </p>
        <?php 
    }     
} 
function moove_txtDispatchDateCallback($args) {  
    ?>
    <input type="date" id="txtDispatchDate" name="txtDispatchDate" value="<?php echo get_option(esc_html('txtDispatchDate')) ?>">
    <p class="description txtDispatchDate"> <?php echo esc_html($args[0]) ?> </p>
    <?php      
} 
function moove_txtWeightInGramsCallback($args) {  
    if($GLOBALS['edit'] == false){ 
        ?>
        <input type="decimal" id="txtWeightInGrams" name="txtWeightInGrams" value="<?php echo get_option(esc_html('txtWeightInGrams')) ?>">
        <p class="description txtWeightInGrams"> <?php echo esc_html($args[0]) ?> </p>
        <?php    
    } else{
        $itemRes['total_items'] =  $GLOBALS['order_edit']->get_item_count();

        $weight = 0;
		if ( sizeof( $GLOBALS['order_edit']->get_items() ) > 0 ) {
			foreach( $GLOBALS['order_edit']->get_items() as $item ) {
				if ( $item['product_id'] > 0 ) {
					$_product = $item->get_product();
					if ( ! $_product->is_virtual() ) {
						$weight += floatval($_product->get_weight() * 1000) * $item['quantity'];
					}
				}
			}
		}

        ?>
        <input type="text" id="txtWeightInGrams" name="txtWeightInGrams" readonly=true value="<?php echo esc_html($weight) ?>">
        <p class="description txtWeightInGrams"> <?php echo esc_html($args[0]) ?> </p>
        <?php 
    }
} 
function moove_txtWidthInCentimetersCallback($args) {  
    ?>
    <input type="decimal" id="txtWidthInCentimeters"  name="txtWidthInCentimeters" value="<?php echo get_option(esc_html('txtWidthInCentimeters')) ?>">
    <p class="description txtWidthInCentimeters"> <?php echo esc_html($args[0]) ?> </p>
    <?php      
} 
function moove_txtLengthInCentimetersCallback($args) {  
    ?>
    <input type="decimal" id="txtLengthInCentimeters" name="txtLengthInCentimeters" value="<?php echo get_option(esc_html('txtLengthInCentimeters')) ?>">
    <p class="description txtLengthInCentimeters"> <?php echo esc_html($args[0]) ?> </p>
    <?php      
} 
function moove_txtHeightInCentimetersCallback($args) {  
    ?>
    <input type="decimal" id="txtHeightInCentimeters" name="txtHeightInCentimeters" value="<?php echo get_option(esc_html('txtHeightInCentimeters')) ?>">
    <p class="description txtHeightInCentimeters"> <?php echo esc_html($args[0]) ?> </p>
    <?php      
} 
function moove_txtInvoiceNumberCallback($args) {  
    ?>
    <input type="text" id="txtInvoiceNumber" style="width:400px;" name="txtInvoiceNumber" value="<?php echo get_option(esc_html('txtInvoiceNumber')) ?>">
    <p class="description txtInvoiceNumber"> <?php echo esc_html($args[0]) ?> </p>
    <?php      
} 
function moove_txtNoteOneCallback($args) {  
    ?>
    <input type="text" id="txtNoteOne" style="width:400px;" name="txtNoteOne" value="<?php echo get_option(esc_html('txtNoteOne')) ?>">
    <p class="description txtNoteOne"> <?php echo esc_html($args[0]) ?> </p>
    <?php      
} 
function moove_txtNoteTwoCallback($args) {  
    ?>
    <input type="text" id="txtNoteTwo" style="width:400px;" name="txtNoteTwo" value="<?php echo esc_html(get_option('txtNoteTwo')) ?>">
    <p class="description txtNoteTwo"> <?php echo esc_html($args[0]) ?> </p>
    <?php      
} 
function moove_txtRecipientChargeCallback($args) {  
    ?>
    <input type="text" id="txtRecipientCharge" style="width:400px;" name="txtRecipientCharge" value="<?php echo get_option(esc_html('txtRecipientCharge')) ?>">
    <p class="description txtRecipientCharge"> <?php echo esc_html($args[0]) ?> </p>
    <?php      
} 
function moove_txtVolumesNumberCallback($args) {  
    ?>
    <input type="number" id="txtVolumesNumber" name="txtVolumesNumber" value="<?php echo get_option(esc_html('txtVolumesNumber')) ?>">
    <p class="description txtVolumesNumber"> <?php echo esc_html($args[0]) ?> </p>
    <?php      
} 
function moove_txtCountryCallback($args) {  
    if($GLOBALS['edit'] == false){ 
        ?>
        <input type="text" id="txtCountry" style="width:400px;" name="txtCountry" value="<?php echo get_option(esc_html('txtCountry')) ?>">
        <p class="description txtCountry"> <?php echo esc_html($args[0]) ?> </p>
        <?php  
    }   
    else{
        $country = $GLOBALS['order_edit']->get_shipping_country();
        ?>
        <input type="text" id="txtCountry" style="width:400px;" readonly=true name="txtCountry" value="<?php echo esc_html($country) ?>">
        <p class="description txtCountry"> <?php echo esc_html($args[0]) ?> </p>
        <?php  
    }
} 
function moove_txtZipCodeCallback($args) { 
    if($GLOBALS['edit'] == false){  
        ?>
        <input type="text" id="txtZipCode" style="width:400px;" name="txtZipCode" value="<?php echo get_option(esc_html('txtZipCode')) ?>">
        <p class="description txtZipCode"> <?php echo esc_html($args[0]) ?> </p>
        <?php   
    }  
    else{
        $zipCode = $GLOBALS['order_edit']->get_shipping_postcode();
        ?>
        <input type="text" id="txtZipCode" style="width:400px;" readonly=true name="txtZipCode" value="<?php echo esc_html($zipCode) ?>">
        <p class="description txtZipCode"> <?php echo esc_html($args[0]) ?> </p>
        <?php   
    } 
} 
function moove_txtAddressCallback($args) {  
    if($GLOBALS['edit'] == false){  
        ?>
        <input type="text" id="txtAddress" style="width:400px;" name="txtAddress" value="<?php echo get_option(esc_html('txtAddress')) ?>">
        <p class="description txtAddress"> <?php echo esc_html($args[0]) ?> </p>
        <?php      
    }
    else{
        $address = $GLOBALS['order_edit']->get_shipping_address_1() . " / " . $GLOBALS['order_edit']->get_shipping_address_2() ;
        ?>
        <input type="text" id="txtAddress" style="width:400px;" readonly=true name="txtAddress" value="<?php echo esc_html($address) ?>">
        <p class="description txtAddress"> <?php echo esc_html($args[0]) ?> </p>
        <?php  
    }
} 
function moove_txtCityCallback($args) { 
    if($GLOBALS['edit'] == false){   
        ?>
        <input type="text" id="txtCity" style="width:400px;" name="txtCity" value="<?php echo get_option(esc_html('txtCity')) ?>">
        <p class="description txtCity"> <?php echo esc_html($args[0]) ?> </p>
        <?php      
    }
    else{   
        $city = $GLOBALS['order_edit']->get_shipping_city();
        ?>
        <input type="text" id="txtCity" style="width:400px;" readonly=true name="txtCity" value="<?php echo esc_html($city) ?>">
        <p class="description txtCity"> <?php echo esc_html($args[0]) ?> </p>
        <?php      
    }
} 
function moove_txtStateCallback($args) {  
    if($GLOBALS['edit'] == false){  
        ?>
        <input type="text" id="txtState" style="width:400px;" name="txtState" value="<?php echo get_option(esc_html('txtState')) ?>">
        <p class="description txtState"> <?php echo esc_html($args[0]) ?> </p>
        <?php    
    }else{
        $state = $GLOBALS['order_edit']->get_shipping_state();
        ?>
        <input type="text" id="txtState" style="width:400px;" readonly=true name="txtState" value="<?php echo esc_html($state) ?>">
        <p class="description txtState"> <?php echo esc_html($args[0]) ?> </p>
        <?php    
    }
} 
function moove_txtContactNameCallback($args) {  
    if($GLOBALS['edit'] == false){  
        ?>
        <input type="text" id="txtContactName" style="width:400px;" name="txtContactName" value="<?php echo get_option(esc_html('txtContactName')) ?>">
        <p class="description txtContactName"> <?php echo esc_html($args[0]) ?> </p>
        <?php     
    } 
    else{  
        $contact_name = $GLOBALS['order_edit']->get_billing_first_name();
        ?>
        <input type="text" id="txtContactName" style="width:400px;" readonly=true name="txtContactName" value="<?php echo esc_html($contact_name) ?>">
        <p class="description txtContactName"> <?php echo esc_html($args[0]) ?> </p>
        <?php     
    } 
} 
function moove_txtPhoneCallback($args) {  
    ?>
    <input type="text" id="txtPhone" style="width:400px;" name="txtPhone" value="<?php echo get_option(esc_html('txtPhone')) ?>">
    <p class="description txtPhone"> <?php echo esc_html($args[0]) ?> </p>
    <?php      
} 
function moove_txtEmailCallback($args) {  
    ?>
    <input type="text" id="txtEmail" style="width:400px;" name="txtEmail" value="<?php echo get_option(esc_html('txtEmail')) ?>">
    <p class="description txtEmail"> <?php echo esc_html($args[0]) ?> </p>
    <?php      
} 
function moove_txtAdditionalServicesCallback($args) {  
    ?>
    <input type="text" id="txtAdditionalServices" style="width:400px;" name="txtAdditionalServices" value="<?php echo get_option(esc_html('txtAdditionalServices')) ?>">
    <p class="description txtAdditionalServices"> <?php echo esc_html($args[0]) ?> </p>
    <?php      
} 
function moove_createShipment(){
	$token = moove_getAPIKey();
	$createEndpoint = $GLOBALS['API_MOOVE'] . "/criar_postagem";

	$post = new moove_WSPost();
	$post->client_number = sanitize_text_field($_POST["txtClientNumber"]);
	$post->dispatch_date = sanitize_text_field($_POST["txtDispatchDate"]);
	$post->weight_in_grams = floatval(sanitize_text_field($_POST["txtWeightInGrams"]));

	$recipient_address = new moove_WSRecipientAddress();
	$recipient_address->country = sanitize_text_field($_POST["txtCountry"]);
	$recipient_address->zipcode = sanitize_text_field($_POST["txtZipCode"]);
	$recipient_address->street_name = sanitize_text_field($_POST["txtAddress"]);
	$recipient_address->city = sanitize_text_field($_POST["txtCity"]);
	$recipient_address->state = sanitize_text_field($_POST["txtState"]);

	$recipient_data = new moove_WSRecipientData();
	$recipient_data->name = sanitize_text_field($_POST["txtContactName"]);

	
    $valueTxtWidthInCentimeters = sanitize_text_field( $_POST['txtWidthInCentimeters'] );
    if ( ! empty( $valueTxtWidthInCentimeters ) ) {
        $post->width_in_centimeters = floatval($valueTxtWidthInCentimeters);
    }

    $valueTxtLengthInCentimeters = sanitize_text_field( $_POST['txtLengthInCentimeters'] );
    if ( ! empty( $valueTxtLengthInCentimeters ) ) {
        $post->length_in_centimeters = floatval($valueTxtLengthInCentimeters);
    }

    $valueTxtHeightInCentimeters = sanitize_text_field( $_POST['txtHeightInCentimeters'] );
    if ( ! empty( $valueTxtLengthInCentimeters ) ) {
        $post->height_in_centimeters = floatval(valueTxtHeightInCentimeters);
    }

    $valueTxtInvoiceNumber = sanitize_text_field( $_POST['txtInvoiceNumber'] );
    if ( ! empty( $valueTxtInvoiceNumber ) ) {
        $post->invoice_number = $valueTxtInvoiceNumber;
    }

    $valueTxtNoteOne = sanitize_text_field( $_POST['txtNoteOne'] );
    if ( ! empty( $valueTxtNoteOne ) ) {
        $post->note_1 = $valueTxtNoteOne;
    }

    $valueTxtNoteTwo = sanitize_text_field( $_POST['txtNoteTwo'] );
    if ( ! empty( $valueTxtNoteTwo ) ) {
        $post->note_2 = $valueTxtNoteTwo;
    }

    $valueTxtRecipientCharge = sanitize_text_field( $_POST['txtRecipientCharge'] );
    if ( ! empty( $valueTxtRecipientCharge ) ) {
        $post->recipient_charge = $valueTxtRecipientCharge;
    }

    $valueTxtVolumesNumber = sanitize_text_field( $_POST['txtVolumesNumber'] );
    if ( ! empty( $valueTxtVolumesNumber ) ) {
        $post->volumes = $valueTxtVolumesNumber;
    }

    $valueTxtPhone = sanitize_text_field( $_POST['txtPhone'] );
    if ( ! empty( $valueTxtPhone ) ) {
        $recipient_data->phone_1 = $valueTxtPhone;
    }
    
    $valueTxtEmail = sanitize_text_field( $_POST['txtEmail'] );
	if ( ! empty( $valueTxtEmail ) ){
        $recipient_data->email = $valueTxtEmail;
    }

    $valueTxtAdditionalServices = sanitize_text_field( $_POST['txtAdditionalServices'] );
	if ( ! empty( $valueTxtAdditionalServices ) ){
        $recipient_data->additional_services = array($valueTxtAdditionalServices);
    }
	
	$body = new moove_WSBody();
	$body->post = $post;
	$body->recipient_address = $recipient_address;
	$body->recipient_data = $recipient_data;

	$posts = array($body);
	$myObject = new moove_WSBaseClass();
	$myObject->posts = $posts;

	$jsonData = json_encode($myObject);

	
	$args = array(
		'body'    => $jsonData,
		'headers' => array(
			'Authorization' => $token,
			'Content-Type' => 'application/json'
		)
   	);
	
	// var_dump($token);
	// var_dump($createEndpoint);
	// var_dump($jsonData);

	$response = wp_remote_post( $createEndpoint, $args );

}

/* ----------------------------------------------------------------------------- */
/* Option Manage Shipments */
/* ----------------------------------------------------------------------------- */ 
function moove_txtStartDateCallback($args) {  
    ?>
    <input type="date" id="txtStartDate" name="txtStartDate" value="<?php echo get_option(esc_html('txtStartDate')) ?>">
    <p class="description txtStartDate"> <?php echo esc_html($args[0]) ?> </p>
    <?php      
} 
function moove_txtEndDateCallback($args) {  
    ?>
    <input type="date" id="txtEndDate" name="txtEndDate" value="<?php echo get_option(esc_html('txtEndDate')) ?>">
    <p class="description txtEndDate"> <?php echo esc_html($args[0]) ?> </p>
    <?php      
} 
function moove_ddlStatusCallback() {
	$options = get_option('plugin_options');
	echo "<select id='ddlStatus' name='plugin_options[dropdown1]'>";
	echo "<option value='all' selected='selected'>All</option>";
	echo "<option value='association_pending'>Association Pending</option>";
	echo "<option value='canceled'>Canceled</option>";
	echo "<option value='failure_1'>Failure 1st</option>";
	echo "<option value='failure_2'>Failure 2nd</option>";
	echo "<option value='failure_3'>Failure 3rd</option>";
	echo "<option value='in_distribution'>In Distribution</option>";
	echo "<option value='not_picked'>Not Picked</option>";
	echo "<option value='picked'>Picked</option>";
	echo "<option value='success'>Success</option>";
	echo "</select>";
}
function moove_getShipments(){

    $valueTxtStartDate = sanitize_text_field( $_POST['txtStartDate'] );
    $valueTxtEndDate = sanitize_text_field( $_POST['txtEndDate'] );

    if(!empty($valueTxtStartDate) && !empty($valueTxtEndDate)){
        $token = moove_getAPIKey();
        $moove_getShipmentsEndpoint = $GLOBALS['API_MOOVE'] . "/customer_posts?start_date=" . $valueTxtStartDate . "&end_date=" . $valueTxtEndDate . "&limit=50";

        $status =  sanitize_text_field($_POST["ddlStatus"]);
        //var_dump($status);

        if(!empty($status))
            $moove_getShipmentsEndpoint = $moove_getShipmentsEndpoint . "&status=" . $status;

        
        //var_dump($token);
        //var_dump($moove_getShipmentsEndpoint);

        $args = array(
            'headers' => array(
                'Authorization' => $token,
			    'Content-Type' => 'application/json'
            )
        );
    
        $response = wp_remote_get($moove_getShipmentsEndpoint, $args );
        //var_dump($response);
    
        $http_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body($response);

        if($http_code == 200){
            $item = array();
            $result = array();

            $jsonIterator = new RecursiveIteratorIterator(
                new RecursiveArrayIterator(json_decode($body, TRUE)),
                RecursiveIteratorIterator::SELF_FIRST);
            
            foreach ($jsonIterator as $key => $val) {
                if(($key == 'client_number') ||
                    ($key == 'tracking_code') ||
                    ($key == 'dispatch_date') ||
                    ($key == 'weight_in_grams') ||
                    ($key == 'created_at'))
                {
                    if(is_array($val)) {
                        continue;
                    } else {
                        if(strlen($val) > 50)
                            continue;

                        $item += [$key => $val];
                        if($key == 'created_at'){
                            array_push($result, $item);
                            $item = array();
                        }
                    }
                }
            }
            //var_dump($result);
            return $result;
        }
        else{
            echo 'Error calling customer_posts. Http error: ' . esc_html($http_code);
        }
        //var_dump($http_code);
        //var_dump($body);
    }
}

/* ----------------------------------------------------------------------------- */
/* Option Manage Orders */
/* ----------------------------------------------------------------------------- */ 
function moove_getOrders(){
    
    $itemRes = array();
    $result = array();

    $args = array(
        'limit' => 9999,
        'return' => 'ids',
        'status' => 'completed'
    );
    $query = new WC_Order_Query( $args );
    $orders = $query->get_orders();
    foreach( $orders as $order_id ) {
        $order = wc_get_order( $order_id );
        $order_data = $order->get_data();

        $itemRes['order_id'] = $order_data['id'];
        $itemRes['status'] = $order_data['status'];

        $user_id = get_post_meta( $order_id, '_customer_user', true );
        $customer = new WC_Customer( $user_id );

        $itemRes['customer_name'] = $customer->get_display_name();
        $itemRes['created_at'] = $order_data['date_created']->date('Y-m-d H:i:s');

        
        $itemRes['total_items'] =  $order->get_item_count();

        $weight = 0;
		if ( sizeof( $order->get_items() ) > 0 ) {
			foreach( $order->get_items() as $item ) {
				if ( $item['product_id'] > 0 ) {
					$_product = $item->get_product();
					if ( ! $_product->is_virtual() ) {
						$weight += floatval($_product->get_weight() * 1000) * $item['quantity'];
					}
				}
			}
		}

        $itemRes['total_weight'] = $weight;

        array_push($result, $itemRes);
        $itemRes = array();
    }

    //var_dump($result);
    return $result;
}


/* ----------------------------------------------------------------------------- */
/* Auxiliar classes to Create Shipment */
/* ----------------------------------------------------------------------------- */ 
class moove_WSBaseClass {}
class moove_WSBody{}
class moove_WSPost {}
class moove_WSRecipientAddress {}
class moove_WSRecipientData {}

/* ----------------------------------------------------------------------------- */
/* Auxiliar classes to Manage Shipments */
/* ----------------------------------------------------------------------------- */ 
class moove_ShipmentsLT extends WP_List_Table {

    function __construct(){
    global $status, $page;

        parent::__construct( array(
            'singular'  => __( 'shipment', 'mylisttable' ),     
            'plural'    => __( 'shipments', 'mylisttable' ),   
            'ajax'      => false        
    ) );
    }

  function column_default( $item, $column_name ) {
    switch( $column_name ) { 
        case 'client_number':
        case 'tracking_code':
        case 'dispatch_date':
        case 'weight_in_grams':
        case 'created_at':
            return $item[ $column_name ];
        default:
            return print_r( $item, true ) ; //Show the whole array for troubleshooting purposes
    }
  }

function get_columns(){
        $columns = array(
            'client_number' => __( 'Client #', 'mylisttable' ),
            'tracking_code'    => __( 'Tracking Code', 'mylisttable' ),
            'dispatch_date'    => __( 'Dispatch Date', 'mylisttable' ),
            'weight_in_grams'      => __( 'Weight (grams)', 'mylisttable' ),
            'created_at'      => __( 'Created At', 'mylisttable' )
        );
         return $columns;
    }
function prepare_items() {
  $columns  = $this->get_columns();
  $hidden   = array();
  $sortable = array();
  $this->_column_headers = array( $columns, $hidden, $sortable );
  $this->items = $GLOBALS['RESULT_SHIPMENTS'];;
}
} 

/* ----------------------------------------------------------------------------- */
/* Auxiliar classes to Manage Orders */
/* ----------------------------------------------------------------------------- */ 
class moove_OrdersLT extends WP_List_Table {

    function __construct(){
    global $status, $page;

        parent::__construct( array(
            'singular'  => __( 'order', 'mylisttable' ),     
            'plural'    => __( 'orders', 'mylisttable' ),   
            'ajax'      => false        
    ) );
    }

  function column_default( $item, $column_name ) {
    switch( $column_name ) { 
        case 'order_id':
        case 'status':
        case 'total_weight':
        case 'total_items':
        case 'customer_name':
        case 'created_at':
            return $item[ $column_name ];
        default:
            return print_r( $item, true ) ; //Show the whole array for troubleshooting purposes
    }
  }

function get_columns(){
        $columns = array(
            'order_id' => __( 'Order #', 'mylisttable' ),
            'status'    => __( 'Status', 'mylisttable' ),
            'total_weight'    => __( 'Total Weight (grams)', 'mylisttable' ),
            'total_items'      => __( 'Total Items Quantity', 'mylisttable' ),
            'customer_name'     => __( 'Customer Name', 'mylisttable' ),
            'created_at'      => __( 'Created At', 'mylisttable' )
        );
         return $columns;
    }

    function column_order_id($item) {
        $actions = array(
                  'create'      => sprintf('<a href="?page=%s&action=%s&order_id=%s">Create</a>',sanitize_key($_REQUEST['page']),'select_order',sanitize_key($item['order_id']))
              );
      
        return sprintf('%1$s %2$s', $item['order_id'], $this->row_actions($actions) );
      }

function prepare_items() {
  $columns  = $this->get_columns();
  $hidden   = array();
  $sortable = array();
  $this->_column_headers = array( $columns, $hidden, $sortable );
  $this->items = $GLOBALS['RESULT_ORDERS'];;
}
} 

?>