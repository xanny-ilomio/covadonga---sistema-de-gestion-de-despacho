import { useState, useEffect, useMemo } from 'react';
import Header from '../../components/Header';
import { guides, routes } from '../../api/client';
import styles from '../../styles/HistorialGuias.module.css';
import guia from '../../../public/assets/pedido.svg';

const BASE_URL = 'http://localhost:8888';

const MESES = [
  'Enero','Febrero','Marzo','Abril','Mayo','Junio',
  'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'
];

// ─── Helpers ──────────────────────────────────────────────────────────────────
function formatFecha(fechaStr) {
  if (!fechaStr) return '';
  const [y, m, d] = fechaStr.split('-');
  return `${d}/${m}/${y}`;
}

function mesAnioLabel(fechaStr) {
  if (!fechaStr) return '';
  const [y, m] = fechaStr.split('-');
  return `${MESES[parseInt(m) - 1]} ${y}`;
}

// ─── Mini calendario ──────────────────────────────────────────────────────────
function MiniCalendario({ mesActual, anioActual, guias, onSeleccionarDia, diaSeleccionado, onCambiarMes }) {
  const diasEnMes   = new Date(anioActual, mesActual + 1, 0).getDate();
  const primerDia   = new Date(anioActual, mesActual, 1).getDay();
  const offset      = primerDia === 0 ? 6 : primerDia - 1; // lunes primero

  // Días que tienen guías este mes
  const diasConGuia = new Set(
    guias
      .filter(g => {
        const [y, m] = g.EMISSION_DATE.split('-');
        return parseInt(m) - 1 === mesActual && parseInt(y) === anioActual;
      })
      .map(g => parseInt(g.EMISSION_DATE.split('-')[2]))
  );

  const celdas = [];
  for (let i = 0; i < offset; i++) celdas.push(null);
  for (let d = 1; d <= diasEnMes; d++) celdas.push(d);

  return (
    <div className={styles.calendario}>
      <div className={styles.calHeader}>
        <button className={styles.calNav} onClick={() => onCambiarMes(-1)}>‹</button>
        <span className={styles.calTitulo}>{MESES[mesActual]} {anioActual}</span>
        <button className={styles.calNav} onClick={() => onCambiarMes(1)}>›</button>
      </div>
      <div className={styles.calGrid}>
        {['Lu','Ma','Mi','Ju','Vi','Sá','Do'].map(d => (
          <div key={d} className={styles.calDiaNombre}>{d}</div>
        ))}
        {celdas.map((dia, i) => (
          <div
            key={i}
            className={`
              ${styles.calDia}
              ${!dia ? styles.calDiaVacio : ''}
              ${dia && diasConGuia.has(dia) ? styles.calDiaConGuia : ''}
              ${dia && diaSeleccionado === dia ? styles.calDiaSeleccionado : ''}
            `}
            onClick={() => dia && onSeleccionarDia(dia === diaSeleccionado ? null : dia)}
          >
            {dia ?? ''}
            {dia && diasConGuia.has(dia) && (
              <span className={styles.calPunto} />
            )}
          </div>
        ))}
      </div>
    </div>
  );
}

// ─── Card de guía ─────────────────────────────────────────────────────────────
function CardGuia({ guia, onDescargar, descargando }) {
  return (
    <div className={styles.cardGuia}>
      <div className={styles.cardIzq}>
        <div className={styles.guiaNum}>{guia.GUIDE_NUMBER}</div>
        <div className={styles.guiaMeta}>
          <span className={styles.guiaRuta}>{guia.NAME_ROUTE}</span>
          <span className={styles.guiaSep}>·</span>
          <span className={styles.guiaChofer}>{guia.driver_name}</span>
        </div>
        <div className={styles.guiaDetalle}>
          <span className={styles.guiaTag}>🚛 {guia.PLATE}</span>
          <span className={styles.guiaTag}>⚖ {Number(guia.TOTAL_WEIGHT).toFixed(2)} kg</span>
          <span className={styles.guiaTag}>📦 {guia.total_orders} pedido(s)</span>
        </div>
      </div>
      <div className={styles.cardDer}>
        <span className={styles.guiaFecha}>{formatFecha(guia.EMISSION_DATE)}</span>
        <button
          className={styles.btnDescargar}
          onClick={() => onDescargar(guia.ID_GUIDE, guia.GUIDE_NUMBER)}
          disabled={descargando}
          title="Descargar PDF"
        >
          {descargando ? '...' : '⬇'}
        </button>
      </div>
    </div>
  );
}

// ─── Componente principal ─────────────────────────────────────────────────────
export default function HistorialGuias() {
  const hoy = new Date();

  const [todasGuias,   setTodasGuias]   = useState([]);
  const [listaRutas,   setListaRutas]   = useState([]);
  const [cargando,     setCargando]     = useState(true);
  const [descargando,  setDescargando]  = useState(null);

  // Filtros
  const [mesActual,    setMesActual]    = useState(hoy.getMonth());
  const [anioActual,   setAnioActual]   = useState(hoy.getFullYear());
  const [diaSelec,     setDiaSelec]     = useState(null);
  const [rutaFiltro,   setRutaFiltro]   = useState('');
  const [busqueda,     setBusqueda]     = useState('');

  // ─── Cargar datos ──────────────────────────────────────────────────────────
  useEffect(() => {
    async function cargar() {
      setCargando(true);
      try {
        const [resGuias, resRutas] = await Promise.all([
          guides.getAll(),
          routes.getAll(),
        ]);
        if (resGuias.status  === 'success') setTodasGuias(resGuias.data);
        if (resRutas.status  === 'success') setListaRutas(resRutas.data);
      } catch (err) {
        console.error('Error cargando historial:', err);
      } finally {
        setCargando(false);
      }
    }
    cargar();
  }, []);

  // ─── Cambiar mes en calendario ─────────────────────────────────────────────
  function cambiarMes(delta) {
    setDiaSelec(null);
    let nuevoMes  = mesActual + delta;
    let nuevoAnio = anioActual;
    if (nuevoMes < 0)  { nuevoMes = 11; nuevoAnio--; }
    if (nuevoMes > 11) { nuevoMes = 0;  nuevoAnio++; }
    setMesActual(nuevoMes);
    setAnioActual(nuevoAnio);
  }

  // ─── Filtrar guías ─────────────────────────────────────────────────────────
  const guiasFiltradas = useMemo(() => {
    return todasGuias.filter(g => {
      const [y, m, d] = g.EMISSION_DATE.split('-').map(Number);

      // Filtro de mes y año
      if (m - 1 !== mesActual || y !== anioActual) return false;

      // Filtro de día (calendario)
      if (diaSelec && d !== diaSelec) return false;

      // Filtro de ruta
      if (rutaFiltro && String(g.ID_ROUTE) !== String(rutaFiltro)) return false;

      // Búsqueda por número de guía o chofer
      if (busqueda) {
        const q = busqueda.toLowerCase();
        if (
          !g.GUIDE_NUMBER.toLowerCase().includes(q) &&
          !g.driver_name.toLowerCase().includes(q)  &&
          !g.NAME_ROUTE.toLowerCase().includes(q)
        ) return false;
      }

      return true;
    });
  }, [todasGuias, mesActual, anioActual, diaSelec, rutaFiltro, busqueda]);

  // Agrupar por día para mostrar separadores
  const guiasPorDia = useMemo(() => {
    const grupos = {};
    guiasFiltradas.forEach(g => {
      const dia = g.EMISSION_DATE;
      if (!grupos[dia]) grupos[dia] = [];
      grupos[dia].push(g);
    });
    return grupos;
  }, [guiasFiltradas]);

  // ─── Descargar PDF ─────────────────────────────────────────────────────────
  async function descargarPdf(id, numero) {
    setDescargando(id);
    try {
      const token  = localStorage.getItem('token');
      const res    = await fetch(`${BASE_URL}/guides/${id}/pdf`, {
        headers: { Authorization: `Bearer ${token}` },
      });
      if (res.ok) {
        const blob = await res.blob();
        const url  = URL.createObjectURL(new Blob([blob], { type: 'application/pdf' }));
        const link = document.createElement('a');
        link.href     = url;
        link.download = `${numero}.pdf`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        setTimeout(() => URL.revokeObjectURL(url), 60000);
      }
    } catch (err) {
      console.error('Error descargando PDF:', err);
    } finally {
      setDescargando(null);
    }
  }

  async function descargarHistorial() {
  try {
    const token  = localStorage.getItem('token');
    const params = new URLSearchParams({ month: mesActual + 1, year: anioActual });
    if (rutaFiltro) params.append('route_id', rutaFiltro);

    const res = await fetch(`${BASE_URL}/guides/export?${params}`, {
      headers: { Authorization: `Bearer ${token}` },
    });

    if (res.ok) {
      const blob = await res.blob();
      const url  = URL.createObjectURL(new Blob([blob], { type: 'application/pdf' }));
      const link = document.createElement('a');
      link.href     = url;
      link.download = `historial-${MESES[mesActual].toLowerCase()}-${anioActual}.pdf`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      setTimeout(() => URL.revokeObjectURL(url), 60000);
    }
  } catch (err) {
    console.error('Error exportando historial:', err);
  }
}

  // Estadísticas del mes actual
  const guiasMes   = todasGuias.filter(g => {
    const [y, m] = g.EMISSION_DATE.split('-').map(Number);
    return m - 1 === mesActual && y === anioActual;
  });
  const kgMes      = guiasMes.reduce((s, g) => s + Number(g.TOTAL_WEIGHT), 0);
  const pedidosMes = guiasMes.reduce((s, g) => s + Number(g.total_orders || 0), 0);

  return (
    <div className={styles.appContainer}>
      <Header />

      <main className={styles.main}>

        {/* COLUMNA IZQUIERDA — calendario y filtros */}
        <aside className={styles.sidebar}>

          {/* Resumen del mes */}
          <div className={styles.resumenMes}>
            <div className={styles.resumenTitulo}>{MESES[mesActual]} {anioActual}</div>
            <div className={styles.resumenStats}>
              <div className={styles.resumenStat}>
                <span className={styles.resumenNum}>{guiasMes.length}</span>
                <span className={styles.resumenLabel}>Guías</span>
              </div>
              <div className={styles.resumenDivider} />
              <div className={styles.resumenStat}>
                <span className={styles.resumenNum}>{pedidosMes}</span>
                <span className={styles.resumenLabel}>Pedidos</span>
              </div>
              <div className={styles.resumenDivider} />
              <div className={styles.resumenStat}>
                <span className={styles.resumenNum}>{kgMes.toFixed(0)}</span>
                <span className={styles.resumenLabel}>kg</span>
              </div>
            </div>
          </div>

          {/* Calendario */}
          <MiniCalendario
            mesActual={mesActual}
            anioActual={anioActual}
            guias={todasGuias}
            onSeleccionarDia={setDiaSelec}
            diaSeleccionado={diaSelec}
            onCambiarMes={cambiarMes}
          />

          {/* Filtro por ruta */}
          <div className={styles.filtroGrupo}>
            <label className={styles.filtroLabel}>Filtrar por ruta</label>
            <select
              value={rutaFiltro}
              onChange={e => setRutaFiltro(e.target.value)}
              className={styles.filtroSelect}
            >
              <option value="">Todas las rutas</option>
              {listaRutas.map(r => (
                <option key={r.ID_ROUTE} value={r.ID_ROUTE}>{r.NAME_ROUTE}</option>
              ))}
            </select>
          </div>

          {/* Limpiar filtros */}
          {(diaSelec || rutaFiltro || busqueda) && (
            <button
              className={styles.btnLimpiar}
              onClick={() => { setDiaSelec(null); setRutaFiltro(''); setBusqueda(''); }}
            >
              ✕ Limpiar filtros
            </button>
          )}

        </aside>

        {/* COLUMNA DERECHA — lista de guías */}
        <section className={styles.contenido}>

          <div className={styles.contenidoHeader}>
            <h1 className={styles.titulo}>Historial de Guías</h1>
            <div className={styles.headerAcciones}>
              <button
                className={styles.btnExportar}
                onClick={descargarHistorial}
                disabled={guiasFiltradas.length === 0}
                title={`Descargar ${guiasFiltradas.length} guía(s) de ${MESES[mesActual]}`}
              >
                ⬇ Exportar {guiasFiltradas.length > 0 ? `(${guiasFiltradas.length})` : ''}
              </button>
              <input
                type="text"
                placeholder="Buscar por guía, chofer o ruta..."
                value={busqueda}
                onChange={e => setBusqueda(e.target.value)}
                className={styles.buscador}
              />
            </div>
          </div>

          <div className={styles.lista}>
            {cargando ? (
              <div className={styles.estadoVacio}>
                <span className={styles.estadoIcono}>⏳</span>
                <p>Cargando historial...</p>
              </div>
            ) : guiasFiltradas.length === 0 ? (
              <div className={styles.estadoVacio}>
                <img src={guia} className={styles.estadoIcono}/>
                <p>No hay guías para {diaSelec ? `el ${diaSelec} de ` : ''}{MESES[mesActual].toLowerCase()} {anioActual}</p>
                {(diaSelec || rutaFiltro || busqueda) && (
                  <button
                    className={styles.btnLimpiarInline}
                    onClick={() => { setDiaSelec(null); setRutaFiltro(''); setBusqueda(''); }}
                  >
                    Quitar filtros
                  </button>
                )}
              </div>
            ) : (
              Object.entries(guiasPorDia)
                .sort(([a], [b]) => b.localeCompare(a))
                .map(([fecha, guiasDelDia]) => (
                  <div key={fecha} className={styles.grupoFecha}>
                    <div className={styles.separadorFecha}>
                      <div className={styles.separadorLinea} />
                      <span className={styles.separadorLabel}>{formatFecha(fecha)}</span>
                      <div className={styles.separadorLinea} />
                    </div>
                    {guiasDelDia.map(g => (
                      <CardGuia
                        key={g.ID_GUIDE}
                        guia={g}
                        onDescargar={descargarPdf}
                        descargando={descargando === g.ID_GUIDE}
                      />
                    ))}
                  </div>
                ))
            )}
          </div>

        </section>

      </main>
    </div>
  );
}