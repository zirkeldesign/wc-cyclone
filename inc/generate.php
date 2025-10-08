<?php

namespace Cyclone;

use Carbon\Carbon;

class Generate {
	/**
	 * Generate a product.
	 * @param  [type] $type [description]
	 * @return [type]       [description]
	 */
	public static function product( $type ) {
		// use the factory to create a Faker\Generator instance
		$faker = \Faker\Factory::create();

		// Generate a product title & category from Faker
		// --- will keep going until it has a unique product title
		do {
			$title = ucwords( $faker->words( 2, true ) );
			$category = $faker->randomElement( array( 'Drama', 'Mystery', 'Romance', 'Horror', 'Travel', 'Health' ) );
		} while( Helpers::getPageByTitle( $title, 'OBJECT', 'product' ) );

		// Create product
		$product = [
			'title' => $title,
			'category' => $category,
			'sku' => strtoupper( $faker->numerify( substr( str_replace( ' ', '', $title ), 0, 5 ) . '#####' ) ),
			'content' => implode( "\n\n", $faker->paragraphs( rand( 3, 6 ) ) ),
			'excerpt' => $faker->paragraph( 3 ),
			'price' => $faker->randomFloat( rand( 0, 2 ), 5, 125 ),
		];

		$post_id = wp_insert_post( array(
			'post_type' => 'product',
			'post_title' => $product['title'],
			'post_excerpt' => $product['excerpt'],
			'post_content' => $product['content'],
			'post_status' => 'publish',
		), true );

		if ( ! ( $post_id instanceof WP_Error ) ) {

			// visibility
			update_post_meta( $post_id, '_visibility', 'visible' );

			// price
			update_post_meta( $post_id, '_price', $product['price'] );
			update_post_meta( $post_id, '_regular_price', $product['price'] );

			// add categories (@todo more than one)
			$terms = [$product['category']];
			wp_set_object_terms( $post_id, $terms, 'product_cat', true );

			// add tags (@todo)
			// add attributes (@todo)
			// add variations (@todo)

			/**
			 * Product image
			 */

			// figure out a random matching image that's unique
			do {
				$dir = plugin_dir_path( __FILE__ ) . '../data/products/images/' . $type . '/';
				$images = glob($dir . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
				$image = $images[array_rand($images)];
				$filename = basename($image);
			} while( Helpers::checkImageExists( $filename ) );

			// upload the image
			$upload_file = wp_upload_bits( $filename, null, file_get_contents( $image ) );
			if ( ! $upload_file['error'] ) {
				$wp_filetype = wp_check_filetype( $filename, null );
				$attachment = array(
					'post_mime_type' => $wp_filetype['type'],
					'post_parent' => $post_id,
					'post_title' => preg_replace( '/\.[^.]+$/', '', $filename ),
					'post_content' => '',
					'post_status' => 'inherit'
				);

				// insert attachment
				$attachment_id = wp_insert_attachment( $attachment, $upload_file['file'], $post_id );
				if ( ! is_wp_error( $attachment_id ) ) {
					require_once( ABSPATH . "wp-admin" . '/includes/image.php' );
					$attachment_data = wp_generate_attachment_metadata( $attachment_id, $upload_file['file'] );
					wp_update_attachment_metadata( $attachment_id,  $attachment_data );

					// set thumbnail
					set_post_thumbnail( $post_id, $attachment_id );
				}
			}

			// sku
			update_post_meta( $post_id, '_sku', $product['sku'] );

			// success
			return true;
		}

		// failed
		return false;
	}

	/**
	 * Generate a customer.
	 * @param  [type] $from [description]
	 * @return [type]       [description]
	 */
	public static function customer( $from ) {
		$user = Helpers::userInfo();

		// user registered date (random using from)
		if ( is_int( $from ) ) {
			$day = rand(0, $from);
			$hour = rand(0, 23);
			$registered = date( 'Y-m-d H:i:s', strtotime("-$day day -$hour hour") );
		} else {
			$registered = $from;
		}

		// filter WC new customer data
		add_filter( 'woocommerce_new_customer_data', function($data) use ($user, $registered) {
			$data['first_name'] = $user['first_name'];
			$data['last_name'] = $user['last_name'];
			$data['user_registered'] = $registered;
			return $data;
		});

		// email, username, password
		$user_id = wc_create_new_customer( strtolower( $user['email'] ), $user['username'], 'cyclone' );

		// billing/shipping address
		$meta = array(
			'billing_country'       => $user['address']['country'],
			'billing_first_name'    => $user['first_name'],
			'billing_last_name'     => $user['last_name'],
			'billing_address_1'     => $user['address']['street'],
			'billing_city'          => $user['address']['city'],
			'billing_state'         => $user['address']['state'],
			'billing_postcode'      => $user['address']['postcode'],
			'billing_email'         => $user['email'],
			'billing_phone'         => $user['address']['phone'],
			'shipping_country'      => $user['address']['country'],
			'shipping_first_name'   => $user['first_name'],
			'shipping_last_name'    => $user['last_name'],
			'shipping_address_1'    => $user['address']['street'],
			'shipping_city'         => $user['address']['city'],
			'shipping_state'        => $user['address']['state'],
			'shipping_postcode'     => $user['address']['postcode'],
			'shipping_email'        => $user['email'],
			'shipping_phone'        => $user['address']['phone'],
			'last_update'           => strtotime($registered),
		);

		foreach ($meta as $key => $value) {
			update_user_meta( $user_id, $key, $value );
		}

		return $user_id;
	}

	/**
	 * Generate an order.
	 * @param  [type] $from     [description]
	 * @param  [type] $customer [description]
	 * @return [type]           [description]
	 */
	public static function order( $from, $customer ) {
		global $wpdb;

		// use the factory to create a Faker\Generator instance
		$faker = \Faker\Factory::create();

		$user = false;
		// if no customer, order is for a guest
		if ( $customer ) {
			// have an int, find the customer - otherwise create a customer
			if ( is_int( $customer ) ) {
				// find matching user
				$wpUser = get_user_by( 'ID', $customer );
				$user = $wpUser ? $customer : false;

				// make sure $from is at most the # of days ago customer was created
				$registeredDiff = Carbon::parse($wpUser->user_registered)->diffInDays(Carbon::now());
				$from = $registeredDiff < $from ? $registeredDiff + 1 : $from;
			} else {
				$user = self::customer( $from );
			}
		}


		// dates for the order/customer
		$gmt_offset = get_option('gmt_offset');
		$day = rand(0, $from);
		$hour = rand(0, 23);
		$minute = rand(1, 5);
		$new_date = date( 'Y-m-d H:i:s', strtotime("-$day day -$hour hour") );
		$gmt_new_date = date( 'Y-m-d H:i:s', strtotime("-$day day -$hour hour -$gmt_offset hour") );

		// figure out the order status
		$statuses = [
			'completed'  => 80,
			'processing' => 5,
			'on-hold'    => 5,
			'failed'     => 10,
		];

		$status = Helpers::getRandomWeightedElement($statuses);
		// create base order
		$data = [
			'customer_id'         => absint( $user ),
			'status'              => $status,
			'created_via'         => 'wc-cyclone',
			'customer_ip_address' => $faker->ipv4,
			'customer_user_agent' => $faker->userAgent,
		];
		$order = wc_create_order($data);

		// get all products
		$products = $wpdb->get_col("SELECT ID FROM {$wpdb->prefix}posts WHERE post_type = 'product'");
		foreach ( $products as $product_id ) {
			$product_ids[] = $product_id;
		}

		// add random products to order
		$count = ( count($product_ids) < 50 ? count($product_ids) : 50);
		for ( $i = 0; $i < rand( 1, $count ); $i++ ) {
			// get random product id & unset so we don't add twice
			$key = rand(1, count($product_ids)) - 1;
			$id = $product_ids[$key];

			// unset item and reset array keys
			unset($product_ids[$key]);
			$product_ids = array_values($product_ids);

			$quantities = apply_filters('wc_cyclone_order_items_count', [
				1 => 50,
				2 => 25,
				3 => 15,
				4 => 8,
				5 => 2,
			]);

			if (is_object(wc_get_product($id))) {
				$quantity = Helpers::getRandomWeightedElement($quantities);
				$order->add_product( wc_get_product($id), $quantity );
			}
		}

		// no user? let's generate guest info
		if (! $user) {
			$info = Helpers::userInfo();
		}

		// create addresses
		$billing = [
			'country'   => $user ? get_user_meta( $user, 'billing_country', true ) : $info['address']['country'],
			'first_name'=> $user ? get_user_meta( $user, 'billing_first_name', true ) : $info['first_name'],
			'last_name' => $user ? get_user_meta( $user, 'billing_last_name', true ) : $info['last_name'],
			'company'   => '',
			'address_1' => $user ? get_user_meta( $user, 'billing_address_1', true ) : $info['address']['street'],
			'address_2' => '',
			'city'      => $user ? get_user_meta( $user, 'billing_city', true ) : $info['address']['city'],
			'state'     => $user ? get_user_meta( $user, 'billing_state', true ) : $info['address']['state'],
			'postcode'  => $user ? get_user_meta( $user, 'billing_postcode', true ) : $info['address']['postcode'],
			'email'     => $user ? get_user_meta( $user, 'billing_email', true ) : $info['email'],
			'phone'     => $user ? get_user_meta( $user, 'billing_phone', true ) : $info['address']['phone'],
		];
		$shipping = [
			'country'   => $user ? get_user_meta( $user, 'shipping_country', true ) : $info['address']['country'],
			'first_name'=> $user ? get_user_meta( $user, 'shipping_first_name', true ) : $info['first_name'],
			'last_name' => $user ? get_user_meta( $user, 'shipping_last_name', true ) : $info['last_name'],
			'company'   => '',
			'address_1' => $user ? get_user_meta( $user, 'shipping_address_1', true ) : $info['address']['street'],
			'address_2' => '',
			'city'      => $user ? get_user_meta( $user, 'shipping_city', true ) : $info['address']['city'],
			'state'     => $user ? get_user_meta( $user, 'shipping_state', true ) : $info['address']['state'],
			'postcode'  => $user ? get_user_meta( $user, 'shipping_postcode', true ) : $info['address']['postcode'],
			'email'     => $user ? get_user_meta( $user, 'shipping_email', true ) : $info['email'],
			'phone'     => $user ? get_user_meta( $user, 'shipping_phone', true ) : $info['address']['phone'],
		];

		// attach addresses to order
		$order->set_address( $billing, 'billing' );
		$order->set_address( $shipping, 'shipping' );

		// @todo apply coupon sometimes
		// @todo sometimes add fee

		$order->calculate_totals();
		$order_id = $order->get_id();

		if ( $order_id ) {
			$gateways = [
				'bacs'   => 20,
				'stripe' => 40,
				'paypal' => 30,
				'cod'    => 10,
			];

			$gateway = Helpers::getRandomWeightedElement($gateways);
			update_post_meta( $order_id, '_payment_method', $gateway );
			update_post_meta( $order_id, '_payment_method_title', ucfirst($gateway) );
			// @todo more stripe/paypal data like customer id, payment id, etc.

			// @todo more shipping methods + adding line items for them
			update_post_meta( $order_id, '_shipping_method', 'free_shipping' );
			update_post_meta( $order_id, '_shipping_method_title', 'Free Shipping' );

			foreach ( $data as $key => $value ) {
				update_post_meta( $order_id, '_'.$key, $value );
			}

			// paid?
			$paid_odds = [
				'paid' => 90,
				'not_paid' => 10,
			];
			$paid = Helpers::getRandomWeightedElement($paid_odds);

			if ($paid == 'paid') {
				$id = strtoupper($gateway) . $faker->ean13;
				$order->payment_complete($id);
				update_post_meta( $order->get_id(), '_paid_date', $new_date );
			}

			// update order date
			wp_update_post([
				'ID' => $order->get_id(),
				'post_date' => $new_date,
				'post_modified' => $new_date,
				'post_date_gmt' => $gmt_new_date,
				'post_modified_gmt' => $gmt_new_date,
			]);
		}

		return true;
	}
}
