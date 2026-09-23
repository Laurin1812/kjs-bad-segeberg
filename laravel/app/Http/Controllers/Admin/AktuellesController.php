<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Beitrag;
use App\Models\BeitragKategorie;
use App\Support\AktuellesRules;
use App\Support\BeitragKategorieUpdater;
use App\Support\BeitragUpdater;
use App\Support\ContentVersionConflictException;
use App\Support\ContentVersioning;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * KJS Bad Segeberg - Phase 7C (Admin-Modul "Aktuelles").
 *
 * Zweites echtes Fachmodul im neuen, server-gerenderten Blade-Admin (siehe
 * Phase 7A: Auth/Zugriffsschutz/Admin-Shell; Phase 7B: "Inhalte/Seiten" als
 * erstes Vorbild fuer dieses Modul) - bildet die Beitraege der Rubrik
 * "Aktuelles" ab (Beitrag::where('typ', 'aktuelles')).
 *
 * ABGRENZUNG (Auftrag Teil 16 "NICHT JETZT"): "Beitrag" ist ein von
 * Aktuelles UND Service ("typ" unterscheidet beide, siehe Beitrag-
 * Klassenkommentar) gemeinsam genutztes Model - dieses Modul filtert
 * ausnahmslos auf typ='aktuelles' (siehe ensureAktuelles()) und lehnt jeden
 * Zugriff auf einen Service-Beitrag mit 404 ab, exakt wie InhalteController
 * Hundeausbildung/Kreisjaegermeister-Seiten ablehnt. Termine, Downloads,
 * Partner, Hundeausbildung, Kreisjaegermeister, Hundeboerse-/Waffenboerse-
 * Admin, Kontaktanfragen, Medienmanager, Einstellungen, Benutzer sind KEIN
 * Teil dieses Controllers.
 *
 * SCHREIBLOGIK (Auftrag Teil 2 "keine doppelte Fachlogik"): nutzt fuer das
 * eigentliche Speichern ausschliesslich App\Support\BeitragUpdater -
 * dieselbe Klasse, die (seit Phase 7C) auch Api\Admin\AdminListController::
 * aktuelles() fuer denselben fachlichen Feld-/Relations-Teil nutzt (siehe
 * dortiger Klassenkommentar). Anders als beim Page-Seiten-Modul (Phase 7B)
 * gibt es hier praktisch KEINE "vom Formular nicht verwalteten Felder" -
 * das alte Admin-Formular (admin.js' aktuellesEdit()) zeigt bereits ALLE
 * neun fachlichen Beitrag-Felder in einem einzigen, immer gleichen
 * Formular (kein seitentyp-abhaengiges Ein-/Ausblenden wie bei Page) -
 * trotzdem wird defensiv nach demselben "aktuelle Werte vorbelegen, dann
 * Formular druebermergen"-Muster wie in InhalteController::update()
 * gespeichert (siehe currentFieldValues()), damit ein spaeter hinzukommendes
 * Feld nicht denselben Preservation-Bug wiederholt, der in Phase 7B
 * gefunden wurde.
 *
 * VERSIONIERUNG (Auftrag Teil 9): admin.js' Aktuelles-Schreibweg versioniert
 * NICHT pro Beitrag, sondern fuer die GESAMTE Liste ("aktuelles" ist ein
 * einziger ContentVersioning-Schluessel fuer alle Beitraege + Kategorien +
 * Einstellungen, siehe AdminListController::SECTION_AKTUELLES /
 * ContentVersioning-Aufrufe dort). Dieses Modul nutzt bewusst DENSELBEN
 * Schluessel (nicht einen neuen, feineren pro-Beitrag-Schluessel erfunden -
 * siehe Auftrag "keine neue grosse Architektur bauen"): ein Konflikt hier
 * bedeutet "die Aktuelles-Daten wurden zwischenzeitlich irgendwo anders
 * (JSON-API ODER dieser Blade-Admin) veraendert", exakt wie zuvor.
 *
 * KATEGORIEN (Nachbesserung nach Erstauslieferung - Auftrag Teil 5 "keine
 * Felder/Funktionen erfinden", aber Phase-7C-Ziel ist "Aktuelles
 * VOLLSTAENDIG im neuen Blade-Admin bearbeiten koennen"): admin.js erlaubt
 * im Beitragsformular, neue Kategorien anzulegen bzw. eine ungenutzte
 * wieder zu loeschen (window.aktuellesKategorieAdd/-Delete) - das ist KEIN
 * Feld eines einzelnen Beitrags, sondern eine eigene Verwaltung der
 * modulweiten Kategorie-Liste. kategorieAnlegen()/kategorieLoeschen() unten
 * bilden GENAU diese beiden Aktionen jetzt auch hier ab (bewusst in DIESES
 * Modul integriert statt einer eigenen grossen Verwaltungsseite, siehe
 * Liste unter "Kategorien" auf admin.aktuelles.index) - ueber dieselbe
 * geteilte Schicht App\Support\BeitragKategorieUpdater, die auch die
 * JSON-API (AdminListController::aktuelles()) fuer ihr Get-or-Create pro
 * Beitrag nutzt (siehe dortiger Kommentar). Die "nur loeschen, wenn kein
 * Beitrag sie mehr traegt"-Regel aus admin.js' aktuellesKategorieDelete()
 * wird dabei 1:1 uebernommen (BeitragKategorieUpdater::
 * loeschenWennUngenutzt()), nicht neu erfunden. Das Beitragsformular selbst
 * zeigt weiterhin nur die Auswahl einer bereits bestehenden Kategorie
 * (Dropdown) - Anlegen/Loeschen passiert bewusst zentral in der Liste,
 * nicht pro-Formular dupliziert.
 */
class AktuellesController extends Controller
{
    private const TYP = 'aktuelles';

    public function index(): View
    {
        $beitraege = AktuellesRules::sortiertNeuesteZuerst(
            Beitrag::where('typ', self::TYP)->with('kategorie')->get()
        );

        return view('admin.aktuelles.index', [
            'beitraege' => $beitraege,
            'kategorien' => $this->kategorien()->loadCount('beitraege'),
            'currentVersion' => ContentVersioning::current(self::TYP),
        ]);
    }

    public function neu(): View
    {
        $beitrag = new Beitrag([
            'typ' => self::TYP,
            'jahr' => (int) now()->format('Y'),
            'archiviert' => false,
        ]);

        return view('admin.aktuelles.bearbeiten', [
            'beitrag' => $beitrag,
            'istNeu' => true,
            'kategorien' => $this->kategorien(),
            'previewUrl' => null,
            'currentVersion' => ContentVersioning::current(self::TYP),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateData($request);

        $expectedVersion = $request->filled('expected_version') ? (int) $request->input('expected_version') : null;

        try {
            $beitrag = DB::transaction(function () use ($validated, $expectedVersion) {
                ContentVersioning::assertNotStale(self::TYP, $expectedVersion);

                $legacyIndex = BeitragUpdater::nextLegacyIndex(self::TYP);
                $slug = BeitragUpdater::generateUniqueSlug(self::TYP, $validated['titel'], $legacyIndex);
                $beitrag = new Beitrag(['typ' => self::TYP, 'slug' => $slug, 'legacy_index' => $legacyIndex]);
                BeitragUpdater::applyFields($beitrag, $validated);

                ContentVersioning::bump(self::TYP);

                return $beitrag;
            });
        } catch (ContentVersionConflictException) {
            return back()->withInput()->withErrors([
                'titel' => 'Die Aktuelles-Daten wurden zwischenzeitlich an anderer Stelle gespeichert. Bitte die Seite neu laden und Ihre Änderungen erneut eintragen.',
            ]);
        }

        return redirect()->route('admin.aktuelles.bearbeiten', $beitrag)->with('status', 'Beitrag angelegt.');
    }

    public function edit(Beitrag $beitrag): View
    {
        $this->ensureAktuelles($beitrag);
        $beitrag->load(['downloads' => fn ($q) => $q->orderBy('sortierung'), 'galerieBilder' => fn ($q) => $q->orderBy('sortierung')]);

        return view('admin.aktuelles.bearbeiten', [
            'beitrag' => $beitrag,
            'istNeu' => false,
            'kategorien' => $this->kategorien(),
            'previewUrl' => route('aktuelles.show', $beitrag->slug),
            'currentVersion' => ContentVersioning::current(self::TYP),
        ]);
    }

    public function update(Request $request, Beitrag $beitrag): RedirectResponse
    {
        $this->ensureAktuelles($beitrag);

        $validated = $this->validateData($request);

        // Preservation (siehe Klassenkommentar): aktuelle Werte vorbelegen,
        // das Formular ueberschreibt nur, was es tatsaechlich sendet -
        // strukturell identisch zu InhalteController::update()'s
        // currentFieldValues()-Muster (Phase 7B), auch wenn admin.js'
        // Formular fuer Aktuelles heute bereits alle Felder zeigt und diese
        // Vorbelegung deshalb aktuell keinen Unterschied macht.
        $data = array_merge($this->currentFieldValues($beitrag), $validated);

        $expectedVersion = $request->filled('expected_version') ? (int) $request->input('expected_version') : null;

        try {
            DB::transaction(function () use ($beitrag, $data, $expectedVersion) {
                ContentVersioning::assertNotStale(self::TYP, $expectedVersion);
                BeitragUpdater::applyFields($beitrag, $data);
                ContentVersioning::bump(self::TYP);
            });
        } catch (ContentVersionConflictException) {
            return back()->withInput()->withErrors([
                'titel' => 'Die Aktuelles-Daten wurden zwischenzeitlich an anderer Stelle gespeichert. Bitte die Seite neu laden und Ihre Änderungen erneut eintragen.',
            ]);
        }

        return redirect()->route('admin.aktuelles.bearbeiten', $beitrag)->with('status', 'Beitrag gespeichert.');
    }

    public function destroy(Request $request, Beitrag $beitrag): RedirectResponse
    {
        $this->ensureAktuelles($beitrag);

        $expectedVersion = $request->filled('expected_version') ? (int) $request->input('expected_version') : null;

        try {
            DB::transaction(function () use ($beitrag, $expectedVersion) {
                ContentVersioning::assertNotStale(self::TYP, $expectedVersion);

                // Eingebettete Downloads/Galerie mit loeschen - dasselbe
                // Verhalten wie ein Beitrag, der beim admin.js-Vollspeichern
                // aus dem Payload verschwindet (siehe AdminListController::
                // aktuelles()' "Beitrag::whereNotIn('id', $keepIds)->delete()"
                // - dort bleiben Downloads/Galerie bereits heute als
                // verwaiste Zeilen zurueck, siehe Analysebericht). Hier
                // raeumen wir sie zusaetzlich explizit mit auf, da ein
                // dedizierter Loeschen-Knopf (anders als das implizite
                // Verschwinden aus einem Vollspeichern) die bewusste
                // Erwartung "dieser Beitrag ist wirklich weg" weckt.
                BeitragUpdater::replaceEmbeddedDownloads($beitrag, []);
                BeitragUpdater::replaceEmbeddedGalerie($beitrag, []);
                $beitrag->delete();

                ContentVersioning::bump(self::TYP);
            });
        } catch (ContentVersionConflictException) {
            return back()->withErrors([
                'titel' => 'Die Aktuelles-Daten wurden zwischenzeitlich an anderer Stelle gespeichert. Bitte die Seite neu laden und erneut versuchen.',
            ]);
        }

        return redirect()->route('admin.aktuelles.index')->with('status', 'Beitrag gelöscht.');
    }

    /** @return array<string, mixed> */
    private function validateData(Request $request): array
    {
        $request->validate([
            'titel' => ['required', 'string', 'max:190'],
            'datum' => ['nullable', 'date'],
            'jahr' => ['nullable', 'integer', 'min:1900', 'max:2200'],
            'kategorie_id' => ['nullable', 'integer', Rule::exists('beitrag_kategorien', 'id')->where('typ', self::TYP)],
        ], [
            'titel.required' => 'Bitte einen Titel angeben.',
            'datum.date' => 'Bitte ein gültiges Datum angeben.',
            'kategorie_id.exists' => 'Bitte eine gültige Kategorie auswählen.',
        ]);

        $data = $request->only(['titel', 'datum', 'jahr', 'kategorie_id', 'bild', 'text', 'link', 'galerie_titel']);
        $data['archiviert'] = $request->boolean('archiviert');

        // Nachbesserung nach Erstauslieferung (Preservation-Bug): "downloads"/
        // "galerie" nur dann ueberhaupt in $data aufnehmen, wenn das
        // Formular den jeweiligen Schluessel tatsaechlich gesendet hat -
        // $request->has() unterscheidet sauber "Schluessel fehlt komplett"
        // (BeitragUpdater::applyFields() fasst die Relation dann gar nicht
        // an, bestehende Zeilen bleiben erhalten) von "Schluessel vorhanden,
        // aber eine leere Liste" (bewusstes Leeren). Das echte Blade-
        // Formular rendert beide Bereiche zwar unconditional vorbelegt
        // (siehe bearbeiten.blade.php) und sendet sie deshalb im
        // Normalfall immer mit - dieser Unterschied schuetzt trotzdem vor
        // jedem Aufrufer/Request, der diese Bereiche gar nicht mit editiert
        // (z.B. ein zukuenftig schlankeres Formular oder ein manueller
        // Teil-Request), siehe BeitragUpdater::applyFields()-Kommentar.
        if ($request->has('downloads')) {
            $data['downloads'] = $this->filterRows($request->input('downloads'), ['titel', 'datei', 'vorschau']);
        }
        if ($request->has('galerie')) {
            $data['galerie'] = $this->filterRows($request->input('galerie'), ['bild', 'titel']);
        }

        return $data;
    }

    /**
     * Aktuelle Werte aller von BeitragUpdater::applyFields() verwalteten
     * Skalarfelder (nicht: downloads/galerie - die werden in update() immer
     * unbedingt aus dem Formular gesetzt, siehe validateData(), da beide
     * Bereiche im Formular unconditional gerendert werden, genau wie im
     * Alt-Admin). Siehe Klassenkommentar "Preservation".
     *
     * @return array<string, mixed>
     */
    private function currentFieldValues(Beitrag $beitrag): array
    {
        return [
            'titel' => $beitrag->titel,
            'datum' => $beitrag->datum?->toDateString(),
            'jahr' => $beitrag->jahr,
            'kategorie_id' => $beitrag->kategorie_id,
            'bild' => $beitrag->bild,
            'text' => $beitrag->text,
            'link' => $beitrag->link,
            'galerie_titel' => $beitrag->galerie_titel,
            'archiviert' => $beitrag->archiviert,
        ];
    }

    /** @return Collection<int, BeitragKategorie> */
    private function kategorien()
    {
        return BeitragKategorie::where('typ', self::TYP)->orderBy('sortierung')->get();
    }

    /**
     * "+ Neu" (siehe Klassenkommentar "Kategorien") - Get-or-Create ueber
     * die geteilte Schicht, damit ein bereits vorhandener Name (admin.js'
     * eigenes String-Dedup) hier ebenso idempotent bleibt: kein Fehler,
     * einfach dieselbe bestehende Kategorie, exakt wie im Alt-Admin.
     */
    public function kategorieAnlegen(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:190'],
        ], [
            'name.required' => 'Bitte einen Namen für die neue Kategorie angeben.',
        ]);

        $expectedVersion = $request->filled('expected_version') ? (int) $request->input('expected_version') : null;
        $name = trim((string) $request->input('name'));

        try {
            DB::transaction(function () use ($name, $expectedVersion) {
                ContentVersioning::assertNotStale(self::TYP, $expectedVersion);
                BeitragKategorieUpdater::ensure(self::TYP, $name);
                ContentVersioning::bump(self::TYP);
            });
        } catch (ContentVersionConflictException) {
            return redirect()->route('admin.aktuelles.index')->with('kategorie_fehler', 'Die Aktuelles-Daten wurden zwischenzeitlich an anderer Stelle gespeichert. Bitte die Seite neu laden und erneut versuchen.');
        }

        return redirect()->route('admin.aktuelles.index')->with('kategorie_status', 'Kategorie „'.$name.'" angelegt.');
    }

    /**
     * "🗑" (siehe Klassenkommentar "Kategorien") - loescht nur, wenn kein
     * Beitrag diese Kategorie mehr traegt (BeitragKategorieUpdater::
     * loeschenWennUngenutzt()), sonst eine verstaendliche deutsche Meldung
     * mit der Anzahl betroffener Beitraege statt eines stillen Fehlschlags.
     */
    public function kategorieLoeschen(Request $request, BeitragKategorie $kategorie): RedirectResponse
    {
        abort_unless($kategorie->typ === self::TYP, 404);

        $expectedVersion = $request->filled('expected_version') ? (int) $request->input('expected_version') : null;

        try {
            $geloescht = DB::transaction(function () use ($kategorie, $expectedVersion) {
                ContentVersioning::assertNotStale(self::TYP, $expectedVersion);
                $ok = BeitragKategorieUpdater::loeschenWennUngenutzt($kategorie);
                if ($ok) {
                    ContentVersioning::bump(self::TYP);
                }

                return $ok;
            });
        } catch (ContentVersionConflictException) {
            return redirect()->route('admin.aktuelles.index')->with('kategorie_fehler', 'Die Aktuelles-Daten wurden zwischenzeitlich an anderer Stelle gespeichert. Bitte die Seite neu laden und erneut versuchen.');
        }

        if (! $geloescht) {
            $anzahl = BeitragKategorieUpdater::anzahlVerwendungen($kategorie);

            return redirect()->route('admin.aktuelles.index')->with('kategorie_fehler', 'Kategorie „'.$kategorie->name.'" wird noch von '.$anzahl.' Beitrag(en) verwendet und kann nicht gelöscht werden. Bitte dort zuerst eine andere Kategorie wählen.');
        }

        return redirect()->route('admin.aktuelles.index')->with('kategorie_status', 'Kategorie „'.$kategorie->name.'" gelöscht.');
    }

    private function ensureAktuelles(Beitrag $beitrag): void
    {
        abort_unless($beitrag->typ === self::TYP, 404);
    }

    /**
     * Identisch zu InhalteController::filterRows() (Phase 7B) - bewusst eine
     * eigene, kleine Kopie statt eines geteilten Helfers: reines,
     * fachlogikfreies Eingabe-Trimmen (kein Feld-Mapping, keine Regel), das
     * Risiko einer abweichenden Interpretation zwischen Page- und
     * Beitrag-Feldern gibt es hier nicht.
     *
     * @param  list<string>  $keys
     * @return list<array<string, string>>
     */
    private function filterRows(mixed $rows, array $keys): array
    {
        if (! is_array($rows)) {
            return [];
        }
        $result = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $eintrag = [];
            foreach ($keys as $key) {
                $eintrag[$key] = trim((string) ($row[$key] ?? ''));
            }
            $result[] = $eintrag;
        }

        return $result;
    }
}
