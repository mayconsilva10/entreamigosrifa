<div class="d-flex justify-content-center">
    <img src="data:image/svg+xml;base64,{{qrCode}}" alt="{{t:raffle.pix_qr_alt}}" width="240" height="240" style="width: 240px; height:240px">
</div>

<div class="d-flex justify-content-center">
    <button type="button" class="form-control btn btn-dark" style="z-index: 2;" data-label="{{t:raffle.pix_copy}}" data-copied="{{t:raffle.pix_copied}}" onclick="copyCodeQrCode();">{{t:raffle.pix_copy}}</button>
</div>

<div class="d-flex justify-content-center" style="margin-top:-35px;">
    <label for="codeQrCode" class="visually-hidden">{{t:raffle.pix_code_label}}</label>
    <input type="text" id="codeQrCode" value="{{codeQrCode}}" readonly>
</div>

<script>
    // Na página da rifa o raffle.js já define copyCodeQrCode (com feedback no botão); aqui só o fallback.
    window.copyCodeQrCode = window.copyCodeQrCode || function () {
        var content = document.getElementById('codeQrCode');
        var button = document.querySelector('[onclick*="copyCodeQrCode"]');

        content.select();
        document.execCommand('copy');

        if (button) {
            button.textContent = button.dataset.copied;
            setTimeout(function () { button.textContent = button.dataset.label; }, 2000);
        }
    };
</script>
