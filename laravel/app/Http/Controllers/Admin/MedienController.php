<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MedienArchivEintrag;
use App\Models\MedienEintrag;
use App\Support\AdminIdentity;
use App\Support\MediaLibrary;
use App\Support\MediaReferencedException;
use App\Support\MediaServiceUnavailableException;
use App\Support\MediaStorage;
use App\Support\MediaUploadService;
use App\Support\MediaValidationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * KJS Bad Segeberg - Phase 7K (Admin-Modul "Medien").
 *
 * ANALYSE-ERGEBNIS (Auftrag: vor der Umsetzung pruefen, ob es bereits eine
 * echte zentrale Medienbibliothek gibt oder nur modulbezogene Dateien):
 * es GIBT bereits eine echte, generische, authentifizierte Medienbibliothek
 * (Phase 5B.1/5B.2) - "medien"-Tabelle (App\Models\MedienEintrag), "medien_
 * archiv"-Tabelle (App\Models\MedienArchivEintrag, bislang ohne Konsument),
 * zentrale Pfad-/URL-Logik (App\Support\MediaStorage), Upload/Loeschen inkl.
 * Referenzpruefung vor dem Loeschen (App\Support\MediaUploadService/
 * MediaReferenceScanner) und ein Dateisystem-Scan fuer die ca. 250
 * historischen Bilder/PDFs ohne DB-Zeile. Siehe App\Support\MediaLibrary
 * fuer die vollstaendige Herleitung/Begruendung ("Fall A": bestehende
 * Datenquelle verwenden, keine neue zweite Medienhaltung) und dafuer, warum
 * diese Klasse bewusst NICHT die bestehende JSON-API (Api\Admin\
 * AdminMediaController, weiterhin vom alten admin.js genutzt) umbaut,
 * sondern denselben Unterbau (MediaStorage/MediaUploadService/
 * MedienEintrag/MedienArchivEintrag) ueber eine eigene kleine Lese-Klasse
 * ein zweites Mal konsumiert.
 *
 * SCOPE (1:1 wie im alten admin.js' "🖼️ Medien & Bilder"-Panel, ERWEITERT
 * um die dort nie eigenstaendig verwaltbaren PDFs, da AdminMediaController
 * beide Typen ohnehin schon einheitlich behandelt - siehe Auftrag Punkt 5
 * "Filter Alle/Bilder/Dateien"): Uebersicht aller Bilder+PDFs mit Vorschau,
 * Hochladen (Bild ODER PDF), Loeschen (mit Referenzpruefung, siehe unten)
 * und Archivieren/Wiederherstellen (NUR fuer Bilder - 1:1 wie im Altsystem,
 * das kannte nie eine PDF-Archivierung). KEIN Digital-Asset-Management-
 * System (keine Tags/Ordner/Volltextsuche/Bildbearbeitung - Auftrag "kein
 * grosses DAM-System bauen").
 *
 * "Modul/Herkunft"-Filter (Auftrag Punkt 5) bewusst NICHT umgesetzt: alle
 * hier gelisteten Dateien kommen einheitlich aus GENAU zwei Verzeichnissen
 * (images/, downloads/ - siehe config/kjs_media.php) unter derselben
 * zentralen Verwaltung - es gibt keine zweite "Herkunft" zu unterscheiden.
 * Hundeboerse-/Waffenboerse-Bilder liegen bewusst in einem voellig
 * getrennten Verzeichnis (public/uploads/boersen/<modul>/, siehe
 * App\Support\BoerseUploads) und tauchen hier folgerichtig gar nicht erst
 * auf - dieselbe Trennung wie im Altsystem, keine neue Vermischung.
 *
 * LOESCHEN (Auftrag Punkt 7 "nur wenn Nutzung sicher nachvollziehbar"):
 * delegiert vollstaendig an MediaUploadService::deleteByFilename(), das
 * bereits VOR jedem Loeschen App\Support\MediaReferenceScanner befragt und
 * bei einem Treffer MediaReferencedException wirft (siehe dortiger
 * Klassenkommentar fuer die dokumentierten Grenzen dieser Pruefung - u.a.
 * Hundeboerse/Waffenboerse werden bewusst nicht durchsucht, koennen aber
 * durch die oben beschriebene Verzeichnistrennung ohnehin nie ueber DIESES
 * Modul geloescht werden). Kein Override-Parameter, kein "trotzdem
 * loeschen" - identisch zur bestehenden JSON-API.
 *
 * SICHERHEIT (Auftrag Punkt 3): Dateiname kommt in allen Routen NIE als
 * freier Pfad, sondern als einzelnes Routensegment, zusaetzlich per
 * pruefeDateiname() gegen Path-Traversal/verschachtelte Pfade abgesichert
 * (identisch zur bestehenden Pruefung in Api\Admin\AdminMediaController::
 * destroy()) - MediaStorage::assertWithinRoot() (in MediaUploadService::
 * delete()) bleibt als zweite, unabhaengige Schutzschicht bestehen.
 */
class MedienController extends Controller
{
    /** GET admin/medien - aktive (nicht archivierte) Bilder+PDFs, optional nach Typ gefiltert. */
    public function index(Request $request): View
    {
        $aktuellerFilter = in_array($request->query('typ'), ['bild', 'datei'], true)
            ? $request->query('typ')
            : '';

        $gesamt = MediaLibrary::liste();
        $aktiv = array_values(array_filter($gesamt, fn (array $i) => ! $i['ist_archiviert']));

        $zaehler = [
            '' => count($aktiv),
            'bild' => count(array_filter($aktiv, fn (array $i) => $i['media_type'] === 'image')),
            'datei' => count(array_filter($aktiv, fn (array $i) => $i['media_type'] === 'pdf')),
        ];
        $archivAnzahl = count($gesamt) - count($aktiv);

        $angezeigt = $aktuellerFilter === ''
            ? $aktiv
            : array_values(array_filter($aktiv, fn (array $i) => $i['media_type'] === ($aktuellerFilter === 'bild' ? 'image' : 'pdf')));

        return view('admin.medien.index', compact('angezeigt', 'aktuellerFilter', 'zaehler', 'archivAnzahl'));
    }

    /**
     * GET admin/medien/archiv - eigene Unterseite statt Akkordeon (1:1
     * UX-Entscheidung wie im Altsystem, siehe dortiger admin.js-Kommentar
     * "fuehlte sich an wie ist eh alles sichtbar") - archivierte Bilder
     * verschwinden komplett aus index() und tauchen ausschliesslich hier auf.
     */
    public function archiv(): View
    {
        $gesamt = MediaLibrary::liste();
        $archiviert = array_values(array_filter($gesamt, fn (array $i) => $i['ist_archiviert']));

        return view('admin.medien.archiv', ['angezeigt' => $archiviert]);
    }

    /** POST admin/medien/bilder - Feldname "datei" (multipart/form-data), analog AdminMediaController::storeImage(). */
    public function bildHochladen(Request $request, MediaUploadService $service): RedirectResponse
    {
        if (! $request->hasFile('datei') || ! $request->file('datei')->isValid()) {
            return back()->withErrors(['medien' => 'Keine gültige Bilddatei ausgewählt.']);
        }

        try {
            $service->uploadImage($request->file('datei'), $this->angemeldeterBenutzer($request));

            return back()->with('status', 'Bild hochgeladen.');
        } catch (MediaValidationException|MediaServiceUnavailableException $e) {
            return back()->withErrors(['medien' => $e->getMessage()]);
        } catch (Throwable) {
            return back()->withErrors(['medien' => 'Bild-Upload fehlgeschlagen.']);
        }
    }

    /** POST admin/medien/dateien - Feldname "datei" (multipart/form-data), analog AdminMediaController::storePdf(). */
    public function dateiHochladen(Request $request, MediaUploadService $service): RedirectResponse
    {
        if (! $request->hasFile('datei') || ! $request->file('datei')->isValid()) {
            return back()->withErrors(['medien' => 'Keine gültige PDF-Datei ausgewählt.']);
        }

        try {
            $service->uploadPdf($request->file('datei'), $this->angemeldeterBenutzer($request));

            return back()->with('status', 'Datei hochgeladen.');
        } catch (MediaValidationException|MediaServiceUnavailableException $e) {
            return back()->withErrors(['medien' => $e->getMessage()]);
        } catch (Throwable) {
            return back()->withErrors(['medien' => 'PDF-Upload fehlgeschlagen.']);
        }
    }

    /**
     * PUT admin/medien/{dateiname}/archivieren - Ein-Klick-Umschalter, 1:1
     * nach dem Muster von TermineController::archivToggle() (kein separater
     * "wiederherstellen"-Endpunkt fuer denselben Zustandswechsel). NUR fuer
     * Bilder gedacht (siehe Klassenkommentar) - PDFs bekommen in der View
     * bewusst keinen Archivieren-Button, die Route selbst prueft das aber
     * zusaetzlich serverseitig ab (kein "unsichtbarer, aber trotzdem
     * erreichbarer" Endpunkt).
     */
    public function archivToggle(string $dateiname): RedirectResponse
    {
        $this->pruefeDateiname($dateiname);

        $existiert = MedienEintrag::where('media_type', 'image')->where('path', $dateiname)->exists()
            || is_file(MediaStorage::rootPathFor('image').'/'.$dateiname);
        if (! $existiert) {
            abort(404);
        }

        $eintrag = MedienArchivEintrag::where('dateiname', $dateiname)->first();
        if ($eintrag) {
            $eintrag->delete();
            $status = 'Bild wiederhergestellt.';
        } else {
            MedienArchivEintrag::create(['dateiname' => $dateiname, 'archiviert_am' => now()]);
            $status = 'Bild archiviert.';
        }

        return back()->with('status', $status);
    }

    /**
     * DELETE admin/medien/{typ}/{dateiname} - "typ" ist das deutsche
     * Routensegment ("bild"/"datei"), wird hier auf den internen
     * media_type-Wert ("image"/"pdf") abgebildet (derselbe Ansatz wie bei
     * anderen Modulen, die deutsche URL-Segmente statt interner
     * Enum-Werte verwenden).
     */
    public function loeschen(string $typ, string $dateiname, MediaUploadService $service): RedirectResponse
    {
        abort_unless(in_array($typ, ['bild', 'datei'], true), 404);
        $this->pruefeDateiname($dateiname);
        $mediaType = $typ === 'bild' ? 'image' : 'pdf';

        $existsInDb = MedienEintrag::where('media_type', $mediaType)->where('path', $dateiname)->exists();
        $existsOnDisk = is_file(MediaStorage::rootPathFor($mediaType).'/'.$dateiname);
        if (! $existsInDb && ! $existsOnDisk) {
            abort(404);
        }

        try {
            $service->deleteByFilename($mediaType, $dateiname);
            // Archiv-Eintrag mit aufraeumen, falls vorhanden - sonst wuerde
            // medien_archiv auf eine geloeschte Datei verweisen (1:1 wie im
            // Altsystem, siehe admin.js medienDeleteImage()-Kommentar
            // "Archiv-Eintrag bereinigt").
            MedienArchivEintrag::where('dateiname', $dateiname)->delete();

            return back()->with('status', 'Datei gelöscht.');
        } catch (MediaReferencedException $e) {
            $orte = array_slice($e->references, 0, 3);
            $hinweis = 'Nicht gelöscht – wird noch verwendet: '.implode('; ', $orte);
            if (count($e->references) > 3) {
                $hinweis .= ' (und weitere)';
            }

            return back()->withErrors(['medien' => $hinweis]);
        } catch (Throwable) {
            return back()->withErrors(['medien' => 'Löschen fehlgeschlagen.']);
        }
    }

    /**
     * Defense-in-depth zusaetzlich zu MediaStorage::assertWithinRoot() (das
     * den AUFGELOESTEN Pfad prueft) - ein Dateiname darf hier gar nicht
     * erst wie ein Pfad aussehen. 1:1 dieselbe Pruefung wie
     * AdminMediaController::destroy().
     */
    private function pruefeDateiname(string $dateiname): void
    {
        abort_if($dateiname !== basename($dateiname) || str_contains($dateiname, '..'), 422, 'Ungültiger Dateiname.');
    }

    private function angemeldeterBenutzer(Request $request): ?string
    {
        $user = AdminIdentity::currentUser($request);

        return $user['email'] ?? $user['sub'] ?? null;
    }
}
