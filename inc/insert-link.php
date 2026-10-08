<?php
function k_insert_link_init()
{
    /**
     * Create insert_link page
     */
    if (function_exists('acf_add_options_page')) {
        acf_add_options_page(array(
            'page_title' => 'Insert Link',
            'menu_title' => 'Insert Link',
            'menu_slug' => 'insert_link',
            'icon_url' => 'dashicons-insert',
            'capability' => 'manage_options',
            'id' => 'insert_link',
            'post_id' => 'insert_link',
        ));
    }

    /**
     * Add insert_link field group
     */
    if (function_exists('acf_add_local_field_group')) {
        acf_add_local_field_group(array(
            'key' => 'insert_link_settings',
            'title' => __('Settings', 'rom'),
            'fields' => array(
                array(
                    'label' => __('Console', 'rom'),
                    'name' => 'insert_link_console',
                    'key' => 'insert_link_console',
                    'type' => 'taxonomy',
                    'taxonomy' => 'console',
                    'field_type' => 'select',
                    'allow_null' => 1,
                ),
                array(
                    'label' => __('Url', 'rom'),
                    'name' => 'insert_link_url',
                    'key' => 'insert_link_url',
                    'type' => 'text',
                ),
                array(
                    'label' => __('Type', 'rom'),
                    'name' => 'insert_link_type',
                    'key' => 'insert_link_type',
                    'type' => 'text',
                ),
                array(
                    'label' => __('Post Status', 'rom'),
                    'name' => 'insert_link_post_status',
                    'key' => 'insert_link_post_status',
                    'type' => 'radio',
                    'choices' => array(
                        'any' => 'All',
                        'draft' => 'Draft',
                        'publish' => 'Publish',
                    ),
                    'layout' => 'horizontal',
                ),
                array(
                    'label' => __('Remove', 'rom'),
                    'name' => 'insert_link_remove',
                    'key' => 'insert_link_remove',
                    'type' => 'true_false',
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'options_page',
                        'operator' => '==',
                        'value' => 'insert_link',
                    ),
                ),
            ),
        ));
    }
}

add_action('init', 'k_insert_link_init');

/**
 * Insert Link
 */
function k_insert_link()
{

//    error_reporting(E_ALL);
//    ini_set('display_errors', 'On');

    $posts_per_page = 20;

    $params = $_REQUEST['acf'];

    if (empty($params['insert_link_console'])) {
        _e('Please select console.', 'rom');
        exit;
    }
    if (empty($params['insert_link_url'])) {
        _e('Please input url.', 'rom');
        exit;
    }

    $console = $params['insert_link_console'];
    $url = $params['insert_link_url'];
    $type = $params['insert_link_type'];
    $post_status = $params['insert_link_post_status'];
    $remove = $params['insert_link_remove'];
    update_field('insert_link_console', $console, 'insert_link');
    update_field('insert_link_url', $url, 'insert_link');
    update_field('insert_link_type', $type, 'insert_link');

    if ($remove) {
        $args = array(
            'post_type' => 'rom',
            'post_status' => $post_status,
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'title',
            'order' => 'DESC',
            'tax_query' => array(
                array(
                    'taxonomy' => 'console',
                    'field' => 'term_id',
                    'terms' => $console,
                ),
            ),
        );
        $query = new WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                delete_field('download_list');
            }
        }

        header('Content-Type: application/json');
        echo json_encode(array('status' => false, 'message' => __('Remove Link is completed.', 'rom')));

    } else {

        $offset = $_REQUEST['offset'];

        $args = array(
            'post_type' => 'rom',
            'post_status' => $post_status,
            'posts_per_page' => $posts_per_page,
            'offset' => $offset,
            'fields' => 'ids',
            'orderby' => 'title',
            'order' => 'DESC',
            'tax_query' => array(
                array(
                    'taxonomy' => 'console',
                    'field' => 'term_id',
                    'terms' => $console,
                ),
            ),
        );
        $query = new WP_Query($args);

        $cache_html = get_post_meta(2, 'dit_me_' . $console, true);

        if (!$cache_html) {

            $response = wp_remote_get($url, array(
                'timeout' => 120,
            ));

            $responseBody = wp_remote_retrieve_body($response);

            update_post_meta(2, 'dit_me_' . $console, $responseBody);

            $cache_html = $responseBody;
        }

        if ($cache_html) {

            $cache_html = preg_replace('~[\r\n]+~', '', $cache_html);

            preg_match_all('/<a href="(.*?)" class="flex flex-col items-center rounded-lg font-mono group hover:bg-gray-100 hover:shadow dark:hover:bg-purple-700">(.*?)<\/a>/sui', $cache_html, $matches);

            $elements = $matches[0];

            if ($elements) {

                if ($query->have_posts()) {

                    $message = '';

                    while ($query->have_posts()) {
                        $query->the_post();
                        $title = get_the_title();
                        $title = html_entity_decode($title);
                        $temp_title = cleanString($title);
                        $temp_title = preg_replace('/\([^)]+\)/', '', $temp_title);
                        $temp_title = strtolower($temp_title);
                        $words = preg_split('/\s+/', $temp_title);
                        $temp_title = preg_grep('/^[A-Za-z\d]+$/', $words);
                        $temp_title = array_unique($temp_title);
                        sort($temp_title);

                        $links = '';
                        foreach ($elements as $key => $element) {

                            preg_match('/<div class="flex-1 truncate">(.*?)<\/div>/ui', $element, $name_matches);
                            $name = trim($name_matches[1]);
                            $name = html_entity_decode($name);
                            $name = trim(pathinfo($name, PATHINFO_FILENAME));

                            preg_match('/href="(.*?)"/ui', $element, $link_matches);
                            $link = $link_matches[1];

                            $link = trim('https://statics.romsvault.com/' . $link);

                            preg_match('/<div class="hidden whitespace-nowrap text-right mx-2 w-1\/6 sm:block">(.*?)<\/div>/ui', $element, $size_matches);

                            $size = trim($size_matches[1]);

                            if (have_rows('download_list')) {
                                while (have_rows('download_list')) {
                                    the_row();
                                    if (get_sub_field('url') == $link) {
                                        $links .= '<br>Existed: ' . urldecode($link);
                                        continue 2;
                                    }
                                }
                            }

                            $temp_name = cleanString($name);
                            $temp_name = preg_replace('/\([^)]+\)/', '', $temp_name);
                            $temp_name = strtolower($temp_name);
                            $words = preg_split('/\s+/', $temp_name);
                            $temp_name = preg_grep('/^[A-Za-z\d]+$/', $words);
                            $temp_name = array_unique($temp_name);
                            sort($temp_name);

                            if (($temp_title == $temp_name) ||
                                (count($temp_title) >= 2 && strpos($name, $title) !== FALSE && strpos($name, $title) == 0) ||
                                ((count(array_intersect($temp_title, $temp_name)) >= count($temp_title) - 1) && count($temp_title) >= 5)) {

                                $temp_title_numbers = array_filter($temp_title, 'is_numeric');
                                $temp_name_numbers = array_filter($temp_name, 'is_numeric');
                                if (empty($temp_title_numbers) && !empty($temp_name_numbers)) continue;
                                if (!empty($temp_title_numbers) && empty($temp_name_numbers)) continue;
                                if (!empty($temp_title_numbers) && !empty($temp_name_numbers)) {
                                    foreach ($temp_title_numbers as $key => $value) {
                                        if ($temp_name_numbers[$key] != $temp_title_numbers[$key]) continue 2;
                                    }
                                }

                                $row = array(
                                    'name' => $name,
                                    'url' => $link,
                                    'file_size' => $size,
                                    'file_type' => $type
                                );

                                add_row('download_list', $row);

                                $links .= '<br>Added: ' . urldecode($link);
                            }
                        }

                        if ($links) {
                            $message .= '<li><a href="' . get_edit_post_link() . '" target="_blank">' . get_the_title() . '</a>' . $links . '</li>';
                        }
                    }

                    header('Content-Type: application/json');
                    echo json_encode(array('status' => true, 'offset' => $offset + $posts_per_page, 'message' => $message));
                } else {

                    delete_post_meta(2, 'dit_me_' . $console);

                    header('Content-Type: application/json');
                    echo json_encode(array('status' => false, 'message' => __('Insert Link is completed.', 'rom')));
                }
            }

        }
    }

    exit;
}

add_action('wp_ajax_k_insert_link', 'k_insert_link');

function cleanString($string)
{
    $utf8 = array(
        '/[áàâãªä]/u' => 'a',
        '/[ÁÀÂÃÄ]/u' => 'A',
        '/[ÍÌÎÏ]/u' => 'I',
        '/[íìîï]/u' => 'i',
        '/[éèêë]/u' => 'e',
        '/[ÉÈÊË]/u' => 'E',
        '/[óòôõºö]/u' => 'o',
        '/[ÓÒÔÕÖ]/u' => 'O',
        '/[úùûü]/u' => 'u',
        '/[ÚÙÛÜ]/u' => 'U',
        '/ç/' => 'c',
        '/Ç/' => 'C',
        '/ñ/' => 'n',
        '/Ñ/' => 'N',
        '/–/' => '-', // UTF-8 hyphen to "normal" hyphen
        '/:/' => ' -', // UTF-8 : to "normal" hyphen
        '/[’‘‹›‚]/u' => ' ', // Literally a single quote
        '/[“”«»„]/u' => ' ', // Double quote
        '/ /' => ' ', // nonbreaking space (equiv. to 0x160)
    );
    $string = preg_replace(array_keys($utf8), array_values($utf8), $string);

    $string = preg_replace('/[^A-Za-z0-9\()]/', ' ', $string); // Removes special chars.

    return $string;
}

/**
 * Edit download links
 */
function k_edit_download_links()
{
    $post_id = $_GET['post_id'];
    $field = $_GET['field'];
    ?>
    <form class="k-popover-download-links" method="POST"
          style="background-color: #fff; border: 1px solid #ccd0d4; width: 750px; padding: 10px; position: absolute; z-index: 10; top: 100%; left: 50%; transform: translateX(-50%);">
        <input type="hidden" name="action" value="k_save_download_links">
        <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
        <input type="hidden" name="field" value="<?php echo $field; ?>">
        <table class="widefat">
            <tr>
                <th><?php _e('Name', 'rom'); ?></th>
                <th><?php _e('Link', 'rom'); ?></th>
                <th><?php _e('Size', 'rom'); ?></th>
                <th><?php _e('Type', 'rom'); ?></th>
                <th><?php _e('Xóa', 'rom'); ?></th>
            </tr>

            <?php
            if (have_rows($field, $post_id)):
                while (have_rows($field, $post_id)) : the_row();
                    ?>
                    <tr>
                        <td><input style="width: 100%;" type="text" name="names[]"
                                   value="<?php the_sub_field('name'); ?>"></td>
                        <td><input style="width: 100%;" type="text" name="links[]"
                                   value="<?php the_sub_field('url'); ?>"></td>
                        <td><input style="width: 100%;" type="text" name="sizes[]"
                                   value="<?php the_sub_field('file_size'); ?>"></td>
                        <td><input style="width: 100%;" type="text" name="types[]"
                                   value="<?php the_sub_field('file_type'); ?>"></td>
                        <td>
                            <a class="k-remove-download-link" style="color: #a00;" href="javascript:void(0);">
                                <span class="dashicons dashicons-no-alt"></span>
                            </a>
                        </td>
                    </tr>
                <?php
                endwhile;
            endif;
            ?>
        </table>
        <div style="display: flex; justify-content: space-between; margin-top: 10px;">
            <a class="button button-primary k-close-download-links"
               ref="javascript:void(0);"><?php _e('Close', 'rom'); ?></a>
            <button class="button button-primary k-save-download-links" type="submit"><?php _e('Save', 'rom'); ?></button>
            <a class="button button-primary k-add-download-link" href="javascript:void(0);"><?php _e('Add', 'rom'); ?></a>
        </div>
    </form>
    <?php
    exit;
}

add_action('wp_ajax_k_edit_download_links', 'k_edit_download_links');

/**
 * Add download link
 */
function k_add_download_link()
{
    ?>
    <tr>
        <td><input style="width: 100%;" type="text" name="names[]"></td>
        <td><input style="width: 100%;" type="text" name="links[]"></td>
        <td><input style="width: 100%;" type="text" name="sizes[]"></td>
        <td><input style="width: 100%;" type="text" name="types[]"></td>
        <td>
            <a class="k-remove-download-link" style="color: #a00;" href="javascript:void(0);">
                <span class="dashicons dashicons-no-alt"></span>
            </a>
        </td>
    </tr>
    <?php
    exit;
}

add_action('wp_ajax_k_add_download_link', 'k_add_download_link');

/**
 * Save download links
 */
function k_save_download_links()
{
    $post_id = $_POST['post_id'];
    $field = $_POST['field'];
    $names = $_POST['names'];
    $links = $_POST['links'];
    $sizes = $_POST['sizes'];
    $types = $_POST['types'];

    if (!empty($names) || !empty($links) || !empty($sizes) || !empty($types)) {
        delete_field($field, $post_id);
        foreach ($names as $key => $value) {
            $row = array(
                'name' => $names[$key],
                'url' => $links[$key],
                'file_size' => $sizes[$key],
                'file_type' => $types[$key]
            );
            add_row($field, $row, $post_id);
        }
    } else {
        delete_field($field, $post_id);
    }

    if ($links = get_field($field, $post_id)) {
        foreach ($links as $value) {
            echo $value['name'] . '<br>';
        }
    } else {
        echo '';
    }

    exit;
}

add_action('wp_ajax_k_save_download_links', 'k_save_download_links');

/**
 * Change post status
 */
function k_change_post_status() {
    $post_id = $_POST['post_id'];
    $status = get_post_status($post_id);

    if ($status != 'publish') {
        wp_update_post(array(
            'ID' => $post_id,
            'post_status' => 'publish'
        ));
    } elseif ($status != 'draft') {
        wp_update_post(array(
            'ID' => $post_id,
            'post_status' => 'draft'
        ));
    }

    echo get_post_status($post_id);
    exit;
}

add_action('wp_ajax_k_change_post_status', 'k_change_post_status');

/**
 * Edit text field
 */
function k_edit_text_field()
{
    $post_id = $_GET['post_id'];
    $field = $_GET['field'];
    ?>
    <form class="k-popover-text-field" method="POST"
          style="background-color: #fff; border: 1px solid #ccd0d4; width: 400px; padding: 10px; position: absolute; z-index: 10; top: 100%; left: 50%; transform: translateX(-50%);">
        <input type="hidden" name="action" value="k_save_text_field">
        <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
        <input type="hidden" name="field" value="<?php echo $field; ?>">
        <textarea style="width: 100%; height: 80px;" name="text"><?php the_field($field, $post_id); ?></textarea>
        <div style="display: flex; justify-content: space-between; margin-top: 10px;">
            <a class="button button-primary k-close-text-field" ref="javascript:void(0);"><?php _e('Close', 'rom'); ?></a>
            <button class="button button-primary k-save-text-field" type="submit"><?php _e('Save', 'rom'); ?></button>
        </div>
    </form>
    <?php
    exit;
}

add_action('wp_ajax_k_edit_text_field', 'k_edit_text_field');

/**
 * Save text field
 */
function k_save_text_field()
{
    $post_id = $_POST['post_id'];
    $field = $_POST['field'];
    $text = $_POST['text'];
    update_field($field, $text, $post_id);

    echo get_field($field, $post_id);
    echo '<br><a class="k-edit-text-field" href="javascript:void(0);" data-post_id=' . $post_id . ' data-field="' . $field . '"><i class="dashicons dashicons-edit"></i></a>';
    exit;
}

add_action('wp_ajax_k_save_text_field', 'k_save_text_field');