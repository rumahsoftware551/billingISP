import '../css/app.css';
import React from 'react';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
const pages = import.meta.glob('./pages/**/*.tsx');
createInertiaApp({
  resolve: async (name) => { const page = pages[`./pages/${name}.tsx`]; if(!page) throw new Error(`Page ${name} not found`); return (await page()) as any; },
  setup({el,App,props}) { createRoot(el).render(<App {...props}/>); },
  progress:{color:'#38bdf8'}
});

if (typeof window !== 'undefined' && (window.location.pathname === '/portal' || window.location.pathname.startsWith('/portal/')) && 'serviceWorker' in navigator) {
  window.addEventListener('load', () => { navigator.serviceWorker.register('/portal-sw.js').catch(() => {}); });
}
