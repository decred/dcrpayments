package main

import (
	"encoding/hex"
	"flag"
	"fmt"
	"sort"

	"github.com/decred/dcrd/chaincfg"
	"github.com/decred/dcrd/hdkeychain"
)

var seedString string

func main() {
	flag.StringVar(&seedString, "seed", "", "hex encoded seed")
	flag.Parse()

	// generate a random seed
	seed, err := hdkeychain.GenerateSeed(hdkeychain.RecommendedSeedLen)
	if err != nil {
		fmt.Println(err)
		return
	}

	// use specified hex encoded seed if present
	if seedString != "" {
		seed, err = hex.DecodeString(seedString)
		if err != nil {
			fmt.Println(err)
			return
		}
	} else {
		seedString = fmt.Sprintf("%x", seed)
	}

	fmt.Println("seed =>", seedString)

	networks := []struct {
		Name   string
		Params *chaincfg.Params
	}{
		{"Mainnet", &chaincfg.MainNetParams},
		{"Testnet", &chaincfg.TestNet2Params},
	}
	sort.SliceStable(networks, func(i, j int) bool { return networks[i].Name < networks[j].Name })

	for _, net := range networks {
		master, err := hdkeychain.NewMaster(seed, net.Params)
		if err != nil {
			fmt.Println(err)
			return
		}

		purpose, err := master.Child(44 + hdkeychain.HardenedKeyStart)
		if err != nil {
			fmt.Println(err)
			return
		}

		coinType, err := purpose.Child(20 + hdkeychain.HardenedKeyStart)
		if err != nil {
			fmt.Println(err)
			return
		}

		account, err := coinType.Child(20 + hdkeychain.HardenedKeyStart)
		if err != nil {
			fmt.Println(err)
			return
		}

		branch0, err := account.Child(0)
		if err != nil {
			fmt.Println(err)
			return
		}

		branch0Pub, err := branch0.Neuter()
		if err != nil {
			fmt.Println(err)
			return
		}

		epk, err := branch0Pub.String()
		if err != nil {
			fmt.Println(err)
			return
		}

		fmt.Println(net.Params.Name, "epk =>", epk)
	}
}
