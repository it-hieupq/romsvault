<?php
/**
 * Report admin — wp-admin management for member post reports (member_report).
 * ACF options page + paginated report table + row/bulk delete handlers. The
 * table schema lives in setting-member.php; front-end submission is the REST
 * endpoint rf/v1/report (auth/actions/report.php). Admin-only (manage_options).
 */

function app_report_init() {
    /**
     * Create report page
     */
    if (function_exists('acf_add_options_page')) {
        acf_add_options_page(array(
            'page_title' => 'Report',
            'menu_title' => 'Report',
            'menu_slug' => 'report',
            'icon_url' => 'dashicons-warning',
            'capability' => 'manage_options',
            'id' => 'report',
            'post_id' => 'report',
            // Submenu of Configs (inc/options.php), which makes the page hook
            // and body class configs_page_report.
            'parent_slug' => 'options',
        ));
    }

    /**
     * Add app_report field group
     */
    if (function_exists('acf_add_local_field_group')) {
        acf_add_local_field_group(array(
            'location' => array(
                array(
                    array(
                        'param' => 'options_page',
                        'operator' => '==',
                        'value' => 'report',
                    ),
                ),
            ),
        ));
    }
}

add_action('init', 'app_report_init');

/**
 * Delete row
 */
function app_report_delete_row()
{
    // Check user capability
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Unauthorized.', 'rom')));
    }
    
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'report_delete_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'rom')));
    }
    
    global $wpdb;
    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    
    if ($id <= 0) {
        wp_send_json_error(array('message' => __('Invalid ID.', 'rom')));
    }
    
    $result = $wpdb->delete(
        rf_member_report_table(),
        array('id' => $id),
        array('%d')
    );
    
    if ($result) {
        wp_send_json_success(array('message' => __('Deleted.', 'rom')));
    } else {
        wp_send_json_error(array('message' => __('Error. Please contact administrator.', 'rom')));
    }
}

add_action('wp_ajax_app_report_delete_row', 'app_report_delete_row');

/**
 * Delete rows
 */
function app_report_delete_rows()
{
    // Check user capability
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Unauthorized.', 'rom')));
    }
    
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'report_delete_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'rom')));
    }
    
    global $wpdb;
    
    if (!isset($_POST['ids']) || !is_array($_POST['ids'])) {
        wp_send_json_error(array('message' => __('Invalid IDs.', 'rom')));
    }
    
    $ids = array_map('absint', $_POST['ids']);
    $ids = array_filter($ids);
    
    if (empty($ids)) {
        wp_send_json_error(array('message' => __('No valid IDs provided.', 'rom')));
    }
    
    // Use prepared statement for security
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $table_name = rf_member_report_table();
    
    $query = $wpdb->prepare(
        "DELETE FROM {$table_name} WHERE id IN({$placeholders})",
        ...$ids
    );
    
    $result = $wpdb->query($query);
    
    if ($result !== false) {
        wp_send_json_success(array('message' => sprintf(__('%d item(s) deleted.', 'rom'), $result)));
    } else {
        wp_send_json_error(array('message' => __('Error. Please contact administrator.', 'rom')));
    }
}

add_action('wp_ajax_app_report_delete_rows', 'app_report_delete_rows');

/**
 * create an action for your options page that will run before the ACF callback function
 * see above for information on the hook you need to use
 */
add_action('configs_page_report', 'app_before_report_page', 5);
function app_before_report_page() {
    // Before ACF outputs the options page content start an object buffer so that we can capture the output
    ob_start();
}

/**
 * create an action for your options page that will run after the ACF callback function
 * see above for information on the hook you need to use
 */
add_action('configs_page_report', 'app_after_report_page', 20);
function app_after_report_page(){
    // After ACF finishes get the output and modify it
    $content = ob_get_clean();

    // the number of times we should replace any string
    $count = 1;

    // insert something after tag <div>
    ob_start();

    global $wpdb;
    
    // Pagination
    $per_page = rf_admin_per_page( 10 );
    $current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;
    
    // Get total count (optimized)
    $total_count = $wpdb->get_var('SELECT COUNT(*) FROM ' . rf_member_report_table());
    
    // Optimized query with limit and only needed fields. Reporter identity is the
    // member row (JOIN member); LEFT JOIN so a report survives member deletion.
    $table_name   = rf_member_report_table();
    $member_table = rf_member_table();
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT r.id, r.post_id, r.url_report, r.report_type, r.report_content, r.created_at,
                m.email AS member_email, m.name AS member_name
         FROM {$table_name} r
         LEFT JOIN {$member_table} m ON m.id = r.member_id
         ORDER BY r.created_at DESC
         LIMIT %d OFFSET %d",
        $per_page,
        $offset
    ));
    
    // Cache post data to avoid multiple queries
    $post_ids = array_unique(wp_list_pluck($rows, 'post_id'));
    $posts_cache = array();
    if (!empty($post_ids)) {
        $posts = get_posts(array(
            'post__in' => $post_ids,
            'post_type' => 'any',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ));
        foreach ($posts as $post) {
            $posts_cache[$post->ID] = array(
                'title' => $post->post_title,
                'type' => $post->post_type,
                'edit_link' => get_edit_post_link($post->ID)
            );
        }
    }
    
    if ($rows) :
        ?>
        <style>
            /* Prevent horizontal scroll */
            body.configs_page_report {
                overflow-x: hidden !important;
            }
            
            /* Hide default ACF form submit button */
            body.configs_page_report .acf-form-submit,
            body.configs_page_report #submitdiv,
            body.configs_page_report .submitbox,
            body.configs_page_report .acf-button,
            body.configs_page_report input[type="submit"].acf-button {
                display: none !important;
            }
            
            /* Full width layout */
            body.configs_page_report #postbox-container-1 {
                display: none !important;
            }
            
            body.configs_page_report #postbox-container-2 {
                width: 100% !important;
                margin-right: 0 !important;
                box-sizing: border-box !important;
            }
            
            body.configs_page_report #post-body-content {
                width: 100% !important;
                box-sizing: border-box !important;
            }
            
            body.configs_page_report #post-body {
                width: 100% !important;
                box-sizing: border-box !important;
            }
            
            body.configs_page_report .wrap {
                box-sizing: border-box !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            
            body.configs_page_report #wpbody-content {
                box-sizing: border-box !important;
                overflow-x: hidden !important;
            }
            
            body.configs_page_report #wpbody-content .wrap {
                margin-left: 2px !important;
                margin-right: 0 !important;
                padding-right: 10px !important;
                padding-left: 0 !important;
                padding-top: 0 !important;
                padding-bottom: 0 !important;
                box-sizing: border-box !important;
            }
            
            body.configs_page_report .acf-postbox {
                max-width: 100% !important;
                width: 100% !important;
                box-sizing: border-box !important;
                margin: 0 !important;
            }
            
            body.configs_page_report #post-body.columns-2 {
                width: 100% !important;
            }
            
            body.configs_page_report .postbox-container {
                box-sizing: border-box !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            
            body.configs_page_report #wpcontent {
                box-sizing: border-box !important;
                overflow-x: hidden !important;
            }
            
            body.configs_page_report .postbox {
                box-sizing: border-box !important;
            }
            
            body.configs_page_report .inside {
                box-sizing: border-box !important;
                padding: 0 !important;
            }
            
            .report-table-wrapper {
                overflow-x: auto;
                margin: 20px 0;
                margin-left: 0;
                margin-right: 0;
                padding: 0;
                -webkit-overflow-scrolling: touch;
                box-sizing: border-box;
                width: 100%;
                max-width: 100%;
            }
            .report-table-wrapper table {
                min-width: 1000px;
                width: 100%;
                box-sizing: border-box;
                table-layout: auto;
            }
            .report-table-wrapper th,
            .report-table-wrapper td {
                padding: 10px;
                word-wrap: break-word;
                vertical-align: top;
            }
            .report-table-wrapper .col-type {
                min-width: 150px;
                max-width: 200px;
            }
            .report-table-wrapper .col-email {
                min-width: 150px;
                max-width: 200px;
            }
            .report-table-wrapper .col-issue {
                min-width: 120px;
                max-width: 150px;
            }
            .report-table-wrapper .col-url {
                min-width: 200px;
                max-width: 300px;
            }
            .report-table-wrapper .col-url a {
                word-break: break-all;
                display: inline-block;
                max-width: 100%;
            }
            .report-table-wrapper .col-describe {
                min-width: 200px;
                max-width: 400px;
            }
            .report-table-wrapper .col-describe {
                word-break: break-word;
                overflow-wrap: break-word;
            }
            .report-table-wrapper .col-actions {
                min-width: 100px;
                text-align: center;
            }
            .report-table-wrapper .col-time {
                min-width: 100px;
                white-space: nowrap;
            }
            .report-pagination {
                margin: 20px 0;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .report-pagination .page-numbers {
                padding: 5px 10px;
                text-decoration: none;
                border: 1px solid #ccc;
                border-radius: 3px;
            }
            .report-pagination .page-numbers.current {
                background: #0073aa;
                color: white;
                border-color: #0073aa;
            }
        </style>
        
        <form method="get" style="margin:12px 0">
            <input type="hidden" name="page" value="report" />
            <?php rf_admin_per_page_field( rf_admin_today_badge( rf_admin_today_count( rf_member_report_table(), false ) ) ); ?>
        </form>

        <div class="report-table-wrapper" data-nonce="<?php echo esc_attr(wp_create_nonce('report_delete_nonce')); ?>">
            <table class="widefat striped">
                <thead>
                <tr>
                    <th class="col-type">Type</th>
                    <th class="col-email">Email</th>
                    <th class="col-issue">Issue</th>
                    <th class="col-url">URL</th>
                    <th class="col-describe">Describe</th>
                    <th class="col-actions">
                        <button class="button button-small delete-rows" type="button">Delete</button>
                        <button class="button button-small button-primary" id="checkAll" type="button">Check</button>
                    </th>
                    <th class="col-time">Time</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row) : 
                    $post_data = isset($posts_cache[$row->post_id]) ? $posts_cache[$row->post_id] : null;
                    $post_title = $post_data ? $post_data['title'] : 'Post #' . $row->post_id;
                    $post_type = $post_data ? $post_data['type'] : 'unknown';
                    $edit_link = $post_data ? $post_data['edit_link'] : '#';
                ?>
                    <tr>
                        <td class="col-type">
                            <?php if ($edit_link !== '#') : ?>
                                <a class="dashicons dashicons-edit" target="_blank" href="<?php echo esc_url($edit_link); ?>" title="Edit Post"></a>
                            <?php endif; ?>
                            <strong><?php echo esc_html(strtoupper('[' . $post_type . ']')); ?></strong><br>
                            <small><?php echo esc_html($post_title); ?></small>
                        </td>
                        <td class="col-email">
                            <?php echo esc_html($row->member_email ?: '—'); ?>
                            <?php if (!empty($row->member_name)) : ?><br><small><?php echo esc_html($row->member_name); ?></small><?php endif; ?>
                        </td>
                        <td class="col-issue"><?php echo esc_html($row->report_type); ?></td>
                        <td class="col-url">
                            <a href="<?php echo esc_url($row->url_report); ?>" target="_blank" title="<?php echo esc_attr($row->url_report); ?>">
                                <?php echo esc_html(wp_trim_words($row->url_report, 15, '...')); ?>
                            </a>
                        </td>
                        <td class="col-describe">
                            <?php 
                            if (!empty($row->report_content)) {
                                echo esc_html(wp_trim_words($row->report_content, 20, '...'));
                            } else {
                                echo '<em>No description</em>';
                            }
                            ?>
                        </td>
                        <td class="col-actions" style="text-align: center;">
                            <input class="check-item" type="checkbox" name="ids" value="<?php echo esc_attr($row->id); ?>">
                        </td>
                        <td class="col-time">
                            <?php echo rf_admin_time_ago((string) $row->created_at, false); // member_report.created_at is site-local ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php
        // Pagination
        $total_pages = ceil($total_count / $per_page);
        if ($total_pages > 1) :
            $base_url = add_query_arg('per_page', $per_page, admin_url('admin.php?page=report'));
            ?>
            <div class="report-pagination">
                <span class="displaying-num">
                    <?php 
                    $start = ($current_page - 1) * $per_page + 1;
                    $end = min($current_page * $per_page, $total_count);
                    printf(__('Showing %d-%d of %d reports', 'rom'), $start, $end, $total_count);
                    ?>
                </span>
                <div class="pagination-links">
                    <?php
                    if ($current_page > 1) {
                        echo '<a class="page-numbers" href="' . esc_url(add_query_arg('paged', $current_page - 1, $base_url)) . '">« Previous</a>';
                    }
                    
                    for ($i = 1; $i <= $total_pages; $i++) {
                        if ($i == 1 || $i == $total_pages || ($i >= $current_page - 2 && $i <= $current_page + 2)) {
                            $class = ($i == $current_page) ? 'current' : '';
                            echo '<a class="page-numbers ' . $class . '" href="' . esc_url(add_query_arg('paged', $i, $base_url)) . '">' . $i . '</a>';
                        } elseif ($i == $current_page - 3 || $i == $current_page + 3) {
                            echo '<span class="page-numbers dots">…</span>';
                        }
                    }
                    
                    if ($current_page < $total_pages) {
                        echo '<a class="page-numbers" href="' . esc_url(add_query_arg('paged', $current_page + 1, $base_url)) . '">Next »</a>';
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?>
    <?php else : ?>
        <div class="acf-admin-notice notice notice-error">
            <p><?php _e('Not found.', 'rom'); ?></p>
        </div>
    <?php
    endif;

    $my_content = ob_get_clean();

    $content = str_replace('<div id="postbox-container-2" class="postbox-container">', '<div id="postbox-container-2" class="postbox-container">' . $my_content, $content, $count);

    // output the new content
    echo $content;
}