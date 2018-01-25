package main

import (
	"context"
	"encoding/json"
	"errors"
	"io/ioutil"
	"net/http"
	"strconv"
	"sync"
	"time"

	"github.com/decred/dcrd/chaincfg"
	"github.com/decred/dcrpayments/util"
	"github.com/go-chi/chi"
	"github.com/go-chi/chi/middleware"
	"github.com/go-chi/render"

	"github.com/pressly/lg"
	"github.com/sirupsen/logrus"
)

const (
	appListener     = ":8080"
	appName         = "dcrpayd"
	appVersion      = "1.0.0"
	priceCacheSecs  = 300
	priceServiceURL = "https://api.coinmarketcap.com/v1/ticker/decred/"
)

// Address is used as a response type.
type Address struct {
	Addr string
}

// AddressInput is the required fields to process a GET address request
type AddressInput struct {
	AccountExtendedPubKey string
	Index                 uint32
}

// AddressGetResponse models the response to a GetAddress query.
type AddressGetResponse struct {
	*Address
}

// ErrNotFound is used to reply when a given resource cannot be located.
var ErrNotFound = &ErrResponse{
	HTTPStatusCode: 404,
	StatusText:     "Resource not found.",
}

// ErrResponse is ...
type ErrResponse struct {
	Err            error `json:"-"`
	HTTPStatusCode int   `json:"-"`

	StatusText string `json:"status"`
	AppCode    int64  `json:"code,omitempty"`
	ErrorText  string `json:"error,omitempty"`
}

// Price is used as a response type.
type Price struct {
	Price float64
}

// PriceQuoteGetResponse models the response to a GetPriceQuote query.
type PriceQuoteGetResponse struct {
	*Price
}

type pricequotes struct {
	sync.RWMutex

	params *chaincfg.Params

	priceQuoteCurrentIndex int64
	priceQuotes            map[int64]pricequoteData
}

type pricequoteData struct {
	price           float64
	source          string
	quoteTimeFetch  int64
	quoteTimeSource int64
}

// PriceQuotes is our memory store
var PriceQuotes = &pricequotes{
	params:      &chaincfg.TestNet2Params,
	priceQuotes: make(map[int64]pricequoteData),
}

// CoinmarketcapV1Response models the response from V1 of the coinmarketcap API
type CoinmarketcapV1Response struct {
	ID               string `json:"id"`
	Name             string `json:"name"`
	Symbol           string `json:"symbol"`
	Rank             string `json:"rank"`
	PriceUsd         string `json:"price_usd"`
	PriceBtc         string `json:"price_btc"`
	Two4HVolumeUsd   string `json:"24h_volume_usd"`
	MarketCapUsd     string `json:"market_cap_usd"`
	AvailableSupply  string `json:"available_supply"`
	TotalSupply      string `json:"total_supply"`
	MaxSupply        string `json:"max_supply"`
	PercentChange1H  string `json:"percent_change_1h"`
	PercentChange24H string `json:"percent_change_24h"`
	PercentChange7D  string `json:"percent_change_7d"`
	LastUpdated      string `json:"last_updated"`
}

func main() {
	// Initialize logger
	logger := logrus.New()
	lg.RedirectStdlogOutput(logger)
	lg.DefaultLogger = logger

	// Initialize router and middleware usage
	r := chi.NewRouter()
	r.Use(NewStructuredLogger(logger))
	r.Use(middleware.RequestID)
	r.Use(render.SetContentType(render.ContentTypeJSON))

	serverCtx := context.Background()
	serverCtx = lg.WithLoggerContext(serverCtx, logger)
	lg.Log(serverCtx).Infof("Starting %s %s on %s", appName, appVersion, appListener)

	// GET /
	r.Get("/", func(w http.ResponseWriter, r *http.Request) {
		w.Write([]byte(appName + " " + appVersion + " says hello world!"))
		return
	})

	r.Route("/address", func(r chi.Router) {
		r.Route("/{AccountExtendedPubKey}", func(r chi.Router) {
			r.Route("/{Index}", func(r chi.Router) {
				r.Use(addressCtx)
				r.Get("/", addressGet) // GET /address/{AccountExtendedPubKey}/{Index}
			})
		})
	})

	r.Get("/pricequote", pricequoteGet)

	updatePrice()

	priceTicker := time.NewTicker(time.Second * priceCacheSecs)
	go func() {
		for range priceTicker.C {
			updatePrice()
		}
	}()

	http.ListenAndServe(appListener, r)
}

// addressCtx is middleware used to populate an input struct from the URL params.
func addressCtx(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		var addressInput *AddressInput

		xpub := chi.URLParam(r, "AccountExtendedPubKey")
		idx := chi.URLParam(r, "Index")

		if idx == "" || xpub == "" {
			render.Status(r, http.StatusPreconditionFailed)
			render.Render(w, r, ErrNotFound)
			return
		}

		index, err := Uint32(idx)
		if err != nil {
			render.Status(r, http.StatusExpectationFailed)
			render.Render(w, r, ErrNotFound)
			return
		}

		addressInput = &AddressInput{
			AccountExtendedPubKey: xpub,
			Index: index,
		}

		ctx := context.WithValue(r.Context(), "addressInput", addressInput)
		next.ServeHTTP(w, r.WithContext(ctx))
	})
}

// addressGet derives the given address for the given xpub.
func addressGet(w http.ResponseWriter, r *http.Request) {
	addressInput := r.Context().Value("addressInput").(*AddressInput)

	derivedAddress, err := deriveAddress(addressInput.AccountExtendedPubKey, addressInput.Index)
	if err != nil {
		render.Status(r, http.StatusExpectationFailed)
		render.Render(w, r, ErrNotFound)
		return
	}

	addr := Address{
		Addr: derivedAddress,
	}

	agr := &AddressGetResponse{
		Address: &addr,
	}

	render.Render(w, r, agr)
}

// deriveAddress derives an address from the extended public key for the specified
// index.
func deriveAddress(xpub string, index uint32) (string, error) {
	var params *chaincfg.Params

	switch string(xpub[0]) {
	case "d":
		params = &chaincfg.MainNetParams
	case "t":
		params = &chaincfg.TestNet2Params
		break
	default:
		return "", errors.New("unhandled network")
	}

	addr, err := util.DeriveAddress(params, xpub, index)
	if err != nil {
		return "", err
	}

	return addr, nil
}

// pricequoteGet fetches a new price quote or returns a cached one.
func pricequoteGet(w http.ResponseWriter, r *http.Request) {
	PriceQuotes.RLock()
	priceQuote, exists := PriceQuotes.priceQuotes[PriceQuotes.priceQuoteCurrentIndex]
	PriceQuotes.RUnlock()
	if !exists {
		render.Status(r, http.StatusExpectationFailed)
		render.Render(w, r, ErrNotFound)
		return
	}

	price := Price{
		Price: priceQuote.price,
	}

	pqgr := &PriceQuoteGetResponse{
		Price: &price,
	}

	render.Render(w, r, pqgr)
}

// Render AddressGetResponse
func (agr *AddressGetResponse) Render(w http.ResponseWriter, r *http.Request) error {
	return nil
}

// Render ErrResponse
func (e *ErrResponse) Render(w http.ResponseWriter, r *http.Request) error {
	render.Status(r, e.HTTPStatusCode)
	return nil
}

// Render PriceQuoteGetResponse
func (pqgr *PriceQuoteGetResponse) Render(w http.ResponseWriter, r *http.Request) error {
	return nil
}

func updatePrice() {
	// get quote
	response, err := http.Get(priceServiceURL)

	if err != nil {
		return
	}
	defer response.Body.Close()

	jsonReply, err := ioutil.ReadAll(response.Body)
	if err != nil {
		return
	}

	var data []CoinmarketcapV1Response
	err = json.Unmarshal(jsonReply, &data)
	if err != nil {
		return
	}

	floatPrice, err := strconv.ParseFloat(data[0].PriceUsd, 64)
	if err != nil {
		return
	}

	int64Time, err := Int64(data[0].LastUpdated)
	if err != nil {
		return
	}

	// update quote
	now := time.Now().Unix()
	PriceQuotes.Lock()
	PriceQuotes.priceQuoteCurrentIndex = now
	PriceQuotes.priceQuotes[now] = pricequoteData{
		price:           floatPrice,
		quoteTimeFetch:  now,
		quoteTimeSource: int64Time,
		source:          priceServiceURL,
	}
	PriceQuotes.Unlock()
}

// Int64 converts the given string representation of an integer into int64.
func Int64(val string) (int64, error) {
	return strconv.ParseInt(val, 0, 64)
}

// Uint32 converts the given string representation of an integer into uint32.
func Uint32(val string) (uint32, error) {
	i, err := strconv.ParseUint(val, 0, 32)
	if err != nil {
		return 0, err
	}
	return uint32(i), nil
}
