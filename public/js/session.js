(() => {
    const script = document.currentScript;
    if (!script) return;
    let timer;
    const check = async () => {
        try {
            const response = await fetch(script.dataset.check, {headers: {'Accept': 'application/json'}, cache: 'no-store'});
            if (response.status === 401 || response.status === 419) {
                window.location.replace(script.dataset.login);
                return;
            }
            if (!response.ok) throw new Error('Session check unavailable');
            const data = await response.json();
            timer = setTimeout(check, Math.max(1, data.remaining) * 1000 + 500);
        } catch {
            timer = setTimeout(check, 15000);
        }
    };
    timer = setTimeout(check, Number(script.dataset.timeout) * 1000 + 500);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) { clearTimeout(timer); check(); }
    });
})();
