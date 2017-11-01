epkgen
####

epkgen is a simple utility that will print out an extended public key for
the mainnet and testnet networks for a given seed (or a randomly generated one).

## Installation and updating

### Windows/Linux/BSD/POSIX - Build from source

Building or updating from source requires the following build dependencies:

- **Go 1.8 or 1.9**

  Installation instructions can be found here: http://golang.org/doc/install.
  It is recommended to add `$GOPATH/bin` to your `PATH` at this point.

- **Dep**

  Dep is used to manage project dependencies and provide reproducible builds.
  It is recommended to use the latest Dep release, unless a bug prevents doing
  so.  The latest releases (for both binary and source) can be found
  [here](https://github.com/golang/dep/releases).

Unfortunately, the use of `dep` prevents a handy tool such as `go get` from
automatically downloading, building, and installing the source in a single
command.  Instead, the latest project and dependency sources must be first
obtained manually with `git` and `dep`, and then `go` is used to build and
install the project.

**Getting the source**:

For a first time installation, the project and dependency sources can be
obtained manually with `git` and `dep` (create directories as needed):

```
git clone https://github.com/decred/dcrpayments $GOPATH/src/github.com/decred/dcrpayments
cd $GOPATH/src/github.com/decred/dcrpayments/epkgen
dep ensure -v
```

To update an existing source tree, pull the latest changes and install the
matching dependencies:

```
cd $GOPATH/src/github.com/decred/dcrpayments/epkgen
git pull
dep ensure -v
```

**Building/Installing**:

The `go` tool is used to build or install (to `GOPATH`) the project.  Some
example build instructions are provided below (all must run from the `dcrwallet`
project directory).

To build a `epkgen` executable and install it to `$GOPATH/bin/`:

```
go install
```

To build a `epkgen` executable and place it in the current directory:

```
go build
```