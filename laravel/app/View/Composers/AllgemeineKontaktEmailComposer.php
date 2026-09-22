<?php

namespace App\View\Composers;

use App\Models\Setting;
use Illuminate\View\View;

/**
 * KJS Bad Segeberg - Phase 4 Korrektur (allgemeine KJS-Kontaktadresse in
 * Fliesstexten).
 *
 * Ersetzt die zuletzt noch hart codierten Vorkommen der allgemeinen
 * KJS-Geschaeftsstellen-Adresse ("info@kjs-bad-segeberg.de") in reinem
 * Fliesstext auf faq/downloads/jaeger.vorstand - dieselbe zentrale Quelle
 * wie Topbar (siehe TopbarComposer) und Kontaktbox (siehe components/
 * kontaktbox.blade.php): settings-Tabelle, Gruppe "einstellungen", Key
 * "email". Bewusst EIN schlanker Composer statt drei duplizierter
 * Controller-Aenderungen, da alle drei Views reinen Lesezugriff auf
 * denselben Wert brauchen und keine eigene Business-Logik dafuer haben.
 *
 * Fehlt der Wert, wird KEIN Platzhalter erfunden - die Views blenden den
 * jeweiligen Mail-Verweis dann einfach aus (siehe dortiges @if).
 */
class AllgemeineKontaktEmailComposer
{
    public function compose(View $view): void
    {
        $email = trim((string) Setting::where('gruppe', 'einstellungen')
            ->where('key', 'email')
            ->value('value'));

        $view->with('kjsAllgemeineEmail', $email !== '' ? $email : null);
    }
}
