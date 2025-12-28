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
const modulePages = import.meta.glob('../../Modules/*/resources/js/pages/**/*.tsx');

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
    const modulePath = `../../Modules/${moduleName}/resources/js/pages/${pagePath}.tsx`;

    const page = modulePages[modulePath];
    if (!page) {
      throw new Error(
        `Module page not found: ${name}\n` +
        `Expected path: Modules/${moduleName}/resources/js/pages/${pagePath}.tsx`
      );
    }

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
