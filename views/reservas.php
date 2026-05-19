<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

require_once "../config/database.php";
$db = getDB();

// ── GUARDIA AUTOMÁTICO DE EXPIRACIONES (48 HORAS) ───────────────────
// Busca reservas que sigan 'activas' pero que ya hayan pasado la 'fecha_limite' de vigencia.
// Las marca automáticamente como 'expirada' y libera el vehículo asociado a 'disponible'.
$stmtExpirar = $db->query("SELECT id_reservas, id_vehiculos FROM reservas WHERE estado = 'activa' AND fecha_limite < NOW()");
$expiradas = $stmtExpirar->fetchAll();
if (!empty($expiradas)) {
    foreach ($expiradas as $exp) {
        $db->prepare("UPDATE reservas SET estado = 'expirada' WHERE id_reservas = ?")->execute([$exp['id_reservas']]);
        $db->prepare("UPDATE vehiculos SET estado = 'disponible' WHERE id_vehiculos = ?")->execute([$exp['id_vehiculos']]);
    }
}

// ── PROCESAR ACCIONES DEL CRUD ──────────────────────────────────────

// POST: Crear o Editar Reserva
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {
    $action = $_POST['form_action'];
    $id = (int)($_POST['id_reservas'] ?? 0);
    
    $id_clientes   = (int)($_POST['id_clientes'] ?? 0);
    $id_vehiculos  = (int)($_POST['id_vehiculos'] ?? 0);
    $estado        = $_POST['estado'] ?? 'activa';
    $observaciones = trim($_POST['observaciones'] ?? '');
    $id_usuario    = (int)$_SESSION['id_usuario'];

    if ($id_clientes > 0 && $id_vehiculos > 0) {
        if ($action === 'crear') {
            // Calcular fecha de expiración automáticamente (48 horas a futuro)
            $fecha_limite = date('Y-m-d H:i:s', strtotime('+48 hours'));

            // Insertar reserva
            $stmt = $db->prepare("INSERT INTO reservas (id_clientes, id_vehiculos, id_usuario, fecha_limite, estado, observaciones) VALUES (?, ?, ?, ?, 'activa', ?)");
            $stmt->execute([$id_clientes, $id_vehiculos, $id_usuario, $fecha_limite, $observaciones]);
            
            // AUTOMATIZACIÓN: Cambiar estado del vehículo a 'reservado'
            $db->prepare("UPDATE vehiculos SET estado = 'reservado' WHERE id_vehiculos = ?")->execute([$id_vehiculos]);

            header("Location: reservas.php?status=created");
            exit();

        } elseif ($action === 'editar' && $id > 0) {
            // Obtener datos antiguos para comparar vehículos y estados
            $stmtOld = $db->prepare("SELECT id_vehiculos, estado FROM reservas WHERE id_reservas = ?");
            $stmtOld->execute([$id]);
            $oldData = $stmtOld->fetch();

            // Actualizar datos de la reserva
            $stmt = $db->prepare("UPDATE reservas SET id_clientes = ?, id_vehiculos = ?, id_usuario = ?, estado = ?, observaciones = ? WHERE id_reservas = ?");
            $stmt->execute([$id_clientes, $id_vehiculos, $id_usuario, $estado, $observaciones, $id]);

            if ($oldData) {
                // Si cambió de vehículo
                if ((int)$oldData['id_vehiculos'] !== $id_vehiculos) {
                    // Liberar el vehículo antiguo
                    $db->prepare("UPDATE vehiculos SET estado = 'disponible' WHERE id_vehiculos = ?")->execute([$oldData['id_vehiculos']]);
                }

                // Sincronizar estado comercial del carro según la reserva
                if ($estado === 'activa') {
                    $db->prepare("UPDATE vehiculos SET estado = 'reservado' WHERE id_vehiculos = ?")->execute([$id_vehiculos]);
                } elseif ($estado === 'cancelada' || $estado === 'expirada') {
                    $db->prepare("UPDATE vehiculos SET estado = 'disponible' WHERE id_vehiculos = ?")->execute([$id_vehiculos]);
                } elseif ($estado === 'convertida') {
                    $db->prepare("UPDATE vehiculos SET estado = 'vendido' WHERE id_vehiculos = ?")->execute([$id_vehiculos]);
                }
            }

            header("Location: reservas.php?status=updated");
            exit();
        }
    }
}

// GET: Eliminar Reserva
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = (int)$_GET['id_reservas'];
    
    // Obtener vehículo asociado y estado
    $stmtRes = $db->prepare("SELECT id_vehiculos, estado FROM reservas WHERE id_reservas = ?");
    $stmtRes->execute([$id]);
    $res = $stmtRes->fetch();
    
    if ($res) {
        // AUTOMATIZACIÓN: Si eliminan una reserva activa, liberar el auto a disponible
        if ($res['estado'] === 'activa') {
            $db->prepare("UPDATE vehiculos SET estado = 'disponible' WHERE id_vehiculos = ?")->execute([$res['id_vehiculos']]);
        }
        
        $db->prepare("DELETE FROM reservas WHERE id_reservas = ?")->execute([$id]);
    }
    
    header("Location: reservas.php?status=deleted");
    exit();
}

// ── CONSULTAS GENERALES ─────────────────────────────────────────────

// Listado de Reservas con cruce de Clientes y Vehículos
$stmt = $db->query("
    SELECT r.*, 
           c.nombre AS cliente_nombre, c.apellido AS cliente_apellido, c.documento AS cliente_doc,
           v.marca AS auto_marca, v.modelo AS auto_modelo, v.precio_lista AS auto_precio,
           u.nombre AS asesor_nombre
    FROM reservas r
    INNER JOIN clientes c ON c.id_clientes = r.id_clientes
    INNER JOIN vehiculos v ON v.id_vehiculos = r.id_vehiculos
    LEFT JOIN usuarios u ON u.id_usuario = r.id_usuario
    ORDER BY r.id_reservas DESC
");
$reservas = $stmt->fetchAll();

// Listado de Clientes para los selectores
$clientes = $db->query("SELECT id_clientes, nombre, apellido, documento FROM clientes ORDER BY nombre ASC")->fetchAll();

// Listado de Vehículos para los selectores
$vehiculos = $db->query("SELECT id_vehiculos, marca, modelo, precio_lista, estado FROM vehiculos ORDER BY marca ASC")->fetchAll();

// Mapeo de alertas
$alerts = [
    'created' => ['success', 'Reserva registrada con éxito. Estado del vehículo cambiado a "Reservado". El límite de expiración se fijó en 48 horas automáticamente.'],
    'updated' => ['success', 'Reserva actualizada correctamente. El estado del vehículo se ha sincronizado.'],
    'deleted' => ['success', 'Reserva eliminada de la base de datos.'],
];
$status = $_GET['status'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar Reservas — Drive Collection</title>
    <meta name="description" content="Gestión y control de reservas temporales con expiración automática de 48 horas.">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Estilos -->
    <link rel="stylesheet" href="/EntreVentaCarros/assetes/css/reservas.css">
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

        <a href="reservas.php" class="nav-item active">
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
            <h1>Módulo de Reservas</h1>
            <p>Control de vigencia comercial. Los vehículos apartados expiran automáticamente a las 48 horas.</p>
        </div>
        <div class="topbar-right">
            <button class="btn btn-primary" id="btn-crear-reserva">
                <svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor;"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                Nueva Reserva
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
                    <th>ID</th>
                    <th>Cliente</th>
                    <th>Vehículo reservado</th>
                    <th>Precio del Auto</th>
                    <th>Asesor Registrador</th>
                    <th>Fecha Reserva</th>
                    <th>Límite Expiración (48h)</th>
                    <th>Estado</th>
                    <th style="text-align:right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reservas)): ?>
                <tr>
                    <td colspan="9" style="text-align:center;color:var(--muted);padding:3rem;">
                        No hay reservas registradas en el sistema.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($reservas as $r): 
                    // Evaluar si está vencida en base de datos o si su tiempo ya pasó y sigue activa
                    $estaVencida = ($r['estado'] === 'expirada' || (strtotime($r['fecha_limite']) < time() && $r['estado'] === 'activa'));
                ?>
                <tr class="<?= $estaVencida ? 'row-expired' : '' ?>">
                    <td><strong>#<?= $r['id_reservas'] ?></strong></td>
                    <td>
                        <div style="font-weight:600;color:#fff"><?= htmlspecialchars($r['cliente_nombre'] . ' ' . $r['cliente_apellido']) ?></div>
                        <div style="font-size:.72rem;color:var(--muted)">Doc: <?= htmlspecialchars($r['cliente_doc']) ?></div>
                    </td>
                    <td>
                        <div style="font-weight:600"><?= htmlspecialchars($r['auto_marca'] . ' ' . $r['auto_modelo']) ?></div>
                        <div style="font-size:.72rem;color:var(--muted)">ID Auto: #<?= $r['id_vehiculos'] ?></div>
                    </td>
                    <td><span class="price-text">$<?= number_format($r['auto_precio'], 2) ?></span></td>
                    <td><span style="color:var(--muted);font-size:.84rem"><?= htmlspecialchars($r['asesor_nombre'] ?: 'Sistema') ?></span></td>
                    <td><?= date('d/m/Y H:i', strtotime($r['fecha_reserva'])) ?></td>
                    <td>
                        <div style="display:flex; flex-direction:column;">
                            <span><?= date('d/m/Y H:i', strtotime($r['fecha_limite'])) ?></span>
                            <?php if ($estaVencida): ?>
                                <span class="time-warning">
                                    <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                                    EXPIRADA (48h)
                                </span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-<?= $estaVencida ? 'expirada' : htmlspecialchars($r['estado']) ?>">
                            <?= $estaVencida ? 'expirada' : htmlspecialchars($r['estado']) ?>
                        </span>
                    </td>
                    <td style="text-align:right;">
                        <div style="display:inline-flex; gap:.4rem">
                            <!-- Editar -->
                            <button class="btn btn-secondary btn-icon btn-edit-reservation"
                                    data-id="<?= $r['id_reservas'] ?>"
                                    data-id-cliente="<?= $r['id_clientes'] ?>"
                                    data-id-vehiculo="<?= $r['id_vehiculos'] ?>"
                                    data-observaciones="<?= htmlspecialchars($r['observaciones'] ?? '', ENT_QUOTES) ?>"
                                    data-estado="<?= htmlspecialchars($r['estado']) ?>"
                                    title="Editar">
                                <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                            </button>
                            <!-- Eliminar -->
                            <a href="reservas.php?action=delete&id_reservas=<?= $r['id_reservas'] ?>" class="btn btn-danger btn-icon" title="Eliminar" onclick="return confirm('¿Estás seguro de que deseas eliminar esta reserva? Se liberará el vehículo correspondiente a estado Disponible si la reserva estaba Activa.')">
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
     MODAL: REGISTRAR / EDITAR RESERVA
═══════════════════════════════════════ -->
<div class="modal-overlay" id="reserve-modal">
    <div class="modal">
        <div class="modal-header">
            <h2 id="modal-title">Nueva Reserva</h2>
            <button class="modal-close btn-close-modal">
                <svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
            </button>
        </div>
        
        <form id="reserve-form" method="POST" action="reservas.php">
            <div class="modal-body">
                <input type="hidden" name="form_action" id="form-action" value="crear">
                <input type="hidden" name="id_reservas" id="form-id-reserva" value="">
                
                <div class="form-grid">
                    
                    <div class="form-group">
                        <label for="form-cliente">Cliente *</label>
                        <select name="id_clientes" id="form-cliente" required>
                            <option value="">-- Seleccionar Cliente --</option>
                            <?php foreach ($clientes as $cl): ?>
                                <option value="<?= $cl['id_clientes'] ?>">
                                    <?= htmlspecialchars($cl['nombre'] . ' ' . $cl['apellido']) ?> (Doc: <?= htmlspecialchars($cl['documento']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="form-vehiculo">Vehículo a Apartar *</label>
                        <select name="id_vehiculos" id="form-vehiculo" required>
                            <option value="">-- Seleccionar Vehículo --</option>
                            <?php foreach ($vehiculos as $vh): ?>
                                <option value="<?= $vh['id_vehiculos'] ?>">
                                    <?= htmlspecialchars($vh['marca'] . ' ' . $vh['modelo']) ?> ─ $<?= number_format($vh['precio_lista'], 2) ?> USD [<?= ucfirst($vh['estado']) ?>]
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="form-estado">Estado de Reserva</label>
                        <select name="estado" id="form-estado">
                            <option value="activa">Activa</option>
                            <option value="convertida">Convertida (Compra confirmada)</option>
                            <option value="expirada">Expirada (Vigencia 48h agotada)</option>
                            <option value="cancelada">Cancelada</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="form-observaciones">Observaciones / Detalles</label>
                        <textarea name="observaciones" id="form-observaciones" rows="3" placeholder="Detalles de la reserva, compromisos de pago..."></textarea>
                    </div>

                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-close-modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar Reserva</button>
            </div>
        </form>
    </div>
</div>

<script src="/EntreVentaCarros/assetes/js/reservas.js"></script>

</body>
</html>
