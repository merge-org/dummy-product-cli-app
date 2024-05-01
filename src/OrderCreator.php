<?php
declare(strict_types=1);

namespace MergeOrg\DummyProductCliApp;

use stdClass;
use Faker\Generator;
use Automattic\WooCommerce\Client;

final class OrderCreator {

	/**
	 * @var Generator
	 */
	private Generator $generator;

	/**
	 * @var Client
	 */
	private Client $client;

	/**
	 * @var ProductUpserter
	 */
	private ProductUpserter $productCreator;

	/**
	 * @param Generator $generator
	 * @param Client $client
	 * @param ProductUpserter $productCreator
	 */
	public function __construct(Generator $generator,
		Client $client,
		ProductUpserter $productCreator
	) {
		$this->generator = $generator;
		$this->client = $client;
		$this->productCreator = $productCreator;
	}

	/**
	 * @param string $randomImageUrl
	 * @param bool $forceCreateProducts
	 * @param int $maxProducts
	 * @return stdClass|null
	 */
	public function create(string $randomImageUrl, bool $forceCreateProducts = FALSE, int $maxProducts = 5): ?stdClass {
		$productsToCreate = rand(1, $maxProducts);
		$productsToCreateIndex = 0;
		$lineItems = [];
		while($productsToCreateIndex < $productsToCreate) {
			$product = $this->productCreator->upsert($randomImageUrl, $forceCreateProducts);
			$productExists = FALSE;
			foreach($lineItems as $lineItem) {
				if($lineItem["product_id"] === $product->id) {
					$productExists = TRUE;
					break;
				}
			}

			if($productExists) {
				continue;
			}

			$lineItems[] = [
				"product_id" => $product->id,
				"quantity" => rand(1, 5),
			];
			$productsToCreateIndex++;
		}

		$order = $this->client->post("orders", [
			"payment_method" => "bacs",
			"payment_method_title" => "Direct Bank Transfer",
			"set_paid" => TRUE,
			"billing" => [
				"first_name" => $firstName = $this->generator->name($gender = rand(0, 1) ? "male" : "female"),
				"last_name" => $lastName = $this->generator->lastName($gender),
				"address_1" => $address = $this->generator->address(),
				"address_2" => "",
				"city" => $city = $this->generator->city(),
				"state" => $city,
				"postcode" => $postcode = $this->generator->postcode(),
				"country" => $country = $this->generator->countryCode(),
				"email" => $this->generator->email(),
				"phone" => $this->generator->phoneNumber(),
			],
			"shipping" => [
				"first_name" => $firstName,
				"last_name" => $lastName,
				"address_1" => $address,
				"address_2" => "",
				"city" => $city,
				"state" => $city,
				"postcode" => $postcode,
				"country" => $country,
			],
			"line_items" => $lineItems,
		]);

		if(!($order->id ?? FALSE)) {
			return NULL;
		}

		return $order;
	}

}
