import { useState, useEffect } from 'react';
import Header from '../../components/Header';
import { routes, trucks, drivers, guides } from '../../api/client';
import styles from '../../styles/GestionRutas.module.css';

const BASE_URL       = 'http://localhost:8888';
const CARGA_MINIMA   = 1200; // kg mínimos para generar guía
const CAP_CAMION_CHICO = 350; // si la cap. del camión es ≤ esto, no aplica mínimo

// ─── Helpers ──────────────────────────────────────────────────────────────────
function calcularKg(pedidos = []) {
  return pedidos.reduce((s, p) => s + (Number(p.WEIGHT_REAL) || 0), 0);
}

// ─── Barra de capacidad ───────────────────────────────────────────────────────
function BarraCapacidad({ usado, maximo }) {
  const pct   = maximo > 0 ? Math.min(100, Math.round((usado / maximo) * 100)) : 0;
  const color = pct >= 90 ? '#ef4444' : pct >= 70 ? '#f59e0b' : '#fff';
  return (
    <div className={styles.barraWrap}>
      <span className={styles.barraLabel}>Capacidad:</span>
      <div className={styles.barraTrack}>
        <div className={styles.barraFill} style={{ width: `${pct}%`, background: color }} />
      </div>
      <span className={styles.barraPct}>{pct}%</span>
    </div>
  );
}

// ─── Modal crear/editar ruta ──────────────────────────────────────────────────
// ─── Modal crear ruta ─────────────────────────────────────────────────────────
function ModalRuta({ listaEstados, onGuardar, onCerrar, guardando, error }) {
  const [nombre,         setNombre]         = useState('');
  const [estadosSelec,   setEstadosSelec]   = useState([]); // IDs seleccionados

  function toggleEstado(id) {
    setEstadosSelec(prev =>
      prev.includes(id) ? prev.filter(e => e !== id) : [...prev, id]
    );
  }

  return (
    <div className={styles.overlay}>
      <div className={styles.modal}>
        <div className={styles.modalHeader}>
          <h2 className={styles.modalTitulo}>Nueva ruta</h2>
          <button className={styles.btnCerrar} onClick={onCerrar}>✕</button>
        </div>
        <div className={styles.modalBody}>
          <div className={styles.campo}>
            <label>Nombre de la ruta</label>
            <input
              type="text"
              placeholder="Ej: Aragua, Occidente..."
              value={nombre}
              onChange={e => setNombre(e.target.value)}
              autoFocus
            />
          </div>

          <div className={styles.campo}>
            <label>Estados que cubre</label>
            <div className={styles.estadosGrid}>
              {listaEstados.map(s => (
                <button
                  key={s.ID_STATE}
                  type="button"
                  className={`${styles.estadoChip} ${estadosSelec.includes(s.ID_STATE) ? styles.estadoChipActivo : ''}`}
                  onClick={() => toggleEstado(s.ID_STATE)}
                >
                  {estadosSelec.includes(s.ID_STATE) ? '✓ ' : ''}{s.NAME_STATE}
                </button>
              ))}
            </div>
            {listaEstados.length === 0 && (
              <p className={styles.estadosVacio}>No hay estados disponibles sin ruta asignada</p>
            )}
          </div>
        </div>

        {error && <p className={styles.errorMsg}>{error}</p>}

        <div className={styles.modalFooter}>
          <button className={styles.btnSecundario} onClick={onCerrar}>Cancelar</button>
          <button
            className={styles.btnPrimario}
            onClick={() => onGuardar({ name: nombre, estados: estadosSelec })}
            disabled={guardando}
          >
            {guardando ? 'Creando...' : 'Crear ruta'}
          </button>
        </div>
      </div>
    </div>
  );
}

// ─── Modal asignar camión ─────────────────────────────────────────────────────
function ModalAsignarCamion({ ruta, listaCamiones, onGuardar, onCerrar, guardando, error }) {
  const [camionId, setCamionId] = useState(ruta?.TRUCK_ID ?? '');
  return (
    <div className={styles.overlay}>
      <div className={styles.modal}>
        <div className={styles.modalHeader}>
          <h2 className={styles.modalTitulo}>Asignar camión — {ruta?.NAME_ROUTE}</h2>
          <button className={styles.btnCerrar} onClick={onCerrar}>✕</button>
        </div>
        <div className={styles.modalBody}>
          <div className={styles.campo}>
            <label>Camión</label>
            <select
              value={camionId}
              onChange={e => setCamionId(e.target.value)}
              className={styles.selectCamion}
            >
              <option value="">Sin camión asignado</option>
              {listaCamiones.map(c => (
                <option key={c.ID_TRUCK} value={c.ID_TRUCK}>
                  {c.BRAND} — {c.PLATE} ({Number(c.CAPACITY).toLocaleString()} kg)
                </option>
              ))}
            </select>
          </div>
        </div>
        {error && <p className={styles.errorMsg}>{error}</p>}
        <div className={styles.modalFooter}>
          <button className={styles.btnSecundario} onClick={onCerrar}>Cancelar</button>
          <button className={styles.btnPrimario} onClick={() => onGuardar(camionId)} disabled={guardando}>
            {guardando ? 'Guardando...' : 'Asignar'}
          </button>
        </div>
      </div>
    </div>
  );
}

// ─── Modal generar guía ───────────────────────────────────────────────────────
function ModalGuia({ ruta, listaChoferes, listaCamiones, capacidadRuta, onCerrar, onGenerada }) {
  const [choferId,  setChoferId]  = useState('');
  const [choferNombre, setChoferNombre] = useState('');
  const [camionId,  setCamionId]  = useState(ruta?.TRUCK_ID ? String(ruta.TRUCK_ID) : '');
  const [generando, setGenerando] = useState(false);
  const [error,     setError]     = useState('');

  const kgTotales  = calcularKg(ruta?.pending_orders ?? []);
  const camionSel  = listaCamiones.find(c => String(c.ID_TRUCK) === String(camionId));
  const capCamion  = camionSel ? Number(camionSel.CAPACITY) : capacidadRuta;
  const esCamionChico = capCamion <= CAP_CAMION_CHICO;
  const minimoReq  = esCamionChico ? 0 : CARGA_MINIMA;
  const cumpleMin  = kgTotales >= minimoReq;

  async function handleContinuar() {
    setError('');
    if (!choferId)  { setError('Selecciona un chofer');  return; }
    if (!camionId)  { setError('Selecciona un camión');  return; }
    if (!cumpleMin) {
      setError(`La carga mínima para generar la guía es de ${CARGA_MINIMA} kg. Carga actual: ${kgTotales.toFixed(2)} kg`);
      return;
    }
    setGenerando(true);
    try {
      const res = await guides.create({
        route_id:  ruta.ID_ROUTE,
        driver_id: parseInt(choferId),
        truck_id:  parseInt(camionId),
      });
      if (res.status === 'success') {
        const token  = localStorage.getItem('token');
        const guiaId = res.data.ID_GUIDE;
        const pdfRes = await fetch(`${BASE_URL}/guides/${guiaId}/pdf`, {
          headers: { Authorization: `Bearer ${token}` },
        });
        if (pdfRes.ok) {
          const blob = await pdfRes.blob();
          const url  = URL.createObjectURL(new Blob([blob], { type: 'application/pdf' }));
        
          // Leer el nombre del header Content-Disposition
          const disposition = pdfRes.headers.get('Content-Disposition');
          const nombreMatch = disposition?.match(/filename="(.+)"/);
          const nombre      = nombreMatch ? nombreMatch[1] : `guia-${guiaId}.pdf`;
        
          const link    = document.createElement('a');
          link.href     = url;
          link.download = nombre;
          document.body.appendChild(link);
          link.click();
          document.body.removeChild(link);
          setTimeout(() => URL.revokeObjectURL(url), 60000);
        }}
      else {
        setError(res.message || 'Error al generar la guía');
      }
    } catch (err) {
      setError('Error de conexión');
    } finally {
      setGenerando(false);
    }
  }

  return (
    <div className={styles.overlay}>
      <div className={styles.modal}>
        <div className={styles.modalHeader}>
          <h2 className={styles.modalTitulo}>Generar Guía de Despacho</h2>
          <button className={styles.btnCerrar} onClick={onCerrar}>✕</button>
        </div>

        <div className={styles.modalRutaInfo}>
          <span className={styles.modalRutaNombre}>{ruta.NAME_ROUTE.toUpperCase()}</span>
          <span className={styles.modalRutaPedidos}>
            {(ruta.pending_orders ?? []).length} pedido(s) · {kgTotales.toFixed(2)} kg
          </span>
        </div>

        {/* Indicador de carga mínima */}
        {!esCamionChico && (
          <div className={`${styles.cargaMinima} ${cumpleMin ? styles.cargaOk : styles.cargaFalta}`}>
            {cumpleMin
              ? `✓ Carga suficiente (mínimo ${CARGA_MINIMA} kg)`
              : `⚠ Faltan ${(CARGA_MINIMA - kgTotales).toFixed(1)} kg para el mínimo requerido (${CARGA_MINIMA} kg)`
            }
          </div>
        )}

        <div className={styles.modalBody}>
          <div className={styles.campo}>
            <label>Chofer asignado</label>
            <input
              type="text"
              placeholder="Busca por nombre..."
              list="choferes-modal"
              value={choferNombre}
              autoComplete="off"
              onChange={e => {
                setChoferNombre(e.target.value);
                const encontrado = listaChoferes.find(c =>
                  `${c.NAME_DRIVER} ${c.LASTNAME}`.toLowerCase() === e.target.value.toLowerCase()
                );
                setChoferId(encontrado ? encontrado.ID_DRIVER : '');
              }}
            />
            <datalist id="choferes-modal">
              {listaChoferes.map(c => (
                <option key={c.ID_DRIVER} value={`${c.NAME_DRIVER} ${c.LASTNAME}`} />
              ))}
            </datalist>
          </div>

          <div className={styles.campo}>
            <label>Camión</label>
            <select value={camionId} onChange={e => setCamionId(e.target.value)} className={styles.selectCamion}>
              <option value="">Selecciona un camión...</option>
              {listaCamiones.map(c => (
                <option key={c.ID_TRUCK} value={c.ID_TRUCK}>
                  {c.BRAND} — {c.PLATE} ({Number(c.CAPACITY).toLocaleString()} kg)
                </option>
              ))}
            </select>
          </div>
        </div>

        {error && <p className={styles.errorMsg}>{error}</p>}

        <div className={styles.modalFooter}>
          <button className={styles.btnSecundario} onClick={onCerrar}>Cancelar</button>
          <button className={styles.btnPrimario} onClick={handleContinuar} disabled={generando}>
            {generando ? 'Generando...' : 'Continuar y generar PDF'}
          </button>
        </div>
      </div>
    </div>
  );
}

// ─── Card acordeón ────────────────────────────────────────────────────────────
function CardRuta({ ruta, listaCamiones, listaChoferes, capacidadMaxGlobal, onGenerarGuia, onAsignarCamion }) {
  const [abierta, setAbierta] = useState(false);

  const pedidos    = ruta.pending_orders ?? [];
  const kgUsados   = calcularKg(pedidos);
  const camion     = listaCamiones.find(c => String(c.ID_TRUCK) === String(ruta.TRUCK_ID));
  const capacidad  = camion ? Number(camion.CAPACITY) : capacidadMaxGlobal;
  const tieneCamion = !!camion;

  // Regla de carga mínima
  const esCamionChico = tieneCamion && Number(camion.CAPACITY) <= CAP_CAMION_CHICO;
  const minimoReq     = esCamionChico ? 0 : CARGA_MINIMA;
  const puedeGenerar  = pedidos.length > 0 && kgUsados >= minimoReq;

  return (
    <div className={styles.cardRuta}>
      <button className={styles.cardHeader} onClick={() => setAbierta(p => !p)}>
        <span className={styles.rutaNombre}>{ruta.NAME_ROUTE.toUpperCase()}</span>
        <div className={styles.barraContainer}>
          {!tieneCamion && <span className={styles.sinCamion}>⚠ Camión no asignado</span>}
          <BarraCapacidad usado={kgUsados} maximo={capacidad} />
        </div>
        <span className={`${styles.flecha} ${abierta ? styles.flechaAbierta : ''}`}>▼</span>
      </button>

      <div className={`${styles.panel} ${abierta ? styles.panelAbierto : ''}`}>
        <div className={styles.panelInner}>

          {/* IZQUIERDA — pedidos */}
          <div className={styles.panelIzq}>
            <div className={styles.panelSubHeader}>
              <h3 className={styles.panelSubtitulo}>Pedidos asignados:</h3>
              <button
                className={styles.btnAsignarCamion}
                onClick={() => onAsignarCamion(ruta)}
              >
                {tieneCamion ? `${camion.BRAND} (${camion.PLATE})` : 'Asignar camión'}
              </button>
            </div>

            {pedidos.length === 0 ? (
              <p className={styles.sinPedidos}>Sin pedidos asignados</p>
            ) : (
              <ul className={styles.listaPedidos}>
                {pedidos.map(p => (
                  <li key={p.ID_ORDER} className={styles.itemPedido}>
                    <span className={styles.itemCliente}>{p.NAME_CLIENT}</span>
                    <span className={styles.itemKg}>{Number(p.WEIGHT_REAL).toFixed(1)} kg</span>
                  </li>
                ))}
              </ul>
            )}

            {/* Advertencia de carga mínima */}
            {!esCamionChico && pedidos.length > 0 && !puedeGenerar && (
              <p className={styles.advertenciaMin}>
                ⚠ Faltan {(CARGA_MINIMA - kgUsados).toFixed(1)} kg para el mínimo ({CARGA_MINIMA} kg)
              </p>
            )}
          </div>

          {/* DERECHA — kg y botón */}
          <div className={styles.panelDer}>
            <div className={styles.kgDisplay}>
              <span className={styles.kgUsados}>{kgUsados.toFixed(0)}</span>
              <span className={styles.kgSep}>kg</span>
              <span className={styles.kgMax}>/ {capacidad.toLocaleString()} kg</span>
            </div>
            <button
              className={styles.btnGenerarGuia}
              onClick={() => onGenerarGuia(ruta)}
              disabled={!puedeGenerar}
              title={!puedeGenerar && !esCamionChico ? `Mínimo ${CARGA_MINIMA} kg requeridos` : ''}
            >
              Generar Guía de Despacho
            </button>
          </div>

        </div>
      </div>
    </div>
  );
}

// ─── Popup éxito ──────────────────────────────────────────────────────────────
function PopupExito({ mensaje }) {
  return (
    <div className={styles.overlay}>
      <div className={styles.popup}>
        <div className={styles.successIcon}>✓</div>
        <h2>Datos Registrados</h2>
        <p>{mensaje}</p>
      </div>
    </div>
  );
}

// ─── Componente principal ─────────────────────────────────────────────────────
export default function GestionRutas() {
  const [listaRutas,    setListaRutas]    = useState([]);
  const [listaCamiones, setListaCamiones] = useState([]);
  const [listaChoferes, setListaChoferes] = useState([]);
  const [cargando,      setCargando]      = useState(true);

  // Modales
  const [modalNuevaRuta,   setModalNuevaRuta]   = useState(false);
  const [modalGuia,        setModalGuia]        = useState(null);
  const [modalCamion,      setModalCamion]      = useState(null);
  const [guardando,        setGuardando]        = useState(false);
  const [errorModal,       setErrorModal]       = useState('');
  const [popupExito,       setPopupExito]       = useState('');
  // Al inicio del componente
  const [listaEstados, setListaEstados] = useState([]);

  
  async function cargarDatos() {
    setCargando(true);
    try {
      const [resRutas, resCam, resCho] = await Promise.all([
        routes.getAll(),
        trucks.getAll(),
        drivers.getAll(),
      ]);
      if (resCam.status === 'success') setListaCamiones(resCam.data);
      if (resCho.status === 'success') setListaChoferes(resCho.data);

      if (resRutas.status === 'success') {
        // Cargar detalle de cada ruta para obtener los pedidos
        const detalladas = await Promise.all(
          resRutas.data.map(async r => {
            try {
              const det = await routes.getById(r.ID_ROUTE);
              return det.status === 'success' ? det.data : r;
            } catch { return r; }
          })
        );
        setListaRutas(detalladas);
      }
    } catch (err) {
      console.error('Error cargando datos:', err);
    } finally {
      setCargando(false);
    }
  }

  useEffect(() => { cargarDatos(); }, []);

  const capacidadMaxGlobal = listaCamiones.length > 0
    ? Math.max(...listaCamiones.map(c => Number(c.CAPACITY) || 0))
    : 5000;

  function mostrarExito(msg) {
    setPopupExito(msg);
    setTimeout(() => setPopupExito(''), 2500);
  }

  // ─── Crear ruta ─────────────────────────────────────────────────────────
  async function crearRuta(form) {
  setErrorModal('');
  if (!form.name.trim()) { setErrorModal('El nombre es requerido'); return; }

  setGuardando(true);
  try {
    // 1. Crear la ruta
    const res = await routes.create({ name: form.name });
    if (res.status !== 'success') {
      setErrorModal(res.message || 'Error al crear la ruta'); return;
    }

    const nuevaRutaId = res.data.ID_ROUTE;

    // 2. Asignar cada estado seleccionado a la ruta
    await Promise.all(
      form.estados.map(stateId =>
        routes.assignState(nuevaRutaId, stateId)
      )
    );

    setModalNuevaRuta(false);
    await cargarDatos();
    mostrarExito('Ruta creada correctamente');
  } catch {
    setErrorModal('Error de conexión');
  } finally {
    setGuardando(false);
  }
}

  // ─── Asignar camión a ruta ───────────────────────────────────────────────
  async function asignarCamion(camionId) {
    setErrorModal('');
    setGuardando(true);
    try {
      // Guardamos el truck_id en la ruta actualizando su nombre (reutilizamos update)
      // El backend no tiene endpoint de asignación de camión a ruta directamente,
      // lo manejamos al generar la guía. Aquí solo lo guardamos localmente en el estado.
      setListaRutas(prev => prev.map(r =>
        r.ID_ROUTE === modalCamion.ID_ROUTE
          ? { ...r, TRUCK_ID: camionId ? parseInt(camionId) : null }
          : r
      ));
      setModalCamion(null);
      mostrarExito('Camión asignado a la ruta');
    } catch { setErrorModal('Error al asignar camión'); }
    finally { setGuardando(false); }
  }

  return (
    <div className={styles.appContainer}>
      <Header />

      <main className={styles.main}>
        <h1 className={styles.titulo}>Rutas</h1>

        {/* BOTÓN NUEVA RUTA */}
        <div className={styles.topBar}>
          <span className={styles.totalRutas}>{listaRutas.length} ruta(s)</span>
          <button
            className={styles.btnNuevaRuta}
            onClick={() => { setErrorModal(''); setModalNuevaRuta(true); }}
          >
            + Nueva ruta
          </button>
        </div>

        {/* LISTA */}
        <div className={styles.lista}>
          {cargando ? (
            <p className={styles.msgVacio}>Cargando rutas...</p>
          ) : listaRutas.length === 0 ? (
            <p className={styles.msgVacio}>No hay rutas registradas</p>
          ) : (
            listaRutas.map(ruta => (
              <CardRuta
                key={ruta.ID_ROUTE}
                ruta={ruta}
                listaCamiones={listaCamiones}
                listaChoferes={listaChoferes}
                capacidadMaxGlobal={capacidadMaxGlobal}
                onGenerarGuia={r => setModalGuia(r)}
                onAsignarCamion={r => { setErrorModal(''); setModalCamion(r); }}
              />
            ))
          )}
        </div>
      </main>

      {/* MODAL NUEVA RUTA */}
      {modalNuevaRuta && (
        <ModalRuta
          listaEstados={listaEstados}
          onGuardar={crearRuta}
          onCerrar={() => setModalNuevaRuta(false)}
          guardando={guardando}
          error={errorModal}
        />
      )}

      {/* MODAL ASIGNAR CAMIÓN */}
      {modalCamion && (
        <ModalAsignarCamion
          ruta={modalCamion}
          listaCamiones={listaCamiones}
          onGuardar={asignarCamion}
          onCerrar={() => setModalCamion(null)}
          guardando={guardando}
          error={errorModal}
        />
      )}

      {/* MODAL GENERAR GUÍA */}
      {modalGuia && (
        <ModalGuia
          ruta={modalGuia}
          listaChoferes={listaChoferes}
          listaCamiones={listaCamiones}
          capacidadRuta={capacidadMaxGlobal}
          onCerrar={() => setModalGuia(null)}
          onGenerada={() => {
            setModalGuia(null);
            mostrarExito('Guía generada correctamente. El PDF se abrió en una nueva pestaña.');
            cargarDatos();
          }}
        />
      )}

      {/* POPUP ÉXITO */}
      {popupExito && <PopupExito mensaje={popupExito} />}
    </div>
  );
}