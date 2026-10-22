# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: clientes.spec.ts >> CP-CLI-01 — Registro con consulta DNI y rechazo de documento duplicado
- Location: e2e\clientes.spec.ts:4:1

# Error details

```
Error: expect(received).toBe(expected) // Object.is equality

Expected: 0
Received: 1
```

# Page snapshot

```yaml
- generic [active] [ref=f1e1]:
  - link "Ir al contenido" [ref=f1e2] [cursor=pointer]:
    - /url: "#module-content"
  - banner [ref=f1e3]:
    - link "Logo de Lunnys Perú Lunnys Perú PLATAFORMA DE GESTIÓN" [ref=f1e4] [cursor=pointer]:
      - /url: http://127.0.0.1:8011/dashboard
      - img "Logo de Lunnys Perú" [ref=f1e5]
      - generic [ref=f1e6]:
        - text: Lunnys
        - strong [ref=f1e7]: Perú
        - generic [ref=f1e8]: PLATAFORMA DE GESTIÓN
    - generic [ref=f1e9]:
      - generic "Cuenta qa_e2e_gerencia · Gerencia" [ref=f1e10]:
        - generic [ref=f1e11]: Q
        - generic [ref=f1e12]:
          - strong [ref=f1e13]: qa_e2e_gerencia
          - generic [ref=f1e14]: Gerencia
      - button "Cerrar sesión" [ref=f1e16] [cursor=pointer]
  - generic [ref=f1e17]:
    - complementary "Menú de módulos" [ref=f1e18]:
      - group [ref=f1e19]:
        - text: 
        - navigation "Navegación principal" [ref=f1e21]:
          - group [ref=f1e22]:
            - generic "Paneles" [ref=f1e23] [cursor=pointer]
            - link "Panel de Gerencia" [ref=f1e32] [cursor=pointer]:
              - /url: http://127.0.0.1:8011/dashboard/gerencia
          - group [ref=f1e37]:
            - generic "Comercial" [ref=f1e38] [cursor=pointer]
          - group [ref=f1e48]:
            - generic "Operaciones" [ref=f1e49] [cursor=pointer]
          - group [ref=f1e56]:
            - generic "Inventario" [ref=f1e57] [cursor=pointer]
          - group [ref=f1e66]:
            - generic "Administración" [ref=f1e67] [cursor=pointer]
          - group [ref=f1e77]:
            - generic "Información" [ref=f1e78] [cursor=pointer]
    - main [ref=f1e85]:
      - generic [ref=f1e86]:
        - generic [ref=f1e87]:
          - generic [ref=f1e88]:
            - text: RESUMEN DE TU OPERACIÓN
            - heading "Panel de Gerencia" [level=1] [ref=f1e89]
            - navigation "Ruta de navegación" [ref=f1e90]:
              - generic [ref=f1e91]: Inicio
              - generic [ref=f1e92]: /
              - generic [ref=f1e93]: Dashboard
          - paragraph [ref=f1e94]:
            - text: Hola,
            - strong [ref=f1e95]: qa_e2e_gerencia
            - text: Bienvenido a Lunnys Perú
        - generic [ref=f1e96]:
          - link [ref=f1e97] [cursor=pointer]:
            - /url: http://127.0.0.1:8011/clients
            - heading "Clientes registrados" [level=2] [ref=f1e98]
            - paragraph [ref=f1e99]: "393"
          - link [ref=f1e105] [cursor=pointer]:
            - /url: http://127.0.0.1:8011/contracts
            - heading "Contratos activos" [level=2] [ref=f1e106]
            - paragraph [ref=f1e107]: "49"
          - link [ref=f1e111] [cursor=pointer]:
            - /url: http://127.0.0.1:8011/payments?status=VENCIDO
            - heading "Pagos vencidos" [level=2] [ref=f1e112]
            - paragraph [ref=f1e113]: "98"
          - link [ref=f1e117] [cursor=pointer]:
            - /url: http://127.0.0.1:8011/installations
            - heading "Instalaciones en proceso" [level=2] [ref=f1e118]
            - paragraph [ref=f1e119]: "0"
          - button "Equipos Haz clic para ver stock" [ref=f1e122] [cursor=pointer]:
            - generic [ref=f1e123]: Equipos
            - generic [ref=f1e128]: Haz clic para ver stock
          - button "Materiales Haz clic para ver stock ⚠ Stock mínimo" [ref=f1e129] [cursor=pointer]:
            - generic [ref=f1e130]: Materiales
            - generic [ref=f1e135]: Haz clic para ver stock
            - generic [ref=f1e136]: ⚠ Stock mínimo
        - region [ref=f1e137]:
          - generic [ref=f1e138]:
            - generic [ref=f1e139]:
              - heading "Resumen de ventas" [level=2] [ref=f1e140]
              - paragraph [ref=f1e141]: Últimos 6 meses · Ventas confirmadas · Mes actual en curso
            - generic [ref=f1e142]:
              - generic [ref=f1e143]: Total del período
              - strong [ref=f1e144]: S/ 0.00
          - paragraph [ref=f1e145]: Aún no hay ventas confirmadas en este período.
          - list "Ventas confirmadas por mes, en soles" [ref=f1e146]:
            - listitem [ref=f1e147]:
              - generic [ref=f1e148]: S/ 0.00
              - generic [ref=f1e150]: may. 2026
            - listitem [ref=f1e151]:
              - generic [ref=f1e152]: S/ 0.00
              - generic [ref=f1e154]: jun. 2026
            - listitem [ref=f1e155]:
              - generic [ref=f1e156]: S/ 0.00
              - generic [ref=f1e158]: jul. 2026
            - listitem [ref=f1e159]:
              - generic [ref=f1e160]: S/ 0.00
              - generic [ref=f1e162]: ago. 2026
            - listitem [ref=f1e163]:
              - generic [ref=f1e164]: S/ 0.00
              - generic [ref=f1e166]: sep. 2026
            - listitem [ref=f1e167]:
              - generic [ref=f1e168]: S/ 0.00
              - generic [ref=f1e170]: oct. 2026
          - paragraph [ref=f1e171]: Indicadores calculados con los registros existentes.
        - region [ref=f1e172]:
          - generic [ref=f1e174]:
            - heading "Planes más vendidos" [level=2] [ref=f1e175]
            - paragraph [ref=f1e176]: Top 5 · Ventas confirmadas en los últimos 6 meses · Cantidad de ventas
          - paragraph [ref=f1e177]: Aún no hay ventas confirmadas de planes en este período.
```

# Test source

```ts
  1  | import { test, expect, fixture, fillClient, evidence } from './helpers';
  2  | import { dniMode } from '../scripts/e2e/environment.mjs';
  3  | 
  4  | test('CP-CLI-01 — Registro con consulta DNI y rechazo de documento duplicado', async ({ page }, info) => {
  5  |     const data = fixture('client-data');
  6  |     info.annotations.push({ type: 'DNI', description: dniMode });
  7  |     if (dniMode === 'real') {
  8  |         test.skip(!process.env.E2E_APIPERU_API_KEY || !/^[0-9]{8}$/.test(process.env.E2E_DNI || ''), 'Falta clave de API Perú o DNI de prueba autorizado.');
  9  |         data.numero_documento = process.env.E2E_DNI;
  10 |     } else {
  11 |         await page.route('**/clients/dni-lookup', route => route.fulfill({
  12 |             status: 200, contentType: 'application/json',
  13 |             body: JSON.stringify({ data: { dni: data.numero_documento, nombres: 'Ana Lucía', apellidos: 'Torres Vega', direccion: '' } }),
  14 |         }));
  15 |     }
  16 |     await test.step('Registrar cliente y comprobar persistencia', async () => {
> 17 |         expect(fixture('client-state', data.numero_documento).count).toBe(0);
     |                                                                      ^ Error: expect(received).toBe(expected) // Object.is equality
  18 |         await page.goto('/clients');
  19 |         await page.getByRole('link', { name: /Registrar cliente/ }).click();
  20 |         await page.locator('#tipo_documento').selectOption('DNI');
  21 |         await page.locator('#numero_documento').fill(data.numero_documento);
  22 |         await page.getByRole('button', { name: 'Consultar DNI', exact: true }).click();
  23 |         await expect(page.locator('#nombres_razon_social')).not.toHaveValue('');
  24 |         await expect(page.locator('#apellidos')).not.toHaveValue('');
  25 |         const names = await page.locator('#nombres_razon_social').inputValue();
  26 |         const surnames = await page.locator('#apellidos').inputValue();
  27 |         const { nombres_razon_social, apellidos, ...contact } = data;
  28 |         await fillClient(page, contact);
  29 |         await page.getByRole('button', { name: 'Registrar cliente', exact: true }).click();
  30 |         await expect(page).toHaveURL(/\/clients\/\d+$/);
  31 |         await expect(page.locator('[data-toast]')).toContainText('registrado correctamente');
  32 |         await page.reload();
  33 |         await expect(page.getByRole('heading', { name: new RegExp(`Cliente #.*${names}`) })).toBeVisible();
  34 |         const state = fixture('client-state', data.numero_documento);
  35 |         expect(state.count).toBe(1);
  36 |         expect(state.record).toMatchObject({ nombres_razon_social: names, apellidos: surnames, correo: data.correo, telefono: data.telefono, estado: 'PENDIENTE' });
  37 |         await evidence(page, info, 'cliente-registrado', state);
  38 |     });
  39 |     await test.step('Intentar el mismo documento y comprobar que no se duplica', async () => {
  40 |         const before = fixture('client-state', data.numero_documento);
  41 |         await page.goto('/clients/create');
  42 |         await fillClient(page, data);
  43 |         await page.getByRole('button', { name: 'Registrar cliente', exact: true }).click();
  44 |         await expect(page.locator('#numero_documento-error')).toContainText('ya está registrado');
  45 |         expect(fixture('client-state', data.numero_documento)).toEqual(before);
  46 |         await evidence(page, info, 'documento-duplicado-rechazado');
  47 |     });
  48 | });
  49 | 
  50 | test('CP-CLI-02 — Actualizar cliente y rechazar IP inválida sin cambios parciales', async ({ page }, info) => {
  51 |     const client = fixture('client');
  52 |     await test.step('Buscar, editar y comprobar correo y teléfono guardados', async () => {
  53 |         await page.goto('/clients');
  54 |         await page.getByLabel('Nombre o documento').fill(client.numero_documento);
  55 |         await page.getByRole('button', { name: 'Aplicar filtros' }).click();
  56 |         const row = page.locator('tbody tr').filter({ hasText: client.numero_documento });
  57 |         await expect(row).toHaveCount(1);
  58 |         await row.getByRole('link', { name: /^Editar / }).click();
  59 |         await page.locator('#correo').fill(`actualizado.${client.numero_documento}@example.test`);
  60 |         await page.locator('#telefono').fill('900111223');
  61 |         await page.getByRole('button', { name: 'Guardar cambios' }).click();
  62 |         await expect(page).toHaveURL(new RegExp(`/clients/${client.cliente_id}$`));
  63 |         await page.reload();
  64 |         await expect(page.locator('dl.account-detail').first()).toContainText(`actualizado.${client.numero_documento}@example.test`);
  65 |         expect(fixture('client-state', client.numero_documento).record.telefono).toBe('900111223');
  66 |         await evidence(page, info, 'cliente-actualizado', fixture('client-state', client.numero_documento));
  67 |     });
  68 |     await test.step('Rechazar IP inválida y conservar íntegramente el registro', async () => {
  69 |         const before = fixture('client-state', client.numero_documento);
  70 |         await page.getByRole('link', { name: 'Editar cliente', exact: true }).click();
  71 |         await page.locator('#ip_router').fill('999.999.1.1');
  72 |         await page.locator('#telefono').fill('900999999');
  73 |         await page.getByRole('button', { name: 'Guardar cambios' }).click();
  74 |         await expect(page.locator('#ip_router-error')).toContainText('IP válida');
  75 |         expect(fixture('client-state', client.numero_documento)).toEqual(before);
  76 |         await evidence(page, info, 'ip-invalida-rechazada');
  77 |     });
  78 | });
  79 | 
```