# Integração API WooCommerce

## Descrição

Este plugin é uma integração personalizada para o WooCommerce que permite a busca e atualização de produtos a partir de uma API externa. Ele foi projetado para filtrar produtos com preços acima de  500,00 e lidar com produtos duplicados, escolhendo o de menor preço maior que  500,00.

## Requisitos

- WordPress  5.0 ou superior
- WooCommerce  4.0 ou superior
- PHP  7.0 ou superior

## Instalação

1. Faça o upload do diretório `integracao-api` para a pasta `wp-content/plugins/` do seu site WordPress.
2. Acesse o painel de administração do WordPress e vá para "Plugins" no menu lateral.
3. Ative o plugin "Integração API".


## Uso

O plugin é executado automaticamente como um cron job diário. Você pode verificar os logs de erro no painel de administração do WordPress para monitorar o progresso e quaisquer problemas que possam ocorrer durante a integração.

## Suporte

Para suporte, entre em contato com o desenvolvedor do plugin ou consulte a documentação da API externa.

## Changelog

###  1.0
- Versão inicial do plugin.

## Licença

Este plugin é distribuído sob a licença GPL2.

## Autor

Jean-Pierre
