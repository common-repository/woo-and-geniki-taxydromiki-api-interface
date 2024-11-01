<?php
/* 
  Plugin Name: Geniki Taxydromiki for Woocommerce
  Plugin URI: http://emspace.gr
  Description: Provides interface with Geniki Taxydromiki web service API for Woocommerce
  Version: 1.0.0
  Author: emspace.gr 
  Author URI: http://emspace.gr
  License:           GPL-3.0+
  License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 */
if (!defined('ABSPATH'))
    exit;


load_plugin_textdomain('geniki-woocommerce', false, dirname(plugin_basename(__FILE__)) . '/languages/');

function mysite_woocommerce_order_status_completed( $order_id ) {

//get Settings

$soapurl= "https://voucher.taxydromiki.gr/JobServices.asmx" ;
if (get_option ('testmode') == 1)
{
$soapurl= "https://testvoucher.taxydromiki.gr/JobServices.asmx";
}

$username= get_option ('username');
$password = get_option ('password');
$appkey = get_option ('appkey');
$sel_methods= get_option('methods');


//get order
$order = new WC_Order($order_id);

//get shipping method of order
$shipping_methods = $order->get_shipping_methods(); 
foreach ($shipping_methods as $shipping_method)
{	 
}
$method_name=$shipping_method['item_meta']['method_id']['0'];

//get payment method
$payment_method_name= $order->__get('payment_method');


$services ="";
$CodAmount =0;

//check if cash on delivery
if(strcmp($payment_method_name,'cod')==0)
{
$services = 'αμ';
$CodAmount=$order->__get('order_total');
}

// Ti shipping tha exoyn energopoihmeno ?
//if(strcmp($method_name,'free_shipping')==0)
if( in_array(  $method_name,$sel_methods )	)
{
	$last_name=$order->__get('shipping_last_name');
	$first_name=$order->__get('shipping_first_name');
	$name = $last_name.' '.$first_name;
	$address = $order->__get('shipping_address_1') . ', '. $order->__get('shipping_address_2');
	$city = $order->__get('shipping_city').', '.$order->__get('shipping_country');
	$phone = $order->__get('billing_phone');
	$weight = 1;
	$pieces = 1;
	$zip= $order->__get('shipping_postcode');	
	$message =$order->customer_message;
	$ReceivedDate= date("Y-m-d");  
	
	
	//create voucher data
	$oVoucher = array(
	'OrderId' => $order_id,
	'Name' => $name,
	'Address' => $address,
	'City' => $city,
	'Telephone' => $phone,
	'Zip' => $zip,
	'Destination' => "",
	'Courier' => "",
	'Pieces' => $pieces,
	'Weight' => $weight,
	'Comments' => $message,
	'Services' => $services ,
	'CodAmount' => $CodAmount,
	'InsAmount' => 0,
	'VoucherNumber' => "",
	'SubCode' => "",
	'BelongsTo' => "",
	'DeliverTo' => "",
	'ReceivedDate' => $ReceivedDate
	);

		
	try { 
	 
	 $soap = new SoapClient($soapurl."?WSDL");
	 $oAuthResult = $soap->Authenticate(
	array(
	'sUsrName' => $username,
	'sUsrPwd' => $password,
	'applicationKey' => $appkey
	)
	);
	if ($oAuthResult->AuthenticateResult->Result != 0) {
	$order->add_order_note(__('Order not sent to Geniki Taxydromiki due to authentication failure', ''));

	}else
	{

	$xml = array(
	'sAuthKey' => $oAuthResult->AuthenticateResult->Key, 
	'oVoucher' => $oVoucher, 
	'eType' => "Voucher"
	);

	$oResult = $soap->CreateJob($xml);

	if($oResult->CreateJobResult->Result != 0) {
	$order->add_order_note(__('Job was not created successfully, please contact Geniki Taxydromiki', ''));
	}else
	{
	$xml = array (
	'authKey' => $oAuthResult->AuthenticateResult->Key,
	'voucherNo' => $oResult->CreateJobResult->Voucher,
	'language' => 'el'
	);
	$TT = $soap->TrackAndTrace($xml);

	$order->add_order_note(__('Job was sent successfully to Gen. Taxydromiki, Voucher number is '.$oResult->CreateJobResult->Voucher.' </br><a target="_blank" href="'.$soapurl.'/GetVouchersPdf?authKey='.urlencode($oAuthResult->AuthenticateResult->Key).'&voucherNumbers='.$oResult->CreateJobResult->Voucher.'&Format=Sticker&extraInfoFormat=None">Print<a>', ''));
	}


	$soap->ClosePendingJobs(
	array('sAuthKey' => $oAuthResult->AuthenticateResult->Key)
	);

	}

	} catch(SoapFault $fault) {
	$order->add_order_note(__('Error'.$fault, ''));
	}
}else{
//De xreiazetai	
	$order->add_order_note(__('Order send by other method', ''));
}

}
add_action( 'woocommerce_order_status_completed','mysite_woocommerce_order_status_completed' );


function geniki_admin_menu() {

    /* add new top level */
    add_menu_page(
		__( 'Gen. Taxidromiki', 'geniki-woocommerce' ),
		__( 'Gen. Taxidromiki', 'geniki-woocommerce' ),
		'manage_options',
		'geniki_admin_menu',
		'geniki_admin_page',
		plugins_url( '/', __FILE__ ) . '/images/gt-icon.png'
	);

	

    

}

function geniki_admin_page() 
{
global $woocommerce;


echo '<div class="wrap">';

echo '<h1>'.__('Settings page for Geniki Taxydromiki', 'geniki-woocommerce').'</h1><form method="post" action="options.php">';
settings_fields( 'geniki-group' );
do_settings_sections( 'geniki-group' );
echo '<table class="form-table">';

echo '<tr valign="top">';
echo '<th scope="row">'.__('Username','geniki-woocommerce').'</th>';
echo '<td><input type="text" name="username" value="'.get_option('username').'" /></td>';
echo '</tr>';

echo '<tr valign="top">';
echo '<th scope="row">'.__('Password','geniki-woocommerce').'</th>';
echo '<td><input type="text" name="password" value="'.get_option('password').'" /></td>';
echo '</tr>';

echo '<tr valign="top">';
echo '<th scope="row">'.__('AppKey','geniki-woocommerce').'</th>';
echo '<td><input type="text" name="appkey" value="'.get_option('appkey').'" /></td>';
echo '</tr>';

echo '<tr valign="top">';
echo '<th scope="row">'.__('Test Mode','geniki-woocommerce').'</th>';
$checked = ( (int)get_option ('testmode') == 1 ) ? 'checked="checked"' : '';
echo '<td><input type="checkbox" name="testmode"  value="1"  '.$checked.'/></td>';
echo '</tr>';
/**/
$woocommerce->shipping->load_shipping_methods();
echo '<tr valign="top">';
echo '<th scope="row">'.__('Shipping methods', 'geniki-woocommerce').'</th>';
echo '<td>'; 
$options3= get_option('methods');
echo "<select id='methods' name='methods[]' multiple='multiple'>";
foreach ($woocommerce->shipping->get_shipping_methods() as $shipping_method) {
$selected = false;
		if( in_array(  $shipping_method->id,$options3 )	) {
			$selected = true;			
			} 

echo "<option value='".$shipping_method->id."' " . selected( $selected, true, false ) . ">".$shipping_method->title."</option>";
}
echo '</select>';
echo '</td>';
echo '</tr>';

echo '</table>';
submit_button(); 
echo '</div>';



}


add_action( 'admin_menu', 'geniki_admin_menu' );
add_action( 'admin_init', 'register_geniki_settings' );
function register_geniki_settings() { // whitelist options 
  register_setting( 'geniki-group', 'username' );
  register_setting( 'geniki-group', 'password' );
  register_setting( 'geniki-group', 'appkey' );  
  register_setting( 'geniki-group', 'testmode' );
  register_setting( 'geniki-group', 'methods' );
}
?>