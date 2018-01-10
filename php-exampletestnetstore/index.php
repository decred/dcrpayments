<?php
// this is a simple example testnet store that shows a couple of items
// and accepts payments for them in Decred.

// require pdo sqlite or die
if (!extension_loaded("pdo_sqlite")) {
  fatal("pdo_sqlite is required and is not loaded!");
}

// add .. to path so we can load the library we use to derive addresses
set_include_path(get_include_path() . PATH_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "php-addressgen");
require_once("bwwc-include-all.php");

$appCtx = array(
  "cacheTTL" => 900,
  "confirmationsRequired" => 2,
  "dbConn" => null,
  "dbFilename" => "exampleStoreV1.db",
  "paymentEPK" => "tpub...",
  "rootPath" => __DIR__ . DIRECTORY_SEPARATOR,
  "storeName" => "Decred Testnet Store Example",
);

// verify specified EPK
list($epk, $err) = Bip32ExtendedPubkey::decred_parse_string($appCtx["paymentEPK"]);
if (!is_null($err)) {
    fatal("decred_parse_string failed for '{$appCtx["paymentEPK"]}': {$err}");
}

// init and load a price quote
$appCtx["dbPath"] = $appCtx["rootPath"] . "db" . DIRECTORY_SEPARATOR . $appCtx["dbFilename"];
$appCtx["dbConn"] = dbConnect($appCtx);
list ($appCtx["dcrPriceQuoteId"], $appCtx["dcrPriceUSD"], $wasCached, $outOfDate) = getDecredPriceUSD($appCtx);
info("DCR/USD: PriceQuote Id=${appCtx["dcrPriceQuoteId"]},Value={$appCtx["dcrPriceUSD"]},"
  . "wasCached={$wasCached},outOfDate={$outOfDate}");

// api for client javascript poller to talk to
// ====
if (!empty($_GET["cmd"])) {
  switch ($_GET["cmd"]) {
    case "getOrder":
    // in a production scenario, it'd be good to have something like the
    // following running from crontab:
    // ordersUpdate($appCtx, $appCtx["cacheTTL"]);

    // since this is just a demo, update all order info when we get an ajax
    // request and set a short cache timeout.
    ordersUpdate($appCtx, 30);

    $sth = dbExecPrepQuery($appCtx["dbConn"], "SELECT COUNT(*) as count, * "
      . "FROM Orders WHERE Id=? AND UserId=?",
      array($_GET["orderId"], $_GET["userId"]));
    $row = $sth->fetch(PDO::FETCH_ASSOC);

    if ($row["count"] == 0) {
      print json_encode(array("err" => "bad order #{$_GET["orderId"]}!"));
      exit;
    }

    $orderStatus = "Unknown";
    if ($row["Done"] == 1) {
      $orderStatus = "Payment complete with {$row["Confirmations"]} "
        . "confirmations!";
    } else if (empty($row["PaymentTx"])) {
      $orderStatus = "No transaction seen yet, waiting for payment...";
    } else {
      if ($row["Confirmations"] != $appCtx["confirmationsRequired"]) {
        $orderStatus = "Waiting for more blocks to be mined... "
          . "(have {$row["Confirmations"]} out of "
          . "{$appCtx["confirmationsRequired"]} confirmations required)";
      } else {
        warn("transaction not marked Done yet?");
      }
    }

    print json_encode(array(
      "data" => array(
        "Done" => $row["Done"],
        "OrderId" => $_GET["orderId"],
        "OrderStatus" => $orderStatus,
        "PaymentTx" => (empty($row["PaymentTx"]) ? "" : $row["PaymentTx"]),
      ),
      "err" => "",
    ));
    exit;
  case "setOrder":
    // lookup productId
    $sth = dbExecPrepQuery($appCtx["dbConn"], "SELECT COUNT(*) AS count, * "
      . "FROM Products WHERE Id = ? LIMIT 1", $_GET["productId"]);
    $productRow = $sth->fetch(PDO::FETCH_ASSOC);

    // create a random userId that we would link to in a non-demo scenario
    $sth = dbExecPrepQuery($appCtx["dbConn"],"SELECT ABS(RANDOM() % 9223372036854775807) AS Id");
    $userRow = $sth->fetch(PDO::FETCH_ASSOC);
    $userId = $userRow["Id"];

    // exit if productId doesn't exist
    if ($productRow["count"] == 0) {
      print json_encode(array("err" => "invalid product"));
      exit;
    }

    // get price in DCR for product
    $paymentAmount = round($productRow["Price_USD"] / $appCtx["dcrPriceUSD"], 2, PHP_ROUND_HALF_UP);

    // create the order with a random UserID since we don't do anything with
    // user data
    $sth = dbExecPrepQuery($appCtx["dbConn"], "INSERT INTO Orders "
      . "(PaymentAmount,PriceQuoteId,ProductId,UserId) VALUES (?,?,?,?)",
      array($paymentAmount, $appCtx["dcrPriceQuoteId"], $productRow["Id"], $userId));
    $orderId = $appCtx["dbConn"]->lastInsertId();
    unset($sth);
    info("inserted {$orderId} ProductId={$productRow["Id"]},userId={$userId}");

    $paymentAddress = BWWC__generate_decred_address_from_bip32_epk($appCtx["paymentEPK"], $orderId);

    if ($paymentAddress == "") {
      $errText = "unable to derive address";
      $sth = dbExecPrepQuery($appCtx["dbConn"], "UPDATE Orders SET Done = 1 "
        . "ErrorText = ? LastAttempt = ?", array($errText, time()));
      print json_encode(array("err" => $errText));
      exit;
    }

    info("set PaymentAddress=${paymentAddress} for Order.Id=${orderId}");
    $sth = dbExecPrepQuery($appCtx["dbConn"], "UPDATE Orders SET PaymentAddress = ? "
      . " WHERE Id = ?", array($paymentAddress, $orderId));

    // reply
    print json_encode(array(
      "data" => array(
        "OrderId" => $orderId,
        "PaymentAddress" => $paymentAddress,
        "PaymentAmount" => $paymentAmount,
        "UserId" => $userId,
      ),
      "err" => "",
    ));
    exit;
  default:
    exit;
  }
}?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <meta name="description" content="Decred testnet store">
    <meta name="author" content="Decred Developers">
    <link rel="icon" href="./favicon.ico">

    <title><?php echo $appCtx["storeName"];?></title>

    <!-- Bootstrap core CSS -->
    <link href="./css/bootstrap-3.3.7.min.css" rel="stylesheet">

    <!-- IE10 viewport hack for Surface/desktop Windows 8 bug -->
    <link href="./css/ie10-viewport-bug-workaround.css" rel="stylesheet">

    <!-- Custom styles for this template -->
    <link href="./css/example.css" rel="stylesheet">

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
  </head>

  <body>

    <nav class="navbar navbar-inverse navbar-fixed-top">
      <div class="container">
        <div class="navbar-header">
          <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
            <span class="sr-only">Toggle navigation</span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
          <a class="navbar-brand" href="#"><?php
            echo $appCtx["storeName"];?></a>
        </div>
        <div id="navbar" class="navbar-collapse collapse">
        </div><!--/.navbar-collapse -->
      </div>
    </nav>

    <!-- Main jumbotron for a primary marketing message or call to action -->
    <div class="jumbotron">
      <div class="container">
        <h1>Buy stuff with Decred!</h1>
        <p>This is a simple example store that accepts Decred payments.  Click one of the 'Buy Now' buttons below and you will be presented with a Decred payment dialog.  Send testnet coins to the specified address to finish checking out.</p>
        <p><a class="btn btn-primary btn-lg disabled" href="#" role="button">Learn more &raquo;</a></p>
      </div>
    </div>

    <div class="container">
      <!-- Example row of columns -->
      <div class="row">
        <?php
        $sth = dbExecPrepQuery($appCtx["dbConn"], "SELECT * FROM Products ORDER BY id");
        while ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
          ?><div class="col-md-4">
          <h2><?php echo "{$row["Make"]} {$row["Model"]}";?></h2>
          <?php echo "{$row["Description"]}"; ?>
          <p><button type="button" data-ProductId="<?php echo "{$row["Id"]}";?>"
          id="productId-<?php echo "{$row["Id"]}";?>"
          class="btn btn-success" data-toggle="modal"
          data-target="#orderModal">Buy Now For
          $<?php echo round($row["Price_USD"]);?> USD</button></p>
          </div>
        <?php
        }
        ?>
      </div>

      <hr>

      <footer>
        <p>2017 Decred Developers</p>
      </footer>

  <div class="modal fade" id="orderModal" tabindex="-1" role="dialog" aria-labelledby="orderModalLabel" data-keyboard="false" data-backdrop="static">
		<div class="modal-dialog" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
						<h4 class="modal-title" id="orderModalLabel">New Order</h4>
					</div>
					<div class="modal-body">
            <div id="modal-qrcode"></div>
            <div id="modal-instructions"></div>
            <div id="modal-tx"></div>
            <div id="modal-status"></div>
            <div id="modal-error"></div>
            <div id="modal-success"></div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
					</div>
				</div>
			</div>
		</div> <!-- /modal -->
    </div> <!-- /container -->

    <!-- Bootstrap core JavaScript
    ================================================== -->
    <!-- Placed at the end of the document so the pages load faster -->
    <script src="./js/jquery-3.2.1.min.js"></script>
    <script src="./js/jquery.periodicalupdater.min.js"></script>
    <script src="./js/bootstrap-3.3.7.min.js"></script>
    <!-- IE10 viewport hack for Surface/desktop Windows 8 bug -->
    <script src="./js/ie10-viewport-bug-workaround.js"></script>
    <!-- Custom javascript for this page -->
    <script src="./js/qrcode.min.js"></script>
    <script src="./js/example.js"></script>
  </body>
</html><?php

// order form handler
// =====
// - get address
// - present tabbed dialog with QR code tab selected (other tab: copy/paste)
// - pay with testnetfaucet button?
// - upon payment complete... go to success page
// - timeout after 15 mins/expire order automatically?
// - error page... (shouldnt happen unless main page isnt working too...)

function curlWithTimeout($URL) {
  $timeOut = 1500;
  $c = curl_init($URL);
  curl_setopt($c, CURLOPT_USERAGENT, "decred/dcrpayments bot");
  curl_setopt($c, CURLOPT_CONNECTTIMEOUT_MS, $timeOut);
  curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($c, CURLOPT_TIMEOUT_MS, $timeOut);
  $r = curl_exec($c);
  if ($r === false) {
      warn("curl error: " . curl_error($c) . " (errno: " . curl_errno($c)  . ") while scraping {$URL}");
  }
  curl_close($c);
  return $r;
}

function dbConnect($appCtx) {
	try {
		$dbConn = new PDO("sqlite:{$appCtx["dbPath"]}" , "", "", array(
				PDO::ATTR_ERRMODE => true,
				PDO::ATTR_TIMEOUT => 60,
				PDO::ERRMODE_WARNING => true,
				//PDO::ERRMODE_EXCEPTION => true,
			)
		);
	} catch (PDOException $e) {
    fatal("dbConnect failed: " . $e->getMessage());
  }
  // make sqlite3 webscale!!shift+one
  $sth = $dbConn->prepare("PRAGMA journal_mode=WAL;");
  $sth->execute();
	$sth = $dbConn->prepare("PRAGMA mmap_size=268435456;");
  $sth->execute();
  // V1 table structure
  $sth = $dbConn->prepare(
  "CREATE TABLE IF NOT EXISTS Orders (
    Id INTEGER NOT NULL PRIMARY KEY,
    PaymentAddress TEXT,
    PaymentAmount REAL,
    PaymentConfirmed INTEGER NOT NULL default 0,
    PaymentTx TEXT,
    PriceQuoteId INTEGER NOT NULL,
    ProductId INTEGER NOT NULL,
    UserId INTEGER UNIQUE NOT NULL,
    Created INTEGER NOT NULL default (strftime('%s','now')),
    Confirmations INTEGER NOT NULL default 0,
    Done INTEGER NOT NULL default 0,
    ErrorText TEXT,
    LastAttempt INTEGER NOT NULL default 0
  );");
  $sth->execute();
  $sth = $dbConn->prepare(
  "CREATE TABLE IF NOT EXISTS PriceQuotes (
    Id INTEGER NOT NULL PRIMARY KEY,
    URL TEXT NOT NULL,
    Token TEXT NOT NULL,
    Currency TEXT NOT NULL,
    Value REAL NOT NULL,
    Timestamp INTEGER NOT NULL default (strftime('%s','now'))
  );");
  $sth->execute();
  $sth = $dbConn->prepare(
  "CREATE TABLE IF NOT EXISTS Products (
    Id INTEGER NOT NULL PRIMARY KEY,
    Description TEXT NOT NULL,
    Make TEXT NOT NULL,
    Model TEXT NOT NULL,
    Price_USD REAL NOT NULL
  );");
  $sth->execute();
  $sth = dbExecPrepQuery($dbConn, "INSERT OR REPLACE INTO Products "
    . "(Id,Description,Make,Model,Price_USD) "
    . "VALUES (1,?,?,?,?)",
    array("- BCM2837<br>- 1.2 GHz 64-bit quad-core<br>- 1GB RAM<br>", "Raspberry Pi", "3", 35.00));
  $sth = dbExecPrepQuery($dbConn, "INSERT OR REPLACE INTO Products "
  . "(Id,Description,Make,Model,Price_USD) "
  . "VALUES (2,?,?,?,?)",
  array("- BCM2835<br>- 700 MHz single-core<br>- 512MB RAM<br>", "Raspberry Pi", "0 W", 10.00));
  return $dbConn;
}

/* create a statement handler and return it */
function dbExecPrepQuery($dbh, $query, $args = "") {
	if (gettype($dbh) != "object") {
		fatal("dbExecPrepQuery called with bad dbh");
	}
	if (empty($query)) {
		fatal("dbExecPrepQuery called without a query");
	}

	if (!is_array($args)) {
		if (empty($args)) {
			$args = array();
		} else {
			$args = array($args);
		}
	}

	foreach ($args as $k => $v) {
		if (!isset($v)) {
			fatal("dbExecPrepQuery was called with an non-set argument (arg #$k) "
				. "query ($query)");
		}
	}

	try {
		//fatal($query . $args[0]);
		$sth = $dbh->prepare($query);
	} catch (PDOException $e) {
		fatal("dbExecPrepQuery couldn't create statement handler for $query");
	}

	if (!$sth) {
		fatal("dbExecPrepQuery couldn't create statement handler for $query");
	}

	try {
		$sth->execute($args);
	} catch (PDOException $e) {
		fatal("DB Error: " . $e->getMessage() . " (query: $query)");
	}

	return($sth);
}

function fatal($err) {
  $err = "FATAL ERROR: " . rtrim($err);
  error_log($err);
  die($err . "\n");
}

function info($s) {
  $s = "INFO: " . rtrim($s);
  error_log($s);
}

function getDecredPriceUSD($appCtx) {
  // see if we have a cached quote in the database
  $sth = dbExecPrepQuery($appCtx["dbConn"], "SELECT COUNT(*) AS count, Id, Value "
    . "FROM PriceQuotes WHERE Timestamp > ? AND Token = 'DCR' "
    . "AND Currency = 'USD' ORDER BY "
    . "TimeStamp LIMIT 1", time() - $appCtx["cacheTTL"]);
  $row = $sth->fetch(PDO::FETCH_ASSOC);

  // try to get a new quote if we need one
  if ($row["count"] == 0) {
    $url = "https://api.coinmarketcap.com/v1/ticker/decred/";
    $res = curlWithTimeout($url);
    $decodedJSON = json_decode($res, true);
    // coinmarketcap uses a list with a single element for some reason
    $decodedJSON = $decodedJSON[0];
    if (is_numeric($decodedJSON["price_usd"]) && $decodedJSON["price_usd"] != 0) {
      $roundedPrice = round($decodedJSON["price_usd"], 2, PHP_ROUND_HALF_UP);
      $sth = dbExecPrepQuery($appCtx["dbConn"], "INSERT INTO PriceQuotes "
        . "(URL,Token,Currency,Value) VALUES (?,?,?,?)",
        array($url, "DCR", "USD", $roundedPrice));
      return array($appCtx["dbConn"]->lastInsertId(), $roundedPrice, 0, 0);
    } else {
      $sth = dbExecPrepQuery($appCtx["dbConn"], "SELECT COUNT(*) AS count, Value "
      . "FROM PriceQuotes ORDER BY TimeStamp LIMIT 1");
      $row = $sth->fetch(PDO::FETCH_ASSOC);

      // nothing else we can do
      if ($row["count"] == 0) {
        fatal("no price quotes available");
      }

      // continue with an out-of-date quote
      return array($appCtx["dbConn"]->lastInsertId(), $row["Value"], 1, 1);
    }
  // return the cache quote
  } else {
    return array($row["Id"], $row["Value"], 1, 0);
  }
}

function getPaymentsToAddrDcrdata($appCtx, $address, $amount, $orderId, $created) {
  $URL = "https://testnet.dcrdata.org/api/address/{$address}/raw";
  $r = curlWithTimeout($URL);
  $jsonData = json_decode($r, true);

  $sth = dbExecPrepQuery($appCtx["dbConn"], "UPDATE Orders "
  . "SET LastAttempt = ? WHERE Id = ?", array(time(), $orderId));

  if (empty($jsonData)) {
    return false;
  }

  // inspect the result, try to find a tx that matches...
  foreach ($jsonData as $txIdx => $txData) {
    // we either get time + blocktime or nothing if it's in mempool which
    // seems wrong
    if (empty($txData["time"])) {
      // XXX just fake with the current time since it's probably the tx we
      // want.
      $txData["time"] = time();
    }
    $matchingAddressSeen = false;
    $lastAddressSeen = "";
    foreach ($txData["vout"] as $voutIdx => $voutData) {
        foreach ($voutData["scriptPubKey"]["addresses"] as $addrIdx => $addr) {
            if ($addr === $address) {
                $matchingAddressSeen = true;
            }
            $lastAddressSeen = $addr;
        }
        if ($matchingAddressSeen &&
        $voutData["value"] >= $amount &&
        $txData["time"] >= $created &&
        !empty($txData["txid"])) {
          $sth = dbExecPrepQuery($appCtx["dbConn"], "UPDATE Orders "
            . "SET Confirmations=?,PaymentTx=? WHERE Id=?",
            array($txData["confirmations"], $txData["txid"], $orderId));
          info("DCRDATA: updated orderId {$orderId} "
            . "Confirmations={$txData["confirmations"]},PaymentTx={$txData["txid"]}");
          if ($txData["confirmations"] >= $appCtx["confirmationsRequired"]) {
            $sth = dbExecPrepQuery($appCtx["dbConn"], "UPDATE Orders "
            . "SET Done=1 WHERE Id=?", array($orderId));
            info("DCRDATA: updated orderId {$orderId} Done=1");
          }
          return true;
        }
    }
  }

  return false;
}

function getPaymentsToAddrInsight($appCtx, $address, $amount, $orderId, $created) {
  $URL = "https://testnet.decred.org/api/addr/${address}/utxo?noCache=1";
  $r = curlWithTimeout($URL);
  $jsonData = json_decode($r, true);

  $sth = dbExecPrepQuery($appCtx["dbConn"], "UPDATE Orders "
    . "SET LastAttempt = ? WHERE Id = ?", array(time(), $orderId));

  if (empty($jsonData)) {
    return array(false, "");
  }

  // inspect the result, try to find a tx that matches...
  foreach ($jsonData as $txIdx => $txData) {
    // confirmations won't be set if it's not mined
    if (empty($txData["confirmations"])) {
      $txData["confirmations"] = 0;
    }
    if ($txData["address"] == $address &&
        $txData["amount"] == $amount &&
        $txData["ts"] >= $created &&
        !empty($txData["txid"])) {
            $sth = dbExecPrepQuery($appCtx["dbConn"], "UPDATE Orders "
              . "SET Confirmations=?,PaymentTx=? WHERE Id=?",
              array($txData["confirmations"], $txData["txid"], $orderId));
            info("INSIGHT: found and set payment txid {$txData["txid"]} for orderId {$orderId}");
            if ($txData["confirmations"] >= $appCtx["confirmationsRequired"]) {
              $sth = dbExecPrepQuery($appCtx["dbConn"], "UPDATE Orders "
              . "SET Done=1 WHERE Id=?", array($orderId));
              info("INSIGHT: updated orderId {$orderId} Done=1");
            }
            return true;
    }
  }

  return array(false, "");
}

function ordersUpdate($appCtx, $cacheTTL) {
  // re-check any pending orders
  $sth = dbExecPrepQuery($appCtx["dbConn"], "SELECT COUNT(*) AS count, * "
    . "FROM Orders WHERE Done = 0 AND LastAttempt <= ?",
    array(time() - $cacheTTL));

  while ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
    if ($row["count"] == 0) {
      return;
    }

    $found = getPaymentsToAddrDcrdata($appCtx,
      $row["PaymentAddress"], $row["PaymentAmount"], $row["Id"], $row["Created"]);
    if ($found) {
      // we don't need to hit insight
      continue;
    }

    $processed = getPaymentsToAddrInsight($appCtx,
      $row["PaymentAddress"], $row["PaymentAmount"], $row["Id"], $row["Created"]);
  }
}

function warn($wrn) {
  $wrn = "WARN: " . rtrim($wrn);
  error_log($wrn);
}
?>
