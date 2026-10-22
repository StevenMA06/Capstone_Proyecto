# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: asistente.spec.ts >> CP-IA-02 — Consulta operativa, nueva conversación y protección de contraseñas
- Location: e2e\asistente.spec.ts:77:1

# Error details

```
Error: No se pudo ejecutar la preparación E2E (ri-ai-data).
PHP Warning:  Module "openssl" is already loaded in Unknown on line 0
PHP Warning:  Module "ftp" is already loaded in Unknown on line 0
Falló la preparación E2E: SQLSTATE[HY000] [1049] Unknown database 'lunnys_dni_20261016_03_202610170418460_e2e_test' (Connection: mysql, Host: 127.0.0.1, Port: 3306, Database: lunnys_dni_20261016_03_202610170418460_e2e_test, SQL: select * from `usuarios` where `nombre_usuario` = qa_e2e_gerencia limit 1)
Revisa .env.e2e y que MySQL esté iniciado.
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
            - paragraph [ref=f1e99]: "0"
          - link [ref=f1e105] [cursor=pointer]:
            - /url: http://127.0.0.1:8011/contracts
            - heading "Contratos activos" [level=2] [ref=f1e106]
            - paragraph [ref=f1e107]: "0"
          - link [ref=f1e111] [cursor=pointer]:
            - /url: http://127.0.0.1:8011/payments?status=VENCIDO
            - heading "Pagos vencidos" [level=2] [ref=f1e112]
            - paragraph [ref=f1e113]: "0"
          - link [ref=f1e117] [cursor=pointer]:
            - /url: http://127.0.0.1:8011/installations
            - heading "Instalaciones en proceso" [level=2] [ref=f1e118]
            - paragraph [ref=f1e119]: "0"
          - button "Equipos Haz clic para ver stock" [ref=f1e122] [cursor=pointer]:
            - generic [ref=f1e123]: Equipos
            - generic [ref=f1e128]: Haz clic para ver stock
          - button "Materiales Haz clic para ver stock" [ref=f1e129] [cursor=pointer]:
            - generic [ref=f1e130]: Materiales
            - generic [ref=f1e135]: Haz clic para ver stock
        - region [ref=f1e136]:
          - generic [ref=f1e137]:
            - generic [ref=f1e138]:
              - heading "Resumen de ventas" [level=2] [ref=f1e139]
              - paragraph [ref=f1e140]: Últimos 6 meses · Ventas confirmadas · Mes actual en curso
            - generic [ref=f1e141]:
              - generic [ref=f1e142]: Total del período
              - strong [ref=f1e143]: S/ 0.00
          - paragraph [ref=f1e144]: Aún no hay ventas confirmadas en este período.
          - list "Ventas confirmadas por mes, en soles" [ref=f1e145]:
            - listitem [ref=f1e146]:
              - generic [ref=f1e147]: S/ 0.00
              - generic [ref=f1e149]: may. 2026
            - listitem [ref=f1e150]:
              - generic [ref=f1e151]: S/ 0.00
              - generic [ref=f1e153]: jun. 2026
            - listitem [ref=f1e154]:
              - generic [ref=f1e155]: S/ 0.00
              - generic [ref=f1e157]: jul. 2026
            - listitem [ref=f1e158]:
              - generic [ref=f1e159]: S/ 0.00
              - generic [ref=f1e161]: ago. 2026
            - listitem [ref=f1e162]:
              - generic [ref=f1e163]: S/ 0.00
              - generic [ref=f1e165]: sep. 2026
            - listitem [ref=f1e166]:
              - generic [ref=f1e167]: S/ 0.00
              - generic [ref=f1e169]: oct. 2026
          - paragraph [ref=f1e170]: Indicadores calculados con los registros existentes.
        - region [ref=f1e171]:
          - generic [ref=f1e173]:
            - heading "Planes más vendidos" [level=2] [ref=f1e174]
            - paragraph [ref=f1e175]: Top 5 · Ventas confirmadas en los últimos 6 meses · Cantidad de ventas
          - paragraph [ref=f1e176]: Aún no hay ventas confirmadas de planes en este período.
```

# Test source

```ts
  1  | import { execFileSync } from 'node:child_process';
  2  | import { root, php } from './environment.mjs';
  3  | 
  4  | function diagnostic(value) {
  5  |     let text = String(value || '').trim();
  6  |     for (const key of ['GEMINI_API_KEY', 'E2E_GEMINI_API_KEY', 'DB_PASSWORD', 'E2E_DB_PASSWORD', 'APIPERU_API_KEY', 'E2E_APIPERU_API_KEY', 'INSTALLATION_DRIVE_CLIENT_SECRET', 'INSTALLATION_DRIVE_REFRESH_TOKEN', 'E2E_INSTALLATION_DRIVE_CLIENT_SECRET', 'E2E_INSTALLATION_DRIVE_REFRESH_TOKEN']) {
  7  |         const secret = process.env[key];
  8  |         if (secret) text = text.replaceAll(secret, '[oculto]');
  9  |     }
  10 |     return text.slice(0, 6000);
  11 | }
  12 | 
  13 | export function runFixture(command, args = [], execute = execFileSync) {
  14 |     let output;
  15 |     try {
  16 |         output = execute(php, ['scripts/e2e/fixture.php', command, ...args], {
  17 |             cwd: root, encoding: 'utf8', env: process.env, stdio: ['ignore', 'pipe', 'pipe'],
  18 |         });
  19 |     } catch (error) {
  20 |         const detail = diagnostic(String(error.stderr || '').trim() || String(error.stdout || '').trim() || error.message);
> 21 |         throw new Error(`No se pudo ejecutar la preparación E2E (${command}).\n${detail}\nRevisa .env.e2e y que MySQL esté iniciado.`);
     |               ^ Error: No se pudo ejecutar la preparación E2E (ri-ai-data).
  22 |     }
  23 |     try {
  24 |         const raw = String(output).replace(/^\uFEFF/, '');
  25 |         // Extraer el JSON aunque haya advertencias de PHP antes del objeto
  26 |         const jsonStart = raw.indexOf('{');
  27 |         const jsonEnd = raw.lastIndexOf('}');
  28 |         if (jsonStart === -1 || jsonEnd === -1) throw new Error('No se encontró JSON en la salida');
  29 |         const result = JSON.parse(raw.slice(jsonStart, jsonEnd + 1));
  30 |         if (!result || typeof result !== 'object' || Array.isArray(result)) throw new Error('Formato inesperado');
  31 |         return result;
  32 |     } catch {
  33 |         throw new Error(`La preparación E2E (${command}) no devolvió JSON válido. PHP informó:\n${diagnostic(output) || '(sin salida)'}\nRevisa la configuración E2E; no es un error de los casos funcionales.`);
  34 |     }
  35 | }
  36 | 
```