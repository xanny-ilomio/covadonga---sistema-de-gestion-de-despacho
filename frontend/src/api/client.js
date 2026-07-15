// URL base de la API — en desarrollo apunta al backend en Docker
const BASE_URL = 'http://localhost:8888';

// ─── Función base para todas las peticiones ───────────────────────────────────
// Agrega automáticamente el token JWT si existe en localStorage
async function request(endpoint, options = {}) {
  const token = localStorage.getItem('token');

  const config = {
    headers: {
      'Content-Type': 'application/json',
      // Si hay token, lo agrega en cada petición automáticamente
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    ...options,
  };

  const response = await fetch(`${BASE_URL}${endpoint}`, config);
  const data = await response.json();

  // Si el servidor responde 401, el token expiró — limpiar sesión
  if (response.status === 401) {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    window.location.href = '/';
  }

  return data;
}

// ─── Auth ─────────────────────────────────────────────────────────────────────
export const auth = {
  login: (username, password) =>
    request('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ username, password }),
    }),
};

// ─── Clientes ─────────────────────────────────────────────────────────────────
export const clients = {
  getAll:   ()           => request('/clients'),
  getById:  (id)         => request(`/clients/${id}`),
  search:   (query)      => request(`/clients?search=${encodeURIComponent(query)}`),
  create:   (data)       => request('/clients', { method: 'POST', body: JSON.stringify(data) }),
  update:   (id, data)   => request(`/clients/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
  delete:   (id)         => request(`/clients/${id}`, { method: 'DELETE' }),
};

// ─── Ciudades y estados ───────────────────────────────────────────────────────
export const cities = {
  getAll:  ()            => request('/cities'),
  search:  (query)       => request(`/cities?search=${encodeURIComponent(query)}`),
  create:  (data)        => request('/cities', { method: 'POST', body: JSON.stringify(data) }),
};

export const states = {
  getAll: () => request('/states'),
};

// ─── Productos ────────────────────────────────────────────────────────────────
export const products = {
  getAll:  ()            => request('/products'),
  getById: (id)          => request(`/products/${id}`),
  search:  (query)       => request(`/products?search=${encodeURIComponent(query)}`),
  create:  (data)        => request('/products', { method: 'POST', body: JSON.stringify(data) }),
  update:  (id, data)    => request(`/products/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
  delete:  (id)          => request(`/products/${id}`, { method: 'DELETE' }),
};

// ─── Pedidos ──────────────────────────────────────────────────────────────────
export const orders = {
  getAll:         (status)      => request(`/orders${status ? `?status=${status}` : ''}`),
  getById:        (id)          => request(`/orders/${id}`),
  create:         (data)        => request('/orders', { method: 'POST', body: JSON.stringify(data) }),
  updateWeights:  (id, data)    => request(`/orders/${id}/weights`, { method: 'PUT', body: JSON.stringify(data) }),
  updateStatus:   (id, status)  => request(`/orders/${id}/status`, { method: 'PUT', body: JSON.stringify({ status }) }),
};

// ─── Rutas ────────────────────────────────────────────────────────────────────
export const routes = {
  getAll:      ()         => request('/routes'),
  getById:     (id)       => request(`/routes/${id}`),
  create:      (data)     => request('/routes', { method: 'POST', body: JSON.stringify(data) }),
  update:      (id, data) => request(`/routes/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
  getStates:   ()         => request('/states'),
  assignState: (id, stateId) =>
    request(`/routes/${id}/assign-state`, { method: 'PUT', body: JSON.stringify({ state_id: stateId }) }),
};

// ─── Guías de despacho ────────────────────────────────────────────────────────
export const guides = {
  getAll:  ()     => request('/guides'),
  getById: (id)   => request(`/guides/${id}`),
  create:  (data) => request('/guides', { method: 'POST', body: JSON.stringify(data) }),
  // El PDF se abre en una pestaña nueva con el token en la URL
  openPdf: (id)   => {
    const token = localStorage.getItem('token');
    window.open(`${BASE_URL}/guides/${id}/pdf?token=${token}`, '_blank');
  },
  export: (month, year, routeId) => {
    const token  = localStorage.getItem('token');
    const params = new URLSearchParams({ month, year });
    if (routeId) params.append('route_id', routeId);
    return fetch(`${BASE_URL}/guides/export?${params}`, {
      headers: { Authorization: `Bearer ${token}` },
    });
  },
};

// ─── Camiones y conductores ───────────────────────────────────────────────────
export const trucks = {
  getAll:  ()            => request('/trucks'),
  create:  (data)        => request('/trucks', { method: 'POST', body: JSON.stringify(data) }),
  update:  (id, data)    => request(`/trucks/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
  delete:  (id)          => request(`/trucks/${id}`, { method: 'DELETE' }),
};

export const drivers = {
  getAll:  ()            => request('/drivers'),
  create:  (data)        => request('/drivers', { method: 'POST', body: JSON.stringify(data) }),
  update:  (id, data)    => request(`/drivers/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
  delete:  (id)          => request(`/drivers/${id}`, { method: 'DELETE' }),
};

// ─── Estadísticas ─────────────────────────────────────────────────────────────
export const stats = {
  get: (period = 30) => request(`/stats?period=${period}`),
};