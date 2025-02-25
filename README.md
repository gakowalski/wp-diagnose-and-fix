# Network Diagnostics Tool

Zaawansowany plugin WordPress do kompleksowej diagnostyki problemów sieciowych.

## Główne funkcje

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
