<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

require_once "../config/database.php";
$db = getDB();

// ── PROCESAR ACCIONES DEL CRUD ──────────────────────────────────────

// POST: Editar Solicitud Directamente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'editar') {
    $id                 = (int)($_POST['id_solicitudes'] ?? 0);
    $id_vehiculos       = (int)($_POST['id_vehiculos'] ?? 0);
    $nombre             = trim($_POST['nombre'] ?? '');
    $correo             = trim($_POST['correo'] ?? '');
    $telefono           = trim($_POST['telefono'] ?? '');
    $metodo_adquisicion = $_POST['metodo_adquisicion'] ?? 'No definido';
    $estado             = $_POST['estado'] ?? 'pendiente';
    $observaciones      = trim($_POST['observaciones'] ?? '');

    if ($id > 0 && $id_vehiculos > 0 && !empty($nombre) && !empty($correo)) {
        $stmt = $db->prepare("
            UPDATE solicitudes 
            SET id_vehiculos = ?, nombre = ?, correo = ?, telefono = ?, metodo_adquisicion = ?, estado = ?, observaciones = ? 
            WHERE id_solicitudes = ?
        ");
        $stmt->execute([$id_vehiculos, $nombre, $correo, $telefono, $metodo_adquisicion, $estado, $observaciones, $id]);

        header("Location: solicitudes.php?status=updated");
        exit();
    }
}

// POST: Onboarding Automatizado (Convertir Solicitud a Venta)
// "prellene los campos de facturación interna y archive automáticamente el lead original tras confirmarse la compra"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'convertir') {
    $id_solicitudes = (int)($_POST['id_solicitudes'] ?? 0);
    $id_vehiculos   = (int)($_POST['id_vehiculos'] ?? 0);
    
    // Datos de facturación del cliente comprador
    $documento = trim($_POST['documento'] ?? '');
    $nombre    = trim($_POST['nombre'] ?? '');
    $apellido  = trim($_POST['apellido'] ?? '');
    $correo    = trim($_POST['correo'] ?? '');
    $telefono  = trim($_POST['telefono'] ?? '');

    // Datos del negocio
    $monto         = (float)($_POST['monto'] ?? 0);
    $metodo_pago   = $_POST['metodo_pago'] ?? 'Contado';
    $observaciones = trim($_POST['observaciones'] ?? '');
    $id_usuario    = (int)$_SESSION['id_usuario'];

    if ($id_solicitudes > 0 && $id_vehiculos > 0 && !empty($documento) && !empty($nombre) && $monto > 0) {
        
        // 1. Verificar si el cliente ya existe en el sistema por documento o correo
        $stmtCheck = $db->prepare("SELECT id_clientes FROM clientes WHERE documento = ? OR correo = ?");
        $stmtCheck->execute([$documento, $correo]);
        $id_clientes = (int)$stmtCheck->fetchColumn();

        // 2. Si no existe, crear el cliente de forma automatizada (Onboarding)
        if ($id_clientes === 0) {
            $stmtInsCli = $db->prepare("INSERT INTO clientes (documento, nombre, apellido, correo, telefono) VALUES (?, ?, ?, ?, ?)");
            $stmtInsCli->execute([$documento, $nombre, $apellido, $correo, $telefono]);
            $id_clientes = (int)$db->lastInsertId();
        }

        // 3. Registrar la venta vinculando el lead original y el asesor
        $stmtInsSale = $db->prepare("
            INSERT INTO ventas (id_clientes, id_vehiculos, id_usuario, id_solicitudes, monto, metodo_pago, observaciones) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtInsSale->execute([$id_clientes, $id_vehiculos, $id_usuario, $id_solicitudes, $monto, $metodo_pago, $observaciones]);
        $id_ventas = (int)$db->lastInsertId();

        // 4. AUTOMATIZACIÓN: Cambiar estado del vehículo a 'vendido'
        $db->prepare("UPDATE vehiculos SET estado = 'vendido' WHERE id_vehiculos = ?")->execute([$id_vehiculos]);

        // 5. AUTOMATIZACIÓN DE ARCHIVADO: Cambiar estado del Lead a 'convertida' y enlazar la venta creada
        $db->prepare("
            UPDATE solicitudes 
            SET estado = 'convertida', id_ventas = ? 
            WHERE id_solicitudes = ?
        ")->execute([$id_ventas, $id_solicitudes]);

        header("Location: solicitudes.php?status=converted");
        exit();
    }
}

// GET: Archivar Lead Directamente
if (isset($_GET['action']) && $_GET['action'] === 'archive') {
    $id = (int)$_GET['id_solicitudes'];
    
    $stmt = $db->prepare("UPDATE solicitudes SET estado = 'archivada' WHERE id_solicitudes = ?");
    $stmt->execute([$id]);
    
    header("Location: solicitudes.php?status=archived");
    exit();
}

// GET: Eliminar Lead
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = (int)$_GET['id_solicitudes'];
    
    $stmt = $db->prepare("DELETE FROM solicitudes WHERE id_solicitudes = ?");
    $stmt->execute([$id]);
    
    header("Location: solicitudes.php?status=deleted");
    exit();
}

// ── CONSULTAS GENERALES ─────────────────────────────────────────────

// Listado de Solicitudes Web con cruce del Vehículo cotizado y Venta asociada (si existe)
$stmt = $db->query("
    SELECT s.*, 
           v.marca AS auto_marca, v.modelo AS auto_modelo, v.precio_lista AS auto_precio,
           vt.id_ventas AS venta_confirmada_id, vt.fecha_venta AS venta_fecha
    FROM solicitudes s
    INNER JOIN vehiculos v ON v.id_vehiculos = s.id_vehiculos
    LEFT JOIN ventas vt ON vt.id_ventas = s.id_ventas
    ORDER BY s.id_solicitudes DESC
");
$solicitudes = $stmt->fetchAll();

// Listado de Vehículos para dropdowns (con precio cargado en data-precio)
$vehiculos = $db->query("SELECT id_vehiculos, marca, modelo, precio_lista, estado FROM vehiculos ORDER BY marca ASC")->fetchAll();

// Mapeo de alertas
$alerts = [
    'updated'   => ['success', 'Lead web actualizado con éxito.'],
    'converted' => ['success', '¡Conversión exitosa! El cliente fue incorporado al padrón general, se generó la venta facturada y el Lead original fue automáticamente archivado como "CONVERTIDO".'],
    'archived'  => ['success', 'Lead archivado correctamente.'],
    'deleted'   => ['success', 'Lead web eliminado del registro administrativo.'],
];
$status = $_GET['status'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bandeja de Solicitudes Web — Drive Collection</title>
    <meta name="description" content="Gestión de leads y cotizaciones entrantes del portal público.">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Estilos -->
    <link rel="stylesheet" href="/EntreVentaCarros/assetes/css/solicitudes.css">
</head>
<body>

<!-- ═══════════════════════════════════════
     SIDEBAR
═══════════════════════════════════════ -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-logo">
            <svg viewBox="0 0 24 24"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.85 7h10.29l1.08 3.11H5.77L6.85 7zM19 17H5v-5h14v5z"/></svg>
        </div>
        <div class="brand-text">
            <h2>Drive Collection</h2>
            <span>Admin Panel</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-label">Administración</div>
        
        <a href="dashboard.php" class="nav-item">
            <svg viewBox="0 0 24 24"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
            Dashboard
        </a>
        
        <a href="vehiculos.php" class="nav-item">
            <svg viewBox="0 0 24 24"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.85 7h10.29l1.08 3.11H5.77L6.85 7zM19 17H5v-5h14v5z"/></svg>
            Vehículos
        </a>

        <a href="clientes.php" class="nav-item">
            <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
            Clientes
        </a>

        <a href="asesores.php" class="nav-item">
            <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
            Asesores
        </a>

        <div class="nav-label">Operaciones</div>

        <a href="reservas.php" class="nav-item">
            <svg viewBox="0 0 24 24"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>
            Reservas
        </a>

        <a href="ventas.php" class="nav-item">
            <svg viewBox="0 0 24 24"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>
            Ventas
        </a>

        <a href="solicitudes.php" class="nav-item active">
            <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/></svg>
            Solicitudes Web
        </a>

        <a href="asistencias.php" class="nav-item">
            <svg viewBox="0 0 24 24"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm2 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
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
            <h1>Bandeja de Solicitudes Web</h1>
            <p>Monitoreo y conversión de cotizaciones enviadas por clientes interesados desde el catálogo público.</p>
        </div>
    </header>

    <!-- Alertas de Estado -->
    <?php if (isset($alerts[$status])): ?>
    <div class="alert alert-<?= $alerts[$status][0] ?>" role="alert">
        <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
        <?= $alerts[$status][1] ?>
    </div>
    <?php endif; ?>

    <!-- Tabla de Contenido -->
    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Prospecto (Lead)</th>
                    <th>Auto Solicitado</th>
                    <th>Método Deseado</th>
                    <th>Fecha Entrada</th>
                    <th>Estado</th>
                    <th>Observaciones Cliente</th>
                    <th style="text-align:right">Operaciones de Venta / Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($solicitudes)): ?>
                <tr>
                    <td colspan="8" style="text-align:center;color:var(--muted);padding:3rem;">
                        No se registran solicitudes de cotización en el portal web.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($solicitudes as $s): ?>
                <tr>
                    <td><strong>#<?= $s['id_solicitudes'] ?></strong></td>
                    <td>
                        <div style="font-weight:600;color:#fff"><?= htmlspecialchars($s['nombre']) ?></div>
                        <div style="font-size:.72rem;color:var(--muted)">Mail: <?= htmlspecialchars($s['correo']) ?></div>
                        <div style="font-size:.72rem;color:var(--muted)">Tel: <?= htmlspecialchars($s['telefono'] ?: 'No registrado') ?></div>
                    </td>
                    <td>
                        <div style="font-weight:600"><?= htmlspecialchars($s['auto_marca'] . ' ' . $s['auto_modelo']) ?></div>
                        <div style="font-size:.72rem;color:var(--muted)">Precio de Lista: $<?= number_format($s['auto_precio'], 2) ?></div>
                    </td>
                    <td>
                        <span class="acq-badge">
                            <?= htmlspecialchars($s['metodo_adquisicion']) ?>
                        </span>
                    </td>
                    <td><?= date('d/m/Y H:i', strtotime($s['fecha_solicitud'])) ?></td>
                    <td>
                        <span class="badge badge-<?= strtolower($s['estado']) ?>">
                            <?= htmlspecialchars($s['estado']) ?>
                        </span>
                    </td>
                    <td>
                        <span style="font-size:.84rem;color:var(--muted)">
                            <?= htmlspecialchars($s['observaciones'] ?: '—') ?>
                        </span>
                    </td>
                    <td style="text-align:right;">
                        <div style="display:inline-flex; gap:.4rem">
                            
                            <!-- 🌟 BOTÓN REGISTRAR VENTA (ONBOARDING DE FACTURACIÓN) 🌟 -->
                            <?php if ($s['estado'] === 'pendiente' || $s['estado'] === 'en_proceso'): ?>
                                <button class="btn btn-primary btn-icon btn-convert-lead"
                                        data-id="<?= $s['id_solicitudes'] ?>"
                                        data-id-vehiculo="<?= $s['id_vehiculos'] ?>"
                                        data-nombre="<?= htmlspecialchars($s['nombre']) ?>"
                                        data-correo="<?= htmlspecialchars($s['correo']) ?>"
                                        data-telefono="<?= htmlspecialchars($s['telefono'] ?? '') ?>"
                                        data-metodo-adquisicion="<?= htmlspecialchars($s['metodo_adquisicion']) ?>"
                                        title="Registrar Compra / Facturar Venta">
                                    <svg viewBox="0 0 24 24" style="width:15px;height:15px;fill:#0b0e1a"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H7c0-2.76 2.24-5 5-5s5 2.24 5 5c0 1.04-.42 1.99-1.07 2.75z"/></svg>
                                    Facturar Venta
                                </button>
                            <?php endif; ?>

                            <!-- Archivar -->
                            <?php if ($s['estado'] === 'pendiente' || $s['estado'] === 'en_proceso'): ?>
                                <a href="solicitudes.php?action=archive&id_solicitudes=<?= $s['id_solicitudes'] ?>" class="btn btn-secondary btn-icon" title="Archivar Lead" onclick="return confirm('¿Deseas archivar esta solicitud?')">
                                    <svg viewBox="0 0 24 24"><path d="M20.54 5.23l-1.39-1.68C18.88 3.21 18.47 3 18 3H6c-.47 0-.88.21-1.16.55L3.46 5.23C3.17 5.57 3 6.02 3 6.5V19c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6.5c0-.48-.17-.93-.46-1.27zM6.24 5h11.52l.83 1H5.41l.83-1zM5 19V8h14v11H5zm3-5h8v-2H8v2z"/></svg>
                                </a>
                            <?php endif; ?>

                            <!-- Editar Ficha -->
                            <button class="btn btn-secondary btn-icon btn-edit-lead"
                                    data-id="<?= $s['id_solicitudes'] ?>"
                                    data-id-vehiculo="<?= $s['id_vehiculos'] ?>"
                                    data-nombre="<?= htmlspecialchars($s['nombre']) ?>"
                                    data-correo="<?= htmlspecialchars($s['correo']) ?>"
                                    data-telefono="<?= htmlspecialchars($s['telefono'] ?? '') ?>"
                                    data-metodo-adquisicion="<?= htmlspecialchars($s['metodo_adquisicion']) ?>"
                                    data-estado="<?= htmlspecialchars($s['estado']) ?>"
                                    data-observaciones="<?= htmlspecialchars($s['observaciones'] ?? '', ENT_QUOTES) ?>"
                                    title="Editar Ficha">
                                <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                            </button>

                            <!-- Eliminar -->
                            <a href="solicitudes.php?action=delete&id_solicitudes=<?= $s['id_solicitudes'] ?>" class="btn btn-danger btn-icon" title="Eliminar" onclick="return confirm('¿Estás seguro de que deseas eliminar permanentemente esta solicitud?')">
                                <svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</main>

<!-- ═══════════════════════════════════════
     MODAL: EDITAR FICHA DE LEAD
═══════════════════════════════════════ -->
<div class="modal-overlay" id="edit-modal">
    <div class="modal">
        <div class="modal-header">
            <h2>Editar Ficha de Lead Web</h2>
            <button class="modal-close btn-close-modal">
                <svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
            </button>
        </div>
        
        <form id="edit-form" method="POST" action="solicitudes.php">
            <div class="modal-body">
                <input type="hidden" name="form_action" value="editar">
                <input type="hidden" name="id_solicitudes" id="edit-id-solicitud" value="">
                
                <div class="form-grid">
                    
                    <div class="form-group">
                        <label for="edit-nombre">Nombre del Prospecto *</label>
                        <input type="text" name="nombre" id="edit-nombre" required>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="edit-correo">Correo Electrónico *</label>
                            <input type="email" name="correo" id="edit-correo" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit-telefono">Teléfono Contacto</label>
                            <input type="text" name="telefono" id="edit-telefono">
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="edit-vehiculo">Vehículo Cotizado *</label>
                            <select name="id_vehiculos" id="edit-vehiculo" required>
                                <?php foreach ($vehiculos as $vh): ?>
                                    <option value="<?= $vh['id_vehiculos'] ?>">
                                        <?= htmlspecialchars($vh['marca'] . ' ' . $vh['modelo']) ?> ($<?= number_format($vh['precio_lista'], 2) ?> USD)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="edit-metodo-adquisicion">Método de Adquisición</label>
                            <select name="metodo_adquisicion" id="edit-metodo-adquisicion">
                                <option value="Contado">Contado</option>
                                <option value="Crédito">Crédito</option>
                                <option value="Leasing">Leasing</option>
                                <option value="Permuta">Permuta</option>
                                <option value="No definido">No definido</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="edit-estado">Estado del Lead</label>
                        <select name="estado" id="edit-estado">
                            <option value="pendiente">Pendiente</option>
                            <option value="en_proceso">En Proceso</option>
                            <option value="convertida">Convertida (Venta Confirmada)</option>
                            <option value="archivada">Archivada</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit-observaciones">Observaciones / Requisitos del Lead</label>
                        <textarea name="observaciones" id="edit-observaciones" rows="3"></textarea>
                    </div>

                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-close-modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Actualizar Ficha</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════
     MODAL: ONBOARDING FACTURACIÓN AUTOMÁTICA
═══════════════════════════════════════ -->
<div class="modal-overlay" id="convert-modal">
    <div class="modal">
        <div class="modal-header">
            <h2>Facturar Compra — Onboarding de Cliente y Venta</h2>
            <button class="modal-close btn-close-modal">
                <svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
            </button>
        </div>
        
        <form id="convert-form" method="POST" action="solicitudes.php">
            <div class="modal-body">
                <input type="hidden" name="form_action" value="convertir">
                <input type="hidden" name="id_solicitudes" id="conv-id-solicitud" value="">
                
                <div class="form-grid">
                    
                    <!-- Sección de Cliente -->
                    <h3 class="form-section-title">1. Ficha del Comprador (Facturación)</h3>
                    
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="conv-doc">Documento Identidad (DNI/RUT/RFC) *</label>
                            <input type="text" name="documento" id="conv-doc" placeholder="Ej. 77665544" required>
                        </div>
                        <div style="display:flex;align-items:flex-end;padding-bottom:.4rem;font-size:.7rem;color:var(--muted)">
                            * Requerido para facturación interna y registro único.
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="conv-nombre">Nombre(s) *</label>
                            <input type="text" name="nombre" id="conv-nombre" required>
                        </div>
                        <div class="form-group">
                            <label for="conv-apellido">Apellido(s) *</label>
                            <input type="text" name="apellido" id="conv-apellido" required>
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="conv-correo">Correo Electrónico *</label>
                            <input type="email" name="correo" id="conv-correo" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="conv-telefono">Teléfono *</label>
                            <input type="text" name="telefono" id="conv-telefono" required>
                        </div>
                    </div>

                    <!-- Sección de Venta -->
                    <h3 class="form-section-title">2. Datos de Transacción Stock</h3>

                    <div class="form-group">
                        <label for="conv-vehiculo">Vehículo Apartado *</label>
                        <!-- Locked select representing the requested vehicle -->
                        <select name="id_vehiculos" id="conv-vehiculo" style="background:#1f2937;pointer-events:none;" required>
                            <?php foreach ($vehiculos as $vh): ?>
                                <option value="<?= $vh['id_vehiculos'] ?>" data-precio="<?= $vh['precio_lista'] ?>">
                                    <?= htmlspecialchars($vh['marca'] . ' ' . $vh['modelo']) ?> ($<?= number_format($vh['precio_lista'], 2) ?> USD)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="conv-monto">Monto de Facturación ($ USD) *</label>
                            <input type="number" step="0.01" name="monto" id="conv-monto" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="conv-metodo-pago">Método de Pago Seleccionado *</label>
                            <select name="metodo_pago" id="conv-metodo-pago" required>
                                <option value="Contado">Contado</option>
                                <option value="Crédito">Crédito</option>
                                <option value="Leasing">Leasing</option>
                                <option value="Permuta">Permuta</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="conv-observaciones">Detalles de Facturación Interna</label>
                        <textarea name="observaciones" id="conv-observaciones" rows="3"></textarea>
                    </div>

                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-close-modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Confirmar Compra y Archivar Lead</button>
            </div>
        </form>
    </div>
</div>

<script src="/EntreVentaCarros/assetes/js/solicitudes.js"></script>

</body>
</html>
