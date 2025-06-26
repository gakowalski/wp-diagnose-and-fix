<?php
/*
Plugin Name: WordPress Diagnostics & Configuration Analyzer
Description: Kompleksowe narzędzie diagnostyczne dla WordPress. Sprawdza konfigurację wp-config.php, analizuje 
            wtyczki i motywy, testuje wydajność PHP oraz wykonuje zaawansowane testy sieciowe (ping, traceroute, 
            SSL/TLS, DNS, skanowanie portów, wykrywanie WAF, MTU, SMTP). Oferuje porównanie wyników serwer/klient, 
            eksport raportów do PDF/CSV, historię testów oraz rekomendacje bezpieczeństwa.
Version: 4.2
Author: Grzegorz Kowalski
Text Domain: wp-diagnostics
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Zapobiegaj bezpośredniemu dostępowi.
}

// Definiuj stałe pluginu
define( 'WP_DIAGNOSTICS_VERSION', '4.2' );
define( 'WP_DIAGNOSTICS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_DIAGNOSTICS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_DIAGNOSTICS_INCLUDES_DIR', WP_DIAGNOSTICS_PLUGIN_DIR . 'includes/' );

/**
 * Główna klasa pluginu
 */
class WP_Diagnostics_Plugin {
    
    /**
     * Instancja singletona
     */
    private static $instance = null;
    
    /**
     * Komponenty pluginu
     */
    public $network_tests;
    public $config_checker;
    public $plugins_analyzer;
    public $php_checker;
    public $admin_interface;
    
    /**
     * Singleton pattern
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Konstruktor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Inicjalizacja pluginu
     */
    private function init() {
        // Załaduj pliki includes
        $this->load_includes();
        
        // Dodaj hooki WordPress
        add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
        add_action( 'init', array( $this, 'wp_init' ) );
        add_action( 'admin_init', array( $this, 'admin_init' ) );
        
        // Hook aktywacji i deaktywacji
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
        register_uninstall_hook( __FILE__, array( __CLASS__, 'uninstall' ) );
    }
    
    /**
     * Załaduj pliki includes
     */
    private function load_includes() {
        $includes = array(
            'class-network-tests.php',
            'class-config-checker.php', 
            'class-plugins-analyzer.php',
            'class-php-checker.php',
            'class-admin-interface.php',
            'class-export-manager.php',
            'class-dns-tester.php',
            'class-ssl-tester.php',
            'class-security-scanner.php'
        );
        
        foreach ( $includes as $file ) {
            $file_path = WP_DIAGNOSTICS_INCLUDES_DIR . $file;
            if ( file_exists( $file_path ) ) {
                require_once $file_path;
                
                // Debug - sprawdź czy klasa została załadowana
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( "WP Diagnostics: Loaded file: {$file}" );
                }
            } else {
                error_log( "WP Diagnostics: Missing file {$file_path}" );
            }
        }
    }
    
    /**
     * Inicjalizuj komponenty pluginu
     */
    private function init_components() {
        if ( is_admin() ) {
            // Debug - sprawdź czy klasy istnieją
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "WP Diagnostics: Class WP_Diagnostics_Network_Tests exists: " . ( class_exists( 'WP_Diagnostics_Network_Tests' ) ? 'YES' : 'NO' ) );
                error_log( "WP Diagnostics: Class WP_Diagnostics_Config_Checker exists: " . ( class_exists( 'WP_Diagnostics_Config_Checker' ) ? 'YES' : 'NO' ) );
                error_log( "WP Diagnostics: Class WP_Diagnostics_Admin_Interface exists: " . ( class_exists( 'WP_Diagnostics_Admin_Interface' ) ? 'YES' : 'NO' ) );
            }
            
            // Bezpieczna inicjalizacja - sprawdzaj istnienie klas
            if ( class_exists( 'WP_Diagnostics_Network_Tests' ) ) {
                $this->network_tests = new WP_Diagnostics_Network_Tests();
            }
            
            if ( class_exists( 'WP_Diagnostics_Config_Checker' ) ) {
                $this->config_checker = new WP_Diagnostics_Config_Checker();
            }
            
            if ( class_exists( 'WP_Diagnostics_Plugins_Analyzer' ) ) {
                $this->plugins_analyzer = new WP_Diagnostics_Plugins_Analyzer();
            }
            
            if ( class_exists( 'WP_Diagnostics_PHP_Checker' ) ) {
                $this->php_checker = new WP_Diagnostics_PHP_Checker();
            }
            
            if ( class_exists( 'WP_Diagnostics_Admin_Interface' ) ) {
                $this->admin_interface = new WP_Diagnostics_Admin_Interface();
            }
        }
    }
    
    /**
     * Hook: plugins_loaded
     */
    public function plugins_loaded() {
        // Załaduj tłumaczenia
        load_plugin_textdomain( 'wp-diagnostics', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
        
        // Inicjalizuj komponenty po załadowaniu wszystkich pluginów
        // ale tylko jeśli nie jesteśmy w trakcie aktywacji/deaktywacji pluginu
        if ( ! ( defined( 'WP_ADMIN' ) && isset( $_GET['action'] ) && in_array( $_GET['action'], array( 'activate', 'deactivate' ) ) ) ) {
            $this->init_components();
        }
    }
    
    /**
     * Hook: init
     */
    public function wp_init() {
        // Stwórz tabele w bazie danych jeśli potrzebne
        $this->maybe_create_tables();
    }
    
    /**
     * Hook: admin_init
     */
    public function admin_init() {
        // Sprawdź uprawnienia użytkownika
        $this->check_user_permissions();
    }
    
    /**
     * Sprawdź uprawnienia użytkownika
     */
    private function check_user_permissions() {
        if ( is_admin() && isset( $_GET['page'] ) && 
             strpos( $_GET['page'], 'wp-diagnostics' ) !== false ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( __( 'Nie masz uprawnień do dostępu do tej strony.', 'wp-diagnostics' ) );
            }
        }
    }
    
    /**
     * Stwórz tabele w bazie danych
     */
    private function maybe_create_tables() {
        $current_version = get_option( 'wp_diagnostics_db_version', '0' );
        
        if ( version_compare( $current_version, WP_DIAGNOSTICS_VERSION, '<' ) ) {
            $this->create_tables();
            update_option( 'wp_diagnostics_db_version', WP_DIAGNOSTICS_VERSION );
        }
    }
    
    /**
     * Stwórz tabele w bazie danych
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_name = $wpdb->prefix . 'wp_diagnostics_history';
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            test_date datetime DEFAULT CURRENT_TIMESTAMP,
            host varchar(255) NOT NULL,
            test_type varchar(50) NOT NULL,
            results longtext NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY test_date (test_date),
            KEY host (host),
            KEY test_type (test_type)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
    
    /**
     * Aktywacja pluginu
     */
    public function activate() {
        $this->create_tables();
        
        // Ustaw domyślne opcje
        add_option( 'wp_diagnostics_settings', array(
            'enable_history' => true,
            'max_history_entries' => 1000,
            'enable_debug' => false
        ) );
        
        // Wyczyść cache
        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
        }
    }
    
    /**
     * Deaktywacja pluginu
     */
    public function deactivate() {
        // Wyczyść scheduled events jeśli jakieś były
        wp_clear_scheduled_hook( 'wp_diagnostics_cleanup' );
        
        // Wyczyść cache
        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
        }
    }
    
    /**
     * Odinstalowanie pluginu - wyczyść dane
     */
    public static function uninstall() {
        global $wpdb;
        
        // Usuń tabele
        $table_name = $wpdb->prefix . 'wp_diagnostics_history';
        $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
        
        // Usuń opcje
        delete_option( 'wp_diagnostics_settings' );
        delete_option( 'wp_diagnostics_db_version' );
        
        // Wyczyść cache
        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
        }
    }
    
    /**
     * Pobierz ustawienia pluginu
     */
    public function get_settings() {
        return get_option( 'wp_diagnostics_settings', array() );
    }
    
    /**
     * Zapisz ustawienia pluginu
     */
    public function save_settings( $settings ) {
        return update_option( 'wp_diagnostics_settings', $settings );
    }
}

/**
 * Funkcja pomocnicza do pobierania instancji pluginu
 */
function wp_diagnostics() {
    return WP_Diagnostics_Plugin::get_instance();
}

// Uruchom plugin
wp_diagnostics();
