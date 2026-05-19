-- Creación de la base de datos
create database drivecollection_db;
use drivecollection_db;

-- Tabla de Usuarios (para acceso administrativo)
CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(150) NOT NULL UNIQUE,
    clave VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'asesor') DEFAULT 'asesor',
    foto VARCHAR(255) DEFAULT NULL,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de Clientes
CREATE TABLE clientes (
    id_clientes INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    documento VARCHAR(30) NOT NULL UNIQUE,
    tipo_doc ENUM('DNI', 'CE', 'Pasaporte') DEFAULT 'DNI',
    correo VARCHAR(150) NOT NULL UNIQUE,
    telefono VARCHAR(20) DEFAULT NULL,
    direccion TEXT DEFAULT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de Vehículos
CREATE TABLE vehiculos (
    id_vehiculos INT AUTO_INCREMENT PRIMARY KEY,
    marca VARCHAR(60) NOT NULL,
    modelo VARCHAR(80) NOT NULL,
    anio YEAR NOT NULL,
    precio_lista DECIMAL(14,2) NOT NULL,
    color VARCHAR(40) DEFAULT NULL,
    kilometraje INT DEFAULT 0,
    transmision ENUM('Manual', 'Automática', 'CVT') DEFAULT 'Automática',
    combustible ENUM('Gasolina', 'Diésel', 'Híbrido', 'Eléctrico') DEFAULT 'Gasolina',
    descripcion TEXT DEFAULT NULL,
    estado ENUM('disponible', 'reservado', 'vendido') DEFAULT 'disponible',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de Imágenes de Vehículos
CREATE TABLE imagenes_vehiculo (
    id_imagenes_vehiculo INT AUTO_INCREMENT PRIMARY KEY,
    id_vehiculos INT NOT NULL,
    ruta VARCHAR(255) NOT NULL,
    es_principal TINYINT(1) DEFAULT 0,
    orden INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_vehiculos) REFERENCES vehiculos(id_vehiculos) ON DELETE CASCADE
);

-- Tabla de Reservas
CREATE TABLE reservas (
    id_reservas INT AUTO_INCREMENT PRIMARY KEY,
    id_clientes INT NOT NULL,
    id_vehiculos INT NOT NULL,
    id_usuario INT DEFAULT NULL,
    fecha_reserva TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_limite TIMESTAMP NOT NULL,
    estado ENUM('activa', 'expirada', 'convertida', 'cancelada') DEFAULT 'activa',
    observaciones TEXT DEFAULT NULL,
    FOREIGN KEY (id_clientes) REFERENCES clientes(id_clientes) ON DELETE CASCADE,
    FOREIGN KEY (id_vehiculos) REFERENCES vehiculos(id_vehiculos) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE SET NULL
);

-- Tabla de Ventas
CREATE TABLE ventas (
    id_ventas INT AUTO_INCREMENT PRIMARY KEY,
    id_clientes INT NOT NULL,
    id_vehiculos INT NOT NULL,
    id_usuario INT DEFAULT NULL,
    id_reservas INT DEFAULT NULL,
    id_solicitudes INT DEFAULT NULL,
    monto DECIMAL(14,2) NOT NULL,
    metodo_pago ENUM('Contado', 'Crédito', 'Leasing', 'Permuta') NOT NULL,
    fecha_venta TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    observaciones TEXT DEFAULT NULL,
    FOREIGN KEY (id_clientes) REFERENCES clientes(id_clientes) ON DELETE CASCADE,
    FOREIGN KEY (id_vehiculos) REFERENCES vehiculos(id_vehiculos) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
    FOREIGN KEY (id_reservas) REFERENCES reservas(id_reservas) ON DELETE SET NULL
);

-- Tabla de Asistencias
CREATE TABLE asistencias (
    id_asistencias INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    fecha DATE NOT NULL,
    hora_entrada TIME DEFAULT NULL,
    hora_salida TIME DEFAULT NULL,
    estado ENUM('presente', 'ausente', 'tardanza', 'permiso') DEFAULT 'presente',
    observaciones VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
);

-- Tabla de Solicitudes (Formulario Público)
CREATE TABLE solicitudes (
    id_solicitudes INT AUTO_INCREMENT PRIMARY KEY,
    id_vehiculos INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(150) NOT NULL,
    telefono VARCHAR(20) DEFAULT NULL,
    metodo_adquisicion ENUM('Contado', 'Crédito', 'Leasing', 'Permuta', 'No definido') DEFAULT 'No definido',
    observaciones TEXT DEFAULT NULL,
    estado ENUM('pendiente', 'en_proceso', 'convertida', 'archivada') DEFAULT 'pendiente',
    id_ventas INT DEFAULT NULL,
    fecha_solicitud TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_vehiculos) REFERENCES vehiculos(id_vehiculos) ON DELETE CASCADE,
    FOREIGN KEY (id_ventas) REFERENCES ventas(id_ventas) ON DELETE SET NULL
);


-- Nota: En un sistema real, la contraseña debe estar hasheada.
INSERT INTO usuarios (nombre, correo, clave, rol) 
VALUES ('Administrador', 'admin@drivecollection.com', 'admin123', 'admin');

-- Insertar algunos vehículos de ejemplo
INSERT INTO vehiculos (marca, modelo, anio, precio_lista, color, kilometraje, transmision, combustible, descripcion, estado) VALUES
('Toyota',   'Corolla Cross',  2024, 98500000.00,  'Blanco Perla',  0,     'CVT',        'Híbrido',  'SUV híbrida, techo panorámico, 7 airbags.', 'disponible'),
('Mazda',    'CX-5',           2024, 115000000.00, 'Rojo Soul',     0,     'Automática', 'Gasolina', 'Turbo 2.5L, i-Activ AWD, pantalla 10.25".', 'disponible'),
('BMW',      '320i',           2023, 195000000.00, 'Negro Zafiro',  12000, 'Automática', 'Gasolina', 'Sport Line, control de crucero adaptativo.', 'reservado');

-- Esto cambia el usuario a 'admin' y encripta la contraseña 'admin123'
UPDATE usuarios SET 
    nombre = 'admin', 
    clave = '123' 
WHERE id_usuario = 1;

select * from usuarios;
