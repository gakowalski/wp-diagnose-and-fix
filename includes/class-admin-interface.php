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
            $php_info = $php_checker->get_php_info();
            $this->render_php_results( $php_info );
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
            <div class="postbox">
                <h3 class="hndle"><?php _e( 'Analiza Wtyczek i Motywów', 'wp-diagnostics' ); ?></h3>
                <div class="inside">
                    <p><?php _e( 'Funkcja analizy wtyczek i motywów zostanie wkrótce dodana.', 'wp-diagnostics' ); ?></p>
                    <p><em><?php _e( 'Ta sekcja będzie zawierać sprawdzenie aktywnych wtyczek, ich wersji, kompatybilności i potencjalnych problemów bezpieczeństwa.', 'wp-diagnostics' ); ?></em></p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Renderuj wyniki PHP
     */
    private function render_php_results( $results ) {
        // Implementacja będzie dodana w kolejnych plikach
        echo '<div class="wp-diagnostics-php-results">';
        echo '<p>' . __( 'Analiza PHP zostanie zaimplementowana w osobnej klasie.', 'wp-diagnostics' ) . '</p>';
        echo '</div>';
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
}
