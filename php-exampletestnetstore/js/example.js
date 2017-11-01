var orderData = new Object();
var counter = new Object();
var qrcode = new QRCode(document.getElementById("modal-qrcode"), {
    width: 128,
    height: 128,
    colorDark : "#000000",
    colorLight : "#ffffff",
    correctLevel : QRCode.CorrectLevel.H
});

$("#orderModal").on("show.bs.modal", function (event) {
  var button = $(event.relatedTarget);
  var modal = $(this);
  var orderId = 0;
  var paymentAddress = "";
  var paymentAmount = 0.00;
  var userId = 0;
  var productId = button.data("productid");
  if (productId != 1 && productId != 2) {
      alert("invalid productId: not 1 or 2!");
      return
  }

  // Reset to default state
  $("#modal-qrcode").hide();
  qrcode.clear();
  $("#modal-instructions").html();
  $("#modal-tx").html();
  $("#modal-status").html();
  $("#modal-error").html();
  $("#modal-success").html();

  // make an order of this product for the user
  var orderPromise = $.ajax({
    url: "index.php",
    data: {
        "cmd": "setOrder",
        "productId": productId,
    },
  });
    
  orderPromise.then(
    // success
    function(result, textStatus) {
        orderData = $.parseJSON(result);
        r = $.parseJSON(result);
        // error
        if (r["err"] != "") {
            console.log(r[err]);
            orderError(r[err]);
            return;
        // success
        } else {
            orderId = r["data"]["OrderId"];
            paymentAddress = r["data"]["PaymentAddress"];
            paymentAmount = r["data"]["PaymentAmount"];
            userId = r["data"]["UserId"];
            console.log("order data set; orderId=" + orderId +
                ",paymentAddress=" + paymentAddress + ",paymentAmount="
                + paymentAmount, ",userId=" + userId);
            modal.find(".modal-title").text("Order #" + orderId);
            $("#modal-instructions").html("<strong>Instructions:</strong> &nbsp;Please send "
                + "<strong>exactly</strong> " + paymentAmount + " DCR to "
                + "<strong>" + paymentAddress + "</strong> to complete your "
                + "order.");
            qrcode.makeCode(paymentAddress);
            $("#modal-qrcode").show();

            var orderUpdater = $.PeriodicalUpdater("index.php", {
                method: 'get',
                data: {
                    "cmd": "getOrder",
                    "orderId": orderId,
                    "userId": userId,
                },
                maxCalls: 0,
                autoStop: 0,
                minTimeout: 15000,
                maxTimeout: 30000,
                multiplier: 2,
                runatonce: false,
                type: "text",
                verbose: 0
            }, function(result, success, xhr, handle) {
                if (success) {
                    r = $.parseJSON(result);
                    // error
                    if (r["err"] != "") {
                        console.log(r["err"]);
                        orderError(r["err"]);
                        return;
                    // success
                    } else {
                        $("#modal-status").html("<strong>Status:</strong> "
                            + "&nbsp;" + r["data"]["OrderStatus"]);
                        if (r["data"]["PaymentTx"] != "") {
                            $("#modal-tx").html("<strong>TX:</strong> "
                            + "&nbsp;" + r["data"]["PaymentTx"]);
                        }
                        if (r["data"]["Done"] == 1) {
                            orderSuccess("Payment complete! You may now " +
                                "close the modal.");
                            orderUpdater.stop();
                            return;
                        }
                    }
                } else {
                    orderError("unable to order update from server");
                    orderUpdater.stop();
                }
            });

            $("#orderModal").on("hide.bs.modal", function (event) {
                console.log("modal hidden, stopping updater");
                orderUpdater.stop();
            });
        }
    // error
    },function() {
        orderError();
    });
});

function orderError(err) {
    $("#modal-error").html('<div class="alert alert-danger" role="alert">' +
        '<strong>Error!</strong> ' + err + '</div>');
}

function orderSuccess(success) {
    $("#modal-success").html('<div class="alert alert-success" role="alert">' +
    '<strong>Done!</strong> ' + success + '</div>');
}
