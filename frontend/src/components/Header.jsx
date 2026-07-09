import React from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import logo from '../../public/icons/isotipo_blanco.svg';
import styles from '../styles/Header.module.css';

export default function Header() {
  const { logout } = useAuth();
  const navigate = useNavigate();

  function handleLogout() {
    logout();
    navigate('/');
  }

  return (
    <header className={styles.navbar}>
      <div className={styles.leftSpacer}></div>
      <div className={styles.navbarCenter}>
        <img src={logo} alt="Logo Covadonga" className={styles.brandLogo} onClick={() => navigate(`/dashboard`)}/>
      </div>
      <div className={styles.navbarRight}>
        <button className={styles.logoutButton} onClick={handleLogout} title="Cerrar Sesión">
          Cerrar Sesión
        </button>
      </div>
    </header>
  );
}