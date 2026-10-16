import { test, expect, fixture, evidence } from './helpers';
import { loginAs } from './commercial-helpers';
import { orderCard, rejected } from './installation-helpers';

test('CP-PED-01 — Coordinar visita, solicitar recursos y rechazar cantidades sin stock', async ({ page }, info) => {
    test.setTimeout(240_000);
    const data = fixture('pi-coordination');
    const order = data.first;
    await test.step('Call Center confirma la fecha propuesta por el contrato', async () => {
        await loginAs(page, data.office);
        await page.goto(`/dashboard/call-center/visitas?search=${order.code}`);
        const details = page.locator(`#coordinar-visita-${order.id}`);
        await details.locator('summary').click();
        await expect(details.locator('[name="fecha_visita"]')).toHaveValue(order.day);
        await details.locator('[name="franja_horaria"]').selectOption('08:00 a 12:00');
        await details.locator('[name="hora_inicio"]').selectOption('09:00');
        await details.locator('[name="canal_coordinacion"]').selectOption('LLAMADA');
        await details.getByRole('button', { name: 'Guardar fecha confirmada' }).click();
        const state = fixture('pi-state', String(order.id));
        expect(state.order.fecha_programada).toBe(`${order.day} 09:00:00`);
        expect(state.order.canal_coordinacion).toBe('LLAMADA');
        expect(state.order.coordinado_en).toBeTruthy();
        await evidence(page, info, 'pedido-visita-confirmada', state);
    });
    await loginAs(page, 'qa_e2e_gerencia');
    let card = await orderCard(page, order);
    await test.step('Enviar solicitud completa de equipo y material desde el formulario', async () => {
        await card.locator('#solicitar-recursos-'+order.id+' summary').click();
        await card.locator('select[name="equipo_id"]').selectOption(String(order.equipment.equipo_id));
        await card.locator('[name="materials[0][material_id]"]').selectOption(String(order.material.material_id));
        await card.locator('[name="materials[0][cantidad_asignada]"]').fill('10');
        await card.getByRole('button', { name: 'Enviar solicitud completa a Almacén' }).click();
        const state = fixture('pi-state', String(order.id));
        expect(state.groups).toHaveLength(1);
        expect(state.requests).toHaveLength(2);
        expect(state.requests.map((r: any) => r.tipo).sort()).toEqual(['EQUIPO', 'MATERIAL']);
        expect(state.order.tecnico_id).toBeNull();
        await evidence(page, info, 'pedido-solicitud-completa', state);
    });
    await test.step('En otro pedido rechazar solicitud sin stock y mantener bloqueada la asignación', async () => {
        const negative = data.second;
        await loginAs(page, data.office);
        await page.goto(`/dashboard/call-center/visitas?search=${negative.code}`);
        const details = page.locator(`#coordinar-visita-${negative.id}`);
        await details.locator('summary').click();
        await details.locator('[name="franja_horaria"]').selectOption('08:00 a 12:00');
        await details.locator('[name="hora_inicio"]').selectOption('09:00');
        await details.locator('[name="canal_coordinacion"]').selectOption('LLAMADA');
        await details.getByRole('button', { name: 'Guardar fecha confirmada' }).click();
        await loginAs(page, 'qa_e2e_gerencia');
        card = await orderCard(page, negative);
        const before = fixture('pi-state', String(negative.id));
        const token = await page.locator('input[name="_token"]').first().inputValue();
        const response = await page.request.post(`/orders/${negative.id}/resources`, {
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token },
            form: { equipo_id: String(negative.equipment.equipo_id), 'materials[0][material_id]': String(negative.material.material_id), 'materials[0][cantidad_asignada]': '101' },
        });
        const after = fixture('pi-state', String(negative.id));
        await evidence(page, info, 'pedido-stock-insuficiente', { status: response.status(), before, after });
        expect.soft(response.status(), 'Cantidad solicitada 101 supera el stock 100: debe rechazarse').toBe(422);
        expect.soft(after.requests, 'No debe guardar solicitudes sin stock').toEqual(before.requests);
        expect.soft(after.groups, 'El rechazo debe revertir la solicitud completa').toEqual(before.groups);
        expect(after.order.tecnico_id).toBeNull();
        await card.locator('.order-card-more > summary').click();
        await expect(card.locator('.order-next-attention').filter({ hasText: 'Almacén' }).first()).toBeVisible();
    });
});

test('CP-PED-02 — Asignar técnico y bloquear doble rol y cruce de horario', async ({ page }, info) => {
    test.setTimeout(180_000);
    const data = fixture('pi-assignment');
    let card = await orderCard(page, data.first);
    await test.step('Rechazar el mismo técnico como responsable y apoyo', async () => {
        const before = fixture('pi-state', String(data.first.id));
        const rejection = await rejected(page, `/orders/${data.first.id}/schedule`, { tecnico_id: String(data.tech_id), tecnico_secundario_id: String(data.tech_id) }, 'PATCH');
        expect(fixture('pi-state', String(data.first.id)).order).toEqual(before.order);
        await evidence(page, info, 'pedido-tecnico-duplicado', rejection);
    });
    await test.step('Asignar técnico libre mediante la interfaz', async () => {
        await card.locator('.order-card-more > summary').click();
        await card.locator('.order-assignment-action summary').click();
        await card.locator('[name="tecnico_id"]').selectOption(String(data.tech_id));
        await card.getByRole('button', { name: 'Confirmar asignación y avisar' }).click();
        const state = fixture('pi-state', String(data.first.id));
        expect(state.order.tecnico_id).toBe(data.tech_id);
        expect(state.order.estado).toBe('PROGRAMADO');
        expect(state.order.fase_flujo).toBe('ESPERANDO_CONFIRMACION_TECNICO');
        expect(state.order.asignado_en).toBeTruthy();
        await evidence(page, info, 'pedido-tecnico-asignado', state);
    });
    await test.step('Excluir técnico ocupado y rechazar una solicitud forzada', async () => {
        card = await orderCard(page, data.second);
        await card.locator('.order-card-more > summary').click();
        await card.locator('.order-assignment-action summary').click();
        await expect(card.locator(`[name="tecnico_id"] option[value="${data.tech_id}"]`)).toHaveCount(0);
        const before = fixture('pi-state', String(data.second.id));
        const rejection = await rejected(page, `/orders/${data.second.id}/schedule`, { tecnico_id: String(data.tech_id) }, 'PATCH');
        expect(fixture('pi-state', String(data.second.id)).order).toEqual(before.order);
        await evidence(page, info, 'pedido-cruce-horario', rejection);
    });
});
