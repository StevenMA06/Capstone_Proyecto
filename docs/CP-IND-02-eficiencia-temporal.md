# CP-IND-02 — Consulta de eficiencia temporal del sistema

**Descripción:** Comprobar los tiempos de las instalaciones finalizadas por período y el aviso de procesos sin medición automática.

**Prerrequisitos:** Cuenta con permiso para consultar indicadores; ejecuciones completadas con marcas válidas de inicio y fin; período y proceso válidos.

1. Abrir Indicadores.
2. Seleccionar Desde y Hasta.
3. Seleccionar Instalaciones en Proceso.
4. Pulsar Aplicar filtros.
5. Ubicar Eficiencia temporal del sistema.
6. Comparar promedio, mínimo, máximo y cantidad con las ejecuciones completadas cuyo fin pertenece al período.
7. Abrir Ver inicio, fin y cálculo. El inicio corresponde a Iniciar instalación y el cierre se registra automáticamente cuando se confirman en Drive la fotografía y el contrato firmado verificado.
8. Cambiar el proceso a Clientes o Contratos.
9. Aplicar filtros y comprobar Sin medición automática.
10. Comprobar que no aparece Registrar observación del pretest.

**Resultado esperado:** Los tiempos y la cantidad coinciden con la fuente `ejecuciones_instalacion`, con estado COMPLETADA, inicio y fin presentes y fin posterior al inicio. El filtro se aplica a la fecha de finalización y los segundos se convierten a minutos. Los procesos sin marcas automáticas muestran Sin medición automática; no se inventan tiempos ni se ofrece un formulario de observaciones.

Esta definición armoniza el título, la descripción, los pasos y el resultado esperado del PPF. No añade una funcionalidad de registro manual de observaciones ni altera resultados históricos. La copia corregida del Word conserva el resto del contenido original.
