import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import { VitePWA } from 'vite-plugin-pwa'
import path from 'node:path'

export default defineConfig({
  plugins: [
    react(),
    tailwindcss(),
    /*
     * Portal dipasang sebagai aplikasi web progresif agar tetap terbuka pada
     * koneksi desa yang tidak stabil (CON-02): kerangka aplikasi disimpan di
     * peramban, data publik yang pernah dibuka disajikan dari singgahan bila
     * jaringan putus, dan halaman luring menjelaskan keadaannya.
     */
    VitePWA({
      registerType: 'autoUpdate',
      includeAssets: ['favicon.svg', 'luring.html'],
      manifest: {
        name: 'Portal Desa',
        short_name: 'Portal Desa',
        description:
          'Portal resmi pemerintah desa: informasi, transparansi anggaran, layanan administrasi daring, dan pengaduan masyarakat.',
        lang: 'id',
        start_url: '/',
        scope: '/',
        display: 'standalone',
        background_color: '#f8fafc',
        theme_color: '#0f766e',
        icons: [
          { src: 'ikon-192.png', sizes: '192x192', type: 'image/png' },
          { src: 'ikon-512.png', sizes: '512x512', type: 'image/png' },
          { src: 'ikon-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
        ],
        shortcuts: [
          { name: 'Ajukan Surat', url: '/layanan' },
          { name: 'Lacak Pengaduan', url: '/pengaduan/lacak' },
          { name: 'Transparansi APBDes', url: '/transparansi/apbdes' },
        ],
      },
      workbox: {
        navigateFallback: '/index.html',
        // Permintaan API tidak pernah dijawab oleh kerangka aplikasi.
        navigateFallbackDenylist: [/^\/api/, /^\/storage/, /^\/rss/, /^\/sitemap\.xml$/, /^\/robots\.txt$/],
        globPatterns: ['**/*.{js,css,html,svg,png,woff2}'],
        runtimeCaching: [
          {
            // Data publik boleh basi sesaat demi tetap terbaca saat jaringan mati.
            urlPattern: ({ url }) =>
              url.pathname.startsWith('/api/v1/') &&
              !url.pathname.startsWith('/api/v1/admin') &&
              !url.pathname.startsWith('/api/v1/permohonan') &&
              !url.pathname.startsWith('/api/v1/auth'),
            handler: 'NetworkFirst',
            options: {
              cacheName: 'data-publik',
              networkTimeoutSeconds: 5,
              expiration: { maxEntries: 120, maxAgeSeconds: 60 * 60 * 24 },
              cacheableResponse: { statuses: [200] },
            },
          },
          {
            urlPattern: ({ url }) => url.pathname.startsWith('/storage/'),
            handler: 'CacheFirst',
            options: {
              cacheName: 'media-desa',
              expiration: { maxEntries: 80, maxAgeSeconds: 60 * 60 * 24 * 14 },
              cacheableResponse: { statuses: [200] },
            },
          },
        ],
      },
      devOptions: { enabled: false },
    }),
  ],
  resolve: {
    alias: { '@': path.resolve(__dirname, './src') },
  },
  server: {
    port: 5173,
    proxy: {
      // Selama pengembangan, permintaan API diteruskan ke Laravel.
      '/api': { target: 'http://127.0.0.1:8000', changeOrigin: true },
      '/storage': { target: 'http://127.0.0.1:8000', changeOrigin: true },
    },
  },
  preview: {
    // Pratinjau hasil build memakai penerusan yang sama, agar perilaku
    // aplikasi web progresif dapat diuji seperti keadaan produksi.
    port: 4173,
    proxy: {
      '/api': { target: 'http://127.0.0.1:8000', changeOrigin: true },
      '/storage': { target: 'http://127.0.0.1:8000', changeOrigin: true },
    },
  },
})
