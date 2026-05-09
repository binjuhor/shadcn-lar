import '../css/app.css';
import './bootstrap';

import React, { StrictMode } from 'react';
import { createInertiaApp } from '@inertiajs/react';
import type { Page } from '@inertiajs/core';
import { createRoot } from 'react-dom/client';
import { AppLayout } from './layouts';
import { Providers } from './providers';
import { initI18n } from './lib/i18n';

const appName = import.meta.env.VITE_APP_NAME || 'Shadcn Laravel Admin';

// Inertia PHP v3 changed @inertia to render page data in a <script> tag rather
// than a data-page attribute on the root div. Read from either format.
function resolveInitialPage(id = 'app'): Page | undefined {
  const script = document.querySelector<HTMLScriptElement>(
    `script[data-page="${id}"][type="application/json"]`
  );
  if (script?.textContent) {
    return JSON.parse(script.textContent) as Page;
  }
  const el = document.getElementById(id);
  return el?.dataset?.page ? JSON.parse(el.dataset.page) as Page : undefined;
}

// Glob patterns for page discovery
const mainPages = import.meta.glob('./pages/**/*.tsx');

// Try multiple glob patterns for module pages
const modulePages1 = import.meta.glob('../../Modules/*/resources/js/pages/**/*.tsx');
const modulePages2 = import.meta.glob('/Modules/*/resources/js/pages/**/*.tsx');

// Merge all module pages
const modulePages = { ...modulePages1, ...modulePages2 };


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
      throw new Error(
        `Module page not found: ${name}\n` +
        `Expected path: Modules/${moduleName}/resources/js/pages/${pagePath}.tsx\n` +
        `Tried keys: ${pathFormats.join(', ')}`
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
  page: resolveInitialPage(),
  setup({ el, App, props }) {
    const initialPage = props.initialPage;
    const locale = initialPage.props.locale as string || 'en';
    const translations = initialPage.props.translations as Record<string, string> || {};

    initI18n(locale, translations);

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
