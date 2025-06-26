/**
 * WordPress Diagnostics - Network Tests JavaScript
 */

class WPDiagnosticsNetworkTests {
    constructor() {
        this.results = {};
        this.init();
    }

    init() {
        // Inicjalizacja event listenerów
        document.addEventListener('DOMContentLoaded', () => {
            this.bindEvents();
        });
    }

    bindEvents() {
        // Formularz testów sieciowych
        const networkForm = document.getElementById('wp-diagnostics-network-form');
        if (networkForm) {
            networkForm.addEventListener('submit', (e) => {
                this.handleNetworkTests(e);
            });
        }

        // Auto-test przy zmianie adresu
        const addressInput = document.getElementById('test-address');
        if (addressInput) {
            addressInput.addEventListener('change', () => {
                this.performClientTests(addressInput.value);
            });
        }

        // Export buttony
        document.querySelectorAll('.export-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.exportResults(e.target.dataset.format);
            });
        });
    }

    async handleNetworkTests(event) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        const address = formData.get('address');

        if (!address) {
            this.showNotice('Wprowadź adres do testowania', 'error');
            return;
        }

        this.showLoading(true);
        
        try {
            // Wykonaj testy klienckie równolegle z serwerowymi
            const clientTests = this.performClientTests(address);
            
            // Wyślij formularz przez AJAX
            const response = await fetch(wpDiagnostics.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            const serverResults = await response.text();
            const clientResults = await clientTests;

            // Wyświetl wyniki
            this.displayResults(serverResults, clientResults, address);
            
        } catch (error) {
            this.showNotice('Błąd podczas wykonywania testów: ' + error.message, 'error');
        } finally {
            this.showLoading(false);
        }
    }

    async performClientTests(host) {
        const results = {
            timestamp: new Date().toISOString(),
            dns_resolution: await this.resolveDNS(host),
            client_ping: await this.pingFromBrowser(host),
            browser_info: this.getBrowserInfo(),
            network_info: this.getNetworkInfo()
        };

        return results;
    }

    async resolveDNS(host) {
        try {
            const startTime = performance.now();
            const response = await fetch(`https://dns.google/resolve?name=${encodeURIComponent(host)}&type=A`);
            const endTime = performance.now();
            
            if (!response.ok) {
                throw new Error('DNS resolution failed');
            }
            
            const data = await response.json();
            
            return {
                success: true,
                ip: data.Answer?.[0]?.data || 'Nie znaleziono rekordów A',
                time: Math.round(endTime - startTime),
                ttl: data.Answer?.[0]?.TTL || null,
                provider: 'Google DNS'
            };
        } catch (error) {
            return {
                success: false,
                error: error.message,
                time: null
            };
        }
    }

    async pingFromBrowser(host) {
        const tests = [];
        const attempts = 3;

        for (let i = 0; i < attempts; i++) {
            const startTime = performance.now();
            try {
                // Próba pobrania favicon lub innego zasobu
                const response = await fetch(`https://${host}/favicon.ico`, {
                    mode: 'no-cors',
                    cache: 'no-cache',
                    redirect: 'follow'
                });
                
                const endTime = performance.now();
                tests.push({
                    attempt: i + 1,
                    success: true,
                    time: Math.round(endTime - startTime)
                });
            } catch (error) {
                const endTime = performance.now();
                tests.push({
                    attempt: i + 1,
                    success: false,
                    time: Math.round(endTime - startTime),
                    error: error.message
                });
            }
        }

        const successfulTests = tests.filter(t => t.success);
        const avgTime = successfulTests.length > 0 
            ? Math.round(successfulTests.reduce((sum, t) => sum + t.time, 0) / successfulTests.length)
            : null;

        return {
            tests: tests,
            average_time: avgTime,
            success_rate: Math.round((successfulTests.length / attempts) * 100),
            total_attempts: attempts
        };
    }

    getBrowserInfo() {
        return {
            user_agent: navigator.userAgent,
            platform: navigator.platform,
            language: navigator.language,
            viewport: {
                width: window.innerWidth,
                height: window.innerHeight
            },
            screen: {
                width: screen.width,
                height: screen.height,
                color_depth: screen.colorDepth
            },
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone
        };
    }

    getNetworkInfo() {
        const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
        
        if (!connection) {
            return { available: false };
        }

        return {
            available: true,
            effective_type: connection.effectiveType,
            downlink: connection.downlink,
            rtt: connection.rtt,
            save_data: connection.saveData
        };
    }

    displayResults(serverResults, clientResults, address) {
        // Aktualizuj sekcję wyników serwera
        const serverSection = document.getElementById('server-results');
        if (serverSection) {
            serverSection.innerHTML = serverResults;
        }

        // Wyświetl wyniki klienckie
        const clientSection = document.getElementById('client-results');
        if (clientSection) {
            clientSection.innerHTML = this.formatClientResults(clientResults, address);
        }

        // Stwórz tabelę porównania
        this.createComparisonTable(clientResults, address);

        // Zapisz wyniki do zmiennej globalnej dla eksportu
        window.diagnosticsResults = {
            server: serverResults,
            client: clientResults,
            address: address,
            timestamp: new Date().toISOString()
        };
    }

    formatClientResults(results, address) {
        let html = `
            <div class="nd-card">
                <h3>🌐 Wyniki testów klienckich dla: ${address}</h3>
                
                <h4>DNS Resolution</h4>
                <div class="nd-grid">
                    <div class="nd-grid-item">
                        <strong>Status:</strong> ${results.dns_resolution.success ? '✅ Sukces' : '❌ Błąd'}<br>
                        ${results.dns_resolution.success ? 
                            `<strong>IP:</strong> ${results.dns_resolution.ip}<br>
                             <strong>Czas:</strong> ${results.dns_resolution.time}ms<br>
                             <strong>TTL:</strong> ${results.dns_resolution.ttl || 'N/A'}s` :
                            `<strong>Błąd:</strong> ${results.dns_resolution.error}`
                        }
                    </div>
                </div>

                <h4>Test Ping (przez przeglądarkę)</h4>
                <div class="nd-grid">
                    <div class="nd-grid-item">
                        <strong>Średni czas:</strong> ${results.client_ping.average_time || 'N/A'}ms<br>
                        <strong>Sukces:</strong> ${results.client_ping.success_rate}%<br>
                        <strong>Próby:</strong> ${results.client_ping.total_attempts}
                    </div>
                </div>

                <h4>Informacje o przeglądarce</h4>
                <div class="nd-grid">
                    <div class="nd-grid-item">
                        <strong>Platform:</strong> ${results.browser_info.platform}<br>
                        <strong>Język:</strong> ${results.browser_info.language}<br>
                        <strong>Strefa czasowa:</strong> ${results.browser_info.timezone}
                    </div>
                    <div class="nd-grid-item">
                        <strong>Rozdzielczość:</strong> ${results.browser_info.screen.width}x${results.browser_info.screen.height}<br>
                        <strong>Viewport:</strong> ${results.browser_info.viewport.width}x${results.browser_info.viewport.height}<br>
                        <strong>Głębia kolorów:</strong> ${results.browser_info.screen.color_depth}-bit
                    </div>
                </div>`;

        if (results.network_info.available) {
            html += `
                <h4>Informacje o połączeniu</h4>
                <div class="nd-grid">
                    <div class="nd-grid-item">
                        <strong>Typ połączenia:</strong> ${results.network_info.effective_type}<br>
                        <strong>Prędkość pobierania:</strong> ${results.network_info.downlink} Mbps<br>
                        <strong>RTT:</strong> ${results.network_info.rtt}ms<br>
                        <strong>Oszczędzanie danych:</strong> ${results.network_info.save_data ? 'Tak' : 'Nie'}
                    </div>
                </div>`;
        }

        html += `</div>`;
        return html;
    }

    createComparisonTable(clientResults, address) {
        const comparisonSection = document.getElementById('comparison-results');
        if (!comparisonSection) return;

        const html = `
            <div class="nd-card">
                <h3>📊 Porównanie wyników serwer vs klient</h3>
                <table class="nd-comparison-table widefat">
                    <thead>
                        <tr>
                            <th>Test</th>
                            <th>Serwer</th>
                            <th>Klient</th>
                            <th>Różnica</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>DNS Resolution</strong></td>
                            <td id="server-dns-time">-</td>
                            <td>${clientResults.dns_resolution.success ? clientResults.dns_resolution.time + 'ms' : 'Błąd'}</td>
                            <td id="dns-diff">-</td>
                        </tr>
                        <tr>
                            <td><strong>Ping/Connection</strong></td>
                            <td id="server-ping-time">-</td>
                            <td>${clientResults.client_ping.average_time ? clientResults.client_ping.average_time + 'ms' : 'Błąd'}</td>
                            <td id="ping-diff">-</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        `;

        comparisonSection.innerHTML = html;
    }

    exportResults(format) {
        if (!window.diagnosticsResults) {
            this.showNotice('Brak wyników do eksportu. Uruchom najpierw testy.', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'wp_diagnostics_export');
        formData.append('format', format);
        formData.append('data', JSON.stringify(window.diagnosticsResults));
        formData.append('nonce', wpDiagnostics.nonce);

        fetch(wpDiagnostics.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => {
            if (!response.ok) throw new Error('Export failed');
            return response.blob();
        })
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `wp-diagnostics-${new Date().toISOString().slice(0,10)}.${format}`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
            
            this.showNotice('Eksport zakończony pomyślnie!', 'success');
        })
        .catch(error => {
            console.error('Export error:', error);
            this.showNotice('Błąd podczas eksportu: ' + error.message, 'error');
        });
    }

    showLoading(show) {
        const button = document.querySelector('#wp-diagnostics-network-form input[type="submit"]');
        const loader = document.getElementById('diagnostics-loader');
        
        if (button) {
            button.disabled = show;
            button.value = show ? wpDiagnostics.strings.running : 'Uruchom testy';
        }
        
        if (loader) {
            loader.style.display = show ? 'block' : 'none';
        }
    }

    showNotice(message, type = 'info') {
        const noticesContainer = document.getElementById('diagnostics-notices') || document.querySelector('.wrap');
        const notice = document.createElement('div');
        notice.className = `notice notice-${type} is-dismissible nd-notice nd-notice-${type}`;
        notice.innerHTML = `<p>${message}</p><button type="button" class="notice-dismiss" onclick="this.parentElement.remove()">×</button>`;
        
        noticesContainer.insertBefore(notice, noticesContainer.firstChild);
        
        // Auto-remove po 5 sekundach
        setTimeout(() => {
            if (notice.parentElement) {
                notice.remove();
            }
        }, 5000);
    }
}

// Inicjalizacja
const wpDiagnosticsNetworkTests = new WPDiagnosticsNetworkTests();
