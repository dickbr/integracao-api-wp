<?php
set_time_limit(10000);
/**
 * Plugin Name: Integracao API
 * Plugin URI: https://meusite.com/meu-plugin-woocommerce
 * Description: Este é um plugin personalizado para integrar a API externa com o WooCommerce.
 * Version:  1.0
 * Author: Jean-Pierre
 * License: GPL2
 */
function integracao_api_init() {
    integracao_api_buscar_produtos();

    if (!wp_next_scheduled('integracao_api_cron_hook')) {
      wp_schedule_event(time(), 'daily', 'integracao_api_cron_hook');
    } 

    add_action('integracao_api_cron_hook', 'integracao_api_init');
}

function hasMinPrice($price, $min_price){
  return $price >= $min_price;
}

function getSlug($name){
  $name = isset($name) ? $name : 'Produto sem nome';
  return sanitize_title($name);
}

function getProductBySku($sku){
  $args = array(
    'post_type' => 'product',
    'meta_query' => array(
        array(
            'key' => '_sku',
            'value' => $sku,
            'compare' => '=',
            ),
        ),
  );
  
  $products = get_posts($args);

  if (!empty($products) && isset($products[0])) {
      return $products[0]->ID;
  }

  return null;
}

function updateAndContinue($sku, $price, $product, $currentPage){
  $post_id = getProductBySku($sku);
  if(isset($post_id)){
    $actual_price = get_post_meta($post_id, '_price', true);
    if($price > $actual_price){
      error_log('sku: ' . $sku . ' | preço atual: ' . $actual_price . ' | preço api: ' . $price);
      $product['ID'] = $post_id;
      $result = wp_update_post($product);
      logResultQuery($result, $sku, 'atualizado | post_id ' . $post_id, $currentPage);
    }

    return true;
  }

  return false;
}

function logResultQuery($result, $sku, $operation, $pag){
   if (is_wp_error($result)) {
        error_log('pag '. $pag . ' | Erro ao '. $operation .' o produto com SKU: ' . $sku . ' - ' . $result->get_error_message());
    } else {
        error_log('pag '. $pag . ' | Produto '. $operation .' com sucesso: ' . $sku);
    }
}

function integracao_api_buscar_produtos() {
    $base_url = 'https://mcstaging.vendas.agis.com.br/rest/all/V1/agis/reseller/product/list';
    $token = 'lowuhjnbyyy6mwabi4yedt6udc1pgzvd';
    $products = [];

    $currentPage = 1;
    $pageSize = 250;

    do {
        $searchCriteria = array(
            'currentPage' => $currentPage,
            'pageSize' => $pageSize
        );

        $url = $base_url . '?searchCriteria[currentPage]=' . $searchCriteria['currentPage'] . '&searchCriteria[pageSize]=' . $searchCriteria['pageSize'];

        try{
          $ch = curl_init($url);

          curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($ch, CURLOPT_TIMEOUT,  5);
          curl_setopt($ch, CURLOPT_HTTPHEADER, array(
              "Content-Type: application/json",
              "Authorization: Bearer $token"
          ));

          $response = curl_exec($ch);

          curl_close($ch);

          $produtos_pagina = json_decode($response, true);

          if(!isset($produtos_pagina)){
            error_log('nenhum dado retornado da api');
            $currentPage++;
            continue;
          }

          $items = $produtos_pagina['items'];

          if(empty($items)){
            error_log('produtos não encontrados na api');
            $products = [];
            $currentPage++;
            continue;
          }

          $products = $produtos_pagina;

          foreach ($produtos_pagina['items'] as &$item) {
            $price = $item['stock'][0]['price'];
            $sku = $item['sku'];
            if(!hasMinPrice($price, 500)){
              error_log('produto não possui valor minimo de R$500,00 | preço: '. $price .' | sku: ' . $sku);
              continue;
            }

            $slug = getSlug($item['name']);
            
            $product = [
              'post_title' =>  $item['name'],
              'post_name' =>  $slug,
              'post_status'   => 'publish',
              'post_author'   =>   1,
              'post_type'     => 'product',
              'meta_input' => [
                '_sku' => $sku,
                '_quantity' => $item['stock'][0]['qty'],
                '_price' => $price,
                '_visibility' => 'visible',
                '_stock_status' => 'instock',
                '_backorders' => 'no',
                '_sold_individually' => 'no',
              ]
            ];

              $continue = updateAndContinue($sku, $price, $product, $currentPage);
              
              if($continue) {
                continue;
              }

              $result = wp_insert_post($product);

              logResultQuery($result, $sku, 'inserido', $currentPage);
            }

          $currentPage++;
        } catch (Exception $e) {
           error_log('pag::error' . $currentPage . ' | Erro: ' . $e->getMessage());
           $currentPage++;
           continue;
        }

    // } while ($currentPage <= 1);
    } while (!empty($products));

}


function integracao_api_agendar_cron() {
    if (!wp_next_scheduled('integracao_api_cron_job')) {
        wp_schedule_event(time(), 'daily', 'integracao_api_cron_job');
    }
}

function integracao_api_executar_cron_job() {
    integracao_api_init();
}

function integracao_api_register_rest_route() {
    register_rest_route('v1', '/criar-produtos', array(
        'methods' => 'GET',
        'callback' => 'integracao_api_test_endpoint',
        'permission_callback' => '__return_true',
    ));
}

function integracao_api_test_endpoint() {
    integracao_api_init();
    return new WP_REST_Response('API Finish',  200);
}


add_action('rest_api_init', 'integracao_api_register_rest_route');
add_action('init', 'integracao_api_agendar_cron');
add_action('integracao_api_cron_job', 'integracao_api_executar_cron_job');



