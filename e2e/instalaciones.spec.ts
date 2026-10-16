import { createHash } from 'node:crypto';
import { test, expect, fixture, evidence } from './helpers';
import { loginAs, signedPdf } from './commercial-helpers';
import { rejected } from './installation-helpers';

test('CP-INS-01 — Confirmar asignación e iniciar únicamente una instalación autorizada', async ({ page }, info) => {
    test.setTimeout(180_000);
    const data = fixture('pi-start');
    await loginAs(page, data.tech);
    await test.step('Consultar asignaciones y rechazar inicio sin confirmación', async () => {
        await page.goto('/technician');
        await expect(page.getByText(data.first.code, { exact: true })).toBeVisible();
        await expect(page.getByText(data.second.code, { exact: true })).toBeVisible();
        const before = fixture('pi-state', String(data.second.id));
        const rejection = await rejected(page, `/technician/installations/${data.second.id}/start`, {});
        const after = fixture('pi-state', String(data.second.id));
        expect(after.order).toEqual(before.order);
        expect(after.execution).toBeNull();
        await evidence(page, info, 'instalacion-sin-confirmar', rejection);
    });
    await test.step('Confirmar recepción desde Mis instalaciones y abrir ficha', async () => {
        const card = page.locator('article').filter({ hasText: data.first.code });
        await card.getByRole('button', { name: 'Confirmar que recibí la asignación' }).click();
        expect(fixture('pi-state', String(data.first.id)).order.confirmado_por_tecnico_en).toBeTruthy();
        await page.goto(`/technician/installations/${data.first.id}`);
        await page.locator('[data-installation-tab="recursos"]').click();
        await expect(page.locator('#instalacion-recursos')).toContainText(data.first.equipment.codigo);
        await expect(page.locator('#instalacion-recursos')).toContainText(data.first.material.nombre);
        await evidence(page, info, 'instalacion-recursos-asignados');
    });
    await test.step('Iniciar y comprobar estado y hora de Perú persistidos', async () => {
        const before = fixture('pi-state', String(data.first.id));
        await page.getByRole('button', { name: 'Iniciar instalación', exact: true }).click();
        const after = fixture('pi-state', String(data.first.id));
        expect(after.order.estado).toBe('EN_PROCESO');
        expect(after.execution.estado).toBe('EN_PROCESO');
        expect(after.execution.inicio_en >= before.now && after.execution.inicio_en <= after.now).toBe(true);
        expect(after.timezone).toBe('America/Lima');
        expect(after.execution.fin_en).toBeNull();
        await evidence(page, info, 'instalacion-iniciada-hora-peru', after);
    });
    await test.step('Otro técnico no puede consultar ni iniciar la ficha ajena', async () => {
        await loginAs(page, data.other);
        await page.goto('/technician');
        await expect(page.getByText(data.first.code, { exact: true })).toHaveCount(0);
        expect((await page.request.get(`/technician/installations/${data.first.id}`)).status()).toBe(404);
        const token = await page.locator('input[name="_token"]') .first().inputValue();
        expect((await page.request.post(`/technician/installations/${data.first.id}/start`, { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token! } })).status()).toBe(404);
        expect(fixture('pi-state', String(data.first.id)).order.estado).toBe('EN_PROCESO');
        await evidence(page, info, 'instalacion-acceso-ajeno-bloqueado');
    });
});

test('CP-INS-02 — Validar fotografía, guardar evidencia y verificar condiciones del cierre', async ({ page }, info) => {
    test.setTimeout(240_000);
    const real = process.env.E2E_DRIVE_MODE === 'real';
    if (!real) info.annotations.push({ type: 'integration-pending', description: 'Google Drive real y cierre con contrato firmado pendientes. Se acredita guardado local, rechazo de archivo y bloqueo de cierre.' });
    const data = fixture('pi-evidence');
    const order = data.first;
    await loginAs(page, data.tech);
    await page.goto(`/technician/installations/${order.id}`);
    await page.locator('[data-installation-tab="evidencias"]').click();
    await test.step('Rechazar archivo de texto sin crear evidencias', async () => {
        const token = await page.locator('input[name="_token"]') .first().inputValue();
        const response = await page.request.post(`/technician/installations/${order.id}/evidence`, {
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token! },
            multipart: { evidence: { name: 'invalido.txt', mimeType: 'text/plain', buffer: Buffer.from('No es una fotografía') } },
        });
        expect(response.status()).toBe(422);
        const rejection = await response.json();
        expect(rejection.errors.evidence).toBeTruthy();
        expect(fixture('pi-state', String(order.id)).photos).toHaveLength(0);
        await evidence(page, info, 'instalacion-archivo-no-permitido', rejection);
    });
    await test.step('Previsualizar y guardar una fotografía ficticia', async () => {
        await page.getByText('Agregar foto de la instalación', { exact: true }).click();
        // PNG válido, ficticio y sin datos personales. No se simula ninguna respuesta de Drive.
        const photo = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j4l8AAAAASUVORK5CYII=', 'base64');
        await page.locator('#file-photo').setInputFiles({ name: 'evidencia-qa.png', mimeType: 'image/png', buffer: photo });
        const preview = page.getByAltText('Vista previa del archivo seleccionado');
        await expect(preview).toBeVisible();
        await expect.poll(() => preview.evaluate((img: HTMLImageElement) => img.naturalWidth)).toBeGreaterThan(0);
        await page.locator('#description-photo').fill('Fotografía ficticia para CP-INS-02');
        await evidence(page, info, 'instalacion-vista-previa-foto');
        await page.getByRole('button', { name: 'Guardar fotografía', exact: true }).click();
        const state = fixture('pi-state', String(order.id));
        expect(state.photos).toHaveLength(1);
        expect(state.photos[0].local_exists).toBe(true);
        expect(state.photos[0].local_sha256).toBe(createHash('sha256').update(photo).digest('hex'));
        expect(state.photos[0].pedido_id).toBe(order.id);
        expect(state.order.estado).toBe('EN_PROCESO');
        expect(state.execution.fin_en).toBeNull();
        const drivePhoto = state.drive.find((d: any) => d.tipo === 'FOTO');
        expect(drivePhoto.estado).toBe(real ? 'CONFIRMADO' : 'PENDIENTE');
        if (real) {
            expect(drivePhoto.drive_file_id).toBeTruthy();
            expect(drivePhoto.source_sha256).toBe(state.photos[0].local_sha256);
        }
        await evidence(page, info, 'instalacion-foto-guardada-cierre-pendiente', state);
    });
    if (!real) return;
    await test.step('Validar contrato firmado y confirmar cierre automático con Drive real', async () => {
        await loginAs(page, 'qa_e2e_gerencia');
        const signed = await signedPdf(page, info, order.contract_id);
        await page.goto(`/contracts/${order.contract_id}/edit`);
        await page.locator('#documento_pdf').setInputFiles(signed);
        await page.getByRole('button', { name: 'Guardar cambios' }).click();
        await expect.poll(() => fixture('pi-state', String(order.id)).order.estado, { timeout: 60_000 }).toBe('COMPLETADO');
        const state = fixture('pi-state', String(order.id));
        expect(state.execution.estado).toBe('COMPLETADA');
        expect(state.execution.fin_en).toBeTruthy();
        expect(state.contract.firma_revision).toBe('VERIFICADA');
        expect(state.drive.some((d: any) => d.tipo === 'CONTRATO_VALIDADO' && d.estado === 'CONFIRMADO' && d.drive_file_id)).toBe(true);
        await loginAs(page, data.tech);
        await page.goto('/technician');
        await expect(page.locator('article').filter({ hasText: order.code })).toContainText('Completado');
        await evidence(page, info, 'instalacion-completada-drive-real', state);
    });
});
