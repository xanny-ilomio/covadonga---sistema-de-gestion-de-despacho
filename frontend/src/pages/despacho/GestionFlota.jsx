import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import Header from '../../components/Header';
import { trucks, drivers } from '../../api/client';
import styles from '../../styles/GestionFlota.module.css';

// ─── Popup de confirmación genérico ──────────────────────────────────────────
function PopupConfirm({ mensaje, onConfirmar, onCancelar }) {
  return (
    <div className={styles.overlay}>
      <div className={styles.popupConfirm}>
        <div className={styles.confirmIcon}>⚠</div>
        <h3>¿Estás seguro?</h3>
        <p>{mensaje}</p>
        <div className={styles.confirmBtns}>
          <button className={styles.btnCancelar} onClick={onCancelar}>Cancelar</button>
          <button className={styles.btnEliminar} onClick={onConfirmar}>Eliminar</button>
        </div>
      </div>
    </div>
  );
}

// ─── Popup de éxito ───────────────────────────────────────────────────────────
function PopupExito({ mensaje }) {
  return (
    <div className={styles.overlay}>
      <div className={styles.popup}>
        <div className={styles.successIcon}>✓</div>
        <h2>¡Listo!</h2>
        <p>{mensaje}</p>
      </div>
    </div>
  );
}

// ─── Modal de camión ──────────────────────────────────────────────────────────
function ModalCamion({ camion, onGuardar, onCerrar, guardando, error }) {
  const esEdicion = !!camion?.ID_TRUCK;
  const [form, setForm] = useState({
    brand:    camion?.BRAND    ?? '',
    plate:    camion?.PLATE    ?? '',
    capacity: camion?.CAPACITY ?? '',
  });

  function handleChange(campo, valor) {
    setForm(prev => ({ ...prev, [campo]: valor }));
  }

  return (
    <div className={styles.overlay}>
      <div className={styles.modal}>
        <div className={styles.modalHeader}>
          <h2 className={styles.modalTitulo}>
            {esEdicion ? 'Editar camión' : 'Nuevo camión'}
          </h2>
          <button className={styles.btnCerrar} onClick={onCerrar}>✕</button>
        </div>

        <div className={styles.modalBody}>
          <div className={styles.campo}>
            <label>Marca</label>
            <input
              type="text"
              placeholder="Ford, Iveco..."
              value={form.brand}
              onChange={e => handleChange('brand', e.target.value)}
            />
          </div>
          <div className={styles.campo}>
            <label>Placa</label>
            <input
              type="text"
              placeholder="ABC123"
              value={form.plate}
              onChange={e => handleChange('plate', e.target.value.toUpperCase())}
            />
          </div>
          <div className={styles.campo}>
            <label>Capacidad (kg)</label>
            <input
              type="number"
              min="1"
              placeholder="5000"
              value={form.capacity}
              onChange={e => handleChange('capacity', e.target.value)}
            />
          </div>
        </div>

        {error && <p className={styles.errorMsg}>{error}</p>}

        <div className={styles.modalFooter}>
          <button className={styles.btnSecundario} onClick={onCerrar}>
            Cancelar
          </button>
          <button
            className={styles.btnPrimario}
            onClick={() => onGuardar(form)}
            disabled={guardando}
          >
            {guardando ? 'Guardando...' : esEdicion ? 'Guardar cambios' : 'Agregar camión'}
          </button>
        </div>
      </div>
    </div>
  );
}

// ─── Modal de chofer ──────────────────────────────────────────────────────────
function ModalChofer({ chofer, onGuardar, onCerrar, guardando, error }) {
  const esEdicion = !!chofer?.ID_DRIVER;
  const [form, setForm] = useState({
    name:     chofer?.NAME_DRIVER  ?? '',
    lastname: chofer?.LASTNAME     ?? '',
    ci:       chofer?.CI           ?? '',
    phone:    chofer?.PHONE_DRIVER ?? '',
  });

  function handleChange(campo, valor) {
    setForm(prev => ({ ...prev, [campo]: valor }));
  }

  return (
    <div className={styles.overlay}>
      <div className={styles.modal}>
        <div className={styles.modalHeader}>
          <h2 className={styles.modalTitulo}>
            {esEdicion ? 'Editar chofer' : 'Nuevo chofer'}
          </h2>
          <button className={styles.btnCerrar} onClick={onCerrar}>✕</button>
        </div>

        <div className={styles.modalBody}>
          <div className={styles.campoGrid}>
            <div className={styles.campo}>
              <label>Nombre</label>
              <input
                type="text"
                placeholder="Juan"
                value={form.name}
                onChange={e => handleChange('name', e.target.value)}
              />
            </div>
            <div className={styles.campo}>
              <label>Apellido</label>
              <input
                type="text"
                placeholder="Pérez"
                value={form.lastname}
                onChange={e => handleChange('lastname', e.target.value)}
              />
            </div>
          </div>
          <div className={styles.campo}>
            <label>Cédula</label>
            <input
              type="number"
              placeholder="12345678"
              value={form.ci}
              onChange={e => handleChange('ci', e.target.value)}
            />
          </div>
          <div className={styles.campo}>
            <label>Teléfono</label>
            <input
              type="number"
              placeholder="4141234567"
              value={form.phone}
              onChange={e => handleChange('phone', e.target.value)}
            />
          </div>
        </div>

        {error && <p className={styles.errorMsg}>{error}</p>}

        <div className={styles.modalFooter}>
          <button className={styles.btnSecundario} onClick={onCerrar}>
            Cancelar
          </button>
          <button
            className={styles.btnPrimario}
            onClick={() => onGuardar(form)}
            disabled={guardando}
          >
            {guardando ? 'Guardando...' : esEdicion ? 'Guardar cambios' : 'Agregar chofer'}
          </button>
        </div>
      </div>
    </div>
  );
}

// ─── Componente principal ─────────────────────────────────────────────────────
export default function GestionFlota() {
  const navigate = useNavigate();

  const [tab,        setTab]        = useState('camiones'); // 'camiones' | 'choferes'
  const [listaCamiones, setListaCamiones] = useState([]);
  const [listaChoferes, setListaChoferes] = useState([]);
  const [cargando,   setCargando]   = useState(true);

  // Modales
  const [modalCamion,  setModalCamion]  = useState(null); // null | {} | {camion}
  const [modalChofer,  setModalChofer]  = useState(null);
  const [confirmElim,  setConfirmElim]  = useState(null); // { tipo, id, nombre }
  const [guardando,    setGuardando]    = useState(false);
  const [errorModal,   setErrorModal]   = useState('');
  const [popupExito,   setPopupExito]   = useState('');   // mensaje de éxito

  // ─── Cargar datos ───────────────────────────────────────────────────────
  async function cargarDatos() {
    setCargando(true);
    try {
      const [resCam, resCho] = await Promise.all([
        trucks.getAll(),
        drivers.getAll(),
      ]);
      if (resCam.status === 'success') setListaCamiones(resCam.data);
      if (resCho.status === 'success') setListaChoferes(resCho.data);
    } catch (err) {
      console.error('Error cargando flota:', err);
    } finally {
      setCargando(false);
    }
  }

  useEffect(() => { cargarDatos(); }, []);

  // ─── Mostrar popup de éxito brevemente ─────────────────────────────────
  function mostrarExito(msg) {
    setPopupExito(msg);
    setTimeout(() => setPopupExito(''), 2000);
  }

  // ─── CAMIONES ───────────────────────────────────────────────────────────
  async function guardarCamion(form) {
    setErrorModal('');
    if (!form.brand.trim()) { setErrorModal('La marca es requerida'); return; }
    if (!form.plate.trim()) { setErrorModal('La placa es requerida'); return; }
    if (!form.capacity || form.capacity <= 0) { setErrorModal('La capacidad debe ser mayor a 0'); return; }

    setGuardando(true);
    try {
      const esEdicion = !!modalCamion?.ID_TRUCK;
      const res = esEdicion
        ? await trucks.update(modalCamion.ID_TRUCK, form)
        : await trucks.create(form);

      if (res.status === 'success') {
        setModalCamion(null);
        await cargarDatos();
        mostrarExito(esEdicion ? 'Camión actualizado correctamente' : 'Camión agregado correctamente');
      } else {
        setErrorModal(res.message || 'Error al guardar');
      }
    } catch (err) {
      setErrorModal('Error de conexión');
    } finally {
      setGuardando(false);
    }
  }

  async function eliminarCamion(id) {
    try {
      const res = await trucks.delete(id);
      if (res.status === 'success') {
        setConfirmElim(null);
        await cargarDatos();
        mostrarExito('Camión eliminado correctamente');
      }
    } catch (err) {
      console.error(err);
    }
  }

  // ─── CHOFERES ───────────────────────────────────────────────────────────
  async function guardarChofer(form) {
    setErrorModal('');
    if (!form.name.trim())     { setErrorModal('El nombre es requerido'); return; }
    if (!form.lastname.trim()) { setErrorModal('El apellido es requerido'); return; }
    if (!form.ci)              { setErrorModal('La cédula es requerida'); return; }
    if (!form.phone)           { setErrorModal('El teléfono es requerido'); return; }

    setGuardando(true);
    try {
      const esEdicion = !!modalChofer?.ID_DRIVER;
      const res = esEdicion
        ? await drivers.update(modalChofer.ID_DRIVER, form)
        : await drivers.create(form);

      if (res.status === 'success') {
        setModalChofer(null);
        await cargarDatos();
        mostrarExito(esEdicion ? 'Chofer actualizado correctamente' : 'Chofer agregado correctamente');
      } else {
        setErrorModal(res.message || 'Error al guardar');
      }
    } catch (err) {
      setErrorModal('Error de conexión');
    } finally {
      setGuardando(false);
    }
  }

  async function eliminarChofer(id) {
    try {
      const res = await drivers.delete(id);
      if (res.status === 'success') {
        setConfirmElim(null);
        await cargarDatos();
        mostrarExito('Chofer eliminado correctamente');
      }
    } catch (err) {
      console.error(err);
    }
  }

  // ─── Render ─────────────────────────────────────────────────────────────
  const listaMostrada = tab === 'camiones' ? listaCamiones : listaChoferes;

  return (
    <div className={styles.appContainer}>
      <Header />

      <main className={styles.main}>
        <h1 className={styles.titulo}>Flota y Choferes</h1>

        {/* TABS */}
        <div className={styles.tabs}>
          <button
            className={`${styles.tab} ${tab === 'camiones' ? styles.tabActivo : ''}`}
            onClick={() => setTab('camiones')}
          >
            Camiones
          </button>
          <button
            className={`${styles.tab} ${tab === 'choferes' ? styles.tabActivo : ''}`}
            onClick={() => setTab('choferes')}
          >
            Choferes
          </button>
        </div>

        {/* HEADER DE LISTA */}
        <div className={styles.listaHeader}>
          <span className={styles.listaCount}>
            {listaMostrada.length} {tab === 'camiones' ? 'camión(es)' : 'chofer(es)'}
          </span>
          <button
            className={styles.btnAgregar}
            onClick={() => {
              setErrorModal('');
              tab === 'camiones' ? setModalCamion({}) : setModalChofer({});
            }}
          >
            + Agregar {tab === 'camiones' ? 'camión' : 'chofer'}
          </button>
        </div>

        {/* LISTA */}
        <div className={styles.lista}>
          {cargando ? (
            <p className={styles.msgVacio}>Cargando...</p>
          ) : listaMostrada.length === 0 ? (
            <p className={styles.msgVacio}>
              No hay {tab === 'camiones' ? 'camiones' : 'choferes'} registrados
            </p>
          ) : tab === 'camiones' ? (
            listaCamiones.map(cam => (
              <div key={cam.ID_TRUCK} className={styles.card}>
                <div className={styles.cardIzq}>
                  <span className={styles.cardIcono}></span>
                  <div className={styles.cardInfo}>
                    <span className={styles.cardNombre}>{cam.BRAND}</span>
                    <span className={styles.cardSub}>
                      {cam.PLATE} · {Number(cam.CAPACITY).toLocaleString()} kg
                    </span>
                  </div>
                </div>
                <div className={styles.cardAcciones}>
                  <button
                    className={styles.btnEditar}
                    onClick={() => { setErrorModal(''); setModalCamion(cam); }}
                    title="Editar"
                  >
                    ✏
                  </button>
                  <button
                    className={styles.btnEliminarCard}
                    onClick={() => setConfirmElim({
                      tipo: 'camion',
                      id:   cam.ID_TRUCK,
                      nombre: `${cam.BRAND} (${cam.PLATE})`,
                    })}
                    title="Eliminar"
                  >
                    🗑
                  </button>
                </div>
              </div>
            ))
          ) : (
            listaChoferes.map(cho => (
              <div key={cho.ID_DRIVER} className={styles.card}>
                <div className={styles.cardIzq}>
                  <span className={styles.cardIcono}></span>
                  <div className={styles.cardInfo}>
                    <span className={styles.cardNombre}>
                      {cho.NAME_DRIVER} {cho.LASTNAME}
                    </span>
                    <span className={styles.cardSub}>
                      CI: {cho.CI} · {cho.PHONE_DRIVER}
                    </span>
                  </div>
                </div>
                <div className={styles.cardAcciones}>
                  <button
                    className={styles.btnEditar}
                    onClick={() => { setErrorModal(''); setModalChofer(cho); }}
                    title="Editar"
                  >
                    ✏
                  </button>
                  <button
                    className={styles.btnEliminarCard}
                    onClick={() => setConfirmElim({
                      tipo: 'chofer',
                      id:   cho.ID_DRIVER,
                      nombre: `${cho.NAME_DRIVER} ${cho.LASTNAME}`,
                    })}
                    title="Eliminar"
                  >
                    🗑
                  </button>
                </div>
              </div>
            ))
          )}
        </div>
      </main>

      {/* MODAL CAMIÓN */}
      {modalCamion !== null && (
        <ModalCamion
          camion={modalCamion?.ID_TRUCK ? modalCamion : null}
          onGuardar={guardarCamion}
          onCerrar={() => setModalCamion(null)}
          guardando={guardando}
          error={errorModal}
        />
      )}

      {/* MODAL CHOFER */}
      {modalChofer !== null && (
        <ModalChofer
          chofer={modalChofer?.ID_DRIVER ? modalChofer : null}
          onGuardar={guardarChofer}
          onCerrar={() => setModalChofer(null)}
          guardando={guardando}
          error={errorModal}
        />
      )}

      {/* POPUP CONFIRMAR ELIMINACIÓN */}
      {confirmElim && (
        <PopupConfirm
          mensaje={`¿Deseas eliminar a ${confirmElim.nombre}? Esta acción no se puede deshacer.`}
          onConfirmar={() =>
            confirmElim.tipo === 'camion'
              ? eliminarCamion(confirmElim.id)
              : eliminarChofer(confirmElim.id)
          }
          onCancelar={() => setConfirmElim(null)}
        />
      )}

      {/* POPUP ÉXITO */}
      {popupExito && <PopupExito mensaje={popupExito} />}
    </div>
  );
}