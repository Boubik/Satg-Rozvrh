<?php

/**
 * Plugin Name: STAG Rozvrh
 * Plugin URI: https://github.com/Boubik/Satg-Rozvrh
 * Description: Načítá a zobrazuje rozvrh učitelů ze STAG API s možností vlastního formátování výpisu. Lze zobrazit pomocí shortcode [stag_rozvrh] nebo widgetu.
 * Version: 1.0.0
 * Author: Boubik
 * Text Domain: stag-rozvrh
 */

if (! defined('ABSPATH')) {
    exit; // Zabránit přímému přístupu
}

define('STAG_ROZVRH_DEFAULT_API_URL', 'https://stag-demo.zcu.cz/ws/');

/**
 * Pomocná funkce pro extrakci času z možného pole (API někdy vrací např. ["value" => "12:30"] místo stringu).
 */
function stag_rozvrh_extract_time($val)
{
    if (is_array($val) && isset($val['value'])) {
        return $val['value'];
    }
    return $val;
}

/**
 * Nastavení výchozích hodnot při aktivaci pluginu.
 */
function stag_rozvrh_default_options()
{
    $defaults = array(
        'api_url'         => STAG_ROZVRH_DEFAULT_API_URL,
        'api_user'        => '',
        'api_pass'        => '',
        'fields'          => array('predmet', 'typ', 'cas', 'mistnost'),
        'cache_duration'  => 1,       // doba cache v dnech (výchozí 1 den)
        'ls_start'        => '02-01', // začátek letního semestru
        'zs_start'        => '09-01', // začátek zimního semestru
        'custom_header'   => '',
        'custom_row'      => ''
    );
    if (get_option('stag_rozvrh_settings') === false) {
        add_option('stag_rozvrh_settings', $defaults);
    }
    // Vložíme i volbu, kam budeme ukládat čas poslední aktualizace
    if (get_option('stag_rozvrh_last_update') === false) {
        add_option('stag_rozvrh_last_update', 0);
    }
}
register_activation_hook(__FILE__, 'stag_rozvrh_default_options');

/**
 * Přidání položky do menu "Nastavení".
 */
add_action('admin_menu', function () {
    add_options_page(
        'STAG Rozvrh',
        'STAG Rozvrh',
        'manage_options',
        'stag-rozvrh-settings',
        'stag_rozvrh_render_settings_page'
    );
});

/**
 * Registrace nastavení (group).
 */
add_action('admin_init', function () {
    register_setting('stag_rozvrh_settings_group', 'stag_rozvrh_settings');
    // Volbu 'stag_rozvrh_last_update' neregistrujeme jako user setting, jen ji zobrazujeme (je to spíše informativní).
});

/**
 * Vykreslení stránky nastavení.
 */
function stag_rozvrh_render_settings_page()
{
    if (! current_user_can('manage_options')) {
        return;
    }

    $options       = get_option('stag_rozvrh_settings', array());
    $api_url       = $options['api_url']         ?? STAG_ROZVRH_DEFAULT_API_URL;
    $api_user      = $options['api_user']        ?? '';
    $api_pass      = $options['api_pass']        ?? '';
    $fields        = $options['fields']          ?? array('predmet', 'typ', 'cas', 'mistnost');
    $cache_dur     = intval($options['cache_duration'] ?? 1);
    $ls_start      = $options['ls_start']        ?? '';
    $zs_start      = $options['zs_start']        ?? '';
    $custom_header = $options['custom_header']   ?? '';
    $custom_row    = $options['custom_row']      ?? '';

    // Zpracování odeslání formuláře
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stag_rozvrh_nonce'])) {
        if (!wp_verify_nonce($_POST['stag_rozvrh_nonce'], 'stag_rozvrh_save_settings')) {
            echo '<div class="error"><p>Neplatná bezpečnostní kontrola (nonce).</p></div>';
        } else {
            if (isset($_POST['stag_rozvrh_flush'])) {
                // Vymazat cache
                stag_rozvrh_flush_cache();
                // Resetovat datum poslední aktualizace na 0, což vyvolá zprávu "momentálně není aktualizovaný"
                update_option('stag_rozvrh_last_update', 0);
                echo '<div class="updated"><p>Cache byla vymazána.</p></div>';
            } else {
                // Uložit nastavení
                $new_options = array();
                $new_options['api_url']         = sanitize_text_field($_POST['stag_api_url']        ?? '');
                $new_options['api_user']        = sanitize_text_field($_POST['stag_api_user']       ?? '');
                $new_options['api_pass']        = sanitize_text_field($_POST['stag_api_pass']       ?? '');
                $new_options['fields']          = isset($_POST['stag_fields']) ? array_map('sanitize_text_field', (array)$_POST['stag_fields']) : array();
                $new_options['cache_duration']  = intval($_POST['stag_cache_duration'] ?? 1);
                $new_options['ls_start']        = sanitize_text_field($_POST['stag_ls_start']       ?? '');
                $new_options['zs_start']        = sanitize_text_field($_POST['stag_zs_start']       ?? '');
                $new_options['custom_header']   = wp_kses_post($_POST['stag_custom_header']         ?? '');
                $new_options['custom_row']      = wp_kses_post($_POST['stag_custom_row']            ?? '');

                update_option('stag_rozvrh_settings', $new_options);
                echo '<div class="updated"><p>Nastavení bylo uloženo.</p></div>';

                // Aktualizace lokálních proměnných
                $api_url       = $new_options['api_url'];
                $api_user      = $new_options['api_user'];
                $api_pass      = $new_options['api_pass'];
                $fields        = $new_options['fields'];
                $cache_dur     = $new_options['cache_duration'];
                $ls_start      = $new_options['ls_start'];
                $zs_start      = $new_options['zs_start'];
                $custom_header = $new_options['custom_header'];
                $custom_row    = $new_options['custom_row'];
            }
        }
    }

    // Možná pole k zobrazení
    $all_fields = array(
        'predmet'  => 'Předmět',
        'typ'      => 'Typ akce',
        'cas'      => 'Čas',
        'ucitel'   => 'Učitel',
        'mistnost' => 'Místnost'
    );

?>
    <div class="wrap">
        <h1>Nastavení STAG Rozvrh</h1>
        <form method="post" action="">
            <?php wp_nonce_field('stag_rozvrh_save_settings', 'stag_rozvrh_nonce'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">API URL</th>
                    <td>
                        <input type="url" name="stag_api_url" value="<?php echo esc_attr($api_url); ?>" size="50" />
                        <p class="description">Např. <?php echo esc_html(STAG_ROZVRH_DEFAULT_API_URL); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">API uživatelské jméno</th>
                    <td>
                        <input type="text" name="stag_api_user" value="<?php echo esc_attr($api_user); ?>" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">API heslo</th>
                    <td>
                        <input type="password" name="stag_api_pass" value="<?php echo esc_attr($api_pass); ?>" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Doba platnosti cache (dny)</th>
                    <td>
                        <input type="number" name="stag_cache_duration" value="<?php echo esc_attr($cache_dur); ?>" min="0" />
                        <p class="description">Počet dnů, po které se mají ukládat data do cache.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Začátek letního semestru</th>
                    <td>
                        <input type="text" name="stag_ls_start" value="<?php echo esc_attr($ls_start); ?>" placeholder="MM-DD" pattern="\d{2}-\d{2}" />
                        <p class="description">Formát: MM-DD (např. 02-01)</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Začátek zimního semestru</th>
                    <td>
                        <input type="text" name="stag_zs_start" value="<?php echo esc_attr($zs_start); ?>" placeholder="MM-DD" pattern="\d{2}-\d{2}" />
                        <p class="description">Formát: MM-DD (např. 09-01)</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Vlastní hlavička výpisu</th>
                    <td>
                        <textarea name="stag_custom_header" rows="3" cols="60"><?php echo esc_textarea($custom_header); ?></textarea>
                        <p class="description">Text (HTML) zobrazený nad seznamem akcí.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Vlastní formát řádku</th>
                    <td>
                        <textarea id="stag_custom_row" name="stag_custom_row" rows="4" cols="60"><?php echo esc_textarea($custom_row); ?></textarea>
                        <button type="button" id="stag-insert-newline">Vložit \n</button>
                        <p class="description">
                            Použijte placeholdery: <code>{predmet}</code>, <code>{typ}</code>, <code>{cas_od}</code>, <code>{cas_do}</code>, <code>{ucitel}</code>, <code>{mistnost}</code>.<br>
                            Např.: <em>{predmet} čas: od {cas_od} do {cas_do}</em><br>
                            Pokud chcete nový řádek, vložte <code>\n</code> (nahradí se za <code>&lt;br></code>).
                        </p>
                    </td>
                </tr>
            </table>
            <p>
                <input type="submit" name="submit" class="button button-primary" value="Uložit změny">
                <input type="submit" name="stag_rozvrh_flush" class="button button-secondary" value="Vymazat cache">
            </p>
        </form>

        <?php
        // Zobrazení poslední aktualizace z DB (pokud nějaká proběhla)
        $last_update = get_option('stag_rozvrh_last_update', 0);
        echo '<hr>';
        if ($last_update) {
            echo '<p><strong>Poslední aktualizace dat z API:</strong> ' . wp_date('d.m.Y H:i:s', $last_update) . ' (časové pásmo: ' . wp_date('P', $last_update) . ')</p>';
        } else {
            echo '<p><strong>Poslední aktualizace dat z API:</strong> momentálně není aktualizovaný.</p>';
        }
        echo '<p>[stag_rozvrh staglogin="your_login"]</p>';
        echo '<p>[stag_rozvrh ucitidno="your_teacher_id"]</p>';
        ?>

        <script>
            document.getElementById('stag-insert-newline').addEventListener('click', function() {
                var ta = document.getElementById('stag_custom_row');
                ta.value += "\\n";
            });
        </script>
    </div>
    <?php
}

/**
 * Vymazání cache pluginu (transientů).
 */
function stag_rozvrh_flush_cache()
{
    global $wpdb;
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE '\_transient\_stag\_rozvrh\_%' 
            OR option_name LIKE '\_transient\_timeout\_stag\_rozvrh\_%'"
    );
}

/**
 * Určení aktuálního semestru (LS/ZS) podle data.
 */
function stag_get_current_semester()
{
    $options = get_option('stag_rozvrh_settings', array());
    $ls_start = $options['ls_start'] ?? '';
    $zs_start = $options['zs_start'] ?? '';

    $today = new DateTime();
    $year  = $today->format('Y');

    $ls_date = DateTime::createFromFormat('Y-m-d', $year . '-' . $ls_start);
    $zs_date = DateTime::createFromFormat('Y-m-d', $year . '-' . $zs_start);

    if (!$ls_date || !$zs_date) {
        return array('sem' => 'ZS', 'year' => $year);
    }

    if ($today >= $ls_date && $today < $zs_date) {
        return array('sem' => 'LS', 'year' => $year - 1);
    } elseif ($today >= $zs_date) {
        return array('sem' => 'ZS', 'year' => $year);
    } else {
        return array('sem' => 'ZS', 'year' => $year - 1);
    }
}

/**
 * Získání ID učitele z API (s cache).
 */
function stag_get_teacher_id($stagLogin)
{
    $transient_key = 'stag_rozvrh_tid_' . sanitize_key($stagLogin);
    $teacherId = get_transient($transient_key);
    if ($teacherId !== false) {
        return $teacherId;
    }

    $options = get_option('stag_rozvrh_settings', array());
    $api_url  = $options['api_url']  ?? STAG_ROZVRH_DEFAULT_API_URL;
    $api_user = $options['api_user'] ?? '';
    $api_pass = $options['api_pass'] ?? '';

    if (empty($stagLogin) || empty($api_user) || empty($api_pass)) {
        return false;
    }

    $url = rtrim($api_url, '/') . '/services/rest2/ucitel/getUcitIdnoByStagLogin?stagLogin=' . urlencode($stagLogin);
    $response = wp_remote_get($url, array(
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode($api_user . ':' . $api_pass)
        ),
        'timeout' => 15
    ));

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $teacherId = trim($body);
    if ($teacherId === '' || !ctype_digit($teacherId)) {
        return false;
    }

    // Uložit do cache (bez expirace)
    set_transient($transient_key, $teacherId, 0);
    return $teacherId;
}

/**
 * Získání rozvrhu učitele (s cache). Data v JSON.
 * Po úspěšném načtení aktualizujeme 'stag_rozvrh_last_update'.
 */
function stag_get_schedule($teacherId, $desired_year, $desired_sem)
{
    $options = get_option('stag_rozvrh_settings', array());
    $cache_days = intval($options['cache_duration'] ?? 1);
    $cache_sec = $cache_days * 86400;

    $transient_key = 'stag_rozvrh_schedule_' . sanitize_key($teacherId . '_' . $desired_year . $desired_sem);
    $schedule = get_transient($transient_key);
    if ($schedule !== false) {
        return $schedule;
    }

    $api_url  = $options['api_url']  ?? STAG_ROZVRH_DEFAULT_API_URL;
    $api_user = $options['api_user'] ?? '';
    $api_pass = $options['api_pass'] ?? '';

    if (empty($teacherId) || empty($api_user) || empty($api_pass)) {
        return false;
    }

    $url = rtrim($api_url, '/') . '/services/rest2/rozvrhy/getRozvrhByUcitel';
    $url .= '?ucitIdno=' . urlencode($teacherId);
    $url .= '&rok=' . urlencode($desired_year);
    $url .= '&semestr=' . urlencode($desired_sem);
    $url .= '&grafickyRozvrh=false';
    $url .= '&outputFormat=JSON';

    $response = wp_remote_get($url, array(
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode($api_user . ':' . $api_pass),
            'Accept'        => 'application/json'
        ),
        'timeout' => 15
    ));

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    if ($data === null) {
        return false;
    }

    $events = array();
    if (isset($data['rozvrhovaAkce'])) {
        $events = $data['rozvrhovaAkce'];
    } elseif (isset($data[0]['rozvrhovaAkce'])) {
        $events = $data[0]['rozvrhovaAkce'];
    } elseif (is_array($data) && isset($data[0]) && isset($data[0]['nazev'])) {
        // Může to být rovnou seznam akcí
        $events = $data;
    }

    // Pokud se nám podařilo získat data, uložíme do cache a zapíšeme čas do DB
    $schedule = array('data' => $events);
    set_transient($transient_key, $schedule, $cache_sec);

    // Uložit čas poslední aktualizace do plugin option
    update_option('stag_rozvrh_last_update', time());

    return $schedule;
}

/**
 * Vykreslení rozvrhu – bez jakékoliv dekorace (žádné styly, tabulka se generuje bez inline CSS).
 */
function stag_render_schedule($schedule, $settings)
{
    $custom_header = $settings['custom_header'] ?? '';
    $custom_row    = $settings['custom_row']    ?? '';
    $fields        = $settings['fields']        ?? array('predmet', 'typ', 'cas', 'mistnost');

    // Začneme prázdným $output
    $output = '';

    // Pokud je vyplněna vlastní hlavička, přidáme ji bez stylů
    if (!empty($custom_header)) {
        $output .= $custom_header . "\n";
    }

    // Pokud je vyplněn vlastní formát řádku, vypisujeme řádek za řádkem
    if (!empty($custom_row)) {
        foreach ($schedule as $event) {
            $cas_od = stag_rozvrh_extract_time($event['hodinaSkutOd'] ?? $event['hodinaOd'] ?? '');
            $cas_do = stag_rozvrh_extract_time($event['hodinaSkutDo'] ?? $event['hodinaDo'] ?? '');

            $replacements = array(
                '{predmet}'  => $event['predmet']   ?? '',
                '{typ}'      => ($event['typAkce'] ?? $event['typAkceZkr']) ?? '',
                '{cas_od}'   => $cas_od,
                '{cas_do}'   => $cas_do,
                '{ucitel}'   => $event['vsichniUciteleJmenaTituly'] ?? '',
                '{mistnost}' => $event['mistnost'] ?? ''
            );

            if (isset($event['predmet'])) {
                $row = str_replace(array_keys($replacements), array_values($replacements), $custom_row);
                // Nahrazení doslovného "\n" za <br>
                $row = str_replace('\n', '<br>', $row);
                // Přidáme do výstupu, třeba s mezerou
                $output .= $row . "\n";
            }
        }
    } else {
        // Standardní tabulkový výpis bez inline stylů
        $output .= "<table border=\"1\" cellpadding=\"5\" cellspacing=\"0\">\n";
        $output .= "<thead><tr>\n";
        if (in_array('predmet', $fields))   $output .= "<th>Předmět</th>\n";
        if (in_array('typ', $fields))       $output .= "<th>Typ akce</th>\n";
        if (in_array('cas', $fields))       $output .= "<th>Od</th><th>Do</th>\n";
        if (in_array('ucitel', $fields))    $output .= "<th>Učitel</th>\n";
        if (in_array('mistnost', $fields))  $output .= "<th>Místnost</th>\n";
        $output .= "</tr></thead>\n<tbody>\n";

        foreach ($schedule as $event) {
            $output .= "<tr>\n";
            if (in_array('predmet', $fields)) {
                $katedra     = $event['katedra']     ?? '';
                $predmetCode = $event['predmet']      ?? '';
                $nazev       = $event['nazev']        ?? '';
                $subjectDisplay = '';
                if ($katedra || $predmetCode) {
                    $subjectDisplay .= $katedra;
                    if ($katedra && $predmetCode) {
                        $subjectDisplay .= '/';
                    }
                    $subjectDisplay .= $predmetCode . ' – ';
                }
                $subjectDisplay .= $nazev;
                $output .= "<td>" . esc_html($subjectDisplay) . "</td>\n";
            }
            if (in_array('typ', $fields)) {
                $typAkce = $event['typAkce'] ?? ($event['typAkceZkr'] ?? '');
                $output .= "<td>" . esc_html($typAkce) . "</td>\n";
            }
            if (in_array('cas', $fields)) {
                $cas_od = stag_rozvrh_extract_time($event['hodinaSkutOd'] ?? $event['hodinaOd'] ?? '');
                $cas_do = stag_rozvrh_extract_time($event['hodinaSkutDo'] ?? $event['hodinaDo'] ?? '');
                $output .= "<td>" . esc_html($cas_od) . "</td><td>" . esc_html($cas_do) . "</td>\n";
            }
            if (in_array('ucitel', $fields)) {
                $teachers = $event['vsichniUciteleJmenaTituly'] ?? '';
                if (!$teachers && !empty($event['ucitel'])) {
                    $u = $event['ucitel'];
                    $teachers = ($u['jmeno'] ?? '') . ' ' . ($u['prijmeni'] ?? '');
                }
                $output .= "<td>" . esc_html($teachers) . "</td>\n";
            }
            if (in_array('mistnost', $fields)) {
                $budova   = $event['budova']   ?? '';
                $mistnost = $event['mistnost'] ?? '';
                $output .= "<td>" . esc_html($budova . ' ' . $mistnost) . "</td>\n";
            }
            $output .= "</tr>\n";
        }

        $output .= "</tbody></table>\n";
    }

    return $output;
}

/**
 * Shortcode: [stag_rozvrh stagLogin="baroch" semestr="LS|ZS"]
 */
add_shortcode('stag_rozvrh', 'stag_rozvrh_shortcode');
function stag_rozvrh_shortcode($atts)
{
    $a = shortcode_atts(array(
        'staglogin' => '',
        'ucitidno'  => '',
        'semestr'   => ''
    ), $atts, 'stag_rozvrh');

    $semInput = strtoupper(sanitize_text_field($a['semestr']));

    // Pokud je zadáno ID učitele, použijeme jej přímo
    if (!empty($a['ucitidno'])) {
        $teacherId = sanitize_text_field($a['ucitidno']);
    }
    // Jinak pokud je zadán stagLogin, použijeme funkci pro získání ID
    elseif (!empty($a['staglogin'])) {
        $stagLogin = sanitize_text_field($a['staglogin']);
        $teacherId = stag_get_teacher_id($stagLogin);
        if (!$teacherId) {
            return '<div class="stag-rozvrh-error">Chyba: učitel s loginem ' . esc_html($stagLogin) . ' nebyl nalezen.</div>';
        }
    }
    // Pokud ani jeden z parametrů není zadán, zobrazíme chybovou hlášku
    else {
        return '<div class="stag-rozvrh-error">Musíte zadat buď login učitele (staglogin) nebo ID učitele (ucitidno).</div>';
    }

    if (!empty($semInput) && in_array($semInput, array('LS', 'ZS'))) {
        $desired_sem  = $semInput;
        $desired_year = date('Y');
    } else {
        $sem_info     = stag_get_current_semester();
        $desired_sem  = $sem_info['sem'];
        $desired_year = $sem_info['year'];
    }

    $schedule = stag_get_schedule($teacherId, $desired_year, $desired_sem);
    if (!$schedule || !isset($schedule['data'])) {
        return '<div class="stag-rozvrh-error">Chyba při načítání rozvrhu učitele.</div>';
    }

    $settings = get_option('stag_rozvrh_settings', array());
    return stag_render_schedule($schedule['data'], $settings);
}

/**
 * Widget: STAG Rozvrh Widget
 */
class Stag_Rozvrh_Widget extends WP_Widget
{
    function __construct()
    {
        parent::__construct(
            'stag_rozvrh_widget',
            __('STAG Rozvrh Widget', 'stag-rozvrh'),
            array('description' => __('Zobrazuje rozvrh učitele ze STAG API', 'stag-rozvrh'))
        );
    }

    public function widget($args, $instance)
    {
        echo $args['before_widget'];
        $title     = !empty($instance['title'])     ? $instance['title']     : __('Rozvrh učitele', 'stag-rozvrh');
        $stagLogin = !empty($instance['staglogin']) ? $instance['staglogin'] : '';

        echo $args['before_title'] . apply_filters('widget_title', $title) . $args['after_title'];

        if (empty($stagLogin)) {
            echo '<p>Není nastaven login učitele.</p>';
        } else {
            echo do_shortcode('[stag_rozvrh stagLogin="' . esc_attr($stagLogin) . '"]');
        }
        echo $args['after_widget'];
    }

    public function form($instance)
    {
        $title     = $instance['title']     ?? __('Rozvrh učitele', 'stag-rozvrh');
        $stagLogin = $instance['staglogin'] ?? '';
    ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>">Titulek:</label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>"
                name="<?php echo $this->get_field_name('title'); ?>" type="text"
                value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('staglogin'); ?>">STAG Login učitele:</label>
            <input class="widefat" id="<?php echo $this->get_field_id('staglogin'); ?>"
                name="<?php echo $this->get_field_name('staglogin'); ?>" type="text"
                value="<?php echo esc_attr($stagLogin); ?>">
        </p>
<?php
    }

    public function update($new_instance, $old_instance)
    {
        $instance = array();
        $instance['title']     = sanitize_text_field($new_instance['title']     ?? '');
        $instance['staglogin'] = sanitize_text_field($new_instance['staglogin'] ?? '');
        return $instance;
    }
}

function register_stag_rozvrh_widget()
{
    register_widget('Stag_Rozvrh_Widget');
}
add_action('widgets_init', 'register_stag_rozvrh_widget');
