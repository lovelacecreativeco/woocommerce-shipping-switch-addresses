<?php

// Add Swap and Undo buttons to the WooCommerce admin order page
add_action('woocommerce_admin_order_data_after_shipping_address', 'add_swap_and_undo_buttons');

function add_swap_and_undo_buttons($order) {
    $swap_done = $order->get_meta('_swap_done');
    ?>
    <script>
        jQuery(document).ready(function($) {
            $('#swap-sender-shipping').click(function(e) {
                e.preventDefault();
                let orderId = <?php echo $order->get_id(); ?>;
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'swap_sender_shipping_addresses',
                        order_id: orderId
                    },
                    beforeSend: function() {
                        $('#swap-sender-shipping').text('Swapping...');
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Error swapping addresses');
                        }
                    },
                    error: function() {
                        alert('An error occurred.');
                    }
                });
            });

            $('#undo-swap-addresses').click(function(e) {
                e.preventDefault();
                let orderId = <?php echo $order->get_id(); ?>;
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'undo_sender_shipping_swap',
                        order_id: orderId
                    },
                    beforeSend: function() {
                        $('#undo-swap-addresses').text('Undoing...');
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Error undoing swap');
                        }
                    },
                    error: function() {
                        alert('An error occurred.');
                    }
                });
            });
        });
    </script>

    <?php if ($swap_done === 'yes') : ?>
        <p><button id="undo-swap-addresses" class="button">Undo Swap</button></p>
        <p>Swap performed on: <?php echo date('Y-m-d H:i:s', $order->get_meta('_swap_timestamp')); ?></p>
    <?php else : ?>
        <p><button id="swap-sender-shipping" class="button">Swap Sender & Shipping Addresses</button></p>
    <?php endif; ?>
    <?php
}

// Handle the swap of sender and shipping addresses
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

    $origin_address_service = new \Automattic\WCShipping\OriginAddresses\OriginAddressService();
    $origin_addresses = $origin_address_service->get_origin_addresses();

    if (empty($origin_addresses)) {
        wp_send_json_error(['message' => 'No sender address found']);
    }

    // Assume first sender address is used
    $sender_address = $origin_addresses[0];
    $shipping_address = $order->get_address('shipping');

    // Store original addresses and timestamp for undo
    $order->update_meta_data('_original_sender_address', json_encode($sender_address));
    $order->update_meta_data('_original_shipping_address', json_encode($shipping_address));
    $order->update_meta_data('_swap_done', 'yes');
    $order->update_meta_data('_swap_timestamp', time());

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
        'phone'      => $sender_address['phone'] ?? '',
    ], 'shipping');

    $origin_address_service->update_origin_addresses($shipping_address);

    $order->save();

    wp_send_json_success(['message' => 'Sender and shipping addresses swapped successfully. Undo available.']);
}

// Handle the undo swap functionality
add_action('wp_ajax_undo_sender_shipping_swap', 'handle_undo_sender_shipping_swap');

function handle_undo_sender_shipping_swap() {
    if (!isset($_POST['order_id'])) {
        wp_send_json_error(['message' => 'Invalid order ID']);
    }

    $order_id = intval($_POST['order_id']);
    $order = wc_get_order($order_id);

    if (!$order) {
        wp_send_json_error(['message' => 'Order not found']);
    }

    $original_sender = json_decode($order->get_meta('_original_sender_address'), true);
    $original_shipping = json_decode($order->get_meta('_original_shipping_address'), true);

    if (!$original_sender || !$original_shipping) {
        wp_send_json_error(['message' => 'No swap data found']);
    }

    // Restore original addresses
    $order->set_address($original_shipping, 'shipping');
    $origin_address_service = new \Automattic\WCShipping\OriginAddresses\OriginAddressService();
    $origin_address_service->update_origin_addresses($original_sender);

    // Remove all non-default addresses
    $origin_addresses = $origin_address_service->get_origin_addresses();
    foreach ($origin_addresses as $address) {
        if (empty($address['default_address']) || !$address['default_address']) {
            $origin_address_service->delete_origin_address($address['id']);
        }
    }

    // Remove swap status and timestamp
    $order->delete_meta_data('_original_sender_address');
    $order->delete_meta_data('_original_shipping_address');
    $order->delete_meta_data('_swap_done');
    $order->delete_meta_data('_swap_timestamp');

    $order->save();

    wp_send_json_success(['message' => 'Address swap undone successfully, and non-default origin addresses removed.']);
}
