<div class="d-flex justify-content-center">
    <img src="data:image/png;base64,{{mp_qr_image}}" style='width:240px;height:240px' />
</div>

<div class="d-flex justify-content-center">
    <button type="button" class="form-control btn btn-dark" style="z-index: 2;" onclick="copyMpCodeQrCode();">COPIAR CÓDIGO PIX</button>
</div>

<div class="d-flex justify-content-center" style="margin-top:-35px;">
    <input type="text" id="mpcodeQrCode" value="{{mpcodeQrCode}}">
</div>

<script>
    function copyMpCodeQrCode() {
        var content = document.getElementById('mpcodeQrCode');

        content.select();
        document.execCommand('copy');

        alert("Codigo foi copiado com sucesso!");
    }
</script>