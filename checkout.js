document.addEventListener("DOMContentLoaded", function() {
    let checkoutButton = document.getElementById("checkout-button");

    if (checkoutButton) {
        checkoutButton.addEventListener("click", function(event) {
            event.preventDefault(); 

            let paymentMethod = document.querySelector('select[name="payment_method"]').value;

            if (typeof totalAmount === "undefined" || totalAmount <= 0) {
                alert("Invalid amount. Please add items to your cart.");
                return;
            }

            if (paymentMethod === "Paystack") {
                let handler = PaystackPop.setup({
                    key: 'pk_test_b795400fb17600dbc52bd090f351624ef243845e', // Paystack Public Key
                    email: userEmail, 
                    amount: totalAmount, 
                    currency: "NGN",
                    ref: "TXN_" + Math.floor((Math.random() * 1000000000) + 1),
                    callback: function(response) {
                        window.location.href = "paystack_verify.php?reference=" + response.reference;
                    },
                    onClose: function() {
                        alert('Transaction was not completed');
                    }
                });
                handler.openIframe();
            } else {
                document.querySelector("form").submit();
            }
        });
    } else {
        console.error("Checkout button not found!");
    }
});


