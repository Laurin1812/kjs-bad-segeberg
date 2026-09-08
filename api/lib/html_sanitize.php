<?php
/**
 * Server-seitige HTML-Sanitisierung fuer redaktionell erfassten Rich-Text
 * (aktuell: Waffenboerse-Admin-Feld "beschreibung", befuellt ueber den
 * TipTap-Editor in admin/admin.js, siehe initTiptap()/fTipTap()).
 *
 * Hintergrund (Sicherheits-Finalisierung, Punkt "Waffenboerse pruefen"):
 * admin/speichern.php hat das Feld "beschreibung" bisher komplett roh/
 * unveraendert gespeichert, mit der Begruendung, der Inhalt komme "nur" aus
 * dem eigenen TipTap-Editor und sei deshalb vertrauenswuerdig. Diese Annahme
 * stimmt am eigentlichen API-Endpunkt nicht: speichern.php ist ein normaler
 * JSON-POST-Endpunkt, der von JEDEM Client mit gueltigem Bearer-Token und
 * "waffenboerse"- oder "admin"-Berechtigung aufgerufen werden kann - auch
 * OHNE die TipTap-Oberflaeche zu benutzen. Ein Redakteur-Account (bewusst
 * NICHT voll-admin, nur modulbezogen berechtigt) koennte also per direktem
 * API-Aufruf beliebiges HTML/<script> in "beschreibung" einschleusen und im
 * selben Request "status":"published" setzen - der Inhalt wuerde sofort per
 * innerHTML auf waffenboerse/detail.html an jeden Besucher ausgeliefert
 * (gespeicherte XSS). Diese Datei schliesst genau diese Luecke.
 *
 * Ansatz: DOM-basiertes Allowlist-Sanitizing (kein regexbasiertes Parsen/
 * Saeubern von HTML-Struktur - das waere unzuverlaessig und war fuer die
 * Hundeboerse-Loesung explizit ausgeschlossen). Der Eingabestring wird mit
 * PHP's eingebautem DOMDocument in einen echten DOM-Baum geparst, danach
 * Knoten fuer Knoten gegen eine feste Tag-/Attribut-Allowlist geprueft und
 * ein komplett neuer, sauberer DOM-Baum aufgebaut, der anschliessend
 * serialisiert wird. Alles, was nicht explizit erlaubt ist, wird entfernt.
 * Das ist dieselbe grundlegende Technik, die auch etablierte Bibliotheken
 * wie HTMLPurifier oder DOMPurify verwenden.
 *
 * Warum keine fertige Bibliothek (z.B. Composer-Paket "ezyang/htmlpurifier")?
 * composer.json/vendor/ existieren in diesem Repo bereits (siehe PHPMailer-
 * Setup fuer das Kontaktformular), das waere also grundsaetzlich der
 * naheliegende Weg. In dieser Sandbox-Umgebung ist der Zugriff auf
 * Packagist/repo.packagist.org jedoch per Netzwerk-Policy blockiert (kein
 * "composer require" moeglich) - ein vollstaendiges Drittanbieter-Paket von
 * Hand (ausserhalb des offiziellen Composer-Installationswegs, ohne
 * composer.lock) einzeln zusammenzukopieren, waere unsauber und schwer
 * nachvollziehbar. Diese schlanke, auf den tatsaechlich von TipTap erzeugten
 * HTML-Umfang zugeschnittene Loesung ist die vertretbare Alternative; siehe
 * Abschlussbericht fuer die Empfehlung, auf dem echten Server (mit vollem
 * Internetzugriff) spaeter "composer require ezyang/htmlpurifier"
 * nachzuruesten und hinter derselben Funktionssignatur auszutauschen.
 *
 * Sehr punktuelle Werte-Validatoren (z.B. "ist diese eine Farbangabe ein
 * gueltiger Hex-/RGB-Wert", "beginnt diese URL mit einem erlaubten Schema")
 * nutzen kurze, eng verankerte regulaere Ausdruecke. Das ist etwas anderes
 * als die im Auftrag ausgeschlossene "regexbasierte Sanitisierung" von
 * HTML-Struktur: hier wird nie versucht, Tags/Attribute per Regex aus einem
 * HTML-String herauszuschneiden - das uebernimmt vollstaendig der DOM-Parser.
 * Regex kommt ausschliesslich zur enge begrenzten Pruefung eines einzelnen,
 * bereits vom DOM extrahierten Attributwerts zum Einsatz (Standardpraxis,
 * genauso auch intern in HTMLPurifier/DOMPurify verwendet).
 */

declare(strict_types=1);

/** Tags, die inkl. ihres gesamten Inhalts vollstaendig verworfen werden. */
const KJS_SANITIZE_STRIP_WITH_CONTENT = [
    'script', 'style', 'object', 'embed', 'applet', 'form', 'input', 'button',
    'textarea', 'select', 'option', 'link', 'meta', 'base', 'svg', 'math',
    'noscript', 'template', 'frame', 'frameset', 'audio', 'video', 'source',
    'track', 'xml', 'title', 'head',
];

/** Erlaubte Tags -> Liste der pro Tag erlaubten Attributnamen. */
const KJS_SANITIZE_ALLOWED_TAGS = [
    'p' => ['style', 'data-spacing'],
    'br' => [],
    'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [], 'del' => [],
    'code' => [], 'pre' => [],
    'blockquote' => [],
    'hr' => [],
    'ul' => ['data-spacing'], 'ol' => ['data-spacing'], 'li' => [],
    'h1' => ['style'], 'h2' => ['style'], 'h3' => ['style'],
    'h4' => ['style'], 'h5' => ['style'], 'h6' => ['style'],
    'a' => ['href', 'target'],
    'img' => ['src', 'alt', 'class'],
    'span' => ['style'],
    'mark' => [],
    'table' => [], 'thead' => [], 'tbody' => [],
    'tr' => [], 'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
];

const KJS_SANITIZE_IMG_CLASSES = [
    'img-25', 'img-50', 'img-75', 'img-100', 'img-links', 'img-rechts', 'img-zentriert',
];

const KJS_SANITIZE_SPACING_VALUES = ['compact', 'wide'];

/**
 * Entfernt Steuerzeichen, die Browser bei der URL-Auswertung ignorieren
 * (bekannter Bypass: "jav\tascript:alert(1)" - ohne diese Bereinigung
 * wuerde eine naive Schema-Pruefung den eingebetteten Tab uebersehen).
 */
function kjs_sanitize_strip_control_chars(string $value): string
{
    $result = preg_replace('/[\x00-\x1F\x7F]/', '', $value);
    return $result ?? '';
}

function kjs_sanitize_url(string $value, array $allowedSchemes): ?string
{
    $clean = trim(kjs_sanitize_strip_control_chars($value));
    if ($clean === '') {
        return null;
    }
    $scheme = parse_url($clean, PHP_URL_SCHEME);
    if ($scheme === null || $scheme === false) {
        // Keine Schema-Angabe: relative URL (z.B. "/uploads/..."), erlaubt -
        // ausser "//host/..." (protokoll-relativ, zeigt auf fremden Host).
        if (strpos($clean, '//') === 0) {
            return null;
        }
        return $clean;
    }
    return in_array(strtolower($scheme), $allowedSchemes, true) ? $clean : null;
}

function kjs_sanitize_style_value(string $tag, string $value): ?string
{
    $value = trim($value);
    if (in_array($tag, ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
        if (preg_match('/^text-align:\s*(left|right|center|justify)\s*;?\s*$/i', $value, $m)) {
            return 'text-align: ' . strtolower($m[1]);
        }
        return null;
    }
    if ($tag === 'span') {
        if (preg_match('/^color:\s*(#[0-9a-fA-F]{3,8}|rgb\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*\))\s*;?\s*$/', $value, $m)) {
            return 'color: ' . $m[1];
        }
        return null;
    }
    return null;
}

function kjs_sanitize_attr_value(string $tag, string $attr, string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    if ($attr === 'href' && $tag === 'a') {
        return kjs_sanitize_url($value, ['http', 'https', 'mailto', 'tel']);
    }
    if ($attr === 'src' && $tag === 'img') {
        return kjs_sanitize_url($value, ['http', 'https']);
    }
    if ($attr === 'target' && $tag === 'a') {
        return $value === '_blank' ? '_blank' : null;
    }
    if ($attr === 'class' && $tag === 'img') {
        $parts = array_values(array_filter(
            explode(' ', $value),
            static fn(string $c): bool => in_array($c, KJS_SANITIZE_IMG_CLASSES, true)
        ));
        return $parts ? implode(' ', $parts) : null;
    }
    if ($attr === 'style') {
        return kjs_sanitize_style_value($tag, $value);
    }
    if ($attr === 'data-spacing') {
        return in_array($value, KJS_SANITIZE_SPACING_VALUES, true) ? $value : null;
    }
    if ($attr === 'alt') {
        return $value;
    }
    if (in_array($attr, ['colspan', 'rowspan'], true)) {
        return ctype_digit($value) ? $value : null;
    }
    return null;
}

/**
 * <iframe> wird nur fuer echte YouTube-Embeds akzeptiert (TipTap-Youtube-
 * Extension, admin.js: TT.Youtube.configure({ nocookie: true })) - jedes
 * andere iframe-Ziel wird komplett verworfen, nicht nur "entschaerft".
 */
function kjs_sanitize_youtube_iframe(DOMElement $node, DOMDocument $outputDoc): ?DOMElement
{
    $src = kjs_sanitize_strip_control_chars(trim($node->getAttribute('src')));
    $parts = parse_url($src);
    if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
        return null;
    }
    if (strtolower($parts['scheme']) !== 'https') {
        return null;
    }
    $host = strtolower($parts['host']);
    $allowedHosts = ['www.youtube-nocookie.com', 'youtube-nocookie.com', 'www.youtube.com', 'youtube.com'];
    if (!in_array($host, $allowedHosts, true)) {
        return null;
    }
    if (strpos($parts['path'] ?? '', '/embed/') !== 0) {
        return null;
    }

    $iframe = $outputDoc->createElement('iframe');
    $iframe->setAttribute('src', $src);
    foreach (['width', 'height'] as $dim) {
        $v = $node->getAttribute($dim);
        if ($v !== '' && ctype_digit($v)) {
            $iframe->setAttribute($dim, $v);
        }
    }
    $iframe->setAttribute('frameborder', '0');
    $iframe->setAttribute('allowfullscreen', 'allowfullscreen');
    $iframe->setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture');
    return $iframe;
}

function kjs_sanitize_walk(DOMNode $sourceParent, DOMNode $outParent, DOMDocument $outputDoc): void
{
    foreach (iterator_to_array($sourceParent->childNodes) as $node) {
        if ($node instanceof DOMText) {
            $outParent->appendChild($outputDoc->createTextNode($node->textContent));
            continue;
        }
        if (!($node instanceof DOMElement)) {
            // Kommentare, CDATA, Processing-Instructions etc.: verwerfen.
            continue;
        }
        $tag = strtolower($node->tagName);

        if ($tag === 'iframe') {
            $safeIframe = kjs_sanitize_youtube_iframe($node, $outputDoc);
            if ($safeIframe !== null) {
                $outParent->appendChild($safeIframe);
            }
            continue; // iframe-Inhalt wird nie uebernommen
        }

        if (in_array($tag, KJS_SANITIZE_STRIP_WITH_CONTENT, true)) {
            continue; // Tag UND kompletter Inhalt verworfen
        }

        if (!isset(KJS_SANITIZE_ALLOWED_TAGS[$tag])) {
            // Unbekanntes/nicht erlaubtes Tag (z.B. <div>, <font>): Tag
            // selbst verwerfen, aber Kindinhalt (z.B. reiner Text) behalten.
            kjs_sanitize_walk($node, $outParent, $outputDoc);
            continue;
        }

        $outEl = $outputDoc->createElement($tag);
        foreach (KJS_SANITIZE_ALLOWED_TAGS[$tag] as $attrName) {
            if (!$node->hasAttribute($attrName)) {
                continue;
            }
            $safeValue = kjs_sanitize_attr_value($tag, $attrName, $node->getAttribute($attrName));
            if ($safeValue !== null) {
                $outEl->setAttribute($attrName, $safeValue);
            }
        }
        // rel wird nie vom Nutzer uebernommen, sondern bei target="_blank"
        // immer fest gesetzt (Schutz vor Reverse-Tabnabbing).
        if ($tag === 'a' && $outEl->getAttribute('target') === '_blank') {
            $outEl->setAttribute('rel', 'noopener noreferrer');
        }
        $outParent->appendChild($outEl);
        kjs_sanitize_walk($node, $outEl, $outputDoc);
    }
}

/**
 * Saeubert einen HTML-Rich-Text-String auf die oben definierte Allowlist.
 * Nicht erlaubte Tags/Attribute/URL-Schemata werden entfernt, legitime
 * Formatierung (Ueberschriften, Fett/Kursiv, Listen, Links, Bilder, Tabellen,
 * YouTube-Einbettung, Textfarbe/-ausrichtung) bleibt erhalten.
 */
function kjs_boerse_sanitize_rich_html(?string $html): string
{
    $html = trim((string) $html);
    if ($html === '') {
        return '';
    }

    $prevSetting = libxml_use_internal_errors(true);
    $sourceDoc = new DOMDocument();
    $ok = $sourceDoc->loadHTML(
        '<?xml encoding="UTF-8"?><div id="kjs-sanitize-root">' . $html . '</div>',
        LIBXML_NOERROR | LIBXML_NOWARNING
    );
    libxml_clear_errors();
    libxml_use_internal_errors($prevSetting);

    if (!$ok) {
        return ''; // nicht parsebar -> lieber nichts anzeigen als evtl. unsicheren Rohtext
    }

    $xpath = new DOMXPath($sourceDoc);
    $rootNodes = $xpath->query('//*[@id="kjs-sanitize-root"]');
    if ($rootNodes === false || $rootNodes->length === 0) {
        return '';
    }
    $sourceRoot = $rootNodes->item(0);

    $outputDoc = new DOMDocument();
    $outputRoot = $outputDoc->createElement('div');
    $outputDoc->appendChild($outputRoot);

    kjs_sanitize_walk($sourceRoot, $outputRoot, $outputDoc);

    $inner = '';
    foreach (iterator_to_array($outputRoot->childNodes) as $child) {
        $serialized = $outputDoc->saveHTML($child);
        if ($serialized !== false) {
            $inner .= $serialized;
        }
    }
    return $inner;
}
