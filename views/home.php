<?php
session_start();
require_once __DIR__ . '/../config/database.php';
$db = getDB();

// Obtener vehículos disponibles
$stmt = $db->query("
    SELECT v.id_vehiculos, v.marca, v.modelo, v.anio,
           v.precio_lista, v.transmision, v.combustible, v.kilometraje,
           img.ruta AS imagen_principal
    FROM vehiculos v
    LEFT JOIN imagenes_vehiculo img
           ON img.id_vehiculos = v.id_vehiculos AND img.es_principal = 1
    WHERE v.estado = 'disponible'
    ORDER BY v.id_vehiculos DESC
");
$vehiculos = $stmt->fetchAll();

// Obtener asesores activos
$stmtAsesores = $db->query("
    SELECT id_usuario, nombre, foto
    FROM usuarios
    WHERE rol = 'asesor' AND activo = 1
    ORDER BY nombre ASC
");
$asesores = $stmtAsesores->fetchAll();

// Procesar formulario de cotización (POST)
$mensaje = '';
$msgTipo = '';
$id_vehiculo_enviado = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_cotizacion'])) {
    $id_vehiculos       = (int) ($_POST['id_vehiculos'] ?? 0);
    $nombre             = trim($_POST['nombre']             ?? '');
    $correo             = trim($_POST['correo']             ?? '');
    $telefono           = trim($_POST['telefono']           ?? '');
    $metodo_adquisicion = trim($_POST['metodo_adquisicion'] ?? 'No definido');
    $observaciones      = trim($_POST['observaciones']      ?? '');

    if (empty($nombre) || empty($correo) || $id_vehiculos <= 0) {
        $mensaje = 'Por favor completa tu nombre, correo y selecciona un vehículo.';
        $msgTipo = 'error';
    } else {
        $stmtIns = $db->prepare("
            INSERT INTO solicitudes (id_vehiculos, nombre, correo, telefono, metodo_adquisicion, observaciones)
            VALUES (:id_vehiculos, :nombre, :correo, :telefono, :metodo_adquisicion, :observaciones)
        ");
        $success = $stmtIns->execute([
            ':id_vehiculos'       => $id_vehiculos,
            ':nombre'             => $nombre,
            ':correo'             => $correo,
            ':telefono'           => $telefono,
            ':metodo_adquisicion' => $metodo_adquisicion,
            ':observaciones'      => $observaciones,
        ]);

        if ($success) {
            $_SESSION['cotizacion_enviada'] = true;
            $_SESSION['id_vehiculo_enviado'] = $id_vehiculos;
            header("Location: home.php");
            exit();
        } else {
            $mensaje = 'Ocurrió un error al enviar. Intenta de nuevo.';
            $msgTipo = 'error';
        }
    }
}

// Consumir mensaje flash en GET si existe
if (isset($_SESSION['cotizacion_enviada'])) {
    $mensaje = '¡Solicitud enviada! Nuestro equipo se pondrá en contacto contigo pronto.';
    $msgTipo = 'success';
    $id_vehiculo_enviado = (int)($_SESSION['id_vehiculo_enviado'] ?? 0);
    unset($_SESSION['cotizacion_enviada']);
    unset($_SESSION['id_vehiculo_enviado']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Drive Collection — Concesionaria de Vehículos</title>
    <meta name="description" content="Drive Collection, tu concesionaria de confianza. Explora nuestro catálogo de vehículos disponibles y solicita una cotización en línea.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/EntreVentaCarros/assetes/css/home.css">
</head>
<body>

<!-- ════════════════════════════════
     NAVBAR
════════════════════════════════ -->
<nav class="navbar" id="navbar">
    <a href="#" class="nav-brand">
        <div class="nav-logo">
            <svg viewBox="0 0 24 24"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.85 7h10.29l1.08 3.11H5.77L6.85 7zM19 17H5v-5h14v5z"/></svg>
        </div>
        <span>Drive Collection</span>
    </a>

    <ul class="nav-links">
        <li><a href="#beneficios">Beneficios</a></li>
        <li><a href="#horarios">Horarios</a></li>
        <li><a href="#asesores">Asesores</a></li>
        <li><a href="#vehiculos">Vehículos</a></li>
        <li><a href="../views/login.php" class="btn-admin">Admin</a></li>
    </ul>
</nav>

<!-- ════════════════════════════════
     HERO
════════════════════════════════ -->
<section class="hero">
    <div class="hero-bg"></div>
    <div class="hero-grid-overlay"></div>

    <div class="hero-content">
        <div class="hero-tag">
            <span></span>
            Tu próximo vehículo te espera
        </div>

        <h1>
            Conduce el auto<br>
            de tus <em>sueños</em>
        </h1>

        <p>
            En Drive Collection encontrarás los mejores vehículos con garantía,
            financiación a tu medida y el acompañamiento de nuestros asesores expertos.
        </p>

        <div class="hero-actions">
            <a href="#vehiculos" class="btn-primary">Ver Catálogo</a>
            <a href="#horarios" class="btn-outline">Visitarnos</a>
        </div>

        <div class="hero-stats">
            <div>
                <div class="hero-stat-value">+200</div>
                <div class="hero-stat-label">Vehículos</div>
            </div>
            <div>
                <div class="hero-stat-value">+1.5K</div>
                <div class="hero-stat-label">Clientes</div>
            </div>
            <div>
                <div class="hero-stat-value">10+</div>
                <div class="hero-stat-label">Años</div>
            </div>
        </div>
    </div><!-- /hero-content -->

    <!-- Columna derecha: tarjeta visual -->
    <div class="hero-visual">
        <div class="hero-card">

            <!-- Badge flotante superior -->
            <div class="hero-badge hero-badge-1">
                <div class="badge-dot green"></div>
                Disponible ahora
            </div>

            <!-- Imagen / ilustración del vehículo -->
            <div class="hero-car-img">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.85 7h10.29l1.08 3.11H5.77L6.85 7zM19 17H5v-5h14v5zM7.5 14c-.83 0-1.5.67-1.5 1.5S6.67 17 7.5 17 9 16.33 9 15.5 8.33 14 7.5 14zm9 0c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5z"/>
                </svg>
            </div>

            <!-- Info del vehículo destacado -->
            <div class="hero-card-info">
                <div>
                    <div class="hero-card-name">Mazda CX-5 Turbo</div>
                    <div class="hero-card-sub">2024 · Automática · AWD</div>
                </div>
                <div class="hero-card-price">$115M</div>
            </div>

            <!-- Specs pills -->
            <div class="hero-card-specs">
                <span class="hero-spec-pill">
                    <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z"/></svg>
                    CVT
                </span>
                <span class="hero-spec-pill">
                    <svg viewBox="0 0 24 24"><path d="M19.77 7.23l.01-.01-3.72-3.72L15 4.56l2.11 2.11c-.94.36-1.61 1.26-1.61 2.33 0 1.38 1.12 2.5 2.5 2.5.36 0 .69-.08 1-.21v7.21c0 .55-.45 1-1 1s-1-.45-1-1V14c0-1.1-.9-2-2-2h-1V5c0-1.1-.9-2-2-2H6c-1.1 0-2 .9-2 2v16h10v-7.5h1.5v5c0 1.38 1.12 2.5 2.5 2.5s2.5-1.12 2.5-2.5V9c0-.69-.28-1.32-.73-1.77z"/></svg>
                    Gasolina
                </span>
                <span class="hero-spec-pill">
                    <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/></svg>
                    0 km
                </span>
            </div>

            <!-- Badge flotante inferior -->
            <div class="hero-badge hero-badge-2">
                <div class="badge-dot gold"></div>
                Cotiza en línea
            </div>
        </div>
    </div><!-- /hero-visual -->

</section>


<!-- ════════════════════════════════
     BENEFICIOS
════════════════════════════════ -->
<section class="benefits-section" id="beneficios">
    <div class="section-header">
        <div class="section-tag">¿Por qué elegirnos?</div>
        <h2 class="section-title">Nuestros <em>beneficios</em></h2>
        <p class="section-sub">Más de una década entregando experiencias de compra premium con respaldo y transparencia.</p>
    </div>

    <div class="benefits-grid">
        <div class="benefit-card">
            <div class="benefit-icon">
                <svg viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"/></svg>
            </div>
            <h3>Garantía Certificada</h3>
            <p>Todos nuestros vehículos pasan por una revisión técnica de 150 puntos antes de ser ofrecidos.</p>
        </div>

        <div class="benefit-card">
            <div class="benefit-icon">
                <svg viewBox="0 0 24 24"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>
            </div>
            <h3>Financiación Flexible</h3>
            <p>Crédito, leasing o permuta. Encontramos el plan que se adapta a tu presupuesto.</p>
        </div>

        <div class="benefit-card">
            <div class="benefit-icon">
                <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
            </div>
            <h3>Asesores Expertos</h3>
            <p>Nuestro equipo te guía desde la selección hasta la entrega, sin presiones.</p>
        </div>

        <div class="benefit-card">
            <div class="benefit-icon">
                <svg viewBox="0 0 24 24"><path d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm13.5-9-1.96 2.5H17V9.5h2.5zM18 18.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>
            </div>
            <h3>Entrega Inmediata</h3>
            <p>Vehículos listos para entregar. Sin demoras, trámites simplificados y documentación completa.</p>
        </div>

        <div class="benefit-card">
            <div class="benefit-icon">
                <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14l-5-5 1.41-1.41L12 14.17l7.59-7.59L21 8l-9 9z"/></svg>
            </div>
            <h3>Trámites Incluidos</h3>
            <p>Nos encargamos del traspaso, SOAT y tecnomecánica para que solo disfrutes tu nuevo auto.</p>
        </div>

        <div class="benefit-card">
            <div class="benefit-icon">
                <svg viewBox="0 0 24 24"><path d="M6.5 10h-2v7h2v-7zm6 0h-2v7h2v-7zm8.5 9H2v2h19v-2zm-2.5-9h-2v7h2v-7zM11.5 1L2 6v2h19V6l-9.5-5z"/></svg>
            </div>
            <h3>Respaldo de Marca</h3>
            <p>Trabajamos con las mejores marcas del mercado con contratos de distribución oficial.</p>
        </div>
    </div>
</section>

<!-- ════════════════════════════════
     HORARIOS
════════════════════════════════ -->
<section class="horarios-section" id="horarios">
    <div class="section-header">
        <div class="section-tag">Visítanos</div>
        <h2 class="section-title">Horarios &amp; <em>Contacto</em></h2>
        <p class="section-sub">Estamos disponibles para atenderte y resolver todas tus dudas personalmente.</p>
    </div>

    <div class="horarios-inner">
        <div class="horario-card">
            <table class="horario-table">
                <tr><td>Lunes — Viernes</td><td>8:00 AM – 7:00 PM</td></tr>
                <tr><td>Sábado</td><td>9:00 AM – 5:00 PM</td></tr>
                <tr><td>Domingo</td><td>10:00 AM – 2:00 PM</td></tr>
                <tr><td>Festivos</td><td>10:00 AM – 1:00 PM</td></tr>
            </table>
        </div>

        <div>
            <div class="contacto-item">
                <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                <div>
                    <strong>Dirección</strong><br>
                    <span>Av. Principal #45-20, Bogotá, Colombia</span>
                </div>
            </div>
            <div class="contacto-item">
                <svg viewBox="0 0 24 24"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>
                <div>
                    <strong>Teléfono</strong><br>
                    <span>+57 601 234 5678</span>
                </div>
            </div>
            <div class="contacto-item">
                <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                <div>
                    <strong>Correo</strong><br>
                    <span>info@drivecollection.com</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ════════════════════════════════
     ASESORES
════════════════════════════════ -->
<section class="asesores-section" id="asesores">
    <div class="section-header">
        <div class="section-tag">Nuestro equipo</div>
        <h2 class="section-title">Conoce a nuestros <em>asesores</em></h2>
        <p class="section-sub">Profesionales comprometidos en encontrarte el vehículo perfecto.</p>
    </div>

    <div class="asesores-grid">
        <?php if (empty($asesores)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                </div>
                <h3>Equipo de Asesores</h3>
                <p>Nuestros asesores expertos se encuentran listos para guiarte en tu proceso de compra de forma personalizada.</p>
            </div>
        <?php else: ?>
            <?php foreach ($asesores as $a): ?>
            <div class="asesor-card">
                <div class="asesor-avatar">
                    <?php if (!empty($a['foto'])): ?>
                        <img src="/EntreVentaCarros/<?= htmlspecialchars($a['foto']) ?>" alt="<?= htmlspecialchars($a['nombre']) ?>">
                    <?php else: ?>
                        <?= strtoupper(substr($a['nombre'], 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <h3><?= htmlspecialchars($a['nombre']) ?></h3>
                <span>Asesor de Ventas</span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<!-- ════════════════════════════════
     VEHÍCULOS
════════════════════════════════ -->
<section class="vehiculos-section" id="vehiculos">
    <div class="section-header">
        <div class="section-tag">Catálogo</div>
        <h2 class="section-title">Vehículos <em>disponibles</em></h2>
        <p class="section-sub">Todos con revisión técnica completa. Filtra, compara y solicita tu cotización en línea.</p>
    </div>

    <div class="vehiculos-grid">
        <?php if (empty($vehiculos)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <svg viewBox="0 0 24 24"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99z"/></svg>
                </div>
                <h3>Catálogo en Actualización</h3>
                <p>Estamos preparando nuevos vehículos exclusivos para ti. Vuelve pronto o solicita asesoría personalizada.</p>
            </div>
        <?php else: ?>
            <?php foreach ($vehiculos as $v): ?>
            <div class="vehiculo-card" onclick="abrirModal(<?= $v['id_vehiculos'] ?>, '<?= htmlspecialchars(addslashes($v['marca'] . ' ' . $v['modelo']), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($v['imagen_principal'] ?? ''), ENT_QUOTES) ?>')">

                <?php if (!empty($v['imagen_principal'])): ?>
                    <div class="vehiculo-img">
                        <img src="/EntreVentaCarros/<?= htmlspecialchars($v['imagen_principal']) ?>" alt="<?= htmlspecialchars($v['marca'] . ' ' . $v['modelo']) ?>" loading="lazy">
                    </div>
                <?php else: ?>
                    <div class="vehiculo-img-placeholder">
                        <svg viewBox="0 0 24 24"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99z"/></svg>
                    </div>
                <?php endif; ?>

                <div class="vehiculo-body">
                    <div class="vehiculo-tag"><?= htmlspecialchars($v['combustible']) ?></div>
                    <div class="vehiculo-nombre"><?= htmlspecialchars($v['marca'] . ' ' . $v['modelo']) ?></div>
                    <div class="vehiculo-anio">Año <?= htmlspecialchars($v['anio']) ?></div>

                    <div class="vehiculo-specs">
                        <span class="vehiculo-spec">
                            <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z"/></svg>
                            <?= htmlspecialchars($v['transmision']) ?>
                        </span>
                        <span class="vehiculo-spec">
                            <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
                            <?= number_format($v['kilometraje'], 0, ',', '.') ?> km
                        </span>
                    </div>

                    <div class="vehiculo-footer">
                        <div class="vehiculo-precio">
                            $<?= number_format($v['precio_lista'], 0, ',', '.') ?>
                        </div>
                        <span class="btn-detalle">Ver detalles</span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<!-- ════════════════════════════════
     MODAL DETALLE + COTIZACIÓN
════════════════════════════════ -->
<div class="modal-overlay" id="modal-overlay">
    <div class="modal" id="modal">
        <div class="modal-header">
            <h2 id="modal-titulo">Detalle del Vehículo</h2>
            <button class="modal-close" id="modal-close" aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
            </button>
        </div>
        <div class="modal-body">

            <!-- Mensaje solicitud -->
            <?php if (!empty($mensaje)): ?>
            <div class="alert-<?= $msgTipo ?>">
                <?= htmlspecialchars($mensaje) ?>
            </div>
            <?php endif; ?>

            <?php if ($msgTipo !== 'success'): ?>
            <!-- Imagen principal -->
            <div class="galeria-principal">
                <img id="galeria-img-principal" src="" alt="Vehículo">
            </div>
            <div class="galeria-thumbs" id="galeria-thumbs"></div>

            <!-- Formulario de cotización (solo se muestra si no fue exitoso) -->
            <div class="form-title">📋 Solicitar Cotización</div>

            <form method="POST" action="" id="form-cotizacion">
                <input type="hidden" name="id_vehiculos" id="input-id-vehiculo">

                <div class="form-row">
                    <div class="fgroup">
                        <label for="cot-nombre">Nombre completo *</label>
                        <input type="text" id="cot-nombre" name="nombre" placeholder="Tu nombre" required>
                    </div>
                    <div class="fgroup">
                        <label for="cot-correo">Correo electrónico *</label>
                        <input type="email" id="cot-correo" name="correo" placeholder="tu@correo.com" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="fgroup">
                        <label for="cot-telefono">Teléfono</label>
                        <input type="tel" id="cot-telefono" name="telefono" placeholder="3001234567">
                    </div>
                    <div class="fgroup">
                        <label for="cot-metodo">Método de adquisición</label>
                        <select id="cot-metodo" name="metodo_adquisicion">
                            <option value="No definido">No definido</option>
                            <option value="Contado">Contado</option>
                            <option value="Crédito">Crédito</option>
                            <option value="Leasing">Leasing</option>
                            <option value="Permuta">Permuta</option>
                        </select>
                    </div>
                </div>

                <div class="fgroup">
                    <label for="cot-obs">Observaciones</label>
                    <textarea id="cot-obs" name="observaciones" placeholder="¿Alguna pregunta o requerimiento especial?"></textarea>
                </div>

                <button type="submit" name="enviar_cotizacion" class="btn-primary" style="width:100%">Enviar Solicitud</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ════════════════════════════════
     FOOTER
════════════════════════════════ -->
<footer class="footer">
    <div class="footer-brand">
        <div class="nav-logo">
            <svg viewBox="0 0 24 24"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99z"/></svg>
        </div>
        <span>Drive Collection</span>
    </div>
    <p>© <?= date('Y') ?> Drive Collection · Todos los derechos reservados</p>
</footer>

<script src="/EntreVentaCarros/assetes/js/home.js"></script>
<script>
    // Abrir modal automáticamente si hay mensaje de respuesta (POST o redirección por éxito)
    <?php 
    $id_vehiculo_modal = $id_vehiculo_enviado > 0 ? $id_vehiculo_enviado : (int)($_POST['id_vehiculos'] ?? 0);
    if (!empty($mensaje) && $id_vehiculo_modal > 0): 
    ?>
    window.addEventListener('DOMContentLoaded', () => {
        abrirModal(<?= $id_vehiculo_modal ?>, 'Cotización enviada', '');
    });
    <?php endif; ?>
</script>

</body>
</html>
