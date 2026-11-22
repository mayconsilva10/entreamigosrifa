<form class="adm-settings-form" action="/admin/customer/setPayments" method="post" data-adm-submit>
  {{pix}}
  {{mercado_pago}}
  {{bank}}

  <div class="adm-formbar">
    <button type="submit" class="adm-btn adm-btn--primary"><i class="bi bi-check2" aria-hidden="true"></i><span>{{t:admin.settings.save}}</span></button>
  </div>
</form>
