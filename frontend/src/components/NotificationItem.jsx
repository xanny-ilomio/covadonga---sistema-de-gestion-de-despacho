// src/components/NotificationItem.jsx
import { Link } from 'react-router-dom';

export default function NotificationItem({ pedido }) {
  return (
    // Al hacer clic, redirige por ejemplo a: /pedidos/4312
    <Link to={`/pedidos/${pedido.id}`} className="notification-item-link">
      <div className={styles.notificationcard}>
        <span className={styles.ordernumber}>#{pedido.id}</span>
        <span className={styles.clientname}>{pedido.cliente}</span>
        <button className={styles.downloadbtn}>
          {/* Aquí pones el icono de descarga rojo de image_288954.png */}
          <i className={styles.icondownload}></i> 
        </button>
      </div>
    </Link>
  );
}