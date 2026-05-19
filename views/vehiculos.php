<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

require_once "../config/database.php";
$db = getDB();

// ── ACCIONES AJAX / REDIRECTS ──────────────────────────────────────

// AJAX: Obtener imágenes de un vehículo
if (isset($_GET['action']) && $_GET['action'] === 'get_images') {
    $id = (int)$_GET['id_vehiculos'];
    $stmt = $db->prepare("SELECT * FROM imagenes_vehiculo WHERE id_vehiculos = ? ORDER BY es_principal DESC, id_imagenes_vehiculo ASC");
    $stmt->execute([$id]);
    header('Content-Type: application/json');
    echo json_encode($stmt->fetchAll());
    exit();
}

// GET: Establecer imagen principal
if (isset($_GET['action']) && $_GET['action'] === 'set_principal') {
    $imgId = (int)$_GET['id_imagenes_vehiculo'];
    $vId = (int)$_GET['id_vehiculos'];
    
    // Quitar principal anterior
    $db->prepare("UPDATE imagenes_vehiculo SET es_principal = 0 WHERE id_vehiculos = ?")->execute([$vId]);
    // Asignar nueva principal
    $db->prepare("UPDATE imagenes_vehiculo SET es_principal = 1 WHERE id_imagenes_vehiculo = ?")->execute([$imgId]);
    
    header("Location: vehiculos.php?status=principal_set");
    exit();
}

// GET: Eliminar una imagen
if (isset($_GET['action']) && $_GET['action'] === 'delete_image') {
    $imgId = (int)$_GET['id_imagenes_vehiculo'];
    $vId = (int)$_GET['id_vehiculos'];
    
    $stmt = $db->prepare("SELECT ruta, es_principal FROM imagenes_vehiculo WHERE id_imagenes_vehiculo = ?");
    $stmt->execute([$imgId]);
    $img = $stmt->fetch();
    
    if ($img) {
        $fullPath = __DIR__ . '/../' . $img['ruta'];
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
        $db->prepare("DELETE FROM imagenes_vehiculo WHERE id_imagenes_vehiculo = ?")->execute([$imgId]);
        
        // Si eliminamos la principal, poner la siguiente como principal
        if ($img['es_principal']) {
            $next = $db->prepare("SELECT id_imagenes_vehiculo FROM imagenes_vehiculo WHERE id_vehiculos = ? LIMIT 1");
            $next->execute([$vId]);
            $nextId = $next->fetchColumn();
            if ($nextId) {
                $db->prepare("UPDATE imagenes_vehiculo SET es_principal = 1 WHERE id_imagenes_vehiculo = ?")->execute([$nextId]);
            }
        }
    }
    
    header("Location: vehiculos.php?status=image_deleted");
    exit();
}

// POST: Subir nueva imagen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'upload_image') {
    $vId = (int)($_POST['id_vehiculos'] ?? 0);
    if ($vId > 0 && !empty($_FILES['image'])) {
        $file = $_FILES['image'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($ext, $allowed)) {
                $uploadDir = __DIR__ . '/../uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $filename = uniqid('img_', true) . '.' . $ext;
                $dbPath = 'uploads/' . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                    // Si es la primera imagen, establecerla como principal
                    $check = $db->prepare("SELECT COUNT(*) FROM imagenes_vehiculo WHERE id_vehiculos = ?");
                    $check->execute([$vId]);
                    $isFirst = ($check->fetchColumn() == 0);
                    
                    $stmt = $db->prepare("INSERT INTO imagenes_vehiculo (id_vehiculos, ruta, es_principal) VALUES (?, ?, ?)");
                    $stmt->execute([$vId, $dbPath, $isFirst ? 1 : 0]);
                    
                    header("Location: vehiculos.php?status=image_uploaded");
                    exit();
                }
            }
        }
    }
    header("Location: vehiculos.php?status=upload_failed");
    exit();
}

// POST: Crear o Editar Vehículo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {
    $action = $_POST['form_action'];
    $id = (int)($_POST['id_vehiculos'] ?? 0);
    
    $marca       = trim($_POST['marca'] ?? '');
    $modelo      = trim($_POST['modelo'] ?? '');
    $anio        = (int)($_POST['anio'] ?? 0);
    $precio      = (float)($_POST['precio_lista'] ?? 0);
    $color       = trim($_POST['color'] ?? '');
    $kilometraje = (int)($_POST['kilometraje'] ?? 0);
    $trans       = $_POST['transmision'] ?? 'Automática';
    $comb        = $_POST['combustible'] ?? 'Gasolina';
    $desc        = trim($_POST['descripcion'] ?? '');
    $estado      = $_POST['estado'] ?? 'disponible';

    if (!empty($marca) && !empty($modelo) && $anio > 0 && $precio > 0) {
        if ($action === 'crear') {
            $stmt = $db->prepare("INSERT INTO vehiculos (marca, modelo, anio, precio_lista, color, kilometraje, transmision, combustible, descripcion, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$marca, $modelo, $anio, $precio, $color, $kilometraje, $trans, $comb, $desc, $estado]);
            header("Location: vehiculos.php?status=created");
            exit();
        } elseif ($action === 'editar' && $id > 0) {
            $stmt = $db->prepare("UPDATE vehiculos SET marca = ?, modelo = ?, anio = ?, precio_lista = ?, color = ?, kilometraje = ?, transmision = ?, combustible = ?, descripcion = ?, estado = ? WHERE id_vehiculos = ?");
            $stmt->execute([$marca, $modelo, $anio, $precio, $color, $kilometraje, $trans, $comb, $desc, $estado, $id]);
            header("Location: vehiculos.php?status=updated");
            exit();
        }
    }
}

// GET: Eliminar Vehículo
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = (int)$_GET['id_vehiculos'];
    
    // Eliminar fotos del disco primero
    $stmt = $db->prepare("SELECT ruta FROM imagenes_vehiculo WHERE id_vehiculos = ?");
    $stmt->execute([$id]);
    $images = $stmt->fetchAll();
    foreach ($images as $img) {
        $fullPath = __DIR__ . '/../' . $img['ruta'];
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }
    
    // Eliminar registro del carro (la BD cascada automáticamente la tabla de imágenes)
    $db->prepare("DELETE FROM vehiculos WHERE id_vehiculos = ?")->execute([$id]);
    
    header("Location: vehiculos.php?status=deleted");
    exit();
}

// ── CONSULTAR VEHÍCULOS DEL CATÁLOGO ───────────────────────────────
$stmt = $db->query("
    SELECT v.*, img.ruta AS imagen_principal
    FROM vehiculos v
    LEFT JOIN imagenes_vehiculo img
           ON img.id_vehiculos = v.id_vehiculos AND img.es_principal = 1
    ORDER BY v.id_vehiculos DESC
");
$vehiculos = $stmt->fetchAll();

// Mapeo de alertas
$alerts = [
    'created'        => ['success', 'Vehículo registrado correctamente.'],
    'updated'        => ['success', 'Vehículo actualizado correctamente.'],
    'deleted'        => ['success', 'Vehículo eliminado correctamente.'],
    'image_uploaded' => ['success', 'Imagen del vehículo subida correctamente.'],
    'image_deleted'  => ['success', 'Imagen eliminada correctamente.'],
    'principal_set'  => ['success', 'Se ha establecido la imagen principal.'],
    'upload_failed'  => ['danger', 'Fallo al subir la imagen. Formatos permitidos: JPG, PNG y WEBP.'],
];
$status = $_GET['status'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar Vehículos — Drive Collection</title>
    <meta name="description" content="Gestión del catálogo de vehículos de la concesionaria.">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Estilos -->
    <link rel="stylesheet" href="/EntreVentaCarros/assetes/css/vehiculos.css">
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
        
        <a href="vehiculos.php" class="nav-item active">
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
            <h1>Gestión de Vehículos</h1>
            <p>Monitorea, crea y edita los vehículos del catálogo comercial.</p>
        </div>
        <div class="topbar-right">
            <button class="btn btn-primary" id="btn-crear-vehiculo">
                <svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor;"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                Nuevo Vehículo
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
                    <th>Vehículo</th>
                    <th>Año</th>
                    <th>Color</th>
                    <th>Kilometraje</th>
                    <th>Transmisión</th>
                    <th>Combustible</th>
                    <th>Precio de Lista</th>
                    <th>Estado</th>
                    <th style="text-align:right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($vehiculos)): ?>
                <tr>
                    <td colspan="9" style="text-align:center;color:var(--muted);padding:3rem;">
                        No hay vehículos registrados en la base de datos.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($vehiculos as $v): ?>
                <tr>
                    <td>
                        <div class="vehicle-cell">
                            <?php if (!empty($v['imagen_principal'])): ?>
                                <img src="/EntreVentaCarros/<?= htmlspecialchars($v['imagen_principal']) ?>" class="vehicle-thumb-mini" alt="Miniatura">
                            <?php else: ?>
                                <div class="vehicle-thumb-mini-placeholder">
                                    <svg viewBox="0 0 24 24"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.85 7h10.29l1.08 3.11H5.77L6.85 7zM19 17H5v-5h14v5z"/></svg>
                                </div>
                            <?php endif; ?>
                            <div class="vehicle-meta">
                                <strong><?= htmlspecialchars($v['marca'] . ' ' . $v['modelo']) ?></strong>
                                <span>ID: #<?= $v['id_vehiculos'] ?></span>
                            </div>
                        </div>
                    </td>
                    <td><?= $v['anio'] ?></td>
                    <td><?= htmlspecialchars($v['color'] ?: 'N/D') ?></td>
                    <td><?= number_format($v['kilometraje']) ?> Km</td>
                    <td><?= htmlspecialchars($v['transmision']) ?></td>
                    <td><?= htmlspecialchars($v['combustible']) ?></td>
                    <td><span class="price-text">$<?= number_format($v['precio_lista'], 2) ?></span></td>
                    <td>
                        <span class="badge badge-<?= $v['estado'] ?>">
                            <?= $v['estado'] ?>
                        </span>
                    </td>
                    <td style="text-align:right;">
                        <div style="display:inline-flex; gap:.4rem">
                            <!-- Administrar fotos -->
                            <button class="btn btn-secondary btn-icon btn-images-vehicle" data-id="<?= $v['id_vehiculos'] ?>" title="Galería / Fotos">
                                <svg viewBox="0 0 24 24"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
                            </button>
                            <!-- Editar -->
                            <button class="btn btn-secondary btn-icon btn-edit-vehicle"
                                    data-id="<?= $v['id_vehiculos'] ?>"
                                    data-marca="<?= htmlspecialchars($v['marca'], ENT_QUOTES) ?>"
                                    data-modelo="<?= htmlspecialchars($v['modelo'], ENT_QUOTES) ?>"
                                    data-anio="<?= $v['anio'] ?>"
                                    data-precio="<?= $v['precio_lista'] ?>"
                                    data-color="<?= htmlspecialchars($v['color'] ?? '', ENT_QUOTES) ?>"
                                    data-kilometraje="<?= $v['kilometraje'] ?>"
                                    data-transmision="<?= $v['transmision'] ?>"
                                    data-combustible="<?= $v['combustible'] ?>"
                                    data-descripcion="<?= htmlspecialchars($v['descripcion'] ?? '', ENT_QUOTES) ?>"
                                    data-estado="<?= $v['estado'] ?>"
                                    title="Editar datos">
                                <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                            </button>
                            <!-- Eliminar -->
                            <a href="vehiculos.php?action=delete&id_vehiculos=<?= $v['id_vehiculos'] ?>" class="btn btn-danger btn-icon" title="Eliminar" onclick="return confirm('¿Estás seguro de que deseas eliminar este vehículo permanentemente?')">
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
     MODAL: REGISTRAR / EDITAR VEHÍCULO
═══════════════════════════════════════ -->
<div class="modal-overlay" id="vehicle-modal">
    <div class="modal">
        <div class="modal-header">
            <h2 id="modal-title">Nuevo Vehículo</h2>
            <button class="modal-close btn-close-modal">
                <svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
            </button>
        </div>
        
        <form id="vehicle-form" method="POST" action="vehiculos.php">
            <div class="modal-body">
                <input type="hidden" name="form_action" id="form-action" value="crear">
                <input type="hidden" name="id_vehiculos" id="form-id-vehiculo" value="">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="form-marca">Marca *</label>
                        <input type="text" name="marca" id="form-marca" placeholder="Ej. Toyota" required>
                    </div>
                    <div class="form-group">
                        <label for="form-modelo">Modelo *</label>
                        <input type="text" name="modelo" id="form-modelo" placeholder="Ej. Corolla" required>
                    </div>
                    <div class="form-group">
                        <label for="form-anio">Año *</label>
                        <input type="number" name="anio" id="form-anio" min="1950" max="<?= date('Y') + 1 ?>" placeholder="Ej. 2023" required>
                    </div>
                    <div class="form-group">
                        <label for="form-precio">Precio Lista (USD) *</label>
                        <input type="number" step="0.01" name="precio_lista" id="form-precio" placeholder="Ej. 24900" required>
                    </div>
                    <div class="form-group">
                        <label for="form-color">Color</label>
                        <input type="text" name="color" id="form-color" placeholder="Ej. Negro Metálico">
                    </div>
                    <div class="form-group">
                        <label for="form-kilometraje">Kilometraje</label>
                        <input type="number" name="kilometraje" id="form-kilometraje" placeholder="Ej. 15000">
                    </div>
                    <div class="form-group">
                        <label for="form-transmision">Transmisión</label>
                        <select name="transmision" id="form-transmision">
                            <option value="Manual">Manual</option>
                            <option value="Automática">Automática</option>
                            <option value="CVT">CVT</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="form-combustible">Combustible</label>
                        <select name="combustible" id="form-combustible">
                            <option value="Gasolina">Gasolina</option>
                            <option value="Diésel">Diésel</option>
                            <option value="Híbrido">Híbrido</option>
                            <option value="Eléctrico">Eléctrico</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="form-estado">Estado comercial</label>
                        <select name="estado" id="form-estado">
                            <option value="disponible">Disponible</option>
                            <option value="reservado">Reservado</option>
                            <option value="vendido">Vendido</option>
                        </select>
                    </div>
                    <div class="form-group form-group-full">
                        <label for="form-descripcion">Descripción corta</label>
                        <textarea name="descripcion" id="form-descripcion" rows="3" placeholder="Información técnica destacada, equipamiento..."></textarea>
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

<!-- ═══════════════════════════════════════
     MODAL: GALERÍA DE IMÁGENES
═══════════════════════════════════════ -->
<div class="modal-overlay" id="image-modal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h2>Galería del Vehículo</h2>
            <button class="modal-close btn-close-modal">
                <svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
            </button>
        </div>
        
        <div class="modal-body">
            <!-- Formulario oculto de carga -->
            <form id="image-upload-form" method="POST" action="vehiculos.php?action=upload_image" enctype="multipart/form-data">
                <input type="hidden" name="id_vehiculos" id="image-vehicle-id" value="">
                
                <div class="upload-container" id="upload-trigger">
                    <svg viewBox="0 0 24 24"><path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"/></svg>
                    <h3>Subir nueva fotografía</h3>
                    <p>Formatos admitidos: JPG, PNG, WEBP · Límite sugerido 5MB</p>
                    <input type="file" name="image" id="image-upload-input" accept="image/*">
                </div>
            </form>

            <h3 style="font-size:1rem;color:#fff;margin-bottom:.5rem;">Imágenes actuales</h3>
            <div class="image-manager-grid" id="images-grid">
                <!-- Se llena dinámicamente con AJAX (vehiculos.js) -->
            </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-close-modal">Cerrar Galería</button>
        </div>
    </div>
</div>

<script src="/EntreVentaCarros/assetes/js/vehiculos.js"></script>

</body>
</html>
