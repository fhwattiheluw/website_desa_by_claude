import { useState, type ReactNode } from 'react'
import {
  Bar, BarChart, CartesianGrid, Cell, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis,
} from 'recharts'
import { Table2, BarChart3 } from 'lucide-react'
import { GARIS_BANTU, SEKUENSIAL, SERI, TEKS } from './warna'

export interface SeriBatang {
  kunci: string
  label: string
  warna: string
}

interface Props {
  judul: string
  deskripsi?: string
  data: Record<string, string | number>[]
  kunciLabel: string
  seri: SeriBatang[]
  format: (nilai: number) => string
  tinggiPerBaris?: number
  catatan?: ReactNode
}

/**
 * Batang horizontal untuk perbandingan besaran antarkategori.
 *
 * Setiap grafik menyediakan padanan tabel data agar dapat dibaca pengguna
 * pembaca layar dan pengguna yang kesulitan membedakan warna
 * (REQ-F-APB-007, REQ-UI-009).
 */
export function GrafikBatang({
  judul,
  deskripsi,
  data,
  kunciLabel,
  seri,
  format,
  tinggiPerBaris = 34,
  catatan,
}: Props) {
  const [tampilTabel, setTampilTabel] = useState(false)

  const tinggi = Math.max(220, data.length * tinggiPerBaris * (seri.length > 1 ? 1.5 : 1) + 60)

  return (
    <figure className="rounded-xl border border-slate-200 bg-white p-5">
      <figcaption className="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
          <h3 className="text-base font-semibold text-slate-900">{judul}</h3>
          {deskripsi && <p className="mt-0.5 text-sm text-slate-600">{deskripsi}</p>}
        </div>
        <button
          type="button"
          onClick={() => setTampilTabel((tampil) => !tampil)}
          aria-pressed={tampilTabel}
          className="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-slate-300 px-3 text-sm text-slate-700 hover:bg-slate-50"
        >
          {tampilTabel ? <BarChart3 aria-hidden className="size-4" /> : <Table2 aria-hidden className="size-4" />}
          {tampilTabel ? 'Tampilkan grafik' : 'Tampilkan tabel'}
        </button>
      </figcaption>

      {tampilTabel ? (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <caption className="sr-only">{judul}</caption>
            <thead>
              <tr className="border-b border-slate-200 text-left text-slate-600">
                <th scope="col" className="py-2 pr-4 font-medium">Kategori</th>
                {seri.map((s) => (
                  <th key={s.kunci} scope="col" className="py-2 pr-4 text-right font-medium">
                    {s.label}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {data.map((baris, indeks) => (
                <tr key={indeks} className="border-b border-slate-100 last:border-0">
                  <th scope="row" className="py-2 pr-4 font-normal text-slate-800">
                    {String(baris[kunciLabel])}
                  </th>
                  {seri.map((s) => (
                    <td key={s.kunci} className="py-2 pr-4 text-right tabular-nums text-slate-700">
                      {format(Number(baris[s.kunci] ?? 0))}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <div style={{ height: tinggi }}>
          <ResponsiveContainer width="100%" height="100%">
            <BarChart data={data} layout="vertical" margin={{ top: 4, right: 56, bottom: 4, left: 4 }} barGap={2}>
              <CartesianGrid horizontal={false} stroke={GARIS_BANTU} />
              <XAxis
                type="number"
                tickFormatter={(nilai: number) => format(nilai)}
                tick={{ fill: TEKS.sekunder, fontSize: 12 }}
                axisLine={false}
                tickLine={false}
              />
              <YAxis
                type="category"
                dataKey={kunciLabel}
                width={190}
                tick={{ fill: TEKS.utama, fontSize: 12 }}
                tickFormatter={(nilai: string) => (nilai.length > 34 ? `${nilai.slice(0, 33)}…` : nilai)}
                axisLine={false}
                tickLine={false}
              />
              <Tooltip
                cursor={{ fill: 'rgba(148,163,184,0.12)' }}
                labelFormatter={(label) => String(label)}
                formatter={(nilai, nama) => [format(Number(nilai ?? 0)), String(nama ?? '')]}
                contentStyle={{
                  borderRadius: 8,
                  border: `1px solid ${GARIS_BANTU}`,
                  fontSize: 13,
                  boxShadow: '0 4px 12px rgba(15,23,42,0.08)',
                }}
              />
              {seri.length > 1 && (
                <Legend
                  verticalAlign="top"
                  align="left"
                  height={32}
                  formatter={(nilai) => <span style={{ color: TEKS.sekunder, fontSize: 12 }}>{nilai}</span>}
                />
              )}
              {seri.map((s) => (
                <Bar key={s.kunci} dataKey={s.kunci} name={s.label} fill={s.warna} radius={[0, 4, 4, 0]} barSize={14}>
                  {data.map((_, indeks) => (
                    <Cell key={indeks} />
                  ))}
                </Bar>
              ))}
            </BarChart>
          </ResponsiveContainer>
        </div>
      )}

      {catatan && <p className="mt-3 text-xs text-slate-500">{catatan}</p>}
    </figure>
  )
}

export const WARNA_SERI = SERI
export const WARNA_TUNGGAL = SEKUENSIAL
