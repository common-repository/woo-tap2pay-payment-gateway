(function($){
    $paymentField = $('#tap2pay-payment-widget').first();
    if($paymentField.length == 0) {
        console.log("Tap2pay: missing payment field");
        return;
    }
    merchantId = $paymentField.data('merchant-id');
    if(!merchantId) {
        console.log("Tap2pay: missing merchant_id");
        return;
    }

    invoiceId = $paymentField.data('invoice-id');
    if(!invoiceId) {
        console.log("Tap2pay: missing invoice_id");
        return;
    }

    nextUrl = $paymentField.data('success-url');

    var T2P_Handler = new T2P.Checkout({merchant_id: merchantId});

    var next = 0;
    T2P_Handler.on('chatOpened', function() { next = 1; });
    T2P_Handler.on('complete', function() { next = 2; });
    T2P_Handler.on('close', function() {
        switch(next){
        case 0:
            break;
        case 1:
            setTimeout(function(){ window.location.reload(); }, 500);
            break;
        case 2:
            setTimeout(function(){
                if(nextUrl) {
                    window.location.href=nextUrl;
                } else {
                    window.location.reload();
                }
            }, 500);
            break;
        }
    });


    $paymentField.find('.tap2pay-pay-btn').click(function(e){
        e.preventDefault();

        T2P_Handler.openInvoice(invoiceId);
    });
})(jQuery);
