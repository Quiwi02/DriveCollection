<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

require_once "../config/database.php";
$db = getDB();

$errorMsg = '';
$successMsg = '';

// ── PROCESAR ACCIONES DEL CRUD ──────────────────────────────────────

// POST: Crear o Editar Cliente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {
    $action = $_POST['form_action'];
    $id = (int)($_POST['id_clientes'] ?? 0);
    
    $nombre    = trim($_POST['nombre'] ?? '');
    $apellido  = trim($_POST['apellido'] ?? '');
    $tipo_doc  = $_POST['tipo_doc'] ?? 'DNI';
    $documento = trim($_POST['documento'] ?? '');
    $correo    = trim($_POST['correo'] ?? '');
    $telefono  = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');

    if (empty($nombre) || empty($apellido) || empty($documento) || empty($correo)) {
        header("Location: clientes.php?status=missing_fields");
        exit();
    } else {
        // VALIDACIÓN ANTI-DUPLICIDAD
        if ($action === 'crear') {
            // Validar documento único
            $checkDoc = $db->prepare("SELECT COUNT(*) FROM clientes WHERE documento = ?");
            $checkDoc->execute([$documento]);
            if ($checkDoc->fetchColumn() > 0) {
                header("Location: clientes.php?status=duplicate_doc");
                exit();
            }

            // Validar correo único
            $checkMail = $db->prepare("SELECT COUNT(*) FROM clientes WHERE correo = ?");
            $checkMail->execute([$correo]);
            if ($checkMail->fetchColumn() > 0) {
                header("Location: clientes.php?status=duplicate_mail");
                exit();
            }

            // Insertar cliente
            $stmt = $db->prepare("INSERT INTO clientes (nombre, apellido, tipo_doc, documento, correo, telefono, direccion) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nombre, $apellido, $tipo_doc, $documento, $correo, $telefono, $direccion]);
            
            header("Location: clientes.php?status=created");
            exit();

        } elseif ($action === 'editar' && $id > 0) {
            // Validar documento único (excluyendo al cliente actual)
            $checkDoc = $db->prepare("SELECT COUNT(*) FROM clientes WHERE documento = ? AND id_clientes != ?");
            $checkDoc->execute([$documento, $id]);
            if ($checkDoc->fetchColumn() > 0) {
                header("Location: clientes.php?status=duplicate_doc");
                exit();
            }

            // Validar correo único (excluyendo al cliente actual)
            $checkMail = $db->prepare("SELECT COUNT(*) FROM clientes WHERE correo = ? AND id_clientes != ?");
            $checkMail->execute([$correo, $id]);
            if ($checkMail->fetchColumn() > 0) {
                header("Location: clientes.php?status=duplicate_mail");
                exit();
            }

            // Actualizar cliente
            $stmt = $db->prepare("UPDATE clientes SET nombre = ?, apellido = ?, tipo_doc = ?, documento = ?, correo = ?, telefono = ?, direccion = ? WHERE id_clientes = ?");
            $stmt->execute([$nombre, $apellido, $tipo_doc, $documento, $correo, $telefono, $direccion, $id]);
            
            header("Location: clientes.php?status=updated");
            exit();
        }
    }
}

// GET: Eliminar Cliente
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = (int)$_GET['id_clientes'];
    
    $stmt = $db->prepare("DELETE FROM clientes WHERE id_clientes = ?");
    $stmt->execute([$id]);
    
    header("Location: clientes.php?status=deleted");
    exit();
}

// ── CONSULTAR CLIENTES REGISTRADOS ──────────────────────────────────
$stmt = $db->query("SELECT * FROM clientes ORDER BY id_clientes DESC");
$clientes = $stmt->fetchAll();

// Mapeo de alertas
$alerts = [
    'created'        => ['success', 'Cliente registrado correctamente.'],
    'updated'        => ['success', 'Cliente actualizado correctamente.'],
    'deleted'        => ['success', 'Cliente eliminado correctamente.'],
    'duplicate_doc'  => ['danger', 'Error de duplicidad: El número de documento ya pertenece a otro cliente registrado.'],
    'duplicate_mail' => ['danger', 'Error de duplicidad: El correo electrónico ya pertenece a otro cliente registrado.'],
    'missing_fields' => ['danger', 'Error: Por favor completa todos los campos requeridos (*).'],
];
$status = $_GET['status'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar Clientes — Drive Collection</title>
    <meta name="description" content="Gestión de la base de datos de clientes de la concesionaria.">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Estilos -->
    <link rel="stylesheet" href="/EntreVentaCarros/assetes/css/clientes.css">
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

        <a href="clientes.php" class="nav-item active">
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
            <h1>Gestión de Clientes</h1>
            <p>Registra, edita y administra los datos de contacto y facturación de clientes.</p>
        </div>
        <div class="topbar-right">
            <button class="btn btn-primary" id="btn-crear-cliente">
                <svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor;"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                Nuevo Cliente
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
                    <th>Cliente</th>
                    <th>Documento</th>
                    <th>Correo electrónico</th>
                    <th>Teléfono</th>
                    <th>Dirección</th>
                    <th>Registro</th>
                    <th style="text-align:right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($clientes)): ?>
                <tr>
                    <td colspan="7" style="text-align:center;color:var(--muted);padding:3rem;">
                        No hay clientes registrados en la base de datos.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($clientes as $c): ?>
                <tr>
                    <td>
                        <div class="client-cell">
                            <div class="client-avatar-circle">
                                <?= strtoupper(substr($c['nombre'], 0, 1)) ?>
                            </div>
                            <div class="client-meta">
                                <strong><?= htmlspecialchars($c['nombre'] . ' ' . $c['apellido']) ?></strong>
                                <span>ID: #<?= $c['id_clientes'] ?></span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge-doc">
                            <?= htmlspecialchars($c['tipo_doc']) ?>: <?= htmlspecialchars($c['documento']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($c['correo']) ?></td>
                    <td><?= htmlspecialchars($c['telefono'] ?: '—') ?></td>
                    <td><?= htmlspecialchars($c['direccion'] ?: '—') ?></td>
                    <td><?= date('d/m/Y', strtotime($c['fecha_registro'])) ?></td>
                    <td style="text-align:right;">
                        <div style="display:inline-flex; gap:.4rem">
                            <!-- Editar -->
                            <button class="btn btn-secondary btn-icon btn-edit-client"
                                    data-id="<?= $c['id_clientes'] ?>"
                                    data-nombre="<?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>"
                                    data-apellido="<?= htmlspecialchars($c['apellido'], ENT_QUOTES) ?>"
                                    data-tipo-doc="<?= $c['tipo_doc'] ?>"
                                    data-documento="<?= htmlspecialchars($c['documento'], ENT_QUOTES) ?>"
                                    data-correo="<?= htmlspecialchars($c['correo'], ENT_QUOTES) ?>"
                                    data-telefono="<?= htmlspecialchars($c['telefono'] ?? '', ENT_QUOTES) ?>"
                                    data-direccion="<?= htmlspecialchars($c['direccion'] ?? '', ENT_QUOTES) ?>"
                                    title="Editar">
                                <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                            </button>
                            <!-- Eliminar -->
                            <a href="clientes.php?action=delete&id_clientes=<?= $c['id_clientes'] ?>" class="btn btn-danger btn-icon" title="Eliminar" onclick="return confirm('¿Estás seguro de que deseas eliminar este cliente permanentemente? Se eliminarán todas sus reservas y registros vinculados.')">
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
     MODAL: REGISTRAR / EDITAR CLIENTE
═══════════════════════════════════════ -->
<div class="modal-overlay" id="client-modal">
    <div class="modal">
        <div class="modal-header">
            <h2 id="modal-title">Nuevo Cliente</h2>
            <button class="modal-close btn-close-modal">
                <svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
            </button>
        </div>
        
        <form id="client-form" method="POST" action="clientes.php">
            <div class="modal-body">
                <input type="hidden" name="form_action" id="form-action" value="crear">
                <input type="hidden" name="id_clientes" id="form-id-cliente" value="">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="form-nombre">Nombre *</label>
                        <input type="text" name="nombre" id="form-nombre" placeholder="Ej. Juan" required>
                    </div>
                    <div class="form-group">
                        <label for="form-apellido">Apellido *</label>
                        <input type="text" name="apellido" id="form-apellido" placeholder="Ej. Pérez" required>
                    </div>
                    <div class="form-group">
                        <label for="form-tipo-doc">Tipo Documento *</label>
                        <select name="tipo_doc" id="form-tipo-doc">
                            <option value="DNI">DNI</option>
                            <option value="CE">CE</option>
                            <option value="Pasaporte">Pasaporte</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="form-documento">Nº Documento *</label>
                        <input type="text" name="documento" id="form-documento" placeholder="Ej. 74839201" required>
                    </div>
                    <div class="form-group">
                        <label for="form-correo">Correo electrónico *</label>
                        <input type="email" name="correo" id="form-correo" placeholder="juan.perez@email.com" required>
                    </div>
                    <div class="form-group">
                        <label for="form-telefono">Teléfono</label>
                        <input type="text" name="telefono" id="form-telefono" placeholder="Ej. +51 987654321">
                    </div>
                    <div class="form-group form-group-full">
                        <label for="form-direccion">Dirección residencial</label>
                        <textarea name="direccion" id="form-direccion" rows="3" placeholder="Ej. Av. Larco 123, Miraflores"></textarea>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-close-modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<script src="/EntreVentaCarros/assetes/js/clientes.js"></script>

</body>
</html>
