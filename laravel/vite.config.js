import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

// Phase 1 (Blade-Fundament, Laravel-Vollmigration): der von "laravel new"
// mitgelieferte Starter-Zuschnitt (Tailwind CSS + der "bunny"-Google-Font-Loader
// fuer "Instrument Sans") wurde hier bewusst ENTFERNT statt nur ungenutzt stehen
// gelassen. KJS Segeberg hat ein eigenes, seit Jahren gewachsenes Stylesheet auf
// Basis von CSS Custom Properties (siehe resources/css/app.css) und ausschliesslich
// websichere Systemschriften (var(--font-sans)/var(--font-heading)) - Tailwind
// wuerde hier keine einzige Zeile Nutzen bringen, aber zusaetzliches Zeug
// dauerhaft im Projekt "hybrid" mitschleppen (genau das, was der Auftrag fuer
// diese Migration ausdruecklich verbietet). Falls spaeter doch einmal Tailwind
// gebraucht werden sollte: "composer require -D @tailwindcss/vite tailwindcss"
// und den Plugin-Eintrag unten wieder ergaenzen.
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
