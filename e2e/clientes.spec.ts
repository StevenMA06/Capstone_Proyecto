import { test, expect, fixture, fillClient, evidence } from './helpers';
import { dniMode } from '../scripts/e2e/environment.mjs';

test('CP-CLI-01 — Registro con consulta DNI y rechazo de documento duplicado', async ({ page }, info) => {
    const data = fixture('client-data');
    info.annotations.push({ type: 'DNI', description: dniMode });
    if (dniMode === 'real') {
        test.skip(!process.env.E2E_APIPERU_API_KEY || !/^[0-9]{8}$/.test(process.env.E2E_DNI || ''), 'Falta clave de API Perú o DNI de prueba autorizado.');
        data.numero_documento = process.env.E2E_DNI;
    } else {
        await page.route('**/clients/dni-lookup', route => route.fulfill({
            status: 200, contentType: 'application/json',
            body: JSON.stringify({ data: { dni: data.numero_documento, nombres: 'Ana Lucía', apellidos: 'Torres Vega', direccion: '' } }),
        }));
    }
    await test.step('Registrar cliente y comprobar persistencia', async () => {
        expect(fixture('client-state', data.numero_documento).count).toBe(0);
        await page.goto('/clients');
        await page.getByRole('link', { name: /Registrar cliente/ }).click();
        await page.locator('#tipo_documento').selectOption('DNI');
        await page.locator('#numero_documento').fill(data.numero_documento);
        await page.getByRole('button', { name: 'Consultar DNI', exact: true }).click();
        await expect(page.locator('#nombres_razon_social')).not.toHaveValue('');
        await expect(page.locator('#apellidos')).not.toHaveValue('');
        const names = await page.locator('#nombres_razon_social').inputValue();
        const surnames = await page.locator('#apellidos').inputValue();
        const { nombres_razon_social, apellidos, ...contact } = data;
        await fillClient(page, contact);
        await page.getByRole('button', { name: 'Registrar cliente', exact: true }).click();
        await expect(page).toHaveURL(/\/clients\/\d+$/);
        await expect(page.locator('[data-toast]')).toContainText('registrado correctamente');
        await page.reload();
        await expect(page.getByRole('heading', { name: new RegExp(`Cliente #.*${names}`) })).toBeVisible();
        const state = fixture('client-state', data.numero_documento);
        expect(state.count).toBe(1);
        expect(state.record).toMatchObject({ nombres_razon_social: names, apellidos: surnames, correo: data.correo, telefono: data.telefono, estado: 'PENDIENTE' });
        await evidence(page, info, 'cliente-registrado', state);
    });
    await test.step('Intentar el mismo documento y comprobar que no se duplica', async () => {
        const before = fixture('client-state', data.numero_documento);
        await page.goto('/clients/create');
        await fillClient(page, data);
        await page.getByRole('button', { name: 'Registrar cliente', exact: true }).click();
        await expect(page.locator('#numero_documento-error')).toContainText('ya está registrado');
        expect(fixture('client-state', data.numero_documento)).toEqual(before);
        await evidence(page, info, 'documento-duplicado-rechazado');
    });
});

test('CP-CLI-02 — Actualizar cliente y rechazar IP inválida sin cambios parciales', async ({ page }, info) => {
    const client = fixture('client');
    await test.step('Buscar, editar y comprobar correo y teléfono guardados', async () => {
        await page.goto('/clients');
        await page.getByLabel('Nombre o documento').fill(client.numero_documento);
        await page.getByRole('button', { name: 'Aplicar filtros' }).click();
        const row = page.locator('tbody tr').filter({ hasText: client.numero_documento });
        await expect(row).toHaveCount(1);
        await row.getByRole('link', { name: /^Editar / }).click();
        await page.locator('#correo').fill(`actualizado.${client.numero_documento}@example.test`);
        await page.locator('#telefono').fill('900111223');
        await page.getByRole('button', { name: 'Guardar cambios' }).click();
        await expect(page).toHaveURL(new RegExp(`/clients/${client.cliente_id}$`));
        await page.reload();
        await expect(page.locator('dl.account-detail').first()).toContainText(`actualizado.${client.numero_documento}@example.test`);
        expect(fixture('client-state', client.numero_documento).record.telefono).toBe('900111223');
        await evidence(page, info, 'cliente-actualizado', fixture('client-state', client.numero_documento));
    });
    await test.step('Rechazar IP inválida y conservar íntegramente el registro', async () => {
        const before = fixture('client-state', client.numero_documento);
        await page.getByRole('link', { name: 'Editar cliente', exact: true }).click();
        await page.locator('#ip_router').fill('999.999.1.1');
        await page.locator('#telefono').fill('900999999');
        await page.getByRole('button', { name: 'Guardar cambios' }).click();
        await expect(page.locator('#ip_router-error')).toContainText('IP válida');
        expect(fixture('client-state', client.numero_documento)).toEqual(before);
        await evidence(page, info, 'ip-invalida-rechazada');
    });
});
