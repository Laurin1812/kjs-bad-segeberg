<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * KJS Bad Segeberg - Phase 5 (URL-Erhalt / alte Pfade / Redirects).
 *
 * Buendelt die alten Query-String-getriebenen Detailseiten des frueheren
 * Webroots (aktuelles/beitrag.html?i=, seiten/index.html?s=, partner/
 * detail.html?id=), die sich NICHT wie die uebrigen alten .html-URLs per
 * simplem "Route::permanentRedirect()" auf eine feste Ziel-URL abbilden
 * lassen, weil das Redirect-Ziel vom jeweiligen Query-Parameter abhaengt.
 * Alle uebrigen (statischen) alten URLs werden direkt in routes/web.php per
 * Route::permanentRedirect() behandelt - siehe dortiger Kommentarblock
 * "Phase 5".
 *
 * Grundsatz fuer alle drei Methoden (Auftrag: "keine erfundenen
 * Zielseiten"): es wird ausschliesslich auf eine Seite weitergeleitet, die
 * anhand des mitgegebenen Parameters TATSAECHLICH in der Datenbank
 * existiert. Fehlt der Parameter oder existiert dazu keine Seite, wird NIE
 * geraten - stattdessen entweder ein sinnvoller, eindeutig zur alten
 * Funktion passender UEBERGEORDNETER Fallback (Listing-Seite) angesteuert,
 * oder es bleibt bei einer echten 404 (siehe Methodenkommentare).
 */
class LegacyUrlController extends Controller
{
    /**
     * seiten/index.html?s=<slug> - ersetzt das alte, ordnerübergreifende
     * "versucheOrdner()"-Modul (siehe RegistrySeiteController-
     * Klassenkommentar): war im Original sowohl fuer per Registry
     * hinzugefuegte Zusatzseiten von jaeger/aufgaben/verbraucher/weitere
     * ALS AUCH fuer dynamisch angelegte UNTERSEITEN (Kind-Seiten) zustaendig
     * - beide liegen heute gleichermassen in der "pages"-Tabelle (siehe
     * KjsPagesConfig-Klassenkommentar), unterschieden nur durch
     * "parent_id". "slug" traegt in der Praxis keine Dubletten (per
     * Stichprobe der echten Bestandsdaten geprueft - anders als die
     * DB-Unique-Regel ["section","parent_id","slug"] es technisch erlauben
     * wuerde), genau wie im alten System, das ebenfalls ohne
     * Section-/Parent-Angabe im Query-String auskam.
     *
     * SONDERFALL "hundeausbildung": diese Section liegt zwar ebenfalls in
     * der "pages"-Tabelle (ein Hub + bis zu 19 Kurs-Kind-Seiten, siehe
     * HundeausbildungController-Klassenkommentar), hat aber ein EIGENES,
     * flaches URL-Schema ("/aufgaben/jagdhundeschule/{slug}" statt
     * "/{section}/{parentSlug}/{childSlug}") - beim Live-Test mit echten
     * Bestandsdaten entdeckt: die generische Formel haette hier
     * "/hundeausbildung/hundeausbildung/<kurs>" erzeugt, eine gar nicht
     * existierende Route (404 statt der beabsichtigten Weiterleitung).
     */
    public function seiten(Request $request): RedirectResponse
    {
        $slug = trim((string) $request->query('s', ''));

        if ($slug === '') {
            abort(404);
        }

        $page = Page::where('slug', $slug)->with('parent')->first();

        if (! $page) {
            abort(404);
        }

        if ($page->section === 'hundeausbildung') {
            $ziel = $page->parent ? '/aufgaben/jagdhundeschule/'.$page->slug : '/aufgaben/hundeausbildung';

            return redirect($ziel, 301);
        }

        // Sonderfall "kreisjaegermeister": ebenfalls eine eigene Section in
        // der "pages"-Tabelle, aber eine Singleton-Seite ohne Slug-URL
        // (siehe KreisjaegermeisterController-Klassenkommentar) - die
        // generische Formel wuerde "/kreisjaegermeister/kreisjaegermeister"
        // erzeugen (nicht existierende Route) statt der tatsaechlichen,
        // bewusst mit Tippfehler beibehaltenen Route "/kreisjjaegermeister".
        if ($page->section === 'kreisjaegermeister') {
            return redirect('/kreisjjaegermeister', 301);
        }

        $ziel = $page->parent
            ? '/'.$page->section.'/'.$page->parent->slug.'/'.$page->slug
            : '/'.$page->section.'/'.$page->slug;

        return redirect($ziel, 301);
    }

    /**
     * partner/detail.html?id=pn-<timestamp> - "id" ist exakt dasselbe Feld
     * wie das heutige "external_id" (siehe PartnerController-
     * Klassenkommentar: "entspricht 1:1 der bisherigen ?id=-Verlinkung"),
     * daher keine Datenbankabfrage noetig - der Wert wird unveraendert als
     * Pfadsegment weitergereicht. Existiert dazu kein Partner (mehr), liefert
     * PartnerController::show() selbst eine echte 404 (kein erfundenes
     * Ziel). Fehlt "id" komplett, ist die Partner-Uebersicht die einzige
     * fachlich eindeutige, nicht geratene Alternative.
     */
    public function partnerDetail(Request $request): RedirectResponse
    {
        $id = trim((string) $request->query('id', ''));

        return redirect($id !== '' ? '/partner/detail/'.$id : '/partner', 301);
    }

    /**
     * hundeboerse/detail.html?id=hb-<...> (Phase 6A) - "id" wird
     * unveraendert als Pfadsegment weitergereicht, exakt wie bei
     * partnerDetail() oben. Existiert dazu keine (mehr) veroeffentlichte
     * Anzeige, liefert HundeboerseController::show() selbst eine echte 404
     * (kein erfundenes Ziel). Fehlt "id", ist die Hundeboerse-Uebersicht
     * die einzige fachlich eindeutige Alternative.
     */
    public function hundeboerseDetail(Request $request): RedirectResponse
    {
        $id = trim((string) $request->query('id', ''));

        return redirect($id !== '' ? '/hundeboerse/detail/'.$id : '/hundeboerse', 301);
    }

    /**
     * waffenboerse/detail.html?id=wb-<...> (Phase 6B) - exakt dasselbe
     * Muster wie hundeboerseDetail() oben. Existiert dazu keine (mehr)
     * veroeffentlichte Anzeige, liefert WaffenboerseController::show()
     * selbst eine echte 404 (kein erfundenes Ziel). Fehlt "id", ist die
     * Waffenboerse-Uebersicht die einzige fachlich eindeutige Alternative.
     */
    public function waffenboerseDetail(Request $request): RedirectResponse
    {
        $id = trim((string) $request->query('id', ''));

        return redirect($id !== '' ? '/waffenboerse/detail/'.$id : '/waffenboerse', 301);
    }
}
