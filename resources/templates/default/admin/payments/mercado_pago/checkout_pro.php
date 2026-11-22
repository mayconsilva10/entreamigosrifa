<div class="adm-checkout__mp" id="adm-mp-button" data-public-key="{{mp_public_key}}" data-preference="{{id_preference}}" data-locale="{{mp_locale}}" data-label="{{t:admin.checkout.card.button}}"></div>
<script src="https://sdk.mercadopago.com/js/v2"></script>
<script>
  (function () {
    var el = document.getElementById('adm-mp-button');
    if (!el || typeof MercadoPago === 'undefined') return;
    var mp = new MercadoPago(el.dataset.publicKey, { locale: el.dataset.locale });
    mp.checkout({ preference: { id: el.dataset.preference }, render: { container: '#adm-mp-button', label: el.dataset.label || undefined } });
  })();
</script>
