<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

require_once "../config/database.php";
$db = getDB();

// Consultas para las estadísticas
$stats = [
    'carros'       => (int) $db->query("SELECT COUNT(*) FROM vehiculos WHERE estado = 'disponible'")->fetchColumn(),
    'reservas'     => (int) $db->query("SELECT COUNT(*) FROM reservas WHERE estado = 'activa'")->fetchColumn(),
    'ventas_mes'   => (int) $db->query("SELECT COUNT(*) FROM ventas WHERE MONTH(fecha_venta) = MONTH(NOW()) AND YEAR(fecha_venta) = YEAR(NOW())")->fetchColumn(),
    'asesores'     => (int) $db->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'asesor' AND activo = 1")->fetchColumn(),
    'cotizaciones' => (int) $db->query("SELECT COUNT(*) FROM solicitudes WHERE estado = 'pendiente'")->fetchColumn(),
    'ingresos'     => (float) $db->query("SELECT COALESCE(SUM(monto), 0) FROM ventas")->fetchColumn(),
];

// Últimas ventas
$stmtVentas = $db->prepare("
    SELECT v.id_ventas, v.monto, v.metodo_pago, v.fecha_venta,
           c.nombre AS cliente_nombre, c.apellido AS cliente_apellido,
           ve.marca, ve.modelo
    FROM ventas v
    JOIN clientes c  ON c.id_clientes  = v.id_clientes
    JOIN vehiculos ve ON ve.id_vehiculos = v.id_vehiculos
    ORDER BY v.fecha_venta DESC
    LIMIT 5
");
$stmtVentas->execute();
$ultimasVentas = $stmtVentas->fetchAll();

// Últimas solicitudes
$stmtSolicitudes = $db->prepare("
    SELECT s.id_solicitudes, s.nombre, s.correo, s.metodo_adquisicion,
           s.estado, s.fecha_solicitud,
           ve.marca, ve.modelo
    FROM solicitudes s
    JOIN vehiculos ve ON ve.id_vehiculos = s.id_vehiculos
    ORDER BY s.fecha_solicitud DESC
    LIMIT 5
");
$stmtSolicitudes->execute();
$ultimasSolicitudes = $stmtSolicitudes->fetchAll();
?>
<!DOCTYPE html>

<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Drive Collection</title>
    <meta name="description" content="Panel administrativo de Drive Collection. Estadísticas en tiempo real de vehículos, reservas y ventas.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/EntreVentaCarros/assetes/css/dashboard.css">
</head>
<body>

<!-- ═══════════════════════════════════════
     SIDEBAR
═══════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">

    <div class="sidebar-brand">
        <div class="brand-logo">
            <svg viewBox="0 0 24 24"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.85 7h10.29l1.08 3.11H5.77L6.85 7zM19 17H5v-5h14v5zM7.5 14c-.83 0-1.5.67-1.5 1.5S6.67 17 7.5 17 9 16.33 9 15.5 8.33 14 7.5 14zm9 0c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5z"/></svg>
        </div>
        <div class="brand-text">
            <h2>Drive Collection</h2>
            <span>Panel Administrativo</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-label">Principal</div>

        <a href="dashboard.php" class="nav-item active" id="nav-dashboard">
            <svg viewBox="0 0 24 24"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
            Dashboard
        </a>

        <div class="nav-label">Gestión</div>

        <a href="vehiculos.php" class="nav-item" id="nav-vehiculos">
            <svg viewBox="0 0 24 24"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.85 7h10.29l1.08 3.11H5.77L6.85 7zM19 17H5v-5h14v5z"/></svg>
            Vehículos
        </a>

        <a href="clientes.php" class="nav-item" id="nav-clientes">
            <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
            Clientes
        </a>

        <a href="asesores.php" class="nav-item" id="nav-asesores">
            <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
            Asesores
        </a>

        <a href="reservas.php" class="nav-item" id="nav-reservas">
            <svg viewBox="0 0 24 24"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>
            Reservas
        </a>

        <a href="ventas.php" class="nav-item" id="nav-ventas">
            <svg viewBox="0 0 24 24"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>
            Ventas
        </a>

        <a href="solicitudes.php" class="nav-item" id="nav-solicitudes">
            <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/></svg>
            Solicitudes
        </a>

        <a href="asistencias.php" class="nav-item" id="nav-asistencias">
            <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
            Asistencias
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <?= strtoupper(substr($_SESSION['usuario_nombre'] ?? 'A', 0, 1)) ?>
            </div>
            <div class="user-details">
                <strong><?= htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Admin') ?></strong>
                <span><?= ucfirst($_SESSION['usuario_rol'] ?? 'admin') ?></span>
            </div>
            <a href="logout.php" class="btn-logout" title="Cerrar sesión">
                <svg viewBox="0 0 24 24"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
            </a>
        </div>
    </div>

</aside>

<!-- ═══════════════════════════════════════
     MAIN
═══════════════════════════════════════ -->
<main class="main">

    <!-- Topbar -->
    <header class="topbar">
        <div class="topbar-left">
            <h1>Dashboard</h1>
            <p>Resumen general en tiempo real</p>
        </div>
        <div class="topbar-right">
            <span class="badge-date" id="live-date"></span>
        </div>
    </header>

    <!-- Banner ingresos históricos -->
    <div class="income-banner">
        <div class="income-banner-left">
            <div class="income-icon">
                <svg viewBox="0 0 24 24"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>
            </div>
            <div>
                <div class="income-label">Ingresos Históricos Totales</div>
                <div class="income-amount">
                    $<?= number_format($stats['ingresos'], 0, ',', '.') ?>
                </div>
            </div>
        </div>
        <span class="income-tag">💰 Acumulado histórico</span>
    </div>

    <!-- Contenido -->
    <div class="content">

        <!-- Tarjetas de estadísticas -->
        <div class="stats-grid">

            <div class="stat-card gold">
                <div class="stat-icon gold">
                    <svg viewBox="0 0 24 24"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99z"/></svg>
                </div>
                <div class="stat-value"><?= $stats['carros'] ?></div>
                <div class="stat-label">Carros en Stock</div>
            </div>

            <div class="stat-card blue">
                <div class="stat-icon blue">
                    <svg viewBox="0 0 24 24"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>
                </div>
                <div class="stat-value"><?= $stats['reservas'] ?></div>
                <div class="stat-label">Reservas Activas</div>
            </div>

            <div class="stat-card green">
                <div class="stat-icon green">
                    <svg viewBox="0 0 24 24"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>
                </div>
                <div class="stat-value"><?= $stats['ventas_mes'] ?></div>
                <div class="stat-label">Ventas del Mes</div>
            </div>

            <div class="stat-card purple">
                <div class="stat-icon purple">
                    <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                </div>
                <div class="stat-value"><?= $stats['asesores'] ?></div>
                <div class="stat-label">Asesores Totales</div>
            </div>

            <div class="stat-card orange">
                <div class="stat-icon orange">
                    <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/></svg>
                </div>
                <div class="stat-value"><?= $stats['cotizaciones'] ?></div>
                <div class="stat-label">Cotizaciones Pendientes</div>
            </div>

        </div>

        <!-- Tablas recientes -->
        <div class="tables-grid">

            <!-- Últimas Ventas -->
            <div class="table-card">
                <div class="table-header">
                    <h3>
                        <svg viewBox="0 0 24 24"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>
                        Últimas Ventas
                    </h3>
                    <a href="#">Ver todas</a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Vehículo</th>
                            <th>Monto</th>
                            <th>Pago</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ultimasVentas)): ?>
                        <tr><td colspan="4" class="empty-row">No hay ventas registradas</td></tr>
                        <?php else: ?>
                        <?php foreach ($ultimasVentas as $v): ?>
                        <tr>
                            <td><?= htmlspecialchars($v['cliente_nombre'] . ' ' . $v['cliente_apellido']) ?></td>
                            <td><?= htmlspecialchars($v['marca'] . ' ' . $v['modelo']) ?></td>
                            <td>$<?= number_format($v['monto'], 0, ',', '.') ?></td>
                            <td><?= htmlspecialchars($v['metodo_pago']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Últimas Solicitudes -->
            <div class="table-card">
                <div class="table-header">
                    <h3>
                        <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/></svg>
                        Últimas Solicitudes
                    </h3>
                    <a href="#">Ver todas</a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Vehículo</th>
                            <th>Método</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ultimasSolicitudes)): ?>
                        <tr><td colspan="4" class="empty-row">No hay solicitudes registradas</td></tr>
                        <?php else: ?>
                        <?php foreach ($ultimasSolicitudes as $s): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['nombre']) ?></td>
                            <td><?= htmlspecialchars($s['marca'] . ' ' . $s['modelo']) ?></td>
                            <td><?= htmlspecialchars($s['metodo_adquisicion']) ?></td>
                            <td>
                                <span class="badge badge-<?= htmlspecialchars($s['estado']) ?>">
                                    <?= htmlspecialchars($s['estado']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div><!-- /content -->

</main>

<script src="/EntreVentaCarros/assetes/js/dashboard.js"></script>

</body>
</html>
