import { execFileSync } from 'node:child_process';
import { test, expect, fixture, evidence } from './helpers';
import { loginAs, pdfText } from './commercial-helpers';
import { clientReport, invalidRange, oracle } from './analytics-helpers';

test('CP-REP-01 — Consultar clientes por período y rechazar rango invertido', async ({ page }, info) => {
    test.setTimeout(180_000);
    const data = fixture('ri-data');
    await test.step('Filtrar y contrastar todos los registros con la base', async () => {
        const rows = await clientReport(page, data);
        const source = oracle(data);
        expect(source.count).toBe(3);
        await expect(rows).toHaveCount(source.count);
        const text = await rows.allTextContents();
        for (const client of source.clients) expect(text.some(t => t.includes(client.numero_documento))).toBe(true);
        for (const client of [data.clients[0], data.clients[4]]) expect(text.some(t => t.includes(client.document))).toBe(false);
        await evidence(page, info, 'reporte-clientes-periodo-y-limites', source);
    });
    await test.step('Rechazar rango invertido y conservar reporte válido', async () => {
        await invalidRange(page, '/reports', data);
        await expect(page.locator('.an-table tbody tr')).toHaveCount(3);
        await evidence(page, info, 'reporte-rango-invertido');
    });
});

test('CP-REP-02 — Exportar PDF y Excel y denegar reporte reservado', async ({ page }, info) => {
    test.setTimeout(180_000);
    const data = fixture('ri-data');
    await clientReport(page, data);
    for (const [format, name] of [['pdf', 'PDF'], ['xlsx', 'Excel']]) {
        await test.step(`Descargar y abrir ${name} para comparar contenido y período`, async () => {
            const pending = page.waitForEvent('download', { timeout: 15_000 }).catch(() => null);
            const http = page.waitForResponse(r => r.url().endsWith('/reports/export') && r.request().method() === 'POST');
            await page.getByRole('button', { name: `Descargar ${name}`, exact: true }).click();
            const response = await http;
            if (response.status() !== 200) {
                await evidence(page, info, `reporte-${format}-error-exportacion`, { status: response.status(), body: (await response.text()).slice(0,2000) });
                expect.soft(response.status(), `Gerencia debe poder exportar ${name}`).toBe(200);
                await clientReport(page, data);
                return;
            }
            const download = await pending;
            expect(download, 'La respuesta satisfactoria debe descargar el archivo').not.toBeNull();
            if (!download) return;
            expect(await download.failure()).toBeNull();
            expect(download.suggestedFilename()).toMatch(new RegExp(`\\.${format}$`));
            const file = info.outputPath(`reporte-clientes.${format}`);
            await download.saveAs(file);
            await info.attach(`reporte-${format}`, { path: file, contentType: format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const fs = await import('node:fs');
            const bytes = fs.readFileSync(file);
            let content: string;
            if (format === 'pdf') {
                expect(bytes.subarray(0,5).toString()).toBe('%PDF-');
                content = pdfText(bytes, info);
            } else {
                expect(bytes.subarray(0,2).toString()).toBe('PK');
                const parsed = JSON.parse(execFileSync(process.env.E2E_SIGNATURE_PYTHON || 'python', ['scripts/e2e/read-xlsx.py', file], { encoding: 'utf8' }));
                content = JSON.stringify(parsed);
                const rows = parsed.flatMap((sheet: any) => sheet.rows);
                const dataRows = rows.filter((row: string[]) => row.some(cell => /^CLI-\d+$/.test(cell)));
                expect(dataRows).toHaveLength(3);
            }
            expect(content).toContain('Clientes registrados');
            expect(content).toContain(data.from);
            expect(content).toContain(data.to);
            for (const client of data.inside) expect(content).toContain(client.document);
            for (const client of [data.clients[0], data.clients[4]]) expect(content).not.toContain(client.document);
            await evidence(page, info, `reporte-${format}-verificado`, { content });
        });
    }
    await test.step('Cuenta con Reportes no puede exportar Inventario reservado', async () => {
        await loginAs(page, data.limited);
        await clientReport(page, data);
        await expect(page.locator('.an-catalog').getByRole('link', { name: 'Inventario', exact: true })).toHaveCount(0);
        const token = await page.locator('input[name="_token"]').first().inputValue();
        const response = await page.request.post('/reports/export', { headers: { 'X-CSRF-TOKEN': token }, form: { type: 'materiales_existencias', format: 'xlsx', from: data.from, to: data.to } });
        expect(response.status()).toBe(403);
        expect(response.headers()['content-disposition']).toBeUndefined();
        expect(await response.text()).not.toContain(data.inside[0].document);
        await evidence(page, info, 'reporte-exportacion-restringida', { status: response.status() });
    });
});
