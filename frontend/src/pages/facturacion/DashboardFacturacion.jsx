import { useState, useEffect, useRef } from 'react';
import { useAuth } from '../../context/AuthContext';
import { useNavigate } from 'react-router-dom';
import { orders } from '../../api/client';
import logo from '../../../public/icons/isotipo_blanco.svg';
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
  const { user, logout } = useAuth();
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

  function handleLogout() {
    clearInterval(intervalRef.current);
    logout();
    navigate('/');
  }

  const pedidosVisibles = pedidos.filter(p => !vistos.has(p.ID_ORDER));

  return (
    <div className={styles.appContainer}>

      <header className={styles.navbar}>
        <div className={styles.leftSpacer}></div>
        <div className={styles.navbarCenter}>
          <img src={logo} alt="Logo Covadonga" className={styles.brandLogo} />
        </div>
        <div className={styles.navbarRight}>
          <button className={styles.logoutButton} onClick={handleLogout}>
            Cerrar Sesión
          </button>
        </div>
      </header>

      <main className={styles.mainContent}>
        <div className={styles.dashboardGrid}>

          <section className={styles.actionSection}>
            <div className={styles.textGroup}>
              <h1 className={styles.sectionTitle}>Módulo de Facturación</h1>
              <p className={styles.sectionSubtitle}>
                Bienvenido, {user?.username}. Seleccione una sección para comenzar.
              </p>
            </div>
            <div className={styles.buttonGroup}>
              <button className={`${styles.menuButton} ${styles.menuButtonActive}`}>
                <span className={styles.btnIcon}>📋</span> Gestionar Pedidos
              </button>
              <button className={styles.menuButton}>
                <span className={styles.btnIcon}>⏱️</span> Consultar Historial
              </button>
              <button className={styles.menuButton}>
                <span className={styles.btnIcon}>📊</span> Ver Estadísticas
              </button>
            </div>
          </section>

          <section className={styles.notificationsSection}>
            <div className={styles.panelHeader}>
              <div className={styles.liveIndicator}>
                <span className={styles.pingPulse}></span>
                <h2 className={styles.panelTitle}>Pedidos listos para descargar</h2>
              </div>
              <div className={styles.panelMeta}>
                <span className={styles.panelSubtitle}>Actualiza cada 30s</span>
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
                  <p className={styles.emptyMsg}>No hay pedidos listos por ahora</p>
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