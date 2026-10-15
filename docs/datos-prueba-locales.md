# Datos ficticios en pantallas normales

En desarrollo local, ejecutar manualmente:

```powershell
php artisan db:seed --class=LocalAnalyticsDemoSeeder
```

El cargador no se ejecuta con migraciones ni con DatabaseSeeder. Solo admite entorno local/testing y MySQL en loopback. Inserta registros PRUEBA-BI en una transacción sin actualizar ni borrar registros previos; una segunda ejecución no duplica la muestra.

Incluye 78 clientes, contratos, pagos y pedidos, personal ficticio, ventas vinculadas a contratos, equipos, materiales, Kardex y observaciones ficticias. Las existencias de la muestra corresponden al saldo de sus movimientos. No son operaciones ni evidencia real del pretest. La base local no se publica en GitHub. No se envían correos ni se crean usuarios o contraseñas.

Usar el rango de fechas desde el primer día de hace cinco meses hasta hoy para ver seis meses. Eliminar vista_previa=1 de la URL para consultar los datos cargados en la base normal.

No cambia .env ni requiere una migración adicional. Se necesita tener las migraciones anteriores aplicadas, incluida observaciones_pretest, para mostrar los ejemplos de tiempos.
