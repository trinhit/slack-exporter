<?php

function buildSlackApiUrl($methodName, $params = []) {
    $url = 'https://slack.com/api/' . $methodName . '?' . http_build_query($params);

    return $url;
}

function downloadSlackFile($url, $targetPath) {
    $headers = [
        'Authorization: Bearer ' . SLACK_API_TOKEN,
    ];

    $ch = curl_init($url);
    $options = array(
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_AUTOREFERER    => true,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_POST            => 1,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_VERBOSE        => false,
        CURLOPT_HTTPHEADER     => $headers
    );

    curl_setopt_array($ch,$options);
    $data = curl_exec($ch);
    curl_close($ch);

    return file_put_contents($targetPath, $data);
}

function sendPostRequestToSlackApi($methodName, $params) {
    $url = buildSlackApiUrl($methodName, $params);
    $headers = [
        'Authorization: Bearer ' . SLACK_API_TOKEN,
    ];

    $ch = curl_init($url);
    $options = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_AUTOREFERER    => true,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_POST            => 1,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_VERBOSE        => false,
        CURLOPT_HTTPHEADER     => $headers
    );

    curl_setopt_array($ch,$options);
    $data = curl_exec($ch);
    curl_close($ch);

    return $data;
}

function validUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function extractThreadTsFromSlackPermalink($permalink) {
    $result = parse_url($permalink);
    parse_str($result['query'], $params);
    return $params['thread_ts'];
}