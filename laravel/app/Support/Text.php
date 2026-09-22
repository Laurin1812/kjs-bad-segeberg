<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * KJS Bad Segeberg - Phase 2/3 (Oeffentliche Inhaltsseiten, Laravel-
 * Vollmigration). Kleine Text-Helfer, server-seitiger Port der
 * clientseitigen kurztext()-Funktion aus aktuelles/index.html (Anzeige-
 * Kurztext einer News-Karte: Markdown-Syntax grob entfernt, auf 150 Zeichen
 * gekuerzt).
 */
class Text
{
    public static function excerpt(?string $markdown, int $length = 150): string
    {
        if (! $markdown) {
            return '';
        }

        $plain = preg_replace('/!\[.*?\]\(.*?\)/', '', $markdown);
        $plain = preg_replace('/\[([^\]]+)\]\(.*?\)/', '$1', $plain);
        $plain = preg_replace('/[#*_>`~]/', '', $plain);
        $plain = trim(preg_replace('/\n+/', ' ', $plain));

        return mb_strlen($plain) > $length ? mb_substr($plain, 0, $length).' …' : $plain;
    }

    /**
     * Phase 3 (Dynamische Seitenfamilien & Hundeausbildung): server-
     * seitiger Port der HTML-vs-Markdown-Weiche aus jaeger/hochwild.html
     * & Co. ("/<[a-z][\s\S]*>/i.test(d.inhalt) ? d.inhalt : marked.parse(...)").
     * In den echten Daten ist "inhalt" (Page-Modell) durchgaengig bereits
     * fertiges HTML aus dem Rich-Text-Editor der Admin-Oberflaeche (siehe
     * z.B. content/jaeger/hochwild.json) - die Markdown-Weiche bleibt hier
     * trotzdem als Fallback erhalten, fuer den (in den aktuellen Daten
     * nicht vorkommenden) Fall reinen Klartexts/Markdowns, exakt wie im
     * Original. Von FesteSeiteController/RegistrySeiteController/
     * HundeausbildungController gemeinsam genutzt (keine Dopplung je
     * Seitenfamilie).
     */
    public static function renderInhalt(?string $inhalt): string
    {
        if (! $inhalt) {
            return '';
        }

        return preg_match('/<[a-z][\s\S]*>/i', $inhalt) === 1
            ? $inhalt
            : (string) Str::markdown($inhalt);
    }
}
