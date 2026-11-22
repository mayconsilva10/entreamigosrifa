<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\LegacyView;
use Illuminate\Http\Request;

/**
 * Base do site público.
 */
abstract class SiteController extends Controller
{
    /** Colaborador logado no site (cadastro rápido por telefone). */
    protected function visitor(): ?Customer
    {
        return auth('website')->user();
    }

    protected function renderMaster(string $title, string $content, string $css = ''): string
    {
        return LegacyView::render('website/__master', [
            'title' => $title.' | Entre Amigos',
            'content' => $content,
            'css_style' => $css,
        ]);
    }

    protected function renderMasterShared(string $title, string $content, array $metas = [], string $viteAssets = ''): string
    {
        return LegacyView::render('website/shared/__master', [
            'title' => $title,
            'content' => $content,
            'css_style' => '',
            'meta_title' => e($metas['meta_title'] ?? 'Entre Amigos Rifa'),
            'meta_description' => e($metas['meta_description'] ?? ''),
            'meta_image' => $metas['meta_image'] ?? url('/'),
            'meta_image_alt' => e($metas['meta_image_alt'] ?? 'Entre Amigos Rifa'),
            'meta_image_size' => $metas['meta_image_size'] ?? '',
            'meta_url' => $metas['meta_url'] ?? url()->current(),
            'viteAssets' => $viteAssets,
        ]);
    }

    protected function header(bool $innerPage = true): string
    {
        return LegacyView::render('website/site/header', [
            'link' => $innerPage ? url('/') : '',
            'css_style' => $innerPage ? 'header-inner-pages' : '',
        ]);
    }

    protected function alert(string $css, string $message): string
    {
        return LegacyView::render('website/alert/status', ['css' => $css, 'message' => $message]);
    }

    /** @param  array<string, array{0: string, 1: string}>  $messages */
    protected function statusMessage(Request $request, array $messages): string
    {
        $status = $request->query('status');
        if (! $status || ! isset($messages[$status])) {
            return '';
        }

        return $this->alert($messages[$status][0], $messages[$status][1]);
    }
}
