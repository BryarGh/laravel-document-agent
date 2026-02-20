<div class="document-agent-status" data-agent-status>
    Agent: Checking...
</div>
<script>
    (async () => {
        const el = document.querySelector('[data-agent-status]');
        if (!el) return;
        const script = await import('/vendor/document-agent/agent.js').catch(() => null);
        if (!script || !script.detectAgent) {
            el.textContent = 'Agent: Not detected';
            return;
        }
        const res = await script.detectAgent();
        if (res.online) {
            let status = `Agent v${res.version || 'unknown'} — `;
            try {
                const statusRes = await fetch(`http://127.0.0.1:${res.port}/status`);
                if (statusRes.ok) {
                    const s = await statusRes.json();
                    const scanner = s.default_scanner_available ? 'Scanner: Yes' : 'Scanner: No';
                    const queue = `Queue: ${s.queued_jobs ?? s.queued_uploads_count ?? 0}`;
                    status += `${scanner}, ${queue}`;
                } else {
                    status += 'Status unavailable';
                }
            } catch (e) {
                status += 'Status unavailable';
            }
            el.textContent = status;
        } else {
            el.textContent = 'Agent: Not detected';
        }
    })();
</script>
