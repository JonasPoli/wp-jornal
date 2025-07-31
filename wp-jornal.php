<?php
/**
 * Plugin Name: WP Jornal
 * Description: Um plugin de exemplo para exibir mensagens no painel.
 * Version: 0.1
 * Author: Seu Nome
 */

add_action('admin_notices', function () {
    echo '<div class="notice notice-success is-dismissible"><p>✅ Plugin WP Jornal ativado!</p></div>';
});
