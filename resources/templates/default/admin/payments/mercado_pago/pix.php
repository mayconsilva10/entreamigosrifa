<div class="adm-pix">
  <img class="adm-pix__qr" src="data:image/png;base64,{{qr_image}}" width="220" height="220" alt="{{t:admin.checkout.pix.qr_alt}}">
  <div class="adm-pix__code">
    <label class="adm-label" for="adm-pix-code">{{t:admin.checkout.pix.code}}</label>
    <div class="adm-input-group">
      <input type="text" class="adm-input is-mono" id="adm-pix-code" value="{{qr_code}}" readonly @focus="$el.select()">
      <button type="button" class="adm-btn adm-btn--primary" x-copy="document.getElementById('adm-pix-code').value"><i class="bi bi-files" aria-hidden="true"></i>{{t:admin.checkout.pix.copy}}</button>
    </div>
    <p class="adm-hint">{{expires}}</p>
    <ol class="adm-pix__how">
      <li>{{t:admin.checkout.pix.step1}}</li>
      <li>{{t:admin.checkout.pix.step2}}</li>
      <li>{{t:admin.checkout.pix.step3}}</li>
    </ol>
  </div>
</div>
