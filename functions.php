<?php

add_action('wp_ajax_swap_sender_shipping_addresses', 'handle_sender_shipping_swap');

function handle_sender_shipping_swap() {
    if (!isset($_POST['order_id'])) {
        wp_send_json_error(['message' => 'Invalid order ID']);
    }

    $order_id = intval($_POST['order_id']);
    $order = wc_get_order($order_id);

    if (!$order) {
        wp_send_json_error(['message' => 'Order not found']);
    }

    // Load WooCommerce origin address settings
    $origin_address_service = new \Automattic\WCShipping\OriginAddresses\OriginAddressService();
    $origin_addresses = $origin_address_service->get_origin_addresses();

    if (empty($origin_addresses)) {
        wp_send_json_error(['message' => 'No sender address found']);
    }

    // Assume the first sender address is used
    $sender_address = $origin_addresses[0];

    // Get shipping address from order
    $shipping_address = $order->get_address('shipping');

    // Swap sender and shipping address, including phone
    $order->set_address([
        'first_name' => $sender_address['first_name'] ?? '',
        'last_name'  => $sender_address['last_name'] ?? '',
        'company'    => $sender_address['company'] ?? '',
        'address_1'  => $sender_address['address_1'] ?? '',
        'address_2'  => $sender_address['address_2'] ?? '',
        'city'       => $sender_address['city'] ?? '',
        'state'      => $sender_address['state'] ?? '',
        'postcode'   => $sender_address['postcode'] ?? '',
        'country'    => $sender_address['country'] ?? '',
        'phone'      => $sender_address['phone'] ?? '', // Added phone field
    ], 'shipping');

    // Update the sender address with the shipping details
    $new_sender_address = [
        'first_name' => $shipping_address['first_name'],
        'last_name'  => $shipping_address['last_name'],
        'company'    => $shipping_address['company'],
        'address_1'  => $shipping_address['address_1'],
        'address_2'  => $shipping_address['address_2'],
        'city'       => $shipping_address['city'],
        'state'      => $shipping_address['state'],
        'postcode'   => $shipping_address['postcode'],
        'country'    => $shipping_address['country'],
        'phone'      => $shipping_address['phone'], // Added phone field
    ];

    $origin_address_service->update_origin_addresses($new_sender_address);

    // Save the updated order
    $order->save();

    wp_send_json_success(['message' => 'Sender and shipping addresses swapped successfully']);
}
