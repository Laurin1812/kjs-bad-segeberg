<?php

namespace App\Support;

/**
 * KJS Bad Segeberg - Phase 2 (Oeffentliche Inhaltsseiten, Laravel-
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
}
