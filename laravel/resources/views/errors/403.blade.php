{{-- Phase 7A (Laravel-Admin-Grundlage / Auth / Admin-Shell): 403 (kein Zugriff) im KJS-Design - z.B. wenn ein angemeldeter Nicht-Admin /admin aufruft (siehe App\Http\Middleware\EnsureAdminWebSession). --}}
<x-error-page
    code="403"
    headline="Kein Zugriff."
    text="Für diesen Bereich fehlt Ihrem Konto die erforderliche Berechtigung."
/>
