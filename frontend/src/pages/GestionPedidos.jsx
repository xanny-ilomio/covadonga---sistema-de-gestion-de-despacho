import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import Header from '../components/Header';
import { useAuth } from '../context/AuthContext';
import { orders } from '../api/client';
import styles from '../styles/GestionPedidos.module.css';

const BASE_URL = 'http://localhost:8888';

// Formatea "2026-07-01" → "01/07"
function formatFecha(fechaStr) {
  if (!fechaStr) return '';
  const [, mes, dia] = fechaStr.split('-');
  return `${dia}/${mes}`;
}

export default function GestionPedidos() {
  const { user } = useAuth();
  const navigate  = useNavigate();
  const rol       = user?.rol; // 'facturacion' | 'despacho'

  const [filtro,    setFiltro]    = useState('Pendiente');
  const [pedidos,   setPedidos]   = useState([]);
  const [cargando,  setCargando]  = useState(true);
  const [descargando, setDescargando] = useState(new Set());

  // ─── Cargar pedidos según filtro ─────────────────────────────────────────
  useEffect(() => {
    async function cargar() {
      setCargando(true);
      try {
        const res = await orders.getAll(filtro);
        if (res.status === 'success') setPedidos(res.data);
      } catch (err) {
        console.error('Error cargando pedidos:', err);
      } finally {
        setCargando(false);
      }
    }
    cargar();
  }, [filtro]);

  // ─── Descargar PDF del pedido ─────────────────────────────────────────────
  async function descargarPdf(idPedido) {
    setDescargando(prev => new Set([...prev, idPedido]));
    try {
      const token = localStorage.getItem('token');
      const res   = await fetch(`${BASE_URL}/orders/${idPedido}/pdf`, {
        headers: { Authorization: `Bearer ${token}` },
      });
      if (res.ok) {
        const blob    = await res.blob();
        const pdfBlob = new Blob([blob], { type: 'application/pdf' });
        const url     = URL.createObjectURL(pdfBlob);
        const link    = document.createElement('a');
        link.href     = url;
        link.download = `pedido-${String(idPedido).padStart(5, '0')}.pdf`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        setTimeout(() => URL.revokeObjectURL(url), 60000);
      } else {
        alert('Error al generar el PDF');
      }
    } catch (err) {
      console.error(err);
      alert('Error de conexión');
    } finally {
      setDescargando(prev => {
        const next = new Set(prev);
        next.delete(idPedido);
        return next;
      });
    }
  }

  // ─── Render ───────────────────────────────────────────────────────────────
  return (
    <div className={styles.appContainer}>
      <Header />

      <main className={styles.main}>

        <h1 className={styles.titulo}>Pedidos</h1>

        {/* FILTROS */}
        <div className={styles.filtros}>
          {['Pendiente', 'Asignado', 'Despachado'].map(estado => (
            <button
              key={estado}
              className={`${styles.filtroBtn} ${styles[`filtro${estado}`]} ${filtro === estado ? styles.filtroActivo : ''}`}
              onClick={() => setFiltro(estado)}
            >
              {estado}
            </button>
          ))}
        </div>

        {/* LISTA */}
        <div className={styles.lista}>
          {cargando ? (
            <p className={styles.msgVacio}>Cargando pedidos...</p>
          ) : pedidos.length === 0 ? (
            <p className={styles.msgVacio}>No hay pedidos en estado {filtro}</p>
          ) : (
            pedidos.map(pedido => {
              const estaDescargando = descargando.has(pedido.ID_ORDER);
              const numPedido       = String(pedido.ID_ORDER).padStart(5, '0');
              const mostrarDescarga = filtro === 'Asignado' || filtro === 'Despachado';
              const mostrarEditar   = rol === 'despacho' && filtro === 'Pendiente';

              return (
                <div key={pedido.ID_ORDER} className={styles.card}>

                  {/* LADO IZQUIERDO */}
                  <div className={styles.cardIzq}>
                    <span className={styles.numPedido}>#{numPedido}</span>
                    <span className={styles.nombreCliente}>{pedido.NAME_CLIENT}</span>
                  </div>

                  {/* LADO DERECHO */}
                  <div className={styles.cardDer}>
                    <div className={styles.cardInfo}>
                      <span className={styles.fecha}>{formatFecha(pedido.DATE_ORDERED)}</span>
                      <span className={styles.ruta}>
                        {pedido.NAME_ROUTE ?? 'Sin ruta'}
                      </span>
                    </div>

                    {/* BOTÓN EDITAR — solo despacho en Pendiente */}
                    {mostrarEditar && (
                      <button
                        className={styles.btnEditar}
                        onClick={() => navigate(`/despacho/pedido/${pedido.ID_ORDER}`)}
                        title="Editar pesos"
                      >
                        ✏
                      </button>
                    )}

                    {/* BOTÓN DESCARGAR — Asignado o Despachado */}
                    {mostrarDescarga && (
                      <button
                        className={styles.btnDescargar}
                        onClick={() => descargarPdf(pedido.ID_ORDER)}
                        disabled={estaDescargando}
                        title="Descargar PDF"
                      >
                        {estaDescargando ? '...' : '⬇'}
                      </button>
                    )}
                  </div>

                </div>
              );
            })
          )}
        </div>

      </main>
    </div>
  );
}