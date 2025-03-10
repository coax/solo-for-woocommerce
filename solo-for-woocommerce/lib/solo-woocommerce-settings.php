<?php
/**
 * Plugin Name: Solo for WooCommerce
 * Plugin URI: https://solo.com.hr/api-dokumentacija/dodaci
 * Description: Narudžba u tvojoj WooCommerce trgovini će automatski kreirati račun ili ponudu u servisu Solo.
 * Version: 1.9
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

// Version check
$version_check = get_transient('solo_tag');
$tag = $installed = SOLO_VERSION;

if (($installed!=$version_check) && (!isset($_GET['update']))) {
	// Read transient if exists
	$tag = ltrim(get_transient('solo_tag'), 'v');

	// Transient not found, fetch latest version from GitHub
	if (!$version_check) {
		$json = wp_remote_get('https://api.github.com/repos/coax/solo-for-woocommerce/releases',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept' => 'application/json'
				)
			)
		);
		$decoded_json = json_decode($json['body'], true);
		if (isset($decoded_json[0]['name'])) {
			$tag = ltrim($decoded_json[0]['name'], 'v');
			$url = $decoded_json[0]['assets'][0]['browser_download_url'];

			// Create temporary transients (instead session)
			set_transient('solo_tag', $tag, 60*60*24);
			set_transient('solo_url', $url, 60*60*24);
		}
	}

	// Display notice
	if (version_compare($installed, $tag, '<')) {
?>
      <div class="notice notice-info notice-alt"><p><?php echo __('Dostupna je nova verzija dodatka', 'solo-for-woocommerce'); ?>: <a href="https://github.com/coax/solo-for-woocommerce/releases" target="_blank">Solo for WooCommerce <?php echo $tag; ?></a></p><p><a href="<?php echo wp_nonce_url('?page=solo-woocommerce&update=true', 'solo_woocommerce_update_nonce'); ?>" class="button button-small button-primary"><?php echo __('Instaliraj novu verziju', 'solo-for-woocommerce'); ?></a></p></div>
<?php
	}
}

// Tabs
$default_tab = null;
$tab = isset($_GET['tab']) ? $_GET['tab'] : $default_tab;

// Init main class
$solo_woocommerce = new solo_woocommerce;

// Define default variables
$token = $tip_usluge = $jezik_ = $prikazi_porez = $tip_racuna = $rok_placanja = $napomene_racun = $napomene_ponuda = $iban = $akcija = $posalji = $naslov = $poruka = '';

// Create variables from settings
$settings = get_option('solo_woocommerce_postavke');
if (!empty($settings)) {
	foreach ($settings as $key => $option) {
		${$key} = $option;
	}
}
?>
<div class="wrap">
  <form action="options.php" method="post">
    <?php settings_fields('solo_woocommerce_postavke'); ?>
    <h1><div class="solo-logo"></div><?php echo esc_html(get_admin_page_title()); ?></h1>
    <p><?php echo __('Narudžba u tvojoj WooCommerce trgovini će automatski kreirati račun ili ponudu u servisu Solo.', 'solo-for-woocommerce'); ?></p>
    <nav class="nav-tab-wrapper">
      <a href="?page=solo-woocommerce" class="nav-tab <?php if($tab===null):?>nav-tab-active<?php endif; ?>">API token</a>
<?php if ($token) { ?>
      <a href="?page=solo-woocommerce&tab=postavke" class="nav-tab<?php if($tab==='postavke'):?> nav-tab-active<?php endif; ?>"><?php echo __('Solo postavke', 'solo-for-woocommerce'); ?></a>
      <a href="?page=solo-woocommerce&tab=akcije" class="nav-tab<?php if($tab==='akcije'):?> nav-tab-active<?php endif; ?>"><?php echo __('Načini plaćanja i akcije', 'solo-for-woocommerce'); ?></a>
      <a href="?page=solo-woocommerce&tab=email" class="nav-tab<?php if($tab==='email'):?> nav-tab-active<?php endif; ?>"><?php echo __('E-mail postavke', 'solo-for-woocommerce'); ?></a>
      <a href="?page=solo-woocommerce&tab=tecaj" class="nav-tab<?php if($tab==='tecaj'):?> nav-tab-active<?php endif; ?>"><?php echo __('Tečajna lista', 'solo-for-woocommerce'); ?></a>
      <a href="?page=solo-woocommerce&tab=arhiva" class="nav-tab<?php if($tab==='arhiva'):?> nav-tab-active<?php endif; ?>"><?php echo __('Arhiva', 'solo-for-woocommerce'); ?></a>
      <a href="?page=solo-woocommerce&tab=podrska" class="nav-tab<?php if($tab==='podrska'):?> nav-tab-active<?php endif; ?>"><?php echo __('Podrška', 'solo-for-woocommerce'); ?></a>
<?php } ?>
    </nav>
    <div class="tab-content">
<?php
// API token missing
if (!$token) $tab = $default_tab;

switch($tab):
	default:
?>
      <input type="hidden" name="solo_woocommerce_postavke[prikazi_porez]" value="<?php echo $prikazi_porez ?>">
      <input type="hidden" name="solo_woocommerce_postavke[tip_racuna]" value="<?php echo $tip_racuna ?>">
      <input type="hidden" name="solo_woocommerce_postavke[posalji]" value="<?php echo $posalji ?>">
      <table class="form-table">
        <tbody>
          <tr>
            <th>
              <label for="token"><?php echo __('API token', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo __('Upiši svoj API token. Token ćeš pronaći u web servisu klikom na Postavke.', 'solo-for-woocommerce'); ?>"></sup></label>
            </th>
            <td class="mailserver-pass-wrap">
              <span class="wp-pwd">
                <input type="password" name="solo_woocommerce_postavke[token]" id="token" value="<?php echo $token; ?>" autocorrect="off" autocomplete="off" maxlength="33" placeholder="" class="regular-text" class="mailserver-pass-wrap">
                <button type="button" class="button wp-hide-pw hide-if-no-js" id="toggle"><span class="dashicons dashicons-visibility"></span></button>
              </span>
              <p class="description"><?php if ($token==''): ?><?php echo __('Upiši i spremi promjene kako bi se prikazale ostale opcije.', 'solo-for-woocommerce'); ?><?php else: ?><a href="#" class="provjera"><?php echo __('Provjeri valjanost tokena', 'solo-for-woocommerce'); ?></a><?php endif; ?></p>
            </td>
          </tr>
        </tbody>
      </table>
      <?php submit_button(__('Spremi promjene', 'solo-for-woocommerce')); ?>
<?php
		break;

	case 'postavke':
?>
      <input type="hidden" name="solo_woocommerce_postavke[posalji]" value="<?php echo $posalji ?>">
      <table class="form-table">
        <tbody>
          <tr>
            <th>
              <label for="tip_usluge"><?php echo __('Tip usluge', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo __('Upiši redni broj glavnog tipa usluge iz web sučelja > Usluge > Tipovi usluga.<br>Koristi se samo za generiranje poziva na broj.', 'solo-for-woocommerce'); ?>"></sup></label>
            </th>
            <td>
              <input type="text" name="solo_woocommerce_postavke[tip_usluge]" id="tip_usluge" value="<?php echo $tip_usluge; ?>" autocorrect="off" autocomplete="off" maxlength="2" placeholder="" class="small-text int">
              <p class="description"><?php echo __('Nije obavezno upisati.', 'solo-for-woocommerce'); ?></p>
            </td>
          </tr>
          <tr>
            <th><label for="jezik_"><?php echo __('Jezik', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo __('Odaberi jezik na kojem želiš kreirati račun ili ponudu.', 'solo-for-woocommerce'); ?>"></sup></label></th>
            <td>
              <select name="solo_woocommerce_postavke[jezik_]">
<?php
		$languages = [__('Hrvatski', 'solo-for-woocommerce') => 1, __('Engleski', 'solo-for-woocommerce') => 2, __('Njemački', 'solo-for-woocommerce') => 3, __('Francuski', 'solo-for-woocommerce') => 4, __('Talijanski', 'solo-for-woocommerce') => 5, __('Španjolski', 'solo-for-woocommerce') => 6];

		foreach ($languages as $key => $value) {
			echo '<option value="' . $value . '"' . (($jezik_==$value) ? ' selected' : '') . '>' . $key . '</option>';
		}
?>
              </select>
            </td>
          </tr>
          <tr>
            <th>
              <label for="prikazi_porez"><?php echo __('Prikaži porez', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo __('Uključi ako želiš prikazati PDV na računu ili ponudi.', 'solo-for-woocommerce'); ?>"></sup></label>
            </th>
            <td>
              <fieldset>
                <label for="prikazi_porez"><input type="checkbox" name="solo_woocommerce_postavke[prikazi_porez]" id="prikazi_porez" value="1"<?php if ($prikazi_porez==1) echo ' checked="checked"' ?>> <?php echo __('Da', 'solo-for-woocommerce'); ?></label>
                <p class="description"><?php echo __('Obavezno uključi ako si u sustavu PDV-a.', 'solo-for-woocommerce'); ?></p>
              </fieldset>
            </td>
          </tr>
          <tr>
            <th>
              <label for="tip_racuna"><?php echo __('Tip računa', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo __('Odaberi zadani tip računa.', 'solo-for-woocommerce'); ?>">?</sup></label>
            </th>
            <td>
              <select name="solo_woocommerce_postavke[tip_racuna]">
<?php
		$types = ['R' => 1, 'R1' => 2, 'R2' => 3, __('bez oznake', 'solo-for-woocommerce') => 4, __('Avansni', 'solo-for-woocommerce') => 5];

		foreach ($types as $key => $value) {
			echo '<option value="' . $value . '"' . (($tip_racuna==$value) ? ' selected' : '') . '>' . $key . '</option>';
		}
?>
              </select>
              <p class="description"><?php echo __('Odnosi se samo na račune. Ponude nemaju tipove.', 'solo-for-woocommerce'); ?></p>
            </td>
          </tr>
          <tr>
            <th>
              <label for="rok_placanja"><?php echo __('Rok plaćanja', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo __('Upiši broj dana koji se dodaje na datum izrade računa ili ponude, a do kojeg kupac treba platiti.<br>Ako nije upisano, Solo će staviti zadani broj dana za rok plaćanja (7) ili će kopirati s prethodnog računa ili ponude.', 'solo-for-woocommerce'); ?>"></sup></label>
            </th>
            <td>
              <input type="text" name="solo_woocommerce_postavke[rok_placanja]" id="rok_placanja" value="<?php echo $rok_placanja; ?>" autocorrect="off" autocomplete="off" maxlength="2" placeholder="" class="small-text int">
              <p class="description"><?php echo __('Nije obavezno upisati.', 'solo-for-woocommerce'); ?></p>
            </td>
          </tr>
          <tr>
            <th><label for="napomene_racun"><?php echo __('Napomene na računu', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo __('Upiši napomene koje će se pojaviti na svakom računu.<br>Solo prihvaća do najviše 1000 znakova.', 'solo-for-woocommerce'); ?>"></sup></label></th>
            <td>
              <textarea name="solo_woocommerce_postavke[napomene_racun]" id="napomene_racun" rows="2" maxlength="1000" class="large-text"><?php echo $napomene_racun; ?></textarea>
              <p class="description"><?php echo __('Nije obavezno upisati.', 'solo-for-woocommerce'); ?></p>
            </td>
          </tr>
          <tr>
            <th><label for="napomene_ponuda"><?php echo __('Napomene na ponudi', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo __('Upiši napomene koje će se pojaviti na svakoj ponudi.<br>Solo prihvaća do najviše 1000 znakova.', 'solo-for-woocommerce'); ?>"></sup></label></th>
            <td>
              <textarea name="solo_woocommerce_postavke[napomene_ponuda]" id="napomene_ponuda" rows="2" maxlength="1000" class="large-text"><?php echo $napomene_ponuda; ?></textarea>
              <p class="description"><?php echo __('Nije obavezno upisati.', 'solo-for-woocommerce'); ?></p>
            </td>
          </tr>
          <tr>
            <th><label for="iban"><?php echo __('IBAN za uplatu', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo __('Odaberi IBAN (tvoj žiro račun) koji će se pojaviti na računu ili ponudi.<br>IBAN možeš mijenjati u web sučelju > Postavke > Moja tvrtka.', 'solo-for-woocommerce'); ?>"></sup></label></th>
            <td>
              <select name="solo_woocommerce_postavke[iban]">
<?php
		$ibans = [__('Glavni IBAN', 'solo-for-woocommerce') => 1, __('Drugi IBAN (ako postoji)', 'solo-for-woocommerce') => 2];

		foreach ($ibans as $key => $value) {
			echo '<option value="' . $value . '"' . (($iban==$value) ? ' selected' : '') . '>' . $key . '</option>';
		}
?>
              </select>
            </td>
          </tr>
        </tbody>
      </table>
      <?php submit_button(__('Spremi promjene', 'solo-for-woocommerce')); ?>
<?php
		break;

	case 'akcije':
?>
      <input type="hidden" name="solo_woocommerce_postavke[prikazi_porez]" value="<?php echo $prikazi_porez ?>">
      <input type="hidden" name="solo_woocommerce_postavke[tip_racuna]" value="<?php echo $tip_racuna ?>">
      <input type="hidden" name="solo_woocommerce_postavke[posalji]" value="<?php echo $posalji ?>">
<?php
		// Show enabled WooCommerce gateways
		$gateways = WC()->payment_gateways->payment_gateways();
		$available_gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ($gateways) {
?>
      <p><?php echo __('Namjesti postavke kreiranja računa ili ponude za svaki od prikazanih <a href="admin.php?page=wc-settings&tab=checkout" target="_blank">načina plaćanja</a>.', 'solo-for-woocommerce'); ?></p>
<?php
			foreach ($gateways as $gateway) {
				$gateway_id = $gateway->id;
				$gateway_title = $gateway->title;
				$gateway_description = $gateway->method_description;

				// Mark active payment methods
				$color = '';
				if (is_array($available_gateways)) {
					foreach ($available_gateways as $available_gateway) {
						if ($gateway_id === $available_gateway->id) {
							$color = ' notice-alt notice-success';
							break;
						}
					}
				}

				// Beautify gateway names
				$translations = array(
					'bacs' => __('Uplata na žiro račun', 'solo-for-woocommerce'),
					'cheque' => __('Plaćanje čekom (fiskalizacija)', 'solo-for-woocommerce'),
					'cod' => __('Plaćanje pri pouzeću', 'solo-for-woocommerce'),
					'stripe' => __('Stripe (kartice, fiskalizacija)', 'solo-for-woocommerce'),
					'stripe_sepa' => __('Stripe SEPA uplata', 'solo-for-woocommerce'),
					'braintree_credit_card' => __('Braintree (kartice, fiskalizacija)', 'solo-for-woocommerce'),
					'braintree_paypal' => __('Braintree (PayPal)', 'solo-for-woocommerce'),
					'paypal' => __('PayPal', 'solo-for-woocommerce'),
					'ppec_paypal' => __('PayPal', 'solo-for-woocommerce'),
					'ppcp-gateway' => __('PayPal', 'solo-for-woocommerce'),
					'corvuspay' => __('CorvusPay (kartice, fiskalizacija)', 'solo-for-woocommerce'),
					'monri' => __('Monri (kartice, fiskalizacija)', 'solo-for-woocommerce'),
					'mypos_virtual' => __('myPOS (kartice, fiskalizacija)', 'solo-for-woocommerce'),
					'wooplatnica-croatia' => __('Uplatnica', 'solo-for-woocommerce'),
					'erste-kekspay-woocommerce' => __('KEKS Pay', 'solo-for-woocommerce'),
					'eh_paypal_express' => __('PayPal Express (kartice, fiskalizacija)', 'solo-for-woocommerce'),
					'revolut_cc' => __('Revolut (kartice, fiskalizacija)', 'solo-for-woocommerce'),
					'aircash-woocommerce' => __('Aircash (kartice, fiskalizacija)', 'solo-for-woocommerce')
				);

				// Show only available payments
				if (isset($translations[$gateway_id])) {
					$gateway_title = $translations[$gateway_id];

					// Dynamic variable error handling
					if (isset(${$gateway_id . '1'})) {
						$dynamic_var1 = ${$gateway_id . '1'};
						$dynamic_var2 = ${$gateway_id . '2'};
					} else {
						$dynamic_var1 = $dynamic_var2 = '';
					}
?>
      <div class="card<?php echo $color; ?>">
        <h3><a href="admin.php?page=wc-settings&tab=checkout&section=<?php echo esc_attr($gateway_id); ?>" target="_blank"><?php echo $gateway_title; ?></a></h3>
        <p><?php echo $gateway_description; ?></p>
        <hr>
        <label for="<?php echo esc_attr($gateway_id); ?>"><?php echo __('Automatski kreiraj', 'solo-for-woocommerce'); ?></label>
        <select name="solo_woocommerce_postavke[<?php echo esc_attr($gateway_id); ?>1]" id="<?php echo esc_attr($gateway_id); ?>">
<?php
					$types = [__('ništa', 'solo-for-woocommerce') => '', __('račun', 'solo-for-woocommerce') => 'racun', __('ponudu', 'solo-for-woocommerce') => 'ponuda'];

					foreach ($types as $key => $value) {
						echo '<option value="' . $value . '"' . (($dynamic_var1==$value) ? ' selected' : '') . '>' . $key . '</option>';
					}
?>
        </select>
        <label for="<?php echo esc_attr($gateway_id); ?>"><?php echo __('kada', 'solo-for-woocommerce'); ?></label>
        <select name="solo_woocommerce_postavke[<?php echo esc_attr($gateway_id); ?>2]" id="<?php echo esc_attr($gateway_id); ?>">
<?php
					$actions = ["primiš narudžbu (bez uplate)" => 1, "kupac uplati" => 2];

					foreach ($actions as $key => $value) {
						echo '<option value="' . $value . '"' . (($dynamic_var2==$value) ? ' selected' : '') . '>' . $key . '</option>';
					}
?>
        </select>
      </div>
<?php
				}
			}
?>
      <br>
      <div class="notice notice-info inline">
        <p><?php echo __('Akcija <b>"primiš narudžbu (bez uplate)"</b> se izvršava čim kupac napravi narudžbu neovisno o tipu plaćanja. Takve narudžbe će imati status <span class="status processing">Processing / U obradi</span> ili <span class="status on-hold">On hold / Na čekanju</span> u WooCommerce popisu narudžbi.', 'solo-for-woocommerce'); ?></p>
        <p><?php echo __('Akcija <b>"kupac uplati"</b> se izvršava kada narudžbu obilježiš kao <span class="status completed">Completed / Završeno</span> u WooCommerce popisu narudžbi ili kada naplata karticom bude uspješna (trebalo bi automatski promijeniti status).', 'solo-for-woocommerce'); ?></p>
      </div>
      <?php submit_button(__('Spremi promjene', 'solo-for-woocommerce')); ?>
<?php
		} else {
?>
      <br>
      <div class="notice notice-error inline"><p><?php echo __('Prvo uključi barem jedan način plaćanja u <a href="admin.php?page=wc-settings&tab=checkout" target="_blank">WooCommerce postavkama</a>.', 'solo-for-woocommerce'); ?></p></div>
<?php
		}
		break;

	case 'email':

		// Cron check
		if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
?>
      <br>
      <div class="notice notice-error inline"><p><?php echo __('Za automatsko slanje računa ili ponude na e-mail, potrebno je izbrisati <code>define(\'DISABLE_WP_CRON\', true);</code> iz <i>wp-config.php</i> datoteke.', 'solo-for-woocommerce'); ?></p></div>
<?php
		} else {
			$known_smtp_plugins = array(
				// WP Mail SMTP
				'wp-mail-smtp/wp_mail_smtp.php',
				// Post SMTP
				'post-smtp/postman-smtp.php',
				// Easy WP SMTP
				'easy-wp-smtp/easy-wp-smtp.php',
				// FluentSMTP
				'fluent-smtp/fluent-smtp.php',
				// SureMail
				'suremails/suremails.php',
				// SMTP Mailer
				'smtp-mailer/main.php',
				// WP SMTP Mailer - SMTP7
				'wp-mail-smtp-mailer/wp-mail-smtp-mailer.php',
				// YaySMTP and Email Logs
				'yaysmtp/yay-smtp.php',
				// Site Mailer
				'site-mailer/site-mailer.php',
				// SMTP by BestWebSoft
				'bws-smtp/bws-smtp.php',
				// Swift SMTP (formerly Welcome Email Editor)
				'welcome-email-editor/sb_welcome_email_editor.php',
				// Configure SMTP
				'configure-smtp/configure-smtp.php',
				// Bit SMTP
				'bit-smtp/bit_smtp.php',
			);

			// Retrieve the list of active plugins
			$active_plugins = (array) get_option('active_plugins', array());

			// Optionally include network activated plugins on multisite installs
			if (is_multisite()) {
				$active_plugins = array_merge($active_plugins, array_keys(get_site_option('active_sitewide_plugins', array())));
			}

			$smtp_plugin_active = false;

			foreach ($known_smtp_plugins as $plugin_file) {
				if (in_array($plugin_file, $active_plugins, true)) {
					$smtp_plugin_active = true;
					break;
				}
			}

			if (!$smtp_plugin_active) {
?>
      <br>
      <div class="notice notice-error inline"><p><?php echo __('Potrebno je instalirati (i aktivirati) SMTP dodatak za WordPress (npr. <a href="https://wordpress.org/plugins/smtp-mailer/" target="_blank">SMTP Mailer</a>) kako bi se račun ili ponuda automatski poslali kupcu na e-mail.', 'solo-for-woocommerce'); ?></p></div>
<?php
			}
?>
      <input type="hidden" name="solo_woocommerce_postavke[prikazi_porez]" value="<?php echo $prikazi_porez ?>">
      <input type="hidden" name="solo_woocommerce_postavke[tip_racuna]" value="<?php echo $tip_racuna ?>">
      <table class="form-table">
        <tbody>
          <tr>
            <th><label for="posalji"><?php echo __('Automatsko slanje', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo __('Uključi ako želiš da se račun ili ponuda automatski pošalju e-mailom kupcu nakon uspješne kupnje ili narudžbe.', 'solo-for-woocommerce'); ?>"></sup></label></th>
            <td>
              <fieldset>
                <label for="posalji"><input type="checkbox" name="solo_woocommerce_postavke[posalji]" id="posalji" value="1"<?php if ($posalji==1) echo ' checked="checked"' ?>> <?php echo __('Da', 'solo-for-woocommerce'); ?></label>
              </fieldset>
            </td>
          </tr>
          <tr>
            <th><label for="naslov"><?php echo __('Naslov poruke', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo __('Upiši naslov e-mail poruke koju će kupac dobiti.', 'solo-for-woocommerce'); ?>"></sup></label></th>
            <td>
              <input type="text" name="solo_woocommerce_postavke[naslov]" id="naslov" value="<?php echo $naslov; ?>" autocorrect="off" autocomplete="off" maxlength="100" placeholder="" class="regular-text">
            </td>
          </tr>
          <tr>
            <th><label for="poruka"><?php echo __('Sadržaj poruke', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo __('Upiši sadržaj e-mail poruke koju će kupac dobiti.<br>HTML formatiranje nije podržano.', 'solo-for-woocommerce'); ?>"></sup></label></th>
            <td>
              <textarea name="solo_woocommerce_postavke[poruka]" id="poruka" rows="8" class="large-text"><?php echo $poruka; ?></textarea>
              <p class="description">*<?php echo __('PDF kopija dokumenta će automatski biti u privitku', 'solo-for-woocommerce'); ?></p>
            </td>
          </tr>
        </tbody>
      </table>
      <?php submit_button(__('Spremi promjene', 'solo-for-woocommerce')); ?>
<?php
		}

		break;

	case 'tecaj':

		// Display exchange rate
		solo_woocommerce_exchange(3);

		break;

	case 'arhiva':

		// Check for table in database
		global $wpdb;
		$table_name = $wpdb->prefix . 'solo_woocommerce';
		if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'")!=$table_name) {
			// Create table if doesn't exist
			solo_woocommerce_create_table();
		}

		// Read from database
		$results = $wpdb->get_results(
			"SELECT * FROM $table_name ORDER BY id DESC"
		);

		if (array_filter($results)) {
?>
      <p><?php echo __('Prikazane su sve narudžbe koje je WooCommerce poslao u servis Solo. Imaj na umu da WooCommerce šalje samo narudžbe za koje je u <a href="?page=solo-woocommerce&tab=akcije">"Načini plaćanja i akcije"</a> omogućeno kreiranje dokumenta.', 'solo-for-woocommerce'); ?></p>
      <table class="widefat fixed striped" id="arhiva">
        <colgroup>
          <col style="width:9%;">
          <col style="width:29%;">
          <col style="width:29%;">
          <col style="width:11%;">
          <col style="width:11%;">
          <col style="width:11%;">
        </colgroup>
        <thead>
          <tr>
            <th data-sortas="numeric"><?php echo __('Narudžba', 'solo-for-woocommerce'); ?></th>
            <th data-sortas="case-insensitive"><?php echo __('API zahtjev', 'solo-for-woocommerce'); ?></th>
            <th data-sortas="case-insensitive"><?php echo __('API odgovor', 'solo-for-woocommerce'); ?></th>
            <th data-sortas="datetime"><?php echo __('Datum zahtjeva', 'solo-for-woocommerce'); ?></th>
            <th data-sortas="datetime"><?php echo __('Datum odgovora', 'solo-for-woocommerce'); ?></th>
            <th data-sortas="datetime"><?php echo __('Datum slanja', 'solo-for-woocommerce'); ?></th>
          </tr>
        </thead>
        <tbody>
<?php
			function timeago($datetime) {
				$seconds_ago = (time() - strtotime($datetime . ' Europe/Zagreb'));
				$prefix = 'Prije ';
				$when = $suffix = '';
				if ($seconds_ago >= 31536000) {
					return;
				} elseif ($seconds_ago>=2419200) {
					return;
				} elseif ($seconds_ago>=86400) {
					return;
				} elseif ($seconds_ago>=3600) {
					$when = intval($seconds_ago / 3600);
					if ($when==1) {
						$suffix = ' sat';
					} elseif ($when>1 && $when<5) {
						$suffix = ' sata';
					} else {
						$suffix = ' sati';
					}
				} elseif ($seconds_ago>=120) {
					$when = intval($seconds_ago / 60);
					if ($when==1) {
						$suffix = ' minutu';
					} elseif ($when>1 && $when<5) {
						$suffix = ' minute';
					} else {
						$suffix = ' minuta';
					}
				} elseif ($seconds_ago>=60) {
					$prefix = 'Prije minutu';
				} elseif ($seconds_ago>=0) {
					$prefix = 'Upravo sada';
				} else {
					return;
				}
				return $prefix . $when . $suffix;
			}

			foreach($results as $row) {
				$api_request = $row->api_request;
				$api_request = preg_replace('/token=[a-zA-Z0-9]{29}/', 'token=*****************************', $api_request);
				$api_request = nl2br($api_request);
				$api_response = $row->api_response;
				//$api_response = str_replace(' ', '&nbsp;', $api_response);
				$api_response = nl2br($api_response);
				$created = $row->created;
				$updated = $row->updated;
				if (!$updated || $updated=='0000-00-00 00:00:00') $updated = '&ndash;';
				$sent = $row->sent;
				if (!$sent || $sent=='0000-00-00 00:00:00') $sent = '&ndash;';
?>
          <tr class="shrink">
            <td data-sortvalue="<?php echo $row->order_id; ?>"><p><a href="post.php?post=<?php echo $row->order_id; ?>&action=edit"><?php echo $row->order_id; ?></a></p></td>
            <td><p><?php echo $api_request; ?></p></td>
            <td><p><?php echo $api_response; ?></p></td>
            <td data-sortvalue="<?php echo $created; ?>"><p><?php echo $created . '<br>' . timeago($created); ?></p></td>
            <td data-sortvalue="<?php echo $updated; ?>"><p><?php echo $updated . '<br>' . timeago($updated); ?></p></td>
            <td data-sortvalue="<?php echo $sent; ?>"><p><?php echo $sent . '<br>' . timeago($sent); ?></p></td>
          </tr>
<?php
				}
?>
        </tbody>
      </table>
<?php
		} else {
?>
      <br><div class="notice notice-warning inline"><p><?php echo __('Još niti jedna narudžba nije poslana u Solo.', 'solo-for-woocommerce'); ?></p></div>
<?php
		}
		break;

	case 'podrska':
?>
      <p><?php echo __('Tehnička podrška za ovaj dodatak nalazi se na <a href="https://github.com/coax/solo-for-woocommerce#podrška" target="_blank">GitHub stranicama</a>.', 'solo-for-woocommerce'); ?></p>
      <p><?php echo __('Imaš instaliranu verziju', 'solo-for-woocommerce'); ?> <b><?php echo SOLO_VERSION; ?></b></p>
<?php
		// Path to wp-config.php
		$wp_config_file = ABSPATH . 'wp-config.php';

		// Read wp-config.php file contents
		$config_contents = file_get_contents($wp_config_file);

		// Check if both WP_DEBUG and WP_DEBUG_LOG are enabled
		$is_wp_debug_enabled = strpos($config_contents, "define( 'WP_DEBUG', true );") !== false;
		$is_wp_debug_log_enabled = strpos($config_contents, "define( 'WP_DEBUG_LOG', true );") !== false;

		// Path to debug.log
		$log_file = WP_CONTENT_DIR . '/debug.log';

		// Logging disabled
		if (!$is_wp_debug_enabled || !$is_wp_debug_log_enabled) {
?>
      <div class="notice notice-info inline">
        <p><?php echo __('Ako imaš problema s dodatkom, omogući <i>debugiranje</i> u WordPressu za dobivanje informacija o tome gdje i kako dolazi do greške. U <i>wp-config.php</i> datoteku dodaj ove linije:<br><code>define(\'WP_DEBUG\', true);</code><br><code>define(\'WP_DEBUG_LOG\', true);</code><br><code>define(\'WP_DEBUG_DISPLAY\', false);</code>', 'solo-for-woocommerce'); ?></p>
      </div>
<?php
		} else {
			if (file_exists($log_file)) {
				$filtered_lines = array();

				// Parse log
				$file_handle = fopen($log_file, 'r');
				if ($file_handle) {
					while (($line = fgets($file_handle)) !== false) {
						// Check if line starts with a date and time (format: [YYYY-MM-DD HH:MM:SS])
						if (preg_match('/^\[\d{2}-\w{3}-\d{4}(?: \d{2}:\d{2}:\d{2})?/', $line, $matches)) {
							if (strpos($line, $this->plugin_name) !== false) {
								$filtered_lines[] = $line;
							}
						}
					}
					fclose($file_handle);
				}

				// Display filtered lines
				if (!empty($filtered_lines)) {
?>
      <p><?php echo sprintf(__('Greške iz <a href="%s" target="_blank">debug.log</a> datoteke:', 'solo-for-woocommerce'), esc_url(content_url('debug.log'))); ?></p>
<?php
					// Sort descending
					rsort($filtered_lines);

					// Limit 50 logs
					$filtered_lines = array_slice($filtered_lines, 0, 50);

					foreach ($filtered_lines as $filtered_line) {
						// Extract date and time
						preg_match('/\[(.*?)\]/', $filtered_line, $matches);
						$date_time = isset($matches[1]) ? $matches[1] : '&ndash;';

						// Convert to Y-m-d H:i:s format
						try {
							$date = new DateTime($date_time);
							 // Get WordPress timezone
							$local_timezone = new DateTimeZone(wp_timezone_string());
							$date->setTimezone($local_timezone);
							$formatted_date_time = $date->format('Y-m-d H:i:s');
						} catch (Exception $e) {
							$formatted_date_time = '&ndash;';
						}

						// Extract error message (everything after date and time)
						$error_message = trim(str_replace($matches[0], '', $filtered_line));

						echo '<code>[' . esc_html($formatted_date_time) . '] ' . esc_html($error_message) . '</code><br>' . PHP_EOL;
					}
				}
			}
		}

		break;
endswitch;
?>
    </div>
  </form>
</div>