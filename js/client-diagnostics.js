class ClientDiagnostics {
    constructor() {
        this.results = {};
    }

    async performTests(host) {
        this.results = {
            dnsResolution: await this.resolveDNS(host),
            clientPing: await this.pingFromBrowser(host),
            browserInfo: this.getBrowserInfo()
        };
        return this.results;
    }

    async resolveDNS(host) {
        try {
            const response = await fetch(`https://dns.google/resolve?name=${encodeURIComponent(host)}`);
            const data = await response.json();
            return {
                success: true,
                ip: data.Answer?.[0]?.data || 'Nie znaleziono',
                time: performance.now()
            };
        } catch (error) {
            return { success: false, error: error.message };
        }
    }

    async pingFromBrowser(host) {
        const startTime = performance.now();
        try {
            const response = await fetch(`//${host}/favicon.ico`, {
                mode: 'no-cors',
                cache: 'no-cache'
            });
            const endTime = performance.now();
            return {
                success: true,
                time: Math.round(endTime - startTime)
            };
        } catch (error) {
            return { success: false, error: error.message };
        }
    }

    getBrowserInfo() {
        return {
            userAgent: navigator.userAgent,
            platform: navigator.platform,
            connection: navigator.connection ? {
                type: navigator.connection.effectiveType,
                downlink: navigator.connection.downlink,
                rtt: navigator.connection.rtt
            } : 'Nie dostępne'
        };
    }
}

function exportResults(format) {
    const data = new FormData();
    data.append('action', 'export_results');
    data.append('format', format);
    
    fetch(ajaxurl, {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
    })
    .then(response => response.blob())
    .then(blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `network_diagnostic.${format}`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
    })
    .catch(error => console.error('Export error:', error));
}
