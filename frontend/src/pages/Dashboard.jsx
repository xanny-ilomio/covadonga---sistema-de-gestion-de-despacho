import { Navigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

// Este componente solo redirige al dashboard correcto según el rol
export default function Dashboard() {
  const { user } = useAuth();

  if (user?.rol === 'facturacion') {
    return <Navigate to="/facturacion" replace />;
  }

  if (user?.rol === 'despacho') {
    return <Navigate to="/despacho" replace />;
  }

  // Si por alguna razón no hay rol reconocido, volver al login
  return <Navigate to="/" replace />;
}