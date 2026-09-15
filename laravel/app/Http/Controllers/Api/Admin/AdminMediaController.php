<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MedienEintrag;
use App\Support\MediaReferencedException;
use App\Support\MediaServiceUnavailableException;
use App\Support\MediaStorage;
use App\Support\MediaUploadService;
use App\Support\MediaValidationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Phase 5B.1 (sichere Laravel-Medienarchitektur): authentifizierte
 * Admin-Endpunkte fuer die neue, generische Medienbibliothek - bewusst
 * getrennt von den bestehenden Admin-Schreib-Controllern (AdminSettings-/
 * AdminList-/AdminPageController), weil "Medien hoch-/herunterladen" fachlich
 * nichts mit "ein Content-Modul speichern" zu tun hat, auch wenn beide
 * dieselbe Netlify-Identity-Middleware verwenden (Auftrag Punkt 6:
 * "Bestehende Netlify-Identity-/Permission-Middleware weiterverwenden").
 *
 * Alle Routen sind mit "identity.permission:medien" abgesichert (siehe
 * routes/api.php) - "medien" ist ein eigenstaendiger, nicht admin-only
 * Berechtigungsschluessel aus admin.js' PERM_BY_KEY (bestaetigt: PERM_BY_KEY
 * ['medien'] = 'medien'), genau nach demselben 1:1-Muster wie alle anderen
 * Module.
 *
 * Response-Format konsequent identisch zu den bestehenden Admin-Controllern:
 * {success:false, error:<kurzer_code>, message:<lesbarer_text>} im
 * Fehlerfall, {success:true, ...} im Erfolgsfall.
 *
 * Phase 5B.2 (Admin-Medienfunktionen auf diese API umstellen, Auftrag
 * Punkt 4 "Medienbibliothek alt+neu"): index() zeigt jetzt NICHT mehr nur
 * die "medien"-Tabelle, sondern zusaetzlich historische Dateien, die direkt
 * im Dateisystem liegen, aber (noch) keine DB-Zeile besitzen - siehe
 * scanLegacyFiles()/serializeLegacy(). Und Punkt 5 "Löschen –
 * Sicherheitsverbesserung": destroy() ist jetzt filename-basiert (nicht
 * mehr ID-basiert, siehe dortiger Methodenkommentar) und respektiert
 * MediaReferencedException (409, siehe MediaReferenceScanner).
 */
class AdminMediaController extends Controller
{
    private function errorResponse(string $error, string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'error' => $error, 'message' => $message], $status);
    }

    /** @return array<string, mixed> */
    private function serialize(MedienEintrag $medium): array
    {
        return [
            'id' => $medium->id,
            'media_type' => $medium->media_type,
            'original_name' => $medium->original_name,
            'mime_type' => $medium->mime_type,
            'size_bytes' => $medium->size_bytes,
            'width' => $medium->width,
            'height' => $medium->height,
            'uploaded_by' => $medium->uploaded_by,
            'url' => MediaStorage::publicUrl($medium),
            'thumb_url' => MediaStorage::thumbUrl($medium),
            'card_url' => MediaStorage::cardUrl($medium),
            'created_at' => optional($medium->created_at)->toIso8601String(),
        ];
    }

    /**
     * Phase 5B.2: liefert dieselbe Feldform wie serialize(), aber fuer eine
     * Datei, die NUR auf der Platte liegt (kein "medien"-Datensatz) - id
     * bleibt null (das UI unterscheidet ohnehin nicht danach, siehe
     * admin.js apiGetDirLaravel()), original_name/mime_type/Masse werden aus
     * dem Dateisystem abgeleitet statt aus der DB. width/height werden
     * bewusst NICHT per getimagesize() ermittelt (koennte bei ~250
     * historischen Bildern jede Listenabfrage spuerbar verlangsamen) -
     * admin.js zeigt diese Werte ohnehin nirgends an.
     */
    private function serializeLegacy(string $mediaType, string $filename, int $sizeBytes, int $mtime): array
    {
        $medium = new MedienEintrag(['media_type' => $mediaType, 'path' => $filename]);
        $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };

        return [
            'id' => null,
            'media_type' => $mediaType,
            'original_name' => $filename,
            'mime_type' => $mime,
            'size_bytes' => $sizeBytes,
            'width' => null,
            'height' => null,
            'uploaded_by' => null,
            'url' => MediaStorage::publicUrl($medium),
            'thumb_url' => MediaStorage::thumbUrl($medium),
            'card_url' => MediaStorage::cardUrl($medium),
            'created_at' => date('c', $mtime),
        ];
    }

    /**
     * Phase 5B.2: nicht-rekursives Directory-Listing der historischen
     * Original-Dateien (images/ bzw. downloads/, OHNE thumb/card-
     * Unterordner - is_file() filtert Verzeichniseintraege automatisch
     * heraus, es wird nirgends in Unterordner hinabgestiegen). Dieselbe
     * Endungs-Filterliste wie admin.js' medienBilderListe()/loadPdfGallery(),
     * damit die Medienbibliothek exakt dieselben Dateien zeigt wie bisher
     * (inkl. historischer GIF/SVG-Bilder, die fuer NEUE Uploads per
     * kjs_media.php nicht mehr erlaubt sind, aber weiter angezeigt/
     * geloescht werden koennen muessen).
     *
     * @return array<string, array{size:int, mtime:int}> Dateiname => Metadaten
     */
    private function scanLegacyFiles(string $mediaType): array
    {
        $dir = MediaStorage::rootPathFor($mediaType);
        if (! is_dir($dir)) {
            return [];
        }
        $pattern = $mediaType === 'image' ? '/\.(jpg|jpeg|png|gif|webp|svg)$/i' : '/\.pdf$/i';

        $out = [];
        foreach (scandir($dir) ?: [] as $entry) {
            $full = $dir.'/'.$entry;
            if (! is_file($full) || ! preg_match($pattern, $entry)) {
                continue;
            }
            $out[$entry] = ['size' => (int) (@filesize($full) ?: 0), 'mtime' => (int) (@filemtime($full) ?: 0)];
        }

        return $out;
    }

    /**
     * GET admin/media - Liste ALLER Bilder/PDFs (Auftrag Punkt 4:
     * "bestehende historische Dateien weiterhin anzeigen, neue Laravel-
     * Medien ebenfalls anzeigen, keine Duplikate"): zuerst alle "medien"-
     * Datensaetze (massgebliche Metadaten), danach ein Dateisystem-Scan pro
     * Typ - jede Datei, die dort bereits per DB-Zeile erfasst ist, wird
     * uebersprungen (Dateiname ist eindeutig, siehe medien.path als unique
     * Spalte), jede andere als "legacy"-Eintrag ergaenzt. Optionaler
     * Query-Parameter "type" (image|pdf) filtert wie bisher; ungueltige/
     * fehlende Werte liefern beide Typen (admin.js ruft IMMER mit
     * explizitem type auf, siehe apiGetDirLaravel()).
     */
    public function index(Request $request): JsonResponse
    {
        $type = $request->query('type');
        $types = in_array($type, ['image', 'pdf'], true) ? [$type] : ['image', 'pdf'];

        $items = [];
        foreach ($types as $t) {
            $dbByFilename = [];
            foreach (MedienEintrag::where('media_type', $t)->get() as $m) {
                $dbByFilename[$m->path] = true;
                $items[] = ['sort' => $m->created_at?->timestamp ?? 0, 'item' => $this->serialize($m)];
            }
            foreach ($this->scanLegacyFiles($t) as $filename => $meta) {
                if (isset($dbByFilename[$filename])) {
                    continue; // bereits per DB-Zeile erfasst - keine Duplikate (Auftrag Punkt 4)
                }
                $items[] = ['sort' => $meta['mtime'], 'item' => $this->serializeLegacy($t, $filename, $meta['size'], $meta['mtime'])];
            }
        }

        usort($items, fn ($a, $b) => $b['sort'] <=> $a['sort']);

        return response()->json(['success' => true, 'items' => array_values(array_map(fn ($x) => $x['item'], $items))]);
    }

    private function uploadedBy(Request $request): ?string
    {
        $user = $request->attributes->get('identity_user');

        return is_array($user) ? ($user['email'] ?? $user['sub'] ?? null) : null;
    }

    /** POST admin/media/images - Feldname "file" (multipart/form-data). */
    public function storeImage(Request $request, MediaUploadService $service): JsonResponse
    {
        $file = $request->file('file');
        if ($file === null || ! $file->isValid()) {
            return $this->errorResponse('invalid_payload', 'Keine gültige Bilddatei im Feld "file" übermittelt.', 422);
        }

        try {
            $medium = $service->uploadImage($file, $this->uploadedBy($request));

            return response()->json(['success' => true, 'item' => $this->serialize($medium)], 201);
        } catch (MediaValidationException $e) {
            return $this->errorResponse('invalid_payload', $e->getMessage(), 422);
        } catch (MediaServiceUnavailableException $e) {
            return $this->errorResponse('service_unavailable', $e->getMessage(), 503);
        } catch (Throwable $e) {
            return $this->errorResponse('upload_failed', 'Bild-Upload fehlgeschlagen.', 500);
        }
    }

    /** POST admin/media/pdfs - Feldname "file" (multipart/form-data). */
    public function storePdf(Request $request, MediaUploadService $service): JsonResponse
    {
        $file = $request->file('file');
        if ($file === null || ! $file->isValid()) {
            return $this->errorResponse('invalid_payload', 'Keine gültige PDF-Datei im Feld "file" übermittelt.', 422);
        }

        try {
            $medium = $service->uploadPdf($file, $this->uploadedBy($request));

            return response()->json(['success' => true, 'item' => $this->serialize($medium)], 201);
        } catch (MediaValidationException $e) {
            return $this->errorResponse('invalid_payload', $e->getMessage(), 422);
        } catch (MediaServiceUnavailableException $e) {
            return $this->errorResponse('service_unavailable', $e->getMessage(), 503);
        } catch (Throwable $e) {
            return $this->errorResponse('upload_failed', 'PDF-Upload fehlgeschlagen.', 500);
        }
    }

    /**
     * DELETE admin/media - Phase 5B.2: umgestellt von einer numerischen ID
     * (Phase 5B.1, bis heute von keinem Frontend-Code aufgerufen - siehe
     * dortiger Klassenkommentar) auf (media_type, filename) im JSON-Body.
     * Grund: die Medienbibliothek zeigt jetzt AUCH historische Dateien ohne
     * DB-Zeile (siehe index()) - admin.js kennt fuer die immer nur Typ und
     * Dateiname, nie eine ID. MediaUploadService::deleteByFilename() findet
     * bei Bedarf selbst die passende DB-Zeile oder behandelt die Datei als
     * rein dateisystembasiert.
     *
     * Reihenfolge: erst pruefen, ob ueberhaupt etwas zu loeschen da ist
     * (DB-Zeile ODER Datei auf der Platte) -> sonst 404, statt "erfolgreich"
     * nichts zu tun zu melden. Referenzpruefung (409) und Path-Traversal-
     * Schutz laufen dann innerhalb von deleteByFilename()/delete().
     */
    public function destroy(Request $request, MediaUploadService $service): JsonResponse
    {
        $mediaType = $request->input('media_type');
        $filename = $request->input('filename');

        if (! in_array($mediaType, ['image', 'pdf'], true) || ! is_string($filename) || $filename === '') {
            return $this->errorResponse('invalid_payload', 'media_type ("image"/"pdf") und filename sind erforderlich.', 422);
        }
        // Defense-in-depth zusaetzlich zu MediaStorage::assertWithinRoot()
        // (das den AUFGELOESTEN Pfad prueft) - ein Dateiname darf hier gar
        // nicht erst wie ein Pfad aussehen.
        if ($filename !== basename($filename) || str_contains($filename, '..')) {
            return $this->errorResponse('invalid_payload', 'Ungültiger Dateiname.', 422);
        }

        $existsInDb = MedienEintrag::where('media_type', $mediaType)->where('path', $filename)->exists();
        $existsOnDisk = is_file(MediaStorage::rootPathFor($mediaType).'/'.$filename);
        if (! $existsInDb && ! $existsOnDisk) {
            return $this->errorResponse('not_found', 'Datei nicht gefunden.', 404);
        }

        try {
            $service->deleteByFilename($mediaType, $filename);

            return response()->json(['success' => true]);
        } catch (MediaReferencedException $e) {
            return response()->json([
                'success' => false,
                'error' => 'referenced',
                'message' => $e->getMessage(),
                'references' => $e->references,
            ], 409);
        } catch (Throwable $e) {
            return $this->errorResponse('delete_failed', 'Löschen fehlgeschlagen.', 500);
        }
    }
}
