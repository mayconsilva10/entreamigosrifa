# Redesign do Painel Administrativo (`/admin`) — Plano por etapas

> Executado pelo agente `executor-painel`, **uma etapa por sessão**.
> Contexto entre sessões: apenas `.claude/painel/ESTADO.md` (≤ 8 linhas) + "Contexto fixo" + "Decisões" + a seção da etapa.
> O usuário deu **permissão total para alterar o layout do painel**. Dependências listadas aqui estão **aprovadas**.

## Contexto fixo (não reexplorar)

- Rotas: `routes/web.php`, grupo `auth:admin` prefixo `admin` (Home, Customer, Raffle, Checkout) + `api/v1` (`newCodeShared`, `customers/getDataCustomers`).
- Controllers: `app/Http/Controllers/Admin/*`. Base `PanelController`: `panel($title, $content, $module)`, `renderMaster()`,
  `modules()` (menu), `pagination()`, `success()/danger()/statusMessage()`, `ownedRaffle()`.
- Templates legados `LegacyView::render('admin/...')` com `{{var}}` em `resources/templates/default/admin/**`:
  `__master.php` (head + scripts), `panel.php` (shell), `menu/sidebar|topbar`, `pagination/*`, `alert/status.php`,
  `modules/home` (dashboard), `modules/sortition/*` (lista, form, manage `list_numbers/box.php`, liberação `marked/numbersMarked.php`,
  preview `marked/view.php`, ganhadores `finish/*`, modais `modal/*`), `modules/customers/settings/*` (perfil, pagamento Pix/MP/banco,
  social, notificação, senha), `modules/checkout/index.php` (pagar liberação), `modules/register/*` (login/cadastro/recuperação).
- Front atual: **SB Admin 2 (CSS Bootstrap 4)** + `bootstrap.bundle` 5 + `bootstrap.min.js` duplicados, jQuery, jquery.easing,
  Inputmask, VanillaMasker, Select2 (CDN), Font Awesome. Atributos `data-toggle` (BS4) e `data-bs-toggle` (BS5) misturados → modais/dropdowns quebrados.
- Dados: `raffles.numbers` é JSON (situação por número: disponível/reservado/vendido — ver `Raffle::filterBySituation`, constantes
  `Raffle::RESERVED/UNAVAILABLE`), `raffles.winners` JSON, `raffles.status`, `transactions` (pagamentos de liberação da rifa ao sistema = "repasses/histórico").
- Já existem no projeto: Vite (`vite.config.js` com `raffle.css/js`), Alpine + `@alpinejs/mask` + `@alpinejs/collapse`, `motion`,
  `canvas-confetti`, `/vendor/bootstrap` (5.x CSS+JS), `/vendor/bootstrap-icons`. i18n: `{{t:grupo.chave}}` (ver `.claude/i18n/ESTADO.md` CONVENÇÕES).
- Não há testes de admin hoje.

## Decisões de arquitetura (valem para todas as etapas)

1. **Sai SB Admin 2, entra Bootstrap 5.3 puro** (`/vendor/bootstrap`) só para grid/utilitários/modal/dropdown/offcanvas, com
   **tema próprio** em `resources/css/admin.css` (CSS puro, tokens em `:root`, sem Tailwind — mesmo motivo da página pública).
   Todos os atributos viram `data-bs-*`. Um único `bootstrap.bundle.min.js`.
2. **JS do painel em `resources/js/admin.js`** (Vite, entrada nova), injetado via placeholder `{{viteAssets}}` no `admin/__master.php`
   (gerado no `PanelController`, igual ao `SharedController`). Alpine para interatividade; componentes globais:
   `$store.toast` (toasts), `$store.confirm` (confirmação assíncrona substitui `alert/confirm`), `x-copy` (Clipboard API + toast),
   `countUp` em KPIs (motion, respeita `prefers-reduced-motion`).
3. **jQuery é removido até a etapa 7.** Substituições: Inputmask/VanillaMasker → `@alpinejs/mask` (`$money` p/ preço);
   Select2 → `tom-select` (import dinâmico só na tela de banco); `$.post` → `fetch` com header `X-CSRF-TOKEN`.
   Enquanto houver página não migrada, jQuery continua carregado só nela (flag no controller), nunca global.
4. **Ícones: Bootstrap Icons** (já em `/vendor`, igual à página pública). Font Awesome sai na etapa 7.
5. **Sistema de cores semântico (tokens)** — obrigatório usar tokens, nunca hex solto nos templates:
   - Marca: `--brand-600 #4F46E5` (ação primária), `--brand-50` fundo de seleção. Superfícies: `--bg #F6F7FB`, `--surface #FFF`,
     `--border #E4E7EC`, texto `--ink-900 #101828` / `--ink-600 #475467` (≥ 4.5:1 sobre surface).
   - Status de bilhete/pedido (fundo claro + texto escuro + ícone, nunca só cor):
     `--st-paid` verde (`#ECFDF3`/`#067647`, `bi-check-circle-fill`) · `--st-pending` âmbar (`#FFFAEB`/`#B54708`, `bi-hourglass-split`) ·
     `--st-expired` cinza riscado (`#F2F4F7`/`#475467`, `bi-clock-history`) · `--st-winner` violeta/dourado (`#F4F3FF`/`#5925DC` + borda `#FDB022`, `bi-trophy-fill`) ·
     `--st-available` neutro (`#FFF` + borda) · `--danger` (`#FEF3F2`/`#B42318`) só para destruição/erro.
   - Status de rifa: Ativa (verde), Aguardando liberação/pagamento (âmbar), Encerrada (cinza), Sorteada (violeta).
   - **Ação vs leitura**: botões/links interativos sempre `--brand-*` ou semântico sólido; áreas de leitura (KPIs, tabelas) em neutros —
     cor de marca nunca em texto estático. Dark mode: tokens redefinidos em `@media (prefers-color-scheme: dark)` + `[data-theme=dark]`
     com toggle no topbar (localStorage em try/catch).
   - Validar contraste AA (texto ≥ 4.5:1, ícone/borda de estado ≥ 3:1) em todos os pares — registrar a tabela no topo do `admin.css`.
6. **Componentes reutilizáveis como partials LegacyView** em `resources/templates/default/admin/components/`:
   `kpi.php`, `badge.php` (recebe `status` → classe/ícone/rótulo via helper PHP), `empty_state.php` (ícone, título, texto, CTA),
   `page_header.php` (título, breadcrumb, ações), `table_toolbar.php` (busca, chips de filtro com contagem), `pagination` (refeito),
   `confirm_modal.php`. Mapeamento status→badge num único helper PHP (`App\Support\AdminUi`), testável.
7. **Tabelas de alta densidade**: `<table>` semântica no desktop (≥ 768px), header sticky, números alinhados à direita com
   `font-variant-numeric: tabular-nums`, linha 48px (densidade "confortável"/"compacta" alternável), ação primária visível na linha
   e secundárias em menu `⋯`. **Mobile: cada linha vira card** (CSS `display:grid` com `data-label` por célula), **sem rolagem horizontal**.
   Filtros = chips com contagem + busca com debounce (client-side quando a página já tem os dados; query string `?status=&q=&page=` quando paginado no servidor).
   Paginação: "Mostrando 11–20 de 134" + anterior/próxima + páginas compactas (`1 … 4 5 6 … 14`), mantendo filtros na URL.
8. **i18n junto com o redesign**: todo template tocado já sai com `{{t:admin.*}}` e chaves em `lang/pt_BR/admin.php` **e** `lang/en/admin.php`
   (substitui a etapa 2c do plano de i18n). Dinheiro via `$raffle->formatMoney()` / `Money::format`, nunca `'R$ '.number_format`.
   Strings no Alpine via `$t()` com grupo `admin_js` (mesmo padrão de `raffle_js`).
9. **Microinterações leves**: transições 150–200ms (`ease-out`), skeleton nos carregamentos AJAX, botão com spinner + `disabled` ao enviar
   formulário (evita duplo envio), toast após ação (sucesso/erro com ação "Desfazer" quando reversível), highlight da linha alterada,
   confetti só ao registrar ganhador. Tudo desligado em `prefers-reduced-motion`.
10. **Orçamento**: `admin.js` inicial ≤ 30 kB gzip (sem contar tom-select/confetti sob demanda); CSS ≤ 25 kB gzip. Não instalar nada fora desta lista:
    `tom-select` (novo). Sem libs de gráfico — sparklines/barras em SVG inline gerado no servidor.
11. Sem mudança de regra de negócio, a não ser que a etapa peça. Backend novo só para alimentar a UI (filtros, contagens, JSON da grade).

---

## Etapa 1 — Fundação: tokens, shell e rede de segurança

**Objetivo:** novo esqueleto visual em todas as páginas, sem redesenhar o conteúdo ainda.
- **Testes de caracterização primeiro**: `tests/Feature/Admin/AdminPagesTest.php` — cliente autenticado (guard `admin`) acessa cada GET
  do grupo admin (dashboard, sortition, create, edit, list, listSortition, winnerLottery, todas as settings) → 200 e contém o título;
  dono não acessa rifa de outro. Deve passar **antes** de mexer no layout.
- `resources/css/admin.css` (tokens da decisão 5, reset leve, tipografia Inter/system-ui, componentes base: botão, card, badge, form, tabela, modal, toast)
  e `resources/js/admin.js` (Alpine + mask + collapse, stores toast/confirm, `x-copy`); registrar no `vite.config.js`; `{{viteAssets}}` no `PanelController`.
- Novo `__master.php` (BS 5.3 CSS de `/vendor/bootstrap`, Bootstrap Icons, um único bundle JS, jQuery só quando a view pedir) e novo `panel.php`:
  sidebar fixa 248px recolhível (desktop), **bottom nav** com 4 itens no mobile (< 768px), topbar com título da página, busca global (placeholder),
  toggle de tema, menu do usuário (perfil/sair). Menu ativo destacado. Skip-link e landmarks (`nav`, `main`).
- Partials de `components/` da decisão 6 (`page_header`, `badge`, `empty_state`, `kpi`, `confirm_modal`, `alert` → vira toast via flash) +
  `App\Support\AdminUi` (status→badge) com teste unitário.
- `lang/{pt_BR,en}/admin.php` com as chaves do shell; `lang/*/admin_js.php` para toasts/confirm.
- Migrar `data-toggle`→`data-bs-toggle` em todos os templates admin (busca global) para nada quebrar no BS5.
**Pronto quando:** testes verdes (inclusive os novos), `npm run build` ok, todas as páginas do painel abrem com o shell novo sem erro de console,
modais e dropdowns existentes funcionam.

## Etapa 2 — Dashboard e KPIs

**Objetivo:** responder em 5 s "quanto entrou, o que preciso fazer agora, como estão minhas rifas".
- `HomeController::dashboard()`: calcular **Arrecadado (pago)**, **A confirmar** (qtd reservados + valor R$), **Rifas ativas**, **% vendido geral**;
  valores por moeda da rifa (agrupar se moedas diferentes).
- Linha de 4 KPIs (`components/kpi.php`: rótulo, valor com countUp, ícone, subtexto/variação). KPI "A confirmar" é **acionável**
  (cor âmbar + link "Confirmar pagamentos" para a liberação da rifa com mais pendências).
- **Fila de ações** ("Precisa da sua atenção"): pendências por rifa, rifas com sorteio em ≤ 3 dias, rifas aguardando pagamento de liberação,
  conta sem Pix configurado. Cada item com CTA.
- "Progresso das rifas" vira lista de cards: miniatura, título, badge de status, barra empilhada **pago/reservado/livre** (cores dos tokens),
  `x de y vendidos`, data do sorteio relativa ("em 4 dias"), ações rápidas (Gerenciar, Copiar link).
- Loteria Federal: card compacto lateral (último concurso + próximo), colapsável no mobile.
- Empty state de primeira vez: checklist de onboarding (1. Configurar Pix → 2. Criar rifa → 3. Compartilhar link) com progresso.
- Feature test: KPIs calculados corretamente com rifa fake (pagos/reservados).
**Pronto quando:** testes verdes, build ok, dashboard sem rolagem horizontal em 360px.

## Etapa 3 — Lista de rifas ("Minhas Rifas")

**Objetivo:** encontrar e agir sobre qualquer rifa em 2 cliques.
- `RaffleController::index`: filtros `?status=ativa|aguardando|encerrada&q=` + contagens por status para os chips; paginação nova.
- Toolbar (`table_toolbar.php`): busca por título/código, chips com contagem, ordenar (mais recente, sorteio mais próximo, mais vendida).
- Tabela: Rifa (foto 40px + título + link curto copiável), Status (badge), Preço, Vendidos (barra mini + `x/y`), Pendentes (badge âmbar clicável),
  Sorteio (data + relativa), Ações: **Gerenciar** (primária) + `⋯` (Compartilhar, Personalizar link, Visualizar, Editar, Encerrar, Excluir).
  Mobile: card por rifa com as mesmas infos e botão Gerenciar largura total.
- Modais refeitos com `confirm_modal`: Excluir (digitar o título para confirmar), Personalizar link (validação inline do código, preview da URL,
  `fetch` no lugar de `$.post`, sem `window.location` no fechamento — atualizar a linha), Compartilhar (copiar link, WhatsApp com texto
  pronto, Web Share API no mobile quando disponível).
- Empty states: nenhuma rifa (CTA criar) e "nenhum resultado para o filtro" (limpar filtros).
- Remover jQuery desta página. Feature tests: filtro por status, busca, contagens.
**Pronto quando:** testes verdes, build ok, sem jQuery na página, mobile sem scroll horizontal.

## Etapa 4 — Gestão de bilhetes e compradores (manage + liberação)

**Objetivo:** a tela operacional mais usada: ver quem comprou, confirmar pagamentos em lote, marcar números manualmente.
- Unificar `listSortition` (`list_numbers/box.php`) e `list` (`marked/numbersMarked.php`) numa tela com **abas**:
  **Compradores** (padrão) · **Bilhetes** (grade) · **Histórico**. Header da rifa com KPIs mini (pagos, pendentes R$, livres, % vendido) e link público.
- **Compradores**: uma linha por comprador (nome, telefone com botão WhatsApp "cobrar" com mensagem pré-pronta, qtd números, chips dos números
  (máx 6 + "+n"), valor, status agregado: Pago/Pendente/Parcial, reservado há X tempo). Filtros: Pendentes (padrão quando houver), Pagos, Todos + busca
  por nome/telefone/número. **Seleção em lote** com checkbox + barra de ação sticky inferior ("3 selecionados · R$ 45,00 — Confirmar pagamento | Cancelar reserva").
  Confirmar/Cancelar via `confirm_modal` → POST existente (`confirmNumbersCustomers`/`cancelNumbersCustomers`) → toast + atualização da linha.
  Linha expandível (`x-collapse`) mostra todos os números e datas.
- **Bilhetes**: grade renderizada no cliente a partir de JSON compacto (mesma ideia de `window.__RAFFLE__`: string de status), faixas de 100,
  filtros por status com contagem, busca de número, legenda com ícone+cor; clique num número ocupado abre popover com comprador; seleção de livres
  + "Atribuir a comprador" abre drawer (offcanvas) com telefone (busca `getDataCustomers`) → nome → salvar (`fastRegisterCustomersNumbers`). Grid 10 col desktop / 5 mobile, alvo ≥ 44px.
- **Histórico**: linha do tempo das transações/eventos disponíveis (reservas confirmadas/canceladas se houver timestamp; senão, transactions da rifa).
- Controller: endpoint/variáveis JSON para a grade e lista; manter rotas antigas (redirecionar `list` → aba Compradores).
- Feature tests: confirmação em lote altera só números do dono, cancelamento libera números, JSON da grade com status corretos.
**Pronto quando:** testes verdes, build ok, rifa de 10.000 números navegável sem travar, sem jQuery na tela.

## Etapa 5 — Formulários: criação/edição de rifa e configurações

**Objetivo:** criar uma rifa sem dúvidas e sem erro de digitação.
- `sortition/form.php`: layout em seções numeradas (1. Prêmio — título, descrição, fotos · 2. Números e preço · 3. Sorteio — data, regras)
  com **resumo ao vivo** lateral (desktop) / colapsável (mobile): "100 números × R$ 10,00 = R$ 1.000,00 potencial · custo de liberação R$ X".
  Preço com `$money` do `@alpinejs/mask`, quantidade com presets (100/500/1.000/10.000), data com mínimo hoje e "em X dias".
  Upload de fotos drag & drop com preview, reordenar não é necessário, remover com confirmação, limite de tamanho validado no cliente.
  Validação inline (erros do servidor ao lado do campo via `old()`/flash de erros), botão salvar sticky no mobile com spinner.
  Edição: campos bloqueados (ex.: quantidade) com explicação em tooltip/hint, não só `disabled`.
  Ajustar parse do preço no `RaffleController::store/update` (máscara "R$ 1,234.56") para aceitar o formato novo de forma robusta (teste).
- Configurações (`customers/settings/*`): navegação lateral (desktop) / lista (mobile) no lugar das abas; cards por seção; Pix com tipo de chave
  e máscara por tipo (Alpine), banco com `tom-select` (import dinâmico); senha com medidor de força e mostrar/ocultar; Social/Notificação com toggles.
  Remover Inputmask/VanillaMasker/Select2/jQuery dessas telas.
- Feature tests: criar rifa com preço mascarado, editar, salvar Pix.
**Pronto quando:** testes verdes, build ok, formulários sem jQuery.

## Etapa 6 — Encerramento, ganhadores e pagamentos de liberação

**Objetivo:** fechar o ciclo da rifa com confiança.
- `finish/winnerLottery.php`: fluxo guiado (1. Resultado da Loteria Federal mostrado → 2. Informar número/posição → 3. Pré-visualizar ganhador
  com nome/telefone → 4. Confirmar). Card do ganhador com badge `--st-winner`; confetti (import dinâmico) ao adicionar; remover ganhador com confirmação.
  Encerrar rifa com `confirm_modal` explicando consequências (números órfãos liberados).
- `checkout/index.php` (pagar liberação): resumo do pedido claro (números × tarifa), Pix com QR + copia-e-cola (`x-copy`), status com polling leve
  ou botão "Já paguei, verificar", estados pago/pendente/expirado com badges.
- Histórico de pagamentos ("repasses"): lista das `transactions` do dono (data, rifa, gateway, valor, status badge) com filtros e paginação
  (nova aba em Configurações ou página `/admin/customer/payments-history` — escolher a mais simples e registrar em DECISÕES).
- Preview da rifa (`marked/view.php`): substituir pelo iframe/abertura da página pública `/share/{code}` se equivalente; senão só aplicar o shell.
- Login/cadastro/recuperação (`register/*`): aplicar tokens e componentes (tela dividida com marca), sem mudar fluxo.
- Feature tests: adicionar/remover ganhador, histórico lista só transações do dono.
**Pronto quando:** testes verdes, build ok.

## Etapa 7 — Limpeza, acessibilidade e QA

**Objetivo:** fechar dívidas e garantir qualidade.
- Remover do painel: SB Admin 2 (`/themes/default/assets/css/sb-admin-2*`, `sb-admin-2.min.js` só se nada mais usar), jQuery, jquery.easing,
  Inputmask, VanillaMasker, Select2 CDN, Font Awesome; templates órfãos (`menu/topbar/messages|alerts` se não usados, tabelas antigas).
  Conferir com `Grep` antes de apagar cada arquivo.
- Acessibilidade: foco visível em tudo, ordem de tab, `aria-live` nos toasts, modais com foco preso e Esc, labels em todos os campos,
  tabela→cards preserva rótulos para leitor de tela, contraste da tabela de tokens (claro e escuro) conferido.
- **Pedido do usuário — sidebar recolhida:** no modo recolhido (desktop), cada item do menu deve mostrar o NOME abaixo do ícone
  (texto pequeno centralizado, como no SB Admin 2 antigo), não só o ícone. Ajustar a largura da sidebar recolhida para caber
  (~96–104px, ellipsis/quebra em 2 linhas), manter tooltip/aria-label e conferir no tema claro e escuro.
- `prefers-reduced-motion`, larguras 360/768/1366, dark mode em todas as telas.
- Orçamento (decisão 10) medido com `npm run build` (gzip).
- i18n: comparar chaves `admin`/`admin_js` pt_BR × en; marcar etapa 2c como FEITA em `.claude/i18n/ESTADO.md`.
- Atualizar `ESTADO.md` para CONCLUÍDO com pendências de QA manual (navegador real, Lighthouse).
**Pronto quando:** `php artisan test` verde, build ok, nenhum `jquery`/`sb-admin`/`fa-` restante nos templates admin, sidebar recolhida com nome sob cada ícone.
