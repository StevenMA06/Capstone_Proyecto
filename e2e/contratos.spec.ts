import { test, expect, fixture, evidence } from './helpers';
import { loginAs, registerContract, signedPdf } from './commercial-helpers';

test('CP-CON-01 — Registrar y activar únicamente con firma e instalación completada', async ({ page }, info) => {
    test.setTimeout(120_000);
    let state: any;
    await test.step('Registrar contrato pendiente con código único', async () => {
        state = await registerContract(page);
        expect(state.contract.estado).toBe('PENDIENTE');
        expect(state.contract.codigo).toMatch(/^CON-/);
        expect(state.code_count).toBe(1);
        expect(state.order.contrato_id).toBe(state.contract.contrato_id);
        await evidence(page, info, 'contrato-pendiente', state);
    });
    const id = state.contract.contrato_id;
    const signed = await signedPdf(page, info, id);
    await test.step('Validar firma real sin activar un pedido incompleto', async () => {
        await page.getByRole('link', { name: 'Actualizar contrato' }).click();
        await page.locator('#documento_pdf').setInputFiles(signed);
        await page.getByRole('button', { name: 'Guardar cambios' }).click();
        await expect(page).toHaveURL(new RegExp(`/contracts/${id}$`));
        state = fixture('commercial-contract-state', String(id));
        expect(state.contract).toMatchObject({ estado: 'PENDIENTE', firma_revision: 'VERIFICADA' });
        expect(state.signed_pdf_exists).toBe(true);
        expect(state.order.estado).not.toBe('COMPLETADO');
        await evidence(page, info, 'firma-valida-instalacion-pendiente', state);
    });
    await test.step('Preparar instalación completada y comprobar activación por interfaz', async () => {
        fixture('commercial-complete-order', String(id));
        info.annotations.push({ type: 'alcance', description: 'Pedido completado preparado por fixture. Se prueba contratos; no certifica ejecución RF-INS ni sincronización real con Drive.' });
        await page.getByRole('link', { name: 'Actualizar contrato' }).click();
        await page.locator('#documento_pdf').setInputFiles(signed);
        await page.getByRole('button', { name: 'Guardar cambios' }).click();
        await expect(page).toHaveURL(new RegExp(`/contracts/${id}$`));
        await page.reload();
        state = fixture('commercial-contract-state', String(id));
        expect(state.contract).toMatchObject({ estado: 'ACTIVO', firma_revision: 'VERIFICADA' });
        expect(state.contract.firma_validada_en).toBeTruthy();
        expect(state.order.estado).toBe('COMPLETADO');
        await expect(page.locator('dl.contracts-detail')).toContainText('Activo');
        await evidence(page, info, 'contrato-activo', state);
    });
});

test('CP-CON-02 — Cancelación documentada con revisión independiente', async ({ page }, info) => {
    const reviewer = fixture('commercial-reviewer');
    const initial = fixture('commercial-contract');
    const id = initial.contract.contrato_id;
    let state: any;
    await test.step('Solicitar cancelación conservando motivo y documento de respaldo', async () => {
        await page.goto(`/contracts/${id}`);
        await page.locator('#cancelacion-contrato summary').click();
        const category = page.locator('#categoria');
        await category.selectOption({ index: 1 });
        await page.locator('#explicacion').fill('El cliente ficticio solicita cancelar antes de iniciar la instalación.');
        const pdf = await page.request.get(`/contracts/${id}/draft`);
        expect(pdf.ok()).toBe(true);
        await page.locator('#evidencia').setInputFiles({ name: 'respaldo-cancelacion-ficticio.pdf', mimeType: 'application/pdf', buffer: await pdf.body() });
        await page.getByRole('button', { name: 'Enviar solicitud a revisión' }).click();
        await expect(page.locator('.cancellation-history')).toContainText('Pendiente');
        state = fixture('commercial-contract-state', String(id));
        expect(state.contract.estado).toBe('PENDIENTE');
        expect(state.cancellation.estado).toBe('PENDIENTE');
        expect(state.evidence_exists).toBe(true);
        await evidence(page, info, 'solicitud-pendiente', state);
    });
    await test.step('Bloquear la aprobación propia en interfaz y servidor', async () => {
        await expect(page.getByRole('button', { name: 'Aprobar y cancelar' })).toHaveCount(0);
        await expect(page.locator('.cancellation-review-lock')).toContainText('otra persona');
        const token = await page.locator('input[name="_token"]').first().inputValue();
        const response = await page.request.post(`/contracts/${id}/cancellation-requests/${state.cancellation.solicitud_cancelacion_id}/approve`, {
            headers: { Referer: page.url() }, form: { _token: token }, maxRedirects: 0,
        });
        expect(response.status()).toBe(302);
        await page.goto(response.headers().location);
        await expect(page.locator('.error-summary')).toContainText('otra cuenta debe aprobar');
        expect(fixture('commercial-contract-state', String(id)).cancellation.estado).toBe('PENDIENTE');
        await info.attach('autoaprobacion-rechazada', { body: JSON.stringify({ status: response.status(), location: response.headers().location }), contentType: 'application/json' });
        await evidence(page, info, 'autoaprobacion-bloqueada');
    });
    await test.step('Aprobar con otro usuario y comprobar trazabilidad completa', async () => {
        await loginAs(page, reviewer.nombre_usuario);
        await page.goto('/contracts/cancellation-requests');
        await page.locator('#cancellation-search').fill(initial.contract.codigo);
        await page.getByRole('button', { name: 'Buscar', exact: true }).click();
        const row = page.locator('.cancellation-queue-card').filter({ hasText: initial.contract.codigo });
        await expect(row).toHaveCount(1);
        await row.getByRole('link', { name: /Revisar y decidir/ }).click();
        await page.getByRole('button', { name: 'Aprobar y cancelar' }).click();
        await expect(page.locator('dl.contracts-detail')).toContainText('Cancelado');
        state = fixture('commercial-contract-state', String(id));
        expect(state.contract.estado).toBe('CANCELADO');
        expect(state.order.estado).toBe('CANCELADO');
        expect(state.client_state).not.toBe('PENDIENTE');
        expect(state.cancellation.estado).toBe('APROBADA');
        expect(state.cancellation.resuelto_por).toBe(reviewer.usuario_id);
        expect(state.cancellation.solicitado_por).not.toBe(reviewer.usuario_id);
        for (const key of ['solicitado_en', 'resuelto_en', 'resuelto_roles', 'categoria', 'explicacion', 'evidencia_sha256']) expect(state.cancellation[key]).toBeTruthy();
        expect(state.evidence_exists).toBe(true);
        await evidence(page, info, 'cancelacion-aprobada', state);
    });
});
