<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\ContentVersionConflictException;
use App\Support\ContentVersioning;
use App\Support\KjsPagesConfig;
use App\Support\PagePermissions;
use App\Support\PageUpdater;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * KJS Bad Segeberg - Phase 7B (Admin-Modul "Inhalte / Seiten").
 *
 * Erstes echtes Fachmodul im neuen, server-gerenderten Blade-Admin (siehe
 * Phase 7A: Auth/Zugriffsschutz/Admin-Shell) - bildet GENAU den Ausschnitt
 * der "seiten-*"-Registry-Familie ab, den Api\Admin\AdminPageController
 * bereits ueber die bestehende JSON-Schreib-API (admin.js) verwaltet:
 *
 *   - section IN [jaeger, aufgaben, verbraucher]: feste Vorlagen-Seiten
 *     (KjsPagesConfig::fixedSlugs()) + per Registry hinzugefuegte
 *     Zusatzseiten + deren Unterseiten (parent_id).
 *   - section = weitere: "Weitere Themen"-Seiten (immer flach, kein
 *     fixedSlugs-Konzept).
 *
 * Bewusst AUSSERHALB dieses Moduls (Auftrag Teil 3 "Sondermodule nicht in
 * dieses Modul hineinziehen" / Teil 11 "NICHT JETZT: Hundeausbildung"):
 *   - section = hundeausbildung (Hub + Kurse): eigenes, im Auftrag explizit
 *     ausgeschlossenes Modul, obwohl es technisch derselben Page-Familie
 *     angehoert und ueber denselben AdminPageController/dieselbe
 *     PagePermissions-Logik laeuft.
 *   - section = kreisjaegermeister: zwar ebenfalls eine Page-Zeile
 *     (Singleton), wird aber NICHT ueber AdminPageController/
 *     identity.page_permission verwaltet, sondern als eigenstaendiges
 *     "Liste"-Modul ueber Api\Admin\AdminListController::kreisjaegermeister()
 *     mit einem eigenen, modulweiten Recht ("identity.permission:kjm") - ein
 *     strukturell anderer Verwaltungsweg, der hier bewusst nicht mit
 *     hineingezogen wird (siehe Abschlussbericht Punkt 6).
 *
 * SCHREIBLOGIK (Auftrag Teil 4): nutzt fuer das eigentliche Speichern
 * ausschliesslich App\Support\PageUpdater::saveWithVersionCheck() - exakt
 * dieselbe Methode, die (seit Phase 7B) auch Api\Admin\AdminPageController
 * fuer denselben Zweck aufruft. Keine zweite Validierungs-/Update-Schicht.
 * Die einzige NEUE Regel hier (siehe update()) ist eine serverseitige
 * Mindestpruefung "Seitentitel darf nicht leer sein" fuer die Blade-Ansicht
 * (die JSON-API hat dafuer bislang GAR KEINE Update-Validierung - siehe
 * Analysebericht/PageUpdater::applyFields()) - eine Ergaenzung, kein
 * Duplikat einer bestehenden Regel.
 *
 * BERECHTIGUNG (Auftrag Teil 5): aktuell reicht die bereits in Phase 7A
 * eingefuehrte 'admin.web'-Middleware (siehe routes/web.php) - jeder Zugriff
 * auf /admin/* verlangt bereits eine Admin-Rolle, Nicht-Admins werden vorher
 * durch EnsureAdminWebSession abgewiesen (403). App\Support\PagePermissions
 * (die bestehende, granulare Pro-Seite-Rechtepruefung aus der JSON-API) wird
 * hier bewusst NICHT zusaetzlich aufgerufen, weil sie fuer jeden Admin ohnehin
 * true liefert (PagePermissions::userMayAccess() hat einen Admin-Bypass) und
 * fuer jeden Nicht-Admin nie erreicht wird. resolvePermissionKey() unten
 * bildet trotzdem schon die "kind"-Aufloesung nach demselben Muster wie
 * Http\Middleware\EnsurePagePermission nach - das ist die vorgesehene
 * Erweiterungsstelle fuer Phase 8 (Redakteure/Sub-Admin-Rechte), wenn diese
 * Methode tatsaechlich gegen PagePermissions::userMayAccess() geprueft
 * werden soll, statt nur zu dokumentieren, WELCHES Recht zustaendig waere.
 */
class InhalteController extends Controller
{
    /** @var list<string> */
    private const IN_SCOPE_SECTIONS = ['jaeger', 'aufgaben', 'verbraucher', 'weitere'];

    public function index(): View
    {
        $seiten = Page::whereIn('section', self::IN_SCOPE_SECTIONS)
            ->orderBy('sortierung')
            ->orderBy('titel')
            ->get();

        $gruppen = [];
        foreach (self::IN_SCOPE_SECTIONS as $section) {
            $inSection = $seiten->where('section', $section);
            $topLevel = $inSection->whereNull('parent_id');
            if ($topLevel->isEmpty()) {
                continue;
            }
            $zeilen = [];
            foreach ($topLevel as $top) {
                $zeilen[] = $this->listenZeile($top, false);
                foreach ($inSection->where('parent_id', $top->id) as $child) {
                    $zeilen[] = $this->listenZeile($child, true);
                }
            }
            $gruppen[] = ['bereich' => $this->bereich($section), 'zeilen' => $zeilen];
        }

        return view('admin.inhalte.index', ['gruppen' => $gruppen]);
    }

    public function edit(Page $page): View
    {
        $this->ensureInScope($page);
        $page->load([
            'parent',
            'downloads' => fn ($q) => $q->orderBy('sortierung'),
            'galerieBilder' => fn ($q) => $q->orderBy('sortierung'),
            'links' => fn ($q) => $q->orderBy('sortierung'),
        ]);

        return view('admin.inhalte.bearbeiten', [
            'page' => $page,
            'seitentyp' => $this->seitentyp($page),
            'bereich' => $this->bereich($page->section),
            'istDynamisch' => ! $this->istFesteSeite($page),
            'hatUnterseitenSystem' => $page->parent_id === null,
            'zeigeAntragUrl' => $page->slug === 'mitglied-werden',
            'zeigeHundeboerseCta' => $page->slug === 'hundevermittlung',
            'previewUrl' => $this->publicUrl($page),
            'currentVersion' => ContentVersioning::current(PageUpdater::versionSectionFor($page)),
        ]);
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $this->ensureInScope($page);

        $request->validate([
            'titel' => ['required', 'string', 'max:200'],
            'kontakt_email' => ['nullable', 'email', 'max:190'],
        ], [
            'titel.required' => 'Bitte einen Seitentitel angeben.',
            'kontakt_email.email' => 'Bitte eine gültige E-Mail-Adresse angeben.',
        ]);

        // Preservation-Fix: dieses Blade-Formular verwaltet nur einen TEIL der
        // Felder, die PageUpdater::applyFields() kennt (anders als admin.js'
        // collectStandard(), das immer den kompletten Datensatz sendet). Wuerde
        // man hier nur die vom Formular gesendeten Felder an applyFields()
        // durchreichen, wuerden alle hier nicht dargestellten Felder (z.B.
        // kontakt_telefon, vorschaubild, bild_flat, in_navigation,
        // veroeffentlicht) durch applyFields()' Default-Werte stillschweigend
        // ueberschrieben - genau der vom Nutzer gemeldete Datenverlust-Bug.
        //
        // Fix: $data wird zuerst mit den AKTUELLEN Page-Werten fuer JEDES von
        // applyFields() verwaltete Skalarfeld vorbelegt (siehe
        // currentFieldValues()), und erst DANACH werden die tatsaechlich vom
        // Formular gesendeten Felder druebergelegt. Fehlt ein Formularfeld,
        // weil es fuer diesen Seitentyp gar nicht gerendert wird (z.B.
        // antrag_url ausserhalb von "mitglied-werden"), bleibt der bestehende
        // Page-Wert unveraendert erhalten. Keine zweite Schreiblogik -
        // PageUpdater/AdminPageController bleiben unangetastet.
        $data = array_merge($this->currentFieldValues($page), $request->only([
            'titel', 'nav_label', 'untertitel', 'intro', 'inhalt',
            'hero_bild', 'bild', 'bild_alt', 'bild_groesse',
            'kontakt_name', 'kontakt_email',
            'unterseiten_titel', 'linkliste_titel', 'galerie_titel',
            'antrag_url', 'hundeboerse_cta_titel', 'hundeboerse_cta_text', 'hundeboerse_cta_button',
        ]));
        $data['downloads'] = $this->filterRows($request->input('downloads', []), ['titel', 'datei', 'vorschau']);
        $data['galerie'] = $this->filterRows($request->input('galerie', []), ['bild', 'titel']);

        // linkliste (samt linkliste_titel oben) wird nur dargestellt, wenn die
        // Seite ein Unterseiten-System hat (siehe edit(), $hatUnterseitenSystem
        // == $page->parent_id === null). PageUpdater::replacePageLinks()
        // loescht aber IMMER zuerst alle bestehenden Links, bevor es (falls
        // $items ein Array ist) neue anlegt - fuer Seiten ohne dieses Formular-
        // Feld wird die bestehende Linkliste deshalb unveraendert zurueck in
        // den Payload gegeben, statt eines leeren Arrays (das haette
        // bestehende Links geloescht, obwohl die UI sie nie gezeigt hat).
        if ($page->parent_id === null) {
            $data['linkliste'] = $this->filterRows($request->input('linkliste', []), ['url', 'titel']);
        } else {
            $page->loadMissing('links');
            $data['linkliste'] = $page->links
                ->sortBy('sortierung')
                ->map(fn ($link) => ['url' => $link->href, 'titel' => $link->label])
                ->values()
                ->all();
        }

        $expectedVersion = $request->filled('expected_version') ? (int) $request->input('expected_version') : null;

        try {
            PageUpdater::saveWithVersionCheck($page, PageUpdater::versionSectionFor($page), $data, $expectedVersion);
        } catch (ContentVersionConflictException) {
            return back()->withInput()->withErrors([
                'titel' => 'Diese Seite wurde zwischenzeitlich an anderer Stelle gespeichert. Bitte die Seite neu laden und Ihre Änderungen erneut eintragen.',
            ]);
        }

        return redirect()->route('admin.inhalte.bearbeiten', $page)->with('status', 'Seite gespeichert.');
    }

    /**
     * Aktuelle Werte aller von PageUpdater::applyFields() verwalteten
     * Skalarfelder (nicht: downloads/galerie/linkliste - die werden in
     * update() gesondert behandelt, siehe dortiger Kommentar). Dient als
     * Vorbelegung fuer das nur teilweise verwaltende Blade-Formular, damit
     * ein partielles Speichern keine vom Formular nicht dargestellten Felder
     * auf Default-Werte zuruecksetzt.
     *
     * @return array<string, mixed>
     */
    private function currentFieldValues(Page $page): array
    {
        return [
            'titel' => $page->titel,
            'untertitel' => $page->untertitel,
            'nav_label' => $page->nav_label,
            'intro' => $page->intro,
            'inhalt' => $page->inhalt,
            'hero_bild' => $page->hero_bild,
            'bild' => $page->bild,
            'bild_alt' => $page->bild_alt,
            'vorschaubild' => $page->vorschaubild,
            'kurzbeschreibung' => $page->kurzbeschreibung,
            'bild_groesse' => $page->bild_groesse,
            'bild_flat' => $page->bild_flat,
            'kontakt_name' => $page->kontakt_name,
            'kontakt_email' => $page->kontakt_email,
            'kontakt_telefon' => $page->kontakt_telefon,
            'antrag_url' => $page->antrag_url,
            'unterseiten_titel' => $page->unterseiten_titel,
            'galerie_titel' => $page->galerie_titel,
            'gruppe' => $page->gruppe,
            'linkliste_titel' => $page->linkliste_titel,
            'hundeboerse_cta_titel' => $page->hundeboerse_cta_titel,
            'hundeboerse_cta_text' => $page->hundeboerse_cta_text,
            'hundeboerse_cta_button' => $page->hundeboerse_cta_button,
            'in_navigation' => $page->in_navigation,
            'veroeffentlicht' => $page->veroeffentlicht,
        ];
    }

    /** @return array{page: Page, istUnterseite: bool, typ: string, url: ?string} */
    private function listenZeile(Page $page, bool $istUnterseite): array
    {
        return [
            'page' => $page,
            'istUnterseite' => $istUnterseite,
            'typ' => $this->seitentyp($page),
            'url' => $this->publicUrl($page),
        ];
    }

    private function ensureInScope(Page $page): void
    {
        abort_unless(in_array($page->section, self::IN_SCOPE_SECTIONS, true), 404);
    }

    private function istFesteSeite(Page $page): bool
    {
        return $page->parent_id === null
            && in_array($page->section, KjsPagesConfig::familySections(), true)
            && in_array($page->slug, KjsPagesConfig::fixedSlugs($page->section), true);
    }

    /**
     * Welches PagePermissions-Recht fuer eine Page-Zeile zustaendig waere -
     * dieselbe "kind"-Aufloesung wie Http\Middleware\EnsurePagePermission
     * (siehe dortiges match()), nur ausgehend von einer bereits geladenen
     * Page statt URL-Segmenten. AKTUELL ungenutzt/nicht scharf geschaltet
     * (siehe Klassenkommentar "BERECHTIGUNG") - die vorgesehene
     * Erweiterungsstelle fuer Phase 8, wenn diese Methode tatsaechlich gegen
     * PagePermissions::userMayAccess() geprueft werden soll.
     */
    private function resolvePermissionKey(Page $page): ?string
    {
        if ($page->parent_id !== null) {
            $parent = $page->relationLoaded('parent') ? $page->parent : Page::find($page->parent_id);

            return $parent ? PagePermissions::forSubSeite($parent->slug) : null;
        }
        if ($page->section === 'weitere') {
            return PagePermissions::forWeitereSeite();
        }
        if ($this->istFesteSeite($page)) {
            return PagePermissions::forFesteSeite($page->section, $page->slug);
        }

        return PagePermissions::forRegistrierteSeite($page->section);
    }

    private function seitentyp(Page $page): string
    {
        if ($page->parent_id !== null) {
            return 'Unterseite';
        }
        if ($page->section === 'weitere') {
            return 'Weitere-Themen-Seite';
        }

        return $this->istFesteSeite($page) ? 'Feste Seite' : 'Registry-Zusatzseite';
    }

    private function bereich(string $section): string
    {
        return match ($section) {
            'jaeger' => 'Jäger',
            'aufgaben' => 'Aufgaben',
            'verbraucher' => 'Verbraucher & Natur',
            'weitere' => 'Weitere Themen',
            default => $section,
        };
    }

    /**
     * Echte, bereits registrierte oeffentliche Route fuer eine Page-Zeile -
     * siehe routes/web.php ("/{section}/{slug}", "/weitere/{slug}",
     * "/{section}/{parentSlug}/{childSlug}"). Bewusst KEINE geratenen URLs:
     * liefert null, wenn keine dieser drei bestehenden Routen zutrifft
     * (Auftrag Teil 7 "Nur echte vorhandene Route verwenden").
     */
    private function publicUrl(Page $page): ?string
    {
        if ($page->parent_id !== null) {
            $parent = $page->relationLoaded('parent') ? $page->parent : Page::find($page->parent_id);
            if (! $parent) {
                return null;
            }

            return url($page->section.'/'.$parent->slug.'/'.$page->slug);
        }
        if ($page->section === 'weitere') {
            return url('weitere/'.$page->slug);
        }
        if (in_array($page->section, ['jaeger', 'aufgaben', 'verbraucher'], true)) {
            return url($page->section.'/'.$page->slug);
        }

        return null;
    }

    /**
     * Filtert eine Liste eingebetteter Zeilen (Downloads/Galerie/Linkliste)
     * auf die vorgegebenen Schluessel - PageUpdater selbst verwirft dann
     * noch leere Zeilen (siehe dortige replaceEmbedded*()-Methoden), diese
     * Methode sorgt nur dafuer, dass ausschliesslich bekannte Schluessel
     * durchgereicht werden.
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
