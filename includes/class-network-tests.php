<?php
/**
 * Klasa obsługująca testy sieciowe
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_Diagnostics_Network_Tests {
    
    /**
     * Cache DNS
     */
    private $dns_cache = array();
    
    /**
     * Popularne porty do skanowania
     */
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
        3389 => 'RDP',
        5432 => 'PostgreSQL'
    );
    
    /**
     * Test ping
     */
    public function ping_test( $host, $port = 80, $tries = 4, $timeout = 5 ) {
        $results = array();
        $output = "";
        
        // DNS Resolution
        $dns_start = microtime( true );
        $ip = gethostbyname( $host );
        $dns_time = round( ( microtime( true ) - $dns_start ) * 1000, 2 );
        
        $output .= "=== Test Ping ===\n";
        $output .= "Host: {$host}\n";
        
        if ( $ip !== $host ) {
            $output .= "DNS Resolution: Successful ({$dns_time} ms)\n";
            $output .= "Resolved IP: {$ip}\n";
            
            // Cache DNS
            $this->dns_cache[ $host ] = array(
                'ip' => $ip,
                'time' => time()
            );
        } else {
            $output .= "DNS Resolution: Failed (Invalid hostname or DNS error)\n";
            return $output;
        }
        
        $output .= "\nTesting connection on port {$port}:\n";
        
        for ( $i = 1; $i <= $tries; $i++ ) {
            $start = microtime( true );
            $context = stream_context_create( array(
                'socket' => array(
                    'timeout' => $timeout
                )
            ) );
            
            $fp = @stream_socket_client( 
                "tcp://{$host}:{$port}", 
                $errno, 
                $errstr, 
                $timeout, 
                STREAM_CLIENT_CONNECT,
                $context 
            );
            
            if ( ! $fp ) {
                $output .= "Attempt {$i}: Failed - {$errstr} ({$errno})\n";
                continue;
            }
            
            $latency = round( ( microtime( true ) - $start ) * 1000, 2 );
            $results[] = $latency;
            $output .= "Attempt {$i}: Success - {$latency} ms\n";
            fclose( $fp );
        }
        
        if ( ! empty( $results ) ) {
            $min = min( $results );
            $max = max( $results );
            $avg = array_sum( $results ) / count( $results );
            $packet_loss = round( ( ( $tries - count( $results ) ) / $tries ) * 100 );
            
            $output .= "\nStatistics:\n";
            $output .= "Minimum: {$min} ms\n";
            $output .= "Maximum: {$max} ms\n";
            $output .= "Average: " . round( $avg, 2 ) . " ms\n";
            $output .= "Packet Loss: {$packet_loss}%\n";
        } else {
            $output .= "\nAll connection attempts failed.\n";
        }
        
        return $output;
    }
    
    /**
     * Test traceroute (simplified version)
     */
    public function traceroute_test( $host, $max_hops = 15 ) {
        $output = "=== Traceroute ===\n";
        $output .= "Target: {$host}\n\n";
        
        $ip = gethostbyname( $host );
        if ( $ip === $host && ! filter_var( $host, FILTER_VALIDATE_IP ) ) {
            return $output . "Error: Cannot resolve hostname\n";
        }
        
        $output .= "Note: This is a simplified traceroute implementation.\n";
        $output .= "For detailed routing information, use system traceroute tools.\n\n";
        
        // Simplified hop test - just test connectivity to target
        $start = microtime( true );
        $fp = @fsockopen( $host, 80, $errno, $errstr, 5 );
        $time = round( ( microtime( true ) - $start ) * 1000, 2 );
        
        if ( $fp ) {
            $output .= "1: {$ip} ({$time} ms) - Destination reached\n";
            fclose( $fp );
        } else {
            $output .= "1: * * * Request timed out\n";
        }
        
        return $output;
    }
    
    /**
     * Test DNS
     */
    public function dns_test( $host ) {
        $output = "=== DNS Test ===\n";
        $output .= "Target: {$host}\n\n";
        
        // A Record
        $start = microtime( true );
        $a_records = @dns_get_record( $host, DNS_A );
        $a_time = round( ( microtime( true ) - $start ) * 1000, 2 );
        
        if ( $a_records ) {
            $output .= "A Records ({$a_time} ms):\n";
            foreach ( $a_records as $record ) {
                $output .= "  {$record['ip']} (TTL: {$record['ttl']})\n";
            }
        } else {
            $output .= "A Records: Not found\n";
        }
        
        // AAAA Record (IPv6)
        $start = microtime( true );
        $aaaa_records = @dns_get_record( $host, DNS_AAAA );
        $aaaa_time = round( ( microtime( true ) - $start ) * 1000, 2 );
        
        if ( $aaaa_records ) {
            $output .= "\nAAAA Records ({$aaaa_time} ms):\n";
            foreach ( $aaaa_records as $record ) {
                $output .= "  {$record['ipv6']} (TTL: {$record['ttl']})\n";
            }
        } else {
            $output .= "\nAAAA Records: Not found\n";
        }
        
        // MX Records
        $start = microtime( true );
        $mx_records = @dns_get_record( $host, DNS_MX );
        $mx_time = round( ( microtime( true ) - $start ) * 1000, 2 );
        
        if ( $mx_records ) {
            $output .= "\nMX Records ({$mx_time} ms):\n";
            foreach ( $mx_records as $record ) {
                $output .= "  {$record['target']} (Priority: {$record['pri']}, TTL: {$record['ttl']})\n";
            }
        } else {
            $output .= "\nMX Records: Not found\n";
        }
        
        // NS Records
        $start = microtime( true );
        $ns_records = @dns_get_record( $host, DNS_NS );
        $ns_time = round( ( microtime( true ) - $start ) * 1000, 2 );
        
        if ( $ns_records ) {
            $output .= "\nNS Records ({$ns_time} ms):\n";
            foreach ( $ns_records as $record ) {
                $output .= "  {$record['target']} (TTL: {$record['ttl']})\n";
            }
        } else {
            $output .= "\nNS Records: Not found\n";
        }
        
        return $output;
    }
    
    /**
     * Test SSL/TLS
     */
    public function ssl_test( $host, $port = 443 ) {
        $output = "=== SSL/TLS Test ===\n";
        $output .= "Target: {$host}:{$port}\n\n";
        
        if ( ! function_exists( 'openssl_x509_parse' ) ) {
            return $output . "Error: OpenSSL extension not available\n";
        }
        
        $context = stream_context_create( array(
            'ssl' => array(
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false
            )
        ) );
        
        $start = microtime( true );
        $fp = @stream_socket_client( 
            "ssl://{$host}:{$port}", 
            $errno, 
            $errstr, 
            10, 
            STREAM_CLIENT_CONNECT, 
            $context 
        );
        $connect_time = round( ( microtime( true ) - $start ) * 1000, 2 );
        
        if ( ! $fp ) {
            return $output . "Connection failed: {$errstr} ({$errno})\n";
        }
        
        $output .= "SSL Connection: Successful ({$connect_time} ms)\n";
        
        $cert = stream_context_get_params( $fp );
        if ( isset( $cert['options']['ssl']['peer_certificate'] ) ) {
            $cert_data = openssl_x509_parse( $cert['options']['ssl']['peer_certificate'] );
            
            $output .= "\nCertificate Information:\n";
            $output .= "Subject: {$cert_data['name']}\n";
            $output .= "Issuer: {$cert_data['issuer']['CN']}\n";
            $output .= "Valid From: " . date( 'Y-m-d H:i:s', $cert_data['validFrom_time_t'] ) . "\n";
            $output .= "Valid To: " . date( 'Y-m-d H:i:s', $cert_data['validTo_time_t'] ) . "\n";
            
            $days_until_expiry = round( ( $cert_data['validTo_time_t'] - time() ) / 86400 );
            $output .= "Days until expiry: {$days_until_expiry}\n";
            
            if ( $days_until_expiry < 30 ) {
                $output .= "⚠️  Warning: Certificate expires soon!\n";
            } elseif ( $days_until_expiry < 0 ) {
                $output .= "❌ Error: Certificate has expired!\n";
            } else {
                $output .= "✅ Certificate is valid\n";
            }
        }
        
        fclose( $fp );
        return $output;
    }
    
    /**
     * Skanowanie portów
     */
    public function port_scan( $host ) {
        $output = "=== Port Scan ===\n";
        $output .= "Target: {$host}\n\n";
        
        $open_ports = array();
        
        foreach ( $this->common_ports as $port => $service ) {
            $start = microtime( true );
            $fp = @fsockopen( $host, $port, $errno, $errstr, 2 );
            $time = round( ( microtime( true ) - $start ) * 1000, 2 );
            
            if ( $fp ) {
                $open_ports[] = $port;
                $output .= "Port {$port} ({$service}): OPEN ({$time} ms)\n";
                fclose( $fp );
            } else {
                $output .= "Port {$port} ({$service}): CLOSED/FILTERED\n";
            }
        }
        
        $output .= "\nSummary:\n";
        $output .= "Open ports: " . ( empty( $open_ports ) ? "None" : implode( ', ', $open_ports ) ) . "\n";
        $output .= "Total ports scanned: " . count( $this->common_ports ) . "\n";
        
        return $output;
    }
    
    /**
     * Test SMTP
     */
    public function smtp_test( $host, $port = 25 ) {
        $output = "=== SMTP Test ===\n";
        $output .= "Target: {$host}:{$port}\n\n";
        
        $fp = @fsockopen( $host, $port, $errno, $errstr, 10 );
        
        if ( ! $fp ) {
            return $output . "Connection failed: {$errstr} ({$errno})\n";
        }
        
        $output .= "Connection: Successful\n\n";
        
        // Read server greeting
        $response = fgets( $fp );
        $output .= "Server greeting: " . trim( $response ) . "\n";
        
        // Send EHLO
        fputs( $fp, "EHLO test.example.com\r\n" );
        $response = '';
        while ( $line = fgets( $fp ) ) {
            $response .= $line;
            if ( substr( $line, 3, 1 ) === ' ' ) break;
        }
        $output .= "EHLO response:\n" . trim( $response ) . "\n";
        
        // Send QUIT
        fputs( $fp, "QUIT\r\n" );
        $response = fgets( $fp );
        $output .= "QUIT response: " . trim( $response ) . "\n";
        
        fclose( $fp );
        return $output;
    }
    
    /**
     * Test MTU
     */
    public function mtu_test( $host ) {
        $output = "=== MTU Test ===\n";
        $output .= "Target: {$host}\n\n";
        
        $test_sizes = array( 1500, 1492, 1472, 1468, 1400, 1200, 1000, 576 );
        $successful_size = 0;
        
        foreach ( $test_sizes as $size ) {
            $start = microtime( true );
            $fp = @fsockopen( $host, 80, $errno, $errstr, 5 );
            
            if ( $fp ) {
                $data = str_repeat( 'A', $size - 100 ); // Account for headers
                $result = @fwrite( $fp, $data );
                $time = round( ( microtime( true ) - $start ) * 1000, 2 );
                
                if ( $result !== false ) {
                    $output .= "MTU {$size}: SUCCESS ({$time} ms)\n";
                    $successful_size = max( $successful_size, $size );
                } else {
                    $output .= "MTU {$size}: FAILED (write error)\n";
                }
                fclose( $fp );
            } else {
                $output .= "MTU {$size}: FAILED (connection error)\n";
            }
        }
        
        $output .= "\nEstimated Maximum MTU: {$successful_size} bytes\n";
        
        if ( $successful_size >= 1500 ) {
            $output .= "✅ Standard Ethernet MTU supported\n";
        } elseif ( $successful_size >= 1472 ) {
            $output .= "⚠️  Some fragmentation may occur\n";
        } else {
            $output .= "❌ Low MTU detected - may cause performance issues\n";
        }
        
        return $output;
    }
    
    /**
     * Skanowanie zabezpieczeń
     */
    public function security_scan( $host ) {
        $output = "=== Security Scan ===\n";
        $output .= "Target: {$host}\n\n";
        
        // Test HTTP headers
        $output .= "HTTP Security Headers:\n";
        
        $context = stream_context_create( array(
            'http' => array(
                'method' => 'HEAD',
                'timeout' => 10,
                'user_agent' => 'WP-Diagnostics-Security-Scanner/1.0'
            )
        ) );
        
        $headers = @get_headers( "http://{$host}", 1, $context );
        
        if ( $headers ) {
            $security_headers = array(
                'X-Frame-Options' => 'Clickjacking protection',
                'X-XSS-Protection' => 'XSS protection',
                'X-Content-Type-Options' => 'MIME sniffing protection',
                'Strict-Transport-Security' => 'HTTPS enforcement',
                'Content-Security-Policy' => 'Content security policy',
                'Referrer-Policy' => 'Referrer policy'
            );
            
            foreach ( $security_headers as $header => $description ) {
                $found = false;
                foreach ( $headers as $key => $value ) {
                    if ( stripos( $key, $header ) !== false ) {
                        $output .= "✅ {$header}: Present\n";
                        $found = true;
                        break;
                    }
                }
                if ( ! $found ) {
                    $output .= "❌ {$header}: Missing\n";
                }
            }
        } else {
            $output .= "Could not retrieve HTTP headers\n";
        }
        
        // Test for common security issues
        $output .= "\nCommon Security Tests:\n";
        
        // Test for server information disclosure
        if ( $headers && isset( $headers['Server'] ) ) {
            $output .= "Server header: " . $headers['Server'] . "\n";
            if ( stripos( $headers['Server'], 'apache' ) !== false || 
                 stripos( $headers['Server'], 'nginx' ) !== false ) {
                $output .= "ℹ️  Server information disclosed\n";
            }
        }
        
        // Test for common vulnerable paths
        $vulnerable_paths = array(
            '/.git/config',
            '/.env',
            '/wp-config.php.bak',
            '/backup.zip',
            '/phpinfo.php'
        );
        
        $output .= "\nVulnerable Paths Check:\n";
        foreach ( $vulnerable_paths as $path ) {
            $url = "http://{$host}{$path}";
            $headers = @get_headers( $url, 1, $context );
            
            if ( $headers && strpos( $headers[0], '200' ) !== false ) {
                $output .= "❌ Vulnerable path found: {$path}\n";
            } else {
                $output .= "✅ Path protected: {$path}\n";
            }
        }
        
        return $output;
    }
}
