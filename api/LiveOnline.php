// 合并多个m3u直播链接并设置 ua，解决某些播放器不能播放 itv 的问题。目前存在的问题是多个 m3u 里面的相同的 group-title 会合并到一起。
// 原帖地址（需翻墙）：https://pixman.io/topics/40
<?php
// 设置多个M3U文件的URL数组
$m3uUrls = [
    'http://i.880824.xyz:5000/4gtv.m3u',
    'http://i.880824.xyz:5000/itv_proxy.m3u',
    'http://i.880824.xyz:5000/tptv_proxy.m3u',
    'http://i.880824.xyz:5000/beesport.m3u',
    'https://live.fanmingming.com/tv/m3u/ipv6.m3u',
// 添加更多URL...
];

// 设置User-Agent
$userAgent = 'MyCustomUserAgent/1.0';

// 创建HTTP上下文选项
$options = [
    'http' => [
        'header' => "User-Agent: $userAgent\r\n"
    ]
];

// 创建上下文资源
$context = stream_context_create($options);

// 读取M3U文件的内容
function getM3UContent($url, $context) {
    $content = file_get_contents($url, false, $context);
    if ($content === false) {
        die("无法读取M3U文件：$url");
    }
    return $content;
}

// 提取文件名
function getFileNameFromUrl($url) {
    return pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME);
}

// 初始化内容存储数组
$groups = [];
$groupTitleMap = [];

// 读取并处理多个M3U文件的内容
foreach ($m3uUrls as $url) {
    $content = getM3UContent($url, $context);
    $fileName = getFileNameFromUrl($url);
    // 移除头部
    $content = preg_replace('/^#EXTM3U\s*/', '', $content);

    // 按行拆分内容
    $lines = explode("\n", $content);
    $currentGroup = '';
    $currentTvgId = '';
    $currentEntry = '';

    foreach ($lines as $line) {
        if (preg_match('/#EXTINF.*group-title="([^"]*)".*tvg-id="([^"]*)".*,/', $line, $matches)) {
            $originalGroup = $matches[1];
            $currentTvgId = $matches[2];

            // 如果group-title重复，则在末尾添加文件名
            $newGroup = $originalGroup;
            if (isset($groupTitleMap[$originalGroup])) {
                $newGroup .= ' (' . $fileName . ')';
            }
            $groupTitleMap[$originalGroup] = true;

            $line = preg_replace('/group-title="[^"]*"/', 'group-title="' . $newGroup . '"', $line);

            $currentEntry = $line;
        } else if (!empty($line)) {
            $currentEntry .= "\n" . $line;
            $groups[$newGroup][$currentTvgId][] = $currentEntry;
            $currentEntry = '';
        }
    }
}

// 对每个group-title下的条目按tvg-id排序
foreach ($groups as $group => &$entries) {
    ksort($entries);
}

// 初始化合并内容变量
$mergedContent = "#EXTM3U\n";

// 合并并生成新的M3U内容
foreach ($groups as $group => $entries) {
    foreach ($entries as $tvgIdEntries) {
        foreach ($tvgIdEntries as $entry) {
            $mergedContent .= $entry . "\n";
        }
    }
}

// 设置HTTP头部，使得PHP输出被浏览器识别为M3U文件
header('Content-Type: audio/x-mpegurl');
header('Content-Disposition: attachment; filename="LiveOnline.m3u"');

// 输出合并后的内容
echo $mergedContent;
