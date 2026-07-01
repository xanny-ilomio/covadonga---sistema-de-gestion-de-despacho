<?php

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
    // Despacho genera la guía para una ruta
    // Body: { "route_id": 1, "driver_id": 2, "truck_id": 1 }
    // El sistema toma automáticamente todos los pedidos 'Asignado' de esa ruta
    public function store(): void {
        $authUser = AuthMiddleware::handle();
        if ($authUser['rol'] !== 'despacho') {
            Response::forbidden('Solo despacho puede generar guías de despacho');
        }

        $body     = json_decode(file_get_contents('php://input'), true);
        $routeId  = $body['route_id']  ?? null;
        $driverId = $body['driver_id'] ?? null;
        $truckId  = $body['truck_id']  ?? null;

        if (!$routeId)  Response::error('route_id es requerido', 422);
        if (!$driverId) Response::error('driver_id es requerido', 422);
        if (!$truckId)  Response::error('truck_id es requerido', 422);

        // Verificar que hay pedidos asignados en la ruta
        $orders = $this->guideModel->getAssignedOrdersByRoute((int) $routeId);
        if (empty($orders)) {
            Response::error('No hay pedidos en estado Asignado para esta ruta', 422);
        }

        try {
            $guideId = $this->guideModel->create((int) $routeId, (int) $driverId, (int) $truckId);
            $guide   = $this->guideModel->findById($guideId);
            Response::success($guide, 'Guía de despacho generada correctamente', 201);
        } catch (Exception $e) {
            Response::serverError('Error al generar la guía: ' . $e->getMessage());
        }
    }

    // GET /guides/{id}/pdf
    // Genera y descarga el PDF de la guía de despacho
    // Usa HTML + CSS imprimible — no requiere librerías externas
    public function pdf(int $id): void {
        AuthMiddleware::handle();
 
        $guide = $this->guideModel->findById($id);
        if (!$guide) Response::notFound("Guía con ID {$id} no encontrada");
 
        // Calcular totales
        $totalOrders = count($guide['orders']);
        $totalWeight = $guide['TOTAL_WEIGHT'];
 
        // Construir filas de pedidos
        $rows = '';
        $num  = 1;
        foreach ($guide['orders'] as $order) {
            $products = '';
            foreach ($order['items'] as $item) {
                $products .= htmlspecialchars($item['NAME_PRODUCT']) .
                             ' x' . $item['AMOUNT'] . ', ';
            }
            $products = rtrim($products, ', ');
 
            $rows .= "
            <tr>
                <td>{$num}</td>
                <td>" . htmlspecialchars($order['NAME_CLIENT']) . "</td>
                <td>" . htmlspecialchars($order['RIF']) . "</td>
                <td>" . htmlspecialchars($order['NAME_CITY']) . ", " . htmlspecialchars($order['NAME_STATE']) . "</td>
                <td>{$order['PHONE_CLIENT']}</td>
                <td>" . number_format((float)$order['WEIGHT_REAL'], 2) . " kg</td>
                <td>{$products}</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>";
            $num++;
        }

        $html = '<!DOCTYPE html>
            <html lang="es">
            <head>
            <meta charset="UTF-8">
            <title>Guía de Despacho ' . htmlspecialchars($guide['GUIDE_NUMBER']) . '</title>
            <style>
              * { box-sizing: border-box; margin: 0; padding: 0; }
              body { font-family: Arial, sans-serif; font-size: 11px; color: #111; padding: 20px; }
              .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; border-bottom: 2px solid #B91C1C; padding-bottom: 14px; }
              .company { font-size: 22px; font-weight: bold; color: #B91C1C; }
              .doc-title { font-size: 14px; font-weight: bold; text-align: right; }
              .doc-number { font-size: 18px; color: #B91C1C; font-weight: bold; }
              .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 18px; }
              .info-box { border: 1px solid #ddd; border-radius: 6px; padding: 10px 14px; }
              .info-box h3 { font-size: 10px; text-transform: uppercase; color: #888; margin-bottom: 8px; letter-spacing: .5px; }
              .info-row { display: flex; gap: 6px; margin-bottom: 4px; }
              .info-label { font-weight: bold; min-width: 80px; color: #444; }
              table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
              thead { background: #B91C1C; color: #fff; }
              thead th { padding: 7px 6px; text-align: left; font-size: 10px; }
              tbody tr:nth-child(even) { background: #FEF2F2; }
              tbody td { padding: 7px 6px; border-bottom: 1px solid #eee; vertical-align: top; }
              .totals { display: flex; justify-content: flex-end; gap: 30px; margin-bottom: 30px; }
              .total-item { text-align: center; }
              .total-val { font-size: 18px; font-weight: bold; color: #B91C1C; }
              .total-label { font-size: 10px; color: #888; margin-top: 2px; }
              .signatures { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 30px; margin-top: 40px; }
              .sig-box { text-align: center; border-top: 1px solid #333; padding-top: 8px; }
              .sig-label { font-size: 10px; color: #555; }
              .note { font-size: 9px; color: #aaa; text-align: center; margin-top: 20px; border-top: 1px solid #eee; padding-top: 10px; }
              @media print {
                body { padding: 10px; }
                .no-print { display: none; }
              }
            </style>
            </head>
            <body>
    
            <div class="header">
              <div>
                <div class="company">Covadonga</div>
                <div style="color:#888;font-size:11px;margin-top:3px">Sistema de Gestión de Despacho</div>
              </div>
              <div style="text-align:right">
                <div class="doc-title">GUÍA DE DESPACHO</div>
                <div class="doc-number">' . htmlspecialchars($guide['GUIDE_NUMBER']) . '</div>
                <div style="color:#888;margin-top:4px">Fecha: ' . htmlspecialchars($guide['EMISSION_DATE']) . '</div>
              </div>
            </div>
    
            <div class="info-grid">
              <div class="info-box">
                <h3>Información de la ruta</h3>
                <div class="info-row"><span class="info-label">Ruta:</span> ' . htmlspecialchars($guide['NAME_ROUTE']) . '</div>
                <div class="info-row"><span class="info-label">Total pedidos:</span> ' . $totalOrders . '</div>
                <div class="info-row"><span class="info-label">Peso total:</span> ' . number_format((float)$totalWeight, 2) . ' kg</div>
              </div>
              <div class="info-box">
                <h3>Vehículo y conductor</h3>
                <div class="info-row"><span class="info-label">Conductor:</span> ' . htmlspecialchars($guide['driver_name']) . '</div>
                <div class="info-row"><span class="info-label">Cédula:</span> ' . htmlspecialchars($guide['driver_ci']) . '</div>
                <div class="info-row"><span class="info-label">Camión:</span> ' . htmlspecialchars($guide['BRAND']) . '</div>
                <div class="info-row"><span class="info-label">Placa:</span> ' . htmlspecialchars($guide['PLATE']) . '</div>
                <div class="info-row"><span class="info-label">Capacidad:</span> ' . htmlspecialchars($guide['CAPACITY']) . ' kg</div>
              </div>
            </div>
    
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>Cliente</th>
                  <th>RIF</th>
                  <th>Destino</th>
                  <th>Teléfono</th>
                  <th>Peso real</th>
                  <th>Productos</th>
                  <th>Firma entrega</th>
                  <th>Firma recepción</th>
                </tr>
              </thead>
              <tbody>' . $rows . '</tbody>
            </table>
    
            <div class="totals">
              <div class="total-item">
                <div class="total-val">' . $totalOrders . '</div>
                <div class="total-label">Pedidos</div>
              </div>
              <div class="total-item">
                <div class="total-val">' . number_format((float)$totalWeight, 2) . ' kg</div>
                <div class="total-label">Peso total</div>
              </div>
            </div>
    
            <div class="signatures">
              <div class="sig-box">
                <div style="height:40px"></div>
                <div class="sig-label">Despachado por</div>
                <div style="margin-top:4px;font-size:10px;color:#888">Fecha: _______________</div>
              </div>
              <div class="sig-box">
                <div style="height:40px"></div>
                <div class="sig-label">Conductor: ' . htmlspecialchars($guide['driver_name']) . '</div>
                <div style="margin-top:4px;font-size:10px;color:#888">Hora: _______________</div>
              </div>
              <div class="sig-box">
                <div style="height:40px"></div>
                <div class="sig-label">Recibido por</div>
                <div style="margin-top:4px;font-size:10px;color:#888">Fecha: _______________</div>
              </div>
            </div>
    
            <div class="note">
              Documento generado digitalmente. Los campos de fecha, hora y firmas deben completarse al momento de la entrega.
            </div>
    
            <div class="no-print" style="text-align:center;margin-top:24px">
              <button onclick="window.print()" style="background:#B91C1C;color:#fff;border:none;padding:10px 28px;border-radius:6px;font-size:14px;cursor:pointer">
                Imprimir / Guardar PDF
              </button>
            </div>
    
            </body>
        </html>';

        // Enviar como HTML imprimible — el navegador maneja la impresión/PDF
        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: inline; filename="guia-' . $guide['GUIDE_NUMBER'] . '.html"');
        echo $html;
        exit;
    }
}