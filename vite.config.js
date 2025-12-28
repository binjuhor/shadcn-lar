import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '');

  return {
    plugins: [
      laravel({
        input: 'resources/js/app.tsx',
        refresh: [
          'resources/views/**',
          'Modules/*/resources/views/**',
          'Modules/*/resources/js/**',
        ],
      }),
      react(),
    ],
    resolve: {
      alias: {
        '@': path.resolve(__dirname, 'resources/js'),
        '@modules': path.resolve(__dirname, 'Modules'),
      },
    },
    build: {
      rollupOptions: {
        output: {
          manualChunks(id) {
            // Share vendor libs across all modules
            if (id.includes('node_modules')) {
              if (id.includes('react') || id.includes('react-dom')) {
                return 'vendor-react';
              }
              if (id.includes('@radix-ui') || id.includes('lucide-react')) {
                return 'vendor-ui';
              }
              return 'vendor';
            }
            // Separate module chunks
            if (id.includes('/Modules/')) {
              const match = id.match(/\/Modules\/(\w+)\//);
              if (match) {
                return `module-${match[1].toLowerCase()}`;
              }
            }
          },
        },
      },
    },
  };
});
