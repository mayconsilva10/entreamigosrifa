# Redesign da página de rifa (`/share/{shareCode}`) — Plano por etapas

> Executado pelo agente `executor-redesign`, **uma etapa por sessão**.
> Contexto entre sessões: apenas `.claude/redesign/ESTADO.md` (≤ 8 linhas) + a seção da etapa abaixo.
> Dependências front-end listadas aqui estão **aprovadas pelo usuário** (exceção à regra do AGENTS.md).

## Contexto fixo (não reexplorar)

- Renderização: `app/Http/Controllers/Website/SharedController.php` → `raffle()` monta variáveis e chama
  `LegacyView::render('website/shared/shared', ...)` (templates PHP com placeholders `{{var}}`).
- Templates: `resources/templates/default/website/shared/{__master,shared,photos,photos_all}.php`,
  `.../numbers/*.php`, `.../register/fast_register.php`, `.../payments/checkout.php`.
- Front atual: Bootstrap 5 + jQuery + GLightbox + Swiper + AOS (em `/public/vendor`), sem Vite na página.
- Fluxo atual (causa do atrito): cada clique chama `POST /api/v1/setNumberCustomer/{share}/{idCustomer}`
  (`V1Controller::toggleNumber`, `routes/web.php:98`) e **reserva na hora** → exige cliente logado →
  modal `fast_register` aberto à força (`style="display:block"`).
- Grade atual renderiza só números disponíveis + reservados pelo visitante (reservados/pagos de terceiros somem).
- Pagamento: Mercado Pago (`/getMercadoPagoCheckOut/{share}`, `auth:website`) ou modal Pix do dono.

## Decisões de arquitetura (valem para todas as etapas)

1. **Seleção é carrinho no cliente** (Alpine store + `sessionStorage`); nada é reservado ao clicar.
2. **Cadastro só no checkout**: drawer/bottom-sheet "Telefone → (se novo) Nome" via AJAX no `fastRegister`
   (resposta JSON quando `expectsJson()`), sem recarregar a página.
3. **Reserva em lote**: novo endpoint `POST /api/v1/reserveNumbers/{shareCode}` (transação + `lockForUpdate`),
   retorna `{reserved:[], conflicts:[], total_pay, expires_at}`. `toggleNumber` permanece por compatibilidade.
4. **Grade por dados, não por HTML**: controller injeta JSON compacto de status (`window.__RAFFLE__`:
   preço, total, string de status por número `D/R/U`, meus números). Cliente renderiza faixas de 100.
5. **CSS isolado**: `resources/css/raffle.css` (CSS puro com tokens em `:root`), **sem Tailwind** nesta página
   (o preflight do Tailwind 4 conflita com Bootstrap). JS em `resources/js/raffle.js`, injetado no
   `shared/__master.php` via placeholder `{{viteAssets}}` gerado no controller (`Vite` facade).
6. **Reaproveitar o que já carrega**: GLightbox (zoom), Swiper (galeria), Bootstrap Icons (ícones). Nada de Lucide/GSAP.
7. Bibliotecas novas (npm, via Vite): `alpinejs`, `@alpinejs/mask`, `@alpinejs/collapse`, `motion`,
   `canvas-confetti` (import dinâmico só na confirmação). Orçamento: **≤ 35 kB gzip** de JS novo.

---

## Etapa 1 — Arquitetura e Fluxo

**Objetivo:** base técnica pronta, sem mudar visual ainda.
- Instalar dependências npm acima; criar `resources/js/raffle.js` (Alpine + store `cart`) e `resources/css/raffle.css`;
  registrar ambos em `vite.config.js` (`input`).
- `SharedController::raffle()`: gerar `raffleData` JSON (ver decisão 4) e `viteAssets`; parar de renderizar o
  `fast_register` automaticamente (manter template para uso no drawer).
- Endpoint `reserveNumbers` (controller V1, rota em `routes/web.php` no grupo `api/v1`), com validação via FormRequest,
  transação, conflitos e expiração de reserva (usar regra/tempo já existente no modelo, se houver).
- `CustomerController::fastRegister`: responder JSON quando `expectsJson()`; buscar cliente existente pelo telefone.
- Feature tests: reserva em lote OK, conflito (número já reservado), total calculado no servidor, cadastro JSON.
**Pronto quando:** `php artisan test` verde, `npm run build` ok, página antiga ainda funciona.

## Etapa 2 — Grid de Números e Checkout Fixo

**Objetivo:** nova experiência de escolha e barra sticky.
- Reestruturar `shared.php`: Hero (galeria Swiper 4:5 + GLightbox, título, preço, data com "em X dias",
  barra % vendido, organizador com foto/WhatsApp, compartilhar) → faixa de confiança (Pix, números na hora,
  ganhador avisado, resumo de `draw_rules` + accordion completo) → seletor de números.
- Remover abas (Bilhetes/Meus Números/Fotos/Regulamento); "Meus números" vira link "Consultar pelo telefone".
- Seletor: busca (pula para a faixa e destaca; se ocupado, sugere o próximo livre), filtros com contagem
  (Disponíveis/Reservados/Pagos/Todos), chips de faixas `0001–0100 …` com livres por faixa, grid 10 col desktop /
  5 col mobile, botões ≥ 44 px, legenda de 4 estados (ícone + cor + `aria-label`), atalhos `+1 +5 +10`,
  `Aleatório`, `Limpar`. Padronizar rótulos via `$raffle->label()`.
- Barra fixa inferior (aparece com ≥1 selecionado): qtd, chips dos números (expansível), total, botão
  "PAGAR RIFA" (desabilitado com 0). WhatsApp flutuante sobe acima da barra.
- Drawer de checkout: telefone (máscara) → nome se novo → `reserveNumbers` → contador de reserva → MP ou Pix.
  Tratar conflitos (remover do carrinho + aviso).
**Pronto quando:** fluxo completo funciona no celular (360 px) e desktop sem recarregar a página.

## Etapa 3 — Plugins e Animações

**Objetivo:** microinterações funcionais, leves.
- Seleção: escala 0.92→1.05→1 (~180 ms) + `navigator.vibrate(10)` quando suportado.
- Barra sticky: slide-up 250 ms; total e contador com tween numérico (`motion` `animate`) + pulse.
- Aleatório/+N: stagger 40 ms nos números escolhidos; chip da faixa pulsa se fora da tela.
- Troca de faixa: fade/slide 150 ms. Conflito: shake 300 ms + toast.
- Compartilhar: `navigator.share()` → fallback `clipboard` com ícone ✓ "Link copiado!" 2 s; idem Pix copia-e-cola.
- Contador de reserva fica vermelho/pulsa nos 2 min finais. Confetti **só** em pagamento confirmado (import dinâmico).
- Skeleton no grid enquanto monta. Tudo desligado em `prefers-reduced-motion`. Só `transform`/`opacity`.
**Pronto quando:** nenhuma animação bloqueia interação; reduced-motion respeitado.

## Etapa 4 — Código e Microcopy

**Objetivo:** textos, acessibilidade e limpeza.
- Aplicar microcopy (tabela abaixo); remover "Click aqui", ícone de livro, alertas genéricos.
- A11y: foco visível, `aria-pressed` nos números, `aria-live` no total, labels nos inputs, contraste AA.
- Remover JS/CSS mortos do fluxo antigo nesta página (manter `toggleNumber` no backend).
- Meta OG com foto do prêmio e preço.

| Onde | Texto |
|---|---|
| Título do seletor | Escolha seus números da sorte 🍀 |
| Contador | {livres} de {total} ainda disponíveis |
| Aleatório | 🎲 Escolher pra mim |
| Botão pagar (0) | Escolha ao menos 1 número |
| Botão pagar (n) | PAGAR RIFA · R$ {total} |
| Drawer | Só falta seu WhatsApp para reservar seus números |
| Ajuda telefone | Usamos só para avisar se você ganhar |
| Reserva | Seus números estão guardados por {mm:ss}. Pague para garantir. |
| Busca ocupado | O {n} já tem dono 😅 O {m} está livre — quer ele? |
| Conflito | Ops! Alguém acabou de pegar o {n}. Removemos do seu carrinho. |
| Compartilhar | Compartilhar rifa → Link copiado! |
| Sucesso | Pagamento confirmado! Você concorre com {números}. Sorteio em {data}. |

## Etapa 5 — QA e Performance

- `php artisan test` + novos testes (concorrência de reserva, preço adulterado no cliente ignorado).
- Build de produção; medir JS/CSS novos (≤ 35 kB gzip JS); Lighthouse mobile (meta: Perf ≥ 85, A11y ≥ 95, CLS < 0.1).
- Testar 360 px, 768 px, 1366 px; iOS Safari (sem vibrate), Android Chrome; rifa com 100 e 10.000 números.
- Checklist manual: visitante novo, cliente existente, número disputado, reserva expirada, MP e Pix do dono.
- Corrigir regressões encontradas; registrar pendências no ESTADO.md.
