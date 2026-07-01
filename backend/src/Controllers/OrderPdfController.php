<?php

require_once '/var/www/backend/src/lib/fpdf.php';

class OrderPdfController {

    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function pdf(int $id): void {

        AuthMiddleware::handle();

        // Traer datos del pedido
        $stmt = $this->db->prepare("
            SELECT
                o.ID_ORDER,
                o.DATE_ORDERED,
                o.WEIGHT,
                o.WEIGHT_REAL,
                o.STATUS,
                o.UPDATED_AT,
                c.NAME_CLIENT,
                c.RIF,
                c.PHONE_CLIENT,
                ci.NAME_CITY,
                s.NAME_STATE,
                r.NAME_ROUTE
            FROM ORDERS o
            JOIN CLIENT c     ON o.CLIENT_FK  = c.ID_CLIENT
            JOIN CITY ci      ON c.CITY_FK    = ci.ID_CITY
            JOIN STATE s      ON ci.STATE_FK  = s.ID_STATE
            LEFT JOIN ROUTE r ON o.ROUTE_FK   = r.ID_ROUTE
            WHERE o.ID_ORDER = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $order = $stmt->fetch();

        if (!$order) {
            Response::notFound('Pedido no encontrado');
        }

        // Traer productos del pedido
        $stmtItems = $this->db->prepare("
            SELECT
                p.NAME_PRODUCT,
                op.AMOUNT,
                op.WEIGHT_APROX,
                op.WEIGHT_REAL,
                op.PRICE_AT_PURCHASE
            FROM ORDER_PRODUCT op
            JOIN PRODUCT p ON op.PRODUCT_FK = p.ID_PRODUCT
            WHERE op.ORDER_FK = :order_id
        ");
        $stmtItems->execute([':order_id' => $id]);
        $items = $stmtItems->fetchAll();

        // Calcular totales
        $orderNum   = str_pad($order['ID_ORDER'], 5, '0', STR_PAD_LEFT);
        $totalAprox = array_sum(array_column($items, 'WEIGHT_APROX'));
        $totalReal  = array_sum(array_column($items, 'WEIGHT_REAL'));

        // Colores
        $rojo      = [185, 28, 28];
        $gris      = [100, 100, 100];
        $negro     = [17, 17, 17];
        $blanco    = [255, 255, 255];
        $grisClaro = [245, 245, 245];

        // Crear PDF
        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);

        // ── ENCABEZADO ────────────────────────────────────────────────────────
        $pdf->SetFillColor(...$rojo);
        $pdf->Rect(0, 0, 210, 38, 'F');

        $pdf->SetFont('Arial', 'B', 22);
        $pdf->SetTextColor(...$blanco);
        $pdf->SetXY(15, 8);
        $pdf->Cell(100, 10, 'Covadonga', 0, 0, 'L');

        $pdf->SetFont('Arial', '', 9);
        $pdf->SetXY(15, 20);
        $pdf->Cell(100, 6, 'Sistema de Gestion de Despacho', 0, 0, 'L');

        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetXY(110, 8);
        $pdf->Cell(85, 6, 'PEDIDO DE VENTA', 0, 0, 'R');

        $pdf->SetFont('Arial', 'B', 18);
        $pdf->SetXY(110, 15);
        $pdf->Cell(85, 8, '#' . $orderNum, 0, 0, 'R');

        $pdf->SetFont('Arial', '', 9);
        $pdf->SetXY(110, 25);
        $pdf->Cell(85, 6, 'Fecha: ' . $order['DATE_ORDERED'], 0, 0, 'R');

        // ── ESTADO ────────────────────────────────────────────────────────────
        $pdf->SetY(42);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetTextColor(...$rojo);
        $pdf->Cell(0, 6, 'Estado: ' . $order['STATUS'], 0, 1, 'L');

        // ── CAJAS DE INFORMACIÓN ──────────────────────────────────────────────
        $pdf->SetFillColor(...$grisClaro);
        $pdf->SetDrawColor(220, 220, 220);

        // Caja izquierda — cliente
        $pdf->Rect(15, 52, 85, 48, 'DF');
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetTextColor(...$gris);
        $pdf->SetXY(18, 54);
        $pdf->Cell(79, 5, 'DATOS DEL CLIENTE', 0, 1, 'L');

        $pdf->SetTextColor(...$negro);
        $y = 61;
        foreach ([
            'Cliente'  => $order['NAME_CLIENT'],
            'RIF'      => $order['RIF'],
            'Telefono' => $order['PHONE_CLIENT'],
            'Ciudad'   => $order['NAME_CITY'] . ', ' . $order['NAME_STATE'],
        ] as $label => $valor) {
            $pdf->SetXY(18, $y);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(25, 5, $label . ':', 0, 0, 'L');
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(54, 5, (string)$valor, 0, 1, 'L');
            $y += 6;
        }

        // Caja derecha — despacho
        $pdf->Rect(110, 52, 85, 48, 'DF');
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetTextColor(...$gris);
        $pdf->SetXY(113, 54);
        $pdf->Cell(79, 5, 'DATOS DEL DESPACHO', 0, 1, 'L');

        $pdf->SetTextColor(...$negro);
        $y = 61;
        foreach ([
            'Ruta'        => $order['NAME_ROUTE'] ?? 'Por asignar',
            'Peso aprox'  => number_format((float)$order['WEIGHT'], 2) . ' kg',
            'Peso real'   => $order['WEIGHT_REAL']
                             ? number_format((float)$order['WEIGHT_REAL'], 2) . ' kg'
                             : 'Pendiente',
            'Actualizado' => $order['UPDATED_AT'] ?? '-',
        ] as $label => $valor) {
            $pdf->SetXY(113, $y);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(28, 5, $label . ':', 0, 0, 'L');
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(54, 5, (string)$valor, 0, 1, 'L');
            $y += 6;
        }

        // ── TABLA DE PRODUCTOS ────────────────────────────────────────────────
        $pdf->SetY(108);

        // Encabezado tabla
        $pdf->SetFillColor(...$rojo);
        $pdf->SetTextColor(...$blanco);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetDrawColor(...$rojo);
        $pdf->SetX(15);
        $pdf->Cell(8,  7, '#',           1, 0, 'C', true);
        $pdf->Cell(65, 7, 'Producto',    1, 0, 'L', true);
        $pdf->Cell(20, 7, 'Cantidad',    1, 0, 'C', true);
        $pdf->Cell(28, 7, 'Peso aprox.', 1, 0, 'R', true);
        $pdf->Cell(28, 7, 'Peso real',   1, 0, 'R', true);
        $pdf->Cell(26, 7, 'Precio',      1, 1, 'R', true);

        // Filas
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->SetFont('Arial', '', 9);
        $num     = 1;
        $fillRow = false;
        foreach ($items as $item) {
            $pdf->SetFillColor(...($fillRow ? [250, 242, 242] : $blanco));
            $pdf->SetTextColor(...$negro);
            $pdf->SetX(15);
            $pdf->Cell(8,  7, (string)$num,                                           1, 0, 'C', true);
            $pdf->Cell(65, 7, $item['NAME_PRODUCT'],                                  1, 0, 'L', true);
            $pdf->Cell(20, 7, (string)$item['AMOUNT'],                                1, 0, 'C', true);
            $pdf->Cell(28, 7, number_format((float)$item['WEIGHT_APROX'], 2) . ' kg', 1, 0, 'R', true);
            $pdf->Cell(28, 7, $item['WEIGHT_REAL']
                ? number_format((float)$item['WEIGHT_REAL'], 2) . ' kg'
                : '-',                                                                1, 0, 'R', true);
            $pdf->Cell(26, 7, 'Bs. ' . number_format((float)$item['PRICE_AT_PURCHASE'], 2), 1, 1, 'R', true);
            $num++;
            $fillRow = !$fillRow;
        }

        // Fila totales
        $pdf->SetFillColor(...$negro);
        $pdf->SetTextColor(...$blanco);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetX(15);
        $pdf->Cell(93, 7, 'TOTALES - ' . count($items) . ' producto(s)', 1, 0, 'L', true);
        $pdf->Cell(20, 7, '',                                              1, 0, 'C', true);
        $pdf->Cell(28, 7, number_format((float)$totalAprox, 2) . ' kg',   1, 0, 'R', true);
        $pdf->Cell(28, 7, $totalReal
            ? number_format((float)$totalReal, 2) . ' kg'
            : '-',                                                         1, 0, 'R', true);
        $pdf->Cell(26, 7, '',                                              1, 1, 'R', true);

        // ── PIE ───────────────────────────────────────────────────────────────
        $pdf->SetY(-25);
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(...$gris);
        $pdf->SetX(15);
        $pdf->Cell(90, 8, 'Documento generado por el sistema Covadonga', 0, 0, 'L');
        $pdf->Cell(90, 8, 'Pedido #' . $orderNum . ' - ' . date('d/m/Y H:i'), 0, 0, 'R');

        // ── ENVIAR PDF ────────────────────────────────────────────────────────
        $pdf->Output('D', 'pedido-' . $orderNum . '.pdf');
        exit;
    }
}