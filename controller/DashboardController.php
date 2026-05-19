<?php
session_start();

// Protección de sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /EntreVentaCarros/controller/AuthController.php');
    exit;
}

require_once __DIR__ . '/../model/DashboardModel.php';

$model = new DashboardModel();

$stats = [
    'carros'       => $model->getTotalCarrosDisponibles(),
    'reservas'     => $model->getReservasActivas(),
    'ventas_mes'   => $model->getVentasDelMes(),
    'asesores'     => $model->getTotalAsesores(),
    'cotizaciones' => $model->getCotizacionesPendientes(),
    'ingresos'     => $model->getIngresosTotales(),
];

$ultimasVentas      = $model->getUltimasVentas(5);
$ultimasSolicitudes = $model->getUltimasSolicitudes(5);

require_once __DIR__ . '/../views/dashboard.php';
