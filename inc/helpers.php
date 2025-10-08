<?php

namespace Cyclone;

class Helpers {
	/**
	 * Creates fake user info.
	 * We have it here and not in the Generate class because
	 * sometimes we need this data for a guest user instead
	 * of for creating a real valid user.
	 * @return array $user
	 */
	public static function userInfo() {
		// use the factory to create a Faker\Generator instance
		$faker = \Faker\Factory::create();

		// Generate a real random city & country from geonames DB
		$cities = file( plugin_dir_path( __FILE__ ) . '../data/cities.csv' );
		$found = explode( ',' , trim( $cities[rand( 0, count( $cities ) - 1 )] ) );
		$city = $found[0];
		$country = substr( $found[1], 0, 2 );

		// Build up user data
		$user = [
			'first_name' => $faker->firstName,
			'last_name'  => $faker->lastName,
			'email'      => $faker->email,
			'username'   => $faker->userName,
			'address'    => [
				'street'   => $faker->streetAddress,
				'city'     => $city,
				'state'    => $faker->state,
				'postcode' => $faker->postcode,
				'country'  => $country,
				'phone'    => $faker->e164PhoneNumber,
			],
		];

		return $user;
	}

	/**
	 * Get a random weighted element from an array.
	 * @param  array  $weightedValues
	 * @return string $key
	 */
	public static function getRandomWeightedElement($weightedValues = []) {
		$rand = mt_rand(1, (int) array_sum($weightedValues));

		foreach ($weightedValues as $key => $value) {
			$rand -= $value;
			if ($rand <= 0) {
				return $key;
			}
		}
	}

	/**
	 * Check if an image exists by filename.
	 * @param  string $filename
	 * @return bool
	 */
	public static function checkImageExists( $filename ) {
		$args = array(
			'post_per_page' => 1,
			'post_type'     => 'attachment',
			'name'          => trim ( $filename ),
		);
		$get_posts = new \WP_Query( $args );

		if ( isset($get_posts->posts[0]) ) {
			return true;
		}
		return false;
	}

	/**
	 * Legacy helper for get_page_by_title(), which is deprecated since WP 6.2
	 *
	 * @param string $title
	 * @param mixed $output
	 * @param string $post_type
	 * @return mixed
	 */
	public static function getPageByTitle( $title, $output = OBJECT, $post_type = 'page' ) {
		$query = new WP_Query(
			array(
				'post_type'              => $post_type,
				'title'                  => $page_title,
				'post_status'            => 'all',
				'posts_per_page'         => 1,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
				'orderby'                => 'date',
				'order'                  => 'ASC',
			)
		);

		if ( ! empty( $query->post ) ) {
			$_post = $query->post;

			if ( ARRAY_A === $output ) {
				return $_post->to_array();
			} elseif ( ARRAY_N === $output ) {
				return array_values( $_post->to_array() );
			}

			return $_post;
		}

		return null;
	}
 }