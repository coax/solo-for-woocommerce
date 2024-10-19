<?php
/**
 * Plugin Name: Solo for WooCommerce
 * Plugin URI: https://solo.com.hr/api-dokumentacija/dodaci
 * Description: Narudžba u tvojoj WooCommerce trgovini će automatski kreirati račun ili ponudu u servisu Solo.
 * Version: 1.6
 * Requires at least: 5.2
 * Requires PHP: 7.2
 * Author: Solo
 * Author URI: https://solo.com.hr/
 * License: CC BY-NC-ND
 * License URI: https://creativecommons.org/licenses/by-nc-nd/4.0/
 * Text Domain: solo-for-woocommerce
 * Domain Path: /languages
 */

// Disallow direct call to this file
if (!defined('WPINC')) {
	die;
}

//// Plugin version
if (!defined('SOLO_VERSION')) {
	define('SOLO_VERSION', '1.6');
}

//// Activate plugin
register_activation_hook(__FILE__, 'solo_woocommerce_activate');

function solo_woocommerce_activate() {
	// Check PHP version
	if (version_compare(PHP_VERSION, '7.2', '<')) {
		wp_die(__('Solo for WooCommerce dodatak ne podržava PHP ' . PHP_VERSION . '. Ažuriraj PHP na verziju 7.2 ili noviju.', 'solo-for-woocommerce'), __('Greška', 'solo-for-woocommerce'), array("back_link" => true));
	}

	// Check if WooCommerce plugin installed
	if (!class_exists('WooCommerce')) {
		wp_die(__('Solo for WooCommerce ne radi bez WooCommerce dodatka.<br>Prvo instaliraj WooCommerce i zatim aktiviraj ovaj dodatak.', 'solo-for-woocommerce'), __('Greška', 'solo-for-woocommerce'), array("back_link" => true));
	}

	// Check if Woo Solo Api plugin installed
	if (is_plugin_active('woo-solo-api/woo-solo-api.php')) {
		wp_die(__('Prvo deaktiviraj "Woo Solo Api" dodatak.', 'solo-for-woocommerce'), __('Greška', 'solo-for-woocommerce'), array("back_link" => true));
	}

	// Check if MX R1 plugin installed
	if (is_plugin_active('woocommerce-mx-r1/woocommerce-mx-r1.php')) {
		wp_die(__('Prvo deaktiviraj "WooCommerce MX R1 račun" dodatak.<br>Solo for WooCommerce automatski dodaje polja za pravne osobe (R1 račun) pri naručivanju.', 'solo-for-woocommerce'), __('Greška', 'solo-for-woocommerce'), array("back_link" => true));
	}

	// Add exchange rate to database
	solo_woocommerce_exchange(1);

	// Create custom table in database
	solo_woocommerce_create_table();

	// Inform
	solo_woocommerce_inform('activation');
}

//// Deactivate plugin
register_deactivation_hook(__FILE__, 'solo_woocommerce_deactivate');

function solo_woocommerce_deactivate() {
	// Delete exchange rate from database and remove scheduled job
	solo_woocommerce_exchange(4);

	// Delete temporary transients
	delete_transient('solo_tag');
	delete_transient('solo_url');

	// Inform
	solo_woocommerce_inform('deactivation');

	// Note: keep table with orders
}

//// Uninstall plugin
register_uninstall_hook(__FILE__, 'solo_woocommerce_uninstall');

function solo_woocommerce_uninstall() {
	// Delete exchange rate from database and remove scheduled job
	solo_woocommerce_exchange(4);

	// Delete temporary transients
	delete_transient('solo_tag');
	delete_transient('solo_url');

	// Delete plugin settings from database
	delete_option('solo_woocommerce_postavke');

	// Inform
	solo_woocommerce_inform('uninstall');

	// Note: keep table with orders
}

//// Inform on activation, deactivation, uninstall
function solo_woocommerce_inform($event) {
	global $wp_version;
	$woo_version = WC()->version;

	$plugin_data = array(
		'event' => $event,
		'site_url' => get_site_url(),
		'plugin_version' => SOLO_VERSION,
		'wordpress' => $wp_version,
		'woocommerce' => $woo_version
	);

	wp_remote_post('https://api.solo.com.hr/solo-for-woocommerce', array(
		'method' => 'POST',
		'body' => json_encode($plugin_data),
		'headers' => array(
			'Content-Type' => 'application/json'
		)
	));
}

//// Create, update, view, delete exchange rate
function solo_woocommerce_exchange(int $action) {
	switch($action) {
		// Create
		case 1:
			$encoded_json = solo_woocommerce_exchange_fetch();

			// Create exchange rate in wp_options table
			add_option('solo_woocommerce_tecaj', $encoded_json, '', 'no');

			// Add scheduled job for updating exchange rate
			wp_schedule_event(time(), 'hourly', 'solo_woocommerce_exchange_update', array(2));

			break;
		// Update
		case 2:
			$encoded_json = solo_woocommerce_exchange_fetch();

			// Update exchange rate in wp_options table
			update_option('solo_woocommerce_tecaj', $encoded_json, '', 'no');

			break;
		// View
		case 3:
			// Read exchange rate from wp_options table
			$exchange = get_option('solo_woocommerce_tecaj');
			if (!$exchange) {
				echo '<br><div class="notice notice-error inline"><p>' . __('Tečajna lista nije dostupna. Pokušaj <a href="' . admin_url('plugins.php') . '#deactivate-solo-for-woocommerce">deaktivirati</a> i ponovno aktivirati dodatak.', 'solo-for-woocommerce') . '</p></div>';
			} else {
				$decoded_json = json_decode($exchange, true);
				echo '<p>' . __('Tečajna lista je formatirana za Solo gdje se HNB-ov tečaj dijeli s 1 (npr. tečaj za račun ili ponudu u valuti USD treba biti 0,94 umjesto 7,064035).<br>Podaci se automatski ažuriraju svakih sat vremena (iduće ažuriranje u ' . get_date_from_gmt(date('H:i', wp_next_scheduled('solo_woocommerce_exchange_update', array(2))), 'H:i') . '). Izvor podataka: <a href="https://www.hnb.hr/statistika/statisticki-podaci/financijski-sektor/sredisnja-banka-hnb/devizni-tecajevi/referentni-tecajevi-esb-a" target="_blank">Hrvatska Narodna Banka</a>', 'solo-for-woocommerce') . '</p>';
				echo '<table class="widefat striped" style="width:auto;"><colgroup><col style="width:50%;"><col style="width:50%;"></colgroup><thead><th>Valuta</th><th>Tečaj</th></thead><tbody>';
				foreach($decoded_json as $key => $val) {
					if ($key=='datum') continue; // Remove date from view
					echo '<tr><td>1 ' . $key . '</td><td>' . str_replace('.', ',', $val) . ' EUR</td></tr>';
				}
				echo '</tbody></table>';
			}

			break;
		// Delete
		case 4:
			// Remove scheduled job for exchange rate during plugin deactivation
			wp_clear_scheduled_hook('solo_woocommerce_exchange_update', array(2));

			// Delete exchange rate from wp_options table
			delete_option('solo_woocommerce_tecaj');

			break;
	}
}

function solo_woocommerce_exchange_fetch() {
	$json = wp_remote_get(esc_url_raw('https://api.hnb.hr/tecajn-eur/v3'));
	// Proceed if no error
	if (!is_wp_error($json)) {
		// Read data
		$data = wp_remote_retrieve_body($json);
		// Decode JSON
		$decoded_json = json_decode($data, true);
		// Parse JSON
		$array = array('datum' => get_date_from_gmt(date('Y-m-d H:i:s')));
		foreach($decoded_json as $item) {
			// Filter and reuse results
			$array[$item['valuta']] = substr(1/solo_woocommerce_floatvalue($item['srednji_tecaj']), 0, 8);
		}
		// Build JSON
		return json_encode($array);
	}
}

//// Needed for exchange rate parsing
function solo_woocommerce_floatvalue($val) {
	$val = str_replace(',', '.', $val);
	$val = preg_replace('/\.(?=.*\.)/', '', $val);
	return floatval($val);
}

//// Create custom table to save WooCommerce orders
function solo_woocommerce_create_table() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
	$table_name = $wpdb->prefix . 'solo_woocommerce';

	// Prevent table creation if already exists
	if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'")!=$table_name) {
		// Define table structure
		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			order_id varchar(50) NOT NULL,
			api_request text NOT NULL,
			api_response text NOT NULL,
			created datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			updated datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			sent datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY (id)
		) $charset_collate;";

		// Create table
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}
}

//// Call Solo API to create document
function solo_woocommerce_api_post($url, $api_request, $order_id, $document_type) {
	// Make API call
	$api_response = wp_remote_post($url, array(
			'body' => $api_request,
			'sslverify' => false,
			'timeout' => 10,
		)
	);
	$api_response = wp_remote_retrieve_body($api_response);

	// Save API response to our table
	global $wpdb;
	$table_name = $wpdb->prefix . 'solo_woocommerce';
	$wpdb->update(
		$table_name,
		array(
			'api_response' => $api_response,
			'updated' => current_time('mysql')
		),
		array(
			'order_id' => $order_id
		)
	);

	// Decode JSON from API response
	$json_response = json_decode($api_response, true);
	$status = $json_response['status'];
	if (isset($json_response['racun']['pdf'])) $pdf = $json_response['racun']['pdf'];
	if (isset($json_response['ponuda']['pdf'])) $pdf = $json_response['ponuda']['pdf'];

	// Check for errors
	if ($status==0 && isset($pdf)) {
		// Download and send PDF
		wp_schedule_single_event(time()+10, 'solo_woocommerce_api_get', array($pdf, $order_id, $document_type));
	} elseif ($status==100) {
		// Retry after 10 seconds
		wp_schedule_single_event(time()+10, 'solo_woocommerce_api_post', array($url, $api_request, $order_id, $document_type));
	} else {
		// Stop on other errors
		return;
	}
};

//// Download PDF and send e-mail to buyer
function solo_woocommerce_api_get($pdf, $order_id, $document_type) {
	// Init main class and get setting
	$solo_woocommerce = new solo_woocommerce;
	$send = $solo_woocommerce->setting('posalji');
	$title = $solo_woocommerce->setting('naslov');
	$body = $solo_woocommerce->setting('poruka');

	// Proceed if enabled in settings
	if ($send==1) {
		// Read order details
		$order = wc_get_order($order_id);
		$billing_email = $order->get_billing_email();

		// Set download folder
		$folder = ABSPATH . 'wp-content/uploads/';

		// Save PDF
		$remote_file = file_get_contents($pdf);
		$local_file = $folder . $document_type . '.pdf';
		file_put_contents($local_file, $remote_file);

		// Send e-mail with PDF in attachment
		$headers = 'Content-Type: text/mixed; charset=UTF-8';
		$sent = wp_mail($billing_email, $title, $body, $headers, array($local_file));

		if ($sent) {
			// Save sent date
			global $wpdb;
			$table_name = $wpdb->prefix . 'solo_woocommerce';
			$wpdb->update(
				$table_name,
				array(
					'sent' => current_time('mysql')
				),
				array(
					'order_id' => $order_id
				)
			);
		}

		// Delete PDF
		wp_delete_file($local_file);
	}
};

//// Main class, holds properties and methods
class solo_woocommerce {
	// Declare params to avoid "PHP Deprecated: Creation of dynamic property" warnings
	public $plugin_name = '';

	// Magic function
	public function __construct() {
		if (is_admin()) {
			// Shortcuts
			$this->plugin_name = 'solo-woocommerce';

			// Create settings link in WordPress > Plugins
			add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'solo_woocommerce_settings_link'));

			// Create settings link inside WooCommerce menu
			add_action('admin_menu', array($this, 'solo_woocommerce_submenu_link'), 99);

			// Load custom CSS and JS
			add_action('admin_enqueue_scripts', array($this, 'solo_woocommerce_css_js'));

			// Plugin settings (or update plugin)
			add_action('admin_init', array($this, 'solo_woocommerce_settings'));

			// Always show messages
			add_action('admin_notices', array($this, 'solo_woocommerce_show_messages'));

			// Ajax token check
			add_action('wp_ajax_check_token', array($this, 'solo_woocommerce_check_token'));
		}

		// Scheduled job for updating exchange rate
		add_action('solo_woocommerce_exchange_update', 'solo_woocommerce_exchange');

		// WooCommerce: remove certain fields in checkout
		add_filter('woocommerce_checkout_fields', array($this, 'solo_woocommerce_remove_fields'), 11);

		// WooCommerce: show custom fields in checkout
		add_action('woocommerce_before_checkout_billing_form', array($this, 'solo_woocommerce_custom_fields'), 12);

		// WooCommerce: save custom fields after checkout
		add_action('woocommerce_checkout_update_order_meta', array($this, 'solo_woocommerce_custom_meta'), 13);

		// WooCommerce: hooks
		add_action('woocommerce_order_status_changed', array($this, 'solo_woocommerce_process_order'), 14, 3);

		// WooCommerce: show custom fields in admin
		add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'solo_woocommerce_admin_order_meta'), 15);
		add_action('manage_shop_order_posts_custom_column', array($this, 'solo_woocommerce_admin_column_meta'), 16);
		add_action('woocommerce_order_details_after_order_table', array($this, 'solo_woocommerce_customer_order_meta'), 17);

		// Scheduled job for calling Solo API
		add_action('solo_woocommerce_api_post', 'solo_woocommerce_api_post', 1, 4);

		// Scheduled job for downloading PDF
		add_action('solo_woocommerce_api_get', 'solo_woocommerce_api_get', 2, 3);
	}

	//// Show notices
	function solo_woocommerce_show_messages() {
		settings_errors();
	}

	//// Removes certain fields in checkout
	public function solo_woocommerce_remove_fields($fields) {
		unset($fields['billing']['billing_company']);
		unset($fields['billing']['billing_state']);
		return $fields;
	}

	//// Show custom fields in checkout
	public function solo_woocommerce_custom_fields($fields) {
		echo '<div id="vat_number">';
		woocommerce_form_field('vat_checkbox', array(
				'type' => 'checkbox',
				'label' => __('Trebam R1 račun', 'solo-for-woocommerce'),
				'required' => false,
				'class' => array('input-checkbox')
			)
		);
		woocommerce_form_field('company_name', array(
				'type' => 'text',
				'label' => __('Naziv tvrtke', 'solo-for-woocommerce'),
				'placeholder' => __('Naziv tvrtke', 'solo-for-woocommerce'),
				'required' => false,
				'class' => array('form-row-wide hidden')
			),
			$fields->get_value('company_name')
		);
		woocommerce_form_field('vat_number', array(
				'type' => 'text',
				'label' => __('OIB', 'solo-for-woocommerce'),
				'placeholder' => __('OIB', 'solo-for-woocommerce'),
				'required' => false,
				'class' => array('form-row-wide hidden')
			),
			$fields->get_value('vat_number')
		);
		echo '</div>';
		echo '<style>#vat_number .hidden{display:none;}</style>';
		echo '<script>jQuery(function($){$("#vat_number [type=checkbox]").on("click",function(){if($(this).is(":checked")){$("#company_name_field,#vat_number_field").removeClass("hidden");$("#company_name").focus();}else{$("#company_name_field,#vat_number_field").addClass("hidden");}});});</script>';
	}

	//// Save custom fields after checkout
	public function solo_woocommerce_custom_meta($order_id) {
		if (!empty($_POST['vat_number'])) {
			update_post_meta($order_id, '_company_name', sanitize_text_field($_POST['company_name']));
			update_post_meta($order_id, '_vat_number', sanitize_text_field($_POST['vat_number']));
		}
	}

	//// Show custom fields to admin
	public function solo_woocommerce_admin_order_meta($order) {
		$naziv_tvrtke = get_post_meta($order->get_id(), '_company_name', true);
		$oib = get_post_meta($order->get_id(), '_vat_number', true);
		if ($naziv_tvrtke) echo '<p><strong>' . __('Podaci za R1 račun', 'solo-for-woocommerce') . ':</strong><br>' . $naziv_tvrtke . '<br>' . $oib . '</p>';
	}

	public function solo_woocommerce_admin_column_meta($column) {
		if ($column=='order_number') {
			global $the_order;

			$naziv_tvrtke = get_post_meta($the_order->get_id(), '_company_name', true);
			if ($naziv_tvrtke) echo '<br>' . $naziv_tvrtke;
		}
	}

	//// Show custom fields to customer
	public function solo_woocommerce_customer_order_meta($order) {
		$naziv_tvrtke = get_post_meta($order->get_id(), '_company_name', true);
		$oib = get_post_meta($order->get_id(), '_vat_number', true);
		if ($naziv_tvrtke) {
			echo '<h2 class="woocommerce-column__title">' . __('Podaci za R1 račun', 'solo-for-woocommerce') . '</h2>';
			echo '<p>' . $naziv_tvrtke . '<br>' . $oib . '</p>';
		}
	}

	//// Create settings link in plugins
	public function solo_woocommerce_settings_link($links) {
		$url = esc_url(add_query_arg('page', $this->plugin_name, get_admin_url() . 'admin.php'));
		$settings_link = '<a href="' . $url . '">' . __('Postavke', 'solo-for-woocommerce') . '</a>';
		array_unshift($links, $settings_link);
		return $links;
	}

	//// Create settings link under WooCommerce menu
	public function solo_woocommerce_submenu_link() {
		add_submenu_page('woocommerce', 'Solo for WooCommerce', __('Solo postavke', 'solo-for-woocommerce'), 'manage_options', $this->plugin_name, array($this, 'solo_woocommerce_settings_url'));
	}

	//// Settings file location
	public function solo_woocommerce_settings_url() {
		require_once plugin_dir_path(__FILE__) . 'lib/' . $this->plugin_name . '-settings.php';
	}

	//// Load custom CSS and JS
	public function solo_woocommerce_css_js() {
		wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'lib/' . $this->plugin_name . '.css', false, SOLO_VERSION);
		wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'lib/' . $this->plugin_name . '.js', array('jquery'), SOLO_VERSION);
		wp_localize_script($this->plugin_name, 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
	}

	//// Return single setting
	public static function setting($id) {
		$data = get_option('solo_woocommerce_postavke');
		if (isset($data[$id])) return $data[$id];
	}

	//// Plugin settings (or update plugin)
	function solo_woocommerce_settings() {
		// Update plugin
		if (isset($_GET['update']) && current_user_can('update_plugins')) {
			// Nonce check
			if (check_admin_referer('solo_woocommerce_update_nonce')) {
				// Prepare update file to download
				$url = get_transient('solo_url');
				$temp_file = download_url($url);
				if (is_wp_error($temp_file)) {
					wp_die($result->get_error_message());
				}

				// Deactivate plugin
				deactivate_plugins(__FILE__);

				// Set download folder
				$folder = WP_PLUGIN_DIR;
				WP_Filesystem();
				$result = unzip_file($temp_file, $folder);
				if (is_wp_error($result)) {
					wp_die($result->get_error_message());
				}

				// Delete temporary file
				unlink($temp_file);

				// Activate plugin
				activate_plugins(__FILE__);

				// Show custom notice
				add_settings_error('solo_woocommerce_postavke', 'solo_woocommerce_postavke', __('Dodatak uspješno ažuriran.', 'solo-for-woocommerce'), 'updated');
			}
		}

		register_setting('solo_woocommerce_postavke', 'solo_woocommerce_postavke', array($this, 'solo_woocommerce_form_validation'));
	}

	function solo_woocommerce_form_validation($data) {
		// Read settings
		$settings_data = get_option('solo_woocommerce_postavke');

		// Create array if doesn't exist
		if (!is_array($settings_data)) $settings_data = array();

		// Validate fields
		if ($data) {
			$message = __('Postavke uspješno spremljene.', 'solo-for-woocommerce');
			$type = 'updated';

			foreach($data as $key => $value) {
				// API token validation
				if ($key=='token' && !preg_match('/^[a-zA-Z0-9]{33}$/', $data[$key])) {
					$message = __('API token nije ispravan.', 'solo-for-woocommerce');
					$type = 'error';
					$settings_data = '';

					break;
				} else {
					$settings_data[$key] = sanitize_textarea_field($value);

					// Checkboxes
					if (!isset($data['prikazi_porez'])) $settings_data['prikazi_porez'] = 0;
					if (!isset($data['posalji'])) $settings_data['posalji'] = 0;

					// Required param for API
					if (empty($data['tip_racuna']) || $data['tip_racuna']<=0) $settings_data['tip_racuna'] = 1;
				}
			}

			// Show custom notice
			add_settings_error('solo_woocommerce_postavke', 'solo_woocommerce_postavke', $message, $type);

			return $settings_data;
		}
	}

	//// Ajax token check
	function solo_woocommerce_check_token() {
		$token = $_GET['token'];
		$url = wp_remote_get('https://api.solo.com.hr/licenca?token=' . $token);

		if (is_wp_error($url)) {
			$error_code = wp_remote_retrieve_response_code($url);
			$error_message = wp_remote_retrieve_response_message($url);

			$response = $error_code . ': ' . $error_message;
		} else {
			$response = wp_remote_retrieve_body($url);
		}

		echo $response;

		wp_die();
	}

	//// Process order before sending to Solo API
	function solo_woocommerce_process_order($order_id, $old_status, $new_status) {
		// Get order information
		$order = wc_get_order($order_id);
		$order_status = $order->get_status();
		$payment_method = $order->get_payment_method();

		// Get plugin settings
		$settings = get_option('solo_woocommerce_postavke');
		$document_type = $trigger = '';
		if (!empty($settings)) {
			foreach ($settings as $key => $value) {
				${$key} = $value;
				// Find document type and trigger for this order
				if ($key==$payment_method . '1') $document_type = $value;
				if ($key==$payment_method . '2') $trigger = $value;
			}
		}

		// Setting found for this gateway, proceed
		if ($document_type<>'' && $trigger<>'') {

			// Check if order already exists
			global $wpdb;
			$table_name = $wpdb->prefix . 'solo_woocommerce';
			$exists = $wpdb->get_var("SELECT order_id FROM $table_name WHERE order_id=$order_id");

			// Proceed on "checkout" or "completed"
			if (($old_status=='pending' && $new_status=='on-hold' && $trigger==1 && !$exists) || ($old_status=='pending' && $new_status=='processing' && $trigger==1 && !$exists) || ($new_status=='completed' && $old_status<>$new_status && $trigger==2 && !$exists)) {
				// Get order information
				$date_created = $order->get_date_created();
				$kupac_ime = $order->get_billing_first_name();
				$kupac_prezime = $order->get_billing_last_name();
				$kupac_naziv = $kupac_ime . ' ' . $kupac_prezime;
					// Custom fields added by plugin
					$naziv_tvrtke = get_post_meta($order_id, '_company_name', true);
					$kupac_oib = get_post_meta($order_id, '_vat_number', true);
					if ($naziv_tvrtke<>'') $kupac_naziv = $naziv_tvrtke;
				$kupac_adresa = $order->get_billing_address_1();
				if (!empty($order->get_billing_address_2())) $kupac_adresa .= ' ' . $order->get_billing_address_2();
				$kupac_adresa = $kupac_adresa . ', ' . $order->get_billing_postcode() . ' ' . $order->get_billing_city() . ', ' . $order->get_billing_country();

				// Payment methods (needed for fiscalization)
				$nacin_placanja = $fiskalizacija = '';
				switch ($payment_method) {
					// Direct bank transfer
					case 'bacs':
						$nacin_placanja = 1;
						$fiskalizacija = 0;
						break;
					// Check payments
					case 'cheque':
						$nacin_placanja = 4;
						$fiskalizacija = 1;
						break;
					// Cash on delivery
					case 'cod':
						$nacin_placanja = 1;
						$fiskalizacija = 0;
						break;
					// Stripe (Credit Card)
					case 'stripe':
						$nacin_placanja = 3;
						$fiskalizacija = 1;
						break;
					// Stripe (SEPA Direct Debit)
					case 'stripe_sepa':
						$nacin_placanja = 1;
						$fiskalizacija = 0;
						break;
					// PayPal
					case 'paypal':
						$nacin_placanja = 1;
						$fiskalizacija = 0;
						break;
					case 'ppec-gateway':
						$nacin_placanja = 1;
						$fiskalizacija = 0;
						break;
					case 'ppcp-gateway':
						$nacin_placanja = 1;
						$fiskalizacija = 0;
						break;
					// Braintree (Credit Card)
					case 'braintree_credit_card':
						$nacin_placanja = 3;
						$fiskalizacija = 1;
						break;
					// Braintree (PayPal)
					case 'braintree_paypal':
						$nacin_placanja = 1;
						$fiskalizacija = 0;
						break;
					// CorvusPay (Credit Card)
					case 'corvuspay':
						$nacin_placanja = 3;
						$fiskalizacija = 1;
						break;
					// Monri (Credit Card)
					case 'pikpay':
						$nacin_placanja = 3;
						$fiskalizacija = 1;
						break;
					// myPOS (Credit Card)
					case 'mypos_virtual':
						$nacin_placanja = 3;
						$fiskalizacija = 1;
						break;
					// Uplatnica
					case 'wooplatnica-croatia':
						$nacin_placanja = 1;
						$fiskalizacija = 0;
						break;
					// KEKS Pay
					case 'erste-kekspay-woocommerce':
						$nacin_placanja = 1;
						$fiskalizacija = 0;
						break;
					// PayPal Express
					case 'eh_paypal_express':
						$nacin_placanja = 3;
						$fiskalizacija = 1;
						break;
					// Revolut
					case 'revolut_cc':
						$nacin_placanja = 3;
						$fiskalizacija = 1;
						break;
					// Stop
					default:
						return;
				}

				// Grammar
				$grammar = 'ponude';
				if ($document_type=='racun') $grammar = 'racuna';

				// API URL
				$url = 'https://api.solo.com.hr/' . $document_type;

				// Create POST request from order details
				$i = 0;
				$api_request = 'token=' . $token . PHP_EOL;
				if (!isset($tip_usluge)) {
					$tip_usluge = 1;
				} else {
					if (!is_numeric($tip_usluge)) $tip_usluge = 1;
				}
				$api_request .= '&tip_usluge=' . $tip_usluge . PHP_EOL;
				if (!isset($prikazi_porez)) $prikazi_porez = 0;
				$api_request .= '&prikazi_porez=' . $prikazi_porez . PHP_EOL;
				if (!isset($tip_racuna) || empty($tip_racuna)) $tip_racuna = 1;
				$api_request .= '&tip_racuna=' . $tip_racuna . PHP_EOL;
				$api_request .= '&kupac_naziv=' . urlencode($kupac_naziv) . PHP_EOL;
				$api_request .= '&kupac_adresa=' . urlencode($kupac_adresa) . PHP_EOL;
				$api_request .= '&kupac_oib=' . urlencode($kupac_oib) . PHP_EOL;

				// Order items
				$items = $order->get_items();
				foreach ($items as $item_key => $item) {
					$i++;

					$item_name = $item->get_name();
					$item_quantity = $item->get_quantity();

					$taxes = WC_Tax::get_rates($item->get_tax_class());
					foreach ($taxes as $key => $value) {
						$item_tax = $value['rate'];
					}
					if (!in_array($item_tax, array(5, 13, 25))) $item_tax = 0;
					$item_tax = round($item_tax);

					// Override tax if not in order
					$tax_total = $item->get_subtotal_tax();
					if ($tax_total==0) $item_tax = 0;

					$item_ = $item['variation_id'] ? wc_get_product($item['variation_id']) : wc_get_product($item['product_id']);
					$item_price = wc_get_price_excluding_tax($item_, array('price' => $item_->get_regular_price()));
					$item_discount = 0;
					// On sale products
					if ($item_->is_on_sale()) {
						$item_sale_price = wc_get_price_excluding_tax($item_, array('price' => $item_->get_sale_price()));
						$item_discount = 100 - (($item_sale_price/$item_price) * 100);
						// Max 18 chars
						$item_discount = substr($item_discount, 0, 8);
						// Max 4 decimals
						$item_discount = number_format($item_discount, 4, ',', '');
					}
					$item_price = round($item_price, 2);
					$item_price = number_format($item_price, 2, ',', '');

					$item_quantity = str_replace('.', ',', $item_quantity);
					$item_discount = str_replace('.', ',', $item_discount);

					$api_request .= '&usluga=' . $i . PHP_EOL;
					$api_request .= '&opis_usluge_' . $i . '=' . urlencode($item_name) . PHP_EOL;
					$api_request .= '&jed_mjera_' . $i . '=2' . PHP_EOL;
					$api_request .= '&cijena_' . $i . '=' . $item_price . PHP_EOL;
					$api_request .= '&kolicina_' . $i . '=' . $item_quantity . PHP_EOL;
					$api_request .= '&popust_' . $i . '=' . $item_discount . PHP_EOL;
					$api_request .= '&porez_stopa_' . $i . '=' . $item_tax . PHP_EOL;
				}

				// Coupons
				$coupon_price = 0;
				foreach($order->get_items('coupon') as $item_id => $item) {
					$coupon_data = $item->get_data();
					$coupon_price = $coupon_data['discount'];
					$coupon_code = $coupon_data['code'];
					$coupon_tax = $coupon_data['discount_tax'];
					$coupon_price = ($coupon_price + $coupon_tax);
					if ($coupon_tax>0) {
						$coupon_tax = 25;
					} else {
						$coupon_tax = 0;
					}

					if ($coupon_price>0) {
						$i++;

						$coupon_price = $coupon_price / (1 + ($coupon_tax/100));
						$coupon_price = -1 * $coupon_price;
						$coupon_price = round($coupon_price, 2);
						$coupon_price = number_format($coupon_price, 2, ',', '');

						$api_request .= '&usluga=' . $i . PHP_EOL;
						$api_request .= '&opis_usluge_' . $i . '=' . urlencode(__('Kupon za popust', 'solo-for-woocommerce') . ' (' . $coupon_code . ')') . PHP_EOL;
						$api_request .= '&jed_mjera_' . $i . '=1' . PHP_EOL;
						$api_request .= '&cijena_' . $i . '=' . $coupon_price . PHP_EOL;
						$api_request .= '&kolicina_' . $i . '=1' . PHP_EOL;
						$api_request .= '&popust_' . $i . '=0' . PHP_EOL;
						$api_request .= '&porez_stopa_' . $i . '=' . $coupon_tax . PHP_EOL;
					}
				}

				// Shipping
				$shipping_price = $order->get_shipping_total();
				$shipping_tax = $order->get_shipping_tax();
				if ($shipping_price>0) {
					$i++;

					$shipping_tax = (($shipping_tax/$shipping_price) * 100);
					if (!is_numeric($shipping_tax)) $shipping_tax = 0;
					$shipping_tax = round($shipping_tax);
					$shipping_price = round($shipping_price, 2);
					$shipping_price = number_format($shipping_price, 2, ',', '');

					$api_request .= '&usluga=' . $i . PHP_EOL;
					$api_request .= '&opis_usluge_' . $i . '=' . urlencode(__('Poštarina', 'solo-for-woocommerce')) . PHP_EOL;
					$api_request .= '&jed_mjera_' . $i . '=1' . PHP_EOL;
					$api_request .= '&cijena_' . $i . '=' . $shipping_price . PHP_EOL;
					$api_request .= '&kolicina_' . $i . '=1' . PHP_EOL;
					$api_request .= '&popust_' . $i . '=0' . PHP_EOL;
					$api_request .= '&porez_stopa_' . $i . '=' . $shipping_tax . PHP_EOL;
				}

				$api_request .= '&nacin_placanja=' . $nacin_placanja . PHP_EOL;
				if (isset($rok_placanja) && !empty($rok_placanja)) $api_request .= '&rok_placanja=' . $rok_placanja . PHP_EOL;
				if (isset($iban) && !empty($iban)) $api_request .= '&iban=' . $iban . PHP_EOL;

				// Other currencies
				$currency = $order->get_currency();
				$currency_exchange = 1;
				if ($currency<>'EUR') {
					$exchange_rates = get_option('solo_woocommerce_tecaj');
					$exchange_rates = json_decode($exchange_rates, true);
					if (!empty($exchange_rates)) {
						foreach ($exchange_rates as $key => $value) {
							if ($currency==$key) $currency_exchange = $value;
						}
					}
					$currency_exchange = str_replace('.', ',', $currency_exchange);

					// Transform currency name to integer
					$accepted_currencies = array(
						'AUD' => '2',
						'CAD' => '3',
						'CZK' => '4',
						'DKK' => '5',
						'HUF' => '6',
						'JPY' => '7',
						'NOK' => '8',
						'SEK' => '9',
						'CHF' => '10',
						'GBP' => '11',
						'USD' => '12',
						'BAM' => '13',
						'EUR' => '14',
						'PLN' => '15'
					);

					// Set document currency and exchange rate
					if (isset($accepted_currencies[$currency])) {
						$currency_id = $accepted_currencies[$currency];

						$api_request .= '&valuta_' . $grammar . '=' . $currency_id . PHP_EOL;
						$api_request .= '&tecaj=' . $currency_exchange . PHP_EOL;
						$api_request .= '&napomene=' . urlencode(__('Preračunato po srednjem tečaju HNB-a', 'solo-for-woocommerce') . ' (1 EUR = ' . $currency_exchange . ' ' . $currency . ')') . PHP_EOL;
					} else {
						// Stop
						return;
					}
				}

				// Notes
				if (isset(${'napomene_' . $document_type}) && !empty(${'napomene_' . $document_type})) $api_request .= '&napomene=' . urlencode(${'napomene_' . $document_type}) . PHP_EOL;

				// Language
				if (isset($jezik_) && !empty($jezik_)) $api_request .= '&jezik_' . $grammar . '=' . $jezik_ . PHP_EOL;

				// Fiscalization
				$api_request .= '&fiskalizacija=' . $fiskalizacija . PHP_EOL;

				// Check for table in database
				global $wpdb;
				$table_name = $wpdb->prefix . 'solo_woocommerce';
				if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'")!=$table_name) {
					// Create table if doesn't exist
					solo_woocommerce_create_table();
				}

				// Save order to database
				$wpdb->insert(
					$table_name,
					array(
						'order_id' => $order_id,
						'api_request' => 'POST ' . $url . PHP_EOL . $api_request,
						'created' => current_time('mysql')
					)
				);

				// Send order to Solo API
				solo_woocommerce_api_post($url, $api_request, $order_id, $document_type);
			}
		}
	}
}
$solo_woocommerce = new solo_woocommerce;