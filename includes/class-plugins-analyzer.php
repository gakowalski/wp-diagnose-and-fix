<?php
/**
 * Klasa do analizy wtyczek i motywów WordPress
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_Diagnostics_Plugins_Analyzer {
    
    /**
     * Konstruktor
     */
    public function __construct() {
        // Inicjalizacja jeśli potrzebna
    }
    
    /**
     * Analizuj aktywne wtyczki
     */
    public function analyze_active_plugins() {
        $active_plugins = get_option( 'active_plugins', array() );
        $plugins_data = array();
        
        foreach ( $active_plugins as $plugin_file ) {
            $plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file );
            $plugins_data[] = array(
                'name' => $plugin_data['Name'],
                'version' => $plugin_data['Version'],
                'author' => $plugin_data['Author'],
                'file' => $plugin_file,
                'size' => $this->get_plugin_size( $plugin_file ),
                'last_updated' => $this->get_plugin_last_updated( $plugin_file ),
                'security_issues' => $this->check_plugin_security( $plugin_file )
            );
        }
        
        return $plugins_data;
    }
    
    /**
     * Analizuj aktywny motyw
     */
    public function analyze_active_theme() {
        $theme = wp_get_theme();
        $parent_theme = $theme->get( 'Template' );
        
        $theme_data = array(
            'name' => $theme->get( 'Name' ),
            'version' => $theme->get( 'Version' ),
            'author' => $theme->get( 'Author' ),
            'description' => $theme->get( 'Description' ),
            'is_child_theme' => ! empty( $parent_theme ),
            'parent_theme' => $parent_theme,
            'size' => $this->get_theme_size(),
            'template_files' => $this->get_theme_template_files(),
            'security_issues' => $this->check_theme_security()
        );
        
        return $theme_data;
    }
    
    /**
     * Sprawdź problemy z wtyczkami
     */
    public function check_plugin_issues() {
        $issues = array();
        
        // Sprawdź nieaktywne wtyczki
        $all_plugins = get_plugins();
        $active_plugins = get_option( 'active_plugins', array() );
        
        foreach ( $all_plugins as $plugin_file => $plugin_data ) {
            if ( ! in_array( $plugin_file, $active_plugins ) ) {
                $issues['inactive_plugins'][] = array(
                    'name' => $plugin_data['Name'],
                    'file' => $plugin_file,
                    'size' => $this->get_plugin_size( $plugin_file )
                );
            }
        }
        
        // Sprawdź wtyczki bez aktualizacji
        $update_plugins = get_site_transient( 'update_plugins' );
        if ( ! empty( $update_plugins->response ) ) {
            foreach ( $update_plugins->response as $plugin_file => $plugin_update ) {
                $plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file );
                $issues['outdated_plugins'][] = array(
                    'name' => $plugin_data['Name'],
                    'current_version' => $plugin_data['Version'],
                    'new_version' => $plugin_update->new_version,
                    'file' => $plugin_file
                );
            }
        }
        
        // Sprawdź potencjalne konflikty
        $issues['potential_conflicts'] = $this->check_plugin_conflicts();
        
        return $issues;
    }
    
    /**
     * Główna metoda analizy - punkt wejścia
     */
    public function analyze() {
        return array(
            'active_plugins' => $this->analyze_active_plugins(),
            'active_theme' => $this->analyze_active_theme(),
            'plugin_issues' => $this->check_plugin_issues(),
            'statistics' => $this->get_statistics()
        );
    }
    
    /**
     * Pobierz statystyki
     */
    public function get_statistics() {
        $all_plugins = get_plugins();
        $active_plugins = get_option( 'active_plugins', array() );
        $inactive_plugins = array_diff( array_keys( $all_plugins ), $active_plugins );
        
        // Sprawdź aktualizacje
        $update_plugins = get_site_transient( 'update_plugins' );
        $plugins_needing_updates = ! empty( $update_plugins->response ) ? count( $update_plugins->response ) : 0;
        
        // Sprawdź przestarzałe wtyczki
        $outdated_count = 0;
        foreach ( $inactive_plugins as $plugin_file ) {
            $plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file );
            $last_updated = $this->get_plugin_last_updated( $plugin_file );
            if ( $last_updated && strtotime( $last_updated ) < strtotime( '-2 years' ) ) {
                $outdated_count++;
            }
        }
        
        return array(
            'total_plugins' => count( $all_plugins ),
            'active_plugins' => count( $active_plugins ),
            'inactive_plugins' => count( $inactive_plugins ),
            'plugins_needing_updates' => $plugins_needing_updates,
            'outdated_plugins' => $outdated_count
        );
    }
    
    /**
     * Pobierz rozmiar wtyczki
     */
    private function get_plugin_size( $plugin_file ) {
        $plugin_dir = WP_PLUGIN_DIR . '/' . dirname( $plugin_file );
        return $this->get_directory_size( $plugin_dir );
    }
    
    /**
     * Pobierz rozmiar motywu
     */
    private function get_theme_size() {
        $theme = wp_get_theme();
        $theme_dir = $theme->get_stylesheet_directory();
        return $this->get_directory_size( $theme_dir );
    }
    
    /**
     * Pobierz rozmiar katalogu
     */
    private function get_directory_size( $directory ) {
        $size = 0;
        
        if ( is_dir( $directory ) ) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator( $directory, RecursiveDirectoryIterator::SKIP_DOTS )
            );
            
            foreach ( $iterator as $file ) {
                if ( $file->isFile() ) {
                    $size += $file->getSize();
                }
            }
        }
        
        return $size;
    }
    
    /**
     * Sprawdź bezpieczeństwo wtyczki
     */
    private function check_plugin_security( $plugin_file ) {
        $issues = array();
        $plugin_dir = WP_PLUGIN_DIR . '/' . dirname( $plugin_file );
        
        // Sprawdź czy wtyczka ma wrażliwe pliki
        $sensitive_files = array( '.htaccess', 'wp-config.php', 'config.php' );
        foreach ( $sensitive_files as $file ) {
            if ( file_exists( $plugin_dir . '/' . $file ) ) {
                $issues[] = sprintf( __( 'Znaleziono wrażliwy plik: %s', 'wp-diagnostics' ), $file );
            }
        }
        
        // Sprawdź czy wtyczka ma uprawnienia do zapisu
        if ( is_writable( $plugin_dir ) ) {
            $issues[] = __( 'Katalog wtyczki ma uprawnienia do zapisu', 'wp-diagnostics' );
        }
        
        return $issues;
    }
    
    /**
     * Sprawdź bezpieczeństwo motywu
     */
    private function check_theme_security() {
        $issues = array();
        $theme = wp_get_theme();
        $theme_dir = $theme->get_stylesheet_directory();
        
        // Sprawdź czy motyw ma wrażliwe pliki
        $sensitive_files = array( '.htaccess', 'wp-config.php', 'config.php' );
        foreach ( $sensitive_files as $file ) {
            if ( file_exists( $theme_dir . '/' . $file ) ) {
                $issues[] = sprintf( __( 'Znaleziono wrażliwy plik: %s', 'wp-diagnostics' ), $file );
            }
        }
        
        // Sprawdź czy motyw ma podejrzane funkcje
        $suspicious_functions = array( 'eval', 'base64_decode', 'exec', 'shell_exec' );
        $theme_files = $this->get_theme_php_files( $theme_dir );
        
        foreach ( $theme_files as $file ) {
            $content = file_get_contents( $file );
            foreach ( $suspicious_functions as $function ) {
                if ( strpos( $content, $function ) !== false ) {
                    $issues[] = sprintf( __( 'Znaleziono podejrzaną funkcję "%s" w pliku %s', 'wp-diagnostics' ), 
                                       $function, basename( $file ) );
                }
            }
        }
        
        return $issues;
    }
    
    /**
     * Pobierz pliki PHP motywu
     */
    private function get_theme_php_files( $theme_dir ) {
        $php_files = array();
        
        if ( is_dir( $theme_dir ) ) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator( $theme_dir, RecursiveDirectoryIterator::SKIP_DOTS )
            );
            
            foreach ( $iterator as $file ) {
                if ( $file->isFile() && $file->getExtension() === 'php' ) {
                    $php_files[] = $file->getPathname();
                }
            }
        }
        
        return $php_files;
    }
    
    /**
     * Pobierz pliki szablonów motywu
     */
    private function get_theme_template_files() {
        $theme = wp_get_theme();
        $theme_dir = $theme->get_stylesheet_directory();
        $template_files = array();
        
        $template_patterns = array(
            'index.php',
            'style.css',
            'functions.php',
            'header.php',
            'footer.php',
            'sidebar.php',
            'single.php',
            'page.php',
            'archive.php',
            'search.php',
            '404.php'
        );
        
        foreach ( $template_patterns as $template ) {
            if ( file_exists( $theme_dir . '/' . $template ) ) {
                $template_files[] = $template;
            }
        }
        
        return $template_files;
    }
    
    /**
     * Sprawdź konflikty między wtyczkami
     */
    private function check_plugin_conflicts() {
        $conflicts = array();
        
        // Sprawdź czy wtyczki nie definiują tych samych funkcji
        $active_plugins = get_option( 'active_plugins', array() );
        $defined_functions = array();
        
        foreach ( $active_plugins as $plugin_file ) {
            $plugin_dir = WP_PLUGIN_DIR . '/' . dirname( $plugin_file );
            $plugin_functions = $this->get_plugin_functions( $plugin_dir );
            
            foreach ( $plugin_functions as $function ) {
                if ( isset( $defined_functions[ $function ] ) ) {
                    $conflicts[] = sprintf( 
                        __( 'Funkcja "%s" zdefiniowana w wtyczkach: %s i %s', 'wp-diagnostics' ),
                        $function,
                        $defined_functions[ $function ],
                        dirname( $plugin_file )
                    );
                } else {
                    $defined_functions[ $function ] = dirname( $plugin_file );
                }
            }
        }
        
        return $conflicts;
    }
    
    /**
     * Pobierz funkcje zdefiniowane w wtyczce
     */
    private function get_plugin_functions( $plugin_dir ) {
        $functions = array();
        
        if ( is_dir( $plugin_dir ) ) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator( $plugin_dir, RecursiveDirectoryIterator::SKIP_DOTS )
            );
            
            foreach ( $iterator as $file ) {
                if ( $file->isFile() && $file->getExtension() === 'php' ) {
                    $content = file_get_contents( $file->getPathname() );
                    preg_match_all( '/function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $content, $matches );
                    if ( ! empty( $matches[1] ) ) {
                        $functions = array_merge( $functions, $matches[1] );
                    }
                }
            }
        }
        
        return array_unique( $functions );
    }
    
    /**
     * Pobierz ostatnią datę aktualizacji wtyczki
     */
    private function get_plugin_last_updated( $plugin_file ) {
        $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
        
        if ( file_exists( $plugin_path ) ) {
            return date( 'Y-m-d H:i:s', filemtime( $plugin_path ) );
        }
        
        return null;
    }
}
