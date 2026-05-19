<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

require_once "../config/database.php";
$db = getDB();

// ── CONSULTAR TOTAL ACUMULADO DE INGRESOS HISTÓRICOS ────────────────
// "Las ventas deben mostrar un banner superior con el total acumulado de ingresos históricos"
$stmtIngresos = $db->query("SELECT SUM(monto) FROM ventas");
$ingresosHistoricos = (float)$stmtIngresos->fetchColumn();

// ── PROCESAR ACCIONES DEL CRUD ──────────────────────────────────────

// POST: Registrar o Editar Venta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {
    $action = $_POST['form_action'];
    $id = (int)($_POST['id_ventas'] ?? 0);
    
    $id_clientes   = (int)($_POST['id_clientes'] ?? 0);
    $id_vehiculos  = (int)($_POST['id_vehiculos'] ?? 0);
    $monto         = (float)($_POST['monto'] ?? 0);
    $metodo_pago   = $_POST['metodo_pago'] ?? 'Contado';
    $observaciones = trim($_POST['observaciones'] ?? '');
    $id_usuario    = (int)$_SESSION['id_usuario'];

    if ($id_clientes > 0 && $id_vehiculos > 0 && $monto > 0) {
        if ($action === 'crear') {
            // Insertar venta
            $stmt = $db->prepare("INSERT INTO ventas (id_clientes, id_vehiculos, id_usuario, monto, metodo_pago, observaciones) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id_clientes, $id_vehiculos, $id_usuario, $monto, $metodo_pago, $observaciones]);
            
            // AUTOMATIZACIÓN: Cambiar estado del vehículo a 'vendido'
            $db->prepare("UPDATE vehiculos SET estado = 'vendido' WHERE id_vehiculos = ?")->execute([$id_vehiculos]);

            header("Location: ventas.php?status=created");
            exit();

        } elseif ($action === 'editar' && $id > 0) {
            // Obtener vehículo antiguo
            $stmtOld = $db->prepare("SELECT id_vehiculos FROM ventas WHERE id_ventas = ?");
            $stmtOld->execute([$id]);
            $oldVehId = (int)$stmtOld->fetchColumn();

            // Actualizar datos de la venta
            $stmt = $db->prepare("UPDATE ventas SET id_clientes = ?, id_vehiculos = ?, id_usuario = ?, monto = ?, metodo_pago = ?, observaciones = ? WHERE id_ventas = ?");
            $stmt->execute([$id_clientes, $id_vehiculos, $id_usuario, $monto, $metodo_pago, $observaciones, $id]);

            // Sincronizar vehículos si cambió
            if ($oldVehId > 0 && $oldVehId !== $id_vehiculos) {
                // Liberar el vehículo antiguo
                $db->prepare("UPDATE vehiculos SET estado = 'disponible' WHERE id_vehiculos = ?")->execute([$oldVehId]);
                // Marcar el nuevo como vendido
                $db->prepare("UPDATE vehiculos SET estado = 'vendido' WHERE id_vehiculos = ?")->execute([$id_vehiculos]);
            }

            header("Location: ventas.php?status=updated");
            exit();
        }
    }
}

// GET: Eliminar Registro de Venta
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = (int)$_GET['id_ventas'];
    
    // Obtener vehículo asociado
    $stmtVeh = $db->prepare("SELECT id_vehiculos FROM ventas WHERE id_ventas = ?");
    $stmtVeh->execute([$id]);
    $vehId = (int)$stmtVeh->fetchColumn();
    
    if ($vehId > 0) {
        // AUTOMATIZACIÓN: Liberar carro a disponible
        $db->prepare("UPDATE vehiculos SET estado = 'disponible' WHERE id_vehiculos = ?")->execute([$vehId]);
    }

    $stmt = $db->prepare("DELETE FROM ventas WHERE id_ventas = ?");
    $stmt->execute([$id]);
    
    header("Location: ventas.php?status=deleted");
    exit();
}

// ── CONSULTAS GENERALES ─────────────────────────────────────────────

// Listado de Ventas con cruce de tablas
$stmt = $db->query("
    SELECT v.*, 
           c.nombre AS cliente_nombre, c.apellido AS cliente_apellido, c.documento AS cliente_doc,
           vh.marca AS auto_marca, vh.modelo AS auto_modelo, vh.precio_lista AS auto_precio_original,
           u.nombre AS asesor_nombre
    FROM ventas v
    INNER JOIN clientes c ON c.id_clientes = v.id_clientes
    INNER JOIN vehiculos vh ON vh.id_vehiculos = v.id_vehiculos
    LEFT JOIN usuarios u ON u.id_usuario = v.id_usuario
    ORDER BY v.id_ventas DESC
");
$ventas = $stmt->fetchAll();

// Listado de Clientes
$clientes = $db->query("SELECT id_clientes, nombre, apellido, documento FROM clientes ORDER BY nombre ASC")->fetchAll();

// Listado de Vehículos (incluyendo disponibles y vendidos para permitir edición)
$vehiculos = $db->query("SELECT id_vehiculos, marca, modelo, precio_lista, estado FROM vehiculos ORDER BY marca ASC")->fetchAll();

// Mapeo de alertas
$alerts = [
    'created' => ['success', 'Venta registrada con éxito. El vehículo asociado ha sido catalogado como "Vendido".'],
    'updated' => ['success', 'Registro de venta actualizado correctamente.'],
    'deleted' => ['success', 'Registro de venta eliminado de la base de datos. El vehículo se ha liberado a "Disponible".'],
];
$status = $_GET['status'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulo de Ventas — Drive Collection</title>
    <meta name="description" content="Historial de operaciones de venta y facturación interna de la concesionaria.">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Estilos -->
    <link rel="stylesheet" href="/EntreVentaCarros/assetes/css/ventas.css">
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

        <a href="ventas.php" class="nav-item active">
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
            <h1>Módulo de Ventas</h1>
            <p>Control de operaciones de venta, facturación interna y facturación de stock.</p>
        </div>
        <div class="topbar-right">
            <button class="btn btn-primary" id="btn-registrar-venta">
                <svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor;"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                Registrar Venta
            </button>
        </div>
    </header>

    <!-- 🌟 BANNER SUPERIOR DE INGRESOS HISTÓRICOS 🌟 -->
    <section class="revenue-banner">
        <div class="revenue-left">
            <div class="revenue-icon-box">
                <svg viewBox="0 0 24 24"><path d="M21 18v1c0 1.1-.9 2-2 2H5c-1.11 0-2-.9-2-2V5c0-1.1.89-2 2-2h14c1.1 0 2 .9 2 2v1h-9c-1.11 0-2 .9-2 2v8c0 1.1.89 2 2 2h9zm-9-2h10V8H12v8zm4-2.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>
            </div>
            <div class="revenue-meta">
                <h3>Total de Ingresos Históricos</h3>
                <p>Monto acumulado por concepto de facturaciones confirmadas en Drive Collection</p>
            </div>
        </div>
        <div class="revenue-amount">
            $<?= number_format($ingresosHistoricos, 2) ?> USD
        </div>
    </section>

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
                    <th>Vehículo adquirido</th>
                    <th>Monto Transacción</th>
                    <th>Asesor Comercial</th>
                    <th>Método de Pago</th>
                    <th>Fecha Operación</th>
                    <th>Origen</th>
                    <th style="text-align:right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ventas)): ?>
                <tr>
                    <td colspan="9" style="text-align:center;color:var(--muted);padding:3rem;">
                        No hay ventas registradas en la base de datos.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($ventas as $v): ?>
                <tr>
                    <td><strong>#<?= $v['id_ventas'] ?></strong></td>
                    <td>
                        <div style="font-weight:600;color:#fff"><?= htmlspecialchars($v['cliente_nombre'] . ' ' . $v['cliente_apellido']) ?></div>
                        <div style="font-size:.72rem;color:var(--muted)">Doc: <?= htmlspecialchars($v['cliente_doc']) ?></div>
                    </td>
                    <td>
                        <div style="font-weight:600"><?= htmlspecialchars($v['auto_marca'] . ' ' . $v['auto_modelo']) ?></div>
                        <div style="font-size:.72rem;color:var(--muted)">ID Auto: #<?= $v['id_vehiculos'] ?></div>
                    </td>
                    <td><span class="price-text">$<?= number_format($v['monto'], 2) ?></span></td>
                    <td><span style="color:#fff;font-size:.84rem"><?= htmlspecialchars($v['asesor_nombre'] ?: 'Sistema') ?></span></td>
                    <td>
                        <span class="badge badge-<?= strtolower($v['metodo_pago']) ?>">
                            <?= htmlspecialchars($v['metodo_pago']) ?>
                        </span>
                    </td>
                    <td><?= date('d/m/Y H:i', strtotime($v['fecha_venta'])) ?></td>
                    <td>
                        <?php if ($v['id_reservas'] > 0): ?>
                            <span class="linked-tag" title="Venta originada de una reserva previa">
                                <svg viewBox="0 0 24 24"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>
                                Reserva #<?= $v['id_reservas'] ?>
                            </span>
                        <?php elseif ($v['id_solicitudes'] > 0): ?>
                            <span class="linked-tag" title="Venta originada de un lead web">
                                <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/></svg>
                                Web #<?= $v['id_solicitudes'] ?>
                            </span>
                        <?php else: ?>
                            <span style="color:var(--muted);font-size:.75rem">Directo</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;">
                        <div style="display:inline-flex; gap:.4rem">
                            <!-- Editar -->
                            <button class="btn btn-secondary btn-icon btn-edit-sale"
                                    data-id="<?= $v['id_ventas'] ?>"
                                    data-id-cliente="<?= $v['id_clientes'] ?>"
                                    data-id-vehiculo="<?= $v['id_vehiculos'] ?>"
                                    data-monto="<?= $v['monto'] ?>"
                                    data-metodo-pago="<?= htmlspecialchars($v['metodo_pago']) ?>"
                                    data-observaciones="<?= htmlspecialchars($v['observaciones'] ?? '', ENT_QUOTES) ?>"
                                    title="Editar">
                                <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                            </button>
                            <!-- Eliminar -->
                            <a href="ventas.php?action=delete&id_ventas=<?= $v['id_ventas'] ?>" class="btn btn-danger btn-icon" title="Eliminar" onclick="return confirm('¿Estás seguro de que deseas eliminar esta venta permanentemente? El vehículo volverá a estar Disponible en Stock.')">
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
     MODAL: REGISTRAR / EDITAR VENTA
═══════════════════════════════════════ -->
<div class="modal-overlay" id="sale-modal">
    <div class="modal">
        <div class="modal-header">
            <h2 id="modal-title">Registrar Venta</h2>
            <button class="modal-close btn-close-modal">
                <svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
            </button>
        </div>
        
        <form id="sale-form" method="POST" action="ventas.php">
            <div class="modal-body">
                <input type="hidden" name="form_action" id="form-action" value="crear">
                <input type="hidden" name="id_ventas" id="form-id-venta" value="">
                
                <div class="form-grid">
                    
                    <div class="form-group">
                        <label for="form-cliente">Cliente Comprador *</label>
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
                        <label for="form-vehiculo">Vehículo Vendido *</label>
                        <select name="id_vehiculos" id="form-vehiculo" required>
                            <option value="">-- Seleccionar Vehículo --</option>
                            <?php foreach ($vehiculos as $vh): ?>
                                <option value="<?= $vh['id_vehiculos'] ?>" data-precio="<?= $vh['precio_lista'] ?>">
                                    <?= htmlspecialchars($vh['marca'] . ' ' . $vh['modelo']) ?> ─ $<?= number_format($vh['precio_lista'], 2) ?> USD [<?= ucfirst($vh['estado']) ?>]
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="form-monto">Monto de Venta ($ USD) *</label>
                            <input type="number" step="0.01" name="monto" id="form-monto" placeholder="Ej. 25000.00" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="form-metodo-pago">Método de Pago *</label>
                            <select name="metodo_pago" id="form-metodo-pago" required>
                                <option value="Contado">Contado</option>
                                <option value="Crédito">Crédito</option>
                                <option value="Leasing">Leasing</option>
                                <option value="Permuta">Permuta</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="form-observaciones">Observaciones / Detalles de Facturación</label>
                        <textarea name="observaciones" id="form-observaciones" rows="3" placeholder="Detalles de facturas, cheques, números de transacción bancarios..."></textarea>
                    </div>

                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-close-modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Confirmar Venta</button>
            </div>
        </form>
    </div>
</div>

<script src="/EntreVentaCarros/assetes/js/ventas.js"></script>

</body>
</html>
