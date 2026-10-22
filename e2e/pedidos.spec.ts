import { test, expect, fixture, evidence } from './helpers';
import { loginAs } from './commercial-helpers';
import { orderCard, rejected } from './installation-helpers';

async function coordinateVisit(page: any, order: any) {
    await page.goto(`/dashboard/call-center/visitas?search=${order.code}`);
    const details = page.locator(`#coordinar-visita-${order.id}`);
    await details.locator('summary').click();
    await details.locator('[name="franja_horaria"]').selectOption('08:00 a 12:00');
    await details.locator('[name="hora_inicio"]').selectOption('09:00');
    await details.locator('[name="canal_coordinacion"]').selectOption('LLAMADA');
    await details.getByRole('button', { name: 'Guardar fecha confirmada' }).click();
}

async function controlledMeasurement(form: any, elapsed: number, active: number, discarded = 0) {
    await form.evaluate((element: HTMLFormElement, values) => {
        const assign = (name: string, value: string) => {
            const field = element.querySelector<HTMLInputElement>(`[name="${name}"]`);
            if (!field) throw new Error(`No existe ${name}`);
            field.value = value;
        };
        const now = Date.now();
        assign('indicador_inicio', new Date(now - values.elapsed * 1000).toISOString());
        assign('indicador_ultima_interaccion', new Date(now).toISOString());
        assign('indicador_activo_segundos', String(values.active));
        assign('indicador_pausa_descartada_segundos', String(values.discarded));
    }, { elapsed, active, discarded });
}

async function csrfPatch(page: any, url: string, form: Record<string, string> = {}) {
    const token = await page.locator('input[name="_token"]').first().inputValue();
    // Las acciones HTML usan POST con _method=PATCH y devuelven una redirección.
    // Seguirla con el método PATCH acaba en 405, aunque la acción se haya completado.
    const response = await page.request.post(url, { form: { _token: token, _method: 'PATCH', ...form }, maxRedirects: 0 });
    expect(response.status(), `${url} respondió: ${await response.text()}`).toBe(302);
}

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
        await expect(card.locator('form[data-auto-indicator] [name="indicador_token"]')).not.toHaveValue('');
        await card.getByRole('button', { name: 'Enviar solicitud completa a Almacén' }).click();
        const state = fixture('pi-state', String(order.id));
        expect(state.groups).toHaveLength(1);
        expect(state.requests).toHaveLength(2);
        expect(state.requests.map((r: any) => r.tipo).sort()).toEqual(['EQUIPO', 'MATERIAL']);
        expect(state.order.tecnico_id).toBeNull();
        const measurements = fixture('indicator-order-state', String(order.id)).rows;
        expect(measurements.filter((row: any) => row.indicador === 'pedido_recursos')).toHaveLength(1);
        await evidence(page, info, 'pedido-solicitud-completa', { state, measurements });
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
        const measurements = fixture('indicator-order-state', String(data.first.id)).rows;
        expect(measurements.filter((row: any) => row.indicador === 'pedido_asignacion')).toHaveLength(1);
        await evidence(page, info, 'pedido-tecnico-asignado', { state, measurements });
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

test('IA-PED-01 — mide solicitud, preparación y asignación del mismo pedido', async ({ page }, info) => {
    test.setTimeout(240_000);
    const data = fixture('pi-coordination');
    const order = data.first;

    await loginAs(page, data.office);
    await coordinateVisit(page, order);

    await loginAs(page, data.requester);
    let card = await orderCard(page, order);
    const resourceDetails = card.locator(`#solicitar-recursos-${order.id}`);
    await resourceDetails.locator('summary').click();
    const resourceForm = resourceDetails.locator('form[data-auto-indicator="pedido_recursos"]');
    await resourceForm.locator('[name="equipo_id"]').selectOption(String(order.equipment.equipo_id));
    await resourceForm.locator('[name="materials[0][material_id]"]').selectOption(String(order.material.material_id));
    await resourceForm.locator('[name="materials[0][cantidad_asignada]"]').fill('10');
    await controlledMeasurement(resourceForm, 100, 100);
    await resourceForm.getByRole('button', { name: 'Enviar solicitud completa a Almacén' }).click();

    let state = fixture('pi-state', String(order.id));
    const firstGroup = state.groups[0];
    const firstRequest = state.requests[0];
    expect(state.requests.every((row: any) => row.estado === 'PENDIENTE_GERENCIA')).toBe(true);

    await loginAs(page, data.reviewer);
    await page.goto(`/orders?search=${order.code}`);
    await csrfPatch(page, `/orders/${order.id}/resources/${firstRequest.solicitud_id}/approve`);
    state = fixture('pi-state', String(order.id));
    expect(state.requests.every((row: any) => row.estado === 'AUTORIZADA')).toBe(true);
    expect(fixture('indicator-order-state', String(order.id)).rows).toHaveLength(1);

    await loginAs(page, data.requester);
    await page.goto(`/warehouse/requests?pedido=${order.id}`);
    await csrfPatch(page, `/warehouse/requests/${firstRequest.solicitud_id}/fulfill`);
    state = fixture('pi-state', String(order.id));
    expect(state.requests.every((row: any) => row.estado === 'ENTREGADA')).toBe(true);
    expect(fixture('indicator-order-state', String(order.id)).rows).toHaveLength(1);

    await loginAs(page, data.requester);
    card = await orderCard(page, order);
    await card.locator('.order-card-more > summary').click();
    const assignment = card.locator('.order-assignment-action');
    await assignment.locator('summary').click();
    const assignmentForm = assignment.locator('form[data-auto-indicator="pedido_asignacion"]');
    await assignmentForm.locator('[name="tecnico_id"]').selectOption(String(data.tech_id));
    await controlledMeasurement(assignmentForm, 60, 60);
    await assignmentForm.getByRole('button', { name: 'Confirmar asignación y avisar' }).click();

    state = fixture('pi-state', String(order.id));
    const measurements = fixture('indicator-order-state', String(order.id)).rows;
    expect(state.order.estado).toBe('PROGRAMADO');
    expect(measurements).toHaveLength(2);
    expect(measurements.map((row: any) => row.indicador)).toEqual(['pedido_recursos', 'pedido_asignacion']);
    expect(measurements.map((row: any) => Number(row.duracion_activa_segundos))).toEqual([100, 60]);
    expect(measurements[0]).toMatchObject({ pedido_id: order.id, intento: firstGroup.solicitud_grupo_id, referencia_id: firstGroup.solicitud_grupo_id });
    expect(measurements[1]).toMatchObject({ pedido_id: order.id, intento: null, referencia_id: order.id });
    expect(fixture('indicator-order-state', String(data.second.id)).rows).toHaveLength(0);
    await evidence(page, info, 'indicador-pedido-exitoso', { order, state, measurements });
});

test('IA-PED-02 — conserva el tramo rechazado y promedia un solo pedido programado', async ({ page }, info) => {
    test.setTimeout(300_000);
    const data = fixture('pi-coordination');
    const order = data.second;

    await loginAs(page, data.office);
    await coordinateVisit(page, order);
    await loginAs(page, data.requester);
    let card = await orderCard(page, order);
    let details = card.locator(`#solicitar-recursos-${order.id}`);
    await details.locator('summary').click();
    let form = details.locator('form[data-auto-indicator="pedido_recursos"]');
    await form.locator('[name="equipo_id"]').selectOption(String(order.equipment.equipo_id));
    await form.locator('[name="materials[0][material_id]"]').selectOption(String(order.material.material_id));
    await form.locator('[name="materials[0][cantidad_asignada]"]').fill('10');
    await controlledMeasurement(form, 100, 100);
    await form.getByRole('button', { name: 'Enviar solicitud completa a Almacén' }).click();

    let state = fixture('pi-state', String(order.id));
    const rejectedGroup = state.groups[0];
    const rejectedRequest = state.requests[0];
    expect(state.requests.every((row: any) => row.estado === 'PENDIENTE_GERENCIA')).toBe(true);
    await loginAs(page, data.reviewer);
    await page.goto(`/orders?search=${order.code}`);
    await csrfPatch(page, `/orders/${order.id}/resources/${rejectedRequest.solicitud_id}/reject`, { decision_motivo_codigo: 'CANTIDAD_NO_JUSTIFICADA' });
    state = fixture('pi-state', String(order.id));
    expect(state.requests.every((row: any) => row.estado === 'RECHAZADA')).toBe(true);
    expect(state.requests.every((row: any) => row.decision_motivo_codigo === 'CANTIDAD_NO_JUSTIFICADA')).toBe(true);

    await loginAs(page, data.requester);
    card = await orderCard(page, order);
    details = card.locator(`#solicitar-recursos-${order.id}`);
    await details.locator('summary').click();
    form = details.locator('form[data-auto-indicator="pedido_recursos"]');
    await form.locator('[name="equipo_id"]').selectOption(String(order.equipment.equipo_id));
    await form.locator('[name="materials[0][material_id]"]').selectOption(String(order.material.material_id));
    await form.locator('[name="materials[0][cantidad_asignada]"]').fill('10');
    await controlledMeasurement(form, 80, 80);
    await form.getByRole('button', { name: 'Enviar solicitud completa a Almacén' }).click();

    state = fixture('pi-state', String(order.id));
    const approvedGroup = state.groups.find((row: any) => row.solicitud_grupo_id !== rejectedGroup.solicitud_grupo_id);
    const approvedRequest = state.requests.find((row: any) => row.solicitud_grupo_id === approvedGroup.solicitud_grupo_id);
    expect(state.requests.filter((row: any) => row.solicitud_grupo_id === approvedGroup.solicitud_grupo_id).every((row: any) => row.estado === 'PENDIENTE_GERENCIA')).toBe(true);
    await loginAs(page, data.reviewer);
    await page.goto(`/orders?search=${order.code}`);
    await csrfPatch(page, `/orders/${order.id}/resources/${approvedRequest.solicitud_id}/approve`);
    expect(fixture('indicator-order-state', String(order.id)).rows).toHaveLength(2);
    await loginAs(page, data.requester);
    await page.goto(`/warehouse/requests?pedido=${order.id}`);
    await csrfPatch(page, `/warehouse/requests/${approvedRequest.solicitud_id}/fulfill`);
    expect(fixture('indicator-order-state', String(order.id)).rows).toHaveLength(2);

    await loginAs(page, data.requester);
    card = await orderCard(page, order);
    await card.locator('.order-card-more > summary').click();
    const assignment = card.locator('.order-assignment-action');
    await assignment.locator('summary').click();
    const assignmentForm = assignment.locator('form[data-auto-indicator="pedido_asignacion"]');
    await assignmentForm.locator('[name="tecnico_id"]').selectOption(String(data.tech_id));
    await controlledMeasurement(assignmentForm, 60, 60);
    await assignmentForm.getByRole('button', { name: 'Confirmar asignación y avisar' }).click();

    state = fixture('pi-state', String(order.id));
    const measurements = fixture('indicator-order-state', String(order.id)).rows;
    expect(state.order.estado).toBe('PROGRAMADO');
    expect(measurements.filter((row: any) => row.indicador === 'pedido_recursos')).toHaveLength(2);
    expect(measurements.map((row: any) => Number(row.duracion_activa_segundos))).toEqual([100, 80, 60]);
    expect(measurements.map((row: any) => row.intento)).toEqual([rejectedGroup.solicitud_grupo_id, approvedGroup.solicitud_grupo_id, null]);
    expect(measurements.reduce((total: number, row: any) => total + Number(row.duracion_activa_segundos), 0)).toBe(240);
    expect(fixture('indicator-order-state', String(data.first.id)).rows).toHaveLength(0);
    const day = new Date().toLocaleDateString('en-CA', { timeZone: 'America/Lima' });
    const summary = fixture('indicator-order-summary', day);
    expect(summary.panel.count).toBe(summary.raw.count);
    expect(summary.panel.average).toBe(summary.raw.average);
    await evidence(page, info, 'indicador-pedido-rechazado-reenviado', { order, state, measurements, summary });
});
