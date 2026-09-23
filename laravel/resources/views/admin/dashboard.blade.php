{{--
    Phase 7A (Laravel-Admin-Grundlage / Auth / Admin-Shell).

    Dashboard der neuen Blade-Admin-Oberflaeche - siehe DashboardController-
    Klassenkommentar fuer die Herkunft jeder einzelnen Zahl (ausschliesslich
    echte Eloquent-Counts, keine erfundenen Werte). Bewusst KEIN Link von
    einer Kachel zu einem Bearbeitungsformular - die dazugehoerigen Module
    existieren in der neuen Oberflaeche noch nicht (siehe Auftrag "NICHT
    JETZT").
--}}
<x-layouts.admin title="Dashboard">
    <div class="panel-header">
        <h1>Dashboard</h1>
    </div>
    <div class="panel-body">
        <div class="welcome-screen" style="text-align:left;padding:0 0 1.5rem;">
            <p style="margin:0;">Willkommen im neuen KJS-Laravel-Admin. Diese Oberfläche wird schrittweise um die einzelnen Fachmodule (siehe Navigation links) erweitert.</p>
        </div>

        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-card__label">Seiten</div>
                <div class="stat-card__value">{{ $anzahlSeiten }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-card__label">Aktuelles-Beiträge</div>
                <div class="stat-card__value">{{ $anzahlAktuelles }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-card__label">Termine</div>
                <div class="stat-card__value">{{ $anzahlTermine }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-card__label">Offene Kontaktanfragen</div>
                <div class="stat-card__value">{{ $anzahlOffeneKontaktanfragen }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-card__label">Hundebörse – wartet auf Freigabe</div>
                <div class="stat-card__value">{{ $anzahlPendingHundeboerse }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-card__label">Waffenbörse – wartet auf Freigabe</div>
                <div class="stat-card__value">{{ $anzahlPendingWaffenboerse }}</div>
            </div>
        </div>

        <div class="hint-card">
            Die Bearbeitung der einzelnen Module (Inhalte, Aktuelles, Termine, Hundebörse, Waffenbörse, Kontaktanfragen, Medien, Einstellungen, Benutzer) ist über diese neue Oberfläche noch nicht möglich – bis zur schrittweisen Migration erfolgt sie weiterhin über den bisherigen Admin-Bereich.
        </div>
    </div>
</x-layouts.admin>
