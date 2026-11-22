<div class="modal fade " id="paymentSharedProprietary" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="paymentSharedProprietaryLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="paymentSharedProprietaryLabel">PAGAMENTO DE NÚMEROS DA RIFA</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <section id="faq" class="faq section-bg">
                    <div class="container aos-init aos-animate" data-aos="fade-up">
                        
                        <div id="faq-list-2" class="collapse show" data-bs-parent=".faq-list">
                            {{pagePixGenerate}}
                        </div>
                        
                        <!--<div class="faq-list">
                            <ul>
                                <li data-aos="fade-up" data-aos-delay="200" class="aos-init aos-animate">
                                    <i class="fa-brands fa-pix icon-help"></i> <a data-bs-toggle="collapse" data-bs-target="#faq-list-2" class="collapsed" aria-expanded="true">PIX<i class="bx bx-chevron-down icon-show"></i><i class="bx bx-chevron-up icon-close"></i></a>
                                    <div id="faq-list-2" class="collapse show" data-bs-parent=".faq-list">
                                        <p>
                                            
                                        </p>
                                    </div>
                                </li>
                            </ul>
                        </div>-->
                    </div>
                </section>
            </div>
            <div class="modal-footer">
                <a href="{{URL}}/checkoutFinish/{{shared}}" class="btn btn-primary">Finalizar Pagamento</a>
            </div>
        </div>
    </div>
</div>