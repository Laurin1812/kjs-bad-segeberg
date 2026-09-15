<?php

// Phase 5B.1 (sichere Laravel-Medienarchitektur, siehe Analysebericht
// Phase 5A Punkt 3/7): zentrale Konfiguration fuer ALLES, was mit
// Bild-/PDF-Ablage zu tun hat - App\Support\MediaStorage und
// App\Support\MediaUploadService lesen Pfade/Limits AUSSCHLIESSLICH von
// hier, nie direkt per env()/hartcodiert an anderer Stelle. Auf Carstens
// Server muss (voraussichtlich) NUR die echte .env angepasst werden
// (KJS_IMAGES_PATH/KJS_DOWNLOADS_PATH auf den dortigen tatsaechlichen
// Pfad), ohne dass irgendein Code beruehrt werden muss.
//
// LOKALER DEFAULT: dirname(base_path()) ist von laravel/ aus das
// Repository-Wurzelverzeichnis - genau dort liegen die bestehenden
// images/-/downloads/-Ordner schon heute (siehe Analysebericht Punkt 3,
// Netlify liefert sie unveraendert von dort aus). Das ist bewusst NUR der
// Default fuer die lokale Entwicklung/dieses Sandbox-Setup - auf dem
// echten Server wird KJS_IMAGES_PATH/KJS_DOWNLOADS_PATH in der .env
// voraussichtlich einen anderen, expliziten Pfad tragen (siehe
// Abschlussbericht "Server-Anforderungen").
//
// URL-PRAEFIX bewusst GETRENNT vom Dateisystempfad (Auftrag Punkt 2): eine
// hochgeladene Datei liegt unter images.path/<name>, ist aber unter
// images.url_prefix/<name> erreichbar - auf Netlify sind beide "/images"
// (weil der Ordner direkt im ausgelieferten Repo-Root liegt), auf einem
// echten Server koennen Dateisystempfad und oeffentlicher URL-Pfad
// vollstaendig unabhaengig voneinander sein.
return [

    'images' => [
        'path' => env('KJS_IMAGES_PATH', dirname(base_path()).'/images'),
        'url_prefix' => env('KJS_IMAGES_URL_PREFIX', '/images'),

        // Unterordner-NAMEN (nicht ganze Pfade) fuer die beiden
        // Vorschau-Varianten - liegen IMMER unter images.path bzw.
        // images.url_prefix, siehe MediaStorage::thumbDiskPath()/
        // cardDiskPath()/thumbUrl()/cardUrl(). Identische Namen wie im
        // bestehenden admin.js (images/thumb/, images/card/) und in
        // js/main.js (kjsThumbUrl()/kjsCardUrl()) - siehe Analysebericht
        // Punkt 3 - bewusst NICHT geaendert, damit bestehende und neue
        // Dateien unter derselben Konvention nebeneinander liegen.
        'thumb_dir' => 'thumb',
        'card_dir' => 'card',

        // Dieselben Zielgroessen/Qualitaetsstufen wie im bestehenden
        // client-seitigen admin.js (THUMB_MAX_DIMENSION/CARD_MAX_DIMENSION)
        // und im bereits produktiven serverseitigen Vorbild
        // api/lib/boerse_upload.php (kjs_boerse_generate_image_variants) -
        // bewusst NICHT neu erfunden, siehe Auftrag Punkt 4 ("funktional
        // moeglichst gleich zum bestehenden Verhalten").
        'thumb_max_dimension' => (int) env('KJS_THUMB_MAX_DIMENSION', 480),
        'thumb_quality' => (int) env('KJS_THUMB_QUALITY', 72),
        'card_max_dimension' => (int) env('KJS_CARD_MAX_DIMENSION', 800),
        'card_quality' => (int) env('KJS_CARD_QUALITY', 78),

        // Grosszuegiger als das alte, rein technisch bedingte ~1MB-Limit
        // der GitHub-Contents-API (siehe Analysebericht Punkt 1) - unechte
        // Handyfotos VOR der clientseitigen Komprimierung koennen mehrere
        // MB gross sein, und diese neue API ist (noch) nicht an admin.js
        // angebunden (Auftrag Punkt 8), validiert also auch unkomprimierte
        // Originale. Ueber KJS_MAX_IMAGE_BYTES ohne Codeaenderung anpassbar.
        'max_bytes' => (int) env('KJS_MAX_IMAGE_BYTES', 15 * 1024 * 1024),

        // Bewusst NUR diese drei (Auftrag Punkt 3): kein SVG (Skript-
        // Ausfuehrungsrisiko im Browser bei Direktaufruf), keine neuen
        // GIF-Uploads (Animation wuerde durch die Vorschau-Rasterung ohnehin
        // kaputtgehen, siehe bestehendes admin.js IMG_SKIP_TYPES-Verhalten,
        // hier aber zusaetzlich als serverseitige Ablehnung statt nur
        // "unveraendert durchlassen").
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
    ],

    'downloads' => [
        'path' => env('KJS_DOWNLOADS_PATH', dirname(base_path()).'/downloads'),
        'url_prefix' => env('KJS_DOWNLOADS_URL_PREFIX', '/downloads'),

        // Grosszuegiger als das alte ~1MB-Client-Limit (siehe
        // Analysebericht Punkt 1) - echte Broschueren/Formulare als PDF
        // koennen mehrere MB gross sein; ueber KJS_MAX_PDF_BYTES ohne
        // Codeaenderung anpassbar.
        'max_bytes' => (int) env('KJS_MAX_PDF_BYTES', 20 * 1024 * 1024),

        'allowed_mimes' => ['application/pdf'],
    ],

];
