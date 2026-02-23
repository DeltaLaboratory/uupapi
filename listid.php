<?php
require_once 'api/shared/main.php';
require_once 'api/shared/cache.php';
require_once 'api/shared/fileinfo.php';
require_once 'shared/main.php';

$search = isset($_GET['search']) ? $_GET['search'] : null;
$sortByDate = isset($_GET['sortByDate']) ? $_GET['sortByDate'] : false;

header('Content-Type: application/json');

$apiResponse = uupListIdsWithMeta($search, $sortByDate);
if(isset($apiResponse['error']))
    http_response_code(400);

sendResponse($apiResponse);

function uupListIdsWithMeta($search = null, $sortByDate = 0) {
    uupApiPrintBrand();

    $sortByDate = $sortByDate ? 1 : 0;

    $res = "listid-$sortByDate";
    $cache = new UupDumpCache($res, false);
    $builds = $cache->get();
    $cached = ($builds !== false);

    if(!$cached) {
        $builds = uupGetFromFileinfoWithMeta($sortByDate);
        if($builds === false) return ['error' => 'NO_FILEINFO_DIR'];

        $cache->put($builds, 60);
    }

    if(count($builds) && $search != null) {
        if(!preg_match('/^regex:/', $search)) {
            $searchSafe = preg_quote($search, '/');

            if(preg_match('/^".*"$/', $searchSafe)) {
                $searchSafe = preg_replace('/^"|"$/', '', $searchSafe);
            } else {
                $searchSafe = str_replace(' ', '.*', $searchSafe);
            }
        } else {
            $searchSafe = preg_replace('/^regex:/', '', $search);
        }

        @preg_match("/$searchSafe/", "");
        if(preg_last_error()) {
            return array('error' => 'SEARCH_NO_RESULTS');
        }

        foreach($builds as $key => $val) {
            $buildString[$key] = $val['title'].' '.$val['build'].' '.$val['arch'];
        }

        $remove = preg_grep('/.*'.$searchSafe.'.*/i', $buildString, PREG_GREP_INVERT);
        $removeKeys = array_keys($remove);

        foreach($removeKeys as $value) {
            unset($builds[$value]);
        }

        if(empty($builds)) {
            return array('error' => 'SEARCH_NO_RESULTS');
        }

        unset($remove, $removeKeys, $buildString);
    }

    return array(
        'apiVersion' => uupApiVersion(),
        'builds' => $builds,
    );
}

function uupGetFromFileinfoWithMeta($sortByDate = 0) {
    $dirs = uupApiGetFileinfoDirs();
    $fullDir = $dirs['fileinfoData'];
    $metaDir = $dirs['fileinfoMeta'];
    $fileinfoRoot = $dirs['fileinfo'];

    // Scan both full/ and metadata/, union by UUID
    $fullFiles = file_exists($fullDir) ? preg_grep('/\.json$/', scandir($fullDir)) : [];
    $metaFiles = file_exists($metaDir) ? preg_grep('/\.json$/', scandir($metaDir)) : [];
    $files = array_unique(array_merge($fullFiles, $metaFiles));

    $cacheFile = $fileinfoRoot.'/cache.json';
    $cacheV2Version = 2;

    $database = @json_decode(@file_get_contents($cacheFile), true);

    if(isset($database['version']) && $database['version'] == $cacheV2Version && isset($database['database'])) {
        $database = $database['database'];
    } else {
        $database = array();
    }

    $newDb = array();
    $builds = array();
    $buildAssoc = array();

    foreach($files as $file) {
        if($file == '.' || $file == '..') continue;

        $uuid = preg_replace('/\.json$/', '', $file);

        if(!isset($database[$uuid])) {
            // Try metadata first, then full
            $metaFile = "$metaDir/$file";
            $fullFile = "$fullDir/$file";

            $info = false;
            if(file_exists($metaFile))
                $info = @json_decode(@file_get_contents($metaFile), true);
            if($info === false && file_exists($fullFile))
                $info = @json_decode(@file_get_contents($fullFile), true);
            if($info === false) $info = [];

            $temp = array(
                'title' => isset($info['title']) ? $info['title'] : 'UNKNOWN',
                'build' => isset($info['build']) ? $info['build'] : 'UNKNOWN',
                'arch' => isset($info['arch']) ? $info['arch'] : 'UNKNOWN',
                'created' => isset($info['created']) ? $info['created'] : null,
            );

            $newDb[$uuid] = $temp;
        } else {
            $temp = $database[$uuid];
            $newDb[$uuid] = $temp;
        }

        $title = $temp['title'];
        $build = $temp['build'];
        $arch = $temp['arch'];
        $created = $temp['created'];

        $entry = array(
            'title' => $title,
            'build' => $build,
            'arch' => $arch,
            'created' => $created,
            'uuid' => $uuid,
        );

        $tmp = explode('.', $build);
        if(isset($tmp[1])) {
            $tmp[0] = str_pad($tmp[0], 10, '0', STR_PAD_LEFT);
            $tmp[1] = str_pad($tmp[1], 10, '0', STR_PAD_LEFT);
            $tmp = $tmp[0].$tmp[1];
        } else {
            $tmp = 0;
        }

        if($sortByDate) {
            $tmp = $created.$tmp;
        }

        $buildAssoc[$tmp][] = $arch.$title.$uuid;
        $builds[$tmp.$arch.$title.$uuid] = $entry;
    }

    if(empty($buildAssoc)) return [];

    krsort($buildAssoc);
    $buildsNew = array();

    foreach($buildAssoc as $key => $val) {
        sort($val);
        foreach($val as $id) {
            $buildsNew[] = $builds[$key.$id];
        }
    }

    if($newDb != $database) {
        @file_put_contents($cacheFile, json_encode([
            'version' => $cacheV2Version,
            'database' => $newDb,
        ])."\n");
    }

    return $buildsNew;
}
