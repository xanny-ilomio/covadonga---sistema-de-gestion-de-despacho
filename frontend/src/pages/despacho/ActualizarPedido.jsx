import { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import Header from '../../components/Header';
import { orders } from '../../api/client';
import styles from '../../styles/ActualizarPedido.module.css';

export default function ActualizarPedido() {
  const { id }   = useParams();   // /despacho/pedido/:id
  const navigate = useNavigate();

  const [pedido,    setPedido]    = useState(null);
  const [pesos,     setPesos]     = useState({});   // { id_op: valor }
  const [cargando,  setCargando]  = useState(true);
  const [guardando, setGuardando] = useState(false);
  const [popup,     setPopup]     = useState(false);
  const [error,     setError]     = useState('');

  // ─── Cargar datos del pedido ──────────────────────────────────────────────
useEffect(() => {
  async function cargar() {
    try {
      const res = await orders.getById(parseInt(id));
      if (res.status === 'success') {
        // Si el pedido no está Pendiente, no se puede editar
        if (res.data.STATUS !== 'Pendiente') {
          navigate(-1);
          return;
        }
        setPedido(res.data);
        const pesosIniciales = {};
        res.data.items.forEach(item => {
          pesosIniciales[item.ID_OP] = item.WEIGHT_REAL ?? '';
        });
        setPesos(pesosIniciales);
      }
    } catch (err) {
      console.error('Error cargando pedido:', err);
    } finally {
      setCargando(false);
    }
  }
  cargar();
}, [id]);

  // ─── Manejar cambio de peso por ítem ─────────────────────────────────────
  function handlePeso(idOp, valor) {
    setPesos(prev => ({ ...prev, [idOp]: valor }));
  }

  // ─── Total de peso real calculado en tiempo real ──────────────────────────
  const totalReal = Object.values(pesos).reduce(
    (sum, v) => sum + (Number(v) || 0), 0
  );

  // ─── Guardar pesos ────────────────────────────────────────────────────────
  async function handleGuardar() {
    setError('');

    // Validar que todos los ítems tienen peso real ingresado
    const items = pedido.items.map(item => ({
      id_op:       item.ID_OP,
      weight_real: Number(pesos[item.ID_OP]),
    }));

    if (items.some(i => !i.weight_real || i.weight_real <= 0)) {
      setError('Ingresa el peso real de todos los productos antes de guardar');
      return;
    }

    setGuardando(true);
    try {
      const res = await orders.updateWeights(parseInt(id), { items });
      if (res.status === 'success') {
        setPopup(true);
        setTimeout(() => navigate(-1), 2500);
      } else {
        setError(res.message || 'Error al guardar los pesos');
      }
    } catch (err) {
      console.error(err);
      setError('Error de conexión al guardar');
    } finally {
      setGuardando(false);
    }
  }

  // ─── Loading ──────────────────────────────────────────────────────────────
  if (cargando) {
    return (
      <div className={styles.appContainer}>
        <Header />
        <div className={styles.loading}>Cargando pedido...</div>
      </div>
    );
  }

  if (!pedido) {
    return (
      <div className={styles.appContainer}>
        <Header />
        <div className={styles.loading}>Pedido no encontrado</div>
      </div>
    );
  }

  const numPedido = String(pedido.ID_ORDER).padStart(5, '0');

  return (
    <div className={styles.appContainer}>
      <Header />

      <main className={styles.main}>
        <div className={styles.card}>

          {/* ENCABEZADO */}
          <div className={styles.cardHeader}>
            <button
              className={styles.backBtn}
              onClick={() => navigate(-1)}
              type="button"
            >
              ← Volver
            </button>
            <div className={styles.headerCenter}>
              <span className={styles.numPedido}>#{numPedido}</span>
              <span className={styles.estadoBadge}>{pedido.STATUS}</span>
            </div>
            <div style={{ width: '80px' }} />
          </div>

          {/* INFO DEL CLIENTE */}
          <div className={styles.infoGrid}>
            <div className={styles.infoItem}>
              <span className={styles.infoLabel}>Cliente</span>
              <span className={styles.infoValor}>{pedido.NAME_CLIENT}</span>
            </div>
            <div className={styles.infoItem}>
              <span className={styles.infoLabel}>RIF</span>
              <span className={styles.infoValor}>{pedido.RIF}</span>
            </div>
            <div className={styles.infoItem}>
              <span className={styles.infoLabel}>Ciudad</span>
              <span className={styles.infoValor}>{pedido.NAME_CITY}</span>
            </div>
            <div className={styles.infoItem}>
              <span className={styles.infoLabel}>Estado</span>
              <span className={styles.infoValor}>{pedido.NAME_STATE}</span>
            </div>
            <div className={styles.infoItem}>
              <span className={styles.infoLabel}>Ruta asignada</span>
              <span className={styles.infoValor}>{pedido.NAME_ROUTE ?? 'Por asignar'}</span>
            </div>
            <div className={styles.infoItem}>
              <span className={styles.infoLabel}>Fecha</span>
              <span className={styles.infoValor}>{pedido.DATE_ORDERED}</span>
            </div>
          </div>

          <div className={styles.divider} />

          {/* TABLA DE PRODUCTOS */}
          <h2 className={styles.subtitulo}>Actualizar pesos reales</h2>
          <p className={styles.hint}>
            Ingresa el peso real en kg de cada producto tras el picking.
            Los demás datos no pueden modificarse.
          </p>

          <div className={styles.tableWrapper}>
            <table className={styles.tabla}>
              <thead>
                <tr>
                  <th>Producto</th>
                  <th>Bultos</th>
                  <th>Peso aprox.</th>
                  <th>Peso real (kg)</th>
                </tr>
              </thead>
              <tbody>
                {pedido.items.map(item => (
                  <tr key={item.ID_OP}>
                    <td className={styles.tdProducto}>{item.NAME_PRODUCT}</td>
                    <td className={styles.tdCentro}>{item.AMOUNT}</td>
                    <td className={styles.tdCentro}>
                      {Number(item.WEIGHT_APROX).toFixed(2)} kg
                    </td>
                    <td className={styles.tdInput}>
                      <input
                        type="number"
                        min="0.01"
                        step="0.01"
                        placeholder="0.00"
                        value={pesos[item.ID_OP] ?? ''}
                        onChange={e => handlePeso(item.ID_OP, e.target.value)}
                        className={styles.inputPeso}
                      />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* TOTALES */}
          <div className={styles.totalesRow}>
            <div className={styles.totalItem}>
              <span className={styles.totalLabel}>Peso aprox. total</span>
              <span className={styles.totalValor}>
                {Number(pedido.WEIGHT).toFixed(2)} kg
              </span>
            </div>
            <div className={styles.totalItem}>
              <span className={styles.totalLabel}>Peso real total</span>
              <span className={`${styles.totalValor} ${styles.totalReal}`}>
                {totalReal.toFixed(2)} kg
              </span>
            </div>
          </div>

          {/* ERROR */}
          {error && <p className={styles.errorMsg}>{error}</p>}

          {/* BOTÓN GUARDAR */}
          <button
            className={styles.btnGuardar}
            onClick={handleGuardar}
            disabled={guardando}
          >
            {guardando ? 'Guardando...' : 'Guardar pesos y asignar ruta'}
          </button>

        </div>
      </main>

      {/* POPUP ÉXITO */}
      {popup && (
        <div className={styles.overlay}>
          <div className={styles.popup}>
            <div className={styles.successIcon}>✓</div>
            <h2>Pedido Actualizado</h2>
            <p>Los pesos fueron guardados y el pedido fue asignado a su ruta.</p>
          </div>
        </div>
      )}
    </div>
  );
}