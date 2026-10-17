import { type Page } from '@playwright/test';
import { test, expect, fixture, evidence } from './helpers';

export const oracle = (data: any) => fixture('ri-oracle', `${data.from}|${data.to}`);
export async function filterAnalytics(page: Page, path: string, data: any, process = 'clientes') {
    const opened = await page.goto(path);
    const status = opened?.status();
    if (status !== 200) {
        await evidence(page, test.info(), 'error-apertura-analitica', { path, status });
        expect.soft(status, 'La entrada desde el menú debe abrir sin errores').toBe(200);
        // Continuar el diagnóstico por un proceso específico no elimina el fallo de apertura.
        expect((await page.goto(`${path}?proceso=${process}`))?.status()).toBe(200);
    }
    await page.locator('#desde').fill(data.from);
    await page.locator('#hasta').fill(data.to);
    await page.locator('#proceso').selectOption(process);
    await page.getByRole('button', { name: 'Aplicar filtros', exact: true }).click();
    await expect(page.locator('#proceso')).toHaveValue(process);
}
export async function clientReport(page: Page, data: any) {
    expect((await page.goto('/reports'))?.status()).toBe(200);
    await page.locator('.an-catalog').getByRole('link', { name: 'Clientes', exact: true }).click();
    await page.locator('#tipo').selectOption('clientes_altas');
    await page.locator('#desde').fill(data.from);
    await page.locator('#hasta').fill(data.to);
    await page.getByRole('button', { name: 'Filtrar', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Clientes registrados', exact: true })).toBeVisible();
    return page.locator('.an-table tbody tr');
}
export async function chartValues(page: Page) {
    const chart = page.locator('[aria-labelledby="explorer-title"]');
    await chart.getByText('Ver valores exactos del gráfico', { exact: true }).click();
    return chart.locator('tbody tr').evaluateAll(rows => rows.map(row => Array.from(row.querySelectorAll('td'), cell => cell.textContent?.trim() || '')));
}
export async function invalidRange(page: Page, path: string, data: any, tooLong = false) {
    await page.locator('#desde').fill(tooLong ? '2000-01-01' : data.to);
    await page.locator('#hasta').fill(tooLong ? '2006-01-02' : data.from);
    await page.getByRole('button', { name: path === '/reports' ? 'Filtrar' : 'Aplicar filtros', exact: true }).click();
    await expect(page.getByRole('alert')).toContainText(tooLong ? /cinco años/ : /fecha|posterior|igual/);
    const response = await page.request.get(`${path}?desde=${tooLong ? '2000-01-01' : data.to}&hasta=${tooLong ? '2006-01-02' : data.from}&proceso=clientes`, { headers: { Accept: 'application/json' } });
    expect(response.status()).toBe(422);
    expect((await response.json()).errors.hasta).toBeTruthy();
}
