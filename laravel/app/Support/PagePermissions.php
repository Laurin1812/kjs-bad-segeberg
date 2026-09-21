<?php

namespace App\Support;

/**
 * Phase 4 (Fortsetzung, Admin-Schreibweg fuer normale Inhaltsseiten/
 * Unterseiten/Hundeausbildung): server-seitige Nachbildung der granularen
 * Pro-Seite-Rechtepruefung, die bisher NUR client-seitig in admin.js
 * existierte (PERM_BY_KEY/PERM_BY_DIR/LEGACY_INHALTSSEITEN_KEYS/
 * LEGACY_NAVIGATION_KEYS, canAccessDef()). Das war der in
 * AdminPageController dokumentierte Blocker ("~30 Slug-zu-Recht-
 * Zuordnungen ... nicht mit vertretbarer Sorgfalt zu verifizieren") -
 * diese Klasse loest ihn per bewusstem 1:1-Hand-Port (keine Ableitung, keine
 * Vereinfachung) derselben Tabellen: admin.js bleibt die einzige "Quelle
 * der Wahrheit", diese Klasse kopiert sie nur fuer die Server-Seite. Nutzt
 * seit der Umstellung von Netlify Identity auf Laravel Fortify
 * App\Support\AdminIdentity::isAdmin()/hasPermission() (dieselbe Signatur
 * wie zuvor NetlifyIdentity) fuer die eigentliche Rollen-/Rechtepruefung.
 *
 * WICHTIG bei kuenftigen Aenderungen an admin.js' PERM_BY_KEY/PERM_BY_DIR/
 * LEGACY_*: diese Tabellen hier IMMER synchron nachziehen, sonst laeuft ein
 * Redakteur client-seitig in eine Seite hinein, die er speichern darf sehen
 * kann, serverseitig aber (zu Recht oder faelschlich) 403 bekommt - oder,
 * schlimmer, umgekehrt.
 *
 * Nur fuer die Seiten-Familie relevant (jaeger/aufgaben/verbraucher feste
 * Seiten, deren Unterseiten, Registry-Zusatzseiten, "weitere Themen" und
 * Hundeausbildung) - die Settings-/Listen-Module haben ihr je EIN festes
 * Recht bereits direkt in routes/api.php ("identity.permission:<key>").
 */
class PagePermissions
{
    /**
     * Direkte Kopie von KjsPagesConfig::fixedFamilies()[$section][0], aber
     * bewusst hier nochmal separat gehalten (nicht von dort importiert) -
     * diese Liste beantwortet eine andere Frage ("welches Recht?") als
     * KjsPagesConfig ("welcher Slug ist ueberhaupt eine feste Seite?"), auch
     * wenn die Slug-Listen zufaellig identisch sind. Quelle: admin.js'
     * PERM_BY_KEY, jeweils die Datei-Pfade der NAV-Eintraege mit
     * file:'content/<section>/<slug>.json' nachverfolgt.
     *
     * 'uebersicht' (jaeger) hat KEINEN NAV-Eintrag in admin.js (siehe dortiger
     * Kommentar "bewusst aus dem Admin-Menü entfernt") - also auch kein
     * PERM_BY_KEY-Eintrag und damit (fail-closed, wie jedes unbekannte
     * Modul) admin-only. Hier bewusst NICHT aufgenommen (kein Schluessel =
     * "resolve() liefert null" = admin-only), statt fuer ein Modul, das
     * ueber die Admin-Oberflaeche ohnehin nie erreichbar ist, ein Recht zu
     * erfinden, das admin.js selbst nicht kennt.
     *
     * @var array<string, array<string, string>>
     */
    private const FIXED_SLUG_PERMISSIONS = [
        'jaeger' => [
            'hochwild' => 'hochwild',
            'infomobil' => 'infomobil',
            'jaeger-werden' => 'jaeger_werden',
            'landesjagdverband' => 'landesjagdverband',
            'mitglied-werden' => 'mitglied_werden',
            'niederwild' => 'niederwild',
            'satzung' => 'satzung',
            'schiessobleute' => 'schiessobleute',
            'ueber-uns' => 'ueber_uns',
        ],
        'aufgaben' => [
            'jagdhorn' => 'aufgaben_jagdhorn',
            'jugend' => 'aufgaben_jugend',
            'jungwildrettung' => 'aufgaben_jungwild',
            'naturschutz' => 'aufgaben_natur',
            'schiessen' => 'aufgaben_schiessen',
            'schweisshunde' => 'aufgaben_schweisshunde',
        ],
        'verbraucher' => [
            'gruenes-klassenzimmer' => 'verbraucher_gruenes_klassenzimmer',
            'lernort-natur' => 'verbraucher_lernort_natur',
            'waidmannssprache' => 'verbraucher_waidmannssprache',
            'wildfleisch' => 'verbraucher_wildfleisch',
        ],
    ];

    /**
     * 1:1 Kopie von admin.js' PERM_BY_DIR, soweit fuer die Seiten-Familie
     * relevant (Unterseiten- und Registry-Zusatzseiten-Verzeichnisse).
     * Wichtig: 'content/seiten-sub-waidmannssprache' fehlt hier GENAUSO wie
     * im admin.js-Original (dort kein PERM_BY_DIR-Eintrag) - eine bereits
     * bestehende Unstimmigkeit im Alt-System (siehe Abschlussbericht), die
     * hier bewusst NICHT stillschweigend "korrigiert" wird, um admin.js'
     * Verhalten exakt zu spiegeln statt es zu veraendern.
     */
    private const DIR_PERMISSIONS = [
        'seiten-sub-wildfleisch' => 'verbraucher_wildfleisch',
        'seiten-sub-lernort-natur' => 'verbraucher_lernort_natur',
        'seiten-sub-gruenes-klassenzimmer' => 'verbraucher_gruenes_klassenzimmer',
        'seiten-sub-ueber-uns' => 'ueber_uns',
        'seiten-sub-mitglied-werden' => 'mitglied_werden',
        'seiten-sub-jaeger-werden' => 'jaeger_werden',
        'seiten-sub-niederwild' => 'niederwild',
        'seiten-sub-hochwild' => 'hochwild',
        'seiten-sub-schiessobleute' => 'schiessobleute',
        'seiten-sub-satzung' => 'satzung',
        'seiten-sub-landesjagdverband' => 'landesjagdverband',
        'seiten-sub-schiessen' => 'aufgaben_schiessen',
        'seiten-sub-schweisshunde' => 'aufgaben_schweisshunde',
        'seiten-sub-jugend' => 'aufgaben_jugend',
        'seiten-sub-jagdhorn' => 'aufgaben_jagdhorn',
        'seiten-sub-naturschutz' => 'aufgaben_natur',
        'seiten-sub-jungwildrettung' => 'aufgaben_jungwild',
        // Registry-Zusatzseiten (dieselben drei Werte wie admin.js):
        'seiten-aufgaben' => 'aufgaben_sonstiges',
        'seiten-kjs' => 'inhaltsseiten',
        // 'content/seiten-verbraucher' hat in admin.js KEINEN PERM_BY_DIR-
        // Eintrag (der Importer legt fuer "verbraucher" nie Registry-
        // Zusatzseiten an, siehe KjsPagesConfig-Kommentar) - hier bewusst
        // ebenfalls weggelassen statt geraten (= admin-only, faellt in der
        // Praxis nie an, da Page-Zeilen dafuer nie existieren).
        'seiten-weitere' => 'inhaltsseiten',
    ];

    /** 1:1 Kopie von admin.js' LEGACY_INHALTSSEITEN_KEYS. */
    private const LEGACY_INHALTSSEITEN_KEYS = [
        'ueber_uns', 'mitglied_werden', 'jaeger_werden', 'niederwild', 'hochwild',
        'schiessobleute', 'satzung', 'landesjagdverband',
        'aufgaben_schiessen', 'aufgaben_hundeausbildung', 'aufgaben_schweisshunde',
        'aufgaben_jugend', 'aufgaben_jagdhorn', 'aufgaben_natur', 'aufgaben_jungwild',
        'aufgaben_sonstiges',
        'verbraucher_wildfleisch', 'verbraucher_lernort_natur',
        'verbraucher_gruenes_klassenzimmer', 'verbraucher_waidmannssprache',
        'service', 'downloads', 'faq', 'footer', 'impressum', 'startseite',
    ];

    /**
     * Erfordertes Recht fuer "feste Vorlagen-Seite" ODER "Registry-
     * Zusatzseite" derselben Section (beide nutzen in AdminPageController
     * dieselbe versionSection($section,$slug)-Form und daher hier dieselbe
     * Aufloesung: erst FIXED_SLUG_PERMISSIONS, sonst - fuer Zusatzseiten -
     * das jeweilige DIR_PERMISSIONS-Recht der Section).
     */
    public static function forFesteSeite(string $section, string $slug): ?string
    {
        return self::FIXED_SLUG_PERMISSIONS[$section][$slug] ?? null;
    }

    public static function forRegistrierteSeite(string $section): ?string
    {
        $dir = match ($section) {
            'jaeger' => 'seiten-kjs',
            'aufgaben' => 'seiten-aufgaben',
            'verbraucher' => 'seiten-verbraucher',
            default => null,
        };

        return $dir !== null ? (self::DIR_PERMISSIONS[$dir] ?? null) : null;
    }

    public static function forWeitereSeite(): ?string
    {
        return self::DIR_PERMISSIONS['seiten-weitere'] ?? null;
    }

    /** Unterseite: Recht richtet sich nach dem ELTERN-Slug (PERM_BY_DIR-Muster admin.js). */
    public static function forSubSeite(string $parentSlug): ?string
    {
        return self::DIR_PERMISSIONS['seiten-sub-'.$parentSlug] ?? null;
    }

    public static function forHundeausbildungHub(): ?string
    {
        // admin.js PERM_BY_KEY['auf-hunde-uebersicht'] = 'aufgaben_hundeausbildung'.
        return 'aufgaben_hundeausbildung';
    }

    public static function forHundeausbildungKurs(): ?string
    {
        // admin.js PERM_BY_DIR['content/aufgaben/hundeausbildung'] = 'jagdhundeschule'.
        return 'jagdhundeschule';
    }

    /**
     * Spiegelbild von admin.js' canAccessDef(): admin immer erlaubt; sonst
     * das aufgeloeste Recht direkt, oder - Bestandsschutz - das alte
     * Sammelrecht "inhaltsseiten", falls $permissionKey darunter fiel.
     *
     * @param  array{roles: list<string>, permissions: list<string>}  $user
     */
    public static function userMayAccess(array $user, ?string $permissionKey): bool
    {
        if (AdminIdentity::isAdmin($user)) {
            return true;
        }
        if ($permissionKey === null) {
            return false;
        }
        if (AdminIdentity::hasPermission($user, $permissionKey)) {
            return true;
        }
        if (in_array($permissionKey, self::LEGACY_INHALTSSEITEN_KEYS, true)
            && AdminIdentity::hasPermission($user, 'inhaltsseiten')) {
            return true;
        }

        return false;
    }
}
