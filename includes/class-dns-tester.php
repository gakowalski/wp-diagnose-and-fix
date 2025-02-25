<?php
class DNS_Tester {
    private $dns_servers = [
        'Google' => '8.8.8.8',
        'Cloudflare' => '1.1.1.1',
        'OpenDNS' => '208.67.222.222',
        'Quad9' => '9.9.9.9'
    ];

    public function test_dns_resolution($domain) {
        $result = "Test DNS na różnych serwerach:\n";
        
        foreach ($this->dns_servers as $provider => $server) {
            $result .= "\n$provider ($server):\n";
            $cmd = "nslookup $domain $server";
            $output = $this->execute_dns_query($domain, $server);
            $result .= $output ?: "Brak odpowiedzi\n";
        }

        return $result;
    }

    private function execute_dns_query($domain, $server) {
        $result = '';
        try {
            $dns = dns_get_record($domain, DNS_A + DNS_AAAA + DNS_MX + DNS_NS, [], [$server]);
            foreach ($dns as $record) {
                $result .= "Typ: {$record['type']}, ";
                $result .= "TTL: {$record['ttl']}, ";
                $result .= "Dane: " . ($record['ip'] ?? $record['target'] ?? '') . "\n";
            }
        } catch (Exception $e) {
            $result = "Błąd: " . $e->getMessage() . "\n";
        }
        return $result;
    }
}
