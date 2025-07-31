<?php
/**
 * Plugin Name: WP Jornal
 * Description: Gera um jornal em HTML a partir das notícias dos últimos 18 meses.
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
            $thumb = get_the_post_thumbnail($p->ID, 'thumbnail');
            echo '<p><label><input type="radio" name="destaque" value="' . esc_attr($p->ID) . '" required> ' . $thumb . ' ' . esc_html($p->post_title) . ' (' . esc_html($date) . ')</label></p>';
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
            $thumb = get_the_post_thumbnail($p->ID, 'thumbnail');
            echo '<p><label><input type="checkbox" name="posts[]" value="' . esc_attr($p->ID) . '"> ' . $thumb . ' ' . esc_html($p->post_title) . ' (' . esc_html($date) . ')</label></p>';
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
 * Busca posts dos últimos 18 meses
 */
function wpj_recent_posts($exclude = 0)
{
    $args = [
        'date_query' => [
            'after' => date('Y-m-d', strtotime('-18 months')),
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
 * Remove shortcodes do Divi, trechos entre colchetes e tags HTML.
 */
function wpj_clean_text($text)
{
    $text = strip_shortcodes($text);
    $text = preg_replace('/\[[^\]]*\]/', '', $text);
    $text = wp_strip_all_tags($text);
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim($text);
}

/**
 * Corta o texto sem quebrar palavras.
 * Retorna um array com a parte inicial e o restante.
 */
function wpj_cut_text($text, $limit)
{
    if (mb_strlen($text) <= $limit) {
        return [$text, ''];
    }
    $cut = mb_substr($text, 0, $limit);
    $last_space = mb_strrpos($cut, ' ');
    if ($last_space !== false) {
        $head = mb_substr($cut, 0, $last_space);
        $tail = ltrim(mb_substr($text, $last_space));
    } else {
        $head = $cut;
        $tail = ltrim(mb_substr($text, $limit));
    }
    return [$head, $tail];
}

/**
 * Divide o texto em partes iguais sem quebrar palavras.
 */
function wpj_split_equal_parts($text, $parts)
{
    $segments = [];
    $len = mb_strlen($text);
    $start = 0;
    for ($i = $parts; $i > 1; $i--) {
        $avg = (int) ceil(($len - $start) / $i);
        $offset = $start + $avg;
        if ($offset < $len) {
            $next_space = mb_strpos($text, ' ', $offset);
            if ($next_space === false) {
                $next_space = $len;
            }
        } else {
            $next_space = $len;
        }
        $segments[] = trim(mb_substr($text, $start, $next_space - $start));
        $start = $next_space + 1;
    }
    $segments[] = trim(mb_substr($text, $start));
    return $segments;
}

/**
 * Limita caracteres e adiciona reticências sem quebrar palavras.
 */
function wpj_limit_chars($text, $limit)
{
    $text = wpj_clean_text($text);
    if (mb_strlen($text) > $limit) {
        list($short, $rest) = wpj_cut_text($text, $limit);
        return $short . '...';
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
    $materia_full_tpl = file_get_contents($template_dir . 'materia-full.html');
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
        wpj_limit_chars($destaque->post_content, 1800),
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

        $first_name = get_the_author_meta('first_name', $p->post_author);
        $last_name  = get_the_author_meta('last_name', $p->post_author);
        $author = trim($first_name . ' ' . $last_name);
        $texto = wpj_clean_text($p->post_content);
        $texto .= ' <div><small><strong>Autor:</strong> ' . esc_html($author) . ' </small></div>';

        list($parte1, $resto) = wpj_cut_text($texto, 700);
        $paginas = [];
        if (mb_strlen($resto) <= 4900) {
            $paginas[] = wpj_split_equal_parts($resto, 3);
        } else {
            list($primeira, $resto_total) = wpj_cut_text($resto, 4900);
            $paginas[] = wpj_split_equal_parts($primeira, 3);
            while ($resto_total !== '') {
                list($chunk, $resto_total) = wpj_cut_text($resto_total, 6700);
                $paginas[] = wpj_split_equal_parts($chunk, 3);
            }
        }

        $primeira_pagina = array_shift($paginas);
        $temp = str_replace([
            '__post_1_titulo__',
            '__post_1_data__',
            '__post_1_conteudo_700__',
            '__post_1_conteudo_paragrafo_a__',
            '__post_1_conteudo_paragrafo_b__',
            '__post_1_conteudo_paragrafo_c__',
            '__post_1_imagem_url__',
            '__post_1_imagem_legenda__'
        ], [
            esc_html($p->post_title),
            get_the_date('d/m/Y', $p),
            $parte1,
            $primeira_pagina[0] ?? '',
            $primeira_pagina[1] ?? '',
            $primeira_pagina[2] ?? '',
            esc_url($img['url']),
            esc_html($img['caption'])
        ], $materia_tpl);
        $materias_html .= $temp;

        foreach ($paginas as $pagina) {
            $temp_full = str_replace([
                '__post_1_conteudo_paragrafo_a__',
                '__post_1_conteudo_paragrafo_b__',
                '__post_1_conteudo_paragrafo_c__'
            ], [
                $pagina[0] ?? '',
                $pagina[1] ?? '',
                $pagina[2] ?? ''
            ], $materia_full_tpl);
            $materias_html .= $temp_full;
        }
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

