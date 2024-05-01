<?php
declare(strict_types=1);

namespace MergeOrg\DummyProductCliApp;

use Faker\Generator;
use Automattic\WooCommerce\Client;

final class CategoryUpserter {

	/**
	 * @var Generator
	 */
	private Generator $generator;

	/**
	 * @var Client
	 */
	private Client $client;

	/**
	 * @param Generator $generator
	 * @param Client $client
	 */
	public function __construct(Generator $generator, Client $client) {
		$this->generator = $generator;
		$this->client = $client;
	}

	/**
	 * @param bool $random
	 * @return ?int
	 */
	public function upsert(bool $random = FALSE): ?int {
		$categories = [
			"T-Shirt",
			"Swimwear",
			"Hoodies",
			"Jackets",
			"Trousers",
			"Belts",
		];

		$categoryName = $random ? $this->generator->category : $categories[rand(0, count($categories) - 1)];
		$categorySlug = strtolower($categoryName);
		$categorySlug = str_replace(" ", "-", $categorySlug);

		$category = $this->client->get("products/categories", ["slug" => $categorySlug]);
		if($category[0] ?? FALSE) {
			return $category[0]->id;
		}

		$category =
			$this->client->post("products/categories", [
				"name" => $categoryName,
				"slug" => $categorySlug,
			]);

		if($category->id ?? FALSE) {
			return $category->id;
		}

		return NULL;
	}
}
