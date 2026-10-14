<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecureDocumentStorage
{
    /** Store document bytes encrypted; filenames deliberately contain no personal data. */
    public function store(UploadedFile $file, string $directory): string
    {
        $path = trim($directory, '/').'/'.bin2hex(random_bytes(16)).'.enc';
        Storage::disk('local')->put($path, Crypt::encryptString($file->get()));

        return $path;
    }

    public function download(string $path, string $downloadName): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($path), 404);
        $contents = Crypt::decryptString(Storage::disk('local')->get($path));

        return response()->streamDownload(static function () use ($contents): void {
            echo $contents;
        }, $downloadName, ['Cache-Control' => 'no-store, private']);
    }
}
