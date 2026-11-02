import { createRoot } from 'react-dom/client';
import './styles.css';
import App from './App';

const savedTheme = window.localStorage.getItem('erp_cloud.theme');
if (savedTheme === 'dark' || savedTheme === 'light') {
  document.documentElement.classList.toggle('dark', savedTheme === 'dark');
}

createRoot(document.getElementById('root')!).render(<App />);
