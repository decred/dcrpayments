package util

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"net/http"
	"net/url"
	"strconv"
	"strings"
	"time"

	"github.com/decred/dcrd/chaincfg"
	"github.com/decred/dcrd/dcrutil"
	"github.com/decred/dcrd/hdkeychain"
	"github.com/decred/dcrwallet/wallet/udb"
)

// DcrDataAddressResponse models the expected JSON response from an address query.
type DcrDataAddressResponse struct {
	Address             string `json:"address"`
	AddressTransactions []struct {
		Txid          string  `json:"txid"`
		Size          int     `json:"size"`
		Time          int     `json:"time"`
		Value         float64 `json:"value"`
		Confirmations int     `json:"confirmations"`
	} `json:"address_transactions"`
}

// FaucetResponse represents the expected JSON response from the testnet faucet.
type FaucetResponse struct {
	Txid  string
	Error string
}

// InsightAddressResponse models the expected JSON response from an address query.
type InsightAddressResponse []struct {
	Size     int    `json:"size"`
	Txid     string `json:"txid"`
	Version  int    `json:"version"`
	Locktime int    `json:"locktime"`
	Vin      []struct {
		Txid        string  `json:"txid"`
		Vout        int     `json:"vout"`
		Tree        int     `json:"tree"`
		Amountin    float64 `json:"amountin"`
		Blockheight int     `json:"blockheight"`
		Blockindex  int     `json:"blockindex"`
		ScriptSig   struct {
			Asm string `json:"asm"`
			Hex string `json:"hex"`
		} `json:"scriptSig"`
		PrevOut struct {
			Addresses []string `json:"addresses"`
			Value     float64  `json:"value"`
		} `json:"prevOut"`
		Sequence int64 `json:"sequence"`
	} `json:"vin"`
	Vout []struct {
		Value        float64 `json:"value"`
		N            int     `json:"n"`
		Version      int     `json:"version"`
		ScriptPubKey struct {
			Asm       string   `json:"asm"`
			ReqSigs   int      `json:"reqSigs"`
			Type      string   `json:"type"`
			Addresses []string `json:"addresses"`
		} `json:"scriptPubKey"`
	} `json:"vout"`
	Confirmations int    `json:"confirmations"`
	Blockhash     string `json:"blockhash"`
	Time          int    `json:"time"`
	Blocktime     int    `json:"blocktime"`
}

func getNetworkName(params *chaincfg.Params) string {
	if strings.HasPrefix(params.Name, "testnet") {
		return "testnet"
	}
	return params.Name
}

// DeriveAddress derives an address using the provided xpub and address index.
func DeriveAddress(params *chaincfg.Params, xpub string, index uint32) (string, error) {
	// Parse the extended public key.
	acctKey, err := hdkeychain.NewKeyFromString(xpub)
	if err != nil {
		return "", err
	}

	// Derive the appropriate branch key.
	branchKey, err := acctKey.Child(udb.ExternalBranch)
	if err != nil {
		return "", err
	}

	key, err := branchKey.Child(index)
	if err != nil {
		return "", err
	}

	addr, err := key.Address(params)
	if err != nil {
		return "", err
	}

	return addr.EncodeAddress(), nil
}

// PayWithTestnetFaucet makes a request to the testnet faucet to pay for
func PayWithTestnetFaucet(faucetURL string, address string, amount float64, overridetoken string) (string, error) {
	dcraddress, err := dcrutil.DecodeAddress(address)
	if err != nil {
		return "", fmt.Errorf("address is invalid: %v", err)
	}

	if !dcraddress.IsForNet(&chaincfg.TestNet2Params) {
		return "", fmt.Errorf("faucet only supports testnet")
	}

	dcramount := strconv.FormatFloat(amount, 'f', -1, 32)
	if err != nil {
		return "", fmt.Errorf("unable to process amount: %v", err)
	}

	// build request
	form := url.Values{}
	form.Add("address", address)
	form.Add("amount", dcramount)
	form.Add("overridetoken", overridetoken)

	req, err := http.NewRequest("POST", faucetURL, strings.NewReader(form.Encode()))
	if err != nil {
		return "", err
	}
	req.PostForm = form
	req.Header.Add("Content-Type", "application/x-www-form-urlencoded")

	// limit the time we take
	ctx, cancel := context.WithTimeout(context.Background(), 2500*time.Millisecond)
	// it is good practice to use the cancellation function even with a timeout
	defer cancel()
	req.WithContext(ctx)

	client := &http.Client{}
	resp, err := client.Do(req)
	if err != nil {
		return "", err
	}

	if resp == nil {
		return "", errors.New("unknown error")
	}

	jsonReply := resp.Header.Get("X-Json-Reply")
	if jsonReply == "" {
		return "", fmt.Errorf("bad reply from %v", faucetURL)
	}

	fr := &FaucetResponse{}
	err = json.Unmarshal([]byte(jsonReply), fr)
	if err != nil {
		return "", fmt.Errorf("unable to process reply: '%v': %v", jsonReply, err)
	}

	if fr.Error != "" {
		return "", errors.New(fr.Error)
	}

	return fr.Txid, nil
}
