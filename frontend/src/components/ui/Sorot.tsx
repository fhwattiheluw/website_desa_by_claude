import { Fragment } from 'react'

/** Menetralkan karakter yang bermakna khusus pada ekspresi reguler. */
function aman(teks: string) {
  return teks.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

/**
 * Menyorot kata kunci di dalam cuplikan hasil pencarian (REQ-F-SRC-002).
 *
 * Penyorotan memakai elemen `<mark>`, bukan sekadar warna: pembaca layar
 * mengumumkannya sebagai teks yang ditandai, dan artinya tetap tersampaikan
 * kepada pengguna yang kesulitan membedakan warna (REQ-UI-009).
 */
export function Sorot({ teks, kunci }: { teks: string; kunci: string }) {
  const kata = kunci
    .split(/\s+/)
    .map((satu) => satu.trim())
    .filter((satu) => satu.length >= 2)

  if (kata.length === 0) return <>{teks}</>

  const pola = new RegExp(`(${kata.map(aman).join('|')})`, 'gi')
  const potongan = teks.split(pola)

  return (
    <>
      {potongan.map((bagian, indeks) =>
        // Potongan berindeks ganjil adalah hasil tangkapan pola.
        indeks % 2 === 1 ? (
          <mark key={indeks} className="rounded bg-amber-100 px-0.5 text-slate-900">
            {bagian}
          </mark>
        ) : (
          <Fragment key={indeks}>{bagian}</Fragment>
        ),
      )}
    </>
  )
}
