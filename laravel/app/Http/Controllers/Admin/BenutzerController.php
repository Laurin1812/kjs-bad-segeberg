<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AdminIdentity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Laravel\Fortify\Rules\Password;

/**
 * KJS Bad Segeberg - Phase 7L (Admin-Modul "Benutzer"), Teil B.
 *
 * Zwoelftes und letztes echtes Fachmodul im neuen, server-gerenderten
 * Blade-Admin (siehe Auftrag "letzter Baustein vor dem Cutover-/QA-Block") -
 * zweiter der beiden letzten Sidebar-Platzhalter.
 *
 * ANALYSE (Auftrag Teil B Punkt 1): es gibt bislang KEINERLEI Laravel-CRUD
 * fuer Benutzer - weder Controller noch View. Einzige bisherige
 * Verwaltungsmoeglichkeit war das Artisan-Kommando `php artisan
 * admin:create-user` (siehe App\Console\Commands\CreateAdminUser), das
 * genau das hier fortgesetzte Rollen-Schema verwendet: `roles` ist ein
 * JSON-Array (kein separates Rollen-Model/keine Pivot-Tabelle, siehe
 * App\Models\User), `App\Support\AdminIdentity::isAdmin()` prueft
 * ausschliesslich, ob der String "admin" in diesem Array enthalten ist -
 * das ist die GESAMTE Rollen-/Zugriffslogik des neuen Blade-Admins.
 *
 * BEWUSST NICHT gebaut (Auftrag Teil B Punkt 2 "keine neue Rollen-/
 * Permission-Engine bauen" + "wenn aktuell nur admin als Rolle real genutzt
 * wird: das nicht kuenstlich verkomplizieren"): eine UI fuer die granularen
 * `permissions` (Modul-Rechte fuer eine theoretische "redakteur"-Rolle,
 * siehe App\Support\PagePermissions-Klassenkommentar) - diese werden
 * ausschliesslich von der ALTEN JSON-Admin-API (`identity.permission:<key>`-
 * Middleware) ausgewertet, NICHT vom neuen `admin.web`-Bereich (der prueft
 * einzig AdminIdentity::isAdmin()). Dieses Modul verwaltet deshalb nur genau
 * das eine Recht, das der neue Admin tatsaechlich kennt: "Administrator"
 * (ja/nein), exakt wie schon `admin:create-user --admin`. Das `permissions`-
 * Feld eines Benutzers bleibt beim Speichern hier unangetastet (kein
 * Mass-Assignment auf diesem Feld).
 *
 * SCHUTZREGELN (Auftrag Teil B Punkt 5, 1:1 vom Alt-System `admin-users.js`
 * uebernommen - siehe dortiger Kopfkommentar "VERALTET", die Regeln selbst
 * bleiben aber weiterhin sinnvoll):
 * - Selbst-Lockout-Schutz: ein Admin kann sich nicht selbst die
 *   Administrator-Rolle entziehen (unabhaengig davon, ob noch andere Admins
 *   existieren).
 * - Letzter-Admin-Schutz: der letzte verbleibende Administrator kann weder
 *   herabgestuft noch geloescht werden. In der Praxis kann dieser Fall nur
 *   als Selbst-Aktion auftreten (nur der admin.web-Middleware wegen muss
 *   jeder Aufrufer bereits Administrator sein - existiert nur noch EIN
 *   Administrator, ist der Aufrufer zwangslaeufig genau dieser eine), die
 *   Zaehl-Pruefung bleibt aber bewusst als zusaetzliche, vom Selbst-Check
 *   unabhaengige Absicherung bestehen (defense-in-depth, greift z.B. sofort
 *   korrekt, falls das Rollenmodell spaeter um weitere Akteure erweitert
 *   wird).
 * - Selbst-Loesch-Schutz: der eigene Account kann nicht geloescht werden
 *   (unabhaengig davon, ob noch andere Admins existieren).
 * - "keine frei erfundenen Rollen": das Formular sendet nie einen rohen
 *   Rollen-String, sondern ausschliesslich eine "ist_admin"-Checkbox - ein
 *   manipulierter Request mit einem rohen "roles"-Feld wird ignoriert (siehe
 *   validateData()), die tatsaechlichen Rollen werden serverseitig einzig
 *   aus dieser Checkbox abgeleitet.
 *
 * PASSWORT (Auftrag Teil B Punkt 4): dieselbe Passwort-Regel wie Fortifys
 * eigener Reset-Weg (siehe App\Actions\Fortify\ResetUserPassword) - beim
 * Anlegen Pflichtfeld, beim Bearbeiten optional ("leer lassen" behaelt den
 * bestehenden Hash), immer mit Bestaetigungsfeld, nie im Klartext geloggt.
 */
class BenutzerController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.benutzer.index', [
            'benutzer' => User::orderBy('name')->get(),
            'eigeneId' => $this->eigeneId($request),
        ]);
    }

    public function neu(): View
    {
        return view('admin.benutzer.bearbeiten', [
            'benutzer' => new User,
            'istNeu' => true,
            'istAdmin' => false,
            'istEigenerAccount' => false,
            'istLetzterAdmin' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateData($request, null);

        $benutzer = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'roles' => $request->boolean('ist_admin') ? ['admin'] : [],
            'permissions' => [],
        ]);

        return redirect()->route('admin.benutzer.bearbeiten', $benutzer)->with('status', 'Benutzer angelegt.');
    }

    public function edit(Request $request, User $benutzer): View
    {
        return view('admin.benutzer.bearbeiten', [
            'benutzer' => $benutzer,
            'istNeu' => false,
            'istAdmin' => self::istAdmin($benutzer),
            'istEigenerAccount' => $benutzer->id === $this->eigeneId($request),
            'istLetzterAdmin' => self::istAdmin($benutzer) && self::adminAnzahl() <= 1,
        ]);
    }

    public function update(Request $request, User $benutzer): RedirectResponse
    {
        $validated = $this->validateData($request, $benutzer);

        $warAdmin = self::istAdmin($benutzer);
        $sollAdmin = $request->boolean('ist_admin');

        if ($warAdmin && ! $sollAdmin) {
            $fehler = $this->pruefeAbstufungErlaubt($benutzer, $request);
            if ($fehler !== null) {
                return back()->withInput()->withErrors(['ist_admin' => $fehler]);
            }
        }

        $benutzer->name = $validated['name'];
        $benutzer->email = $validated['email'];
        $benutzer->roles = $sollAdmin ? ['admin'] : [];
        if (($validated['password'] ?? '') !== '') {
            $benutzer->password = Hash::make($validated['password']);
        }
        $benutzer->save();

        return redirect()->route('admin.benutzer.bearbeiten', $benutzer)->with('status', 'Benutzer gespeichert.');
    }

    public function destroy(Request $request, User $benutzer): RedirectResponse
    {
        if ($benutzer->id === $this->eigeneId($request)) {
            return back()->withErrors(['name' => 'Sie können Ihren eigenen Account nicht löschen.']);
        }
        if (self::istAdmin($benutzer) && self::adminAnzahl() <= 1) {
            return back()->withErrors(['name' => 'Der letzte verbleibende Administrator kann nicht gelöscht werden.']);
        }

        $benutzer->delete();

        return redirect()->route('admin.benutzer.index')->with('status', 'Benutzer gelöscht.');
    }

    /** Selbst-Lockout-/Letzter-Admin-Schutz beim Herabstufen (siehe Klassenkommentar), gemeinsam genutzt von update(). */
    private function pruefeAbstufungErlaubt(User $benutzer, Request $request): ?string
    {
        if ($benutzer->id === $this->eigeneId($request)) {
            return 'Sie können sich nicht selbst die Administrator-Rolle entziehen.';
        }
        if (self::adminAnzahl() <= 1) {
            return 'Der letzte verbleibende Administrator kann nicht herabgestuft werden.';
        }

        return null;
    }

    private function eigeneId(Request $request): ?int
    {
        $sub = AdminIdentity::currentUser($request)['sub'] ?? null;

        return $sub !== null ? (int) $sub : null;
    }

    private static function istAdmin(User $benutzer): bool
    {
        return in_array('admin', is_array($benutzer->roles) ? $benutzer->roles : [], true);
    }

    private static function adminAnzahl(): int
    {
        return User::whereJsonContains('roles', 'admin')->count();
    }

    /**
     * "keine frei erfundenen Rollen" (siehe Klassenkommentar): only() nimmt
     * ausschliesslich Name/E-Mail/Passwort auf - ein zusaetzlich im Request
     * mitgeschicktes rohes "roles"-Feld (z.B. ein manipulierter Request mit
     * roles[]=redakteur) wird hier gar nicht erst gelesen, die tatsaechliche
     * Rolle wird in store()/update() ausschliesslich aus der booleschen
     * "ist_admin"-Checkbox abgeleitet.
     *
     * @return array<string, string>
     */
    private function validateData(Request $request, ?User $benutzer): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($benutzer?->id)],
        ];
        $rules['password'] = $benutzer === null
            ? ['required', 'string', new Password, 'confirmed']
            : ['nullable', 'string', new Password, 'confirmed'];

        return $request->validate($rules);
    }
}
