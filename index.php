<?php
/*
Plugin Name: Network Diagnostics Tool
Description: Zaawansowane narzędzie do diagnostyki sieci dla WordPress. Wykonuje kompleksowe testy sieciowe 
            włączając ping, traceroute, testy SSL/TLS, analizę DNS, skanowanie portów, wykrywanie WAF, 
            testy MTU oraz SMTP. Umożliwia porównanie wyników testów wykonanych po stronie serwera i klienta, 
            eksport raportów do PDF/CSV oraz przechowywanie historii testów.
Version: 3.0
Author: Grzegorz Kowalski
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
        // Dodajemy stronę w menu administratora.
        add_action( 'admin_menu', array( $this, 'nd_add_admin_menu' ) );
        add_action('admin_enqueue_scripts', array($this, 'nd_enqueue_scripts'));
        add_action('init', array($this, 'create_history_table'));
        add_action('wp_ajax_export_results', array($this, 'handle_export'));
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
        if ('toplevel_page_network-diagnostics' !== $hook) {
            return;
        }
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
                
                addressInput.addEventListener("change", async function() {
                    const results = await diagnostics.performTests(this.value);
                    document.querySelector("#client-results").innerHTML = `
                        <h3>Wyniki testów klienckich:</h3>
                        <pre>${JSON.stringify(results, null, 2)}</pre>
                    `;
                });
            });
        ');
    }

    public function nd_add_admin_menu() {
        add_menu_page(
            'Diagnostyka Sieci',      // Tytuł strony
            'Diagnostyka Sieci',      // Tytuł menu
            'manage_options',         // Uprawnienia
            'network-diagnostics',    // Slug menu
            array( $this, 'nd_admin_page' ), // Funkcja wyświetlająca stronę
            'dashicons-admin-generic',// Ikona
            80                        // Pozycja w menu
        );
    }

    public function nd_admin_page() {
        ?>
        <div class="wrap">
            <h1>Diagnostyka Sieci</h1>
            <p>Wprowadź adres IP lub nazwę domeny, aby przetestować połączenie sieciowe.</p>
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
                $address = sanitize_text_field( $_POST['address'] );
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
        </div>
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
            '/var/log/httpd/error_log',
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
}

// Inicjalizacja pluginu.
new NetworkDiagnosticsPlugin();