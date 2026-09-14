<?php

namespace App\Support;

use RuntimeException;

/**
 * Siehe ContentVersioning::assertNotStale() - wird von den Admin-Write-
 * Controllern in eine HTTP-409-Antwort uebersetzt (identisches Verhalten zur
 * bisherigen 409-Behandlung bei einer veralteten Git-SHA in admin.js/
 * doSave()).
 */
class ContentVersionConflictException extends RuntimeException
{
    public function __construct(public readonly string $section, public readonly int $currentVersion)
    {
        parent::__construct("Veraltete Version fuer Modul \"{$section}\" - zwischenzeitlich anderswo gespeichert.");
    }
}
