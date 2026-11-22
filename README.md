# Entre Amigos Rifa (Laravel 12)

Migração do sistema PHP puro (`../entreamigosrifa`) para Laravel 12.

## Rodar localmente

Requisitos: PHP 8.2+, Composer, MySQL 8.

```bash
composer install
cp .env.example .env        # ajuste DB_* (local: root / 1234)
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve           # http://127.0.0.1:8000
```

Dados do seed:

- Painel: http://127.0.0.1:8000/admin/login, com `demo@entreamigos.local` / `12345678`
- Rifa pública: http://127.0.0.1:8000/share/rifa-demo

## Estrutura

| Parte | Onde |
|---|---|
| Rotas (site, painel, API v1) | `routes/web.php` |
| Painel do dono da rifa | `app/Http/Controllers/Admin/*` |
| Site público / página da rifa | `app/Http/Controllers/Website/*` |
| API usada pelo JavaScript | `app/Http/Controllers/Api/V1Controller.php` |
| Retorno + webhook Mercado Pago | `app/Http/Controllers/MercadoPagoController.php` |
| Models (tabelas `customers`, `sortition`, `transactions`, `owner`) | `app/Models` |
| Mercado Pago (API REST) e Loteria Federal | `app/Services` |
| PIX estático (BR Code + QR em SVG) | `app/Support/Pix.php` |
| Templates herdados (`{{variavel}}`) | `resources/templates/default` via `App\Support\LegacyView` |
| Assets do tema e bibliotecas front-end | `public/themes`, `public/vendor`, `public/node_modules` |
| Uploads (fotos de rifas e perfis) | `storage/app/public/images`, servidos em `/storage/images` |

### Autenticação

Dois guards sobre a mesma tabela `customers` (`config/auth.php`):

- `admin`: dono da rifa no painel `/admin` (e-mail + senha ou Google)
- `website`: colaborador que entra na página da rifa pelo cadastro rápido (nome + telefone)

### Templates

As telas antigas foram mantidas como estavam e são renderizadas por `LegacyView`, que substitui `{{variavel}}`
e injeta o token CSRF em todo `<form method="post">` e no `jQuery.ajaxSetup`. Dá para migrar tela por tela para Blade.

### Status da rifa (`sortition.status`)

1. Aguardando pagamento da liberação (o dono paga `owner.value_by_number` × números gerados)
2. Liberada / em andamento
3. Finalizada (sorteio feito, mostra os ganhadores)

Situação de cada número (JSON em `sortition.numbers`): `D` disponível, `R` reservado, `I` pago/confirmado.

## Variáveis de ambiente

| Variável | Uso |
|---|---|
| `MP_PUBLIC_KEY`, `MP_ACCESS_TOKEN` | Conta Mercado Pago da plataforma. Localmente use credenciais `TEST-` |
| `GOOGLE_CLIENT_ID` | Botão "Entrar com Google" |
| `JWT_KEY` | Assinatura dos tokens de `POST /api/v1/auth` |
| `RIFA_VALUE_BY_NUMBER` | Valor padrão por número (a linha `owner` id=1 tem prioridade) |
| `RIFA_PIX_CITY` | Cidade no payload PIX |

O Mercado Pago só aceita `notification_url` com HTTPS público. Em `http://` local o webhook não é enviado;
o status é conferido ao abrir o checkout ou a página da rifa.

## Laravel Boost

Instalado (`laravel/boost`, dev). O MCP `laravel-boost` está em `.mcp.json`, as diretrizes em `AGENTS.md`
e as skills em `.claude/`. Para atualizar: `php artisan boost:update`.
