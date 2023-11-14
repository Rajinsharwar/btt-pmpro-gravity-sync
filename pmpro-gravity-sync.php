<?php
/*
 * Plugin Name: PmPro and Gravity Form Sync
 * Description: WordPress plugin for Big Tomato Tech to sync between Gravity Form and Paid Membership pro.
 * Version: 1.0
 * Requires at least: 6.0
 * Author: Rajin Sharwar
 * Author URI: https://profiles.wordpress.org/rajinsharwar
 * Text Domain: pmpro-gravity-sync
 */

function assign_pmpro_level_on_activation($user_id, $user_data, $entry) {
    // Check the form ID to ensure it's the desired form.
    $form_id = 64; // Replace 64 with the actual form ID you want to target.

    if ($entry['form_id'] == $form_id) {
        // Get the selected product value from the submitted form data.
        $selected_product_value = rgar($entry, '8'); // Replace '8' with the actual field ID.

        // Remove the option index and pipe character.
        $selected_product_value = strtok($selected_product_value, '|');

        // Map the selected product option to the PMPro level ID.
        $pmpro_level = 0; // Default level ID if no match is found.

        if ($selected_product_value == 'Basic:  $62/month') {
            $pmpro_level = 2;
        } elseif ($selected_product_value == 'Basic Plus Domain: $65.50/month') {
            $pmpro_level = 3;
        } elseif ($selected_product_value == 'NewsTrack:  $124/month') {
            $pmpro_level = 4;
        } elseif ($selected_product_value == 'State-Large:  $188/month') {
            $pmpro_level = 5;
        } elseif ($selected_product_value == 'Basic-Annual: $669.60/year') {
            $pmpro_level = 6;
        } elseif ($selected_product_value == 'Basic Plus-Annual: $707.40/year') {
            $pmpro_level = 7;
        } elseif ($selected_product_value == 'NewsTrack-Annual: $1,339.20/year') {
            $pmpro_level = 8;
        } elseif ($selected_product_value == 'State-Large-Annual: $2,030.40/year') {
            $pmpro_level = 9;
        }

        // Assign the PMPro level to the user.
        if ($pmpro_level > 0) {
            pmpro_changeMembershipLevel($pmpro_level, $user_id);

            // Now, create the PMPro order.
            global $current_user;
            wp_get_current_user();

            $user_email = $current_user->user_email; // Get the user's email address from WordPress user data.
            $level = pmpro_getLevel($pmpro_level);
            $total_amount = get_total_amount_from_stripe($entry['id'], $form_id); // Implement this function to get the total amount from Stripe.
            $last_four_digits = get_last_four_digits_from_stripe($entry['id'], $form_id); // Implement this function to get the last four digits of the card used in Stripe.
            $gateway = 'stripe';
            $transaction_id = get_transaction_id_from_stripe($entry['id'], $form_id);
            $order_date = current_time('mysql');

            $order = new MemberOrder();
            $order->user_id = $user_id;
            $order->membership_id = $pmpro_level;
            $order->InitialPayment = $total_amount;
            $order->PaymentAmount = $total_amount;
            $order->payment_type = 'Stripe - Card ' . $last_four_digits;
            $order->cardtype = 'Visa'; // Set the appropriate card type.
            $order->gateway = $gateway;
            $order->payment_transaction_id = $transaction_id;
            $order->status = 'success';
            $order->subscription_transaction_id = $transaction_id;
            $order->timestamp = $order_date;

            if ($order->saveOrder()) {
                // Order created successfully.
            }
        }
    }
}

add_action('gform_user_registered', 'assign_pmpro_level_on_activation', 10, 3);

// Implement the following functions to obtain Stripe information.
function get_total_amount_from_stripe($entry_id, $form_id) {
    global $wpdb;
    
    // Specify the table name for Gravity Forms entries.
    $entry_table = $wpdb->prefix . 'gf_entry';

    // Query the database to get the transaction ID based on the entry ID.
    $payment_amount = $wpdb->get_var($wpdb->prepare(
        "SELECT payment_amount FROM $entry_table WHERE id = %d AND form_id = %d",
        $entry_id,
        $form_id
    ));

    return $payment_amount;
}

function get_last_four_digits_from_stripe($entry_id, $form_id) {
    global $wpdb;
    
    // Specify the table name for Gravity Forms entries.
    $entry_meta_table = $wpdb->prefix . 'gf_entry_meta';

    // Query the database to get the last four digits of the credit card based on the entry ID and form ID.
    $last_four_digits = $wpdb->get_var($wpdb->prepare(
        "SELECT meta_value FROM $entry_meta_table WHERE entry_id = %d AND meta_key = 33.1",
        $entry_id
    ));

    return $last_four_digits;
}

function get_transaction_id_from_stripe($entry_id, $form_id) {
    global $wpdb;
    
    // Specify the table name for Gravity Forms entries.
    $entry_table = $wpdb->prefix . 'gf_entry';

    // Query the database to get the transaction ID based on the entry ID.
    $transaction_id = $wpdb->get_var($wpdb->prepare(
        "SELECT transaction_id FROM $entry_table WHERE id = %d AND form_id = %d",
        $entry_id,
        $form_id
    ));

    return $transaction_id;
}