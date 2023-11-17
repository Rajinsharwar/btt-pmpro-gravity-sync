<?php

/*
 * Plugin Name: PmPro and Gravity Form Sync
 * Description: WordPress plugin for Big Tomato Tech to sync between Gravity Form and Paid Membership pro.
 * Version: 1.3
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
            $order->initial_payment = $total_amount;
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

                /** 
                 * Now update the Fee. the Fee is the SUM of initial_payment and billing_amount. 
                 * So, making the value update in one would do. 
                 * REF: paid-memberships-pro/adminpages/dashboard.php
                 * 
                 */
                global $wpdb;

                $fee = get_total_amount_from_stripe($entry['id'], $form_id);

                // Update the initial_payment column for the specified user ID.
                $query = $wpdb->prepare(
                    "UPDATE {$wpdb->pmpro_memberships_users}
                    SET initial_payment = %f
                    WHERE user_id = %d",
                    $fee,
                    $user_id
                );

                $wpdb->query($query);
            }
        }
    }
}

add_action('gform_user_registered', 'assign_pmpro_level_on_activation', 10, 3);

// Implement the following functions to obtain Stripe information.
function get_total_amount_from_stripe( $entry_id, $form_id ) {
    global $wpdb;

    // Define the table name
    $table_name = $wpdb->prefix . 'gf_pmpro_sync';

    // Query to retrieve the grand total amount based on entry ID and form ID
    $query = $wpdb->prepare(
        "SELECT grandtotal_amount FROM $table_name WHERE entry_id = %d AND form_id = %d",
        $entry_id,
        $form_id
    );

    // Get the result from the database
    $total_amount = $wpdb->get_var( $query );

    // Return the total amount
    return $total_amount;
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

/*
*
*Get the total amount charged by Stripe.
*
*/
use Gravity_Forms\Gravity_Forms\Orders\Summaries\GF_Order_Summary;

function log_stripe_total_amount( $entry, $form ) {
    // Check if the form ID is 64
    if ( $form['id'] == 64 ) {
        // Get the total amount charged by Stripe
        $order_summary = GF_Order_Summary::render( $form, $entry );

        // Extract grandtotal_amount from HTML using regex
        $grandtotal_amount = get_grandtotal_amount_from_html( $order_summary );

        // Save the grandtotal amount in the database
        save_grandtotal_to_database( $form['id'], $entry['id'], $grandtotal_amount );
    }
}

add_action( 'gform_after_submission_64', 'log_stripe_total_amount', 10, 2 );

function get_grandtotal_amount_from_html( $html ) {
    // Use regex to extract the grandtotal amount from the HTML
    $pattern = '/<td class="grandtotal_amount">\$([0-9.,]+)<\/td>/';
    preg_match( $pattern, $html, $matches );

    // Return the matched grandtotal amount or an empty string if not found
    return isset( $matches[1] ) ? $matches[1] : '';
}

// Function to save grandtotal to the database
function save_grandtotal_to_database( $form_id, $entry_id, $grandtotal_amount ) {
    global $wpdb;

    // Define the table name
    $table_name = $wpdb->prefix . 'gf_pmpro_sync'; // Replace 'your_custom_table_name' with your desired table name

    // Check if the table exists, if not, create it
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            form_id mediumint(9) NOT NULL,
            entry_id mediumint(9) NOT NULL,
            grandtotal_amount varchar(255) NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    // Insert data into the table
    $wpdb->insert(
        $table_name,
        array(
            'form_id'          => $form_id,
            'entry_id'         => $entry_id,
            'grandtotal_amount'=> $grandtotal_amount,
        )
    );
}

/*
* Add PMPro billing fields to the edit user profile page.
*/
function add_billing_fields_to_profile()
{
    
    //check for register helper
    if(!function_exists("pmprorh_add_registration_field"))
        return;
    
    //define the fields
    $fields = array();
    $fields[] = new PMProRH_Field("pmpro_baddress1", "text", array("label"=>"Billing Address 1", "size"=>40, "profile"=>true, "required"=>false));
    $fields[] = new PMProRH_Field("pmpro_baddress2", "text", array("label"=>"Billing Address 2", "size"=>40, "profile"=>true, "required"=>false));
    $fields[] = new PMProRH_Field("pmpro_bcity", "text", array("label"=>"Billing City", "size"=>40, "profile"=>true, "required"=>false));
    $fields[] = new PMProRH_Field("pmpro_bstate", "text", array("label"=>"Billing State", "size"=>10, "profile"=>true, "required"=>false));
    $fields[] = new PMProRH_Field("pmpro_bzipcode", "text", array("label"=>"Billing Postal Code", "size"=>10, "profile"=>true, "required"=>false));
    $fields[] = new PMProRH_Field("pmpro_bphone", "text", array("label"=>"Billing Phone", "size"=>40, "profile"=>true, "required"=>false)); 
    
    //add the fields into a new checkout_boxes are of the checkout page
    foreach($fields as $field)
        pmprorh_add_registration_field("profile", $field);
}
add_action("init", "add_billing_fields_to_profile");