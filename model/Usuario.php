<?php
require_once __DIR__ . '/../config/database.php';

class Usuario {
    private $conn;

    public function __construct() {
        $this->conn = getDB();
    }

    public function login(string $nombre, string $clave) {
        $sql = "SELECT * FROM usuarios WHERE nombre = :nombre LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $nombre = htmlspecialchars(strip_tags($nombre));
        $stmt->bindParam(':nombre', $nombre);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($clave === $row['clave']) {
                return $row;
            }
        }
        return false;
    }
}
?>
