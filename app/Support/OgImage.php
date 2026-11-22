<?php

namespace App\Support;

use App\Models\Raffle;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Card de prévia (Open Graph) 1200×630 para WhatsApp, Telegram, Facebook, X...
 *
 * Foto do prêmio à esquerda; à direita título, preço por número, data do sorteio,
 * barra de vendidos e chamada para escolher os números.
 * O arquivo é gerado sob demanda em storage/app/public/og e reaproveitado enquanto
 * os dados exibidos não mudarem (o hash dos dados vai no nome do arquivo, o que
 * também força as redes a buscarem a imagem nova quando algo muda).
 */
class OgImage
{
    private const W = 1200;

    private const H = 630;

    private const SPLIT = 580; // largura do painel da foto

    /** URL pública do card; null se não for possível gerar (sem GD, erro...). */
    public static function url(Raffle $raffle, int $soldPercent): ?string
    {
        if (! function_exists('imagettftext')) {
            return null;
        }

        $data = self::data($raffle, $soldPercent);
        $file = 'og/'.$raffle->id.'-'.substr(md5(json_encode($data)), 0, 12).'.jpg';
        $disk = Storage::disk('public');

        if (! $disk->exists($file)) {
            try {
                $jpeg = self::render($data);
            } catch (Throwable $e) {
                Log::warning('OgImage: falha ao gerar card', ['raffle' => $raffle->id, 'error' => $e->getMessage()]);

                return null;
            }

            // Remove versões antigas desta rifa
            foreach ($disk->files('og') as $old) {
                if (str_starts_with(basename($old), $raffle->id.'-')) {
                    $disk->delete($old);
                }
            }
            $disk->put($file, $jpeg);
        }

        return url('storage/'.$file);
    }

    private static function data(Raffle $raffle, int $soldPercent): array
    {
        return [
            'v' => 2, // incrementar ao mudar o layout
            'locale' => app()->getLocale(),
            'title' => trim(html_entity_decode(strip_tags((string) $raffle->title))),
            'price' => $raffle->formatMoney($raffle->number_price),
            'date' => RaffleHelpers::dateLottery($raffle),
            'total' => $raffle->total_numbers,
            'sold' => max(0, min(100, $soldPercent)),
            'photo' => $raffle->photosList()[0] ?? null,
            'labels' => [
                'badge' => __('raffle.og_card_badge'),
                'per' => __('raffle.og_card_per_number'),
                'draw' => __('raffle.og_card_draw'),
                'sold' => __('raffle.og_card_sold', ['percent' => max(0, min(100, $soldPercent))]),
                'numbers' => __('raffle.og_card_numbers', ['count' => number_format($raffle->total_numbers, 0, ',', str_starts_with(app()->getLocale(), 'en') ? ',' : '.')]),
                'cta' => __('raffle.og_card_cta'),
                'brand' => 'Entre Amigos Rifa',
            ],
        ];
    }

    private static function render(array $d): string
    {
        $img = imagecreatetruecolor(self::W, self::H);
        imagealphablending($img, true);

        $white = self::color($img, '#ffffff');
        $ink = self::color($img, '#1f1235');
        $green = self::color($img, '#16a34a');
        $greenLight = self::color($img, '#4ade80');
        $lilac = self::color($img, '#e9d5ff');
        $lilacSoft = self::color($img, '#c4b5fd');
        $track = self::color($img, '#3b1d6e');

        // Painel da foto: fundo claro + foto inteira (contain), sem cortar o prêmio
        imagefilledrectangle($img, 0, 0, self::SPLIT - 1, self::H - 1, $white);
        self::drawPhoto($img, $d['photo'], 28, 28, self::SPLIT - 56, self::H - 56);

        // Painel de texto: gradiente roxo diagonal
        self::gradient($img, self::SPLIT, 0, self::W - self::SPLIT, self::H, [91, 33, 182], [46, 16, 101], true);
        // Brilho decorativo
        imagefilledellipse($img, self::W - 40, 40, 360, 360, imagecolorallocatealpha($img, 255, 255, 255, 120));
        imagefilledellipse($img, self::W - 20, self::H + 40, 300, 300, imagecolorallocatealpha($img, 74, 222, 128, 118));

        $x = self::SPLIT + 52;
        $maxW = self::W - $x - 48;

        // Selo "RIFA ONLINE"
        $y = 58;
        self::pill($img, $x, $y, $d['labels']['badge'], self::font('Bold'), 15, $green, $white, 16, 8);

        // Título (até 2 linhas, reticências se passar)
        $y += 58;
        $titleSize = match (true) {
            mb_strlen($d['title']) > 40 => 34, mb_strlen($d['title']) > 20 => 42, default => 50
        };
        $lines = self::wrap(mb_strtoupper($d['title']), self::font('ExtraBold'), $titleSize, $maxW, 2);
        foreach ($lines as $line) {
            $y += (int) ($titleSize * 1.25);
            imagettftext($img, $titleSize, 0, $x, $y, $white, self::font('ExtraBold'), $line);
        }

        // Preço
        $y += 34;
        $priceSize = 54;
        $box = self::box(self::font('ExtraBold'), $priceSize, $d['price']);
        $pillH = $box['h'] + 36;
        self::rounded($img, $x, $y, $x + $box['w'] + 44, $y + $pillH, 18, $white);
        imagettftext($img, $priceSize, 0, $x + 22, $y + 18 + $box['h'], $green, self::font('ExtraBold'), $d['price']);
        imagettftext($img, 20, 0, $x + $box['w'] + 62, $y + 18 + $box['h'] - 4, $lilac, self::font('Medium'), $d['labels']['per']);

        // Data do sorteio
        $y += $pillH + 46;
        imagettftext($img, 17, 0, $x, $y, $lilacSoft, self::font('SemiBold'), mb_strtoupper($d['labels']['draw']));
        $labelW = self::box(self::font('SemiBold'), 17, mb_strtoupper($d['labels']['draw']))['w'];
        imagettftext($img, 22, 0, $x + $labelW + 12, $y + 1, $white, self::font('Bold'), $d['date']);

        // Barra de vendidos
        $y += 26;
        $barW = $maxW;
        self::rounded($img, $x, $y, $x + $barW, $y + 14, 7, $track);
        $fill = (int) round($barW * $d['sold'] / 100);
        self::rounded($img, $x, $y, $x + max(14, $fill), $y + 14, 7, $greenLight);
        $y += 40;
        imagettftext($img, 17, 0, $x, $y, $white, self::font('SemiBold'), $d['labels']['sold']);
        $numW = self::box(self::font('Medium'), 17, $d['labels']['numbers'])['w'];
        imagettftext($img, 17, 0, $x + $barW - $numW, $y, $lilac, self::font('Medium'), $d['labels']['numbers']);

        // Chamada (CTA) presa ao rodapé
        $ctaH = 66;
        $ctaY = self::H - 52 - $ctaH;
        $ctaY = max($ctaY, $y + 26);
        self::rounded($img, $x, $ctaY + 5, $x + $maxW, $ctaY + $ctaH + 5, 16, self::color($img, '#0f5132'));
        self::rounded($img, $x, $ctaY, $x + $maxW, $ctaY + $ctaH, 16, $green);
        $cta = $d['labels']['cta'];
        $ctaSize = 24;
        while ($ctaSize > 14 && self::box(self::font('Bold'), $ctaSize, $cta)['w'] > $maxW - 48) {
            $ctaSize--;
        }
        $ctaBox = self::box(self::font('Bold'), $ctaSize, $cta);
        imagettftext($img, $ctaSize, 0, $x + (int) (($maxW - $ctaBox['w']) / 2), $ctaY + (int) (($ctaH + $ctaBox['h']) / 2), $white, self::font('Bold'), $cta);

        // Marca
        imagettftext($img, 14, 0, $x, self::H - 20, $lilacSoft, self::font('SemiBold'), $d['labels']['brand']);

        ob_start();
        imagejpeg($img, null, 86);
        imagedestroy($img);

        return (string) ob_get_clean();
    }

    private static function drawPhoto(\GdImage $img, ?string $photo, int $x, int $y, int $w, int $h): void
    {
        $src = $photo ? self::loadImage($photo) : null;
        if (! $src) {
            $src = self::loadImage('storage/images/system/logo.png');
        }
        if (! $src) {
            return;
        }

        $sw = imagesx($src);
        $sh = imagesy($src);
        $scale = min($w / $sw, $h / $sh);
        $dw = (int) round($sw * $scale);
        $dh = (int) round($sh * $scale);
        imagecopyresampled($img, $src, $x + (int) (($w - $dw) / 2), $y + (int) (($h - $dh) / 2), 0, 0, $dw, $dh, $sw, $sh);
        imagedestroy($src);
    }

    private static function loadImage(string $path): ?\GdImage
    {
        $bytes = null;
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            $response = Http::timeout(5)->get($path);
            $bytes = $response->successful() ? $response->body() : null;
        } elseif (($disk = Media::diskPath($path)) !== null && Storage::disk('public')->exists($disk)) {
            $bytes = Storage::disk('public')->get($disk);
        } elseif (is_file(public_path(Media::normalize($path)))) {
            $bytes = file_get_contents(public_path(Media::normalize($path)));
        }

        $image = $bytes ? @imagecreatefromstring($bytes) : false;

        return $image ?: null;
    }

    private static function font(string $weight): string
    {
        return resource_path('fonts/Poppins-'.$weight.'.ttf');
    }

    private static function color(\GdImage $img, string $hex): int
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        return imagecolorallocate($img, $r, $g, $b);
    }

    /** @return array{w:int,h:int} largura e altura de cap (sem descendentes) do texto */
    private static function box(string $font, int $size, string $text): array
    {
        $b = imagettfbbox($size, 0, $font, $text);
        $cap = imagettfbbox($size, 0, $font, 'H');

        return ['w' => $b[2] - $b[0], 'h' => $cap[1] - $cap[7]];
    }

    /** @return array<int, string> */
    private static function wrap(string $text, string $font, int $size, int $maxW, int $maxLines): array
    {
        $lines = [];
        $current = '';
        $words = preg_split('/\s+/u', $text) ?: [];

        foreach ($words as $i => $word) {
            $try = $current === '' ? $word : $current.' '.$word;
            if (self::box($font, $size, $try)['w'] <= $maxW || $current === '') {
                $current = $try;

                continue;
            }
            $lines[] = $current;
            $current = $word;
            if (count($lines) === $maxLines) {
                $current = '';
                $lines[$maxLines - 1] = self::ellipsis($lines[$maxLines - 1].' '.implode(' ', array_slice($words, $i)), $font, $size, $maxW);
                break;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }

        return array_map(fn ($l) => self::box($font, $size, $l)['w'] > $maxW ? self::ellipsis($l, $font, $size, $maxW) : $l, $lines);
    }

    private static function ellipsis(string $text, string $font, int $size, int $maxW): string
    {
        if (self::box($font, $size, $text)['w'] <= $maxW) {
            return $text;
        }
        while (mb_strlen($text) > 1 && self::box($font, $size, $text.'…')['w'] > $maxW) {
            $text = rtrim(mb_substr($text, 0, -1));
        }

        return $text.'…';
    }

    private static function pill(\GdImage $img, int $x, int $y, string $text, string $font, int $size, int $bg, int $fg, int $padX, int $padY): void
    {
        $box = self::box($font, $size, $text);
        self::rounded($img, $x, $y, $x + $box['w'] + $padX * 2, $y + $box['h'] + $padY * 2, (int) (($box['h'] + $padY * 2) / 2), $bg);
        imagettftext($img, $size, 0, $x + $padX, $y + $padY + $box['h'], $fg, $font, $text);
    }

    private static function rounded(\GdImage $img, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
    {
        $r = min($r, (int) (($x2 - $x1) / 2), (int) (($y2 - $y1) / 2));
        imagefilledrectangle($img, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledrectangle($img, $x1, $y1 + $r, $x2, $y2 - $r, $color);
        foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) {
            imagefilledellipse($img, $cx, $cy, $r * 2, $r * 2, $color);
        }
    }

    private static function gradient(\GdImage $img, int $x, int $y, int $w, int $h, array $from, array $to, bool $diagonal): void
    {
        $steps = $diagonal ? $w + $h : $h;
        for ($i = 0; $i < $steps; $i++) {
            $t = $i / max(1, $steps - 1);
            $c = imagecolorallocate($img,
                (int) ($from[0] + ($to[0] - $from[0]) * $t),
                (int) ($from[1] + ($to[1] - $from[1]) * $t),
                (int) ($from[2] + ($to[2] - $from[2]) * $t));
            if ($diagonal) {
                // linha diagonal x+y = i, recortada ao retângulo
                $x0 = max(0, $i - $h);
                $x1 = min($w - 1, $i);
                imageline($img, $x + $x0, $y + $i - $x0, $x + $x1, $y + $i - $x1, $c);
            } else {
                imageline($img, $x, $y + $i, $x + $w - 1, $y + $i, $c);
            }
        }
    }
}
