<?php
/**
 * Plugin Name: Solo for WooCommerce
 * Plugin URI: https://solo.com.hr/api-dokumentacija/dodaci
 * Description: Narudžba u tvojoj WooCommerce trgovini će automatski kreirati račun ili ponudu u servisu Solo.
 * Version: 1.0
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

$default_tab = null;
$tab = isset($_GET['tab']) ? $_GET['tab'] : $default_tab;

// Init main class
$solo_woocommerce = new solo_woocommerce;

// Define default variables
$token = $tip_usluge = $jezik_ = $valuta_ = $prikazi_porez = $tip_racuna = $rok_placanja = $akcija = $posalji = $naslov = $poruka = '';

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
      <table class="form-table">
        <tbody>
          <tr>
            <th>
              <label for="token"><?php echo __('API token', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo __('Upiši svoj API token. Token ćeš pronaći u web servisu klikom na Postavke.', 'solo-for-woocommerce'); ?>"></sup></label>
            </th>
            <td>
              <input type="text" name="solo_woocommerce_postavke[token]" id="token" value="<?php echo $token; ?>" autocorrect="off" autocomplete="off" maxlength="33" placeholder="" class="regular-text">
              <p class="description"><?php if ($token==''): ?><?php echo __('Upiši i spremi promjene kako bi se otvorile ostale opcije.', 'solo-for-woocommerce'); ?><?php else: ?><a href="#" class="provjera"><?php echo __('Provjeri valjanost tokena', 'solo-for-woocommerce'); ?></a><?php endif; ?></p>
            </td>
          </tr>
        </tbody>
      </table>
      <?php submit_button(__('Spremi promjene', 'solo-for-woocommerce')); ?>
<?php
		break;

	case 'postavke':
?>
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

		// Show enabled WooCommerce gateways
		$gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ($gateways) {
?>
      <p><?php echo __('Namjesti postavke kreiranja računa ili ponude za svaki od prikazanih <a href="admin.php?page=wc-settings&tab=checkout" target="_blank">načina plaćanja</a>.', 'solo-for-woocommerce'); ?></p>
<?php
			foreach ($gateways as $gateway) {
				$gateway_id = $gateway->id;
				$gateway_title = $gateway->title;
				$gateway_description = $gateway->method_description;

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
					'pikpay' => __('Monri (kartice, fiskalizacija)', 'solo-for-woocommerce')
				);

				// Show only available payments
				if (isset($translations[$gateway_id])) {
					$gateway_title = $translations[$gateway_id];

					// Dynamic variable error handling
					if (isset(${$gateway_id . '_'})) {
						$dynamic_var_ = ${$gateway_id . '_'};
						$dynamic_var__ = ${$gateway_id . '__'};
					} else {
						$dynamic_var_ = $dynamic_var__ = '';
					}
?>
      <div class="card">
        <h3><a href="admin.php?page=wc-settings&tab=checkout&section=<?php echo esc_attr($gateway_id); ?>" target="_blank"><?php echo $gateway_title; ?></a></h3>
        <p><?php echo $gateway_description; ?></p>
        <hr>
        <label for="<?php echo esc_attr($gateway_id); ?>"><?php echo __('Automatski kreiraj', 'solo-for-woocommerce'); ?></label>
        <select name="solo_woocommerce_postavke[<?php echo esc_attr($gateway_id); ?>_]" id="<?php echo esc_attr($gateway_id); ?>">
<?php
					$types = [__('ništa', 'solo-for-woocommerce') => '', __('račun', 'solo-for-woocommerce') => 'racun', __('ponudu', 'solo-for-woocommerce') => 'ponuda'];

					foreach ($types as $key => $value) {
						echo '<option value="' . $value . '"' . (($dynamic_var_==$value) ? ' selected' : '') . '>' . $key . '</option>';
					}
?>
        </select>
        <label for="<?php echo esc_attr($gateway_id); ?>"><?php echo __('u koraku', 'solo-for-woocommerce'); ?></label>
        <select name="solo_woocommerce_postavke[<?php echo esc_attr($gateway_id); ?>__]" id="<?php echo esc_attr($gateway_id); ?>">
<?php
					$actions = ["Checkout" => "on-hold", "Completed" => "completed"];

					foreach ($actions as $key => $value) {
						echo '<option value="' . $value . '"' . (($dynamic_var__==$value) ? ' selected' : '') . '>' . $key . '</option>';
					}
?>
        </select>
      </div>
<?php
				}
			}
?>
      <br><div class="notice notice-info inline">
        <p><?php echo __('<b>Checkout</b> korak se izvršava čim kupac napravi narudžbu neovisno o tipu plaćanja', 'solo-for-woocommerce'); ?></p>
        <p><?php echo __('<b>Completed</b> korak se izvršava kada narudžbu obilježiš kao <u>završenu</u> u WooCommerce administraciji ili kada naplata karticom bude uspješna (automatski)', 'solo-for-woocommerce'); ?></p>
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
?>
      <br><div class="notice notice-info inline"><p><?php echo __('Za automatsko slanje mailova trebaš imati namještene SMTP postavke, bilo ručno ili putem jednog od besplatnih WordPress dodataka za slanje (npr. <a href="https://wordpress.org/plugins/wp-mail-smtp/" target="_blank">WP Mail SMTP</a>).', 'solo-for-woocommerce'); ?></p></div>
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
              <textarea name="solo_woocommerce_postavke[poruka]" id="poruka" rows="12" class="large-text"><?php echo $poruka; ?></textarea>
              <p class="description">*<?php echo __('PDF kopija dokumenta će automatski biti u privitku', 'solo-for-woocommerce'); ?></p>
            </td>
          </tr>
        </tbody>
      </table>
      <?php submit_button(__('Spremi promjene', 'solo-for-woocommerce')); ?>
<?php
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
      <table class="widefat striped" id="arhiva">
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
            <th data-sort="int"><?php echo __('Broj narudžbe', 'solo-for-woocommerce'); ?></th>
            <th data-sort="string"><?php echo __('API zahtjev', 'solo-for-woocommerce'); ?></th>
            <th data-sort="string"><?php echo __('API odgovor', 'solo-for-woocommerce'); ?></th>
            <th data-sort="string"><?php echo __('Datum zahtjeva', 'solo-for-woocommerce'); ?></th>
            <th data-sort="string"><?php echo __('Datum odgovora', 'solo-for-woocommerce'); ?></th>
            <th data-sort="string"><?php echo __('Datum slanja', 'solo-for-woocommerce'); ?></th>
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
				// Beautify zeroes
				$updated = $row->updated;
				if (!$updated || $updated=='0000-00-00 00:00:00') $updated = '&ndash;';
				// Check if sent to customer
				$sent = $row->sent;
				if (!$sent || $sent=='0000-00-00 00:00:00') $sent = '&ndash;';
?>
          <tr class="shrink">
            <td><p><a href="post.php?post=<?php echo $row->order_id; ?>&action=edit"><?php echo $row->order_id; ?></a></p></td>
            <td><p><?php echo nl2br($row->api_request); ?></p></td>
            <td><p><?php echo nl2br($row->api_response); ?></p></td>
            <td><p><?php echo $row->created . '<br>' . timeago($row->created); ?></p></td>
            <td><p><?php echo $updated . '<br>' . timeago($updated); ?></p></td>
            <td><p><?php echo $sent . '<br>' . timeago($sent); ?></p></td>
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
      <br><div class="notice notice-info inline"><p><?php echo __('Tehnička podrška za ovaj dodatak nalazi se na <a href="https://github.com/coax/solo-for-woocommerce#podrška" target="_blank">GitHub stranicama</a>.', 'solo-for-woocommerce'); ?></p><p><?php echo __('Imaš instaliranu verziju', 'solo-for-woocommerce'); ?> <?php echo SOLO_VERSION; ?>.</p></div>
<?php
		break;
endswitch;
?>
    </div>
  </form>
</div>