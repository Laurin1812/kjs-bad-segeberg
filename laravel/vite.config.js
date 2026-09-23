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
            // Phase 7A (Laravel-Admin-Grundlage): "resources/css/admin.css"
            // ist ein bewusst EIGENER, zweiter Vite-Eintrag statt eines
            // Imports in app.css - der neue Blade-Admin (siehe
            // components/layouts/admin.blade.php) teilt sich mit den
            // oeffentlichen Seiten weder Layout noch Farbschema-Variablen
            // (siehe admin.css-Kopfkommentar), beide Bereiche laden dadurch
            // weiterhin nur genau das CSS, das sie tatsaechlich brauchen.
            // Phase 7B (Admin-Modul "Inhalte/Seiten"): "resources/js/
            // admin-inhalte-editor.js" ist der vierte, ebenfalls eigene
            // Eintrag - der per npm installierte TipTap-Rich-Text-Editor
            // (siehe dortiger Klassenkommentar) wird NUR auf der Bearbeiten-
            // Seite dieses einen Moduls eingebunden, nicht global in app.js/
            // admin.css.
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/admin.css', 'resources/js/admin-inhalte-editor.js'],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
