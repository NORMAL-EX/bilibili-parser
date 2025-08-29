<?php
/**
 * B站视频解析器 API (增强版)
 * 支持批量解析、多格式支持
 * 仅供个人学习使用
 */

class BilibiliParser {
    private $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    public $cookie = '';
    private $multiCurl = null;
    
    /**
     * 批量解析视频
     */
    public function parseBatch($inputs) {
        $results = [];
        $bvids = [];
        
        // 提取所有BV号
        foreach ($inputs as $key => $input) {
            $bvid = $this->extractBvid($input);
            if ($bvid) {
                $bvids[$key] = $bvid;
            } else {
                $results[$key] = ['error' => '无效的BV号或链接: ' . $input];
            }
        }
        
        // 批量获取视频信息
        $videoInfos = $this->getVideoInfoBatch($bvids);
        
        // 批量获取播放信息
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
            
            // 组合结果
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
        
        // 获取视频信息
        $videoInfo = $this->getVideoInfo($bvid);
        if (!$videoInfo) {
            return ['error' => '获取视频信息失败'];
        }
        
        // 获取视频流信息
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
        
        // 支持多种输入格式
        if (preg_match('/^BV[a-zA-Z0-9]+$/i', $input)) {
            return $input;
        }
        
        // 支持短链接
        if (preg_match('/b23\.tv\/([a-zA-Z0-9]+)/', $input, $matches)) {
            // 需要请求短链接获取真实BV号
            $realUrl = $this->getRealUrl('https://b23.tv/' . $matches[1]);
            if ($realUrl && preg_match('/BV[a-zA-Z0-9]+/i', $realUrl, $bvMatches)) {
                return $bvMatches[0];
            }
        }
        
        // 从URL中提取BV号
        if (preg_match('/BV[a-zA-Z0-9]+/i', $input, $matches)) {
            return $matches[0];
        }
        
        // 支持av号
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
     * 获取视频播放信息（支持多种质量）
     */
    private function getPlayInfo($bvid, $cid, $quality = 80) {
        // 质量参数映射
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
        
        // 处理DASH格式
        if (isset($data['dash'])) {
            $dash = $data['dash'];
            
            // 获取所有视频流
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
            
            // 按质量排序
            usort($videos, function($a, $b) {
                return $b['quality_id'] - $a['quality_id'];
            });
            
            // 获取音频流
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
            
            // 按带宽排序
            usort($audios, function($a, $b) {
                return $b['bandwidth'] - $a['bandwidth'];
            });
            
            $result['videos'] = $videos;
            $result['audios'] = $audios;
            $result['format'] = 'dash';
            
            // 支持FLV降级
            if (isset($dash['flac'])) {
                $result['flac'] = $dash['flac'];
            }
            
            // 支持杜比音效
            if (isset($dash['dolby'])) {
                $result['dolby'] = $dash['dolby'];
            }
        }
        // 处理FLV格式
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
        
        // 选择最接近目标质量的视频
        $selectedVideo = null;
        if (isset($playInfo['videos']) && count($playInfo['videos']) > 0) {
            foreach ($playInfo['videos'] as $video) {
                if ($video['quality_id'] == $preferredQuality) {
                    $selectedVideo = $video;
                    break;
                }
            }
            if (!$selectedVideo) {
                // 选择最高质量
                $selectedVideo = $playInfo['videos'][0];
            }
        }
        
        // 构建下载信息
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
            
            // 所有可用质量
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
            
            // 特殊格式
            if (isset($playInfo['flac'])) {
                $downloadInfo['flac'] = $playInfo['flac'];
            }
            if (isset($playInfo['dolby'])) {
                $downloadInfo['dolby'] = $playInfo['dolby'];
            }
            
            $downloadInfo['merge_command'] = 'ffmpeg -i video.mp4 -i audio.mp4 -c copy output.mp4';
        } else {
            if ($selectedVideo) {
                $downloadInfo['video'] = $selectedVideo;
            }
        }
        
        $result['data']['download'] = $downloadInfo;
        
        // 添加提示信息
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
            $bytes = ($bandwidth * $duration) / 8000; // 转换为字节
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
     * 并发HTTP GET请求（优化速度）
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

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// 初始化响应
$response = [
    'code' => -1,
    'message' => '',
    'data' => null
];

// 创建解析器实例
$parser = new BilibiliParser();

// 设置cookie（如果提供）
if (isset($_GET['cookie']) && !empty($_GET['cookie'])) {
    $parser->cookie = $_GET['cookie'];
}

// 判断是否批量解析
if (isset($_GET['batch'])) {
    // 批量解析模式
    $inputs = [];
    
    // 支持多种批量输入方式
    if (isset($_GET['bv'])) {
        // 逗号分隔
        $inputs = array_map('trim', explode(',', $_GET['bv']));
    } else if (isset($_GET['list'])) {
        // JSON数组
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
    // 单个解析模式
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

// 输出JSON响应
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>