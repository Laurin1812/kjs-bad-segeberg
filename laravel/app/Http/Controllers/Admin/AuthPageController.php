<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminIdentity;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * KJS Bad Segeberg - Phase 7A (Laravel-Admin-Grundlage / Auth / Admin-Shell).
 *
 * Liefert NUR die drei deutschen Auth-Formularseiten (Login, Passwort
 * vergessen, Passwort zuruecksetzen) fuer den neuen Blade-Admin unter
 * "/admin". Die eigentliche Anmelde-/Reset-LOGIK bleibt bewusst vollstaendig
 * bei Laravel Fortify - siehe config/fortify.php-Kommentar "Routing-
 * Bruecke": Fortify registriert seine POST-Endpunkte (Login, Logout,
 * Passwort-E-Mail, Passwort-Update) bereits fest unter dem per .htaccess
 * gebrueckten Praefix "api/admin/auth/*" (siehe config('fortify.paths.*')
 * unten in den Views) - dieser Controller/diese Views muessen dafuer NICHTS
 * Neues registrieren, nur ganz gewoehnliche <form method="POST"
 * action="..."> dorthin schicken. config('fortify.views') bleibt bewusst
 * "false" (siehe dortiger Kommentar: admin.js hat weiterhin seine eigene,
 * unveraenderte Login-Oberflaeche und spricht dieselben Endpunkte per JSON
 * an) - Fortify registriert deshalb selbst KEINE der hier gebauten
 * GET-Routen, es gibt also keine Kollision.
 */
class AuthPageController extends Controller
{
    /**
     * GET /admin/login. Bereits angemeldete Admins werden direkt zum
     * Dashboard weitergeleitet (kein doppeltes Login-Formular) - bewusst
     * KEIN generisches "guest:web"-Middleware auf der Route (siehe
     * routes/web.php), da dessen Standard-Ziel (route('home')) auf die
     * OEFFENTLICHE Startseite zeigen wuerde statt auf das Admin-Dashboard.
     */
    public function login(Request $request): View|RedirectResponse
    {
        $user = AdminIdentity::currentUser($request);
        if ($user !== null && AdminIdentity::isAdmin($user)) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    /** GET /admin/passwort-vergessen. */
    public function passwortVergessen(): View
    {
        return view('admin.passwort-vergessen');
    }

    /**
     * GET /admin/passwort-zuruecksetzen/{token}. $email kommt - wie bei
     * Fortifys eigener (hier deaktivierter) View-Route ueblich - als
     * Query-Parameter aus dem Link der Reset-E-Mail (siehe
     * Illuminate\Auth\Notifications\ResetPassword) und wird nur als
     * versteckter Formularwert vorausgefuellt, nicht serverseitig geprueft
     * (das macht Fortifys eigener PUT-Endpunkt beim Absenden).
     */
    public function passwortZuruecksetzen(Request $request, string $token): View
    {
        return view('admin.passwort-zuruecksetzen', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }
}
