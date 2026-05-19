<?php
require_once __DIR__ . '/../model/Usuario.php';

class AuthController {
    public function login(string $usuario, string $clave): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $usuarioModel = new Usuario();
        $userData = $usuarioModel->login($usuario, $clave);

        if ($userData) {
            // Unificamos a id_usuario (singular)
            $_SESSION['id_usuario'] = $userData['id_usuario'];
            $_SESSION['usuario_nombre'] = $userData['nombre'];
            $_SESSION['usuario_rol'] = $userData['rol'] ?? 'Admin';
            return true;
        }

        return false;
    }
}
?>