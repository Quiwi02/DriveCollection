-- ============================================================
--  DRIVE COLLECTION — Sistema de Concesionaria de Vehículos
--  Script de Base de Datos
--  Fecha: 2026-05-18
-- ============================================================

CREATE DATABASE drive_collection CHARACTER SET utf8mb4COLLATE utf8mb4_unicode_ci;

USE drive_collection;

-- ------------------------------------------------------------
-- 1. USUARIOS (Panel Administrativo)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nombre      VARCHAR(100)    NOT NULL,
    email       VARCHAR(150)    NOT NULL UNIQUE,
    password    VARCHAR(255)    NOT NULL,          -- bcrypt hash
    rol         ENUM('admin','asesor') NOT NULL DEFAULT 'asesor',
    foto        VARCHAR(255)    NULL,
    activo      TINYINT(1)      NOT NULL DEFAULT 1,
    creado_en   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 2. CLIENTES
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clientes (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nombre          VARCHAR(100)    NOT NULL,
    apellido        VARCHAR(100)    NOT NULL,
    documento       VARCHAR(30)     NOT NULL UNIQUE,   -- anti-duplicidad
    tipo_doc        ENUM('CC','CE','NIT','Pasaporte') NOT NULL DEFAULT 'CC',
    email           VARCHAR(150)    NOT NULL,
    telefono        VARCHAR(20)     NULL,
    direccion       VARCHAR(255)    NULL,
    creado_en       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_documento (documento)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 3. VEHÍCULOS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS vehiculos (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    marca           VARCHAR(60)     NOT NULL,
    modelo          VARCHAR(80)     NOT NULL,
    anio            YEAR            NOT NULL,
    precio_lista    DECIMAL(14,2)   NOT NULL,
    color           VARCHAR(40)     NULL,
    kilometraje     INT UNSIGNED    NOT NULL DEFAULT 0,
    transmision     ENUM('Manual','Automática','CVT') NOT NULL DEFAULT 'Automática',
    combustible     ENUM('Gasolina','Diésel','Híbrido','Eléctrico') NOT NULL DEFAULT 'Gasolina',
    descripcion     TEXT            NULL,
    estado          ENUM('disponible','reservado','vendido') NOT NULL DEFAULT 'disponible',
    creado_en       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_estado (estado)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 4. IMÁGENES DE VEHÍCULO
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS imagenes_vehiculo (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    vehiculo_id     INT UNSIGNED    NOT NULL,
    ruta            VARCHAR(255)    NOT NULL,          -- path relativo en servidor
    es_principal    TINYINT(1)      NOT NULL DEFAULT 0,
    orden           TINYINT UNSIGNED NOT NULL DEFAULT 0,
    creado_en       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_img_vehiculo
        FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_vehiculo_principal (vehiculo_id, es_principal)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 5. RESERVAS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reservas (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    cliente_id      INT UNSIGNED    NOT NULL,
    vehiculo_id     INT UNSIGNED    NOT NULL,
    asesor_id       INT UNSIGNED    NULL,             -- usuario asesor asignado
    fecha_reserva   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_limite    DATETIME        NOT NULL,          -- +48 h generado automáticamente
    estado          ENUM('activa','expirada','convertida','cancelada')
                                    NOT NULL DEFAULT 'activa',
    observaciones   TEXT            NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_res_cliente
        FOREIGN KEY (cliente_id) REFERENCES clientes(id)
        ON UPDATE CASCADE,
    CONSTRAINT fk_res_vehiculo
        FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id)
        ON UPDATE CASCADE,
    CONSTRAINT fk_res_asesor
        FOREIGN KEY (asesor_id) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_estado_limite (estado, fecha_limite)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 6. VENTAS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ventas (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    cliente_id      INT UNSIGNED    NOT NULL,
    vehiculo_id     INT UNSIGNED    NOT NULL,
    asesor_id       INT UNSIGNED    NULL,
    reserva_id      INT UNSIGNED    NULL,              -- si provino de una reserva
    solicitud_id    INT UNSIGNED    NULL,              -- si provino de cotización web
    monto           DECIMAL(14,2)   NOT NULL,          -- vinculado al precio_lista
    metodo_pago     ENUM('Contado','Crédito','Leasing','Permuta') NOT NULL,
    fecha_venta     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    observaciones   TEXT            NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_vta_cliente
        FOREIGN KEY (cliente_id) REFERENCES clientes(id)
        ON UPDATE CASCADE,
    CONSTRAINT fk_vta_vehiculo
        FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id)
        ON UPDATE CASCADE,
    CONSTRAINT fk_vta_asesor
        FOREIGN KEY (asesor_id) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_vta_reserva
        FOREIGN KEY (reserva_id) REFERENCES reservas(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_fecha_venta (fecha_venta)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 7. ASISTENCIAS (control de asesores)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS asistencias (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    usuario_id      INT UNSIGNED    NOT NULL,
    fecha           DATE            NOT NULL,
    hora_entrada    TIME            NULL,
    hora_salida     TIME            NULL,
    estado          ENUM('presente','ausente','tardanza','permiso')
                                    NOT NULL DEFAULT 'presente',
    observaciones   VARCHAR(255)    NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_asi_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY uq_asistencia_dia (usuario_id, fecha)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 8. SOLICITUDES (cotizaciones web — leads públicos)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS solicitudes (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    vehiculo_id         INT UNSIGNED    NOT NULL,
    nombre              VARCHAR(100)    NOT NULL,
    correo              VARCHAR(150)    NOT NULL,
    telefono            VARCHAR(20)     NULL,
    metodo_adquisicion  ENUM('Contado','Crédito','Leasing','Permuta','No definido')
                                        NOT NULL DEFAULT 'No definido',
    observaciones       TEXT            NULL,
    estado              ENUM('pendiente','en_proceso','convertida','archivada')
                                        NOT NULL DEFAULT 'pendiente',
    venta_id            INT UNSIGNED    NULL,         -- se llena al convertir a venta
    creado_en           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_sol_vehiculo
        FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id)
        ON UPDATE CASCADE,
    CONSTRAINT fk_sol_venta
        FOREIGN KEY (venta_id) REFERENCES ventas(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_estado_sol (estado)
) ENGINE=InnoDB;

-- ============================================================
-- DATOS DE EJEMPLO (seed)
-- ============================================================

-- Admin por defecto  (contraseña: Admin123!)
INSERT INTO usuarios (nombre, email, password, rol) VALUES
('Administrador', 'admin@drivecollection.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Asesores de ejemplo
INSERT INTO usuarios (nombre, email, password, rol) VALUES
('Carlos Mendoza',  'carlos@drivecollection.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'asesor'),
('Laura Gómez',     'laura@drivecollection.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'asesor');

-- Clientes de ejemplo
INSERT INTO clientes (nombre, apellido, documento, tipo_doc, email, telefono) VALUES
('Juan',    'Pérez',    '1012345678', 'CC', 'juan.perez@email.com',   '3001234567'),
('María',   'López',    '1098765432', 'CC', 'maria.lopez@email.com',  '3109876543'),
('Empresa', 'ABC S.A.', '900123456',  'NIT','contacto@abc.com',       '6011234567');

-- Vehículos de ejemplo
INSERT INTO vehiculos (marca, modelo, anio, precio_lista, color, kilometraje, transmision, combustible, descripcion, estado) VALUES
('Toyota',   'Corolla Cross',  2024, 98500000,  'Blanco Perla',  0,     'CVT',        'Híbrido',  'SUV híbrida, techo panorámico, 7 airbags.', 'disponible'),
('Mazda',    'CX-5',           2024, 115000000, 'Rojo Soul',     0,     'Automática', 'Gasolina', 'Turbo 2.5L, i-Activ AWD, pantalla 10.25".', 'disponible'),
('Chevrolet','Tracker Premier', 2023, 89900000, 'Gris Grafito',  5000,  'Automática', 'Gasolina', '1.2T, Apple CarPlay, cámara 360°.',         'disponible'),
('Renault',  'Duster',         2024, 76500000,  'Azul Cosmos',   0,     'Manual',     'Gasolina', '1.3T 4x4, pantalla 9.3", sensor parking.',  'disponible'),
('BMW',      '320i',           2023, 195000000, 'Negro Zafiro',  12000, 'Automática', 'Gasolina', 'Sport Line, control de crucero adaptativo.', 'reservado');

-- Imágenes principales de ejemplo
INSERT INTO imagenes_vehiculo (vehiculo_id, ruta, es_principal, orden) VALUES
(1, 'uploads/vehiculos/corolla-cross-1.jpg',   1, 1),
(2, 'uploads/vehiculos/mazda-cx5-1.jpg',        1, 1),
(3, 'uploads/vehiculos/tracker-1.jpg',          1, 1),
(4, 'uploads/vehiculos/duster-1.jpg',           1, 1),
(5, 'uploads/vehiculos/bmw-320i-1.jpg',         1, 1);

-- Solicitud web de ejemplo
INSERT INTO solicitudes (vehiculo_id, nombre, correo, telefono, metodo_adquisicion, observaciones) VALUES
(1, 'Pedro Ramírez', 'pedro@correo.com', '3151234567', 'Crédito',
 'Me interesa conocer los plazos de financiación disponibles.');
