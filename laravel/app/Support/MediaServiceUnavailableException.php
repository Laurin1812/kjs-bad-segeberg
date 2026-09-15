<?php

namespace App\Support;

use RuntimeException;

/** Phase 5B.1: wird vom Controller in eine 503-Antwort uebersetzt - eine Aussage ueber den SERVER (z.B. fehlende GD-Erweiterung), nicht ueber die hochgeladene Datei. */
class MediaServiceUnavailableException extends RuntimeException
{
}
