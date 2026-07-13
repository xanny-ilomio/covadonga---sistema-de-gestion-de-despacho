<?php

require_once '/var/www/backend/src/lib/fpdf.php';

class GuideController {

    private Guide $guideModel;

    public function __construct() {
        $this->guideModel = new Guide();
    }

    // GET /guides
    public function index(): void {
        AuthMiddleware::handle();
        Response::success($this->guideModel->findAll());
    }

    // GET /guides/{id}
    public function show(int $id): void {
        AuthMiddleware::handle();
        $guide = $this->guideModel->findById($id);
        if (!$guide) Response::notFound("Guía con ID {$id} no encontrada");
        Response::success($guide);
    }

    // POST /guides
    public function store(): void {
        $authUser = AuthMiddleware::handle();
        if ($authUser['rol'] !== 'despacho') {
            Response::forbidden('Solo despacho puede generar guías');
        }

        $body     = json_decode(file_get_contents('php://input'), true);
        $routeId  = $body['route_id']  ?? null;
        $driverId = $body['driver_id'] ?? null;
        $truckId  = $body['truck_id']  ?? null;

        if (!$routeId)  Response::error('route_id es requerido', 422);
        if (!$driverId) Response::error('driver_id es requerido', 422);
        if (!$truckId)  Response::error('truck_id es requerido', 422);

        $orders = $this->guideModel->getAssignedOrdersByRoute((int) $routeId);
        if (empty($orders)) {
            Response::error('No hay pedidos en estado Asignado para esta ruta', 422);
        }

        try {
            $guideId = $this->guideModel->create((int) $routeId, (int) $driverId, (int) $truckId);
            $guide   = $this->guideModel->findById($guideId);
            Response::success($guide, 'Guía generada correctamente', 201);
        } catch (Exception $e) {
            Response::serverError('Error al generar la guía: ' . $e->getMessage());
        }
    }

    // GET /guides/{id}/pdf
    public function pdf(int $id): void {
        AuthMiddleware::handle();

        $guide = $this->guideModel->findById($id);
        if (!$guide) Response::notFound("Guía con ID {$id} no encontrada");

        // ─── Datos ────────────────────────────────────────────────────────────
        $guideNum    = htmlspecialchars($guide['GUIDE_NUMBER']);
        $rutaNombre  = strtoupper($guide['NAME_ROUTE']);
        $choferNombre= $guide['driver_name'];
        $choferCI    = $guide['driver_ci'];
        $placa       = $guide['PLATE'];
        $marca       = $guide['BRAND'];
        $capacidad   = number_format((float)$guide['CAPACITY'], 0);
        $fecha       = $guide['EMISSION_DATE'];
        $totalKg     = number_format((float)$guide['TOTAL_WEIGHT'], 2);
        $orders      = $guide['orders'];

        // ─── Colores ──────────────────────────────────────────────────────────
        $rojo      = [185, 28,  28];
        $negro     = [17,  17,  17];
        $blanco    = [255, 255, 255];
        $gris      = [100, 100, 100];
        $grisClaro = [245, 245, 245];

        // ─── Crear PDF ────────────────────────────────────────────────────────
        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 20);

        // ── ENCABEZADO ────────────────────────────────────────────────────────
        $pdf->SetFillColor(...$rojo);
        $pdf->Rect(0, 0, 210, 40, 'F');

        $pdf->SetFont('Arial', 'B', 22);
        $pdf->SetTextColor(...$blanco);
        $pdf->SetXY(15, 8);
        $pdf->Cell(100, 10, 'Alimentos Covadonga', 0, 0, 'L');

        $pdf->SetFont('Arial', '', 9);
        $pdf->SetXY(15, 21);
        $pdf->Cell(100, 6, 'Sistema de Gestion de Despacho', 0, 0, 'L');

        $pdf->SetFont('Arial', 'B', 13);
        $pdf->SetXY(110, 8);
        $pdf->Cell(85, 7, 'GUIA DE DESPACHO', 0, 0, 'R');

        $pdf->SetFont('Arial', 'B', 18);
        $pdf->SetXY(110, 16);
        $pdf->Cell(85, 8, $guideNum, 0, 0, 'R');

        $pdf->SetFont('Arial', '', 9);
        $pdf->SetXY(110, 27);
        $pdf->Cell(85, 6, 'Fecha: ' . $fecha, 0, 0, 'R');

        // ── INFO RUTA Y VEHÍCULO ──────────────────────────────────────────────
        $pdf->SetFillColor(...$grisClaro);
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->Rect(15, 46, 85, 36, 'DF');
        $pdf->Rect(110, 46, 85, 36, 'DF');

        // Caja izquierda — ruta y chofer
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetTextColor(...$gris);
        $pdf->SetXY(18, 48);
        $pdf->Cell(79, 5, 'INFORMACION DE LA RUTA', 0, 1, 'L');

        $pdf->SetTextColor(...$negro);
        $y = 55;
        foreach ([
            'Ruta'   => $rutaNombre,
            'Chofer' => $choferNombre,
            'CI'     => $choferCI,
        ] as $label => $valor) {
            $pdf->SetXY(18, $y);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(22, 5, $label . ':', 0, 0, 'L');
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(60, 5, (string)$valor, 0, 1, 'L');
            $y += 6;
        }

        // Caja derecha — vehículo
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetTextColor(...$gris);
        $pdf->SetXY(113, 48);
        $pdf->Cell(79, 5, 'VEHICULO ASIGNADO', 0, 1, 'L');

        $pdf->SetTextColor(...$negro);
        $y = 55;
        foreach ([
            'Marca'     => $marca,
            'Placa'     => $placa,
            'Capacidad' => $capacidad . ' kg',
        ] as $label => $valor) {
            $pdf->SetXY(113, $y);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(26, 5, $label . ':', 0, 0, 'L');
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(54, 5, (string)$valor, 0, 1, 'L');
            $y += 6;
        }

        // ── TABLA DE PEDIDOS ──────────────────────────────────────────────────
        $pdf->SetY(90);

        // Encabezado tabla
        $pdf->SetFillColor(...$rojo);
        $pdf->SetTextColor(...$blanco);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetDrawColor(...$rojo);
        $pdf->SetX(15);
        $pdf->Cell(15,  7, '#',              1, 0, 'C', true);
        $pdf->Cell(20,  7, 'Codigo',         1, 0, 'C', true);
        $pdf->Cell(70,  7, 'Razon Social',   1, 0, 'L', true);
        $pdf->Cell(30,  7, 'Kg Totales',     1, 0, 'R', true);
        $pdf->Cell(45,  7, 'Estado entrega', 1, 1, 'C', true);

        // Filas de pedidos
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->SetFont('Arial', '', 9);
        $num     = 1;
        $fillRow = false;

        foreach ($orders as $order) {
            $pdf->SetFillColor(...($fillRow ? [250, 242, 242] : $blanco));
            $pdf->SetTextColor(...$negro);
            $pdf->SetX(15);
            $pdf->Cell(15,  7, (string)$num,                                        1, 0, 'C', true);
            $pdf->Cell(20,  7, $order['RIF'],                                        1, 0, 'C', true);
            $pdf->Cell(70,  7, $order['NAME_CLIENT'],                                1, 0, 'L', true);
            $pdf->Cell(30,  7, number_format((float)$order['WEIGHT_REAL'], 2) . ' kg', 1, 0, 'R', true);
            $pdf->Cell(45,  7, '',                                                   1, 1, 'C', true);
            $num++;
            $fillRow = !$fillRow;
        }

        // Fila de totales
        $pdf->SetFillColor(...$negro);
        $pdf->SetTextColor(...$blanco);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetX(15);
        $pdf->Cell(105, 7, 'TOTAL — ' . count($orders) . ' pedido(s)',  1, 0, 'L', true);
        $pdf->Cell(30,  7, $totalKg . ' kg',                             1, 0, 'R', true);
        $pdf->Cell(45,  7, '',                                           1, 1, 'C', true);

        // ── BLOQUE DE FIRMAS ──────────────────────────────────────────────────
        $pdf->SetY($pdf->GetY() + 16);
        $pdf->SetTextColor(...$negro);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetX(15);
        $pdf->Cell(0, 6, 'CONTROL Y FIRMAS', 0, 1, 'L');

        $pdf->SetFont('Arial', '', 9);
        $pdf->SetDrawColor(...$negro);

        $firmas = [
            'Fecha'                          => '__________________',
            'Hora'                           => '__________________',
            'Entregado por'                  => '__________________________________',
            'Recibido por (Cliente)'         => '__________________________________',
            'Jefe de Despacho (Firma/Sello)' => '__________________________________',
        ];

        $y = $pdf->GetY() + 4;
        foreach ($firmas as $label => $linea) {
            $pdf->SetXY(15, $y);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(58, 6, $label . ':', 0, 0, 'L');
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(100, 6, $linea, 0, 1, 'L');
            $y += 9;
        }

        // ── PIE ───────────────────────────────────────────────────────────────
        $pdf->SetY(-18);
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(...$gris);
        $pdf->SetX(15);
        $pdf->Cell(90, 8, 'Alimentos Covadonga — Guia de Despacho', 0, 0, 'L');
        $pdf->Cell(90, 8, $guideNum . ' — ' . date('d/m/Y H:i'), 0, 0, 'R');

        // ── ENVIAR PDF ────────────────────────────────────────────────────────
        header('Content-Disposition: attachment; filename="guia-' . $guideNum . '.pdf"');
          $pdf->Output('D', 'guia-' . $guideNum . '.pdf');
        exit;
    }
}