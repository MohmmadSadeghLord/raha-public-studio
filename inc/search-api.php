<?php
if (!defined('ABSPATH')) exit;

add_action('pre_get_posts', function ($query) {
    if (!is_admin() && $query->is_main_query() && $query->is_search()) {
        $query->set('post_type', 'post');
        $query->set('post_status', 'publish');
        $query->set('posts_per_page', 6);
        $query->set('ignore_sticky_posts', true);
    }
});

add_action('rest_api_init', function () {
    register_rest_route('raha/v1', '/search', [
        'methods'             => WP_REST_Server::READABLE,
        'permission_callback' => '__return_true',
        'callback'            => 'raha_search_posts',
        'args'                => [
            'q'        => ['type' => 'string', 'default' => ''],
            'category' => ['type' => 'string', 'default' => ''],
            'sort'     => ['type' => 'string', 'default' => 'newest', 'enum' => ['newest', 'oldest', 'relevant']],
            'page'     => ['type' => 'integer', 'default' => 1, 'minimum' => 1, 'maximum' => 10000],
        ],
    ]);
});

function raha_search_posts(WP_REST_Request $request) {
    $term = sanitize_text_field($request->get_param('q'));
    if (strlen($term) > 320) {
        return new WP_Error('raha_search_too_long', 'عبارت جست‌وجو بیش از حد طولانی است.', ['status' => 400]);
    }

    $category = sanitize_title($request->get_param('category'));
    $sort = $request->get_param('sort');
    $page = (int) $request->get_param('page');
    $args = [
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'posts_per_page'      => 6,
        'paged'               => $page,
        'ignore_sticky_posts' => true,
        's'                   => $term,
    ];
    if ($category) {
        $args['category_name'] = $category;
    }
    if ($sort === 'oldest') {
        $args['orderby'] = 'date';
        $args['order'] = 'ASC';
    } elseif ($sort === 'relevant' && $term !== '') {
        $args['orderby'] = 'relevance';
    } else {
        $args['orderby'] = 'date';
        $args['order'] = 'DESC';
    }

    $query = new WP_Query($args);
    $items = [];
    foreach ($query->posts as $post) {
        $cats = get_the_category($post->ID);
        $display_cat = !empty($cats) ? $cats[0]->name : 'عمومی';
        if ($category) {
            foreach ($cats as $cat) {
                if ($cat->slug === $category) {
                    $display_cat = $cat->name;
                    break;
                }
            }
        }
        $items[] = [
            'id'    => (int) $post->ID,
            'title' => get_the_title($post->ID),
            'desc'  => wp_strip_all_tags(get_the_excerpt($post->ID)),
            'tag'   => $display_cat,
            'date'  => get_the_date('', $post->ID),
            'url'   => get_permalink($post->ID),
            'thumb' => get_the_post_thumbnail_url($post->ID, 'medium') ?: '',
        ];
    }

    return rest_ensure_response([
        'items' => $items,
        'total' => (int) $query->found_posts,
        'pages' => (int) $query->max_num_pages,
        'page'  => $page,
    ]);
}
