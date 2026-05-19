<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

require_once "../config/database.php";
$db = getDB();

// ── PROCESAR ACCIONES DEL CRUD ──────────────────────────────────────

// POST: Registrar o Editar Asistencia
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {
    $action = $_POST['form_action'];
    $id = (int)($_POST['id_asistencias'] ?? 0);
    
    $id_usuario    = (int)($_POST['id_usuario'] ?? 0);
    $fecha         = $_POST['fecha'] ?? '';
    $hora_entrada  = $_POST['hora_entrada'] ?? null;
    $hora_salida   = $_POST['hora_salida'] ?? null;
    $estado        = $_POST['estado'] ?? 'presente';
    $observaciones = trim($_POST['observaciones'] ?? '');

    if (empty($hora_entrada)) { $hora_entrada = null; }
    if (empty($hora_salida)) { $hora_salida = null; }

    if ($id_usuario > 0 && !empty($fecha)) {
        if ($action === 'crear') {
            // VALIDACIÓN ANTI-DUPLICIDAD: Máximo una asistencia por asesor al día
            $check = $db->prepare("SELECT COUNT(*) FROM asistencias WHERE id_usuario = ? AND fecha = ?");
            $check->execute([$id_usuario, $fecha]);
            if ($check->fetchColumn() > 0) {
                header("Location: asistencias.php?status=duplicate_log");
                exit();
            }

            // Insertar asistencia
            $stmt = $db->prepare("INSERT INTO asistencias (id_usuario, fecha, hora_entrada, hora_salida, estado, observaciones) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id_usuario, $fecha, $hora_entrada, $hora_salida, $estado, $observaciones]);

            header("Location: asistencias.php?status=created");
            exit();

        } elseif ($action === 'editar' && $id > 0) {
            // VALIDACIÓN ANTI-DUPLICIDAD EN EDICIÓN
            $check = $db->prepare("SELECT COUNT(*) FROM asistencias WHERE id_usuario = ? AND fecha = ? AND id_asistencias != ?");
            $check->execute([$id_usuario, $fecha, $id]);
            if ($check->fetchColumn() > 0) {
                header("Location: asistencias.php?status=duplicate_log");
                exit();
            }

            // Actualizar asistencia
            $stmt = $db->prepare("UPDATE asistencias SET id_usuario = ?, fecha = ?, hora_entrada = ?, hora_salida = ?, estado = ?, observaciones = ? WHERE id_asistencias = ?");
            $stmt->execute([$id_usuario, $fecha, $hora_entrada, $hora_salida, $estado, $observaciones, $id]);

            header("Location: asistencias.php?status=updated");
            exit();
        }
    }
}

// GET: Registrar Salida Rápida (Clock-out con un clic)
if (isset($_GET['action']) && $_GET['action'] === 'clockout') {
    $id = (int)$_GET['id_asistencias'];
    $nowTime = date('H:i:s');
    
    $stmt = $db->prepare("UPDATE asistencias SET hora_salida = ? WHERE id_asistencias = ?");
    $stmt->execute([$nowTime, $id]);
    
    header("Location: asistencias.php?status=clocked_out");
    exit();
}

// GET: Eliminar Registro de Asistencia
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = (int)$_GET['id_asistencias'];
    
    $stmt = $db->prepare("DELETE FROM asistencias WHERE id_asistencias = ?");
    $stmt->execute([$id]);
    
    header("Location: asistencias.php?status=deleted");
    exit();
}

// ── CONSULTAS GENERALES ─────────────────────────────────────────────

// Listado de Asistencias con cruce de Usuarios
$stmt = $db->query("
    SELECT a.*, u.nombre AS asesor_nombre, u.foto AS asesor_foto, u.rol AS asesor_rol
    FROM asistencias a
    INNER JOIN usuarios u ON u.id_usuario = a.id_usuario
    ORDER BY a.fecha DESC, a.hora_entrada DESC
");
$asistencias = $stmt->fetchAll();

// Listado de Asesores Activos para el selector
$asesores = $db->query("SELECT id_usuario, nombre FROM usuarios WHERE activo = 1 ORDER BY nombre ASC")->fetchAll();

// Mapeo de alertas
$alerts = [
    'created'       => ['success', 'Asistencia del asesor registrada con éxito.'],
    'updated'       => ['success', 'Registro de asistencia actualizado correctamente.'],
    'deleted'       => ['success', 'Asistencia eliminada de la base de datos.'],
    'clocked_out'   => ['success', 'Salida del asesor marcada correctamente con la hora del servidor actual.'],
    'duplicate_log' => ['danger', 'Error de duplicidad: Ya existe un registro de asistencia para este asesor comercial en la fecha seleccionada.'],
];
$status = $_GET['status'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Asistencias comercial — Drive Collection</title>
    <meta name="description" content="Gestión y control de horarios de entrada y salida del staff de asesores comerciales.">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Estilos -->
    <link rel="stylesheet" href="/EntreVentaCarros/assetes/css/asistencias.css">
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

        <a href="solicitudes.php" class="nav-item">
            <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/></svg>
            Solicitudes Web
        </a>

        <a href="asistencias.php" class="nav-item active">
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
            <h1>Control de Asistencias</h1>
            <p>Monitoreo diario de horarios de entrada y salida del staff de asesores.</p>
        </div>
        <div class="topbar-right">
            <button class="btn btn-primary" id="btn-registrar-asistencia">
                <svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor;"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                Registrar Ingreso
            </button>
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
                    <th>Fecha</th>
                    <th>Asesor Comercial</th>
                    <th>Hora Entrada</th>
                    <th>Hora Salida</th>
                    <th>Estado de Jornada</th>
                    <th>Observaciones</th>
                    <th style="text-align:right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($asistencias)): ?>
                <tr>
                    <td colspan="7" style="text-align:center;color:var(--muted);padding:3rem;">
                        No hay registros de asistencias en esta fecha.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($asistencias as $a): ?>
                <tr>
                    <td><strong><?= date('d/m/Y', strtotime($a['fecha'])) ?></strong></td>
                    <td>
                        <div class="advisor-cell">
                            <?php if (!empty($a['asesor_foto'])): ?>
                                <img src="/EntreVentaCarros/<?= htmlspecialchars($a['asesor_foto']) ?>" class="advisor-avatar-img" alt="Foto">
                            <?php else: ?>
                                <div class="advisor-avatar-placeholder">
                                    <?= strtoupper(substr($a['asesor_nombre'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <div class="advisor-meta">
                                <strong><?= htmlspecialchars($a['asesor_nombre']) ?></strong>
                                <span><?= ucfirst($a['asesor_rol']) ?></span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="time-stamp"><?= $a['hora_entrada'] ? date('H:i', strtotime($a['hora_entrada'])) : '—' ?></span>
                    </td>
                    <td>
                        <?php if ($a['hora_salida']): ?>
                            <span class="time-stamp"><?= date('H:i', strtotime($a['hora_salida'])) ?></span>
                        <?php else: ?>
                            <span style="color:var(--muted)">Sin registrar</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-<?= strtolower($a['estado']) ?>">
                            <?= htmlspecialchars($a['estado']) ?>
                        </span>
                    </td>
                    <td>
                        <span style="font-size:.84rem;color:var(--muted)">
                            <?= htmlspecialchars($a['observaciones'] ?: '—') ?>
                        </span>
                    </td>
                    <td style="text-align:right;">
                        <div style="display:inline-flex; gap:.4rem">
                            <!-- Registrar Salida Rápida -->
                            <?php if (empty($a['hora_salida'])): ?>
                                <a href="asistencias.php?action=clockout&id_asistencias=<?= $a['id_asistencias'] ?>" class="btn btn-secondary btn-icon" title="Marcar Salida Actual" onclick="return confirm('¿Registrar la hora de salida de este asesor comercial en este momento?')">
                                    <svg viewBox="0 0 24 24" style="fill:#22c55e;"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
                                </a>
                            <?php endif; ?>
                            <!-- Editar -->
                            <button class="btn btn-secondary btn-icon btn-edit-attendance"
                                    data-id="<?= $a['id_asistencias'] ?>"
                                    data-id-usuario="<?= $a['id_usuario'] ?>"
                                    data-fecha="<?= $a['fecha'] ?>"
                                    data-hora-entrada="<?= $a['hora_entrada'] ?>"
                                    data-hora-salida="<?= $a['hora_salida'] ?>"
                                    data-estado="<?= htmlspecialchars($a['estado']) ?>"
                                    data-observaciones="<?= htmlspecialchars($a['observaciones'] ?? '', ENT_QUOTES) ?>"
                                    title="Editar">
                                <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                            </button>
                            <!-- Eliminar -->
                            <a href="asistencias.php?action=delete&id_asistencias=<?= $a['id_asistencias'] ?>" class="btn btn-danger btn-icon" title="Eliminar" onclick="return confirm('¿Estás seguro de que deseas eliminar este registro de asistencia?')">
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
     MODAL: REGISTRAR / EDITAR ASISTENCIA
═══════════════════════════════════════ -->
<div class="modal-overlay" id="attendance-modal">
    <div class="modal">
        <div class="modal-header">
            <h2 id="modal-title">Registrar Asistencia</h2>
            <button class="modal-close btn-close-modal">
                <svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
            </button>
        </div>
        
        <form id="attendance-form" method="POST" action="asistencias.php">
            <div class="modal-body">
                <input type="hidden" name="form_action" id="form-action" value="crear">
                <input type="hidden" name="id_asistencias" id="form-id-asistencia" value="">
                
                <div class="form-grid">
                    
                    <div class="form-group">
                        <label for="form-asesor">Asesor comercial *</label>
                        <select name="id_usuario" id="form-asesor" required>
                            <option value="">-- Seleccionar Asesor --</option>
                            <?php foreach ($asesores as $as): ?>
                                <option value="<?= $as['id_usuario'] ?>">
                                    <?= htmlspecialchars($as['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="form-fecha">Fecha Jornada *</label>
                            <input type="date" name="fecha" id="form-fecha" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="form-estado">Estado de Asistencia</label>
                            <select name="estado" id="form-estado">
                                <option value="presente">Presente</option>
                                <option value="tardanza">Tardanza</option>
                                <option value="ausente">Ausente</option>
                                <option value="permiso">Permiso</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="form-hora-entrada">Hora Entrada *</label>
                            <input type="time" name="hora_entrada" id="form-hora-entrada" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="form-hora-salida">Hora Salida</label>
                            <input type="time" name="hora_salida" id="form-hora-salida">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="form-observaciones">Observaciones / Justificaciones</label>
                        <textarea name="observaciones" id="form-observaciones" rows="3" placeholder="Ej. Tardanza por tráfico, permiso de salud..."></textarea>
                    </div>

                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-close-modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar Registro</button>
            </div>
        </form>
    </div>
</div>

<script src="/EntreVentaCarros/assetes/js/asistencias.js"></script>

</body>
</html>
