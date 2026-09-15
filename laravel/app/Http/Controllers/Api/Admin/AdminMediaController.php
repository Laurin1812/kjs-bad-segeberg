<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MedienEintrag;
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
 * WICHTIG (Auftrag Punkt 8): admin/admin.js' bestehende Upload-Funktionen
 * werden in dieser Phase NICHT auf diese neue API umgestellt - Phase 5B.1
 * ist ausschliesslich Backend/API. Diese Endpunkte sind bis zur naechsten
 * Phase von keinem Frontend-Code aus erreichbar.
 *
 * Response-Format konsequent identisch zu den bestehenden Admin-Controllern:
 * {success:false, error:<kurzer_code>, message:<lesbarer_text>} im
 * Fehlerfall, {success:true, ...} im Erfolgsfall.
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
     * GET admin/media - Liste der Mediendatensaetze, neueste zuerst.
     * Optionaler Query-Parameter "type" (image|pdf) filtert nach media_type -
     * ungueltige/unbekannte Werte werden ignoriert (kein Fehler), damit ein
     * Tippfehler im Query-String nicht die ganze Liste als 422 abbricht.
     */
    public function index(Request $request): JsonResponse
    {
        $query = MedienEintrag::query()->orderByDesc('created_at')->orderByDesc('id');

        $type = $request->query('type');
        if (in_array($type, ['image', 'pdf'], true)) {
            $query->where('media_type', $type);
        }

        $items = $query->get()->map(fn (MedienEintrag $m) => $this->serialize($m))->values();

        return response()->json(['success' => true, 'items' => $items]);
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
     * DELETE admin/media/{medium} - loescht Original + Varianten + DB-Zeile
     * gemeinsam (siehe MediaUploadService::delete() fuer die bewusst NICHT
     * vorhandene Referenzpruefung, Auftrag Punkt 7).
     */
    public function destroy(int $medium, MediaUploadService $service): JsonResponse
    {
        $eintrag = MedienEintrag::find($medium);
        if ($eintrag === null) {
            return $this->errorResponse('not_found', 'Mediendatensatz nicht gefunden.', 404);
        }

        try {
            $service->delete($eintrag);

            return response()->json(['success' => true]);
        } catch (Throwable $e) {
            return $this->errorResponse('delete_failed', 'Löschen fehlgeschlagen.', 500);
        }
    }
}
