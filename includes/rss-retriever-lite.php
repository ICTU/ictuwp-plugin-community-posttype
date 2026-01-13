<?php
/*
Plugin Name: RSS Retriever Lite
Version: 1.1.1
Description: RSS Retriever Lite is a lightweight WordPress plugin for importing and managing RSS and Atom feeds. It supports Google and Yandex product feeds, YouTube and Vimeo video feeds, automatic updates, scheduling, filtering, translation, and integration with WooCommerce, Polylang and WPML.
Author: RSS Retriever Team
Plugin URI: https://www.rssretriever.com/
Author URI: https://www.rssretriever.com/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: rss-retriever-lite
*/

if (! defined('ABSPATH')) {
    die('This file cannot be accessed directly.');
}

const rss_retrieval_CRON_MAGIC             = 'rssrtvr_cron_magic';
const rss_retrieval_MAX_CURL_REDIRECTS     = 20;
const rss_retrieval_POST_LIFE_CHECK_PERIOD = 3600;
const rss_retrieval_MIN_UPDATE_TIME        = 1;
const rss_retrieval_CURL_USER_AGENT        = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36';
const rss_retrieval_CHECK_DATE             = 'rssrtvr_checkdate';
const rss_retrieval_POST_LIFE_CHECK_DATE   = 'rssrtvr_post_life_check_date';
const rss_retrieval_ACCOUNTS               = 'rssrtvr_accaunts';
const rss_retrieval_FEED_OPTIONS           = 'rssrtvr_feed_options';
const rss_retrieval_FEEDS_UPDATED          = 'rssrtvr_feeds_updated';
const rss_retrieval_SYNDICATED_FEEDS       = 'rssrtvr_syndicated_feeds';
const rss_retrieval_RSS_PULL_MODE          = 'rssrtvr_rss_pull_mode';
const rss_retrieval_PC_INTERVAL            = 'rssrtvr_pseudo_cron_interval';
const rss_retrieval_FEED_PULL_TIME         = 'rssrtvr_feed_pull_time';
const rss_retrieval_MAX_EXEC_TIME          = 'rssrtvr_max_exec_time';
const rss_retrieval_LOG                    = 'rssrtvr_parse_feed_log';
const rss_retrieval_KEEP_IMAGES            = 'rssrtvr_keep_images';
const rss_retrieval_PC_NAME                = 'rss_retrieval_custom_interval';
const rss_retrieval_BLOCK_DIVIDER          = '825670622173';

function rss_retrieval_fixurl($url) {

    if (! is_object($url)) {
        $url = str_replace(' ', '+', trim($url));
        if (! preg_match('!^https?://.+!i', $url)) {
            $url = 'https://' . $url;
        }

        $parsed_url = wp_parse_url($url);
        if (isset($parsed_url['path']) && ! preg_match('/%[0-9A-Fa-f]{2}/', $parsed_url['path'])) {
            $encoded_path = rawurlencode($parsed_url['path']);
            $encoded_path = str_replace(array('%2F', '%24', '%40', '%3A'), ['/', '$', '@', ':'], $encoded_path);
        } else {
            $encoded_path = $parsed_url['path'] ?? '';
        }

        $url = $parsed_url['scheme'] . '://' . esc_attr($parsed_url['host']) . $encoded_path;
        if (isset($parsed_url['query'])) {
            if (! preg_match('/%[0-9A-Fa-f]{2}/', $parsed_url['query'])) {
                parse_str($parsed_url['query'], $query_array);
                $encoded_query = http_build_query($query_array);
                $url          .= '?' . $encoded_query;
            } else {
                $url .= '?' . $parsed_url['query'];
            }
        }
    }

    return $url;
}

function rss_retrieval_html_cleanup($html) {
    $pre_contents    = [];
    $pre_placeholder = 'PRE_PLACEHOLDER_' . uniqid();
    preg_match_all('#<pre[^>]*>.*?</pre>#is', $html, $matches);
    foreach ($matches[0] as $index => $pre) {
        $pre_contents[$index] = $pre;
        $html                   = str_replace($pre, $pre_placeholder . $index, $html);
    }

    $decoded_html = html_entity_decode($html, ENT_QUOTES, 'UTF-8');

    foreach ($pre_contents as $index => $content) {
        $decoded_html = str_replace($pre_placeholder . $index, $content, $decoded_html);
    }

    $decoded_html = preg_replace('~<(?:!DOCTYPE|/?(?:html|body|head))[^>]*>\s*~i', '', $decoded_html);

    return $decoded_html;
}

function rss_retrieval_remove_emojis($string) {
    $emoji_pattern = '[\x{1F100}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]|\xEF[\xB8-\xBB][\x80-\xBF]|[\xF0-\xF4][\x80-\xBF]{3}';
    return preg_replace("/$emoji_pattern/u", '', $string);
}

function rss_retrieval_file_get_contents($url, $as_array = false, $headers = false, $referrer = false, $ua = false) {
    global $rssrtvr_lite;

    if (trim($url) === '') {
        return false;
    }

    if (strpos($url, '&amp;') !== false) {
        $url = html_entity_decode($url);
    }

    if (stream_is_local($url)) {
        $content = @call_user_func('file_get_contents', $url);
        if ($content === false && isset($rssrtvr_lite)) {
            $rssrtvr_lite->log('Failed to read local file: ' . $url);
        }
    } elseif (wp_parse_url($url, PHP_URL_SCHEME) !== '') {

        if ($headers === false) {
            if (isset($rssrtvr_lite->current_feed['options']['http_headers'])) {
                $headers = $rssrtvr_lite->current_feed['options']['http_headers'];
            } else {
                $headers = '';
            }
        }

        $headers       = trim($headers);
        $headers_array = [];

        if (strlen($headers)) {
            foreach (explode("\n", $headers) as $line) {
                $line = trim($line);
                if (strpos($line, ':') !== false) {
                    list($key, $value)             = explode(':', $line, 2);
                    $headers_array[trim($key)] = trim($value);
                }
            }
        }

        if ($ua === false) {
            if (isset($rssrtvr_lite->current_feed['options']['user_agent'])) {
                $ua = $rssrtvr_lite->current_feed['options']['user_agent'];
            } else {
                $ua = '';
            }
        }

        if (strtolower($referrer) === 'self') {
            $headers_array['Referer'] = $url;
        } elseif (strlen($referrer)) {
            $headers_array['Referer'] = $referrer;
        }

        if (strlen($ua)) {
            $headers_array['User-Agent'] = $ua;
        }

        $response = wp_remote_get(
            $url,
            [
                'headers'     => $headers_array,
                'timeout'     => 15,
                'redirection' => rss_retrieval_MAX_CURL_REDIRECTS,
            ]
        );

        if (is_wp_error($response)) {
            if (isset($rssrtvr_lite)) {
                $rssrtvr_lite->log('Failed to retrieve ' . $url);
                $rssrtvr_lite->log('WP Error: ' . $response->get_error_message());
            }
            $content = false;
        } else {
            $code = wp_remote_retrieve_response_code($response);
            if ($code !== 200) {
                if (isset($rssrtvr_lite)) {
                    $rssrtvr_lite->log('Server response: ' . $code);
                }
                $content = false;
            } else {
                $content = wp_remote_retrieve_body($response);
            }
        }
    }

    if (! empty($content) && $as_array) {
        $content = explode("\n", trim($content));
    }

    return $content;
}

function rss_retrieval_get_header_field_value($header, $field) {
    if (is_array($header)) {
        $end = count($header) - 1;
        if ($end) {
            for ($i = $end; $i >= 0; $i--) {
                if (strpos($header[$i], ':') !== false) {
                    list($name, $value) = explode(':', $header[$i]);
                    if (mb_strtolower(trim($name)) == mb_strtolower($field)) {
                        return trim($value);
                    }
                }
            }
        }
    }
    return '';
}

function rss_retrieval_get_headers($url) {
    global $rssrtvr;

    $ua = isset($rssrtvr->current_feed['options']['user_agent']) ? $rssrtvr->current_feed['options']['user_agent'] : '';

    if (wp_parse_url($url, PHP_URL_SCHEME) === null || wp_parse_url($url, PHP_URL_SCHEME) === '') {
        return false;
    }

    $args = [
        'timeout'     => 10,
        'redirection' => defined('rss_retrieval_MAX_CURL_REDIRECTS') ? rss_retrieval_MAX_CURL_REDIRECTS : 5,
    ];

    if ($ua !== '') {
        $args['user-agent'] = $ua;
    }

    $response = wp_remote_head($url, $args);
    if (is_wp_error($response)) {
        return false;
    }

    $code    = wp_remote_retrieve_response_code($response);
    $message = wp_remote_retrieve_response_message($response);
    $out     = [];
    $out[]   = 'HTTP/1.1 ' . esc_html(intval($code)) . ' ' . (string) $message;

    $headers = wp_remote_retrieve_headers($response);
    if (is_object($headers) && method_exists($headers, 'getAll')) {
        $headers = $headers->getAll();
    }
    if (is_array($headers)) {
        foreach ($headers as $k => $v) {
            if (is_array($v)) {
                $v = implode(', ', $v);
            }
            $out[] = $k . ': ' . $v;
        }
    }

    return $out;
}

function rss_retrieval_is_binary($url) {
    $header = rss_retrieval_get_headers($url);
    if (! is_array($header)) {
        return false;
    }
    if (isset($header[0]) && stripos($header[0], 'forbidden') !== false) {
        return false;
    }
    $content_type   = rss_retrieval_get_header_field_value($header, 'content-type');
    $content_length = rss_retrieval_get_header_field_value($header, 'content-length');
    if (stripos($content_type, 'text') !== false || intval($content_length) === 0) {
        return false;
    }
    return true;
}

function rss_retrieval_strip_specific_tags($html, $tagsToRemove) {
    if (strlen(trim($html))) {
        if (! stripos($html, '<body>')) {
            $html = '<body>' . $html . '</body>';
        }
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        @$dom->loadHTML(@mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $contentRemovingTags = [
            'audio',
            'canvas',
            'embed',
            'figure',
            'figcaption',
            'video',
            'source',
            'img',
            'style',
            'script',
            'iframe',
            'object',
            'param',
            'picture',
            'track',
            'map',
            'area',
            'noscript',
            'applet',
            'frame',
            'frameset',
        ];

        foreach ($tagsToRemove as $tag) {
            $tag      = strtolower(trim($tag));
            $elements = $dom->getElementsByTagName($tag);
            for ($i = $elements->length; --$i >= 0;) {
                $element = $elements->item($i);

                if (in_array($tag, $contentRemovingTags)) {
                    $element->parentNode->removeChild($element);
                } else {
                    $fragment = $dom->createDocumentFragment();

                    while ($element->childNodes->length > 0) {
                        $fragment->appendChild($element->childNodes->item(0));
                    }

                    $element->parentNode->replaceChild($fragment, $element);
                }
            }
        }

        return rss_retrieval_html_cleanup($dom->saveHTML());
    }

    return $html;
}

function rss_retrieval_chop_str($str, $max_length = 0, $ending = '...') {
    $length = mb_strlen($str);
    if ($max_length > 1 && $length > $max_length) {
        $ninety  = $max_length * 0.9;
        $length -= $ninety;
        $first   = mb_substr($str, 0, -$length);
        $last    = mb_substr($str, $ninety - $max_length);
        $str     = $first . esc_html($ending) . $last;
    }
    return $str;
}

function rss_retrieval_shorten_html($text, $max_length = 0, $ending = '...', $exact = false) {
    if ($max_length == 0 || mb_strlen(preg_replace('/<.*?>/', '', $text)) <= $max_length) {
        return $text;
    }
    $total_length   = mb_strlen($ending);
    $open_tags      = [];
    $truncated_text = '';
    preg_match_all('/(<.+?>)?([^<>]*)/su', $text, $lines, PREG_SET_ORDER);
    foreach ($lines as $line_matchings) {
        if (! empty($line_matchings[1])) {
            if (preg_match('/^<(\s*.+?\/\s*|\s*(img|br|input|hr|area|base|basefont|col|frame|isindex|link|meta|param)(\s.+?)?)>$/isu', $line_matchings[1])) {
            } elseif (preg_match('/^<\s*\/([^\s]+?)\s*>$/su', $line_matchings[1], $tag_matchings)) {
                $pos = array_search($tag_matchings[1], $open_tags);
                if ($pos !== false) {
                    unset($open_tags[$pos]);
                }
            } elseif (preg_match('/^<\s*([^\s>!]+).*?>$/su', $line_matchings[1], $tag_matchings)) {
                array_unshift($open_tags, mb_strtolower($tag_matchings[1]));
            }
            $truncated_text .= $line_matchings[1];
        }
        $content_length = mb_strlen(preg_replace('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i', ' ', $line_matchings[2]));
        if ($total_length + $content_length > $max_length) {
            $left            = $max_length - $total_length;
            $entities_length = 0;
            if (preg_match_all('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i', $line_matchings[2], $entities, PREG_OFFSET_CAPTURE)) {
                foreach ($entities[0] as $entity) {
                    if ($entity[1] + 1 - $entities_length <= $left--) {
                        $entities_length += mb_strlen($entity[0]);
                    } else {
                        break;
                    }
                }
            }
            $truncated_text .= mb_substr($line_matchings[2], 0, $left + $entities_length);
            break;
        } else {
            $truncated_text .= $line_matchings[2];
            $total_length   += $content_length;
        }
        if ($total_length >= $max_length) {
            break;
        }
    }
    if (! $exact) {
        $space_pos = mb_strrpos($truncated_text, ' ');
        if (isset($space_pos)) {
            $truncated_text = mb_substr($truncated_text, 0, $space_pos);
        }
    }
    $truncated_text .= $ending;
    foreach ($open_tags as $tag) {
        $truncated_text .= '</' . esc_html($tag) . '>';
    }
    return $truncated_text;
}

function rss_retrieval_strip_tags($text) {
    if (is_null($text) || ! is_scalar($text)) {
        return '';
    }
    // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
    return trim(strip_tags(
        preg_replace(
            '/ +/',
            ' ',
            preg_replace('@<(style|script|iframe|embed|noscript|object|svg)[^>]*?>.*?</\\1>@si', '', $text)
        )
    ));
}

function rss_retrieval_REQUEST_URI() {
    if (isset($_SERVER['REQUEST_URI'])) {
        return esc_url_raw(wp_unslash($_SERVER['REQUEST_URI']));
    }
    return '';
}

function rss_retrieval_fix_white_spaces($str) {
    return preg_replace('/\s\s+/', ' ', preg_replace('/\s\"/', ' "', preg_replace('/\s\'/', ' \'', $str)));
}

function rss_retrieval_delete_media_by_url($media_urls) {
    $wp_upload_dir = wp_upload_dir();
    if (! is_array($media_urls)) {
        $media_urls = [$media_urls];
    }
    if (count($media_urls)) {
        $media_urls = array_values(array_unique($media_urls));
        foreach ($media_urls as $url) {

            if (strpos($url, '/') === 0) {
                continue;
            }

            preg_match('/\/wp-content\/(.*?)$/', $url, $link_match);
            preg_match('/.*?\/wp-content\//', $wp_upload_dir['path'], $path_match);
            if (isset($path_match[0]) && isset($link_match[1])) {
                wp_delete_file($path_match[0] . $link_match[1]);
            } else {
                wp_delete_file(str_replace($wp_upload_dir['url'], $wp_upload_dir['path'], $url));
            }
        }
    }
}

function rss_retrieval_post_exists($post, $method = '') {
    global $wpdb, $rssrtvr_lite;

    if ($method === '') {
        $method = $rssrtvr_lite->current_feed['options']['duplicate_check_method'];
    }

    $rssrtvr_lite->log('Duplicate check by ' . str_replace('_', ' ', str_replace('guid', 'link', $method)));

    $name          = trim(sanitize_title(rss_retrieval_fix_white_spaces($post['post_title'])));
    $no_emoji_name = rss_retrieval_remove_emojis($name);

    if (strlen(($post['link']))) {
        $post_link = trim($post['link']);
    } else {
        $post_link = trim($post['guid']);
    }

    if (empty($post_link)) {
        if ($method === 'link_and_title') {
            $method = 'title';
        } elseif ($method === 'guid') {
            $method = 'none';
        }
    }

    if (empty($name)) {
        if ($method === 'link_and_title') {
            $method = 'guid';
        } elseif ($method === 'title') {
            $method = 'none';
        }
    }

    switch ($method) {
        case 'guid':
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $result = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DISTINCT ID
                    FROM {$wpdb->posts} AS posts
                    JOIN {$wpdb->postmeta} AS postmeta
                    ON posts.ID = postmeta.post_id
                    WHERE postmeta.meta_key = '_rssrtvr_post_link'
                    AND postmeta.meta_value = %s
                    AND posts.post_status NOT IN ('trash')
                    AND posts.post_type NOT IN ('attachment','revision','nav_menu_item')",
                    $post_link
                )
            );
            $rssrtvr_lite->link_checked = $post_link;
            break;

        case 'title':
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $result = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DISTINCT ID
                    FROM {$wpdb->posts} AS posts
                    LEFT JOIN {$wpdb->postmeta} AS postmeta
                    ON posts.ID = postmeta.post_id
                    WHERE (
                            (postmeta.meta_key = '_rssrtvr_post_name' AND postmeta.meta_value = %s)
                            OR (posts.post_name = %s)
                            OR (posts.post_name = %s)
                        )
                    AND posts.post_status NOT IN ('trash')
                    AND posts.post_type NOT IN ('attachment','revision','nav_menu_item')",
                    $name,
                    $name,
                    $no_emoji_name
                )
            );
            break;

        case 'link_and_title':
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $result = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DISTINCT ID
                    FROM {$wpdb->posts} AS posts
                    LEFT JOIN {$wpdb->postmeta} AS postmeta
                    ON posts.ID = postmeta.post_id
                    WHERE (
                            (postmeta.meta_key = '_rssrtvr_post_name' AND postmeta.meta_value = %s)
                            OR (posts.post_name = %s)
                            OR (posts.post_name = %s)
                            OR (postmeta.meta_key = '_rssrtvr_post_link' AND postmeta.meta_value = %s)
                        )
                    AND posts.post_status NOT IN ('trash')
                    AND posts.post_type NOT IN ('attachment','revision','nav_menu_item')",
                    $name,
                    $name,
                    $no_emoji_name,
                    $post_link
                )
            );
            $rssrtvr_lite->link_checked = $post_link;
            break;

        default:
            return false;
    }

    if (isset($result)) {
        foreach ($result as $res) {
            if (function_exists('pll_get_post_language')) {
                if (($rssrtvr_lite->current_feed['options']['polylang_language'] === '' && pll_get_post_language($res->ID) === pll_default_language()) || pll_get_post_language($res->ID) === $rssrtvr_lite->current_feed['options']['polylang_language']) {
                    return $res->ID;
                } else {
                    $rssrtvr_lite->polylang_translations[pll_get_post_language($res->ID)] = $res->ID;
                }
            } elseif (defined('ICL_SITEPRESS_VERSION') && isset($GLOBALS['sitepress'])) {
                global $sitepress;
                $post_language = $sitepress->get_language_for_element($res->ID, 'post_' . get_post_type($res->ID));
                if (($rssrtvr_lite->current_feed['options']['wpml_language'] === '' && $post_language === $sitepress->get_default_language()) || $post_language === $rssrtvr_lite->current_feed['options']['wpml_language']) {
                    return $res->ID;
                } else {
                    $rssrtvr_lite->wpml_translations[$post_language] = $res->ID;
                }
            } else {
                return $res->ID;
            }
        }
    }
    return false;
}

function rss_retrieval_attach_post_thumbnail($post_id, $image_url, $title) {
    $attach_id = rss_retrieval_add_image_to_library($image_url, $title, $post_id);
    if ($attach_id !== false) {
        if (set_post_thumbnail($post_id, $attach_id)) {
            return $attach_id;
        }
    }
    return false;
}

function rss_retrieval_default_options() {
    $defaults = [
        rss_retrieval_ACCOUNTS             => [],
        rss_retrieval_CHECK_DATE           => 0,
        rss_retrieval_POST_LIFE_CHECK_DATE => 0,
        rss_retrieval_MAX_EXEC_TIME        => 60,
        rss_retrieval_SYNDICATED_FEEDS     => [],
        rss_retrieval_FEEDS_UPDATED        => [],
        rss_retrieval_RSS_PULL_MODE        => 'auto',
        rss_retrieval_PC_INTERVAL          => 2 * rss_retrieval_MIN_UPDATE_TIME,
        rss_retrieval_FEED_PULL_TIME       => 0,
        rss_retrieval_KEEP_IMAGES          => 'on',
    ];

    foreach ($defaults as $name => $val) {
        if (get_option($name) === false) {
            update_option($name, $val);
        }
    }

    $options = get_option(rss_retrieval_ACCOUNTS);

    $services = [
        'deepl'  => ['api_key'],
        'yandex' => ['api_key'],
        'google' => ['api_key'],
    ];

    foreach ($services as $service => $keys) {
        foreach ($keys as $key) {
            $option_key = "{$service}_{$key}";
            if (! isset($options[$option_key])) {
                $options[$option_key] = '';
            }
        }

        $api_limit_key             = "{$service}_api_limit";
        $options[$api_limit_key] = [
            'epoch'        => $options[$api_limit_key]['epoch'] ?? 0,
            'max_requests' => $options[$api_limit_key]['max_requests'] ?? 0,
            'count'        => $options[$api_limit_key]['count'] ?? 0,
            'period'       => $options[$api_limit_key]['period'] ?? 3600,
        ];
    }

    if (! get_option(rss_retrieval_CRON_MAGIC)) {
        update_option(rss_retrieval_CRON_MAGIC, md5(time()));
    }

    update_option(rss_retrieval_ACCOUNTS, $options);
}

function rss_retrieval_yandex_translate($apikey, $text, $dir, $return_empty = false) {
    global $rssrtvr_lite;

    if ($rssrtvr_lite->api_overlimit('yandex_api_limit')) {
        $rssrtvr_lite->log('Yandex Translate API hourly request limit has been reached');
        return $return_empty ? '' : false;
    }

    if (str_starts_with($apikey, 'trnsl.')) {
        $rssrtvr_lite->log('Translate content with Yandex Translate API v1.5');

        $response = wp_remote_post(
            'https://translate.yandex.net/api/v1.5/tr.json/translate',
            [
                'timeout' => 15,
                'body'    => [
                    'key'    => trim($apikey),
                    'lang'   => $dir,
                    'format' => 'html',
                    'text'   => $text,
                ],
            ]
        );

        if (is_wp_error($response)) {
            $rssrtvr_lite->log('Yandex Translate request error: ' . $response->get_error_message());
            return $return_empty ? '' : false;
        }

        $json = json_decode(wp_remote_retrieve_body($response), true);
    } else {
        $rssrtvr_lite->log('Translate content with Yandex Translate API v2');

        list($sl, $tr) = explode('-', $dir);

        $postData = [
            'texts'              => [$text],
            'sourceLanguageCode' => $sl,
            'targetLanguageCode' => $tr,
        ];

        $response = wp_remote_post(
            'https://translate.api.cloud.yandex.net/translate/v2/translate',
            [
                'timeout' => 15,
                'headers' => [
                    'Authorization' => 'Api-Key ' . $apikey,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode($postData),
            ]
        );

        if (is_wp_error($response)) {
            $rssrtvr_lite->log('Yandex Translate request error: ' . $response->get_error_message());
            return $return_empty ? '' : false;
        }

        $json = json_decode(wp_remote_retrieve_body($response), true);
    }

    if (! isset($json['code']) && isset($json['translations'][0]['text'])) {
        $rssrtvr_lite->log('Done');
        return $json['translations'][0]['text'];
    } elseif (isset($json['code']) && (int) $json['code'] === 200 && isset($json['text'][0])) {
        $rssrtvr_lite->log('Done');
        return $json['text'][0];
    } else {
        $rssrtvr_lite->log('Yandex Translate report: "' . ($json['message'] ?? 'Unknown error') . '"');
        return $return_empty ? '' : false;
    }
}

function rss_retrieval_google_translate($apikey, $text, $source, $target, $return_empty = false) {
    global $rssrtvr_lite;

    if ($rssrtvr_lite->api_overlimit('google_api_limit')) {
        $rssrtvr_lite->log('Google Translate API hourly request limit has been reached');
        return $return_empty ? '' : false;
    }

    $rssrtvr_lite->log('Translate content with Google Translate');

    $response = wp_remote_post(
        'https://translation.googleapis.com/language/translate/v2',
        [
            'timeout' => 15,
            'body'    => [
                'key'    => trim($apikey),
                'source' => $source,
                'target' => $target,
                'q'      => $text,
            ],
        ]
    );

    if (is_wp_error($response)) {
        $rssrtvr_lite->log('Google Translate request error: ' . $response->get_error_message());
        return $return_empty ? '' : false;
    }

    $json = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($json['data']['translations'][0]['translatedText'])) {
        $rssrtvr_lite->log('Done');
        return $json['data']['translations'][0]['translatedText'];
    } else {
        $msg = '';
        if (isset($json['error']['errors'][0]['message'])) {
            $msg = $json['error']['errors'][0]['message'];
        } elseif (isset($json['error']['message'])) {
            $msg = $json['error']['message'];
        } else {
            $msg = 'Unknown error';
        }
        $rssrtvr_lite->log('Google Translate report: "' . esc_html($msg) . '"');
        return $return_empty ? '' : false;
    }
}

function rss_retrieval_deepl_translate($apikey, $text, $target, $use_api_free = false, $return_empty = false) {
    global $rssrtvr_lite;

    if ($rssrtvr_lite->api_overlimit('deepl_api_limit')) {
        $rssrtvr_lite->log('DeepL API hourly request limit has been reached');
        return $return_empty ? '' : false;
    }

    $rssrtvr_lite->log('Translate content with DeepL');

    $url = $use_api_free ? 'https://api-free.deepl.com/v2/translate' : 'https://api.deepl.com/v2/translate';

    $response = wp_remote_post(
        $url,
        [
            'timeout' => 15,
            'body'    => [
                'preserve_formatting' => 1,
                'auth_key'            => trim($apikey),
                'target_lang'         => $target,
                'text'                => $text,
            ],
        ]
    );

    if (is_wp_error($response)) {
        $rssrtvr_lite->log('DeepL request error: ' . $response->get_error_message());
        return $return_empty ? '' : false;
    }

    $json = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($json['translations'][0]['text'])) {
        $rssrtvr_lite->log('Done');
        return $json['translations'][0]['text'];
    } else {
        $rssrtvr_lite->log(isset($json['message']) ? $json['message'] : 'Unknown DeepL error');
        return $return_empty ? '' : false;
    }
}

function rss_retrieval_compare_files($file_name_1, $file_name_2) {
    $file1 = rss_retrieval_file_get_contents($file_name_1);
    $file2 = rss_retrieval_file_get_contents($file_name_2);
    if ($file1 && $file2) {
        return (md5($file1) == md5($file2));
    }
    return false;
}

function rss_retrieval_save_image($image_url, $preferred_name = '', $width = -1, $height = -1, $compression = -1, $output_image_type = null) {
    global $rssrtvr_lite;

    $wp_upload_dir = wp_upload_dir();
    $temp_name     = wp_unique_filename($wp_upload_dir['path'], md5(time()) . '.tmp');
    $image_url     = trim($image_url);

    if (str_starts_with($image_url, '//')) {
        $image_url = 'http:' . $image_url;
    } elseif (str_starts_with($image_url, '/') && isset($rssrtvr_lite->post['link'])) {
        $parse     = wp_parse_url($rssrtvr_lite->post['link']);
        $image_url = $parse['scheme'] . '://' . esc_attr($parse['host']) . $image_url;
    }

    if (!wp_is_writable($wp_upload_dir['path'])) {
        $rssrtvr_lite->log($wp_upload_dir['path'] . ' is not writable. The image will be hotlinked');
        return $image_url;
    }

    if (! function_exists('gd_info')) {
        $rssrtvr_lite->log('GD library is missing. The image will be hotlinked');
        return $image_url;
    }

    if (! isset($image_file) || $image_file === false) {
        $image_file = rss_retrieval_file_get_contents($image_url);
        if ($image_file === false) {
            $image_file = rss_retrieval_file_get_contents($image_url, false, false, false, rss_retrieval_CURL_USER_AGENT);
        }
    }

    file_put_contents($wp_upload_dir['path'] . '/' . $temp_name, $image_file, LOCK_EX);

    $image_info = @getimagesize($wp_upload_dir['path'] . '/' . $temp_name);
    if ($image_info !== false) {
        $image_type = $image_info[2];
        $rssrtvr_lite->log('Save image "' . $image_url . '"');
        if ($image_type === IMAGETYPE_JPEG || $image_type === IMAGETYPE_JPEG2000) {
            $image = @imagecreatefromjpeg($wp_upload_dir['path'] . '/' . $temp_name);
            $rssrtvr_lite->log('JPEG format detected');
        } elseif ($image_type === IMAGETYPE_GIF) {
            $image = @imagecreatefromgif($wp_upload_dir['path'] . '/' . $temp_name);
            $rssrtvr_lite->log('GIF format detected');
        } elseif ($image_type === IMAGETYPE_PNG) {
            $image = @imagecreatefrompng($wp_upload_dir['path'] . '/' . $temp_name);
            $rssrtvr_lite->log('PNG format detected');
        } elseif ($image_type === IMAGETYPE_BMP) {
            $image = @imagecreatefrombmp($wp_upload_dir['path'] . '/' . $temp_name);
            $rssrtvr_lite->log('BMP format detected');
        } elseif ($image_type === IMAGETYPE_WBMP) {
            $image = @imagecreatefromwbmp($wp_upload_dir['path'] . '/' . $temp_name);
            $rssrtvr_lite->log('WBMP format detected');
        } elseif ($image_type === IMAGETYPE_WEBP) {
            $image = @imagecreatefromwebp($wp_upload_dir['path'] . '/' . $temp_name);
            $rssrtvr_lite->log('WEBP format detected');
        } elseif ($image_type === IMAGETYPE_XBM) {
            $image = @imagecreatefromxbm($wp_upload_dir['path'] . '/' . $temp_name);
            $rssrtvr_lite->log('XBM format detected');
        } else {
            $rssrtvr_lite->log('The image file format is not recognized. The image will be hotlinked');
            wp_delete_file($wp_upload_dir['path'] . '/' . $temp_name);
            return $image_url;
        }

        if ($output_image_type != null) {
            $ext = str_ireplace('tiff', 'tif', str_replace('jpeg', 'jpg', image_type_to_extension($output_image_type, true)));
        } else {
            $ext               = str_ireplace('tiff', 'tif', str_replace('jpeg', 'jpg', image_type_to_extension($image_type, true)));
            $output_image_type = $image_type;
        }

        if ($image == false) {
            $rssrtvr_lite->log('Can\'t process the image. The image will be hotlinked');
            wp_delete_file($wp_upload_dir['path'] . '/' . $temp_name);
            return $image_url;
        }

        $default_file_name = sanitize_file_name(sanitize_title($preferred_name) . $ext);
        if ($preferred_name !== '' && strpos($default_file_name, '%') === false) {
            $file_name = $default_file_name;
        } else {
            $file_name = basename($image_url);
        }

        $do_transform_image = ($width != -1 || $height != -1 || $compression != -1 || $output_image_type != $image_type);

        if (file_exists($wp_upload_dir['path'] . '/' . $file_name)) {
            if (! $do_transform_image && rss_retrieval_compare_files($wp_upload_dir['path'] . '/' . $temp_name, $wp_upload_dir['path'] . '/' . $file_name)) {
                imagedestroy($image);
                wp_delete_file($wp_upload_dir['path'] . '/' . $temp_name);
                return $wp_upload_dir['url'] . '/' . $file_name;
            }
            $file_name = wp_unique_filename($wp_upload_dir['path'], $file_name);
        }

        $image_path      = $wp_upload_dir['path'] . '/' . $file_name;
        $local_image_url = $wp_upload_dir['url'] . '/' . $file_name;

        if ($do_transform_image) {
            $img_width  = imagesx($image);
            $img_height = imagesy($image);

            if (preg_match('/%$/', $width)) {
                $width = (int) round($img_width * intval($width) / 100);
            }
            if (preg_match('/%$/', $height)) {
                $height = (int) round($img_height * intval($height) / 100);
            }

            if ($width == -1 && $height == -1) {
                $width  = $img_width;
                $height = $img_height;
            } elseif ($width == -1) {
                $width = (int) round($img_width * ($height / $img_height));
            } elseif ($height == -1) {
                $height = (int) round($img_height * ($width / $img_width));
            }

            $new_image = imagecreatetruecolor($width, $height);
            imagecopyresampled($new_image, $image, 0, 0, 0, 0, $width, $height, $img_width, $img_height);
            imagedestroy($image);
            wp_delete_file($wp_upload_dir['path'] . '/' . $temp_name);

            switch ($output_image_type) {
                case IMAGETYPE_JPEG:
                    $result = imagejpeg($new_image, $image_path, intval($compression));
                    break;
                case IMAGETYPE_GIF:
                    $result = imagegif($new_image, $image_path);
                    break;
                case IMAGETYPE_PNG:
                    $result = imagepng($new_image, $image_path);
                    break;
                case IMAGETYPE_BMP:
                    $result = imagebmp($new_image, $image_path);
                    break;
                case IMAGETYPE_WBMP:
                    $result = imagewbmp($new_image, $image_path);
                    break;
                case IMAGETYPE_WEBP:
                    $result = imagewebp($new_image, $image_path);
                    break;
                case IMAGETYPE_XBM:
                    $result = imagexbm($new_image, $image_path);
                    break;
                default:
                    $result = false;
            }
            imagedestroy($new_image);

            if ($result) {
                $default_image_path = $wp_upload_dir['path'] . '/' . $default_file_name;
                if ($default_file_name != $file_name) {
                    if (rss_retrieval_compare_files($default_image_path, $image_path)) {
                        if (wp_delete_file($image_path)) {
                            $local_image_url = $wp_upload_dir['url'] . '/' . $default_file_name;
                        }
                    }
                }

                $rssrtvr_lite->log('Done. Local image URL: ' . $local_image_url);
                return $rssrtvr_lite->convert_image($local_image_url);
            } else {
                $rssrtvr_lite->log('Failed to convert the source file. The image will be hotlinked');
                return $image_url;
            }
        } else {
            imagedestroy($image);
            global $wp_filesystem;
            if (!$wp_filesystem) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }
            if ($wp_filesystem->move($wp_upload_dir['path'] . '/' . $temp_name, $image_path, true)) {
                $rssrtvr_lite->log('Done. Local image URL: ' . $local_image_url);
                return $rssrtvr_lite->convert_image($local_image_url);
            }
        }
    }
    wp_delete_file($wp_upload_dir['path'] . '/' . $temp_name);
    $rssrtvr_lite->log('The source file is not recognized: ' . $image_url);
    $rssrtvr_lite->log('The image will be hotlinked');
    return $image_url;
}

function rss_retrieval_disable_kses() {
    global $rssrtvr_lite;
    if (($rssrtvr_lite->current_feed['options']['sanitize'] ?? '') !== 'on') {
        remove_filter('content_save_pre', 'wp_filter_post_kses');
        remove_filter('content_filtered_save_pre', 'wp_filter_post_kses');
        remove_filter('excerpt_filtered_save_pre', 'wp_filter_post_kses');
    }
}

function rss_retrieval_enable_kses() {
    global $rssrtvr_lite;
    if (($rssrtvr_lite->current_feed['options']['sanitize'] ?? '') !== 'on') {
        add_filter('content_save_pre', 'wp_filter_post_kses');
        add_filter('content_filtered_save_pre', 'wp_filter_post_kses');
        add_filter('excerpt_save_pre', 'wp_filter_post_kses');
    }
}

function rss_retrieval_add_image_to_library($image_url, $title = '', $post_id = false) {
    if (! is_string($image_url)) {
        return false;
    }
    $title = trim($title);
    if ($post_id == false) {
        global $rss_retrieval_images_to_attach;
        $rss_retrieval_images_to_attach[] = [
            'url'   => $image_url,
            'title' => $title,
        ];
    } else {
        $upload_dir = wp_upload_dir();
        if (! file_exists($upload_dir['path'] . '/' . basename($image_url))) {
            $image_url = rss_retrieval_save_image($image_url, $title);
        }
        $img_path = str_replace($upload_dir['url'], $upload_dir['path'], $image_url);
        if (file_exists($img_path) && filesize($img_path)) {
            $wp_filetype = wp_check_filetype($upload_dir['path'] . basename($image_url), null);
            $attachment  = [
                'post_mime_type' => $wp_filetype['type'],
                'post_title'     => preg_replace('/\.[^.]+$/', '', $title),
                'post_content'   => '',
                'post_parent'    => $post_id,
                'post_status'    => 'inherit',
            ];
            $attach_id   = wp_insert_attachment($attachment, $upload_dir['path'] . '/' . basename($image_url), $post_id);
            rss_retrieval_disable_kses();
            wp_update_post(
                [
                    'ID'          => $attach_id,
                    'post_parent' => $post_id,
                ]
            );
            update_post_meta($attach_id, '_wp_attachment_image_alt', $title);
            rss_retrieval_enable_kses();
            if (! function_exists('wp_generate_attachment_metadata')) {
                require_once ABSPATH . 'wp-admin/includes/image.php';
            }
            if (! function_exists('media_handle_upload')) {
                require_once ABSPATH . 'wp-admin/includes/media.php';
            }
            $attach_data = wp_generate_attachment_metadata($attach_id, $upload_dir['path'] . '/' . basename($image_url));
            wp_update_attachment_metadata($attach_id, $attach_data);
            return $attach_id;
        }
    }
    return false;
}
function rss_retrieval_unzip($content) {
    $wp_upload_dir = wp_upload_dir();
    // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
    $tempfile      = $wp_upload_dir['path'] . '/' . esc_html(wp_unique_filename($wp_upload_dir['path'], 'zip-' . date('Y-m-d-H-i')) . '.tmp');
    $success       = file_put_contents($tempfile, $content, LOCK_EX);
    if (! $success) {
        return $content;
    }

    $zip = new ZipArchive();
    if ($zip->open($tempfile) !== true) {
        wp_delete_file($tempfile);
        return $content;
    }

    $result = '';
    if ($zip->numFiles > 0) {
        $result = $zip->getFromIndex(0);
    }

    $zip->close();
    wp_delete_file($tempfile);
    return $result;
}

function rss_retrieval_pack_conetnt($post, $extended = false) {
    $packed_content = trim($post['post_title']) . "\n\n" . rss_retrieval_BLOCK_DIVIDER . "\n\n" . trim($post['post_content']) . "\n\n" . rss_retrieval_BLOCK_DIVIDER;

    if ($extended) {
        $packed_content .= "\n\n" . trim($post['post_excerpt']) . "\n\n" . rss_retrieval_BLOCK_DIVIDER . "\n\n" . implode(',', $post['categories']) . "\n\n" . rss_retrieval_BLOCK_DIVIDER . "\n\n" . implode(',', $post['tags_input']) . "\n\n" . rss_retrieval_BLOCK_DIVIDER;
    }
    return $packed_content;
}

function rss_retrieval_unpack_content($post, $packed_content) {
    $parts = explode(rss_retrieval_BLOCK_DIVIDER, $packed_content);

    if ('' !== trim($parts[0] ?? '')) {
        $post['post_title'] = rss_retrieval_strip_tags($parts[0]);
    }

    if ('' !== trim($parts[1] ?? '')) {
        $post['post_content'] = trim($parts[1]);
    }

    if ('' !== trim($parts[2] ?? '')) {
        $post['post_excerpt'] = trim($parts[2]);
    }

    if ('' !== trim($parts[3] ?? '')) {
        $post['categories'] = explode(',', $parts[3]);
    }

    if ('' !== trim($parts[4] ?? '')) {
        $post['tags_input'] = explode(',', $parts[4]);
    }
    return $post;
}

function rss_retrieval_shorten_string_by_words($string, $numWords) {
    $parts = preg_split('/(\s+)/', $string, -1, PREG_SPLIT_DELIM_CAPTURE);

    $words           = 0;
    $shortenedString = '';

    foreach ($parts as $index => $part) {
        $shortenedString .= $part;
        if (! ctype_space($part) && $index % 2 == 0) {
            ++$words;
        }

        if ($words == $numWords) {
            break;
        }
    }

    if ($index % 2 == 0 && ctype_punct(substr($shortenedString, -1))) {
        $shortenedString = substr($shortenedString, 0, -1);
    }

    if (trim($shortenedString) !== trim($string)) {
        $shortenedString .= '...';
    }

    return $shortenedString;
}

function rss_retrieval_get_youtube_video($keyword) {
    global $rssrtvr_lite;
    $rssrtvr_lite->log('Search YouTube for "' . $keyword . '"');
    $res = rss_retrieval_file_get_contents('https://www.youtube.com/results?search_query=' . urlencode($keyword) . '&sp=EgIYAw%253D%253D', false, '', 'self', rss_retrieval_CURL_USER_AGENT);

    preg_match_all('/"\/watch\?v=([a-zA-Z0-9_-]{11})[\\\u0026]?/', $res, $matches);
    $items = $matches[1];
    foreach ($items as $item) {
        $page = rss_retrieval_file_get_contents('https://www.youtube.com/watch?v=' . $item);
        if (strpos($page, '"playableInEmbed":true') !== false && strpos($page, '"isShorts":true') === false) {
            $rssrtvr_lite->log('Done');
            return '<div class="video-container"><iframe width="560" height="315" src="https://www.youtube.com/embed/' . $item . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>';
        }
    }
    $rssrtvr_lite->log('The requested video was not found');
    return '';
}

function rss_retrieval_options_menu() {
    if (isset($_POST['submit_options']) && check_admin_referer('rss_retrieval_general_settings')) {
        update_option(rss_retrieval_MAX_EXEC_TIME, abs(intval($_POST[rss_retrieval_MAX_EXEC_TIME])));

        if (intval($_POST[rss_retrieval_PC_INTERVAL] ?? 0) * 60 >= get_option(rss_retrieval_MAX_EXEC_TIME)) {
            $pseudo_cron_interval = intval($_POST[rss_retrieval_PC_INTERVAL]);
        } else {
            $pseudo_cron_interval = max(2 * rss_retrieval_MIN_UPDATE_TIME, round(get_option(rss_retrieval_MAX_EXEC_TIME) / 60));
        }

        if (update_option(rss_retrieval_RSS_PULL_MODE, rss_retrieval_sanitize_user_input(wp_unslash($_POST[rss_retrieval_RSS_PULL_MODE])))) {
            wp_clear_scheduled_hook('rss_retrieval_update_by_wp_cron');
        }

        if (update_option(rss_retrieval_PC_INTERVAL, $pseudo_cron_interval)) {
            wp_clear_scheduled_hook('rss_retrieval_update_by_wp_cron');
        }

        update_option(rss_retrieval_KEEP_IMAGES, isset($_POST[rss_retrieval_KEEP_IMAGES]) ? 'on' : '');
        echo '<div id="message" class="notice updated"><p><strong>Settings saved.</strong></p></div>';
    }
?>
    <div class="wrap">
        <h2><?php esc_html_e('Settings', 'rss-retriever-lite'); ?></h2>
        <div class="metabox-holder postbox-container">
            <form method="post" action="<?php echo esc_url(rss_retrieval_REQUEST_URI()); ?>" name="general_settings">
                <div class="section" style="display:block">
                    <table class="form-table">
                        <tr>
                            <th scope="row">RSS pull mode</th>
                            <td>
                                <select
                                    id="rssrtvr-pull-mode"
                                    class="rssrtvr-lite-pull-mode"
                                    name="<?php echo esc_attr(rss_retrieval_RSS_PULL_MODE); ?>">
                                    <?php
                                    echo '<option ' . ((get_option(rss_retrieval_RSS_PULL_MODE) === 'auto') ? 'selected ' : '') . 'value="auto">Auto</option>';
                                    echo '<option ' . ((get_option(rss_retrieval_RSS_PULL_MODE) === 'cron') ? 'selected ' : '') . 'value="cron">Cron job or manually</option>';
                                    ?>
                                </select>
                                <p id="auto" class="description" style="display:none;">
                                    In this mode, the RSS Retriever Lite plugin uses WordPress pseudo cron, which will be executed by the WordPress every
                                    <input type="number" min="<?php echo esc_attr(rss_retrieval_MIN_UPDATE_TIME); ?>" size="4"
                                        name="<?php echo esc_attr(rss_retrieval_PC_INTERVAL); ?>"
                                        value="<?php echo esc_attr(get_option(rss_retrieval_PC_INTERVAL)); ?>"> minutes.<br>
                                    The pseudo cron will trigger when someone visits your WordPress site, if the scheduled time has passed.
                                </p>
                                <p id="cron" class="description" style="display:none;">
                                    In this mode, you need to manually configure cron at your host. For example, if you want to run a cron job once a hour, just add the following line into your crontab:<br>
                                    <code><?php echo esc_html('0 * * * * /usr/bin/curl --silent ' . esc_html(get_option('siteurl')) . '/?pull-feeds=' . esc_attr(get_option(rss_retrieval_CRON_MAGIC))); ?></code>
                                    <?php
                                    if (defined('WP_CACHE') && WP_CACHE) {
                                        echo '<br>&#x26A0; It seems you are using some caching plugin. Make sure you add the URL from the above cronjob to the exception list of your caching plugin.';
                                    }
                                    ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Max execution time</th>
                            <td>
                                <input type="number" min="0" name="<?php echo esc_attr(rss_retrieval_MAX_EXEC_TIME); ?>" size="5" value="<?php echo esc_attr(get_option(rss_retrieval_MAX_EXEC_TIME)); ?>">
                                <p class="description">Maximum PHP execution time, given to RSS Retriever Lite to execute all operations. If set to zero, no time limit is imposed.</p>
                            </td>
                        </tr>
                    </table>
                    <?php wp_nonce_field('rss_retrieval_general_settings'); ?>
                    <br>
                    <div style="text-align:left;">
                        <input type="submit" name="submit_options" class="button-primary"
                            value="<?php esc_attr_e('Update options', 'rss-retriever-lite'); ?>" />
                    </div>
                </div>
            </form>
        </div>
    </div>
<?php
}

function rss_retrieval_period_select($p, $n) {
    echo '<select name="' . esc_html(esc_attr($n)) . '" style="vertical-align: top;">';
    foreach (
        [
            '3600'    => 'a hour',
            '86400'   => 'a day',
            '2678400' => 'a month',
        ] as $v => $o
    ) {
        echo '<option ' . selected(intval($p), intval($v), false) . ' value="' . esc_html(esc_attr($v)) . '">' . esc_html(esc_html($o)) . '</option>';
    }
    echo '</select>';
}

function rss_retrieval_accounts_menu() {
    $accounts = get_option(rss_retrieval_ACCOUNTS);

    if (isset($_POST['modify_accounts']) && check_admin_referer('rss_retrieval_accounts')) {

        $lite_api_keys = [
            'deepl_api_key',
            'yandex_api_key',
            'google_api_key',
        ];

        foreach ($lite_api_keys as $key) {
            if (isset($_POST[$key])) {
                $accounts[$key] = trim(rss_retrieval_sanitize_user_input($_POST[$key]));
            }
        }

        $accounts['deepl_api_limit']['max_requests']  = abs(intval($_POST['deepl_api_limit'] ?? $accounts['deepl_api_limit']['max_requests']));
        $accounts['google_api_limit']['max_requests'] = abs(intval($_POST['google_api_limit'] ?? $accounts['google_api_limit']['max_requests']));
        $accounts['yandex_api_limit']['max_requests'] = abs(intval($_POST['yandex_api_limit'] ?? $accounts['yandex_api_limit']['max_requests']));
        $accounts['deepl_api_limit']['period']        = intval($_POST['deepl_api_limit_period'] ?? $accounts['deepl_api_limit']['period']);
        $accounts['google_api_limit']['period']       = intval($_POST['google_api_limit_period'] ?? $accounts['google_api_limit']['period']);
        $accounts['yandex_api_limit']['period']       = intval($_POST['yandex_api_limit_period'] ?? $accounts['yandex_api_limit']['period']);

        update_option(rss_retrieval_ACCOUNTS, $accounts);
    }
?>
    <div class="wrap">
        <h2><?php esc_html_e('Accounts', 'rss-retriever-lite'); ?></h2>
        <br>
        <form method="post" action="<?php echo esc_url(rss_retrieval_REQUEST_URI()); ?>">

            <div id="third_party">
                <table class="form-table">
                    <tr>
                        <td colspan="2">
                            <table class="rssrtvr-box">

                                <tr>
                                    <th>DeepL API key</th>
                                    <td>
                                        <input type="text" style="width: 100%;" name="deepl_api_key" size="80" value="<?php echo esc_attr(esc_attr(stripslashes($accounts['deepl_api_key']))); ?>">
                                        <p class="description">Enter your API key above in order to use DeepL Translator. If you don't have one, get it <a href="https://www.deepl.com/pro.html" target="_blank">here</a>.</p>
                                    </td>
                                </tr>

                                <tr>
                                    <th>DeepL request limit</th>
                                    <td>
                                        <input type="number" min="0" name="deepl_api_limit" size="6" value="<?php echo esc_attr(esc_attr(stripslashes($accounts['deepl_api_limit']['max_requests']))); ?>">
                                        <?php
                                        rss_retrieval_period_select($accounts['deepl_api_limit']['period'], 'deepl_api_limit_period');
                                        ?>
                                        <p class="description">Set the limit for DeepL API requests. A value of <code>0</code> is interpreted as no limit.</p>
                                    </td>
                                </tr>

                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td colspan="2">
                            <table class="rssrtvr-box">

                                <tr>
                                    <th>Google Translate API key</th>
                                    <td>
                                        <input type="text" style="width: 100%;" name="google_api_key" size="80" value="<?php echo esc_attr(stripslashes($accounts['google_api_key'])); ?>">
                                        <p class="description">Enter your API key above in order to use Google Translate. If you don't have one, get it <a href="https://cloud.google.com/translate/docs/getting-started" target="_blank">here</a>.</p>
                                    </td>
                                </tr>

                                <tr>
                                    <th>Google Translate request limit</th>
                                    <td>
                                        <input type="number" min="0" name="google_api_limit" size="6" value="<?php echo esc_attr(stripslashes($accounts['google_api_limit']['max_requests'])); ?>">
                                        <?php
                                        rss_retrieval_period_select($accounts['google_api_limit']['period'], 'google_api_limit_period');
                                        ?>
                                        <p class="description">Set the limit for Google Translate API requests. A value of <code>0</code> is interpreted as no limit.</p>
                                    </td>
                                </tr>

                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td colspan="2">
                            <table class="rssrtvr-box">

                                <tr>
                                    <th>Yandex Translate API key</th>
                                    <td>
                                        <input type="text" style="width: 100%;" name="yandex_api_key" size="80" value="<?php echo esc_attr(stripslashes($accounts['yandex_api_key'])); ?>">
                                        <p class="description">Enter your API key above in order to use Yandex Translate. If you don't have one, get it
                                            <a href="https://cloud.yandex.com/en/docs/iam/operations/api-key/create" target="_blank">here</a> or <a href="https://translate.yandex.com/developers/keys" target="_blank">here</a>.
                                        </p>
                                        <p class="description">Both Yandex Translate API v1.5 and v2 keys are supported.</p>
                                    </td>
                                </tr>

                                <tr>
                                    <th>Yandex Translate request limit</th>
                                    <td>
                                        <input type="number" min="0" name="yandex_api_limit" size="6" value="<?php echo esc_attr(stripslashes($accounts['yandex_api_limit']['max_requests'])); ?>">
                                        <?php
                                        rss_retrieval_period_select($accounts['yandex_api_limit']['period'], 'yandex_api_limit_period');
                                        ?>
                                        <p class="description">Set the limit for Yandex Translate API requests. A value of <code>0</code> is interpreted as no limit.</p>
                                    </td>
                                </tr>

                            </table>
                        </td>
                    </tr>
                </table>
            </div>

            <?php wp_nonce_field('rss_retrieval_accounts'); ?>
            <br>
            <div style="text-align:left;">
                <input type="submit" name="modify_accounts" class="button-primary"
                    value="<?php esc_attr_e('Update settings', 'rss-retriever-lite'); ?>" />&nbsp;&nbsp;
                <input type="button" name="cancel"
                    value="<?php esc_attr_e('Cancel', 'rss-retriever-lite'); ?>"
                    class="button" onclick="javascript:history.go(-1)" />
            </div>
        </form>
    </div>
<?php
}

function rss_retrieval_sanitize_user_input($input, $default = '') {
    if (is_array($default)) {
        return is_array($input) ? array_map('sanitize_text_field', $input) : [];
    }

    if (is_int($default)) {
        return absint($input);
    }

    if (is_bool($default) || $default === 0 || $default === 1) {
        return (isset($input) && $input === 'on');
    }

    if (current_user_can('unfiltered_html')) {
        return $input;
    }

    return wp_kses_post($input);
}

function rss_retrieval_set_feed_options($options) {
    $result = [];
    foreach ($options as $option => $value) {
        if (isset($_POST[$option])) {
            $input = wp_unslash($_POST[$option]);
            if ($option === 'date_min' || $option === 'date_max') {
                $result[$option] = intval($input);
            } elseif (is_array($value)) {
                $result[$option] = is_array($input) ? array_map('sanitize_text_field', $input) : [];
            } elseif (is_int($value)) {
                $result[$option] = intval($input);
            } else {
                $result[$option] = wp_unslash($input);
            }
        } else {
            if (is_array($value)) {
                $result[$option] = [];
            } elseif (is_int($value)) {
                $result[$option] = 0;
            } else {
                $result[$option] = '';
            }
        }
    }

    if ($result['date_min'] > $result['date_max']) {
        $min = $result['date_min'];
        $result['date_min'] = $result['date_max'];
        $result['date_max'] = $min;
    }

    if (!empty($result['interval'])) {
        $result['interval'] = max(rss_retrieval_MIN_UPDATE_TIME, intval($result['interval']));
    }

    if (isset($result['delay'])) {
        $result['delay'] = abs(intval($result['delay']));
    }

    return $result;
}

function rss_retrieval_xml_syndicator_menu() {
    global $rssrtvr_lite, $wpdb;
?>
    <div class="wrap">
        <?php if (isset($_POST['modify_selected_feeds'])) { ?>
            <h2><?php esc_html_e('RSS Retriever Lite Syndicator - Mass Modify Selected Feeds', 'rss-retriever-lite'); ?></h2>
            <table class="widefat" style="margin: 8pt 0 8pt 0;">
                <tr>
                    <td>
                        <p>&#x1f4a1; Use the red check box to the left of each feed option you want to apply it to all selected feeds. Unchecked options will not be applied.</p>
                    </td>
                </tr>
            </table>
        <?php
        } elseif (isset($_POST['alter_default_settings'])) {
        ?>
            <h2><?php esc_html_e('RSS Retriever Lite Syndicator - Default Settings', 'rss-retriever-lite'); ?></h2>
            <table class="widefat" style="margin: 8pt 0 8pt 0;">
                <tr>
                    <td>
                        <p>&#x1f4a1; These settings will be suggested each time you add a new feed.</p>
                    </td>
                </tr>
            </table>
        <?php
        } else {
            echo '<h2>' . esc_html(esc_html__('RSS Retriever Lite', 'rss-retriever-lite')) . '</h2>';
        }
        ?>

        <?php
        if (
            isset($_GET['edit-feed-id']) && isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'rss_retrieval_xml_syndicator')
        ) {
            $feed_id = absint($_GET['edit-feed-id']);
            if (isset($rssrtvr_lite->feeds[$feed_id]['options'])) {
                $rssrtvr_lite->current_feed['options'] = $rssrtvr_lite->feeds[$feed_id]['options'];
                $source = rss_retrieval_fixurl($rssrtvr_lite->feeds[$feed_id]['url']);
                $rssrtvr_lite->feedPreview($source, true);
                $rssrtvr_lite->showSettings(true, $rssrtvr_lite->feeds[$feed_id]['options']);
            }
        } elseif (isset($_POST['update_feed_settings']) && check_admin_referer('rss_retrieval_xml_syndicator')) {

            if (trim($_POST['feed_title']) === '') {
                $_POST['feed_title'] = 'no name';
            }
            $rssrtvr_lite->feeds[(int) $_POST['feed_id']]['title'] = trim(stripslashes(esc_attr($_POST['feed_title'], ENT_NOQUOTES)));

            if (isset($_POST['url'])) {
                $new_url = rss_retrieval_sanitize_user_input($_POST['url']);
                $old_url = $rssrtvr_lite->feeds[(int) $_POST['feed_id']]['url'];

                if (stripos($new_url, 'http') === 0 && $new_url !== $old_url) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                    $wpdb->query(
                        $wpdb->prepare(
                            "UPDATE {$wpdb->prefix}postmeta 
                            SET meta_value = %s 
                            WHERE meta_key = '_rssrtvr_rss_source' 
                            AND meta_value = %s",
                            $new_url,
                            $old_url
                        )
                    );
                    $rssrtvr_lite->feeds[(int) $_POST['feed_id']]['url'] = $new_url;
                }
            }

            $rssrtvr_lite->feeds[(int) $_POST['feed_id']]['options']['interval'] = abs((int) $_POST['interval']);
            $rssrtvr_lite->feeds[(int) $_POST['feed_id']]['options'] = rss_retrieval_set_feed_options($rssrtvr_lite->feeds[(int) $_POST['feed_id']]['options']);
            update_option(rss_retrieval_SYNDICATED_FEEDS, $rssrtvr_lite->feeds);
            $rssrtvr_lite->showMainPage(false);
        } elseif (isset($_POST['check_for_updates']) && check_admin_referer('rss_retrieval_xml_syndicator')) {
            $rssrtvr_lite->show_report = true;
            if (isset($_POST['feed_ids'])) {
                $rssrtvr_lite->syndicateFeeds(array_map('absint', (array) $_POST['feed_ids']), false);
            } else {
                echo '<div id="message" class="notice updated"><p><strong>No feeds selected.</strong></p></div>';
            }
            $rssrtvr_lite->showMainPage(false);
        } elseif (isset($_POST['apply_settings_to_selected_feeds']) && check_admin_referer('rss_retrieval_xml_syndicator')) {
            $new_options = array_map('sanitize_text_field', wp_unslash($_POST));

            $rssrtvr_lite->modifyFeeds($new_options);
            $rssrtvr_lite->showMainPage(false);
        } elseif (isset($_POST['delete_feeds']) && isset($_POST['feed_ids']) && check_admin_referer('rss_retrieval_xml_syndicator')) {
            $rssrtvr_lite->deleteFeeds(array_map('absint', (array) $_POST['feed_ids']), false, true);
            $rssrtvr_lite->showMainPage(false);
        } elseif (isset($_POST['shuffle_update_time']) && isset($_POST['feed_ids']) && check_admin_referer('rss_retrieval_xml_syndicator')) {
            $rssrtvr_lite->shuffleUpdateTimes(array_map('absint', (array) $_POST['feed_ids']));
            $rssrtvr_lite->showMainPage(false);
        } elseif (isset($_POST['delete_posts']) && isset($_POST['feed_ids']) && check_admin_referer('rss_retrieval_xml_syndicator')) {
            $rssrtvr_lite->deleteFeeds(array_map('absint', (array) $_POST['feed_ids']), true, false);
            $rssrtvr_lite->showMainPage(false);
        } elseif (isset($_POST['delete_feeds_and_posts']) && isset($_POST['feed_ids']) && check_admin_referer('rss_retrieval_xml_syndicator')) {
            $rssrtvr_lite->deleteFeeds(array_map('absint', (array) $_POST['feed_ids']), true, true);
            $rssrtvr_lite->showMainPage(false);
        } elseif (isset($_POST['new_feed']) && check_admin_referer('rss_retrieval_xml_syndicator')) {
            $source                               = rss_retrieval_sanitize_user_input($_POST['feed_url']);
            $rssrtvr_lite->current_feed['options'] = $rssrtvr_lite->global_options;

            if (! empty($_POST['user_agent'])) {
                $rssrtvr_lite->current_feed['options']['user_agent'] = rss_retrieval_sanitize_user_input($_POST['user_agent']);
            }

            if (! empty($_POST['http_headers'])) {
                $rssrtvr_lite->current_feed['options']['http_headers'] = rss_retrieval_sanitize_user_input($_POST['http_headers']);
            }

            if (strlen(trim($source)) && $rssrtvr_lite->feedPreview(rss_retrieval_fixurl($source), false)) {
                $rssrtvr_lite->current_feed['options']['undefined_category'] = 'use_global';
                $rssrtvr_lite->showSettings(true, $rssrtvr_lite->current_feed['options']);
            } else {
                $rssrtvr_lite->showMainPage(false);
            }
        } elseif (isset($_POST['syndicate_feed']) && check_admin_referer('rss_retrieval_xml_syndicator')) {
            if (intval($_POST['date_min']) > intval($_POST['date_max'])) {
                $_POST['date_min'] = $_POST['date_max'];
            }

            if (trim($_POST['feed_title']) === '') {
                $_POST['feed_title'] = 'no name';
            }

            $interval = abs((int) $_POST['interval']);
            if ($interval) {
                $interval = max(rss_retrieval_MIN_UPDATE_TIME, $interval);
            }

            $feed                                  = [];
            $feed['url']                           = rss_retrieval_sanitize_user_input($_POST['feed_url']);
            $feed['title']                         = trim(stripslashes(esc_attr(rss_retrieval_sanitize_user_input($_POST['feed_title']), ENT_NOQUOTES)));
            $feed['updated']                       = 0;
            $feed['options']['interval']           = $interval;
            $feed['options']                       = rss_retrieval_set_feed_options($rssrtvr_lite->global_options);
            $id                                    = array_push($rssrtvr_lite->feeds, $feed);
            $rssrtvr_lite->feeds_updated[$id - 1] = 0;
            update_option(rss_retrieval_SYNDICATED_FEEDS, $rssrtvr_lite->feeds);
            $rssrtvr_lite->showMainPage(false);
        } elseif (isset($_POST['update_default_settings']) && check_admin_referer('rss_retrieval_xml_syndicator')) {
            if (intval($_POST['date_min']) > intval($_POST['date_max'])) {
                $_POST['date_min'] = $_POST['date_max'];
            }
            $rssrtvr_lite->global_options['interval'] = abs((int) $_POST['interval']);
            $rssrtvr_lite->global_options             = rss_retrieval_set_feed_options($rssrtvr_lite->global_options);
            update_option(rss_retrieval_FEED_OPTIONS, $rssrtvr_lite->global_options);
            $rssrtvr_lite->showMainPage(false);
        } elseif (isset($_POST['alter_default_settings']) && check_admin_referer('rss_retrieval_xml_syndicator')) {
            $rssrtvr_lite->showSettings(false, $rssrtvr_lite->global_options);
        } elseif (isset($_POST['modify_selected_feeds']) && check_admin_referer('rss_retrieval_xml_syndicator')) {
            if (isset($_POST['feed_ids'])) {
                $rssrtvr_lite->showSettings(false, $rssrtvr_lite->global_options, true);
            } else {
                echo '<div id="message" class="notice updated"><p><strong>No feeds selected.</strong></p></div>';
                $rssrtvr_lite->showMainPage(false);
            }
        } else {
            $rssrtvr_lite->showMainPage(false);
        }
        ?>
    </div>
<?php
}

function rss_retrieval_syndicator_log_menu() {
?>
    <div class="wrap">
        <h2><?php esc_html_e('Syndicator Log', 'rss-retriever-lite'); ?></h2>
        <br>
        <textarea readonly id="rssrtvr-lite-log" cols="100" rows="20" wrap="on" style="margin:0;height:30em;width:100%;background-color:white"><?php echo esc_textarea(get_option(rss_retrieval_LOG)); ?></textarea>
        <p>
            <a href="#rssrtvr-lite-log" class="rssrtvr-button rssrtvr-lite-copy">
                <?php esc_html_e('Copy to clipboard', 'rss-retriever-lite'); ?>
            </a>
        </p>
    </div>
    <?php
}

function rss_retrieval_plugins_action_link($links) {
    $links[] = '<a href="' . esc_url(get_admin_url(null, 'admin.php?page=rssretriever_lite')) . '">Syndicator</a>';
    return $links;
}

function rss_retrieval_add_admin_menu_item($admin_bar) {
    $args = [
        'id'     => 'new-cybsrseo-feed-admin-menu-item',
        'title'  => 'RSS Retriever Lite Feed',
        'href'   => esc_url(get_admin_url(null, 'admin.php?page=rssretriever')),
        'parent' => 'new-content',
    ];
    $admin_bar->add_menu($args);
}
class rss_retrieval_Syndicator {
    public $langs;
    public $post = [];
    public $link_checked;
    public $insideitem;
    public $parents               = [];
    public $new_tag               = false;
    public $polylang_translations = [];
    public $wpml_translations     = [];
    public $element_tag;
    public $tag;
    public $count;
    public $failure;
    public $skip;
    public $xml_tags;
    public $posts_found;
    public $max;
    public $current_feed     = [];
    public $current_feed_url = '';
    public $feeds            = [];
    public $feeds_updated    = [];
    public $update_period;
    public $feed_title;
    public $blog_charset;
    public $feed_charset;
    public $feed_charset_convert;
    public $preview;
    public $global_options = [];
    public $edit_existing;
    public $current_category;
    public $current_custom_field;
    public $current_custom_field_attr = [];
    public $generator;
    public $xml_parse_error;
    public $show_report = false;
    public $document_type;
    public $parse_feed_log = '';
    public $image_urls     = [];

    function __construct() {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $option_value = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = %s LIMIT 1", rss_retrieval_FEEDS_UPDATED));
        if ($option_value) {
            $this->feeds_updated = maybe_unserialize($option_value);
        }

        $this->blog_charset   = strtoupper(get_option('blog_charset'));
        $this->global_options = $this->init_feed_options(get_option(rss_retrieval_FEED_OPTIONS));
        update_option(rss_retrieval_FEED_OPTIONS, $this->global_options);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $option_value = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = %s LIMIT 1", rss_retrieval_SYNDICATED_FEEDS));
        if ($option_value) {
            $this->feeds = maybe_unserialize($option_value);
        }

        if (! empty(array_filter($this->feeds))) {
            for ($i = 0; $i < count($this->feeds); $i++) {
                $this->feeds[$i]['options'] = $this->init_feed_options($this->feeds[$i]['options']);
            }
            update_option(rss_retrieval_SYNDICATED_FEEDS, $this->feeds);
        }

        $this->langs = [
            'YANDEX_TRANSLATE_LANGS' => [
                'hy-ru' => '&#x1F1E6;&#x1F1F2; Armenian - &#x1F1F7;&#x1F1FA; Russian',
                'az-ru' => '&#x1F1E6;&#x1F1FF; Azerbaijani - &#x1F1F7;&#x1F1FA; Russian',
                'be-bg' => '&#x1F1E7;&#x1F1FE; Belarusian - &#x1F1E7;&#x1F1EC; Bulgarian',
                'be-cs' => '&#x1F1E7;&#x1F1FE; Belarusian - &#x1F1E8;&#x1F1FF; Czech',
                'be-de' => '&#x1F1E7;&#x1F1FE; Belarusian - &#x1F1E9;&#x1F1EA; German',
                'be-en' => '&#x1F1E7;&#x1F1FE; Belarusian - &#x1F1EC;&#x1F1E7; English',
                'be-es' => '&#x1F1E7;&#x1F1FE; Belarusian - &#x1F1EA;&#x1F1F8; Spanish',
                'be-fr' => '&#x1F1E7;&#x1F1FE; Belarusian - &#x1F1EB;&#x1F1F7; French',
                'be-it' => '&#x1F1E7;&#x1F1FE; Belarusian - &#x1F1EE;&#x1F1F9; Italian',
                'be-pl' => '&#x1F1E7;&#x1F1FE; Belarusian - &#x1F1F5;&#x1F1F1; Polish',
                'be-ro' => '&#x1F1E7;&#x1F1FE; Belarusian - &#x1F1F7;&#x1F1F4; Romanian',
                'be-ru' => '&#x1F1E7;&#x1F1FE; Belarusian - &#x1F1F7;&#x1F1FA; Russian',
                'be-sr' => '&#x1F1E7;&#x1F1FE; Belarusian - &#x1F1F7;&#x1F1F8; Serbian',
                'be-tr' => '&#x1F1E7;&#x1F1FE; Belarusian - &#x1F1F9;&#x1F1F7; Turkish',
                'bg-be' => '&#x1F1E7;&#x1F1EC; Bulgarian - &#x1F1E7;&#x1F1FE; Belarusian',
                'bg-ru' => '&#x1F1E7;&#x1F1EC; Bulgarian - &#x1F1F7;&#x1F1FA; Russian',
                'bg-uk' => '&#x1F1E7;&#x1F1EC; Bulgarian - &#x1F1FA;&#x1F1E6; Ukrainian',
                'ca-en' => '&#x1F1E8;&#x1F1E6; Catalan - &#x1F1EC;&#x1F1E7; English',
                'ca-ru' => '&#x1F1E8;&#x1F1E6; Catalan - &#x1F1F7;&#x1F1FA; Russian',
                'zh-de' => '&#x1F1E8;&#x1F1F3; Chinese - &#x1F1E9;&#x1F1EA; German',
                'zh-en' => '&#x1F1E8;&#x1F1F3; Chinese - &#x1F1EC;&#x1F1E7; English',
                'zh-fr' => '&#x1F1E8;&#x1F1F3; Chinese - &#x1F1EB;&#x1F1F7; French',
                'zh-ja' => '&#x1F1E8;&#x1F1F3; Chinese - &#x1F1EF;&#x1F1F5; Japanese',
                'zh-it' => '&#x1F1E8;&#x1F1F3; Chinese - &#x1F1EE;&#x1F1F9; Italian',
                'zh-ru' => '&#x1F1E8;&#x1F1F3; Chinese - &#x1F1F7;&#x1F1FA; Russian',
                'zh-es' => '&#x1F1E8;&#x1F1F3; Chinese - &#x1F1EA;&#x1F1F8; Spanish',
                'cs-be' => '&#x1F1E8;&#x1F1FF; Czech - &#x1F1E7;&#x1F1FE; Belarusian',
                'cs-en' => '&#x1F1E8;&#x1F1FF; Czech - &#x1F1EC;&#x1F1E7; English',
                'cs-ru' => '&#x1F1E8;&#x1F1FF; Czech - &#x1F1F7;&#x1F1FA; Russian',
                'cs-uk' => '&#x1F1E8;&#x1F1FF; Czech - &#x1F1FA;&#x1F1E6; Ukrainian',
                'da-en' => '&#x1F1E9;&#x1F1F0; Danish - &#x1F1EC;&#x1F1E7; English',
                'da-ru' => '&#x1F1E9;&#x1F1F0; Danish - &#x1F1F7;&#x1F1FA; Russian',
                'en-be' => '&#x1F1EC;&#x1F1E7; English - &#x1F1E7;&#x1F1FE; Belarusian',
                'en-bn' => '&#x1F1EC;&#x1F1E7; English - &#x1F1E7;&#x1F1E9; Bengali',
                'en-ca' => '&#x1F1EC;&#x1F1E7; English - &#x1F1E8;&#x1F1E6; Catalan',
                'en-zh' => '&#x1F1EC;&#x1F1E7; English - &#x1F1E8;&#x1F1F3; Chinese',
                'en-cs' => '&#x1F1EC;&#x1F1E7; English - &#x1F1E8;&#x1F1FF; Czech',
                'en-da' => '&#x1F1EC;&#x1F1E7; English - &#x1F1E9;&#x1F1F0; Danish',
                'en-de' => '&#x1F1EC;&#x1F1E7; English - &#x1F1E9;&#x1F1EA; German',
                'en-el' => '&#x1F1EC;&#x1F1E7; English - &#x1F1EC;&#x1F1F7; Greek',
                'en-es' => '&#x1F1EC;&#x1F1E7; English - &#x1F1EA;&#x1F1F8; Spanish',
                'en-et' => '&#x1F1EC;&#x1F1E7; English - &#x1F1EA;&#x1F1EA; Estonian',
                'en-fi' => '&#x1F1EC;&#x1F1E7; English - &#x1F1EB;&#x1F1EE; Finnish',
                'en-fr' => '&#x1F1EC;&#x1F1E7; English - &#x1F1EB;&#x1F1F7; French',
                'en-ja' => '&#x1F1EC;&#x1F1E7; English - &#x1F1EF;&#x1F1F5; Japanese',
                'en-hu' => '&#x1F1EC;&#x1F1E7; English - &#x1F1ED;&#x1F1FA; Hungarian',
                'en-it' => '&#x1F1EC;&#x1F1E7; English - &#x1F1EE;&#x1F1F9; Italian',
                'en-lt' => '&#x1F1EC;&#x1F1E7; English - &#x1F1F1;&#x1F1F9; Lithuanian',
                'en-lv' => '&#x1F1EC;&#x1F1E7; English - &#x1F1F1;&#x1F1FB; Latvian',
                'en-mk' => '&#x1F1EC;&#x1F1E7; English - &#x1F1F2;&#x1F1F0; Macedonian',
                'en-nl' => '&#x1F1EC;&#x1F1E7; English - &#x1F1F3;&#x1F1F1; Dutch',
                'en-no' => '&#x1F1EC;&#x1F1E7; English - &#x1F1F3;&#x1F1F4; Norwegian',
                'en-pt' => '&#x1F1EC;&#x1F1E7; English - &#x1F1F5;&#x1F1F9; Portuguese',
                'en-ru' => '&#x1F1EC;&#x1F1E7; English - &#x1F1F7;&#x1F1FA; Russian',
                'en-sk' => '&#x1F1EC;&#x1F1E7; English - &#x1F1F8;&#x1F1F0; Slovak',
                'en-sl' => '&#x1F1EC;&#x1F1E7; English - &#x1F1F8;&#x1F1EE; Slovenian',
                'en-sq' => '&#x1F1EC;&#x1F1E7; English - &#x1F1E6;&#x1F1F1; Albanian',
                'en-sv' => '&#x1F1EC;&#x1F1E7; English - &#x1F1F8;&#x1F1EA; Swedish',
                'en-tr' => '&#x1F1EC;&#x1F1E7; English - &#x1F1F9;&#x1F1F7; Turkish',
                'en-uk' => '&#x1F1EC;&#x1F1E7; English - &#x1F1FA;&#x1F1E6; Ukrainian',
                'de-be' => '&#x1F1E9;&#x1F1EA; German - &#x1F1E7;&#x1F1FE; Belarusian',
                'de-zh' => '&#x1F1E9;&#x1F1EA; German - &#x1F1E8;&#x1F1F3; Chinese',
                'de-en' => '&#x1F1E9;&#x1F1EA; German - &#x1F1EC;&#x1F1E7; English',
                'de-es' => '&#x1F1E9;&#x1F1EA; German - &#x1F1EA;&#x1F1F8; Spanish',
                'de-fr' => '&#x1F1E9;&#x1F1EA; German - &#x1F1EB;&#x1F1F7; French',
                'de-ja' => '&#x1F1E9;&#x1F1EA; German - &#x1F1EF;&#x1F1F5; Japanese',
                'de-it' => '&#x1F1E9;&#x1F1EA; German - &#x1F1EE;&#x1F1F9; Italian',
                'de-ru' => '&#x1F1E9;&#x1F1EA; German - &#x1F1F7;&#x1F1FA; Russian',
                'de-tr' => '&#x1F1E9;&#x1F1EA; German - &#x1F1F9;&#x1F1F7; Turkish',
                'de-uk' => '&#x1F1E9;&#x1F1EA; German - &#x1F1FA;&#x1F1E6; Ukrainian',
                'el-en' => '&#x1F1EC;&#x1F1F7; Greek - &#x1F1EC;&#x1F1E7; English',
                'el-ru' => '&#x1F1EC;&#x1F1F7; Greek - &#x1F1F7;&#x1F1FA; Russian',
                'et-en' => '&#x1F1EA;&#x1F1EA; Estonian - &#x1F1EC;&#x1F1E7; English',
                'et-ru' => '&#x1F1EA;&#x1F1EA; Estonian - &#x1F1F7;&#x1F1FA; Russian',
                'fi-en' => '&#x1F1EB;&#x1F1EE; Finnish - &#x1F1EC;&#x1F1E7; English',
                'fi-ru' => '&#x1F1EB;&#x1F1EE; Finnish - &#x1F1F7;&#x1F1FA; Russian',
                'fr-be' => '&#x1F1EB;&#x1F1F7; French - &#x1F1E7;&#x1F1FE; Belarusian',
                'fr-zh' => '&#x1F1EB;&#x1F1F7; French - &#x1F1E8;&#x1F1F3; Chinese',
                'fr-de' => '&#x1F1EB;&#x1F1F7; French - &#x1F1E9;&#x1F1EA; German',
                'fr-en' => '&#x1F1EB;&#x1F1F7; French - &#x1F1EC;&#x1F1E7; English',
                'fr-ja' => '&#x1F1EB;&#x1F1F7; French - &#x1F1EF;&#x1F1F5; Japanese',
                'fr-it' => '&#x1F1EB;&#x1F1F7; French - &#x1F1EE;&#x1F1F9; Italian',
                'fr-ru' => '&#x1F1EB;&#x1F1F7; French - &#x1F1F7;&#x1F1FA; Russian',
                'fr-uk' => '&#x1F1EB;&#x1F1F7; French - &#x1F1FA;&#x1F1E6; Ukrainian',
                'ja-en' => '&#x1F1EF;&#x1F1F5; Japanese - &#x1F1EC;&#x1F1E7; English',
                'ja-ru' => '&#x1F1EF;&#x1F1F5; Japanese - &#x1F1F7;&#x1F1FA; Russian',
                'ja-zh' => '&#x1F1EF;&#x1F1F5; Japanese - &#x1F1E8;&#x1F1F3; Chinese',
                'ja-de' => '&#x1F1EF;&#x1F1F5; Japanese - &#x1F1E9;&#x1F1EA; German',
                'ja-fr' => '&#x1F1EF;&#x1F1F5; Japanese - &#x1F1EB;&#x1F1F7; French',
                'hr-ru' => '&#x1F1ED;&#x1F1F7; Croatian - &#x1F1F7;&#x1F1FA; Russian',
                'hu-en' => '&#x1F1ED;&#x1F1FA; Hungarian - &#x1F1EC;&#x1F1E7; English',
                'hu-ru' => '&#x1F1ED;&#x1F1FA; Hungarian - &#x1F1F7;&#x1F1FA; Russian',
                'it-be' => '&#x1F1EE;&#x1F1F9; Italian - &#x1F1E7;&#x1F1FE; Belarusian',
                'it-zh' => '&#x1F1EE;&#x1F1F9; Italian - &#x1F1E8;&#x1F1F3; Chinese',
                'it-de' => '&#x1F1EE;&#x1F1F9; Italian - &#x1F1E9;&#x1F1EA; German',
                'it-en' => '&#x1F1EE;&#x1F1F9; Italian - &#x1F1EC;&#x1F1E7; English',
                'it-fr' => '&#x1F1EE;&#x1F1F9; Italian - &#x1F1EB;&#x1F1F7; French',
                'it-ru' => '&#x1F1EE;&#x1F1F9; Italian - &#x1F1F7;&#x1F1FA; Russian',
                'it-uk' => '&#x1F1EE;&#x1F1F9; Italian - &#x1F1FA;&#x1F1E6; Ukrainian',
                'lt-en' => '&#x1F1F1;&#x1F1F9; Lithuanian - &#x1F1EC;&#x1F1E7; English',
                'lt-ru' => '&#x1F1F1;&#x1F1F9; Lithuanian - &#x1F1F7;&#x1F1FA; Russian',
                'lv-en' => '&#x1F1F1;&#x1F1FB; Latvian - &#x1F1EC;&#x1F1E7; English',
                'lv-ru' => '&#x1F1F1;&#x1F1FB; Latvian - &#x1F1F7;&#x1F1FA; Russian',
                'mk-en' => '&#x1F1F2;&#x1F1F0; Macedonian - &#x1F1EC;&#x1F1E7; English',
                'mk-ru' => '&#x1F1F2;&#x1F1F0; Macedonian - &#x1F1F7;&#x1F1FA; Russian',
                'nl-en' => '&#x1F1F3;&#x1F1F1; Dutch - &#x1F1EC;&#x1F1E7; English',
                'nl-ru' => '&#x1F1F3;&#x1F1F1; Dutch - &#x1F1F7;&#x1F1FA; Russian',
                'no-en' => '&#x1F1F3;&#x1F1F4; Norwegian - &#x1F1EC;&#x1F1E7; English',
                'no-ru' => '&#x1F1F3;&#x1F1F4; Norwegian - &#x1F1F7;&#x1F1FA; Russian',
                'pl-be' => '&#x1F1F5;&#x1F1F1; Polish - &#x1F1E7;&#x1F1FE; Belarusian',
                'pl-ru' => '&#x1F1F5;&#x1F1F1; Polish - &#x1F1F7;&#x1F1FA; Russian',
                'pl-uk' => '&#x1F1F5;&#x1F1F1; Polish - &#x1F1FA;&#x1F1E6; Ukrainian',
                'pt-en' => '&#x1F1F5;&#x1F1F9; Portuguese - &#x1F1EC;&#x1F1E7; English',
                'pt-ru' => '&#x1F1F5;&#x1F1F9; Portuguese - &#x1F1F7;&#x1F1FA; Russian',
                'ro-be' => '&#x1F1F7;&#x1F1F4; Romanian - &#x1F1E7;&#x1F1FE; Belarusian',
                'ro-ru' => '&#x1F1F7;&#x1F1F4; Romanian - &#x1F1F7;&#x1F1FA; Russian',
                'ro-uk' => '&#x1F1F7;&#x1F1F4; Romanian - &#x1F1FA;&#x1F1E6; Ukrainian',
                'ru-az' => '&#x1F1F7;&#x1F1FA; Russian - &#x1F1E6;&#x1F1FF; Azerbaijani',
                'ru-be' => '&#x1F1F7;&#x1F1FA; Russian - &#x1F1E7;&#x1F1FE; Belarusian',
                'ru-bg' => '&#x1F1F7;&#x1F1FA; Russian - &#x1F1E7;&#x1F1EC; Bulgarian',
                'ru-ca' => '&#x1F1F7;&#x1F1FA; Russian - &#x1F1E8;&#x1F1E6; Catalan',
                'ru-cs' => '&#x1F1F7;&#x1F1FA; Russian - &#x1F1E8;&#x1F1FF; Czech',
                'ru-da' => '&#x1F1F7;&#x1F1FA; Russian - &#x1F1E9;&#x1F1F0; Danish',
                'ru-de' => '&#x1F1F7;&#x1F1FA; Russian - &#x1F1E9;&#x1F1EA; German',
                'ru-el' => '&#x1F1F7;&#x1F1FA; Russian - &#x1F1EC;&#x1F1F7; Greek',
                'ru-en' => '&#x1F1F7;&#x1F1FA; Russian - &#x1F1EC;&#x1F1E7; English',
                'ru-es' => '&#x1F1F7;&#x1F1FA; Russian - &#x1F1EA;&#x1F1F8; Spanish',
                'ru-et' => '&#x1F1F7;&#x1F1FA; Russian - &#x1F1EA;&#x1F1EA; Estonian',
                'ru-fi' => '&#x1F1F7;&#x1F1FA; Russian - &#x1F1EB;&#x1F1EE; Finnish',
                'ru-fr' => '&#x1F1F7;&#x1F1FA; Russian - &#x1F1EB;&#x1F1F7; French',
                'ru-hr' => '&#x1F1F7;&#x1F1FA; Russian - &#x1F1ED;&#x1F1F7; Croatian',
                'ru-hu' => '&#x1F1F7;&#x1F1FA; Russian - &#x1F1ED;&#x1F1FA; Hungarian',
                'ru-hy' => '&#x1F1F7;&#x1F1FA; Russian - &#x1F1E6;&#x1F1F2; Armenian',
                'ru-it' => '&#x1F1F7;&#x1F1FA; Russian - &#x1F1EE;&#x1F1F9; Italian',
                'ru-ja' => '&#x1F1F7;&#x1F1FA; Russian - &#x1F1EF;&#x1F1F5; Japanese',
                'ru-lt' => '&#x1F1F7;&#x1F1FA; Russian - &#x1F1F1;&#x1F1F9; Lithuanian',
                'ru-lv' => '&#x1F1F7;&#x1F1FA; Russian - &#x1F1F1;&#x1F1FB; Latvian',
                'ru-mk' => '&#x1F1F7;&#x1F1FA; Russian - &#x1F1F2;&#x1F1F0; Macedonian',
                'ru-nl' => '&#x1F1F7;&#x1F1FA; Russian - &#x1F1F3;&#x1F1F1; Dutch',
                'ru-no' => '&#x1F1F7;&#x1F1FA; Russian - &#x1F1F3;&#x1F1F4; Norwegian',
                'ru-pl' => '&#x1F1F7;&#x1F1FA; Russian - &#x1F1F5;&#x1F1F1; Polish',
                'ru-pt' => '&#x1F1F7;&#x1F1FA; Russian - &#x1F1F5;&#x1F1F9; Portuguese',
                'ru-ro' => '&#x1F1F7;&#x1F1FA; Russian - &#x1F1F7;&#x1F1F4; Romanian',
                'ru-sk' => '&#x1F1F7;&#x1F1FA; Russian - &#x1F1F8;&#x1F1F0; Slovak',
                'ru-sl' => '&#x1F1F7;&#x1F1FA; Russian - &#x1F1F8;&#x1F1EE; Slovenian',
                'ru-sq' => '&#x1F1F7;&#x1F1FA; Russian - &#x1F1E6;&#x1F1F1; Albanian',
                'ru-sr' => '&#x1F1F7;&#x1F1FA; Russian - &#x1F1F7;&#x1F1F8; Serbian',
                'ru-sv' => '&#x1F1F7;&#x1F1FA; Russian - &#x1F1F8;&#x1F1EA; Swedish',
                'ru-tr' => '&#x1F1F7;&#x1F1FA; Russian - &#x1F1F9;&#x1F1F7; Turkish',
                'ru-uk' => '&#x1F1F7;&#x1F1FA; Russian - &#x1F1FA;&#x1F1E6; Ukrainian',
                'sk-en' => '&#x1F1F8;&#x1F1F0; Slovak - &#x1F1EC;&#x1F1E7; English',
                'sk-ru' => '&#x1F1F8;&#x1F1F0; Slovak - &#x1F1F7;&#x1F1FA; Russian',
                'es-be' => '&#x1F1EA;&#x1F1F8; Spanish - &#x1F1E7;&#x1F1FE; Belarusian',
                'es-zh' => '&#x1F1EA;&#x1F1F8; Spanish - &#x1F1E8;&#x1F1F3; Chinese',
                'es-de' => '&#x1F1EA;&#x1F1F8; Spanish - &#x1F1E9;&#x1F1EA; German',
                'es-en' => '&#x1F1EA;&#x1F1F8; Spanish - &#x1F1EC;&#x1F1E7; English',
                'es-ru' => '&#x1F1EA;&#x1F1F8; Spanish - &#x1F1F7;&#x1F1FA; Russian',
                'es-uk' => '&#x1F1EA;&#x1F1F8; Spanish - &#x1F1FA;&#x1F1E6; Ukrainian',
                'sl-en' => '&#x1F1F8;&#x1F1EE; Slovenian - &#x1F1EC;&#x1F1E7; English',
                'sl-ru' => '&#x1F1F8;&#x1F1EE; Slovenian - &#x1F1F7;&#x1F1FA; Russian',
                'sq-en' => '&#x1F1E6;&#x1F1F1; Albanian - &#x1F1EC;&#x1F1E7; English',
                'sq-ru' => '&#x1F1E6;&#x1F1F1; Albanian - &#x1F1F7;&#x1F1FA; Russian',
                'sr-be' => '&#x1F1F7;&#x1F1F8; Serbian - &#x1F1E7;&#x1F1FE; Belarusian',
                'sr-ru' => '&#x1F1F7;&#x1F1F8; Serbian - &#x1F1F7;&#x1F1FA; Russian',
                'sr-uk' => '&#x1F1F7;&#x1F1F8; Serbian - &#x1F1FA;&#x1F1E6; Ukrainian',
                'sv-en' => '&#x1F1F8;&#x1F1EA; Swedish - &#x1F1EC;&#x1F1E7; English',
                'sv-ru' => '&#x1F1F8;&#x1F1EA; Swedish - &#x1F1F7;&#x1F1FA; Russian',
                'tr-be' => '&#x1F1F9;&#x1F1F7; Turkish - &#x1F1E7;&#x1F1FE; Belarusian',
                'tr-de' => '&#x1F1F9;&#x1F1F7; Turkish - &#x1F1E9;&#x1F1EA; German',
                'tr-en' => '&#x1F1F9;&#x1F1F7; Turkish - &#x1F1EC;&#x1F1E7; English',
                'tr-ru' => '&#x1F1F9;&#x1F1F7; Turkish - &#x1F1F7;&#x1F1FA; Russian',
                'tr-uk' => '&#x1F1F9;&#x1F1F7; Turkish - &#x1F1FA;&#x1F1E6; Ukrainian',
                'uk-bg' => '&#x1F1FA;&#x1F1E6; Ukrainian - &#x1F1E7;&#x1F1EC; Bulgarian',
                'uk-cs' => '&#x1F1FA;&#x1F1E6; Ukrainian - &#x1F1E8;&#x1F1FF; Czech',
                'uk-de' => '&#x1F1FA;&#x1F1E6; Ukrainian - &#x1F1E9;&#x1F1EA; German',
                'uk-en' => '&#x1F1FA;&#x1F1E6; Ukrainian - &#x1F1EC;&#x1F1E7; English',
                'uk-es' => '&#x1F1FA;&#x1F1E6; Ukrainian - &#x1F1EA;&#x1F1F8; Spanish',
                'uk-fr' => '&#x1F1FA;&#x1F1E6; Ukrainian - &#x1F1EB;&#x1F1F7; French',
                'uk-it' => '&#x1F1FA;&#x1F1E6; Ukrainian - &#x1F1EE;&#x1F1F9; Italian',
                'uk-pl' => '&#x1F1FA;&#x1F1E6; Ukrainian - &#x1F1F5;&#x1F1F1; Polish',
                'uk-ro' => '&#x1F1FA;&#x1F1E6; Ukrainian - &#x1F1F7;&#x1F1F4; Romanian',
                'uk-ru' => '&#x1F1FA;&#x1F1E6; Ukrainian - &#x1F1F7;&#x1F1FA; Russian',
                'uk-sr' => '&#x1F1FA;&#x1F1E6; Ukrainian - &#x1F1F7;&#x1F1F8; Serbian',
                'uk-tr' => '&#x1F1FA;&#x1F1E6; Ukrainian - &#x1F1F9;&#x1F1F7; Turkish',
            ],
            'GOOGLE_TRANSLATE_LANGS' => [
                'af'    => '&#x1F1E6;&#x1F1FF; Afrikaans',
                'sq'    => '&#x1F1E6;&#x1F1F1; Albanian',
                'ar'    => '&#x1F1E6;&#x1F1EA; Arabic',
                'az'    => '&#x1F1E6;&#x1F1FF; Azerbaijani',
                'eu'    => '&#x1F1EA;&#x1F1F8; Basque',
                'be'    => '&#x1F1E7;&#x1F1FE; Belarusian',
                'bn'    => '&#x1F1E7;&#x1F1E9; Bengali',
                'bg'    => '&#x1F1E7;&#x1F1EC; Bulgarian',
                'ca'    => '&#x1F1E8;&#x1F1E6; Catalan',
                'zh-CN' => '&#x1F1E8;&#x1F1F3; Chinese Simplified',
                'zh-TW' => '&#x1F1E8;&#x1F1F3; Chinese Traditional',
                'hr'    => '&#x1F1ED;&#x1F1F7; Croatian',
                'cs'    => '&#x1F1E8;&#x1F1FF; Czech',
                'da'    => '&#x1F1E9;&#x1F1F0; Danish',
                'nl'    => '&#x1F1F3;&#x1F1F1; Dutch',
                'en'    => '&#x1F1EC;&#x1F1E7; English',
                'eo'    => '&#x1F1EA;&#x1F1F3; Esperanto',
                'et'    => '&#x1F1EA;&#x1F1EA; Estonian',
                'tl'    => '&#x1F1F5;&#x1F1ED; Filipino',
                'fi'    => '&#x1F1EB;&#x1F1EE; Finnish',
                'fr'    => '&#x1F1EB;&#x1F1F7; French',
                'gl'    => '&#x1F1EC;&#x1F1F5; Galician',
                'ka'    => '&#x1F1EC;&#x1F1EA; Georgian',
                'de'    => '&#x1F1E9;&#x1F1EA; German',
                'el'    => '&#x1F1EC;&#x1F1F7; Greek',
                'gu'    => '&#x1F1EE;&#x1F1EA; Gujarati',
                'ht'    => '&#x1F1ED;&#x1F1F9; Haitian Creole',
                'iw'    => '&#x1F1EE;&#x1F1F1; Hebrew',
                'hi'    => '&#x1F1EE;&#x1F1F3; Hindi',
                'hu'    => '&#x1F1ED;&#x1F1FA; Hungarian',
                'is'    => '&#x1F1EE;&#x1F1F8; Icelandic',
                'id'    => '&#x1F1EE;&#x1F1E9; Indonesian',
                'ga'    => '&#x1F1EE;&#x1F1EA; Irish',
                'it'    => '&#x1F1EE;&#x1F1F9; Italian',
                'ja'    => '&#x1F1EF;&#x1F1F5; Japanese',
                'kn'    => '&#x1F1FA;&#x1F1F2; Kannada',
                'ko'    => '&#x1F1F0;&#x1F1F7; Korean',
                'la'    => '&#x1F1F1;&#x1F1FA; Latin',
                'lv'    => '&#x1F1F1;&#x1F1FB; Latvian',
                'lt'    => '&#x1F1F1;&#x1F1F9; Lithuanian',
                'mk'    => '&#x1F1F2;&#x1F1F0; Macedonian',
                'ms'    => '&#x1F1F2;&#x1F1FE; Malay',
                'mt'    => '&#x1F1F2;&#x1F1F9; Maltese',
                'no'    => '&#x1F1F3;&#x1F1F4; Norwegian',
                'fa'    => '&#x1F1EE;&#x1F1F7; Persian',
                'pl'    => '&#x1F1F5;&#x1F1F1; Polish',
                'pt'    => '&#x1F1F5;&#x1F1F9; Portuguese',
                'ro'    => '&#x1F1F7;&#x1F1F4; Romanian',
                'ru'    => '&#x1F1F7;&#x1F1FA; Russian',
                'sr'    => '&#x1F1F7;&#x1F1F8; Serbian',
                'sk'    => '&#x1F1F8;&#x1F1F0; Slovak',
                'sl'    => '&#x1F1F8;&#x1F1EE; Slovenian',
                'es'    => '&#x1F1EA;&#x1F1F8; Spanish',
                'sw'    => '&#x1F1F8;&#x1F1F3; Swahili',
                'sv'    => '&#x1F1F8;&#x1F1EA; Swedish',
                'ta'    => '&#x1F1F9;&#x1F1F3; Tamil',
                'te'    => '&#x1F1F9;&#x1F1F0; Telugu',
                'th'    => '&#x1F1F9;&#x1F1ED; Thai',
                'tr'    => '&#x1F1F9;&#x1F1F7; Turkish',
                'uk'    => '&#x1F1FA;&#x1F1E6; Ukrainian',
                'ur'    => '&#x1F1F0;&#x1F1F7; Urdu',
                'vi'    => '&#x1F1FB;&#x1F1F3; Vietnamese',
                'cy'    => '&#x1F1EA;&#x1F1FA; Welsh',
                'yi'    => '&#x1F1EF;&#x1F1F4; Yiddish',
            ],
            'DEEPL_TRANSLATE_LANGS'  => [
                'BG'    => '&#x1f1e7;&#x1f1ec; Bulgarian',
                'CS'    => '&#x1F1E8;&#x1F1FF; Czech',
                'DA'    => '&#x1F1E9;&#x1F1F0; Danish',
                'DE'    => '&#x1F1E9;&#x1F1EA; German',
                'EL'    => '&#x1F1EC;&#x1F1F7; Greek',
                'EN-GB' => '&#x1F1EC;&#x1F1E7; English (British)',
                'EN-US' => '&#x1F1FA;&#x1F1F8; English (American)',
                'EN'    => '&#x1F1FA;&#x1F1F8; English (unspecified variant)',
                'ES'    => '&#x1F1EA;&#x1F1F8; Spanish',
                'ET'    => '&#x1F1EA;&#x1F1EA; Estonian',
                'FI'    => '&#x1F1EB;&#x1F1EE; Finnish',
                'FR'    => '&#x1F1EB;&#x1F1F7; French',
                'HU'    => '&#x1F1ED;&#x1F1FA; Hungarian',
                'IT'    => '&#x1F1EE;&#x1F1F9; Italian',
                'JA'    => '&#x1F1EF;&#x1F1F5; Japanese',
                'LT'    => '&#x1F1F1;&#x1F1F9; Lithuanian',
                'LV'    => '&#x1F1F1;&#x1F1FB; Latvian',
                'NL'    => '&#x1F1F3;&#x1F1F1; Dutch',
                'PL'    => '&#x1F1F5;&#x1F1F1; Polish',
                'PT-PT' => '&#x1F1F5;&#x1F1F9; Portuguese (unspecified variant)',
                'PT-BR' => '&#x1F1E7;&#x1F1F7; Portuguese (Brazilian)',
                'PT'    => '&#x1F1F5;&#x1F1F9; Portuguese (unspecified variant)',
                'RO'    => '&#x1F1F7;&#x1F1F4; Romanian',
                'RU'    => '&#x1F1F7;&#x1F1FA; Russian',
                'SK'    => '&#x1F1F8;&#x1F1F0; Slovak',
                'SL'    => '&#x1F1F8;&#x1F1EE; Slovenian',
                'SV'    => '&#x1F1F8;&#x1F1EA; Swedish',
                'ZH'    => '&#x1F1E8;&#x1F1F3; Chinese',
            ],
        ];
    }

    function api_overlimit($api_limit_id) {
        $accounts = get_option(rss_retrieval_ACCOUNTS);

        if (floor($accounts[$api_limit_id]['epoch'] / $accounts[$api_limit_id]['period']) !== floor(time() / $accounts[$api_limit_id]['period'])) {
            $accounts[$api_limit_id]['epoch'] = time();
            $accounts[$api_limit_id]['count'] = 0;
        }

        if ($accounts[$api_limit_id]['max_requests'] == 0 || $accounts[$api_limit_id]['count'] < $accounts[$api_limit_id]['max_requests']) {
            ++$accounts[$api_limit_id]['count'];
            update_option(rss_retrieval_ACCOUNTS, $accounts);
            return false;
        }

        return true;
    }

    function init_feed_options($options) {
        $default_options = [
            'interval'                  => 1440,
            'delay'                     => 0,
            'max_items'                 => 1,
            'post_lifetime'             => 0,
            'post_status'               => 'publish',
            'comment_status'            => 'open',
            'ping_status'               => 'closed',
            'post_type'                 => 'post',
            'custom_taxonomies'         => [],
            'post_format'               => 'default',
            'post_template'             => 'default',
            'post_author'               => 1,
            'base_date'                 => 'post',
            'duplicate_check_method'    => 'guid',
            'undefined_category'        => 'use_default',
            'user_agent'                => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
            'http_headers'              => '',
            'create_tags'               => '',
            'auto_tags'                 => '',
            'polylang_language'         => '',
            'wpml_language'             => '',
            'post_tags'                 => '',
            'post_category'             => [],
            'date_min'                  => 0,
            'date_max'                  => 0,
            'xml_section_tags'          => '',
            'preserve_titles'           => '',
            'remove_emojis_from_slugs'  => 'on',
            'insert_media_attachments'  => 'no',
            'set_thumbnail'             => 'first_image',
            'sanitize'                  => '',
            'convert_encoding'          => '',
            'require_thumbnail'         => '',
            'use_fifu'                  => '',
            'store_images'              => '',
            'add_to_media_library'      => '',
            'image_format'              => 'keep',
            'compression_quality'       => 80,
            'tags_to_woocommerce'       => '',
            'cats_to_woocommerce'       => '',
            'utf8_encoding'             => '',
            'alt_post_thumbnail_src'    => '',
            'strip_tags'                => '',
            'post_title_template'       => '%post_title%',
            'post_slug_template'        => '%post_title%',
            'post_content_template'     => '%post_content%',
            'post_excerpt_template'     => '',
            'custom_fields'             => '',
            'translator'                => 'none',
            'yandex_translation_dir'    => '',
            'google_translation_source' => '',
            'google_translation_target' => '',
            'deepl_translation_target'  => '',
            'deepl_use_api_free'        => 'on',
            'filter_post_title'         => 'on',
            'filter_post_content'       => 'on',
            'filter_post_excerpt'       => 'on',
            'filter_post_link'          => '',
            'filter_all_phrases'        => '',
            'filter_any_phrases'        => '',
            'filter_none_phrases'       => '',
            'filter_any_tags'           => '',
            'filter_none_tags'          => '',
            'filter_days_newer'         => 0,
            'filter_days_older'         => 0,
            'filter_post_longer'        => 0,
            'filter_post_shorter'       => 0,
        ];

        foreach ($default_options as $key => $value) {
            if (! isset($options[$key])) {
                $options[$key] = $default_options[$key];
            }
        }
        return $options;
    }

    function add_custom_cron_interval($schedules) {
        $schedules[rss_retrieval_PC_NAME] = [
            'interval' => intval(get_option(rss_retrieval_PC_INTERVAL)) * 60,
            'display'  => esc_html(rss_retrieval_PC_NAME),
        ];
        return $schedules;
    }

    function fix_excerpt($text) {
        if (preg_match('/<p>(.*?)<\/p>/is', $text, $matches)) {
            if (wp_strip_all_tags($text) === $matches[1]) {
                return $matches[1];
            }
        }
        return $text;
    }

    function update_feeds() {
        if (time() > get_option(rss_retrieval_FEED_PULL_TIME) + get_option(rss_retrieval_MAX_EXEC_TIME) + 30) {
            wp_cache_flush();
            $feed_cnt = count($this->feeds);
            if ($feed_cnt) {
                $feed_ids          = range(0, $feed_cnt - 1);
                $this->show_report = false;
                $this->syndicateFeeds($feed_ids, true);
            }
        }
    }

    function delete_post_media($post_id) {
        $post          = get_post($post_id, ARRAY_A);
        $wp_upload_dir = wp_upload_dir();

        $attachments = get_children(
            [
                'post_parent'    => $post_id,
                'post_type'      => 'attachment',
                'post_mime_type' => 'image',
            ]
        );

        foreach ($attachments as $attachment) {
            wp_delete_attachment($attachment->ID, true);
        }

        preg_match_all('/<img(.+?)src=[\'\"](.+?)[\'\"](.*?)>/is', $post['post_content'] . $post['post_excerpt'], $matches);
        $media_urls = $matches[2];
        preg_match_all('/<img.*?srcset=[\'\"](.+?)[\'\"].*?>/is', $post['post_content'] . $post['post_excerpt'], $matches);

        if (count($matches[1])) {
            foreach ($matches[1] as $item) {
                preg_match_all('/(.+?)\s+.+?[\,\'\"]/is', $item, $srcsets);
                if (count($srcsets[1])) {
                    foreach ($srcsets[1] as $link) {
                        $media_urls[] = trim($link);
                    }
                }
            }
        }

        $media_urls = array_values(array_unique($media_urls));
        rss_retrieval_delete_media_by_url($media_urls);
    }

    function on_before_delete_post($post_id) {
        if (get_post_meta($post_id, '_rssrtvr_rss_source', true)) {
            $this->delete_post_media($post_id);
        }
    }

    function resetPost() {
        global $rss_retrieval_images_to_attach, $rss_retrieval_urls_to_check;

        $this->skip = false;
        $this->post = [
            'post_title'        => '',
            'post_name'         => '',
            'post_author'       => '',
            'post_content'      => '',
            'post_excerpt'      => '',
            'guid'              => '',
            'post_date'         => time(),
            'post_date_gmt'     => time(),
            'categories'        => [],
            'tags_input'        => [],
            'comments'          => [],
            'media_content'     => [],
            'media_thumbnail'   => [],
            'media_description' => '',
            'enclosure_url'     => '',
            'enclosure_type'    => '',
            'link'              => '',
        ];

        $this->xml_tags                = [];
        $rss_retrieval_images_to_attach = [];
        $rss_retrieval_urls_to_check    = [];
    }

    function parse_placeholders($content) {
        if (strpos($content, '%post_title%') !== false) {
            $content = str_replace('%post_title%', trim($this->post['post_title']), $content);
        }

        if (strpos($content, '%post_content%') !== false) {
            $content = str_replace('%post_content%', trim($this->post['post_content']), $content);
        }

        if (strpos($content, '%post_content_notags%') !== false) {
            $content = str_replace('%post_content_notags%', rss_retrieval_strip_tags($this->post['post_content']), $content);
        }

        if (strpos($content, '%post_excerpt%') !== false) {
            $content = str_replace('%post_excerpt%', trim($this->post['post_excerpt']), $content);
        }

        if (strpos($content, '%post_excerpt_notags%') !== false) {
            $content = str_replace('%post_excerpt_notags%', rss_retrieval_strip_tags($this->post['post_excerpt']), $content);
        }

        if (strpos($content, '%post_guid%') !== false) {
            $content = str_replace('%post_guid%', trim($this->post['guid'] ?? ''), $content);
        }

        if (strpos($content, '%media_description%') !== false) {
            $content = str_replace('%media_description%', trim($this->post['media_description']), $content);
        }

        if (strpos($content, '%enclosure_url%') !== false) {
            $content = str_replace('%enclosure_url%', trim($this->post['enclosure_url']), $content);
        }

        if (strpos($content, '%post_date%') !== false) {
            $content = str_replace('%post_date%', trim($this->post['post_date']), $content);
        }

        if (strpos($content, '%categories%') !== false) {
            $content = str_replace('%categories%', trim(implode(', ', $this->post['categories'])), $content);
        }

        $xml_tags = $this->xml_tags;

        $content = preg_replace_callback(
            '/%post_content\[(.*?)\]%/s',
            function ($matches) {
                return rss_retrieval_shorten_html(trim($this->post['post_content']), intval($matches[1]));
            },
            $content
        );

        $content = preg_replace_callback(
            '/%post_content_notags\[(.*?)\]%/s',
            function ($matches) {
                return rss_retrieval_shorten_string_by_words(rss_retrieval_strip_tags($this->post['post_content']), intval($matches[1]));
            },
            $content
        );

        $content = preg_replace_callback(
            '/%post_excerpt\[(.*?)\]%/s',
            function ($matches) {
                return rss_retrieval_shorten_html($this->post['post_excerpt'], intval($matches[1]));
            },
            $content
        );

        $content = preg_replace_callback(
            '/%post_excerpt_notags\[(.*?)\]%/s',
            function ($matches) {
                return rss_retrieval_shorten_string_by_words(rss_retrieval_strip_tags($this->post['post_excerpt']), intval($matches[1]));
            },
            $content
        );

        $content = preg_replace_callback(
            '/%post_date\[(.*?)\]%/s',
            function ($matches) {
                // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
                return date(strval($matches[1]), strtotime($this->post['post_date']));
            },
            $content
        );

        $content = preg_replace_callback(
            '/%xml_tags\[(.*?)\]%/s',
            function ($matches) use ($xml_tags) {
                $xt = trim($matches[1]);
                if (isset($xml_tags[$xt]['val'])) {
                    return html_entity_decode(trim($xml_tags[$xt]['val']));
                }
            },
            $content
        );

        $content = preg_replace_callback(
            '/%xml_tags_attr\[(.*?)\]\[(.*?)\]%/s',
            function ($matches) use ($xml_tags) {
                $xt = trim($matches[1]);
                $xa = mb_strtoupper(trim($matches[2]));
                if (isset($xml_tags[$xt]['attr'][$xa])) {
                    return html_entity_decode($xml_tags[$xt]['attr'][$xa]);
                }
            },
            $content
        );

        $media_thumbnail = $this->post['media_thumbnail'];
        $content = preg_replace_callback(
            '/%media_thumbnail\[(.*?)\]%/s',
            function ($matches) use ($media_thumbnail) {
                $cf = intval($matches[1]);
                if (isset($media_thumbnail[$cf])) {
                    return $media_thumbnail[$cf];
                }
            },
            $content
        );

        $media_content = $this->post['media_content'];
        $content = preg_replace_callback(
            '/%media_content\[(.*?)\]%/s',
            function ($matches) use ($media_content) {
                $cf = intval($matches[1]);
                if (isset($media_content[$cf])) {
                    return $media_content[$cf];
                }
            },
            $content
        );

        $content = preg_replace_callback(
            '/%youtube_video\[(.*?)\]%/s',
            function ($matches) {
                return rss_retrieval_get_youtube_video($this->parse_placeholders($matches[1]));
            },
            $content
        );

        if (isset($this->post['link'])) {
            $content = str_replace('%link%', $this->post['link'], $content);
        }

        return trim($content);
    }

    function parse_w3cdtf($w3cdate) {
        if (preg_match('/^\s*(\d{4})(-(\d{2})(-(\d{2})(T(\d{2}):(\d{2})(:(\d{2})(\.\d+)?)?(?:([-+])(\d{2}):?(\d{2})|(Z))?)?)?)?\s*$/', $w3cdate, $match)) {
            list($year, $month, $day, $hours, $minutes, $seconds) = [$match[1], $match[3], $match[5], $match[7], $match[8], $match[10]];
            if (is_null($month)) {
                $month = (int) gmdate('m');
            }
            if (is_null($day)) {
                $day = (int) gmdate('d');
            }
            if (is_null($hours)) {
                $hours   = (int) gmdate('H');
                $seconds = $minutes = 0;
            }
            $epoch = gmmktime($hours, $minutes, $seconds, $month, $day, $year);
            if ($match[14] !== 'Z') {
                list($tz_mod, $tz_hour, $tz_min) = [$match[12], $match[13], $match[14]];
                $tz_hour                         = (int) $tz_hour;
                $tz_min                          = (int) $tz_min;
                $offset_secs                     = (($tz_hour * 60) + $tz_min) * 60;
                if ($tz_mod === '+') {
                    $offset_secs *= -1;
                }
                $offset = $offset_secs;
            }
            $epoch += $offset;
            return $epoch;
        } else {
            return -1;
        }
    }

    function log($message) {
        if (! $this->preview) {
            // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
            $this->parse_feed_log .= '[' . wp_unslash(date('d-m-y h:i:s')) . '] ' . wp_unslash($message) . PHP_EOL;
            update_option(rss_retrieval_LOG, $this->parse_feed_log);
        }
    }

    function parseFeed($feed_url) {
        $this->tag                  = '';
        $this->insideitem           = 0;
        $this->element_tag          = '';
        $this->feed_title           = '';
        $this->generator            = '';
        $this->current_feed_url     = $feed_url;
        $this->feed_charset_convert = '';
        $this->posts_found          = 0;
        $this->failure              = false;
        $this->document_type        = 'XML';

        $content = rss_retrieval_file_get_contents($feed_url);

        if (strpos($content, 'PK') === 0) {
            $content = rss_retrieval_unzip($content);
        } elseif (strpos($content, "\x1f\x8b\x08") === 0 || (strpos($content, "\x78\x01") === 0 || strpos($content, "\x78\x9c") === 0 || strpos($content, "\x78\xda") === 0)) {
            $content = @gzuncompress($content);
        } elseif (strpos($content, "\x45\x5a\x68") === 0) {
            $content = @bzdecompress($content);
        }

        preg_match('/^(.*?)<\?xml/is', $content, $matches);
        if (isset($matches[1])) {
            $content = str_replace($matches[1], '', $content);
        }

        $this->current_feed['options']['xml_section_tags'] = 'ENTRY,ITEM';
        $content = str_replace('&rsquo;', '\'', $content);
        $content = str_replace('a?', 'a', $content);

        if (preg_match('/body.*?(\{direction:rtl.*?\})/is', $content, $matches)) {
            $content = str_replace($matches[1], '', $content);
        }

        if (preg_match('/body.*?(\{direction:ltr.*?\})/is', $content, $matches)) {
            $content = str_replace($matches[1], '', $content);
        }

        try {
            $xml = @new SimpleXMLElement($content, LIBXML_NOCDATA);

            if (isset($xml->channel->title)) {
                $this->feed_title = $xml->channel->title;
            } elseif (isset($xml->title)) {
                $this->feed_title = $xml->title;
            }
        } catch (Exception $e) {
            $this->feed_title = '';
        }

        if (! $content || ! strlen($content)) {
            if (is_admin()) {
                echo '<div id="message" class="error"><p>Unable to acquire <a href="' . esc_html(esc_url($feed_url)) . '" target="_blank">' . esc_html(urldecode($feed_url)) . '</a></p></div>';
            } else {
                $this->log('Unable to acquire ' . $feed_url);
            }
            return false;
        }
        $rss_lines = @explode("\n", trim($content));

        if (is_array($rss_lines) && count($rss_lines)) {
            preg_match("/encoding[. ]?=[. ]?[\"'](.*?)[\"']/i", $rss_lines[0], $matches);
            if (($matches[1] ?? '') !== '') {
                $this->feed_charset = trim($matches[1]);
            } else {
                $this->feed_charset = 'not defined';
            }

            $xml_parser = xml_parser_create($this->blog_charset);
            xml_parser_set_option($xml_parser, XML_OPTION_TARGET_ENCODING, $this->blog_charset);
            xml_set_element_handler($xml_parser, [$this, 'startElement'], [$this, 'endElement']);
            xml_set_character_data_handler($xml_parser, [$this, 'charData']);

            $do_mb_convert_encoding = ($this->current_feed['options']['convert_encoding'] === 'on' && $this->feed_charset !== 'not defined' && $this->blog_charset !== strtoupper($this->feed_charset));
            $do_uft8_encoding       = ($this->current_feed['options']['utf8_encoding'] === 'on' && $this->blog_charset === 'UTF-8');

            $is_flvembed           = false;
            $this->xml_parse_error = 0;

            foreach ($rss_lines as $line) {
                $line = rtrim($line);
                if ($this->count >= $this->max || $this->failure) {
                    break;
                }

                if ($do_uft8_encoding) {
                    $line = iconv('ISO-8859-1', 'UTF-8', $line);
                }
                if ($do_mb_convert_encoding) {
                    $line = iconv($this->feed_charset, $this->blog_charset, $line);
                }

                if (mb_strtolower(trim($line)) === '<flv_embed>') {
                    $is_flvembed = true;
                } elseif ($is_flvembed) {
                    if (mb_strtolower(trim($line)) === '</flv_embed>') {
                        $is_flvembed = false;
                    } elseif (stripos(trim($line), '<![CDATA[') === false && stripos(trim($line), ']]>') === false) {
                        $line = '<![CDATA[' . esc_html(trim($line)) . ']]>';
                    }
                }

                xml_parse($xml_parser, $line . PHP_EOL);

                $this->xml_parse_error = xml_get_error_code($xml_parser);

                if ($this->xml_parse_error) {
                    xml_parser_free($xml_parser);
                    if (! is_admin()) {
                        $this->log('XML parse error ' . $this->xml_parse_error);
                    }
                    return false;
                }
            }

            xml_parser_free($xml_parser);

            if (! $this->count) {
                if (! is_admin()) {
                    $this->log('0 items added from ' . $feed_url);
                }
                return 0;
            }
            return $this->count;
        } else {
            if (is_admin()) {
                echo '<div id="message" class="error"><p>The source feed is empty.</p></div>';
            } else {
                $this->log('The source feed is empty.');
            }
            return false;
        }
    }

    function modifyFeeds($new_options) {
        if (
            isset($_POST['_wpnonce']) &&
            wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['_wpnonce'])),
                'rss_retrieval_xml_syndicator'
            ) &&
            current_user_can('manage_options')
        ) {
            $feed_ids_raw = isset($_POST['feed_ids']) ? wp_unslash($_POST['feed_ids']) : '';
            $feed_ids_decoded = base64_decode(sanitize_text_field($feed_ids_raw), true);
            $feed_ids = $feed_ids_decoded !== false ? maybe_unserialize($feed_ids_decoded) : [];

            if (is_array($feed_ids) && count($feed_ids) > 0) {
                $feed_ids = array_map('absint', $feed_ids);
                $feeds_cnt = count($this->feeds);

                for ($i = 0; $i < $feeds_cnt; $i++) {
                    if (in_array($i, $feed_ids, true)) {
                        foreach ($new_options as $option => $value) {
                            if (strpos($option, 'chk_') === 0) {
                                $option_to_change = substr($option, 4);

                                if ($option_to_change === 'date_range') {
                                    $this->feeds[$i]['options']['date_min'] = sanitize_text_field($new_options['date_min'] ?? '');
                                    $this->feeds[$i]['options']['date_max'] = sanitize_text_field($new_options['date_max'] ?? '');
                                } else {
                                    $this->feeds[$i]['options'][$option_to_change] = sanitize_text_field($new_options[$option_to_change] ?? '');
                                }
                            }
                        }
                    }
                }
            }

            update_option(rss_retrieval_SYNDICATED_FEEDS, $this->feeds);
        }
    }

    private function maybe_increase_time_limit() {
        $max_exec_time = (int) get_option('rss_retrieval_MAX_EXEC_TIME');
        if ($max_exec_time > 0 && function_exists('set_time_limit')) {
            try {
                // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
                @call_user_func('set_time_limit', $max_exec_time);
            } catch (\Throwable $e) {
                // host may disallow
            }
        }
    }

    function syndicateFeeds($feed_ids, $check_time) {
        $this->maybe_increase_time_limit();

        $this->preview = false;
        $feeds_cnt     = count($this->feeds);
        if (is_array($feed_ids) && count($feed_ids) > 0) {
            if ($this->show_report) {
                @ob_end_flush();
                ob_implicit_flush();
                echo '<div id="message" class="notice updated"><p>';
                flush();
            }
            $this->parse_feed_log = '';
            for ($i = 0; $i < $feeds_cnt; $i++) {
                if (in_array($i, $feed_ids)) {
                    if (! $check_time || $this->getUpdateTime($i) === 'asap') {
                        update_option(rss_retrieval_FEED_PULL_TIME, time());
                        $this->feeds_updated[$i] = time();
                        update_option(rss_retrieval_FEEDS_UPDATED, $this->feeds_updated);
                        $this->current_feed = $this->feeds[$i];
                        $this->resetPost();
                        $this->max = (int) $this->current_feed['options']['max_items'];
                        $this->log('Feed URL: ' . $this->current_feed['url']);

                        if ($this->show_report) {
                            echo 'Syndicating <a href="' . esc_html(esc_url($this->current_feed['url'])) . '" target="_blank"><strong>' . esc_html(esc_html($this->current_feed['title'])) . '</strong></a>...';
                            flush();
                        }

                        if ($this->current_feed['options']['undefined_category'] === 'use_global') {
                            $this->current_feed['options']['undefined_category'] = $this->global_options['undefined_category'];
                        }

                        $this->count = 0;
                        $result      = $this->parseFeed($this->current_feed['url']);

                        if ($this->show_report) {
                            if ($this->count === 1) {
                                echo esc_html($this->count . ' ' . $this->current_feed['options']['post_type'] . ' was added');
                                $this->log($this->count . ' ' . $this->current_feed['options']['post_type'] . ' was added' . PHP_EOL);
                            } else {
                                echo esc_html($this->count . ' ' . $this->current_feed['options']['post_type'] . 's were added');
                                $this->log($this->count . ' ' . $this->current_feed['options']['post_type'] . 's were added.' . PHP_EOL);
                            }
                            if ($result === false) {
                                echo esc_html('[!]');
                                $this->log('Feed syndication failed');
                            }
                            echo '<br>';
                            flush();
                            $this->parse_feed_log .= PHP_EOL;
                        }
                    }
                }
            }

            if ($this->show_report) {
                echo '</p></div>';
            }
        }
    }

    function get_attributes($item) {
        $attr = '';
        foreach ($item['attr'] as $atname => $atval) {
            $attr .= ' ' . esc_html(strtolower($atname)) . '="' . esc_html($atval) . '"';
        }
        return $attr;
    }

    function rm_style($s) {
        return trim(preg_replace('/<style.*?>.*?<\/style>/si', '', $s));
    }

    function displayPost() {
        if (empty($this->feed_title)) {
            $host = wp_parse_url($this->current_feed_url, PHP_URL_HOST);
            if (! empty($host)) {
                $this->feed_title = ucfirst(str_ireplace('www.', '', $host));
            } else {
                $this->feed_title = 'no name';
            }
        }

        if (! mb_strlen(trim($this->post['post_excerpt'])) && mb_strlen(trim($this->post['media_description']))) {
            $this->post['post_excerpt'] = $this->post['media_description'];
        }

        if (! mb_strlen(trim($this->post['post_content']))) {
            $this->post['post_content'] = $this->post['post_excerpt'];
        }

        $attachment       = '';
        $video_extensions = wp_get_video_extensions();
        if ($this->post['enclosure_url'] !== '') {
            $ext = mb_strtolower(pathinfo($this->post['enclosure_url'], PATHINFO_EXTENSION));
            if (in_array($ext, $video_extensions)) {
                $video = ['src' => $this->post['enclosure_url']];
                if (isset($this->post['media_thumbnail'][0])) {
                    $video['poster'] = $this->post['media_thumbnail'][0];
                }
                $attachment .= wp_video_shortcode($video);
            } elseif ($this->post['enclosure_type'] === 'audio/mpeg') {
                $audio       = ['src' => $this->post['enclosure_url']];
                $attachment .= wp_audio_shortcode($audio);
            } elseif (stripos($this->post['enclosure_type'], 'image/') !== false || $this->post['enclosure_type'] === '') {
                $attachment .= '<img style="max-width:100%" src="' . $this->post['enclosure_url'] . '">';
            }
        } else {
            if (count($this->post['media_content'])) {
                $attachment .= '<div class="media_block">';
                for ($i = 0; $i < count($this->post['media_content']); $i++) {
                    $ext = mb_strtolower(pathinfo($this->post['media_content'][$i], PATHINFO_EXTENSION));
                    if (in_array($ext, $video_extensions)) {
                        $video = ['src' => $this->post['media_content'][$i]];
                        if (isset($this->post['media_thumbnail'][$i])) {
                            $video['poster'] = $this->post['media_thumbnail'][$i];
                        }
                        $attachment .= wp_video_shortcode($video);
                    } elseif (isset($this->post['media_thumbnail'][$i])) {
                        $attachment .= '<a href="' . $this->post['media_content'][$i] . '"><img style="max-width:100%" src="' . $this->post['media_thumbnail'][$i] . '" class="media_thumbnail"></a>';
                    } else {
                        $attachment .= '<img style="max-width:100%" src="' . $this->post['media_content'][$i] . '">';
                    }
                }
                $attachment .= '</div>';
            }
            if (count($this->post['media_thumbnail'])) {
                $attachment .= '<div class="media_block">';
                for ($i = 0; $i < sizeof($this->post['media_thumbnail']); $i++) {
                    $attachment .= '<img style="max-width:100%" src="' . $this->post['media_thumbnail'][$i] . '" class="media_thumbnail">';
                }
                $attachment .= '</div>';
            }
        }
    ?>

        <strong>Preview mode</strong>
        <select id="preview_mode_switch">
            <?php
            $anything_to_display = false;
            $allowed_tags = [
                'code',
                'pre',
                'img',
                'p',
                'br',
                'i',
                'b',
                'u',
                'ul',
                'ol',
                'li',
                'table',
                'td',
                'tr',
                'th',
                'div',
                'span',
                'h1',
                'h2',
                'h3',
                'h4',
                'h5',
                'hr',
                'video',
                'audio'
            ];
            $alwd = '<' . implode('><', $allowed_tags) . '>';

            $post_content = $this->rm_style(strip_tags(rss_retrieval_fix_white_spaces(trim($this->post['post_content'])), "<a>$alwd"));
            $post_excerpt = $this->rm_style(strip_tags(rss_retrieval_fix_white_spaces(trim($this->post['post_excerpt'])), "<a>$alwd"));

            if (! strlen($post_content) && strlen($post_excerpt)) {
                $post_content = $post_excerpt;
            }

            if (strlen($post_content)) {
                echo '<option value="post_view">Post content</option>';
                $anything_to_display = true;
            }

            if (strlen($attachment)) {
                echo '<option value="attachment_view">Attachment</option>';
                $anything_to_display = true;
            }

            if (count($this->xml_tags)) {
                echo '<option value="xml_view">XML structure</option>';
                $anything_to_display = true;
            }
            ?>
        </select>

        <?php
        echo '<div id="post_view" style="display:none; overflow:auto; max-height:250pt; border:1px #ccc solid; background-color:white; padding:12px; margin:8px 0 8px; 0;">';
        if (strlen($post_content)) {
            echo wp_kses_post(wp_kses_post(wp_kses_post(force_balance_tags($post_content))));
        }
        echo '</div>';

        echo '<div id="full_text_view" style="display:none; overflow:auto; max-height:250pt; border:1px #ccc solid; background-color:white; padding:12px; margin:8px 0 8px; 0;">';
        if (! empty($full_text)) {
            echo wp_kses_post(wp_kses_post(wp_kses_post(force_balance_tags($full_text))));
        }
        echo '</div>';

        echo '<div id="xml_view" style="display:none; overflow:auto; max-height:350pt; border:1px #ccc solid; background-color:white; padding:12px; margin:8px 0 8px; 0;">';
        if (! empty($this->xml_tags)) {
            $attr = $this->get_attributes($this->xml_tags[strtolower($this->element_tag)]);
            echo '<span style="font-family: monospace, monospace;"><strong>&lt;' . esc_html(strtolower($this->element_tag)) . esc_html(esc_attr($attr)) . '&gt;</strong><br>';
            foreach ($this->xml_tags as $tag => $item) {
                if (isset($item['val']) && $item['val'] !== '<xml section>' && (trim($item['val']) !== '' || ! empty($item['attr']))) {
                    $attr = $this->get_attributes($item);
                    echo '&nbsp;&nbsp;&nbsp;&nbsp;<strong>' . esc_html(esc_html('<' . esc_html($tag) . $attr . '>')) . '</strong>' . esc_html(trim($item['val'])) . '<strong>' . esc_html(esc_html('</' . esc_html($tag) . '>')) . '</strong><br>';
                }
            }
            echo '<strong>&lt;/' . esc_html(strtolower($this->element_tag)) . '&gt;</strong></span>';
        }
        echo '</div>';

        echo '<div id="attachment_view" style="display:none; overflow:auto; max-height:350pt; border:1px #ccc solid; background-color:white; padding:12px; margin:8px 0 8px; 0;">';
        if (strlen($attachment)) {
            echo wp_kses_post($attachment) .  '<p>Adjust the <a href="#media-attachments">Media Attachments</a> settings to handle attachments.</p>';
        }
        echo '</div>';
    }

    function feedPreview($feed_url, $edit_existing = false) {
        echo '<br>';
        $this->edit_existing = $edit_existing;
        $this->max           = 1;
        $this->preview       = true;
        $this->resetPost();
        $this->count = 0;

        $result = $this->parseFeed($feed_url);

        if (! $result) {
            if ($this->xml_parse_error) {
                echo '<div id="message" class="notice is-dismissible notice-error">';
                echo '<p><strong>RSS Retriever Lite parser error:</strong> ' . esc_html($this->xml_parse_error) . ' (' . esc_html(xml_error_string($this->xml_parse_error)) . '). Perhaps the feed is unreachable, broken or empty. ';
                echo '</p></div>';
            }
        }
        return ($result);
    }

    function startElement($parser, $name, $attribs) {
        $this->tag = $name;
        $this->current_custom_field_attr[$name] = $attribs;
        $this->xml_tags[strtolower($name)]['attr'] = $attribs;

        if (! isset($this->xml_tags[strtolower($name)]['val'])) {
            $this->xml_tags[strtolower($name)]['val'] = '';
        }

        $xml_section_tags = explode(',', trim($this->current_feed['options']['xml_section_tags']));

        if (in_array($name, $xml_section_tags)) {
            ++$this->insideitem;
            $this->element_tag = $name;
            $this->resetPost();
            $this->xml_tags[strtolower($name)]['attr'] = $attribs;
            $this->xml_tags[strtolower($name)]['val']  = '<xml section>';
        } elseif (($this->insideitem <= 0) && $name === 'TITLE' && mb_strlen(trim($this->feed_title))) {
            $this->tag = '';
        }

        if ($this->insideitem >= 0) {

            if (isset($attribs['TERM']) && $name === 'CATEGORY') {
                $this->current_category .= $attribs['TERM'];
            }

            if (isset($attribs['URL']) && $name === 'MEDIA:CONTENT') {
                $this->post['media_content'][] = $attribs['URL'];

                if (isset($attribs['TYPE'])) {
                    $this->post['enclosure_type'] = $attribs['TYPE'];
                }
            }

            if (isset($attribs['URL']) && $name === 'MEDIA:THUMBNAIL') {
                $this->post['media_thumbnail'][] = $attribs['URL'];
                if (isset($attribs['TYPE'])) {
                    $this->post['enclosure_type'] = $attribs['TYPE'];
                }
            }

            if ($name === 'ENCLOSURE') {
                if (isset($attribs['URL'])) {

                    $this->post['enclosure_url'] = $attribs['URL'];
                    if (isset($attribs['TYPE'])) {
                        $this->post['enclosure_type'] = $attribs['TYPE'];
                    }
                }
            }

            if (($this->insideitem >= 0) && $name === 'LINK' && isset($attribs['HREF']) && isset($attribs['REL'])) {
                if (stripos($attribs['REL'], 'enclosure') !== false) {

                    $this->post['enclosure_url'] = $attribs['HREF'];
                    if (isset($attribs['TYPE'])) {
                        $this->post['enclosure_type'] = $attribs['TYPE'];
                    }
                } elseif (stripos($attribs['REL'], 'alternate') !== false && $this->post['link'] === '') {
                    $this->post['link'] = $attribs['HREF'];
                }
            } elseif (($this->insideitem >= 0) && $name === 'LINK' && $this->post['link'] === '' && isset($attribs['HREF'])) {
                $this->post['link'] = $attribs['HREF'];
            }
        }

        if (strlen(trim($name)) && $this->insideitem > 0 && ! in_array($name, $this->parents)) {
            $this->parents   = array_values($this->parents);
            $this->parents[] = $name;
        }
    }

    function endElement($parser, $name) {
        $this->new_tag = true;

        if ($name === 'CATEGORY') {
            $category = trim(rss_retrieval_fix_white_spaces($this->current_category));

            if (mb_strlen($category) > 0) {
                $this->post['categories'][] = $category;
            }
            $this->current_category = '';
        }

        $custom_field_name = $this->getCustomField($name);

        if (strlen($custom_field_name)) {

            if (isset($this->post['custom_fields'][$custom_field_name])) {

                if (!is_array($this->post['custom_fields'][$custom_field_name])) {
                    $this->post['custom_fields'][$custom_field_name] = [$this->post['custom_fields'][$custom_field_name]];
                    $this->post['custom_fields_attr'][$custom_field_name] = [$this->post['custom_fields_attr'][$custom_field_name]];
                }
                $this->post['custom_fields'][$custom_field_name][] = $this->current_custom_field;
                $this->post['custom_fields_attr'][$custom_field_name][] = $this->current_custom_field_attr[$name];
            } else {
                $this->post['custom_fields'][$custom_field_name] = $this->current_custom_field;
                $this->post['custom_fields_attr'][$custom_field_name] = $this->current_custom_field_attr[$name];
            }
            $this->current_custom_field_attr[$name] = [];
            $this->current_custom_field = '';
        }

        if (($name === $this->element_tag)) {
            --$this->insideitem;

            if ($this->insideitem <= 0) {
                ++$this->posts_found;

                if (($this->count < $this->max)) {

                    if ($this->preview) {
                        $this->displayPost();
                        ++$this->count;
                    } else {

                        if (! $this->failure) {
                            $this->insertPost();
                        }

                        if ($this->show_report) {
                            echo esc_html(esc_html(str_repeat(' ', 1024)));
                            flush();
                        }
                    }
                }
            }
        } elseif ($this->count >= $this->max) {
            $this->insideitem = 0;
        }

        if (strlen(trim($name)) && $this->insideitem > 0 && ($key = array_search($name, $this->parents)) !== false) {
            unset($this->parents[$key]);
        }
    }

    function getCustomField($tag_name) {
        if (($this->current_feed['options']['custom_fields'] ?? '') !== '') {
            $custom_fields_array = explode("\n", stripslashes(trim($this->current_feed['options']['custom_fields'])));
            foreach ($custom_fields_array as $item) {
                $item = stripslashes($item);
                @list($tag, $name) = explode('->', $item);

                if (!isset($tag) || !isset($name)) {
                    continue;
                }
                $tag = mb_strtoupper(trim($tag));

                if ($tag === $tag_name) {
                    return trim($name);
                }
            }
        }
        return false;
    }

    function charData($parser, $data) {
        if ($this->insideitem >= 0) {

            if (!$this->preview) {
                $custom_field_name = $this->getCustomField($this->tag);
                if ($custom_field_name && mb_strlen(trim($data))) {
                    $this->current_custom_field .= html_entity_decode($data, ENT_QUOTES);
                }
            }

            $xml_section_tags = explode(',', trim(strtoupper($this->current_feed['options']['xml_section_tags'])));

            if (in_array($data, ['&', '<', '|', chr(9), chr(10), chr(11), chr(13)])) {
                $this->new_tag = false;
            }

            if (($this->tag) && ! in_array($this->tag, $xml_section_tags)) {
                $tag = strtolower($this->tag);
                if (! isset($this->xml_tags[$tag]['val'])) {
                    $this->xml_tags[$tag]['val'] = $data;
                } elseif ($this->new_tag && trim($this->xml_tags[$tag]['val']) !== '' && trim($data) !== '') {
                    $this->xml_tags[$tag]['val'] = trim($this->xml_tags[$tag]['val']) . ',' . $data;
                    $this->new_tag                 = false;
                } else {
                    $this->xml_tags[$tag]['val'] .= $data;
                }
            }

            switch ($this->tag) {
                case 'TITLE':
                    if (count($this->parents) >= 2) {
                        $xml_section_tags = explode(',', trim($this->current_feed['options']['xml_section_tags']));

                        if (in_array($this->parents[count($this->parents) - 2], $xml_section_tags)) {
                            $this->post['post_title'] .= $data;
                        }
                    }
                    break;
                case 'G:TITLE':
                    $this->post['post_title'] .= $data;
                    break;
                case 'DESCRIPTION':
                    $this->post['post_excerpt'] .= $data;
                    break;
                case 'G:DESCRIPTION':
                    $this->post['post_excerpt'] .= $data;
                    break;
                case 'MEDIA:DESCRIPTION':
                    $this->post['media_description'] .= $data;
                    break;
                case 'SUMMARY':
                    $this->post['post_excerpt'] .= $data;
                    break;
                case 'LINK':
                    if (trim($data) !== '') {
                        $this->post['link'] .= trim($data);
                    }
                    break;
                case 'G:LINK':
                    if (trim($data) !== '') {
                        $this->post['link'] .= trim($data);
                    }
                    break;
                case 'CONTENT:ENCODED':
                    if (isset($this->post['post_content'])) {
                        $this->post['post_content'] .= $data;
                    }
                    break;
                case 'CONTENT':
                    if (isset($this->post['post_content'])) {
                        $this->post['post_content'] .= $data;
                    }
                    break;
                case 'TURBO:CONTENT':
                    $this->post['post_content'] .= $data;
                    break;
                case 'YANDEX:FULL-TEXT':
                    if (! strlen(trim($this->post['post_content']))) {
                        $this->post['post_content'] .= $data;
                    }
                    break;
                case 'CATEGORY':
                    $this->current_category .= trim($data);
                    break;
                case 'TAGS':
                    $tags = explode(',', trim(rss_retrieval_fix_white_spaces($data)));
                    foreach ($tags as $tag) {
                        if (mb_strlen(trim($tag)) > 0) {
                            $this->post['categories'][] = trim($tag);
                        }
                    }
                    break;
                case 'POST_TAGS':
                    $tags = explode(',', trim(rss_retrieval_fix_white_spaces($data)));
                    foreach ($tags as $tag) {
                        if (mb_strlen(trim($tag)) > 0) {
                            $this->post['tags_input'][] = trim($tag);
                        }
                    }
                    break;
                case 'MEDIA:KEYWORDS':
                    $this->post['tags_input'] = array_merge($this->post['tags_input'], explode(',', trim($data)));
                    break;
                case 'GUID':
                    $this->post['guid'] .= trim($data);
                    break;
                case 'ID':
                    $this->post['guid'] .= trim($data);
                    break;
                case 'ATOM:ID':
                    $this->post['guid'] .= trim($data);
                    break;
                case 'DC:DATE':
                    $this->post['post_date'] = $this->parse_w3cdtf($data);
                    if ($this->post['post_date']) {
                        $this->tag = '';
                    }
                    break;
                case 'DCTERMS:ISSUED':
                    $this->post['post_date'] = $this->parse_w3cdtf($data);
                    if ($this->post['post_date']) {
                        $this->tag = '';
                    }
                    break;
                case 'UPDATED':
                    if (strpos($this->post['link'], '.youtube.com/') === false && strpos($this->post['link'], '//youtube.com/') === false) {
                        $this->post['post_date'] = $this->parse_w3cdtf($data);
                        if ($this->post['post_date']) {
                            $this->tag = '';
                        }
                    }
                    break;
                case 'PUBLISHED':
                    $this->post['post_date'] = $this->parse_w3cdtf($data);
                    if ($this->post['post_date']) {
                        $this->tag = '';
                    }
                    break;
                case 'ISSUED':
                    $this->post['post_date'] = $this->parse_w3cdtf($data);
                    if ($this->post['post_date']) {
                        $this->tag = '';
                    }
                    break;
                case 'PUBDATE':
                    $this->post['post_date'] = strtotime($data);
                    if ($this->post['post_date']) {
                        $this->tag = '';
                    }
                    break;
            }
        } elseif ($this->tag === 'TITLE') {
            $this->feed_title .= rss_retrieval_fix_white_spaces($data);
        } elseif ($this->tag === 'GENERATOR') {
            $this->generator .= trim($data);
        }
    }

    function shuffleUpdateTimes($feed_ids) {
        if (count($feed_ids) > 0) {
            $cnt = count($this->feeds);
            for ($i = 0; $i < $cnt; $i++) {
                if (in_array($i, $feed_ids)) {
                    if (intval(intval($this->feeds[$i]['options']['interval']))) {
                        $this->feeds_updated[$i] = time() - 60 * wp_rand(60, intval($this->feeds[$i]['options']['interval']));
                    }
                }
            }
            update_option(rss_retrieval_FEEDS_UPDATED, $this->feeds_updated);
        }
    }

    function deleteFeeds($feed_ids, $delete_posts = false, $delele_feeds = false) {
        global $wpdb;
        $feeds_cnt = count($feed_ids);
        if ($feeds_cnt > 0) {

            @ob_end_flush();
            ob_implicit_flush();
            echo '<div id="message" class="updated fade"><p>';
            echo 'Please wait...';
            flush();

            $posts_deleted = 0;
            if ($delete_posts) {
                $to_delete = '(';
                $cnt       = count($feed_ids);
                for ($i = 0; $i < $cnt; $i++) {
                    $to_delete .= "'" . $this->feeds[$feed_ids[$i]]['url'] . "', ";
                }
                $to_delete .= ')';
                $to_delete  = str_replace(', )', ')', $to_delete);

                $urls = [];
                foreach ($feed_ids as $feed_id) {
                    if (isset($this->feeds[$feed_id]['url'])) {
                        $urls[] = $this->feeds[$feed_id]['url'];
                    }
                }

                $post_ids = [];
                if (! empty($urls)) {
                    $placeholders = implode(',', array_fill(0, count($urls), '%s'));

                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                    $post_ids = $wpdb->get_col(
                        $wpdb->prepare(
                            "SELECT post_id 
                            FROM {$wpdb->postmeta} 
                            WHERE meta_key = %s 
                            AND meta_value IN ($placeholders)",
                            array_merge(['_rssrtvr_rss_source'], $urls)
                        )
                    );
                }

                if (count($post_ids) > 0) {
                    foreach ($post_ids as $post_id) {
                        $this->delete_post_media($post_id);
                        wp_delete_post($post_id, true);
                        ++$posts_deleted;
                        echo esc_html(esc_html(str_repeat(' ', 1024)));
                        flush();
                    }
                }
            }

            $feeds_deleted = 0;
            if ($delele_feeds) {
                $feeds         = [];
                $feeds_updated = [];
                foreach ($this->feeds as $i => $feed) {
                    if (! in_array($i, $feed_ids)) {
                        $feeds[]         = $feed;
                        $feeds_updated[] = $this->feeds_updated[$i] ?? $feed['updated'];
                    } else {
                        ++$feeds_deleted;
                    }
                }
                $this->feeds         = $feeds;
                $this->feeds_updated = $feeds_updated;
                update_option(rss_retrieval_FEEDS_UPDATED, $this->feeds_updated);
            }

            update_option(rss_retrieval_SYNDICATED_FEEDS, $this->feeds);
            echo ' ' . esc_html($feeds_deleted) . ' feeds, ' . esc_html($posts_deleted) . ' posts deleted.</p></div>';
        }
    }

    function convert_image($source_url) {

        if ($this->current_feed['options']['image_format'] === 'keep' || $this->current_feed['options']['store_images'] !== 'on') {
            return $source_url;
        }

        $headers = get_headers($source_url, 1);
        if (! isset($headers['Content-Type']) || $headers['Content-Type'] !== 'image/png') {
            return $source_url;
        }

        $upload_dir  = wp_upload_dir();
        $upload_path = $upload_dir['basedir'];
        $upload_url  = $upload_dir['baseurl'];

        $relative_path = str_replace($upload_url, '', $source_url);
        $source_path   = $upload_path . $relative_path;

        $new_extension = $this->current_feed['options']['image_format'] === 'webp' ? '.webp' : '.jpg';
        $new_filename  = basename($source_path, '.png') . $new_extension;
        $new_path      = $upload_path . esc_html(dirname($relative_path)) . '/' . $new_filename;

        $path_parts = pathinfo($new_path);
        $counter    = 1;
        while (file_exists($new_path)) {
            $new_filename = $path_parts['filename'] . '-' . esc_html($counter) . '.' . $path_parts['extension'];
            $new_path     = $path_parts['dirname'] . '/' . $new_filename;
            ++$counter;
        }

        if (class_exists('Imagick')) {
            $this->log('Convert image to ' . $this->current_feed['options']['image_format'] . ' with Imagick');
            $image = new Imagick($source_path);
            $image->setImageFormat($this->current_feed['options']['image_format']);
            $image->setImageCompressionQuality(intval($this->current_feed['options']['compression_quality']));
            $image->stripImage();
            $image->writeImage($new_path);
            $image->destroy();
            $this->log('Success');
        } elseif (function_exists('imagecreatefrompng')) {
            $this->log('Convert image to ' . $this->current_feed['options']['image_format'] . ' with GD');
            $image = imagecreatefrompng($source_path);
            switch ($this->current_feed['options']['image_format']) {
                case 'webp':
                    imagepalettetotruecolor($image);
                    imagewebp($image, $new_path, intval($this->current_feed['options']['compression_quality']));
                    break;
                case 'jpeg':
                default:
                    imagejpeg($image, $new_path, intval($this->current_feed['options']['compression_quality']));
                    break;
            }
            imagedestroy($image);
            $this->log('Success');
        } else {
            $this->log('Neither Imagick nor GD extensions are available. Image conversion failed');
        }

        wp_delete_file($source_path);

        $new_url = $upload_url . esc_html(dirname($relative_path)) . '/' . $new_filename;
        return $new_url;
    }


    function get_article_images() {
        preg_match_all('/<img(.+?)src=[\'\"](.+?)[\'\"](.*?)>/is', $this->post['post_content'] . $this->post['post_excerpt'], $matches);
        if (isset($matches[2])) {
            $image_urls = $matches[2];
        } else {
            $image_urls = [];
        }

        preg_match_all('/<img.*?srcset=[\'\"](.+?)[\'\"].*?>/is', $this->post['post_content'] . $this->post['post_excerpt'], $matches);
        if (count($matches[1])) {
            foreach ($matches[1] as $item) {
                preg_match_all('/(.+?)\s+.+?[\,\'\"]/is', $item, $srcsets);
                if (count($srcsets[1])) {
                    foreach ($srcsets[1] as $link) {
                        $image_urls[] = trim($link);
                    }
                }
            }
        }

        return $image_urls;
    }

    function filterPost() {
        $this->log('Apply post filtering');

        if (! empty(trim($this->current_feed['options']['filter_any_tags'])) && count($this->post['categories'])) {
            $categories = array_map('mb_strtolower', $this->post['categories']);
            $found      = false;
            $this->log('The post must contain any of these tags: ' . esc_html($this->current_feed['options']['filter_any_tags']));
            foreach (explode(',', $this->current_feed['options']['filter_any_tags']) as $category) {
                if (in_array(mb_strtolower(trim($category)), $categories)) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                $this->log('The post does not meet the tag/category filtering rules');
                $this->log('The post will not be added' . PHP_EOL);
                return false;
            }
        }

        if (! empty(trim($this->current_feed['options']['filter_none_tags'])) && count($this->post['categories'])) {
            $categories = array_map('mb_strtolower', $this->post['categories']);
            $this->log('The post must contain none of these tags (categories): ' . esc_html($this->current_feed['options']['filter_none_tags']));
            foreach (explode(',', $this->current_feed['options']['filter_none_tags']) as $category) {
                if (in_array(mb_strtolower(trim($category)), $categories)) {
                    $this->log('The post does not meet tag/category filtering rules.');
                    $this->log('The post will not be added' . PHP_EOL);
                    return false;
                }
            }
        }

        if (
            isset($this->current_feed['options']['filter_days_newer']) &&
            ((int) $this->current_feed['options']['filter_days_newer']) &&
            (time() - $this->post['post_date_epoch']) > (86400 * (int) $this->current_feed['options']['filter_days_newer'])
        ) {
            $this->log('The post date is older than a specified period');
            $this->log('The post will not be added' . PHP_EOL);
            return false;
        }

        if (
            isset($this->current_feed['options']['filter_days_newer']) &&
            ((int) $this->current_feed['options']['filter_days_older']) &&
            (time() - $this->post['post_date_epoch']) < (86400 * (int) $this->current_feed['options']['filter_days_older'])
        ) {
            $this->log('The post date is newer than a specified period');
            $this->log('The post will not be added' . PHP_EOL);
            return false;
        }

        if (
            isset($this->current_feed['options']['filter_post_longer']) &&
            $this->current_feed['options']['filter_post_longer'] != 0 &&
            mb_strlen(rss_retrieval_strip_tags($this->post['post_content'])) < $this->current_feed['options']['filter_post_longer']
        ) {
            $this->log('The post is too short');
            $this->log('The post will not be added' . PHP_EOL);
            return;
        }

        if (
            isset($this->current_feed['options']['filter_post_shorter']) &&
            $this->current_feed['options']['filter_post_shorter'] != 0 &&
            mb_strlen(rss_retrieval_strip_tags($this->post['post_content'])) > $this->current_feed['options']['filter_post_shorter']
        ) {
            $this->log('The post is too long');
            $this->log('The post will not be added' . PHP_EOL);
            return;
        }

        if (($this->current_feed['options']['filter_post_link'] ?? '') === 'on') {
            if (! empty($this->current_feed['options']['filter_all_phrases'])) {
                $this->log('The post link must contain all these keywords: ' . esc_html($this->current_feed['options']['filter_all_phrases']));
                $keywords = explode(',', $this->current_feed['options']['filter_all_phrases']);
                foreach ($keywords as $keyword) {
                    if (! stripos($this->post['link'], trim($keyword))) {
                        $this->log('The post link does not contain all the specified keywords');
                        $this->log('The post will not be added' . PHP_EOL);
                        return false;
                    }
                }
            }

            if (! empty($this->current_feed['options']['filter_any_phrases'])) {
                $this->log('The post link must contain any of these keywords: ' . esc_html($this->current_feed['options']['filter_any_phrases']));
                $keywords = explode(',', $this->current_feed['options']['filter_any_phrases']);
                $found    = false;
                foreach ($keywords as $keyword) {
                    if (stripos($this->post['link'], trim($keyword))) {
                        $found = true;
                        break;
                    }
                }
                if (! $found) {
                    $this->log('No mandatory keywords found in the post link');
                    $this->log('The post will not be added' . PHP_EOL);
                    return false;
                }
            }

            if (! empty($this->current_feed['options']['filter_none_phrases'])) {
                $this->log('The post link must contain none of these keywords: ' . esc_html($this->current_feed['options']['filter_none_phrases']));
                $keywords = explode(',', $this->current_feed['options']['filter_none_phrases']);
                foreach ($keywords as $keyword) {
                    if (stripos($this->post['link'], trim($keyword))) {
                        $this->log('The post link contains at least one of blacklisted keywords');
                        $this->log('The post will not be added' . PHP_EOL);
                        return false;
                    }
                }
            }
        }

        $text = ' ';
        if (($this->current_feed['options']['filter_post_title'] ?? '') === 'on') {
            $text .= $this->post['post_title'] . ' ';
        }
        if (($this->current_feed['options']['filter_post_content'] ?? '') === 'on') {
            $text .= $this->post['post_content'] . ' ';
        }
        if (($this->current_feed['options']['filter_post_excerpt'] ?? '') === 'on') {
            $text .= $this->post['post_excerpt'] . ' ';
        }
        $text = html_entity_decode($text, ENT_QUOTES);

        if (mb_strlen($text) > 1) {

            if (! empty($this->current_feed['options']['filter_all_phrases'])) {
                $this->log('The post must contain all these phrases: ' . esc_html($this->current_feed['options']['filter_all_phrases']));
                $keywords = explode(',', $this->current_feed['options']['filter_all_phrases']);
                foreach ($keywords as $keyword) {
                    if (! preg_match('/\b' . preg_quote(trim($keyword, '/')) . '\b/isu', $text)) {
                        $this->log('The post does not contain all the specified keywords or phrases');
                        $this->log('The post will not be added' . PHP_EOL);
                        return false;
                    }
                }
            }

            if (! empty($this->current_feed['options']['filter_any_phrases'])) {
                $this->log('The post must contain any of these phrases: ' . esc_html($this->current_feed['options']['filter_any_phrases']));
                $keywords = explode(',', $this->current_feed['options']['filter_any_phrases']);
                $found    = false;
                foreach ($keywords as $keyword) {
                    if (preg_match('/\b' . preg_quote(trim($keyword), '/') . '\b/isu', $text)) {
                        $found = true;
                        break;
                    }
                }
                if (! $found) {
                    $this->log('No mandatory keywords or phrases found in the post');
                    $this->log('The post will not be added' . PHP_EOL);
                    return false;
                }
            }

            if (! empty($this->current_feed['options']['filter_none_phrases'])) {
                $this->log('The post must contain none of these phrases: ' . esc_html($this->current_feed['options']['filter_none_phrases']));
                $keywords = explode(',', $this->current_feed['options']['filter_none_phrases']);
                foreach ($keywords as $keyword) {
                    if (preg_match('/\b' . preg_quote(trim($keyword), '/') . '\b/isu', $text)) {
                        $this->log('The post contains at least one of blacklisted keywords or phrases');
                        $this->log('The post will not be added' . PHP_EOL);
                        return false;
                    }
                }
            }
        }

        $this->log('Done');
        return true;
    }

    function maskShortCodes($content) {
        $shtps = [
            '%link%',
            '%post_title%',
            '%post_content%',
            '%post_content_notags%',
            '%post_excerpt%',
            '%post_excerpt_notags%',
            '%post_guid%',
            '%media_description%',
            '%enclosure_url%',
            '%post_date%',
            '%categories%',
            '%post_content[',
            '%post_content_notags[',
            '%post_excerpt[',
            '%post_excerpt_notags[',
            '%post_date[',
            '%xml_tags[',
            '%xml_tags_attr[',
            '%media_thumbnail[',
            '%media_content[',
            '%youtube_video[',
        ];

        foreach ($shtps as $shtp) {
            if (mb_strpos($content, $shtp) !== false) {
                $content = str_replace($shtp, mb_substr($shtp, 0, 1) . '@@M@@' . mb_substr($shtp, 1, mb_strlen($shtp) - 1), $content);
            }
        }

        return $content;
    }

    function unmaskShortCodes($content) {
        return str_replace('%@@M@@', '%', $content);
    }

    function remove_absolute_links($link, $source) {
        return preg_replace_callback(
            '/href\s*=\s*([\'"]?)' . esc_html(preg_quote($link, '/')) . '([^\'"]*#[^\'"]*)\1/',
            function ($matches) {
                return 'href=' . esc_attr($matches[1]) . $matches[2] . $matches[1];
            },
            $source
        );
    }

    function process_post_template($template) {
        $res = $template;

        if ($this->failure || $this->skip) {
            $this->log('The post will not be added' . PHP_EOL);
            return false;
        }

        if ($res !== '') {
            $res = stripslashes($this->parse_placeholders($res));
        }
        return $res;
    }

    function disable_thumbnails($sizes) {
        return [];
    }

    function insertPost() {
        global $rss_retrieval_images_to_attach;

        $this->link_checked          = 'none';
        $this->image_urls            = [];
        $this->polylang_translations = [];
        $this->wpml_translations     = [];

        if (! mb_strlen(trim($this->post['post_title']))) {
            if (mb_strlen($this->post['post_excerpt'])) {
                $text = rss_retrieval_strip_tags($this->post['post_excerpt']);
            } else {
                $text = rss_retrieval_strip_tags($this->post['post_content']);
            }

            $this->post['post_title'] = trim($this->post['post_title']);
            if (strlen($this->post['post_title'])) {
                $this->post['post_title'] = mb_substr($text, 0, mb_strrpos(mb_substr($text, 0, 35), ' ')) . '...';
            }
        }

        if (! empty($this->post['link'])) {
            $this->log('Import ' . $this->post['link']);
        } else {
            $this->log('Generate a new post');
        }

        if ($this->show_report) {
            echo esc_html(esc_html(str_repeat(' ', 1024)));
            flush();
        }

        if (strpos($this->post['link'], '.youtube.com/') !== false || strpos($this->post['link'], '//youtube.com/') !== false) {
            $this->post['post_excerpt'] = htmlentities($this->post['media_description'], ENT_QUOTES, 'UTF-8');
            $this->post['post_content'] = $this->post['link'] . "\n" . $this->post['post_excerpt'];
        } elseif (strpos($this->post['link'], '.flickr.com/') !== false || strpos($this->post['link'], '//flickr.com/') !== false) {
            $this->post['post_excerpt'] = $this->post['post_content'] = $this->post['link'] . "\n<br>" . strip_tags($this->post['post_content'], '<br>,<b>,<p>,<a>');
        } elseif (strpos($this->post['link'], '.ign.com/') !== false || strpos($this->post['link'], '//ign.com/') !== false) {
            $content = rss_retrieval_file_get_contents($this->post['link'], false, '', 'self', rss_retrieval_CURL_USER_AGENT);
            preg_match('/"url":"(https:[-\/\.a-z0-9]+\.mp4)","width":1920/', $content, $matches);
            if (isset($matches[1])) {
                $this->post['post_content'] = '<p>[video src="' . esc_attr($matches[1]) . '"]</p><br><p>' . $this->post['post_excerpt'] . '</p>';
            }
        }

        if (! mb_strlen(trim($this->post['post_excerpt'])) && mb_strlen(trim($this->post['media_description']))) {
            $this->post['post_excerpt'] = $this->post['media_description'];
        }

        if (! mb_strlen(trim($this->post['post_content'])) && mb_strlen(trim($this->post['post_excerpt']))) {
            $this->post['post_content'] = $this->post['post_excerpt'];
        }

        if (! strlen($this->post['link']) && isset($this->xml_tags['url']['val'])) {
            $this->post['link'] = $this->xml_tags['url']['val'];
        }

        $this->post['post_date_epoch'] = $this->post['post_date'];

        if ($this->current_feed['options']['base_date'] === 'syndication') {
            $post_date = time();
        } else {
            $post_date = (int) $this->post['post_date'];
        }

        $post_date                      += 60 * ($this->current_feed['options']['date_min'] + wp_rand(0, $this->current_feed['options']['date_max'] - $this->current_feed['options']['date_min']));
        $this->post['post_date']         = addslashes(gmdate('Y-m-d H:i:s', $post_date + 3600 * (int) get_option('gmt_offset')));
        $this->post['post_modified']     = $this->post['post_date'];
        $this->post['post_date_gmt']     = addslashes(gmdate('Y-m-d H:i:s', $post_date));
        $this->post['post_modified_gmt'] = $this->post['post_date_gmt'];
        $this->post['post_status']       = $this->current_feed['options']['post_status'];
        $this->post['comment_status']    = $this->current_feed['options']['comment_status'];
        $this->post['ping_status']       = $this->current_feed['options']['ping_status'];

        $result_dup = rss_retrieval_post_exists($this->post);

        if ($result_dup) {
            $this->log('The post already exists ID: ' . $result_dup);
            $this->log('Skip' . PHP_EOL);
            return;
        }

        if (preg_match_all('/<img(.+?)src=[\'\"](.+?)[\'\"](.*?)>/is', $this->post['post_content'] . $this->post['post_excerpt'], $matches)) {
            $o_imgs = array_merge($this->image_urls, array_unique($matches[2]));
        }

        if (trim($this->current_feed['options']['strip_tags']) !== '') {
            $this->post['post_content'] = rss_retrieval_strip_specific_tags($this->post['post_content'], explode(',', $this->current_feed['options']['strip_tags']));
            $this->post['post_excerpt'] = rss_retrieval_strip_specific_tags($this->post['post_excerpt'], explode(',', $this->current_feed['options']['strip_tags']));
        }

        if (! $this->filterPost()) {
            return;
        }

        $this->log('Process post templates');

        $this->post['post_title']   = $this->maskShortCodes($this->post['post_title']);
        $this->post['post_content'] = $this->maskShortCodes($this->post['post_content']);
        $this->post['post_excerpt'] = $this->maskShortCodes($this->post['post_excerpt']);

        $order = ['title', 'content', 'excerpt'];

        foreach ($order as $template) {
            switch ($template) {
                case 'title':
                    $res = $this->process_post_template($this->current_feed['options']['post_title_template']);
                    if ($res !== false) {
                        $this->post['post_title'] = $res;
                    } else {
                        return;
                    }
                    break;
                case 'content':
                    $res = $this->process_post_template($this->current_feed['options']['post_content_template']);
                    if ($res !== false) {
                        $this->post['post_content'] = $res;
                    } else {
                        return;
                    }
                    break;
                case 'excerpt':
                    $res = $this->process_post_template($this->current_feed['options']['post_excerpt_template']);
                    if ($res !== false) {
                        $this->post['post_excerpt'] = $res;
                    } else {
                        return;
                    }
            }
        }

        $this->post['post_title']   = $this->unmaskShortCodes($this->post['post_title']);
        $this->post['post_content'] = $this->unmaskShortCodes($this->post['post_content']);
        $this->post['post_excerpt'] = $this->unmaskShortCodes($this->post['post_excerpt']);
        $rss_retrieval_post_name      = sanitize_title($this->post['post_title']);

        if (! isset($this->post['tags_input']) || ! is_array($this->post['tags_input'])) {
            $this->post['tags_input'] = [];
        }

        $this->post['custom_taxonomies'] = $this->current_feed['options']['custom_taxonomies'];

        if ($this->current_feed['options']['translator'] !== 'none') {

            if (! is_array($this->post['categories'])) {
                $this->post['categories'] = [];
            }

            if (! is_array($this->post['tags_input'])) {
                $this->post['tags_input'] = [];
            }

            $packed_content = rss_retrieval_pack_conetnt($this->post, true);

            if ($this->current_feed['options']['translator'] === 'yandex_translate') {
                $translated = rss_retrieval_yandex_translate(get_option(rss_retrieval_ACCOUNTS)['yandex_api_key'], $packed_content, $this->current_feed['options']['yandex_translation_dir']);
            } elseif ($this->current_feed['options']['translator'] === 'google_translate') {
                $translated = rss_retrieval_google_translate(get_option(rss_retrieval_ACCOUNTS)['google_api_key'], $packed_content, $this->current_feed['options']['google_translation_source'], $this->current_feed['options']['google_translation_target']);
            } elseif ($this->current_feed['options']['translator'] === 'deepl_translate') {
                $translated = rss_retrieval_deepl_translate(get_option(rss_retrieval_ACCOUNTS)['deepl_api_key'], $packed_content, $this->current_feed['options']['deepl_translation_target'], ($this->current_feed['options']['deepl_use_api_free'] === 'on'));
            }

            if (empty($translated)) {
                $this->log('Translation failed');
                $this->log('The post will not be added' . PHP_EOL);
                return;
            }

            $this->post = rss_retrieval_unpack_content($this->post, $translated);
        }

        if (is_array($this->current_feed['options']['post_category'])) {
            $post_categories = $this->current_feed['options']['post_category'];
        } else {
            $post_categories = [];
        }

        $cat_ids = $this->getCategoryIds($this->post['categories']);
        if (empty($cat_ids) && $this->current_feed['options']['undefined_category'] === 'drop') {
            $this->log('No mandatory categories found in the post.');
            $this->log('The post will not be added' . PHP_EOL);
            return;
        }

        if (! empty($cat_ids)) {
            $post_categories = array_merge($post_categories, $cat_ids);
        } elseif ($this->current_feed['options']['undefined_category'] === 'use_default' && empty($post_categories)) {
            $post_categories[] = get_option('default_category');
        }

        $post_categories = array_unique($post_categories);
        $this->post['post_category'] = $post_categories;

        if ($this->current_feed['options']['create_tags'] === 'on') {
            $this->post['tags_input'] = array_merge($this->post['tags_input'], $this->post['categories']);
        }

        if ($this->current_feed['options']['auto_tags'] === 'on') {
            $terms = get_terms(
                [
                    'taxonomy'   => 'post_tag',
                    'hide_empty' => false,
                ]
            );
            foreach ($terms as $term) {
                if (preg_match('/\b' . esc_html(preg_quote($term->name, '/')) . '\b/isu', ' ' . rss_retrieval_strip_tags($this->post['post_title'] . ' ' . $this->post['post_content'] . ' ' . $this->post['post_excerpt'] . ' '))) {
                    $this->post['tags_input'][] = $term->name;
                }
            }
        }

        if ($this->current_feed['options']['post_tags'] !== '') {
            $tags = explode(',', $this->parse_placeholders($this->current_feed['options']['post_tags']));
            $this->post['tags_input'] = array_merge($this->post['tags_input'], $tags);
        }

        if (($this->current_feed['options']['cats_to_woocommerce'] ?? '') === 'on') {
            $cat_names = [];
            foreach ($this->post['post_category'] as $cat_id) {
                $cat_names[] = get_cat_name($cat_id);
            }
            if (isset($this->post['custom_taxonomies']['product_cat'])) {
                $this->post['custom_taxonomies']['product_cat'] .= ',' . implode(',', $cat_names);
            }
        }

        if (($this->current_feed['options']['tags_to_woocommerce'] ?? '') === 'on') {
            if (isset($this->post['custom_taxonomies']['product_tag'])) {
                $this->post['custom_taxonomies']['product_tag'] .= ',' . implode(',', $this->post['tags_input']);
            }
        }

        if (! isset($this->post['post_type'])) {
            $this->post['post_type'] = $this->current_feed['options']['post_type'];
        }

        if (! isset($this->post['post_author']) || $this->post['post_author'] === '') {
            if ($this->current_feed['options']['post_author'] == 0) {
                $wp_user_search = get_users(['role__in' => ['author', 'editor', 'administrator']]);
                shuffle($wp_user_search);
                $this->post['post_author'] = $wp_user_search[0]->ID;
            } else {
                $this->post['post_author'] = $this->current_feed['options']['post_author'];
            }
        }

        if (strlen($this->current_feed['options']['post_slug_template'])) {
            $this->post['post_name'] = trim(stripslashes($this->parse_placeholders($this->current_feed['options']['post_slug_template'])));
        } else {
            $this->post['post_name'] = stripslashes($this->post['post_title']);
        }

        if ($this->current_feed['options']['remove_emojis_from_slugs'] === 'on') {
            $this->post['post_name'] = rss_retrieval_remove_emojis($this->post['post_name']);
        }

        $this->post['tags_input'] = array_values(array_unique($this->post['tags_input']));

        if (! isset($this->post['media_thumbnail'][0]) && $this->post['enclosure_url'] !== '' && stripos($this->post['enclosure_type'], 'image/') !== false) {
            $this->post['media_thumbnail'][0] = $this->post['enclosure_url'];
        }

        if (! isset($this->post['media_thumbnail'][0]) && ! empty($this->post['media_content'][0])) {
            $this->post['media_thumbnail'][0] = $this->post['media_content'][0];
        }

        $attachment = '';
        if ($this->current_feed['options']['insert_media_attachments'] !== 'no') {
            $attachment = '';
            $video_extensions = wp_get_video_extensions();
            if ($this->post['enclosure_url'] !== '') {
                $attachment .= '<div class="media_block">';
                $ext         = mb_strtolower(pathinfo($this->post['enclosure_url'], PATHINFO_EXTENSION));
                if (in_array($ext, $video_extensions)) {
                    $attachment .= '<video controls src="' . $this->post['enclosure_url'] . '"';
                    if (isset($this->post['media_thumbnail'][0])) {
                        $attachment .= ' poster="' . $this->post['media_thumbnail'][0] . '"';
                    }
                    $attachment .= '></video>';
                } elseif (in_array($this->post['enclosure_type'], ['audio/mpeg', 'audio/ogg', 'audio/wav'])) {
                    $attachment .= '<audio controls><source src="' . $this->post['enclosure_url'] . '" type="' . $this->post['enclosure_type'] . '"></audio>';
                } elseif (stripos($this->post['enclosure_type'], 'image/') !== false) {
                    $attachment .= '<img src="' . $this->post['enclosure_url'] . '">';
                }
                $attachment .= '</div>';
            } elseif (sizeof($this->post['media_content'])) {
                $attachment .= '<div class="media_block">';
                for ($i = 0; $i < sizeof($this->post['media_content']); $i++) {
                    $ext = mb_strtolower(pathinfo($this->post['media_content'][$i], PATHINFO_EXTENSION));
                    if (in_array($ext, $video_extensions)) {
                        $attachment .= '<video controls src="' . $this->post['media_content'][$i] . '"';
                        if (isset($this->post['media_thumbnail'][$i])) {
                            $attachment .= ' poster="' . $this->post['media_thumbnail'][$i] . '"';
                        }
                        $attachment .= '></video>';
                    } elseif (isset($this->post['media_thumbnail'][$i])) {
                        $attachment .= '<a href="' . $this->post['media_content'][$i] . '"><img src="' . $this->post['media_thumbnail'][$i] . '" class="media_thumbnail"></a>';
                    } else {
                        $attachment .= '<img src="' . $this->post['media_content'][$i] . '" class="media_thumbnail">';
                    }
                }
                $attachment .= '</div>';
            } elseif (sizeof($this->post['media_thumbnail'])) {
                $attachment .= '<div class="media_block">';
                for ($i = 0; $i < sizeof($this->post['media_thumbnail']); $i++) {
                    $attachment .= '<img src="' . $this->post['media_thumbnail'][$i] . '" class="media_thumbnail">';
                }
                $attachment .= '</div>';
            }
        }

        if ($attachment !== '') {
            if ($this->current_feed['options']['insert_media_attachments'] === 'top') {
                $this->post['post_content'] = $attachment . $this->post['post_content'];
                $this->post['post_excerpt'] = $attachment . $this->post['post_excerpt'];
            } elseif ($this->current_feed['options']['insert_media_attachments'] === 'bottom') {
                $this->post['post_content'] .= $attachment;
                $this->post['post_excerpt'] .= $attachment;
            }
        }

        $this->image_urls = array_values(array_unique(array_merge($this->get_article_images(), $this->image_urls)));

        if ($this->current_feed['options']['store_images'] === 'on') {
            $home = get_option('home');
            if (count($this->image_urls)) {
                for ($i = 0; $i < count($this->image_urls); $i++) {
                    if (isset($this->image_urls[$i]) && strpos($this->image_urls[$i], $home) === false) {
                        $new_image_url              = $media_urls[] = $images[] = rss_retrieval_save_image($this->image_urls[$i], $this->post['post_title']);
                        $this->post['post_content'] = str_replace($this->image_urls[$i], $new_image_url, $this->post['post_content']);
                        $this->post['post_excerpt'] = str_replace($this->image_urls[$i], $new_image_url, $this->post['post_excerpt']);
                        if ($this->show_report) {
                            echo esc_html(esc_html(str_repeat(' ', 1024)));
                            flush();
                        }
                    }
                }
            }
        }

        if (empty($this->image_urls) && ! empty($o_imgs)) {
            $this->image_urls = $o_imgs;
        }

        if ($this->current_feed['options']['set_thumbnail'] === 'first_image') {
            if (! empty($this->image_urls)) {
                $post_thumb_src = $this->image_urls[0];
                $this->log('Generate post thumbnail from first post image');
            }
        } elseif ($this->current_feed['options']['set_thumbnail'] === 'last_image') {
            if (! empty($this->image_urls)) {
                $post_thumb_src = $this->image_urls[count($this->image_urls) - 1];
                $this->log('Generate post thumbnail from the last post image');
            }
        } elseif ($this->current_feed['options']['set_thumbnail'] === 'random_image') {
            if (! empty($this->image_urls)) {
                $post_thumb_src = $this->image_urls[wp_rand(0, count($this->image_urls) - 1)];
                $this->log('Generate post thumbnail from a random post image');
            }
        } elseif ($this->current_feed['options']['set_thumbnail'] === 'media_attachment' && isset($this->post['media_content'][0]) && (str_ends_with($this->post['media_content'][0], '.jpg') || str_ends_with($this->post['media_content'][0], '.png') || str_ends_with($this->post['media_content'][0], '.webm'))) {
            $post_thumb_src = trim($this->post['media_content'][0]);
            $this->log('Generate post thumbnail from the media content / attachment');
        } elseif ($this->current_feed['options']['set_thumbnail'] === 'media_attachment' && isset($this->post['media_thumbnail'][0])) {
            $post_thumb_src = trim($this->post['media_thumbnail'][0]);
            $this->log('Generate post thumbnail from the media thumbnail / attachment');
        }

        if (isset($post_thumb_src)) {
            if ($this->current_feed['options']['use_fifu'] === 'on') {
                $image_url = $post_thumb_src;
            } else {
                $image_url = $media_urls[] = rss_retrieval_save_image($post_thumb_src, $this->post['post_title']);
            }
        }

        if (has_action('wpml_switch_language')) {
            if ($this->current_feed['options']['wpml_language'] === '') {
                do_action('wpml_switch_language', null);
            } else {
                do_action('wpml_switch_language', $this->current_feed['options']['wpml_language']);
            }
        }

        $this->post['post_content'] = $this->remove_absolute_links($this->post['link'], $this->post['post_content']);
        $this->post['post_excerpt'] = $this->remove_absolute_links($this->post['link'], $this->post['post_excerpt']);
        $this->post['post_excerpt'] = $this->fix_excerpt($this->post['post_excerpt']);
        $this->log('Insert new post into database');

        unset($this->post['guid']);
        rss_retrieval_disable_kses();
        $post_id = @wp_insert_post($this->post, true);
        rss_retrieval_enable_kses();

        if (! is_wp_error($post_id)) {
            $res = add_post_meta($post_id, '_rssrtvr_rss_source', $this->current_feed['url']);
            if ($res) {
                $wp_insert_post_error = false;
            } else {
                $this->log('Can\'t save the feed source URL');
                $wp_insert_post_error = 'Can\'t save the feed source URL.';
            }
        } else {
            $wp_insert_post_error = 'Internal WordPress error. ' . $post_id->get_error_message($post_id->get_error_code());
        }

        if ($this->current_feed['options']['post_template'] !== 'default') {
            add_post_meta($post_id, '_wp_page_template', $this->current_feed['options']['post_template']);
        }

        if ($wp_insert_post_error) {
            if ($this->show_report) {
                echo '<br>' . esc_html($wp_insert_post_error) . '<br>';
            }
            $wp_upload_dir = wp_upload_dir();
            if (isset($media_urls) && count($media_urls)) {
                $media_urls = array_values(array_unique($media_urls));
                foreach ($media_urls as $url) {
                    preg_match('/\/wp-content\/(.*?)$/', $url, $link_match);
                    preg_match('/.*?\/wp-content\//', $wp_upload_dir['path'], $path_match);
                    if (isset($path_match[0]) && isset($link_match[1])) {
                        wp_delete_file($path_match[0] . $link_match[1]);
                    } else {
                        wp_delete_file(str_replace($wp_upload_dir['url'], $wp_upload_dir['path'], $url));
                    }
                }
            }
            $this->log($wp_insert_post_error . PHP_EOL);
            @wp_delete_post($post_id, true);
            return;
        }

        $this->log('Done');

        if (function_exists('pll_set_post_language') && function_exists('pll_save_post_translations')) {
            if ($this->current_feed['options']['polylang_language'] !== '') {
                $this->polylang_translations[$this->current_feed['options']['polylang_language']] = $post_id;
                pll_set_post_language($post_id, $this->current_feed['options']['polylang_language']);
                pll_save_post_translations($this->polylang_translations);
            }
        }

        if (defined('ICL_SITEPRESS_VERSION') && isset($GLOBALS['sitepress'])) {

            if ($this->current_feed['options']['wpml_language'] !== '') {
                $language_code                             = $this->current_feed['options']['wpml_language'];
                $element_type                              = 'post_' . get_post_type($post_id);
                $this->wpml_translations[$language_code] = $post_id;

                $trid = null;
                foreach ($this->wpml_translations as $existing_language_code => $existing_post_id) {
                    if ($existing_language_code != $language_code) {
                        $trid = apply_filters('wpml_element_trid', null, $existing_post_id, $element_type);
                        break;
                    }
                }

                if (empty($trid)) {
                    $trid = $post_id;
                }

                do_action(
                    'wpml_set_element_language_details',
                    [
                        'element_id'           => $post_id,
                        'element_type'         => $element_type,
                        'trid'                 => $trid,
                        'language_code'        => $language_code,
                        'source_language_code' => null,
                    ]
                );

                do_action('wpml_set_element_translations', $this->wpml_translations);
            }
        }

        if ($this->current_feed['options']['post_format'] !== 'default') {
            set_post_format($post_id, $this->current_feed['options']['post_format']);
        }

        if (! add_post_meta($post_id, '_rssrtvr_post_name', $rss_retrieval_post_name)) {
            $this->log('Can\'t save the post name');
            $this->log('The post will not be added' . PHP_EOL);
            wp_delete_post($post_id, true);
            return;
        }

        if (! add_post_meta($post_id, '_rssrtvr_post_link', $this->post['link'])) {
            $this->log('Can\'t save the post URL');
            $this->log('The post will not be added' . PHP_EOL);
            wp_delete_post($post_id, true);
            return;
        }

        if ($this->current_feed['options']['post_lifetime'] > 0) {
            $res = add_post_meta($post_id, '_rssrtvr_post_lifetime', time() + $this->current_feed['options']['post_lifetime'] * 3600);
        } else {
            $res = add_post_meta($post_id, '_rssrtvr_post_lifetime', 2147483647);
        }
        if (! $res) {
            $this->log('Can\'t save the post lifetime');
            $this->log('The post will not be added' . PHP_EOL);
            wp_delete_post($post_id, true);
            return;
        }

        if (isset($this->post['custom_fields'])) {
            foreach ($this->post['custom_fields'] as $name => $value) {
                rss_retrieval_disable_kses();
                if (is_array($value)) {
                    $value = implode(',', $value);
                }
                if (!add_post_meta($post_id, $name, $value, true)) {
                    update_post_meta($post_id, $name, $value);
                }
                rss_retrieval_enable_kses();
            }
        }

        $args       = [
            'public'   => true,
            '_builtin' => false,
        ];
        $taxonomies = get_taxonomies($args, 'objects', 'and');
        foreach ($taxonomies as $taxonomy) {
            if (in_array($this->post['post_type'], $taxonomy->object_type) && isset($this->post['custom_taxonomies'][$taxonomy->name])) {
                wp_set_object_terms($post_id, explode(',', $this->parse_placeholders($this->post['custom_taxonomies'][$taxonomy->name])), $taxonomy->name, true);
            }
        }

        if ($this->current_feed['options']['add_to_media_library'] === 'on') {

            if (count($this->image_urls)) {
                global $post;
                $post = get_post($post_id);
                $this->log('Adding images to the Media Library');

                for ($i = 0; $i < count($this->image_urls); $i++) {

                    if (isset($this->image_urls[$i]) && strpos($this->image_urls[$i], '//') != 0) {

                        $image_url  = $this->image_urls[$i];
                        $upload_dir = wp_upload_dir();
                        if (! file_exists($upload_dir['path'] . '/' . basename($image_url))) {
                            $image_url = rss_retrieval_save_image($image_url, $post->post_title);
                        }
                        $img_path = str_replace($upload_dir['url'], $upload_dir['path'], $image_url);

                        add_filter('intermediate_image_sizes_advanced', [$this, 'disable_thumbnails']);
                        $attachment_id = attachment_url_to_postid($image_url);

                        if (file_exists($img_path) && filesize($img_path)) {
                            if (! $attachment_id) {
                                $wp_filetype = wp_check_filetype($upload_dir['path'] . basename($image_url), null);
                                $attachment  = [
                                    'post_mime_type' => $wp_filetype['type'],
                                    'post_title'     => preg_replace('/\.[^.]+$/', '', $post->post_title),
                                    'post_content'   => '',
                                    'post_status'    => 'inherit',
                                ];

                                $attachment_id = wp_insert_attachment($attachment, $upload_dir['path'] . '/' . basename($image_url), $post_id);
                                rss_retrieval_disable_kses();
                                wp_update_post(
                                    [
                                        'ID'          => $attachment_id,
                                        'post_parent' => $post_id,
                                    ]
                                );
                                rss_retrieval_enable_kses();
                                if (! function_exists('wp_generate_attachment_metadata')) {
                                    require_once ABSPATH . 'wp-admin/includes/image.php';
                                }
                                if (! function_exists('media_handle_upload')) {
                                    require_once ABSPATH . 'wp-admin/includes/media.php';
                                }
                                $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload_dir['path'] . '/' . basename($image_url));
                                wp_update_attachment_metadata($attachment_id, $attachment_data);
                            }
                        }
                        remove_filter('intermediate_image_sizes_advanced', [$this, 'disable_thumbnails']);

                        if ($this->show_report) {
                            echo esc_html(esc_html(str_repeat(' ', 1024)));
                            flush();
                        }
                    }
                }
                rss_retrieval_disable_kses();
                wp_update_post($post);
                rss_retrieval_enable_kses();
                $this->log('Done');
            }
        }

        if (count($rss_retrieval_images_to_attach)) {
            $attach_ids = [];
            foreach ($rss_retrieval_images_to_attach as $image) {
                if (strlen(trim($image['url']))) {
                    if ($image['title'] !== '') {
                        $attach_id = rss_retrieval_add_image_to_library($image['url'], $image['title'], $post_id);
                    } else {
                        $attach_id = rss_retrieval_add_image_to_library($image['url'], stripslashes($this->post['post_title']), $post_id);
                    }
                    if ($attach_id) {
                        $attach_ids[] = $attach_id;
                    }
                }
            }
            if ($this->current_feed['options']['set_thumbnail'] === 'first_image') {
                $image_url = $attach_ids[0];
            } elseif ($this->current_feed['options']['set_thumbnail'] === 'last_image') {
                $image_url = $attach_ids[count($attach_ids) - 1];
            } elseif ($this->current_feed['options']['set_thumbnail'] === 'random_image') {
                $image_url = $attach_ids[wp_rand(0, count($attach_ids) - 1)];
            }
        }

        if ($this->current_feed['options']['set_thumbnail'] !== 'no_thumb') {

            if (! isset($image_url)) {
                if (preg_match_all('/src.?=.?["\']https:\/\/(www\.)?youtube\.com\/embed\/(.*?)["\'\?]/is', stripslashes($this->post['post_content'] . $this->post['post_excerpt']), $matches) && count($matches[2])) {
                    if ($this->current_feed['options']['set_thumbnail'] === 'first_image') {
                        $image_url = 'https://img.youtube.com/vi/' . $matches[2][0] . '/maxresdefault.jpg';
                    } elseif ($this->current_feed['options']['set_thumbnail'] === 'last_image') {
                        $image_url = 'https://img.youtube.com/vi/' . $matches[2][count($matches[2]) - 1] . '/maxresdefault.jpg';
                    } else {
                        $image_url = 'https://img.youtube.com/vi/' . $matches[2][wp_rand(0, count($matches[2]) - 1)] . '/maxresdefault.jpg';
                    }
                }
            }

            if ($this->current_feed['options']['set_thumbnail'] === 'alternative_image' || ! isset($image_url)) {
                if (strlen(trim($this->current_feed['options']['alt_post_thumbnail_src']))) {
                    $image_url = $this->current_feed['options']['alt_post_thumbnail_src'];
                } else {
                    $this->log('No alternative image source specified');
                }
            }

            if (! empty($image_url)) {
                if ($this->current_feed['options']['use_fifu'] !== 'on') {
                    $thumb_id = rss_retrieval_attach_post_thumbnail($post_id, $image_url, $this->post['post_title']);
                    if ($thumb_id !== false) {
                        $this->log('Post thumbnail successfully generated and saved to host');
                    }
                } elseif (function_exists('fifu_dev_set_image') && ($thumb_id = fifu_dev_set_image($post_id, $image_url))) {
                    if (rss_retrieval_is_binary($image_url)) {
                        $this->log('The post thumbnail is now handled by FIFU');
                    } else {
                        $this->log('The thumbnail source image was not found or broken');
                        $thumb_id = false;
                    }
                } else {
                    $this->log('The post thumbnail has not been set because FIFU is not active or the image source is invalid');
                }
            }

            if ($this->current_feed['options']['require_thumbnail'] === 'on' && empty($thumb_id)) {
                $this->log('The plugin was unable to generate a post thumbnail' . PHP_EOL);
                $this->delete_post_media($post_id);
                wp_delete_post($post_id, true);
                return;
            }

            if (isset($post_thumb_src)) {
                if (! add_post_meta($post_id, '_rssrtvr_thumb_source', $post_thumb_src)) {
                    $this->log('Can\'t save the post thumbnail source URL' . PHP_EOL);
                    $this->delete_post_media($post_id);
                    wp_delete_post($post_id, true);
                    return;
                }
            }
        }

        if (get_post_meta($post_id, '_rssrtvr_rss_source', true) === '') {
            $this->log('Unable to set the rss_retrieval_rss_source filed');
            $this->log('The post will not be added' . PHP_EOL);
            $this->delete_post_media($post_id);
            wp_delete_post($post_id, true);
            return;
        }

        ++$this->count;
        $this->log('New post title: ' . rss_retrieval_fix_white_spaces(trim(wp_strip_all_tags(stripslashes($this->post['post_title'])))));
        $this->log('New post ID: ' . esc_html($post_id) . PHP_EOL);

        if (has_action('wpml_switch_language')) {
            do_action('wpml_switch_language', null);
        }
    }

    function getCategoryIds($category_names) {
        global $wpdb;

        $cat_ids = [];
        if (is_array($category_names)) {
            foreach ($category_names as $cat_name) {
                if (function_exists('term_exists')) {
                    $cat_id = term_exists($cat_name, 'category');
                    if ($cat_id) {
                        $cat_ids[] = $cat_id['term_id'];
                    } elseif ($this->current_feed['options']['undefined_category'] === 'create_new') {
                        $term = wp_insert_term($cat_name, 'category');
                        if (! is_wp_error($term) && isset($term['term_id'])) {
                            $cat_ids[] = $term['term_id'];
                        }
                    }
                } else {
                    $cat_name_escaped = addslashes($cat_name);
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                    $results = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT cat_ID 
                            FROM {$wpdb->prefix}categories 
                            WHERE LOWER(cat_name) = LOWER(%s)",
                            $cat_name_escaped
                        )
                    );

                    if ($results) {
                        foreach ($results as $term) {
                            $cat_ids[] = (int) $term->cat_ID;
                        }
                    } elseif ($this->current_feed['options']['undefined_category'] === 'create_new') {
                        if (function_exists('wp_insert_category')) {
                            $cat_id = wp_insert_category(array('cat_name' => $cat_name));
                        } else {
                            $cat_name_sanitized = sanitize_title($cat_name);
                            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                            $wpdb->query(
                                $wpdb->prepare(
                                    "INSERT INTO {$wpdb->prefix}categories 
                                    SET cat_name = %s, category_nicename = %s",
                                    $cat_name_escaped,
                                    $cat_name_sanitized
                                )
                            );
                            $cat_id = $wpdb->insert_id;
                        }
                        $cat_ids[] = $cat_id;
                    }
                }
            }
        }
        if ((count($cat_ids) != 0)) {
            $cat_ids = array_unique($cat_ids);
        }
        return $cat_ids;
    }

    function categoryChecklist($post_id = 0, $descendents_and_self = 0, $selected_cats = false) {
        wp_category_checklist($post_id, $descendents_and_self, $selected_cats);
    }

    function showChangeBox($change_selected, $name) {
        if ($change_selected) {
            echo '<input type="checkbox" style="border-color: red;" name="' . 'chk_' . esc_html($name) . '" id="' . 'chk_' . esc_html($name) . '"> ';
        }
    }

    function showSettings($islocal, $settings, $change_selected = false) {
        ?>
        <form name="feed_settings" action="<?php echo esc_url(preg_replace('/\&edit-feed-id\=[0-9]+/', '', rss_retrieval_REQUEST_URI())); ?>" method="post">

            <ul class="tabs">
                <li class="active" rel="basic">Basic</li>
                <li rel="templates">Post templates</li>
                <li rel="advanced">Advanced</li>
                <li rel="media_handling">Media handling</li>
                <li rel="filtering">Content filtering</li>
            </ul>
            <div id="basic" class="tab_content">
                <br>
                <table class="form-table 
				<?php
                if ($change_selected) {
                    echo 'rssrtvr-form';
                }
                ?>
				">
                    <?php
                    if ($islocal) {
                    ?>
                        <tr>
                            <th scope="row">Feed title</th>
                            <td>
                                <input type="text" name="feed_title" style="width:100%" value="<?php echo ($this->edit_existing) ? esc_html($this->feeds[(int) $_GET['edit-feed-id']]['title']) : esc_html($this->feed_title); ?>">
                                <p class="description">A title of feed to be used in RSS Retriever Lite Syndicator.</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">Feed URL</th>
                            <td>
                                <?php
                                echo '<input type="text" name="url" style="width:100%" value="' . esc_html(esc_attr($this->current_feed_url)) . '">';
                                ?>
                                <p class="description">The URL of the feed.</p>
                            </td>
                        </tr>

                    <?php } ?>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'interval'); ?><?php echo esc_html__('Check for updates every', 'rss-retriever-lite'); ?></th>
                        <td>
                            <?php
                            echo '<input type="number" min="0" name="interval" value="' . esc_attr($settings['interval']) . '" size="4"> minutes.';
                            ?>
                            <p class="description">If you don't need automatic updates, set this parameter to 0.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'max_items'); ?><?php echo esc_html__('Maximum number of posts', 'rss-retriever-lite'); ?></th>
                        <td>
                            <?php
                            echo '<input type="number" min="0" name="max_items" value="' . esc_attr($settings['max_items']) . '" size="4">' . '<p class="description">Use low values to decrease the syndication time and improve SEO of your blog.</p>';
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'duplicate_check_method'); ?><?php echo esc_html__('Check for duplicates by', 'rss-retriever-lite'); ?></th>
                        <td>
                            <select name="duplicate_check_method">
                                <?php
                                echo '<option ' . (($settings['duplicate_check_method'] === 'link_and_title') ? 'selected ' : '') . 'value="link_and_title">Link and title</option>';
                                echo '<option ' . (($settings['duplicate_check_method'] === 'guid') ? 'selected ' : '') . 'value="guid">Link only</option>';
                                echo '<option ' . (($settings['duplicate_check_method'] === 'title') ? 'selected ' : '') . 'value="title">Title only</option>';
                                echo '<option ' . (($settings['duplicate_check_method'] === 'none') ? 'selected ' : '') . 'value="none">Don\'t check for duplicate posts</option>';
                                ?>
                            </select>
                            <p class="description">Choose the method to skip existing posts.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php $this->showChangeBox($change_selected, 'post_type'); ?>
                            <?php echo esc_html__('Post type', 'rss-retriever-lite'); ?>
                        </th>
                        <td>
                            <select name="post_type" id="rssrtvr-lite-post-type">
                                <?php
                                $post_types = get_post_types();
                                foreach ($post_types as $post_type) {
                                    echo '<option ' . esc_html(selected($settings['post_type'], $post_type, false)) . ' value="' . esc_html(esc_attr($post_type)) . '">' . esc_html(esc_html($post_type)) . '</option>';
                                }
                                ?>
                            </select>
                            <p class="description">Select WordPress <em>post type</em>.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'custom_taxonomies'); ?><?php echo esc_html__('Custom taxonomies', 'rss-retriever-lite'); ?></th>
                        <td>
                            <?php
                            $args       = [
                                'public'   => true,
                                '_builtin' => false,
                            ];
                            $taxonomies = get_taxonomies($args, 'objects', 'and');
                            foreach ($taxonomies as $taxonomy) {
                                if (isset($settings['custom_taxonomies'][$taxonomy->name])) {
                                    $value = $settings['custom_taxonomies'][$taxonomy->name];
                                } else {
                                    $value = '';
                                }
                                echo '<table id="custom_taxonomy_' . esc_attr($taxonomy->name) . '">';
                                echo '<tr>';
                                echo '<td style="padding:0px;">';
                                echo '<p class="description"><strong>' . esc_html($taxonomy->label) . '</strong> (separate with commas)</p>';
                                echo '<input type="text" size="120" name="custom_taxonomies[' . esc_attr($taxonomy->name) . ']" value="' . esc_attr($value) . '">';
                                echo '</td>';
                                echo '</tr>';
                                echo '</table>';
                            }
                            ?>
                            <table id="custom_taxonomy_undefined">
                                <tr>
                                    <td style="padding:0px">
                                        <input type="text" size="60" disabled value="No custom taxonomies defined for this post type.">
                                    </td>
                                </tr>
                            </table>
                            <p class="description">Assign WordPress <em>custom taxonomies</em>. Placeholders are allowed here [<a href="https://www.rssretriever.com/documentation/#custom-taxonomies" target="_blank">?</a>]</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'post_template'); ?><?php echo esc_html__('Custom post template', 'rss-retriever-lite'); ?></th>
                        <td>
                            <?php
                            $custom    = wp_get_theme()->get_post_templates();
                            $templates = ['Default'];
                            foreach ($custom as $post_type => $template) {
                                foreach ($template as $template_file => $name) {
                                    $templates[] = $template_file;
                                }
                            }
                            $templates = array_unique($templates, SORT_STRING);
                            echo '<select name="post_template">';
                            foreach ($templates as $name) {
                                echo '<option ' . (($settings['post_template'] === $name) ? 'selected ' : '') . 'value="' . esc_html($name) . '">' . esc_html($name) . '</option>';
                            }
                            echo '</select><br>';
                            ?>
                            <p class="description">Select a custom template file for a single post type.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'post_format'); ?><?php echo esc_html__('Post format', 'rss-retriever-lite'); ?></th>
                        <td>
                            <select name="post_format">
                                <?php
                                foreach (array('default', 'aside', 'gallery', 'link', 'image', 'quote', 'status', 'video', 'audio', 'chat') as $f) {
                                    echo '<option ' . (($settings['post_format'] === $f) ? 'selected ' : '') . 'value="' . esc_html($f) . '">' . esc_html($f) . '</option>';
                                }
                                ?>
                            </select>
                            <p class="description">Set the WordPress <em>post format</em>.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'post_author'); ?><?php echo esc_html__('Post author', 'rss-retriever-lite'); ?></th>
                        <td>
                            <select name="post_author">
                                <?php
                                $wp_user_search = get_users(array('role__in' => ['author', 'editor', 'administrator']));
                                foreach ($wp_user_search as $user) {
                                    echo '<option ' . ((intval($settings['post_author']) === intval($user->ID)) ? 'selected ' : '') . 'value="' . esc_attr($user->ID) . '">' . esc_html($user->display_name) . '</option>';
                                }
                                echo '<option ' . ((intval($settings['post_author']) === 0) ? 'selected ' : '') . 'value="0">&lt;random author&gt;</option>';
                                ?>
                            </select>
                            <p class="description">Assign the post author.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'post_lifetime'); ?><?php echo esc_html__('Post lifetime', 'rss-retriever-lite'); ?></th>
                        <td>
                            <?php
                            echo '<input type="number" min="0" name="post_lifetime" value="' . esc_attr($settings['post_lifetime']) . '" size="4"> hours.';
                            ?>
                            <p class="description">The period of time after which the post will be deleted. If you don't want to limit the post lifetime, set this parameter to 0.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'post_status'); ?><?php echo esc_html__('Post status', 'rss-retriever-lite'); ?></th>
                        <td>
                            <select name="post_status">
                                <?php
                                echo '<option ' . (($settings['post_status'] === 'publish') ? 'selected ' : '') . 'value="publish">Publish immediately</option>';
                                echo '<option ' . (($settings['post_status'] === 'pending') ? 'selected ' : '') . 'value="pending">Hold for review</option>';
                                echo '<option ' . (($settings['post_status'] === 'draft') ? 'selected ' : '') . 'value="draft">Save as draft</option>';
                                echo '<option ' . (($settings['post_status'] === 'private') ? 'selected ' : '') . 'value="private">Save as private</option>';
                                ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'base_date'); ?><?php echo esc_html__('Base date', 'rss-retriever-lite'); ?></th>
                        <td>
                            <select name="base_date">
                                <?php
                                echo '<option ' . (($settings['base_date'] === 'post') ? 'selected ' : '') . 'value="post">Get date from post</option>';
                                echo '<option ' . (($settings['base_date'] === 'syndication') ? 'selected ' : '') . 'value="syndication">Use syndication date</option>';
                                ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'date_range'); ?><?php echo esc_html__('Post date adjustment range', 'rss-retriever-lite'); ?></th>
                        <td>
                            <?php
                            echo '<input type="number" name="date_min" value="' . esc_attr($settings['date_min']) . '" size="6"> .. <input type="number" name="date_max" value="' . esc_attr($settings['date_max']) . '" size="6">';
                            ?>
                            minutes.
                            <p class="description">This range will be used to randomly adjust the publication date for every generated post. For example, if you set the adjustment range as <code>[0..60]</code>,
                                the post dates will be increased by a random value between 0 and 60 minutes. Negative values are allowed.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'post_category[]'); ?><?php echo esc_html__('Categories', 'rss-retriever-lite'); ?></th>
                        <td>
                            <ul id="categorychecklist" class="list:category categorychecklist form-no-clear">
                                <div id="categories-all" class="rssretriever-ui-tabs-panel">
                                    <?php
                                    $this->categoryChecklist(null, false, $settings['post_category']);
                                    ?>
                                </div>
                            </ul>
                            <p class="description">Assign the post to the selected categories.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'undefined_category'); ?><?php echo esc_html__('Undefined categories', 'rss-retriever-lite'); ?></th>
                        <td>
                            <select name="undefined_category">
                                <?php
                                if ($islocal) {
                                    echo '<option ' . (($settings['undefined_category'] === 'use_global') ? 'selected ' : '') . 'value="use_global">Use default settings</option>';
                                }
                                echo '<option ' . (($settings['undefined_category'] === 'use_default') ? 'selected ' : '') . 'value="use_default">Post to default WordPress category</option>';
                                echo '<option ' . (($settings['undefined_category'] === 'create_new') ? 'selected ' : '') . 'value="create_new">Create new categories defined in syndicating post</option>';
                                echo '<option ' . (($settings['undefined_category'] === 'drop') ? 'selected ' : '') . 'value="drop">Do not syndicate post that doesn\'t match at least one category defined above</option>';
                                ?>
                            </select>
                            <p class="description">This option defines what the RSS Retriever Lite syndicator have to do if none of the post categories mutch the predefined defined ones [<a href="https://www.rssretriever.com/documentation/#undefined-categories" target="_blank">?</a>]</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'create_tags'); ?><?php echo esc_html__('Tags from category names', 'rss-retriever-lite'); ?></th>
                        <td>
                            <?php
                            echo '<input type="checkbox" name="create_tags" id="create_tags" ' . (($settings['create_tags'] === 'on') ? 'checked ' : '') . '>';
                            ?>
                            <label for="create_tags">when checked, post category names will be added as post tags.</label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'post_tags'); ?><?php echo esc_html__('Post tags', 'rss-retriever-lite'); ?></th>
                        <td>
                            <?php
                            echo '<input type="text" style="width:100%;" name="post_tags" value="' . esc_html(stripslashes($settings['post_tags'])) . '">';
                            ?>
                            <p class="description">Separate with commas.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'auto_tags'); ?><?php echo esc_html__('Auto tags', 'rss-retriever-lite'); ?></th>
                        <td>
                            <?php
                            echo '<input type="checkbox" name="auto_tags" id="auto_tags" ' . (($settings['auto_tags'] === 'on') ? 'checked ' : '') . '>';
                            ?>
                            <label for="auto_tags">if checked, the RSS Retriever Lite plugin will look for existing tags within your content and add them automatically.</label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'tags_to_woocommerce'); ?><?php echo esc_html__('Tags to WooCommerce', 'rss-retriever-lite'); ?></th>
                        <td>
                            <?php
                            echo '<input type="checkbox" name="tags_to_woocommerce" id="tags_to_woocommerce" ' . (($settings['tags_to_woocommerce'] === 'on') ? 'checked ' : '') . '>';
                            ?>
                            <label for="tags_to_woocommerce">check this option to assign importing post tags to WooCommerce product tags.</label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'cats_to_woocommerce'); ?><?php echo esc_html__('Categories to WooCommerce', 'rss-retriever-lite'); ?></th>
                        <td>
                            <?php
                            echo '<input type="checkbox" name="cats_to_woocommerce" id="cats_to_woocommerce" ' . (($settings['cats_to_woocommerce'] === 'on') ? 'checked ' : '') . '>';
                            ?>
                            <label for="cats_to_woocommerce">check this option to assign importing post categories to WooCommerce product categories.</label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'comment_status'); ?><?php echo esc_html__('Comments', 'rss-retriever-lite'); ?></th>
                        <td>
                            <select name="comment_status">
                                <?php
                                echo '<option ' . (($settings['comment_status'] === 'open') ? 'selected ' : '') . 'value="open">Allow comments on syndicated posts</option>';
                                echo '<option ' . (($settings['comment_status'] === 'closed') ? 'selected ' : '') . 'value="closed">Disallow comments on syndicated posts</option>';
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'ping_status'); ?><?php echo esc_html__('Pings', 'rss-retriever-lite'); ?></th>
                        <td>
                            <select name="ping_status">
                                <?php
                                echo '<option ' . (($settings['ping_status'] === 'open') ? 'selected ' : '') . 'value="open">Accept pings</option>';
                                echo '<option ' . (($settings['ping_status'] === 'closed') ? 'selected ' : '') . 'value="closed">Don\'t accept pings</option>';
                                ?>
                            </select>
                        </td>
                    </tr>

                    <?php
                    if (function_exists('pll_languages_list')) {
                        $languages = pll_the_languages(
                            [
                                'hide_if_empty' => 0,
                                'raw'           => 1,
                            ]
                        );
                        if ($settings['polylang_language'] === '') {
                            $settings['polylang_language'] = pll_default_language();
                        }
                    } else {
                        $languages = [];
                    }
                    ?>
                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'polylang_language'); ?><?php echo esc_html__('Polylang language', 'rss-retriever-lite'); ?></th>
                        <td>
                            <select name="polylang_language"
                                <?php
                                if (! count($languages)) {
                                    echo 'disabled';
                                }
                                ?>>
                                <?php
                                if (count($languages)) {
                                    foreach ($languages as $l) {
                                        echo '<option ' . (($settings['polylang_language'] === $l['slug']) ? 'selected ' : '') . 'value = "' . esc_attr($l['slug']) . '">' . esc_attr($l['name']) . '</option>';
                                    }
                                } else {
                                    echo '<option value = "' . esc_attr($settings['polylang_language']) . '">The Polylang plugin is inactive</option>';
                                }
                                ?>
                            </select>
                            <p class="description">Assign a Polylang language to every post or page generated from this content source [<a href="https://www.rssretriever.com/documentation/#polylang-language" target="_blank">?</a>]</p>
                        </td>
                    </tr>

                    <?php
                    if (defined('ICL_SITEPRESS_VERSION')) {
                        $languages = apply_filters('wpml_active_languages', null, 'orderby=id&order=desc');
                        if ($settings['wpml_language'] === '') {
                            $settings['wpml_language'] = apply_filters('wpml_default_language', null);
                        }
                    } else {
                        $languages = [];
                    }
                    ?>
                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'wpml_language'); ?><?php echo esc_html__('WPML language', 'rss-retriever-lite'); ?></th>
                        <td>
                            <select name="wpml_language"
                                <?php
                                if (! count($languages)) {
                                    echo 'disabled';
                                }
                                ?>>
                                <?php
                                if (count($languages)) {
                                    foreach ($languages as $l) {
                                        echo '<option ' . (($settings['wpml_language'] === $l['language_code']) ? 'selected ' : '') . 'value = "' . esc_attr($l['language_code']) . '">' . esc_attr($l['native_name']) . '</option>';
                                    }
                                } else {
                                    echo '<option value = "' . esc_attr($settings['wpml_language']) . '">The WPML plugin is inactive</option>';
                                }
                                ?>
                            </select>
                            <p class="description">Assign a WPML language to every post or page generated from this content source [<a href="https://www.rssretriever.com/documentation/#wpml-language" target="_blank">?</a>]</p>
                        </td>
                    </tr>
                </table>
            </div>

            <div id="media_handling" class="tab_content">
                <br>
                <table class="form-table 
				<?php
                if ($change_selected) {
                    echo 'rssrtvr-form';
                }
                ?>
				">
                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'store_images'); ?><?php echo esc_html__('Store images locally', 'rss-retriever-lite'); ?></th>
                        <td>
                            <?php
                            echo '<input type="checkbox" name="store_images" id="store_images" ' . (($settings['store_images'] === 'on') ? 'checked ' : '') . '>';
                            ?>
                            <label for="store_images">if checked, all images will be copied into the default uploads folder [<a href="https://www.rssretriever.com/documentation/#store-images-locally" target="_blank">?</a>]</label>
                        </td>
                    </tr>

                    <th scope="row" id="image_format_selector"><?php $this->showChangeBox($change_selected, 'image_format'); ?>Convert PNG images to</th>
                    <td>
                        <select name="image_format" id="image_format">
                            <?php
                            echo '<option ' . (($settings['image_format'] === 'keep') ? 'selected ' : '') . 'value="keep">Do not convert</option>';
                            echo '<option ' . (($settings['image_format'] === 'jpeg') ? 'selected ' : '') . 'value="jpeg">JPEG</option>';
                            echo '<option ' . (($settings['image_format'] === 'webp') ? 'selected ' : '') . 'value="webp">WebP</option>';
                            ?>
                        </select>
                        &nbsp; <label for="compression_quality">Compression quality</label>
                        <input style="vertical-align: middle;" type="number" id="compression_quality" name="compression_quality" min="10" max="100" value="<?php echo esc_attr($settings['compression_quality']); ?>" size="4">
                        <p class="description">Select the format to which you want to convert locally stored PNG images.</p>
                    </td>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'add_to_media_library'); ?><?php echo esc_html__('Add to Media Library', 'rss-retriever-lite'); ?></th>
                        <td>
                            <?php
                            echo '<input type="checkbox" name="add_to_media_library" id="add_to_media_library" ' . (($settings['add_to_media_library'] === 'on') ? 'checked ' : '') . '>';
                            ?>
                            <label for="add_to_media_library">if checked, all images will be added to the WordPress Media Library [<a href="https://www.rssretriever.com/documentation/#add-to-media-library" target="_blank">?</a>]</label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><a name="media-attachments"></a><?php $this->showChangeBox($change_selected, 'insert_media_attachments'); ?><?php echo esc_html__('Media attachments', 'rss-retriever-lite'); ?></th>
                        <td>
                            <select name="insert_media_attachments">
                                <?php
                                echo '<option ' . (($settings['insert_media_attachments'] === 'no') ? 'selected ' : '') . 'value="no">Do not insert attachments</option>';
                                echo '<option ' . (($settings['insert_media_attachments'] === 'top') ? 'selected ' : '') . 'value="top">Insert attachments at top of post</option>';
                                echo '<option ' . (($settings['insert_media_attachments'] === 'bottom') ? 'selected ' : '') . 'value="bottom">Insert attachments at bottom of post</option>';
                                ?>
                            </select>
                            <p class="description">If enabled the RSS Retriever Lite syndicator inserts media attachments (if available) into the aggregating post. The
                                following types of attachments are supported: <code>&lt;media:content&gt;</code>, <code>&lt;media:thumbnail&gt;</code> and <code>&lt;enclosure&gt;</code>.
                                <br>All the aggregated images will contain <code>class="media_thumbnail"</code> in the <code>&lt;img&gt;</code> tag.
                            </p>
                        </td>
                    </tr>
                </table>

                <table class="form-table 
				<?php
                if ($change_selected) {
                    echo 'rssrtvr-form';
                }
                ?>
				">
                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'set_thumbnail'); ?><?php echo esc_html__('Post thumbnails', 'rss-retriever-lite'); ?></th>
                        <td>
                            <select id="set_thumbnail" name="set_thumbnail">
                                <?php
                                echo '<option ' . (($settings['set_thumbnail'] === 'no_thumb') ? 'selected ' : '') . 'value="no_thumb">Do not create</option>';
                                echo '<option ' . (($settings['set_thumbnail'] === 'first_image') ? 'selected ' : '') . 'value="first_image">Create from first post image</option>';
                                echo '<option ' . (($settings['set_thumbnail'] === 'last_image') ? 'selected ' : '') . 'value="last_image">Create from last post image</option>';
                                echo '<option ' . (($settings['set_thumbnail'] === 'random_image') ? 'selected ' : '') . 'value="random_image">Create from random post image</option>';
                                echo '<option ' . (($settings['set_thumbnail'] === 'media_attachment') ? 'selected ' : '') . 'value="media_attachment">Create from media attachment</option>';
                                echo '<option ' . (($settings['set_thumbnail'] === 'alternative_image') ? 'selected ' : '') . 'value="alternative_image">Use alternative source</option>';
                                ?>
                            </select>
                            <p class="description">Select the source image for the post thumbnail.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'use_fifu'); ?><?php echo esc_html__('Use FIFU', 'rss-retriever-lite'); ?></th>
                        <td>
                            <?php
                            echo '<input type="checkbox" name="use_fifu" id="use_fifu" ' . (($settings['use_fifu'] === 'on') ? 'checked ' : '') . '>';
                            echo '<label for="use_fifu">when checked, the post thumbnail won\'t be stored locally. It will be hotlinked and displayed by the <a href="https://wordpress.org/plugins/featured-image-from-url/" target="_blank">FIFU</a> plugin, which must be installed and activated.</label>';
                            if (! function_exists('fifu_dev_set_image')) {
                                echo '<p class="description" id="fifu_warning">&#x26A0; FIFU is not detected. If you enable this option, the post thumbnail will not be generated. Please install and activate FIFU first.</p>';
                            }
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'alt_post_thumbnail_src'); ?><?php echo esc_html__('Alternative thumbnail source', 'rss-retriever-lite'); ?></th>
                        <td>
                            <?php
                            echo '<input type="text" name="alt_post_thumbnail_src" style="margin:0;width:100%;" value="' . esc_html(stripslashes($settings['alt_post_thumbnail_src'])) . '" size="20">';
                            ?>
                            <p class="description">The alternative post thumbnail source URL for the case if the source image was not found.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'require_thumbnail'); ?><?php echo esc_html__('Post thumbnail is required', 'rss-retriever-lite'); ?></th>
                        <td>
                            <?php
                            echo '<input type="checkbox" name="require_thumbnail" id="require_thumbnail" ' . (($settings['require_thumbnail'] === 'on') ? 'checked ' : '') . '>';
                            ?>
                            <label for="require_thumbnail">if the plugin will not be able to create post thumbnail as specified above (e.g. the source image is missing or broken), the post will not be published.</label>
                        </td>
                    </tr>

                </table>
            </div>

            <div id="templates" class="tab_content">
                <br>
                <table class="form-table 
				<?php
                if ($change_selected) {
                    echo 'rssrtvr-form';
                }
                ?>
				">
                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'post_title_template'); ?><?php echo esc_html__('Post title', 'rss-retriever-lite'); ?></th>
                        <td>
                            <?php
                            echo '<textarea style="width:100%; height:5em; background-color:white;" wrap="on" name="post_title_template" id="post_title_template">' . esc_html(stripslashes($settings['post_title_template'])) . '</textarea>';
                            echo '<p class="description">Post title template. Make sure it\'s not empty. The default template value is <code>%post_title%</code>. Placeholders and shortcodes are available for this field.</p>';
                            if (! strlen($settings['post_title_template'])) {
                                echo '<p>&#x26A0; Your post title template is empty. This means that the post to be generated will not have a title.</p>';
                            }
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'post_slug_template'); ?><?php echo esc_html__('Post slug', 'rss-retriever-lite'); ?></th>
                        <td>
                            <?php
                            echo '<input type="text" name="post_slug_template" style="margin:0;width:100%;" value="' . esc_html(stripslashes($settings['post_slug_template'])) . '" size="20">';
                            ?>
                            <p class="description">Post slug template. By default it's the same as title, but you can alter it according to your needs.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'remove_emojis_from_slugs'); ?><?php echo esc_html__('Remove emojis from post slugs', 'rss-retriever-lite'); ?></th>
                        <td>
                            <?php
                            echo '<input type="checkbox" name="remove_emojis_from_slugs" id="remove_emojis_from_slugs" ' . (($settings['remove_emojis_from_slugs'] === 'on') ? 'checked ' : '') . '>';
                            ?>
                            <label for="remove_emojis_from_slugs">removes emoji symbols from post slugs.</label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'post_content_template'); ?><?php echo esc_html__('Post content', 'rss-retriever-lite'); ?></th>
                        <td>
                            <?php
                            echo '<textarea style="width:100%; height:20em; background-color:white;" wrap="on" name="post_content_template" id="post_content_template">' . esc_html(stripslashes($settings['post_content_template'])) . '</textarea>';
                            echo '<p class="description">Post content template. Define the desired HTML layout for your post body here. Make sure it\'s not empty. The default template value is <code>%post_content%</code>.</p>';
                            if (!strlen($settings['post_content_template'])) {
                                echo '<p>&#x26A0; Your post content template is empty. This means that the generated post will not have content.</p>';
                            }
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'post_excerpt_template'); ?><?php echo esc_html__('Post excerpt', 'rss-retriever-lite'); ?></th>
                        <td>
                            <?php
                            echo '<textarea style="width:100%; height:20em; background-color:white;" wrap="on" name="post_excerpt_template" id="post_excerpt_template">' . esc_html(stripslashes($settings['post_excerpt_template'])) . '</textarea>';
                            echo '<p class="description">Post excerpt template. Define the desired HTML layout for your post excerpt here. The default template value is <code>%post_excerpt%</code>.</p>';
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Available placeholders</th>
                        <td>
                            <div style="padding: 1em; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                                <ul style="margin-top: 0.5em;">
                                    <li><code>%link%</code> – <?php echo esc_html__('Post link (URL)', 'rss-retriever-lite'); ?></li>
                                    <li><code>%post_title%</code> – <?php echo esc_html__('Post title', 'rss-retriever-lite'); ?></li>
                                    <li><code>%post_content%</code> – <?php echo esc_html__('Post content (HTML)', 'rss-retriever-lite'); ?></li>
                                    <li><code>%post_content[<em>max_length</em>]%</code> – <?php echo esc_html__('Shortened post content (HTML, max characters)', 'rss-retriever-lite'); ?></li>
                                    <li><code>%post_content_notags%</code> – <?php echo esc_html__('Post content (plain text, no HTML tags)', 'rss-retriever-lite'); ?></li>
                                    <li><code>%post_content_notags[<em>num_words</em>]%</code> – <?php echo esc_html__('Shortened post content (plain text, max words)', 'rss-retriever-lite'); ?></li>
                                    <li><code>%post_excerpt%</code> – <?php echo esc_html__('Post excerpt (HTML)', 'rss-retriever-lite'); ?></li>
                                    <li><code>%post_excerpt[<em>max_length</em>]%</code> – <?php echo esc_html__('Shortened post excerpt (HTML, max characters)', 'rss-retriever-lite'); ?></li>
                                    <li><code>%post_excerpt_notags%</code> – <?php echo esc_html__('Post excerpt (plain text, no HTML tags)', 'rss-retriever-lite'); ?></li>
                                    <li><code>%post_excerpt_notags[<em>num_words</em>]%</code> – <?php echo esc_html__('Shortened post excerpt (plain text, max words)', 'rss-retriever-lite'); ?></li>
                                    <li><code>%enclosure_url%</code> – <?php echo esc_html__('Media enclosure URL', 'rss-retriever-lite'); ?></li>
                                    <li><code>%media_description%</code> – <?php echo esc_html__('Media description', 'rss-retriever-lite'); ?></li>
                                    <li><code>%media_thumbnail[<em>index</em>]%</code> – <?php echo esc_html__('Media thumbnail URL by index (0-based)', 'rss-retriever-lite'); ?></li>
                                    <li><code>%media_content[<em>index</em>]%</code> – <?php echo esc_html__('Media content URL by index (0-based)', 'rss-retriever-lite'); ?></li>
                                    <li><code>%youtube_video[<em>keyword</em>]%</code> – <?php echo esc_html__('Embed code for a YouTube video matching the keyword', 'rss-retriever-lite'); ?></li>
                                    <li><code>%post_guid%</code> – <?php echo esc_html__('Post GUID', 'rss-retriever-lite'); ?></li>
                                    <li><code>%post_date%</code> – <?php echo esc_html__('Post date (default format)', 'rss-retriever-lite'); ?></li>
                                    <li><code>%post_date[<em>format</em>]%</code> – <?php echo esc_html__('Post date (custom PHP date format, e.g. Y-m-d)', 'rss-retriever-lite'); ?></li>
                                    <li><code>%categories%</code> – <?php echo esc_html__('Comma-separated post categories', 'rss-retriever-lite'); ?></li>
                                    <li><code>%xml_tags[<em>tag_name</em>]%</code> – <?php echo esc_html__('Value of an XML tag from the feed', 'rss-retriever-lite'); ?></li>
                                    <li><code>%xml_tags_attr[<em>tag_name</em>][<em>attribute</em>]%</code> – <?php echo esc_html__('Attribute value of an XML tag', 'rss-retriever-lite'); ?></li>
                                </ul>
                            </div>
                            <p class="description">
                                <strong>Usage tips:</strong> Placeholders can be used in post title, slug, content, and excerpt templates. For indexed placeholders, use a number inside brackets (e.g. <code>%media_thumbnail[0]</code>). For custom date formats, use PHP date format strings (e.g. <code>%post_date[Y-m-d]</code>). For XML tags and attributes, use the tag name and attribute name (e.g. <code>%xml_tags[author]</code>, <code>%xml_tags_attr[media:content][url]</code>)
                                [<a href="https://www.rssretriever.com/documentation/#templates" target="_blank">?</a>]
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <div id="advanced" class="tab_content">
                <br>
                <table class="form-table 
				<?php
                if ($change_selected) {
                    echo 'rssrtvr-form';
                }
                ?>
				">
                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'strip_tags'); ?><?php echo esc_html__('HTML tags to strip', 'rss-retriever-lite'); ?></th>
                        <td>
                            <?php
                            echo '<input type="text" name="strip_tags" style="margin:0;width:100%;" value="' . esc_html(stripslashes($settings['strip_tags'])) . '" size="20">';
                            ?>
                            <p class="description">Enter a comma-separated list of tags to remove from the generated posts, e.g.: <code>a, h1, img</code>.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'utf8_encoding'); ?><?php echo esc_html__('UTF-8 encoding', 'rss-retriever-lite'); ?></th>
                        <td>
                            <?php
                            echo '<input type="checkbox" name="utf8_encoding" id="utf8_encoding" ' . (($settings['utf8_encoding'] === 'on') ? 'checked ' : '') . '>';
                            ?>
                            <label for="utf8_encoding">enables UTF-8 encoding. This option converts an ISO-8859-1 string to UTF-8 that may be required when parsing the XML/RSS feeds containing invalid UTF-8 start bytes e.g. <code>
                                    <0x92>
                                </code>.</label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'convert_encoding'); ?><?php echo esc_html__('Convert character encoding', 'rss-retriever-lite'); ?></th>
                        <td>
                            <?php
                            echo '<input type="checkbox" name="convert_encoding" id="convert_encoding" ' . (($settings['convert_encoding'] === 'on') ? 'checked ' : '') . '>';
                            ?>
                            <label for="convert_encoding">enables character encoding conversion. This option may be useful for parsing XML/RSS feeds in national character sets other than UTF-8.</label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'sanitize'); ?><?php echo esc_html__('Sanitize content', 'rss-retriever-lite'); ?></th>
                        <td>
                            <?php
                            echo '<input type="checkbox" name="sanitize" id="sanitize" ' . (($settings['sanitize'] === 'on') ? 'checked ' : '') . '>';
                            ?>
                            <label for="sanitize">sanitize content for allowed HTML tags.</label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'translator'); ?><?php echo esc_html__('Translation', 'rss-retriever-lite'); ?></th>
                        <td>
                            <select name="translator" id="rssrtvr-lite-translator">
                                <?php
                                echo '<option ' . esc_html(selected($settings['translator'], 'none', false)) . ' value="none">Do not translate</option>';
                                echo '<option ' . esc_html(selected($settings['translator'], 'deepl_translate', false)) . ' value="deepl_translate">Use DeepL Translate</option>';
                                echo '<option ' . esc_html(selected($settings['translator'], 'yandex_translate', false)) . ' value="yandex_translate">Use Yandex Translate</option>';
                                echo '<option ' . esc_html(selected($settings['translator'], 'google_translate', false)) . ' value="google_translate">Use Google Translate</option>';
                                ?>
                            </select>
                            <p class="description"><strong>Important</strong>: if the plugin will not be able to translate the article, the post won't be added.</p>

                            <div id="yandex_translate_settings">
                                <table class="rssrtvr-box8">
                                    <tr>
                                        <th><?php $this->showChangeBox($change_selected, 'yandex_translation_dir'); ?>Direction</th>
                                        <td><select name="yandex_translation_dir">
                                                <?php
                                                $langs = $this->langs['YANDEX_TRANSLATE_LANGS'];
                                                asort($langs);
                                                foreach ($langs as $dir => $lang) {
                                                    echo '<option ' . (($settings['yandex_translation_dir'] == $dir) ? 'selected ' : '') . 'value="' . esc_html($dir) . '">' . esc_html($lang) . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <div id="google_translate_settings">
                                <table class="rssrtvr-box8">
                                    <tr>
                                        <th><?php $this->showChangeBox($change_selected, 'google_translation_source'); ?>Source</th>
                                        <td><select name="google_translation_source">
                                                <?php
                                                $langs = $this->langs['GOOGLE_TRANSLATE_LANGS'];
                                                asort($langs);
                                                foreach ($langs as $dir => $lang) {
                                                    echo '<option ' . (($settings['google_translation_source'] == $dir) ? 'selected ' : '') . 'value="' . esc_html($dir) . '">' . esc_html($lang) . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><?php $this->showChangeBox($change_selected, 'google_translation_target'); ?>Target</th>
                                        <td><select name="google_translation_target">
                                                <?php
                                                foreach ($langs as $dir => $lang) {
                                                    echo '<option ' . (($settings['google_translation_target'] == $dir) ? 'selected ' : '') . 'value="' . esc_html($dir) . '">' . esc_html($lang) . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <div id="deepl_translate_settings">
                                <table class="rssrtvr-box8">
                                    <tr>
                                        <th><?php $this->showChangeBox($change_selected, 'deepl_translation_target'); ?>Target language</th>
                                        <td>
                                            <select name="deepl_translation_target">
                                                <?php
                                                $langs = $this->langs['DEEPL_TRANSLATE_LANGS'];
                                                foreach ($langs as $dir => $lang) {
                                                    echo '<option ' . (($settings['deepl_translation_target'] == $dir) ? 'selected ' : '') . 'value="' . esc_html($dir) . '">' . esc_html($lang) . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><?php $this->showChangeBox($change_selected, 'deepl_use_api_free'); ?>Use DeepL API Free</th>
                                        <td>
                                            <input type="checkbox" name="deepl_use_api_free" id="deepl_use_api_free"
                                                <?php
                                                if ($settings['deepl_use_api_free'] === 'on') {
                                                    echo 'checked';
                                                }
                                                ?> />
                                            <label for="deepl_use_api_free">DeepL API Free is a variant of our DeepL API Pro plan that allows developers to translate up to 500,000 characters per month for free.</label>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'custom_fields'); ?>Custom fields</th>
                        <td>
                            <?php
                            echo '<textarea cols="90" rows="10" id="custom_fields" name="custom_fields" style="margin:0;height:10em;width:100%;">' . stripslashes($settings['custom_fields']) . '</textarea>';
                            ?>

                            <p class="description">
                                Assign XML tag values to the custom fields of the imported post. One rule per line
                                <a href="https://www.rssretriever.com/documentation/#custom-fields" target="_blank">[?]</a>
                            </p>

                            <p class="description">
                                Format:<br>
                                <code>xml_tag_name-&gt;custom_field_name</code><br>
                                The tag name on the left must match the XML source. The field name on the right must be an existing WordPress custom field (meta key).
                            </p>

                            <p class="description">
                                Example:<br>
                                <code>title-&gt;my_custom_title</code><br>
                                This rule saves the value of the <code>&lt;title&gt;</code> XML tag into the <code>my_custom_title</code> custom field.<br>
                                If you are using plugins like WooCommerce, their meta keys often start with an underscore (e.g. <code>_price</code>).
                            </p>

                        </td>
                    </tr>

                    <?php $this->showExpertBox($settings, true, $change_selected); ?>
            </div>

            <div id="filtering" class="tab_content">
                <br>
                <table class="form-table 
				<?php
                if ($change_selected) {
                    echo 'rssrtvr-form';
                }
                ?>
				">
                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'filter_post_title'); ?><?php echo esc_html__('Apply filtering to', 'rss-retriever-lite'); ?></th>
                        <td>
                            <?php
                            echo '<input type="checkbox" name="filter_post_title" id="filter_post_title" ' . (($settings['filter_post_title'] === 'on') ? 'checked ' : '') . '> <label for="filter_post_title">post title</label> &nbsp; ';
                            echo '<input type="checkbox" name="filter_post_content" id="filter_post_content" ' . (($settings['filter_post_content'] === 'on') ? 'checked ' : '') . '> <label for="filter_post_content">post content</label> &nbsp; ';
                            echo '<input type="checkbox" name="filter_post_excerpt" id="filter_post_excerpt" ' . (($settings['filter_post_excerpt'] === 'on') ? 'checked ' : '') . '> <label for="filter_post_excerpt">post excerpt</label> &nbsp; ';
                            echo '<input type="checkbox" name="filter_post_link" id="filter_post_link" ' . (($settings['filter_post_link'] === 'on') ? 'checked ' : '') . '> <label for="filter_post_link">post link</label> ';
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'filter_any_phrases'); ?><?php echo esc_html__('Must contain any of these keywords', 'rss-retriever-lite'); ?></th>
                        <td>
                            <?php echo '<input type="text" style="width:100%" name="filter_any_phrases" value="' . esc_html(stripslashes($settings['filter_any_phrases'])) . '">'; ?>
                            <p class="description">Separate keywords and phrases with commas.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'filter_all_phrases'); ?><?php echo esc_html__('Must contain all these keywords', 'rss-retriever-lite'); ?></th>
                        <td>
                            <?php echo '<input type="text" style="width:100%" name="filter_all_phrases" value="' . esc_html(stripslashes($settings['filter_all_phrases'])) . '">'; ?>
                            <p class="description">Separate keywords and phrases with commas.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'filter_none_phrases'); ?><?php echo esc_html__('Must contain none of these keywords', 'rss-retriever-lite'); ?></th>
                        <td>
                            <?php echo '<input type="text" style="width:100%" name="filter_none_phrases" value="' . esc_html(stripslashes($settings['filter_none_phrases'])) . '"'; ?>
                            <p class="description">Separate keywords and phrases with commas.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'filter_any_tags'); ?><?php echo esc_html__('Must contain any of these tags', 'rss-retriever-lite'); ?></th>
                        <td>
                            <?php echo '<input type="text" style="width:100%" name="filter_any_tags" value="' . esc_html(stripslashes($settings['filter_any_tags'])) . '"'; ?>
                            <p class="description">Separate tags and phrases with commas.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'filter_none_tags'); ?><?php echo esc_html__('Must contain none of these tags', 'rss-retriever-lite'); ?></th>
                        <td>
                            <?php echo '<input type="text" style="width:100%" name="filter_none_tags" value="' . esc_html(stripslashes($settings['filter_none_tags'])) . '">'; ?>
                            <p class="description">Separate tags and phrases with commas.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'filter_days_newer'); ?><?php echo esc_html__('Must be newer than', 'rss-retriever-lite'); ?></th>
                        <td>
                            <?php echo '<input type="number" min="0" name="filter_days_newer" value="' . esc_html(stripslashes($settings['filter_days_newer'])) . '" size="3"> day(s).'; ?>
                            <p class="description">Specify the date of a news publication in the feed (if present). Use 0 to not filter by date.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'filter_days_older'); ?><?php echo esc_html__('Must be older than', 'rss-retriever-lite'); ?></th>
                        <td>
                            <?php echo '<input type="number" min="0" name="filter_days_older" value="' . esc_html(stripslashes($settings['filter_days_older'])) . '" size="3"> day(s).'; ?>
                            <p class="description">Specify the date of a news publication in the feed (if present). Use 0 to not filter by date.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'filter_post_longer'); ?><?php echo esc_html__('Must be longer than', 'rss-retriever-lite'); ?></th>
                        <td>
                            <?php echo '<input type="number" min="0" name="filter_post_longer" value="' . esc_html(stripslashes($settings['filter_post_longer'])) . '" size="3"> character(s).'; ?>
                            <p class="description">Specify the the minimum post length. Use 0 for any size.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php $this->showChangeBox($change_selected, 'filter_post_shorter'); ?><?php echo esc_html__('Must be shorter than', 'rss-retriever-lite'); ?></th>
                        <td>
                            <?php echo '<input type="number" min="0" name="filter_post_shorter" value="' . esc_html(stripslashes($settings['filter_post_shorter'])) . '" size="3"> character(s).'; ?>
                            <p class="description">Specify the the maximum post length. Use 0 for any size.</p>
                        </td>
                    </tr>
                </table>
                <br>
                <p class="description">The filtering routine is case-insensitive and it does not match full words. Thus please be careful when using short keywords that may unintentionally match parts of other words.</p>
            </div>
            <?php
            echo '<div class="submit">';
            if (isset($_POST['modify_selected_feeds']) && check_admin_referer('rss_retrieval_xml_syndicator')) {
                echo '<input type="hidden" name="feed_ids" value="' . esc_attr(base64_encode(serialize($_POST['feed_ids']))) . '">';
                echo '<input class="button-primary" name="apply_settings_to_selected_feeds" value="' . esc_html(esc_attr__('Apply these settings to selected feeds', 'rss-retriever-lite')) . '" type="submit">&nbsp;&nbsp;';
                echo '<input class="button" name="cancel" value="' . esc_html(esc_attr__('Cancel', 'rss-retriever-lite')) . '" type="submit">';
            } elseif ($islocal) {
                if ($this->edit_existing) {
                    echo '<input class="button-primary" name="update_feed_settings" value="' . esc_html(esc_attr__('Update Feed Settings', 'rss-retriever-lite')) . '" type="submit">&nbsp;&nbsp;';
                    echo '<input class="button" name="cancel" value="' . esc_html(esc_attr__('Cancel', 'rss-retriever-lite')) . '" type="submit">';
                    echo '<input type="hidden" name="feed_id" value="' . (int) $_GET['edit-feed-id'] . '">';
                } else {
                    echo '<input class="button-primary" name="syndicate_feed" value="' . esc_html(esc_attr__('Syndicate This Feed', 'rss-retriever-lite')) . '" type="submit">&nbsp;&nbsp;';
                    echo '<input class="button" name="cancel" value="' . esc_html(esc_attr__('Cancel', 'rss-retriever-lite')) . '" type="submit">';
                    echo '<input type="hidden" name="feed_url" value="' . esc_html(esc_attr($this->current_feed_url)) . '">';
                }
            } else {
                echo '<input class="button-primary" name="update_default_settings" value="' . esc_html(esc_attr__('Update default settings', 'rss-retriever-lite')) . '" type="submit">&nbsp;';
                echo '<input class="button" name="cancel" value="' . esc_html(esc_attr__('Cancel', 'rss-retriever-lite')) . '" type="submit">';
            }
            echo '</div>';
            wp_nonce_field('rss_retrieval_xml_syndicator');
            ?>
        </form>
    <?php
    }

    function getUpdateTime($id) {
        $time     = time();
        $interval = 60 * (int) $this->feeds[$id]['options']['interval'];
        if (isset($this->feeds_updated[$id])) {
            $updated = (int) $this->feeds_updated[$id];
        } else {
            $updated = (int) $this->feeds[$id]['updated'];
        }
        if (intval($this->feeds[$id]['options']['interval']) === 0) {
            return 'never';
        } elseif (intval($this->feeds[$id]['options']['max_items']) === 0) {
            return 'skip';
        } elseif (($time - $updated) >= $interval) {
            return 'asap';
        } else {
            return 'in ' . round(($interval - ($time - $updated)) / 60) . ' minutes';
        }
    }

    function showExpertBox($settings, $full_menu = false, $change_selected = false) {
    ?>
        <table class="form-table 
		<?php
        if ($change_selected) {
            echo 'rssrtvr-form';
        }
        ?>
		">
            <tr>
                <th scope="row"><?php $this->showChangeBox($change_selected, 'user_agent'); ?><?php echo esc_html__('User agent', 'rss-retriever-lite'); ?></th>
                <td>
                    <?php
                    echo '<input type="text" style="width:100%" name="user_agent" value="' . esc_html(stripslashes($settings['user_agent'])) . '">';
                    ?>
                    <p class="description">Use this field to set a user agent [<a href="https://www.rssretriever.com/documentation/#user-agent" target="_blank">?</a>]</p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php $this->showChangeBox($change_selected, 'http_headers'); ?><?php echo esc_html__('HTTP headers', 'rss-retriever-lite'); ?></th>
                <td>
                    <?php
                    echo '<textarea cols="90" rows="10" wrap="off" name="http_headers" style="margin:0;height:10em;width:100%;">' . esc_html(stripslashes($settings['http_headers'])) . '</textarea>';
                    ?>
                    <p class="description">HTTP headers from a request follow this basic structure of an HTTP header: a case-insensitive string followed
                        by a colon (':') and a value whose structure depends upon the header. One header per line.</p>
                </td>
            </tr>

        </table>
    <?php
    }

    function showMainPage($showsettings = true) {
    ?>
        <div class="metabox-holder postbox-container" style="width:100%;">
            <form style="padding: 22px 12px 22px 12px; margin-bottom: 24px;" action="<?php echo esc_url(rss_retrieval_REQUEST_URI()); ?>" method="post">
                <div style="display: flex; justify-content: space-between; width: 100%;">
                    <div style="align-self: center;"><strong>New RSS URL</strong></div>

                    <div style="flex-grow: 1; margin-left: 10px; margin-right: 10px;">
                        <input type="text" name="feed_url" value="" style="width: 100%;">
                    </div>

                    <div style="align-self: center;">
                        <input class="button-primary" name="new_feed" value="<?php echo esc_attr__('&nbsp; Syndicate &raquo; &nbsp;', 'rss-retriever-lite'); ?>" type="submit">
                    </div>
                </div>
                <?php wp_nonce_field('rss_retrieval_xml_syndicator'); ?>
            </form>

            <form id="syndycated_feeds" action="<?php echo esc_url(rss_retrieval_REQUEST_URI()); ?>" method="post">
                <?php
                if (count($this->feeds) > 0) {
                    $display_feeds = [];
                    for ($i = 0; $i < count($this->feeds); $i++) {
                        $feed_item  = '<tr>';
                        $feed_item .= '<th><input name="feed_ids[]" value="' . $i . '" type="checkbox" id="f' . $i . '"></th>';
                        $edit_url = wp_nonce_url(rss_retrieval_REQUEST_URI() . '&edit-feed-id=' . $i, 'rss_retrieval_xml_syndicator');
                        $feed_item .= '<td><label for="f' . $i . '">' . esc_html($this->feeds[$i]['title']) . ' [<a href="' . esc_url($edit_url) . '">edit</a>]</label></td>';
                        $feed_item .= '<td><a href="' . $this->feeds[$i]['url'] . '" target="_blank">' . rss_retrieval_chop_str(htmlspecialchars($this->feeds[$i]['url']), 100) . '</a></td>';
                        $feed_item .= '<td>' . $this->getUpdateTime($i) . '</td>';
                        if (isset($this->feeds_updated[$i])) {
                            $last_update = $this->feeds_updated[$i];
                        } else {
                            $last_update = $this->feeds[$i]['updated'];
                        }
                        if ($last_update) {
                            $feed_item .= '<td>' . round((time() - $last_update) / 60) . ' minutes ago</td>';
                        } else {
                            $feed_item .= '<td> - </td>';
                        }
                        $feed_item      .= '</tr>';
                        $display_feeds[] = '<!--' . $this->feeds[$i]['title'] . $i . '-->' . $feed_item . PHP_EOL;
                    }
                    echo '<table class="widefat" style="margin-top: .5em" width="100%">';
                    echo '<tr style="background: #f0f0f0;">';
                    echo '<th scope="row" style="width:3%;"><input type="checkbox" onclick="rss_retrieval_CheckAllLs(document.getElementById(\'syndycated_feeds\'));"></th>';
                    echo '<th scope="row" style="font-weight: 600; width:25%;">' . esc_html__('Feed title', 'rss-retriever-lite') . '</th>';
                    echo '<th scope="row" style="font-weight: 600; width:50%;">' . esc_html__('URL', 'rss-retriever-lite') . '</th>';
                    echo '<th scope="row" style="font-weight: 600; width:10%;">' . esc_html__('Next update', 'rss-retriever-lite') . '</th>';
                    echo '<th scope="row" style="font-weight: 600; width:12%;">' . esc_html__('Last update', 'rss-retriever-lite') . '</th>';
                    echo '</tr>';
                    $i = 0;
                    foreach ($display_feeds as $item) {
                        if ($i++ % 2) {
                            $item = str_replace('<tr>', '<tr class="alternate">', $item);
                        }
                        echo wp_kses($item, [
                            'tr'     => ['class' => []],
                            'th'     => ['scope' => [], 'style' => []],
                            'td'     => ['style' => []],
                            'label'  => ['for' => []],
                            'a'      => ['href' => [], 'target' => []],
                            'input'  => ['name' => [], 'value' => [], 'type' => [], 'id' => [], 'onclick' => []],
                            'table'  => ['class' => [], 'style' => [], 'width' => []],
                        ]);
                    }
                    echo '</table>';
                ?>
                    &nbsp;
                    <table width="100%">
                        <tr>
                            <td>
                                <div align="left">
                                    <input class="button-primary" name="check_for_updates"
                                        value="<?php echo esc_attr__('&#x23E9; Pull selected feeds now!', 'rss-retriever-lite'); ?>"
                                        type="submit">
                                    <input class="button secondary" name="modify_selected_feeds"
                                        value="<?php echo esc_attr__('Mass modify selected feeds', 'rss-retriever-lite'); ?>"
                                        type="submit">
                                    <input class="button secondary" name="shuffle_update_time"
                                        value="<?php echo esc_attr__('Shuffle update times', 'rss-retriever-lite'); ?>"
                                        type="submit">
                                </div>
                            </td>
                            <td>
                                <div align="right">
                                    <input class="button secondary" name="delete_feeds_and_posts"
                                        value="<?php echo esc_attr__('&#x274C; Delete selected feeds and syndicated posts', 'rss-retriever-lite'); ?>"
                                        type="submit"
                                        onclick="return confirm('<?php echo esc_js(__('Delete selected feeds and syndicated posts?', 'rss-retriever-lite')); ?>')">
                                    <input class="button secondary" name="delete_feeds"
                                        value="<?php echo esc_attr__('&#x274C; Delete selected feeds', 'rss-retriever-lite'); ?>"
                                        type="submit"
                                        onclick="return confirm('<?php echo esc_js(__('Delete selected feeds?', 'rss-retriever-lite')); ?>')">
                                    <input class="button secondary" name="delete_posts"
                                        value="<?php echo esc_attr__('&#x274C; Delete posts syndicated from selected feeds', 'rss-retriever-lite'); ?>"
                                        type="submit"
                                        onclick="return confirm('<?php echo esc_js(__('Delete posts syndicated from selected feeds?', 'rss-retriever-lite')); ?>')">
                                </div>
                            </td>
                        </tr>
                    </table>
                <?php
                }
                ?>
                <table width="100%">
                    <tr>
                        <td>
                            <div align="right">
                                <br>
                                <input class="button-primary" name="alter_default_settings"
                                    value="<?php echo esc_attr__('Alter default settings', 'rss-retriever-lite'); ?>"
                                    type="submit">
                            </div>
                        </td>
                    </tr>
                </table>
                <?php
                wp_nonce_field('rss_retrieval_xml_syndicator');
                ?>
            </form>
            <?php
            if ($showsettings) {
                $this->showSettings(false, $this->global_options);
            }
            ?>
        </div>
<?php
    }

    function enqueue_scripts($hook) {
        if (
            $hook !== 'toplevel_page_rssretriever_lite' &&
            $hook !== 'rss-retriever-lite_page_rss_retrieval_general_settings' &&
            $hook !== 'rss-retriever-lite_page_rss_retrieval_accounts' &&
            $hook !== 'rss-retriever-lite_page_rss_retrieval_syndicator_log'
        ) {
            return;
        }

        wp_enqueue_style(
            'rssrtvr-lite-admin',
            plugin_dir_url(__FILE__) . 'assets/admin.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'rssrtvr-lite-admin',
            plugin_dir_url(__FILE__) . 'assets/admin.js',
            ['jquery'],
            '1.0.0',
            true
        );

        wp_enqueue_code_editor(['type' => 'text/html']);
        wp_enqueue_script('wp-theme-plugin-editor');
        wp_enqueue_style('wp-codemirror');
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('codemirror-autorefresh');

        $args       = [
            'public'   => true,
            '_builtin' => false,
        ];
        $taxonomies = get_taxonomies($args, 'objects', 'and');
        $map        = [];

        foreach ($taxonomies as $taxonomy) {
            foreach ($taxonomy->object_type as $object_type) {
                $map[$object_type][] = $taxonomy->name;
            }
        }

        wp_localize_script(
            'rssrtvr-lite-admin',
            'rss_retrieval_vars',
            [
                'rss_pull_mode' => get_option(rss_retrieval_RSS_PULL_MODE),
                'post_type_map' => $map,
            ]
        );
    }

    function cron_init() {
        if (get_option(rss_retrieval_RSS_PULL_MODE) === 'auto') {
            add_action('rss_retrieval_update_by_wp_cron', [$this, 'update_feeds']);
            if (! wp_next_scheduled('rss_retrieval_update_by_wp_cron')) {
                wp_schedule_event(time(), rss_retrieval_PC_NAME, 'rss_retrieval_update_by_wp_cron');
            }
        } elseif (function_exists('wp_clear_scheduled_hook') && wp_next_scheduled('rss_retrieval_update_by_wp_cron')) {
            wp_clear_scheduled_hook('rss_retrieval_update_by_wp_cron');
        }
    }
}

if (is_admin()) {
    rss_retrieval_default_options();
}

$rssrtvr_lite = new rss_retrieval_Syndicator();

if (! is_admin()) {
    add_action('wp_loaded', [$rssrtvr_lite, 'cron_init']);
    add_filter('cron_schedules', [$rssrtvr_lite, 'add_custom_cron_interval']);

    $pull_feeds = isset($_GET['pull-feeds']) ? sanitize_text_field(wp_unslash($_GET['pull-feeds'])) : '';

    if ($pull_feeds === get_option('rss_retrieval_CRON_MAGIC')) {
        if (! function_exists('wp_insert_category')) {
            require_once ABSPATH . 'wp-admin/includes/taxonomy.php';
        }

        add_action('shutdown', [$rssrtvr_lite, 'update_feeds']);
    }

    if ((time() - get_option(rss_retrieval_POST_LIFE_CHECK_DATE)) > rss_retrieval_POST_LIFE_CHECK_PERIOD) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $post_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT post_id 
                FROM {$wpdb->prefix}postmeta 
                WHERE meta_key = %s 
                AND meta_value < %d 
                AND meta_value NOT IN (0, 2147483647)",
                '_rssrtvr_post_lifetime',
                time()
            )
        );
        if (count($post_ids) > 0) {
            foreach ($post_ids as $post_id) {
                $rssrtvr_lite->delete_post_media($post_id);
                wp_delete_post($post_id, true);
            }
        }
        update_option(rss_retrieval_POST_LIFE_CHECK_DATE, time());
    }
} else {
    add_action('admin_bar_menu', 'rss_retrieval_add_admin_menu_item');
    add_action('admin_enqueue_scripts', [$rssrtvr_lite, 'enqueue_scripts']);
    add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'rss_retrieval_plugins_action_link');

    if (get_option(rss_retrieval_KEEP_IMAGES) !== 'on') {
        add_action('before_delete_post', [$rssrtvr_lite, 'on_before_delete_post']);
    }
}

function rss_retrieval_main_menu() {
    add_menu_page(
        esc_html__('RSS Syndicator', 'rss-retriever-lite'),
        esc_html__('RSS Retriever Lite', 'rss-retriever-lite'),
        'manage_options',
        'rssretriever_lite',
        'rss_retrieval_xml_syndicator_menu',
        'dashicons-rss'
    );

    add_submenu_page(
        'rssretriever_lite',
        esc_html__('Settings', 'rss-retriever-lite'),
        esc_html__('Settings', 'rss-retriever-lite'),
        'manage_options',
        'rss_retrieval_general_settings',
        'rss_retrieval_options_menu'
    );

    add_submenu_page(
        'rssretriever_lite',
        esc_html__('Accounts', 'rss-retriever-lite'),
        esc_html__('Accounts', 'rss-retriever-lite'),
        'manage_options',
        'rss_retrieval_accounts',
        'rss_retrieval_accounts_menu'
    );

    add_submenu_page(
        'rssretriever_lite',
        esc_html__('Syndicator log', 'rss-retriever-lite'),
        esc_html__('Syndicator log', 'rss-retriever-lite'),
        'manage_options',
        'rss_retrieval_syndicator_log',
        'rss_retrieval_syndicator_log_menu'
    );

    add_submenu_page(
        'rssretriever_lite',
        esc_html__('Go Pro', 'rss-retriever-lite'),
        esc_html__('Go Pro', 'rss-retriever-lite'),
        'manage_options',
        'https://www.rssretriever.com/',
        null
    );
}

if (is_admin()) {
    add_action('admin_menu', 'rss_retrieval_main_menu');
}
?>