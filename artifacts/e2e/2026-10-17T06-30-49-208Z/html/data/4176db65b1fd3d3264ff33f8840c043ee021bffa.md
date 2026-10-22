# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: pagos.spec.ts >> CP-PAG-01 — Registrar, validar y obtener comprobante del pago
- Location: e2e\pagos.spec.ts:4:1

# Error details

```
Error: Command failed: python scripts/e2e/test-pdf.py --text C:\xampp\htdocs\Capstone\Capstone_Proyecto\artifacts\e2e\2026-10-17T06-30-49-208Z\evidencias\pagos-CP-PAG-01-—-Registra-5ed89-btener-comprobante-del-pago-chromium-repeat4\comprobante-verificado.pdf
Traceback (most recent call last):
  File "C:\xampp\htdocs\Capstone\Capstone_Proyecto\scripts\e2e\test-pdf.py", line 6, in <module>
    from pypdf import PdfReader, PdfWriter
ModuleNotFoundError: No module named 'pypdf'

```

# Page snapshot

```yaml
- generic [active] [ref=f13e1]:
  - link "Ir al contenido" [ref=f13e2] [cursor=pointer]:
    - /url: "#module-content"
  - banner [ref=f13e3]:
    - link "Logo de Lunnys Perú Lunnys Perú PLATAFORMA DE GESTIÓN" [ref=f13e4] [cursor=pointer]:
      - /url: http://127.0.0.1:8011/dashboard
      - img "Logo de Lunnys Perú" [ref=f13e5]
      - generic [ref=f13e6]:
        - text: Lunnys
        - strong [ref=f13e7]: Perú
        - generic [ref=f13e8]: PLATAFORMA DE GESTIÓN
    - generic [ref=f13e9]:
      - generic "Cuenta qa_e2e_gerencia · Gerencia" [ref=f13e10]:
        - generic [ref=f13e11]: Q
        - generic [ref=f13e12]:
          - strong [ref=f13e13]: qa_e2e_gerencia
          - generic [ref=f13e14]: Gerencia
      - button "Cerrar sesión" [ref=f13e16] [cursor=pointer]
  - generic [ref=f13e17]:
    - complementary "Menú de módulos" [ref=f13e18]:
      - group [ref=f13e19]:
        - text: 
        - navigation "Navegación principal" [ref=f13e21]:
          - group [ref=f13e22]:
            - generic "Paneles" [ref=f13e23] [cursor=pointer]
          - group [ref=f13e31]:
            - generic "Comercial" [ref=f13e32] [cursor=pointer]
            - generic [ref=f13e42]:
              - link "Clientes" [ref=f13e43] [cursor=pointer]:
                - /url: http://127.0.0.1:8011/clients
              - link "Planes de servicio" [ref=f13e50] [cursor=pointer]:
                - /url: http://127.0.0.1:8011/plans
              - link "Contratos" [ref=f13e55] [cursor=pointer]:
                - /url: http://127.0.0.1:8011/contracts
              - link "Solicitudes de cancelación" [ref=f13e60] [cursor=pointer]:
                - /url: http://127.0.0.1:8011/contracts/cancellation-requests
              - link "Pagos" [ref=f13e65] [cursor=pointer]:
                - /url: http://127.0.0.1:8011/payments
              - link "Zonas de cobertura" [ref=f13e70] [cursor=pointer]:
                - /url: http://127.0.0.1:8011/zones
              - link "Coordinación de visitas" [ref=f13e75] [cursor=pointer]:
                - /url: http://127.0.0.1:8011/dashboard/call-center/visitas
          - group [ref=f13e79]:
            - generic "Operaciones" [ref=f13e80] [cursor=pointer]
          - group [ref=f13e87]:
            - generic "Inventario" [ref=f13e88] [cursor=pointer]
          - group [ref=f13e97]:
            - generic "Administración" [ref=f13e98] [cursor=pointer]
          - group [ref=f13e108]:
            - generic "Información" [ref=f13e109] [cursor=pointer]
    - main [ref=f13e116]:
      - generic [ref=f13e117]:
        - navigation "Ruta de navegación" [ref=f13e118]:
          - link "Inicio" [ref=f13e119] [cursor=pointer]:
            - /url: http://127.0.0.1:8011/dashboard
          - generic [ref=f13e120]: /
          - generic [ref=f13e121]: Gestión de pagos
        - generic [ref=f13e122]:
          - text: ADMINISTRACIÓN FINANCIERA
          - heading "Gestión de pagos" [level=1] [ref=f13e123]
          - paragraph [ref=f13e124]: Registra, consulta y administra los pagos de tus contratos en un solo lugar.
        - generic [ref=f13e125]:
          - generic [ref=f13e126]:
            - heading "Estadísticas de Pagos" [level=2] [ref=f13e127]
            - generic [ref=f13e128]:
              - generic [ref=f13e129]: "Mes:"
              - combobox "Mes:" [ref=f13e130]:
                - option "Todos los meses" [selected]
                - option "enero de 2026"
                - option "febrero de 2026"
                - option "marzo de 2026"
                - option "abril de 2026"
                - option "mayo de 2026"
                - option "junio de 2026"
                - option "julio de 2026"
                - option "agosto de 2026"
                - option "septiembre de 2026"
                - option "octubre de 2026"
                - option "noviembre de 2026"
                - option "diciembre de 2026"
                - option "enero de 2027"
                - option "febrero de 2027"
                - option "marzo de 2027"
                - option "abril de 2027"
                - option "mayo de 2027"
                - option "junio de 2027"
                - option "julio de 2027"
                - option "agosto de 2027"
                - option "septiembre de 2027"
                - option "octubre de 2027"
                - option "noviembre de 2027"
                - option "diciembre de 2027"
          - generic [ref=f13e131]:
            - link " Pendientes 336 Por cobrar 57% del total" [ref=f13e132] [cursor=pointer]:
              - /url: http://127.0.0.1:8011/payments?contract=CON-20261017-PAT0DLME&status=PENDIENTE
              - generic [ref=f13e133]: 
              - generic [ref=f13e135]:
                - heading "Pendientes" [level=3] [ref=f13e136]
                - paragraph [ref=f13e137]: "336"
                - paragraph [ref=f13e138]: Por cobrar
                - paragraph [ref=f13e141]: 57% del total
            - link " Vencidos 168 Requieren atención 28% del total" [ref=f13e142] [cursor=pointer]:
              - /url: http://127.0.0.1:8011/payments?contract=CON-20261017-PAT0DLME&status=VENCIDO
              - generic [ref=f13e143]: 
              - generic [ref=f13e145]:
                - heading "Vencidos" [level=3] [ref=f13e146]
                - paragraph [ref=f13e147]: "168"
                - paragraph [ref=f13e148]: Requieren atención
                - paragraph [ref=f13e151]: 28% del total
            - link " Anulados 0 Error administrativo 0% del total" [ref=f13e152] [cursor=pointer]:
              - /url: http://127.0.0.1:8011/payments?contract=CON-20261017-PAT0DLME&status=ANULADO
              - generic [ref=f13e153]: 
              - generic [ref=f13e155]:
                - heading "Anulados" [level=3] [ref=f13e156]
                - paragraph [ref=f13e157]: "0"
                - paragraph [ref=f13e158]: Error administrativo
                - paragraph [ref=f13e160]: 0% del total
            - link " Realizados 89 Pagados 15% del total 🔍 Filtrado" [ref=f13e161] [cursor=pointer]:
              - /url: http://127.0.0.1:8011/payments?contract=CON-20261017-PAT0DLME&status=REALIZADO
              - generic [ref=f13e162]: 
              - generic [ref=f13e164]:
                - heading "Realizados" [level=3] [ref=f13e165]
                - paragraph [ref=f13e166]: "89"
                - paragraph [ref=f13e167]: Pagados
                - paragraph [ref=f13e170]: 15% del total
              - generic [ref=f13e171]: 🔍 Filtrado
          - paragraph [ref=f13e172]:
            - text: Total de pagos en
            - strong [ref=f13e173]: todos los meses
            - text: ":"
            - strong [ref=f13e174]: "593"
        - generic [ref=f13e175]:
          - complementary [ref=f13e176]:
            - heading "Filtros y buscador" [level=2] [ref=f13e177]
            - paragraph [ref=f13e178]: Encuentra los pagos que necesitas.
            - generic [ref=f13e179]:
              - generic [ref=f13e180]:
                - generic [ref=f13e181]: Cliente o documento
                - textbox "Cliente o documento" [ref=f13e182]:
                  - /placeholder: Buscar por documento…
                - generic [ref=f13e183]: Número de documento del cliente.
              - generic [ref=f13e184]:
                - generic [ref=f13e185]: Código de contrato
                - textbox "Código de contrato" [ref=f13e186]:
                  - /placeholder: "Ej: CON-2026-001"
                  - text: CON-20261017-PAT0DLME
                - generic [ref=f13e187]: Buscar pagos de un contrato específico.
              - generic [ref=f13e188]:
                - generic [ref=f13e189]: Desde (vencimiento)
                - textbox "Desde (vencimiento)" [ref=f13e190]
              - generic [ref=f13e191]:
                - generic [ref=f13e192]: Hasta (vencimiento)
                - textbox "Hasta (vencimiento)" [ref=f13e193]
              - generic [ref=f13e194]:
                - button "Aplicar filtros" [ref=f13e195] [cursor=pointer]
                - link "Limpiar filtros" [ref=f13e196] [cursor=pointer]:
                  - /url: http://127.0.0.1:8011/payments
          - region [ref=f13e197]:
            - generic [ref=f13e199]:
              - heading "Listado de pagos" [level=2] [ref=f13e200]
              - paragraph [ref=f13e201]: 2 pagos encontrados
            - table [ref=f13e203]:
              - caption [ref=f13e204]: Pagos registrados
              - rowgroup [ref=f13e205]:
                - row [ref=f13e206]:
                  - columnheader "Código" [ref=f13e207]
                  - columnheader "Cliente" [ref=f13e208]
                  - columnheader "Contrato" [ref=f13e209]
                  - columnheader "Monto" [ref=f13e210]
                  - columnheader "F. Vencimiento" [ref=f13e211]
                  - columnheader "Estado" [ref=f13e212]
                  - columnheader "Acciones" [ref=f13e213]
              - rowgroup [ref=f13e214]:
                - row [ref=f13e215]:
                  - cell "QA-PAG-84591fee55b1f8fd 2026-10" [ref=f13e216]:
                    - strong [ref=f13e217]: QA-PAG-84591fee55b1f8fd
                    - generic [ref=f13e218]: 2026-10
                  - cell "Ana Lucía Torres Vega DNI 82076168" [ref=f13e219]:
                    - text: Ana Lucía Torres Vega
                    - generic [ref=f13e220]: DNI 82076168
                  - cell "CON-20261017-PAT0DLME QA Fibra a07fff7bdb74" [ref=f13e221]:
                    - strong [ref=f13e222]: CON-20261017-PAT0DLME
                    - generic [ref=f13e223]: QA Fibra a07fff7bdb74
                  - cell [ref=f13e224]:
                    - strong [ref=f13e225]: S/ 79.90
                  - cell "13/10/2026" [ref=f13e226]
                  - cell "Realizado 17/10/2026" [ref=f13e227]:
                    - generic [ref=f13e228]: Realizado
                    - generic [ref=f13e229]: 17/10/2026
                  - cell [ref=f13e230]:
                    - link "Ver detalle del pago QA-PAG-84591fee55b1f8fd" [ref=f13e232] [cursor=pointer]:
                      - /url: http://127.0.0.1:8011/payments/588
                      - text: Ver detalle
                - row [ref=f13e233]:
                  - cell "PAG-20261017-90214A 2026-10" [ref=f13e234]:
                    - strong [ref=f13e235]: PAG-20261017-90214A
                    - generic [ref=f13e236]: 2026-10
                  - cell "Ana Lucía Torres Vega DNI 82076168" [ref=f13e237]:
                    - text: Ana Lucía Torres Vega
                    - generic [ref=f13e238]: DNI 82076168
                  - cell "CON-20261017-PAT0DLME QA Fibra a07fff7bdb74" [ref=f13e239]:
                    - strong [ref=f13e240]: CON-20261017-PAT0DLME
                    - generic [ref=f13e241]: QA Fibra a07fff7bdb74
                  - cell [ref=f13e242]:
                    - strong [ref=f13e243]: S/ 79.90
                  - cell "17/10/2026" [ref=f13e244]
                  - cell "Realizado 17/10/2026" [ref=f13e245]:
                    - generic [ref=f13e246]: Realizado
                    - generic [ref=f13e247]: 17/10/2026
                  - cell [ref=f13e248]:
                    - generic [ref=f13e249]:
                      - link "Ver detalle del pago PAG-20261017-90214A" [ref=f13e250] [cursor=pointer]:
                        - /url: http://127.0.0.1:8011/payments/593
                        - text: Ver detalle
                      - link "Ver comprobante del pago PAG-20261017-90214A" [ref=f13e251] [cursor=pointer]:
                        - /url: http://127.0.0.1:8011/payments/593/receipt
                        - generic [ref=f13e252]: 
                        - text: Comprobante
            - navigation "Paginación de pagos" [ref=f13e253]:
              - generic [ref=f13e254]: 2 registros · Página 1 de 1
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
  9  |     await page.goto('/login');
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
> 33 |     return execFileSync(process.env.E2E_SIGNATURE_PYTHON || 'python', ['scripts/e2e/test-pdf.py', '--text', file], {
     |            ^ Error: Command failed: python scripts/e2e/test-pdf.py --text C:\xampp\htdocs\Capstone\Capstone_Proyecto\artifacts\e2e\2026-10-17T06-30-49-208Z\evidencias\pagos-CP-PAG-01-—-Registra-5ed89-btener-comprobante-del-pago-chromium-repeat4\comprobante-verificado.pdf
  34 |         cwd: root, encoding: 'utf8', env: { ...process.env, PYTHONIOENCODING: 'utf-8' },
  35 |     });
  36 | }
  37 | 
  38 | export async function registerContract(page: Page, info?: TestInfo) {
  39 |     const data = fixture('commercial-data', info ? String(info.repeatEachIndex + 1) : undefined);
  40 |     if (info) await info.attach('datos-del-ciclo', { body: JSON.stringify(data, null, 2), contentType: 'application/json' });
  41 |     await page.goto('/contracts');
  42 |     await page.getByRole('link', { name: /Registrar contrato/ }).click();
  43 |     for (const key of ['cliente_id', 'plan_id', 'zona_id', 'responsable_id']) {
  44 |         await page.locator(`#${key}`).selectOption(String(data[key]));
  45 |     }
  46 |     await page.locator('#fecha_inicio').fill(data.fecha_inicio);
  47 |     await page.locator('#duracion_meses').fill(String(data.duracion_meses));
  48 |     await page.getByRole('button', { name: 'Guardar contrato' }).click();
  49 |     await expect(page).toHaveURL(/\/contracts\/\d+$/);
  50 |     const id = Number(new URL(page.url()).pathname.split('/').pop());
  51 |     const state = fixture('commercial-contract-state', String(id));
  52 |     expect(state.contract).toMatchObject({ cliente_id: data.cliente_id, plan_id: data.plan_id, duracion_meses: data.duracion_meses });
  53 |     expect(String(state.contract.fecha_inicio).slice(0, 10)).toBe(data.fecha_inicio);
  54 |     return state;
  55 | }
  56 | 
  57 | export async function findPayment(page: Page, contractCode: string, paymentCode: string, status: string) {
  58 |     await page.goto('/payments');
  59 |     await page.locator('#contract').fill(contractCode);
  60 |     await page.getByRole('button', { name: 'Aplicar filtros' }).click();
  61 |     await page.locator(`a.stat-card[href*="status=${status}"]`).click();
  62 |     const row = page.locator('tbody tr').filter({ hasText: paymentCode });
  63 |     await expect(row).toHaveCount(1);
  64 |     return row;
  65 | }
  66 | 
```