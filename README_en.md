# Bilibili Video Parser v2.0

<div align="center">

English | [简体中文](./README.md)

![PHP](https://img.shields.io/badge/PHP-%3E%3D7.0-blue)
![License](https://img.shields.io/badge/License-MIT-green)
![Version](https://img.shields.io/badge/Version-2.0-red)
![Educational](https://img.shields.io/badge/Purpose-Educational-orange)

⚠️ **FOR EDUCATIONAL AND RESEARCH PURPOSES ONLY** ⚠️

</div>

## 🎉 v2.0 New Features

- 🚀 **Performance Optimization** - Concurrent Requests for improved batch processing speed
- 📦 **Batch Parsing** - Support parsing up to 20 videos simultaneously
- 🎬 **Multi-format Support** - 8K/4K/1080P60/Dolby Audio and more
- 🔗 **More Input Formats** - BV ID, av ID, short links, full URLs
- 📊 **File Size Estimation** - Display video and audio file sizes

## 📖 Introduction

A powerful Bilibili video parser built with PHP, providing high-performance video parsing services. Features batch parsing, multiple video formats, and other advanced capabilities.

## ⚡ Features

### Core Features
- ✅ Support BV ID, av ID, short links, video URL parsing
- ✅ Batch parsing (up to 20 videos)
- ✅ Auto-select optimal quality
- ✅ DASH format support (separate audio/video)
- ✅ FLV format support (merged audio/video)
- ✅ RESTful API design

### Performance Optimization
- ⚡ Concurrent request technology (cURL multi)
- 📈 Batch processing optimization  
- 🔄 Smart request scheduling

### Format Support
- 🎬 8K (127) - Ultra HD
- 🎬 4K (120) - Ultra HD
- 🎬 1080P60 (116) - High Frame Rate
- 🎬 1080P (80) - HD
- 🎬 720P60 (74) - High Frame Rate
- 🎬 720P (64) - SD
- 🎬 480P (32) - Smooth
- 🎬 360P (16) - Data Saver
- 🎵 Dolby Audio Support
- 🎵 Lossless Audio (FLAC) Support

## 🚀 Quick Start

### Requirements

- PHP >= 7.0
- cURL extension
- JSON extension

### Installation

1. Clone repository
```bash
git clone https://github.com/NORMAL-EX/bilibili-parser.git
cd bilibili-parser
```

2. Configure web server (Apache/Nginx) to serve the project directory

3. Ensure PHP has cURL extension enabled

## 📚 API Documentation

### 1. Single Video Parsing

#### Request Examples

```bash
# Basic usage - BV ID
GET /parser.php?bv=BV1xx411c7mD

# Specify quality
GET /parser.php?bv=BV1xx411c7mD&quality=4K

# Using av ID
GET /parser.php?bv=av170001

# Using short link
GET /parser.php?bv=https://b23.tv/xxxxx

# With Cookie for high quality
GET /parser.php?bv=BV1xx411c7mD&cookie=your_cookie&quality=8K
```

#### Parameters

| Parameter | Type | Required | Description | Example |
|-----------|------|----------|-------------|---------|
| bv | string | Yes | Video identifier | BV1xx411c7mD |
| quality | string/int | No | Target quality | 1080P, 4K, 80, 120 |
| cookie | string | No | Bilibili login cookie | SESSDATA=xxx |

#### Supported Quality Parameters

- String format: `8K`, `4K`, `1080P60`, `1080P`, `720P60`, `720P`, `480P`, `360P`
- Numeric format: `127`, `120`, `116`, `80`, `74`, `64`, `32`, `16`

### 2. Batch Video Parsing

#### Request Examples

```bash
# Comma-separated
GET /parser.php?batch=1&bv=BV1xx411c7mD,BV1yy411c8nE,av170001

# JSON array
GET /parser.php?batch=1&list=["BV1xx411c7mD","BV1yy411c8nE","av170001"]

# Mixed input (BV ID, av ID, URLs)
GET /parser.php?batch=1&bv=BV1xx411c7mD,av170001,https://b23.tv/xxxxx
```

#### Batch Response Format

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "total": 3,
    "success": 3,
    "results": {
      "0": {
        "code": 0,
        "data": { /* Video 1 data */ }
      },
      "1": {
        "code": 0,
        "data": { /* Video 2 data */ }
      },
      "2": {
        "code": 0,
        "data": { /* Video 3 data */ }
      }
    }
  }
}
```

### 3. Complete Response Example

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "bvid": "BV1xx411c7mD",
    "title": "Video Title",
    "owner": "Uploader Name",
    "pic": "Cover Image URL",
    "duration": 300,
    "desc": "Video Description",
    "stats": {
      "view": 1234567,
      "danmaku": 12345,
      "like": 123456
    },
    "format": "dash",
    "from_cache": false,
    "download": {
      "video": {
        "quality": "1080P",
        "quality_id": 80,
        "url": "video_download_url",
        "backup_url": ["backup_url_1", "backup_url_2"],
        "codecs": "avc1.640032",
        "width": 1920,
        "height": 1080,
        "size": "156.3 MB"
      },
      "audio": {
        "url": "audio_download_url",
        "backup_url": ["backup_url_1", "backup_url_2"],
        "codecs": "mp4a.40.2",
        "size": "12.4 MB"
      },
      "available_quality": [
        {
          "quality": "4K",
          "quality_id": 120,
          "resolution": "3840x2160",
          "fps": "30",
          "codecs": "hev1.1.6.L153.90"
        },
        {
          "quality": "1080P60",
          "quality_id": 116,
          "resolution": "1920x1080",
          "fps": "60",
          "codecs": "avc1.640032"
        },
        {
          "quality": "1080P",
          "quality_id": 80,
          "resolution": "1920x1080",
          "fps": "30",
          "codecs": "avc1.640032"
        }
      ],
      "flac": { /* Lossless audio info (if available) */ },
      "dolby": { /* Dolby audio info (if available) */ },
      "merge_command": "ffmpeg -i video.mp4 -i audio.mp4 -c copy output.mp4"
    },
    "tips": "Successfully obtained 1080P video"
  }
}
```

## 🎬 Usage Examples

### PHP Example

```php
<?php
// Include parser
require_once 'parser.php';

// Create instance
$parser = new BilibiliParser();

// Single parsing
$result = $parser->parse('BV1xx411c7mD', '1080P');

// Batch parsing
$videos = [
    'BV1xx411c7mD',
    'av170001',
    'https://www.bilibili.com/video/BV1yy411c8nE'
];
$results = $parser->parseBatch($videos);

// Process results
foreach ($results as $key => $result) {
    if (isset($result['error'])) {
        echo "Video {$key} failed: {$result['error']}\n";
    } else {
        echo "Video {$key}: {$result['data']['title']}\n";
        echo "Download URL: {$result['data']['download']['video']['url']}\n";
    }
}
?>
```

### JavaScript Example

```javascript
// Single video parsing
fetch('http://yoursite.com/parser.php?bv=BV1xx411c7mD&quality=1080P')
  .then(res => res.json())
  .then(data => {
    if (data.code === 0) {
      console.log('Title:', data.data.title);
      console.log('Video URL:', data.data.download.video.url);
      console.log('Audio URL:', data.data.download.audio.url);
    }
  });

// Batch parsing
const videos = ['BV1xx411c7mD', 'BV1yy411c8nE', 'av170001'];
fetch(`http://yoursite.com/parser.php?batch=1&bv=${videos.join(',')}`)
  .then(res => res.json())
  .then(data => {
    console.log(`Successfully parsed ${data.data.success}/${data.data.total} videos`);
    Object.values(data.data.results).forEach(result => {
      if (result.code === 0) {
        console.log(result.data.title);
      }
    });
  });
```

### Python Example

```python
import requests
import json

# Single parsing
response = requests.get('http://yoursite.com/parser.php', params={
    'bv': 'BV1xx411c7mD',
    'quality': '4K'
})
data = response.json()

if data['code'] == 0:
    print(f"Title: {data['data']['title']}")
    print(f"Video: {data['data']['download']['video']['url']}")
    print(f"Audio: {data['data']['download']['audio']['url']}")

# Batch parsing
videos = ['BV1xx411c7mD', 'BV1yy411c8nE', 'av170001']
response = requests.get('http://yoursite.com/parser.php', params={
    'batch': 1,
    'bv': ','.join(videos)
})
batch_data = response.json()
print(f"Success: {batch_data['data']['success']}/{batch_data['data']['total']}")
```

## 🛠️ Advanced Features

### Custom User-Agent

```php
$parser->userAgent = 'Your Custom User-Agent';
```

### Proxy Settings

Add in `httpGet` method:

```php
curl_setopt($ch, CURLOPT_PROXY, 'http://proxy.example.com:8080');
```

## 📊 Performance Comparison

| Feature | v1.0 | v2.0 | Improvement |
|---------|------|------|-------------|
| Single Parse | ~2s | ~1s | 100% ⬆️ |
| Batch 10 | ~20s | ~3s | 566% ⬆️ |
| Concurrency | 1 | 20 | 1900% ⬆️ |

## 🔧 Troubleshooting

### Common Issues

1. **Failed to get quality above 1080P**
   - Valid Bilibili login cookie required
   - Some videos require premium membership

2. **Slow parsing speed**
   - Check network connection
   - Use batch parsing for efficiency

3. **Partial batch parsing failures**
   - Check input format
   - Some videos may be deleted or restricted

## ⚠️ Disclaimer

1. **This project is for educational and research purposes only. Commercial use is strictly prohibited**
2. **Users should comply with relevant laws and Bilibili's terms of service**
3. **Do not use this tool to download copyrighted content**
4. **Do not make excessive requests that cause server load**
5. **Users bear all consequences of using this tool**
6. **Developers are not responsible for any losses caused by using this tool**

## 📋 Changelog

### v2.0.0 (2024-12)
- ✨ Added batch parsing feature
- ⚡ Implemented concurrent request optimization
- 🎬 Support more video formats
- 🔗 Support av ID and short links
- 📊 Added file size estimation
- 🎵 Support Dolby audio and lossless audio

### v1.0.0 (2024-12)
- 🎉 Initial release
- ✅ Basic parsing functionality
- ✅ 1080P priority support

## 🤝 Contributing

Issues and Pull Requests are welcome, but please ensure:

1. Code is for educational and research purposes only
2. No commercial features included
3. Comply with open source license
4. Thoroughly test before submitting

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details

## ⭐ Star History

If this project helps with your learning, please give it a Star!

[![Star History Chart](https://api.star-history.com/svg?repos=NORMAL-EX/bilibili-parser&type=Date)](https://star-history.com/#NORMAL-EX/bilibili-parser&Date)

---

<div align="center">

**⚠️ Reminder: This project is for educational purposes only. Do not use for illegal purposes ⚠️**

Made with ❤️ for Educational Purposes Only

[Report Issues](https://github.com/NORMAL-EX/bilibili-parser/issues) | [Feature Requests](https://github.com/NORMAL-EX/bilibili-parser/discussions)

</div>