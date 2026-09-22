<?php

namespace App\Support;

/**
 * KJS Bad Segeberg - Phase 2 (Oeffentliche Inhaltsseiten, Laravel-
 * Vollmigration).
 *
 * Server-seitiger Port der Bild-Varianten-Helfer aus js/main.js
 * (kjsVariantUrl/kjsThumbUrl/kjsCardUrl). Fuer JSON-getriebene, clientseitig
 * gerenderte Karten war das eine JS-Funktion; server-gerenderte Blade-
 * Views koennen dieselbe simple Pfad-Ableitung direkt in PHP machen, ohne
 * dafuer extra JavaScript zu laden. admin.js legt weiterhin automatisch
 * /images/thumb/<datei> (~480px) und /images/card/<datei> (~800px) neben
 * dem Original ab - beide werden ausschliesslich aus dem bestehenden
 * Original-Pfad abgeleitet (gleicher Dateiname, anderer Ordner).
 *
 * Fehlt die Variante (z.B. vor dieser Umstellung hochgeladenes Bild), sorgt
 * das "onerror"-Attribut (siehe kjsImgFallback in resources/js/app.js)
 * weiterhin dafuer, dass automatisch das Original nachgeladen wird.
 */
class Images
{
    public static function variantUrl(?string $url, string $folder): ?string
    {
        if (! $url) {
            return $url;
        }

        $pos = strrpos($url, '/images/');
        if ($pos === false) {
            return $url;
        }

        return substr($url, 0, $pos).'/images/'.$folder.'/'.substr($url, $pos + strlen('/images/'));
    }

    public static function thumbUrl(?string $url): ?string
    {
        return self::variantUrl($url, 'thumb');
    }

    public static function cardUrl(?string $url): ?string
    {
        return self::variantUrl($url, 'card');
    }

    /**
     * Phase 3 (Dynamische Seitenfamilien & Hundeausbildung): server-
     * seitiger Port der Bildgroessen-Klassen-Weiche aus jaeger/hochwild.html
     * & Co. ("(d.bild_groesse&&d.bild_groesse.indexOf('img-')===0)?d.bild_groesse
     * :(d.bild_groesse==='klein'?'img-25':d.bild_groesse==='mittel'?'img-50'
     * :'img-100')"). Von FesteSeiteController/RegistrySeiteController/
     * HundeausbildungController gemeinsam genutzt.
     */
    public static function groesseClass(?string $bildGroesse): string
    {
        if ($bildGroesse && str_starts_with($bildGroesse, 'img-')) {
            return $bildGroesse;
        }

        return match ($bildGroesse) {
            'klein' => 'img-25',
            'mittel' => 'img-50',
            default => 'img-100',
        };
    }
}
