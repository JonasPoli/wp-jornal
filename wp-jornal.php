<?php
/**
 * Plugin Name: WP Jornal
 * Description: Gera um jornal em HTML a partir das notícias dos últimos 6 meses.
 * Version: 1.0.0
 * Author: Seu Nome
 */

// Diretórios auxiliares
if (!defined('WP_JORNAL_DIR')) {
    define('WP_JORNAL_DIR', plugin_dir_path(__FILE__));
}
if (!defined('WP_JORNAL_URL')) {
    define('WP_JORNAL_URL', plugin_dir_url(__FILE__));
}

// Cria diretório de saída se necessário
register_activation_hook(__FILE__, function () {
    if (!file_exists(WP_JORNAL_DIR . 'gerado')) {
        wp_mkdir_p(WP_JORNAL_DIR . 'gerado');
    }
});

// Adiciona página no menu administrativo
add_action('admin_menu', function () {
    add_menu_page('Jornais', 'Jornais', 'manage_options', 'wp-jornal', 'wpj_admin_page');
});

/**
 * Página administrativa principal
 */
function wpj_admin_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $step = isset($_POST['step']) ? intval($_POST['step']) : 1;

    echo '<div class="wrap">';
    echo '<h1>Gerador de Jornal</h1>';

    if ($step === 1) {
        // Passo 1 - Seleciona destaque
        $posts = wpj_recent_posts();
        echo '<h2>1. Escolha a notícia de destaque</h2>';
        echo '<form method="post">';
        echo '<input type="hidden" name="step" value="2">';
        foreach ($posts as $p) {
            $date = get_the_date('d/m/Y', $p);
            echo '<p><label><input type="radio" name="destaque" value="' . esc_attr($p->ID) . '" required> ' . esc_html($p->post_title) . ' (' . esc_html($date) . ')</label></p>';
        }
        submit_button('Próximo');
        echo '</form>';
    } elseif ($step === 2) {
        // Passo 2 - Seleciona 4 posts
        $destaque_id = intval($_POST['destaque']);
        $posts = wpj_recent_posts($destaque_id);
        echo '<h2>2. Selecione quatro destaques para a página principal</h2>';
        echo '<form method="post">';
        echo '<input type="hidden" name="step" value="3">';
        echo '<input type="hidden" name="destaque" value="' . esc_attr($destaque_id) . '">';
        foreach ($posts as $p) {
            $date = get_the_date('d/m/Y', $p);
            echo '<p><label><input type="checkbox" name="posts[]" value="' . esc_attr($p->ID) . '"> ' . esc_html($p->post_title) . ' (' . esc_html($date) . ')</label></p>';
        }
        echo '<p>Escolha exatamente 4 posts.</p>';
        submit_button('Próximo');
        echo '</form>';
    } elseif ($step === 3) {
        // Passo 3 - Dados da contracapa
        $destaque_id = intval($_POST['destaque']);
        $posts_ids = array_map('intval', $_POST['posts'] ?? []);
        if (count($posts_ids) !== 4) {
            echo '<div class="notice notice-error"><p>É necessário selecionar exatamente quatro posts.</p></div>';
            echo '<a href="' . esc_url(admin_url('admin.php?page=wp-jornal')) . '" class="button">Voltar</a>';
        } else {
            $defaults = wpj_contracapa_defaults();
            echo '<h2>3. Preencha os dados da contracapa</h2>';
            echo '<form method="post">';
            echo '<input type="hidden" name="step" value="4">';
            echo '<input type="hidden" name="destaque" value="' . esc_attr($destaque_id) . '">';
            foreach ($posts_ids as $id) {
                echo '<input type="hidden" name="posts[]" value="' . esc_attr($id) . '">';
            }
            foreach ($defaults as $field => $value) {
                echo '<p><label>' . esc_html(str_replace('_', ' ', $field)) . ': '; 
                echo '<input type="text" name="contra[' . esc_attr($field) . ']" value="' . esc_attr($value) . '" size="40"></label></p>';
            }
            submit_button('Gerar jornal');
            echo '</form>';
        }
    } elseif ($step === 4) {
        // Gera jornal
        $destaque_id = intval($_POST['destaque']);
        $posts_ids = array_map('intval', $_POST['posts'] ?? []);
        $contra = array_map('sanitize_text_field', $_POST['contra'] ?? []);
        $url = wpj_generate_jornal($destaque_id, $posts_ids, $contra);
        if ($url) {
            echo '<div class="updated notice"><p>Jornal gerado com sucesso! <a href="' . esc_url($url) . '" target="_blank">Abrir jornal</a></p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Erro ao gerar jornal.</p></div>';
        }
    }

    echo '<hr/><h2>Jornais gerados</h2>';
    wpj_list_jornais();
    echo '</div>';
}

/**
 * Busca posts dos últimos 6 meses
 */
function wpj_recent_posts($exclude = 0)
{
    $args = [
        'date_query' => [
            'after' => date('Y-m-d', strtotime('-6 months')),
        ],
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'post_type' => 'post',
        'orderby' => 'date',
        'order' => 'DESC',
        'exclude' => $exclude ? [$exclude] : [],
    ];
    return get_posts($args);
}

/**
 * Valores padrão da contracapa
 */
function wpj_contracapa_defaults()
{
    return [
        'presidente' => 'Raul Aderval Leiva',
        'vice_presidente' => 'Marcia Antonia Toledo Pinto',
        '1_secretaria' => 'Lucas Primani',
        '2_secretaria' => 'Diana Mazzola Barreto',
        '1_Tesoureira' => 'Rafael Izidio',
        '2_Tesoureira' => 'Fatima Favaro Satilio',
        'diretor_de_patrimonio' => 'Tomás Velosa Alonso',
        'dep_doutrina' => 'Neusa Marina Stoppa',
        'dep_assistencia' => 'Léa Micelli',
        'dep_mocidade' => 'Lucas Primani',
        'dep_divulgacao' => 'Jonas Ernesto Poli',
    ];
}

/**
 * Lista arquivos gerados
 */
function wpj_list_jornais()
{
    $dir = WP_JORNAL_DIR . 'gerado/';
    if (!file_exists($dir)) {
        return;
    }
    $files = glob($dir . '*.html');
    if (!$files) {
        echo '<p>Nenhum jornal gerado ainda.</p>';
        return;
    }
    echo '<ul>';
    foreach ($files as $file) {
        $url = WP_JORNAL_URL . 'gerado/' . basename($file);
        echo '<li><a target="_blank" href="' . esc_url($url) . '">' . esc_html(basename($file)) . '</a></li>';
    }
    echo '</ul>';
}

/**
 * Limita caracteres e adiciona reticências
 */
function wpj_limit_chars($text, $limit)
{
    $text = wp_strip_all_tags($text);
    if (mb_strlen($text) > $limit) {
        return mb_substr($text, 0, $limit - 3) . '...';
    }
    return $text;
}

/**
 * Gera HTML do jornal
 */
function wpj_generate_jornal($destaque_id, $posts_ids, $contra)
{
    $template_dir = WP_JORNAL_DIR . 'modelo/';
    $capa_tpl = file_get_contents($template_dir . 'capa.html');
    $materia_tpl = file_get_contents($template_dir . 'materia.html');
    $contracapa_tpl = file_get_contents($template_dir . 'contracapa.html');
    $html_tpl = file_get_contents($template_dir . 'html-completo.html');

    // Capa - destaque
    $destaque = get_post($destaque_id);
    $destaque_img = wpj_post_image($destaque_id);
    $capa = str_replace([
        '__destaque_titulo__',
        '__destaque_data__',
        '__destaque_chamada__',
        '__destaque_imagem_url__',
        '__destaque_imagem_legenda__'
    ], [
        esc_html($destaque->post_title),
        get_the_date('d/m/Y', $destaque),
        wpj_limit_chars($destaque->post_content, 700),
        esc_url($destaque_img['url']),
        esc_html($destaque_img['caption'])
    ], $capa_tpl);

    // Outros posts na capa
    $limits = [300, 200, 200, 200];
    foreach ($posts_ids as $i => $post_id) {
        $p = get_post($post_id);
        $index = $i + 1;
        $capa = str_replace([
            "__post_{$index}_titulo__",
            "__post_{$index}_data__",
            "__post_{$index}_chamada__"
        ], [
            esc_html($p->post_title),
            get_the_date('d/m/Y', $p),
            wpj_limit_chars($p->post_content, $limits[$i])
        ], $capa);
    }

    // Matérias (destaque + 4 posts)
    $materias_html = '';
    $all_posts = array_merge([$destaque_id], $posts_ids);
    foreach ($all_posts as $post_id) {
        $p = get_post($post_id);
        $img = wpj_post_image($post_id);
        $temp = $materia_tpl;
        $temp = str_replace([
            '__post_1_titulo__',
            '__post_1_data__',
            '__post_1_conteudo_700__',
            '__post_1_conteudo_paragrafo__',
            '__post_1_imagem_url__',
            '__post_1_imagem_legenda__'
        ], [
            esc_html($p->post_title),
            get_the_date('d/m/Y', $p),
            wpj_limit_chars($p->post_content, 700),
            apply_filters('the_content', $p->post_content),
            esc_url($img['url']),
            esc_html($img['caption'])
        ], $temp);
        $materias_html .= $temp;
    }

    // Contracapa
    foreach ($contra as $field => $value) {
        $contracapa_tpl = str_replace('__' . $field . '__', esc_html($value), $contracapa_tpl);
    }

    // Junta tudo
    $body = $capa . $materias_html . $contracapa_tpl;
    $html = str_replace(['__body__', '__data__'], [$body, date('d/m/Y')], $html_tpl);

    // Salva arquivo
    $dir = WP_JORNAL_DIR . 'gerado/';
    if (!file_exists($dir)) {
        wp_mkdir_p($dir);
    }
    $filename = date('Y-m-d-H-i-s') . '-jornal-o-mensageiro.html';
    $path = $dir . $filename;
    $saved = file_put_contents($path, $html);
    if (!$saved) {
        return false;
    }
    return WP_JORNAL_URL . 'gerado/' . $filename;
}

/**
 * Retorna URL e legenda da imagem destacada
 */
function wpj_post_image($post_id)
{
    $image_id = get_post_thumbnail_id($post_id);
    if ($image_id) {
        $url = wp_get_attachment_image_url($image_id, 'full');
        $caption = get_post($image_id)->post_excerpt;
        return ['url' => $url, 'caption' => $caption];
    }
    return ['url' => '', 'caption' => ''];
}

