import { mkdirSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
import { root, runDir, runId, dniMode, driveMode, aiMode } from './environment.mjs';
const require = createRequire(import.meta.url);

export default class PpfReporter {
    results = [];
    onTestEnd(test, result) {
        const cp = test.title.match(/CP-[A-Z]+-\d{2}/)?.[0];
        const state = result.status === 'passed'
            ? (test.annotations.some(a => a.type === 'ppf-inconsistency') ? 'PARCIAL (PPF inconsistente)' : test.annotations.some(a => a.type === 'integration-pending') ? 'PARCIAL (Drive real pendiente)' : cp === 'CP-CLI-01' && dniMode === 'simulado' ? 'PARCIAL (DNI simulado)' : 'OK')
            : result.status === 'skipped' ? 'BLOQUEADO' : 'No OK';
        this.results.push({ cp, cycle: test.repeatEachIndex + 1, status: state,
            actualStatus: result.status, durationMs: result.duration, title: test.title,
            started: result.startTime.toISOString(), retry: result.retry,
            error: result.error?.message || '', errors: result.errors.map(error => error.message || String(error)), annotations: test.annotations,
            evidence: result.attachments.map(a => ({ name: a.name, path: a.path || '(adjunto en reporte HTML)' })),
        });
    }
    onEnd(result) {
        if (!this.results.length) return;
        mkdirSync(runDir, { recursive: true });
        const version = require('@playwright/test/package.json').version;
        writeFileSync(path.join(runDir, 'resumen-ppf.json'), JSON.stringify({
            runId, playwright: version, dniMode, driveMode, aiMode, suiteStatus: result.status, cases: this.results,
            scope: 'Únicamente los casos y pasos ejecutados. No certifica otros módulos, segundo factor, entrega real de correos ni hardware mínimo. Drive solo se acredita con E2E_DRIVE_MODE=real y CP-INS-02 satisfactorio. Las respuestas de IA requieren E2E_AI_MODE=real. Los prerrequisitos preparados por fixture no certifican sus módulos. Si falla la apertura de una vista, solo quedan acreditados los pasos posteriores que se ejecuten explícitamente como diagnóstico; el caso conserva el fallo inicial.',
        }, null, 2));
        const lines = [
            '# Registro de ejecución para el PPF', '', `Ejecución: ${runId}. Playwright ${version}. DNI: ${dniMode}.`, '',
            '| Caso | Ciclo | Resultado | Duración (ms) |', '|---|---|---|---|',
            ...this.results.map(r => `| ${r.cp} | ${r.cycle} | ${r.status} | ${r.durationMs} |`), '',
            'PARCIAL significa que la interfaz utilizó una respuesta DNI simulada: no acredita API Perú real.',
            'PARCIAL (Drive real pendiente) acredita únicamente el guardado local y las restricciones verificadas; no acredita subida real a Drive ni cierre automático con contrato firmado.',
            'PARCIAL (PPF inconsistente) distingue los pasos verificados de un resultado esperado contradictorio. IA sin Gemini real queda BLOQUEADO, aunque se adjunten comprobaciones locales.',
            'Cada caso contiene pasos positivos y negativos. Un rechazo esperado cuenta como OK.',
            'No OK puede indicar una diferencia funcional con el PPF, incluso si todos los pasos del script terminaron. Consulta los errores y adjuntos.',
            'La ejecución no mide requisitos mínimos de hardware. Registra las condiciones reales si realizas ese segundo escenario.',
        ];
        writeFileSync(path.join(runDir, 'tabla-ppf.md'), lines.join('\n'));
        writeFileSync(path.join(root, 'artifacts/e2e/ultimo.json'), JSON.stringify({ runDir, html: path.join(runDir, 'html') }));
        console.log(`\nEvidencias y tabla del PPF: ${runDir}`);
    }
}
