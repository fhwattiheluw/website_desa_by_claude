import { useEffect, useRef, useState } from 'react'
import { RefreshCw } from 'lucide-react'
import type { KendaliCaptcha } from '@/lib/captcha'
import { Isian } from './Isian'

const ALAMAT_TURNSTILE = 'https://challenges.cloudflare.com/turnstile/v0/api.js'

interface WidgetTurnstile {
  render: (wadah: HTMLElement, opsi: { sitekey: string; language: string; callback: (token: string) => void }) => void
}

/** Memuat skrip widget penyedia pihak ketiga sekali saja. */
function useSkrip(alamat: string | null) {
  const [siap, setSiap] = useState(() => Boolean(alamat) && Boolean(document.querySelector(`script[src="${alamat}"]`)))

  useEffect(() => {
    if (!alamat || document.querySelector(`script[src="${alamat}"]`)) return

    const skrip = document.createElement('script')
    skrip.src = alamat
    skrip.async = true
    skrip.onload = () => setSiap(true)
    document.head.appendChild(skrip)
  }, [alamat])

  return siap
}

export function Captcha({ kendali, galat }: { kendali: KendaliCaptcha; galat?: string }) {
  const { tantangan, jawaban, setJawaban, segarkan } = kendali
  const wadah = useRef<HTMLDivElement>(null)
  const perluWidget = tantangan?.metode === 'turnstile' && Boolean(tantangan.kunci_situs)
  const skripSiap = useSkrip(perluWidget ? ALAMAT_TURNSTILE : null)

  useEffect(() => {
    if (!perluWidget || !skripSiap || !wadah.current || wadah.current.childElementCount > 0) return

    const widget = (window as unknown as { turnstile?: WidgetTurnstile }).turnstile

    widget?.render(wadah.current, {
      sitekey: tantangan.kunci_situs as string,
      language: 'id',
      // Token widget dikirim pada kolom jawaban dan diperiksa ulang oleh server.
      callback: setJawaban,
    })
  }, [perluWidget, skripSiap, tantangan?.kunci_situs, setJawaban])

  if (!tantangan?.aktif) return null

  if (perluWidget) {
    return (
      <div>
        <div ref={wadah} />
        {galat && (
          <p role="alert" className="mt-1 text-xs font-medium text-red-700">
            {galat}
          </p>
        )}
      </div>
    )
  }

  return (
    <div className="rounded-lg border border-slate-200 bg-slate-50 p-4">
      <Isian
        label={tantangan.pertanyaan ?? 'Verifikasi'}
        name="captcha_jawaban"
        value={jawaban}
        onChange={(e) => setJawaban(e.target.value)}
        required
        inputMode="numeric"
        autoComplete="off"
        petunjuk={tantangan.petunjuk}
        galat={galat}
      />
      <button
        type="button"
        onClick={segarkan}
        className="mt-2 inline-flex min-h-9 items-center gap-1.5 text-xs font-medium text-desa-700 underline underline-offset-2"
      >
        <RefreshCw aria-hidden className="size-3.5" /> Ganti pertanyaan
      </button>
      <p className="mt-2 text-xs text-slate-500">
        Pertanyaan sederhana ini memastikan formulir dikirim oleh orang, bukan program otomatis.
      </p>
    </div>
  )
}
