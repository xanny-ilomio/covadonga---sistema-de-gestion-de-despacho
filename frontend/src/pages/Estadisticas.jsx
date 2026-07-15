import { useState, useEffect, useRef } from 'react';
import Header from '../components/Header';
import { useAuth } from '../context/AuthContext';
import { stats } from '../api/client';
import styles from '../styles/Estadisticas.module.css';

const BASE_URL = 'http://localhost:8888';

const PERIODOS = [
  { label: '15 días', value: 15 },
  { label: '1 mes',   value: 30 },
  { label: '3 meses', value: 90 },
  { label: '6 meses', value: 180 },
  { label: '1 año',   value: 365 },
];

// ─── Barra horizontal ─────────────────────────────────────────────────────────
function BarraHorizontal({ label, valor, max, sufijo = '', color = '#B91C1C' }) {
  const pct = max > 0 ? Math.min(100, (valor / max) * 100) : 0;
  return (
    <div className={styles.barraItem}>
      <div className={styles.barraLabelRow}>
        <span className={styles.barraLabel}>{label}</span>
        <span className={styles.barraValor}>{valor}{sufijo}</span>
      </div>
      <div className={styles.barraTrack}>
        <div className={styles.barraFill} style={{ width: `${pct}%`, background: color }} />
      </div>
    </div>
  );
}

// ─── Tarjeta de métrica ───────────────────────────────────────────────────────
function MetricaCard({ label, valor, sub, color = '#B91C1C'}) {
  return (
    <div className={styles.metricaCard}>
      <div className={styles.metricaInfo}>
        <span className={styles.metricaValor} style={{ color }}>{valor}</span>
        <span className={styles.metricaLabel}>{label}</span>
        {sub && <span className={styles.metricaSub}>{sub}</span>}
      </div>
    </div>
  );
}

// ─── Sección con título ───────────────────────────────────────────────────────
function Seccion({ titulo, children }) {
  return (
    <div className={styles.seccion}>
      <h2 className={styles.seccionTitulo}>{titulo}</h2>
      {children}
    </div>
  );
}

// ─── Dashboard Facturación ────────────────────────────────────────────────────
function StatsFacturacion({ data, periodo }) {
  const { orders, clients, products, metrics } = data;

  const byStatus = {};
  (orders?.by_status ?? []).forEach(s => { byStatus[s.STATUS] = parseInt(s.total); });

  const topProductos = products?.top_5_most_ordered ?? [];
  const maxProd      = topProductos[0] ? parseInt(topProductos[0].total_amount) : 1;

  const topClientes  = clients?.top_5 ?? [];
  const maxCli       = topClientes[0] ? parseInt(topClientes[0].total_orders) : 1;

  const historicalData = orders?.daily_history ?? [];
  const maxDiario      = Math.max(...historicalData.map(d => parseInt(d.total)), 1);

  // Encontrar la etiqueta legible del periodo seleccionado
  const labelPeriodo = PERIODOS.find(p => p.value === periodo)?.label ?? `${periodo} días`;

  return (
    <>
      {/* KPIs principales dinámicos por período */}
      <div className={styles.kpiGrid}>
        <MetricaCard
          label="Pedidos registrados"
          valor={orders?.total_period ?? 0}
          sub={`en los últimos ${labelPeriodo}`}
          color="#B91C1C"
        />
        <MetricaCard
          label="Clientes activos"
          valor={clients?.active_this_period ?? 0}
          sub={`compraron en los últimos ${labelPeriodo}`}
          color="#1D4ED8"
        />
        <MetricaCard
          label="Carga vendida (kg)"
          valor={Number(metrics?.total_weight_kg ?? 0).toLocaleString('es-VE', { maximumFractionDigits: 0 })}
          sub={`despachados en los últimos ${labelPeriodo}`}
          color="#16a34a"
        />
        <MetricaCard
          label="Carga prom. / pedido"
          valor={`${Number(metrics?.avg_weight_per_order ?? 0).toLocaleString('es-VE', { maximumFractionDigits: 0 })} kg`}
          sub={`promedio en los últimos ${labelPeriodo}`}
          color="#7C3AED"
        />
      </div>

      <div className={styles.dosColumnas}>

        {/* Pedidos por estado en el período */}
        <Seccion titulo={`Estado de pedidos (${labelPeriodo})`}>
          <div className={styles.estadoGrid}>
            {[
              { label: 'Pendiente',  color: '#f59e0b'},
              { label: 'Asignado',   color: '#3b82f6'},
              { label: 'Despachado', color: '#22c55e'},
            ].map(({ label, color}) => (
              <div key={label} className={styles.estadoCard} style={{ borderLeft: `3px solid ${color}` }}>
                <div>
                  <span className={styles.estadoNum} style={{ color }}>{byStatus[label] ?? 0}</span>
                  <span className={styles.estadoLabel}>{label}</span>
                </div>
              </div>
            ))}
          </div>
        </Seccion>

        {/* Actividad adaptada al período */}
        <Seccion titulo={`Actividad últimos ${labelPeriodo}`}>
          {historicalData.length === 0 ? (
            <p className={styles.sinDatos}>Sin datos para este período</p>
          ) : (
            <div className={styles.barrasLista}>
              {[...historicalData].reverse().slice(0, 10).map(d => (
                <BarraHorizontal
                  key={d.date}
                  label={new Date(d.date + 'T00:00:00').toLocaleDateString('es-VE', { weekday: 'short', day: 'numeric', month: 'short' })}
                  valor={parseInt(d.total)}
                  max={maxDiario}
                  sufijo=" pedidos"
                  color="#B91C1C"
                />
              ))}
            </div>
          )}
        </Seccion>

        {/* Top productos del período */}
        <Seccion titulo={`Productos más pedidos (${labelPeriodo})`}>
          {topProductos.length === 0 ? (
            <p className={styles.sinDatos}>Sin datos de productos en este período</p>
          ) : (
            <div className={styles.barrasLista}>
              {topProductos.map((p, i) => (
                <BarraHorizontal
                  key={p.NAME_PRODUCT}
                  label={p.NAME_PRODUCT}
                  valor={parseInt(p.total_amount)}
                  max={maxProd}
                  sufijo=" bultos"
                  color={i === 0 ? '#B91C1C' : '#FBBF24'}
                />
              ))}
            </div>
          )}
        </Seccion>

        {/* Top clientes del período */}
        <Seccion titulo={`Clientes más activos (${labelPeriodo})`}>
          {topClientes.length === 0 ? (
            <p className={styles.sinDatos}>Sin datos de clientes en este período</p>
          ) : (
            <div className={styles.rankingLista}>
              {topClientes.map((c, i) => (
                <div key={c.NAME_CLIENT} className={styles.rankingItem}>
                  <span className={styles.rankingPos} style={{ color: i < 3 ? '#B91C1C' : '#9ca3af' }}>
                    #{i + 1}
                  </span>
                  <span className={styles.rankingNombre}>{c.NAME_CLIENT}</span>
                  <span className={styles.rankingValor}>{c.total_orders} pedidos</span>
                </div>
              ))}
            </div>
          )}
        </Seccion>

      </div>
    </>
  );
}

// ─── Dashboard Despacho ───────────────────────────────────────────────────────
function StatsDespacho({ data, periodo }) {
  const { orders, guides, weight, fleet, drivers, trucks } = data;

  const byRoute   = orders?.assigned_by_route ?? [];
  const maxRoute  = byRoute[0] ? parseInt(byRoute[0].total_orders) : 1;

  const statsConductores = drivers?.by_performance ?? [];
  const maxCond = statsConductores[0] ? parseFloat(statsConductores[0].total_weight || statsConductores[0].total_orders) : 1;

  const statsCamiones = trucks?.by_usage ?? [];
  const maxCamion = statsCamiones[0] ? parseInt(statsCamiones[0].trips_count || statsCamiones[0].total_orders) : 1;

  // Encontrar el nombre del período para las etiquetas
  const labelPeriodo = PERIODOS.find(p => p.value === periodo)?.label ?? `${periodo} días`;

  return (
    <>
      {/* KPIs principales dinámicos */}
      <div className={styles.kpiGrid}>
        <MetricaCard
          label="Pendientes de pesaje"
          valor={orders?.pending ?? 0}
          sub={`en los últimos ${labelPeriodo}`}
          color="#f59e0b"
        />
        <MetricaCard
          label="Listos para despachar"
          valor={orders?.assigned ?? 0}
          sub={`en los últimos ${labelPeriodo}`}
          color="#3b82f6"
        />
        <MetricaCard
          label="Despachados"
          valor={orders?.dispatched_period ?? 0} 
          sub={`en los últimos ${labelPeriodo}`}
          color="#16a34a"
        />
        <MetricaCard
          label="Guías emitidas"
          valor={guides?.this_period ?? 0}
          sub={`en los últimos ${labelPeriodo}`}
          color="#7C3AED"
        />
      </div>

      <div className={styles.dosColumnas}>

        {/* Peso despachado */}
        <Seccion titulo={`Peso despachado (${labelPeriodo})`}>
          <div className={styles.pesoDisplay}>
            <span className={styles.pesoNum}>
              {Number(weight?.dispatched_period_kg ?? 0).toLocaleString('es-VE', { maximumFractionDigits: 1 })}
            </span>
            <span className={styles.pesoSufijo}>kg</span>
          </div>
          <div className={styles.fleetRow}>
            <div className={styles.fleetItem}>
              <span className={styles.fleetNum}>{fleet?.total_trucks ?? 0}</span>
              <span className={styles.fleetLabel}>Camiones</span>
            </div>
            <div className={styles.fleetItem}>
              <span className={styles.fleetNum}>{fleet?.total_drivers ?? 0}</span>
              <span className={styles.fleetLabel}>Choferes</span>
            </div>
          </div>
        </Seccion>

        {/* Pedidos por ruta */}
        <Seccion titulo={`Pedidos por ruta (${labelPeriodo})`}>
          {byRoute.length === 0 ? (
            <p className={styles.sinDatos}>No se registraron pedidos en este período</p>
          ) : (
            <div className={styles.barrasLista}>
              {byRoute.map((r, index) => {
                const nombreRuta = r.route_name || r.NAME_ROUTE || `Ruta #${index + 1}`;
                const totalPedidos = parseInt(r.total_orders || r.TOTAL_ORDERS || 0);

                return (
                  <BarraHorizontal
                    key={nombreRuta}
                    label={nombreRuta}
                    valor={totalPedidos}
                    max={maxRoute}
                    sufijo=" pedidos"
                    color="#1D4ED8"
                  />
                );
              })}
            </div>
          )}
        </Seccion>

        {/* Rendimiento de Conductores */}
        <Seccion titulo={`Rendimiento de Conductores (${labelPeriodo})`}>
          {statsConductores.length === 0 ? (
            <p className={styles.sinDatos}>Sin datos de conductores en este período</p>
          ) : (
            <div className={styles.barrasLista}>
              {statsConductores.map((cond) => (
                <BarraHorizontal
                  key={cond.driver_name}
                  label={cond.driver_name}
                  valor={parseFloat(cond.total_weight || 0)}
                  max={maxCond}
                  sufijo=" kg"
                  color="#16a34a"
                />
              ))}
            </div>
          )}
        </Seccion>

        {/* Uso de Camiones */}
        <Seccion titulo={`Frecuencia de Camiones (${labelPeriodo})`}>
          {statsCamiones.length === 0 ? (
            <p className={styles.sinDatos}>Sin datos de camiones en este período</p>
          ) : (
            <div className={styles.barrasLista}>
              {statsCamiones.map((cam) => (
                <BarraHorizontal
                  key={cam.PLATE}
                  label={cam.PLATE}
                  valor={parseInt(cam.trips_count || 0)}
                  max={maxCamion}
                  sufijo=" viajes"
                  color="#7C3AED"
                />
              ))}
            </div>
          )}
        </Seccion>

      </div>
    </>
  );
}

// ─── Componente principal ─────────────────────────────────────────────────────
export default function Estadisticas() {
  const { user }     = useAuth();
  const [data,       setData]       = useState(null);
  const [periodo,    setPeriodo]    = useState(30);
  const [cargando,   setCargando]   = useState(true);
  const [error,      setError]      = useState('');
  const [exportando, setExportando] = useState(false);
  const contenidoRef                = useRef(null);

  const esDespacho = user?.rol === 'despacho';

  async function cargar(p) {
    setCargando(true);
    setError('');
    try {
      const res = await stats.get(p);
      if (res.status === 'success') setData(res.data);
      else setError(res.message || 'Error cargando estadísticas');
    } catch {
      setError('Error de conexión');
    } finally {
      setCargando(false);
    }
  }

  useEffect(() => { cargar(periodo); }, [periodo]);

  // Exportar estadísticas como PDF — genera HTML imprimible en nueva pestaña
  async function exportarPdf() {
    setExportando(true);
    try {
      const token = localStorage.getItem('token');
      const res   = await fetch(`${BASE_URL}/stats/export?period=${periodo}`, {
        headers: { Authorization: `Bearer ${token}` },
      });
      if (res.ok) {
        const blob = await res.blob();
        const url  = URL.createObjectURL(new Blob([blob], { type: 'application/pdf' }));
        window.open(url, '_blank');
        setTimeout(() => URL.revokeObjectURL(url), 60000);
      }
    } catch (err) {
      console.error('Error exportando:', err);
    } finally {
      setExportando(false);
    }
  }

  return (
    <div className={styles.appContainer}>
      <Header />
      <main className={styles.main}>

        {/* TOP BAR */}
        <div className={styles.topBar}>
          <div className={styles.topIzq}>
            <h1 className={styles.titulo}>Estadísticas</h1>
            <button
              className={styles.btnExportar}
              onClick={exportarPdf}
              disabled={exportando || !data}
              title="Exportar estadísticas en PDF"
            >
              {exportando ? '...' : '⬇ Exportar PDF'}
            </button>
          </div>
          <div className={styles.topDer}>
            {/* Filtros de período */}
            <div className={styles.periodos}>
              {PERIODOS.map(p => (
                <button
                  key={p.value}
                  className={`${styles.periodoBtn} ${periodo === p.value ? styles.periodoActivo : ''}`}
                  onClick={() => setPeriodo(p.value)}
                >
                  {p.label}
                </button>
              ))}
            </div>
          </div>
        </div>

        {/* CONTENIDO */}
        <div className={styles.contenido} ref={contenidoRef}>
          {cargando ? (
            <div className={styles.estadoVacio}><span>⏳</span><p>Cargando estadísticas...</p></div>
          ) : error ? (
            <div className={styles.estadoVacio}><span>⚠</span><p>{error}</p></div>
          ) : data ? (
            esDespacho
              ? <StatsDespacho data={data} periodo={periodo} />
              : <StatsFacturacion data={data} periodo={periodo} />
          ) : null}
        </div>

      </main>
    </div>
  );
}