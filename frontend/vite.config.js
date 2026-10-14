import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// Build ide priamo do Laravel public/ (index.html + assets/), public/index.php ostáva.
export default defineConfig({
  plugins: [react()],
  build: { outDir: '../public', emptyOutDir: false },
  server: { proxy: { '/api': 'http://localhost:8000' } },
})
