PROJETO: Internacionalização (EUA/Europa). Pagamentos (Stripe/PayPal) ficam FORA até validação jurídica — ver "Bloqueio" abaixo.

PRÓXIMA ETAPA: 2b — extrair textos do restante do site público

ETAPAS
1. [FEITO] Base de i18n: middleware SetLocale, {{t:}} no LegacyView, lang/{pt_BR,en}, seletor de idioma, Money, colunas raffles.currency / customers.locale
2a. [FEITO] Página pública da rifa (/share): shared.php, shared/__master.php, pix.php, raffle.js, SharedController, V1Controller, CustomerController (fastRegister), ReserveNumbersRequest, RaffleHelpers
2b. [ ] Restante do site público: website/__master, site/home, header, termos/privacidade, checkout/*, payments/*, register/*, shared/winner.php, shared/customers/*, MercadoPagoController (mensagens), HomeController
2c. [FEITO] (junto com o redesign do painel, etapas 1–7 — .claude/painel/PLANO.md decisão 8) Painel admin: ~50 templates em admin/**, controllers Admin/* (mensagens, number_format, 'R$'), máscara de preço "R$ 1,234.56" em RaffleController::update/store
3. [ ] Dinheiro/moeda/telefone: seletor de moeda na criação da rifa (config rifa.currencies); preço em centavos inteiros (amount_minor); telefone E.164 (libphonenumber) + campo país; remover '+55' fixo (SharedController, RaffleController, RaffleHelpers::ownerPix); máscara do telefone por país no front; validação "< 10 dígitos" no fastRegister/raffle.js
4. [ ] Fuso horário: raffles.timezone (draw_date hoje é salvo sem fuso, app em UTC); exibir no fuso do visitante
5. [ ] Conteúdo regional: selos "Pague com Pix" e WhatsApp condicionais por meio de pagamento/região; Loteria Federal só p/ BR (alternativa: RNG auditável commit-reveal); GDPR/CCPA (cookies, exportar/excluir dados), termos por região
6. [ ] Novos idiomas: es, de, fr... (copiar lang/en/*.php + incluir em config rifa.locales; Money::FORMATS já cobre)

CONVENÇÕES
- Arquivos: lang/{locale}/{grupo}.php. Grupos: common, raffle (página pública + mensagens servidor), raffle_js (enviado ao navegador em __RAFFLE__.i18n). Criar grupo novo por área (ex.: admin, site).
- Templates legados: {{t:grupo.chave}} (escapado) | {{t!:grupo.chave}} (HTML) | {{LANG}} | {{LOCALE_SWITCHER}}. Traduções podem conter {{variavel}} da view.
- NÃO colocar {{t:}} dentro de string JS em atributo Alpine (o escape quebra aspas): usar $t('chave') / $tc('chave', n) com chave em raffle_js.
- JS: t(key, {param}) e tc(key, n, {param}); plural "singular|plural"; placeholders :param.
- PHP: __('grupo.chave', [...]) / trans_choice. Dinheiro: $raffle->formatMoney($v) (moeda da rifa) ou Raffle::money($v, $currency) / App\Support\Money::format. Datas: translatedFormat(__('common.datetime_format')).
- Idioma: ?lang= > cookie "locale" > customers.locale > Accept-Language > config app.locale (pt_BR). URLs sem prefixo de idioma.
- Testes: tests/TestCase envia Accept-Language pt-BR por padrão; LocalizationTest cobre en.
- Toda chave nova vai nos DOIS idiomas (checar: php -r comparando array_keys).

AMBIENTE
- PHP 8.2 sem extensão intl: Money usa tabela própria (NumberFormatter quando houver intl). Carbon translatedFormat funciona sem intl.

BLOQUEIO (negócio, não técnico)
- Rifa paga = loteria nos EUA/UE; Stripe/PayPal exigem aprovação prévia p/ loterias/rifas. Validar modelo com advogado antes da etapa de pagamentos. Ideias: dono recebe direto (plataforma não toca no dinheiro), cobrança como SaaS, rifas só por convite, modo ONG, entrada gratuita alternativa.
