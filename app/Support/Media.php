<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Caminhos de imagens enviadas.
 *
 * O banco guarda caminhos relativos à raiz pública, ex.: "storage/images/photos/2026/11/foto.jpg".
 * Registros vindos do sistema antigo usam "storage/app/public/images/..." e são normalizados aqui.
 */
class Media
{
    public static function normalize(?string $path): string
    {
        $path = ltrim((string) $path, '/');

        return str_starts_with($path, 'storage/app/public/')
            ? 'storage/'.substr($path, strlen('storage/app/public/'))
            : $path;
    }

    /** URL absoluta de uma imagem (aceita URL externa, ex.: foto do Google). */
    public static function url(?string $path, ?string $default = null): string
    {
        if (empty($path)) {
            return $default ? url($default) : '';
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        return url(self::normalize($path));
    }

    /** Caminho no disco "public" a partir do caminho salvo no banco. */
    public static function diskPath(string $path): ?string
    {
        $path = self::normalize($path);

        return str_starts_with($path, 'storage/') ? substr($path, strlen('storage/')) : null;
    }

    public static function exists(?string $path): bool
    {
        $diskPath = $path ? self::diskPath($path) : null;

        return $diskPath !== null && Storage::disk('public')->exists($diskPath);
    }

    public static function delete(?string $path): void
    {
        if (self::exists($path)) {
            Storage::disk('public')->delete(self::diskPath($path));
        }
    }

    /** Salva a imagem em storage/app/public/images/{folder}/AAAA/MM e retorna o caminho para o banco. */
    public static function storeImage(UploadedFile $file, string $folder): string
    {
        $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'-'.Str::random(6).'.'.$file->extension();
        $stored = $file->storeAs('images/'.$folder.'/'.date('Y/m'), $name, 'public');

        return 'storage/'.$stored;
    }
}
