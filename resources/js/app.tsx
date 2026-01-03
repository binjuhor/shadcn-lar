import '../css/app.css';
import './bootstrap';

import React, { StrictMode } from 'react';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { AppLayout } from './layouts';
import { Providers } from './providers';

const appName = import.meta.env.VITE_APP_NAME || 'Shadcn Laravel Admin';

// Glob patterns for page discovery
const mainPages = import.meta.glob('./pages/**/*.tsx');

// Try multiple glob patterns for module pages
const modulePages1 = import.meta.glob('../../Modules/*/resources/js/pages/**/*.tsx');
const modulePages2 = import.meta.glob('/Modules/*/resources/js/pages/**/*.tsx');

// Merge all module pages
const modulePages = { ...modulePages1, ...modulePages2 };

// Debug: Log available module pages on startup
console.log('[DEBUG] modulePages1 keys:', Object.keys(modulePages1));
console.log('[DEBUG] modulePages2 keys:', Object.keys(modulePages2));
console.log('[DEBUG] Total module pages found:', Object.keys(modulePages).length);

/**
 * Resolve Inertia page component with namespace support
 *
 * Supports two formats:
 * - 'page/path' - resolves from resources/js/pages/
 * - 'Module::page/path' - resolves from Modules/{Module}/resources/js/pages/
 *
 * @example
 * Inertia::render('Dashboard')           -> ./pages/Dashboard.tsx
 * Inertia::render('invoices/index')      -> ./pages/invoices/index.tsx
 * Inertia::render('Invoice::index')      -> Modules/Invoice/resources/js/pages/index.tsx
 * Inertia::render('Blog::posts/create')  -> Modules/Blog/resources/js/pages/posts/create.tsx
 */
async function resolvePageComponent(name: string): Promise<React.ComponentType> {
  // Check for namespace syntax (Module::PagePath)
  if (name.includes('::')) {
    const [moduleName, pagePath] = name.split('::');

    // Try different path formats
    const pathFormats = [
      `../../Modules/${moduleName}/resources/js/pages/${pagePath}.tsx`,
      `/Modules/${moduleName}/resources/js/pages/${pagePath}.tsx`,
    ];

    let page = null;
    let usedPath = '';
    for (const path of pathFormats) {
      if (modulePages[path]) {
        page = modulePages[path];
        usedPath = path;
        break;
      }
    }

    if (!page) {
      // Debug: log available keys
      console.error('[DEBUG] Available module pages:', Object.keys(modulePages));
      console.error('[DEBUG] Tried paths:', pathFormats);
      throw new Error(
        `Module page not found: ${name}\n` +
        `Expected path: Modules/${moduleName}/resources/js/pages/${pagePath}.tsx\n` +
        `Tried keys: ${pathFormats.join(', ')}`
      );
    }

    console.log(`[DEBUG] Resolved ${name} using path: ${usedPath}`);
    const module = await page();
    return (module as { default: React.ComponentType }).default;
  }

  // Standard page resolution (main app)
  const pagePath = `./pages/${name}.tsx`;
  const page = mainPages[pagePath];

  if (!page) {
    throw new Error(
      `Page not found: ${name}\n` +
      `Expected path: resources/js/pages/${name}.tsx`
    );
  }

  const module = await page();
  return (module as { default: React.ComponentType }).default;
}

createInertiaApp({
  title: (title) => `${title} - ${appName}`,
  resolve: resolvePageComponent,
  setup({ el, App, props }) {
    const root = createRoot(el);

    root.render(
      <StrictMode>
        <Providers>
          <AppLayout>
            <App {...props} />
          </AppLayout>
        </Providers>
      </StrictMode>
    );
  },
  progress: {
    color: '#4B5563',
  },
});
