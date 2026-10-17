# Correcciones de CP-REP-02 y CP-IND-01/02

- `reports.export` utiliza el permiso existente `reports.view`. La autorización del FormRequest sigue limitando los tipos de reporte para cuentas operativas. Gerencia puede descargar PDF/XLSX y una cuenta limitada no puede exportar Inventario reservado.
- La consulta de materiales usa unidad_empaque cuando el inventario se controla por empaques y unidad_base en los demás casos, conservando el alias de presentación unidad_medida. No cambia el esquema.
- El cálculo temporal usa ejecuciones_instalacion con estado COMPLETADA, marcas presentes, fin posterior al inicio y finalización dentro del período. Calcula cantidad, promedio, mínimo y máximo en minutos. El detalle explica el inicio manual y el cierre automático real.
- CP-IND-02 queda armonizado en `CP-IND-02-eficiencia-temporal.md` y en la copia corregida del PPF: se verifica la eficiencia temporal y los procesos sin medición, no el registro de observaciones. Se actualiza la expectativa del detalle de inicio y cierre de Playwright; no se reducen las comprobaciones numéricas.

## Verificación

```powershell
git pull origin main
php artisan optimize:clear
npm run e2e:headed -- e2e/reportes.spec.ts e2e/indicadores.spec.ts e2e/bi.spec.ts
```

Se ejecutan seis casos, incluyendo la regresión de BI, que comparte la consulta corregida. Las descargas se abren y comparan mediante lectores independientes de PDF y XLSX. La prueba conserva la comprobación de acceso denegado para una cuenta con alcance limitado. Las marcas temporales ficticias verifican la fórmula, no certifican la ejecución física de instalaciones ni Drive real.

No se requieren migraciones ni permisos nuevos. No se modifican credenciales ni la base operativa.
