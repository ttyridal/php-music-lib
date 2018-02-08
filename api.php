<?php
/* php-music-lib - server backend
 *  Copyright (C) 2018  Torbjorn Tyridal
 *
 *  Licensed under the AGPL, see LICENCE file
 */
header('Content-Type: text/plain');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT'); // Date in the past

require('config.php');
require('urljoin.php');
define(JSON_OPTS, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

function self_url() {
    return (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' .
        $_SERVER['HTTP_HOST']. $_SERVER['DOCUMENT_URI'];
}

function m3u8_encode($items, $opts=0) {
    $base = self_url();
    return "#EXTM3U\n" . join("\n",
        array_map(
            function($i) use ($base){
                return urljoin($base, $i['url']);
            }, array_filter($items, function($i) {
                return $i['type'] === 'file';
            })
        ));
}

function posixpath_join() {
    $args = func_get_args();
    $paths = array();
    foreach ($args as $arg) {
        $paths = array_merge($paths, (array)$arg);
    }

    $paths = array_map(create_function('$p', 'return trim($p, "/");'), $paths);
    $paths = array_filter($paths);
    return '/' . join('/', $paths);
}

function get_query($name, $default=null) {
    return array_key_exists($name, $_GET) ? $_GET[$name] : $default;
}

function is_music_file($path) {
    return is_file($path) &&
        in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), [
        'mp3', 'aac', 'm4a'
    ]);
}

$out_format = get_query('format', 'text');

$request_path = realpath(posixpath_join(MEDIA_ROOT, get_query('p', '/')));

// Ensure the request_path is within our defined root
if (substr($request_path, 0, strlen(MEDIA_ROOT)) !== MEDIA_ROOT) {
    http_response_code(400);
    echo "good bye";
    if (DEBUG > 0)
        echo "\ndebug: '$request_path' is not within MEDIA_ROOT";
        echo "\ndebug: '".get_query('p')."' is not within MEDIA_ROOT";
    die(0);
}

$items = [];
foreach(scandir($request_path) as $i) {
    $i_path = posixpath_join($request_path, $i);
    if (is_music_file($i_path)) {
        $items[] = array(
            type=>'file',
            name=>substr($i,0,-4),
            url=>virtualPath($i_path)
        );
    } elseif (is_dir($i_path) && $i != '.' && $i != '..') {
        $items[] = array(
            type=>'folder',
            name=>$i,
            url=>substr($i_path, strlen(MEDIA_ROOT))
        );
    }
}

if ($out_format === 'json') {
    header('Content-Type: application/json');
    echo json_encode(array('items'=>$items), JSON_OPTS);
} elseif ($out_format === 'm3u8') {
    $fname = str_replace('/', '_', virtualPath($request_path)) . '.m3u8';
    header('Content-Type: audio/mpegurl');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    echo m3u8_encode($items);
} else {
    print_r($items);
}
