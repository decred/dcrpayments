dcrpayd
====

dcrpayd is a Go microservice designed to facilitate Decred payments.

## Usage

```bash no-highlight
    go run *.go

    dcrctl --wallet createaccount incomingpayments
    dcrctl --wallet getmasterpubkey incomingpayments

    curl http://127.0.0.1:8080/address/{masterpubkey}/1
    curl http://127.0.0.1:8080/address/{masterpubkey}/2
    curl http://127.0.0.1:8080/pricequote
```