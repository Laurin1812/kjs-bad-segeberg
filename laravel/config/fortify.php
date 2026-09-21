<?php

use Laravel\Fortify\Features;

/*
|--------------------------------------------------------------------------
| KJS Bad Segeberg - Admin-Authentifizierung (Netlify Identity -> Laravel
| Fortify, Session/"web"-Guard)
|--------------------------------------------------------------------------
|
| Diese Konfiguration ersetzt die bisherige, clientseitige Netlify-Identity-
| Anmeldung im bestehenden Admin-Panel (admin/admin.js, admin/index.html)
| durch klassische Laravel-Session-Authentifizierung ueber den Standard-
| "web"-Guard (siehe config/auth.php - unveraendert die bestehende, bisher
| ungenutzte Eloquent-"users"-Tabelle). Fortify selbst rendert KEINE
| eigenen Views (siehe 'views' => false unten) - admin.js spricht die von
| Fortify registrierten Endpunkte weiterhin per fetch()/JSON an, genau wie
| bisher die Netlify-Identity-/git-gateway-Endpunkte.
|
| WICHTIG (Routing-Bruecke, siehe .htaccess im Repo-Wurzelverzeichnis):
| auf dem echten Apache-Host wird bislang NUR "/api/content/*" und
| "/api/admin/*" per Rewrite an laravel/public/index.php durchgereicht.
| Damit Fortifys Login-/Logout-Routen ohne eine weitere .htaccess-Aenderung
| funktionieren, werden sie unten unter genau diesem bereits gebrueckten
| Praefix registriert ("paths" => 'api/admin/auth/...') statt unter den
| Fortify-Standardpfaden "/login"/"/logout" - siehe RoutePath::for() im
| Fortify-Quellcode, das genau diese Ueberschreibung vorsieht.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Guard
    |--------------------------------------------------------------------------
    |
    | Klassischer Session-Guard "web" (config/auth.php) statt eines Token-
    | basierten Guards - erfuellt Auftragspunkt 2 ("klassische Laravel
    | Session-Authentifizierung ueber den web Guard").
    |
    */

    'guard' => 'web',

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Fortifys eigene Routen (Login/Logout) laufen dadurch durch dieselbe
    | "web"-Middleware-Gruppe (Session, CSRF, verschluesselte Cookies) wie
    | die in routes/api.php unten neu mit ->middleware('web') versehene
    | "admin"-Routengruppe.
    |
    */

    'middleware' => ['web'],

    'auth_middleware' => 'auth',

    /*
    |--------------------------------------------------------------------------
    | Password Broker
    |--------------------------------------------------------------------------
    */

    'passwords' => 'users',

    /*
    |--------------------------------------------------------------------------
    | Username / E-Mail
    |--------------------------------------------------------------------------
    |
    | Login erfolgt mit E-Mail + Passwort (Auftragspunkt 4) - "email" ist
    | zugleich der eindeutige Spaltenname in der bestehenden "users"-Tabelle.
    |
    */

    'username' => 'email',

    'email' => 'email',

    /*
    |--------------------------------------------------------------------------
    | Views
    |--------------------------------------------------------------------------
    |
    | Bewusst AUS: das bestehende Admin-Panel hat bereits eine eigene
    | Login-Oberflaeche (admin/index.html #login-screen, siehe dort) und
    | spricht die Fortify-Endpunkte rein per JSON/fetch() an - eine
    | zusaetzliche serverseitig gerenderte Blade-Login-Seite wuerde die
    | bestehende Oberflaeche NICHT ersetzen, sondern nur ungenutzt
    | danebenstehen ("bestehende Admin-Oberflaeche behalten").
    |
    */

    'views' => false,

    'home' => '/admin/',

    /*
    |--------------------------------------------------------------------------
    | Route-Praefix / eigene Pfade
    |--------------------------------------------------------------------------
    |
    | Siehe Datei-Kopfkommentar oben: Login/Logout (und, falls spaeter
    | Mail-Konfiguration nachgereicht wird, Passwort-Reset) laufen bewusst
    | unter dem bereits fuer Laravel gebrueckten Praefix "api/admin/*",
    | damit KEINE zusaetzliche .htaccess-Anpassung noetig ist.
    |
    */

    'prefix' => '',

    'domain' => null,

    'lowercase_usernames' => false,

    'limiters' => [
        // null = Fortifys eingebauter Standard-Rate-Limiter fuer Login
        // greift automatisch (Laravel\Fortify\LoginRateLimiter: 5
        // Fehlversuche je E-Mail+IP-Kombination, danach 60 Sekunden Sperre,
        // siehe EnsureLoginIsNotThrottled in der Login-Pipeline) - erfuellt
        // Auftragspunkt "Rate Limiting fuer Login" ohne eigenen Code.
        'login' => null,
    ],

    'paths' => [
        'login' => 'api/admin/auth/login',
        'logout' => 'api/admin/auth/logout',
        'password' => [
            'request' => 'api/admin/auth/password/forgot',
            'reset' => 'api/admin/auth/password/reset',
            'email' => 'api/admin/auth/password/email',
            'update' => 'api/admin/auth/password/update',
            'confirm' => null,
            'confirmation' => null,
        ],
        'register' => null,
        'verification' => [
            'notice' => null,
            'verify' => null,
            'send' => null,
        ],
        'user-profile-information' => [
            'update' => null,
        ],
        'user-password' => [
            'update' => null,
        ],
        'two-factor' => [
            'login' => null,
            'enable' => null,
            'confirm' => null,
            'disable' => null,
            'qr-code' => null,
            'secret-key' => null,
            'recovery-codes' => null,
        ],
        'passkey' => [
            'login-options' => null,
            'login' => null,
            'confirm-options' => null,
            'confirm' => null,
            'registration-options' => null,
            'store' => null,
            'destroy' => null,
        ],
    ],

    'redirects' => [
        'login' => null,
        'logout' => null,
        'password-confirmation' => null,
        'register' => null,
        'email-verification' => null,
        'password-reset' => null,
    ],

    'passkeys' => [
        'relying_party_id' => parse_url(config('app.url'), PHP_URL_HOST),
        'allowed_origins' => [config('app.url')],
        'user_handle_secret' => env('PASSKEYS_USER_HANDLE_SECRET', config('app.key')),
        'timeout' => 60000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Auftragspunkt 8 ("keine oeffentliche Registrierung"): Features::
    | registration() ist bewusst NICHT enthalten - Fortify registriert
    | dadurch ueberhaupt keine /register-Route. Zwei-Faktor/Passkeys
    | ebenfalls bewusst aus (nicht angefordert, haette zusaetzliche
    | Datenbankspalten/Views noetig). Features::resetPasswords() bleibt AN
    | (Auftragspunkt 10: "darf vorbereitet werden") - die Routen existieren
    | dadurch bereits, senden aber erst dann tatsaechlich eine E-Mail, wenn
    | spaeter eine echte Mail-Konfiguration (MAIL_MAILER etc.) hinterlegt
    | wird; bis dahin wuerde ein Aufruf lediglich mit einem Mail-Fehler
    | fehlschlagen, ohne dass das den Login/Logout-Kernablauf beeintraechtigt.
    |
    */

    'features' => [
        Features::resetPasswords(),
    ],
];
