import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { fileURLToPath, URL } from 'node:url';
export default defineConfig({ plugins: [react()], resolve: { alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) } }, build: { rollupOptions: { output: { manualChunks: { 'echarts': ['echarts', 'echarts-for-react'], 'react-vendor': ['react', 'react-dom', 'react-router-dom', '@tanstack/react-query', 'react-hook-form'], 'ui-vendor': ['lucide-react', 'qrcode'] } } } } });
