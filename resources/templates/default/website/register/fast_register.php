<div class="modal fade" id="fastRegister" tabindex="-1" aria-labelledby="fastRegisterLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fastRegisterLabel"><i class="fas fa-book-open"></i> Cadastre-se para Concorrer </h5>
            </div>
            <div class="modal-body">
                <div class="tab-content border-bottom" id="nav-tabContent">
                    <div class="tab-pane fade show active" id="nav-register" role="tabpanel" aria-labelledby="nav-register-tab">
                        {{status}}
                        <!-- Section: Design Block -->
                        <form action="/customer/fastRegister" method="post">
                            <!-- Nome do cliente input -->
                            <div class="form-outline mb-2">
                                <input type="text" name="username" id="username" tabindex="1" class="form-control" placeholder="Nome Completo" value="" required>
                            </div>

                            <!-- Telelefone do cliente input -->
                            <div class="form-outline mb-4">
                                <input type="tel" name="phone" id="phoneFastRegister" class="form-control phone" placeholder="Telefone" required>
                            </div>
                            <input type="hidden" name="codeShare" value="{{codeShare}}">
                            <!-- Submit button -->
                            <button type="submit" class="form-control btn-block mb-4 text-white" style="background-color: #5741A3;">
                                Começar a comprar os números
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal-backdrop fade show"></div>