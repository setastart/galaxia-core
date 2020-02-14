<?php

function downloadPageYoutube(string $url) {
    $r = [
        'error' => '',
        'data'  => '',
    ];

    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        $r['error'] = 'Invalid Url';
        return $r;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.3) Gecko/20070309 Firefox/2.0.0.3");
    curl_setopt($ch, CURLOPT_REFERER, "http://www.youtube.com");
    $page = curl_exec($ch);

    if ($page === false) {
        $r['error'] = 'Could not download page';
        return $r;
    }

    $r['data'] = $page;
    return $r;
}

function scrapeYoutubeVideoInfo(string $id) {
    $r = [
        'error' => '',
        '@type' => 'Video',
    ];

    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $id) != 1) {
        $r['error'] = 'Invalid Id';
        return $r;
    }

    $urlInfo = 'https://www.youtube.com/get_video_info?video_id=' . $id;

    $page = downloadPageYoutube($urlInfo);
    if ($page['error']) {
        $r['error'] = $page['error'];
        return $r;
    }

    if (!$page['data']) {
        $r['error'] = $page['Empty page returned'];
        return $r;
    }

    parse_str($page['data'], $data);

    $videoData = json_decode($data['player_response'], true);
    // dp($videoData);

    $r['id']            = $id;
    $r['hash']          = hash('fnv164', $id);
    $r['status']        = $videoData['playabilityStatus']['status'] ?? null;
    $r['title']         = $videoData['videoDetails']['title'] ?? null;
    $r['lengthSeconds'] = $videoData['videoDetails']['lengthSeconds'] ?? null;
    $r['channelId']     = $videoData['videoDetails']['channelId'] ?? null;
    $r['channel']       = $videoData['videoDetails']['author'] ?? null;
    $r['isPrivate']     = $videoData['videoDetails']['isPrivate'] ?? null;
    $r['isUnlisted']    = $videoData['microformat']['playerMicroformatRenderer']['isUnlisted'] ?? null;
    $r['date']          = $videoData['microformat']['playerMicroformatRenderer']['publishDate'] ?? null;
    $r['viewCount']     = $videoData['microformat']['playerMicroformatRenderer']['viewCount'] ?? null;

    return $r;
}

function scrapeYoutubeVideoList(string $url) {
    $r = [
        'error' => '',
        'list'  => [],
    ];

    $page = downloadPageYoutube($url);
    if ($page['error']) {
        $r['error'] = $page['error'];
        return $r;
    }

    if (!$page['data']) {
        $r['error'] = $page['Empty page returned'];
        return $r;
    }


    preg_match_all('/watch\?v=([a-zA-Z0-9_-]{11})/m', $page['data'], $matches);
    foreach ($matches[1] ?? [] as $match) {
        $r['list'][$match] = true;
    }

    return $r;
}
