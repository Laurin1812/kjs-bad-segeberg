<?php

namespace App\Support;

use RuntimeException;

/**
 * Phase 5B.2 (Admin-Medienfunktionen auf Laravel-Media-API umstellen,
 * Auftrag Punkt 5 "Löschen – Sicherheitsverbesserung"): wird geworfen, wenn
 * MediaReferenceScanner vor einem Löschversuch mindestens eine Fundstelle
 * meldet - der Controller übersetzt das in HTTP 409 mit der Fundstellen-
 * liste, DAMIT NICHTS GELÖSCHT WIRD. Bewusst kein Override-Parameter/keine
 * "trotzdem löschen"-Möglichkeit in dieser Phase (Auftrag: "standardmäßig
 * NICHT löschen", "keine automatische Entfernung der Referenzen") - ein
 * Admin, der eine referenzierte Datei wirklich löschen will, muss die
 * Referenz zuerst selbst aus dem Inhalt entfernen.
 */
class MediaReferencedException extends RuntimeException
{
    /** @var string[] */
    public array $references;

    /** @param string[] $references */
    public function __construct(array $references)
    {
        $this->references = $references;
        parent::__construct('Datei wird noch verwendet und wurde nicht gelöscht.');
    }
}
