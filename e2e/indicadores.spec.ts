import { test, expect, fixture, evidence } from './helpers';
import { filterAnalytics, invalidRange, oracle } from './analytics-helpers';

test('CP-IND-01 — Consultar indicadores por proceso y rechazar fechas invertidas', async ({ page }, info) => {
    test.setTimeout(180_000);
    const data = fixture('ri-data');
    await filterAnalytics(page, '/indicators', data);
    const source = oracle(data);
    const activity = page.locator('.an-card').filter({ has: page.getByRole('heading', { name: 'Clientes registrados', exact: true }) });
    await expect(activity.locator('.an-number')).toHaveText(String(source.count));
    await expect(page.getByRole('heading', { name: 'Contratos registrados', exact: true })).toHaveCount(0);
    await evidence(page, info, 'indicadores-clientes-fuente', source);
    await invalidRange(page, '/indicators', data);
    await expect(activity.locator('.an-number')).toHaveText(String(source.count));
    await evidence(page, info, 'indicadores-rango-invertido');
});

test('CP-IND-02 — Eficiencia temporal de instalaciones y procesos sin medición', async ({ page }, info) => {
    test.setTimeout(180_000);
    const data = fixture('ri-data');
    await filterAnalytics(page, '/indicators', data, 'instalaciones');
    const source = oracle(data);
    expect(source.seconds.sort((a: number,b: number) => a-b)).toEqual([600,1800]);
    const section = page.locator('[aria-labelledby="temporal-title"]');
    const observed = await section.innerText();
    await evidence(page, info, 'indicador-eficiencia-calculo', { source, observed });
    expect.soft(observed, 'Promedio de los tiempos persistidos en ejecuciones_instalacion').toContain('20.00 min');
    expect.soft(observed).toContain('2 instalaciones finalizadas');
    expect.soft(observed).toContain('Mínimo: 10.00 min');
    expect.soft(observed).toContain('Máximo: 30.00 min');
    const details = section.getByText('Ver inicio, fin y cálculo', { exact: true });
    expect.soft(await details.count(), 'Debe permitir consultar inicio, fin y cálculo').toBe(1);
    if (await details.count()) {
        await details.click();
        await expect(section).toContainText('Iniciar instalación');
        await expect(section).toContainText('fotografía y el contrato firmado verificado');
    }
    await page.locator('#proceso').selectOption('clientes');
    await page.getByRole('button', { name: 'Aplicar filtros', exact: true }).click();
    await expect(section).toContainText('Sin medición automática');
    await expect(page.getByText('Registrar observación del pretest', { exact: true })).toHaveCount(0);
    await evidence(page, info, 'indicador-sin-medicion-automatica');
});
