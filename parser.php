<?php
/**
 * B站视频解析器 API v2.1
 * 修复403问题，添加下载说明
 * 仅供个人学习使用
 */

class BilibiliParser {
    private $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    public $cookie = '';
    
    /**
     * 批量解析视频
     */
    public function parseBatch($inputs) {
        $results = [];
        $bvids = [];
        
        foreach ($inputs as $key => $input) {
            $bvid = $this->extractBvid($input);
            if ($bvid) {
                $bvids[$key] = $bvid;
            } else {
                $results[$key] = ['error' => '无效的BV号或链接: ' . $input];
            }
        }
        
        $videoInfos = $this->getVideoInfoBatch($bvids);
        
        $playInfoRequests = [];
        foreach ($videoInfos as $key => $info) {
            if (isset($info['error'])) {
                $results[$key] = $info;
            } else {
                $playInfoRequests[$key] = [
                    'bvid' => $bvids[$key],
                    'cid' => $info['cid']
                ];
            }
        }
        
        if (!empty($playInfoRequests)) {
            $playInfos = $this->getPlayInfoBatch($playInfoRequests);
            
            foreach ($playInfos as $key => $playInfo) {
                if (isset($playInfo['error'])) {
                    $results[$key] = $playInfo;
                } else {
                    $results[$key] = $this->formatResult($videoInfos[$key], $playInfo);
                }
            }
        }
        
        return $results;
    }
    
    /**
     * 解析单个视频
     */
    public function parse($input, $quality = 80) {
        $bvid = $this->extractBvid($input);
        if (!$bvid) {
            return ['error' => '无效的BV号或链接'];
        }
        
        $videoInfo = $this->getVideoInfo($bvid);
        if (!$videoInfo) {
            return ['error' => '获取视频信息失败'];
        }
        
        $playInfo = $this->getPlayInfo($bvid, $videoInfo['cid'], $quality);
        if (!$playInfo) {
            return ['error' => '获取视频流信息失败'];
        }
        
        return $this->formatResult($videoInfo, $playInfo, $quality);
    }
    
    /**
     * 提取BV号
     */
    private function extractBvid($input) {
        $input = trim($input);
        
        if (preg_match('/^BV[a-zA-Z0-9]+$/i', $input)) {
            return $input;
        }
        
        if (preg_match('/b23\.tv\/([a-zA-Z0-9]+)/', $input, $matches)) {
            $realUrl = $this->getRealUrl('https://b23.tv/' . $matches[1]);
            if ($realUrl && preg_match('/BV[a-zA-Z0-9]+/i', $realUrl, $bvMatches)) {
                return $bvMatches[0];
            }
        }
        
        if (preg_match('/BV[a-zA-Z0-9]+/i', $input, $matches)) {
            return $matches[0];
        }
        
        if (preg_match('/av(\d+)/i', $input, $matches)) {
            return $this->av2bv($matches[1]);
        }
        
        return null;
    }
    
    /**
     * av号转BV号
     */
    private function av2bv($av) {
        $table = 'fZodR9XQDSUm21yCkr6zBqiveYah8bt4xsWpHnJE7jL5VG3guMTKNPAwcF';
        $tr = [];
        for ($i = 0; $i < 58; $i++) {
            $tr[$table[$i]] = $i;
        }
        $s = [11, 10, 3, 8, 4, 6];
        $xor = 177451812;
        $add = 8728348608;
        
        $av = ($av ^ $xor) + $add;
        $r = 'BV1  4 1 7  ';
        $arr = str_split($r);
        
        for ($i = 0; $i < 6; $i++) {
            $arr[$s[$i]] = $table[intval($av / pow(58, $i)) % 58];
        }
        
        return implode('', $arr);
    }
    
    /**
     * 批量获取视频信息
     */
    private function getVideoInfoBatch($bvids) {
        $urls = [];
        foreach ($bvids as $key => $bvid) {
            $urls[$key] = "https://api.bilibili.com/x/web-interface/view?bvid={$bvid}";
        }
        
        $responses = $this->multiHttpGet($urls);
        $results = [];
        
        foreach ($responses as $key => $response) {
            if ($response === false) {
                $results[$key] = ['error' => '请求失败'];
                continue;
            }
            
            $data = json_decode($response, true);
            if ($data['code'] !== 0) {
                $results[$key] = ['error' => $data['message'] ?? '获取失败'];
                continue;
            }
            
            $results[$key] = [
                'title' => $data['data']['title'],
                'desc' => $data['data']['desc'],
                'pic' => $data['data']['pic'],
                'cid' => $data['data']['cid'],
                'duration' => $data['data']['duration'],
                'owner' => $data['data']['owner']['name'],
                'bvid' => $bvids[$key],
                'view' => $data['data']['stat']['view'],
                'danmaku' => $data['data']['stat']['danmaku'],
                'like' => $data['data']['stat']['like']
            ];
        }
        
        return $results;
    }
    
    /**
     * 获取视频基本信息
     */
    private function getVideoInfo($bvid) {
        $url = "https://api.bilibili.com/x/web-interface/view?bvid={$bvid}";
        
        $response = $this->httpGet($url);
        $data = json_decode($response, true);
        
        if ($data['code'] !== 0) {
            return null;
        }
        
        return [
            'title' => $data['data']['title'],
            'desc' => $data['data']['desc'],
            'pic' => $data['data']['pic'],
            'cid' => $data['data']['cid'],
            'duration' => $data['data']['duration'],
            'owner' => $data['data']['owner']['name'],
            'bvid' => $bvid,
            'view' => $data['data']['stat']['view'],
            'danmaku' => $data['data']['stat']['danmaku'],
            'like' => $data['data']['stat']['like']
        ];
    }
    
    /**
     * 批量获取播放信息
     */
    private function getPlayInfoBatch($requests) {
        $urls = [];
        foreach ($requests as $key => $req) {
            $urls[$key] = "https://api.bilibili.com/x/player/playurl?bvid={$req['bvid']}&cid={$req['cid']}&qn=80&fnval=4048&fourk=1";
        }
        
        $responses = $this->multiHttpGet($urls);
        $results = [];
        
        foreach ($responses as $key => $response) {
            if ($response === false) {
                $results[$key] = ['error' => '获取播放信息失败'];
                continue;
            }
            
            $data = json_decode($response, true);
            if ($data['code'] !== 0) {
                $results[$key] = ['error' => '获取播放信息失败'];
                continue;
            }
            
            $results[$key] = $this->parsePlayInfo($data['data']);
        }
        
        return $results;
    }
    
    /**
     * 获取视频播放信息
     */
    private function getPlayInfo($bvid, $cid, $quality = 80) {
        $qualityMap = [
            '8K' => 127,
            '4K' => 120,
            '1080P60' => 116,
            '1080P' => 80,
            '720P60' => 74,
            '720P' => 64,
            '480P' => 32,
            '360P' => 16
        ];
        
        $qn = is_numeric($quality) ? $quality : ($qualityMap[$quality] ?? 80);
        
        $url = "https://api.bilibili.com/x/player/playurl?bvid={$bvid}&cid={$cid}&qn={$qn}&fnval=4048&fourk=1";
        
        $response = $this->httpGet($url);
        $data = json_decode($response, true);
        
        if ($data['code'] !== 0) {
            return null;
        }
        
        return $this->parsePlayInfo($data['data']);
    }
    
    /**
     * 解析播放信息
     */
    private function parsePlayInfo($data) {
        $result = [];
        
        if (isset($data['dash'])) {
            $dash = $data['dash'];
            
            $videos = [];
            foreach ($dash['video'] as $video) {
                $videos[] = [
                    'quality' => $this->getQualityName($video['id']),
                    'quality_id' => $video['id'],
                    'url' => $video['baseUrl'],
                    'backup_url' => $video['backupUrl'] ?? [],
                    'bandwidth' => $video['bandwidth'],
                    'codecs' => $video['codecs'],
                    'width' => $video['width'],
                    'height' => $video['height'],
                    'frame_rate' => $video['frameRate'],
                    'size' => $this->estimateSize($video['bandwidth'], $data['timelength'] ?? 0)
                ];
            }
            
            usort($videos, function($a, $b) {
                return $b['quality_id'] - $a['quality_id'];
            });
            
            $audios = [];
            foreach ($dash['audio'] as $audio) {
                $audios[] = [
                    'url' => $audio['baseUrl'],
                    'backup_url' => $audio['backupUrl'] ?? [],
                    'bandwidth' => $audio['bandwidth'],
                    'codecs' => $audio['codecs'],
                    'size' => $this->estimateSize($audio['bandwidth'], $data['timelength'] ?? 0)
                ];
            }
            
            usort($audios, function($a, $b) {
                return $b['bandwidth'] - $a['bandwidth'];
            });
            
            $result['videos'] = $videos;
            $result['audios'] = $audios;
            $result['format'] = 'dash';
            
            if (isset($dash['flac'])) {
                $result['flac'] = $dash['flac'];
            }
            
            if (isset($dash['dolby'])) {
                $result['dolby'] = $dash['dolby'];
            }
        }
        else if (isset($data['durl'])) {
            $result['videos'] = [];
            foreach ($data['durl'] as $item) {
                $result['videos'][] = [
                    'quality' => $this->getQualityName($data['quality']),
                    'quality_id' => $data['quality'],
                    'url' => $item['url'],
                    'backup_url' => $item['backup_url'] ?? [],
                    'size' => $item['size'],
                    'length' => $item['length']
                ];
            }
            $result['format'] = 'flv';
        }
        
        $result['accept_quality'] = $data['accept_quality'] ?? [];
        $result['support_formats'] = $data['support_formats'] ?? [];
        
        return $result;
    }
    
    /**
     * 格式化返回结果
     */
    private function formatResult($videoInfo, $playInfo, $preferredQuality = 80) {
        $result = [
            'code' => 0,
            'message' => 'success',
            'data' => [
                'bvid' => $videoInfo['bvid'],
                'title' => $videoInfo['title'],
                'owner' => $videoInfo['owner'],
                'pic' => $videoInfo['pic'],
                'duration' => $videoInfo['duration'],
                'desc' => $videoInfo['desc'],
                'stats' => [
                    'view' => $videoInfo['view'] ?? 0,
                    'danmaku' => $videoInfo['danmaku'] ?? 0,
                    'like' => $videoInfo['like'] ?? 0
                ],
                'format' => $playInfo['format']
            ]
        ];
        
        $selectedVideo = null;
        if (isset($playInfo['videos']) && count($playInfo['videos']) > 0) {
            foreach ($playInfo['videos'] as $video) {
                if ($video['quality_id'] == $preferredQuality) {
                    $selectedVideo = $video;
                    break;
                }
            }
            if (!$selectedVideo) {
                $selectedVideo = $playInfo['videos'][0];
            }
        }
        
        $downloadInfo = [];
        
        if ($playInfo['format'] === 'dash') {
            if ($selectedVideo) {
                $downloadInfo['video'] = [
                    'quality' => $selectedVideo['quality'],
                    'quality_id' => $selectedVideo['quality_id'],
                    'url' => $selectedVideo['url'],
                    'backup_url' => $selectedVideo['backup_url'],
                    'codecs' => $selectedVideo['codecs'],
                    'width' => $selectedVideo['width'],
                    'height' => $selectedVideo['height'],
                    'size' => $selectedVideo['size']
                ];
            }
            
            if (isset($playInfo['audios'][0])) {
                $downloadInfo['audio'] = [
                    'url' => $playInfo['audios'][0]['url'],
                    'backup_url' => $playInfo['audios'][0]['backup_url'],
                    'codecs' => $playInfo['audios'][0]['codecs'],
                    'size' => $playInfo['audios'][0]['size']
                ];
            }
            
            $downloadInfo['available_quality'] = [];
            foreach ($playInfo['videos'] as $v) {
                $downloadInfo['available_quality'][] = [
                    'quality' => $v['quality'],
                    'quality_id' => $v['quality_id'],
                    'resolution' => $v['width'] . 'x' . $v['height'],
                    'fps' => $v['frame_rate'],
                    'codecs' => $v['codecs']
                ];
            }
            
            if (isset($playInfo['flac'])) {
                $downloadInfo['flac'] = $playInfo['flac'];
            }
            if (isset($playInfo['dolby'])) {
                $downloadInfo['dolby'] = $playInfo['dolby'];
            }
            
            // 重要提示：下载说明
            $downloadInfo['important_notice'] = '⚠️ 重要：链接不能直接在浏览器打开（会403），必须用下载工具并添加Referer头';
            $downloadInfo['download_instructions'] = [
                'curl' => 'curl -H "Referer: https://www.bilibili.com" -H "User-Agent: Mozilla/5.0" -o video.mp4 "视频URL"',
                'wget' => 'wget --referer="https://www.bilibili.com" --user-agent="Mozilla/5.0" -O video.mp4 "视频URL"',
                'ffmpeg' => 'ffmpeg -headers "Referer: https://www.bilibili.com" -i "视频URL" -i "音频URL" -c copy output.mp4',
                'aria2c' => 'aria2c --header="Referer: https://www.bilibili.com" --user-agent="Mozilla/5.0" -o video.mp4 "视频URL"'
            ];
            $downloadInfo['merge_command'] = 'ffmpeg -i video.mp4 -i audio.mp4 -c copy output.mp4';
        } else {
            if ($selectedVideo) {
                $downloadInfo['video'] = $selectedVideo;
            }
            $downloadInfo['important_notice'] = '⚠️ 重要：链接不能直接在浏览器打开（会403），必须用下载工具并添加Referer头';
        }
        
        $result['data']['download'] = $downloadInfo;
        
        if ($selectedVideo) {
            if ($selectedVideo['quality_id'] == $preferredQuality) {
                $result['data']['tips'] = '成功获取' . $selectedVideo['quality'] . '视频';
            } else {
                $result['data']['tips'] = '目标质量不可用，已获取最高可用质量：' . $selectedVideo['quality'];
            }
        } else {
            $result['data']['tips'] = '未找到可用视频流';
        }
        
        return $result;
    }
    
    /**
     * 创建下载器PHP脚本
     */
    public function generateDownloader($videoUrl, $audioUrl = null, $filename = 'video') {
        $script = '<?php
/**
 * B站视频下载器脚本
 * 自动生成，用于下载视频和音频
 */

$videoUrl = \'' . addslashes($videoUrl) . '\';
' . ($audioUrl ? '$audioUrl = \'' . addslashes($audioUrl) . '\';' : '') . '
$filename = \'' . addslashes($filename) . '\';

function downloadWithReferer($url, $outputFile) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        \'Referer: https://www.bilibili.com\',
        \'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\'
    ]);
    curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function($resource, $downloadSize, $downloaded) {
        if ($downloadSize > 0) {
            $percent = round($downloaded / $downloadSize * 100, 2);
            echo "\r下载进度: " . $percent . "%";
        }
    });
    curl_setopt($ch, CURLOPT_NOPROGRESS, false);
    
    $fp = fopen($outputFile, \'wb\');
    curl_setopt($ch, CURLOPT_FILE, $fp);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    fclose($fp);
    
    if ($httpCode !== 200) {
        unlink($outputFile);
        return false;
    }
    
    return true;
}

echo "开始下载视频...\n";
if (downloadWithReferer($videoUrl, $filename . ".mp4")) {
    echo "\n视频下载完成！\n";
} else {
    echo "\n视频下载失败！\n";
}
';

        if ($audioUrl) {
            $script .= '
echo "开始下载音频...\n";
if (downloadWithReferer($audioUrl, $filename . "_audio.mp4")) {
    echo "\n音频下载完成！\n";
    echo "请使用以下命令合并音视频：\n";
    echo "ffmpeg -i " . $filename . ".mp4 -i " . $filename . "_audio.mp4 -c copy " . $filename . "_merged.mp4\n";
} else {
    echo "\n音频下载失败！\n";
}
';
        }

        $script .= '
?>';
        return $script;
    }
    
    /**
     * 获取质量名称
     */
    private function getQualityName($qn) {
        $qualityMap = [
            127 => '8K',
            120 => '4K',
            116 => '1080P60',
            80 => '1080P',
            74 => '720P60',
            64 => '720P',
            32 => '480P',
            16 => '360P'
        ];
        return $qualityMap[$qn] ?? '未知';
    }
    
    /**
     * 估算文件大小
     */
    private function estimateSize($bandwidth, $duration) {
        if ($bandwidth && $duration) {
            $bytes = ($bandwidth * $duration) / 8000;
            return $this->formatFileSize($bytes);
        }
        return null;
    }
    
    /**
     * 格式化文件大小
     */
    private function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    /**
     * 获取短链接的真实地址
     */
    private function getRealUrl($shortUrl) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $shortUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_exec($ch);
        $realUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        return $realUrl;
    }
    
    /**
     * 并发HTTP GET请求
     */
    private function multiHttpGet($urls) {
        $mh = curl_multi_init();
        $curlHandles = [];
        
        foreach ($urls as $key => $url) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
            curl_setopt($ch, CURLOPT_REFERER, 'https://www.bilibili.com');
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            if ($this->cookie) {
                curl_setopt($ch, CURLOPT_COOKIE, $this->cookie);
            }
            
            curl_multi_add_handle($mh, $ch);
            $curlHandles[$key] = $ch;
        }
        
        $running = null;
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh);
        } while ($running > 0);
        
        $results = [];
        foreach ($curlHandles as $key => $ch) {
            $results[$key] = curl_multi_getcontent($ch);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        
        curl_multi_close($mh);
        return $results;
    }
    
    /**
     * HTTP GET请求
     */
    private function httpGet($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($ch, CURLOPT_REFERER, 'https://www.bilibili.com');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        if ($this->cookie) {
            curl_setopt($ch, CURLOPT_COOKIE, $this->cookie);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return false;
        }
        
        return $response;
    }
}

// ========== API 接口处理 ==========

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$response = [
    'code' => -1,
    'message' => '',
    'data' => null
];

$parser = new BilibiliParser();

if (isset($_GET['cookie']) && !empty($_GET['cookie'])) {
    $parser->cookie = $_GET['cookie'];
}

// 生成下载器脚本
if (isset($_GET['generate_downloader'])) {
    if (!isset($_GET['video_url']) || empty($_GET['video_url'])) {
        $response['message'] = '缺少视频URL';
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    $videoUrl = $_GET['video_url'];
    $audioUrl = $_GET['audio_url'] ?? null;
    $filename = $_GET['filename'] ?? 'video';
    
    $script = $parser->generateDownloader($videoUrl, $audioUrl, $filename);
    
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="download_' . $filename . '.php"');
    echo $script;
    exit;
}

// 批量解析
if (isset($_GET['batch'])) {
    $inputs = [];
    
    if (isset($_GET['bv'])) {
        $inputs = array_map('trim', explode(',', $_GET['bv']));
    } else if (isset($_GET['list'])) {
        $inputs = json_decode($_GET['list'], true);
    } else {
        $response['message'] = '批量模式需要提供bv参数（逗号分隔）或list参数（JSON数组）';
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    if (empty($inputs)) {
        $response['message'] = '批量解析列表为空';
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    if (count($inputs) > 20) {
        $response['message'] = '批量解析最多支持20个视频';
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    try {
        $results = $parser->parseBatch($inputs);
        $response = [
            'code' => 0,
            'message' => 'success',
            'data' => [
                'total' => count($inputs),
                'success' => count(array_filter($results, function($r) { return !isset($r['error']); })),
                'results' => $results
            ]
        ];
    } catch (Exception $e) {
        $response['code'] = -500;
        $response['message'] = '批量解析异常：' . $e->getMessage();
    }
} else {
    // 单个解析
    if (!isset($_GET['bv']) || empty($_GET['bv'])) {
        $response['message'] = '缺少参数：bv（BV号或视频链接）';
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    $input = $_GET['bv'];
    $quality = $_GET['quality'] ?? '1080P';
    
    try {
        $result = $parser->parse($input, $quality);
        
        if (isset($result['error'])) {
            $response['code'] = -1;
            $response['message'] = $result['error'];
        } else {
            $response = $result;
        }
    } catch (Exception $e) {
        $response['code'] = -500;
        $response['message'] = '解析异常：' . $e->getMessage();
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

/* 
重要说明 - 关于403错误
========================

B站的音视频链接不能直接在浏览器中打开，会返回403 Forbidden错误。
这是因为B站CDN服务器会验证请求的Referer头。

正确的下载方法：

1. 使用curl下载（推荐）:
   curl -H "Referer: https://www.bilibili.com" -H "User-Agent: Mozilla/5.0" -o video.mp4 "视频URL"
   curl -H "Referer: https://www.bilibili.com" -H "User-Agent: Mozilla/5.0" -o audio.mp4 "音频URL"

2. 使用wget下载:
   wget --referer="https://www.bilibili.com" --user-agent="Mozilla/5.0" -O video.mp4 "视频URL"
   wget --referer="https://www.bilibili.com" --user-agent="Mozilla/5.0" -O audio.mp4 "音频URL"

3. 使用ffmpeg直接合并:
   ffmpeg -headers "Referer: https://www.bilibili.com" -i "视频URL" -headers "Referer: https://www.bilibili.com" -i "音频URL" -c copy output.mp4

4. 使用aria2c下载:
   aria2c --header="Referer: https://www.bilibili.com" --user-agent="Mozilla/5.0" -o video.mp4 "视频URL"
   aria2c --header="Referer: https://www.bilibili.com" --user-agent="Mozilla/5.0" -o audio.mp4 "音频URL"

5. 生成PHP下载脚本:
   访问: parser.php?generate_downloader=1&video_url=视频URL&audio_url=音频URL&filename=文件名
   这会生成一个可以直接运行的PHP下载脚本

注意事项：
- 链接有时效性，通常在几小时内有效
- 必须添加Referer头: https://www.bilibili.com
- 建议同时设置User-Agent
- 高画质视频可能需要登录Cookie
*/
?>