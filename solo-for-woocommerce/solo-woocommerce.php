<?php
/**
 * Plugin Name: Solo for WooCommerce
 * Plugin URI: https://solo.com.hr/api-dokumentacija/dodaci
 * Description: Narudžba u tvojoj WooCommerce trgovini će automatski kreirati račun ili ponudu u servisu Solo.
 * Version: 2.7
 * Requires at least: 5.3
 * Tested up to: 7.1
 * Requires PHP: 7.2
 * Requires Plugins: woocommerce
 * Author: Solo
 * Author URI: https://solo.com.hr/
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: solo-for-woocommerce
 * Domain Path: /languages
 */

// Disallow direct call to this file
if (!defined('WPINC')) {
	die;
}

//// Variables
define('SOLO_VERSION', '2.7');
define('SOLO_API_URL', 'https://api.solo.com.hr/');

//// Activate plugin
register_activation_hook(__FILE__, 'solo_woocommerce_activate');

function solo_woocommerce_activate() {
	// Check PHP version
	if (version_compare(PHP_VERSION, '7.2', '<')) {
		// translators: %s is the current PHP version number.
		wp_die(sprintf(esc_html__('Solo for WooCommerce dodatak ne podržava PHP %s. Ažuriraj PHP na verziju 7.2 ili noviju.', 'solo-for-woocommerce'), PHP_VERSION), esc_html__('Greška', 'solo-for-woocommerce'), array("back_link" => true));
	}

	// Check if WooCommerce plugin installed
	if (!class_exists('WooCommerce')) {
		wp_die(esc_html__('Solo for WooCommerce ne radi bez WooCommerce dodatka.<br>Prvo instaliraj WooCommerce i zatim aktiviraj ovaj dodatak.', 'solo-for-woocommerce'), esc_html__('Greška', 'solo-for-woocommerce'), array("back_link" => true));
	}
	if (version_compare(get_option('woocommerce_version'), 5, '<')) {
		wp_die(esc_html__('Solo for WooCommerce radi samo s WooCommerce verzijom 5 ili novijom.', 'solo-for-woocommerce'), esc_html__('Greška', 'solo-for-woocommerce'), array("back_link" => true));
	}

	// Check if Woo Solo Api plugin installed
	if (is_plugin_active('woo-solo-api/woo-solo-api.php')) {
		wp_die(esc_html__('Prvo deaktiviraj "Woo Solo Api" dodatak.', 'solo-for-woocommerce'), esc_html__('Greška', 'solo-for-woocommerce'), array("back_link" => true));
	}

	// Check if MX R1 plugin installed
	if (is_plugin_active('woocommerce-mx-r1/woocommerce-mx-r1.php')) {
		wp_die(esc_html__('Prvo deaktiviraj "WooCommerce MX R1 račun" dodatak.<br>Solo for WooCommerce automatski dodaje polja za pravne osobe (R1 račun) pri naručivanju.', 'solo-for-woocommerce'), esc_html__('Greška', 'solo-for-woocommerce'), array("back_link" => true));
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
	delete_transient('solo_woocommerce_tag');
	delete_transient('solo_woocommerce_url');

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
	delete_transient('solo_woocommerce_tag');
	delete_transient('solo_woocommerce_url');

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

	wp_remote_post(SOLO_API_URL . 'solo-for-woocommerce', array(
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
			wp_schedule_event(time(), 'twicedaily', 'solo_woocommerce_exchange_update', array(2));

			break;
		// Update
		case 2:
			$encoded_json = solo_woocommerce_exchange_fetch();

			// Update only on successful fetch - keep last good rates if HNB is down
			if ($encoded_json !== '') update_option('solo_woocommerce_tecaj', $encoded_json, false);

			break;
		// View
		case 3:
			// Read exchange rate from wp_options table
			$exchange = get_option('solo_woocommerce_tecaj');
			if (!$exchange) {
				// translators: %s is the URL to the plugins page.
				echo '<br><div class="notice notice-error inline"><p>' . sprintf(wp_kses_post(__('Tečajna lista nije dostupna. Pokušaj <a href="%s#deactivate-solo-for-woocommerce">deaktivirati</a> i ponovno aktivirati dodatak.', 'solo-for-woocommerce')), esc_url(admin_url('plugins.php'))) . '</p></div>';
			} else {
				$decoded_json = json_decode($exchange, true);
				$last_updated = isset($decoded_json['datum']) ? $decoded_json['datum'] : '';
				// translators: %1$s is the last updated date, %2$s is the next update time.
				echo '<p>' . wp_kses_post(sprintf(__('Tečajna lista je formatirana za Solo gdje se HNB-ov tečaj dijeli s 1 (npr. tečaj za račun ili ponudu u valuti USD treba biti 0,94 umjesto 7,064035).<br>Zadnje ažuriranje: %1$s. Iduće ažuriranje u %2$s. Izvor podataka: <a href="https://www.hnb.hr/statistika/statisticki-podaci/financijski-sektor/sredisnja-banka-hnb/devizni-tecajevi/referentni-tecajevi-esb-a" target="_blank">Hrvatska Narodna Banka</a>', 'solo-for-woocommerce'), esc_html($last_updated), esc_html(get_date_from_gmt(gmdate('H:i', wp_next_scheduled('solo_woocommerce_exchange_update', array(2))), 'H:i')))) . '</p>';
				echo '<table class="widefat striped" style="width:auto;"><colgroup><col style="width:50%;"><col style="width:50%;"></colgroup><thead><tr><th>' . esc_html__('Valuta', 'solo-for-woocommerce') . '</th><th>' . esc_html__('Tečaj', 'solo-for-woocommerce') . '</th></tr></thead><tbody>';
				foreach($decoded_json as $key => $val) {
					if ($key=='datum') continue; // Remove date from view
					echo '<tr><td>1 ' . esc_html($key) . '</td><td>' . esc_html(str_replace('.', ',', $val)) . ' EUR</td></tr>';
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

	// Stop on connection error or non-200 response
	if (is_wp_error($json) || wp_remote_retrieve_response_code($json) != 200) return '';

	// Decode JSON
	$decoded_json = json_decode(wp_remote_retrieve_body($json), true);
	if (empty($decoded_json) || !is_array($decoded_json)) return '';

	// Parse JSON
	$array = array('datum' => get_date_from_gmt(gmdate('Y-m-d H:i:s')));
	foreach ($decoded_json as $item) {
		// Skip malformed entries (also prevents division by zero)
		if (!is_array($item) || empty($item['valuta']) || empty($item['srednji_tecaj'])) continue;
		$rate = solo_woocommerce_floatvalue($item['srednji_tecaj']);
		if ($rate <= 0) continue;
		$array[$item['valuta']] = substr(1 / $rate, 0, 8);
	}

	// No usable rates - treat as failed fetch
	if (count($array) < 2) return '';

	// Build JSON
	return json_encode($array);
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
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name))!=$table_name) {
		// Define table structure
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
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

//// Call Solo API to create document, returns array('status' => ..., 'message' => ...)
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

	// Connection error or timeout - store the reason so it's visible in Arhiva
	if (is_wp_error($api_response)) {
		// translators: %s is the connection error message.
		$message = sprintf(__('Greška u komunikaciji sa Solo: %s. Prije ponovnog slanja provjeri u Solo je li dokument ipak kreiran.', 'solo-for-woocommerce'), $api_response->get_error_message());
		$api_response = wp_json_encode(array('status' => -1, 'message' => $message), JSON_UNESCAPED_UNICODE);
	} else {
		$api_response = wp_remote_retrieve_body($api_response);
	}

	// Save API response to our table
	global $wpdb;
	$table_name = $wpdb->prefix . 'solo_woocommerce';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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
	$message = $json_response['message'] ?? '';
	$document = $json_response['racun'] ?? $json_response['ponuda'] ?? array();
	$pdf = $document['pdf'] ?? null;

	if ($status == 0 && isset($pdf)) {
		// Download and send PDF
		wp_schedule_single_event(time() + 5, 'solo_woocommerce_api_get', array($pdf, $order_id, $document_type));
	} elseif ($status == 100) {
		// Retry after 10 seconds
		wp_schedule_single_event(time() + 10, 'solo_woocommerce_api_post', array($url, $api_request, $order_id, $document_type));
	}

	// Order note - result visible directly on the order (not for status 100, it is retried automatically)
	$order = wc_get_order($order_id);
	if ($order && $status != 100) {
		if ($status == 0) {
			$number = esc_html($document['broj_racuna'] ?? $document['broj_ponude'] ?? '');
			// translators: %s is the document number in Solo
			$note = $document_type === 'racun' ? sprintf(__('Račun %s je kreiran u Solo.', 'solo-for-woocommerce'), $number) : sprintf(__('Ponuda %s je kreirana u Solo.', 'solo-for-woocommerce'), $number);
			// B2B invoices under fiscalization 2.0 come back with an XML link instead of a PDF
			$file = $document['pdf'] ?? $document['xml'] ?? '';
			if ($file) $note .= ' <a href="' . esc_url($file) . '" target="_blank">' . (isset($document['pdf']) ? 'PDF' : 'XML') . '</a>';
		} else {
			// translators: %s is the error message
			$note = sprintf(__('Slanje u Solo nije uspjelo: %s', 'solo-for-woocommerce'), esc_html($message !== '' ? $message : __('nepoznata greška', 'solo-for-woocommerce')));
			$note .= ' <a href="' . esc_url(admin_url('admin.php?page=solo-woocommerce&tab=arhiva')) . '">' . esc_html__('Arhiva', 'solo-for-woocommerce') . '</a>';
		}
		$order->add_order_note($note);
	}

	return array('status' => (int) $status, 'message' => $message, 'response' => $api_response);
}

//// Download PDF and send e-mail to buyer
function solo_woocommerce_api_get($pdf, $order_id, $document_type) {
	// Read settings (static method - no class instance needed)
	$send = solo_woocommerce::setting('posalji');

	// Pick title/body per document type, falling back to old shared fields for backwards compatibility
	$title_key = $document_type === 'racun' ? 'naslov_racun' : 'naslov_ponuda';
	$body_key = $document_type === 'racun' ? 'poruka_racun' : 'poruka_ponuda';
	$title = solo_woocommerce::setting($title_key);
	$body = solo_woocommerce::setting($body_key);
	if (empty($title)) $title = solo_woocommerce::setting('naslov');
	if (empty($body)) $body = solo_woocommerce::setting('poruka');

	// Proceed if enabled in settings
	if ($send == '1') {
		// Read order details
		$order = wc_get_order($order_id);
		if (!$order) return;
		$billing_email = $order->get_billing_email();

		// Set download folder - private subfolder, not publicly accessible
		$upload_dir = wp_upload_dir();
		$folder = $upload_dir['basedir'] . '/racuni/';
		if (!file_exists($folder)) {
			wp_mkdir_p($folder);
			global $wp_filesystem;
			if (empty($wp_filesystem)) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}
			$wp_filesystem->put_contents($folder . '.htaccess', 'deny from all', FS_CHMOD_FILE);
			$wp_filesystem->put_contents($folder . 'index.php', '<?php // These are not the droids you\'re looking for.', FS_CHMOD_FILE);
		}

		// Unique filename per order to prevent overwrites on concurrent orders
		$local_file = $folder . $document_type . '-' . $order_id . '-' . wp_generate_password(8, false) . '.pdf';

		// Download PDF via WP HTTP API
		$remote_file = wp_remote_get($pdf, array('timeout' => 30));
		if (is_wp_error($remote_file)) {
			return;
		}
		global $wp_filesystem;
		if (empty($wp_filesystem)) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		$wp_filesystem->put_contents($local_file, wp_remote_retrieve_body($remote_file), FS_CHMOD_FILE);

		// Send e-mail with PDF in attachment
		$sent = wp_mail($billing_email, $title, $body, '', array($local_file));

		if ($sent) {
			// Save sent date
			global $wpdb;
			$table_name = $wpdb->prefix . 'solo_woocommerce';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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
		// Shortcuts
		$this->plugin_name = 'solo-woocommerce';

		// Declare HPOS compatibility
		add_action('before_woocommerce_init', function() {
			if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
			}
		});

		// Scheduled jobs
		add_action('solo_woocommerce_exchange_update', 'solo_woocommerce_exchange');
		add_action('solo_woocommerce_api_post', 'solo_woocommerce_api_post', 1, 4);
		add_action('solo_woocommerce_api_get', 'solo_woocommerce_api_get', 2, 3);

		// WooCommerce: process order
		add_action('woocommerce_order_status_changed', array($this, 'solo_woocommerce_process_order'), 14, 3);
		// WooCommerce: gateway confirmed payment
		add_action('woocommerce_payment_complete', array($this, 'solo_woocommerce_payment_complete'), 14);

		// WooCommerce checkout
		if (!is_admin()) {
			add_filter('woocommerce_checkout_fields', array($this, 'solo_woocommerce_remove_fields'), 11);
			add_action('woocommerce_before_checkout_billing_form', array($this, 'solo_woocommerce_custom_fields'), 12);
			add_action('woocommerce_order_details_after_order_table', array($this, 'solo_woocommerce_customer_order_meta'), 17);
			add_action('wp_enqueue_scripts', array($this, 'solo_woocommerce_block_checkout_style'));
		}

		// WooCommerce blocks and checkout saving
		add_action('woocommerce_init', array($this, 'solo_woocommerce_register_block_fields'));
		add_action('woocommerce_set_additional_field_value', array($this, 'solo_woocommerce_save_block_fields'), 10, 4);
		add_action('woocommerce_checkout_update_order_meta', array($this, 'solo_woocommerce_custom_meta'), 13);

		if (is_admin()) {
			// Create settings link in WordPress > Plugins
			add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'solo_woocommerce_settings_link'));

			// Create settings link inside WooCommerce menu
			add_action('admin_menu', array($this, 'solo_woocommerce_submenu_link'), 99);

			// Load custom CSS and JS only on plugin pages and WooCommerce order/product pages
			add_action('admin_enqueue_scripts', array($this, 'solo_woocommerce_css_js'));

			// Plugin settings (or update plugin)
			add_action('admin_init', array($this, 'solo_woocommerce_settings'));
			add_action('wp_dashboard_setup', array($this, 'solo_woocommerce_dashboard_widget'));

			// Always show messages
			add_action('admin_notices', array($this, 'solo_woocommerce_show_messages'));

			// Ajax token check
			add_action('wp_ajax_solo_woocommerce_check_token', array($this, 'solo_woocommerce_check_token'));

			// Ajax retry order
			add_action('wp_ajax_solo_woocommerce_retry_order', array($this, 'solo_woocommerce_retry_order'));

			// WooCommerce: show custom fields in admin order page
			add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'solo_woocommerce_admin_order_meta'), 15);

			// WooCommerce: show company name and Solo status in orders list (classic and HPOS)
			add_action('manage_shop_order_posts_custom_column', array($this, 'solo_woocommerce_admin_column_meta'), 16, 2);
			add_action('manage_woocommerce_page_wc-orders_custom_column', array($this, 'solo_woocommerce_admin_column_meta'), 16, 2);

			// Per-product KPD oznaka field
			add_action('woocommerce_product_options_general_product_data', array($this, 'solo_woocommerce_product_kpd_field'));
			add_action('woocommerce_process_product_meta', array($this, 'solo_woocommerce_save_product_kpd'));
			add_action('manage_product_posts_custom_column', array($this, 'solo_woocommerce_kpd_column_content'), 20, 2);

			// Manual sending from WooCommerce order actions dropdown
			add_filter('woocommerce_order_actions', array($this, 'solo_woocommerce_add_order_action'));
			add_action('woocommerce_order_action_solo_send', array($this, 'solo_woocommerce_manual_send'));
		}
	}

	//// Show notices
	function solo_woocommerce_show_messages() {
		$user_id = get_current_user_id();
		$notice = get_transient('solo_woocommerce_manual_send_notice_' . $user_id);
		if ($notice) {
			delete_transient('solo_woocommerce_manual_send_notice_' . $user_id);
			echo '<div class="notice notice-' . esc_attr($notice['type']) . ' is-dismissible"><p>' . wp_kses_post($notice['message']) . '</p></div>';
		}

		// Only our own messages - WordPress prints its own on Settings pages
		settings_errors('solo_woocommerce_postavke');
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
			'label' => esc_html__('Trebam R1 račun', 'solo-for-woocommerce'),
			'location' => 'contact',
			'type' => 'checkbox',
		));

		woocommerce_register_additional_checkout_field(array(
			'id' => 'solo-for-woocommerce/company_name',
			'label' => esc_html__('Naziv tvrtke', 'solo-for-woocommerce'),
			'location' => 'contact',
			'type' => 'text',
			'required' => false,
		));

		woocommerce_register_additional_checkout_field(array(
			'id' => 'solo-for-woocommerce/company_address',
			'label' => esc_html__('Adresa tvrtke', 'solo-for-woocommerce'),
			'location' => 'contact',
			'type' => 'text',
			'required' => false,
		));

		woocommerce_register_additional_checkout_field(array(
			'id' => 'solo-for-woocommerce/vat_number',
			'label' => esc_html__('OIB tvrtke', 'solo-for-woocommerce'),
			'location' => 'contact',
			'type' => 'text',
			'required' => false,
		));
	}

	//// Block checkout: show R1 fields only when "Trebam R1 račun" is checked (pure CSS, no jQuery)
	public function solo_woocommerce_block_checkout_style() {
		if (!is_checkout() || is_wc_endpoint_url()) return;

		wp_register_style($this->plugin_name . '-checkout-blocks', false, array(), SOLO_VERSION);
		wp_enqueue_style($this->plugin_name . '-checkout-blocks');
		wp_add_inline_style($this->plugin_name . '-checkout-blocks', '#contact:has(#contact-solo-for-woocommerce-r1:not(:checked)) :is(.wc-block-components-address-form__solo-for-woocommerce-company_name,.wc-block-components-address-form__solo-for-woocommerce-company_address,.wc-block-components-address-form__solo-for-woocommerce-vat_number){display:none;}');
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
		}
	}

	//// Show custom fields in checkout
	public function solo_woocommerce_custom_fields($fields) {
		echo '<div id="solo-r1-fields">';
		woocommerce_form_field('vat_checkbox', array(
				'type' => 'checkbox',
				'label' => esc_html__('Trebam R1 račun', 'solo-for-woocommerce'),
				'required' => false,
				'class' => array('input-checkbox')
			)
		);
		woocommerce_form_field('company_name', array(
				'type' => 'text',
				'label' => esc_html__('Naziv tvrtke', 'solo-for-woocommerce'),
				'placeholder' => esc_html__('Naziv tvrtke', 'solo-for-woocommerce'),
				'required' => false,
				'class' => array('form-row-wide hidden')
			),
			$fields->get_value('company_name')
		);
		woocommerce_form_field('company_address', array(
				'type' => 'text',
				'label' => esc_html__('Adresa', 'solo-for-woocommerce'),
				'placeholder' => esc_html__('Adresa', 'solo-for-woocommerce'),
				'required' => false,
				'class' => array('form-row-wide hidden')
			),
			$fields->get_value('company_address')
		);
		woocommerce_form_field('vat_number', array(
				'type' => 'text',
				'label' => esc_html__('OIB', 'solo-for-woocommerce'),
				'placeholder' => esc_html__('OIB', 'solo-for-woocommerce'),
				'required' => false,
				'class' => array('form-row-wide hidden')
			),
			$fields->get_value('vat_number')
		);
		echo '</div>';

		wp_register_script($this->plugin_name . '-checkout', false, array('jquery'), SOLO_VERSION, true);
		wp_enqueue_script($this->plugin_name . '-checkout');
		wp_add_inline_script($this->plugin_name . '-checkout', 'jQuery(function($){$("#solo-r1-fields [type=checkbox]").on("click",function(){if($(this).is(":checked")){$("#company_name_field,#company_address_field,#vat_number_field").removeClass("hidden");$("#company_name").focus();}else{$("#company_name_field,#company_address_field,#vat_number_field").addClass("hidden");}});});');

		wp_register_style($this->plugin_name . '-checkout', false, array(), SOLO_VERSION);
		wp_enqueue_style($this->plugin_name . '-checkout');
		wp_add_inline_style($this->plugin_name . '-checkout', '#solo-r1-fields .hidden{display:none;}');
	}

	//// Save custom fields after checkout
	function solo_woocommerce_custom_meta($order_id) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if (!empty($_POST['vat_checkbox']) && !empty($_POST['vat_number'])) {
			$order = wc_get_order($order_id);
			if (!$order) return;
			$order->update_meta_data('_company_name', sanitize_text_field(wp_unslash($_POST['company_name'] ?? ''))); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$order->update_meta_data('_company_address', sanitize_text_field(wp_unslash($_POST['company_address'] ?? ''))); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$order->update_meta_data('_vat_number', sanitize_text_field(wp_unslash($_POST['vat_number'] ?? ''))); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$order->save();
		}
	}

	//// Show custom fields to admin
	public function solo_woocommerce_admin_order_meta($order) {
		if (!$order || !is_a($order, 'WC_Abstract_Order')) return;
		$naziv_tvrtke = $order->get_meta('_company_name');
		$adresa_tvrtke = $order->get_meta('_company_address');
		$oib = $order->get_meta('_vat_number');
		$is_block_checkout = $order->get_meta('_wc_other/solo-for-woocommerce/company_name') !== '';
		if ($naziv_tvrtke && !$is_block_checkout) {
			echo '<p><strong>' . esc_html__('Podaci za R1 račun', 'solo-for-woocommerce') . ':</strong><br>' . esc_html($naziv_tvrtke) . '<br>' . esc_html($adresa_tvrtke) . '<br>' . esc_html($oib) . '</p>';
		}
	}

	public function solo_woocommerce_admin_column_meta($column, $order = null) {
		if ($column === 'order_number') {
			if (!$order || is_int($order)) {
				global $the_order;
				$order = $the_order;
			}
			if (!$order || !is_a($order, 'WC_Abstract_Order')) return;
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
		$user_id = get_current_user_id();
		$arhiva_url = '<a href="' . esc_url(admin_url('admin.php?page=solo-woocommerce&tab=arhiva')) . '" target="_blank">' . esc_html__('Arhivi', 'solo-for-woocommerce') . '</a>';

		if ($this->order_exists_in_archive($order_id)) {
			set_transient('solo_woocommerce_manual_send_notice_' . $user_id, array(
				'type' => 'warning',
				// translators: %s is a link to the Arhiva tab
				'message' => sprintf(esc_html__('Ova narudžba već postoji u %s. Ako nije kreiran dokument u Solo, pokušaj ponoviti neuspjeli zahtjev izravno iz Arhive.', 'solo-for-woocommerce'), $arhiva_url)
			), 60);
			return;
		}

		$settings = get_option('solo_woocommerce_postavke');
		$payment_method = $order->get_payment_method();
		$document_type = $settings[$payment_method . '1'] ?? '';
		$akcije_url = '<a href="' . esc_url(admin_url('admin.php?page=solo-woocommerce&tab=akcije')) . '" target="_blank">' . esc_html__('načina plaćanja i/ili akcije', 'solo-for-woocommerce') . '</a>';

		if (empty($document_type)) {
			set_transient('solo_woocommerce_manual_send_notice_' . $user_id, array(
				'type' => 'error',
				// translators: %s is a link to the Načini plaćanja i akcije tab
				'message' => sprintf(esc_html__('Ova narudžba ne može biti poslana u Solo jer nema odgovarajućeg %s.', 'solo-for-woocommerce'), $akcije_url)
			), 60);
			return;
		}

		$payment_map = $this->get_payment_map();
		$url = SOLO_API_URL . $document_type;
		$built = $this->build_api_request($order, $document_type, $settings, $payment_map[$payment_method] ?? 1);

		// Unsupported currency or missing exchange rate
		if (!$built['valid']) {
			set_transient('solo_woocommerce_manual_send_notice_' . $user_id, array(
				'type' => 'error',
				'message' => esc_html__('Ova narudžba ne može biti poslana u Solo jer valuta nije podržana ili tečajna lista nije dostupna.', 'solo-for-woocommerce')
			), 60);
			return;
		}

		$result = $this->save_to_archive_and_send($url, $built['request'], $order_id, $document_type);

		if ($result['status'] === 0) {
			$type = 'success';
			// translators: %s is a link to the Arhiva tab
			$message = sprintf(esc_html__('Ova narudžba je uspješno poslana u Solo. Provjeri u %s.', 'solo-for-woocommerce'), $arhiva_url);
		} elseif ($result['status'] === 100) {
			$type = 'warning';
			// translators: %s is a link to the Arhiva tab
			$message = sprintf(esc_html__('Solo je trenutno nedostupan, narudžba će automatski biti ponovno poslana za nekoliko sekundi. Provjeri u %s.', 'solo-for-woocommerce'), $arhiva_url);
		} else {
			$type = 'error';
			$error = $result['message'] !== '' ? rtrim($result['message'], '. ') : __('nepoznata greška', 'solo-for-woocommerce');
			// translators: %1$s is the error message from Solo, %2$s is a link to the Arhiva tab
			$message = sprintf(esc_html__('Narudžba nije kreirana u Solo: %1$s. Detalji su u %2$s.', 'solo-for-woocommerce'), esc_html($error), $arhiva_url);
		}

		set_transient('solo_woocommerce_manual_send_notice_' . $user_id, array(
			'type' => $type,
			'message' => $message
		), 60);
	}

	//// Show custom fields to customer
	public function solo_woocommerce_customer_order_meta($order) {
		if (!$order || !is_a($order, 'WC_Abstract_Order')) return;
		$naziv_tvrtke = $order->get_meta('_company_name');
		$adresa_tvrtke = $order->get_meta('_company_address');
		$oib = $order->get_meta('_vat_number');
		$is_block_checkout = $order->get_meta('_wc_other/solo-for-woocommerce/company_name') !== '';
		if ($naziv_tvrtke && !$is_block_checkout) {
			echo '<h2 class="woocommerce-column__title">' . esc_html__('Podaci za R1 račun', 'solo-for-woocommerce') . '</h2>';
			echo '<p>' . esc_html($naziv_tvrtke) . '<br>' . esc_html($adresa_tvrtke) . '<br>' . esc_html($oib) . '</p>';
		}
	}

	//// Create settings link in plugins
	public function solo_woocommerce_settings_link($links) {
		$url = esc_url(add_query_arg('page', $this->plugin_name, get_admin_url() . 'admin.php'));
		$settings_link = '<a href="' . $url . '">' . esc_html__('Postavke', 'solo-for-woocommerce') . '</a>';
		array_unshift($links, $settings_link);
		return $links;
	}

	//// Create settings link under WooCommerce menu
	public function solo_woocommerce_submenu_link() {
		add_submenu_page('woocommerce', 'Solo for WooCommerce', esc_html__('Solo postavke', 'solo-for-woocommerce'), 'manage_options', $this->plugin_name, array($this, 'solo_woocommerce_settings_url'));
	}

	//// Settings file location
	public function solo_woocommerce_settings_url() {
		require_once plugin_dir_path(__FILE__) . 'lib/' . $this->plugin_name . '-settings.php';
	}

	//// Load custom CSS and JS
	public function solo_woocommerce_css_js() {
		$screen = get_current_screen();
		$allowed_screens = array(
			'woocommerce_page_' . $this->plugin_name,
			'product',
		);
		if (!$screen || !in_array($screen->id, $allowed_screens)) return;

		wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'lib/' . $this->plugin_name . '.css', false, SOLO_VERSION);
		wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'lib/' . $this->plugin_name . '.js', array('jquery'), SOLO_VERSION, true);
		wp_localize_script($this->plugin_name, 'solo_woocommerce_ajax_object', array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('solo_woocommerce_check_token_nonce'),
			'retry_nonce' => wp_create_nonce('solo_woocommerce_retry_order_nonce'),
			'i18n' => array(
				// translators: %s is the Solo account holder
				'token_valid' => __('Ispravan API token za korisnika %s', 'solo-for-woocommerce'),
				// translators: %1$s is the Solo package name, %2$s is the expiry date
				'license_paid' => __('Plaćena %1$s licenca traje do %2$s', 'solo-for-woocommerce'),
				'license_free' => __('Koristiš besplatni paket (ograničenje na 3 računa i ponude).', 'solo-for-woocommerce'),
				'retry' => __('Pokušaj ponovo', 'solo-for-woocommerce'),
				'wait' => __('Pričekaj', 'solo-for-woocommerce'),
				'error' => __('Greška', 'solo-for-woocommerce'),
				'search' => __('Upiši traženi pojam...', 'solo-for-woocommerce'),
				'kpd_placeholder' => __('npr. 47.71.00', 'solo-for-woocommerce')
			)
		));
		wp_localize_script($this->plugin_name, 'kpd_url', array('url' => plugin_dir_url(__FILE__) . 'lib/kpd.json'));
	}

	//// Return single setting
	public static function setting($id) {
		$data = get_option('solo_woocommerce_postavke');
		if (isset($data[$id])) return $data[$id];
	}

	//// Plugin settings (or update plugin)
	function solo_woocommerce_settings() {
		// Only react on our own page
		$is_plugin_page = isset($_GET['page']) && sanitize_key(wp_unslash($_GET['page'])) === $this->plugin_name; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Purge all plugin data
		if ($is_plugin_page && isset($_GET['purge']) && current_user_can('manage_options') && check_admin_referer('solo_woocommerce_purge_nonce')) {
			// Tables
			global $wpdb;
			$table_name = esc_sql($wpdb->prefix . 'solo_woocommerce');
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query("DROP TABLE IF EXISTS `{$table_name}`");
			// Options
			delete_option('solo_woocommerce_postavke');
			delete_option('solo_woocommerce_tecaj');
			// Transients
			delete_transient('solo_woocommerce_tag');
			delete_transient('solo_woocommerce_url');
			delete_transient('solo_woocommerce_table_exists');
			wp_safe_redirect(admin_url('admin.php?page=' . $this->plugin_name));
			exit;
		}

		// Deactivate if another plugin is active
		if (is_plugin_active('woo-solo-api/woo-solo-api.php')) {
			deactivate_plugins(__FILE__);
			// Show custom notice
			add_settings_error('solo_woocommerce_postavke', 'plugin_conflict', esc_html__('Solo for WooCommerce je automatski deaktiviran zbog Woo Solo Api dodatka.', 'solo-for-woocommerce'), 'error');
		}

		// Update plugin
		if ($is_plugin_page && isset($_GET['update'])) {
			// Capability check first, then nonce
			if (!current_user_can('update_plugins')) {
				wp_die(esc_html__('Nemaš dozvolu za ažuriranje dodataka.', 'solo-for-woocommerce'));
			}
			if (check_admin_referer('solo_woocommerce_update_nonce')) {
				// Prepare update file to download
				$url = get_transient('solo_woocommerce_url');
				$temp_file = download_url($url);
				if (is_wp_error($temp_file)) {
					wp_die(esc_html($temp_file->get_error_message()));
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
					wp_die(esc_html($result->get_error_message()));
				}

				// Delete temporary file
				wp_delete_file($temp_file);

				// Activate plugin
				activate_plugins(__FILE__);

				// Inform
				solo_woocommerce_inform('update');

				// Show custom notice
				add_settings_error('solo_woocommerce_postavke', 'solo_woocommerce_postavke', esc_html__('Dodatak uspješno ažuriran.', 'solo-for-woocommerce'), 'updated');
			}
		}

		register_setting('solo_woocommerce_postavke', 'solo_woocommerce_postavke', array($this, 'solo_woocommerce_form_validation'));
	}

	//// Validate and save settings form
	function solo_woocommerce_form_validation($data) {
		// Read current settings
		$old_settings = get_option('solo_woocommerce_postavke');
		if (!is_array($old_settings)) $old_settings = array();

		// Nothing submitted - keep current settings
		if (empty($data) || !is_array($data)) return $old_settings;

		// API token validation (trim pasted whitespace first)
		if (isset($data['token'])) {
			$data['token'] = trim($data['token']);
			if (!preg_match('/^[a-zA-Z0-9]{33}$/', $data['token'])) {
				add_settings_error('solo_woocommerce_postavke', 'solo_woocommerce_postavke', esc_html__('API token nije ispravan.', 'solo-for-woocommerce'), 'error');
				// Reject the change and keep current settings
				return $old_settings;
			}
		}

		// Merge submitted fields into current settings
		$settings_data = $old_settings;
		foreach ($data as $key => $value) {
			$settings_data[$key] = sanitize_textarea_field($value);
		}

		// Checkboxes (unchecked checkboxes are not submitted)
		if (!isset($data['posalji'])) $settings_data['posalji'] = 0;

		// Required param for API
		if (empty($data['tip_racuna']) || $data['tip_racuna'] <= 0) $settings_data['tip_racuna'] = 1;

		// KPD validation
		if (isset($data['kpd'])) {
			$settings_data['kpd'] = preg_match('/^\d+\.\d+\.\d+$/', trim($data['kpd'])) ? sanitize_text_field(trim($data['kpd'])) : '';
		}

		// Show custom notice
		add_settings_error('solo_woocommerce_postavke', 'solo_woocommerce_postavke', esc_html__('Postavke uspješno spremljene.', 'solo-for-woocommerce'), 'updated');

		return $settings_data;
	}

	//// Ajax token check
	function solo_woocommerce_check_token() {
		check_ajax_referer('solo_woocommerce_check_token_nonce', 'nonce');
		if (!current_user_can('manage_options')) wp_die(-1);

		$token = sanitize_text_field(wp_unslash($_REQUEST['token'] ?? ''));
		$url = wp_remote_get(SOLO_API_URL . 'licenca?token=' . urlencode($token));

		if (is_wp_error($url)) {
			$response = wp_remote_retrieve_response_code($url) . ': ' . wp_remote_retrieve_response_message($url);
		} else {
			$response = wp_remote_retrieve_body($url);
		}

		echo $response; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		wp_die();
	}

	//// Ajax retry order
	function solo_woocommerce_retry_order() {
		check_ajax_referer('solo_woocommerce_retry_order_nonce', 'nonce');
		if (!current_user_can('manage_options')) wp_die(-1);

		$order_id = intval(isset($_POST['order_id']) ? $_POST['order_id'] : 0);
		if (!$order_id) wp_send_json_error(__('Neispravan broj narudžbe.', 'solo-for-woocommerce'));

		global $wpdb;
		$table_name = $wpdb->prefix . 'solo_woocommerce';

		// Read original request from DB
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$row = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE order_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$order_id
		));

		if (!$row) wp_send_json_error(__('Narudžba ne postoji u arhivi.', 'solo-for-woocommerce'));

		// Never resend a request that already succeeded (prevents duplicate documents)
		$previous = json_decode($row->api_response, true);
		if (isset($previous['status']) && $previous['status'] == 0) wp_send_json_error(__('Dokument za ovu narudžbu je već kreiran u Solo.', 'solo-for-woocommerce'));

		$order = wc_get_order($order_id);
		if (!$order) wp_send_json_error(__('Narudžba više ne postoji u WooCommerceu.', 'solo-for-woocommerce'));

		// Keep the original document type, but rebuild the request from current order data and settings
		$document_type = strpos($row->api_request, SOLO_API_URL . 'ponuda') !== false ? 'ponuda' : 'racun';
		$url = SOLO_API_URL . $document_type;
		$settings = get_option('solo_woocommerce_postavke');
		$payment_map = $this->get_payment_map();
		$built = $this->build_api_request($order, $document_type, $settings, $payment_map[$order->get_payment_method()] ?? 1);
		if (!$built['valid']) wp_send_json_error(__('Nije moguće poslati novi zahtjev (nepodržana valuta ili nedostaje tečaj).', 'solo-for-woocommerce'));

		// Store the rebuilt request, then send it with the same function as automatic sending (response, PDF e-mail, order note)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update($table_name, array('api_request' => 'POST ' . $url . PHP_EOL . $built['request']), array('order_id' => $order_id));
		$result = solo_woocommerce_api_post($url, str_replace(PHP_EOL, '', $built['request']), $order_id, $document_type);

		if ($result['status'] === 0) {
			wp_send_json_success($result['response']);
		} elseif ($result['status'] === 100) {
			wp_send_json_error(__('Solo je trenutno nedostupan, narudžba će automatski biti ponovno poslana za nekoliko sekundi.', 'solo-for-woocommerce'));
		} else {
			wp_send_json_error($result['response']);
		}
	}

	//// Build customer data from order
	private function build_customer_data($order) {
		$kupac_ime = $order->get_billing_first_name();
		$kupac_prezime = $order->get_billing_last_name();
		$kupac_naziv = $kupac_ime . ' ' . $kupac_prezime;
		$r1_checkbox = $order->get_meta('_wc_other/solo-for-woocommerce/r1');
		$naziv_tvrtke = $order->get_meta('_company_name');
		$adresa_tvrtke = $order->get_meta('_company_address');
		$kupac_oib = $order->get_meta('_vat_number');
		// If block checkout, respect the R1 checkbox - clear data if unchecked
		if ($r1_checkbox === '0') {
			$naziv_tvrtke = $adresa_tvrtke = $kupac_oib = '';
		}
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

	//// Determine tip_kupca based on country and R1 data
	private function resolve_tip_kupca($country, $naziv_tvrtke, $adresa_tvrtke, $oib) {
		$is_r1 = !empty($naziv_tvrtke) && !empty($adresa_tvrtke) && !empty($oib);
		if (!$is_r1) return 1;

		$eu_countries = array('AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK');

		if ($country === 'HR') return 2;
		if (in_array($country, $eu_countries)) return 4;
		return 5;
	}

	//// Build order items payload
	private function build_items_payload($order, &$i, $kpd = '', $tip_kupca = 1) {
		$payload = '';

		// Tax rates stored on the order (rate ID => percent)
		$order_rates = array();
		foreach ($order->get_items('tax') as $tax_item) {
			$order_rates[$tax_item->get_rate_id()] = $tax_item->get_rate_percent();
		}

		foreach ($order->get_items() as $item) {
			$i++;
			$item_name = $item->get_name();
			$item_quantity = $item->get_quantity();
			$tax_total = $item->get_subtotal_tax();

			// VAT rate from the order itself, not from the current visitor's location
			$item_tax = 0;
			$item_taxes = $item->get_taxes();
			foreach ($item_taxes['subtotal'] ?? array() as $rate_id => $amount) {
				if ($amount === '') continue;
				// Rate saved on the order; fall back to the rate definition for very old orders
				$item_tax = !empty($order_rates[$rate_id]) ? $order_rates[$rate_id] : WC_Tax::get_rate_percent_value($rate_id);
				break;
			}
			$item_tax = round($item_tax);

			if (!in_array($item_tax, [5, 13, 25]) || $tax_total == 0) $item_tax = 0;

			$item_id = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();
			$product = wc_get_product($item_id);

			if (!$product) continue;

			if ($item->get_variation_id()) {
				$item_name = $product->get_title();
				foreach ($item->get_meta_data() as $meta) {
					// Skip internal meta (keys starting with _), e.g. _reduced_stock - same rule WooCommerce uses on e-mails and order details
					if (empty($meta->id) || '' === $meta->value || !is_scalar($meta->value) || '_' === substr($meta->key, 0, 1)) continue;
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

			$item_price = number_format(round($item->get_subtotal() / $item->get_quantity(), 2), 2, ',', '');
			// Item discount in % (coupons, manual edits in admin, discount plugins): WooCommerce keeps the price before discount in subtotal and after discount in total
			$item_discount = 0;
			if ($item->get_subtotal() > 0 && $item->get_total() < $item->get_subtotal()) {
				$item_discount = number_format(min(100, (1 - $item->get_total() / $item->get_subtotal()) * 100), 4, ',', '');
			}
			$item_quantity = str_replace('.', ',', $item_quantity);

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

	//// Build fees and shipping payload (coupons are already included in item discounts)
	private function build_extras_payload($order, &$i, $kpd = '', $tip_kupca = 1) {
		$payload = '';

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
				if ($tip_kupca == 2) $payload .= '&kpd_' . $i . '=' . urlencode('53.20.09') . PHP_EOL;
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
			if ($tip_kupca == 2) $payload .= '&kpd_' . $i . '=' . urlencode('53.20.09') . PHP_EOL;
			$payload .= '&opis_usluge_' . $i . '=' . urlencode(esc_html__('Poštarina', 'solo-for-woocommerce')) . PHP_EOL;
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
		if ($currency === 'EUR') return array('payload' => '', 'note' => '', 'valid' => true);

		$accepted_currencies = array(
			'AUD' => '2', 'CAD' => '3', 'CZK' => '4',
			'DKK' => '5', 'HUF' => '6', 'JPY' => '7',
			'NOK' => '8', 'SEK' => '9', 'CHF' => '10',
			'GBP' => '11', 'USD' => '12', 'BAM' => '13',
			'EUR' => '14', 'PLN' => '15'
		);

		if (!isset($accepted_currencies[$currency])) return array('payload' => '', 'note' => '', 'valid' => false);

		// Exchange rate is required - never send a foreign currency document without it
		$exchange_rates = json_decode((string) get_option('solo_woocommerce_tecaj'), true);
		if (empty($exchange_rates[$currency])) return array('payload' => '', 'note' => '', 'valid' => false);
		$currency_exchange = str_replace('.', ',', $exchange_rates[$currency]);

		$currency_id = $accepted_currencies[$currency];
		$payload = '&valuta_' . $grammar . '=' . $currency_id . PHP_EOL;
		$payload .= '&tecaj=' . $currency_exchange . PHP_EOL;
		$note = esc_html__('Preračunato po srednjem tečaju HNB-a', 'solo-for-woocommerce') . ' (1 ' . $currency . ' = ' . $currency_exchange . ' EUR)';

		return array('payload' => $payload, 'note' => $note, 'valid' => true);
	}

	//// Check if order already exists in the archive table
	private function order_exists_in_archive($order_id) {
		global $wpdb;
		$table_name = esc_sql($wpdb->prefix . 'solo_woocommerce');
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_var($wpdb->prepare("SELECT order_id FROM {$table_name} WHERE order_id = %d", $order_id));
	}

	//// Build the Solo API request body for an order
	private function build_api_request($order, $document_type, $settings, $nacin_placanja) {
		$token = $settings['token'] ?? '';
		$tip_usluge = isset($settings['tip_usluge']) && is_numeric($settings['tip_usluge']) ? $settings['tip_usluge'] : 1;
		$tip_racuna = !empty($settings['tip_racuna']) ? $settings['tip_racuna'] : 1;
		$rok_placanja = $settings['rok_placanja'] ?? '';
		$iban = $settings['iban'] ?? '';
		$jezik_ = $settings['jezik_'] ?? '';
		$napomene_racun = $settings['napomene_racun'] ?? '';
		$napomene_ponuda = $settings['napomene_ponuda'] ?? '';
		$kpd = $settings['kpd'] ?? '';

		$grammar = $document_type === 'racun' ? 'racuna' : 'ponude';
		$customer = $this->build_customer_data($order);

		$i = 0;
		$api_request = 'token=' . $token . PHP_EOL;
		$api_request .= '&tip_usluge=' . $tip_usluge . PHP_EOL;
		$api_request .= '&tip_kupca=' . $customer['tip_kupca'] . PHP_EOL;
		$api_request .= '&tip_racuna=' . $tip_racuna . PHP_EOL;
		$api_request .= '&kupac_naziv=' . urlencode($customer['naziv']) . PHP_EOL;
		$api_request .= '&kupac_adresa=' . urlencode($customer['adresa']) . PHP_EOL;
		$api_request .= '&kupac_oib=' . urlencode($customer['oib']) . PHP_EOL;
		$api_request .= $this->build_items_payload($order, $i, $kpd, $customer['tip_kupca']);
		$api_request .= $this->build_extras_payload($order, $i, $kpd, $customer['tip_kupca']);
		$api_request .= '&nacin_placanja=' . $nacin_placanja . PHP_EOL;
		if (!empty($rok_placanja)) $api_request .= '&rok_placanja=' . $rok_placanja . PHP_EOL;
		if (!empty($iban)) $api_request .= '&iban=' . $iban . PHP_EOL;

		$currency_data = $this->resolve_currency($order->get_currency(), $grammar);
		if (!$currency_data['valid']) return array('valid' => false);
		$api_request .= $currency_data['payload'];

		// Notes: exchange rate note first, then shop notes - sent as one field, max 1000 characters
		$napomene = $document_type === 'racun' ? $napomene_racun : $napomene_ponuda;
		$napomene = mb_substr(trim($currency_data['note'] . "\n" . $napomene), 0, 1000);
		if (!empty($napomene)) $api_request .= '&napomene=' . urlencode($napomene) . PHP_EOL;
		if (!empty($jezik_)) $api_request .= '&jezik_' . $grammar . '=' . $jezik_ . PHP_EOL;

		return array('valid' => true, 'request' => $api_request);
	}

	//// Save order to archive table and send to Solo API
	private function save_to_archive_and_send($url, $api_request, $order_id, $document_type) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'solo_woocommerce';

		// Ensure table exists (cached check to avoid SHOW TABLES on every order)
		if (!get_transient('solo_woocommerce_table_exists')) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
				solo_woocommerce_create_table();
			} else {
				set_transient('solo_woocommerce_table_exists', 1, DAY_IN_SECONDS);
			}
		}

		// Save order to database
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table_name,
			array(
				'order_id' => $order_id,
				'api_request' => 'POST ' . $url . PHP_EOL . $api_request,
				'created' => current_time('mysql')
			)
		);

		// Send to Solo API and return the result
		return solo_woocommerce_api_post($url, str_replace(PHP_EOL, '', $api_request), $order_id, $document_type);
	}

	//// Shared payment method to nacin_placanja mapping
	private function get_payment_map() {
		return array(
			'bacs' => 1,
			'cod' => 1,
			'stripe' => 3,
			'stripe_sepa' => 1,
			'paypal' => 1,
			'ppec_paypal' => 1,
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
			'worldline-hosted-tokenization' => 3,
			'worldline-apple-pay' => 3,
			'worldline-google-pay' => 3,
			'raiaccept' => 3,
		);
	}

	//// Payment gateway confirmed payment - handled as "kupac uplati"
	public function solo_woocommerce_payment_complete($order_id) {
		$this->solo_woocommerce_process_order($order_id, '', '', true);
	}

	//// Process order before sending to Solo API
	function solo_woocommerce_process_order($order_id, $old_status, $new_status, $payment_complete = false) {
		$order = wc_get_order($order_id);
		if (!$order) return;
		$payment_method = $order->get_payment_method();

		$settings = get_option('solo_woocommerce_postavke');
		$document_type = $trigger = '';
		if (!empty($settings)) {
			$document_type = $settings[$payment_method . '1'] ?? '';
			$trigger = $settings[$payment_method . '2'] ?? '';
		}

		if ($document_type === '' || $trigger === '') return;

		// "primiš narudžbu": order leaves pending/failed (failed = first card attempt declined, virtual orders go straight to completed)
		$is_checkout = ($trigger == 1 && in_array($old_status, ['pending', 'failed']) && in_array($new_status, ['on-hold', 'processing', 'completed']));

		// "kupac uplati": payment gateway confirmed payment, or order marked as completed
		$is_completed = ($trigger == 2 && ($payment_complete || $new_status == 'completed'));

		if (!$is_checkout && !$is_completed) return;

		// Skip if order already exists in archive
		if ($this->order_exists_in_archive($order_id)) return;

		$payment_map = $this->get_payment_map();
		if (!isset($payment_map[$payment_method])) return;

		$url = SOLO_API_URL . $document_type;
		$built = $this->build_api_request($order, $document_type, $settings, $payment_map[$payment_method]);
		if (!$built['valid']) return;

		$this->save_to_archive_and_send($url, $built['request'], $order_id, $document_type);
	}

	//// Product edit page: KPD oznaka field
	public function solo_woocommerce_product_kpd_field() {
		global $post;
		$kpd = get_post_meta($post->ID, '_solo_kpd', true);
		echo '<div class="options_group">';
		echo '<p class="form-field">';
		echo '<label for="kpd">' . esc_html__('KPD oznaka', 'solo-for-woocommerce') . '</label>';
		echo '<input type="text" id="kpd" name="kpd" value="' . esc_attr($kpd) . '" placeholder="' . esc_attr__('npr. 47.71.00', 'solo-for-woocommerce') . '" maxlength="8" class="short" autocomplete="off" />';
		echo wc_help_tip(esc_html__('Ostavi prazno ako želiš koristiti samo zadanu KPD oznaku iz Postavki.', 'solo-for-woocommerce')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</p>';
		echo '</div>';
	}

	//// Product edit page: save KPD oznaka
	public function solo_woocommerce_save_product_kpd($post_id) {
		$kpd = isset($_POST['kpd']) ? sanitize_text_field(wp_unslash($_POST['kpd'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if (preg_match('/^\d{2}\.\d{2}\.\d{2}$/', $kpd) || $kpd === '') {
			update_post_meta($post_id, '_solo_kpd', $kpd);
		}
	}

	//// Products list: show KPD oznaka below SKU
	public function solo_woocommerce_kpd_column_content($column, $post_id) {
		if ($column === 'sku') {
			$kpd = get_post_meta($post_id, '_solo_kpd', true);
			if ($kpd) {
				echo '<br><span style="color:#999;font-size:11px;">KPD: ' . esc_html($kpd) . '</span>';
			}
		}
	}

	//// Fetch latest version from GitHub
	public function solo_woocommerce_fetch_github_version() {
		$tag = get_transient('solo_woocommerce_tag');
		if ($tag) return $tag;

		$json = wp_remote_get('https://api.github.com/repos/coax/solo-for-woocommerce/releases',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept' => 'application/json'
				)
			)
		);
		if (!is_wp_error($json)) {
			$decoded_json = json_decode(wp_remote_retrieve_body($json), true);
			if (isset($decoded_json[0]['name'])) {
				$tag = ltrim($decoded_json[0]['name'], 'v');
				$url = $decoded_json[0]['assets'][0]['browser_download_url'] ?? '';
				set_transient('solo_woocommerce_tag', $tag, DAY_IN_SECONDS);
				set_transient('solo_woocommerce_url', $url, DAY_IN_SECONDS);
			}
		}
		return $tag ?: '';
	}

	//// Render update notice HTML
	public function solo_woocommerce_update_notice($tag) {
		echo '<p>' . esc_html__('Dostupna je nova verzija dodatka', 'solo-for-woocommerce') . ': <a href="https://github.com/coax/solo-for-woocommerce/releases/tag/v' . esc_html($tag) . '" target="_blank">Solo for WooCommerce ' . esc_html($tag) . '</a></p>';
		echo '<p><a href="' . esc_url(wp_nonce_url('admin.php?page=' . $this->plugin_name . '&update=true', 'solo_woocommerce_update_nonce')) . '" class="button button-small button-primary">' . esc_html__('Instaliraj novu verziju', 'solo-for-woocommerce') . '</a></p>';
	}

	//// WP dashboard update check
	public function solo_woocommerce_dashboard_widget() {
		$tag = ltrim($this->solo_woocommerce_fetch_github_version(), 'v');
		if (!$tag || !version_compare(SOLO_VERSION, $tag, '<') || !current_user_can('update_plugins')) return;
		wp_add_dashboard_widget(
			'solo_woocommerce_widget',
			__('Solo for WooCommerce', 'solo-for-woocommerce'),
			array($this, 'solo_woocommerce_dashboard_widget_content')
		);
	}

	public function solo_woocommerce_dashboard_widget_content() {
		$tag = ltrim(get_transient('solo_woocommerce_tag'), 'v');
		$this->solo_woocommerce_update_notice($tag);
	}
}
$solo_woocommerce = new solo_woocommerce;