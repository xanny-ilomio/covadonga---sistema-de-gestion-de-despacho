import { createContext, useContext, useState } from 'react';

// Contexto que comparte el usuario y token en toda la app
const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  // Inicializa desde localStorage para que la sesión persista al recargar
  const [user, setUser]   = useState(() => {
    const saved = localStorage.getItem('user');
    return saved ? JSON.parse(saved) : null;
  });
  const [token, setToken] = useState(() => localStorage.getItem('token') || null);

  // Se llama después del login exitoso
  function login(userData, tokenValue) {
    setUser(userData);
    setToken(tokenValue);
    localStorage.setItem('user',  JSON.stringify(userData));
    localStorage.setItem('token', tokenValue);
  }

  // Limpia todo al cerrar sesión
  function logout() {
    setUser(null);
    setToken(null);
    localStorage.removeItem('user');
    localStorage.removeItem('token');
  }

  const isAuthenticated = !!token;

  return (
    <AuthContext.Provider value={{ user, token, login, logout, isAuthenticated }}>
      {children}
    </AuthContext.Provider>
  );
}

// Hook para usar el contexto fácilmente en cualquier componente
// En vez de: const { user } = useContext(AuthContext)
// Usas:      const { user } = useAuth()
export function useAuth() {
  return useContext(AuthContext);
}