<?php
require_once '/var/www/backend/src/lib/fpdf.php';
class StatsExportController {

    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    // GET /stats/export?period=30
    public function export(): void {
        $authUser = AuthMiddleware::handle();
        $period   = isset($_GET['period']) ? (int)$_GET['period'] : 30;
        if (!in_array($period, [15, 30, 90, 180, 365])) $period = 30;

        $dateFrom  = date('Y-m-d', strtotime("-{$period} days"));
        $esDespacho = $authUser['rol'] === 'despacho';

        $rojo      = [185, 28, 28];
        $negro     = [17,  17, 17];
        $blanco    = [255, 255, 255];
        $gris      = [100, 100, 100];
        $grisClaro = [245, 245, 245];
        $azul      = [29, 78, 216];
        $verde     = [22, 163, 74];

        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 20);

        // ── ENCABEZADO ────────────────────────────────────────────────────────
        $pdf->SetFillColor(...$rojo);
        $pdf->Rect(0, 0, 210, 38, 'F');

        $pdf->SetFont('Arial', 'B', 20);
        $pdf->SetTextColor(...$blanco);
        $pdf->SetXY(15, 7);
        $pdf->Cell(120, 9, 'Alimentos Covadonga', 0, 0, 'L');

        $pdf->SetFont('Arial', '', 9);
        $pdf->SetXY(15, 18);
        $pdf->Cell(120, 6, 'Reporte de Estadisticas — ' . ($esDespacho ? 'Despacho' : 'Facturacion'), 0, 0, 'L');

        $periodoLabel = $period === 15  ? 'Ultimos 15 dias'  :
                       ($period === 30  ? 'Ultimo mes'        :
                       ($period === 90  ? 'Ultimos 3 meses'   :
                       ($period === 180 ? 'Ultimos 6 meses'   : 'Ultimo ano')));

        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetXY(110, 8);
        $pdf->Cell(85, 7, strtoupper($periodoLabel), 0, 0, 'R');

        $pdf->SetFont('Arial', '', 9);
        $pdf->SetXY(110, 17);
        $pdf->Cell(85, 6, 'Desde: ' . date('d/m/Y', strtotime($dateFrom)), 0, 0, 'R');
        $pdf->SetXY(110, 24);
        $pdf->Cell(85, 6, 'Hasta: ' . date('d/m/Y') . ' — Gen: ' . date('H:i'), 0, 0, 'R');

        if ($esDespacho) {
            $this->seccionDespacho($pdf, $dateFrom, $period, $rojo, $negro, $blanco, $gris, $grisClaro, $azul, $verde);
        } else {
            $this->seccionFacturacion($pdf, $dateFrom, $period, $rojo, $negro, $blanco, $gris, $grisClaro, $azul, $verde);
        }

        $filename = 'estadisticas-' . ($esDespacho ? 'despacho' : 'facturacion') . '-' . $period . 'd.pdf';
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $pdf->Output('D', $filename);
        exit;
    }

    // ─── Helper: título de sección ────────────────────────────────────────────
    private function secTitulo(FPDF $pdf, string $titulo, array $rojo, array $blanco): void {
        $pdf->SetFillColor(...$rojo);
        $pdf->SetTextColor(...$blanco);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetX(15);
        $pdf->Cell(180, 7, '  ' . strtoupper($titulo), 0, 1, 'L', true);
        $pdf->SetTextColor(17, 17, 17);
        $pdf->Ln(2);
    }

    // ─── Helper: tabla genérica ───────────────────────────────────────────────
    private function tabla(FPDF $pdf, array $headers, array $rows, array $widths, array $rojo, array $blanco, array $negro): void {
        // Encabezado
        $pdf->SetFillColor(...$rojo);
        $pdf->SetTextColor(...$blanco);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->SetX(15);
        foreach ($headers as $i => $h) {
            $pdf->Cell($widths[$i], 7, $h, 1, 0, 'C', true);
        }
        $pdf->Ln();

        // Filas
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(...$negro);
        $fill = false;
        foreach ($rows as $row) {
            $pdf->SetFillColor(...($fill ? [250, 242, 242] : [255, 255, 255]));
            $pdf->SetX(15);
            foreach ($row as $i => $cel) {
                $align = $i === 0 ? 'L' : 'C';
                $pdf->Cell($widths[$i], 6, (string)$cel, 1, 0, $align, true);
            }
            $pdf->Ln();
            $fill = !$fill;
        }
        $pdf->Ln(4);
    }

    // ─── Helper: tarjetas KPI ─────────────────────────────────────────────────
    private function kpiRow(FPDF $pdf, array $kpis, array $grisClaro, array $negro, array $gris): void {
        $w = 180 / count($kpis);
        $y = $pdf->GetY();
        foreach ($kpis as $i => $kpi) {
            $x = 15 + $i * $w;
            $pdf->SetFillColor(...$grisClaro);
            $pdf->SetDrawColor(220, 220, 220);
            $pdf->Rect($x, $y, $w - 2, 22, 'DF');
            $pdf->SetXY($x, $y + 3);
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->SetTextColor(...$negro);
            $pdf->Cell($w - 2, 8, (string)$kpi['valor'], 0, 1, 'C');
            $pdf->SetXY($x, $y + 12);
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetTextColor(...$gris);
            $pdf->Cell($w - 2, 5, $kpi['label'], 0, 1, 'C');
        }
        $pdf->SetY($y + 26);
        $pdf->SetTextColor(...$negro);
    }

    // ─── Sección Facturación ──────────────────────────────────────────────────
    private function seccionFacturacion(FPDF $pdf, string $dateFrom, int $period,
        array $rojo, array $negro, array $blanco, array $gris, array $grisClaro, array $azul, array $verde): void {

        $pdf->SetY(46);

        // KPIs
        $stmtHoy = $this->db->prepare("SELECT COUNT(*) AS t FROM ORDERS WHERE DATE_ORDERED = CURDATE()");
        $stmtHoy->execute(); $hoy = (int)$stmtHoy->fetch()['t'];

        $stmtPer = $this->db->prepare("SELECT COUNT(*) AS t FROM ORDERS WHERE DATE_ORDERED >= :f");
        $stmtPer->execute([':f' => $dateFrom]); $per = (int)$stmtPer->fetch()['t'];

        $stmtCli = $this->db->prepare("SELECT COUNT(*) AS t FROM CLIENT");
        $stmtCli->execute(); $cli = (int)$stmtCli->fetch()['t'];

        $stmtKg  = $this->db->prepare("SELECT COALESCE(SUM(WEIGHT),0) AS t FROM ORDERS WHERE DATE_ORDERED >= :f");
        $stmtKg->execute([':f' => $dateFrom]); $kg = number_format((float)$stmtKg->fetch()['t'], 1);

        $this->kpiRow($pdf, [
            ['label' => 'Pedidos hoy',      'valor' => $hoy],
            ['label' => 'En el periodo',    'valor' => $per],
            ['label' => 'Total clientes',   'valor' => $cli],
            ['label' => 'Peso aprox. (kg)', 'valor' => $kg],
        ], $grisClaro, $negro, $gris);

        // Estado de pedidos
        $this->secTitulo($pdf, 'Estado de pedidos', $rojo, $blanco);
        $stmtSt = $this->db->prepare("SELECT STATUS, COUNT(*) AS total FROM ORDERS GROUP BY STATUS ORDER BY total DESC");
        $stmtSt->execute();
        $rows = array_map(fn($r) => [$r['STATUS'], $r['total']], $stmtSt->fetchAll());
        $this->tabla($pdf, ['Estado', 'Cantidad'], $rows, [120, 60], $rojo, $blanco, $negro);

        // Top productos
        $this->secTitulo($pdf, 'Productos mas pedidos en el periodo', $rojo, $blanco);
        $stmtPr = $this->db->prepare("
            SELECT p.NAME_PRODUCT, SUM(op.AMOUNT) AS total
            FROM ORDER_PRODUCT op
            JOIN PRODUCT p ON op.PRODUCT_FK = p.ID_PRODUCT
            JOIN ORDERS o  ON op.ORDER_FK   = o.ID_ORDER
            WHERE o.DATE_ORDERED >= :f
            GROUP BY p.ID_PRODUCT, p.NAME_PRODUCT
            ORDER BY total DESC LIMIT 10
        ");
        $stmtPr->execute([':f' => $dateFrom]);
        $rows = array_map(fn($r) => [$r['NAME_PRODUCT'], number_format((float)$r['total'], 0) . ' bultos'], $stmtPr->fetchAll());
        if ($rows) $this->tabla($pdf, ['Producto', 'Bultos'], $rows, [130, 50], $rojo, $blanco, $negro);
        else { $pdf->SetFont('Arial','',9); $pdf->Cell(0,6,'Sin datos',0,1,'C'); $pdf->Ln(2); }

        // Top clientes
        $this->secTitulo($pdf, 'Clientes mas activos en el periodo', $rojo, $blanco);
        $stmtCl = $this->db->prepare("
            SELECT c.NAME_CLIENT, COUNT(o.ID_ORDER) AS total
            FROM ORDERS o JOIN CLIENT c ON o.CLIENT_FK = c.ID_CLIENT
            WHERE o.DATE_ORDERED >= :f
            GROUP BY c.ID_CLIENT, c.NAME_CLIENT
            ORDER BY total DESC LIMIT 10
        ");
        $stmtCl->execute([':f' => $dateFrom]);
        $rows = array_map(fn($r) => [$r['NAME_CLIENT'], $r['total'] . ' pedidos'], $stmtCl->fetchAll());
        if ($rows) $this->tabla($pdf, ['Cliente', 'Pedidos'], $rows, [130, 50], $rojo, $blanco, $negro);

        // Pedidos por ruta
        $this->secTitulo($pdf, 'Pedidos por ruta en el periodo', $rojo, $blanco);
        $stmtRt = $this->db->prepare("
            SELECT r.NAME_ROUTE, COUNT(o.ID_ORDER) AS total
            FROM ORDERS o JOIN ROUTE r ON o.ROUTE_FK = r.ID_ROUTE
            WHERE o.DATE_ORDERED >= :f
            GROUP BY r.ID_ROUTE, r.NAME_ROUTE ORDER BY total DESC
        ");
        $stmtRt->execute([':f' => $dateFrom]);
        $rows = array_map(fn($r) => [$r['NAME_ROUTE'], $r['total'] . ' pedidos'], $stmtRt->fetchAll());
        if ($rows) $this->tabla($pdf, ['Ruta', 'Pedidos'], $rows, [130, 50], $rojo, $blanco, $negro);
    }

    // ─── Sección Despacho ─────────────────────────────────────────────────────
    private function seccionDespacho(FPDF $pdf, string $dateFrom, int $period,
        array $rojo, array $negro, array $blanco, array $gris, array $grisClaro, array $azul, array $verde): void {

        $pdf->SetY(46);

        // KPIs
        $stmtG  = $this->db->prepare("SELECT COUNT(*) AS t, COALESCE(SUM(TOTAL_WEIGHT),0) AS kg FROM GUIDE WHERE EMISSION_DATE >= :f");
        $stmtG->execute([':f' => $dateFrom]);
        $gd = $stmtG->fetch();

        $stmtP  = $this->db->prepare("SELECT COUNT(*) AS t FROM ORDERS WHERE STATUS='Pendiente'");
        $stmtP->execute(); $pend = (int)$stmtP->fetch()['t'];

        $stmtA  = $this->db->prepare("SELECT COUNT(*) AS t FROM ORDERS WHERE STATUS='Asignado'");
        $stmtA->execute(); $asig = (int)$stmtA->fetch()['t'];

        $this->kpiRow($pdf, [
            ['label' => 'Guias emitidas',    'valor' => (int)$gd['t']],
            ['label' => 'Kg despachados',    'valor' => number_format((float)$gd['kg'], 1)],
            ['label' => 'Pedidos pendientes','valor' => $pend],
            ['label' => 'Pedidos asignados', 'valor' => $asig],
        ], $grisClaro, $negro, $gris);

        // Rendimiento por chofer
        $this->secTitulo($pdf, 'Rendimiento por chofer en el periodo', $rojo, $blanco);
        $stmtD  = $this->db->prepare("
            SELECT CONCAT(d.NAME_DRIVER,' ',d.LASTNAME) AS driver,
                   COUNT(g.ID_GUIDE) AS guias,
                   COUNT(DISTINCT go.ORDER_FK) AS pedidos,
                   COALESCE(SUM(g.TOTAL_WEIGHT),0) AS kg
            FROM GUIDE g
            JOIN DRIVER d ON g.DRIVER_FK = d.ID_DRIVER
            LEFT JOIN GUIDE_ORDER go ON go.GUIDE_FK = g.ID_GUIDE
            WHERE g.EMISSION_DATE >= :f
            GROUP BY d.ID_DRIVER, d.NAME_DRIVER, d.LASTNAME ORDER BY kg DESC
        ");
        $stmtD->execute([':f' => $dateFrom]);
        $rows = array_map(fn($r) => [
            $r['driver'], $r['guias'], $r['pedidos'], number_format((float)$r['kg'], 2) . ' kg'
        ], $stmtD->fetchAll());
        if ($rows) $this->tabla($pdf, ['Chofer','Guias','Pedidos','Kg totales'], $rows, [75,30,35,40], $rojo, $blanco, $negro);
        else { $pdf->SetFont('Arial','',9); $pdf->Cell(0,6,'Sin datos en el periodo',0,1,'C'); $pdf->Ln(2); }

        // Kg por ruta
        $this->secTitulo($pdf, 'Kg despachados por ruta', $rojo, $blanco);
        $stmtR  = $this->db->prepare("
            SELECT r.NAME_ROUTE, COUNT(g.ID_GUIDE) AS guias, COALESCE(SUM(g.TOTAL_WEIGHT),0) AS kg
            FROM GUIDE g JOIN ROUTE r ON g.ROUTE_FK = r.ID_ROUTE
            WHERE g.EMISSION_DATE >= :f
            GROUP BY r.ID_ROUTE, r.NAME_ROUTE ORDER BY kg DESC
        ");
        $stmtR->execute([':f' => $dateFrom]);
        $rows = array_map(fn($r) => [$r['NAME_ROUTE'], $r['guias'], number_format((float)$r['kg'], 2) . ' kg'], $stmtR->fetchAll());
        if ($rows) $this->tabla($pdf, ['Ruta','Guias','Kg'], $rows, [100,30,50], $rojo, $blanco, $negro);

        // Uso por camión
        $this->secTitulo($pdf, 'Uso por camion', $rojo, $blanco);
        $stmtT  = $this->db->prepare("
            SELECT CONCAT(t.BRAND,' (',t.PLATE,')') AS truck,
                   COUNT(g.ID_GUIDE) AS guias,
                   COALESCE(SUM(g.TOTAL_WEIGHT),0) AS kg,
                   t.CAPACITY
            FROM GUIDE g JOIN TRUCK t ON g.TRUCK_FK = t.ID_TRUCK
            WHERE g.EMISSION_DATE >= :f
            GROUP BY t.ID_TRUCK, t.BRAND, t.PLATE, t.CAPACITY ORDER BY kg DESC
        ");
        $stmtT->execute([':f' => $dateFrom]);
        $rows = array_map(fn($r) => [
            $r['truck'], $r['guias'],
            number_format((float)$r['kg'], 2) . ' kg',
            number_format((float)$r['CAPACITY'], 0) . ' kg cap.'
        ], $stmtT->fetchAll());
        if ($rows) $this->tabla($pdf, ['Camion','Guias','Kg despachados','Capacidad'], $rows, [70,25,45,40], $rojo, $blanco, $negro);

        // Ciudades más despachadas
        $this->secTitulo($pdf, 'Ciudades mas atendidas', $rojo, $blanco);
        $stmtC  = $this->db->prepare("
            SELECT ci.NAME_CITY, s.NAME_STATE,
                   COUNT(o.ID_ORDER) AS pedidos,
                   COALESCE(SUM(o.WEIGHT_REAL),0) AS kg
            FROM ORDERS o
            JOIN CLIENT c ON o.CLIENT_FK = c.ID_CLIENT
            JOIN CITY ci  ON c.CITY_FK   = ci.ID_CITY
            JOIN STATE s  ON ci.STATE_FK  = s.ID_STATE
            WHERE o.STATUS='Despachado' AND o.DATE_ORDERED >= :f
            GROUP BY ci.ID_CITY, ci.NAME_CITY, s.NAME_STATE
            ORDER BY pedidos DESC LIMIT 10
        ");
        $stmtC->execute([':f' => $dateFrom]);
        $rows = array_map(fn($r) => [
            $r['NAME_CITY'], $r['NAME_STATE'], $r['pedidos'], number_format((float)$r['kg'], 2) . ' kg'
        ], $stmtC->fetchAll());
        if ($rows) $this->tabla($pdf, ['Ciudad','Estado','Pedidos','Kg'], $rows, [55,55,30,40], $rojo, $blanco, $negro);

        // Pie
        $pdf->SetY(-18);
        $pdf->SetDrawColor(220,220,220);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->SetFont('Arial','',8);
        $pdf->SetTextColor(...$gris);
        $pdf->SetX(15);
        $pdf->Cell(90,8,'Alimentos Covadonga — Reporte de Estadisticas',0,0,'L');
        $pdf->Cell(90,8,date('d/m/Y H:i'),0,0,'R');
    }
}