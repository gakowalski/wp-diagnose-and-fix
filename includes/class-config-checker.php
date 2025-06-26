<?php
/**
 * Klasa obsługująca sprawdzanie konfiguracji WordPress
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_Diagnostics_Config_Checker {
    
    /**
     * Sprawdź konfigurację WordPress
     */
    public function check_configuration() {
        $results = array(
            'keys_salts' => $this->check_keys_and_salts(),
            'debug_settings' => $this->check_debug_settings(),
            'database_settings' => $this->check_database_settings(),
            'security_settings' => $this->check_security_settings(),
            'file_permissions' => $this->check_file_permissions(),
            'wp_version' => $this->check_wp_version()
        );
        
        return $results;
    }
    
    /**
     * Sprawdź klucze i soli WordPress
     */
    private function check_keys_and_salts() {
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
        
        $default_placeholders = array(
            'put your unique phrase here',
            'unique phrase',
            'your-key-here',
            'your_key_here',
            'add your unique phrase here'
        );
        
        $results = array();
        
        foreach ( $keys_to_check as $key ) {
            if ( defined( $key ) ) {
                $value = constant( $key );
                $is_placeholder = false;
                
                // Check if it's a placeholder
                foreach ( $default_placeholders as $placeholder ) {
                    if ( stripos( $value, $placeholder ) !== false || strlen( $value ) < 32 ) {
                        $is_placeholder = true;
                        break;
                    }
                }
                
                $results[ $key ] = array(
                    'defined' => true,
                    'is_placeholder' => $is_placeholder,
                    'length' => strlen( $value ),
                    'status' => $is_placeholder ? 'placeholder' : 'ok'
                );
            } else {
                $results[ $key ] = array(
                    'defined' => false,
                    'status' => 'missing'
                );
            }
        }
        
        return $results;
    }
    
    /**
     * Sprawdź ustawienia debug
     */
    private function check_debug_settings() {
        $debug_constants = array(
            'WP_DEBUG' => array(
                'type' => 'boolean',
                'production_value' => false,
                'development_value' => true,
                'description' => 'Włącza wyświetlanie błędów PHP'
            ),
            'WP_DEBUG_LOG' => array(
                'type' => 'boolean', 
                'production_value' => true,
                'development_value' => true,
                'description' => 'Zapisuje błędy do pliku debug.log'
            ),
            'WP_DEBUG_DISPLAY' => array(
                'type' => 'boolean',
                'production_value' => false,
                'development_value' => true,
                'description' => 'Wyświetla błędy na stronie'
            ),
            'SCRIPT_DEBUG' => array(
                'type' => 'boolean',
                'production_value' => false,
                'development_value' => true,
                'description' => 'Używa nie-zminifikowanych skryptów'
            ),
            'WP_DEBUG_DISPLAY' => array(
                'type' => 'boolean',
                'production_value' => false,
                'development_value' => true,
                'description' => 'Wyświetla błędy użytkownikom'
            )
        );
        
        $results = array();
        $is_production = $this->is_production_environment();
        
        foreach ( $debug_constants as $constant => $config ) {
            $current_value = defined( $constant ) ? constant( $constant ) : null;
            $recommended_value = $is_production ? $config['production_value'] : $config['development_value'];
            
            $status = 'ok';
            if ( $current_value !== $recommended_value ) {
                $status = $is_production ? 'warning' : 'info';
            }
            
            $results[ $constant ] = array(
                'defined' => defined( $constant ),
                'current_value' => $current_value,
                'recommended_value' => $recommended_value,
                'status' => $status,
                'description' => $config['description'],
                'environment' => $is_production ? 'production' : 'development'
            );
        }
        
        return $results;
    }
    
    /**
     * Sprawdź ustawienia bazy danych
     */
    private function check_database_settings() {
        $db_constants = array(
            'DB_NAME' => 'Nazwa bazy danych',
            'DB_USER' => 'Użytkownik bazy danych',
            'DB_PASSWORD' => 'Hasło do bazy danych',
            'DB_HOST' => 'Host bazy danych',
            'DB_CHARSET' => 'Kodowanie znaków',
            'DB_COLLATE' => 'Sortowanie bazy danych'
        );
        
        $results = array();
        
        foreach ( $db_constants as $constant => $description ) {
            if ( defined( $constant ) ) {
                $value = constant( $constant );
                $results[ $constant ] = array(
                    'defined' => true,
                    'has_value' => ! empty( $value ),
                    'length' => strlen( $value ),
                    'description' => $description,
                    'masked_value' => $constant === 'DB_PASSWORD' ? str_repeat( '*', strlen( $value ) ) : null
                );
            } else {
                $results[ $constant ] = array(
                    'defined' => false,
                    'description' => $description
                );
            }
        }
        
        // Test połączenia z bazą danych
        global $wpdb;
        $results['connection_test'] = array(
            'status' => $wpdb->last_error ? 'error' : 'ok',
            'error' => $wpdb->last_error,
            'server_info' => $wpdb->db_server_info(),
            'charset' => $wpdb->charset,
            'collate' => $wpdb->collate
        );
        
        return $results;
    }
    
    /**
     * Sprawdź ustawienia bezpieczeństwa
     */
    private function check_security_settings() {
        global $wpdb;
        
        $results = array();
        
        // SSL settings
        $results['ssl'] = array(
            'force_ssl_admin' => defined( 'FORCE_SSL_ADMIN' ) ? constant( 'FORCE_SSL_ADMIN' ) : false,
            'force_ssl_login' => defined( 'FORCE_SSL_LOGIN' ) ? constant( 'FORCE_SSL_LOGIN' ) : false,
            'is_ssl' => is_ssl()
        );
        
        // File editing
        $results['file_editing'] = array(
            'disallow_file_edit' => defined( 'DISALLOW_FILE_EDIT' ) ? constant( 'DISALLOW_FILE_EDIT' ) : false,
            'disallow_file_mods' => defined( 'DISALLOW_FILE_MODS' ) ? constant( 'DISALLOW_FILE_MODS' ) : false
        );
        
        // Database prefix
        $results['database'] = array(
            'table_prefix' => $wpdb->prefix,
            'is_default_prefix' => $wpdb->prefix === 'wp_',
            'prefix_length' => strlen( $wpdb->prefix )
        );
        
        // Directory browsing
        $results['directory_security'] = $this->check_directory_security();
        
        // File permissions
        $results['file_permissions'] = $this->check_critical_file_permissions();
        
        // WordPress salts age
        $results['salts_age'] = $this->estimate_salts_age();
        
        return $results;
    }
    
    /**
     * Sprawdź uprawnienia plików
     */
    public function check_file_permissions() {
        $critical_files = array(
            ABSPATH . 'wp-config.php' => 0600,
            ABSPATH . '.htaccess' => 0644,
            ABSPATH => 0755,
            WP_CONTENT_DIR => 0755,
            WP_CONTENT_DIR . '/uploads' => 0755
        );
        
        $results = array();
        
        foreach ( $critical_files as $file_path => $recommended_perms ) {
            if ( file_exists( $file_path ) ) {
                $current_perms = fileperms( $file_path ) & 0777;
                $perms_string = decoct( $current_perms );
                $recommended_string = decoct( $recommended_perms );
                
                $status = 'ok';
                if ( $current_perms > $recommended_perms ) {
                    $status = 'warning'; // Too permissive
                } elseif ( $current_perms < $recommended_perms ) {
                    $status = 'error'; // Too restrictive
                }
                
                $results[ basename( $file_path ) ] = array(
                    'exists' => true,
                    'current_permissions' => $perms_string,
                    'recommended_permissions' => $recommended_string,
                    'status' => $status,
                    'path' => $file_path
                );
            } else {
                $results[ basename( $file_path ) ] = array(
                    'exists' => false,
                    'path' => $file_path
                );
            }
        }
        
        return $results;
    }
    
    /**
     * Sprawdź wersję WordPress
     */
    private function check_wp_version() {
        global $wp_version;
        
        $latest_version = $this->get_latest_wp_version();
        
        return array(
            'current_version' => $wp_version,
            'latest_version' => $latest_version,
            'is_latest' => version_compare( $wp_version, $latest_version, '>=' ),
            'update_available' => version_compare( $wp_version, $latest_version, '<' ),
            'security_update' => $this->is_security_update_available( $wp_version, $latest_version )
        );
    }
    
    /**
     * Czy to środowisko produkcyjne?
     */
    private function is_production_environment() {
        // Simple heuristics to detect production environment
        $indicators = array(
            defined( 'WP_DEBUG' ) && ! WP_DEBUG,
            ! defined( 'WP_DEBUG' ),
            $_SERVER['HTTP_HOST'] ?? '' !== 'localhost',
            strpos( $_SERVER['HTTP_HOST'] ?? '', '.local' ) === false,
            strpos( $_SERVER['HTTP_HOST'] ?? '', '.dev' ) === false,
            strpos( $_SERVER['HTTP_HOST'] ?? '', '.test' ) === false
        );
        
        $production_score = count( array_filter( $indicators ) );
        return $production_score >= 3;
    }
    
    /**
     * Sprawdź bezpieczeństwo katalogów
     */
    private function check_directory_security() {
        $directories = array(
            WP_CONTENT_DIR . '/uploads',
            WP_CONTENT_DIR . '/themes',
            WP_CONTENT_DIR . '/plugins',
            ABSPATH . 'wp-admin',
            ABSPATH . 'wp-includes'
        );
        
        $results = array();
        
        foreach ( $directories as $dir ) {
            $index_file = $dir . '/index.php';
            $htaccess_file = $dir . '/.htaccess';
            
            $results[ basename( $dir ) ] = array(
                'has_index_file' => file_exists( $index_file ),
                'has_htaccess' => file_exists( $htaccess_file ),
                'is_readable' => is_readable( $dir ),
                'is_writable' => is_writable( $dir )
            );
        }
        
        return $results;
    }
    
    /**
     * Sprawdź uprawnienia krytycznych plików
     */
    private function check_critical_file_permissions() {
        $files = array(
            'wp-config.php' => ABSPATH . 'wp-config.php',
            '.htaccess' => ABSPATH . '.htaccess'
        );
        
        $results = array();
        
        foreach ( $files as $name => $path ) {
            if ( file_exists( $path ) ) {
                $perms = fileperms( $path ) & 0777;
                $results[ $name ] = array(
                    'permissions' => decoct( $perms ),
                    'is_world_readable' => ( $perms & 0004 ) !== 0,
                    'is_world_writable' => ( $perms & 0002 ) !== 0,
                    'owner_writable' => ( $perms & 0200 ) !== 0
                );
            }
        }
        
        return $results;
    }
    
    /**
     * Oszacuj wiek kluczy soli
     */
    private function estimate_salts_age() {
        // This is a rough estimate based on installation time
        $install_time = get_option( 'fresh_site' ) ? time() : null;
        
        if ( ! $install_time ) {
            // Try to get from first post
            $first_post = get_posts( array(
                'numberposts' => 1,
                'orderby' => 'date',
                'order' => 'ASC',
                'post_status' => 'any'
            ) );
            
            if ( $first_post ) {
                $install_time = strtotime( $first_post[0]->post_date );
            }
        }
        
        if ( $install_time ) {
            $age_days = round( ( time() - $install_time ) / DAY_IN_SECONDS );
            $status = 'ok';
            
            if ( $age_days > 365 ) {
                $status = 'warning'; // Recommend regenerating after 1 year
            }
            
            return array(
                'estimated_age_days' => $age_days,
                'status' => $status,
                'recommendation' => $age_days > 365 ? 'Rozważ regenerację kluczy bezpieczeństwa' : 'Klucze są stosunkowo świeże'
            );
        }
        
        return array(
            'estimated_age_days' => null,
            'status' => 'unknown',
            'recommendation' => 'Nie można określić wieku kluczy'
        );
    }
    
    /**
     * Pobierz najnowszą wersję WordPress
     */
    private function get_latest_wp_version() {
        $version_check = wp_remote_get( 'https://api.wordpress.org/core/version-check/1.7/' );
        
        if ( is_wp_error( $version_check ) ) {
            return 'unknown';
        }
        
        $version_data = json_decode( wp_remote_retrieve_body( $version_check ), true );
        
        if ( isset( $version_data['offers'][0]['version'] ) ) {
            return $version_data['offers'][0]['version'];
        }
        
        return 'unknown';
    }
    
    /**
     * Sprawdź czy dostępna jest aktualizacja bezpieczeństwa
     */
    private function is_security_update_available( $current, $latest ) {
        if ( $latest === 'unknown' ) {
            return false;
        }
        
        // Simple check - if major.minor version is the same but patch is different,
        // it's likely a security update
        $current_parts = explode( '.', $current );
        $latest_parts = explode( '.', $latest );
        
        if ( count( $current_parts ) >= 2 && count( $latest_parts ) >= 2 ) {
            return $current_parts[0] === $latest_parts[0] && 
                   $current_parts[1] === $latest_parts[1] && 
                   version_compare( $current, $latest, '<' );
        }
        
        return false;
    }
    
    /**
     * Sprawdź konfigurację wp-config.php (główna metoda publiczna)
     */
    public function check_wp_config() {
        return $this->check_configuration();
    }
    
    /**
     * Sprawdź konfigurację bazy danych
     */
    public function check_database_config() {
        return $this->check_database_settings();
    }
    
    /**
     * Sprawdź konfigurację bezpieczeństwa
     */
    public function check_security_config() {
        return $this->check_security_settings();
    }
    
    /**
     * Sprawdź konfigurację debug
     */
    public function check_debug_config() {
        return $this->check_debug_settings();
    }
}
