import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import ProtectedRoute from './components/ProtectedRoute';
import Login from './pages/Login';
import DashboardFacturacion from './pages/facturacion/DashboardFacturacion';

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

            {/* Dashboard facturacion */}
            <Route path='/facturacion/*' 
              element={
                <ProtectedRoute requiredRole="facturacion">
                  <DashboardFacturacion/>
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