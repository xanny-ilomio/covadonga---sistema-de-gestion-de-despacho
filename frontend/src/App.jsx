import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import ProtectedRoute from './components/ProtectedRoute';
import Login from './pages/Login';
import DashboardFacturacion from './pages/facturacion/DashboardFacturacion';
import DashboardDespacho from './pages/despacho/DashboardDespacho';
import RegistrarPedido from './pages/facturacion/RegistrarPedido';


// Importaciones lazy — cada página se carga solo cuando se necesita
// Esto hace que el login inicial sea más rápido
import { lazy, Suspense } from 'react';
import Dashboard from './pages/Dashboard';

function LoadingScreen() {
  return (
    <div style={{
      minHeight: '100vh',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      background: '#F8F7F4',
      color: '#888',
      fontSize: '14px',
    }}>
      Cargando...
    </div>
  );
}

export default function App() {
  const GestionPedidos = lazy(() => import('./pages/GestionPedidos'));
  const ActualizarPedido = lazy(() => import('./pages/despacho/ActualizarPedido'));
  const GestionFlota = lazy(() => import('./pages/despacho/GestionFlota'));
  const GestionRutas = lazy(() => import('./pages/despacho/GestionRutas'));
  const HistorialGuias = lazy(() => import('./pages/despacho/HistorialGuias'));
  return (
    <AuthProvider>
      <BrowserRouter>
        <Suspense fallback={<LoadingScreen />}>
          <Routes>
            {/* Ruta pública — Login */}
            <Route path="/" element={<Login />} />

            {/* Ruta intermedia — redirige según rol */}
            <Route path="/dashboard" element={   
              <ProtectedRoute>
                <Dashboard />
              </ProtectedRoute> }
            />

            {/* FACTURACION */}
            <Route path='/facturacion/*' 
              element={
                <ProtectedRoute requiredRole="facturacion">
                  <DashboardFacturacion/>
                </ProtectedRoute>
              }
            />
            <Route path='/facturacion/registrar_pedido' 
              element={
                <ProtectedRoute requiredRole="facturacion">
                  <RegistrarPedido/>
                </ProtectedRoute>
              }
            />
            <Route
              path="/facturacion/pedidos"
              element={
                <ProtectedRoute requiredRole="facturacion">
                  <GestionPedidos />
                </ProtectedRoute>
              }
            />

            {/* DESPACHO */}
            <Route path='/despacho/*' 
              element={
                <ProtectedRoute requiredRole="despacho">
                  <DashboardDespacho/>
                </ProtectedRoute>
              }
            />
            <Route
                path="/despacho/pedidos"
                element={
                  <ProtectedRoute requiredRole="despacho">
                    <GestionPedidos />
                  </ProtectedRoute>
                }
              />

            <Route
              path="/despacho/pedido/:id"
              element={
                <ProtectedRoute requiredRole="despacho">
                  <ActualizarPedido />
                </ProtectedRoute>
              }
            />
              <Route
                path="/despacho/flota"
                element={
                  <ProtectedRoute requiredRole="despacho">
                    <GestionFlota />
                  </ProtectedRoute>
                }
              />
              <Route
                path="/despacho/guias"
                element={
                  <ProtectedRoute requiredRole="despacho">
                    <HistorialGuias />
                  </ProtectedRoute>
                }
              />

            {/* Cualquier ruta no encontrada → login */}
            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </Suspense>
      </BrowserRouter>
    </AuthProvider>
  );
}