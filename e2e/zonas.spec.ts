import { test, expect, fixture, evidence } from './helpers';

test('CP-ZON-01 — Registrar zona activa y consultar su cobertura y plan', async ({ page }, info) => {
    test.setTimeout(120_000);
    const { data, plan } = fixture('kz-zone-data');
    await test.step('Validar obligatorios y registrar zona con plan activo', async () => {
        await page.goto('/zones');
        await page.getByRole('link', { name: /Registrar zona/ }).click();
        await page.getByRole('button', { name: 'Registrar zona', exact: true }).click();
        expect(await page.locator('#nombre').evaluate((el: HTMLInputElement) => el.validity.valueMissing)).toBe(true);
        await page.locator('form.zone-form').evaluate((el: HTMLFormElement) => { el.noValidate = true; });
        await page.getByRole('button', { name: 'Registrar zona', exact: true }).click();
        await expect(page.locator('#nombre-error')).toContainText('obligatorio');
        await expect(page.locator('#distrito-error')).toContainText('obligatorio');
        expect(fixture('kz-zone-state', data.nombre).count).toBe(0);
        await evidence(page, info, 'zona-obligatorios-rechazados');
        for (const key of ['nombre', 'distrito', 'provincia', 'departamento', 'descripcion']) await page.locator(`#${key}`).fill(data[key]);
        await page.locator(`#plan-${plan.plan_id}`).check();
        await page.getByRole('button', { name: 'Registrar zona', exact: true }).click();
        await expect(page).toHaveURL(/\/zones\/\d+$/);
        await page.reload();
        const state = fixture('kz-zone-state', data.nombre);
        expect(state.count).toBe(1);
        expect(state.zone).toMatchObject({ nombre: data.nombre, distrito: data.distrito, estado: 'ACTIVA' });
        expect(state.zone.codigo).toMatch(/^ZON-/);
        expect(state.plans).toHaveLength(1);
        expect(state.plans[0]).toMatchObject({ plan_id: plan.plan_id, estado: 'ACTIVO' });
        await expect(page.locator('.zone-plans-card')).toContainText(plan.nombre);
        await evidence(page, info, 'zona-registrada', state);
    });
    await test.step('Volver al listado y buscar la zona activa', async () => {
        await page.getByRole('link', { name: 'Volver al listado de zonas' }).click();
        await page.locator('#search').fill(data.nombre);
        await page.getByRole('button', { name: 'Aplicar filtros' }).click();
        const row = page.locator('tbody tr').filter({ hasText: data.nombre });
        await expect(row).toHaveCount(1);
        await expect(row).toContainText('Activa');
        await evidence(page, info, 'zona-en-listado');
    });
    await test.step('Verificar cobertura disponible con el plan asociado', async () => {
        await page.locator('#coverage').fill(data.nombre);
        await page.getByRole('button', { name: 'Verificar', exact: true }).click();
        await expect(page.locator('.coverage-result')).toContainText('Cobertura disponible');
        await expect(page.locator('.coverage-result')).toContainText(plan.nombre);
        await evidence(page, info, 'cobertura-disponible');
    });
});

test('CP-ZON-02 — Inhabilitar zona conservando contratos y excluir nuevos registros', async ({ page }, info) => {
    test.setTimeout(120_000);
    const before = fixture('kz-zone-history');
    const control = fixture('kz-zone');
    const zone = before.zone;
    await test.step('Buscar y abrir ficha de una zona activa con historial', async () => {
        expect(before.contracts).toHaveLength(1);
        expect(before.orders).toHaveLength(1);
        await page.goto('/zones');
        await page.locator('#search').fill(zone.nombre);
        await page.getByRole('button', { name: 'Aplicar filtros' }).click();
        await page.locator('tbody tr').filter({ hasText: zone.nombre }).getByRole('link', { name: 'Ver detalle' }).click();
        await expect(page.locator('dl.account-detail')).toContainText('Activa');
    });
    await test.step('Inhabilitar y comprobar conservación del historial', async () => {
        await page.getByRole('button', { name: 'Inhabilitar', exact: true }).click();
        await page.reload();
        await expect(page.locator('dl.account-detail')).toContainText('Inactiva');
        const after = fixture('kz-zone-state', zone.nombre);
        expect(after.count).toBe(1);
        expect(after.zone).toMatchObject({ zona_id: zone.zona_id, codigo: zone.codigo, nombre: zone.nombre, distrito: zone.distrito, estado: 'INACTIVA' });
        expect(after.contracts).toEqual(before.contracts);
        expect(after.orders).toEqual(before.orders);
        expect(after.plans).toEqual(before.plans);
        await evidence(page, info, 'zona-inactiva-historial-conservado', after);
        await page.goto(`/contracts/${before.contracts[0].contrato_id}`);
        await expect(page.locator('dl.contracts-detail')).toContainText(zone.nombre);
        await evidence(page, info, 'contrato-historico-conservado');
    });
    await test.step('Excluir de cobertura y contratos nuevos, incluso con URL manipulada', async () => {
        await page.goto('/zones');
        await page.locator('#coverage').fill(zone.nombre);
        await page.getByRole('button', { name: 'Verificar', exact: true }).click();
        await expect(page.locator('.coverage-result')).toContainText('Sin cobertura disponible');
        await evidence(page, info, 'zona-inactiva-sin-cobertura');
        await page.goto('/contracts/create');
        await expect(page.locator(`#zona_id option[value="${zone.zona_id}"]`)).toHaveCount(0);
        await expect(page.locator(`#zona_id option[value="${control.zone.zona_id}"]`)).toHaveCount(1);
        const body = { ...fixture('contract-data'), zona_id: String(zone.zona_id), plan_id: String(before.plan.plan_id) };
        const count = fixture('contract-count');
        const token = await page.locator('form.contracts-form [name="_token"]').inputValue();
        const response = await page.request.post('/contracts', { headers: { Accept: 'application/json' }, form: { ...body, _token: token } });
        expect(response.status()).toBe(422);
        expect((await response.json()).errors).toHaveProperty('zona_id');
        expect(fixture('contract-count')).toEqual(count);
        await evidence(page, info, 'zona-inactiva-excluida-selector', { rejection: await response.json() });
        info.annotations.push({ type: 'alcance', description: 'Contrato y pedido históricos preparados por CLI; no certifica RF-CON ni RF-PED en este caso.' });
    });
});
