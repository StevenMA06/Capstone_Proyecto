# Consulta de DNI con API Perú

Proveedor: https://apiperu.pe (no apiperu.dev). Referencia: https://docs.apiperu.pe/.

1. Crea una cuenta, verifica tu correo y genera una API Key desde la plataforma de API Perú. Puedes comenzar con su plan gratuito; revisa allí tu cuota disponible.
2. En el archivo .env local añade APIPERU_API_KEY="tu_clave". No publiques la clave ni el .env.
3. Ejecuta git pull origin main y php artisan optimize:clear. Reinicia php artisan serve si estaba ejecutándose.
4. Inicia sesión con permiso para registrar o actualizar clientes, o como Administrador para empleados. Abre el formulario, selecciona DNI, ingresa 8 dígitos y pulsa Consultar DNI.
5. Revisa los nombres y apellidos antes de guardar. La consulta no registra la persona automáticamente.

La clave solo se usa desde Laravel para POST https://api.apiperu.pe/dni. Se requiere salida HTTPS y certificados confiables en PHP. No se desactiva la verificación TLS.

Solo se completan nombres, apellidos y, en clientes, una dirección sugerida cuando la API la devuelve y el campo está vacío. La dirección no está garantizada ni representa necesariamente el lugar de instalación. Teléfono y correo se completan manualmente. Empleados no tiene un campo de dirección.

Los otros formularios actuales seleccionan clientes o empleados existentes; no se añade una consulta donde no hay captura de DNI.

Errores o cupo agotado no impiden completar manualmente el formulario. Cada clic realiza una consulta; no consulta mientras escribes. El endpoint está autenticado, exige permiso de consulta y registro/edición, valida ocho dígitos y limita a diez solicitudes por minuto.

No requiere migraciones ni paquetes nuevos. Para verificar la integración sin consumir consultas: php artisan test --filter=DniLookupTest (base de pruebas aislada).
