import { test, expect, fixture, evidence } from './helpers';
import { fillMaterial, inventoryRow, bypassNativeValidation } from './inventory-helpers';

test('CP-MAT-01 — Registrar material, ingresar stock y rechazar cantidades inválidas', async ({ page }, info) => {
    test.setTimeout(120_000);
    const data = fixture('inventory-material-data');
    let id: number;
    await test.step('Validar datos obligatorios y registrar material con código automático', async () => {
        await page.goto('/materials/create');
        await page.locator('#stock_minimo').fill('2');
        await page.getByRole('button', { name: 'Registrar material', exact: true }).click();
        await expect.soft(page.locator('.error-message').filter({ hasText: /obligatorio/ }), 'PPF: deben mostrarse los mensajes de campos obligatorios').toHaveCount(2, { timeout: 1500 });
        expect(fixture('inventory-material-state', data.nombre).count).toBe(0);
        await evidence(page, info, 'material-campos-obligatorios');
        const token = await page.locator('form.standard-form [name="_token"]').inputValue();
        const response = await page.request.post('/materials', { headers: { Accept: 'application/json' }, form: { _token: token, stock_actual: '0', stock_minimo: '2', estado: 'ACTIVO' } });
        expect(response.status()).toBe(422);
        const errors = (await response.json()).errors;
        expect(errors).toHaveProperty('nombre');
        expect(errors).toHaveProperty('unidad_base');
        expect(fixture('inventory-material-state', data.nombre).count).toBe(0);
        await info.attach('obligatorios-material-servidor', { body: JSON.stringify(errors), contentType: 'application/json' });
        await fillMaterial(page, data);
        await page.getByRole('button', { name: 'Registrar material', exact: true }).click();
        await expect(page).toHaveURL(/\/materials\/\d+$/);
        const state = fixture('inventory-material-state', data.nombre);
        id = state.record.material_id;
        expect(state.count).toBe(1);
        expect(state.record).toMatchObject({ nombre: data.nombre, unidad_base: 'metros', stock_actual: '10.000', stock_minimo: '2.000', estado: 'ACTIVO' });
        expect(state.record.codigo).toMatch(/^MAT-/);
        expect(state.movements).toHaveLength(1);
        await evidence(page, info, 'material-stock-inicial', state);
        const row = await inventoryRow(page, 'materials', data.nombre);
        await row.getByRole('link', { name: 'Ingreso', exact: true }).click();
    });
    await test.step('Ingresar cinco metros y comprobar stock quince y Kardex', async () => {
        await page.locator('#cantidad').fill('5');
        await page.locator('#observacion').fill('Ingreso ficticio de cinco metros.');
        await page.getByRole('button', { name: 'Confirmar ingreso' }).click();
        await expect(page).toHaveURL(new RegExp(`/materials/${id}$`));
        await page.reload();
        const state = fixture('inventory-material-state', data.nombre);
        expect(state.record.stock_actual).toBe('15.000');
        expect(state.movements).toHaveLength(2);
        expect(state.movements[1]).toMatchObject({ tipo_movimiento: 'ENTRADA', cantidad: '5.000', cantidad_anterior: '10.000', cantidad_posterior: '15.000' });
        expect(state.movements[1].responsable_id).toBeTruthy();
        expect(state.movements[1].fecha_movimiento).toBeTruthy();
        await evidence(page, info, 'ingreso-cinco-stock-quince', state);
    });
    for (const quantity of ['0', '-1']) await test.step(`Rechazar ingreso ${quantity} sin cambiar existencias ni Kardex`, async () => {
        const before = fixture('inventory-material-state', data.nombre);
        await page.goto(`/materials/${id}/entry`);
        await page.locator('#cantidad').fill(quantity);
        await page.getByRole('button', { name: 'Confirmar ingreso' }).click();
        expect(await page.locator('#cantidad').evaluate((el: HTMLInputElement) => el.validity.rangeUnderflow)).toBe(true);
        await bypassNativeValidation(page);
        await page.getByRole('button', { name: 'Confirmar ingreso' }).click();
        await expect(page.locator('.error-message').filter({ hasText: 'mayor a cero' })).toBeVisible();
        expect(fixture('inventory-material-state', data.nombre)).toEqual(before);
        await evidence(page, info, `ingreso-${quantity}-rechazado`, before);
    });
});

test('CP-MAT-02 — Salida de material y rechazo de cantidades inválidas o superiores al stock', async ({ page }, info) => {
    test.setTimeout(120_000);
    const material = fixture('inventory-material');
    const id = material.material_id;
    await test.step('Buscar material y retirar veinte de cien metros', async () => {
        const row = await inventoryRow(page, 'materials', material.nombre);
        expect(fixture('inventory-material-state', material.nombre).record.stock_actual).toBe('100.000');
        await row.getByRole('link', { name: 'Salida', exact: true }).click();
        await expect(page.getByRole('heading', { name: 'Registrar salida de material' })).toBeVisible();
        await page.locator('#cantidad').fill('20');
        await page.locator('#observacion').fill('Salida ficticia de veinte metros.');
        await page.getByRole('button', { name: 'Confirmar salida' }).click();
        await expect(page).toHaveURL(new RegExp(`/materials/${id}$`));
        await page.reload();
        const state = fixture('inventory-material-state', material.nombre);
        expect(state.record.stock_actual).toBe('80.000');
        expect(state.movements).toHaveLength(2);
        expect(state.movements[1]).toMatchObject({ tipo_movimiento: 'SALIDA', cantidad: '20.000', cantidad_anterior: '100.000', cantidad_posterior: '80.000' });
        await evidence(page, info, 'salida-veinte-stock-ochenta', state);
    });
    for (const quantity of ['0', '-1', '81']) await test.step(`Rechazar salida ${quantity} sin cambios parciales`, async () => {
        const before = fixture('inventory-material-state', material.nombre);
        await page.goto(`/materials/${id}/exit`);
        await page.locator('#cantidad').fill(quantity);
        await page.getByRole('button', { name: 'Confirmar salida' }).click();
        expect(await page.locator('#cantidad').evaluate((el: HTMLInputElement) => !el.validity.valid)).toBe(true);
        await bypassNativeValidation(page);
        await page.getByRole('button', { name: 'Confirmar salida' }).click();
        await expect(page.locator('.error-message').filter({ hasText: quantity === '81' ? 'Stock insuficiente' : 'mayor a cero' })).toBeVisible();
        expect(fixture('inventory-material-state', material.nombre)).toEqual(before);
        await evidence(page, info, `salida-${quantity}-rechazada`, before);
    });
    info.annotations.push({ type: 'alcance', description: 'El PPF titula el caso «Salida más ajuste», pero sus pasos y resultados solo describen salidas. No certifica ajuste manual de stock.' });
});
