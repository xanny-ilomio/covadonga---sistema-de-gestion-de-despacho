import { useState, useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { orders } from '../../api/client';
import Header from '../../components/Header';
import flota from '../../../public/assets/flota.svg';
import historial from '../../../public/assets/historial.svg';
import pedido from '../../../public/assets/pedido.svg';
import statistics from '../../../public/assets/statistics.svg';
import rutasIcon from '../../../public/assets/route.svg';
import styles from '../../styles/DashboardDespacho.module.css';

const INTERVALO_MS   = 60000;

export default function DashboardDespacho() {
    const navigate = useNavigate();
    const [pedidos, setPedidos] = useState([]);
    const [cargando, setCargando] = useState(true);
    const intervalRef = useRef(null);

    async function cargarPedidos() {
      try {
        const res = await orders.getAll('Pendiente');
        if (res.status === 'success') {
          setPedidos(res.data);
        }
      } catch (err) {
        console.error('Error cargando pedidos:', err);
      } finally {
        setCargando(false);
      }
    }

    useEffect(()=>{
        cargarPedidos();
        intervalRef.current=setInterval(cargarPedidos,INTERVALO_MS); //reloj de actualizacion
        return()=>clearInterval(intervalRef.current); //para apagar el reloj al salir
    },[]);

    function handlePedidoClick(idPedido){
        Navigate(`/despacho/pedido/${idPedido}`);
    }

  return (
    <div className={styles.appContainer}>
      <Header />

      <main className={styles.mainContent}>
        <div className={styles.dashboardGrid}>

          {/* COLUMNA IZQUIERDA BOTONES DE ACCESO*/}
          <section className={styles.actionSection}>
            <div className={styles.textGroup}>
              <h1 className={styles.sectionTitle}>Despacho</h1>
              <p className={styles.sectionSubtitle}>
                Gestión de rutas, flotas y asignación de despachos.
              </p>
            </div>

            <div className={styles.buttonGroup}>
              <button className={styles.menuButton} onClick={() => navigate('/despacho/pedidos')}>
                <span className={styles.btnIconP}><img src={pedido} alt="Pedidos"/></span> Pedidos
              </button>
              <button className={styles.menuButton} onClick={() => navigate('/despacho/rutas')}>
                <span className={styles.btnIcon}><img src={rutasIcon} alt="Rutas"/></span> Rutas
              </button>
              <button className={styles.menuButton} onClick={() => navigate('/despacho/flota')}>
                <span className={styles.btnIcon}><img src={flota} alt="Flota"/></span> Flota y Choferes
              </button>
              <button className={styles.menuButton} onClick={() => navigate('/despacho/guias')}>
                <span className={styles.btnIcon}><img src={historial} alt="Historial"/></span> Historial
              </button>
              <button className={styles.menuButton} onClick={() => navigate('/despacho/estadisticas')}>
              <span className={styles.btnIcon}><img src={statistics} alt="Estadísticas"/></span> Estadísticas
            </button>
            </div>
          </section>

          {/* COLUMNA DERECHA: PANEL DE PEDIDOS POR ASIGNAR*/}
          <section className={styles.rightLayoutGroup}>
            
            {/* PANEL CON LISTADO SCROLLABLE */}
            <div className={styles.notificationsSection}>
              <div className={styles.panelHeader}>
                <div className={styles.liveIndicator}>
                  <h2 className={styles.panelTitle}>Pedidos</h2>
                  <span className={styles.panelSubtitleBadge}>Por asignar</span>
                </div>
                <div className={styles.panelMeta}>
                  {pedidos.length > 0 && (
                    <span className={styles.badge}>{pedidos.length}</span>
                  )}
                </div>
              </div>

              <div className={styles.notificationList}>
                {cargando ? (
                  <p className={styles.emptyMsg}>Cargando registros...</p>
                ) : pedidos.length === 0 ? (
                  <div className={styles.emptyState}>
                    <span className={styles.emptyIcon}>✓</span>
                    <p className={styles.emptyMsg}>Todos los pedidos tienen ruta asignada</p>
                  </div>
                ) : (
                    pedidos.map((pedido) => (
                        <div key={pedido.ID_ORDER} className={styles.despachoRowCard} onClick={()=> handlePedidoClick(pedido.ID_ORDER)}>
                            <div className={styles.cardInfoGroup} onClick={() => navigate(`/despacho/pedido/${pedido.ID_ORDER}`)}>
                                <span className={styles.orderNumber}>#{String(pedido.ID_ORDER).padStart(5,'0')}</span>
                                <span className={styles.clientNameMin}>{pedido.NAME_CLIENT}</span>
                                <span className={styles.dateTag}>{pedido.CREATED_AT ? new Date(pedido.CREATED_AT).toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit' }) : '--/--'}</span>
                            </div>
                        </div>
                        ))
                )}
              </div>
            </div>
          </section>
        </div>
      </main>
    </div>
  );
}