<?php
/**
 * Klasa do sprawdzania konfiguracji i wydajności PHP
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_Diagnostics_PHP_Checker {
    
    /**
     * Konstruktor
     */
    public function __construct() {
        // Inicjalizacja jeśli potrzebna
    }
    
    /**
     * Sprawdź konfigurację PHP
     */
    public function check_php_configuration() {
        $config = array(
            'version' => PHP_VERSION,
            'memory_limit' => ini_get( 'memory_limit' ),
            'max_execution_time' => ini_get( 'max_execution_time' ),
            'max_input_vars' => ini_get( 'max_input_vars' ),
            'post_max_size' => ini_get( 'post_max_size' ),
            'upload_max_filesize' => ini_get( 'upload_max_filesize' ),
            'max_file_uploads' => ini_get( 'max_file_uploads' ),
            'display_errors' => ini_get( 'display_errors' ),
            'log_errors' => ini_get( 'log_errors' ),
            'error_log' => ini_get( 'error_log' ),
            'date_timezone' => ini_get( 'date.timezone' ),
            'opcache_enabled' => extension_loaded( 'Zend OPcache' ) && ini_get( 'opcache.enable' ),
            'session_save_path' => ini_get( 'session.save_path' ),
            'allow_url_fopen' => ini_get( 'allow_url_fopen' ),
            'allow_url_include' => ini_get( 'allow_url_include' )
        );
        
        return $config;
    }
    
    /**
     * Sprawdź załadowane rozszerzenia PHP
     */
    public function check_php_extensions() {
        $required_extensions = array(
            'curl' => 'cURL',
            'gd' => 'GD',
            'json' => 'JSON',
            'mbstring' => 'Multibyte String',
            'mysqli' => 'MySQLi',
            'openssl' => 'OpenSSL',
            'zip' => 'ZIP',
            'xml' => 'XML',
            'imagick' => 'ImageMagick',
            'intl' => 'Internationalization'
        );
        
        $recommended_extensions = array(
            'memcached' => 'Memcached',
            'redis' => 'Redis',
            'opcache' => 'OPcache',
            'xdebug' => 'Xdebug'
        );
        
        $extensions = array(
            'required' => array(),
            'recommended' => array(),
            'loaded' => get_loaded_extensions()
        );
        
        foreach ( $required_extensions as $ext => $name ) {
            $extensions['required'][ $ext ] = array(
                'name' => $name,
                'loaded' => extension_loaded( $ext ),
                'version' => extension_loaded( $ext ) ? phpversion( $ext ) : null
            );
        }
        
        foreach ( $recommended_extensions as $ext => $name ) {
            $extensions['recommended'][ $ext ] = array(
                'name' => $name,
                'loaded' => extension_loaded( $ext ),
                'version' => extension_loaded( $ext ) ? phpversion( $ext ) : null
            );
        }
        
        return $extensions;
    }
    
    /**
     * Test wydajności PHP
     */
    public function run_performance_test() {
        $results = array();
        
        // Test arytmetyczny
        $start = microtime( true );
        $sum = 0;
        for ( $i = 0; $i < 100000; $i++ ) {
            $sum += $i * 2;
        }
        $results['arithmetic'] = round( ( microtime( true ) - $start ) * 1000, 2 );
        
        // Test operacji na łańcuchach
        $start = microtime( true );
        $string = '';
        for ( $i = 0; $i < 10000; $i++ ) {
            $string .= 'test';
        }
        $results['string_operations'] = round( ( microtime( true ) - $start ) * 1000, 2 );
        
        // Test operacji na tablicach
        $start = microtime( true );
        $array = array();
        for ( $i = 0; $i < 10000; $i++ ) {
            $array[] = $i;
        }
        sort( $array );
        $results['array_operations'] = round( ( microtime( true ) - $start ) * 1000, 2 );
        
        // Test operacji I/O
        $start = microtime( true );
        $tmp_file = tempnam( sys_get_temp_dir(), 'wp_diagnostics_' );
        for ( $i = 0; $i < 1000; $i++ ) {
            file_put_contents( $tmp_file, 'test data ' . $i, FILE_APPEND );
        }
        $content = file_get_contents( $tmp_file );
        unlink( $tmp_file );
        $results['io_operations'] = round( ( microtime( true ) - $start ) * 1000, 2 );
        
        // Test pamięci
        $start_memory = memory_get_usage();
        $data = array();
        for ( $i = 0; $i < 10000; $i++ ) {
            $data[] = str_repeat( 'x', 100 );
        }
        $results['memory_usage'] = memory_get_usage() - $start_memory;
        
        return $results;
    }
    
    /**
     * Sprawdź problemy z konfiguracją PHP
     */
    public function check_php_issues() {
        $issues = array();
        $warnings = array();
        $recommendations = array();
        
        // Sprawdź wersję PHP
        if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
            $issues[] = sprintf( 
                __( 'Używana wersja PHP %s jest przestarzała. Zalecana minimalna wersja to 7.4', 'wp-diagnostics' ),
                PHP_VERSION 
            );
        } elseif ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
            $warnings[] = sprintf( 
                __( 'Używana wersja PHP %s. Zalecana jest aktualizacja do PHP 8.0 lub nowszej', 'wp-diagnostics' ),
                PHP_VERSION 
            );
        }
        
        // Sprawdź memory_limit
        $memory_limit = $this->parse_size( ini_get( 'memory_limit' ) );
        if ( $memory_limit < 128 * 1024 * 1024 ) { // 128MB
            $issues[] = sprintf( 
                __( 'Memory limit jest za niski: %s. Zalecane minimum to 128M', 'wp-diagnostics' ),
                ini_get( 'memory_limit' )
            );
        } elseif ( $memory_limit < 256 * 1024 * 1024 ) { // 256MB
            $warnings[] = sprintf( 
                __( 'Memory limit %s może być za niski dla większych stron. Zalecane: 256M lub więcej', 'wp-diagnostics' ),
                ini_get( 'memory_limit' )
            );
        }
        
        // Sprawdź max_execution_time
        $max_execution_time = ini_get( 'max_execution_time' );
        if ( $max_execution_time > 0 && $max_execution_time < 30 ) {
            $warnings[] = sprintf( 
                __( 'Max execution time jest bardzo niski: %s sekund', 'wp-diagnostics' ),
                $max_execution_time
            );
        }
        
        // Sprawdź post_max_size i upload_max_filesize
        $post_max_size = $this->parse_size( ini_get( 'post_max_size' ) );
        $upload_max_filesize = $this->parse_size( ini_get( 'upload_max_filesize' ) );
        
        if ( $upload_max_filesize > $post_max_size ) {
            $issues[] = __( 'upload_max_filesize jest większy niż post_max_size', 'wp-diagnostics' );
        }
        
        // Sprawdź max_input_vars
        $max_input_vars = ini_get( 'max_input_vars' );
        if ( $max_input_vars < 1000 ) {
            $warnings[] = sprintf( 
                __( 'max_input_vars jest niski: %s. Może powodować problemy z dużymi formularzami', 'wp-diagnostics' ),
                $max_input_vars
            );
        }
        
        // Sprawdź display_errors
        if ( ini_get( 'display_errors' ) ) {
            $issues[] = __( 'display_errors jest włączony. Powinien być wyłączony w środowisku produkcyjnym', 'wp-diagnostics' );
        }
        
        // Sprawdź allow_url_include
        if ( ini_get( 'allow_url_include' ) ) {
            $issues[] = __( 'allow_url_include jest włączony. To zagrożenie bezpieczeństwa', 'wp-diagnostics' );
        }
        
        // Sprawdź session.save_path
        $session_save_path = ini_get( 'session.save_path' );
        if ( empty( $session_save_path ) ) {
            $warnings[] = __( 'session.save_path nie jest ustawiony', 'wp-diagnostics' );
        } elseif ( ! is_writable( $session_save_path ) ) {
            $issues[] = __( 'session.save_path nie ma uprawnień do zapisu', 'wp-diagnostics' );
        }
        
        // Sprawdź timezone
        $timezone = ini_get( 'date.timezone' );
        if ( empty( $timezone ) ) {
            $warnings[] = __( 'date.timezone nie jest ustawiony', 'wp-diagnostics' );
        }
        
        // Sprawdź OPcache
        if ( ! extension_loaded( 'Zend OPcache' ) || ! ini_get( 'opcache.enable' ) ) {
            $recommendations[] = __( 'OPcache nie jest włączony. Może znacznie poprawić wydajność', 'wp-diagnostics' );
        }
        
        // Sprawdź object cache
        if ( ! wp_using_ext_object_cache() ) {
            $recommendations[] = __( 'Nie używasz zewnętrznego cache obiektów (Redis/Memcached)', 'wp-diagnostics' );
        }
        
        return array(
            'issues' => $issues,
            'warnings' => $warnings,
            'recommendations' => $recommendations
        );
    }
    
    /**
     * Sprawdź błędy PHP z logów
     */
    public function check_php_errors() {
        $errors = array();
        $error_log = ini_get( 'error_log' );
        
        if ( empty( $error_log ) || ! file_exists( $error_log ) ) {
            return array(
                'log_file' => $error_log,
                'accessible' => false,
                'errors' => array(),
                'message' => __( 'Nie można uzyskać dostępu do pliku logów błędów PHP', 'wp-diagnostics' )
            );
        }
        
        if ( ! is_readable( $error_log ) ) {
            return array(
                'log_file' => $error_log,
                'accessible' => false,
                'errors' => array(),
                'message' => __( 'Plik logów błędów PHP nie jest czytelny', 'wp-diagnostics' )
            );
        }
        
        // Odczytaj ostatnie 100 linii z loga
        $lines = $this->tail_file( $error_log, 100 );
        
        foreach ( $lines as $line ) {
            if ( preg_match( '/\[(.*?)\]\s+(.*?):\s+(.*)/', $line, $matches ) ) {
                $errors[] = array(
                    'timestamp' => $matches[1],
                    'type' => $matches[2],
                    'message' => $matches[3]
                );
            }
        }
        
        return array(
            'log_file' => $error_log,
            'accessible' => true,
            'errors' => array_reverse( $errors ), // Najnowsze na górze
            'total_lines' => count( $lines )
        );
    }
    
    /**
     * Parsuj rozmiar pamięci
     */
    private function parse_size( $size ) {
        $unit = preg_replace( '/[^bkmgtpezy]/i', '', $size );
        $size = preg_replace( '/[^0-9\.]/', '', $size );
        
        if ( $unit ) {
            return round( $size * pow( 1024, stripos( 'bkmgtpezy', $unit[0] ) ) );
        } else {
            return round( $size );
        }
    }
    
    /**
     * Odczytaj ostatnie N linii z pliku
     */
    private function tail_file( $file, $lines = 100 ) {
        $handle = fopen( $file, 'r' );
        $linecounter = $lines;
        $pos = -2;
        $beginning = false;
        $text = array();
        
        while ( $linecounter > 0 ) {
            $t = ' ';
            while ( $t != "\n" ) {
                if ( fseek( $handle, $pos, SEEK_END ) == -1 ) {
                    $beginning = true;
                    break;
                }
                $t = fgetc( $handle );
                $pos--;
            }
            $linecounter--;
            if ( $beginning ) {
                rewind( $handle );
            }
            $text[ $lines - $linecounter - 1 ] = fgets( $handle );
            if ( $beginning ) {
                break;
            }
        }
        
        fclose( $handle );
        return array_reverse( $text );
    }
    
    /**
     * Pobierz informacje o PHP
     */
    public function get_php_info() {
        return array(
            'version' => PHP_VERSION,
            'sapi' => php_sapi_name(),
            'os' => PHP_OS,
            'architecture' => PHP_INT_SIZE * 8 . '-bit',
            'zend_version' => zend_version(),
            'memory_limit' => ini_get( 'memory_limit' ),
            'memory_usage' => $this->format_bytes( memory_get_usage() ),
            'memory_peak' => $this->format_bytes( memory_get_peak_usage() )
        );
    }
    
    /**
     * Formatuj bajty
     */
    private function format_bytes( $bytes, $precision = 2 ) {
        $units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
        
        for ( $i = 0; $bytes > 1024 && $i < count( $units ) - 1; $i++ ) {
            $bytes /= 1024;
        }
        
        return round( $bytes, $precision ) . ' ' . $units[ $i ];
    }
}
