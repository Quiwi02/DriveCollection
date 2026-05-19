<?php
require_once __DIR__ . '/../config/database.php';

class DashboardModel {

    public function getTotalCarrosDisponibles(): int {
        $pdo = getDB();
        $stmt = $pdo->query("SELECT COUNT(*) FROM vehiculos WHERE estado = 'disponible'");
        return (int) $stmt->fetchColumn();
    }

    public function getReservasActivas(): int {
        $pdo = getDB();
        $stmt = $pdo->query("SELECT COUNT(*) FROM reservas WHERE estado = 'activa'");
        return (int) $stmt->fetchColumn();
    }

    public function getVentasDelMes(): int {
        $pdo = getDB();
        $stmt = $pdo->query("SELECT COUNT(*) FROM ventas WHERE MONTH(fecha_venta) = MONTH(NOW()) AND YEAR(fecha_venta) = YEAR(NOW())");
        return (int) $stmt->fetchColumn();
    }

    public function getTotalAsesores(): int {
        $pdo = getDB();
        $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'asesor' AND activo = 1");
        return (int) $stmt->fetchColumn();
    }

    public function getCotizacionesPendientes(): int {
        $pdo = getDB();
        $stmt = $pdo->query("SELECT COUNT(*) FROM solicitudes WHERE estado = 'pendiente'");
        return (int) $stmt->fetchColumn();
    }

    public function getIngresosTotales(): float {
        $pdo = getDB();
        $stmt = $pdo->query("SELECT COALESCE(SUM(monto), 0) FROM ventas");
        return (float) $stmt->fetchColumn();
    }

    public function getUltimasVentas(int $limite = 5): array {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT v.id_ventas, v.monto, v.metodo_pago, v.fecha_venta,
                   c.nombre AS cliente_nombre, c.apellido AS cliente_apellido,
                   ve.marca, ve.modelo
            FROM ventas v
            JOIN clientes c  ON c.id_clientes  = v.id_clientes
            JOIN vehiculos ve ON ve.id_vehiculos = v.id_vehiculos
            ORDER BY v.fecha_venta DESC
            LIMIT :limite
        ");
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getUltimasSolicitudes(int $limite = 5): array {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT s.id_solicitudes, s.nombre, s.correo, s.metodo_adquisicion,
                   s.estado, s.fecha_solicitud,
                   ve.marca, ve.modelo
            FROM solicitudes s
            JOIN vehiculos ve ON ve.id_vehiculos = s.id_vehiculos
            ORDER BY s.fecha_solicitud DESC
            LIMIT :limite
        ");
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
