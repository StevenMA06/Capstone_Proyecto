-- PUNTOS A TENER EN CUENTA AL DESARROLLAR EL SOFTWARE
-- Corrección 3 – Sincronización entre Kardex y stock: La base de datos cuenta con las estructuras necesarias para gestionar el Kardex y las existencias de materiales; sin embargo, se debe garantizar que cada entrada, salida, asignación, consumo, devolución o ajuste actualice correctamente el stock. Esta operación debe realizarse mediante una transacción desde el sistema, de modo que, si alguna parte del proceso falla, se reviertan todos los cambios para evitar diferencias entre el stock actual y el historial del Kardex.

-- Corrección 4 – Contrato activo para instalaciones: Antes de registrar un pedido de instalación, el sistema debe comprobar que el contrato asociado se encuentre en estado ACTIVO. La relación existente en la base de datos garantiza que el contrato exista, pero no verifica su estado; por ello, esta validación deberá realizarse desde el sistema para impedir que se generen instalaciones correspondientes a contratos cancelados, finalizados o que todavía no estén activos.

-- Corrección 5 – Técnico obligatorio en instalaciones programadas: La base de datos permite que inicialmente un pedido de instalación no tenga técnico asignado, lo cual es válido mientras permanezca pendiente. Sin embargo, cuando el pedido cambie al estado PROGRAMADO, el sistema deberá exigir que tenga un técnico responsable y una fecha programada. De esta manera se cumple la regla de negocio establecida para la planificación de las instalaciones.

-- Business Intelligence (BI): La base de datos ya dispone de la información necesaria para implementar los indicadores y paneles de Business Intelligence, por lo que no es necesario crear una tabla específica denominada BI. Los indicadores pueden calcularse utilizando los datos almacenados en clientes, contratos, ventas, pagos, equipos, materiales, Kardex e instalaciones. Por ejemplo, se podrán obtener ventas mensuales, clientes registrados, pagos pendientes, instalaciones completadas, disponibilidad de equipos y niveles de stock; posteriormente, el sistema utilizará estos datos para generar gráficos, indicadores y dashboards.

CREATE DATABASE Db_LunnysPeru
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE Db_LunnysPeru;

CREATE TABLE roles (
    rol_id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre              VARCHAR(50) NOT NULL,
    descripcion         VARCHAR(255) NULL,
    estado              ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    creado_en           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_roles_nombre UNIQUE (nombre)
) ENGINE=InnoDB;

CREATE TABLE permisos (
    permiso_id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo              VARCHAR(100) NOT NULL,
    modulo              VARCHAR(60) NOT NULL,
    accion              VARCHAR(60) NOT NULL,
    descripcion         VARCHAR(255) NULL,
    estado              ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    CONSTRAINT uq_permisos_codigo UNIQUE (codigo)
) ENGINE=InnoDB;

CREATE TABLE rol_permiso (
    rol_id              BIGINT UNSIGNED NOT NULL,
    permiso_id          BIGINT UNSIGNED NOT NULL,
    permitido           BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (rol_id, permiso_id),
    CONSTRAINT fk_rol_permiso_rol
        FOREIGN KEY (rol_id) REFERENCES roles(rol_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_rol_permiso_permiso
        FOREIGN KEY (permiso_id) REFERENCES permisos(permiso_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE empleados (
    empleado_id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo_documento      ENUM('DNI','CE','OTRO') NOT NULL DEFAULT 'DNI',
    numero_documento    VARCHAR(20) NOT NULL,
    nombres             VARCHAR(100) NOT NULL,
    apellidos           VARCHAR(120) NOT NULL,
    correo              VARCHAR(150) NULL,
    telefono            VARCHAR(25) NULL,
    cargo               VARCHAR(80) NOT NULL,
    fecha_ingreso       DATE NULL,
    fecha_cese          DATE NULL,
    estado              ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    creado_en           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_empleados_documento UNIQUE (tipo_documento, numero_documento),
    CONSTRAINT uq_empleados_correo UNIQUE (correo)
) ENGINE=InnoDB;

CREATE TABLE usuarios (
    usuario_id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empleado_id         BIGINT UNSIGNED NULL,
    rol_id              BIGINT UNSIGNED NOT NULL,
    nombre_usuario      VARCHAR(60) NOT NULL,
    correo              VARCHAR(150) NOT NULL,
    password_hash       VARCHAR(255) NOT NULL,
    estado              ENUM('ACTIVO','INACTIVO','BLOQUEADO') NOT NULL DEFAULT 'ACTIVO',
    ultimo_acceso_en    DATETIME NULL,
    creado_en           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_usuarios_nombre UNIQUE (nombre_usuario),
    CONSTRAINT uq_usuarios_correo UNIQUE (correo),
    CONSTRAINT uq_usuarios_empleado UNIQUE (empleado_id),
    CONSTRAINT fk_usuarios_empleado
        FOREIGN KEY (empleado_id) REFERENCES empleados(empleado_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_usuarios_rol
        FOREIGN KEY (rol_id) REFERENCES roles(rol_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE codigos_acceso_2fa (
    codigo_2fa_id       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id          BIGINT UNSIGNED NOT NULL,
    codigo_hash         VARCHAR(255) NOT NULL,
    solicitado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    vence_en            DATETIME NOT NULL,
    usado_en            DATETIME NULL,
    invalidado_en       DATETIME NULL,
    intentos            SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    estado              ENUM('VIGENTE','USADO','VENCIDO','INVALIDADO') NOT NULL DEFAULT 'VIGENTE',
    CONSTRAINT fk_2fa_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(usuario_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX ix_2fa_usuario_estado (usuario_id, estado),
    INDEX ix_2fa_vencimiento (vence_en)
) ENGINE=InnoDB;

CREATE TABLE sesiones_usuario (
    sesion_id           CHAR(36) PRIMARY KEY,
    usuario_id          BIGINT UNSIGNED NOT NULL,
    iniciada_en         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultima_actividad_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expira_en           DATETIME NOT NULL,
    cerrada_en          DATETIME NULL,
    ip                   VARCHAR(45) NULL,
    user_agent          VARCHAR(500) NULL,
    estado              ENUM('ACTIVA','CERRADA','EXPIRADA') NOT NULL DEFAULT 'ACTIVA',
    CONSTRAINT fk_sesion_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(usuario_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX ix_sesion_usuario_estado (usuario_id, estado),
    INDEX ix_sesion_expira (expira_en)
) ENGINE=InnoDB;

CREATE TABLE historial_accesos (
    acceso_id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id          BIGINT UNSIGNED NULL,
    nombre_usuario_intentado VARCHAR(60) NULL,
    fecha_hora          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resultado           ENUM('EXITOSO','CREDENCIALES_INCORRECTAS','2FA_INCORRECTO','2FA_VENCIDO','CUENTA_INACTIVA','BLOQUEADO') NOT NULL,
    ip                  VARCHAR(45) NULL,
    user_agent          VARCHAR(500) NULL,
    detalle             VARCHAR(255) NULL,
    CONSTRAINT fk_historial_acceso_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(usuario_id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX ix_acceso_usuario_fecha (usuario_id, fecha_hora),
    INDEX ix_acceso_resultado_fecha (resultado, fecha_hora)
) ENGINE=InnoDB;

CREATE TABLE clientes (
    cliente_id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo_documento      ENUM('DNI','CE','RUC','OTRO') NOT NULL DEFAULT 'DNI',
    numero_documento    VARCHAR(20) NOT NULL,
    nombres_razon_social VARCHAR(180) NOT NULL,
    apellidos           VARCHAR(120) NULL,
    correo              VARCHAR(150) NULL,
    telefono            VARCHAR(25) NULL,
    direccion           VARCHAR(255) NOT NULL,
    referencia_direccion VARCHAR(255) NULL,
    distrito            VARCHAR(100) NULL,
    provincia           VARCHAR(100) NULL,
    departamento        VARCHAR(100) NULL,
    latitud             DECIMAL(10,7) NULL,
    longitud            DECIMAL(10,7) NULL,
    estado              ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    creado_por          BIGINT UNSIGNED NOT NULL,
    creado_en           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_clientes_documento UNIQUE (tipo_documento, numero_documento),
    CONSTRAINT fk_clientes_creado_por
        FOREIGN KEY (creado_por) REFERENCES usuarios(usuario_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX ix_clientes_nombre (nombres_razon_social),
    INDEX ix_clientes_estado (estado)
) ENGINE=InnoDB;

CREATE TABLE zonas_cobertura (
    zona_id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo              VARCHAR(30) NOT NULL,
    nombre              VARCHAR(120) NOT NULL,
    distrito            VARCHAR(100) NOT NULL,
    provincia           VARCHAR(100) NULL,
    departamento        VARCHAR(100) NOT NULL DEFAULT 'La Libertad',
    descripcion         VARCHAR(500) NULL,
    estado              ENUM('ACTIVA','INACTIVA') NOT NULL DEFAULT 'ACTIVA',
    creado_por          BIGINT UNSIGNED NOT NULL,
    creado_en           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_zona_codigo UNIQUE (codigo),
    CONSTRAINT fk_zona_creado_por
        FOREIGN KEY (creado_por) REFERENCES usuarios(usuario_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX ix_zona_nombre_estado (nombre, estado)
) ENGINE=InnoDB;

CREATE TABLE planes_servicio (
    plan_id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero_plan         VARCHAR(30) NOT NULL,
    nombre              VARCHAR(120) NOT NULL,
    tipo_servicio       VARCHAR(80) NOT NULL,
    velocidad_mbps      INT UNSIGNED NULL,
    precio_mensual      DECIMAL(12,2) NOT NULL,
    condiciones         TEXT NULL,
    descripcion         VARCHAR(500) NULL,
    estado              ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    creado_por          BIGINT UNSIGNED NOT NULL,
    creado_en           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_plan_numero UNIQUE (numero_plan),
    CONSTRAINT chk_plan_precio CHECK (precio_mensual > 0),
    CONSTRAINT fk_plan_creado_por
        FOREIGN KEY (creado_por) REFERENCES usuarios(usuario_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX ix_plan_nombre_estado (nombre, estado)
) ENGINE=InnoDB;

CREATE TABLE plan_zona_cobertura (
    plan_id             BIGINT UNSIGNED NOT NULL,
    zona_id             BIGINT UNSIGNED NOT NULL,
    estado              ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    asignado_en         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (plan_id, zona_id),
    CONSTRAINT fk_plan_zona_plan
        FOREIGN KEY (plan_id) REFERENCES planes_servicio(plan_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_plan_zona_zona
        FOREIGN KEY (zona_id) REFERENCES zonas_cobertura(zona_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE contratos (
    contrato_id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo              VARCHAR(40) NOT NULL,
    cliente_id          BIGINT UNSIGNED NOT NULL,
    plan_id             BIGINT UNSIGNED NOT NULL,
    zona_id             BIGINT UNSIGNED NOT NULL,
    responsable_id      BIGINT UNSIGNED NOT NULL,
    fecha_registro      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_inicio        DATE NOT NULL,
    duracion_meses      SMALLINT UNSIGNED NOT NULL,
    fecha_fin           DATE NOT NULL,
    precio_contratado   DECIMAL(12,2) NOT NULL,
    direccion_servicio  VARCHAR(255) NOT NULL,
    estado              ENUM('PENDIENTE','ACTIVO','FINALIZADO','CANCELADO') NOT NULL DEFAULT 'PENDIENTE',
    pdf_contrato_ruta   VARCHAR(500) NULL,
    observaciones       TEXT NULL,
    actualizado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_contrato_codigo UNIQUE (codigo),
    CONSTRAINT chk_contrato_duracion CHECK (duracion_meses > 0),
    CONSTRAINT chk_contrato_precio CHECK (precio_contratado > 0),
    CONSTRAINT chk_contrato_fechas CHECK (fecha_fin >= fecha_inicio),
    CONSTRAINT fk_contrato_cliente
        FOREIGN KEY (cliente_id) REFERENCES clientes(cliente_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_contrato_plan
        FOREIGN KEY (plan_id) REFERENCES planes_servicio(plan_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_contrato_zona
        FOREIGN KEY (zona_id) REFERENCES zonas_cobertura(zona_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_contrato_responsable
        FOREIGN KEY (responsable_id) REFERENCES empleados(empleado_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX ix_contrato_cliente (cliente_id, estado),
    INDEX ix_contrato_plan (plan_id),
    INDEX ix_contrato_periodo (fecha_inicio, fecha_fin),
    INDEX ix_contrato_estado (estado)
) ENGINE=InnoDB;

CREATE TABLE evidencias_contrato (
    evidencia_contrato_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contrato_id         BIGINT UNSIGNED NOT NULL,
    tipo                ENUM('CONTRATO_FIRMADO','FOTO_VALIDACION','OTRA') NOT NULL,
    ruta_archivo        VARCHAR(500) NOT NULL,
    nombre_archivo      VARCHAR(255) NULL,
    mime_type           VARCHAR(100) NULL,
    subido_por          BIGINT UNSIGNED NOT NULL,
    subido_en           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    observacion         VARCHAR(500) NULL,
    CONSTRAINT fk_evid_contrato
        FOREIGN KEY (contrato_id) REFERENCES contratos(contrato_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_evid_contrato_usuario
        FOREIGN KEY (subido_por) REFERENCES usuarios(usuario_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX ix_evidencia_contrato (contrato_id, tipo)
) ENGINE=InnoDB;

CREATE TABLE ventas (
    venta_id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contrato_id         BIGINT UNSIGNED NOT NULL,
    cliente_id          BIGINT UNSIGNED NOT NULL,
    responsable_id      BIGINT UNSIGNED NOT NULL,
    fecha_venta         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    monto               DECIMAL(12,2) NOT NULL,
    estado              ENUM('CONFIRMADA','ANULADA') NOT NULL DEFAULT 'CONFIRMADA',
    CONSTRAINT uq_venta_contrato UNIQUE (contrato_id),
    CONSTRAINT chk_venta_monto CHECK (monto > 0),
    CONSTRAINT fk_venta_contrato
        FOREIGN KEY (contrato_id) REFERENCES contratos(contrato_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_venta_cliente
        FOREIGN KEY (cliente_id) REFERENCES clientes(cliente_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_venta_responsable
        FOREIGN KEY (responsable_id) REFERENCES empleados(empleado_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX ix_venta_cliente_fecha (cliente_id, fecha_venta),
    INDEX ix_venta_responsable_fecha (responsable_id, fecha_venta)
) ENGINE=InnoDB;

CREATE TABLE pagos (
    pago_id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contrato_id         BIGINT UNSIGNED NOT NULL,
    codigo              VARCHAR(40) NOT NULL,
    periodo_servicio    CHAR(7) NULL COMMENT 'Formato YYYY-MM',
    fecha_vencimiento   DATE NOT NULL,
    fecha_pago          DATETIME NULL,
    monto               DECIMAL(12,2) NOT NULL,
    metodo_pago         VARCHAR(60) NULL,
    referencia_externa  VARCHAR(120) NULL,
    estado              ENUM('PENDIENTE','REALIZADO','VENCIDO','ANULADO') NOT NULL DEFAULT 'PENDIENTE',
    registrado_por      BIGINT UNSIGNED NOT NULL,
    constancia_ruta     VARCHAR(500) NULL,
    observaciones       VARCHAR(500) NULL,
    creado_en           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_pago_codigo UNIQUE (codigo),
    CONSTRAINT chk_pago_monto CHECK (monto > 0),
    CONSTRAINT fk_pago_contrato
        FOREIGN KEY (contrato_id) REFERENCES contratos(contrato_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_pago_usuario
        FOREIGN KEY (registrado_por) REFERENCES usuarios(usuario_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX ix_pago_contrato_estado (contrato_id, estado),
    INDEX ix_pago_vencimiento_estado (fecha_vencimiento, estado),
    INDEX ix_pago_fecha (fecha_pago)
) ENGINE=InnoDB;

CREATE TABLE recordatorios_pago (
    recordatorio_id     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pago_id             BIGINT UNSIGNED NOT NULL,
    creado_por          BIGINT UNSIGNED NOT NULL,
    fecha_recordatorio  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    mensaje             VARCHAR(500) NOT NULL,
    estado              ENUM('PENDIENTE','ATENDIDO','DESCARTADO') NOT NULL DEFAULT 'PENDIENTE',
    atendido_en         DATETIME NULL,
    CONSTRAINT fk_recordatorio_pago
        FOREIGN KEY (pago_id) REFERENCES pagos(pago_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_recordatorio_usuario
        FOREIGN KEY (creado_por) REFERENCES usuarios(usuario_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX ix_recordatorio_pago_estado (pago_id, estado)
) ENGINE=InnoDB;

-- ============================================================
-- MÓDULO DE FACTURACIÓN
-- ============================================================

CREATE TABLE datos_facturacion (
    dato_facturacion_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id          BIGINT UNSIGNED NOT NULL,
    tipo_documento      ENUM('DNI','CE','RUC','OTRO') NOT NULL,
    numero_documento    VARCHAR(20) NOT NULL,
    nombres_razon_social VARCHAR(180) NOT NULL,
    direccion_facturacion VARCHAR(255) NOT NULL,
    correo_facturacion  VARCHAR(150) NULL,
    datos_adicionales_json JSON NULL COMMENT 'Campos adicionales aprobados por Lunnys Perú para facturación',
    estado              ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    creado_por          BIGINT UNSIGNED NOT NULL,
    creado_en           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_datos_fact_cliente
        FOREIGN KEY (cliente_id) REFERENCES clientes(cliente_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_datos_fact_usuario
        FOREIGN KEY (creado_por) REFERENCES usuarios(usuario_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX ix_datos_fact_cliente_estado (cliente_id, estado),
    INDEX ix_datos_fact_documento (tipo_documento, numero_documento)
) ENGINE=InnoDB;

CREATE TABLE formatos_facturacion (
    formato_facturacion_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo              VARCHAR(40) NOT NULL,
    nombre              VARCHAR(120) NOT NULL,
    version             VARCHAR(30) NOT NULL,
    descripcion         VARCHAR(500) NULL,
    plantilla_ruta      VARCHAR(500) NULL,
    estado              ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    aprobado_por        BIGINT UNSIGNED NOT NULL,
    aprobado_en         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    creado_en           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_formato_fact_codigo_version UNIQUE (codigo, version),
    CONSTRAINT fk_formato_fact_usuario
        FOREIGN KEY (aprobado_por) REFERENCES usuarios(usuario_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX ix_formato_fact_estado (estado)
) ENGINE=InnoDB;

CREATE TABLE documentos_facturacion (
    documento_facturacion_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo              VARCHAR(60) NOT NULL,
    tipo_documento      VARCHAR(60) NOT NULL COMMENT 'Tipo definido dentro del alcance aprobado por Lunnys Perú',
    formato_facturacion_id BIGINT UNSIGNED NOT NULL,
    dato_facturacion_id BIGINT UNSIGNED NOT NULL,
    cliente_id          BIGINT UNSIGNED NOT NULL,
    contrato_id         BIGINT UNSIGNED NOT NULL,
    pago_id             BIGINT UNSIGNED NOT NULL,
    fecha_emision       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    contenido_json      JSON NOT NULL COMMENT 'Datos utilizados para generar el documento según el formato aprobado',
    ruta_documento      VARCHAR(500) NOT NULL,
    nombre_archivo      VARCHAR(255) NULL,
    mime_type           VARCHAR(100) NULL,
    estado              ENUM('GENERADO','ANULADO') NOT NULL DEFAULT 'GENERADO',
    generado_por        BIGINT UNSIGNED NOT NULL,
    anulado_por         BIGINT UNSIGNED NULL,
    anulado_en          DATETIME NULL,
    motivo_anulacion    VARCHAR(500) NULL,
    creado_en           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_documento_fact_codigo UNIQUE (codigo),
    CONSTRAINT chk_documento_fact_anulacion CHECK (
        (estado = 'GENERADO' AND anulado_en IS NULL AND anulado_por IS NULL)
        OR
        (estado = 'ANULADO' AND anulado_en IS NOT NULL AND anulado_por IS NOT NULL
         AND motivo_anulacion IS NOT NULL AND CHAR_LENGTH(TRIM(motivo_anulacion)) > 0)
    ),
    CONSTRAINT fk_documento_fact_formato
        FOREIGN KEY (formato_facturacion_id) REFERENCES formatos_facturacion(formato_facturacion_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_documento_fact_dato
        FOREIGN KEY (dato_facturacion_id) REFERENCES datos_facturacion(dato_facturacion_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_documento_fact_cliente
        FOREIGN KEY (cliente_id) REFERENCES clientes(cliente_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_documento_fact_contrato
        FOREIGN KEY (contrato_id) REFERENCES contratos(contrato_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_documento_fact_pago
        FOREIGN KEY (pago_id) REFERENCES pagos(pago_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_documento_fact_generado_por
        FOREIGN KEY (generado_por) REFERENCES usuarios(usuario_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    -- Sin ON UPDATE CASCADE en anulado_por: participa en chk_documento_fact_anulacion
    -- y MySQL no permite esa combinación (mismo caso que en pedido_equipos/movimientos_inventario).
    CONSTRAINT fk_documento_fact_anulado_por
        FOREIGN KEY (anulado_por) REFERENCES usuarios(usuario_id)
        ON DELETE RESTRICT,
    INDEX ix_documento_fact_cliente (cliente_id, fecha_emision),
    INDEX ix_documento_fact_contrato (contrato_id, fecha_emision),
    INDEX ix_documento_fact_pago (pago_id),
    INDEX ix_documento_fact_estado (estado, fecha_emision),
    INDEX ix_documento_fact_formato (formato_facturacion_id)
) ENGINE=InnoDB;

CREATE TABLE historial_facturacion (
    historial_facturacion_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    documento_facturacion_id BIGINT UNSIGNED NOT NULL,
    accion              ENUM('GENERACION','ANULACION') NOT NULL,
    estado_anterior     ENUM('GENERADO','ANULADO') NULL,
    estado_nuevo        ENUM('GENERADO','ANULADO') NOT NULL,
    detalle             VARCHAR(500) NULL,
    realizado_por       BIGINT UNSIGNED NOT NULL,
    realizado_en        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hist_fact_documento
        FOREIGN KEY (documento_facturacion_id) REFERENCES documentos_facturacion(documento_facturacion_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_hist_fact_usuario
        FOREIGN KEY (realizado_por) REFERENCES usuarios(usuario_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX ix_hist_fact_documento_fecha (documento_facturacion_id, realizado_en),
    INDEX ix_hist_fact_accion_fecha (accion, realizado_en)
) ENGINE=InnoDB;

CREATE TABLE equipos (
    equipo_id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo              VARCHAR(60) NOT NULL,
    numero_serie        VARCHAR(100) NULL,
    nombre              VARCHAR(120) NOT NULL,
    tipo                VARCHAR(80) NOT NULL,
    marca               VARCHAR(80) NULL,
    modelo              VARCHAR(100) NULL,
    condicion           ENUM('NUEVO','BUENO','REGULAR','MALO','MALOGRADO') NOT NULL DEFAULT 'BUENO',
    estado              ENUM('DISPONIBLE','ASIGNADO','MANTENIMIENTO','INACTIVO') NOT NULL DEFAULT 'DISPONIBLE',
    observaciones       VARCHAR(500) NULL,
    creado_por          BIGINT UNSIGNED NOT NULL,
    creado_en           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_equipo_codigo UNIQUE (codigo),
    CONSTRAINT uq_equipo_serie UNIQUE (numero_serie),
    CONSTRAINT fk_equipo_creado_por
        FOREIGN KEY (creado_por) REFERENCES usuarios(usuario_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX ix_equipo_nombre (nombre),
    INDEX ix_equipo_estado (estado)
) ENGINE=InnoDB;

CREATE TABLE materiales (
    material_id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo              VARCHAR(60) NOT NULL,
    nombre              VARCHAR(120) NOT NULL,
    descripcion         VARCHAR(500) NULL,
    unidad_base         VARCHAR(30) NOT NULL,
    unidad_empaque      VARCHAR(30) NULL,
    factor_conversion   DECIMAL(10,3) NULL,
    stock_actual        DECIMAL(12,3) NOT NULL DEFAULT 0,
    stock_minimo        DECIMAL(12,3) NOT NULL DEFAULT 0,
    estado              ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    creado_por          BIGINT UNSIGNED NOT NULL,
    creado_en           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_material_codigo UNIQUE (codigo),
    CONSTRAINT chk_material_stock CHECK (stock_actual >= 0),
    CONSTRAINT chk_material_minimo CHECK (stock_minimo >= 0),
    CONSTRAINT fk_material_creado_por
        FOREIGN KEY (creado_por) REFERENCES usuarios(usuario_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX ix_material_nombre (nombre),
    INDEX ix_material_estado (estado)
) ENGINE=InnoDB;

CREATE TABLE pedidos_instalacion (
    pedido_id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo              VARCHAR(40) NOT NULL,
    contrato_id         BIGINT UNSIGNED NOT NULL,
    cliente_id          BIGINT UNSIGNED NOT NULL,
    tecnico_id          BIGINT UNSIGNED NULL,
    fecha_solicitud     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_programada    DATETIME NULL,
    fecha_original      DATETIME NULL,
    estado              ENUM('PENDIENTE','PROGRAMADO','REPROGRAMADO','EN_PROCESO','COMPLETADO','CANCELADO') NOT NULL DEFAULT 'PENDIENTE',
    motivo_reprogramacion VARCHAR(500) NULL,
    observaciones       VARCHAR(500) NULL,
    creado_por          BIGINT UNSIGNED NOT NULL,
    actualizado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_pedido_codigo UNIQUE (codigo),
    CONSTRAINT fk_pedido_contrato
        FOREIGN KEY (contrato_id) REFERENCES contratos(contrato_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_pedido_cliente
        FOREIGN KEY (cliente_id) REFERENCES clientes(cliente_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_pedido_tecnico
        FOREIGN KEY (tecnico_id) REFERENCES empleados(empleado_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_pedido_creado_por
        FOREIGN KEY (creado_por) REFERENCES usuarios(usuario_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX ix_pedido_tecnico_estado (tecnico_id, estado),
    INDEX ix_pedido_fecha (fecha_programada),
    INDEX ix_pedido_contrato (contrato_id)
) ENGINE=InnoDB;

CREATE TABLE pedido_equipos (
    pedido_equipo_id    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pedido_id           BIGINT UNSIGNED NOT NULL,
    equipo_id           BIGINT UNSIGNED NOT NULL,
    asignado_por        BIGINT UNSIGNED NOT NULL,
    fecha_asignacion    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_devolucion    DATETIME NULL,
    condicion_devolucion ENUM('NUEVO','BUENO','REGULAR','MALO','MALOGRADO') NULL,
    estado_asignacion   ENUM('ASIGNADO','UTILIZADO','DEVUELTO','CANCELADO') NOT NULL DEFAULT 'ASIGNADO',
    equipo_asignacion_activa BIGINT UNSIGNED GENERATED ALWAYS AS (
        CASE WHEN estado_asignacion IN ('ASIGNADO','UTILIZADO') AND fecha_devolucion IS NULL
             THEN equipo_id ELSE NULL END
    ) STORED,
    CONSTRAINT uq_equipo_asignacion_activa UNIQUE (equipo_asignacion_activa),
    CONSTRAINT fk_pedido_equipo_pedido
        FOREIGN KEY (pedido_id) REFERENCES pedidos_instalacion(pedido_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_pedido_equipo_equipo
        FOREIGN KEY (equipo_id) REFERENCES equipos(equipo_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_pedido_equipo_usuario
        FOREIGN KEY (asignado_por) REFERENCES usuarios(usuario_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX ix_pedido_equipo_pedido (pedido_id),
    INDEX ix_pedido_equipo_equipo (equipo_id)
) ENGINE=InnoDB;

CREATE TABLE pedido_materiales (
    pedido_material_id  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pedido_id           BIGINT UNSIGNED NOT NULL,
    material_id         BIGINT UNSIGNED NOT NULL,
    cantidad_asignada   DECIMAL(12,3) NOT NULL,
    cantidad_utilizada  DECIMAL(12,3) NOT NULL DEFAULT 0,
    cantidad_devuelta   DECIMAL(12,3) NOT NULL DEFAULT 0,
    asignado_por        BIGINT UNSIGNED NOT NULL,
    asignado_en         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_pedido_material UNIQUE (pedido_id, material_id),
    CONSTRAINT chk_pm_asignada CHECK (cantidad_asignada > 0),
    CONSTRAINT chk_pm_utilizada CHECK (cantidad_utilizada >= 0 AND cantidad_utilizada <= cantidad_asignada),
    CONSTRAINT chk_pm_devuelta CHECK (cantidad_devuelta >= 0 AND cantidad_devuelta <= cantidad_asignada),
    CONSTRAINT chk_pm_total CHECK (cantidad_utilizada + cantidad_devuelta <= cantidad_asignada),
    CONSTRAINT fk_pm_pedido
        FOREIGN KEY (pedido_id) REFERENCES pedidos_instalacion(pedido_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_pm_material
        FOREIGN KEY (material_id) REFERENCES materiales(material_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_pm_usuario
        FOREIGN KEY (asignado_por) REFERENCES usuarios(usuario_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE ejecuciones_instalacion (
    ejecucion_id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pedido_id           BIGINT UNSIGNED NOT NULL,
    tecnico_id          BIGINT UNSIGNED NOT NULL,
    inicio_en           DATETIME NULL,
    fin_en              DATETIME NULL,
    estado              ENUM('PENDIENTE','EN_PROCESO','COMPLETADA') NOT NULL DEFAULT 'PENDIENTE',
    observacion_tecnica TEXT NULL,
    creado_en           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_ejecucion_pedido UNIQUE (pedido_id),
    CONSTRAINT chk_ejecucion_fechas CHECK (fin_en IS NULL OR inicio_en IS NULL OR fin_en >= inicio_en),
    CONSTRAINT fk_ejecucion_pedido
        FOREIGN KEY (pedido_id) REFERENCES pedidos_instalacion(pedido_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_ejecucion_tecnico
        FOREIGN KEY (tecnico_id) REFERENCES empleados(empleado_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX ix_ejecucion_tecnico (tecnico_id, estado)
) ENGINE=InnoDB;

CREATE TABLE evidencias_instalacion (
    evidencia_id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pedido_id           BIGINT UNSIGNED NOT NULL,
    ejecucion_id        BIGINT UNSIGNED NULL,
    tipo                ENUM('FOTOGRAFIA_INSTALACION','OTRA') NOT NULL DEFAULT 'FOTOGRAFIA_INSTALACION',
    ruta_archivo        VARCHAR(500) NOT NULL,
    nombre_archivo      VARCHAR(255) NULL,
    mime_type           VARCHAR(100) NULL,
    descripcion         VARCHAR(500) NULL,
    subido_por          BIGINT UNSIGNED NOT NULL,
    subido_en           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_evidencia_pedido
        FOREIGN KEY (pedido_id) REFERENCES pedidos_instalacion(pedido_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_evidencia_ejecucion
        FOREIGN KEY (ejecucion_id) REFERENCES ejecuciones_instalacion(ejecucion_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_evidencia_usuario
        FOREIGN KEY (subido_por) REFERENCES usuarios(usuario_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX ix_evidencia_pedido (pedido_id)
) ENGINE=InnoDB;

CREATE TABLE movimientos_inventario (
    movimiento_id       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo_movimiento     ENUM('ENTRADA','SALIDA','ASIGNACION','CONSUMO','DEVOLUCION','AJUSTE') NOT NULL,
    equipo_id           BIGINT UNSIGNED NULL,
    material_id         BIGINT UNSIGNED NULL,
    pedido_id           BIGINT UNSIGNED NULL,
    cantidad            DECIMAL(12,3) NOT NULL DEFAULT 1,
    cantidad_anterior   DECIMAL(12,3) NULL,
    cantidad_posterior  DECIMAL(12,3) NULL,
    justificacion       VARCHAR(500) NULL,
    responsable_id      BIGINT UNSIGNED NOT NULL,
    fecha_movimiento    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    observacion         VARCHAR(500) NULL,
    CONSTRAINT chk_mov_item CHECK (
        (equipo_id IS NOT NULL AND material_id IS NULL)
        OR (equipo_id IS NULL AND material_id IS NOT NULL)
    ),
    CONSTRAINT chk_mov_cantidad CHECK (
        (tipo_movimiento = 'AJUSTE' AND cantidad <> 0)
        OR (tipo_movimiento <> 'AJUSTE' AND cantidad > 0)
    ),
    CONSTRAINT chk_mov_ajuste_justificado CHECK (
        tipo_movimiento <> 'AJUSTE' OR (justificacion IS NOT NULL AND CHAR_LENGTH(TRIM(justificacion)) > 0)
    ),
    CONSTRAINT fk_mov_equipo
        FOREIGN KEY (equipo_id) REFERENCES equipos(equipo_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_mov_material
        FOREIGN KEY (material_id) REFERENCES materiales(material_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_mov_pedido
        FOREIGN KEY (pedido_id) REFERENCES pedidos_instalacion(pedido_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_mov_responsable
        FOREIGN KEY (responsable_id) REFERENCES usuarios(usuario_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX ix_mov_material_fecha (material_id, fecha_movimiento),
    INDEX ix_mov_equipo_fecha (equipo_id, fecha_movimiento),
    INDEX ix_mov_pedido (pedido_id),
    INDEX ix_mov_tipo_fecha (tipo_movimiento, fecha_movimiento)
) ENGINE=InnoDB;

CREATE TABLE alertas_inventario (
    alerta_id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    material_id         BIGINT UNSIGNED NOT NULL,
    tipo                ENUM('STOCK_MINIMO','SIN_STOCK') NOT NULL,
    mensaje             VARCHAR(500) NOT NULL,
    generada_en         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atendida_en         DATETIME NULL,
    atendida_por        BIGINT UNSIGNED NULL,
    estado              ENUM('PENDIENTE','ATENDIDA') NOT NULL DEFAULT 'PENDIENTE',
    CONSTRAINT fk_alerta_material
        FOREIGN KEY (material_id) REFERENCES materiales(material_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_alerta_usuario
        FOREIGN KEY (atendida_por) REFERENCES usuarios(usuario_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX ix_alerta_material_estado (material_id, estado)
) ENGINE=InnoDB;

CREATE TABLE reportes_generados (
    reporte_id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo_reporte        VARCHAR(80) NOT NULL,
    formato             ENUM('PDF','EXCEL') NOT NULL,
    filtros_json        JSON NULL,
    ruta_archivo        VARCHAR(500) NOT NULL,
    generado_por        BIGINT UNSIGNED NOT NULL,
    generado_en         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reporte_usuario
        FOREIGN KEY (generado_por) REFERENCES usuarios(usuario_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX ix_reporte_tipo_fecha (tipo_reporte, generado_en)
) ENGINE=InnoDB;

CREATE TABLE conversaciones_ia (
    conversacion_id     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id          BIGINT UNSIGNED NOT NULL,
    titulo              VARCHAR(200) NULL,
    estado              ENUM('ACTIVA','CERRADA') NOT NULL DEFAULT 'ACTIVA',
    creada_en           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cerrada_en          DATETIME NULL,
    CONSTRAINT fk_conversacion_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(usuario_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX ix_conversacion_usuario (usuario_id, creada_en)
) ENGINE=InnoDB;

CREATE TABLE mensajes_ia (
    mensaje_id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversacion_id     BIGINT UNSIGNED NOT NULL,
    rol_mensaje         ENUM('USUARIO','ASISTENTE') NOT NULL,
    contenido           LONGTEXT NOT NULL,
    respuesta_suficiente BOOLEAN NULL,
    creado_en           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mensaje_conversacion
        FOREIGN KEY (conversacion_id) REFERENCES conversaciones_ia(conversacion_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX ix_mensaje_conversacion_fecha (conversacion_id, creado_en)
) ENGINE=InnoDB;

CREATE TABLE fuentes_respuesta_ia (
    fuente_id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mensaje_id          BIGINT UNSIGNED NOT NULL,
    tipo_fuente         ENUM('CLIENTES','CONTRATOS','VENTAS','PAGOS','FACTURACION','EQUIPOS','MATERIALES','KARDEX','INSTALACIONES','INDICADORES','BI','OTRA') NOT NULL,
    referencia_interna  VARCHAR(255) NOT NULL,
    descripcion         VARCHAR(500) NULL,
    CONSTRAINT fk_fuente_mensaje
        FOREIGN KEY (mensaje_id) REFERENCES mensajes_ia(mensaje_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX ix_fuente_mensaje (mensaje_id)
) ENGINE=InnoDB;

CREATE TABLE auditoria_cambios (
    auditoria_id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id          BIGINT UNSIGNED NULL,
    tabla_afectada      VARCHAR(80) NOT NULL,
    registro_id         VARCHAR(80) NOT NULL,
    accion              ENUM('INSERT','UPDATE','INHABILITAR','ANULAR','CAMBIO_ESTADO') NOT NULL,
    valores_anteriores  JSON NULL,
    valores_nuevos      JSON NULL,
    fecha_hora          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_auditoria_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(usuario_id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX ix_auditoria_tabla_registro (tabla_afectada, registro_id),
    INDEX ix_auditoria_fecha (fecha_hora)
) ENGINE=InnoDB;