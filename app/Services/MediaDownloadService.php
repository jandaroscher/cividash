<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Service for downloading media files from the source site configured in
 * seeding.media_base_url (files under /files, static assets under /assets)
 * during the seeding process.
 */
class MediaDownloadService
{
    /**
     * Download a file from the media server and save it locally.
     *
     * @param  string  $systemUrl  The systemurl from JSON (e.g., '/fm/496/SREG%20Dashboard...')
     * @param  string  $type  Type of media: 'tiles', 'categories', 'metrics', 'slider'
     * @return string|null Relative path to the downloaded file (e.g., 'seeds/tiles/filename.svg') or null on failure
     */
    public function downloadFile(string $systemUrl, string $type): ?string
    {
        if (! config('seeding.media_download_enabled', true)) {
            return null;
        }

        // Extract filename from systemUrl
        $encodedFilename = $this->extractFilename($systemUrl);
        if (empty($encodedFilename)) {
            return null;
        }

        // Decode filename for local storage
        $decodedFilename = urldecode($encodedFilename);

        // Build local path
        $storagePath = config('seeding.media_storage_path', 'seeds');
        $localDirectory = "{$storagePath}/{$type}";
        $localPath = "{$localDirectory}/{$decodedFilename}";

        // Check if file already exists (idempotency)
        if (Storage::disk('public')->exists($localPath)) {
            return $localPath;
        }

        // Download file
        try {
            $fileContent = $this->downloadFileContent($downloadUrl);
            if ($fileContent === false) {
                return null;
            }

            // Same protection as downloadAsset(): the source server answers
            // unknown paths with its HTML SPA shell under HTTP 200. Guard tile
            // and metric SVG icons so an HTML page never gets saved as a ".svg"
            // that then fails to render in the browser.
            if (str_ends_with(strtolower($decodedFilename), '.svg') && ! $this->looksLikeSvg($fileContent)) {
                Log::warning("Downloaded media file does not look like a valid SVG, skipping save: {$downloadUrl}");

                return null;
            }

            // Ensure directory exists
            Storage::disk('public')->makeDirectory($localDirectory);

            // Save file
            $saved = Storage::disk('public')->put($localPath, $fileContent);
            if (! $saved) {
                Log::warning("Failed to save media file: {$localPath}");

                return null;
            }

            return $localPath;
        } catch (\Exception $e) {
            Log::error("Error downloading media file {$downloadUrl}: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Extract systemurl from various upload data formats.
     *
     * @param  mixed  $uploadData  Can be string, array with 'systemurl', or null
     */
    public function extractSystemUrl(mixed $uploadData): ?string
    {
        if (empty($uploadData)) {
            return null;
        }

        if (is_string($uploadData)) {
            return ! empty($uploadData) ? $uploadData : null;
        }

        if (is_array($uploadData) && isset($uploadData['systemurl'])) {
            return $uploadData['systemurl'] ?? null;
        }

        return null;
    }

    /**
     * Extract filename from systemUrl.
     *
     * @param  string  $systemUrl  The systemurl (e.g., '/fm/496/SREG%20Dashboard...')
     * @return string The encoded filename (e.g., 'SREG%20Dashboard...')
     */
    public function extractFilename(string $systemUrl): string
    {
        $pathParts = explode('/', trim($systemUrl, '/'));

        return end($pathParts) ?: '';
    }

    /**
     * Download file content with explicit error handling.
     *
     * @param  string  $downloadUrl  The URL to download from
     * @return string|false File content on success, false on failure
     */
    protected function downloadFileContent(string $downloadUrl): string|false
    {
        $errorMessage = null;

        // Register temporary error handler to capture warnings
        $previousHandler = set_error_handler(function ($errno, $errstr) use (&$errorMessage) {
            $errorMessage = $errstr;

            return true; // Suppress default error handling
        });

        try {
            // Call file_get_contents without @ operator
            $fileContent = file_get_contents($downloadUrl);

            // Restore previous error handler
            restore_error_handler();

            if ($fileContent === false) {
                // Get error details from error_get_last if handler didn't capture it
                $lastError = error_get_last();
                $finalErrorMessage = $errorMessage ?? $lastError['message'] ?? 'Unknown error';

                Log::warning("Failed to download file: {$downloadUrl}", [
                    'error' => $finalErrorMessage,
                ]);
            }

            return $fileContent;
        } catch (\Throwable $e) {
            // Ensure error handler is restored even on exception
            restore_error_handler();

            Log::error("Exception downloading file {$downloadUrl}: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Download a static asset from the assets directory.
     *
     * @param  string  $assetPath  The asset path (e.g., 'handlungsfelder/umwelt_ressourcenschutz.svg')
     * @param  string  $type  Type of media: 'handlungsfelder', 'dimensions', 'sdg'
     * @return string|null Relative path to the downloaded file or null on failure
     */
    public function downloadAsset(string $assetPath, string $type): ?string
    {
        if (! config('seeding.media_download_enabled', true)) {
            return null;
        }

        // Extract filename from path
        $pathParts = explode('/', $assetPath);
        $filename = end($pathParts);

        // Build local path
        $storagePath = config('seeding.media_storage_path', 'seeds');
        $localDirectory = "{$storagePath}/{$type}";
        $localPath = "{$localDirectory}/{$filename}";

        // Check if file already exists (idempotency)
        if (Storage::disk('public')->exists($localPath)) {
            return $localPath;
        }

        // Download file
        try {
            $fileContent = $this->downloadFileContent($downloadUrl);
            if ($fileContent === false) {
                return null;
            }

            // The source server responds with HTTP 200 and its SPA's HTML
            // shell for unknown asset paths instead of a real 404 (e.g. when
            // an icon slug does not match any file on the server). Without
            // this check that HTML page gets silently saved with a ".svg"
            // extension, which then fails to render as an icon in the
            // browser.
            if (str_ends_with(strtolower($filename), '.svg') && ! $this->looksLikeSvg($fileContent)) {
                Log::warning("Downloaded asset does not look like a valid SVG, skipping save: {$downloadUrl}");

                return null;
            }

            // Ensure directory exists
            Storage::disk('public')->makeDirectory($localDirectory);

            // Save file
            $saved = Storage::disk('public')->put($localPath, $fileContent);
            if (! $saved) {
                Log::warning("Failed to save asset: {$localPath}");

                return null;
            }

            return $localPath;
        } catch (\Exception $e) {
            Log::error("Error downloading asset {$downloadUrl}: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Heuristically check whether downloaded content is a valid SVG document
     * rather than e.g. an HTML error/fallback page served with a 200 status.
     *
     * @param  string  $content  Raw downloaded file content.
     */
    protected function looksLikeSvg(string $content): bool
    {
        return stripos(substr($content, 0, 1024), '<svg') !== false;
    }
}
