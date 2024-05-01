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
	}, 1)
	->option("-delp --delete-all-products", "Delete all previous products", function(string $input) {
		return (bool) $input;
	}, TRUE)
	->option("-delo --delete-all-orders", "Delete all previous orders", function(string $input) {
		return (bool) $input;
	}, FALSE)
	->parse($_SERVER["argv"]);

$values = $command->values();

$url = $values["url"];
$consumerKey = $values["consumerKey"];
$consumerSecret = $values["consumerSecret"];
$numberOfProducts = $values["numberOfProducts"];
$randomImageUrl =
	($values["randomImageUrl"] ?? FALSE) ?:
		"https://random-image.co/random.jpg?width=800&height=600&v=" . md5((string) microtime(TRUE));
$deleteAllOrders = $values["deleteAllOrders"];
$deleteAllProducts = $values["deleteAllProducts"];

$client = new Client($url, $consumerKey, $consumerSecret, ["verify_ssl" => FALSE]);

$generator = Factory::create();
$generator->addProvider(new Commerce($generator));
$categoryUpserter = new CategoryUpserter($generator, $client);
$productUpserter = new ProductUpserter($generator, $client, $categoryUpserter);

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
