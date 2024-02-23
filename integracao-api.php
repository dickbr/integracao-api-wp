<?php
/**
 * Plugin Name: Integracao API
 * Plugin URI: https://meusite.com/meu-plugin-woocommerce
 * Description: Este é um plugin personalizado para integrar a API externa com o WooCommerce.
 * Version:  1.0
 * Author: Jean-Pierre
 * License: GPL2
 */
function integracao_api_init() {
    $url = 'https://mcstaging.vendas.agis.com.br/rest/all/V1/agis/reseller/product/list';
    $token = 'lowuhjnbyyy6mwabi4yedt6udc1pgzvd';

    $searchCriteria = array(
        'currentPage' =>  1,
        'pageSize' =>  500
    );

    $url .= '?searchCriteria[currentPage]=' . $searchCriteria['currentPage'];
    $url .= '&searchCriteria[pageSize]=' . $searchCriteria['pageSize'];

    $produtos_filtrados = integracao_api_filtrar_produtos($url, $token);
    integracao_api_enviar_produtos($produtos_filtrados);

    if (!wp_next_scheduled('integracao_api_cron_hook')) {
      wp_schedule_event(time(), 'daily', 'integracao_api_cron_hook');
    } 

    add_action('integracao_api_cron_hook', 'integracao_api_init');

}

function integracao_api_filtrar_produtos() {
    $produtos = integracao_api_buscar_produtos();

    $produtos_filtrados = array_filter($produtos, function($produto) {
        return $produto['preco'] >=  500;
    });

    $produtos_filtrados = integracao_api_lidar_com_produtos_duplicados($produtos_filtrados);

    return $produtos_filtrados;
}

function integracao_api_buscar_produtos() {
    $base_url = 'https://mcstaging.vendas.agis.com.br/rest/all/V1/agis/reseller/product/list';
    $token = 'lowuhjnbyyy6mwabi4yedt6udc1pgzvd';
    $produtos = array();

    $currentPage = 1;
    $pageSize = 500;
    $totalPages = 1; 

    do {
        $searchCriteria = array(
            'currentPage' => $currentPage,
            'pageSize' => $pageSize
        );

        $url = $base_url . '?searchCriteria[currentPage]=' . $searchCriteria['currentPage'] . '&searchCriteria[pageSize]=' . $searchCriteria['pageSize'];

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json",
            "Authorization: Bearer $token"
        ));
        curl_setopt($ch, CURLOPT_TIMEOUT,  300);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code != 200) {
            break;
        }

        $produtos_pagina = json_decode($response, true);

        if (is_array($produtos_pagina) && isset($produtos_pagina['items'])) {
            foreach ($produtos_pagina['items'] as &$item) {
                if (isset($item['stock'][0]['price'])) {
                    $item['preco'] = $item['stock'][0]['price'];
                } else {
                    $item['preco'] = 0;
                }
            }
            $produtos = array_merge($produtos, $produtos_pagina['items']);
        }

        $currentPage++;

    } while ($currentPage <= $totalPages);

    return $produtos;
}

function integracao_api_lidar_com_produtos_duplicados($produtos) {
    $produtos_unicos = array();
    $produtos_duplicados = array();

    foreach ($produtos as $produto) {
        $sku = $produto['sku'];
        $warehouse = isset($produto['warehouse']) ? $produto['warehouse'] : null;

        $preco = $produto['preco'];

        if (isset($produtos_duplicados[$sku])) {
            if ($preco < $produtos_duplicados[$sku]['preco'] && $preco >  500) {
                $produtos_duplicados[$sku] = $produto;
            }
        } else {
            $produtos_duplicados[$sku] = $produto;
            $produtos_unicos[] = $produto;
        }
    }

    return $produtos_unicos;
}

function integracao_api_enviar_produtos($produtos) {
    foreach ($produtos as $produto) {
        $post_id = wc_get_product_id_by_sku($produto['sku']);

        if (!isset($produto['preco'])) {
            error_log('A chave "preco" não foi encontrada no array do produto com SKU: ' . $produto['sku']);
            continue;   
        }

        $nome = isset($produto['name']) ? $produto['name'] : 'Produto sem nome';
        $slug = sanitize_title($nome);

        if (empty($nome)) {
            $nome = 'Produto ' . $produto['sku'];
            $slug = sanitize_title($nome);
        }
        $post_data = array(
            'post_title'    => $nome,
            'post_name'     => $slug,
            'post_content'  => '',
            'post_status'   => 'publish',
            'post_author'   =>   1,
            'post_type'     => 'product',
            'meta_input'    => array(
                '_price' => $produto['preco'],
                '_sku'   => $produto['sku'],
                '_visibility' => 'visible',
                '_stock_status' => 'instock',
                '_backorders' => 'no',
                '_sold_individually' => 'no',
                '_weight' => '',
                '_length' => '',
                '_width' => '',
                '_height' => '',
                '_upsell_ids' => '',
                '_crosssell_ids' => '',
                '_purchase_note' => '',
                '_default_attributes' => '',
                '_virtual' => 'no',
                '_downloadable' => 'no',
                '_download_limit' => '',
                '_download_expiry' => '',
                '_downloadable_files' => '',
                '_featured' => 'no',
                '_product_attributes' => '',
                '_wc_rating_count' => '',
                '_wc_average_rating' => '',
                '_wc_review_count' => '',
                '_variation_description' => '',
                '_thumbnail_id' => '',
                '_product_image_gallery' => '',
                '_sale_price' => '',
                '_sale_price_dates_from' => '',
                '_sale_price_dates_to' => '',
            ),
        );
        if ($post_id) {
            $post_data['ID'] = $post_id;
            $update_result = wp_update_post($post_data);
            if (is_wp_error($update_result)) {
                error_log('Erro ao atualizar o produto com SKU: ' . $produto['sku'] . ' - ' . $update_result->get_error_message());
            } else {
                error_log('Produto atualizado com sucesso: ' . $produto['sku']);
            }
        } else {
            $insert_result = wp_insert_post($post_data);
            if (is_wp_error($insert_result)) {
                error_log('Erro ao inserir o novo produto com SKU: ' . $produto['sku'] . ' - ' . $insert_result->get_error_message());
            } else {
                error_log('Produto criado com sucesso: ' . $produto['sku']);
            }
        }
    }
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



