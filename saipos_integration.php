<?php

/**
 * Plugin Name: Saipos Integration
 * Description: Integrates WooCommerce with Saipos API
 * Version: 1.0.0
 * Author: Joao Rodrigues - joaoomatheus@hotmail.com
 * Author URI: joaoomatheus@hotmail.com
 * Text Domain: saipos-integration
 */
add_action('woocommerce_checkout_order_processed', 'saipos_api_integration', 10, 10);
function saipos_api_integration($order_id)
{
    $order = wc_get_order($order_id);

    $items = $order->get_items();
    $order_data = $order->get_data();
    $token = getTokenSaipos();

    $order_billing_number = $order_data['meta_data'][3]->value; // take care with this, it's not the best way to do it and maybe you have to change, var_dump the $order_data to see the correct index;
    $api_url = 'https://homolog-order-api.saipos.com/order';

    $api_key = json_decode($token, true)['token'];

    $order_items = getOrderItems($items);
    $client_name = $order_data["billing"]["first_name"] . ' ' . $order_data["billing"]["last_name"];

    $request_args = array(
        'headers' => array(
            'Authorization' => $api_key,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ),
        'body' => json_encode(array(
            'order_id' => $order_data['id'] . '',
            'display_id' => $order_data['id'] . '',
            'cod_store' => '123',
            'created_at' => '2020-10-08T01:25:49.992093Z',
            'notes' => '',
            'total_discount' => 0,
            'total_amount' => floatval($order_data["total"]),
            'customer' => array(
                'id' => '123', // change for your need
                'name' => $client_name,
                'phone' => $order_data["billing"]["phone"],
            ),
            'order_method' => array(
                'mode' => 'DELIVERY',
                'delivery_by' => 'PARTNER',
                'delivery_fee' => 1,
                'scheduled' => true,
                'delivery_date_time' => date("Y-m-d\TH:i:s.u\Z"),
            ),
            'delivery_address' => array(
                'country' => "BR", // change for your need
                'state' => "SC", // change for your need
                'city' => $order_data["billing"]["city"],
                'district' => $order_data['meta_data'][4]->value,
                'street_name' => $order_data["billing"]["address_1"],
                'street_number' => $order_billing_number,
                'postal_code' => $order_data["billing"]["postcode"],
                'reference' => '',
                'complement' => $order_data["billing"]["address_2"],
                'coordinates' => array(
                    'latitude' => -9.825868,
                    'longitude' => -67.948632,
                ),
            ),
            'items' => $order_items,
            'payment_types' => array(
                array(
                    'code' => 'CARD',
                    'amount' => floatval($order_data["total"]),
                    'change_for' => 0,
                ),
            ),
        )),
    );

    $response = wp_safe_remote_post($api_url, $request_args);

    if (is_wp_error($response)) {
        error_log('Error: ' . $response->get_error_message());
        error_log('Response body: ' . wp_remote_retrieve_body($response));
    } else {
        wp_remote_retrieve_body($response);
    }
}

function getTokenSaipos()
{
    $api_url = 'https://homolog-order-api.saipos.com/auth';
    $request_args = array(
        'headers' => array(
            'accept' => 'application/json',
            'content-type' => 'application/json',
        ),
        'body' => json_encode(array(
            'idPartner' => '', // insert your partner id here
            'secret' => '', // insert your secret here
        )),
    );

    $response = wp_safe_remote_post($api_url, $request_args);

    if (is_wp_error($response)) {
        error_log('Error: ' . $response->get_error_message());
        return 'Response body: ' . wp_remote_retrieve_body($response);
    } else {
        $response_body = wp_remote_retrieve_body($response);
        return $response_body;
    }
}

function getOrderItems($total_order_items)
{
    $items_to_return = [];
    foreach ($total_order_items as $item_key => $item) {
        $item_data    = $item->get_data();
        $product_name = $item_data['name'];
        $quantity     = $item_data['quantity'];
        $product        = $item->get_product();
        $item_object  = [
            'integration_code' => '1234', // change for your need
            'desc_item' => $product_name,
            'quantity' => $quantity,
            'unit_price' => floatval($product->get_price()),
            'notes' => '', // change for your need
            'choice_items' => [], // change for your need
        ];
        $items_to_return[] = $item_object;
    }
    return $items_to_return;
}
