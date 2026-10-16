@echo off
REM Script simple para ejecutar tests de Contratos
echo.
echo ========================================
echo TESTS DE CONTRATOS - LUNNYS PERU
echo ========================================
echo.

cd /d C:\xampp\htdocs\Capstone\Capstone_Proyecto
C:\xampp\php\php.exe artisan test testsphpunit/Contratos/ContractCriticalTest.php

echo.
echo ========================================
echo EJECUCION COMPLETADA
echo ========================================
pause
