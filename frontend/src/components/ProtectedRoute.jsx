import { Navigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

// Envuelve cualquier página que requiera login
// Si no está autenticado, redirige al login automáticamente
export default function ProtectedRoute({ children, requiredRole }) {
  const { isAuthenticated, user } = useAuth();

  if (!isAuthenticated) {
    return <Navigate to="/" replace />;
  }

  // Si la ruta requiere un rol específico y el usuario no lo tiene
  if (requiredRole && user?.rol !== requiredRole) {
    return <Navigate to="/dashboard" replace />;
  }

  return children;
}