<html>
<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <style>
        .wrap .wrap-title {
            font-weight: 600;
        }
        .submit_button {
            margin-top: 20px;
        }
    </style>
</head>
</html>

<?php

// Options for naming plugin
/*
Plugin Name: Aktualizace stavu skladu
Description: Plugin pro aktualizace stavu skladu produktů
Version: 1.0
Author: Daniyar
Text Domain: stock_status_update
*/


add_action( 'admin_menu', 'add_update_stock_status_page' );

// Create import products page inside Woocommerce products
function add_update_stock_status_page() {
    add_submenu_page(
        'woocommerce',
        'Aktualizace stavu skladu',
        'Aktualizace stavu skladu',
        'manage_options',
        'update-stock-status-tab',
        'update_stock_callback'
    );
}


// Getting SKU of products from products list
function get_product_by_using_sku( $sku ) {
    global $wpdb;
    $product_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $sku));
    if ($product_id) {
        $product = wc_get_product($product_id);

        return $product;
    }
    return null;
}



// Plugin page
function update_stock_callback()
{
    echo '<div class="wrap">';
    echo '<h1 class="wrap-title">Aktualizace stavu skladu</h1>';
    echo '<form action="' . esc_url($_SERVER['REQUEST_URI']) . '" method="post" enctype="multipart/form-data">';
    echo '<button class="btn btn-primary submit_button" type="submit" name="update_stock">Aktualizovat</button>';
    echo '</form>';
    echo '</div>';

    if (isset($_POST['update_stock'])) {
        // API key
        $headers = array(
                'APIKey: YOUR_API_KEY'
        );

        // Getting sku value from all existing products
        $args = array(
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        );

        $product_ids = get_posts( $args );

        $products_sku = [];

        // Paste all sku to array
        foreach ( $product_ids as $product_id ) {
            $sku = get_post_meta( $product_id, '_sku', true );
            if ( $sku ) {
                array_push($products_sku, $sku);
            }
        }




        $sku_params = array();

        // Create url for fetch json from API
        foreach ($products_sku as $sku) {
            $sku_params[] = 'RegNum=' . $sku;
        }

        // Getting all data from API
        $url = 'https://portal.facecounter.eu/pwc_api/api/v1/Stocks/Checker?' . implode('&', $sku_params);
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        curl_close($ch);

        // Decode json
        $product = json_decode($response);

        // Updating data quantity for products
        foreach ($products_sku as $index => $sku) {
            $existing_product_id = get_product_by_using_sku($sku);
            $product_id = $existing_product_id->id;

            update_post_meta($product_id, '_stock', $product[$index]->quantity);

        }
        echo '<div class="alert alert-success" role="alert">Stav skladu byl aktualizován!</div>';
    }
}
