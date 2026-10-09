<?php
// Disallow direct call to this file
if (!defined('WPINC')) {
	die;
}

// Timeago dates
function solo_woocommerce_timeago($datetime) {
	if (empty($datetime) || $datetime === '0000-00-00 00:00:00') return '';

	try {
		// Stored dates use the site's timezone (current_time('mysql'))
		$dt = new DateTime($datetime, wp_timezone());
	} catch (Exception $e) {
		return '';
	}

	$seconds_ago = time() - $dt->getTimestamp();

	// Future dates, or older than a day - don't show relative time
	if ($seconds_ago < 0 || $seconds_ago >= 86400) return '';

	if ($seconds_ago >= 3600) {
		$when = intval($seconds_ago / 3600);
		$form = solo_woocommerce_plural_form($when);
		// translators: %d is the number of hours
		if ($form === 0) return sprintf(__('Prije %d sat', 'solo-for-woocommerce'), $when);
		// translators: %d is the number of hours
		if ($form === 1) return sprintf(__('Prije %d sata', 'solo-for-woocommerce'), $when);
		// translators: %d is the number of hours
		return sprintf(__('Prije %d sati', 'solo-for-woocommerce'), $when);
	}

	if ($seconds_ago >= 120) {
		$when = intval($seconds_ago / 60);
		$form = solo_woocommerce_plural_form($when);
		// translators: %d is the number of minutes
		if ($form === 0) return sprintf(__('Prije %d minutu', 'solo-for-woocommerce'), $when);
		// translators: %d is the number of minutes
		if ($form === 1) return sprintf(__('Prije %d minute', 'solo-for-woocommerce'), $when);
		// translators: %d is the number of minutes
		return sprintf(__('Prije %d minuta', 'solo-for-woocommerce'), $when);
	}

	if ($seconds_ago >= 60) return __('Prije minutu', 'solo-for-woocommerce');

	return __('Upravo sada', 'solo-for-woocommerce');
}

// Croatian plural form: 0 = 1, 21, 31... / 1 = 2-4, 22-24... / 2 = everything else (5-20, 25-30...)
function solo_woocommerce_plural_form($n) {
	if ($n % 10 == 1 && $n % 100 != 11) return 0;
	if ($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 12 || $n % 100 > 14)) return 1;
	return 2;
}

// Version check only for users who can update plugins
$version_check = get_transient('solo_woocommerce_tag');
$tag = $installed = SOLO_VERSION;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
if (($installed != $version_check) && (!isset($_GET['update']))) {
	// Fetch latest version from GitHub (uses transient cache)
	$tag = ltrim($this->solo_woocommerce_fetch_github_version(), 'v');

	// Display notice
	if (version_compare($installed, $tag, '<') && current_user_can('update_plugins')) {
?>
      <div class="notice notice-info notice-alt"><?php $this->solo_woocommerce_update_notice($tag); ?></div>
<?php
	}
}

// Tabs
$default_tab = null;
$tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : $default_tab; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

// Define default variables
$token = $tip_usluge = $jezik_ = $tip_racuna = $rok_placanja = $napomene_racun = $napomene_ponuda = $iban = $akcija = $posalji = $naslov = $poruka = $kpd = '';
$naslov_racun = $poruka_racun = $naslov_ponuda = $poruka_ponuda = '';

// Create variables from settings
$settings = get_option('solo_woocommerce_postavke');
if (!empty($settings)) {
	foreach ($settings as $key => $option) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
		${$key} = $option; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	}
}

// Backwards compatibility: use old shared naslov/poruka as fallback if new fields are empty
if ($naslov_racun === '') $naslov_racun = $naslov;
if ($poruka_racun === '') $poruka_racun = $poruka;
if ($naslov_ponuda === '') $naslov_ponuda = $naslov;
if ($poruka_ponuda === '') $poruka_ponuda = $poruka;
?>
<div class="wrap">
  <form action="options.php" method="post">
    <?php settings_fields('solo_woocommerce_postavke'); ?>
    <h1><div class="solo-logo"></div><?php echo esc_html(get_admin_page_title()); ?></h1>
    <p><?php echo esc_html__('Narudžba u tvojoj WooCommerce trgovini će automatski kreirati račun ili ponudu u servisu Solo.', 'solo-for-woocommerce'); ?></p>
    <nav class="nav-tab-wrapper">
      <a href="?page=solo-woocommerce" class="nav-tab <?php if($tab===null):?>nav-tab-active<?php endif; ?>"><?php echo esc_html__('API token', 'solo-for-woocommerce'); ?></a>
<?php if ($token) { ?>
      <a href="?page=solo-woocommerce&tab=postavke" class="nav-tab<?php if($tab==='postavke'):?> nav-tab-active<?php endif; ?>"><?php echo esc_html__('Solo postavke', 'solo-for-woocommerce'); ?></a>
      <a href="?page=solo-woocommerce&tab=akcije" class="nav-tab<?php if($tab==='akcije'):?> nav-tab-active<?php endif; ?>"><?php echo esc_html__('Načini plaćanja i akcije', 'solo-for-woocommerce'); ?></a>
      <a href="?page=solo-woocommerce&tab=email" class="nav-tab<?php if($tab==='email'):?> nav-tab-active<?php endif; ?>"><?php echo esc_html__('E-mail postavke', 'solo-for-woocommerce'); ?></a>
      <a href="?page=solo-woocommerce&tab=tecaj" class="nav-tab<?php if($tab==='tecaj'):?> nav-tab-active<?php endif; ?>"><?php echo esc_html__('Tečajna lista', 'solo-for-woocommerce'); ?></a>
      <a href="?page=solo-woocommerce&tab=arhiva" class="nav-tab<?php if($tab==='arhiva'):?> nav-tab-active<?php endif; ?>"><?php echo esc_html__('Arhiva', 'solo-for-woocommerce'); ?></a>
      <a href="?page=solo-woocommerce&tab=podrska" class="nav-tab<?php if($tab==='podrska'):?> nav-tab-active<?php endif; ?>"><?php echo esc_html__('Podrška', 'solo-for-woocommerce'); ?></a>
<?php } ?>
    </nav>
    <div class="tab-content">
<?php
// API token missing
if (!$token) $tab = $default_tab;

switch($tab):
	default:
?>
      <input type="hidden" name="solo_woocommerce_postavke[tip_racuna]" value="<?php echo esc_attr($tip_racuna) ?>">
      <input type="hidden" name="solo_woocommerce_postavke[posalji]" value="<?php echo esc_attr($posalji) ?>">
      <table class="form-table">
        <tbody>
          <tr>
            <th>
              <label for="token"><?php echo esc_html__('API token', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo esc_html__('Upiši svoj API token. Token ćeš pronaći u web servisu klikom na Postavke.', 'solo-for-woocommerce'); ?>"></sup></label>
            </th>
            <td class="mailserver-pass-wrap">
              <span class="wp-pwd">
                <input type="password" name="solo_woocommerce_postavke[token]" id="token" value="<?php echo esc_attr($token); ?>" autocorrect="off" autocomplete="off" maxlength="33" placeholder="" class="regular-text">
                <button type="button" class="button wp-hide-pw hide-if-no-js" id="toggle"><span class="dashicons dashicons-visibility"></span></button>
              </span>
              <p class="description"><?php if ($token==''): ?><?php echo esc_html__('Upiši i spremi promjene kako bi se prikazale ostale opcije.', 'solo-for-woocommerce'); ?><?php else: ?><a href="#" class="provjera"><?php echo esc_html__('Provjeri valjanost tokena', 'solo-for-woocommerce'); ?></a><?php endif; ?></p>
            </td>
          </tr>
        </tbody>
      </table>
      <?php submit_button(esc_html__('Spremi promjene', 'solo-for-woocommerce')); ?>
<?php
		break;

	case 'postavke':
?>
      <input type="hidden" name="solo_woocommerce_postavke[posalji]" value="<?php echo esc_attr($posalji) ?>">
      <table class="form-table">
        <tbody>
          <tr>
            <th>
              <label for="tip_usluge"><?php echo esc_html__('Tip usluge', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo esc_html__('Upiši redni broj glavnog tipa usluge iz web sučelja > Usluge > Tipovi usluga.<br>Koristi se samo za generiranje poziva na broj.', 'solo-for-woocommerce'); ?>"></sup></label>
            </th>
            <td>
              <input type="text" name="solo_woocommerce_postavke[tip_usluge]" id="tip_usluge" value="<?php echo esc_attr($tip_usluge); ?>" autocorrect="off" autocomplete="off" maxlength="2" placeholder="" class="small-text int">
              <p class="description"><?php echo esc_html__('Nije obavezno upisati.', 'solo-for-woocommerce'); ?></p>
            </td>
          </tr>
          <tr>
            <th><label for="jezik_"><?php echo esc_html__('Jezik', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo esc_html__('Odaberi jezik na kojem želiš kreirati račun ili ponudu.', 'solo-for-woocommerce'); ?>"></sup></label></th>
            <td>
              <select name="solo_woocommerce_postavke[jezik_]">
<?php
		$languages = [esc_html__('Hrvatski', 'solo-for-woocommerce') => 1, esc_html__('Engleski', 'solo-for-woocommerce') => 2, esc_html__('Njemački', 'solo-for-woocommerce') => 3, esc_html__('Francuski', 'solo-for-woocommerce') => 4, esc_html__('Talijanski', 'solo-for-woocommerce') => 5, esc_html__('Španjolski', 'solo-for-woocommerce') => 6];

		foreach ($languages as $key => $value) {
			echo '<option value="' . esc_attr($value) . '"' . (($jezik_==$value) ? ' selected' : '') . '>' . esc_attr($key) . '</option>';
		}
?>
              </select>
            </td>
          </tr>
          <tr>
            <th>
              <label for="tip_racuna"><?php echo esc_html__('Tip računa', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo esc_html__('Odaberi zadani tip računa.', 'solo-for-woocommerce'); ?>">?</sup></label>
            </th>
            <td>
              <select name="solo_woocommerce_postavke[tip_racuna]">
<?php
		$types = ['R' => 1, 'R1' => 2, 'R2' => 3, esc_html__('bez oznake', 'solo-for-woocommerce') => 4, esc_html__('Avansni', 'solo-for-woocommerce') => 5];

		foreach ($types as $key => $value) {
			echo '<option value="' . esc_attr($value) . '"' . (($tip_racuna==$value) ? ' selected' : '') . '>' . esc_attr($key) . '</option>';
		}
?>
              </select>
              <p class="description"><?php echo esc_html__('Odnosi se samo na račune. Ponude nemaju tipove.', 'solo-for-woocommerce'); ?></p>
            </td>
          </tr>
          <tr>
            <th>
              <label for="rok_placanja"><?php echo esc_html__('Rok plaćanja', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo esc_html__('Upiši broj dana koji se dodaje na datum izrade računa ili ponude, a do kojeg kupac treba platiti.<br>Ako nije upisano, Solo će staviti zadani broj dana za rok plaćanja (7) ili će kopirati s prethodnog računa ili ponude.', 'solo-for-woocommerce'); ?>"></sup></label>
            </th>
            <td>
              <input type="text" name="solo_woocommerce_postavke[rok_placanja]" id="rok_placanja" value="<?php echo esc_attr($rok_placanja); ?>" autocorrect="off" autocomplete="off" maxlength="2" placeholder="" class="small-text int">
              <p class="description"><?php echo esc_html__('Nije obavezno upisati.', 'solo-for-woocommerce'); ?></p>
            </td>
          </tr>
          <tr>
            <th>
              <label for="kpd_"><?php echo esc_html__('KPD oznaka', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo esc_html__('Ova KPD oznaka će se koristiti u svim stavkama narudžbe ako ne odabereš KPD oznaku u samom proizvodu.', 'solo-for-woocommerce'); ?>"></sup></label>
            </th>
            <td>
              <input type="text" name="solo_woocommerce_postavke[kpd]" id="kpd_" value="<?php echo esc_attr($kpd); ?>" autocorrect="off" autocomplete="off" maxlength="8" placeholder="">
              <p class="description"><?php echo esc_html__('Prikazuje se samo na B2B računima.', 'solo-for-woocommerce'); ?></p>
            </td>
          </tr>
          <tr>
            <th><label for="napomene_racun"><?php echo esc_html__('Napomene na računu', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo esc_html__('Upiši napomene koje će se pojaviti na svakom računu.<br>Solo prihvaća do najviše 1000 znakova.', 'solo-for-woocommerce'); ?>"></sup></label></th>
            <td>
              <textarea name="solo_woocommerce_postavke[napomene_racun]" id="napomene_racun" rows="2" maxlength="1000" class="large-text"><?php echo esc_textarea($napomene_racun); ?></textarea>
              <p class="description"><?php echo esc_html__('Nije obavezno upisati.', 'solo-for-woocommerce'); ?></p>
            </td>
          </tr>
          <tr>
            <th><label for="napomene_ponuda"><?php echo esc_html__('Napomene na ponudi', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo esc_html__('Upiši napomene koje će se pojaviti na svakoj ponudi.<br>Solo prihvaća do najviše 1000 znakova.', 'solo-for-woocommerce'); ?>"></sup></label></th>
            <td>
              <textarea name="solo_woocommerce_postavke[napomene_ponuda]" id="napomene_ponuda" rows="2" maxlength="1000" class="large-text"><?php echo esc_textarea($napomene_ponuda); ?></textarea>
              <p class="description"><?php echo esc_html__('Nije obavezno upisati.', 'solo-for-woocommerce'); ?></p>
            </td>
          </tr>
          <tr>
            <th><label for="iban"><?php echo esc_html__('IBAN za uplatu', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo esc_html__('Odaberi IBAN (tvoj žiro račun) koji će se pojaviti na računu ili ponudi.<br>IBAN možeš mijenjati u web sučelju > Postavke > Moja tvrtka.', 'solo-for-woocommerce'); ?>"></sup></label></th>
            <td>
              <select name="solo_woocommerce_postavke[iban]">
<?php
		$ibans = [esc_html__('Glavni IBAN', 'solo-for-woocommerce') => 1, esc_html__('Drugi IBAN (ako postoji)', 'solo-for-woocommerce') => 2, esc_html__('Treći IBAN (ako postoji)', 'solo-for-woocommerce') => 3];

		foreach ($ibans as $key => $value) {
			echo '<option value="' . esc_attr($value) . '"' . (($iban==$value) ? ' selected' : '') . '>' . esc_attr($key) . '</option>';
		}
?>
              </select>
            </td>
          </tr>
        </tbody>
      </table>
      <?php submit_button(esc_html__('Spremi promjene', 'solo-for-woocommerce')); ?>
<?php
		break;

	case 'akcije':
?>
      <input type="hidden" name="solo_woocommerce_postavke[tip_racuna]" value="<?php echo esc_attr($tip_racuna) ?>">
      <input type="hidden" name="solo_woocommerce_postavke[posalji]" value="<?php echo esc_attr($posalji) ?>">
<?php
		// Show enabled WooCommerce gateways
		$gateways = WC()->payment_gateways->payment_gateways();
		$available_gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ($gateways) {
?>
      <p><?php echo wp_kses_post(__('Namjesti postavke kreiranja računa ili ponude za svaki od prikazanih <a href="admin.php?page=wc-settings&tab=checkout" target="_blank">načina plaćanja</a>.', 'solo-for-woocommerce')); ?></p>
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
					'bacs' => esc_html__('Uplata na transakcijski račun', 'solo-for-woocommerce'),
					'cod' => esc_html__('Plaćanje pri pouzeću', 'solo-for-woocommerce'),
					'stripe' => esc_html__('Stripe (kartice)', 'solo-for-woocommerce'),
					'stripe_sepa' => esc_html__('Stripe (transakcijski račun)', 'solo-for-woocommerce'),
					'paypal' => esc_html__('PayPal', 'solo-for-woocommerce'),
					'ppec_paypal' => esc_html__('PayPal (Express Checkout)', 'solo-for-woocommerce'),
					'ppcp-gateway' => esc_html__('PayPal', 'solo-for-woocommerce'),
					'braintree_credit_card' => esc_html__('Braintree (kartice)', 'solo-for-woocommerce'),
					'braintree_paypal' => esc_html__('Braintree (PayPal)', 'solo-for-woocommerce'),
					'corvuspay' => esc_html__('CorvusPay (kartice)', 'solo-for-woocommerce'),
					'monri' => esc_html__('Monri (kartice)', 'solo-for-woocommerce'),
					'mypos_virtual' => esc_html__('myPOS (kartice)', 'solo-for-woocommerce'),
					'wooplatnica-croatia' => esc_html__('Uplatnica (transakcijski račun)', 'solo-for-woocommerce'),
					'erste-kekspay-woocommerce' => esc_html__('KEKS Pay (transakcijski račun)', 'solo-for-woocommerce'),
					'eh_paypal_express' => esc_html__('PayPal Express (kartice)', 'solo-for-woocommerce'),
					'revolut_cc' => esc_html__('Revolut (kartice)', 'solo-for-woocommerce'),
					'aircash-woocommerce' => esc_html__('Aircash (kartice)', 'solo-for-woocommerce'),
					'woocommerce_payments' => esc_html__('WooPayments (kartice)', 'solo-for-woocommerce'),
					'woocommerce_payments_sepa' => esc_html__('WooPayments (transakcijski račun)', 'solo-for-woocommerce'),
					'woocommerce_payments_apple_pay' => esc_html__('WooPayments (Apple Pay)', 'solo-for-woocommerce'),
					'woocommerce_payments_google_pay' => esc_html__('WooPayments (Google Pay)', 'solo-for-woocommerce'),
					'teya_payments' => esc_html__('Teya (kartice)', 'solo-for-woocommerce'),
					'borgun' => esc_html__('Teya (kartice)', 'solo-for-woocommerce'),
					'vivacom_smart' => esc_html__('Viva (kartice)', 'solo-for-woocommerce'),
					'mollie_wc_gateway_creditcard' => esc_html__('Mollie (kartice)', 'solo-for-woocommerce'),
					'mollie_wc_gateway_banktransfer' => esc_html__('Mollie (transakcijski račun)', 'solo-for-woocommerce'),
					'mollie_wc_gateway_paypal' => esc_html__('Mollie (PayPal)', 'solo-for-woocommerce'),
					'klarna_payments' => esc_html__('Klarna Payments (kartice)', 'solo-for-woocommerce'),
					'kco' => esc_html__('Klarna Checkout (kartice)', 'solo-for-woocommerce'),
					'worldline-hosted-tokenization' => esc_html__('Worldline (kartice)', 'solo-for-woocommerce'),
					'worldline-apple-pay' => esc_html__('Worldline (Apple Pay)', 'solo-for-woocommerce'),
					'worldline-google-pay' => esc_html__('Worldline (Google Pay)', 'solo-for-woocommerce'),
					'raiaccept' => esc_html__('Raiffeisen (kartice)', 'solo-for-woocommerce'),
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
      <div class="card<?php echo esc_attr($color); ?>">
        <h3><a href="admin.php?page=wc-settings&tab=checkout&section=<?php echo esc_attr($gateway_id); ?>" target="_blank"><?php echo esc_attr($gateway_title); ?></a></h3>
        <p><?php echo wp_kses_post($gateway_description); ?></p>
        <hr>
        <label for="<?php echo esc_attr($gateway_id); ?>1"><?php echo esc_html__('Automatski kreiraj', 'solo-for-woocommerce'); ?></label>
        <select name="solo_woocommerce_postavke[<?php echo esc_attr($gateway_id); ?>1]" id="<?php echo esc_attr($gateway_id); ?>1">
<?php
					$types = [esc_html__('ništa', 'solo-for-woocommerce') => '', esc_html__('račun', 'solo-for-woocommerce') => 'racun', esc_html__('ponudu', 'solo-for-woocommerce') => 'ponuda'];

					foreach ($types as $key => $value) {
						echo '<option value="' . esc_attr($value) . '"' . (($dynamic_var1==$value) ? ' selected' : '') . '>' . esc_attr($key) . '</option>';
					}
?>
        </select>
        <label for="<?php echo esc_attr($gateway_id); ?>2"><?php echo esc_html__('kada', 'solo-for-woocommerce'); ?></label>
        <select name="solo_woocommerce_postavke[<?php echo esc_attr($gateway_id); ?>2]" id="<?php echo esc_attr($gateway_id); ?>2">
<?php
					$actions = [esc_html__('primiš narudžbu (bez uplate)', 'solo-for-woocommerce') => 1, esc_html__('kupac uplati', 'solo-for-woocommerce') => 2];

					foreach ($actions as $key => $value) {
						echo '<option value="' . esc_attr($value) . '"' . (($dynamic_var2==$value) ? ' selected' : '') . '>' . esc_attr($key) . '</option>';
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
        <p><?php echo wp_kses_post(__('Akcija <b>"primiš narudžbu (bez uplate)"</b> se izvršava čim kupac napravi narudžbu neovisno o tipu plaćanja. Takve narudžbe će imati status <span class="status processing">Processing / U obradi</span> ili <span class="status on-hold">On hold / Na čekanju</span> u WooCommerce popisu narudžbi.', 'solo-for-woocommerce')); ?></p>
        <p><?php echo wp_kses_post(__('Akcija <b>"kupac uplati"</b> se izvršava čim online naplata (npr. karticom ili PayPalom) bude uspješna. Kod uplate na transakcijski račun i plaćanja pouzećem izvršava se kada narudžbu obilježiš kao <span class="status completed">Completed / Završeno</span> u WooCommerce popisu narudžbi.', 'solo-for-woocommerce')); ?></p>
      </div>
      <?php submit_button(esc_html__('Spremi promjene', 'solo-for-woocommerce')); ?>
<?php
		} else {
?>
      <br>
      <div class="notice notice-error inline"><p><?php echo wp_kses_post(__('Prvo uključi barem jedan način plaćanja u <a href="admin.php?page=wc-settings&tab=checkout" target="_blank">WooCommerce postavkama</a>.', 'solo-for-woocommerce')); ?></p></div>
<?php
		}
		break;

	case 'email':

		// Cron check
		if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
?>
      <br>
      <div class="notice notice-error inline"><p><?php echo wp_kses_post(__('Za automatsko slanje računa ili ponude na e-mail, potrebno je izbrisati <code>define(\'DISABLE_WP_CRON\', true);</code> iz <i>wp-config.php</i> datoteke.', 'solo-for-woocommerce')); ?></p></div>
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
				// Solid Mail (formerly WP-SMTP)
				'solid-mail/solid-mail.php',
				// Mailgun for WordPress
				'mailgun/mailgun.php',
				// SendGrid
				'sendgrid-email-delivery-simplified/wpsendgrid.php',
				// Brevo (formerly Sendinblue)
				'mailin/mailin.php',
				// Postmark
				'postmark-approved-wordpress-plugin/postmark.php',
				// Gmail SMTP
				'gmail-smtp/main.php',
				// WP SMTP
				'wp-smtp/wp-smtp.php',
				// Easy SMTP
				'easy-smtp/easy-smtp.php',
				// Mailpoet
				'mailpoet/mailpoet.php',
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

			// Not in the list? Detect any mail plugin by what it does, not by its name
			if (!$smtp_plugin_active) {
				$wp_mail_file = wp_normalize_path((new ReflectionFunction('wp_mail'))->getFileName());
				$smtp_plugin_active = has_action('phpmailer_init') || has_filter('pre_wp_mail') || strpos($wp_mail_file, wp_normalize_path(ABSPATH . WPINC)) === false;
			}

			if (!$smtp_plugin_active) {
?>
      <br>
      <div class="notice notice-error inline"><p><?php echo wp_kses_post(__('Potrebno je instalirati (i aktivirati) SMTP dodatak za WordPress (npr. <a href="https://wordpress.org/plugins/smtp-mailer/" target="_blank">SMTP Mailer</a>) kako bi se račun ili ponuda automatski poslali kupcu na e-mail.', 'solo-for-woocommerce')); ?></p></div>
<?php
			}
?>
      <input type="hidden" name="solo_woocommerce_postavke[tip_racuna]" value="<?php echo esc_attr($tip_racuna) ?>">
      <table class="form-table">
        <tbody>
          <tr>
            <th><label for="posalji"><?php echo esc_html__('Automatsko slanje', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo esc_html__('Uključi ako želiš da se račun ili ponuda automatski pošalju e-mailom kupcu nakon uspješne kupnje ili narudžbe.', 'solo-for-woocommerce'); ?>"></sup></label></th>
            <td>
              <fieldset>
                <label for="posalji"><input type="checkbox" name="solo_woocommerce_postavke[posalji]" id="posalji" value="1"<?php if ($posalji==1) echo ' checked="checked"'; if (!$smtp_plugin_active) echo ' disabled'; ?>> <?php echo esc_html__('Da', 'solo-for-woocommerce'); ?></label>
              </fieldset>
            </td>
          </tr>
          <tr>
            <th><label for="naslov_racun"><?php echo esc_html__('Naslov poruke (račun)', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo esc_html__('Upiši naslov e-mail poruke koju će kupac dobiti uz račun.', 'solo-for-woocommerce'); ?>"></sup></label></th>
            <td>
              <input type="text" name="solo_woocommerce_postavke[naslov_racun]" id="naslov_racun" value="<?php echo esc_attr($naslov_racun); ?>" autocorrect="off" autocomplete="off" maxlength="100" placeholder="" class="regular-text">
            </td>
          </tr>
          <tr>
            <th><label for="poruka_racun"><?php echo esc_html__('Sadržaj poruke (račun)', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo esc_html__('Upiši sadržaj e-mail poruke koju će kupac dobiti uz račun.<br>HTML formatiranje nije podržano.', 'solo-for-woocommerce'); ?>"></sup></label></th>
            <td>
              <textarea name="solo_woocommerce_postavke[poruka_racun]" id="poruka_racun" rows="8" class="large-text"><?php echo esc_textarea($poruka_racun); ?></textarea>
              <p class="description">*<?php echo esc_html__('PDF kopija računa će automatski biti u privitku', 'solo-for-woocommerce'); ?></p>
            </td>
          </tr>
          <tr>
            <th><label for="naslov_ponuda"><?php echo esc_html__('Naslov poruke (ponuda)', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo esc_html__('Upiši naslov e-mail poruke koju će kupac dobiti uz ponudu.', 'solo-for-woocommerce'); ?>"></sup></label></th>
            <td>
              <input type="text" name="solo_woocommerce_postavke[naslov_ponuda]" id="naslov_ponuda" value="<?php echo esc_attr($naslov_ponuda); ?>" autocorrect="off" autocomplete="off" maxlength="100" placeholder="" class="regular-text">
            </td>
          </tr>
          <tr>
            <th><label for="poruka_ponuda"><?php echo esc_html__('Sadržaj poruke (ponuda)', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo esc_html__('Upiši sadržaj e-mail poruke koju će kupac dobiti uz ponudu.<br>HTML formatiranje nije podržano.', 'solo-for-woocommerce'); ?>"></sup></label></th>
            <td>
              <textarea name="solo_woocommerce_postavke[poruka_ponuda]" id="poruka_ponuda" rows="8" class="large-text"><?php echo esc_textarea($poruka_ponuda); ?></textarea>
              <p class="description">*<?php echo esc_html__('PDF kopija ponude će automatski biti u privitku', 'solo-for-woocommerce'); ?></p>
            </td>
          </tr>
        </tbody>
      </table>
      <?php submit_button(esc_html__('Spremi promjene', 'solo-for-woocommerce')); ?>
<?php
		}

		break;

	case 'tecaj':

		// Display exchange rate
		solo_woocommerce_exchange(3);

		break;

	case 'arhiva':

		// Check for table in database (cached, same pattern as solo_woocommerce_process_order())
		global $wpdb;
		$table_name = esc_sql($wpdb->prefix . 'solo_woocommerce');
		if (!get_transient('solo_woocommerce_table_exists')) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
				solo_woocommerce_create_table();
			} else {
				set_transient('solo_woocommerce_table_exists', 1, DAY_IN_SECONDS);
			}
		}

		// Read from database
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results("SELECT * FROM `{$table_name}` ORDER BY id DESC");

		if (!empty($results)) {
?>
      <p><?php echo wp_kses_post(__('Prikazane su sve narudžbe koje je WooCommerce poslao u servis Solo. Imaj na umu da WooCommerce šalje samo narudžbe za koje je u <a href="?page=solo-woocommerce&tab=akcije">"Načini plaćanja i akcije"</a> omogućeno kreiranje dokumenta.', 'solo-for-woocommerce')); ?></p>
      <p><a href="#" id="ponovi"><?php echo esc_html__('Pokušaj ponovo sve neuspješne zahtjeve', 'solo-for-woocommerce'); ?></a>
        <span></span></p>
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
            <th data-sortas="numeric"><?php echo esc_html__('Narudžba', 'solo-for-woocommerce'); ?></th>
            <th data-sortas="case-insensitive"><?php echo esc_html__('API zahtjev', 'solo-for-woocommerce'); ?></th>
            <th data-sortas="case-insensitive"><?php echo esc_html__('API odgovor', 'solo-for-woocommerce'); ?></th>
            <th data-sortas="datetime"><?php echo esc_html__('Datum zahtjeva', 'solo-for-woocommerce'); ?></th>
            <th data-sortas="datetime"><?php echo esc_html__('Datum odgovora', 'solo-for-woocommerce'); ?></th>
            <th data-sortas="datetime"><?php echo esc_html__('Datum slanja', 'solo-for-woocommerce'); ?></th>
          </tr>
        </thead>
        <tbody>
<?php
			foreach($results as $row) {
				$api_request = $row->api_request;
				$api_request = preg_replace('/token=[a-zA-Z0-9]{29}/', 'token=*****************************', $api_request);
				$api_request = nl2br(esc_html($api_request));
				$api_response_decoded = json_decode($row->api_response);
				$api_response = $api_response_decoded ? esc_html(json_encode($api_response_decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) : esc_html($row->api_response);
				$api_response = nl2br(str_replace('    ', '&nbsp;&nbsp;', $api_response));
				$created = $row->created;
				$updated = $row->updated;
				if (!$updated || $updated=='0000-00-00 00:00:00') $updated = '&ndash;';
				$sent = $row->sent;
				if (!$sent || $sent=='0000-00-00 00:00:00') $sent = '&ndash;';

				$response_decoded = json_decode($row->api_response, true);
				$response_status = $response_decoded['status'] ?? -1;
				$is_failed = empty($row->api_response) || $response_status !== 0;
?>
          <tr class="shrink <?php echo $is_failed ? 'fail' : ''; ?>" data-order-id="<?php echo intval($row->order_id); ?>">
            <td data-sortvalue="<?php echo intval($row->order_id); ?>"><p><a href="post.php?post=<?php echo intval($row->order_id); ?>&action=edit"><?php echo intval($row->order_id); ?></a></p></td>
            <td><p><?php echo $api_request; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p></td>
            <td>
<?php if ($is_failed): ?>
              <a href="#" class="ponovi" data-order-id="<?php echo intval($row->order_id); ?>"><?php echo esc_html__('Pokušaj ponovo', 'solo-for-woocommerce'); ?></a>
<?php endif; ?>
              <p><?php echo $api_response; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
            </td>
            <td data-sortvalue="<?php echo esc_attr($created); ?>"><p><?php echo esc_html($created) . '<br>' . esc_html(solo_woocommerce_timeago($created)); ?></p></td>
            <td data-sortvalue="<?php echo esc_attr($updated); ?>"><p><?php echo esc_html($updated) . '<br>' . esc_html(solo_woocommerce_timeago($updated)); ?></p></td>
            <td data-sortvalue="<?php echo esc_attr($sent); ?>"><p><?php echo esc_html($sent) . '<br>' . esc_html(solo_woocommerce_timeago($sent)); ?></p></td>
          </tr>
<?php
				}
?>
        </tbody>
      </table>
<?php
		} else {
?>
      <br><div class="notice notice-warning inline"><p><?php echo esc_html__('Još niti jedna narudžba nije poslana u Solo.', 'solo-for-woocommerce'); ?></p></div>
<?php
		}
		break;

	case 'podrska':
?>
      <p><?php echo wp_kses_post(__('Za tehničku podršku molimo te da koristiš opciju <a href="https://solo.com.hr/podrska" target="_blank">Kontaktiraj podršku</a> izravno iz servisa Solo.', 'solo-for-woocommerce')); ?></p>
      <p><?php echo esc_html__('Imaš instaliranu verziju', 'solo-for-woocommerce'); ?> <b><?php echo esc_html(SOLO_VERSION); ?></b></p>
      <p><a href="<?php echo esc_url(wp_nonce_url('admin.php?page=solo-woocommerce&purge=true', 'solo_woocommerce_purge_nonce')); ?>" class="button button-small" onclick="return confirm('<?php echo esc_js(__('Potvrdi brisanje svih zapisa koje je dodatak kreirao u bazi:', 'solo-for-woocommerce')); ?>')"><?php echo esc_html__('Resetiraj dodatak', 'solo-for-woocommerce'); ?></a></p>
      <p><a href="<?php echo esc_url(wp_nonce_url('plugins.php?action=deactivate&plugin=solo-for-woocommerce/solo-woocommerce.php', 'deactivate-plugin_solo-for-woocommerce/solo-woocommerce.php')); ?>" class="button button-small"><?php echo esc_html__('Deaktiviraj dodatak', 'solo-for-woocommerce'); ?></a></p>
<?php
		// Debug logging is on when both WP_DEBUG and WP_DEBUG_LOG are enabled
		$is_debug_log = defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;

		// Log file: wp-content/debug.log by default, or a custom path set in WP_DEBUG_LOG (same logic as WordPress core)
		$default_log = WP_CONTENT_DIR . '/debug.log';
		$log_file = $default_log;
		if (defined('WP_DEBUG_LOG') && is_string(WP_DEBUG_LOG) && !in_array(strtolower(WP_DEBUG_LOG), array('true', '1'), true)) $log_file = WP_DEBUG_LOG;

		// Logging disabled
		if (!$is_debug_log) {
?>
      <div class="notice notice-info inline">
        <p><?php echo wp_kses_post(__('Ako imaš problema s dodatkom, omogući <i>debugiranje</i> u WordPressu za dobivanje informacija o tome gdje i kako dolazi do greške. U <i>wp-config.php</i> datoteku dodaj ove linije:<br><code>define(\'WP_DEBUG\', true);</code><br><code>define(\'WP_DEBUG_LOG\', true);</code><br><code>define(\'WP_DEBUG_DISPLAY\', false);</code>', 'solo-for-woocommerce')); ?></p>
      </div>
<?php
		} elseif (file_exists($log_file) && filesize($log_file) > 0) {
			// Read only the last 512 KB - debug.log can grow to hundreds of MB
			try {
				$file = new SplFileObject($log_file, 'r');
				$size = $file->getSize();
				$file->fseek(max(0, $size - 524288));
				$log_content = $file->fread(min($size, 524288));
			} catch (Exception $e) {
				$log_content = '';
			}

			// Keep lines that start with date and time (format: [DD-Mon-YYYY HH:MM:SS UTC]) and mention this plugin
			$filtered_lines = array();
			foreach (explode("\n", (string) $log_content) as $line) {
				if (preg_match('/^\[\d{2}-\w{3}-\d{4}(?: \d{2}:\d{2}:\d{2})?/', $line) && strpos($line, 'solo-woocommerce') !== false) {
					$filtered_lines[] = $line;
				}
			}

			// Display filtered lines
			if (!empty($filtered_lines)) {
				if ($log_file === $default_log) {
?>
      <p><?php
					// translators: %s is the URL to the debug.log file.
					echo wp_kses_post(sprintf(__('Greške iz <a href="%s" target="_blank">debug.log</a> datoteke:', 'solo-for-woocommerce'), esc_url(content_url('debug.log')))); ?></p>
<?php
				} else {
?>
      <p><?php echo esc_html__('Greške iz debug.log datoteke:', 'solo-for-woocommerce'); ?></p>
<?php
				}

				// Newest first (the log is written chronologically), limit 50
				$filtered_lines = array_slice(array_reverse($filtered_lines), 0, 50);

				foreach ($filtered_lines as $filtered_line) {
					// Extract date and time
					preg_match('/\[(.*?)\]/', $filtered_line, $matches);
					$date_time = isset($matches[1]) ? $matches[1] : '&ndash;';

					// Convert to Y-m-d H:i:s in WordPress timezone
					try {
						$date = new DateTime($date_time);
						$date->setTimezone(wp_timezone());
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

		break;
endswitch;
?>
    </div>
  </form>
</div>