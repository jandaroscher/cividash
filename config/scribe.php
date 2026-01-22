<?php

/*
|--------------------------------------------------------------------------
| Scribe API Documentation Configuration
|--------------------------------------------------------------------------
|
| Scribe is a dev dependency - this config is only used during doc generation.
| We return an empty array if Scribe is not installed to prevent errors.
|
*/

if (!class_exists(\Knuckles\Scribe\Scribe::class)) {
    return [];
}

/*
|--------------------------------------------------------------------------
| Load Scribe-dependent configuration
|--------------------------------------------------------------------------
|
| The actual config with Scribe class references is in a separate file
| that's only loaded when Scribe is available.
|
*/

return require __DIR__ . '/scribe-config.php';
