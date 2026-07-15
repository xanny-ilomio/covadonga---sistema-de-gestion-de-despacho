import { useState, useEffect, useRef } from 'react';
import { useAuth } from '../../context/AuthContext';
import { useNavigate } from 'react-router-dom';
import { orders } from '../../api/client';
import Header from '../../components/Header';
import logo from '../../../public/icons/isotipo_blanco.svg';
import flota from '../../../public/assets/flota.svg';
import download from '../../../public/assets/download.svg';
import historial from '../../../public/assets/historial.svg';
import logout from '../../../public/assets/logout.svg';
import pedido from '../../../public/assets/pedido.svg';
import statistics from '../../../public/assets/statistics.svg';
import styles from '../../styles/DashboardFacturacion.module.css';

const LIMITE_PEDIDOS = 8;
const INTERVALO_MS   = 30000;
const BASE_URL       = 'http://localhost:8888';

// Convierte timestamp a "Hace X min / horas / días"
function tiempoRelativo(fechaStr) {
  if (!fechaStr) return '';
  const ahora    = new Date();
  const fecha    = new Date(fechaStr);
  const diffMins = Math.floor((ahora - fecha) / 60000);

  if (diffMins < 1)  return 'Hace un momento';
  if (diffMins < 60) return `Hace ${diffMins} min`;

  const diffHoras = Math.floor(diffMins / 60);
  if (diffHoras < 24) return `Hace ${diffHoras} h`;

  const diffDias = Math.floor(diffHoras / 24);
  return `Hace ${diffDias} día${diffDias > 1 ? 's' : ''}`;
}

export default function DashboardFacturacion() {
  const navigate         = useNavigate();

  const [pedidos, setPedidos]         = useState([]);
  const [cargando, setCargando]       = useState(true);
  const [descargando, setDescargando] = useState(new Set());
  const intervalRef                   = useRef(null);
  const [vistos, setVistos] = useState(() => {
  const guardados = localStorage.getItem('pedidos_vistos');
    return guardados ? new Set(JSON.parse(guardados)) : new Set();
  });
  const [, setTick] = useState(0);

  useEffect(() => {
    // Actualiza el componente cada 60 segundos para refrescar los tiempos
    const tickInterval = setInterval(() => {
      setTick(t => t + 1);
    }, 60000);
    return () => clearInterval(tickInterval);
  }, []);

  async function cargarPedidos() {
    try {
      const res = await orders.getAll('Asignado');
      if (res.status === 'success') {
        setPedidos(res.data.slice(0, LIMITE_PEDIDOS));
      }
    } catch (err) {
      console.error('Error cargando pedidos:', err);
    } finally {
      setCargando(false);
    }
  }

  useEffect(() => {
    cargarPedidos();
    intervalRef.current = setInterval(cargarPedidos, INTERVALO_MS);
    return () => clearInterval(intervalRef.current);
  }, []);

  async function descargarPedido(idPedido) {
    setDescargando(prev => new Set([...prev, idPedido]));

    try {
      const token = localStorage.getItem('token');
      const res   = await fetch(`${BASE_URL}/orders/${idPedido}/pdf`, {
        headers: { Authorization: `Bearer ${token}` },
      });

      if (res.ok) {
        // El backend devuelve PDF binario — blob con tipo application/pdf
        const blob     = await res.blob();
        const pdfBlob  = new Blob([blob], { type: 'application/pdf' });
        const url      = URL.createObjectURL(pdfBlob);

        // Crear enlace temporal y hacer clic — descarga directa
        const link     = document.createElement('a');
        link.href      = url;
        link.download  = `pedido-${String(idPedido).padStart(5, '0')}.pdf`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        // Liberar memoria después de 60s
        setTimeout(() => URL.revokeObjectURL(url), 60000);

        // Quitar de la lista de notificaciones
        setVistos(prev => {
          const next = new Set([...prev, idPedido]);
          localStorage.setItem('pedidos_vistos', JSON.stringify([...next]));
          return next;
        });
      } else {
        alert('Error al generar el PDF del pedido');
      }
    } catch (err) {
      console.error('Error descargando PDF:', err);
      alert('Error de conexión al descargar el PDF');
    } finally {
      setDescargando(prev => {
        const next = new Set(prev);
        next.delete(idPedido);
        return next;
      });
    }
  }

  const pedidosVisibles = pedidos.filter(p => !vistos.has(p.ID_ORDER));

  return (
    <div className={styles.appContainer}>

      <Header/>

      <main className={styles.mainContent}>
        <div className={styles.dashboardGrid}>

          <section className={styles.actionSection}>
            <div className={styles.textGroup}>
              <h1 className={styles.sectionTitle}>Facturación</h1>
              <p className={styles.sectionSubtitle}>
                Seleccione una sección para comenzar.
              </p>
            </div>
            <div className={styles.buttonGroup}>
              <button className={styles.menuButton} onClick={() => navigate('/facturacion/registrar_pedido')}>
                <span className={styles.btnIconP}><img src={pedido}/></span> Registrar Pedido
              </button>
              <button className={styles.menuButton} onClick={() => navigate('/facturacion/pedidos')}>
                <span className={styles.btnIconP}><img src={pedido}/></span> Estado de Pedidos
              </button>
              <button className={styles.menuButton}  onClick={() => navigate('/facturacion/estadisticas')}>
                <span className={styles.btnIcon}><img src={statistics}/></span> Ver Estadísticas
              </button>
            </div>
          </section>

          <section className={styles.notificationsSection}>
            <div className={styles.panelHeader}>
              <div className={styles.liveIndicator}>
                <h2 className={styles.panelTitle}>Pedidos actualizados</h2>
              </div>
              <div className={styles.panelMeta}>
                <span className={styles.panelSubtitle}>Listos para descargar.</span>
                {pedidosVisibles.length > 0 && (
                  <span className={styles.badge}>{pedidosVisibles.length}</span>
                )}
              </div>
            </div>

            <div className={styles.notificationList}>
              {cargando ? (
                <p className={styles.emptyMsg}>Cargando pedidos...</p>
              ) : pedidosVisibles.length === 0 ? (
                <div className={styles.emptyState}>
                  <span className={styles.emptyIcon}>✓</span>
                  <p className={styles.emptyMsg}>No hay pedidos sin revisar por ahora</p>
                </div>
              ) : (
                pedidosVisibles.map((pedido) => {
                  const estaDescargando = descargando.has(pedido.ID_ORDER);
                  return (
                    <div key={pedido.ID_ORDER} className={styles.notificationCard}>
                      <div className={styles.cardBody}>
                        <span className={styles.orderBadge}>
                          #{String(pedido.ID_ORDER).padStart(5, '0')}
                        </span>
                        <div className={styles.clientDetails}>
                          <span className={styles.clientName}>{pedido.NAME_CLIENT}</span>
                          <span className={styles.timeTag}>
                            {pedido.NAME_ROUTE ?? 'Sin ruta'} · {tiempoRelativo(pedido.UPDATED_AT)}
                          </span>
                        </div>
                      </div>

                      <button
                        className={styles.downloadButton}
                        onClick={() => descargarPedido(pedido.ID_ORDER)}
                        disabled={estaDescargando}
                        title="Descargar PDF"
                      >
                        {estaDescargando ? '...' : '⬇'}
                      </button>
                    </div>
                  );
                })
              )}
            </div>

            <button
              className={styles.refreshButton}
              onClick={cargarPedidos}
              disabled={cargando}
            >
              ↻ Actualizar ahora
            </button>
          </section>

        </div>
      </main>
    </div>
  );
}