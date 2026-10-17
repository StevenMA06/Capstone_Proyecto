import { test, expect, fixture, evidence } from './helpers';
import { findPayment, pdfText } from './commercial-helpers';

test('CP-PAG-01 — Registrar, validar y obtener comprobante del pago', async ({ page }, info) => {
    test.setTimeout(120_000);
    const data = fixture('commercial-payment-data');
    let id: string;
    let payment: any;
    await test.step('Rechazar periodo, vencimiento y monto inválidos sin insertar pagos', async () => {
        const before = fixture('commercial-payment-count', String(data.contract.contrato_id));
        await page.goto('/payments/create');
        await page.locator('#contrato_id').selectOption(String(data.contract.contrato_id));
        await page.locator('#periodo_servicio').fill('2026-99');
        await page.locator('#fecha_vencimiento').fill('');
        await page.locator('#monto').fill('0');
        await page.getByRole('button', { name: 'Registrar pago', exact: true }).click();
        await expect(page.locator('#periodo_servicio')).toHaveAttribute('aria-invalid', 'true');
        await expect(page.locator('#fecha_vencimiento')).toHaveAttribute('aria-invalid', 'true');
        await expect(page.locator('#monto')).toHaveAttribute('aria-invalid', 'true');
        expect(fixture('commercial-payment-count', String(data.contract.contrato_id))).toEqual(before);
        await evidence(page, info, 'registro-pago-invalido');
    });
    await test.step('Registrar pago pendiente y buscarlo por contrato', async () => {
        await page.locator('#periodo_servicio').fill(data.period);
        await page.locator('#fecha_vencimiento').fill(data.today);
        await page.locator('#monto').fill('79.90');
        await page.getByRole('button', { name: 'Registrar pago', exact: true }).click();
        await expect(page).toHaveURL(/\/payments\/\d+$/);
        id = new URL(page.url()).pathname.split('/').pop()!;
        payment = fixture('commercial-payment-state', id).payment;
        expect(payment).toMatchObject({ estado: 'PENDIENTE', periodo_servicio: data.period, monto: '79.90', contrato_id: data.contract.contrato_id });
        expect(payment.fecha_vencimiento.slice(0, 10)).toBe(data.today);
        await evidence(page, info, 'pago-pendiente', payment);
        const row = await findPayment(page, data.contract.codigo, payment.codigo, 'PENDIENTE');
        await row.getByRole('link', { name: /Validar pago/ }).click();
    });
    await test.step('Rechazar confirmación sin método conservando el estado', async () => {
        await expect(page.locator('#fecha_pago')).toHaveValue(data.today);
        await page.getByRole('button', { name: /Confirmar pago$/ }).click();
        await expect(page.locator('#metodo_pago')).toHaveAttribute('aria-invalid', 'true');
        expect(fixture('commercial-payment-state', id).payment.estado).toBe('PENDIENTE');
        await evidence(page, info, 'pago-sin-metodo-rechazado');
    });
    await test.step('Confirmar el pago y comprobar comprobante PDF y datos persistidos', async () => {
        await page.locator('#metodo_pago').selectOption('TRANSFERENCIA');
        await page.locator('#referencia_externa').fill(`QA-OP-${id}`);
        await page.locator('#observaciones').fill('Confirmación ficticia para prueba funcional.');
        await page.getByRole('button', { name: /Confirmar pago$/ }).click();
        await expect(page).toHaveURL(/\/payments(?:\/\d+)?$/);
        // Se conserva la comprobación literal del PPF: no ocultar diferencias de main.
        expect.soft(new URL(page.url()).pathname, 'PPF paso 15: debe regresar al listado de Pagos').toBe('/payments');
        const state = fixture('commercial-payment-state', id);
        expect(state.payment).toMatchObject({ estado: 'REALIZADO', metodo_pago: 'TRANSFERENCIA', referencia_externa: `QA-OP-${id}` });
        expect(state.payment.fecha_pago).toBeTruthy();
        expect.soft(state.receipt_exists, 'PPF: se genera y conserva el comprobante PDF al confirmar').toBe(true);
        const row = await findPayment(page, data.contract.codigo, payment.codigo, 'REALIZADO');
        const link = row.getByRole('link', { name: /comprobante/i });
        const linkCount = await link.count();
        expect.soft(linkCount, 'PPF: el pago confirmado debe ofrecer el enlace Comprobante').toBe(1);
        const response = await page.request.get(linkCount ? (await link.getAttribute('href'))! : `/payments/${id}/receipt`);
        expect.soft(response.ok(), 'PPF: la consulta del comprobante debe responder correctamente').toBe(true);
        const contentType = response.headers()['content-type'] || '';
        expect.soft(contentType, 'PPF: el enlace Comprobante debe entregar un PDF').toContain('application/pdf');
        const pdf = await response.body();
        if (contentType.includes('application/pdf')) {
            expect(pdf.subarray(0, 5).toString()).toBe('%PDF-');
            const text = pdfText(pdf, info);
            expect(text).toContain(payment.codigo);
            expect(text).toContain(data.contract.codigo);
            expect(text).toContain('79.90');
            await info.attach('comprobante-pago-confirmado', { body: pdf, contentType: 'application/pdf' });
        } else {
            await info.attach('respuesta-comprobante-no-disponible', { body: pdf, contentType });
        }
        await evidence(page, info, 'pago-realizado-comprobante', state);
    });
});

test('CP-PAG-02 — Vencimiento automático y validación de pagos vencidos', async ({ page }, info) => {
    test.setTimeout(120_000);
    const data = fixture('commercial-payment-data');
    await test.step('Ejecutar actualización real de vencimientos y comprobar regla temporal', async () => {
        expect(fixture('commercial-overdue', 'dry-run').ok).toBe(true);
        expect(fixture('commercial-payment-state', String(data.boundary.pago_id)).payment.estado).toBe('PENDIENTE');
        const result = fixture('commercial-overdue');
        expect.soft(result.ok, `El comando real de vencimientos debe ejecutarse: ${result.error || result.output || ''}`).toBe(true);
        info.annotations.push({ type: 'alcance', description: 'Pagos VENCIDO preparados como prerrequisito para probar su confirmación incluso si falla la actualización automática. No sustituyen ni acreditan el comando.' });
        const overdue = fixture('commercial-payment-state', String(data.overdue.pago_id));
        const recent = fixture('commercial-payment-state', String(data.recent.pago_id));
        const paid = fixture('commercial-payment-state', String(data.paid.pago_id));
        const boundary = fixture('commercial-payment-state', String(data.boundary.pago_id));
        const threeDays = fixture('commercial-payment-state', String(data.three_days.pago_id));
        expect(boundary.payment.estado).toBe('VENCIDO');
        expect(threeDays.payment.estado).toBe('PENDIENTE');
        expect(fixture('commercial-payment-state', String(data.five_days.pago_id)).payment.estado).toBe('IMPAGO');
        const beforeRepeat = fixture('commercial-payment-state', String(data.boundary.pago_id));
        expect(fixture('commercial-overdue').ok).toBe(true);
        expect(fixture('commercial-payment-state', String(data.boundary.pago_id))).toEqual(beforeRepeat);
        expect(overdue.payment.estado).toBe('VENCIDO');
        expect(paid.payment.estado).toBe('REALIZADO');
        expect.soft(recent.payment.estado, 'PPF paso 3: VENCIDO se asigna al transcurrir cuatro días, no al día siguiente').toBe('PENDIENTE');
        await info.attach('actualizacion-vencimientos', { body: JSON.stringify({ result, overdue, recent, paid }, null, 2), contentType: 'application/json' });
        const row = await findPayment(page, data.contract.codigo, data.overdue.codigo, 'VENCIDO');
        await evidence(page, info, 'pagos-vencidos-filtrados', overdue);
        await row.getByRole('link', { name: /Validar pago/ }).click();
    });
    await test.step('Bloquear método vacío manteniendo el pago vencido', async () => {
        await expect(page.locator('#fecha_pago')).toHaveValue(data.today);
        await page.getByRole('button', { name: /Confirmar pago$/ }).click();
        await expect(page.locator('#metodo_pago')).toHaveAttribute('aria-invalid', 'true');
        expect(fixture('commercial-payment-state', String(data.overdue.pago_id)).payment.estado).toBe('VENCIDO');
        await evidence(page, info, 'vencido-sin-metodo-rechazado');
    });
    await test.step('Confirmar vencido por efectivo sin número de operación', async () => {
        await page.locator('#metodo_pago').selectOption('OTRO');
        await page.locator('#metodo_pago_otro').fill('EFECTIVO');
        await page.getByRole('button', { name: /Confirmar pago$/ }).click();
        await expect(page).toHaveURL(/\/payments$/);
        const state = fixture('commercial-payment-state', String(data.overdue.pago_id));
        expect(state.payment).toMatchObject({ estado: 'REALIZADO', metodo_pago: 'EFECTIVO' });
        expect.soft(state.receipt_exists, 'PPF: pago realizado debe conservar su comprobante').toBe(true);
        await evidence(page, info, 'vencido-confirmado-efectivo', state);
    });
    await test.step('Exigir operación al confirmar una transferencia', async () => {
        const id = String(data.missing_operation.pago_id);
        await page.goto(`/payments/${id}/edit`);
        await page.locator('#metodo_pago').selectOption('TRANSFERENCIA');
        await page.locator('#referencia_externa').fill('');
        await page.getByRole('button', { name: /Confirmar pago$/ }).click();
        await expect(page).toHaveURL(/\/payments\/\d+(?:\/edit)?$/);
        const state = fixture('commercial-payment-state', id);
        expect.soft(state.payment.estado, 'PPF: transferencia sin número de operación debe rechazarse').toBe('VENCIDO');
        expect.soft(await page.locator('#referencia_externa[aria-invalid="true"]').count(), 'PPF: debe identificar el número de operación obligatorio').toBe(1);
        await evidence(page, info, 'transferencia-sin-operacion-resultado-real', state);
        const token = await page.locator('input[name="_token"]').first().inputValue();
        const response = await page.request.patch(`/payments/${id}/complete`, { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token }, form: { fecha_pago: data.today, metodo_pago: 'TRANSFERENCIA', referencia_externa: '' } });
        expect(response.status()).toBe(422);
        expect((await response.json()).errors.referencia_externa).toBeTruthy();
        expect(fixture('commercial-payment-state', id).payment.estado).toBe('VENCIDO');
    });
});
