<?php
session_start();

require_once __DIR__ . '/../model/UsuarioModel.php';

class AuthController {

    private UsuarioModel $model;

    public function __construct() {
        $this->model = new UsuarioModel();
    }

    /**
     * Muestra el formulario de login.
     */
    public function showLogin(): void {
        if (isset($_SESSION['usuario_id'])) {
            header('Location: /EntreVentaCarros/views/dashboard.php');
            exit;
        }
        require_once __DIR__ . '/../views/login.php';
    }

    /**
     * Procesa el POST del formulario de login.
     */
    public function processLogin(): void {
        $correo = trim($_POST['correo'] ?? '');
        $clave  = trim($_POST['clave']  ?? '');
        $error  = '';

        if (empty($correo) || empty($clave)) {
            $error = 'Por favor completa todos los campos.';
        } else {
            $usuario = $this->model->buscarPorCorreo($correo);

            // Comparación directa en texto plano
            if ($usuario && $usuario['clave'] === $clave) {
                $_SESSION['usuario_id']     = $usuario['id_usuario'];
                $_SESSION['usuario_nombre'] = $usuario['nombre'];
                $_SESSION['usuario_rol']    = $usuario['rol'];
                $_SESSION['usuario_foto']   = $usuario['foto'] ?? '';

                header('Location: /EntreVentaCarros/views/dashboard.php');
                exit;
            } else {
                $error = 'Correo o contraseña incorrectos.';
            }
        }

        require_once __DIR__ . '/../views/login.php';
    }

    /**
     * Cierra la sesión y redirige al login.
     */
    public function logout(): void {
        session_unset();
        session_destroy();
        header('Location: /EntreVentaCarros/index.php');
        exit;
    }
}

// --- Punto de entrada ---
$controller = new AuthController();

$action = $_GET['action'] ?? 'showLogin';

match ($action) {
    'login'  => $controller->processLogin(),
    'logout' => $controller->logout(),
    default  => $controller->showLogin(),
};
