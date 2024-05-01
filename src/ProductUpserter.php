<?php
declare(strict_types=1);

namespace MergeOrg\DummyProductCliApp;

use stdClass;
use Faker\Generator;
use Automattic\WooCommerce\Client;

final class ProductUpserter {

	/**
	 * @var Generator
	 */
	private Generator $generator;

	/**
	 * @var Client
	 */
	private Client $client;

	/**
	 * @var CategoryUpserter
	 */
	private CategoryUpserter $categoryUpserter;

	/**
	 * @param Generator $generator
	 * @param Client $client
	 * @param CategoryUpserter $categoryUpserter
	 */
	public function __construct(Generator $generator,
		Client $client,
		CategoryUpserter $categoryUpserter) {
		$this->client = $client;
		$this->generator = $generator;
		$this->categoryUpserter = $categoryUpserter;
	}

	/**
	 * @param string $randomImageUrl
	 * @param bool $forceCreate
	 * @return stdClass|null
	 */
	public function upsert(string $randomImageUrl, bool $forceCreate = FALSE): ?stdClass {
		$sku = rand(101, 105);
		$sku = "SORT-PRODUCT-$sku";

		if($forceCreate) {
			$sku .= "-" . rand(111111, 999999);
		}

		$product = $this->client->get("products", ["sku" => $sku]);
		if($product[0] ?? FALSE) {
			return $product[0];
		}

		$randomPrice = rand(1000, 2000) / 100;

		$args = [
			"name" => ucwords(implode(" ", $this->generator->words(rand(1, 3)))),
			"type" => "simple",
			"description" => ucwords(implode(" ", $this->generator->words(rand(15, 25)))),
			"short_description" => ucwords(implode(" ", $this->generator->words(rand(5, 10)))),
			"sku" => $sku,
			"regular_price" => (string) (float) $randomPrice,
			"images" => [
				[
					"src" => $randomImageUrl . "&v1=" . uniqid("", TRUE),
				],
			],
			"categories" => [
				[
					"id" => $this->categoryUpserter->upsert(),
				],
			],
		];

		$product = $this->client->post("products", $args);

		if(!($product->id ?? FALSE)) {
			return NULL;
		}

		return $product;
	}
}
