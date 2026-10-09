# 🔒 Restricciones de Privacidad del Chatbot

## Datos Protegidos

El chatbot ha sido configurado con **restricciones de privacidad** para proteger información sensible.

### ❌ Información BLOQUEADA

**Clientes:**
- ❌ Nombres completos
- ❌ DNI / Documentos de identidad
- ❌ Teléfonos
- ❌ Direcciones
- ❌ Correos electrónicos
- ❌ Referencias de dirección

**Empleados:**
- ❌ Nombres completos
- ❌ DNI / Documentos de identidad  
- ❌ Teléfonos
- ❌ Direcciones
- ❌ Información salarial
- ❌ Datos personales

### ✅ Información PERMITIDA

**Estadísticas de Clientes:**
- ✅ Total de clientes (activos/inactivos)
- ✅ Cantidad de clientes con contrato
- ✅ Métricas agregadas

**Estadísticas de Empleados:**
- ✅ Total de empleados (activos/inactivos)
- ✅ Distribución por cargo (sin nombres)
- ✅ Métricas agregadas

**Información Técnica:**
- ✅ Equipos (códigos, nombres, estados, ubicaciones)
- ✅ Materiales (códigos, nombres, stock, precios)
- ✅ Contratos (fechas, montos, planes) - SIN datos del cliente
- ✅ Pagos (montos, estados, fechas) - SIN datos personales
- ✅ Estadísticas generales del negocio

## Ejemplos de Consultas

### ✅ PERMITIDAS:

- "¿Cuántos clientes activos tenemos?"
- "¿Cuántos empleados hay por cargo?"
- "Dame información del equipo EQ-001"
- "¿Qué materiales tienen stock bajo?"
- "¿Cuántos contratos activos hay?"
- "¿Cuál es el monto total de pagos pendientes?"

### ❌ BLOQUEADAS:

- "Dame el DNI del cliente Juan Pérez" → **Respuesta: Por privacidad, no puedo proporcionar datos personales**
- "¿Cuál es el teléfono del técnico?" → **Respuesta: Por privacidad, no puedo proporcionar datos personales**
- "Muéstrame la dirección de los clientes" → **Respuesta: Por privacidad, no puedo proporcionar datos personales**

## Respuesta Automática del Chatbot

Cuando alguien intenta obtener información personal, el chatbot responderá:

> "Por razones de privacidad y seguridad, no puedo proporcionar información personal de clientes o empleados. Solo puedo compartir estadísticas agregadas y métricas generales."

## Configuración Técnica

Las restricciones están implementadas en:
- **Archivo**: `app/Services/GeminiService.php`
- **Método**: `getRelevantContext()`
- **Prompt del sistema**: Instruye a Gemini AI para NO revelar datos personales

## Beneficios

1. **Cumplimiento GDPR/LOPD**: Protección de datos personales
2. **Seguridad**: Reduce riesgo de exposición de información sensible
3. **Confianza**: Los empleados y clientes están protegidos
4. **Transparencia**: El chatbot informa cuando no puede dar cierta información

---

**Nota**: Estas restricciones pueden ser ajustadas según las necesidades del negocio, pero se recomienda mantener la protección de datos personales.
