# Configuración del router del cliente

Después de actualizar el proyecto:

```bash
git pull origin main
php artisan migrate
php artisan optimize:clear
```

En el registro de clientes se solicita IP del router, modelo, usuario, contraseña del router, SSID y contraseña WiFi. El número de referencia es opcional y es independiente de la referencia de dirección. La IP admite IPv4 e IPv6.

Los clientes anteriores se conservan con los nuevos campos sin registrar. Al editar sus datos se debe completar la configuración. Para contraseñas ya configuradas, dejar el campo vacío conserva la contraseña; escribir otra la reemplaza. El botón del ojo permite revisar lo que se está escribiendo.

La ficha muestra los datos de configuración y si las contraseñas están configuradas, sin exponerlas. Las contraseñas se almacenan cifradas y no se incluyen en la serialización del cliente ni en los datos recuperados tras errores de formulario.

Conserva la `APP_KEY` existente y respáldala junto con la base de datos: se utiliza para cifrar y descifrar estas contraseñas. No ejecutes `key:generate` en una instalación con datos cifrados.

Estos campos pertenecen exclusivamente al módulo de clientes y no modifican el registro de empleados ni las credenciales de acceso al sistema.
