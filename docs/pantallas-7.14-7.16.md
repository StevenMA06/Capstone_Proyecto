# Pantallas 7.14 a 7.16

Reportes (RF 7.14) consulta registros reales desde MySQL por módulo y período. La tabla y las exportaciones PDF/Excel se generan en el servidor; una consulta sin coincidencias muestra el estado vacío. Indicadores (RF 7.15) y Panel BI (RF 7.16) conservan sus pantallas de presentación hasta que se conecten a datos operativos.

Reportes: clientes, contratos y ventas, pagos, instalaciones e inventario. Indicadores y BI mantienen los gráficos ilustrativos del prototipo.

La exportación se restringe por permisos y genera archivos desde las mismas consultas del servidor. No ejecutar `migrate:fresh` para preparar una base con información.
