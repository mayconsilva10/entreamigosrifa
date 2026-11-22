<?php

namespace App\Http\Controllers\Admin;

use App\Models\Customer;
use App\Models\PlatformSetting;
use App\Models\Raffle;
use App\Services\AdminDashboard;
use App\Services\AdminRaffleList;
use App\Services\AdminRaffleManage;
use App\Services\LoteriaFederal;
use App\Support\AdminUi;
use App\Support\LegacyView;
use App\Support\Media;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Gestão das rifas do dono (painel /admin/sortition — a URL mantém o nome legado).
 */
class RaffleController extends PanelController
{
    /** Tamanho máximo do código do link de compartilhamento. */
    public const SHARE_CODE_MAX = 100;

    /** Tamanho máximo de cada foto do prêmio (KB) — validado também no navegador. */
    public const PHOTO_MAX_KB = 5120;

    /** Quantidade máxima de números por rifa. */
    public const NUMBERS_MAX = 100000;

    private function status(Request $request): string
    {
        $types = [
            'created' => 'success', 'updated' => 'success', 'deleted' => 'success', 'created_winner' => 'success',
            'deleted_winner' => 'success', 'duplicate_register_winner' => 'danger', 'number_exceeded' => 'danger',
            'winner_not_exists' => 'danger', 'no_delete_status_dif' => 'danger', 'exists_numbers_selecteds' => 'danger',
            'update_finish_sortition' => 'success', 'mp_approved' => 'success', 'mp_not_approved' => 'danger',
            'numbers_confirmed' => 'success', 'numbers_cancelled' => 'success', 'numbers_assigned' => 'success',
            'numbers_unavailable' => 'danger', 'nothing_changed' => 'danger',
        ];

        // Traduções são confiáveis; só mp_not_approved contém HTML (<b>).
        $messages = [];
        foreach ($types as $key => $type) {
            $text = __('admin.raffles.flash.'.$key);
            $messages[$key] = [$type, $key === 'mp_not_approved' ? $text : e($text)];
        }

        return $this->statusMessage($request, $messages);
    }

    /** Minhas Rifas: busca, chips por status com contagem, ordenação e paginação (sem jQuery). */
    public function index(Request $request, AdminRaffleList $list): string
    {
        $result = $list->search($this->customer(), $request->query());
        $filters = array_filter([
            'status' => $result['status'],
            'q' => $result['q'],
            'sort' => $result['sort'] === 'recent' ? '' : $result['sort'],
        ], fn ($value) => $value !== '');

        $rows = '';
        foreach ($result['items'] as $stats) {
            $rows .= $this->raffleRow($stats);
        }

        $clear = '<a class="adm-btn adm-btn--secondary" href="'.e(url('/admin/sortition')).'"><i class="bi bi-x-circle" aria-hidden="true"></i>'
            .e(__('admin.raffles.toolbar.clear')).'</a>';

        $body = match (true) {
            $rows !== '' => LegacyView::render('admin/modules/sortition/table', ['rows' => $rows]),
            $result['owned'] === 0 => LegacyView::render('admin/components/empty_state', [
                'icon' => 'bi-ticket-detailed',
                'title' => e(__('admin.raffles.empty.none_title')),
                'text' => e(__('admin.raffles.empty.none_text')),
                'cta' => '<a class="adm-btn adm-btn--primary" href="'.e(url('/admin/sortition/create')).'"><i class="bi bi-plus-lg" aria-hidden="true"></i>'
                    .e(__('admin.raffles.empty.none_cta')).'</a>',
            ]),
            default => LegacyView::render('admin/components/empty_state', [
                'icon' => 'bi-search',
                'title' => e(__('admin.raffles.empty.filter_title')),
                'text' => e($result['q'] !== '' ? __('admin.raffles.empty.filter_query', ['q' => $result['q']]) : __('admin.raffles.empty.filter_text')),
                'cta' => $clear,
            ]),
        };

        $content = LegacyView::render('admin/modules/sortition/index', [
            'header' => LegacyView::render('admin/components/page_header', [
                'breadcrumb' => '',
                'title' => e(__('admin.nav.raffles')),
                'subtitle' => '<p class="adm-page-header__subtitle">'
                    .e(trans_choice('admin.raffles.subtitle', $result['owned'], ['count' => AdminUi::integer($result['owned'])])).'</p>',
                'actions' => '<a class="adm-btn adm-btn--primary" href="'.e(url('/admin/sortition/create')).'">'
                    .'<i class="bi bi-plus-lg" aria-hidden="true"></i>'.e(__('admin.nav.new_raffle')).'</a>',
            ]),
            'flash' => $this->status($request),
            'toolbar' => $result['owned'] === 0 ? '' : $this->toolbar($result, $filters),
            'body' => $body,
            'pagination' => $this->pagination(url('/admin/sortition'), $filters, $result['total'], $result['page'], $result['per_page']),
            'share_base' => e(url('/share').'/'),
            'link_endpoint' => e(url('/api/v1/sortition/newCodeShared')),
            'link_max' => self::SHARE_CODE_MAX,
        ]);

        return $this->panel(__('admin.title.raffles'), $content, 'sortition');
    }

    /**
     * Busca + chips de status (com contagem) + ordenação; tudo via query string (?status=&q=&sort=).
     *
     * @param  array{status: string, q: string, sort: string, counts: array<string, int>}  $result
     * @param  array<string, string>  $filters
     */
    private function toolbar(array $result, array $filters): string
    {
        $chips = '';
        foreach (['all' => '', ...array_combine(array_keys(AdminRaffleList::STATUSES), array_keys(AdminRaffleList::STATUSES))] as $key => $value) {
            $active = $result['status'] === $value;
            $query = array_filter(array_merge($filters, ['status' => $value]), fn ($v) => $v !== '');

            $chips .= '<a class="adm-chip'.($active ? ' is-active' : '').'" href="'.e(url('/admin/sortition').($query ? '?'.http_build_query($query) : '')).'"'
                .($active ? ' aria-current="true"' : '').'>'
                .e(__('admin.raffles.chips.'.$key))
                .'<span class="adm-chip__count">'.AdminUi::integer($result['counts'][$key]).'</span></a>';
        }

        $sorts = '';
        foreach (AdminRaffleList::SORTS as $sort) {
            $sorts .= '<option value="'.$sort.'"'.($result['sort'] === $sort ? ' selected' : '').'>'.e(__('admin.raffles.toolbar.sort_'.$sort)).'</option>';
        }

        $filtered = $result['status'] !== '' || $result['q'] !== '';

        return LegacyView::render('admin/components/table_toolbar', [
            'action' => e(url('/admin/sortition')),
            'search_label' => e(__('admin.raffles.toolbar.search_label')),
            'search_placeholder' => e(__('admin.raffles.toolbar.search_placeholder')),
            'submit' => e(__('admin.raffles.toolbar.submit')),
            'q' => e($result['q']),
            'autofocus' => $result['q'] !== '' ? 'autofocus' : '',
            'hidden' => $result['status'] !== '' ? '<input type="hidden" name="status" value="'.e($result['status']).'">' : '',
            'chips_label' => e(__('admin.raffles.toolbar.filters')),
            'chips' => $chips,
            'sort_label' => e(__('admin.raffles.toolbar.sort_label')),
            'sorts' => $sorts,
            'clear' => $filtered
                ? '<a class="adm-btn adm-btn--ghost adm-btn--sm" href="'.e(url('/admin/sortition')).'"><i class="bi bi-x-circle" aria-hidden="true"></i>'
                    .e(__('admin.raffles.toolbar.clear')).'</a>'
                : '',
        ]);
    }

    /** Item do menu ⋯ (link ou botão). $attrs já escapado. */
    private function menuItem(string $icon, string $label, string $attrs, bool $button = false, bool $danger = false): string
    {
        $tag = $button ? 'button' : 'a';

        return '<li><'.$tag.($button ? ' type="button"' : '').' class="dropdown-item'.($danger ? ' adm-menu__danger' : '').'" '.$attrs.'>'
            .'<i class="bi '.$icon.'" aria-hidden="true"></i>'.e($label).'</'.$tag.'></li>';
    }

    /** @param  array{raffle: Raffle, currency: string, paid: int, reserved: int, free: int, total: int, percent: int, paid_amount: float, reserved_amount: float}  $stats */
    private function raffleRow(array $stats): string
    {
        $raffle = $stats['raffle'];
        $photo = $raffle->photosList()[0] ?? null;
        $shareUrl = url('/share/'.$raffle->share_code);
        $total = max(1, $stats['total']);
        // Mesma regra do destroy(): só rifa aguardando liberação e sem números escolhidos.
        $deletable = $raffle->status === Raffle::STATUS_AWAITING_PAYMENT && $stats['paid'] + $stats['reserved'] === 0;
        $winnersLink = 'href="'.e(url('/admin/sortition/'.$raffle->id.'/winnerLottery')).'"';

        $menu = $this->menuItem('bi-share', __('admin.raffles.menu.share'), '@click="openDialog(\'share\', $el)"', button: true)
            .$this->menuItem('bi-link-45deg', __('admin.raffles.menu.edit_link'), '@click="openDialog(\'link\', $el)"', button: true)
            .$this->menuItem('bi-eye', __('admin.raffles.menu.view'), 'href="'.e(url('/admin/sortition/viewSortition/'.$raffle->share_code)).'" target="_blank" rel="noopener" data-preview-link')
            .'<li><hr class="dropdown-divider"></li>'
            .$this->menuItem('bi-pencil', __('admin.raffles.menu.edit'), 'href="'.e(url('/admin/sortition/'.$raffle->id.'/edit')).'"')
            .match ($raffle->status) {
                Raffle::STATUS_AWAITING_PAYMENT => $this->menuItem('bi-credit-card', __('admin.raffles.menu.pay'), 'href="'.e(url('/admin/'.$raffle->id.'/checkout')).'"'),
                Raffle::STATUS_ACTIVE => $this->menuItem('bi-flag', __('admin.raffles.menu.finish'), $winnersLink),
                default => $this->menuItem('bi-trophy', __('admin.raffles.menu.winners'), $winnersLink),
            }
        .($deletable
            ? '<li><hr class="dropdown-divider"></li>'.$this->menuItem('bi-trash', __('admin.raffles.menu.delete'), '@click="openDialog(\'delete\', $el)"', button: true, danger: true)
            : '');

        $pendingText = e(trans_choice('admin.raffles.row.pending', $stats['reserved'], ['count' => AdminUi::integer($stats['reserved'])]));
        $pending = $stats['reserved'] > 0
            ? '<a class="adm-badge adm-badge--pending adm-badge--link" href="'.e(url('/admin/sortition/'.$raffle->id.'/list')).'" title="'.$pendingText.'">'
                .'<i class="bi bi-hourglass-split" aria-hidden="true"></i>'.AdminUi::integer($stats['reserved'])
                .'<span class="visually-hidden"> '.$pendingText.'</span></a>'
            : '<span class="adm-rl__none" title="'.e(__('admin.raffles.row.no_pending')).'"><span aria-hidden="true">—</span>'
                .'<span class="visually-hidden">'.e(__('admin.raffles.row.no_pending')).'</span></span>';

        return LegacyView::render('admin/modules/sortition/row', [
            'id' => $raffle->id,
            'title' => e($raffle->title),
            'code' => e($raffle->share_code),
            'url' => e($shareUrl),
            'short_url' => e(preg_replace('#^https?://#', '', $shareUrl)),
            'preview_base' => e(url('/admin/sortition/viewSortition').'/'),
            'photo' => e(Media::exists($photo) ? Media::url($photo) : url('storage/images/system/default-placeholder.png')),
            'manage' => e(url('/admin/sortition/'.$raffle->id.'/listSortition')),
            'copy_label' => e(__('admin.raffles.row.copy_link', ['title' => $raffle->title])),
            'more_label' => e(__('admin.raffles.row.more', ['title' => $raffle->title])),
            'badge' => AdminUi::badge(AdminUi::raffleStatus($raffle->status, $raffle->winnersList() !== [])),
            'price' => e($raffle->formatMoney((float) $raffle->number_price)),
            'sold' => AdminUi::integer($stats['paid']),
            'total' => AdminUi::integer($stats['total']),
            'paid_pct' => round($stats['paid'] * 100 / $total, 2),
            'reserved_pct' => round($stats['reserved'] * 100 / $total, 2),
            'bar_label' => e(__('admin.raffles.row.sold_label', [
                'paid' => AdminUi::integer($stats['paid']),
                'reserved' => AdminUi::integer($stats['reserved']),
                'free' => AdminUi::integer($stats['free']),
            ])),
            'pending' => $pending,
            'draw_date' => e($raffle->draw_date ? $raffle->draw_date->translatedFormat(__('common.datetime_format')) : __('admin.raffles.row.no_date')),
            'draw_relative' => $raffle->draw_date && $raffle->status !== Raffle::STATUS_FINISHED
                ? '<span class="adm-rl__sub">'.e(AdminUi::drawWhen($raffle->draw_date)).'</span>'
                : '',
            'menu' => $menu,
        ]);
    }

    public function create(Request $request): string
    {
        return $this->panel(__('admin.title.raffles'), $this->formPage($request, null), 'sortition');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateForm($request);
        $customerId = $this->customer()->id;

        Raffle::query()->create([
            'share_code' => rand(100000, 900000).date('YmdHi').$customerId,
            'customer_id' => $customerId,
            'number_price' => $data['price'],
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'draw_rules' => $data['draw_rules'] ?? '',
            'numbers' => json_encode(Raffle::generateNumbers((int) $data['generated_numbers'])),
            'photos' => json_encode($this->uploadPhotos($request)),
            'status' => Raffle::STATUS_AWAITING_PAYMENT,
            'total_numbers' => (int) $data['generated_numbers'],
            'draw_date' => $data['date_sortition'] ?? null,
        ]);

        return redirect('/admin/sortition?status=created');
    }

    public function edit(Request $request, int $raffleId): RedirectResponse|string
    {
        $raffle = $this->ownedRaffle($raffleId);
        if (! $raffle) {
            return redirect('/admin/sortition');
        }

        return $this->panel(__('admin.title.raffles'), $this->formPage($request, $raffle), 'sortition');
    }

    /**
     * Formulário de criação/edição: seções numeradas (prêmio, números e preço, sorteio), resumo ao vivo,
     * fotos com drag & drop e erros do servidor ao lado de cada campo (old() + bag de erros da sessão).
     */
    private function formPage(Request $request, ?Raffle $raffle): string
    {
        $editing = $raffle !== null;
        $errors = session('errors')?->getBag('default');
        $locked = $editing && $raffle->status !== Raffle::STATUS_AWAITING_PAYMENT;
        $currency = $raffle?->currency ?: (string) config('rifa.default_currency', 'BRL');
        [$decimal, $thousands] = Money::separators();

        $priceValue = Money::parse((string) $request->old('price', $editing ? number_format((float) $raffle->number_price, 2, '.', '') : ''));
        $drawDate = (string) $request->old('date_sortition', $raffle?->draw_date?->format('Y-m-d\TH:i') ?? '');
        $now = now()->format('Y-m-d\TH:i');
        $total = $locked ? (int) $raffle->total_numbers : (int) $request->old('generated_numbers', $raffle?->total_numbers ?? 0);

        $vars = [
            'header' => $this->formHeader($raffle),
            'raffle_title' => e((string) $request->old('title', $raffle?->title ?? '')),
            'description' => e((string) $request->old('description', $raffle?->description ?? '')),
            'draw_rules' => e((string) $request->old('draw_rules', $raffle?->draw_rules ?? '')),
            'generated_numbers' => $total > 0 ? $total : '',
            'numbers_locked' => $locked ? 'readonly aria-readonly="true"' : '',
            'numbers_max' => self::NUMBERS_MAX,
            'price' => $priceValue === null ? '' : number_format($priceValue, 2, $decimal, $thousands),
            'currency_symbol' => e(Money::symbol($currency)),
            'decimal_sep' => e($decimal),
            'date_sortition' => e($drawDate),
            // Na edição, uma data já passada continua válida (o mínimo só impede datas novas no passado).
            'min_date' => $drawDate !== '' && $drawDate < $now ? e($drawDate) : $now,
            'submit_label' => e(__($editing ? 'admin.raffle_form.save' : 'admin.raffle_form.create')),
            'cancel_url' => e(url($editing ? '/admin/sortition/'.$raffle->id.'/listSortition' : '/admin/sortition')),
            'max_kb' => self::PHOTO_MAX_KB,
            'max_mb' => self::PHOTO_MAX_KB / 1024,
        ];

        foreach (['title', 'generated_numbers', 'price', 'date_sortition', 'description', 'draw_rules', 'photos'] as $field) {
            $message = $errors?->first($field === 'photos' ? 'photos*' : $field) ?: '';
            $vars['err_'.$field] = $message === '' ? ''
                : '<p class="adm-field__error" id="'.$field.'-error"><i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>'.e($message).'</p>';
            $vars['inv_'.$field] = $message === '' ? '' : 'aria-invalid="true"';
            $vars['desc_'.$field] = $message === '' ? '' : ' '.$field.'-error';
        }

        $json = json_encode([
            'editing' => $editing,
            'locked' => $locked,
            'showFee' => ! $editing || $raffle->status === Raffle::STATUS_AWAITING_PAYMENT,
            'fee' => PlatformSetting::pricePerNumber(),
            'currency' => $currency,
            'decimal' => $decimal,
            'thousands' => $thousands,
            'maxKb' => self::PHOTO_MAX_KB,
            'hasErrors' => $errors !== null && $errors->any(),
            'photos' => $editing ? $this->photoItems($raffle) : [],
            'deleteUrl' => $editing ? url('/admin/sortition/'.$raffle->id.'/deleteImageSortition') : '',
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

        // Por último: textos do usuário com "{{...}}" não podem ser trocados por outras variáveis da view.
        $vars['data'] = str_replace('{{', '{\u007b', (string) $json);

        return LegacyView::render('admin/modules/sortition/form', $vars);
    }

    private function formHeader(?Raffle $raffle): string
    {
        $title = $raffle ? __('admin.raffle_form.edit_title') : __('admin.raffle_form.create_title');
        $breadcrumb = '<nav class="adm-breadcrumb" aria-label="'.e(__('admin.manage.breadcrumb')).'">'
            .'<a href="'.e(url('/admin/sortition')).'">'.e(__('admin.nav.raffles')).'</a>'
            .($raffle ? '<i class="bi bi-chevron-right" aria-hidden="true"></i><a href="'.e(url('/admin/sortition/'.$raffle->id.'/listSortition')).'">'.e($raffle->title).'</a>' : '')
            .'<i class="bi bi-chevron-right" aria-hidden="true"></i><span aria-current="page">'.e($title).'</span></nav>';

        return LegacyView::render('admin/components/page_header', [
            'breadcrumb' => $breadcrumb,
            'title' => e($title),
            'subtitle' => '<p class="adm-hint">'.e(__($raffle ? 'admin.raffle_form.edit_subtitle' : 'admin.raffle_form.create_subtitle')).'</p>',
            'actions' => $raffle ? AdminUi::badge(AdminUi::raffleStatus($raffle->status, $raffle->winnersList() !== [])) : '',
        ]);
    }

    /** @return list<array{key: int, url: string}> */
    private function photoItems(Raffle $raffle): array
    {
        $items = [];
        foreach ($raffle->photosList() as $key => $path) {
            $items[] = ['key' => (int) $key, 'url' => Media::url($path)];
        }

        return $items;
    }

    public function update(Request $request, int $raffleId): RedirectResponse
    {
        $raffle = $this->ownedRaffle($raffleId);
        if (! $raffle) {
            return redirect('/admin/sortition');
        }

        $data = $this->validateForm($request, editing: true);

        $raffle->fill([
            'draw_date' => $data['date_sortition'] ?? null,
            'number_price' => $data['price'],
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'draw_rules' => $data['draw_rules'] ?? '',
            'photos' => json_encode(array_merge($raffle->photosList(), $this->uploadPhotos($request))),
        ]);

        // A quantidade de números só pode mudar antes da rifa ser liberada
        if ($raffle->status === Raffle::STATUS_AWAITING_PAYMENT && ! empty($data['generated_numbers'])) {
            $raffle->total_numbers = (int) $data['generated_numbers'];
            $raffle->setNumbersList(Raffle::generateNumbers((int) $data['generated_numbers']));
        }

        $raffle->save();

        return redirect('/admin/sortition?status=updated');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $raffle = $this->ownedRaffle($request->input('idDeleteSortition'));
        if (! $raffle) {
            return redirect('/admin/sortition');
        }

        if ($raffle->status !== Raffle::STATUS_AWAITING_PAYMENT) {
            return redirect('/admin/sortition?status=no_delete_status_dif');
        }

        $numbers = $raffle->numbersList();
        if (count(Raffle::filterBySituation($numbers, Raffle::RESERVED)) + count(Raffle::filterBySituation($numbers, Raffle::UNAVAILABLE)) > 0) {
            return redirect('/admin/sortition?status=exists_numbers_selecteds');
        }

        foreach ($raffle->photosList() as $path) {
            Media::delete($path);
        }

        $raffle->delete();

        return redirect('/admin/sortition?status=deleted');
    }

    public function deleteImage(Request $request, int $raffleId): RedirectResponse|JsonResponse
    {
        $raffle = $this->ownedRaffle($raffleId);
        if (! $raffle) {
            return $request->expectsJson()
                ? response()->json(['status' => 'error', 'message' => __('admin.raffles.link.not_found')], 404)
                : redirect('/admin/sortition');
        }

        $photos = $raffle->photosList();
        $key = (int) $request->input('keyPhoto');

        if (isset($photos[$key])) {
            Media::delete($photos[$key]);
            unset($photos[$key]);
            $raffle->photos = json_encode(array_values($photos));
            $raffle->save();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => __('admin.raffle_form.photos.deleted'),
                'photos' => $this->photoItems($raffle),
            ]);
        }

        return redirect("/admin/sortition/{$raffleId}/edit?status=deleted");
    }

    /** Rota antiga "Números marcados": agora é a aba Compradores da gestão da rifa. */
    public function marked(Request $request, int $raffleId): RedirectResponse
    {
        if (! $this->ownedRaffle($raffleId)) {
            return redirect('/admin/sortition');
        }

        return redirect($this->manageUrl($raffleId, ['tab' => 'buyers', 'status' => (string) $request->query('status', '')]));
    }

    /** Dono confirma o pagamento: reservas dos compradores (customers[] ou idCustomer) viram pagas. */
    public function confirmNumbers(Request $request, int $raffleId, AdminRaffleManage $manage): RedirectResponse|JsonResponse
    {
        return $this->changeReserved($request, $raffleId, $manage, confirm: true);
    }

    /** Dono cancela a reserva: números dos compradores voltam a ficar disponíveis. */
    public function cancelNumbers(Request $request, int $raffleId, AdminRaffleManage $manage): RedirectResponse|JsonResponse
    {
        return $this->changeReserved($request, $raffleId, $manage, confirm: false);
    }

    /** Confirmação/cancelamento em lote. fetch (Accept: application/json) recebe JSON com os dados novos da tela. */
    private function changeReserved(Request $request, int $raffleId, AdminRaffleManage $manage, bool $confirm): RedirectResponse|JsonResponse
    {
        $raffle = $this->ownedRaffle($raffleId);
        if (! $raffle) {
            return $this->manageNotFound($request);
        }

        $ids = array_values(array_filter(array_map('intval', (array) ($request->input('customers') ?? $request->input('idCustomer')))));
        $changed = $manage->changeReserved($raffle, $ids, $confirm);
        $key = $confirm ? 'confirmed' : 'cancelled';

        if (! $request->expectsJson()) {
            return redirect($this->manageUrl($raffle->id, ['tab' => 'buyers', 'status' => $changed > 0 ? 'numbers_'.$key : 'nothing_changed']));
        }

        if ($changed === 0) {
            return response()->json(['status' => 'error', 'message' => __('admin.manage.toast.nothing_changed'), 'data' => $manage->payload($raffle)], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => trans_choice('admin.manage.toast.'.$key, $changed, ['count' => AdminUi::integer($changed)]),
            'changed' => $changed,
            'data' => $manage->payload($raffle),
        ]);
    }

    /** Gestão da rifa: abas Compradores (padrão), Bilhetes (grade) e Histórico. Sem jQuery. */
    public function manage(Request $request, int $raffleId, AdminRaffleManage $manage): RedirectResponse|string
    {
        $raffle = $this->ownedRaffle($raffleId);
        if (! $raffle) {
            return redirect('/admin/sortition');
        }

        $this->releaseOrphanNumbers($raffle);

        $data = $manage->payload($raffle);
        $stats = AdminDashboard::stats($raffle);
        $tab = in_array($request->query('tab'), AdminRaffleManage::TABS, true) ? (string) $request->query('tab') : 'buyers';
        $shareUrl = url('/share/'.$raffle->share_code);
        $pending = count(array_filter($data['buyers'], fn ($buyer) => $buyer['reserved'] !== []));

        $json = json_encode($data + [
            'raffleId' => $raffle->id,
            'title' => $raffle->title,
            'shareUrl' => $shareUrl,
            'reservationMinutes' => Raffle::RESERVATION_MINUTES,
            'tab' => $tab,
            'filter' => $pending > 0 ? 'pending' : 'all',
            'endpoints' => [
                'confirm' => url('/admin/sortition/'.$raffle->id.'/confirmNumbersCustomers'),
                'cancel' => url('/admin/sortition/'.$raffle->id.'/cancelNumbersCustomers'),
                'assign' => url('/admin/sortition/fastRegisterCustomersNumbers'),
                'lookup' => url('/api/v1/customers/getDataCustomers'),
            ],
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

        $content = LegacyView::render('admin/modules/sortition/manage/index', [
            'header' => $this->manageHeader($raffle, $shareUrl),
            'flash' => $this->status($request),
            'paid' => AdminUi::integer($stats['paid']),
            'paid_amount' => e($raffle->formatMoney($stats['paid_amount'])),
            'reserved' => AdminUi::integer($stats['reserved']),
            'reserved_amount' => e($raffle->formatMoney($stats['reserved_amount'])),
            'free' => AdminUi::integer($stats['free']),
            'total' => AdminUi::integer($stats['total']),
            'percent' => $stats['percent'],
            'buyers' => LegacyView::render('admin/modules/sortition/manage/buyers'),
            'tickets' => LegacyView::render('admin/modules/sortition/manage/tickets'),
            'history' => $this->historyPanel($raffle, $manage),
            // Por último: nomes de compradores com "{{...}}" não podem ser trocados por outras variáveis da view.
            'data' => str_replace('{{', '{\u007b', (string) $json),
        ]);

        return $this->panel(__('admin.title.manage'), $content, 'sortition');
    }

    private function manageHeader(Raffle $raffle, string $shareUrl): string
    {
        $breadcrumb = '<nav class="adm-breadcrumb" aria-label="'.e(__('admin.manage.breadcrumb')).'">'
            .'<a href="'.e(url('/admin/sortition')).'">'.e(__('admin.nav.raffles')).'</a>'
            .'<i class="bi bi-chevron-right" aria-hidden="true"></i><span aria-current="page">'.e($raffle->title).'</span></nav>';

        $copy = e(__('admin.raffles.row.copy_link', ['title' => $raffle->title]));

        return LegacyView::render('admin/components/page_header', [
            'breadcrumb' => $breadcrumb,
            'title' => e($raffle->title),
            'subtitle' => '<div class="adm-manage__meta">'
                .AdminUi::badge(AdminUi::raffleStatus($raffle->status, $raffle->winnersList() !== []))
                .'<button type="button" class="adm-rl__link" x-data x-copy="$el.dataset.url" data-url="'.e($shareUrl).'" aria-label="'.$copy.'" title="'.$copy.'">'
                .'<i class="bi bi-link-45deg" aria-hidden="true"></i><span class="adm-rl__url">'.e(preg_replace('#^https?://#', '', $shareUrl)).'</span>'
                .'<i class="bi bi-files" aria-hidden="true"></i></button></div>',
            'actions' => '<a class="adm-btn adm-btn--secondary" href="'.e($shareUrl).'" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>'
                .e(__('admin.manage.public_page')).'</a>'
                .'<a class="adm-btn adm-btn--secondary" href="'.e(url('/admin/sortition/'.$raffle->id.'/edit')).'"><i class="bi bi-pencil" aria-hidden="true"></i>'
                .e(__('admin.raffles.menu.edit')).'</a>',
        ]);
    }

    /** Aba Histórico (renderizada no servidor). */
    private function historyPanel(Raffle $raffle, AdminRaffleManage $manage): string
    {
        $items = '';
        foreach ($manage->history($raffle) as $event) {
            $items .= $this->historyItem($raffle, $event);
        }

        if ($items === '') {
            return LegacyView::render('admin/components/empty_state', [
                'icon' => 'bi-clock-history',
                'title' => e(__('admin.manage.history.empty_title')),
                'text' => e(__('admin.manage.history.empty_text')),
                'cta' => '',
            ]);
        }

        return LegacyView::render('admin/modules/sortition/manage/history', ['items' => $items]);
    }

    /** @param  array{at: Carbon|null, type: string, name?: string, count?: int, numbers?: list<int>, gateway?: string|null, status?: string|null}  $event */
    private function historyItem(Raffle $raffle, array $event): string
    {
        $numbers = '';
        if (! empty($event['numbers'])) {
            $shown = array_slice($event['numbers'], 0, 12);
            foreach ($shown as $number) {
                $numbers .= '<span class="adm-num-chip">'.e($raffle->label($number)).'</span>';
            }
            $rest = count($event['numbers']) - count($shown);
            if ($rest > 0) {
                $numbers .= '<span class="adm-num-chip adm-num-chip--more">+'.AdminUi::integer($rest).'</span>';
            }
            $numbers = '<div class="adm-num-chips">'.$numbers.'</div>';
        }

        $approved = ($event['status'] ?? null) === 'approved';
        $count = ['name' => $event['name'] ?? '', 'count' => AdminUi::integer($event['count'] ?? 0)];

        [$icon, $tone, $text, $badge] = match ($event['type']) {
            'paid' => ['bi-check-circle-fill', 'paid', trans_choice('admin.manage.history.paid', $event['count'], $count), AdminUi::badge('paid')],
            'reserved' => ['bi-hourglass-split', 'pending', trans_choice('admin.manage.history.reserved', $event['count'], $count), AdminUi::badge('pending')],
            'release' => ['bi-credit-card', $approved ? 'paid' : 'pending', __('admin.manage.history.release', ['gateway' => $event['gateway'] ?: '—']),
                AdminUi::badge($approved ? 'paid' : 'pending', $approved ? null : (string) ($event['status'] ?: __('admin.status.pending')))],
            default => ['bi-flag', 'neutral', __('admin.manage.history.created'), ''],
        };

        return LegacyView::render('admin/modules/sortition/manage/history_item', [
            'icon' => $icon,
            'tone' => $tone,
            'text' => e($text),
            'badge' => $badge,
            'numbers' => $numbers,
            'datetime' => e($event['at']?->toIso8601String() ?? ''),
            'date' => e($event['at'] ? $event['at']->translatedFormat(__('common.datetime_format')) : __('admin.manage.history.no_date')),
        ]);
    }

    /** @param  array<string, string>  $query  (tab=buyers é o padrão e sai da URL) */
    private function manageUrl(int $raffleId, array $query = []): string
    {
        $query = array_filter($query, fn ($value, $key) => $value !== '' && ! ($key === 'tab' && $value === 'buyers'), ARRAY_FILTER_USE_BOTH);

        return '/admin/sortition/'.$raffleId.'/listSortition'.($query ? '?'.http_build_query($query) : '');
    }

    private function manageNotFound(Request $request): RedirectResponse|JsonResponse
    {
        return $request->expectsJson()
            ? response()->json(['status' => 'error', 'message' => __('admin.raffles.link.not_found')], 404)
            : redirect('/admin/sortition');
    }

    /** Dono reserva números livres para um comprador (busca por e-mail ou telefone; cria se não existir). */
    public function assignNumbers(Request $request, AdminRaffleManage $manage): RedirectResponse|JsonResponse
    {
        $raffle = $this->ownedRaffle($request->input('codeIDSortition'));
        if (! $raffle) {
            return $this->manageNotFound($request);
        }

        $json = $request->expectsJson();
        $input = $request->input('numbers');
        $selected = array_values(array_unique(array_filter(array_map('intval', is_array($input) ? $input : explode(',', (string) $input)))));
        $email = trim((string) $request->input('email'));
        $phone = Customer::onlyDigitsPhone($request->input('phone'));

        $error = match (true) {
            $selected === [] => 'no_numbers',
            $email === '' && $phone === '' => 'contact_required',
            default => null,
        };
        if ($error) {
            return $json
                ? response()->json(['status' => 'error', 'message' => __('admin.manage.assign.'.$error)], 422)
                : redirect($this->manageUrl($raffle->id, ['tab' => 'tickets']));
        }

        $customer = $email !== ''
            ? Customer::query()->where('email', $email)->first()
            : Customer::query()->where('phone', $phone)->first();

        $customer ??= Customer::query()->create([
            'email' => $email ?: null,
            'name' => trim((string) $request->input('username')) ?: __('admin.manage.unnamed'),
            'phone' => $phone,
        ]);

        $result = $manage->assign($raffle, $customer, $selected);

        if (! $json) {
            return redirect($this->manageUrl($raffle->id, ['tab' => 'tickets', 'status' => $result['assigned'] ? 'numbers_assigned' : 'numbers_unavailable']));
        }

        if ($result['assigned'] === []) {
            return response()->json(['status' => 'error', 'message' => __('admin.manage.assign.unavailable'), 'data' => $manage->payload($raffle)], 409);
        }

        $count = count($result['assigned']);
        $message = trans_choice('admin.manage.toast.assigned', $count, ['count' => AdminUi::integer($count), 'name' => $customer->name]);
        if ($result['skipped'] !== []) {
            $message .= ' '.trans_choice('admin.manage.toast.skipped', count($result['skipped']), ['count' => AdminUi::integer(count($result['skipped']))]);
        }

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'assigned' => $result['assigned'],
            'skipped' => $result['skipped'],
            'buyer_id' => $customer->id,
            'data' => $manage->payload($raffle),
        ]);
    }

    /**
     * Pré-visualização da página pública. Rifa liberada ou encerrada: a própria /share/{code} é a prévia fiel.
     * Aguardando liberação: a página pública mostra o pagamento, então o painel explica e oferece os próximos passos.
     */
    public function preview(Request $request, string $shareCode): RedirectResponse|string
    {
        $raffle = Raffle::query()->where('share_code', $shareCode)->where('customer_id', $this->customer()->id)->firstOrFail();

        if ($raffle->status !== Raffle::STATUS_AWAITING_PAYMENT) {
            return redirect('/share/'.$raffle->share_code);
        }

        $content = LegacyView::render('admin/modules/sortition/marked/view', [
            'header' => $this->raffleHeader($raffle, __('admin.preview.title')),
            'empty' => LegacyView::render('admin/components/empty_state', [
                'icon' => 'bi-eye-slash',
                'title' => e(__('admin.preview.awaiting_title')),
                'text' => e(__('admin.preview.awaiting_text')),
                'cta' => '<div class="adm-empty__actions">'
                    .'<a class="adm-btn adm-btn--primary" href="'.e(url('/admin/'.$raffle->id.'/checkout')).'"><i class="bi bi-qr-code" aria-hidden="true"></i>'.e(__('admin.preview.pay')).'</a>'
                    .'<a class="adm-btn adm-btn--secondary" href="'.e(url('/admin/sortition/'.$raffle->id.'/edit')).'"><i class="bi bi-pencil" aria-hidden="true"></i>'.e(__('admin.raffles.menu.edit')).'</a>'
                    .'</div>',
            ]),
        ]);

        return $this->panel(__('admin.preview.title'), $content, 'sortition');
    }

    /** Cabeçalho das telas de uma rifa: breadcrumb Minhas Rifas › rifa › tela, título e badge de status. */
    private function raffleHeader(Raffle $raffle, string $title, string $actions = ''): string
    {
        $breadcrumb = '<nav class="adm-breadcrumb" aria-label="'.e(__('admin.manage.breadcrumb')).'">'
            .'<a href="'.e(url('/admin/sortition')).'">'.e(__('admin.nav.raffles')).'</a>'
            .'<i class="bi bi-chevron-right" aria-hidden="true"></i>'
            .'<a href="'.e($this->manageUrl($raffle->id)).'">'.e($raffle->title).'</a>'
            .'<i class="bi bi-chevron-right" aria-hidden="true"></i><span aria-current="page">'.e($title).'</span></nav>';

        return LegacyView::render('admin/components/page_header', [
            'breadcrumb' => $breadcrumb,
            'title' => e($title),
            'subtitle' => '<div class="adm-manage__meta">'.AdminUi::badge(AdminUi::raffleStatus($raffle->status, $raffle->winnersList() !== []))
                .'<span class="adm-hint">'.e($raffle->title).'</span></div>',
            'actions' => $actions,
        ]);
    }

    /**
     * Ganhadores e encerramento: 1. resultado da Loteria Federal → 2. número sorteado → 3. prévia do ganhador → 4. confirmar.
     */
    public function winners(Request $request, int $raffleId, LoteriaFederal $loteria): RedirectResponse|string
    {
        $raffle = $this->ownedRaffle($raffleId);
        if (! $raffle) {
            return redirect('/admin/sortition');
        }

        $winners = $raffle->winnersList();
        $celebrate = $request->query('status') === 'created_winner';
        $lottery = $loteria->summary();

        $items = '';
        foreach ($winners as $key => $winner) {
            $items .= $this->winnerItem($raffle, $winner, $key, $celebrate && $key === 0);
        }

        $prizes = '';
        foreach ($lottery['prizes'] as $prize) {
            $suggestion = LoteriaFederal::suggestedNumber($prize['ticket'], (int) $raffle->total_numbers);
            $prizes .= LegacyView::render('admin/modules/sortition/finish/prize', [
                'position' => e(__('admin.winners.lottery.position', ['position' => $prize['position']])),
                'ticket' => e($prize['ticket']),
                'action' => $suggestion !== null
                    ? '<button type="button" class="adm-btn adm-btn--secondary adm-btn--sm" @click="pick('.$suggestion.')">'
                        .'<i class="bi bi-arrow-down-circle" aria-hidden="true"></i>'.e(__('admin.winners.lottery.use', ['number' => $raffle->label($suggestion)])).'</button>'
                    : '<span class="adm-hint">'.e(__('admin.winners.lottery.out_of_range')).'</span>',
            ]);
        }

        $pending = count(Raffle::filterBySituation($raffle->numbersList(), Raffle::RESERVED));

        $content = LegacyView::render('admin/modules/sortition/finish/winnerLottery', [
            'header' => $this->raffleHeader($raffle, __('admin.winners.title'),
                '<a class="adm-btn adm-btn--secondary" href="'.e($this->manageUrl($raffle->id)).'"><i class="bi bi-sliders" aria-hidden="true"></i>'
                .e(__('admin.raffles.row.manage')).'</a>'),
            'flash' => $this->status($request),
            'data' => json_encode([
                'previewUrl' => url('/admin/sortition/'.$raffle->id.'/winnerPreview'),
                'total' => (int) $raffle->total_numbers,
                'celebrate' => $celebrate,
                'pending' => $pending,
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            'add_url' => e(url('/admin/sortition/'.$raffle->id.'/winnerLottery')),
            'total' => (int) $raffle->total_numbers,
            'number_hint' => e(__('admin.winners.number.hint', ['total' => $raffle->label((int) $raffle->total_numbers)])),
            'lottery_contest' => e($lottery['contest']),
            'lottery_date' => e($lottery['date']),
            'prizes' => $prizes !== '' ? '<ul class="adm-prizes">'.$prizes.'</ul>' : '<p class="adm-muted-note">'.e(__('admin.dashboard.lottery.unavailable')).'</p>',
            'count' => AdminUi::integer(count($winners)),
            'winner_list' => $items !== ''
                ? '<ul class="adm-winners__list">'.$items.'</ul>'
                : LegacyView::render('admin/components/empty_state', [
                    'icon' => 'bi-trophy',
                    'title' => e(__('admin.winners.empty.title')),
                    'text' => e(__('admin.winners.empty.text')),
                    'cta' => '',
                ]),
            'finish' => $this->finishBlock($raffle, $winners !== []),
        ]);

        return $this->panel(__('admin.winners.title'), $content, 'sortition');
    }

    /** Card de um ganhador (número, nome, contato, remover). */
    private function winnerItem(Raffle $raffle, object $winner, int $key, bool $new): string
    {
        $phone = Customer::onlyDigitsPhone((string) ($winner->phone ?? ''));
        $email = (string) ($winner->email ?? '');
        $hasBuyer = $phone !== '' || $email !== '';
        $label = $raffle->label((int) $winner->number);

        $meta = array_filter([
            $phone !== '' ? '<span><i class="bi bi-telephone" aria-hidden="true"></i>'.e(Customer::formatPhone($phone)).'</span>' : '',
            $email !== '' ? '<span><i class="bi bi-envelope" aria-hidden="true"></i>'.e($email).'</span>' : '',
        ]);

        return LegacyView::render('admin/modules/sortition/finish/list', [
            'new' => $new ? 'is-new' : '',
            'label' => e($label),
            'badge' => $hasBuyer ? AdminUi::badge('winner') : AdminUi::badge('available', __('admin.winners.no_buyer')),
            'name' => $hasBuyer ? e((string) $winner->name) : e(__('admin.winners.no_buyer_text')),
            'meta' => $meta ? '<p class="adm-winner__meta">'.implode('', $meta).'</p>' : '',
            'whatsapp' => $phone !== ''
                ? '<a class="adm-btn adm-btn--whatsapp adm-btn--sm" href="'.e('https://wa.me/55'.$phone).'" target="_blank" rel="noopener">'
                    .'<i class="bi bi-whatsapp" aria-hidden="true"></i>'.e(__('admin.winners.notify')).'</a>'
                : '',
            'delete_url' => e(url('/admin/sortition/'.$raffle->id.'/deleteWinnerLottery/'.$key)),
            'delete_label' => e(__('admin.winners.remove', ['number' => $label])),
        ]);
    }

    /** Rodapé da lista: encerrar a rifa (com confirmação) ou aviso de rifa encerrada. */
    private function finishBlock(Raffle $raffle, bool $hasWinners): string
    {
        if ($raffle->status === Raffle::STATUS_FINISHED) {
            return '<div class="adm-winners__finish adm-winners__finish--done"><i class="bi bi-flag-fill" aria-hidden="true"></i>'
                .'<p>'.e(__('admin.winners.finish.done')).'</p>'
                .'<a class="adm-btn adm-btn--secondary adm-btn--sm" href="'.e(url('/share/'.$raffle->share_code)).'" target="_blank" rel="noopener">'
                .'<i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>'.e(__('admin.manage.public_page')).'</a></div>';
        }

        return '<form class="adm-winners__finish" method="post" action="'.e(url('/admin/sortition/'.$raffle->id.'/executeFinishLottery')).'" @submit.prevent="confirmFinish($el)">'.csrf_field()
            .'<div><p class="adm-winners__finish-title">'.e(__('admin.winners.finish.title')).'</p>'
            .'<p class="adm-hint" id="adm-finish-hint">'.e(__($hasWinners ? 'admin.winners.finish.hint' : 'admin.winners.finish.need_winner')).'</p></div>'
            .'<button type="submit" class="adm-btn adm-btn--primary" aria-describedby="adm-finish-hint"'.($hasWinners ? '' : ' disabled').'>'
            .'<i class="bi bi-flag" aria-hidden="true"></i>'.e(__('admin.winners.finish.button')).'</button></form>';
    }

    /** Prévia do ganhador (JSON): quem comprou o número informado, sem gravar nada. */
    public function previewWinner(Request $request, int $raffleId): JsonResponse
    {
        $raffle = $this->ownedRaffle($raffleId);
        if (! $raffle) {
            return response()->json(['state' => 'not_found', 'message' => __('admin.winners.preview.not_found')], 404);
        }

        $number = (int) $request->query('number');
        if ($number < 1) {
            return response()->json(['state' => 'invalid', 'message' => __('admin.winners.preview.invalid')], 422);
        }
        if ($number > $raffle->total_numbers) {
            return response()->json(['state' => 'exceeded', 'message' => __('admin.raffles.flash.number_exceeded')], 422);
        }

        $base = ['number' => $number, 'label' => $raffle->label($number)];

        if (collect($raffle->winnersList())->contains(fn ($w) => (int) $w->number === $number)) {
            return response()->json([...$base, 'state' => 'duplicate', 'message' => __('admin.raffles.flash.duplicate_register_winner')], 422);
        }

        $buyer = $this->buyerOf($raffle, $number);
        if (! $buyer) {
            return response()->json([...$base, 'state' => 'none', 'message' => __('admin.winners.preview.none')]);
        }

        [$customer, $situation] = $buyer;
        $status = AdminUi::numberStatus($situation);

        return response()->json([
            ...$base,
            'state' => 'buyer',
            'name' => $customer->name,
            'phone' => Customer::formatPhone(Customer::onlyDigitsPhone((string) $customer->phone)),
            'status' => $status,
            'status_label' => AdminUi::badgeData($status)['label'],
            'message' => $situation === Raffle::RESERVED ? __('admin.winners.preview.reserved') : '',
        ]);
    }

    /**
     * Comprador do número (reservado ou pago) ou null.
     *
     * @return array{0: Customer, 1: string}|null
     */
    private function buyerOf(Raffle $raffle, int $number): ?array
    {
        $drawn = collect($raffle->numbersList())->first(fn ($n) => (int) $n->number === $number);
        if (! $drawn || ! in_array($drawn->situation, [Raffle::RESERVED, Raffle::UNAVAILABLE], true) || empty($drawn->customer_id)) {
            return null;
        }

        $customer = Customer::query()->find($drawn->customer_id);

        return $customer ? [$customer, $drawn->situation] : null;
    }

    public function addWinner(Request $request, int $raffleId): RedirectResponse
    {
        $back = '/admin/sortition/'.$raffleId.'/winnerLottery';
        $raffle = $this->ownedRaffle($raffleId);
        $numberPost = (int) $request->input('number');

        if (! $raffle) {
            return redirect('/admin/sortition');
        }
        if ($numberPost < 1) {
            return redirect($back);
        }
        if ($numberPost > $raffle->total_numbers) {
            return redirect($back.'?status=number_exceeded');
        }

        $winners = $raffle->winnersList();
        if (collect($winners)->contains(fn ($w) => (int) $w->number === $numberPost)) {
            return redirect($back.'?status=duplicate_register_winner');
        }

        $customer = $this->buyerOf($raffle, $numberPost)[0] ?? null;

        $entry = $customer
            ? ['number' => $numberPost, 'name' => $customer->name, 'email' => $customer->email, 'phone' => $customer->phone]
            : ['number' => $numberPost, 'name' => 'Não Possui Ganhador', 'email' => '', 'phone' => ''];

        $raffle->winners = json_encode(array_merge([$entry], $winners));
        $raffle->save();

        return redirect($back.'?status=created_winner');
    }

    public function deleteWinner(int $raffleId, int $index): RedirectResponse
    {
        $raffle = $this->ownedRaffle($raffleId);
        if (! $raffle) {
            return redirect('/admin/sortition');
        }

        $winners = $raffle->winnersList();
        unset($winners[$index]);
        $raffle->winners = json_encode(array_values($winners));
        $raffle->save();

        return redirect('/admin/sortition/'.$raffleId.'/winnerLottery?status=deleted_winner');
    }

    /** Encerra a rifa (exige ao menos um ganhador) e libera números presos sem comprador. */
    public function finish(int $raffleId): RedirectResponse
    {
        $raffle = $this->ownedRaffle($raffleId);
        if (! $raffle) {
            return redirect('/admin/sortition');
        }

        if (! $raffle->winnersList()) {
            return redirect('/admin/sortition/'.$raffleId.'/winnerLottery?status=winner_not_exists');
        }

        $this->releaseOrphanNumbers($raffle);
        $raffle->update(['status' => Raffle::STATUS_FINISHED]);

        return redirect('/admin/sortition?status=update_finish_sortition');
    }

    /** Números reservados/vendidos sem cliente voltam a ficar disponíveis. */
    private function releaseOrphanNumbers(Raffle $raffle): array
    {
        $numbers = $raffle->numbersList();
        $changed = false;

        foreach ($numbers as $number) {
            if ($number->situation !== Raffle::AVAILABLE && empty($number->customer_id)) {
                $number->situation = Raffle::AVAILABLE;
                $changed = true;
            }
        }

        if ($changed) {
            $raffle->setNumbersList($numbers);
            $raffle->save();
        }

        return $numbers;
    }

    private function validateForm(Request $request, bool $editing = false): array
    {
        // Preço mascarado no formato do idioma ("R$ 1.234,56" / "$1,234.56"); inválido segue como veio e falha em numeric.
        $price = Money::parse((string) $request->input('price'));
        $request->merge([
            'price' => $price ?? $request->input('price'),
            'date_sortition' => $request->filled('date_sortition') ? $request->input('date_sortition') : null,
        ]);

        $message = fn (string $key, array $params = []) => __('admin.raffle_form.errors.'.$key, $params);
        $range = $message('numbers_range', ['max' => AdminUi::integer(self::NUMBERS_MAX)]);

        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'generated_numbers' => [$editing ? 'nullable' : 'required', 'integer', 'min:1', 'max:'.self::NUMBERS_MAX],
            'description' => ['nullable', 'string'],
            'draw_rules' => ['nullable', 'string'],
            'date_sortition' => ['nullable', 'date'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['image', 'max:'.self::PHOTO_MAX_KB],
        ], [
            'title.required' => $message('title_required'),
            'title.max' => $message('title_max', ['max' => 255]),
            'price.required' => $message('price_required'),
            'price.numeric' => $message('price_invalid'),
            'price.min' => $message('price_invalid'),
            'generated_numbers.required' => $message('numbers_required'),
            'generated_numbers.integer' => $range,
            'generated_numbers.min' => $range,
            'generated_numbers.max' => $range,
            'date_sortition.date' => $message('date_invalid'),
            'photos.*.image' => $message('photo_image'),
            'photos.*.max' => $message('photo_size', ['size' => self::PHOTO_MAX_KB / 1024]),
        ]);
    }

    /** @return array<int, string> */
    private function uploadPhotos(Request $request): array
    {
        return collect($request->file('photos', []))
            ->filter()
            ->map(fn ($file) => Media::storeImage($file, 'photos'))
            ->values()
            ->all();
    }

    /** Novo código de compartilhamento (fetch no modal "Personalizar link" da lista). */
    public function updateCodeShare(Request $request): array
    {
        $code = Str::slug((string) $request->input('newCodeShare'));
        $raffle = $this->ownedRaffle($request->input('idSortitionEditLink'));

        if (! $raffle) {
            return ['status' => 'error', 'message' => __('admin.raffles.link.not_found')];
        }
        if ($code === '') {
            return ['status' => 'error', 'message' => __('admin.raffles.link.empty')];
        }
        if (mb_strlen($code) > self::SHARE_CODE_MAX) {
            return ['status' => 'error', 'message' => __('admin.raffles.link.too_long', ['max' => self::SHARE_CODE_MAX])];
        }
        if (Raffle::query()->where('share_code', $code)->where('id', '!=', $raffle->id)->exists()) {
            return ['status' => 'error', 'message' => __('admin.raffles.link.exists')];
        }

        $raffle->update(['share_code' => $code]);

        return ['status' => 'success', 'message' => __('admin.raffles.link.updated'), 'code' => $code, 'url' => url('/share/'.$code)];
    }
}
