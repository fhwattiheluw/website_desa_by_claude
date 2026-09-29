import { Component, type ErrorInfo, type ReactNode } from 'react'

/**
 * Jaring pengaman antarmuka: kegagalan tak terduga pada satu halaman tidak
 * boleh menyisakan layar kosong tanpa penjelasan (REQ-UI-006).
 */
export class BatasGalat extends Component<{ children: ReactNode }, { gagal: boolean }> {
  state = { gagal: false }

  static getDerivedStateFromError() {
    return { gagal: true }
  }

  componentDidCatch(galat: Error, info: ErrorInfo) {
    console.error('Halaman gagal dirender', galat, info.componentStack)
  }

  render() {
    if (!this.state.gagal) return this.props.children

    return (
      <div className="mx-auto max-w-lg px-4 py-16 text-center">
        <h1 className="text-2xl">Halaman gagal ditampilkan</h1>
        <p className="mt-2 text-slate-600">
          Terjadi gangguan saat menampilkan halaman ini. Silakan muat ulang halaman; bila masalah berlanjut,
          laporkan kepada petugas desa.
        </p>
        <button
          type="button"
          onClick={() => window.location.reload()}
          className="mt-6 inline-flex min-h-11 items-center rounded-lg bg-desa-700 px-4 text-sm font-medium text-white hover:bg-desa-800"
        >
          Muat Ulang Halaman
        </button>
      </div>
    )
  }
}
