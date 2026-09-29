import { useId, type InputHTMLAttributes, type ReactNode, type SelectHTMLAttributes, type TextareaHTMLAttributes } from 'react'

const KELAS_ISIAN =
  'w-full min-h-11 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 ' +
  'placeholder:text-slate-400 focus:border-desa-600 focus:ring-2 focus:ring-desa-600/20 ' +
  'disabled:bg-slate-100 disabled:text-slate-500 aria-[invalid=true]:border-red-500'

function Bungkus({
  id,
  label,
  wajib,
  petunjuk,
  galat,
  children,
}: {
  id: string
  label: string
  wajib?: boolean
  petunjuk?: string
  galat?: string
  children: ReactNode
}) {
  return (
    <div>
      <label htmlFor={id} className="mb-1.5 block text-sm font-medium text-slate-800">
        {label}
        {wajib && (
          <span className="ml-1 text-red-600" aria-label="wajib diisi">
            *
          </span>
        )}
      </label>
      {children}
      {petunjuk && !galat && (
        <p id={`${id}-petunjuk`} className="mt-1 text-xs text-slate-500">
          {petunjuk}
        </p>
      )}
      {galat && (
        <p id={`${id}-galat`} role="alert" className="mt-1 text-xs font-medium text-red-700">
          {galat}
        </p>
      )}
    </div>
  )
}

interface PropsIsian extends InputHTMLAttributes<HTMLInputElement> {
  label: string
  petunjuk?: string
  galat?: string
}

export function Isian({ label, petunjuk, galat, id, ...sisa }: PropsIsian) {
  const otomatis = useId()
  const idIsian = id ?? otomatis

  return (
    <Bungkus id={idIsian} label={label} wajib={sisa.required} petunjuk={petunjuk} galat={galat}>
      <input
        {...sisa}
        id={idIsian}
        aria-invalid={galat ? true : undefined}
        aria-describedby={galat ? `${idIsian}-galat` : petunjuk ? `${idIsian}-petunjuk` : undefined}
        className={KELAS_ISIAN}
      />
    </Bungkus>
  )
}

interface PropsArea extends TextareaHTMLAttributes<HTMLTextAreaElement> {
  label: string
  petunjuk?: string
  galat?: string
}

export function AreaTeks({ label, petunjuk, galat, id, rows = 4, ...sisa }: PropsArea) {
  const otomatis = useId()
  const idIsian = id ?? otomatis

  return (
    <Bungkus id={idIsian} label={label} wajib={sisa.required} petunjuk={petunjuk} galat={galat}>
      <textarea
        {...sisa}
        rows={rows}
        id={idIsian}
        aria-invalid={galat ? true : undefined}
        aria-describedby={galat ? `${idIsian}-galat` : petunjuk ? `${idIsian}-petunjuk` : undefined}
        className={KELAS_ISIAN}
      />
    </Bungkus>
  )
}

interface PropsPilihan extends SelectHTMLAttributes<HTMLSelectElement> {
  label: string
  petunjuk?: string
  galat?: string
  pilihan: { nilai: string; teks: string }[]
  kosong?: string
}

export function Pilihan({ label, petunjuk, galat, pilihan, kosong, id, ...sisa }: PropsPilihan) {
  const otomatis = useId()
  const idIsian = id ?? otomatis

  return (
    <Bungkus id={idIsian} label={label} wajib={sisa.required} petunjuk={petunjuk} galat={galat}>
      <select
        {...sisa}
        id={idIsian}
        aria-invalid={galat ? true : undefined}
        aria-describedby={galat ? `${idIsian}-galat` : petunjuk ? `${idIsian}-petunjuk` : undefined}
        className={KELAS_ISIAN}
      >
        {kosong && <option value="">{kosong}</option>}
        {pilihan.map((opsi) => (
          <option key={opsi.nilai} value={opsi.nilai}>
            {opsi.teks}
          </option>
        ))}
      </select>
    </Bungkus>
  )
}

export function KotakCentang({
  label,
  galat,
  id,
  ...sisa
}: InputHTMLAttributes<HTMLInputElement> & { label: ReactNode; galat?: string }) {
  const otomatis = useId()
  const idIsian = id ?? otomatis

  return (
    <div>
      <div className="flex items-start gap-2.5">
        <input
          {...sisa}
          type="checkbox"
          id={idIsian}
          aria-invalid={galat ? true : undefined}
          aria-describedby={galat ? `${idIsian}-galat` : undefined}
          className="mt-0.5 size-5 shrink-0 rounded border-slate-300 text-desa-700 focus:ring-desa-600"
        />
        <label htmlFor={idIsian} className="text-sm text-slate-700">
          {label}
        </label>
      </div>
      {galat && (
        <p id={`${idIsian}-galat`} role="alert" className="mt-1 text-xs font-medium text-red-700">
          {galat}
        </p>
      )}
    </div>
  )
}

export function Berkas({
  label,
  petunjuk,
  galat,
  id,
  ...sisa
}: InputHTMLAttributes<HTMLInputElement> & { label: string; petunjuk?: string; galat?: string }) {
  const otomatis = useId()
  const idIsian = id ?? otomatis

  return (
    <Bungkus id={idIsian} label={label} wajib={sisa.required} petunjuk={petunjuk} galat={galat}>
      <input
        {...sisa}
        type="file"
        id={idIsian}
        aria-invalid={galat ? true : undefined}
        className="w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-700 file:mr-3 file:min-h-11 file:cursor-pointer file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200"
      />
    </Bungkus>
  )
}
