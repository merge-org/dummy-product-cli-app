<?php
declare(strict_types=1);

namespace MergeOrg\DummyProductCliApp;

use Faker\Factory;
use Ahc\Cli\Input\Command;
use Automattic\WooCommerce\Client;
use Bezhanov\Faker\Provider\Commerce;

if(php_sapi_name() !== "cli") {
	exit("This script can be accessed only via cli");
}

require_once __DIR__ . "/vendor/autoload.php";

$command = new Command("dummy-product-cli-app", "Create dummy product(s)");

$command
	->version("0.0.1")
	->option("-u --url", "WooCommerce URL")
	->option("-ck --consumer-key", "Consumer Key for interacting with the API")
	->option("-cs --consumer-secret", "Consumer Secret for interacting with the API")
	->option("-ru --random-image-url")
	->option("-np --number-of-products", "How many products to be created", function(string $input) {
		return (int) $input;
	}, 0)
	->option("-no --number-of-orders", "How many products to be created", function(string $input) {
		return (int) $input;
	}, 0)
	->option("-mnop --max-number-of-order-products",
		"How many products to be created inside an order",
		function(string $input) {
			return (int) $input;
		},
		5)
	->option("-delp --delete-all-products", "Delete all previous products", function(string $input) {
		return (bool) $input;
	}, FALSE)
	->option("-delo --delete-all-orders", "Delete all previous orders", function(string $input) {
		return (bool) $input;
	}, FALSE)
	->parse($_SERVER["argv"]);

$values = $command->values();

$url = $values["url"];
$consumerKey = $values["consumerKey"];
$consumerSecret = $values["consumerSecret"];
$numberOfProducts = $values["numberOfProducts"];
$numberOfOrders = $values["numberOfOrders"];
$maxNumberOfOrderProducts = $values["maxNumberOfOrderProducts"];
$randomImageUrl =
	($values["randomImageUrl"] ?? FALSE) ?:
		"https://random-image.co/random.jpg?width=600&height=800";
$deleteAllOrders = $values["deleteAllOrders"];
$deleteAllProducts = $values["deleteAllProducts"];

echo "Initiating script `dummy-product-cli-app`\n";

echo "WC URL: 					`$url`\n";
echo "Consumer Key: 			`$consumerKey`\n";
echo "Consumer Secret:			`$consumerSecret`\n";
echo "Products to create:		`$numberOfProducts`\n";
echo "Orders to create:  		`$numberOfOrders`\n";
echo "Max Products per order:	`$maxNumberOfOrderProducts`\n";
echo "Delete all Orders:		`$deleteAllOrders`\n";
echo "Delete all Products:		`$deleteAllProducts`\n";

$client = new Client($url, $consumerKey, $consumerSecret, ["verify_ssl" => FALSE]);

$generator = Factory::create();
$generator->addProvider(new Commerce($generator));
$categoryUpserter = new CategoryUpserter($generator, $client);
$productUpserter = new ProductUpserter($generator, $client, $categoryUpserter);
$orderCreator = new OrderCreator($generator, $client, $productUpserter);

if($deleteAllOrders) {
	$orders = [];
	$fetchMoreOrders = TRUE;
	$loops = 0;
	while($fetchMoreOrders && $loops < 10) {
		$orders_ = $client->get("orders", ["page" => ($loops + 1)]);
		if(!$orders_) {
			$fetchMoreOrders = FALSE;
		}

		$orders = array_merge($orders, (array) $orders_);

		$loops++;
	}

	foreach($orders as $order) {
		$client->delete("orders/$order->id");
	}
}

if($deleteAllProducts) {
	$products = [];
	$fetchMoreProducts = TRUE;
	$loops = 0;
	while($fetchMoreProducts && $loops < 10) {
		$products_ = $client->get("products", ["page" => ($loops + 1)]);
		if(!$products_) {
			$fetchMoreProducts = FALSE;
		}

		$products = array_merge($products, (array) $products_);

		$loops++;
	}

	foreach($products as $product) {
		echo "Deleting product '$product->id'" . PHP_EOL;
		$client->delete("products/$product->id");
	}
}

$createdProducts = 0;
$loop = 0;
while($createdProducts < $numberOfProducts && $loop < 10) {
	$product = $productUpserter->upsert($randomImageUrl, TRUE);
	echo "Created product '$product->id'" . PHP_EOL;

	$loop++;
	$createdProducts++;
}

$createdOrders = 0;
$loop = 0;
while($createdOrders < $numberOfOrders && $loop < 10) {
	$order = $orderCreator->create($randomImageUrl, FALSE, $maxNumberOfOrderProducts);
	echo "Created order '$order->id'" . PHP_EOL;

	$loop++;
	$createdOrders++;
}
