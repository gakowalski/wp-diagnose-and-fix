# WordPress Diagnostics & Configuration Analyzer

Kompleksowe narzędzie diagnostyczne dla WordPress o modularnej architekturze.

## Opis

Plugin oferuje zaawansowaną diagnostykę WordPress:
- Sprawdzanie konfiguracji wp-config.php
- Analiza wtyczek i motywów  
- Testy wydajności PHP
- Zaawansowane testy sieciowe (ping, traceroute, SSL/TLS, DNS, skanowanie portów)
- Wykrywanie WAF, testy MTU i SMTP
- Porównanie wyników serwer/klient
- Eksport raportów (PDF/CSV/JSON/HTML)
- Historia testów z bazą danych
- Rekomendacje bezpieczeństwa

## Struktura Plików

### Główne Pliki
- `wp-diagnostics.php` - Główny plik pluginu z loaderem i singleton pattern
- `make-package.php` - Script do tworzenia paczki ZIP
- `index.php` - Stary monolityczny plik (zachowany jako backup, wyłączony)

### Klasy Modułowe (`includes/`)
- `class-admin-interface.php` - Interfejs administratora, menu, strony
- `class-network-tests.php` - Testy sieciowe (ping, traceroute, porty)
- `class-config-checker.php` - Sprawdzanie konfiguracji WordPress
- `class-plugins-analyzer.php` - Analiza wtyczek i motywów
- `class-php-checker.php` - Testy wydajności PHP
- `class-export-manager.php` - Eksport raportów
- `class-dns-tester.php` - Zaawansowane testy DNS
- `class-ssl-tester.php` - Testy SSL/TLS
- `class-security-scanner.php` - Skanowanie bezpieczeństwa

### Zasoby (`assets/`)
- `css/admin.css` - Style administratora
- `js/network-tests.js` - JavaScript do testów sieciowych

## Instalacja

1. Wypackuj plik ZIP do katalogu `/wp-content/plugins/`
2. Aktywuj plugin w panelu administratora WordPress
3. Przejdź do menu "Diagnostyka WP"

## Wymagania

- WordPress 5.0+
- PHP 7.4+
- Rozszerzenie cURL
- Rozszerzenie JSON
- Uprawnienia do `exec()` dla zaawansowanych testów sieciowych

## Funkcjonalności

### Testy Sieciowe
- **Ping Test**: Sprawdzanie dostępności hostów z czasami odpowiedzi
- **Traceroute**: Śledzenie trasy pakietów
- **Port Scanner**: Skanowanie otwartych portów
- **DNS Tests**: Sprawdzanie rekordów DNS (A, AAAA, MX, TXT, etc.)
- **SSL/TLS Tests**: Analiza certyfikatów SSL
- **WAF Detection**: Wykrywanie Web Application Firewall
- **MTU Discovery**: Znajdowanie optymalnego MTU
- **SMTP Tests**: Testowanie połączeń mailowych

### Analiza Konfiguracji
- **wp-config.php**: Sprawdzanie ustawień WordPress
- **PHP Configuration**: Analiza ustawień PHP
- **Plugin Analysis**: Status wtyczek, wersje, kompatybilność
- **Theme Analysis**: Sprawdzanie motywów

### Bezpieczeństwo
- **Security Scan**: Podstawowe sprawdzenia bezpieczeństwa
- **File Permissions**: Analiza uprawnień plików
- **Database Security**: Sprawdzanie zabezpieczeń bazy danych

### Eksport i Historia
- **Multiple Formats**: PDF, CSV, JSON, HTML
- **History Tracking**: Zapis wyników w bazie danych
- **Compare Results**: Porównywanie poprzednich testów

## Architektura

Plugin wykorzystuje modularną architekturę opartą na wzorcu Singleton:

```php
WP_Diagnostics_Plugin (main)
├── WP_Diagnostics_Admin_Interface
├── WP_Diagnostics_Network_Tests  
├── WP_Diagnostics_Config_Checker
├── WP_Diagnostics_Plugins_Analyzer
├── WP_Diagnostics_PHP_Checker
├── WP_Diagnostics_Export_Manager
├── WP_Diagnostics_DNS_Tester
├── WP_Diagnostics_SSL_Tester
└── WP_Diagnostics_Security_Scanner
```

### Zalety Modularnej Architektury
- **Łatwość utrzymania**: Każda funkcjonalność w osobnej klasie
- **Skalowalność**: Możliwość łatwego dodawania nowych modułów
- **Testowanie**: Każdy moduł można testować niezależnie
- **Bezpieczeństwo**: Ograniczone interakcje między modułami
- **Performance**: Ładowanie tylko potrzebnych komponentów

## Bezpieczeństwo

### Kontrola Dostępu
- Sprawdzanie uprawnień `manage_options`
- Walidacja nonce dla akcji AJAX
- Sanitacja wszystkich danych wejściowych

### Ochrona Przed Atakami
- Escape wszystkich danych wyjściowych
- Ochrona przed Directory Traversal
- Limitowanie czasu wykonania testów
- Walidacja hostów i portów

## Development

### Dodawanie Nowych Modułów

1. Utwórz nową klasę w katalogu `includes/`:
```php
<?php
class WP_Diagnostics_New_Module {
    public function __construct() {
        // Inicjalizacja
    }
    
    public function run_tests() {
        // Logika testów
    }
}
```

2. Dodaj plik do loadera w `wp-diagnostics.php`:
```php
private function load_includes() {
    $includes = array(
        // ... istniejące pliki
        'class-new-module.php'
    );
}
```

3. Inicjalizuj w `init_components()`:
```php
if ( class_exists( 'WP_Diagnostics_New_Module' ) ) {
    $this->new_module = new WP_Diagnostics_New_Module();
}
```

### Debugging

Plugin zawiera rozbudowane logowanie debug:
- Sprawdzanie istnienia klas przed inicjalizacją
- Logowanie ładowania plików
- Debug output dla wszystkich testów

Włącz debug w `wp-config.php`:
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

## Changelog

### Version 4.2
- Kompletna refaktoryzacja do modularnej architektury
- Dodanie klasy Admin Interface z pełnym menu
- Rozdzielenie testów sieciowych na osobne klasy
- Dodanie eksportu w wielu formatach
- Implementacja historii testów w bazie danych
- Poprawki bezpieczeństwa i walidacji
- Dodanie debug logging
- Optymalizacja ładowania plików

### Version 4.1 i wcześniejsze
- Monolityczna implementacja w index.php
- Podstawowe testy sieciowe
- Eksport CSV

## Support

W przypadku problemów:
1. Sprawdź logi WordPress (`/wp-content/debug.log`)
2. Upewnij się, że wszystkie wymagania są spełnione
3. Sprawdź uprawnienia plików
4. Wyłącz inne pluginy w celu wykluczenia konfliktów

## License

GPL v2 or later
