import { test, expect, fixture, fillClient } from './helpers';
import { fillEquipment, fillMaterial } from './inventory-helpers';
import { registerContract } from './commercial-helpers';

const measurement = (indicator: string, type: string, id: number | string) => fixture('indicator-state', `${indicator}|${type}|${id}`).rows;
const assertOneCompleted = (rows: any[]) => {
    expect(rows).toHaveLength(1);
    expect(rows[0]).toMatchObject({ estado: 'COMPLETADA' });
    expect(Number(rows[0].duracion_activa_segundos)).toBeGreaterThanOrEqual(0);
    expect(rows[0].fin_en).toBeTruthy();
};

test.describe('Indicadores automáticos: formularios y persistencia', () => {
    test('IA-TIEMPO — persiste trabajo continuo, umbral y reanudación con reloj controlado', async () => {
        const rows = fixture('indicator-deterministic').rows;
        expect(rows).toHaveLength(4);
        expect(rows.map((row: any) => Number(row.duracion_activa_segundos))).toEqual([10, 60, 120, 150]);
        expect(rows.map((row: any) => Number(row.pausa_descartada_segundos))).toEqual([0, 0, 30, 30]);
        for (const row of rows) {
            const elapsed = (Date.parse(`${row.fin_en.replace(' ', 'T')}Z`) - Date.parse(`${row.inicio_en.replace(' ', 'T')}Z`)) / 1000;
            expect(elapsed).toBe(Number(row.duracion_activa_segundos) + Number(row.pausa_descartada_segundos));
        }
    });

    test('IA-CLI — inicia al documento, conserva el intento fallido y persiste tras guardar', async ({ page }) => {
        const data = fixture('client-data');
        await page.goto('/clients/create');
        await page.locator('#tipo_documento').selectOption('DNI');
        await page.locator('#numero_documento').fill(data.numero_documento);
        await expect(page.locator('[name="indicador_token"]')).not.toHaveValue('');
        await page.getByRole('button', { name: 'Registrar cliente', exact: true }).click();
        await expect(page.locator('#correo-error')).toBeVisible();
        const tokenAfterFailure = await page.locator('[name="indicador_token"]').inputValue();
        expect(tokenAfterFailure).not.toBe('');
        await fillClient(page, data);
        await page.getByRole('button', { name: 'Registrar cliente', exact: true }).click();
        const client = fixture('client-state', data.numero_documento).record;
        assertOneCompleted(measurement('clientes', 'cliente', client.cliente_id));
    });

    test('IA-INACTIVIDAD — limita cada pausa a 120 segundos y reanuda con interacción', async ({ page }) => {
        const data = fixture('client-data');
        await page.goto('/clients/create');
        await page.locator('#tipo_documento').selectOption('DNI');
        await page.locator('#numero_documento').fill(data.numero_documento);
        const token = await page.locator('[name="indicador_token"]').inputValue();
        await page.evaluate(() => {
            const form = document.querySelector('form[data-auto-indicator]')!;
            const last = form.querySelector<HTMLInputElement>('[name="indicador_ultima_interaccion"]')!;
            const active = form.querySelector<HTMLInputElement>('[name="indicador_activo_segundos"]')!;
            const discarded = form.querySelector<HTMLInputElement>('[name="indicador_pausa_descartada_segundos"]')!;
            last.value = new Date(Date.now() - 300_000).toISOString();
            active.value = '10';
            discarded.value = '0';
            form.querySelector<HTMLInputElement>('#correo')!.dispatchEvent(new Event('input', { bubbles: true }));
        });
        expect(Math.floor(Number(await page.locator('[name="indicador_activo_segundos"]').inputValue()))).toBe(130);
        expect(Math.floor(Number(await page.locator('[name="indicador_pausa_descartada_segundos"]').inputValue()))).toBe(180);
        await page.evaluate(() => {
            const form = document.querySelector('form[data-auto-indicator]')!;
            const last = form.querySelector<HTMLInputElement>('[name="indicador_ultima_interaccion"]')!;
            last.value = new Date(Date.now() - 60_000).toISOString();
            form.querySelector<HTMLInputElement>('#telefono')!.dispatchEvent(new Event('input', { bubbles: true }));
        });
        expect(Math.floor(Number(await page.locator('[name="indicador_activo_segundos"]').inputValue()))).toBe(190);
        expect(Math.floor(Number(await page.locator('[name="indicador_pausa_descartada_segundos"]').inputValue()))).toBe(180);
        await expect(page.locator('[name="indicador_token"]')).toHaveValue(token);
    });

    test('IA-CON — persiste solo cuando Guardar contrato crea el contrato', async ({ page }, info) => {
        const state = await registerContract(page, info);
        assertOneCompleted(measurement('contratos', 'contrato', state.contract.contrato_id));
    });

    test('IA-PAG — inicia con fecha o método y persiste al registrar el pago', async ({ page }) => {
        const data = fixture('commercial-payment-data');
        await page.goto('/payments/create');
        await page.locator('#contrato_id').selectOption(String(data.contract.contrato_id));
        await page.locator('#periodo_servicio').fill(data.period);
        await page.locator('#fecha_vencimiento').fill(data.today);
        await page.locator('#monto').fill('79.90');
        await page.locator('#fecha_pago').fill(data.today);
        await page.locator('#metodo_pago').selectOption('TRANSFERENCIA');
        await expect(page.locator('[name="indicador_token"]')).not.toHaveValue('');
        await page.getByRole('button', { name: 'Registrar pago', exact: true }).click();
        const id = new URL(page.url()).pathname.split('/').pop()!;
        assertOneCompleted(measurement('pagos', 'pago', id));
    });

    test('IA-EQU y IA-MAT — no persisten al error y se completan al registro exitoso', async ({ page }) => {
        const equipment = fixture('inventory-equipment-data');
        await page.goto('/equipments/create');
        await page.locator('#numero_serie').fill(equipment.numero_serie);
        await page.getByRole('button', { name: 'Registrar equipo', exact: true }).click();
        expect(fixture('inventory-equipment-state', equipment.numero_serie).count).toBe(0);
        await fillEquipment(page, equipment);
        await page.getByRole('button', { name: 'Registrar equipo', exact: true }).click();
        const savedEquipment = fixture('inventory-equipment-state', equipment.numero_serie).record;
        assertOneCompleted(measurement('equipos', 'equipo', savedEquipment.equipo_id));

        const material = fixture('inventory-material-data');
        await page.goto('/materials/create');
        await page.locator('#nombre').fill(material.nombre);
        await page.getByRole('button', { name: 'Registrar material', exact: true }).click();
        expect(fixture('inventory-material-state', material.nombre).count).toBe(0);
        await fillMaterial(page, material);
        await page.getByRole('button', { name: 'Registrar material', exact: true }).click();
        const savedMaterial = fixture('inventory-material-state', material.nombre).record;
        assertOneCompleted(measurement('materiales', 'material', savedMaterial.material_id));
    });
});

