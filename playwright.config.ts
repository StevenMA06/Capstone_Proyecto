import { defineConfig } from '@playwright/test';
import { baseURL, runDir, dniMode } from './scripts/e2e/environment.mjs';

export default defineConfig({
    testDir: './e2e', fullyParallel: false, workers: 1, retries: 0,
    timeout: 60_000, expect: { timeout: 10_000 }, outputDir: `${runDir}/evidencias`,
    metadata: { dniMode, alcance: 'CP-CLI-01/02, CP-PLA-01/02, CP-CON-01/02, CP-PAG-01/02, CP-EQU-01/02, CP-MAT-01/02, CP-KAR-01/02, CP-ZON-01/02, CP-PED-01/02, CP-INS-01/02; no evalúa RF-ACC' },
    reporter: [
        ['list'], ['html', { outputFolder: `${runDir}/html`, open: 'never' }],
        ['json', { outputFile: `${runDir}/resultados.json` }],
        ['./scripts/e2e/ppf-reporter.mjs'],
    ],
    use: {
        baseURL, viewport: { width: 1366, height: 768 }, timezoneId: 'America/Lima',
        trace: 'on', video: 'on', screenshot: 'only-on-failure',
        channel: process.env.E2E_BROWSER_CHANNEL === 'chrome' ? 'chrome' : undefined,
    },
    projects: [{ name: 'chromium' }],
    globalSetup: './e2e/global-setup.ts',
});
