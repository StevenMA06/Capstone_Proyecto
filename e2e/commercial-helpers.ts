import { execFileSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';
import { type Page, type TestInfo } from '@playwright/test';
import { root } from '../scripts/e2e/environment.mjs';
import { expect, fixture } from './helpers';

export async function loginAs(page: Page, username: string) {
    await page.context().clearCookies();
    await page.goto('/login');
    await page.getByLabel('Correo o usuario').fill(username);
    await page.getByLabel('Contraseña', { exact: true }).fill('PruebasLunnys2026');
    await page.getByRole('button', { name: 'Iniciar sesión' }).click();
    await expect(page).not.toHaveURL(/\/login|\/two-factor/);
}

export async function signedPdf(page: Page, info: TestInfo, id: number) {
    const response = await page.request.get(`/contracts/${id}/draft`);
    expect(response.ok()).toBe(true);
    const bytes = await response.body();
    expect(bytes.subarray(0, 5).toString()).toBe('%PDF-');
    const draft = info.outputPath('contrato-sin-firma.pdf');
    const signed = info.outputPath('contrato-trazo-ficticio.pdf');
    writeFileSync(draft, bytes);
    execFileSync(process.env.E2E_SIGNATURE_PYTHON || 'python', ['scripts/e2e/test-pdf.py', draft, signed], { cwd: root });
    await info.attach('contrato-original', { path: draft, contentType: 'application/pdf' });
    await info.attach('contrato-con-trazo-sintetico', { path: signed, contentType: 'application/pdf' });
    return signed;
}

export function pdfText(bytes: Buffer, info: TestInfo): string {
    const file = info.outputPath('comprobante-verificado.pdf');
    writeFileSync(file, bytes);
    return execFileSync(process.env.E2E_SIGNATURE_PYTHON || 'python', ['scripts/e2e/test-pdf.py', '--text', file], {
        cwd: root, encoding: 'utf8', env: { ...process.env, PYTHONIOENCODING: 'utf-8' },
    });
}

export async function registerContract(page: Page, info?: TestInfo) {
    const data = fixture('commercial-data', info ? String(info.repeatEachIndex + 1) : undefined);
    if (info) await info.attach('datos-del-ciclo', { body: JSON.stringify(data, null, 2), contentType: 'application/json' });
    await page.goto('/contracts');
    await page.getByRole('link', { name: /Registrar contrato/ }).click();
    for (const key of ['cliente_id', 'plan_id', 'zona_id', 'responsable_id']) {
        await page.locator(`#${key}`).selectOption(String(data[key]));
    }
    await page.locator('#fecha_inicio').fill(data.fecha_inicio);
    await page.locator('#duracion_meses').fill(String(data.duracion_meses));
    await page.getByRole('button', { name: 'Guardar contrato' }).click();
    await expect(page).toHaveURL(/\/contracts\/\d+$/);
    const id = Number(new URL(page.url()).pathname.split('/').pop());
    const state = fixture('commercial-contract-state', String(id));
    expect(state.contract).toMatchObject({ cliente_id: data.cliente_id, plan_id: data.plan_id, duracion_meses: data.duracion_meses });
    expect(String(state.contract.fecha_inicio).slice(0, 10)).toBe(data.fecha_inicio);
    return state;
}

export async function findPayment(page: Page, contractCode: string, paymentCode: string, status: string) {
    await page.goto('/payments');
    await page.locator('#contract').fill(contractCode);
    await page.getByRole('button', { name: 'Aplicar filtros' }).click();
    await page.locator(`a.stat-card[href*="status=${status}"]`).click();
    const row = page.locator('tbody tr').filter({ hasText: paymentCode });
    await expect(row).toHaveCount(1);
    return row;
}
