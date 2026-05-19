<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Drive Collection — Iniciar Sesión</title>
    <meta name="description" content="Acceso al panel administrativo de Drive Collection, concesionaria de vehículos.">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Estilos -->
    <link rel="stylesheet" href="/EntreVentaCarros/assetes/css/login.css">
</head>
<body>

<div class="bg-mesh"></div>
<div class="bg-lines"></div>

<div class="card-wrapper">
    <div class="card">

        <!-- Brand -->
        <div class="brand">
            <div class="brand-icon">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.85 7h10.29l1.08 3.11H5.77L6.85 7zM19 17H5v-5h14v5zM7.5 14c-.83 0-1.5.67-1.5 1.5S6.67 17 7.5 17 9 16.33 9 15.5 8.33 14 7.5 14zm9 0c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5z"/>
                </svg>
            </div>
            <h1>Drive Collection</h1>
            <p>Panel Administrativo · Acceso seguro</p>
        </div>

        <!-- Error -->
        <?php if (!empty($error)): ?>
        <div class="alert-error" role="alert">
            <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <!-- Form -->
        <form id="login-form" method="POST" action="/EntreVentaCarros/controller/AuthController.php?action=login" novalidate>

            <div class="form-group">
                <label for="correo">Correo electrónico</label>
                <div class="input-wrap">
                    <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4-8 5-8-5V6l8 5 8-5v2z"/></svg>
                    <input
                        type="email"
                        id="correo"
                        name="correo"
                        placeholder="admin@drivecollection.com"
                        value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>"
                        required
                        autocomplete="email"
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="clave">Contraseña</label>
                <div class="input-wrap">
                    <svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                    <input
                        type="password"
                        id="clave"
                        name="clave"
                        placeholder="••••••••"
                        required
                        autocomplete="current-password"
                    >
                    <button type="button" class="toggle-pass" id="toggle-pass" aria-label="Mostrar contraseña">
                        <svg viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login" id="btn-login">Iniciar Sesión</button>

        </form>

        <p class="login-footer">© <?= date('Y') ?> Drive Collection · Todos los derechos reservados</p>
    </div>
</div>

<script>
    // Toggle visibilidad contraseña
    const toggle = document.getElementById('toggle-pass');
    const inputClave = document.getElementById('clave');

    toggle.addEventListener('click', () => {
        const isPass = inputClave.type === 'password';
        inputClave.type = isPass ? 'text' : 'password';
        toggle.setAttribute('aria-label', isPass ? 'Ocultar contraseña' : 'Mostrar contraseña');
    });

    // Validación básica cliente
    document.getElementById('login-form').addEventListener('submit', function (e) {
        const correo = document.getElementById('correo').value.trim();
        const clave  = document.getElementById('clave').value.trim();
        if (!correo || !clave) {
            e.preventDefault();
            alert('Por favor completa correo y contraseña.');
        }
    });
</script>

</body>
</html>
