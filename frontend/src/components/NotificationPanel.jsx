// src/components/NotificationPanel.jsx
import NotificationItem from './NotificationItem';

export default function NotificationPanel() {
  // Estos datos vendrán de tu API en PHP (ej: GET /orders?status=updated)
  const pedidosActualizados = [
    { id: 4312, cliente: "Distribuidora Lopez" },
    { id: 4313, cliente: "Comercial Maracay" },
    { id: 4314, cliente: "Distribuidora CA" }
  ];

  return (
    <div className={styles.notificationpanelcontainer}>
      <h2>Pedidos actualizados</h2>
      <div className={styles.notificationpanel}>
        {pedidosActualizados.map((pedido) => (
          <NotificationItem key={pedido.id} pedido={pedido} />
        ))}
      </div>
    </div>
  );
}