import { test, expect, fixture, evidence } from './helpers';
import { loginAs } from './commercial-helpers';
import { filterAnalytics, invalidRange, oracle, chartValues, clientReport } from './analytics-helpers';

test('CP-BI-01 — Visualizar gráfico por período y rechazar más de cinco años', async ({ page }, info) => {
    test.setTimeout(180_000);
    const data = fixture('ri-data');
    await filterAnalytics(page, '/bi', data);
    await expect(page.getByRole('heading', { name: 'Panel de gestión', exact: true })).toBeVisible();
    const source = oracle(data);
    const values = await chartValues(page);
    expect(values.reduce((sum, row) => sum + Number(row[1].replaceAll(',', '')), 0)).toBe(source.count);
    await expect(page.locator('#bi-indicator')).toHaveValue('clientes');
    await evidence(page, info, 'bi-grafico-y-datos', { source, values });
    await invalidRange(page, '/bi', data, true);
    await expect(page.locator('#desde')).toHaveValue(data.from);
    await evidence(page, info, 'bi-limite-cinco-anos');
});

test('CP-BI-02 — Comparar gráfico con reporte y denegar acceso sin permiso', async ({ page }, info) => {
    test.setTimeout(180_000);
    const data = fixture('ri-data');
    await filterAnalytics(page, '/bi', data);
    const values = await chartValues(page);
    const total = values.reduce((sum, row) => sum + Number(row[1].replaceAll(',', '')), 0);
    const rows = await clientReport(page, data);
    await expect(rows).toHaveCount(total);
    expect(total).toBe(oracle(data).count);
    await evidence(page, info, 'bi-correspondencia-reporte', { values, total });
    await loginAs(page, data.limited);
    const response = await page.goto(`/bi?desde=${data.from}&hasta=${data.to}&proceso=clientes`);
    expect(response?.status()).toBe(403);
    await expect(page.getByRole('heading', { name: 'Panel de gestión', exact: true })).toHaveCount(0);
    for (const client of data.inside) await expect(page.locator('body')).not.toContainText(client.document);
    expect((await page.request.get(`/bi/generate-chart?indicador=clientes&desde=${data.from}&hasta=${data.to}`)).status()).toBe(403);
    await evidence(page, info, 'bi-acceso-denegado');
});
