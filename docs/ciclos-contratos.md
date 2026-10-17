# Datos diferentes por ciclo de CP-CON-01

Cada ciclo ejecuta el caso completo: registro pendiente, firma sintética verificada sin activar una instalación incompleta y activación tras preparar la instalación completada. Este prerrequisito no acredita instalaciones ni Drive real.

| Ciclo | Cliente ficticio | Velocidad (Mbps) | Precio mensual (S/) | Duración (meses) | Inicio |
|---|---|---|---|---|---|
| 1 | Ana Lucía Torres Vega | 30 | 49.90 | 1 | Hoy, hora de Perú |
| 2 | Luis Alberto Rojas Díaz | 60 | 59.90 | 3 | Hoy + 1 día |
| 3 | María José Quispe Flores | 100 | 79.90 | 6 | Hoy + 2 días |
| 4 | Carlos Andrés Medina Ruiz | 200 | 99.90 | 12 | Hoy + 3 días |
| 5 | Rosa Elena Vargas Soto | 300 | 129.90 | 24 | Hoy + 4 días |

También varían teléfono y dirección; DNI, correo y nombre del plan se generan para evitar colisiones con ejecuciones anteriores. Los datos preparados se adjuntan en `datos-del-ciclo` del reporte HTML. El contrato se registra por interfaz y se comprueba la persistencia de cliente, plan, duración y fecha, además de las comprobaciones originales de firma, pedido y estado.

```powershell
npm run e2e:headed -- e2e/contratos.spec.ts -g "CP-CON-01" --repeat-each=5
```

`repeatEachIndex` identifica el ciclo. A partir del sexto se repiten los cinco perfiles, pero varían la fecha, el teléfono, la dirección y los identificadores. No se cambian los demás casos ni la cantidad de ciclos por defecto. Estos cinco perfiles son datos válidos; no sustituyen casos de entradas inválidas ni acreditan hardware mínimo.
