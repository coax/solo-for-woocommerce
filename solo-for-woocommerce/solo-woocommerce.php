<?php
/**
 * Plugin Name: Solo for WooCommerce
 * Plugin URI: https://solo.com.hr/api-dokumentacija/dodaci
 * Description: Narudžba u tvojoj WooCommerce trgovini će automatski kreirati račun ili ponudu u servisu Solo.
 * Version: 2.1
 * Requires at least: 5.2
 * Requires PHP: 7.2
 * Requires Plugins: woocommerce
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
	define('SOLO_VERSION', '2.1');
}

//// Activate plugin
register_activation_hook(__FILE__, 'solo_woocommerce_activate');

function solo_woocommerce_activate() {
	// Check PHP version
	if (version_compare(PHP_VERSION, '7.2', '<')) {
		wp_die(sprintf(__('Solo for WooCommerce dodatak ne podržava PHP %s. Ažuriraj PHP na verziju 7.2 ili noviju.', 'solo-for-woocommerce'), PHP_VERSION), __('Greška', 'solo-for-woocommerce'), array("back_link" => true));
	}

	// Check if WooCommerce plugin installed
	if (!class_exists('WooCommerce')) {
		wp_die(__('Solo for WooCommerce ne radi bez WooCommerce dodatka.<br>Prvo instaliraj WooCommerce i zatim aktiviraj ovaj dodatak.', 'solo-for-woocommerce'), __('Greška', 'solo-for-woocommerce'), array("back_link" => true));
	}
	if (version_compare(get_option('woocommerce_version'), 5, '<')) {
		wp_die(__('Solo for WooCommerce radi samo s WooCommerce verzijom 5 ili novijom.', 'solo-for-woocommerce'), __('Greška', 'solo-for-woocommerce'), array("back_link" => true));
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
	$woo_version = class_exists('WooCommerce') ? WC()->version : '';

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
			add_option('solo_woocommerce_tecaj', $encoded_json, '', false);

			// Add scheduled job for updating exchange rate
			wp_schedule_event(time(), 'hourly', 'solo_woocommerce_exchange_update', array(2));

			break;
		// Update
		case 2:
			$encoded_json = solo_woocommerce_exchange_fetch();

			// Update exchange rate in wp_options table
			update_option('solo_woocommerce_tecaj', $encoded_json, false);

			break;
		// View
		case 3:
			// Read exchange rate from wp_options table
			$exchange = get_option('solo_woocommerce_tecaj');
			if (!$exchange) {
				echo '<br><div class="notice notice-error inline"><p>' . sprintf(__('Tečajna lista nije dostupna. Pokušaj <a href="%s#deactivate-solo-for-woocommerce">deaktivirati</a> i ponovno aktivirati dodatak.', 'solo-for-woocommerce'), admin_url('plugins.php')) . '</p></div>';
			} else {
				$decoded_json = json_decode($exchange, true);
				$last_updated = isset($decoded_json['datum']) ? $decoded_json['datum'] : '';
				echo '<p>' . sprintf(__('Tečajna lista je formatirana za Solo gdje se HNB-ov tečaj dijeli s 1 (npr. tečaj za račun ili ponudu u valuti USD treba biti 0,94 umjesto 7,064035).<br>Zadnje ažuriranje: %s. Iduće ažuriranje u %s. Izvor podataka: <a href=\"https://www.hnb.hr/statistika/statisticki-podaci/financijski-sektor/sredisnja-banka-hnb/devizni-tecajevi/referentni-tecajevi-esb-a\" target=\"_blank\">Hrvatska Narodna Banka</a>', 'solo-for-woocommerce'), esc_html($last_updated), get_date_from_gmt(date('H:i', wp_next_scheduled('solo_woocommerce_exchange_update', array(2))), 'H:i')) . '</p>';
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
		if (empty($decoded_json)) return '';
		// Parse JSON
		$array = array('datum' => get_date_from_gmt(date('Y-m-d H:i:s')));
		foreach($decoded_json as $item) {
			// Filter and reuse results
			$array[$item['valuta']] = substr(1/solo_woocommerce_floatvalue($item['srednji_tecaj']), 0, 8);
		}
		// Build JSON
		return json_encode($array);
	}
	return '';
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
	if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name))!=$table_name) {
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
			'sslverify' => !defined('WP_DEBUG') || !WP_DEBUG,
			'timeout' => 10,
			'headers' => [
				'Content-Type' => 'application/x-www-form-urlencoded'
			]
		)
	);

	// Handle connection errors
	if (is_wp_error($api_response)) {
		return;
	}

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
	$status = $json_response['status'] ?? -1;
	$pdf = $json_response['racun']['pdf'] ?? $json_response['ponuda']['pdf'] ?? null;

	// Check for errors
	if ($status==0 && isset($pdf)) {
		// Download and send PDF
		wp_schedule_single_event(time()+5, 'solo_woocommerce_api_get', array($pdf, $order_id, $document_type));
	} elseif ($status==100) {
		// Retry after 5 seconds
		wp_schedule_single_event(time()+5, 'solo_woocommerce_api_post', array($url, $api_request, $order_id, $document_type));
	} else {
		// Stop on other errors
		return;
	}
}

//// Download PDF and send e-mail to buyer
function solo_woocommerce_api_get($pdf, $order_id, $document_type) {
	// Init main class and get setting
	$solo_woocommerce = new solo_woocommerce;
	$send = $solo_woocommerce->setting('posalji');
	$title = $solo_woocommerce->setting('naslov');
	$body = $solo_woocommerce->setting('poruka');

	// Proceed if enabled in settings
	if ($send == '1') {
		// Read order details
		$order = wc_get_order($order_id);
		if (!$order) return;
		$billing_email = $order->get_billing_email();

		// Set download folder — private subfolder, not publicly accessible
		$upload_dir = wp_upload_dir();
		$folder = $upload_dir['basedir'] . '/racuni/';
		if (!file_exists($folder)) {
			wp_mkdir_p($folder);
			file_put_contents($folder . '.htaccess', 'deny from all');
			file_put_contents($folder . 'index.php', '<?php // These are not the droids you\'re looking for.');
		}

		// Unique filename per order to prevent overwrites on concurrent orders
		$local_file = $folder . $document_type . '-' . $order_id . '-' . wp_generate_password(8, false) . '.pdf';

		// Download PDF via WP HTTP API
		$remote_file = wp_remote_get($pdf, array('timeout' => 30));
		if (is_wp_error($remote_file)) {
			return;
		}
		file_put_contents($local_file, wp_remote_retrieve_body($remote_file));

		// Send e-mail with PDF in attachment
		$headers = '';
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
}

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

			// Ajax retry order
			add_action('wp_ajax_solo_retry_order', array($this, 'solo_woocommerce_retry_order'));
		}

		// Scheduled job for updating exchange rate
		add_action('solo_woocommerce_exchange_update', 'solo_woocommerce_exchange');

		// WooCommerce: remove certain fields in checkout
		add_filter('woocommerce_checkout_fields', array($this, 'solo_woocommerce_remove_fields'), 11);

		// WooCommerce: show custom fields in checkout
		add_action('woocommerce_before_checkout_billing_form', array($this, 'solo_woocommerce_custom_fields'), 12);

		// WooCommerce blocks: register additional checkout fields
		add_action('woocommerce_init', array($this, 'solo_woocommerce_register_block_fields'));

		// WooCommerce blocks: save additional checkout fields
		add_action('woocommerce_set_additional_field_value', array($this, 'solo_woocommerce_save_block_fields'), 10, 4);

		// WooCommerce: save custom fields after checkout
		add_action('woocommerce_checkout_update_order_meta', array($this, 'solo_woocommerce_custom_meta'), 13);

		// WooCommerce: hooks
		add_action('woocommerce_order_status_changed', array($this, 'solo_woocommerce_process_order'), 14, 3);

		// WooCommerce: show custom fields in admin
		add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'solo_woocommerce_admin_order_meta'), 15);
		add_action('manage_shop_order_posts_custom_column', array($this, 'solo_woocommerce_admin_column_meta'), 16, 2);
		add_action('manage_woocommerce_page_wc-orders_custom_column', array($this, 'solo_woocommerce_admin_column_meta'), 16, 2);
		add_action('woocommerce_order_details_after_order_table', array($this, 'solo_woocommerce_customer_order_meta'), 17);

		// Scheduled job for calling Solo API
		add_action('solo_woocommerce_api_post', 'solo_woocommerce_api_post', 1, 4);

		// Scheduled job for downloading PDF
		add_action('solo_woocommerce_api_get', 'solo_woocommerce_api_get', 2, 3);

		// Per-product KPD oznaka field
		add_action('woocommerce_product_options_general_product_data', array($this, 'solo_woocommerce_product_kpd_field'));
		add_action('woocommerce_process_product_meta', array($this, 'solo_woocommerce_save_product_kpd'));
		add_action('manage_product_posts_custom_column', array($this, 'solo_woocommerce_kpd_column_content'), 20, 2);

		// Declare HPOS compatibility
		add_action('before_woocommerce_init', function() {
			if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
			}
		});

		// Manual sending from WooCommerce orders table
		add_filter('woocommerce_order_actions', array($this, 'solo_woocommerce_add_order_action'));
		add_action('woocommerce_order_action_solo_send', array($this, 'solo_woocommerce_manual_send'));
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

	//// Register additional fields for blocks checkout
	function solo_woocommerce_register_block_fields() {
		if (!function_exists('woocommerce_register_additional_checkout_field')) return;

		woocommerce_register_additional_checkout_field(array(
			'id' => 'solo-for-woocommerce/r1',
			'label' => __('Trebam R1 račun', 'solo-for-woocommerce'),
			'location' => 'contact',
			'type' => 'checkbox',
		));

		woocommerce_register_additional_checkout_field(array(
			'id' => 'solo-for-woocommerce/company_name',
			'label' => __('Naziv tvrtke', 'solo-for-woocommerce'),
			'location' => 'contact',
			'type' => 'text',
			'required' => false,
		));

		woocommerce_register_additional_checkout_field(array(
			'id' => 'solo-for-woocommerce/company_address',
			'label' => __('Adresa tvrtke', 'solo-for-woocommerce'),
			'location' => 'contact',
			'type' => 'text',
			'required' => false,
		));

		woocommerce_register_additional_checkout_field(array(
			'id' => 'solo-for-woocommerce/vat_number',
			'label' => __('OIB tvrtke', 'solo-for-woocommerce'),
			'location' => 'contact',
			'type' => 'text',
			'required' => false,
		));
	}

	//// Save blocks checkout fields to order meta
	function solo_woocommerce_save_block_fields($key, $value, $group, $wc_object) {
		if (!($wc_object instanceof WC_Order)) return;
		if (empty($value)) return;

		$map = array(
			'solo-for-woocommerce/company_name' => '_company_name',
			'solo-for-woocommerce/company_address' => '_company_address',
			'solo-for-woocommerce/vat_number' => '_vat_number',
		);

		if (isset($map[$key])) {
			$wc_object->update_meta_data($map[$key], sanitize_text_field($value));
			$wc_object->save();
		}
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
		woocommerce_form_field('company_address', array(
				'type' => 'text',
				'label' => __('Adresa', 'solo-for-woocommerce'),
				'placeholder' => __('Adresa', 'solo-for-woocommerce'),
				'required' => false,
				'class' => array('form-row-wide hidden')
			),
			$fields->get_value('company_address')
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
		echo '<script>jQuery(function($){$("#vat_number [type=checkbox]").on("click",function(){if($(this).is(":checked")){$("#company_name_field,#company_address_field,#vat_number_field").removeClass("hidden");$("#company_name").focus();}else{$("#company_name_field,#company_address_field,#vat_number_field").addClass("hidden");}});});</script>';
	}

	//// Save custom fields after checkout
	function solo_woocommerce_custom_meta($order_id) {
		if (!empty($_POST['vat_number'])) {
			$order = wc_get_order($order_id);
			if (!$order) return;
			$order->update_meta_data('_company_name', sanitize_text_field($_POST['company_name']));
			$order->update_meta_data('_company_address', sanitize_text_field($_POST['company_address']));
			$order->update_meta_data('_vat_number', sanitize_text_field($_POST['vat_number']));
			$order->save();
		}
	}

	//// Show custom fields to admin
	public function solo_woocommerce_admin_order_meta($order) {
		$naziv_tvrtke = $order->get_meta('_company_name');
		$adresa_tvrtke = $order->get_meta('_company_address');
		$oib = $order->get_meta('_vat_number');
		$is_block_checkout = $order->get_meta('_wc_other/solo-for-woocommerce/company_name') !== '';
		if ($naziv_tvrtke && !$is_block_checkout) {
			echo '<p><strong>' . __('Podaci za R1 račun', 'solo-for-woocommerce') . ':</strong><br>' . esc_html($naziv_tvrtke) . '<br>' . esc_html($adresa_tvrtke) . '<br>' . esc_html($oib) . '</p>';
		}
	}

	public function solo_woocommerce_admin_column_meta($column, $order = null) {
		if ($column === 'order_number') {
			if (!$order) {
				global $the_order;
				$order = $the_order;
			}
			if (!$order) return;
			$naziv_tvrtke = $order->get_meta('_company_name');
			if ($naziv_tvrtke) echo '<br>' . esc_html($naziv_tvrtke);
		}
	}

	//// Add "Pošalji u Solo" to WooCommerce order actions dropdown
	public function solo_woocommerce_add_order_action($actions) {
		$actions['solo_send'] = __('Pošalji u Solo', 'solo-for-woocommerce');
		return $actions;
	}

	//// Handle manual send from order actions dropdown
	public function solo_woocommerce_manual_send($order) {
		$order_id = $order->get_id();
		$old_status = 'pending';
		$new_status = $order->get_status();
		$this->solo_woocommerce_process_order($order_id, $old_status, $new_status);
	}

	//// Show custom fields to customer
	public function solo_woocommerce_customer_order_meta($order) {
		$naziv_tvrtke = $order->get_meta('_company_name');
		$adresa_tvrtke = $order->get_meta('_company_address');
		$oib = $order->get_meta('_vat_number');
		$is_block_checkout = $order->get_meta('_wc_other/solo-for-woocommerce/company_name') !== '';
		if ($naziv_tvrtke && !$is_block_checkout) {
			echo '<h2 class="woocommerce-column__title">' . __('Podaci za R1 račun', 'solo-for-woocommerce') . '</h2>';
			echo '<p>' . esc_html($naziv_tvrtke) . '<br>' . esc_html($adresa_tvrtke) . '<br>' . esc_html($oib) . '</p>';
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
		wp_localize_script($this->plugin_name, 'ajax_object', array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('solo_check_token_nonce'),
			'retry_nonce' => wp_create_nonce('solo_retry_order_nonce')
		));
		wp_localize_script($this->plugin_name, 'kpd_url', plugin_dir_url(__FILE__) . 'lib/kpd.json');
	}

	//// Return single setting
	public static function setting($id) {
		$data = get_option('solo_woocommerce_postavke');
		if (isset($data[$id])) return $data[$id];
	}

	//// Plugin settings (or update plugin)
	function solo_woocommerce_settings() {
		// Deactivate if another plugin is active
		if (is_plugin_active('woo-solo-api/woo-solo-api.php')) {
			deactivate_plugins(__FILE__);
			// Show custom notice
			add_settings_error('solo_woocommerce_postavke', 'plugin_conflict', __('Solo for WooCommerce je automatski deaktiviran zbog Woo Solo Api dodatka.', 'solo-for-woocommerce'), 'error');
		}

		// Update plugin
		if (isset($_GET['update'])) {
			// Capability check first, then nonce
			if (!current_user_can('update_plugins')) {
				wp_die(__('Nemaš dozvolu za ažuriranje dodataka.', 'solo-for-woocommerce'));
			}
			if (check_admin_referer('solo_woocommerce_update_nonce')) {
				// Prepare update file to download
				$url = get_transient('solo_url');
				$temp_file = download_url($url);
				if (is_wp_error($temp_file)) {
					wp_die($temp_file->get_error_message());
				}

				// Deactivate plugin
				deactivate_plugins(__FILE__);

				// WordPress Filesystem API
				if (!function_exists('WP_Filesystem')) {
					require_once(ABSPATH . 'wp-admin/includes/file.php');
				}
				WP_Filesystem();

				// Unzip the file
				$folder = WP_PLUGIN_DIR;
				$result = unzip_file($temp_file, $folder);
				if (is_wp_error($result)) {
					wp_die($result->get_error_message());
				}

				// Delete temporary file
				unlink($temp_file);

				// Activate plugin
				activate_plugins(__FILE__);

				// Inform
				solo_woocommerce_inform('update');

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

					// KPD validation
					if (isset($data['kpd'])) {
						$settings_data['kpd'] = preg_match('/^\d+\.\d+\.\d+$/', trim($data['kpd'])) ? sanitize_text_field(trim($data['kpd'])) : '';
					}
				}
			}

			// Show custom notice
			add_settings_error('solo_woocommerce_postavke', 'solo_woocommerce_postavke', $message, $type);

			return $settings_data;
		}
	}

	//// Ajax token check
	function solo_woocommerce_check_token() {
		check_ajax_referer('solo_check_token_nonce', 'nonce');
		if (!current_user_can('manage_options')) wp_die(-1);

		$token = sanitize_text_field($_REQUEST['token']);
		$url = wp_remote_get('https://api.solo.com.hr/licenca?token=' . urlencode($token));

		if (is_wp_error($url)) {
			$response = wp_remote_retrieve_response_code($url) . ': ' . wp_remote_retrieve_response_message($url);
		} else {
			$response = wp_remote_retrieve_body($url);
		}

		echo $response;
		wp_die();
	}

	//// Ajax retry order
	function solo_woocommerce_retry_order() {
		check_ajax_referer('solo_retry_order_nonce', 'nonce');
		if (!current_user_can('manage_options')) wp_die(-1);

		$order_id = intval($_POST['order_id']);
		if (!$order_id) wp_send_json_error('Invalid order ID');

		global $wpdb;
		$table_name = $wpdb->prefix . 'solo_woocommerce';

		// Read original request from DB
		$row = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM $table_name WHERE order_id = %d",
			$order_id
		));

		if (!$row) wp_send_json_error('Narudžba ne postoji u arhivi.');

		// Extract URL and body from stored api_request
		// Format is: "POST https://api.solo.com.hr/racun\n&token=...&..."
		$lines = explode(PHP_EOL, $row->api_request, 2);
		$url = trim(str_replace('POST ', '', $lines[0]));
		$api_request = isset($lines[1]) ? trim($lines[1]) : '';

		if (empty($url) || empty($api_request)) wp_send_json_error('Nije moguće poslati novi zahtjev.');

		// Determine document type from URL
		$document_type = strpos($url, 'ponuda') !== false ? 'ponuda' : 'racun';

		// Make API call
		$api_response = wp_remote_post($url, array(
			'body' => $api_request,
			'sslverify' => !defined('WP_DEBUG') || !WP_DEBUG,
			'timeout' => 10,
			'headers' => ['Content-Type' => 'application/x-www-form-urlencoded']
		));

		if (is_wp_error($api_response)) {
			wp_send_json_error($api_response->get_error_message());
		}

		$api_response_body = wp_remote_retrieve_body($api_response);

		// Update DB row
		$wpdb->update(
			$table_name,
			array(
				'api_response' => $api_response_body,
				'updated' => current_time('mysql')
			),
			array('order_id' => $order_id)
		);

		// Decode response
		$json_response = json_decode($api_response_body, true);
		$status = $json_response['status'] ?? -1;
		$pdf = $json_response['racun']['pdf'] ?? $json_response['ponuda']['pdf'] ?? null;

		if ($status == 0 && $pdf) {
			// Schedule PDF download and email
			wp_schedule_single_event(time() + 5, 'solo_woocommerce_api_get', array($pdf, $order_id, $document_type));
			wp_send_json_success($api_response_body);
		} elseif ($status == 100) {
			wp_send_json_error(__('Pričekaj barem 10 sekundi prije slanja novog zahtjeva.', 'solo-for-woocommerce'));
		} else {
			wp_send_json_error($api_response_body);
		}
	}

	//// Build customer data from order
	private function build_customer_data($order, $order_id) {
		$kupac_ime = $order->get_billing_first_name();
		$kupac_prezime = $order->get_billing_last_name();
		$kupac_naziv = $kupac_ime . ' ' . $kupac_prezime;
		$naziv_tvrtke = $order->get_meta('_company_name');
		$adresa_tvrtke = $order->get_meta('_company_address');
		$kupac_oib = $order->get_meta('_vat_number');
		if (!empty($naziv_tvrtke)) $kupac_naziv = $naziv_tvrtke;
		$kupac_adresa = $order->get_billing_address_1();
		if (!empty($order->get_billing_address_2())) $kupac_adresa .= ' ' . $order->get_billing_address_2();
		$kupac_adresa .= ', ' . $order->get_billing_postcode() . ' ' . $order->get_billing_city() . ', ' . $order->get_billing_country();
		if (!empty($adresa_tvrtke)) $kupac_adresa = $adresa_tvrtke;

		return array(
			'naziv' => $kupac_naziv,
			'adresa' => $kupac_adresa,
			'oib' => $kupac_oib,
			'tip_kupca' => $this->resolve_tip_kupca($order->get_billing_country(), $naziv_tvrtke, $adresa_tvrtke, $kupac_oib),
		);
	}

	private function resolve_tip_kupca($country, $naziv_tvrtke, $adresa_tvrtke, $oib) {
		$is_r1 = !empty($naziv_tvrtke) && !empty($adresa_tvrtke) && !empty($oib);
		if (!$is_r1) return 1;

		$eu_countries = array('AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK', 'HR');

		if ($country === 'HR') return 2;
		if (in_array($country, $eu_countries)) return 4;
		return 5;
	}

	//// Build order items payload
	private function build_items_payload($order, &$i, $kpd = '', $tip_kupca = 1) {
		$payload = '';
		foreach ($order->get_items() as $item) {
			$i++;
			$item_name = $item->get_name();
			$item_quantity = $item->get_quantity();
			$tax_total = $item->get_subtotal_tax();
			$taxes = WC_Tax::get_rates($item->get_tax_class());
			$item_tax = isset(current($taxes)['rate']) ? round(current($taxes)['rate']) : 0;

			if (!in_array($item_tax, [5, 13, 25]) || $tax_total == 0) $item_tax = 0;

			$item_id = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();
			$product = wc_get_product($item_id);

			if (!$product) continue;

			if ($item->get_variation_id()) {
				$item_name = $product->get_title();
				foreach ($item->get_meta_data() as $meta) {
					if (empty($meta->id) || '' === $meta->value || !is_scalar($meta->value)) continue;
					$meta->key = rawurldecode((string) $meta->key);
					$meta->value = rawurldecode((string) $meta->value);
					$attribute_key = str_replace('attribute_', '', $meta->key);
					$display_key = wc_attribute_label($attribute_key, $product);
					$display_value = wp_kses_post($meta->value);
					if (taxonomy_exists($attribute_key)) {
						$term = get_term_by('slug', $meta->value, $attribute_key);
						if (!is_wp_error($term) && is_object($term) && $term->name) {
							$display_value = $term->name;
						}
					}
					$item_name .= "\r\n" . $display_key . ': ' . $display_value;
				}
			}

/*
			// Discounted item will get current item price, otherwise uses order item price
			$item_regular_price = wc_get_price_excluding_tax($product, array('price' => $item->get_subtotal() / $item->get_quantity()));
			$item_discount = 0;
			if ($product->is_on_sale()) {
				$regular = wc_get_price_excluding_tax($product, array('price' => $product->get_regular_price()));
				$sale = wc_get_price_excluding_tax($product, array('price' => $product->get_sale_price()));
				if ($regular > 0) {
					$item_discount = 100 - (($sale / $regular) * 100);
					$item_discount = substr($item_discount, 0, 8);
					$item_discount = number_format($item_discount, 4, ',', '');
				}
				$item_regular_price = $regular;
			}
			$item_price = number_format(round($item_regular_price, 2), 2, ',', '');
*/
			$item_price = wc_get_price_excluding_tax($product, array('price' => $item->get_subtotal() / $item->get_quantity()));
			$item_price = number_format(round($item_price, 2), 2, ',', '');
			$item_discount = 0;
			$item_quantity = str_replace('.', ',', $item_quantity);
			$item_discount = str_replace('.', ',', $item_discount);

			$payload .= '&usluga=' . $i . PHP_EOL;
			$product_kpd = get_post_meta($item->get_product_id(), '_solo_kpd', true);
			$resolved_kpd = !empty($product_kpd) ? $product_kpd : $kpd;
			if (!empty($resolved_kpd) && $tip_kupca == 2) $payload .= '&kpd_' . $i . '=' . urlencode($resolved_kpd) . PHP_EOL;
			$payload .= '&opis_usluge_' . $i . '=' . urlencode($item_name) . PHP_EOL;
			$payload .= '&jed_mjera_' . $i . '=2' . PHP_EOL;
			$payload .= '&cijena_' . $i . '=' . $item_price . PHP_EOL;
			$payload .= '&kolicina_' . $i . '=' . $item_quantity . PHP_EOL;
			$payload .= '&popust_' . $i . '=' . $item_discount . PHP_EOL;
			$payload .= '&porez_stopa_' . $i . '=' . $item_tax . PHP_EOL;
		}
		return $payload;
	}

	//// Build coupons and shipping payload
	private function build_extras_payload($order, &$i, $kpd = '', $tip_kupca = 1) {
		$payload = '';

		// Coupons
		foreach ($order->get_items('coupon') as $item) {
			$coupon_data = $item->get_data();
			$coupon_price = $coupon_data['discount'];
			$coupon_code = $coupon_data['code'];
			$coupon_tax = $coupon_data['discount_tax'];
			$coupon_price = $coupon_price + $coupon_tax;
			$coupon_tax_rate = ($coupon_data['discount'] > 0) ? round(($coupon_tax / $coupon_data['discount']) * 100) : 0;
			if (!in_array($coupon_tax_rate, [5, 13, 25])) $coupon_tax_rate = 0;

			if ($coupon_price > 0) {
				$i++;
				$coupon_price = -1 * round($coupon_data['discount'], 2);
				$coupon_price = number_format($coupon_price, 2, ',', '');

				$payload .= '&usluga=' . $i . PHP_EOL;
				if (!empty($kpd) && $tip_kupca == 2) $payload .= '&kpd_' . $i . '=' . urlencode($kpd) . PHP_EOL;
				$payload .= '&opis_usluge_' . $i . '=' . urlencode(__('Kupon za popust', 'solo-for-woocommerce') . ' (' . $coupon_code . ')') . PHP_EOL;
				$payload .= '&jed_mjera_' . $i . '=1' . PHP_EOL;
				$payload .= '&cijena_' . $i . '=' . $coupon_price . PHP_EOL;
				$payload .= '&kolicina_' . $i . '=1' . PHP_EOL;
				$payload .= '&popust_' . $i . '=0' . PHP_EOL;
				$payload .= '&porez_stopa_' . $i . '=' . $coupon_tax_rate . PHP_EOL;
			}
		}

		// Fees
		foreach ($order->get_items('fee') as $item) {
			$fee_data = $item->get_data();
			$fee_name = $fee_data['name'];
			$fee_total = $fee_data['total'];
			$fee_tax = $fee_data['total_tax'];

			if ($fee_total != 0) {
				$fee_tax_rate = ($fee_total != 0 && $fee_tax != 0) ? round(($fee_tax / $fee_total) * 100) : 0;
				if (!in_array($fee_tax_rate, [5, 13, 25])) $fee_tax_rate = 0;

				$i++;
				$payload .= '&usluga=' . $i . PHP_EOL;
				if (!empty($kpd) && $tip_kupca == 2) $payload .= '&kpd_' . $i . '=' . urlencode($kpd) . PHP_EOL;
				$payload .= '&opis_usluge_' . $i . '=' . urlencode($fee_name) . PHP_EOL;
				$payload .= '&jed_mjera_' . $i . '=1' . PHP_EOL;
				$payload .= '&cijena_' . $i . '=' . number_format(round($fee_total, 2), 2, ',', '') . PHP_EOL;
				$payload .= '&kolicina_' . $i . '=1' . PHP_EOL;
				$payload .= '&popust_' . $i . '=0' . PHP_EOL;
				$payload .= '&porez_stopa_' . $i . '=' . $fee_tax_rate . PHP_EOL;
			}
		}

		// Shipping
		$shipping_price = $order->get_shipping_total();
		$shipping_tax = $order->get_shipping_tax();
		if ($shipping_price > 0) {
			$i++;
			$shipping_tax = is_numeric($shipping_tax / $shipping_price) ? round(($shipping_tax / $shipping_price) * 100) : 0;
			$shipping_price = number_format(round($shipping_price, 2), 2, ',', '');

			$payload .= '&usluga=' . $i . PHP_EOL;
			if (!empty($kpd) && $tip_kupca == 2) $payload .= '&kpd_' . $i . '=' . urlencode('53.20.09') . PHP_EOL;
			$payload .= '&opis_usluge_' . $i . '=' . urlencode(__('Poštarina', 'solo-for-woocommerce')) . PHP_EOL;
			$payload .= '&jed_mjera_' . $i . '=1' . PHP_EOL;
			$payload .= '&cijena_' . $i . '=' . $shipping_price . PHP_EOL;
			$payload .= '&kolicina_' . $i . '=1' . PHP_EOL;
			$payload .= '&popust_' . $i . '=0' . PHP_EOL;
			$payload .= '&porez_stopa_' . $i . '=' . $shipping_tax . PHP_EOL;
		}

		return $payload;
	}

	//// Resolve currency exchange rate and currency ID
	private function resolve_currency($currency, $grammar) {
		if ($currency === 'EUR') return array('payload' => '', 'valid' => true);

		$accepted_currencies = array(
			'AUD' => '2', 'CAD' => '3', 'CZK' => '4',
			'DKK' => '5', 'HUF' => '6', 'JPY' => '7',
			'NOK' => '8', 'SEK' => '9', 'CHF' => '10',
			'GBP' => '11', 'USD' => '12', 'BAM' => '13',
			'EUR' => '14', 'PLN' => '15'
		);

		if (!isset($accepted_currencies[$currency])) return array('payload' => '', 'valid' => false);

		$exchange_rates = json_decode(get_option('solo_woocommerce_tecaj'), true);
		$currency_exchange = '1';
		if (!empty($exchange_rates) && isset($exchange_rates[$currency])) {
			$currency_exchange = str_replace('.', ',', $exchange_rates[$currency]);
		}

		$currency_id = $accepted_currencies[$currency];
		$payload = '&valuta_' . $grammar . '=' . $currency_id . PHP_EOL;
		$payload .= '&tecaj=' . $currency_exchange . PHP_EOL;
		$payload .= '&napomene=' . urlencode(__('Preračunato po srednjem tečaju HNB-a', 'solo-for-woocommerce') . ' (1 EUR = ' . $currency_exchange . ' ' . $currency . ')') . PHP_EOL;

		return array('payload' => $payload, 'valid' => true);
	}

	//// Process order before sending to Solo API
	function solo_woocommerce_process_order($order_id, $old_status, $new_status) {
		$order = wc_get_order($order_id);
		if (!$order) return;
		$payment_method = $order->get_payment_method();

		$settings = get_option('solo_woocommerce_postavke');
		$document_type = $trigger = '';
		if (!empty($settings)) {
			$token = $settings['token'] ?? '';
			$tip_usluge = isset($settings['tip_usluge']) && is_numeric($settings['tip_usluge']) ? $settings['tip_usluge'] : 1;
			$prikazi_porez = $settings['prikazi_porez'] ?? 0;
			$tip_racuna = !empty($settings['tip_racuna']) ? $settings['tip_racuna'] : 1;
			$rok_placanja = $settings['rok_placanja'] ?? '';
			$iban = $settings['iban'] ?? '';
			$jezik_ = $settings['jezik_'] ?? '';
			$napomene_racun = $settings['napomene_racun'] ?? '';
			$napomene_ponuda = $settings['napomene_ponuda'] ?? '';
			$kpd = $settings['kpd'] ?? '';
			$document_type = $settings[$payment_method . '1'] ?? '';
			$trigger = $settings[$payment_method . '2'] ?? '';
		}

		if ($document_type === '' || $trigger === '') return;

		// Check if order already exists
		global $wpdb;
		$table_name = $wpdb->prefix . 'solo_woocommerce';
		$exists = $wpdb->get_var($wpdb->prepare("SELECT order_id FROM $table_name WHERE order_id = %d", $order_id));

		// Proceed on "checkout" or "completed"
		$is_checkout = ($old_status == 'pending' && in_array($new_status, ['on-hold', 'processing']) && $trigger == 1 && !$exists);
		$is_completed = ($old_status != $new_status && $new_status == 'completed' && $trigger == 2 && !$exists);
		if (!$is_checkout && !$is_completed) return;

		// Payment method mapping
		$payment_map = array(
			'bacs' => 1,
			'cod' => 1,
			'stripe' => 3,
			'stripe_sepa' => 1,
			'paypal' => 1,
			'ppec-gateway' => 1,
			'ppcp-gateway' => 1,
			'braintree_credit_card' => 3,
			'braintree_paypal' => 1,
			'corvuspay' => 3,
			'monri' => 3,
			'mypos_virtual' => 3,
			'wooplatnica-croatia' => 1,
			'erste-kekspay-woocommerce' => 1,
			'eh_paypal_express' => 3,
			'revolut_cc' => 3,
			'aircash-woocommerce' => 3,
			'woocommerce_payments' => 3,
			'woocommerce_payments_sepa' => 1,
			'woocommerce_payments_apple_pay' => 3,
			'woocommerce_payments_google_pay' => 3,
			'teya_payments' => 3,
			'borgun' => 3,
			'vivacom_smart' => 3,
			'mollie_wc_gateway_creditcard' => 3,
			'mollie_wc_gateway_banktransfer' => 1,
			'mollie_wc_gateway_paypal' => 1,
			'klarna_payments' => 3,
			'kco' => 3,
		);

		if (!isset($payment_map[$payment_method])) return;
		$nacin_placanja = $payment_map[$payment_method];

		$grammar = $document_type === 'racun' ? 'racuna' : 'ponude';
		$url = 'https://api.solo.com.hr/' . $document_type;
		$customer = $this->build_customer_data($order, $order_id);

		// Build API request
		$i = 0;
		$api_request = 'token=' . $token . PHP_EOL;
		$api_request .= '&tip_usluge=' . $tip_usluge . PHP_EOL;
		$api_request .= '&tip_kupca=' . $customer['tip_kupca'] . PHP_EOL;
		$api_request .= '&prikazi_porez=' . $prikazi_porez . PHP_EOL;
		$api_request .= '&tip_racuna=' . $tip_racuna . PHP_EOL;
		$api_request .= '&kupac_naziv=' . urlencode($customer['naziv']) . PHP_EOL;
		$api_request .= '&kupac_adresa=' . urlencode($customer['adresa']) . PHP_EOL;
		$api_request .= '&kupac_oib=' . urlencode($customer['oib']) . PHP_EOL;
		$api_request .= $this->build_items_payload($order, $i, $kpd, $customer['tip_kupca']);
		$api_request .= $this->build_extras_payload($order, $i, $kpd, $customer['tip_kupca']);
		$api_request .= '&nacin_placanja=' . $nacin_placanja . PHP_EOL;
		if (!empty($rok_placanja)) $api_request .= '&rok_placanja=' . $rok_placanja . PHP_EOL;
		if (!empty($iban)) $api_request .= '&iban=' . $iban . PHP_EOL;

		// Currency
		$currency_data = $this->resolve_currency($order->get_currency(), $grammar);
		if (!$currency_data['valid']) return;
		$api_request .= $currency_data['payload'];

		// Notes
		$napomene = $document_type === 'racun' ? $napomene_racun : $napomene_ponuda;
		if (!empty($napomene)) $api_request .= '&napomene=' . urlencode($napomene) . PHP_EOL;

		// Language
		if (!empty($jezik_)) $api_request .= '&jezik_' . $grammar . '=' . $jezik_ . PHP_EOL;

		// Ensure table exists
		if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
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

		// Send to Solo API
		solo_woocommerce_api_post($url, str_replace(PHP_EOL, '', $api_request), $order_id, $document_type);
	}

	//// Product edit page: KPD oznaka field
	public function solo_woocommerce_product_kpd_field() {
		global $post;
		$kpd = get_post_meta($post->ID, '_solo_kpd', true);
		echo '<div class="options_group">';
		echo '<p class="form-field">';
		echo '<label for="kpd">' . esc_html__('KPD oznaka', 'solo-for-woocommerce') . '</label>';
		echo '<input type="text" id="kpd" name="kpd" value="' . esc_attr($kpd) . '" placeholder="npr. 47.71.00" maxlength="8" class="short" autocomplete="off" />';
		echo wc_help_tip(__('Ostavi prazno ako želiš koristiti samo zadanu KPD oznaku iz Postavki.', 'solo-for-woocommerce'));
		echo '</p>';
		echo '</div>';
	}

	//// Product edit page: save KPD oznaka
	public function solo_woocommerce_save_product_kpd($post_id) {
		$kpd = isset($_POST['kpd']) ? sanitize_text_field($_POST['kpd']) : '';
		if (preg_match('/^\d{2}\.\d{2}\.\d{2}$/', $kpd) || $kpd === '') {
			update_post_meta($post_id, '_solo_kpd', $kpd);
		}
	}
	public function solo_woocommerce_kpd_column_content($column, $post_id) {
		if ($column === 'sku') {
			$kpd = get_post_meta($post_id, '_solo_kpd', true);
			if ($kpd) {
				echo '<br><span style="color:#999;font-size:11px;">KPD: ' . esc_html($kpd) . '</span>';
			}
		}
	}
}
$solo_woocommerce = new solo_woocommerce;