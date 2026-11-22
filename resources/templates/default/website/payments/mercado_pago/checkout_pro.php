
<script src="https://sdk.mercadopago.com/js/v2"></script>
<script>
// Adicione as credenciais do SDK
  const mp = new MercadoPago('{{mp_public_key}}', {
        locale: 'pt-BR'
  });

  // Inicialize o checkout
  mp.checkout({
      preference: {
          id: '{{id_preference}}'
      },
      render: {
            container: '.cho-container', // Indique o nome da class onde será exibido o botão de pagamento
            label: 'Pagar Rifa', // Muda o texto do botão de pagamento (opcional)
      }
});

 // Inicialize o checkout
 mp.checkout({
      preference: {
          id: '{{id_preference}}'
      },
      render: {
            container: '.cho-mobile', // Indique o nome da class onde será exibido o botão de pagamento
            label: 'Pagar Rifa', // Muda o texto do botão de pagamento (opcional)
      }
});
</script>