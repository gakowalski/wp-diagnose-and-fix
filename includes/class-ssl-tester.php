<?php
class SSL_Tester {
    public function test_ssl($host, $port = 443) {
        $result = "Test SSL/TLS:\n";
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);

        $socket = @stream_socket_client(
            "ssl://{$host}:{$port}",
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$socket) {
            return $result . "Błąd połączenia SSL: $errstr ($errno)";
        }

        $params = stream_context_get_params($socket);
        $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);

        $result .= "Certyfikat:\n";
        $result .= "- Wydany dla: " . $cert['subject']['CN'] . "\n";
        $result .= "- Wydany przez: " . $cert['issuer']['CN'] . "\n";
        $result .= "- Ważny do: " . date('Y-m-d H:i:s', $cert['validTo_time_t']) . "\n";
        
        $protocols = $this->get_supported_protocols($host, $port);
        $result .= "\nObsługiwane protokoły:\n" . implode("\n", $protocols);

        return $result;
    }

    private function get_supported_protocols($host, $port) {
        $protocols = ['SSLv3', 'TLSv1.0', 'TLSv1.1', 'TLSv1.2', 'TLSv1.3'];
        $supported = [];

        foreach ($protocols as $protocol) {
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'min_protocol_version' => $protocol,
                    'max_protocol_version' => $protocol
                ]
            ]);

            if (@stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $context)) {
                $supported[] = "✓ $protocol";
            }
        }

        return $supported;
    }
}
