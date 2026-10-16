import { test, expect, fixture, evidence } from './helpers';
import { fillEquipment, inventoryRow } from './inventory-helpers';

test('CP-EQU-01 — Registro de equipo y rechazo de serie duplicada', async ({ page }, info) => {
    test.setTimeout(120_000);
    const data = fixture('inventory-equipment-data');
    await test.step('Rechazar campos obligatorios vacíos sin insertar el equipo', async () => {
        await page.goto('/equipments');
        await page.getByRole('link', { name: /Registrar equipo/ }).click();
        await page.locator('#numero_serie').fill(data.numero_serie);
        await expect(page.locator('#estado')).toHaveValue('DISPONIBLE');
        await page.getByRole('button', { name: 'Registrar equipo', exact: true }).click();
        await expect.soft(page.locator('.error-message').filter({ hasText: /obligatorio/ }), 'PPF: deben mostrarse los mensajes de campos obligatorios').toHaveCount(2, { timeout: 1500 });
        expect(fixture('inventory-equipment-state', data.numero_serie).count).toBe(0);
        await evidence(page, info, 'equipo-campos-obligatorios');
        const token = await page.locator('form.standard-form [name="_token"]').inputValue();
        const response = await page.request.post('/equipments', { headers: { Accept: 'application/json' }, form: { _token: token, numero_serie: data.numero_serie, condicion: 'NUEVO', estado: 'DISPONIBLE' } });
        expect(response.status()).toBe(422);
        const errors = (await response.json()).errors;
        expect(errors).toHaveProperty('nombre');
        expect(errors).toHaveProperty('tipo');
        expect(fixture('inventory-equipment-state', data.numero_serie).count).toBe(0);
        await info.attach('obligatorios-equipo-servidor', { body: JSON.stringify(errors), contentType: 'application/json' });
    });
    await test.step('Registrar el equipo y comprobar listado, ficha y Kardex', async () => {
        await fillEquipment(page, data);
        await page.getByRole('button', { name: 'Registrar equipo', exact: true }).click();
        await expect(page).toHaveURL(/\/equipments\/\d+$/);
        await page.reload();
        const state = fixture('inventory-equipment-state', data.numero_serie);
        expect(state.count).toBe(1);
        expect(state.record).toMatchObject(data);
        expect(state.record.codigo).toMatch(/^EQ-/);
        expect(state.movements).toHaveLength(1);
        expect(state.movements[0]).toMatchObject({ tipo_movimiento: 'ENTRADA', cantidad: '1.000' });
        await evidence(page, info, 'equipo-registrado', state);
        const row = await inventoryRow(page, 'equipments', data.numero_serie);
        await expect(row).toContainText(data.nombre);
        await expect(row).toContainText('Disponible');
    });
    await test.step('Impedir duplicar la serie y conservar el único registro original', async () => {
        const before = fixture('inventory-equipment-state', data.numero_serie);
        await page.getByRole('link', { name: /Registrar equipo/ }).click();
        await fillEquipment(page, { ...data, nombre: data.nombre + ' duplicado' });
        await page.getByRole('button', { name: 'Registrar equipo', exact: true }).click();
        await expect(page.locator('.error-message').filter({ hasText: 'Este número de serie ya está registrado.' })).toBeVisible();
        expect(fixture('inventory-equipment-state', data.numero_serie)).toEqual(before);
        await evidence(page, info, 'serie-duplicada-rechazada', before);
    });
});

test('CP-EQU-02 — Actualizar condición, inhabilitar y excluir de nuevas solicitudes', async ({ page }, info) => {
    test.setTimeout(120_000);
    const equipment = fixture('inventory-equipment');
    const control = fixture('inventory-equipment');
    const order = fixture('inventory-order');
    await test.step('Buscar y actualizar condición de Nuevo a Regular', async () => {
        const row = await inventoryRow(page, 'equipments', equipment.numero_serie);
        await row.getByRole('link', { name: 'Editar', exact: true }).click();
        await page.locator('#condicion').selectOption('REGULAR');
        await page.getByRole('button', { name: 'Actualizar equipo' }).click();
        await expect(page).toHaveURL(new RegExp(`/equipments/${equipment.equipo_id}$`));
        await page.reload();
        expect(fixture('inventory-equipment-state', equipment.numero_serie).record.condicion).toBe('REGULAR');
        await expect(page.locator('dl').first()).toContainText('Regular');
        await evidence(page, info, 'equipo-condicion-regular');
    });
    await test.step('Inhabilitar con confirmación y conservar su información', async () => {
        page.once('dialog', dialog => dialog.accept());
        await page.getByRole('button', { name: 'Inhabilitar', exact: true }).click();
        const state = fixture('inventory-equipment-state', equipment.numero_serie);
        expect(state.count).toBe(1);
        expect(state.record).toMatchObject({ equipo_id: equipment.equipo_id, codigo: equipment.codigo, condicion: 'REGULAR', estado: 'INACTIVO', numero_serie: equipment.numero_serie });
        await expect(page.locator('dl').first()).toContainText('Inactivo');
        await evidence(page, info, 'equipo-inhabilitado', state);
    });
    await test.step('Excluir del selector y rechazar una solicitud con identificador manipulado', async () => {
        await page.goto(`/orders?search=${encodeURIComponent(order.codigo)}`);
        const card = page.locator(`#pedido-${order.pedido_id}`);
        await card.locator('summary').filter({ hasText: /Solicitar.*Almacén/ }).click();
        const select = card.locator('select[name="equipo_id"]');
        await expect(select).toBeVisible();
        await expect(select.locator(`option[value="${equipment.equipo_id}"]`)).toHaveCount(0);
        await expect(select.locator(`option[value="${control.equipo_id}"]`)).toHaveCount(1);
        const before = fixture('inventory-order-state', String(order.pedido_id));
        const token = await card.locator('.order-resource-request form [name="_token"]').inputValue();
        const response = await page.request.post(`/orders/${order.pedido_id}/resources`, {
            headers: { Accept: 'application/json' }, form: { _token: token, equipo_id: String(equipment.equipo_id) },
        });
        expect(response.status()).toBe(422);
        expect((await response.json()).errors).toHaveProperty('equipo_id');
        expect(fixture('inventory-order-state', String(order.pedido_id))).toEqual(before);
        await evidence(page, info, 'equipo-inactivo-excluido', { before, rejection: await response.json() });
        info.annotations.push({ type: 'alcance', description: 'Pedido y visita coordinada preparados por fixture; no certifica RF-PED ni ejecución de instalaciones.' });
    });
});
