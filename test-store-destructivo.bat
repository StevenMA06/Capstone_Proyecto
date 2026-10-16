@echo off
REM Test DESTRUCTIVO del método store()
REM Ejecuta 35 intentos de romper el sistema

echo.
echo ================================================================
echo  TEST DESTRUCTIVO: Contract::store()
echo ================================================================
echo.
echo Este test intenta ROMPER el sistema con:
echo   - 7 clientes invalidos
echo   - 10 duraciones imposibles
echo   - 8 fechas invalidas
echo   - 10 ataques de seguridad
echo.
echo Total: 35 intentos de destruccion
echo ================================================================
echo.
pause

cd /d C:\xampp\htdocs\Capstone\Capstone_Proyecto
C:\xampp\php\php.exe artisan test testsphpunit/Contratos/ContractStoreTest.php

echo.
echo ================================================================
echo  EJECUCION COMPLETADA
echo ================================================================
echo.
echo Si todos los tests PASARON: El metodo esta bien protegido
echo Si algun test FALLO: Encontraste una vulnerabilidad
echo.
pause
