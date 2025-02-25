<?php
class Security_Scanner {
    public function detect_waf($host) {
        $result = "Wykrywanie zabezpieczeń:\n";
        $headers = $this->get_headers($host);
        
        $waf_signatures = [
            'x-firewall' => 'Generic Firewall',
            'server: cloudflare' => 'Cloudflare',
            'x-sucuri-id' => 'Sucuri',
            'x-cdn' => 'Generic CDN',
            'x-status-code: 403' => 'WAF Block Detection'
        ];

        foreach ($waf_signatures as $signature => $waf_name) {
            if ($this->check_signature($headers, $signature)) {
                $result .= "✓ Wykryto: $waf_name\n";
            }
        }

        return $result;
    }

    private function get_headers($host) {
        $ch = curl_init("https://$host");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    private function check_signature($headers, $signature) {
        return stripos($headers, $signature) !== false;
    }
}
