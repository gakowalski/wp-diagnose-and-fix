<?php
/**
 * Klasa obsługująca interfejs administratora
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_Diagnostics_Admin_Interface {
    
    /**
     * Konstruktor
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_export_results', array( $this, 'handle_export' ) );
    }
    
    /**
     * Dodaj menu w panelu administratora
     */
    public function add_admin_menu() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        // Główne menu
        add_menu_page(
            __( 'WordPress Diagnostics', 'wp-diagnostics' ),
            __( 'Diagnostyka WP', 'wp-diagnostics' ),
            'manage_options',
            'wp-diagnostics',
            array( $this, 'network_tests_page' ),
            'dashicons-admin-tools',
            75
        );
        
        // Submenu - Testy sieciowe
        add_submenu_page(
            'wp-diagnostics',
            __( 'Testy Sieciowe', 'wp-diagnostics' ),
            __( 'Testy Sieciowe', 'wp-diagnostics' ),
            'manage_options',
            'wp-diagnostics',
            array( $this, 'network_tests_page' )
        );
        
        // Submenu - Konfiguracja WordPress
        add_submenu_page(
            'wp-diagnostics',
            __( 'Konfiguracja WordPress', 'wp-diagnostics' ),
            __( 'Konfiguracja WP', 'wp-diagnostics' ),
            'manage_options',
            'wp-diagnostics-config',
            array( $this, 'config_check_page' )
        );
        
        // Submenu - Analiza wtyczek i motywów
        add_submenu_page(
            'wp-diagnostics',
            __( 'Analiza Wtyczek i Motywów', 'wp-diagnostics' ),
            __( 'Wtyczki i Motywy', 'wp-diagnostics' ),
            'manage_options',
            'wp-diagnostics-plugins',
            array( $this, 'plugins_analysis_page' )
        );
        
        // Submenu - Wydajność PHP
        add_submenu_page(
            'wp-diagnostics',
            __( 'Wydajność i Limity PHP', 'wp-diagnostics' ),
            __( 'Wydajność PHP', 'wp-diagnostics' ),
            'manage_options',
            'wp-diagnostics-php',
            array( $this, 'php_performance_page' )
        );
    }
    
    /**
     * Załaduj skrypty i style
     */
    public function enqueue_scripts( $hook ) {
        if ( strpos( $hook, 'wp-diagnostics' ) === false ) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'wp-diagnostics-admin',
            WP_DIAGNOSTICS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WP_DIAGNOSTICS_VERSION
        );
        
        // JavaScript
        if ( $hook === 'toplevel_page_wp-diagnostics' ) {
            wp_enqueue_script(
                'wp-diagnostics-network',
                WP_DIAGNOSTICS_PLUGIN_URL . 'assets/js/network-tests.js',
                array( 'jquery' ),
                WP_DIAGNOSTICS_VERSION,
                true
            );
            
            wp_localize_script( 'wp-diagnostics-network', 'wpDiagnostics', array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'wp_diagnostics_nonce' ),
                'strings' => array(
                    'running' => __( 'Uruchamianie testów...', 'wp-diagnostics' ),
                    'error' => __( 'Wystąpił błąd podczas wykonywania testów.', 'wp-diagnostics' )
                )
            ) );
        }
    }
    
    /**
     * Strona testów sieciowych
     */
    public function network_tests_page() {
        $network_tests = wp_diagnostics()->network_tests;
        
        ?>
        <div class="wrap wp-diagnostics-wrap">
            <h1><?php _e( 'WordPress Diagnostics - Testy Sieciowe', 'wp-diagnostics' ); ?></h1>
            
            <div class="wp-diagnostics-intro">
                <p><?php _e( 'Wprowadź adres IP lub nazwę domeny, aby przetestować połączenie sieciowe i przeprowadzić kompleksową analizę.', 'wp-diagnostics' ); ?></p>
            </div>
            
            <form method="post" id="wp-diagnostics-form" class="wp-diagnostics-form">
                <?php wp_nonce_field( 'wp_diagnostics_tests', 'wp_diagnostics_nonce' ); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="target_host"><?php _e( 'Adres IP lub nazwa domeny', 'wp-diagnostics' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="target_host" name="target_host" 
                                   value="<?php echo esc_attr( $_POST['target_host'] ?? '' ); ?>" 
                                   class="regular-text" required />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="target_port"><?php _e( 'Port', 'wp-diagnostics' ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="target_port" name="target_port" 
                                   min="1" max="65535" 
                                   value="<?php echo esc_attr( $_POST['target_port'] ?? '80' ); ?>" 
                                   class="small-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e( 'Wybierz testy', 'wp-diagnostics' ); ?></th>
                        <td>
                            <fieldset>
                                <label><input type="checkbox" name="tests[]" value="ping" checked /> <?php _e( 'Test Ping', 'wp-diagnostics' ); ?></label><br />
                                <label><input type="checkbox" name="tests[]" value="traceroute" /> <?php _e( 'Traceroute', 'wp-diagnostics' ); ?></label><br />
                                <label><input type="checkbox" name="tests[]" value="dns" /> <?php _e( 'Test DNS', 'wp-diagnostics' ); ?></label><br />
                                <label><input type="checkbox" name="tests[]" value="ssl" /> <?php _e( 'Test SSL/TLS', 'wp-diagnostics' ); ?></label><br />
                                <label><input type="checkbox" name="tests[]" value="ports" /> <?php _e( 'Skanowanie portów', 'wp-diagnostics' ); ?></label><br />
                                <label><input type="checkbox" name="tests[]" value="smtp" /> <?php _e( 'Test SMTP', 'wp-diagnostics' ); ?></label><br />
                                <label><input type="checkbox" name="tests[]" value="mtu" /> <?php _e( 'Test MTU', 'wp-diagnostics' ); ?></label><br />
                                <label><input type="checkbox" name="tests[]" value="security" /> <?php _e( 'Skanowanie zabezpieczeń', 'wp-diagnostics' ); ?></label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button( __( 'Uruchom Testy', 'wp-diagnostics' ), 'primary', 'run_tests' ); ?>
            </form>
            
            <div id="test-results" class="wp-diagnostics-results">
                <?php
                if ( isset( $_POST['run_tests'] ) && wp_verify_nonce( $_POST['wp_diagnostics_nonce'], 'wp_diagnostics_tests' ) ) {
                    $this->display_test_results();
                }
                ?>
            </div>
            
            <?php $this->render_quick_links(); ?>
        </div>
        <?php
    }
    
    /**
     * Strona sprawdzania konfiguracji
     */
    public function config_check_page() {
        $config_checker = wp_diagnostics()->config_checker;
        
        ?>
        <div class="wrap wp-diagnostics-wrap">
            <h1><?php _e( 'Sprawdzanie Konfiguracji WordPress', 'wp-diagnostics' ); ?></h1>
            
            <div class="wp-diagnostics-intro">
                <p><?php _e( 'Sprawdza kluczowe elementy konfiguracji WordPress w pliku wp-config.php oraz inne ustawienia bezpieczeństwa.', 'wp-diagnostics' ); ?></p>
            </div>
            
            <?php
            $config_results = $config_checker->check_configuration();
            $this->render_config_results( $config_results );
            ?>
            
            <?php $this->render_quick_links(); ?>
        </div>
        <?php
    }
    
    /**
     * Strona analizy wtyczek
     */
    public function plugins_analysis_page() {
        $plugins_analyzer = wp_diagnostics()->plugins_analyzer;
        
        ?>
        <div class="wrap wp-diagnostics-wrap">
            <h1><?php _e( 'Analiza Wtyczek i Motywów', 'wp-diagnostics' ); ?></h1>
            
            <div class="wp-diagnostics-intro">
                <p><?php _e( 'Sprawdza stan aktywnych wtyczek, motywów oraz identyfikuje potencjalne problemy bezpieczeństwa i wydajności.', 'wp-diagnostics' ); ?></p>
            </div>
            
            <?php
            $analysis_results = $plugins_analyzer->analyze();
            $this->render_plugins_results( $analysis_results );
            ?>
            
            <?php $this->render_quick_links(); ?>
        </div>
        <?php
    }
    
    /**
     * Strona wydajności PHP
     */
    public function php_performance_page() {
        $php_checker = wp_diagnostics()->php_checker;
        
        ?>
        <div class="wrap wp-diagnostics-wrap">
            <h1><?php _e( 'Wydajność i Limity PHP', 'wp-diagnostics' ); ?></h1>
            
            <div class="wp-diagnostics-intro">
                <p><?php _e( 'Analiza konfiguracji PHP, limitów, rozszerzeń i wydajności serwera.', 'wp-diagnostics' ); ?></p>
            </div>
            
            <?php
            $php_results = array(
                'info' => $php_checker->get_php_info(),
                'configuration' => $php_checker->check_php_configuration(),
                'extensions' => $php_checker->check_php_extensions(),
                'issues' => $php_checker->check_php_issues(),
                'performance' => $php_checker->run_performance_test(),
                'errors' => $php_checker->check_php_errors()
            );
            $this->render_php_results( $php_results );
            ?>
            
            <?php $this->render_quick_links(); ?>
        </div>
        <?php
    }
    
    /**
     * Wyświetl wyniki testów sieciowych
     */
    private function display_test_results() {
        $target_host = sanitize_text_field( $_POST['target_host'] );
        $target_port = intval( $_POST['target_port'] );
        $tests = $_POST['tests'] ?? array();
        
        if ( empty( $target_host ) || empty( $tests ) ) {
            echo '<div class="notice notice-error"><p>' . __( 'Proszę podać poprawny adres hosta i wybrać co najmniej jeden test.', 'wp-diagnostics' ) . '</p></div>';
            return;
        }
        
        echo '<h2>' . sprintf( __( 'Wyniki testów dla: %s', 'wp-diagnostics' ), esc_html( $target_host ) ) . '</h2>';
        
        $network_tests = wp_diagnostics()->network_tests;
        
        foreach ( $tests as $test_type ) {
            switch ( $test_type ) {
                case 'ping':
                    echo $this->render_test_result( __( 'Test Ping', 'wp-diagnostics' ), $network_tests->ping_test( $target_host, $target_port ) );
                    break;
                case 'traceroute':
                    echo $this->render_test_result( __( 'Traceroute', 'wp-diagnostics' ), $network_tests->traceroute_test( $target_host ) );
                    break;
                case 'dns':
                    echo $this->render_test_result( __( 'Test DNS', 'wp-diagnostics' ), $network_tests->dns_test( $target_host ) );
                    break;
                case 'ssl':
                    echo $this->render_test_result( __( 'Test SSL/TLS', 'wp-diagnostics' ), $network_tests->ssl_test( $target_host ) );
                    break;
                case 'ports':
                    echo $this->render_test_result( __( 'Skanowanie portów', 'wp-diagnostics' ), $network_tests->port_scan( $target_host ) );
                    break;
                case 'smtp':
                    echo $this->render_test_result( __( 'Test SMTP', 'wp-diagnostics' ), $network_tests->smtp_test( $target_host, 25 ) );
                    break;
                case 'mtu':
                    echo $this->render_test_result( __( 'Test MTU', 'wp-diagnostics' ), $network_tests->mtu_test( $target_host ) );
                    break;
                case 'security':
                    echo $this->render_test_result( __( 'Skanowanie zabezpieczeń', 'wp-diagnostics' ), $network_tests->security_scan( $target_host ) );
                    break;
            }
        }
    }
    
    /**
     * Renderuj pojedynczy wynik testu
     */
    private function render_test_result( $title, $result ) {
        $output = '<div class="wp-diagnostics-test-result">';
        $output .= '<h3>' . esc_html( $title ) . '</h3>';
        $output .= '<div class="wp-diagnostics-result-content">';
        $output .= '<pre>' . esc_html( $result ) . '</pre>';
        $output .= '</div>';
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Renderuj wyniki konfiguracji
     */
    private function render_config_results( $results ) {
        ?>
        <div class="wp-diagnostics-config-results">
            
            <!-- WordPress Version -->
            <div class="postbox">
                <h3 class="hndle"><?php _e( 'Wersja WordPress', 'wp-diagnostics' ); ?></h3>
                <div class="inside">
                    <?php $this->render_wp_version_section( $results['wp_version'] ); ?>
                </div>
            </div>
            
            <!-- Security Keys and Salts -->
            <div class="postbox">
                <h3 class="hndle"><?php _e( 'Klucze i Soli Bezpieczeństwa', 'wp-diagnostics' ); ?></h3>
                <div class="inside">
                    <?php $this->render_keys_salts_section( $results['keys_salts'] ); ?>
                </div>
            </div>
            
            <!-- Debug Settings -->
            <div class="postbox">
                <h3 class="hndle"><?php _e( 'Ustawienia Debug', 'wp-diagnostics' ); ?></h3>
                <div class="inside">
                    <?php $this->render_debug_settings_section( $results['debug_settings'] ); ?>
                </div>
            </div>
            
            <!-- Database Settings -->
            <div class="postbox">
                <h3 class="hndle"><?php _e( 'Konfiguracja Bazy Danych', 'wp-diagnostics' ); ?></h3>
                <div class="inside">
                    <?php $this->render_database_section( $results['database_settings'] ); ?>
                </div>
            </div>
            
            <!-- Security Settings -->
            <div class="postbox">
                <h3 class="hndle"><?php _e( 'Ustawienia Bezpieczeństwa', 'wp-diagnostics' ); ?></h3>
                <div class="inside">
                    <?php $this->render_security_section( $results['security_settings'] ); ?>
                </div>
            </div>
            
            <!-- File Permissions -->
            <div class="postbox">
                <h3 class="hndle"><?php _e( 'Uprawnienia Plików', 'wp-diagnostics' ); ?></h3>
                <div class="inside">
                    <?php $this->render_file_permissions_section( $results['file_permissions'] ); ?>
                </div>
            </div>
            
        </div>
        <?php
    }
    
    /**
     * Renderuj wyniki analizy wtyczek
     */
    private function render_plugins_results( $results ) {
        ?>
        <div class="wp-diagnostics-plugins-results">
            
            <!-- Aktywne wtyczki -->
            <div class="postbox">
                <h3 class="hndle"><?php _e( 'Aktywne Wtyczki', 'wp-diagnostics' ); ?></h3>
                <div class="inside">
                    <?php $this->render_active_plugins_section( $results['active_plugins'] ); ?>
                </div>
            </div>
            
            <!-- Aktywny motyw -->
            <div class="postbox">
                <h3 class="hndle"><?php _e( 'Aktywny Motyw', 'wp-diagnostics' ); ?></h3>
                <div class="inside">
                    <?php $this->render_active_theme_section( $results['active_theme'] ); ?>
                </div>
            </div>
            
            <!-- Problemy z wtyczkami -->
            <?php if ( !empty( $results['plugin_issues'] ) ): ?>
            <div class="postbox">
                <h3 class="hndle"><?php _e( 'Zidentyfikowane Problemy', 'wp-diagnostics' ); ?></h3>
                <div class="inside">
                    <?php $this->render_plugin_issues_section( $results['plugin_issues'] ); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Statystyki -->
            <div class="postbox">
                <h3 class="hndle"><?php _e( 'Statystyki', 'wp-diagnostics' ); ?></h3>
                <div class="inside">
                    <?php $this->render_plugin_statistics_section( $results['statistics'] ); ?>
                </div>
            </div>
            
        </div>
        <?php
    }
    
    /**
     * Renderuj wyniki PHP
     */
    private function render_php_results( $results ) {
        ?>
        <div class="wp-diagnostics-php-results">
            
            <!-- Informacje o PHP -->
            <div class="postbox">
                <h3 class="hndle"><?php _e( 'Informacje o PHP', 'wp-diagnostics' ); ?></h3>
                <div class="inside">
                    <?php $this->render_php_info_section( $results['info'] ); ?>
                </div>
            </div>
            
            <!-- Konfiguracja PHP -->
            <div class="postbox">
                <h3 class="hndle"><?php _e( 'Konfiguracja PHP', 'wp-diagnostics' ); ?></h3>
                <div class="inside">
                    <?php $this->render_php_config_section( $results['configuration'] ); ?>
                </div>
            </div>
            
            <!-- Rozszerzenia PHP -->
            <div class="postbox">
                <h3 class="hndle"><?php _e( 'Rozszerzenia PHP', 'wp-diagnostics' ); ?></h3>
                <div class="inside">
                    <?php $this->render_php_extensions_section( $results['extensions'] ); ?>
                </div>
            </div>
            
            <!-- Problemy z konfiguracją -->
            <?php if ( !empty( $results['issues']['issues'] ) || !empty( $results['issues']['warnings'] ) ): ?>
            <div class="postbox">
                <h3 class="hndle"><?php _e( 'Problemy i Ostrzeżenia', 'wp-diagnostics' ); ?></h3>
                <div class="inside">
                    <?php $this->render_php_issues_section( $results['issues'] ); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Test wydajności -->
            <div class="postbox">
                <h3 class="hndle"><?php _e( 'Test Wydajności PHP', 'wp-diagnostics' ); ?></h3>
                <div class="inside">
                    <?php $this->render_php_performance_section( $results['performance'] ); ?>
                </div>
            </div>
            
            <!-- Błędy PHP -->
            <?php if ( $results['errors']['accessible'] && !empty( $results['errors']['errors'] ) ): ?>
            <div class="postbox">
                <h3 class="hndle"><?php _e( 'Ostatnie Błędy PHP', 'wp-diagnostics' ); ?></h3>
                <div class="inside">
                    <?php $this->render_php_errors_section( $results['errors'] ); ?>
                </div>
            </div>
            <?php endif; ?>
            
        </div>
        <?php
    }
    
    /**
     * Renderuj szybkie linki
     */
    private function render_quick_links() {
        ?>
        <div class="wp-diagnostics-quick-links">
            <h2><?php _e( 'Dodatkowe Narzędzia Diagnostyczne', 'wp-diagnostics' ); ?></h2>
            <div class="wp-diagnostics-cards">
                <div class="wp-diagnostics-card">
                    <h3>🔒 <?php _e( 'Konfiguracja WordPress', 'wp-diagnostics' ); ?></h3>
                    <p><?php _e( 'Sprawdź klucze bezpieczeństwa, ustawienia debug i konfigurację wp-config.php', 'wp-diagnostics' ); ?></p>
                    <a href="<?php echo admin_url( 'admin.php?page=wp-diagnostics-config' ); ?>" class="button button-primary">
                        <?php _e( 'Sprawdź Konfigurację', 'wp-diagnostics' ); ?>
                    </a>
                </div>
                <div class="wp-diagnostics-card">
                    <h3>🔌 <?php _e( 'Wtyczki i Motywy', 'wp-diagnostics' ); ?></h3>
                    <p><?php _e( 'Analizuj aktywne wtyczki, dostępne aktualizacje i potencjalne problemy bezpieczeństwa', 'wp-diagnostics' ); ?></p>
                    <a href="<?php echo admin_url( 'admin.php?page=wp-diagnostics-plugins' ); ?>" class="button button-primary">
                        <?php _e( 'Analizuj Wtyczki', 'wp-diagnostics' ); ?>
                    </a>
                </div>
                <div class="wp-diagnostics-card">
                    <h3>⚡ <?php _e( 'Wydajność PHP', 'wp-diagnostics' ); ?></h3>
                    <p><?php _e( 'Sprawdź limity PHP, załadowane rozszerzenia i wykonaj test wydajności serwera', 'wp-diagnostics' ); ?></p>
                    <a href="<?php echo admin_url( 'admin.php?page=wp-diagnostics-php' ); ?>" class="button button-primary">
                        <?php _e( 'Sprawdź Wydajność', 'wp-diagnostics' ); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Obsługa eksportu wyników
     */
    public function handle_export() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'wp_diagnostics_nonce' ) ) {
            wp_die( __( 'Błąd weryfikacji bezpieczeństwa.', 'wp-diagnostics' ) );
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Brak uprawnień.', 'wp-diagnostics' ) );
        }
        
        $format = sanitize_text_field( $_POST['format'] ?? 'csv' );
        
        // Implementacja eksportu będzie dodana później
        wp_die( __( 'Funkcja eksportu zostanie zaimplementowana wkrótce.', 'wp-diagnostics' ) );
    }
    
    /**
     * Renderuj sekcję wersji WordPress
     */
    private function render_wp_version_section( $version_data ) {
        ?>
        <table class="wp-diagnostics-table">
            <tr>
                <td><strong><?php _e( 'Aktualna wersja:', 'wp-diagnostics' ); ?></strong></td>
                <td><?php echo esc_html( $version_data['current_version'] ); ?></td>
                <td class="status">
                    <?php if ( $version_data['is_latest'] ): ?>
                        <span class="dashicons dashicons-yes-alt"></span> <?php _e( 'Aktualna', 'wp-diagnostics' ); ?>
                    <?php else: ?>
                        <span class="dashicons dashicons-warning"></span> <?php _e( 'Wymaga aktualizacji', 'wp-diagnostics' ); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong><?php _e( 'Najnowsza wersja:', 'wp-diagnostics' ); ?></strong></td>
                <td><?php echo esc_html( $version_data['latest_version'] ); ?></td>
                <td class="status">
                    <?php if ( $version_data['security_update'] ): ?>
                        <span class="dashicons dashicons-shield-alt"></span> <?php _e( 'Aktualizacja bezpieczeństwa!', 'wp-diagnostics' ); ?>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Renderuj sekcję kluczy i soli
     */
    private function render_keys_salts_section( $keys_data ) {
        ?>
        <table class="wp-diagnostics-table">
            <?php foreach ( $keys_data as $key_name => $key_info ): ?>
            <tr>
                <td><strong><?php echo esc_html( $key_name ); ?>:</strong></td>
                <td>
                    <?php if ( $key_info['defined'] ): ?>
                        <?php if ( $key_info['status'] === 'ok' ): ?>
                            <span class="dashicons dashicons-yes-alt"></span> <?php _e( 'Poprawnie skonfigurowany', 'wp-diagnostics' ); ?>
                            <small>(<?php echo $key_info['length']; ?> znaków)</small>
                        <?php else: ?>
                            <span class="dashicons dashicons-warning"></span> 
                            <?php _e( 'Używa domyślnej wartości!', 'wp-diagnostics' ); ?>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="dashicons dashicons-dismiss"></span> <?php _e( 'Niezdefiniowany', 'wp-diagnostics' ); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <p class="description">
            <?php _e( 'Klucze bezpieczeństwa powinny być unikalne i długie. Możesz wygenerować nowe na', 'wp-diagnostics' ); ?> 
            <a href="https://api.wordpress.org/secret-key/1.1/salt/" target="_blank">api.wordpress.org</a>
        </p>
        <?php
    }
    
    /**
     * Renderuj sekcję ustawień debug
     */
    private function render_debug_settings_section( $debug_data ) {
        ?>
        <table class="wp-diagnostics-table">
            <?php foreach ( $debug_data as $constant => $info ): ?>
            <tr>
                <td><strong><?php echo esc_html( $constant ); ?>:</strong></td>
                <td>
                    <code><?php echo $info['current_value'] === true ? 'true' : ($info['current_value'] === false ? 'false' : 'undefined'); ?></code>
                    <br>
                    <small><?php echo esc_html( $info['description'] ); ?></small>
                </td>
                <td class="status">
                    <?php if ( $info['status'] === 'ok' ): ?>
                        <span class="dashicons dashicons-yes-alt"></span> <?php _e( 'Poprawne', 'wp-diagnostics' ); ?>
                    <?php elseif ( $info['status'] === 'warning' ): ?>
                        <span class="dashicons dashicons-warning"></span> <?php _e( 'Uwaga', 'wp-diagnostics' ); ?>
                    <?php else: ?>
                        <span class="dashicons dashicons-info"></span> <?php _e( 'Info', 'wp-diagnostics' ); ?>
                    <?php endif; ?>
                    <br>
                    <small>
                        <?php _e( 'Zalecane dla', 'wp-diagnostics' ); ?> <?php echo $info['environment']; ?>: 
                        <code><?php echo $info['recommended_value'] === true ? 'true' : ($info['recommended_value'] === false ? 'false' : 'undefined'); ?></code>
                    </small>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php
    }
    
    /**
     * Renderuj sekcję ustawień bazy danych
     */
    private function render_database_section( $db_data ) {
        ?>
        <table class="wp-diagnostics-table">
            <?php foreach ( $db_data as $constant => $info ): ?>
                <?php if ( $constant === 'connection_test' ): ?>
                    <tr>
                        <td><strong><?php _e( 'Test połączenia:', 'wp-diagnostics' ); ?></strong></td>
                        <td>
                            <?php if ( $info['status'] === 'ok' ): ?>
                                <span class="dashicons dashicons-yes-alt"></span> <?php _e( 'Połączenie OK', 'wp-diagnostics' ); ?>
                                <br>
                                <small>Serwer: <?php echo esc_html( $info['server_info'] ); ?></small>
                                <br>
                                <small>Charset: <?php echo esc_html( $info['charset'] ); ?>, Collate: <?php echo esc_html( $info['collate'] ); ?></small>
                            <?php else: ?>
                                <span class="dashicons dashicons-dismiss"></span> <?php _e( 'Błąd połączenia', 'wp-diagnostics' ); ?>
                                <br>
                                <small><?php echo esc_html( $info['error'] ); ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td><strong><?php echo esc_html( $constant ); ?>:</strong></td>
                        <td>
                            <?php if ( $info['defined'] ): ?>
                                <?php if ( isset( $info['masked_value'] ) ): ?>
                                    <code><?php echo esc_html( $info['masked_value'] ); ?></code>
                                <?php else: ?>
                                    <span class="dashicons dashicons-yes-alt"></span> <?php _e( 'Zdefiniowane', 'wp-diagnostics' ); ?>
                                    <small>(<?php echo $info['length']; ?> znaków)</small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="dashicons dashicons-dismiss"></span> <?php _e( 'Niezdefiniowane', 'wp-diagnostics' ); ?>
                            <?php endif; ?>
                            <br>
                            <small><?php echo esc_html( $info['description'] ); ?></small>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </table>
        <?php
    }
    
    /**
     * Renderuj sekcję bezpieczeństwa
     */
    private function render_security_section( $security_data ) {
        ?>
        <h4><?php _e( 'Ustawienia SSL', 'wp-diagnostics' ); ?></h4>
        <table class="wp-diagnostics-table">
            <tr>
                <td><strong><?php _e( 'Aktualnie używa SSL:', 'wp-diagnostics' ); ?></strong></td>
                <td>
                    <?php if ( $security_data['ssl']['is_ssl'] ): ?>
                        <span class="dashicons dashicons-yes-alt"></span> <?php _e( 'Tak', 'wp-diagnostics' ); ?>
                    <?php else: ?>
                        <span class="dashicons dashicons-warning"></span> <?php _e( 'Nie', 'wp-diagnostics' ); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong>FORCE_SSL_ADMIN:</strong></td>
                <td>
                    <code><?php echo $security_data['ssl']['force_ssl_admin'] ? 'true' : 'false'; ?></code>
                </td>
            </tr>
        </table>
        
        <h4><?php _e( 'Edycja plików', 'wp-diagnostics' ); ?></h4>
        <table class="wp-diagnostics-table">
            <tr>
                <td><strong>DISALLOW_FILE_EDIT:</strong></td>
                <td>
                    <code><?php echo $security_data['file_editing']['disallow_file_edit'] ? 'true' : 'false'; ?></code>
                    <?php if ( $security_data['file_editing']['disallow_file_edit'] ): ?>
                        <span class="dashicons dashicons-yes-alt"></span> <?php _e( 'Zabezpieczone', 'wp-diagnostics' ); ?>
                    <?php else: ?>
                        <span class="dashicons dashicons-warning"></span> <?php _e( 'Niezabezpieczone', 'wp-diagnostics' ); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong>DISALLOW_FILE_MODS:</strong></td>
                <td>
                    <code><?php echo $security_data['file_editing']['disallow_file_mods'] ? 'true' : 'false'; ?></code>
                </td>
            </tr>
        </table>
        
        <h4><?php _e( 'Baza danych', 'wp-diagnostics' ); ?></h4>
        <table class="wp-diagnostics-table">
            <tr>
                <td><strong><?php _e( 'Prefiks tabel:', 'wp-diagnostics' ); ?></strong></td>
                <td>
                    <code><?php echo esc_html( $security_data['database']['table_prefix'] ); ?></code>
                    <?php if ( $security_data['database']['is_default_prefix'] ): ?>
                        <span class="dashicons dashicons-warning"></span> <?php _e( 'Używa domyślnego prefiksu!', 'wp-diagnostics' ); ?>
                    <?php else: ?>
                        <span class="dashicons dashicons-yes-alt"></span> <?php _e( 'Niestandardowy prefiks', 'wp-diagnostics' ); ?>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
        
        if ( isset( $security_data['salts_age'] ) ): ?>
            <h4><?php _e( 'Wiek kluczy bezpieczeństwa', 'wp-diagnostics' ); ?></h4>
            <table class="wp-diagnostics-table">
                <tr>
                    <td><strong><?php _e( 'Szacunkowy wiek:', 'wp-diagnostics' ); ?></strong></td>
                    <td>
                        <?php if ( $security_data['salts_age']['estimated_age_days'] !== null ): ?>
                            <?php echo $security_data['salts_age']['estimated_age_days']; ?> <?php _e( 'dni', 'wp-diagnostics' ); ?>
                            <?php if ( $security_data['salts_age']['status'] === 'warning' ): ?>
                                <span class="dashicons dashicons-warning"></span>
                            <?php else: ?>
                                <span class="dashicons dashicons-yes-alt"></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php _e( 'Nieznany', 'wp-diagnostics' ); ?>
                        <?php endif; ?>
                        <br>
                        <small><?php echo esc_html( $security_data['salts_age']['recommendation'] ); ?></small>
                    </td>
                </tr>
            </table>
        <?php endif;
    }
    
    /**
     * Renderuj sekcję uprawnień plików
     */
    private function render_file_permissions_section( $permissions_data ) {
        ?>
        <table class="wp-diagnostics-table">
            <?php foreach ( $permissions_data as $file_name => $info ): ?>
            <tr>
                <td><strong><?php echo esc_html( $file_name ); ?>:</strong></td>
                <td>
                    <?php if ( $info['exists'] ): ?>
                        <code><?php echo esc_html( $info['current_permissions'] ); ?></code>
                        <small>(<?php _e( 'zalecane:', 'wp-diagnostics' ); ?> <?php echo esc_html( $info['recommended_permissions'] ); ?>)</small>
                    <?php else: ?>
                        <span class="dashicons dashicons-dismiss"></span> <?php _e( 'Plik nie istnieje', 'wp-diagnostics' ); ?>
                    <?php endif; ?>
                </td>
                <td class="status">
                    <?php if ( $info['exists'] ): ?>
                        <?php if ( $info['status'] === 'ok' ): ?>
                            <span class="dashicons dashicons-yes-alt"></span> <?php _e( 'OK', 'wp-diagnostics' ); ?>
                        <?php elseif ( $info['status'] === 'warning' ): ?>
                            <span class="dashicons dashicons-warning"></span> <?php _e( 'Zbyt permisywne', 'wp-diagnostics' ); ?>
                        <?php else: ?>
                            <span class="dashicons dashicons-dismiss"></span> <?php _e( 'Zbyt restrykcyjne', 'wp-diagnostics' ); ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <p class="description">
            <?php _e( 'Sprawdź czy uprawnienia plików są odpowiednio skonfigurowane dla bezpieczeństwa.', 'wp-diagnostics' ); ?>
        </p>
        <?php
    }
    
    /**
     * Renderuj sekcję aktywnych wtyczek
     */
    private function render_active_plugins_section( $plugins ) {
        ?>
        <table class="wp-diagnostics-table">
            <tr>
                <th><?php _e( 'Wtyczka', 'wp-diagnostics' ); ?></th>
                <th><?php _e( 'Wersja', 'wp-diagnostics' ); ?></th>
                <th><?php _e( 'Autor', 'wp-diagnostics' ); ?></th>
                <th><?php _e( 'Status', 'wp-diagnostics' ); ?></th>
            </tr>
            <?php foreach ( $plugins as $plugin ): ?>
            <tr>
                <td><strong><?php echo esc_html( $plugin['name'] ); ?></strong></td>
                <td><?php echo esc_html( $plugin['version'] ); ?></td>
                <td><?php echo esc_html( $plugin['author'] ); ?></td>
                <td>
                    <span class="dashicons dashicons-yes-alt"></span> <?php _e( 'Aktywna', 'wp-diagnostics' ); ?>
                    <?php if ( $plugin['network'] ): ?>
                        <br><small><?php _e( 'Aktywna w sieci', 'wp-diagnostics' ); ?></small>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php
    }
    
    /**
     * Renderuj sekcję aktywnego motywu
     */
    private function render_active_theme_section( $theme ) {
        ?>
        <table class="wp-diagnostics-table">
            <tr>
                <td><strong><?php _e( 'Nazwa:', 'wp-diagnostics' ); ?></strong></td>
                <td><?php echo esc_html( $theme['name'] ); ?></td>
            </tr>
            <tr>
                <td><strong><?php _e( 'Wersja:', 'wp-diagnostics' ); ?></strong></td>
                <td><?php echo esc_html( $theme['version'] ); ?></td>
            </tr>
            <tr>
                <td><strong><?php _e( 'Autor:', 'wp-diagnostics' ); ?></strong></td>
                <td><?php echo esc_html( $theme['author'] ); ?></td>
            </tr>
            <tr>
                <td><strong><?php _e( 'Ścieżka:', 'wp-diagnostics' ); ?></strong></td>
                <td><code><?php echo esc_html( $theme['template'] ); ?></code></td>
            </tr>
            <?php if ( $theme['child_theme'] ): ?>
            <tr>
                <td><strong><?php _e( 'Motyw dziecko:', 'wp-diagnostics' ); ?></strong></td>
                <td>
                    <span class="dashicons dashicons-yes-alt"></span> <?php _e( 'Tak', 'wp-diagnostics' ); ?>
                    <br><small><?php _e( 'Motyw nadrzędny:', 'wp-diagnostics' ); ?> <?php echo esc_html( $theme['parent'] ); ?></small>
                </td>
            </tr>
            <?php endif; ?>
        </table>
        <?php
    }
    
    /**
     * Renderuj sekcję problemów z wtyczkami
     */
    private function render_plugin_issues_section( $issues ) {
        ?>
        <?php if ( !empty( $issues['outdated'] ) ): ?>
            <h4><?php _e( 'Przestarzałe wtyczki', 'wp-diagnostics' ); ?></h4>
            <ul>
                <?php foreach ( $issues['outdated'] as $plugin ): ?>
                <li>
                    <span class="dashicons dashicons-warning"></span>
                    <strong><?php echo esc_html( $plugin['name'] ); ?></strong> - 
                    <?php printf( __( 'nie aktualizowana od %d dni', 'wp-diagnostics' ), $plugin['days'] ); ?>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        
        <?php if ( !empty( $issues['mu_plugins'] ) ): ?>
            <h4><?php _e( 'Must-Use wtyczki', 'wp-diagnostics' ); ?></h4>
            <ul>
                <?php foreach ( $issues['mu_plugins'] as $plugin ): ?>
                <li>
                    <span class="dashicons dashicons-info"></span>
                    <strong><?php echo esc_html( $plugin ); ?></strong>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        
        <?php if ( !empty( $issues['dropins'] ) ): ?>
            <h4><?php _e( 'Drop-in wtyczki', 'wp-diagnostics' ); ?></h4>
            <ul>
                <?php foreach ( $issues['dropins'] as $plugin ): ?>
                <li>
                    <span class="dashicons dashicons-info"></span>
                    <strong><?php echo esc_html( $plugin ); ?></strong>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Renderuj sekcję statystyk wtyczek
     */
    private function render_plugin_statistics_section( $stats ) {
        ?>
        <table class="wp-diagnostics-table">
            <tr>
                <td><strong><?php _e( 'Wszystkie wtyczki:', 'wp-diagnostics' ); ?></strong></td>
                <td><?php echo intval( $stats['total_plugins'] ); ?></td>
            </tr>
            <tr>
                <td><strong><?php _e( 'Aktywne wtyczki:', 'wp-diagnostics' ); ?></strong></td>
                <td><?php echo intval( $stats['active_plugins'] ); ?></td>
            </tr>
            <tr>
                <td><strong><?php _e( 'Nieaktywne wtyczki:', 'wp-diagnostics' ); ?></strong></td>
                <td><?php echo intval( $stats['inactive_plugins'] ); ?></td>
            </tr>
            <tr>
                <td><strong><?php _e( 'Must-Use wtyczki:', 'wp-diagnostics' ); ?></strong></td>
                <td><?php echo intval( $stats['mu_plugins'] ); ?></td>
            </tr>
            <tr>
                <td><strong><?php _e( 'Drop-in wtyczki:', 'wp-diagnostics' ); ?></strong></td>
                <td><?php echo intval( $stats['dropins'] ); ?></td>
            </tr>
            <tr>
                <td><strong><?php _e( 'Dostępne motywy:', 'wp-diagnostics' ); ?></strong></td>
                <td><?php echo intval( $stats['total_themes'] ); ?></td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Renderuj sekcję informacji o PHP
     */
    private function render_php_info_section( $info ) {
        ?>
        <table class="wp-diagnostics-table">
            <tr>
                <td><strong><?php _e( 'Wersja PHP:', 'wp-diagnostics' ); ?></strong></td>
                <td>
                    <?php echo esc_html( $info['version'] ); ?>
                    <?php if ( version_compare( $info['version'], '8.0', '<' ) ): ?>
                        <span class="dashicons dashicons-warning"></span> <?php _e( 'Zalecana aktualizacja', 'wp-diagnostics' ); ?>
                    <?php else: ?>
                        <span class="dashicons dashicons-yes-alt"></span> <?php _e( 'Aktualna', 'wp-diagnostics' ); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong><?php _e( 'SAPI:', 'wp-diagnostics' ); ?></strong></td>
                <td><?php echo esc_html( $info['sapi'] ); ?></td>
            </tr>
            <tr>
                <td><strong><?php _e( 'System operacyjny:', 'wp-diagnostics' ); ?></strong></td>
                <td><?php echo esc_html( $info['os'] ); ?> (<?php echo esc_html( $info['architecture'] ); ?>)</td>
            </tr>
            <tr>
                <td><strong><?php _e( 'Wersja Zend:', 'wp-diagnostics' ); ?></strong></td>
                <td><?php echo esc_html( $info['zend_version'] ); ?></td>
            </tr>
            <tr>
                <td><strong><?php _e( 'Limit pamięci:', 'wp-diagnostics' ); ?></strong></td>
                <td><?php echo esc_html( $info['memory_limit'] ); ?></td>
            </tr>
            <tr>
                <td><strong><?php _e( 'Użycie pamięci:', 'wp-diagnostics' ); ?></strong></td>
                <td><?php echo esc_html( $info['memory_usage'] ); ?></td>
            </tr>
            <tr>
                <td><strong><?php _e( 'Szczyt pamięci:', 'wp-diagnostics' ); ?></strong></td>
                <td><?php echo esc_html( $info['memory_peak'] ); ?></td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Renderuj sekcję konfiguracji PHP
     */
    private function render_php_config_section( $config ) {
        ?>
        <table class="wp-diagnostics-table">
            <tr>
                <td><strong>memory_limit:</strong></td>
                <td><?php echo esc_html( $config['memory_limit'] ); ?></td>
            </tr>
            <tr>
                <td><strong>max_execution_time:</strong></td>
                <td><?php echo esc_html( $config['max_execution_time'] ); ?> <?php _e( 'sekund', 'wp-diagnostics' ); ?></td>
            </tr>
            <tr>
                <td><strong>max_input_vars:</strong></td>
                <td><?php echo esc_html( $config['max_input_vars'] ); ?></td>
            </tr>
            <tr>
                <td><strong>post_max_size:</strong></td>
                <td><?php echo esc_html( $config['post_max_size'] ); ?></td>
            </tr>
            <tr>
                <td><strong>upload_max_filesize:</strong></td>
                <td><?php echo esc_html( $config['upload_max_filesize'] ); ?></td>
            </tr>
            <tr>
                <td><strong>max_file_uploads:</strong></td>
                <td><?php echo esc_html( $config['max_file_uploads'] ); ?></td>
            </tr>
            <tr>
                <td><strong>display_errors:</strong></td>
                <td>
                    <?php echo $config['display_errors'] ? 'On' : 'Off'; ?>
                    <?php if ( $config['display_errors'] ): ?>
                        <span class="dashicons dashicons-warning"></span> <?php _e( 'Powinno być wyłączone w produkcji', 'wp-diagnostics' ); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong>log_errors:</strong></td>
                <td><?php echo $config['log_errors'] ? 'On' : 'Off'; ?></td>
            </tr>
            <tr>
                <td><strong>OPcache:</strong></td>
                <td>
                    <?php if ( $config['opcache_enabled'] ): ?>
                        <span class="dashicons dashicons-yes-alt"></span> <?php _e( 'Włączony', 'wp-diagnostics' ); ?>
                    <?php else: ?>
                        <span class="dashicons dashicons-warning"></span> <?php _e( 'Wyłączony', 'wp-diagnostics' ); ?>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Renderuj sekcję rozszerzeń PHP
     */
    private function render_php_extensions_section( $extensions ) {
        ?>
        <h4><?php _e( 'Wymagane rozszerzenia', 'wp-diagnostics' ); ?></h4>
        <table class="wp-diagnostics-table">
            <?php foreach ( $extensions['required'] as $ext => $info ): ?>
            <tr>
                <td><strong><?php echo esc_html( $info['name'] ); ?>:</strong></td>
                <td>
                    <?php if ( $info['loaded'] ): ?>
                        <span class="dashicons dashicons-yes-alt"></span> <?php _e( 'Załadowane', 'wp-diagnostics' ); ?>
                        <?php if ( $info['version'] ): ?>
                            <small>(v<?php echo esc_html( $info['version'] ); ?>)</small>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="dashicons dashicons-dismiss"></span> <?php _e( 'Brak', 'wp-diagnostics' ); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <h4><?php _e( 'Zalecane rozszerzenia', 'wp-diagnostics' ); ?></h4>
        <table class="wp-diagnostics-table">
            <?php foreach ( $extensions['recommended'] as $ext => $info ): ?>
            <tr>
                <td><strong><?php echo esc_html( $info['name'] ); ?>:</strong></td>
                <td>
                    <?php if ( $info['loaded'] ): ?>
                        <span class="dashicons dashicons-yes-alt"></span> <?php _e( 'Załadowane', 'wp-diagnostics' ); ?>
                        <?php if ( $info['version'] ): ?>
                            <small>(v<?php echo esc_html( $info['version'] ); ?>)</small>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="dashicons dashicons-warning"></span> <?php _e( 'Brak', 'wp-diagnostics' ); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php
    }
    
    /**
     * Renderuj sekcję problemów PHP
     */
    private function render_php_issues_section( $issues ) {
        ?>
        <?php if ( !empty( $issues['issues'] ) ): ?>
            <h4><?php _e( 'Problemy krytyczne', 'wp-diagnostics' ); ?></h4>
            <ul>
                <?php foreach ( $issues['issues'] as $issue ): ?>
                <li>
                    <span class="dashicons dashicons-dismiss"></span>
                    <?php echo esc_html( $issue ); ?>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        
        <?php if ( !empty( $issues['warnings'] ) ): ?>
            <h4><?php _e( 'Ostrzeżenia', 'wp-diagnostics' ); ?></h4>
            <ul>
                <?php foreach ( $issues['warnings'] as $warning ): ?>
                <li>
                    <span class="dashicons dashicons-warning"></span>
                    <?php echo esc_html( $warning ); ?>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        
        <?php if ( !empty( $issues['recommendations'] ) ): ?>
            <h4><?php _e( 'Rekomendacje', 'wp-diagnostics' ); ?></h4>
            <ul>
                <?php foreach ( $issues['recommendations'] as $recommendation ): ?>
                <li>
                    <span class="dashicons dashicons-info"></span>
                    <?php echo esc_html( $recommendation ); ?>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Renderuj sekcję testu wydajności PHP
     */
    private function render_php_performance_section( $performance ) {
        ?>
        <table class="wp-diagnostics-table">
            <tr>
                <td><strong><?php _e( 'Test arytmetyczny:', 'wp-diagnostics' ); ?></strong></td>
                <td><?php echo esc_html( $performance['arithmetic'] ); ?> ms</td>
            </tr>
            <tr>
                <td><strong><?php _e( 'Operacje na łańcuchach:', 'wp-diagnostics' ); ?></strong></td>
                <td><?php echo esc_html( $performance['string_operations'] ); ?> ms</td>
            </tr>
            <tr>
                <td><strong><?php _e( 'Operacje na tablicach:', 'wp-diagnostics' ); ?></strong></td>
                <td><?php echo esc_html( $performance['array_operations'] ); ?> ms</td>
            </tr>
            <tr>
                <td><strong><?php _e( 'Operacje I/O:', 'wp-diagnostics' ); ?></strong></td>
                <td><?php echo esc_html( $performance['io_operations'] ); ?> ms</td>
            </tr>
            <tr>
                <td><strong><?php _e( 'Użycie pamięci (test):', 'wp-diagnostics' ); ?></strong></td>
                <td><?php echo esc_html( number_format( $performance['memory_usage'] / 1024 / 1024, 2 ) ); ?> MB</td>
            </tr>
        </table>
        <p class="description">
            <?php _e( 'Niższe wartości oznaczają lepszą wydajność. Wyniki mogą się różnić w zależności od obciążenia serwera.', 'wp-diagnostics' ); ?>
        </p>
        <?php
    }
    
    /**
     * Renderuj sekcję błędów PHP
     */
    private function render_php_errors_section( $errors ) {
        ?>
        <p><strong><?php _e( 'Plik logów:', 'wp-diagnostics' ); ?></strong> <code><?php echo esc_html( $errors['log_file'] ); ?></code></p>
        
        <?php if ( !empty( $errors['errors'] ) ): ?>
            <table class="wp-diagnostics-table">
                <tr>
                    <th><?php _e( 'Czas', 'wp-diagnostics' ); ?></th>
                    <th><?php _e( 'Typ', 'wp-diagnostics' ); ?></th>
                    <th><?php _e( 'Wiadomość', 'wp-diagnostics' ); ?></th>
                </tr>
                <?php foreach ( array_slice( $errors['errors'], 0, 10 ) as $error ): ?>
                <tr>
                    <td><small><?php echo esc_html( $error['timestamp'] ); ?></small></td>
                    <td>
                        <?php if ( strpos( $error['type'], 'Fatal' ) !== false ): ?>
                            <span class="dashicons dashicons-dismiss"></span>
                        <?php elseif ( strpos( $error['type'], 'Warning' ) !== false ): ?>
                            <span class="dashicons dashicons-warning"></span>
                        <?php else: ?>
                            <span class="dashicons dashicons-info"></span>
                        <?php endif; ?>
                        <?php echo esc_html( $error['type'] ); ?>
                    </td>
                    <td><small><?php echo esc_html( $error['message'] ); ?></small></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <p class="description">
                <?php printf( __( 'Pokazano ostatnie 10 błędów z %d dostępnych.', 'wp-diagnostics' ), $errors['total_lines'] ); ?>
            </p>
        <?php else: ?>
            <p><?php _e( 'Brak błędów w logach lub logi są puste.', 'wp-diagnostics' ); ?></p>
        <?php endif; ?>
        <?php
    }
}
