import { useEffect, useId, useRef, useState } from 'react'
import { NavLink } from 'react-router-dom'
import { ChevronDown } from 'lucide-react'
import { useMenuNavigasi, type ButirMenu } from '@/lib/kueri'

const KELAS_BUTIR =
  'flex min-h-11 w-full items-center gap-1 rounded-lg px-3 py-2.5 text-left text-sm font-medium transition-colors'

function kelasTaut(aktif: boolean): string {
  return `${KELAS_BUTIR} ${aktif ? 'bg-desa-50 text-desa-800' : 'text-slate-700 hover:bg-slate-100'}`
}

/**
 * Butir yang menaungi butir lain (REQ-F-ADM-003).
 *
 * Dibuat sebagai tombol pengungkap, bukan tautan yang membuka menu saat
 * disentuh. Pada layar sentuh tidak ada keadaan "melayang": menu yang bergantung
 * padanya membuat butir induk tidak pernah dapat dibuka, hanya dilewati.
 */
function ButirBersarang({ butir, onPindah }: { butir: ButirMenu; onPindah: () => void }) {
  const [terbuka, setTerbuka] = useState(false)
  const bungkus = useRef<HTMLLIElement>(null)
  const idPanel = useId()

  useEffect(() => {
    if (!terbuka) return

    const diLuar = (peristiwa: MouseEvent) => {
      if (!bungkus.current?.contains(peristiwa.target as Node)) setTerbuka(false)
    }
    const tombolEsc = (peristiwa: KeyboardEvent) => {
      if (peristiwa.key === 'Escape') setTerbuka(false)
    }

    document.addEventListener('mousedown', diLuar)
    document.addEventListener('keydown', tombolEsc)

    return () => {
      document.removeEventListener('mousedown', diLuar)
      document.removeEventListener('keydown', tombolEsc)
    }
  }, [terbuka])

  const tutupLalu = () => {
    setTerbuka(false)
    onPindah()
  }

  return (
    <li ref={bungkus} className="lg:relative">
      <button
        type="button"
        aria-expanded={terbuka}
        aria-controls={idPanel}
        onClick={() => setTerbuka((buka) => !buka)}
        className={`${KELAS_BUTIR} text-slate-700 hover:bg-slate-100 lg:w-auto`}
      >
        {butir.label}
        <ChevronDown aria-hidden className={`size-4 transition-transform ${terbuka ? 'rotate-180' : ''}`} />
      </button>

      <ul
        id={idPanel}
        hidden={!terbuka}
        className="ps-3 lg:absolute lg:z-50 lg:ms-0 lg:w-60 lg:rounded-lg lg:border lg:border-slate-200 lg:bg-white lg:p-1 lg:shadow-lg"
      >
        {butir.tautan && (
          <li>
            <NavLink to={butir.tautan} end onClick={tutupLalu} className={({ isActive }) => kelasTaut(isActive)}>
              {butir.label}
            </NavLink>
          </li>
        )}
        {butir.anak.map((anak) => (
          <li key={`${anak.label}-${anak.tautan}`}>
            {anak.tautan && (
              <NavLink to={anak.tautan} onClick={tutupLalu} className={({ isActive }) => kelasTaut(isActive)}>
                {anak.label}
              </NavLink>
            )}
          </li>
        ))}
      </ul>
    </li>
  )
}

/** Navigasi utama, disusun dari pengelola menu (REQ-F-ADM-003, REQ-UI-003). */
export function MenuUtama({ terbuka, onPindah }: { terbuka: boolean; onPindah: () => void }) {
  const menu = useMenuNavigasi()

  return (
    <nav id="menu-utama" aria-label="Navigasi utama" className="border-t border-slate-100 bg-white">
      <ul className={`mx-auto max-w-6xl gap-1 px-2 lg:flex ${terbuka ? 'block pb-2' : 'hidden lg:flex'}`}>
        {menu.map((butir) =>
          butir.anak.length > 0 ? (
            <ButirBersarang key={butir.label} butir={butir} onPindah={onPindah} />
          ) : (
            butir.tautan && (
              <li key={butir.label}>
                <NavLink
                  to={butir.tautan}
                  end={butir.tautan === '/'}
                  onClick={onPindah}
                  className={({ isActive }) => `${kelasTaut(isActive)} lg:w-auto`}
                >
                  {butir.label}
                </NavLink>
              </li>
            )
          ),
        )}
      </ul>
    </nav>
  )
}
