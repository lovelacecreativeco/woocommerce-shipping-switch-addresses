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

    // Store the current addresses before swapping (for undo functionality)
    $order->update_meta_data('_original_sender_address', json_encode($sender_address));
    $order->update_meta_data('_original_shipping_address', json_encode($shipping_address));

    // Swap sender and shipping addresses, including phone
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
        'phone'      => $shipping_address['phone'],
    ];

    $origin_address_service->update_origin_addresses($new_sender_address);

    // Save the updated order
    $order->save();

    wp_send_json_success(['message' => 'Sender and shipping addresses swapped successfully. Undo available.']);
}

add_action('woocommerce_admin_order_data_after_shipping_address', 'add_undo_swap_button');

function add_undo_swap_button($order) {
    // Check if original addresses exist in order meta
    $original_sender = $order->get_meta('_original_sender_address');
    $original_shipping = $order->get_meta('_original_shipping_address');

    if ($original_sender && $original_shipping) {
        ?>
        <script>
            jQuery(document).ready(function($) {
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
                            if(response.success) {
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
        <p><button id="undo-swap-addresses" class="button">Undo Swap</button></p>
        <?php
    }
}

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

    // Retrieve original addresses
    $original_sender = json_decode($order->get_meta('_original_sender_address'), true);
    $original_shipping = json_decode($order->get_meta('_original_shipping_address'), true);

    if (!$original_sender || !$original_shipping) {
        wp_send_json_error(['message' => 'No swap data found']);
    }

    // Restore the original addresses
    $order->set_address($original_shipping, 'shipping');

    $origin_address_service = new \Automattic\WCShipping\OriginAddresses\OriginAddressService();
    $origin_address_service->update_origin_addresses($original_sender);

    // Remove the meta data to prevent repeated undo
    $order->delete_meta_data('_original_sender_address');
    $order->delete_meta_data('_original_shipping_address');

    $order->save();

    wp_send_json_success(['message' => 'Address swap undone successfully']);
}
add_action('woocommerce_admin_order_data_after_shipping_address', 'add_swap_sender_shipping_button');

function add_swap_sender_shipping_button($order) {
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
                        if(response.success) {
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
        });
    </script>
    <p><button id="swap-sender-shipping" class="button">Swap Sender & Shipping Addresses</button></p>
    <?php
}
