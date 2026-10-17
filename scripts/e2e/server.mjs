import { execFileSync, spawn } from 'node:child_process';
import { mkdirSync, writeFileSync, openSync, closeSync } from 'node:fs';
import os from 'node:os';
import { root, php, baseURL, runDir, dniMode, driveMode, aiMode } from './environment.mjs';
import { runFixture } from './fixture-runner.mjs';

export async function startServer() {
try {
    const response = await fetch(`${baseURL}/login`, { signal: AbortSignal.timeout(1000) });
    if (response) throw new Error(`El puerto ${new URL(baseURL).port} está ocupado. Cambia E2E_PORT.`);
} catch (error) {
    if (error.message.includes('está ocupado')) throw error;
}
mkdirSync(runDir, { recursive: true });
const versions = runFixture('init');
let sourceRevision = process.env.E2E_SOURCE_REVISION || 'Sin registro (indicar commit al documentar)';
try { sourceRevision = execFileSync('git', ['rev-parse', 'HEAD'], { cwd: root, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim(); } catch {}
writeFileSync(`${runDir}/ambiente.json`, JSON.stringify({
    date: new Date().toISOString(), timezone: 'America/Lima', node: process.version,
    platform: os.platform(), release: os.release(), cpu: os.cpus()[0]?.model,
    logicalCpus: os.cpus().length, ramBytes: os.totalmem(), viewport: '1366x768',
    baseURL, dniMode, driveMode, aiMode, sourceRevision, twoFactor: false, scenario: process.env.E2E_SCENARIO || 'referencia', ...versions,
    note: 'Recursos del equipo anfitrión; no acreditan límites de una VM o escenario mínimo.',
}, null, 2));
// PHP directo evita procesos nietos de artisan serve y permite cierre fiable en Windows.
const log = openSync(`${runDir}/servidor.log`, 'a');
const child = spawn(php, ['-S', new URL(baseURL).host, `${root}/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`], {
    cwd: `${root}/public`, stdio: ['ignore', log, log], env: process.env,
});
closeSync(log);
let startError;
child.on('error', error => { startError = error; });
const stop = async () => {
    if (child.exitCode !== null) return;
    await new Promise(resolve => {
        child.once('exit', resolve);
        child.kill();
        const timer = setTimeout(resolve, 5000);
        timer.unref();
    });
};
for (let i = 0; i < 100; i++) {
    if (startError || child.exitCode !== null) { await stop(); throw startError || new Error('El servidor E2E terminó. Consulta servidor.log.'); }
    try {
        const response = await fetch(`${baseURL}/login`, { signal: AbortSignal.timeout(1500) });
        if (response.ok) return stop;
    } catch {}
    await new Promise(resolve => setTimeout(resolve, 200));
}
await stop();
throw new Error('El servidor de pruebas no respondió; consulta servidor.log.');
}
