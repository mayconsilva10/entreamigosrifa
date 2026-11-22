<!-- ======= Contact Section ======= -->
<section id="contact" class="contact" style="margin-top: -60px;">
    <div class="container">
        <div class="section-title">
            <h2>Pagamento Pendente</h2>
            <p>Pague a rifa e compartilhe com seus amigos!</p>
        </div>

        <div class="row">
           <!-- <div class="col-lg-6 d-flex align-items-stretch">
                <div class="info">
                    <div class="d-flex align-items-center justify-content-between">
                        <span></span>
                        <img src="{{URL}}/storage/images/payment/logo-pix.png" width="20">
                    </div>

                    {mp_page_pix}
                </div>
            </div>-->

            <div class="col-lg-12 mt-2 mt-lg-0 d-flex align-items-stretch">
                <div class="php-email-form">
                    <div class="d-flex justify-content-between mb-3">
                        <div class="address">
                            <h4>RIFA</h4>
                            <p class="text-dark">{{title_sortition}}</p>
                        </div>
                        <h3><b>{{total_pay}}</b></h3>
                    </div>
                    
                    <hr />

                    <div class="d-flex justify-content-between mb-3">
                        <span>Cotas</span>
                        <h4>{{qtd_numbers}}</h4>
                    </div>

                    <div class="d-flex align-items-end justify-content-center" style="height: 200px;">
                        <div class="mt-auto p-2 bd-highlight">
                            <div class="cho-container"></div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- End Contact Section -->
{{mp_checkout_pro}}
<script>
    function copyCodeQrCode() {
        var content = document.getElementById('codeQrCode');

        content.select();
        document.execCommand('copy');

        alert("Codigo foi copiado com sucesso!");
    }
</script>




<script>
    function copyCodeQrCode() {
        var content = document.getElementById('codeQrCode');

        content.select();
        document.execCommand('copy');

        alert("Codigo foi copiado com sucesso!");
    }
</script>