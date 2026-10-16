@echo off
REM ================================================================
REM Script para ejecutar tests de PHPUnit en Windows
REM Proyecto: Lunnys Peru - Sistema de Gestion
REM ================================================================

setlocal

REM Configuracion
set PHP_PATH=C:\xampp\php\php.exe
set PROJECT_PATH=%~dp0
set TEST_PATH=testsphpunit

echo.
echo ================================================================
echo  TESTS DE LUNNYS PERU - Sistema de Gestion
echo ================================================================
echo.

REM Verificar que PHP existe
if not exist "%PHP_PATH%" (
    echo [ERROR] PHP no encontrado en: %PHP_PATH%
    echo Por favor actualiza la ruta en el script.
    pause
    exit /b 1
)

REM Menu principal
:menu
echo.
echo Selecciona una opcion:
echo.
echo [1] Ejecutar TODOS los tests
echo [2] Ejecutar tests de CONTRATOS (24 tests)
echo [3] Ejecutar tests de PAGOS (24 tests)
echo [4] Ejecutar test especifico de CONTRATOS
echo [5] Ejecutar test especifico de PAGOS
echo [6] Ver resumen de resultados
echo [0] Salir
echo.
set /p option="Opcion: "

if "%option%"=="1" goto all_tests
if "%option%"=="2" goto contract_tests
if "%option%"=="3" goto payment_tests
if "%option%"=="4" goto specific_contract
if "%option%"=="5" goto specific_payment
if "%option%"=="6" goto summary
if "%option%"=="0" goto end
goto menu

REM Ejecutar todos los tests
:all_tests
echo.
echo [EJECUTANDO] Todos los tests...
echo.
"%PHP_PATH%" artisan test %TEST_PATH%/
pause
goto menu

REM Ejecutar tests de contratos
:contract_tests
echo.
echo [EJECUTANDO] Tests de Contratos...
echo.
echo Selecciona que probar:
echo [1] TODOS los tests de Contratos
echo [2] Solo método STORE (35 casos destructivos)
echo [3] Solo método ACTIVATE
echo [4] Solo método UPDATE  
echo [5] Solo método CANCEL FOR NON PAYMENT
echo [0] Volver
echo.
set /p contract_option="Opcion: "

if "%contract_option%"=="1" "%PHP_PATH%" artisan test %TEST_PATH%/Contratos/
if "%contract_option%"=="2" "%PHP_PATH%" artisan test %TEST_PATH%/Contratos/ContractStoreTest.php
if "%contract_option%"=="3" "%PHP_PATH%" artisan test %TEST_PATH%/Contratos/ContractActivateTest.php
if "%contract_option%"=="4" "%PHP_PATH%" artisan test %TEST_PATH%/Contratos/ContractUpdateTest.php
if "%contract_option%"=="5" "%PHP_PATH%" artisan test %TEST_PATH%/Contratos/ContractCancelTest.php
if "%contract_option%"=="0" goto menu

pause
goto menu

REM Ejecutar tests de pagos
:payment_tests
echo.
echo [EJECUTANDO] Tests de Pagos (24 tests)...
echo.
"%PHP_PATH%" artisan test %TEST_PATH%/Pagos/PaymentCriticalTest.php
pause
goto menu

REM Ejecutar test especifico de contratos
:specific_contract
echo.
echo Tests disponibles de CONTRATOS:
echo.
echo [1] test_store_crea_contrato_con_datos_validos
echo [2] test_store_rechaza_cliente_inexistente
echo [3] test_store_escapa_sql_injection_en_direccion
echo [4] test_store_sanitiza_xss_en_referencia
echo [5] test_store_rechaza_duracion_negativa
echo [6] test_store_rechaza_duracion_cero
echo [7] test_activate_cambia_estado_de_pendiente_a_activo
echo [8] test_reactivate_contrato_cancelado_con_deuda_saldada
echo [9] test_update_ignora_mass_assignment_malicioso
echo [0] Volver
echo.
set /p test_num="Numero de test: "

if "%test_num%"=="1" set test_name=test_store_crea_contrato_con_datos_validos
if "%test_num%"=="2" set test_name=test_store_rechaza_cliente_inexistente
if "%test_num%"=="3" set test_name=test_store_escapa_sql_injection_en_direccion
if "%test_num%"=="4" set test_name=test_store_sanitiza_xss_en_referencia
if "%test_num%"=="5" set test_name=test_store_rechaza_duracion_negativa
if "%test_num%"=="6" set test_name=test_store_rechaza_duracion_cero
if "%test_num%"=="7" set test_name=test_activate_cambia_estado_de_pendiente_a_activo
if "%test_num%"=="8" set test_name=test_reactivate_contrato_cancelado_con_deuda_saldada
if "%test_num%"=="9" set test_name=test_update_ignora_mass_assignment_malicioso
if "%test_num%"=="0" goto menu

echo.
echo [EJECUTANDO] %test_name%...
echo.
"%PHP_PATH%" artisan test %TEST_PATH%/Contratos/ContractCriticalTest.php --filter="%test_name%"
pause
goto menu

REM Ejecutar test especifico de pagos
:specific_payment
echo.
echo Tests disponibles de PAGOS:
echo.
echo [1] test_generate_payments_crea_12_pagos_para_contrato_anual
echo [2] test_generate_payments_asigna_codigos_unicos
echo [3] test_confirm_payment_cambia_estado_a_realizado
echo [4] test_confirm_payment_escapa_sql_injection_en_numero_operacion
echo [5] test_mark_as_overdue_respeta_periodo_gracia_4_dias
echo [6] test_mark_as_overdue_cambia_a_impago_despues_5_dias
echo [7] test_annul_cambia_pendiente_a_anulado
echo [8] test_prevent_modification_rechaza_edicion_de_pago_realizado
echo [0] Volver
echo.
set /p test_num="Numero de test: "

if "%test_num%"=="1" set test_name=test_generate_payments_crea_12_pagos_para_contrato_anual
if "%test_num%"=="2" set test_name=test_generate_payments_asigna_codigos_unicos
if "%test_num%"=="3" set test_name=test_confirm_payment_cambia_estado_a_realizado
if "%test_num%"=="4" set test_name=test_confirm_payment_escapa_sql_injection_en_numero_operacion
if "%test_num%"=="5" set test_name=test_mark_as_overdue_respeta_periodo_gracia_4_dias
if "%test_num%"=="6" set test_name=test_mark_as_overdue_cambia_a_impago_despues_5_dias
if "%test_num%"=="7" set test_name=test_annul_cambia_pendiente_a_anulado
if "%test_num%"=="8" set test_name=test_prevent_modification_rechaza_edicion_de_pago_realizado
if "%test_num%"=="0" goto menu

echo.
echo [EJECUTANDO] %test_name%...
echo.
"%PHP_PATH%" artisan test %TEST_PATH%/Pagos/PaymentCriticalTest.php --filter="%test_name%"
pause
goto menu

REM Ver resumen
:summary
echo.
echo ================================================================
echo  RESUMEN DE TESTS
echo ================================================================
echo.
echo Total tests implementados: 48
echo   - Contratos: 24 tests
echo   - Pagos:     24 tests
echo.
echo Estado actual:
echo   - Tests pasando:  14 (34%%)
echo   - Tests fallando: 27 (66%%)
echo.
echo Cobertura destructiva:
echo   - SQL Injection:  3 tests
echo   - XSS:            2 tests
echo   - Mass Assignment: 2 tests
echo   - Validaciones:   12 tests
echo   - Edge cases:     22 tests
echo.
echo Para mas detalles ver: testsphpunit/RESUMEN_COMPLETO.md
echo.
pause
goto menu

REM Salir
:end
echo.
echo Gracias por usar el sistema de tests!
echo.
endlocal
exit /b 0
