<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Raffle;

/**
 * Trechos de tela usados tanto no painel quanto na página pública da rifa.
 */
class RaffleHelpers
{
    public static function socialIcon(string $network): string
    {
        return match ($network) {
            'facebook' => 'ri-facebook-fill',
            'youtube' => 'ri-youtube-fill',
            'instagram' => 'ri-instagram-fill',
            'whats_support' => 'ri-whatsapp-fill',
            'whats_group' => 'ri-group-fill',
            default => '',
        };
    }

    /** Links de redes sociais do dono da rifa. */
    public static function socialLinks(Customer $owner): string
    {
        $html = '';
        foreach ($owner->settingsArray()['social'] as $network => $link) {
            if ($link !== '') {
                $html .= LegacyView::render('website/shared/customers/social', ['link' => e($link), 'icon' => self::socialIcon($network)]);
            }
        }

        return $html;
    }

    /**
     * Carrossel de fotos do prêmio.
     *
     * @return array{photos: string, photos_all: string, first: ?string}
     */
    public static function photos(Raffle $raffle): array
    {
        $photos = $photosAll = '';
        $list = $raffle->photosList();

        foreach ($list as $key => $path) {
            $vars = ['key' => $key, 'local' => Media::url($path), 'active' => $key == 1 ? 'active' : ''];
            $photos .= LegacyView::render('website/shared/photos', $vars);
            $photosAll .= LegacyView::render('website/shared/photos_all', $vars);
        }

        return ['photos' => $photos, 'photos_all' => $photosAll, 'first' => isset($list[0]) ? Media::url($list[0]) : null];
    }

    /** PIX "copia e cola" + QR Code para pagar o dono da rifa. */
    public static function ownerPix(Customer $owner, float $total, string $numbersDescription): string
    {
        $pix = $owner->settingsArray()['payments']['pix'];
        $key = trim($pix['pix_key']);

        if ($key === '') {
            return '<div class="alert alert-warning">'.e(__('raffle.pix_missing')).'</div>';
        }

        return (new Pix(
            pixKey: $pix['type_key'] === 'phone' ? '+55'.$key : $key,
            merchantName: (string) $owner->name,
            merchantCity: (string) config('rifa.pix_city'),
            description: __('raffle.pix_description', ['numbers' => $numbersDescription]),
            amount: $total,
        ))->render();
    }

    public static function dateLottery(Raffle $raffle): string
    {
        return $raffle->draw_date
            ? $raffle->draw_date->locale(app()->getLocale())->translatedFormat(__('common.datetime_format'))
            : __('common.to_be_defined');
    }
}
