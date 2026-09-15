<?php

namespace App\Support;

use RuntimeException;

/** Phase 5B.1: wird vom Controller in eine 422-Antwort uebersetzt - eine Aussage ueber die HOCHGELADENE DATEI, nicht ueber den Server. */
class MediaValidationException extends RuntimeException
{
}
