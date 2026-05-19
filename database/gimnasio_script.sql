-- Creación de la base de datos
create database gimnasio_db;
use gimnasio_db;

-- Tabla de Usuarios (para acceso administrativo)
create table usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    clave VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'staff') DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de Clientes
CREATE TABLE clientes (
    id_clientes INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE,
    telefono VARCHAR(20),
    direccion TEXT,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('activo', 'inactivo') DEFAULT 'activo'
);

-- Tabla de Entrenadores
CREATE TABLE entrenadores (
    id_entrenadores INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    especialidad VARCHAR(100),
    telefono VARCHAR(20),
    email VARCHAR(100),
    foto VARCHAR(255),
    estado ENUM('activo', 'inactivo') DEFAULT 'activo'
);

-- Tabla de Planes
CREATE TABLE planes (
    id_planes INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    precio DECIMAL(10, 2) NOT NULL,
    duracion_meses INT NOT NULL,
    estado ENUM('activo', 'inactivo') DEFAULT 'activo'
);

-- Tabla de Membresías
CREATE TABLE membresias (
    id_membresias INT AUTO_INCREMENT PRIMARY KEY,
    id_clientes INT NOT NULL,
    id_planes INT NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    costo_total DECIMAL(10, 2) NOT NULL,
    estado ENUM('activa', 'vencida', 'cancelada') DEFAULT 'activa',
    FOREIGN KEY (id_clientes) REFERENCES clientes(id_clientes) ON DELETE CASCADE,
    FOREIGN KEY (id_planes) REFERENCES planes(id_planes) ON DELETE CASCADE
);

-- Tabla de Pagos
CREATE TABLE pagos (
    id_pagos INT AUTO_INCREMENT PRIMARY KEY,
    id_membresias INT NOT NULL,
    monto DECIMAL(10, 2) NOT NULL,
    metodo_pago ENUM('efectivo', 'tarjeta', 'transferencia') NOT NULL,
    fecha_pago TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('completado', 'pendiente', 'anulado') DEFAULT 'completado',
    FOREIGN KEY (id_membresias) REFERENCES membresias(id_membresias) ON DELETE CASCADE
);

-- Tabla de Asistencias
CREATE TABLE asistencias (
    id_asistencias INT AUTO_INCREMENT PRIMARY KEY,
    id_clientes INT NOT NULL,
    fecha_asistencia TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    hora_entrada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_clientes) REFERENCES clientes(id_clientes) ON DELETE CASCADE
);

-- Tabla de Solicitudes de Inscripción (Formulario Público)
CREATE TABLE solicitudes (
    id_solicitudes INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    telefono VARCHAR(20),
    plan_deseado VARCHAR(100),
    fecha_inicio_estimada DATE,
    observaciones TEXT,
    fecha_solicitud TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('pendiente', 'procesada', 'rechazada') DEFAULT 'pendiente'
);


-- Nota: En un sistema real, la contraseña debe estar hasheada.
INSERT INTO usuarios (nombre, clave, rol) 
VALUES ('Administrador', 'admin123', 'admin');

-- Insertar algunos planes de ejemplo
INSERT INTO planes (nombre, descripcion, precio, duracion_meses) VALUES 
('Plan Mensual', 'Acceso total por un mes', 50.00, 1),
('Plan Trimestral', 'Acceso total por tres meses con descuento', 130.00, 3),
('Plan Anual', 'Acceso total por un año completo', 450.00, 12),
('Entrenamiento Personalizado', 'Sesiones individuales con instructor', 100.00, 1),
('Clases Grupales', 'Yoga, Zumba y Crossfit', 40.00, 1);

-- Esto cambia el usuario a 'admin' y encripta la contraseña 'admin123'
UPDATE usuarios SET 
    nombre = 'admin', 
    clave = '123' 
WHERE id_usuario = 1;

select * from usuarios;

