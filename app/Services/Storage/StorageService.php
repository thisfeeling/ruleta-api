<?php

namespace App\Services\Storage;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorageService
{
    protected string $disk = 's3';

    /**
     * Upload file to S3
     */
    public function upload(string $path, $content, array $options = []): array
    {
        $fullPath = $this->normalizePath($path);

        Storage::disk($this->disk)->put($fullPath, $content, array_merge([
            'visibility' => 'private',
            'ContentType' => $this->guessContentType($fullPath),
        ], $options));

        return [
            'path' => $fullPath,
            'url' => $this->getUrl($fullPath),
            'signed_url' => $this->getSignedUrl($fullPath),
        ];
    }

    /**
     * Upload audio file
     */
    public function uploadAudio(string $filename, $content, string $subfolder = 'generated'): array
    {
        $path = "audio/{$subfolder}/" . Str::slug(pathinfo($filename, PATHINFO_FILENAME)) . '.mp3';
        return $this->upload($path, $content);
    }

    /**
     * Upload recording (spell game)
     */
    public function uploadRecording(int $showId, int $playerId, $content): array
    {
        $filename = "recordings/show-{$showId}/player-{$playerId}-" . now()->timestamp . ".mp3";
        return $this->upload($filename, $content);
    }

    /**
     * Upload audit backup
     */
    public function uploadAuditBackup(int $showId, string $date, array $logs): string
    {
        $filename = "audit/show-{$showId}/{$date}.json";
        $this->upload($filename, json_encode($logs));
        return $filename;
    }

    /**
     * Get public URL
     */
    public function getUrl(string $path): string
    {
        return Storage::disk($this->disk)->url($path);
    }

    /**
     * Get signed (temporary) URL
     */
    public function getSignedUrl(string $path, int $expiresInMinutes = 60): string
    {
        return Storage::disk($this->disk)->temporaryUrl(
            $path,
            now()->addMinutes($expiresInMinutes)
        );
    }

    /**
     * Check if file exists
     */
    public function exists(string $path): bool
    {
        return Storage::disk($this->disk)->exists($path);
    }

    /**
     * Delete file
     */
    public function delete(string $path): bool
    {
        return Storage::disk($this->disk)->delete($path);
    }

    /**
     * Get file size in bytes
     */
    public function size(string $path): int
    {
        return Storage::disk($this->disk)->size($path);
    }

    /**
     * Download file content
     */
    public function get(string $path): string
    {
        return Storage::disk($this->disk)->get($path);
    }

    // Helpers
    protected function normalizePath(string $path): string
    {
        return ltrim($path, '/');
    }

    protected function guessContentType(string $path): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return match($extension) {
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'json' => 'application/json',
            'txt' => 'text/plain',
            default => 'application/octet-stream',
        };
    }
}
