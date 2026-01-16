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
        '@modules/Blog': path.resolve(__dirname, 'Modules/Blog/resources/js'),
        '@modules/Ecommerce': path.resolve(__dirname, 'Modules/Ecommerce/resources/js'),
        '@modules/Invoice': path.resolve(__dirname, 'Modules/Invoice/resources/js'),
        '@modules/Notification': path.resolve(__dirname, 'Modules/Notification/resources/js'),
        '@modules/Permission': path.resolve(__dirname, 'Modules/Permission/resources/js'),
        '@modules/Finance': path.resolve(__dirname, 'Modules/Finance/resources/js'),
        '@modules/Settings': path.resolve(__dirname, 'Modules/Settings/resources/js'),
      },
    },
    build: {
      rollupOptions: {
        output: {
          manualChunks(id) {
            // Share vendor libs across all modules
            if (id.includes('node_modules')) {
              // React ecosystem packages must stay together
              const reactPackages = [
                'react',
                'react-dom',
                'scheduler',
                'react-is',
                'use-sync-external-store',
                '@inertiajs',
              ];
              if (reactPackages.some(pkg => id.includes(`node_modules/${pkg}/`) || id.includes(`node_modules/${pkg}`))) {
                return 'vendor-react';
              }
              if (id.includes('@radix-ui') || id.includes('lucide-react')) {
                return 'vendor-ui';
              }
              // Keep embla-carousel packages together
              if (id.includes('embla-carousel')) {
                return 'vendor-ui';
              }
              // Keep recharts and d3 packages together
              if (id.includes('recharts') || id.includes('d3-')) {
                return 'vendor-charts';
              }
              // Keep motion/animation packages together
              if (id.includes('motion') || id.includes('framer-motion')) {
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
