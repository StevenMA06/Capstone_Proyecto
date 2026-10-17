import { execFileSync } from 'node:child_process';
import { root, php } from './environment.mjs';

function diagnostic(value) {
    let text = String(value || '').trim();
    for (const key of ['GEMINI_API_KEY', 'E2E_GEMINI_API_KEY', 'DB_PASSWORD', 'E2E_DB_PASSWORD', 'APIPERU_API_KEY', 'E2E_APIPERU_API_KEY', 'INSTALLATION_DRIVE_CLIENT_SECRET', 'INSTALLATION_DRIVE_REFRESH_TOKEN', 'E2E_INSTALLATION_DRIVE_CLIENT_SECRET', 'E2E_INSTALLATION_DRIVE_REFRESH_TOKEN']) {
        const secret = process.env[key];
        if (secret) text = text.replaceAll(secret, '[oculto]');
    }
    return text.slice(0, 6000);
}

export function runFixture(command, args = [], execute = execFileSync) {
    let output;
    try {
        output = execute(php, ['scripts/e2e/fixture.php', command, ...args], {
            cwd: root, encoding: 'utf8', env: process.env, stdio: ['ignore', 'pipe', 'pipe'],
        });
    } catch (error) {
        const detail = diagnostic(String(error.stderr || '').trim() || String(error.stdout || '').trim() || error.message);
        throw new Error(`No se pudo ejecutar la preparación E2E (${command}).\n${detail}\nRevisa .env.e2e y que MySQL esté iniciado.`);
    }
    try {
        const result = JSON.parse(String(output).replace(/^\uFEFF/, '').trim());
        if (!result || typeof result !== 'object' || Array.isArray(result)) throw new Error('Formato inesperado');
        return result;
    } catch {
        throw new Error(`La preparación E2E (${command}) no devolvió JSON válido. PHP informó:\n${diagnostic(output) || '(sin salida)'}\nRevisa la configuración E2E; no es un error de los casos funcionales.`);
    }
}
