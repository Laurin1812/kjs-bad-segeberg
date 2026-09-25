<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ContentVersionConflictException;
use App\Support\ContentVersioning;
use App\Support\EinstellungenUpdater;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * KJS Bad Segeberg - Phase 7L (Admin-Modul "Einstellungen"), Teil A.
 *
 * Elftes echtes Fachmodul im neuen, server-gerenderten Blade-Admin - erster
 * der beiden letzten Sidebar-Platzhalter ("Einstellungen"/"Benutzer", siehe
 * Auftrag). Bildet EIN Formular pro bereits bestehender, rein skalarer
 * "settings"-Gruppe ab (siehe App\Support\EinstellungenUpdater-
 * Klassenkommentar fuer die vollstaendige Analyse/Abgrenzung): "Darstellung"
 * (Gruppe "design"), "Kontakt & Stammdaten" (Gruppe "einstellungen"),
 * "Footer & Social" (Gruppe "footer") und "Impressum" (Gruppe "impressum").
 * "navigation"/"navigation_extra" (Drag&Drop-Reihenfolge) bleiben bewusst
 * ausserhalb dieser Phase (siehe EinstellungenUpdater-Klassenkommentar).
 *
 * Eine URL-Kurzform ("darstellung"/"kontakt"/"footer"/"impressum") statt der
 * internen "gruppe"-Werte, nur damit "admin/einstellungen/einstellungen"
 * (Gruppe "einstellungen" waere sonst gleichlautend mit dem Seiten-Praefix)
 * nicht verwirrend doppelt im Pfad auftaucht - die Zuordnung ist rein
 * kosmetisch, das darunterliegende Datenmodell/die "gruppe"-Spalte bleibt
 * unveraendert.
 *
 * SCHREIBLOGIK: siehe EinstellungenUpdater (keine doppelte Fachlogik hier im
 * Controller, identisch zum etablierten Partner-/Termine-Muster).
 *
 * VIER unabhaengige <form>-Bloecke auf EINER Seite statt vier getrennter
 * Sidebar-Unterseiten (Auftrag: "Settings sinnvoll gruppieren") - jede
 * Gruppe versioniert trotzdem UNABHAENGIG (eigener ContentVersioning-
 * Schluessel je Gruppe, siehe EinstellungenUpdater-Klassenkommentar), ein
 * Speichern-Konflikt in einer Gruppe blockiert also nicht das Speichern
 * einer anderen.
 */
class EinstellungenController extends Controller
{
    /** @var array<string, string> URL-Kurzform => interner "gruppe"-Wert (siehe Klassenkommentar). */
    private const SLUG_ZU_GRUPPE = [
        'darstellung' => 'design',
        'kontakt' => 'einstellungen',
        'footer' => 'footer',
        'impressum' => 'impressum',
    ];

    public function index(): View
    {
        $gruppen = [];
        foreach (self::SLUG_ZU_GRUPPE as $slug => $gruppe) {
            $gruppen[$slug] = [
                'werte' => EinstellungenUpdater::currentValues($gruppe),
                'version' => ContentVersioning::current($gruppe),
            ];
        }
        $gruppen['kontakt']['oeffnungszeitenText'] = EinstellungenUpdater::currentOeffnungszeitenText();

        return view('admin.einstellungen.index', ['gruppen' => $gruppen]);
    }

    public function update(Request $request, string $slug): RedirectResponse
    {
        abort_unless(array_key_exists($slug, self::SLUG_ZU_GRUPPE), 404);
        $gruppe = self::SLUG_ZU_GRUPPE[$slug];

        $validated = $this->validateData($request, $gruppe, $slug);
        // Preservation (siehe EinstellungenUpdater-Klassenkommentar sowie
        // identisches Prinzip bei PartnerController::update()): aktuelle
        // Werte vorbelegen, das Formular ueberschreibt nur, was es
        // tatsaechlich sendet - ein im Payload fehlender bekannter
        // Schluessel behaelt so seinen bisherigen Wert statt geloescht zu
        // werden.
        $data = array_merge(EinstellungenUpdater::currentValues($gruppe), $validated);

        $expectedVersion = $request->filled('expected_version') ? (int) $request->input('expected_version') : null;

        try {
            DB::transaction(function () use ($gruppe, $slug, $data, $request, $expectedVersion) {
                ContentVersioning::assertNotStale($gruppe, $expectedVersion);
                EinstellungenUpdater::apply($gruppe, $data);
                // Preservation gilt auch fuer "oeffnungszeiten" (siehe
                // Kommentar oben): nur bei tatsaechlich gesendetem Feld
                // ueberschreiben, ein weggelassenes Feld (Teil-Request)
                // loescht die bestehenden Sprechzeiten nicht.
                if ($slug === 'kontakt' && $request->has('oeffnungszeiten_text')) {
                    EinstellungenUpdater::applyOeffnungszeiten((string) $request->input('oeffnungszeiten_text', ''));
                }
                ContentVersioning::bump($gruppe);
            });
        } catch (ContentVersionConflictException) {
            return back()->withInput()->withErrors([
                'name' => 'Diese Einstellungen wurden zwischenzeitlich an anderer Stelle gespeichert. Bitte die Seite neu laden und Ihre Änderungen erneut eintragen.',
            ]);
        }

        return redirect()->route('admin.einstellungen.index')->with('status', 'Einstellungen gespeichert.');
    }

    /**
     * Serverseitige Allowlist (Auftrag Teil A Punkt 4 "keine unbekannten
     * Keys aus Requests akzeptieren"): $request->only() nimmt ausschliesslich
     * die hier bekannten Schluessel auf, jeder andere Request-Parameter
     * (egal ob harmlos oder absichtlich manipuliert) wird stillschweigend
     * ignoriert, nie gespeichert.
     *
     * @return array<string, string>
     */
    private function validateData(Request $request, string $gruppe, string $slug): array
    {
        $keys = EinstellungenUpdater::allowedKeys($gruppe);
        $rules = array_fill_keys($keys, ['nullable', 'string', 'max:2000']);
        if ($slug === 'kontakt') {
            $rules['oeffnungszeiten_text'] = ['nullable', 'string', 'max:4000'];
        }
        $request->validate($rules);

        return $request->only($keys);
    }
}
