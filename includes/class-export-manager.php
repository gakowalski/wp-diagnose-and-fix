<?php
/**
 * Klasa do eksportu wyników diagnostyki
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_Diagnostics_Export_Manager {
    
    /**
     * Konstruktor
     */
    public function __construct() {
        add_action( 'wp_ajax_wp_diagnostics_export', array( $this, 'handle_export_ajax' ) );
    }
    
    /**
     * Obsłuż AJAX request dla eksportu
     */
    public function handle_export_ajax() {
        // Sprawdź uprawnienia i nonce
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Brak uprawnień', 'wp-diagnostics' ) );
        }
        
        check_ajax_referer( 'wp_diagnostics_export', 'nonce' );
        
        $format = sanitize_text_field( $_POST['format'] ?? 'json' );
        $data_type = sanitize_text_field( $_POST['data_type'] ?? 'all' );
        
        $data = $this->prepare_export_data( $data_type );
        
        switch ( $format ) {
            case 'pdf':
                $this->export_to_pdf( $data );
                break;
            case 'csv':
                $this->export_to_csv( $data );
                break;
            case 'json':
            default:
                $this->export_to_json( $data );
                break;
        }
        
        wp_die();
    }
    
    /**
     * Przygotuj dane do eksportu
     */
    private function prepare_export_data( $data_type = 'all' ) {
        $plugin = wp_diagnostics();
        $data = array(
            'timestamp' => current_time( 'mysql' ),
            'site_url' => get_site_url(),
            'site_name' => get_bloginfo( 'name' ),
            'wp_version' => get_bloginfo( 'version' )
        );
        
        switch ( $data_type ) {
            case 'network':
                $data['network_tests'] = $this->get_network_test_data();
                break;
            case 'config':
                $data['config_check'] = $this->get_config_check_data();
                break;
            case 'php':
                $data['php_check'] = $this->get_php_check_data();
                break;
            case 'plugins':
                $data['plugins_analysis'] = $this->get_plugins_analysis_data();
                break;
            case 'all':
            default:
                $data['network_tests'] = $this->get_network_test_data();
                $data['config_check'] = $this->get_config_check_data();
                $data['php_check'] = $this->get_php_check_data();
                $data['plugins_analysis'] = $this->get_plugins_analysis_data();
                break;
        }
        
        return $data;
    }
    
    /**
     * Pobierz dane testów sieciowych
     */
    private function get_network_test_data() {
        $plugin = wp_diagnostics();
        
        if ( ! $plugin->network_tests ) {
            return array();
        }
        
        // Tutaj pobierzemy dane z sesji lub cache
        $data = get_transient( 'wp_diagnostics_network_results' );
        
        if ( ! $data ) {
            $data = array(
                'note' => 'Brak ostatnich wyników testów. Uruchom testy sieciowe aby uzyskać dane.'
            );
        }
        
        return $data;
    }
    
    /**
     * Pobierz dane sprawdzania konfiguracji
     */
    private function get_config_check_data() {
        $plugin = wp_diagnostics();
        
        if ( ! $plugin->config_checker ) {
            return array();
        }
        
        return array(
            'wp_config' => $plugin->config_checker->check_wp_config(),
            'database' => $plugin->config_checker->check_database_config(),
            'security' => $plugin->config_checker->check_security_config(),
            'debug' => $plugin->config_checker->check_debug_config(),
            'file_permissions' => $plugin->config_checker->check_file_permissions()
        );
    }
    
    /**
     * Pobierz dane sprawdzania PHP
     */
    private function get_php_check_data() {
        $plugin = wp_diagnostics();
        
        if ( ! $plugin->php_checker ) {
            return array();
        }
        
        return array(
            'configuration' => $plugin->php_checker->check_php_configuration(),
            'extensions' => $plugin->php_checker->check_php_extensions(),
            'issues' => $plugin->php_checker->check_php_issues(),
            'info' => $plugin->php_checker->get_php_info()
        );
    }
    
    /**
     * Pobierz dane analizy wtyczek
     */
    private function get_plugins_analysis_data() {
        $plugin = wp_diagnostics();
        
        if ( ! $plugin->plugins_analyzer ) {
            return array();
        }
        
        return array(
            'active_plugins' => $plugin->plugins_analyzer->analyze_active_plugins(),
            'active_theme' => $plugin->plugins_analyzer->analyze_active_theme(),
            'issues' => $plugin->plugins_analyzer->check_plugin_issues()
        );
    }
    
    /**
     * Eksportuj do JSON
     */
    private function export_to_json( $data ) {
        $filename = 'wp-diagnostics-' . date( 'Y-m-d-H-i-s' ) . '.json';
        
        header( 'Content-Type: application/json' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Cache-Control: no-cache, must-revalidate' );
        
        echo json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
    }
    
    /**
     * Eksportuj do CSV
     */
    private function export_to_csv( $data ) {
        $filename = 'wp-diagnostics-' . date( 'Y-m-d-H-i-s' ) . '.csv';
        
        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Cache-Control: no-cache, must-revalidate' );
        
        $output = fopen( 'php://output', 'w' );
        
        // Nagłówki CSV
        fputcsv( $output, array( 'Kategoria', 'Klucz', 'Wartość', 'Status' ) );
        
        // Przekształć dane do formatu CSV
        $this->array_to_csv( $data, $output );
        
        fclose( $output );
    }
    
    /**
     * Przekształć tablicę do CSV
     */
    private function array_to_csv( $array, $output, $prefix = '' ) {
        foreach ( $array as $key => $value ) {
            $current_key = $prefix ? $prefix . '.' . $key : $key;
            
            if ( is_array( $value ) ) {
                if ( $this->is_indexed_array( $value ) ) {
                    // Tablica indeksowana - każdy element w osobnej linii
                    foreach ( $value as $index => $item ) {
                        if ( is_array( $item ) ) {
                            $this->array_to_csv( $item, $output, $current_key . '[' . $index . ']' );
                        } else {
                            fputcsv( $output, array( $current_key, $index, $item, '' ) );
                        }
                    }
                } else {
                    // Tablica asocjacyjna - rekurencyjnie
                    $this->array_to_csv( $value, $output, $current_key );
                }
            } else {
                // Wartość scalarna
                $status = '';
                if ( $key === 'status' || $key === 'result' || $key === 'success' ) {
                    $status = $value ? 'OK' : 'PROBLEM';
                }
                fputcsv( $output, array( $current_key, '', $value, $status ) );
            }
        }
    }
    
    /**
     * Sprawdź czy tablica jest indeksowana
     */
    private function is_indexed_array( $array ) {
        if ( ! is_array( $array ) ) {
            return false;
        }
        
        return array_keys( $array ) === range( 0, count( $array ) - 1 );
    }
    
    /**
     * Eksportuj do PDF
     */
    private function export_to_pdf( $data ) {
        // Dla PDF będziemy generować HTML i konwertować
        $html = $this->generate_pdf_html( $data );
        
        // Jeśli dostępna jest biblioteka do PDF, użyj jej
        // W przeciwnym razie wyślij HTML
        if ( $this->can_generate_pdf() ) {
            $this->generate_pdf_from_html( $html );
        } else {
            $this->export_as_html( $html );
        }
    }
    
    /**
     * Sprawdź czy można generować PDF
     */
    private function can_generate_pdf() {
        // Sprawdź czy dostępne są biblioteki do PDF
        return class_exists( 'TCPDF' ) || class_exists( 'mPDF' ) || class_exists( 'FPDF' );
    }
    
    /**
     * Generuj HTML dla PDF
     */
    private function generate_pdf_html( $data ) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>WordPress Diagnostics Report</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h1, h2, h3 { color: #333; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f5f5f5; }
                .status-ok { color: green; font-weight: bold; }
                .status-warning { color: orange; font-weight: bold; }
                .status-error { color: red; font-weight: bold; }
                .section { margin-bottom: 30px; page-break-inside: avoid; }
            </style>
        </head>
        <body>
            <h1>WordPress Diagnostics Report</h1>
            
            <div class="section">
                <h2>Informacje ogólne</h2>
                <table>
                    <tr><td><strong>Data generowania:</strong></td><td><?php echo esc_html( $data['timestamp'] ); ?></td></tr>
                    <tr><td><strong>URL strony:</strong></td><td><?php echo esc_html( $data['site_url'] ); ?></td></tr>
                    <tr><td><strong>Nazwa strony:</strong></td><td><?php echo esc_html( $data['site_name'] ); ?></td></tr>
                    <tr><td><strong>Wersja WordPress:</strong></td><td><?php echo esc_html( $data['wp_version'] ); ?></td></tr>
                </table>
            </div>
            
            <?php if ( isset( $data['config_check'] ) ): ?>
            <div class="section">
                <h2>Sprawdzenie konfiguracji</h2>
                <?php $this->render_data_table( $data['config_check'] ); ?>
            </div>
            <?php endif; ?>
            
            <?php if ( isset( $data['php_check'] ) ): ?>
            <div class="section">
                <h2>Sprawdzenie PHP</h2>
                <?php $this->render_data_table( $data['php_check'] ); ?>
            </div>
            <?php endif; ?>
            
            <?php if ( isset( $data['plugins_analysis'] ) ): ?>
            <div class="section">
                <h2>Analiza wtyczek</h2>
                <?php $this->render_data_table( $data['plugins_analysis'] ); ?>
            </div>
            <?php endif; ?>
            
            <?php if ( isset( $data['network_tests'] ) ): ?>
            <div class="section">
                <h2>Testy sieciowe</h2>
                <?php $this->render_data_table( $data['network_tests'] ); ?>
            </div>
            <?php endif; ?>
            
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Renderuj tabelę danych
     */
    private function render_data_table( $data, $level = 0 ) {
        if ( ! is_array( $data ) ) {
            echo '<p>' . esc_html( $data ) . '</p>';
            return;
        }
        
        echo '<table>';
        foreach ( $data as $key => $value ) {
            echo '<tr>';
            echo '<td><strong>' . esc_html( $key ) . '</strong></td>';
            
            if ( is_array( $value ) ) {
                echo '<td>';
                if ( $level < 2 ) { // Ogranicz głębokość zagnieżdżenia
                    $this->render_data_table( $value, $level + 1 );
                } else {
                    echo '<pre>' . esc_html( print_r( $value, true ) ) . '</pre>';
                }
                echo '</td>';
            } else {
                $class = '';
                if ( is_bool( $value ) ) {
                    $class = $value ? 'status-ok' : 'status-error';
                    $value = $value ? 'TAK' : 'NIE';
                }
                echo '<td class="' . $class . '">' . esc_html( $value ) . '</td>';
            }
            echo '</tr>';
        }
        echo '</table>';
    }
    
    /**
     * Eksportuj jako HTML
     */
    private function export_as_html( $html ) {
        $filename = 'wp-diagnostics-' . date( 'Y-m-d-H-i-s' ) . '.html';
        
        header( 'Content-Type: text/html' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Cache-Control: no-cache, must-revalidate' );
        
        echo $html;
    }
    
    /**
     * Generuj PDF z HTML (placeholder - wymaga biblioteki PDF)
     */
    private function generate_pdf_from_html( $html ) {
        // TODO: Implementacja z użyciem TCPDF, mPDF lub podobnej biblioteki
        // Na razie eksportujemy jako HTML
        $this->export_as_html( $html );
    }
    
    /**
     * Zapisz wyniki do historii
     */
    public function save_to_history( $test_type, $results, $host = '' ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wp_diagnostics_history';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'test_date' => current_time( 'mysql' ),
                'host' => $host,
                'test_type' => $test_type,
                'results' => json_encode( $results ),
                'user_id' => get_current_user_id()
            ),
            array( '%s', '%s', '%s', '%s', '%d' )
        );
        
        // Ogranicz historię do maksymalnej liczby wpisów
        $settings = wp_diagnostics()->get_settings();
        $max_entries = $settings['max_history_entries'] ?? 1000;
        
        $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
        
        if ( $count > $max_entries ) {
            $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$table_name} WHERE id NOT IN (
                    SELECT id FROM (
                        SELECT id FROM {$table_name} ORDER BY test_date DESC LIMIT %d
                    ) AS temp
                )",
                $max_entries
            ) );
        }
        
        return $result !== false;
    }
    
    /**
     * Pobierz historię testów
     */
    public function get_test_history( $limit = 50, $test_type = null ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wp_diagnostics_history';
        
        $where = '';
        $params = array();
        
        if ( $test_type ) {
            $where = 'WHERE test_type = %s';
            $params[] = $test_type;
        }
        
        $params[] = $limit;
        
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table_name} {$where} ORDER BY test_date DESC LIMIT %d",
            ...$params
        ) );
        
        // Dekoduj JSON wyników
        foreach ( $results as &$result ) {
            $result->results = json_decode( $result->results, true );
        }
        
        return $results;
    }
}
