# Network Diagnostics Tool - WordPress Plugin

Zaawansowane narzędzie do diagnostyki sieci i konfiguracji dla WordPress. Wykonuje kompleksowe testy sieciowe oraz sprawdza konfigurację WordPress, wtyczki, motywy i wydajność PHP.

## Funkcjonalności

### 🌐 Diagnostyka Sieci
- **Ping** - Test połączenia z możliwością wyboru liczby prób
- **Traceroute** - Śledzenie trasy do hosta
- **Test SMTP** - Sprawdzanie połączenia z serwerem SMTP
- **Skanowanie portów** - Sprawdzanie popularnych portów
- **Test MTU** - Sprawdzanie maksymalnej jednostki transmisji
- **Test SSL/TLS** - Analiza certyfikatów SSL
- **Test DNS** - Sprawdzanie rozwiązywania nazw
- **Skanowanie zabezpieczeń** - Wykrywanie WAF i innych zabezpieczeń

### 🔒 Sprawdzanie Konfiguracji WordPress
- **Analiza wp-config.php** - Sprawdzanie kluczy bezpieczeństwa, soli i ustawień
- **Wykrywanie domyślnych placeholderów** - Identyfikacja niezmienionych kluczy
- **Ustawienia debug** - Analiza konfiguracji debugowania
- **Ustawienia bazy danych** - Weryfikacja połączenia z bazą
- **Kontrola bezpieczeństwa** - Sprawdzanie prefiksów tabel, SSL, edycji plików

### 🔌 Analiza Wtyczek i Motywów
- **Aktywne wtyczki** - Lista z informacjami o aktualizacjach
- **Nieaktywne wtyczki** - Wykrywanie przestarzałych wtyczek
- **Analiza motywów** - Sprawdzanie aktywnego motywu i dostępnych aktualizacji
- **Motyw potomny** - Weryfikacja czy używany jest child theme
- **Statystyki** - Podsumowanie wymagających uwagi elementów

### ⚡ Wydajność i Limity PHP
- **Informacje o PHP** - Wersja, SAPI, system operacyjny
- **Limity konfiguracji** - memory_limit, max_execution_time, upload_max_filesize
- **Rozszerzenia PHP** - Status ważnych rozszerzeń (curl, gd, mbstring, itp.)
- **Test wydajności** - Benchmarki matematyczne, I/O i pamięci

### 📊 Dodatkowe Funkcje
- **Porównanie wyników serwer/klient** - Zestawienie testów wykonanych po obu stronach
- **Eksport raportów** - Możliwość eksportu do PDF i CSV
- **Historia testów** - Przechowywanie wyników w bazie danych
- **Podgląd logów WordPress** - Ostatnie błędy z plików logów

### Testy sieciowe
- Ping z wieloma próbami i szczegółowymi statystykami
- Traceroute z analizą TTL
- Test połączeń SMTP
- Skanowanie popularnych portów
- Test MTU
- Analiza SSL/TLS (wersje protokołów, certyfikaty)
- Kompleksowe testy DNS na różnych serwerach
- Wykrywanie WAF i zabezpieczeń

### Funkcje dodatkowe
- Cache DNS dla optymalizacji zapytań
- Porównanie wyników testów server/client
- Eksport raportów do PDF i CSV
- Historia testów
- Podgląd logów błędów WordPress

### Diagnostyka po stronie klienta
- Rozwiązywanie DNS w przeglądarce
- Pomiar czasu odpowiedzi
- Informacje o przeglądarce i połączeniu

## Wymagania

- WordPress 5.0+
- PHP 7.2+
- Moduły PHP:
  - sockets
  - openssl
  - curl
  - fileinfo (dla eksportu PDF)

## Instalacja

1. Skopiuj plugin do katalogu `/wp-content/plugins/`
2. Aktywuj plugin w panelu WordPress
3. Przejdź do menu "Diagnostyka Sieci"
4. Upewnij się, że folder `debug.log` jest zapisywalny

## Konfiguracja

Plugin nie wymaga wstępnej konfiguracji, ale możesz dostosować:
- Liczbę prób ping
- Wybór testowanych portów
- Rozmiary MTU do testowania

## Eksport danych

Dostępne formaty eksportu:
- PDF (pełny raport z formatowaniem)
- CSV (dane tabelaryczne)

## Rozwiązywanie problemów

1. Jeśli traceroute nie działa:
   - Sprawdź uprawnienia PHP
   - Zweryfikuj dostęp do funkcji sieciowych

2. Problemy z testami SSL:
   - Upewnij się, że OpenSSL jest zainstalowany
   - Sprawdź dostęp do portu 443

3. Błędy testów DNS:
   - Sprawdź konfigurację serwerów DNS
   - Zweryfikuj dostęp do zewnętrznych serwerów DNS

4. Eksport nie działa:
   - Sprawdź uprawnienia do zapisu
   - Zainstaluj wymagane moduły PHP

## Bezpieczeństwo

- Plugin wykonuje tylko niezbędne testy
- Nie przechowuje wrażliwych danych
- Wymaga uprawnień administratora
- Wszystkie dane wejściowe są sanityzowane

## Wsparcie

W przypadku problemów:
1. Sprawdź logi WordPress
2. Zweryfikuj uprawnienia
3. Skontaktuj się z autorem

## Licencja

Plugin jest dostępny na licencji GPL v2 lub nowszej.
