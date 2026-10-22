# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: asistente.spec.ts >> CP-IA-01 — Consultas sugeridas, consulta vacía y acceso autorizado
- Location: e2e\asistente.spec.ts:30:1

# Error details

```
Error: page.goto: net::ERR_CONNECTION_RESET at http://127.0.0.1:8011/login
Call log:
  - navigating to "http://127.0.0.1:8011/login", waiting until "load"

```

# Test source

```ts
  1  | import { execFileSync } from 'node:child_process';
  2  | import { writeFileSync } from 'node:fs';
  3  | import { type Page, type TestInfo } from '@playwright/test';
  4  | import { root } from '../scripts/e2e/environment.mjs';
  5  | import { expect, fixture } from './helpers';
  6  | 
  7  | export async function loginAs(page: Page, username: string) {
  8  |     await page.context().clearCookies();
> 9  |     await page.goto('/login');
     |                ^ Error: page.goto: net::ERR_CONNECTION_RESET at http://127.0.0.1:8011/login
  10 |     await page.getByLabel('Correo o usuario').fill(username);
  11 |     await page.getByLabel('Contraseña', { exact: true }).fill('PruebasLunnys2026');
  12 |     await page.getByRole('button', { name: 'Iniciar sesión' }).click();
  13 |     await expect(page).not.toHaveURL(/\/login|\/two-factor/);
  14 | }
  15 | 
  16 | export async function signedPdf(page: Page, info: TestInfo, id: number) {
  17 |     const response = await page.request.get(`/contracts/${id}/draft`);
  18 |     expect(response.ok()).toBe(true);
  19 |     const bytes = await response.body();
  20 |     expect(bytes.subarray(0, 5).toString()).toBe('%PDF-');
  21 |     const draft = info.outputPath('contrato-sin-firma.pdf');
  22 |     const signed = info.outputPath('contrato-trazo-ficticio.pdf');
  23 |     writeFileSync(draft, bytes);
  24 |     execFileSync(process.env.E2E_SIGNATURE_PYTHON || 'python', ['scripts/e2e/test-pdf.py', draft, signed], { cwd: root });
  25 |     await info.attach('contrato-original', { path: draft, contentType: 'application/pdf' });
  26 |     await info.attach('contrato-con-trazo-sintetico', { path: signed, contentType: 'application/pdf' });
  27 |     return signed;
  28 | }
  29 | 
  30 | export function pdfText(bytes: Buffer, info: TestInfo): string {
  31 |     const file = info.outputPath('comprobante-verificado.pdf');
  32 |     writeFileSync(file, bytes);
  33 |     return execFileSync(process.env.E2E_SIGNATURE_PYTHON || 'python', ['scripts/e2e/test-pdf.py', '--text', file], {
  34 |         cwd: root, encoding: 'utf8', env: { ...process.env, PYTHONIOENCODING: 'utf-8' },
  35 |     });
  36 | }
  37 | 
  38 | export async function registerContract(page: Page) {
  39 |     const data = fixture('commercial-data');
  40 |     await page.goto('/contracts');
  41 |     await page.getByRole('link', { name: /Registrar contrato/ }).click();
  42 |     for (const key of ['cliente_id', 'plan_id', 'zona_id', 'responsable_id']) {
  43 |         await page.locator(`#${key}`).selectOption(String(data[key]));
  44 |     }
  45 |     await page.locator('#fecha_inicio').fill(data.fecha_inicio);
  46 |     await page.locator('#duracion_meses').fill(String(data.duracion_meses));
  47 |     await page.getByRole('button', { name: 'Guardar contrato' }).click();
  48 |     await expect(page).toHaveURL(/\/contracts\/\d+$/);
  49 |     const id = Number(new URL(page.url()).pathname.split('/').pop());
  50 |     return fixture('commercial-contract-state', String(id));
  51 | }
  52 | 
  53 | export async function findPayment(page: Page, contractCode: string, paymentCode: string, status: string) {
  54 |     await page.goto('/payments');
  55 |     await page.locator('#contract').fill(contractCode);
  56 |     await page.getByRole('button', { name: 'Aplicar filtros' }).click();
  57 |     await page.locator(`a.stat-card[href*="status=${status}"]`).click();
  58 |     const row = page.locator('tbody tr').filter({ hasText: paymentCode });
  59 |     await expect(row).toHaveCount(1);
  60 |     return row;
  61 | }
  62 | 
```