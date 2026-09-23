<?php

namespace App\Http\Middleware;

use App\Support\AdminIdentity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * KJS Bad Segeberg - Phase 7A (Laravel-Admin-Grundlage / Auth / Admin-Shell).
 *
 * Schuetzt die NEUEN, server-gerenderten Blade-Admin-Routen (siehe
 * routes/web.php, Praefix "admin", z.B. das kuenftige Dashboard). Bewusst
 * eine EIGENE, kleine Middleware statt der bestehenden
 * App\Http\Middleware\EnsureIdentityPermission wiederzuverwenden: jene ist
 * fuer admin.js' JSON-Schreib-API gebaut und antwortet bei fehlender
 * Anmeldung/Berechtigung IMMER mit 401/403-JSON (siehe dortiger
 * Klassenkommentar) - fuer eine klassische, per Browser aufgerufene
 * Blade-Seite ist stattdessen ein 302-Redirect zur Login-Seite (nicht
 * angemeldet) bzw. eine echte deutsche 403-Fehlerseite (angemeldet, aber
 * keine Admin-Rolle) das richtige Verhalten - exakt der in Auftragspunkt 3
 * ("Admin-Zugriff") geforderte Unterschied. Das zugrunde liegende
 * Rollenmodell selbst ist NICHT neu erfunden: sowohl hier als auch in
 * EnsureIdentityPermission wird ausschliesslich App\Support\AdminIdentity
 * befragt (dieselbe roles/permissions-Struktur, derselbe "admin"-Rollen-
 * String).
 *
 * Bewusst nur "ist admin" (keine granularen Modul-Rechte wie
 * EnsurePagePermission/EnsureIdentityPermission) - Auftragspunkt 3 verlangt
 * fuer Phase 7A nur drei Zustaende: nicht eingeloggt / eingeloggt-aber-
 * kein-Admin / Admin. Granulare Redakteur-Rechte innerhalb des neuen
 * Blade-Admins sind erst mit den eigentlichen Fachmodulen (Phase 8+)
 * relevant, wenn dort tatsaechlich Nicht-Admin-Redakteure etwas bearbeiten
 * koennen sollen.
 */
class EnsureAdminWebSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = AdminIdentity::currentUser($request);

        if ($user === null) {
            // redirect()->guest() setzt zusaetzlich die Session-Variable
            // "url.intended" - Laravel\Fortify\Http\Responses\LoginResponse
            // liefert nach einem erfolgreichen Login bewusst
            // redirect()->intended(...) (Fortify-Kernverhalten,
            // unveraendert) und fuehrt den Redakteur dadurch automatisch
            // zurueck zu der Admin-Seite, die er urspruenglich aufrufen
            // wollte - ganz ohne eigenen Code dafuer.
            return redirect()->guest(route('admin.login'));
        }

        if (! AdminIdentity::isAdmin($user)) {
            abort(403, 'Kein Zugriff auf den Admin-Bereich.');
        }

        $request->attributes->set('identity_user', $user);

        return $next($request);
    }
}
