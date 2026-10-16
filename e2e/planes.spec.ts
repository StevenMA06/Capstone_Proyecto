import { test, expect, fixture, fillPlan, evidence } from './helpers';

test('CP-PLA-01 — Registrar plan y rechazar precio cero en interfaz y servidor', async ({ page }, info) => {
    const data = fixture('plan-data');
    await test.step('Registrar un plan válido y comprobar número automático', async () => {
        await page.goto('/plans');
        await page.getByRole('link', { name: /Registrar nuevo plan/ }).click();
        await expect(page.locator('[name="numero_plan"]')).toHaveCount(0);
        await fillPlan(page, data);
        await page.getByRole('button', { name: 'Registrar plan', exact: true }).click();
        await expect(page).toHaveURL(/\/plans\/\d+$/);
        await page.reload();
        const state = fixture('plan-state', data.nombre);
        expect(state.count).toBe(1);
        expect(state.record.numero_plan).toMatch(/^PLAN-\d{6,}$/);
        expect(state.record.estado).toBe('ACTIVO');
        await expect(page.locator('dl')).toContainText(data.nombre);
        await expect(page.locator('dl')).toContainText('S/ 79.90');
        await evidence(page, info, 'plan-registrado', state);
    });
    await test.step('Comprobar validación del navegador y del servidor', async () => {
        const invalid = { ...fixture('plan-data'), precio_mensual: '0' };
        await page.goto('/plans/create');
        await fillPlan(page, invalid);
        await page.getByRole('button', { name: 'Registrar plan', exact: true }).click();
        expect(await page.locator('#precio_mensual').evaluate((input: HTMLInputElement) => input.validity.rangeUnderflow)).toBe(true);
        expect(fixture('plan-state', invalid.nombre).count).toBe(0);
        await evidence(page, info, 'precio-cero-bloqueado-navegador');
        // Desactivar validación nativa permite verificar también el backend.
        await page.locator('form.account-form').evaluate((form: HTMLFormElement) => { form.noValidate = true; });
        await page.getByRole('button', { name: 'Registrar plan', exact: true }).click();
        await expect(page.locator('#precio_mensual-error')).toContainText('mayor que cero');
        expect(fixture('plan-state', invalid.nombre).count).toBe(0);
        await evidence(page, info, 'precio-cero-rechazado-servidor');
    });
});

test('CP-PLA-02 — Actualizar e inhabilitar plan y excluirlo de contratos nuevos', async ({ page }, info) => {
    const plan = fixture('plan');
    const control = fixture('plan');
    await test.step('Buscar por número y actualizar velocidad', async () => {
        await page.goto('/plans');
        await page.getByLabel('Número de plan', { exact: true }).fill(plan.numero_plan);
        await page.getByRole('button', { name: 'Aplicar filtros' }).click();
        const row = page.locator('tbody tr').filter({ hasText: plan.numero_plan });
        await expect(row).toHaveCount(1);
        await row.getByRole('link', { name: 'Editar', exact: true }).click();
        await page.locator('#velocidad_mbps').fill('150');
        await page.getByRole('button', { name: 'Guardar cambios' }).click();
        await expect(page).toHaveURL(new RegExp(`/plans/${plan.plan_id}$`));
        await page.reload();
        await expect(page.locator('dl')).toContainText('150 Mbps');
        expect(fixture('plan-state', plan.nombre).record.velocidad_mbps).toBe(150);
        await evidence(page, info, 'plan-actualizado');
    });
    await test.step('Inhabilitar y conservar ficha e identidad', async () => {
        await page.getByRole('button', { name: 'Inhabilitar', exact: true }).click();
        await expect(page.locator('dl')).toContainText('Inactivo');
        const state = fixture('plan-state', plan.nombre);
        expect(state.count).toBe(1);
        expect(state.record).toMatchObject({ plan_id: plan.plan_id, numero_plan: plan.numero_plan, estado: 'INACTIVO', velocidad_mbps: 150 });
        await evidence(page, info, 'plan-inhabilitado', state);
    });
    await test.step('Excluir del selector y rechazar identificador inactivo manipulado', async () => {
        await page.goto('/contracts');
        await page.getByRole('link', { name: /Registrar contrato/ }).click();
        await expect(page.locator(`#plan_id option[value="${plan.plan_id}"]`)).toHaveCount(0);
        await expect(page.locator(`#plan_id option[value="${control.plan_id}"]`)).toHaveCount(1);
        await evidence(page, info, 'plan-inactivo-no-disponible');
        const before = fixture('contract-count');
        const body = fixture('contract-data');
        const token = await page.locator('form.contracts-form [name="_token"]').inputValue();
        const response = await page.request.post('/contracts', {
            headers: { Accept: 'application/json' },
            form: { ...body, plan_id: String(plan.plan_id), _token: token },
        });
        expect(response.status()).toBe(422);
        expect((await response.json()).errors).toHaveProperty('plan_id');
        expect(fixture('contract-count')).toEqual(before);
        await info.attach('plan-inactivo-respuesta-servidor', { body: JSON.stringify(await response.json()), contentType: 'application/json' });
    });
});
