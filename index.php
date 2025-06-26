<?php
/*
BACKUP FILE - STARA WERSJA PLUGINU
Ten plik zawiera starą, monolityczną wersję pluginu WordPress Diagnostics.
Nowa, modularna wersja znajduje się w pliku wp-diagnostics.php i katalogu includes/

Oryginalny opis:
WordPress Diagnostics & Configuration Analyzer - kompleksowe narzędzie diagnostyczne dla WordPress. 
Sprawdza konfigurację wp-config.php, analizuje wtyczki i motywy, testuje wydajność PHP oraz wykonuje 
zaawansowane testy sieciowe (ping, traceroute, SSL/TLS, DNS, skanowanie portów, wykrywanie WAF, MTU, SMTP). 
Oferuje porównanie wyników serwer/klient, eksport raportów do PDF/CSV, historię testów oraz rekomendacje bezpieczeństwa.

UWAGA: Ten plik nie jest już aktywnym pluginem WordPress!
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Zapobiegaj bezpośredniemu dostępowi.
}

class NetworkDiagnosticsPlugin {
    private $dns_cache = array();
    private $common_ports = array(
        80 => 'HTTP',
        443 => 'HTTPS',
        21 => 'FTP',
        22 => 'SSH',
        25 => 'SMTP',
        53 => 'DNS',
        110 => 'POP3',
        143 => 'IMAP',
        993 => 'IMAPS',
        995 => 'POP3S',
        3306 => 'MySQL',
    );
    private $test_results = array();

    public function __construct() {
        // Weryfikuj czy wszystkie callback funkcje istnieją
        if ( ! $this->verify_callbacks() ) {
            return; // Nie inicjalizuj jeśli brakuje funkcji
        }
        
        // Dodajemy akcje WordPress
        add_action( 'admin_menu', array( $this, 'nd_add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'nd_enqueue_scripts' ) );
        add_action( 'init', array( $this, 'create_history_table' ) );
        add_action( 'wp_ajax_export_results', array( $this, 'handle_export' ) );
        
        // Debug uprawnień (do usunięcia po rozwiązaniu problemu)
        add_action( 'admin_init', array( $this, 'debug_user_capabilities' ) );
        
        // Sprawdź uprawnienia użytkownika
        add_action( 'admin_init', array( $this, 'check_user_permissions' ) );
    }
    
    /**
     * Sprawdza uprawnienia użytkownika
     */
    public function check_user_permissions() {
        // Sprawdź czy użytkownik ma odpowiednie uprawnienia przy każdym ładowaniu strony admin
        if ( is_admin() && isset( $_GET['page'] ) && 
             in_array( $_GET['page'], array( 'network-diagnostics', 'wp-config-check', 'plugins-themes-analysis', 'php-performance-check' ) ) ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( __( 'Nie masz uprawnień do dostępu do tej strony.' ) );
            }
        }
    }

    /**
     * Funkcja debug do sprawdzania uprawnień
     */
    public function debug_user_capabilities() {
        if ( is_admin() && current_user_can( 'manage_options' ) ) {
            add_action( 'admin_notices', function() {
                echo '<div class="notice notice-info is-dismissible">';
                echo '<p><strong>Debug Info:</strong> Użytkownik ma uprawnienia manage_options. Role: ' . implode(', ', wp_get_current_user()->roles) . '</p>';
                echo '</div>';
            });
        }
    }

    public function create_history_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'network_diagnostics_history';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            test_date datetime DEFAULT CURRENT_TIMESTAMP,
            host varchar(255) NOT NULL,
            test_type varchar(50) NOT NULL,
            results text NOT NULL,
            PRIMARY KEY  (id)
        )";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function handle_export() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $format = $_POST['format'] ?? 'pdf';
        $export = new Export_Manager();
        
        if ($format === 'pdf') {
            $export->export_to_pdf($this->test_results);
        } else {
            $export->export_to_csv($this->test_results);
        }
        
        wp_die();
    }

    public function nd_enqueue_scripts($hook) {
        if (strpos($hook, 'network-diagnostics') === false && 
            strpos($hook, 'wp-config-check') === false && 
            strpos($hook, 'plugins-themes-analysis') === false && 
            strpos($hook, 'php-performance-check') === false) {
            return;
        }
        
        // Enqueue CSS
        wp_enqueue_style(
            'network-diagnostics-admin',
            plugins_url('css/admin-styles.css', __FILE__),
            array(),
            '1.0.0'
        );
        
        // Enqueue JS only for main diagnostics page
        if ('toplevel_page_network-diagnostics' === $hook) {
            wp_enqueue_script(
                'client-diagnostics',
                plugins_url('js/client-diagnostics.js', __FILE__),
                array(),
                '1.0.0',
                true
            );
            wp_add_inline_script('client-diagnostics', '
                document.addEventListener("DOMContentLoaded", function() {
                    const diagnostics = new ClientDiagnostics();
                    const form = document.querySelector("#network-diagnostics-form");
                    const addressInput = document.querySelector("#address");
                    
                    // Add export functionality
                    window.exportResults = function(format) {
                        const data = new FormData();
                        data.append("action", "export_results");
                        data.append("format", format);
                        
                        fetch(ajaxurl, {
                            method: "POST",
                            body: data
                        }).then(response => response.blob())
                        .then(blob => {
                            const url = window.URL.createObjectURL(blob);
                            const a = document.createElement("a");
                            a.href = url;
                            a.download = "diagnostics-report." + format;
                            a.click();
                            window.URL.revokeObjectURL(url);
                        });
                    };
                    
                    if (addressInput) {
                        addressInput.addEventListener("change", async function() {
                            const results = await diagnostics.performTests(this.value);
                            document.querySelector("#client-results").innerHTML = `
                                <div class="nd-card">
                                    <h3>Wyniki testów klienckich:</h3>
                                    <pre>${JSON.stringify(results, null, 2)}</pre>
                                </div>
                            `;
                        });
                    }
                });
            ');
            
            // Localize script for AJAX
            wp_localize_script('client-diagnostics', 'ajax_object', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('export_nonce')
            ));
        }
    }

    public function nd_add_admin_menu() {
        // Debug - sprawdź czy użytkownik ma uprawnienia
        if ( ! current_user_can( 'manage_options' ) ) {
            return; // Nie dodawaj menu jeśli użytkownik nie ma uprawnień
        }
        
        // Główne menu
        $page_hook = add_menu_page(
            'WordPress Diagnostics',  // Tytuł strony
            'Diagnostyka WP',         // Tytuł menu
            'manage_options',         // Uprawnienia
            'network-diagnostics',    // Slug menu
            array( $this, 'nd_admin_page' ), // Funkcja wyświetlająca stronę
            'dashicons-admin-tools',  // Ikona (zmieniona na standardową)
            75                        // Pozycja w menu
        );
        
        // Dodaj submenu dla testów sieciowych (główna strona)
        add_submenu_page(
            'network-diagnostics',
            'Testy Sieciowe',
            'Testy Sieciowe',
            'manage_options',
            'network-diagnostics',
            array( $this, 'nd_admin_page' )
        );
        
        // Dodaj submenu dla sprawdzania konfiguracji WordPress
        add_submenu_page(
            'network-diagnostics',
            'Konfiguracja WordPress',
            'Konfiguracja WP',
            'manage_options',
            'wp-config-check',
            array( $this, 'wp_config_check_page' )
        );
        
        // Dodaj submenu dla sprawdzania wtyczek i motywów
        add_submenu_page(
            'network-diagnostics',
            'Analiza Wtyczek i Motywów',
            'Wtyczki i Motywy',
            'manage_options',
            'plugins-themes-analysis',
            array( $this, 'plugins_themes_analysis_page' )
        );
        
        // Dodaj submenu dla sprawdzania wydajności i limitów PHP
        add_submenu_page(
            'network-diagnostics',
            'Wydajność i Limity PHP',
            'Wydajność PHP',
            'manage_options',
            'php-performance-check',
            array( $this, 'php_performance_check_page' )
        );
        
        // Debug info
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'WordPress Diagnostics: Menu dodane pomyślnie. Hook: ' . $page_hook );
        }
    }

    public function nd_admin_page() {
        ?>
        <div class="wrap">
            <h1>WordPress Diagnostics - Testy Sieciowe</h1>
            <p>Wprowadź adres IP lub nazwę domeny, aby przetestować połączenie sieciowe i przeprowadzić kompleksową analizę.</p>
            <form method="post" id="network-diagnostics-form">
                <?php wp_nonce_field( 'nd_run_tests', 'nd_nonce' ); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="address">Adres IP lub nazwa domeny</label></th>
                        <td><input type="text" id="address" name="address" value="<?php echo isset( $_POST['address'] ) ? esc_attr( $_POST['address'] ) : ''; ?>" required /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="port">Port</label></th>
                        <td><input type="number" id="port" name="port" min="1" max="65535" value="<?php echo isset( $_POST['port'] ) ? esc_attr( $_POST['port'] ) : '80'; ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="ping_tries">Liczba prób ping</label></th>
                        <td><input type="number" id="ping_tries" name="ping_tries" min="1" max="10" value="4" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Wybierz testy</th>
                        <td>
                            <label><input type="checkbox" name="tests[]" value="ping" checked /> Ping</label><br />
                            <label><input type="checkbox" name="tests[]" value="traceroute" /> Traceroute</label><br />
                            <label><input type="checkbox" name="tests[]" value="smtp" /> Test połączenia SMTP</label><br />
                            <label><input type="checkbox" name="tests[]" value="ports" /> Skanuj popularne porty</label><br />
                            <label><input type="checkbox" name="tests[]" value="mtu" /> Test MTU</label><br />
                            <label><input type="checkbox" name="tests[]" value="ssl" /> Test SSL/TLS</label><br />
                            <label><input type="checkbox" name="tests[]" value="dns" /> Test DNS</label><br />
                            <label><input type="checkbox" name="tests[]" value="security" /> Skanowanie zabezpieczeń</label>
                        </td>
                    </tr>
                </table>
                <?php submit_button( 'Uruchom Testy' ); ?>
            </form>
            
            <div id="client-results"></div>
            
            <?php
            // Po wysłaniu formularza i weryfikacji nonce wykonujemy testy.
            if ( isset( $_POST['address'] ) && check_admin_referer( 'nd_run_tests', 'nd_nonce' ) ) {
                $address = sanitize_text_field( $_POST['address' ] );
                $port = isset( $_POST['port'] ) ? intval( $_POST['port'] ) : 80;
                $tests = isset( $_POST['tests'] ) ? $_POST['tests'] : array();
                $ping_tries = isset( $_POST['ping_tries'] ) ? intval( $_POST['ping_tries'] ) : 4;

                echo '<h2>Wyniki testów dla: ' . esc_html( $address ) . '</h2>';

                if ( in_array( 'ping', $tests ) ) {
                    echo '<h3>Ping</h3>';
                    $ping_result = $this->nd_ping( $address, $port, 6, $ping_tries );
                    echo '<pre>' . esc_html( $ping_result ) . '</pre>';
                }

                if ( in_array( 'traceroute', $tests ) ) {
                    try {
                        echo '<h3>Traceroute</h3>';
                        $traceroute_result = $this->nd_traceroute( $address );
                        echo '<pre>' . esc_html( $traceroute_result ) . '</pre>';
                    } catch (Exception $e) {
                        echo '<div class="error"><p>Błąd podczas wykonywania traceroute: ' . esc_html($e->getMessage()) . '</p></div>';
                    }
                }
                
                if ( in_array( 'smtp', $tests ) ) {
                    echo '<h3>Test połączenia SMTP</h3>';
                    $smtp_port = ($port == 80) ? 25 : $port; // Domyślnie port 25 jeśli wybrano 80
                    $smtp_result = $this->nd_test_smtp( $address, $smtp_port );
                    echo '<pre>' . esc_html( $smtp_result ) . '</pre>';
                }

                if ( in_array( 'ports', $tests ) ) {
                    echo '<h3>Skanowanie popularnych portów</h3>';
                    $ports_result = $this->nd_scan_ports( $address );
                    echo '<pre>' . esc_html( $ports_result ) . '</pre>';
                }

                if ( in_array( 'mtu', $tests ) ) {
                    echo '<h3>Test MTU</h3>';
                    $mtu_result = $this->nd_test_mtu( $address, $port );
                    echo '<pre>' . esc_html( $mtu_result ) . '</pre>';
                }

                if (in_array('ssl', $tests)) {
                    $ssl_tester = new SSL_Tester();
                    $ssl_result = $ssl_tester->test_ssl($address);
                    $this->test_results['SSL'] = $ssl_result;
                    echo '<h3>Test SSL/TLS</h3>';
                    echo '<pre>' . esc_html($ssl_result) . '</pre>';
                }

                if (in_array('dns', $tests)) {
                    $dns_tester = new DNS_Tester();
                    $dns_result = $dns_tester->test_dns_resolution($address);
                    $this->test_results['DNS'] = $dns_result;
                    echo '<h3>Test DNS</h3>';
                    echo '<pre>' . esc_html($dns_result) . '</pre>';
                }

                if (in_array('security', $tests)) {
                    $security_scanner = new Security_Scanner();
                    $security_result = $security_scanner->detect_waf($address);
                    $this->test_results['Security'] = $security_result;
                    echo '<h3>Skanowanie zabezpieczeń</h3>';
                    echo '<pre>' . esc_html($security_result) . '</pre>';
                }

                // Zapisz wyniki do historii
                $this->save_test_history($address, $this->test_results);
            }
            
            // Wyświetl ostatnie błędy z logu
            echo '<h2>Ostatnie wpisy z logu błędów WordPress</h2>';
            $log_entries = $this->get_last_wordpress_errors(10);
            
            if (empty($log_entries)) {
                echo '<p>Brak dostępnych wpisów w logu lub dostęp do logu jest niemożliwy.</p>';
            } else {
                echo '<div style="max-height: 400px; overflow-y: auto; background: #f8f8f8; padding: 10px; border: 1px solid #ddd;">';
                echo '<pre>';
                foreach ($log_entries as $entry) {
                    echo esc_html($entry) . "\n";
                }
                echo '</pre>';
                echo '</div>';
            }
            ?>
            
            <?php if (isset($_POST['address'])): ?>
                <div id="comparison-results">
                    <h3>Porównanie wyników server/client:</h3>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>Test</th>
                                <th>Serwer</th>
                                <th>Klient</th>
                            </tr>
                        </thead>
                        <tbody id="comparison-body">
                            <!-- Wypełniane przez JavaScript -->
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <div class="export-buttons">
                <button class="button" onclick="exportResults('pdf')">Eksportuj do PDF</button>
                <button class="button" onclick="exportResults('csv')">Eksportuj do CSV</button>
            </div>
            
            <!-- Szybkie linki do innych narzędzi diagnostycznych -->
            <div class="card" style="margin-top: 20px;">
                <h2>Dodatkowe Narzędzia Diagnostyczne</h2>
                <p>Sprawdź inne aspekty swojej instalacji WordPress:</p>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; margin-top: 15px;">
                    <div style="border: 1px solid #ddd; padding: 15px; border-radius: 4px; background: #f9f9f9;">
                        <h3 style="margin-top: 0;">🔒 Konfiguracja WordPress</h3>
                        <p>Sprawdź klucze bezpieczeństwa, ustawienia debug i konfigurację wp-config.php</p>
                        <a href="<?php echo admin_url('admin.php?page=wp-config-check'); ?>" class="button button-primary">Sprawdź Konfigurację</a>
                    </div>
                    <div style="border: 1px solid #ddd; padding: 15px; border-radius: 4px; background: #f9f9f9;">
                        <h3 style="margin-top: 0;">🔌 Wtyczki i Motywy</h3>
                        <p>Analizuj aktywne wtyczki, dostępne aktualizacje i potencjalne problemy bezpieczeństwa</p>
                        <a href="<?php echo admin_url('admin.php?page=plugins-themes-analysis'); ?>" class="button button-primary">Analizuj Wtyczki</a>
                    </div>
                    <div style="border: 1px solid #ddd; padding: 15px; border-radius: 4px; background: #f9f9f9;">
                        <h3 style="margin-top: 0;">⚡ Wydajność PHP</h3>
                        <p>Sprawdź limity PHP, załadowane rozszerzenia i wykonaj test wydajności serwera</p>
                        <a href="<?php echo admin_url('admin.php?page=php-performance-check'); ?>" class="button button-primary">Sprawdź Wydajność</a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Strona sprawdzania konfiguracji WordPress
     */
    public function wp_config_check_page() {
        ?>
        <div class="wrap">
            <h1>Sprawdzanie Konfiguracji WordPress</h1>
            <p>Sprawdza kluczowe elementy konfiguracji WordPress w pliku wp-config.php</p>
            
            <?php
            $config_results = $this->check_wp_config();
            
            if (isset($config_results['error'])) {
                echo '<div class="error"><p>' . esc_html($config_results['error']) . '</p></div>';
                return;
            }
            ?>
            
            <!-- Klucze i soli -->
            <div class="card">
                <h2>Klucze i soli WordPress</h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Klucz</th>
                            <th>Status</th>
                            <th>Długość</th>
                            <th>Akcja</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($config_results['keys_salts'] as $key => $info): ?>
                        <tr>
                            <td><code><?php echo esc_html($key); ?></code></td>
                            <td>
                                <?php if (!$info['defined']): ?>
                                    <span class="dashicons dashicons-warning" style="color: red;"></span> Brak definicji
                                <?php elseif ($info['is_placeholder']): ?>
                                    <span class="dashicons dashicons-warning" style="color: orange;"></span> Placeholder
                                <?php else: ?>
                                    <span class="dashicons dashicons-yes" style="color: green;"></span> OK
                                <?php endif; ?>
                            </td>
                            <td><?php echo isset($info['length']) ? $info['length'] . ' znaków' : 'N/A'; ?></td>
                            <td>
                                <?php if (!$info['defined'] || $info['is_placeholder']): ?>
                                    <a href="https://api.wordpress.org/secret-key/1.1/salt/" target="_blank" class="button button-small">Wygeneruj nowe</a>
                                <?php else: ?>
                                    <span style="color: green;">✓ Skonfigurowane</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php
                $placeholder_count = 0;
                foreach ($config_results['keys_salts'] as $info) {
                    if (!$info['defined'] || $info['is_placeholder']) {
                        $placeholder_count++;
                    }
                }
                ?>
                
                <?php if ($placeholder_count > 0): ?>
                <div class="notice notice-warning">
                    <p><strong>Ostrzeżenie:</strong> Znaleziono <?php echo $placeholder_count; ?> kluczy/soli z domyślnymi wartościami. 
                    To stanowi zagrożenie bezpieczeństwa! <a href="https://api.wordpress.org/secret-key/1.1/salt/" target="_blank">Wygeneruj nowe klucze</a> i zastąp je w wp-config.php.</p>
                </div>
                <?php else: ?>
                <div class="notice notice-success">
                    <p><strong>Świetnie!</strong> Wszystkie klucze i soli są prawidłowo skonfigurowane.</p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Ustawienia debug -->
            <div class="card" style="margin-top: 20px;">
                <h2>Ustawienia Debug</h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Stała</th>
                            <th>Wartość</th>
                            <th>Rekomendacja</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($config_results['debug_settings'] as $constant => $info): ?>
                        <tr>
                            <td><code><?php echo esc_html($constant); ?></code></td>
                            <td>
                                <?php if ($info['defined']): ?>
                                    <?php if (is_bool($info['value'])): ?>
                                        <code><?php echo $info['value'] ? 'true' : 'false'; ?></code>
                                    <?php else: ?>
                                        <code><?php echo esc_html($info['value']); ?></code>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <em>Nie zdefiniowane</em>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($info['recommended']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Ustawienia bazy danych -->
            <div class="card" style="margin-top: 20px;">
                <h2>Ustawienia Bazy Danych</h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Stała</th>
                            <th>Status</th>
                            <th>Informacje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($config_results['database_settings'] as $constant => $info): ?>
                        <tr>
                            <td><code><?php echo esc_html($constant); ?></code></td>
                            <td>
                                <?php if ($info['defined']): ?>
                                    <?php if ($info['has_value']): ?>
                                        <span class="dashicons dashicons-yes" style="color: green;"></span> Skonfigurowane
                                    <?php else: ?>
                                        <span class="dashicons dashicons-warning" style="color: orange;"></span> Puste
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="dashicons dashicons-no" style="color: red;"></span> Brak definicji
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (isset($info['masked_value'])): ?>
                                    <?php echo esc_html($info['masked_value']); ?>
                                <?php elseif (isset($info['length'])): ?>
                                    <?php echo $info['length']; ?> znaków
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Ustawienia bezpieczeństwa -->
            <div class="card" style="margin-top: 20px;">
                <h2>Ustawienia Bezpieczeństwa</h2>
                
                <h3>Force SSL</h3>
                <table class="widefat">
                    <tbody>
                        <tr>
                            <td><code>FORCE_SSL_ADMIN</code></td>
                            <td>
                                <?php if ($config_results['security_settings']['force_ssl']['FORCE_SSL_ADMIN']): ?>
                                    <span class="dashicons dashicons-yes" style="color: green;"></span> Włączone
                                <?php else: ?>
                                    <span class="dashicons dashicons-no" style="color: orange;"></span> Wyłączone
                                <?php endif; ?>
                            </td>
                            <td>Wymusza SSL dla panelu administracyjnego</td>
                        </tr>
                    </tbody>
                </table>
                
                <h3>Prefiks tabel</h3>
                <table class="widefat">
                    <tbody>
                        <tr>
                            <td>Aktualny prefiks</td>
                            <td><code><?php echo esc_html($config_results['security_settings']['table_prefix']['value']); ?></code></td>
                            <td>
                                <?php if ($config_results['security_settings']['table_prefix']['is_default']): ?>
                                    <span class="dashicons dashicons-warning" style="color: orange;"></span> Używa domyślnego prefiksu
                                <?php else: ?>
                                    <span class="dashicons dashicons-yes" style="color: green;"></span> Używa własnego prefiksu
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <h3>Edycja plików</h3>
                <table class="widefat">
                    <tbody>
                        <tr>
                            <td><code>DISALLOW_FILE_EDIT</code></td>
                            <td>
                                <?php if ($config_results['security_settings']['file_editing']['DISALLOW_FILE_EDIT']): ?>
                                    <span class="dashicons dashicons-yes" style="color: green;"></span> Wyłączone
                                <?php else: ?>
                                    <span class="dashicons dashicons-warning" style="color: orange;"></span> Włączone
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($config_results['security_settings']['file_editing']['recommendation']); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div style="margin-top: 20px;">
                <button class="button button-primary" onclick="location.reload();">Odśwież sprawdzenie</button>
                <a href="<?php echo admin_url('admin.php?page=network-diagnostics'); ?>" class="button">Powrót do diagnostyki sieci</a>
            </div>
        </div>
        
        <style>
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            padding: 20px;
            margin: 20px 0;
        }
        .card h2 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .card h3 {
            margin-top: 20px;
            margin-bottom: 10px;
            color: #23282d;
        }
        .widefat th {
            background: #f9f9f9;
        }
        </style>
        <?php
    }

    private function save_test_history($host, $results) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'network_diagnostics_history';
        
        foreach ($results as $test_type => $result) {
            $wpdb->insert(
                $table_name,
                array(
                    'host' => $host,
                    'test_type' => $test_type,
                    'results' => $result
                )
            );
        }
    }

    /**
     * Wykonuje test ping z obsługą cache DNS
     * 
     * @param string $host Nazwa hosta lub IP
     * @param int $port Port do testu
     * @param int $timeout Timeout w sekundach
     * @param int $tries Liczba prób
     * @return string Wynik testu
     */
    private function nd_ping($host, $port = 80, $timeout = 6, $tries = 4) {
        $results = array();
        // Użyj cache DNS jeśli dostępne
        if (isset($this->dns_cache[$host])) {
            $ip = $this->dns_cache[$host]['ip'];
            $dns_time = 0;
        } else {
            $dns_start = microtime(true);
            $ip = gethostbyname($host);
            $dns_time = round((microtime(true) - $dns_start) * 1000, 2);
            // Cache wyników DNS
            $this->dns_cache[$host] = array(
                'ip' => $ip,
                'time' => time()
            );
        }
        
        $output = "DNS Resolution: ";
        if ($ip != $host) {
            $output .= "Successful ($dns_time ms)\n";
            $output .= "Resolved IP: $ip\n";
        } else {
            $output .= "Failed (Invalid hostname or DNS error)\n";
        }
        
        $output .= "\nTesting connection on port $port:\n";
        
        for ($i = 1; $i <= $tries; $i++) {
            $start = microtime(true);
            $fsock = @fsockopen($host, $port, $errno, $errstr, $timeout);
            
            if (!$fsock) {
                $output .= "Attempt $i: Failed - $errstr ($errno)\n";
                continue;
            }
            
            $latency = round((microtime(true) - $start) * 1000, 2);
            $results[] = $latency;
            $output .= "Attempt $i: Success - $latency ms\n";
            fclose($fsock);
        }
        
        if (!empty($results)) {
            $min = min($results);
            $max = max($results);
            $avg = array_sum($results) / count($results);
            $output .= "\nStatystyki:\n";
            $output .= "Minimum: $min ms\n";
            $output .= "Maximum: $max ms\n";
            $output .= "Average: " . round($avg, 2) . " ms\n";
            $output .= "Packet Loss: " . round((($tries - count($results)) / $tries) * 100) . "%\n";
        }
        
        return $output;
    }

    private function nd_traceroute($host, $max_hops = 30, $timeout = 1) {
        $result = "Traceroute do $host:\n";
        $ip = gethostbyname($host);
        
        for ($ttl = 1; $ttl <= $max_hops; $ttl++) {
            $start = microtime(true);
            $socket = @fsockopen($host, 80, $errno, $errstr, $timeout, null, null, $ttl);
            
            if (!$socket) {
                if ($errno == 0) {
                    $hop_time = round((microtime(true) - $start) * 1000, 2);
                    $current_ip = $this->get_connecting_ip();
                    $result .= "$ttl: $current_ip ($hop_time ms)\n";
                    
                    if ($current_ip == $ip) {
                        break;
                    }
                } else {
                    $result .= "$ttl: * * * Timeout\n";
                }
            } else {
                fclose($socket);
                $hop_time = round((microtime(true) - $start) * 1000, 2);
                $result .= "$ttl: $ip ($hop_time ms) - Destination reached\n";
                break;
            }
        }
        
        return $result;
    }

    private function get_connecting_ip() {
        // Próba pobrania IP poprzez zewnętrzne API
        $ip = @file_get_contents('https://api.ipify.org');
        return $ip ? $ip : 'unknown';
    }
    
    private function nd_test_smtp($host, $port = 25, $timeout = 5) {
        $errno = 0;
        $errstr = '';
        $result = '';
        
        $result .= "Próba połączenia SMTP z $host na porcie $port...\n";
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        
        if (!$socket) {
            return $result . "Błąd połączenia: $errstr ($errno)";
        }
        
        $result .= "Połączenie nawiązane pomyślnie.\n";
        
        // Czytaj powitanie serwera
        $response = fgets($socket);
        $result .= "Odpowiedź serwera: $response";
        
        // Wyślij QUIT aby zakończyć sesję
        fputs($socket, "QUIT\r\n");
        $response = fgets($socket);
        $result .= "Odpowiedź na QUIT: $response";
        
        fclose($socket);
        return $result;
    }
    
    private function get_last_wordpress_errors($count = 10) {
        $log_path = ini_get('error_log');
        $wp_debug_log = WP_CONTENT_DIR . '/debug.log';
        
        // Próbujemy różne możliwe lokalizacje pliku logu
        $possible_logs = array(
            $log_path,
            $wp_debug_log,
            ABSPATH . 'wp-content/debug.log',
            ABSPATH . 'error_log',
            '/var/log/apache2/error.log',
            '/var/log/httpd/error.log',
        );
        
        $entries = array();
        
        foreach ($possible_logs as $log_file) {
            if (file_exists($log_file) && is_readable($log_file)) {
                $logfile_content = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($logfile_content) {
                    $entries = array_merge($entries, array_slice($logfile_content, -$count));
                    break;
                }
            }
        }
        
        // Ogranicz do wymaganej liczby wpisów
        return array_slice($entries, -$count);
    }

    /**
     * Skanuje popularne porty
     */
    private function nd_scan_ports($host) {
        $results = "Skanowanie popularnych portów:\n";
        foreach ($this->common_ports as $port => $service) {
            $start = microtime(true);
            $fp = @fsockopen($host, $port, $errno, $errstr, 1);
            $time = round((microtime(true) - $start) * 1000, 2);
            
            if ($fp) {
                fclose($fp);
                $results .= sprintf("Port %d (%s): Otwarty (%0.2f ms)\n", $port, $service, $time);
            } else {
                $results .= sprintf("Port %d (%s): Zamknięty\n", $port, $service);
            }
        }
        return $results;
    }

    /**
     * Testuje różne rozmiary MTU
     */
    private function nd_test_mtu($host, $port = 80) {
        $sizes = array(1500, 1492, 1472, 1468, 1400);
        $results = "Test MTU:\n";
        
        foreach ($sizes as $size) {
            $data = str_repeat('A', $size);
            $start = microtime(true);
            $fp = @fsockopen($host, $port, $errno, $errstr, 2);
            
            if ($fp) {
                $success = @fwrite($fp, $data);
                fclose($fp);
                $time = round((microtime(true) - $start) * 1000, 2);
                
                if ($success) {
                    $results .= sprintf("MTU %d: OK (%0.2f ms)\n", $size, $time);
                } else {
                    $results .= sprintf("MTU %d: Błąd wysyłania\n", $size);
                }
            } else {
                $results .= sprintf("MTU %d: Błąd połączenia\n", $size);
            }
        }
        
        return $results;
    }

    /**
     * Sprawdza konfigurację WordPress
     */
    private function check_wp_config() {
        $config_path = ABSPATH . 'wp-config.php';
        $results = array();
        
        if (!file_exists($config_path)) {
            return array('error' => 'Nie można znaleźć pliku wp-config.php');
        }
        
        if (!is_readable($config_path)) {
            return array('error' => 'Brak uprawnień do odczytu pliku wp-config.php');
        }
        
        $config_content = file_get_contents($config_path);
        
        // Sprawdź klucze i soli
        $results['keys_salts'] = $this->check_keys_and_salts($config_content);
        
        // Sprawdź ustawienia debug
        $results['debug_settings'] = $this->check_debug_settings($config_content);
        
        // Sprawdź ustawienia bazy danych
        $results['database_settings'] = $this->check_database_settings($config_content);
        
        // Sprawdź ustawienia bezpieczeństwa
        $results['security_settings'] = $this->check_security_settings($config_content);
        
        return $results;
    }
    
    /**
     * Sprawdza czy klucze i soli są wygenerowane czy zawierają placeholdery
     */
    private function check_keys_and_salts($config_content) {
        $keys_to_check = array(
            'AUTH_KEY',
            'SECURE_AUTH_KEY', 
            'LOGGED_IN_KEY',
            'NONCE_KEY',
            'AUTH_SALT',
            'SECURE_AUTH_SALT',
            'LOGGED_IN_SALT',
            'NONCE_SALT'
        );
        
        $results = array();
        $default_placeholders = array(
            'put your unique phrase here',
            'unique phrase',
            'your-key-here',
            'your_key_here'
        );
        
        foreach ($keys_to_check as $key) {
            $pattern = "/define\s*\(\s*['\"]" . $key . "['\"]\s*,\s*['\"]([^'\"]*)['\"].*\)/i";
            
            if (preg_match($pattern, $config_content, $matches)) {
                $value = $matches[1];
                $is_placeholder = false;
                
                // Sprawdź czy to placeholder
                foreach ($default_placeholders as $placeholder) {
                    if (stripos($value, $placeholder) !== false || strlen($value) < 32) {
                        $is_placeholder = true;
                        break;
                    }
                }
                
                $results[$key] = array(
                    'defined' => true,
                    'is_placeholder' => $is_placeholder,
                    'length' => strlen($value),
                    'status' => $is_placeholder ? 'PLACEHOLDER' : 'OK'
                );
            } else {
                $results[$key] = array(
                    'defined' => false,
                    'status' => 'MISSING'
                );
            }
        }
        
        return $results;
    }
    
    /**
     * Sprawdza ustawienia debug
     */
    private function check_debug_settings($config_content) {
        $debug_constants = array(
            'WP_DEBUG' => 'boolean',
            'WP_DEBUG_LOG' => 'boolean', 
            'WP_DEBUG_DISPLAY' => 'boolean',
            'SCRIPT_DEBUG' => 'boolean'
        );
        
        $results = array();
        
        foreach ($debug_constants as $constant => $type) {
            if (defined($constant)) {
                $value = constant($constant);
                $results[$constant] = array(
                    'defined' => true,
                    'value' => $value,
                    'type' => gettype($value),
                    'recommended' => $this->get_debug_recommendation($constant, $value)
                );
            } else {
                $results[$constant] = array(
                    'defined' => false,
                    'recommended' => $this->get_debug_recommendation($constant, null)
                );
            }
        }
        
        return $results;
    }
    
    /**
     * Sprawdza ustawienia bazy danych
     */
    private function check_database_settings($config_content) {
        $db_constants = array('DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST', 'DB_CHARSET', 'DB_COLLATE');
        $results = array();
        
        foreach ($db_constants as $constant) {
            if (defined($constant)) {
                $value = constant($constant);
                $results[$constant] = array(
                    'defined' => true,
                    'has_value' => !empty($value),
                    'length' => strlen($value)
                );
                
                // Nie pokazuj rzeczywistych wartości ze względów bezpieczeństwa
                if ($constant === 'DB_PASSWORD') {
                    $results[$constant]['masked_value'] = str_repeat('*', strlen($value));
                }
            } else {
                $results[$constant] = array('defined' => false);
            }
        }
        
        return $results;
    }
    
    /**
     * Sprawdza ustawienia bezpieczeństwa
     */
    private function check_security_settings($config_content) {
        $results = array();
        
        // Sprawdź czy SSL jest wymuszone
        $results['force_ssl'] = array(
            'FORCE_SSL_ADMIN' => defined('FORCE_SSL_ADMIN') ? constant('FORCE_SSL_ADMIN') : false
        );
        
        // Sprawdź prefiksy tabel
        $results['table_prefix'] = array(
            'value' => $GLOBALS['wpdb']->prefix ?? 'wp_',
            'is_default' => ($GLOBALS['wpdb']->prefix ?? 'wp_') === 'wp_',
            'recommendation' => 'Zmień domyślny prefiks "wp_" na własny dla lepszego bezpieczeństwa'
        );
        
        // Sprawdź czy edycja plików jest wyłączona
        $results['file_editing'] = array(
            'DISALLOW_FILE_EDIT' => defined('DISALLOW_FILE_EDIT') ? constant('DISALLOW_FILE_EDIT') : false,
            'recommendation' => 'Ustaw DISALLOW_FILE_EDIT na true aby wyłączyć edycję plików przez admin'
        );
        
        return $results;
    }
    
    /**
     * Zwraca rekomendacje dla ustawień debug
     */
    private function get_debug_recommendation($constant, $value) {
        $recommendations = array(
            'WP_DEBUG' => 'Włącz na środowisku deweloperskim, wyłącz na produkcji',
            'WP_DEBUG_LOG' => 'Włącz aby logować błędy do pliku',
            'WP_DEBUG_DISPLAY' => 'Wyłącz na produkcji aby nie wyświetlać błędów użytkownikom',
            'SCRIPT_DEBUG' => 'Włącz podczas developmentu aby używać nie-zminifikowanych skryptów'
        );
        
        return $recommendations[$constant] ?? 'Brak rekomendacji';
    }
    
    /**
     * Strona analizy wtyczek i motywów
     */
    public function plugins_themes_analysis_page() {
        ?>
        <div class="wrap">
            <h1>Analiza Wtyczek i Motywów</h1>
            <p>Sprawdza stan aktywnych wtyczek, motywów oraz potencjalne problemy</p>
            
            <?php
            $analysis = $this->analyze_plugins_and_themes();
            ?>
            
            <!-- Aktywne wtyczki -->
            <div class="card">
                <h2>Aktywne Wtyczki (<?php echo count($analysis['active_plugins']); ?>)</h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Nazwa</th>
                            <th>Wersja</th>
                            <th>Autor</th>
                            <th>Status aktualizacji</th>
                            <th>Opis</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($analysis['active_plugins'] as $plugin): ?>
                        <tr>
                            <td><strong><?php echo esc_html($plugin['name']); ?></strong></td>
                            <td><?php echo esc_html($plugin['version']); ?></td>
                            <td><?php echo esc_html($plugin['author']); ?></td>
                            <td>
                                <?php if ($plugin['update_available']): ?>
                                    <span class="dashicons dashicons-warning" style="color: orange;"></span> 
                                    Dostępna aktualizacja: <?php echo esc_html($plugin['new_version']); ?>
                                <?php else: ?>
                                    <span class="dashicons dashicons-yes" style="color: green;"></span> Aktualna
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html(wp_trim_words($plugin['description'], 15)); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Nieaktywne wtyczki -->
            <div class="card" style="margin-top: 20px;">
                <h2>Nieaktywne Wtyczki (<?php echo count($analysis['inactive_plugins']); ?>)</h2>
                <?php if (count($analysis['inactive_plugins']) > 0): ?>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Nazwa</th>
                            <th>Wersja</th>
                            <th>Ostatnia aktualizacja</th>
                            <th>Rekomendacja</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($analysis['inactive_plugins'] as $plugin): ?>
                        <tr>
                            <td><strong><?php echo esc_html($plugin['name']); ?></strong></td>
                            <td><?php echo esc_html($plugin['version']); ?></td>
                            <td><?php echo esc_html($plugin['last_updated']); ?></td>
                            <td>
                                <?php if ($plugin['old_plugin']): ?>
                                    <span style="color: red;">Rozważ usunięcie - nieaktualizowana > 2 lata</span>
                                <?php else: ?>
                                    <span style="color: green;">OK</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p>Brak nieaktywnych wtyczek - świetnie!</p>
                <?php endif; ?>
            </div>
            
            <!-- Aktywny motyw -->
            <div class="card" style="margin-top: 20px;">
                <h2>Aktywny Motyw</h2>
                <table class="widefat">
                    <tbody>
                        <tr>
                            <td><strong>Nazwa</strong></td>
                            <td><?php echo esc_html($analysis['active_theme']['name']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Wersja</strong></td>
                            <td><?php echo esc_html($analysis['active_theme']['version']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Autor</strong></td>
                            <td><?php echo esc_html($analysis['active_theme']['author']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Status aktualizacji</strong></td>
                            <td>
                                <?php if ($analysis['active_theme']['update_available']): ?>
                                    <span class="dashicons dashicons-warning" style="color: orange;"></span> 
                                    Dostępna aktualizacja
                                <?php else: ?>
                                    <span class="dashicons dashicons-yes" style="color: green;"></span> Aktualny
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Motyw potomny</strong></td>
                            <td>
                                <?php if ($analysis['active_theme']['is_child']): ?>
                                    <span class="dashicons dashicons-yes" style="color: green;"></span> Tak - Świetnie!
                                <?php else: ?>
                                    <span class="dashicons dashicons-warning" style="color: orange;"></span> 
                                    Nie - Rozważ utworzenie motywu potomnego
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Statystyki -->
            <div class="card" style="margin-top: 20px;">
                <h2>Podsumowanie</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <div style="background: #f0f8ff; padding: 15px; border-left: 4px solid #0073aa;">
                        <h3>Wtyczki wymagające aktualizacji</h3>
                        <h2 style="margin: 5px 0; color: #0073aa;"><?php echo $analysis['stats']['plugins_needing_updates']; ?></h2>
                    </div>
                    <div style="background: #fff8e1; padding: 15px; border-left: 4px solid #ff9800;">
                        <h3>Nieaktywne wtyczki</h3>
                        <h2 style="margin: 5px 0; color: #ff9800;"><?php echo $analysis['stats']['inactive_plugins']; ?></h2>
                    </div>
                    <div style="background: #f3e5f5; padding: 15px; border-left: 4px solid #9c27b0;">
                        <h3>Przestarzałe wtyczki</h3>
                        <h2 style="margin: 5px 0; color: #9c27b0;"><?php echo $analysis['stats']['outdated_plugins']; ?></h2>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 20px;">
                <button class="button button-primary" onclick="location.reload();">Odśwież analizę</button>
                <a href="<?php echo admin_url('admin.php?page=network-diagnostics'); ?>" class="button">Powrót do diagnostyki sieci</a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Sprawdza wydajność i limity PHP
     */
    public function php_performance_check_page() {
        ?>
        <div class="wrap">
            <h1>Sprawdzanie Wydajności i Limitów PHP</h1>
            <p>Analiza konfiguracji PHP, limitów i wydajności serwera</p>
            
            <?php
            $php_info = $this->get_php_performance_info();
            ?>
            
            <!-- Podstawowe informacje PHP -->
            <div class="card">
                <h2>Podstawowe Informacje PHP</h2>
                <table class="widefat">
                    <tbody>
                        <tr>
                            <td><strong>Wersja PHP</strong></td>
                            <td><?php echo esc_html($php_info['version']); ?></td>
                            <td>
                                <?php if (version_compare($php_info['version'], '7.4', '>=')): ?>
                                    <span class="dashicons dashicons-yes" style="color: green;"></span> Aktualna
                                <?php else: ?>
                                    <span class="dashicons dashicons-warning" style="color: orange;"></span> Przestarzała
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>SAPI</strong></td>
                            <td><?php echo esc_html($php_info['sapi']); ?></td>
                            <td>
                                <?php if ($php_info['sapi'] === 'fpm-fcgi' || $php_info['sapi'] === 'apache2handler'): ?>
                                    <span class="dashicons dashicons-yes" style="color: green;"></span> Wydajne
                                <?php else: ?>
                                    <span class="dashicons dashicons-info" style="color: blue;"></span> Standard
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>System operacyjny</strong></td>
                            <td colspan="2"><?php echo esc_html($php_info['os']); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Limity pamięci i czasu -->
            <div class="card" style="margin-top: 20px;">
                <h2>Limity i Konfiguracja</h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Ustawienie</th>
                            <th>Aktualna Wartość</th>
                            <th>Rekomendowana</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($php_info['limits'] as $setting => $data): ?>
                        <tr>
                            <td><code><?php echo esc_html($setting); ?></code></td>
                            <td><?php echo esc_html($data['current']); ?></td>
                            <td><?php echo esc_html($data['recommended']); ?></td>
                            <td>
                                <?php if ($data['status'] === 'good'): ?>
                                    <span class="dashicons dashicons-yes" style="color: green;"></span> OK
                                <?php elseif ($data['status'] === 'warning'): ?>
                                    <span class="dashicons dashicons-warning" style="color: orange;"></span> Do poprawy
                                <?php else: ?>
                                    <span class="dashicons dashicons-no" style="color: red;"></span> Krytyczne
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Rozszerzenia PHP -->
            <div class="card" style="margin-top: 20px;">
                <h2>Ważne Rozszerzenia PHP</h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Rozszerzenie</th>
                            <th>Status</th>
                            <th>Wersja</th>
                            <th>Opis</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($php_info['extensions'] as $ext => $data): ?>
                        <tr>
                            <td><code><?php echo esc_html($ext); ?></code></td>
                            <td>
                                <?php if ($data['loaded']): ?>
                                    <span class="dashicons dashicons-yes" style="color: green;"></span> Załadowane
                                <?php else: ?>
                                    <span class="dashicons dashicons-no" style="color: red;"></span> Brak
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($data['version']); ?></td>
                            <td><?php echo esc_html($data['description']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Test wydajności -->
            <div class="card" style="margin-top: 20px;">
                <h2>Test Wydajności</h2>
                <p><em>Wykonuje podstawowe testy wydajności serwera</em></p>
                
                <form method="post">
                    <input type="hidden" name="run_performance_test" value="1">
                    <?php wp_nonce_field('performance_test', 'performance_nonce'); ?>
                    <input type="submit" class="button button-primary" value="Uruchom Test Wydajności">
                </form>
                
                <?php if (isset($_POST['run_performance_test']) && check_admin_referer('performance_test', 'performance_nonce')): ?>
                    <?php $perf_results = $this->run_performance_test(); ?>
                    <div style="margin-top: 20px;">
                        <h3>Wyniki Testu Wydajności</h3>
                        <table class="widefat">
                            <tbody>
                                <tr>
                                    <td><strong>Test matematyczny</strong></td>
                                    <td><?php echo esc_html($perf_results['math_test']); ?> ms</td>
                                    <td>
                                        <?php if ($perf_results['math_test'] < 100): ?>
                                            <span style="color: green;">Bardzo dobra</span>
                                        <?php elseif ($perf_results['math_test'] < 300): ?>
                                            <span style="color: orange;">Dobra</span>
                                        <?php else: ?>
                                            <span style="color: red;">Słaba</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Test I/O (zapis/odczyt pliku)</strong></td>
                                    <td><?php echo esc_html($perf_results['io_test']); ?> ms</td>
                                    <td>
                                        <?php if ($perf_results['io_test'] < 50): ?>
                                            <span style="color: green;">Bardzo dobra</span>
                                        <?php elseif ($perf_results['io_test'] < 150): ?>
                                            <span style="color: orange;">Dobra</span>
                                        <?php else: ?>
                                            <span style="color: red;">Słaba</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Test alokacji pamięci</strong></td>
                                    <td><?php echo esc_html($perf_results['memory_test']); ?> ms</td>
                                    <td>
                                        <?php if ($perf_results['memory_test'] < 30): ?>
                                            <span style="color: green;">Bardzo dobra</span>
                                        <?php elseif ($perf_results['memory_test'] < 100): ?>
                                            <span style="color: orange;">Dobra</span>
                                        <?php else: ?>
                                            <span style="color: red;">Słaba</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <div style="margin-top: 20px;">
                <button class="button button-primary" onclick="location.reload();">Odśwież sprawdzenie</button>
                <a href="<?php echo admin_url('admin.php?page=network-diagnostics'); ?>" class="button">Powrót do diagnostyki sieci</a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Pobiera informacje o wydajności PHP
     */
    private function get_php_performance_info() {
        $info = array();
        
        // Podstawowe informacje
        $info['version'] = phpversion();
        $info['sapi'] = php_sapi_name();
        $info['os'] = php_uname();
        
        // Limity i konfiguracja
        $info['limits'] = array();
        
        $limits_config = array(
            'memory_limit' => array('recommended' => '256M', 'min' => '128M'),
            'max_execution_time' => array('recommended' => '300', 'min' => '60'),
            'max_input_vars' => array('recommended' => '3000', 'min' => '1000'),
            'upload_max_filesize' => array('recommended' => '64M', 'min' => '8M'),
            'post_max_size' => array('recommended' => '64M', 'min' => '8M'),
            'max_file_uploads' => array('recommended' => '20', 'min' => '10')
        );
        
        foreach ($limits_config as $setting => $config) {
            $current = ini_get($setting);
            $current_bytes = $this->convert_to_bytes($current);
            $recommended_bytes = $this->convert_to_bytes($config['recommended']);
            $min_bytes = $this->convert_to_bytes($config['min']);
            
            if ($current_bytes >= $recommended_bytes) {
                $status = 'good';
            } elseif ($current_bytes >= $min_bytes) {
                $status = 'warning';
            } else {
                $status = 'critical';
            }
            
            $info['limits'][$setting] = array(
                'current' => $current,
                'recommended' => $config['recommended'],
                'status' => $status
            );
        }
        
        // Ważne rozszerzenia
        $important_extensions = array(
            'curl' => 'Komunikacja HTTP/HTTPS',
            'gd' => 'Przetwarzanie obrazów',
            'mbstring' => 'Obsługa wielu kodowań',
            'xml' => 'Parsowanie XML',
            'zip' => 'Obsługa archiwów ZIP',
            'json' => 'Obsługa JSON',
            'mysqli' => 'Połączenia z bazą MySQL',
            'openssl' => 'Szyfrowanie SSL/TLS',
            'imagick' => 'Zaawansowane przetwarzanie obrazów',
            'opcache' => 'Buforowanie opcodes PHP'
        );
        
        $info['extensions'] = array();
        foreach ($important_extensions as $ext => $description) {
            $loaded = extension_loaded($ext);
            $version = $loaded ? phpversion($ext) : 'N/A';
            
            $info['extensions'][$ext] = array(
                'loaded' => $loaded,
                'version' => $version ?: 'N/A',
                'description' => $description
            );
        }
        
        return $info;
    }
    
    /**
     * Konwertuje wartości PHP na bajty
     */
    private function convert_to_bytes($value) {
        if (is_numeric($value)) {
            return (int) $value;
        }
        
        $unit = strtolower(substr($value, -1));
        $number = (int) substr($value, 0, -1);
        
        switch ($unit) {
            case 'g':
                $number *= 1024;
            case 'm':
                $number *= 1024;
            case 'k':
                $number *= 1024;
        }
        
        return $number;
    }
    
    /**
     * Wykonuje test wydajności
     */
    private function run_performance_test() {
        $results = array();
        
        // Test matematyczny
        $start = microtime(true);
        for ($i = 0; $i < 1000000; $i++) {
            sqrt($i);
        }
        $results['math_test'] = round((microtime(true) - $start) * 1000, 2);
        
        // Test I/O
        $start = microtime(true);
        $test_file = sys_get_temp_dir() . '/wp_performance_test.txt';
        $test_data = str_repeat('WordPress Performance Test ', 1000);
        file_put_contents($test_file, $test_data);
        $read_data = file_get_contents($test_file);
        unlink($test_file);
        $results['io_test'] = round((microtime(true) - $start) * 1000, 2);
        
        // Test pamięci
        $start = microtime(true);
        $test_array = array();
        for ($i = 0; $i < 100000; $i++) {
            $test_array[] = 'test_' . $i;
        }
        unset($test_array);
        $results['memory_test'] = round((microtime(true) - $start) * 1000, 2);
        
        return $results;
    }
    
    /**
     * Analizuje wtyczki i motywy
     */
    private function analyze_plugins_and_themes() {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', array());
        $plugin_updates = get_site_transient('update_plugins');
        
        $analysis = array(
            'active_plugins' => array(),
            'inactive_plugins' => array(),
            'active_theme' => array(),
            'stats' => array(
                'plugins_needing_updates' => 0,
                'inactive_plugins' => 0,
                'outdated_plugins' => 0
            )
        );
        
        // Analizuj wtyczki
        foreach ($all_plugins as $plugin_file => $plugin_data) {
            $is_active = in_array($plugin_file, $active_plugins);
            $update_available = isset($plugin_updates->response[$plugin_file]);
            $new_version = $update_available ? $plugin_updates->response[$plugin_file]->new_version : '';
            
            $plugin_info = array(
                'name' => $plugin_data['Name'],
                'version' => $plugin_data['Version'],
                'author' => strip_tags($plugin_data['Author']),
                'description' => $plugin_data['Description'],
                'update_available' => $update_available,
                'new_version' => $new_version,
                'last_updated' => $this->get_plugin_last_updated($plugin_file),
                'old_plugin' => $this->is_plugin_old($plugin_file)
            );
            
            if ($is_active) {
                $analysis['active_plugins'][] = $plugin_info;
                if ($update_available) {
                    $analysis['stats']['plugins_needing_updates']++;
                }
            } else {
                $analysis['inactive_plugins'][] = $plugin_info;
                $analysis['stats']['inactive_plugins']++;
                if ($plugin_info['old_plugin']) {
                    $analysis['stats']['outdated_plugins']++;
                }
            }
        }
        
        // Analizuj aktywny motyw
        $current_theme = wp_get_theme();
        $theme_updates = get_site_transient('update_themes');
        $theme_update_available = isset($theme_updates->response[$current_theme->get_stylesheet()]);
        
        $analysis['active_theme'] = array(
            'name' => $current_theme->get('Name'),
            'version' => $current_theme->get('Version'),
            'author' => strip_tags($current_theme->get('Author')),
            'update_available' => $theme_update_available,
            'is_child' => $current_theme->parent() !== false
        );
        
        return $analysis;
    }
    
    /**
     * Sprawdza kiedy wtyczka była ostatnio aktualizowana
     */
    private function get_plugin_last_updated($plugin_file) {
        $plugin_path = WP_PLUGIN_DIR . '/' . dirname($plugin_file);
        if (is_dir($plugin_path)) {
            return date('Y-m-d', filemtime($plugin_path));
        }
        return 'Nieznana';
    }
    
    /**
     * Sprawdza czy wtyczka jest przestarzała (nie aktualizowana > 2 lata)
     */
    private function is_plugin_old($plugin_file) {
        $plugin_path = WP_PLUGIN_DIR . '/' . dirname($plugin_file);
        if (is_dir($plugin_path)) {
            $last_modified = filemtime($plugin_path);
            $two_years_ago = strtotime('-2 years');
            return $last_modified < $two_years_ago;
        }
        return false;
    }

    /**
     * Aktywacja pluginu
     */
    public static function activate() {
        // Sprawdź wymagania
        if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
            deactivate_plugins( plugin_basename( __FILE__ ) );
            wp_die( 'Ten plugin wymaga PHP 7.4 lub nowszej wersji.' );
        }
        
        if ( version_compare( get_bloginfo( 'version' ), '5.0', '<' ) ) {
            deactivate_plugins( plugin_basename( __FILE__ ) );
            wp_die( 'Ten plugin wymaga WordPress 5.0 lub nowszej wersji.' );
        }
        
        // Utwórz tabelę historii
        global $wpdb;
        $table_name = $wpdb->prefix . 'network_diagnostics_history';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            test_date datetime DEFAULT CURRENT_TIMESTAMP,
            host varchar(255) NOT NULL,
            test_type varchar(50) NOT NULL,
            results text NOT NULL,
            PRIMARY KEY  (id)
        )";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
        
        // Zapisz informację o aktywacji
        add_option( 'wp_diagnostics_activated', time() );
    }

    /**
     * Deaktywacja pluginu
     */
    public static function deactivate() {
        // Wyczyść opcje tymczasowe
        delete_option( 'wp_diagnostics_activated' );
        delete_transient( 'wp_diagnostics_cache' );
    }

    /**
     * Sprawdza czy wszystkie callback funkcje istnieją
     */
    public function verify_callbacks() {
        $callbacks = array(
            'nd_admin_page',
            'wp_config_check_page',
            'plugins_themes_analysis_page', 
            'php_performance_check_page'
        );
        
        foreach ( $callbacks as $callback ) {
            if ( ! method_exists( $this, $callback ) ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'WordPress Diagnostics: Brak metody ' . $callback );
                }
                return false;
            }
        }
        return true;
    }
}

// Inicjalizacja pluginu.
new NetworkDiagnosticsPlugin();