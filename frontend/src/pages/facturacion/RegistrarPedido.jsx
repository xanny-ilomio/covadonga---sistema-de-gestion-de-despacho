import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { clients, products, orders, cities, states } from '../../api/client';
import Header from '../../components/Header';
import styles from '../../styles/RegistrarPedido.module.css';

export default function RegistrarPedido() {
  const navigate = useNavigate();

  // ─── Datos base de la BD ──────────────────────────────────────────────────
  const [listaClientes,  setListaClientes]  = useState([]);
  const [listaProductos, setListaProductos] = useState([]);
  const [listaCiudades,  setListaCiudades]  = useState([]);

  // ─── Formulario del cliente ───────────────────────────────────────────────
  const [clienteId,   setClienteId]   = useState(null); // null = cliente nuevo
  const [clienteForm, setClienteForm] = useState({
    nombre: '',
    rif:    '',
    phone:  '',
    estado: '',
    ciudad: '',
    email:  '',
    cityId: null,
  });

  // ─── Filas de productos ───────────────────────────────────────────────────
  const [filas, setFilas] = useState([
    { idTemp: 1, productId: '', nombre: '', bultos: 1, precio: 0, kgs: 0 }
  ]);

  // ─── UI ───────────────────────────────────────────────────────────────────
  const [guardando,     setGuardando]     = useState(false);
  const [mostrarPopup,  setMostrarPopup]  = useState(false);
  const [error,         setError]         = useState('');

  // ─── Cargar datos iniciales ───────────────────────────────────────────────
  useEffect(() => {
    async function init() {
      try {
        const [resCli, resProd, resCiu] = await Promise.all([
          clients.getAll(),
          products.getAll(),
          cities.getAll(),
        ]);
        if (resCli.status  === 'success') setListaClientes(resCli.data);
        if (resProd.status === 'success') setListaProductos(resProd.data);
        if (resCiu.status  === 'success') setListaCiudades(resCiu.data);
      } catch (err) {
        console.error('Error cargando datos:', err);
      }
    }
    init();
  }, []);

  // ─── Autorelleno del cliente al escribir nombre ───────────────────────────
  function handleNombreCliente(valor) {
    setClienteForm(prev => ({ ...prev, nombre: valor }));
    

    // Buscar coincidencia exacta en la lista
    const encontrado = listaClientes.find(c =>
      c.NAME_CLIENT.trim().toLowerCase() === valor.trim().toLowerCase()
    );

    if (encontrado) {
      // Cliente existe — autorrellenar todos los campos con datos de la BD
      setClienteId(encontrado.ID_CLIENT);
      setClienteForm({
        nombre: encontrado.NAME_CLIENT,
        rif:    encontrado.RIF         || '',
        phone:  encontrado.PHONE_CLIENT || '',
        estado: encontrado.NAME_STATE  || '',
        ciudad: encontrado.NAME_CITY   || '',
        email:  encontrado.EMAIL_CLIENT || '',
        cityId: encontrado.ID_CITY,
      });
    } else {
      // Cliente nuevo — limpiar ID para que el backend lo cree
      setClienteId(null);
      setClienteForm(prev => ({ ...prev, nombre: valor }));
    }
  }

  // ─── Cambios generales en el formulario del cliente ───────────────────────
  function handleClienteField(campo, valor) {
    setClienteForm(prev => ({ ...prev, [campo]: valor }));

    // Si cambia la ciudad, buscar su ID y estado automáticamente
    if (campo === 'ciudad') {
      const ciudadEncontrada = listaCiudades.find(c =>
        c.NAME_CITY.trim().toLowerCase() === valor.trim().toLowerCase()
      );
      if (ciudadEncontrada) {
        setClienteForm(prev => ({
          ...prev,
          ciudad: valor,
          estado: ciudadEncontrada.NAME_STATE,
          cityId: ciudadEncontrada.ID_CITY,
        }));
      }
    }
  }

  // ─── Cambios en filas de productos ───────────────────────────────────────
  function handleFilaCambio(idTemp, campo, valor) {
    setFilas(prev => prev.map(fila => {
      if (fila.idTemp !== idTemp) return fila;

      const nueva = { ...fila, [campo]: valor };

      // Al cambiar nombre — buscar producto y autorrellenar precio y kg
      if (campo === 'nombre') {
        const prod = listaProductos.find(p =>
          p.NAME_PRODUCT.trim().toLowerCase() === valor.trim().toLowerCase()
        );
        if (prod) {
          nueva.productId = prod.ID_PRODUCT;
          nueva.precio    = (Number(prod.PRICE)       || 0) * (Number(fila.bultos) || 1);
          nueva.kgs       = (Number(prod.WEIGHT_APROX) || 0) * (Number(fila.bultos) || 1);
        } else {
          nueva.productId = '';
          nueva.precio    = 0;
          nueva.kgs       = 0;
        }
      }

      // Al cambiar bultos — recalcular precio y kg
      if (campo === 'bultos') {
        const cantidad = Math.max(0.5, Number(valor) || 1);
        nueva.bultos   = cantidad;
        const prod     = listaProductos.find(p => String(p.ID_PRODUCT) === String(fila.productId));
        if (prod) {
          nueva.precio = (Number(prod.PRICE)        || 0) * cantidad;
          nueva.kgs    = (Number(prod.WEIGHT_APROX) || 0) * cantidad;
        }
      }

      return nueva;
    }));
  }

  function agregarFila() {
    setFilas(prev => [
      ...prev,
      { idTemp: Date.now() + Math.random(), productId: '', nombre: '', bultos: 1, precio: 0, kgs: 0 }
    ]);
  }

  function eliminarFila(idTemp) {
    if (filas.length > 1) {
      setFilas(prev => prev.filter(f => f.idTemp !== idTemp));
    }
  }

  // ─── Totales calculados ───────────────────────────────────────────────────
  const totalPrecio = filas.reduce((s, f) => s + (Number(f.precio) || 0), 0);
  const totalKgs    = filas.reduce((s, f) => s + (Number(f.kgs)    || 0), 0);

  // ─── Registrar pedido ─────────────────────────────────────────────────────
  async function handleSubmit(e) {
    e.preventDefault();
    setError('');

    // Validaciones
    if (!clienteForm.nombre.trim()) {
      setError('El nombre del cliente es requerido'); return;
    }
    if (!clienteForm.rif.trim()) {
      setError('El RIF es requerido'); return;
    }
    if (!clienteForm.cityId) {
      setError('Selecciona una ciudad válida de la lista'); return;
    }
    if (filas.some(f => !f.productId)) {
      setError('Selecciona productos válidos de la lista para todas las filas'); return;
    }

    setGuardando(true);
    try {
      let idClienteFinal = clienteId;

      // Si el cliente no existe, crearlo primero
      if (!clienteId) {
        const resCliente = await clients.create({
          name:    clienteForm.nombre,
          rif:     clienteForm.rif,
          phone:   clienteForm.phone,
          email:   clienteForm.email || null,
          city_id: clienteForm.cityId,
        });
        if (resCliente.status !== 'success') {
          setError(resCliente.message || 'Error al crear el cliente'); return;
        }
        idClienteFinal = resCliente.data.ID_CLIENT;
      }

      // Crear el pedido con los items
      const resPedido = await orders.create({
        client_id: idClienteFinal,
        items: filas.map(f => ({
          product_id: f.productId,
          amount:     f.bultos,
        })),
      });

      if (resPedido.status === 'success') {
        setMostrarPopup(true);
        setTimeout(() => navigate(-1), 2500);
      } else {
        setError(resPedido.message || 'Error al registrar el pedido');
      }
    } catch (err) {
      console.error(err);
      setError('Error de conexión al registrar el pedido');
    } finally {
      setGuardando(false);
    }
  }

  // ─── Render ───────────────────────────────────────────────────────────────
  return (
    <div className={styles.appContainer}>

      <Header/>

      <main className={styles.mainContent}>
        <form className={styles.pedidoForm} onSubmit={handleSubmit}>

          <h1 className={styles.title}>Nuevo Pedido</h1>

          {/* ERROR GLOBAL */}
          {error && <p className={styles.errorMsg}>{error}</p>}

          {/* SECCIÓN CLIENTE */}
          <div className={styles.clientSection}>

            {/* Nombre — con datalist para sugerencias */}
            <div className={styles.inputGroup}>
              <label>Cliente</label>
              <input
                type="text"
                placeholder="Razón social..."
                value={clienteForm.nombre}
                onChange={e => handleNombreCliente(e.target.value)}
                list="clientes-list"
                autoComplete="off"
              />
              <datalist id="clientes-list">
                {listaClientes.map(c => (
                  <option key={c.ID_CLIENT} value={c.NAME_CLIENT} />
                ))}
              </datalist>
            </div>

            <div className={styles.inputGroup}>
              <label>RIF</label>
              <input
                type="text"
                placeholder="J-XXXXXXXX"
                value={clienteForm.rif}
                onChange={e => handleClienteField('rif', e.target.value)}
              />
            </div>

            <div className={styles.inputGroup}>
              <label>Teléfono</label>
              <input
                type="text"
                placeholder="04XXXXXXXXX"
                value={clienteForm.phone}
                onChange={e => handleClienteField('phone', e.target.value)}
              />
            </div>

            {/* Ciudad — con datalist, autorellena el estado */}
            <div className={styles.inputGroup}>
              <label>Ciudad</label>
              <input
                type="text"
                placeholder="Maracay..."
                value={clienteForm.ciudad}
                onChange={e => handleClienteField('ciudad', e.target.value)}
                list="ciudades-list"
                autoComplete="off"
              />
              <datalist id="ciudades-list">
                {listaCiudades.map(c => (
                  <option key={c.ID_CITY} value={c.NAME_CITY} />
                ))}
              </datalist>
            </div>

            {/* Estado — se autorellena al elegir ciudad */}
            <div className={styles.inputGroup}>
              <label>Estado</label>
              <input
                type="text"
                placeholder="Se rellena al elegir ciudad..."
                value={clienteForm.estado}
                onChange={e => handleClienteField('estado', e.target.value)}
                readOnly={!!clienteForm.cityId}
                className={clienteForm.cityId ? styles.inputReadonly : ''}
              />
            </div>

            <div className={styles.inputGroup}>
              <label>Correo</label>
              <input
                type="email"
                placeholder="correo@ejemplo.com"
                value={clienteForm.email}
                onChange={e => handleClienteField('email', e.target.value)}
              />
            </div>

          </div>

          {/* TABLA DE PRODUCTOS */}
          <h2 className={styles.subtitle}>Productos</h2>

          <div className={styles.tableWrapper}>
            <table className={styles.productTable}>
              <thead>
                <tr>
                  <th>Producto</th>
                  <th>Bultos</th>
                  <th>Precio ($)</th>
                  <th>Peso aprox. (kg)</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {filas.map((fila, index) => (
                  <tr key={fila.idTemp}>
                    <td>
                      <input
                        type="text"
                        placeholder="Escriba el producto..."
                        value={fila.nombre}
                        onChange={e => handleFilaCambio(fila.idTemp, 'nombre', e.target.value)}
                        list={`productos-${index}`}
                        className={styles.inputProducto}
                        autoComplete="off"
                      />
                      <datalist id={`productos-${index}`}>
                        {listaProductos.map(p => (
                          <option key={p.ID_PRODUCT} value={p.NAME_PRODUCT} />
                        ))}
                      </datalist>
                    </td>
                    <td>
                      <input
                        type="number"
                        min="1"
                        step="1"
                        value={fila.bultos}
                        onChange={e => handleFilaCambio(fila.idTemp, 'bultos', e.target.value)}
                        className={styles.inputBultos}
                      />
                    </td>
                    <td className={styles.computedCell}>
                      {Number(fila.precio).toLocaleString('es-VE', { minimumFractionDigits: 2 })} $
                    </td>
                    <td className={styles.computedCell}>
                      {Number(fila.kgs).toLocaleString('es-VE', { minimumFractionDigits: 2 })} kg
                    </td>
                    <td>
                      <button
                        type="button"
                        className={styles.deleteRowBtn}
                        onClick={() => eliminarFila(fila.idTemp)}
                        disabled={filas.length === 1}
                      >
                        ✕
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* ACCIONES Y TOTALES */}
          <div className={styles.tableActionsRow}>
            <button type="button" className={styles.addBtn} onClick={agregarFila}>
              + Agregar Producto
            </button>
            
            <div className={styles.totalsGroup}>
              <span className={styles.totalLabel}>TOTAL:</span>
              <span className={styles.totalPrice}>{totalPrecio.toLocaleString()}$</span>
              <span className={styles.totalKgs}>{totalKgs.toLocaleString()}KG</span>
            </div>
          </div>

          <button type="submit" className={styles.submitBtn} disabled={guardando}>
            {guardando ? 'Registrando...' : 'Registrar Pedido'}
          </button>

        </form>
      </main>

      {/* POPUP DE ÉXITO */}
      {mostrarPopup && (
        <div className={styles.overlay}>
          <div className={styles.popup}>
            <div className={styles.successIcon}>✓</div>
            <h2>Pedido Registrado</h2>
            <p>El pedido fue guardado correctamente.</p>
          </div>
        </div>
      )}
    </div>
  );
}