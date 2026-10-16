import { type Page, type TestInfo } from '@playwright/test';
import { test, expect, fixture, evidence } from './helpers';

async function openKardex(page: Page, info: TestInfo) {
    const response = await page.goto('/kardex');
    if (response?.status() !== 200) await evidence(page, info, 'kardex-error-apertura', { status: response?.status() });
    expect(response?.status(), 'El Kardex debe abrir correctamente para ejecutar sus filtros').toBe(200);
    await expect(page.getByRole('heading', { name: 'Historial de movimientos' })).toBeVisible();
}

async function allRows(page: Page) {
    const rows: string[][] = [];
    let pages = 0;
    while (true) {
        pages++;
        expect(pages, 'La paginación debe terminar sin ciclos').toBeLessThan(100);
        rows.push(...await page.locator('tbody tr').evaluateAll(trs => trs
            .filter(tr => tr.querySelectorAll('td').length === 9)
            .map(tr => Array.from(tr.querySelectorAll('td'), td => td.textContent?.trim() || ''))));
        const next = page.getByRole('link', { name: 'Siguiente', exact: true });
        if (!await next.count()) break;
        await next.click();
    }
    return { rows, pages };
}

test('CP-KAR-01 — Filtrar Kardex por artículo y movimiento sin mezclar resultados', async ({ page }, info) => {
    test.setTimeout(120_000);
    const data = fixture('kz-kardex');
    await test.step('Abrir historial con materiales y equipos de control', async () => {
        await openKardex(page, info);
        await page.locator('#search').fill(data.prefix);
        await page.getByRole('button', { name: 'Aplicar', exact: true }).click();
        const found = await allRows(page);
        expect(found.rows).toHaveLength(data.movements.length);
        expect(new Set(found.rows.map(r => r[2]))).toEqual(new Set(['Material', 'Equipo']));
        expect(found.pages).toBeGreaterThan(1);
        await evidence(page, info, 'kardex-controles-y-paginacion', found);
    });
    await test.step('Filtrar materiales y recorrer todas las páginas', async () => {
        await page.locator('[name="item_type"][value="material"]').check();
        await page.getByRole('button', { name: 'Aplicar', exact: true }).click();
        const found = await allRows(page);
        const expected = data.movements.filter((m: any) => m.material_id);
        expect(found.rows).toHaveLength(expected.length);
        for (const row of found.rows) expect(row[2]).toBe('Material');
        await evidence(page, info, 'kardex-solo-materiales', found);
    });
    await test.step('Combinar artículo Materiales y movimiento Entrada', async () => {
        await page.locator('[name="tipo_movimiento"][value="ENTRADA"]').check();
        await page.getByRole('button', { name: 'Aplicar', exact: true }).click();
        const found = await allRows(page);
        expect(found.rows).toHaveLength(data.movements.filter((m: any) => m.material_id && m.tipo_movimiento === 'ENTRADA').length);
        for (const row of found.rows) { expect(row[2]).toBe('Material'); expect(row[1]).toBe('ENTRADA'); }
        await evidence(page, info, 'kardex-entradas-materiales', found);
    });
    await test.step('Limpiar filtros y consultar exclusivamente equipos', async () => {
        await page.getByRole('link', { name: 'Limpiar', exact: true }).click();
        await expect(page.locator('#search')).toHaveValue('');
        await expect(page.locator('[name="item_type"][value=""]')).toBeChecked();
        await expect(page.locator('[name="tipo_movimiento"][value=""]')).toBeChecked();
        await page.locator('#search').fill(data.prefix);
        await page.locator('[name="item_type"][value="equipment"]').check();
        await page.getByRole('button', { name: 'Aplicar', exact: true }).click();
        const found = await allRows(page);
        expect(found.rows).toHaveLength(1);
        expect(found.rows[0][2]).toBe('Equipo');
        expect(found.rows[0][4]).toBe(data.equipment.codigo);
        await evidence(page, info, 'kardex-solo-equipos', found);
    });
});

test('CP-KAR-02 — Buscar artículos, filtrar fechas inclusivas y consultar sin coincidencias', async ({ page }, info) => {
    test.setTimeout(120_000);
    const data = fixture('kz-trace');
    await test.step('Buscar por código y por nombre sin incluir otro artículo', async () => {
        await openKardex(page, info);
        for (const search of [data.material.codigo, data.material.nombre]) {
            await page.locator('#search').fill(search);
            await page.getByRole('button', { name: 'Aplicar', exact: true }).click();
            const found = await allRows(page);
            expect(found.rows).toHaveLength(5);
            for (const row of found.rows) expect(row[4]).toBe(data.material.codigo);
            await evidence(page, info, search === data.material.codigo ? 'kardex-busqueda-codigo' : 'kardex-busqueda-nombre', found);
        }
    });
    await test.step('Limpiar y aplicar rango incluyendo sus fechas límite', async () => {
        await page.getByRole('link', { name: 'Limpiar', exact: true }).click();
        await page.locator('#fecha_desde').fill(data.from);
        await page.locator('#fecha_hasta').fill(data.to);
        await page.getByRole('button', { name: 'Aplicar', exact: true }).click();
        const found = await allRows(page);
        expect(found.rows.length).toBeGreaterThanOrEqual(4);
        for (const row of found.rows) {
            const [day, month, year] = row[0].slice(0, 10).split('/');
            const date = `${year}-${month}-${day}`;
            expect(date >= data.from && date <= data.to).toBe(true);
        }
        const own = found.rows.filter(r => r[4] === data.material.codigo);
        expect(own).toHaveLength(3);
        expect(own.map(r => r[0])).toContain(`${data.display_from} 00:00`);
        expect(own.map(r => r[0])).toContain(`${data.display_to} 23:59`);
        await evidence(page, info, 'kardex-rango-inclusivo', found);
    });
    await test.step('Limpiar y buscar un código inexistente', async () => {
        await page.getByRole('link', { name: 'Limpiar', exact: true }).click();
        await expect(page.locator('#fecha_desde')).toHaveValue('');
        await expect(page.locator('#fecha_hasta')).toHaveValue('');
        await page.locator('#search').fill(`INEXISTENTE-${data.prefix}`);
        await page.getByRole('button', { name: 'Aplicar', exact: true }).click();
        expect((await allRows(page)).rows).toHaveLength(0);
        await expect(page.locator('.empty-state')).toContainText('No se encontraron movimientos');
        await evidence(page, info, 'kardex-sin-coincidencias');
    });
});
