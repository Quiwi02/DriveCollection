<?php
require_once __DIR__ . '/../config/database.php';

class UsuarioModel {

    /**
     * Busca un usuario por correo electrónico.
     * Retorna el array del usuario o false si no existe.
     */
    public function buscarPorCorreo(string $correo): array|false {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE correo = :correo AND activo = 1 LIMIT 1");
        $stmt->execute([':correo' => $correo]);
        return $stmt->fetch();
    }
}
