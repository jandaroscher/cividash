<?php

namespace App\Exceptions\Integration;

use RuntimeException;

/**
 * Thrown when a publish is attempted on a Tile that originates from a different
 * external source than CIVITAS/CORE.
 *
 * Publishing such a Tile would overwrite foreign-authored provenance on the
 * broker, so it is refused outright. Locally-authored Tiles (external_source
 * null) and Tiles already provenanced to civitas-core remain publishable.
 */
class ForeignProvenanceException extends RuntimeException
{
    public static function forSource(string $source): self
    {
        return new self("Refusing to publish a tile owned by a foreign source [{$source}].");
    }
}
