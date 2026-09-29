import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import App from './App'
import { terapkanPreferensiAwal } from './lib/preferensi'
import './index.css'

// Preferensi tampilan diterapkan sebelum render pertama agar tidak berkedip.
terapkanPreferensiAwal()

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
)
