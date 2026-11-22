<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Raffle;
use App\Support\AdminUi;
use App\Support\LegacyView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;

/**
 * Base do painel /admin: layout, menu, paginação e alertas.
 */
abstract class PanelController extends Controller
{
    protected function customer(): Customer
    {
        return auth('admin')->user();
    }

    /** Rifa do cliente logado ou null. */
    protected function ownedRaffle($id): ?Raffle
    {
        return Raffle::query()->where('id', $id)->where('customer_id', $this->customer()->id)->first();
    }

    /**
     * Itens do menu (sidebar no desktop, bottom nav no mobile).
     *
     * @return array<string, array{label: string, short: string, link: string, icon: string, target: string}>
     */
    protected function modules(): array
    {
        return [
            'dashboard' => ['label' => __('admin.nav.dashboard'), 'short' => __('admin.nav.dashboard'), 'link' => url('/admin'), 'icon' => 'bi-speedometer2', 'target' => '_self'],
            'sortition' => ['label' => __('admin.nav.raffles'), 'short' => __('admin.nav.raffles_short'), 'link' => url('/admin/sortition'), 'icon' => 'bi-ticket-detailed', 'target' => '_self'],
            'settings' => ['label' => __('admin.nav.settings'), 'short' => __('admin.nav.settings_short'), 'link' => url('/admin/customer/profile'), 'icon' => 'bi-gear', 'target' => '_self'],
            'support' => ['label' => __('admin.nav.support'), 'short' => __('admin.nav.support'), 'link' => (string) config('rifa.support_url'), 'icon' => 'bi-headset', 'target' => '_blank'],
        ];
    }

    /**
     * Documento HTML do painel (sem jQuery: todas as telas usam Alpine via Vite).
     */
    protected function renderMaster(string $title, string $content): string
    {
        return LegacyView::render('admin/__master', [
            'title' => $title.' | '.__('admin.brand'),
            'content' => $content,
            'viteAssets' => $this->viteAssets(),
            'adminData' => $this->adminData(),
        ]);
    }

    protected function panel(string $title, string $content, string $currentModule): string
    {
        $customer = $this->customer();

        $panel = LegacyView::render('admin/panel', [
            'menu' => $this->menu($currentModule),
            'bottom_nav' => $this->bottomNav($currentModule),
            'menu_top' => LegacyView::render('admin/menu/topbar/index', [
                'page_title' => e($title),
                'customer.name' => e($customer->shortName()),
                'photo' => e($customer->photoUrl()),
            ]),
            'flash' => $this->sessionFlash(),
            'toasts' => LegacyView::render('admin/components/toasts'),
            'confirm' => LegacyView::render('admin/components/confirm_modal'),
            'content' => $content,
            'year' => date('Y'),
        ]);

        return $this->renderMaster($title, $panel);
    }

    private function menu(string $currentModule): string
    {
        $links = '';
        foreach ($this->modules() as $hash => $module) {
            $links .= LegacyView::render('admin/menu/sidebar/link', $this->linkVars($hash, $module, $currentModule, $module['label']));
        }

        return LegacyView::render('admin/menu/sidebar/box', ['links' => $links]);
    }

    private function bottomNav(string $currentModule): string
    {
        $links = '';
        foreach ($this->modules() as $hash => $module) {
            $links .= LegacyView::render('admin/menu/bottom/link', $this->linkVars($hash, $module, $currentModule, $module['short']));
        }

        return LegacyView::render('admin/menu/bottom/box', ['links' => $links]);
    }

    /** @param  array{label: string, short: string, link: string, icon: string, target: string}  $module */
    private function linkVars(string $hash, array $module, string $currentModule, string $label): array
    {
        $current = $hash === $currentModule;
        $external = $module['target'] === '_blank';

        return [
            'link' => e($module['link']),
            'icon' => $module['icon'],
            'label' => e($label),
            'target' => $module['target'],
            'rel' => $external ? 'rel="noopener"' : '',
            'current' => $current ? 'is-active' : '',
            'aria' => $current ? 'aria-current="page"' : '',
            'external' => $external ? '<i class="bi bi-box-arrow-up-right adm-nav__ext" aria-hidden="true"></i>' : '',
        ];
    }

    /** Tags do Vite do painel; vazio se não houver build nem dev server. */
    private function viteAssets(): string
    {
        if (! is_file(public_path('hot')) && ! is_file(public_path('build/manifest.json'))) {
            return '';
        }

        return Vite::__invoke(['resources/css/admin.css', 'resources/js/admin.js'])->toHtml();
    }

    /** window.__ADMIN__ (idioma + textos do admin.js). */
    private function adminData(): string
    {
        return json_encode([
            'locale' => str_replace('_', '-', app()->getLocale()),
            'i18n' => trans('admin_js'),
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
    }

    /** Toast vindo de redirect()->with('toast', ['success'|'danger', 'mensagem']). */
    private function sessionFlash(): string
    {
        $toast = session('toast');
        if (! is_array($toast) || count($toast) < 2) {
            return '';
        }

        [$type, $message] = array_values($toast);

        return $type === 'success' ? $this->success(e((string) $message)) : $this->danger(e((string) $message));
    }

    /**
     * Paginação do painel: "Mostrando 11–20 de 134" + anterior/próxima + páginas compactas (1 … 4 5 6 … 14).
     * $query = filtros atuais, mantidos em todos os links.
     *
     * @param  array<string, string>  $query
     */
    protected function pagination(string $baseUrl, array $query, int $total, int $page, int $perPage): string
    {
        if ($total <= 0) {
            return '';
        }

        $last = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $last));
        $link = fn (int $target) => e($baseUrl.'?'.http_build_query(array_merge($query, $target > 1 ? ['page' => $target] : [])));
        $label = fn (int $target) => e(__('admin.pagination.page', ['page' => $target]));

        $pages = '';
        foreach (AdminUi::pageItems($page, $last) as $item) {
            $pages .= match (true) {
                $item === null => '<li class="adm-pagination__gap" aria-hidden="true">…</li>',
                $item === $page => '<li><a class="adm-pagination__link is-current" href="'.$link($item).'" aria-current="page" aria-label="'.$label($item).'">'.$item.'</a></li>',
                default => '<li><a class="adm-pagination__link" href="'.$link($item).'" aria-label="'.$label($item).'">'.$item.'</a></li>',
            };
        }

        $step = function (int $target, string $key, string $icon, bool $enabled) use ($link): string {
            $content = $key === 'previous'
                ? '<i class="bi '.$icon.'" aria-hidden="true"></i><span>'.e(__('admin.pagination.'.$key)).'</span>'
                : '<span>'.e(__('admin.pagination.'.$key)).'</span><i class="bi '.$icon.'" aria-hidden="true"></i>';

            return $enabled
                ? '<li><a class="adm-pagination__link adm-pagination__step" href="'.$link($target).'" rel="'.($key === 'previous' ? 'prev' : 'next').'">'.$content.'</a></li>'
                : '<li><span class="adm-pagination__link adm-pagination__step is-disabled" aria-disabled="true">'.$content.'</span></li>';
        };

        return LegacyView::render('admin/components/pagination', [
            'summary' => e(__('admin.pagination.summary', [
                'from' => AdminUi::integer(($page - 1) * $perPage + 1),
                'to' => AdminUi::integer(min($total, $page * $perPage)),
                'total' => AdminUi::integer($total),
            ])),
            'pages' => $last > 1 ? $step($page - 1, 'previous', 'bi-chevron-left', $page > 1).$pages.$step($page + 1, 'next', 'bi-chevron-right', $page < $last) : '',
        ]);
    }

    /** Mensagem de sucesso (HTML confiável). No painel vira toast; sem JS aparece como aviso no topo. */
    protected function success(string $message): string
    {
        return $this->alert('success', $message);
    }

    /** Mensagem de erro (HTML confiável). */
    protected function danger(string $message): string
    {
        return $this->alert('danger', $message);
    }

    /** Telas fora do shell (login) sobrescrevem para usar o alerta inline legado. */
    protected function alert(string $type, string $message): string
    {
        return LegacyView::render('admin/components/flash', [
            'type' => $type,
            'icon' => $type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-octagon-fill',
            'message' => $message,
        ]);
    }

    /**
     * Mensagem de alerta a partir de ?status=...
     *
     * @param  array<string, array{0: 'success'|'danger', 1: string}>  $messages
     */
    protected function statusMessage(Request $request, array $messages): string
    {
        $status = $request->query('status');
        if (! $status || ! isset($messages[$status])) {
            return '';
        }

        [$type, $message] = $messages[$status];

        return $type === 'success' ? $this->success($message) : $this->danger($message);
    }
}
