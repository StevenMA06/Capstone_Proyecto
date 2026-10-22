# Registro de ejecución para el PPF

Ejecución: 2026-10-17T06-29-10-405Z. Playwright 1.62.1. DNI: real.

| Caso | Ciclo | Resultado | Duración (ms) |
|---|---|---|---|
| CP-IA-01 | 1 | No OK | 9501 |
| CP-IA-01 | 2 | No OK | 8763 |
| CP-IA-01 | 3 | No OK | 9651 |
| CP-IA-01 | 4 | No OK | 9487 |
| CP-IA-01 | 5 | No OK | 9665 |

PARCIAL significa que la interfaz utilizó una respuesta DNI simulada: no acredita API Perú real.
PARCIAL (Drive real pendiente) acredita únicamente el guardado local y las restricciones verificadas; no acredita subida real a Drive ni cierre automático con contrato firmado.
PARCIAL (PPF inconsistente) distingue los pasos verificados de un resultado esperado contradictorio. IA sin Gemini real queda BLOQUEADO, aunque se adjunten comprobaciones locales.
Cada caso contiene pasos positivos y negativos. Un rechazo esperado cuenta como OK.
No OK puede indicar una diferencia funcional con el PPF, incluso si todos los pasos del script terminaron. Consulta los errores y adjuntos.
La ejecución no mide requisitos mínimos de hardware. Registra las condiciones reales si realizas ese segundo escenario.