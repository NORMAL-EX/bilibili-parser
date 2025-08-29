# Bilibili Video Parser (B站视频解析器) v2.0

<div align="center">

[English](./README_en.md) | 简体中文

![PHP](https://img.shields.io/badge/PHP-%3E%3D7.0-blue)
![License](https://img.shields.io/badge/License-MIT-green)
![Version](https://img.shields.io/badge/Version-2.0-red)
![Educational](https://img.shields.io/badge/Purpose-Educational-orange)

⚠️ **仅供学习和研究使用** ⚠️

</div>

## 🎉 v2.0 新特性

- 🚀 **性能优化** - 并发请求技术，批量处理速度提升
- 📦 **批量解析** - 支持同时解析多达20个视频
- 🎬 **多格式支持** - 支持8K/4K/1080P60/杜比音效等多种格式
- 🔗 **更多输入格式** - 支持BV号、av号、短链接、完整链接
- 📊 **文件大小预估** - 显示视频和音频文件大小

## 📖 介绍

这是一个功能强大的B站视频解析器，基于PHP开发，提供高性能的视频解析服务。支持批量解析、多种视频格式等高级功能。

## ⚡ 功能特性

### 核心功能
- ✅ 支持BV号、av号、短链接、视频链接解析
- ✅ 批量解析（最多20个视频）
- ✅ 自动选择最优画质
- ✅ 支持DASH格式（音视频分离）
- ✅ 支持FLV格式（音视频合并）
- ✅ RESTful API接口设计

### 性能优化
- ⚡ 并发请求技术（cURL multi）
- 📈 批量处理优化
- 🔄 智能请求调度

### 格式支持
- 🎬 8K (127) - 超高清
- 🎬 4K (120) - 超高清
- 🎬 1080P60 (116) - 高帧率
- 🎬 1080P (80) - 高清
- 🎬 720P60 (74) - 高帧率
- 🎬 720P (64) - 标清
- 🎬 480P (32) - 流畅
- 🎬 360P (16) - 省流
- 🎵 杜比音效支持
- 🎵 无损音频(FLAC)支持

## 🚀 快速开始

### 环境要求

- PHP >= 7.0
- cURL扩展
- JSON扩展

### 安装步骤

1. 克隆仓库
```bash
git clone https://github.com/NORMAL-EX/bilibili-parser.git
cd bilibili-parser
```

2. 配置Web服务器（Apache/Nginx）将项目目录设为Web根目录

3. 确保PHP已启用cURL扩展

## 📚 API文档

### 1. 单个视频解析

#### 请求示例

```bash
# 基础用法 - BV号
GET /parser.php?bv=BV1xx411c7mD

# 指定画质
GET /parser.php?bv=BV1xx411c7mD&quality=4K

# 使用av号
GET /parser.php?bv=av170001

# 使用短链接
GET /parser.php?bv=https://b23.tv/xxxxx

# 带Cookie获取高画质
GET /parser.php?bv=BV1xx411c7mD&cookie=your_cookie&quality=8K
```

#### 参数说明

| 参数 | 类型 | 必需 | 说明 | 示例 |
|------|------|------|------|------|
| bv | string | 是 | 视频标识符 | BV1xx411c7mD |
| quality | string/int | 否 | 目标画质 | 1080P, 4K, 80, 120 |
| cookie | string | 否 | B站登录Cookie | SESSDATA=xxx |

#### 支持的quality参数

- 字符串格式: `8K`, `4K`, `1080P60`, `1080P`, `720P60`, `720P`, `480P`, `360P`
- 数字格式: `127`, `120`, `116`, `80`, `74`, `64`, `32`, `16`

### 2. 批量视频解析

#### 请求示例

```bash
# 逗号分隔方式
GET /parser.php?batch=1&bv=BV1xx411c7mD,BV1yy411c8nE,av170001

# JSON数组方式
GET /parser.php?batch=1&list=["BV1xx411c7mD","BV1yy411c8nE","av170001"]

# 混合输入（BV号、av号、链接）
GET /parser.php?batch=1&bv=BV1xx411c7mD,av170001,https://b23.tv/xxxxx
```

#### 批量响应格式

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
        "data": { /* 视频1数据 */ }
      },
      "1": {
        "code": 0,
        "data": { /* 视频2数据 */ }
      },
      "2": {
        "code": 0,
        "data": { /* 视频3数据 */ }
      }
    }
  }
}
```

### 3. 完整响应示例

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "bvid": "BV1xx411c7mD",
    "title": "视频标题",
    "owner": "UP主名称",
    "pic": "封面图片URL",
    "duration": 300,
    "desc": "视频简介",
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
        "url": "视频下载链接",
        "backup_url": ["备用链接1", "备用链接2"],
        "codecs": "avc1.640032",
        "width": 1920,
        "height": 1080,
        "size": "156.3 MB"
      },
      "audio": {
        "url": "音频下载链接",
        "backup_url": ["备用链接1", "备用链接2"],
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
      "flac": { /* 无损音频信息（如果有）*/ },
      "dolby": { /* 杜比音效信息（如果有）*/ },
      "merge_command": "ffmpeg -i video.mp4 -i audio.mp4 -c copy output.mp4"
    },
    "tips": "成功获取1080P视频"
  }
}
```

## 🎬 使用示例

### PHP调用示例

```php
<?php
// 引入解析器
require_once 'parser.php';

// 创建实例
$parser = new BilibiliParser();

// 单个解析
$result = $parser->parse('BV1xx411c7mD', '1080P');

// 批量解析
$videos = [
    'BV1xx411c7mD',
    'av170001',
    'https://www.bilibili.com/video/BV1yy411c8nE'
];
$results = $parser->parseBatch($videos);

// 处理结果
foreach ($results as $key => $result) {
    if (isset($result['error'])) {
        echo "视频 {$key} 解析失败: {$result['error']}\n";
    } else {
        echo "视频 {$key}: {$result['data']['title']}\n";
        echo "下载链接: {$result['data']['download']['video']['url']}\n";
    }
}
?>
```

### JavaScript调用示例

```javascript
// 单个视频解析
fetch('http://yoursite.com/parser.php?bv=BV1xx411c7mD&quality=1080P')
  .then(res => res.json())
  .then(data => {
    if (data.code === 0) {
      console.log('视频标题:', data.data.title);
      console.log('视频链接:', data.data.download.video.url);
      console.log('音频链接:', data.data.download.audio.url);
    }
  });

// 批量解析
const videos = ['BV1xx411c7mD', 'BV1yy411c8nE', 'av170001'];
fetch(`http://yoursite.com/parser.php?batch=1&bv=${videos.join(',')}`)
  .then(res => res.json())
  .then(data => {
    console.log(`成功解析 ${data.data.success}/${data.data.total} 个视频`);
    Object.values(data.data.results).forEach(result => {
      if (result.code === 0) {
        console.log(result.data.title);
      }
    });
  });
```

### Python调用示例

```python
import requests
import json

# 单个解析
response = requests.get('http://yoursite.com/parser.php', params={
    'bv': 'BV1xx411c7mD',
    'quality': '4K'
})
data = response.json()

if data['code'] == 0:
    print(f"标题: {data['data']['title']}")
    print(f"视频: {data['data']['download']['video']['url']}")
    print(f"音频: {data['data']['download']['audio']['url']}")

# 批量解析
videos = ['BV1xx411c7mD', 'BV1yy411c8nE', 'av170001']
response = requests.get('http://yoursite.com/parser.php', params={
    'batch': 1,
    'bv': ','.join(videos)
})
batch_data = response.json()
print(f"成功: {batch_data['data']['success']}/{batch_data['data']['total']}")
```

## 🛠️ 高级功能

### 自定义User-Agent

```php
$parser->userAgent = 'Your Custom User-Agent';
```

### 代理设置

在 `httpGet` 方法中添加：

```php
curl_setopt($ch, CURLOPT_PROXY, 'http://proxy.example.com:8080');
```

## 📊 性能对比

| 功能 | v1.0 | v2.0 | 提升 |
|------|------|------|------|
| 单个解析 | ~2s | ~1s | 100% ⬆️ |
| 10个批量 | ~20s | ~3s | 566% ⬆️ |
| 并发能力 | 1 | 20 | 1900% ⬆️ |

## 🔧 故障排除

### 常见问题

1. **获取1080P以上画质失败**
   - 需要提供有效的B站登录Cookie
   - 部分视频需要大会员权限

2. **解析速度慢**
   - 检查网络连接
   - 使用批量解析提高效率

3. **批量解析部分失败**
   - 检查输入格式是否正确
   - 某些视频可能已被删除或限制

## ⚠️ 免责声明

1. **本项目仅供学习和研究使用，严禁用于任何商业用途**
2. **使用者应遵守相关法律法规和B站用户服务协议**
3. **请勿使用本工具下载受版权保护的内容**
4. **请勿大量请求造成服务器压力**
5. **使用本工具产生的任何后果由使用者自行承担**
6. **开发者不对使用本工具造成的任何损失负责**

## 📋 更新日志

### v2.0.0 (2024-12)
- ✨ 新增批量解析功能
- ⚡ 实现并发请求优化
- 🎬 支持更多视频格式
- 🔗 支持av号和短链接
- 📊 添加文件大小预估
- 🎵 支持杜比音效和无损音频

### v1.0.0 (2024-12)
- 🎉 初始版本发布
- ✅ 基础解析功能
- ✅ 支持1080P优先

## 🤝 贡献

欢迎提交Issue和Pull Request，但请确保：

1. 代码仅用于学习研究目的
2. 不包含任何商业化功能
3. 遵守开源协议
4. 提交前进行充分测试

## 📄 许可证

本项目采用 MIT 许可证 - 查看 [LICENSE](LICENSE) 文件了解详情

## ⭐ Star History

如果这个项目对你的学习有帮助，请给一个Star支持！

[![Star History Chart](https://api.star-history.com/svg?repos=NORMAL-EX/bilibili-parser&type=Date)](https://star-history.com/#NORMAL-EX/bilibili-parser&Date)

---

<div align="center">

**⚠️ 再次提醒：本项目仅供学习研究，请勿用于非法用途 ⚠️**

Made with ❤️ for Educational Purposes Only

[报告问题](https://github.com/NORMAL-EX/bilibili-parser/issues) | [功能建议](https://github.com/NORMAL-EX/bilibili-parser/discussions)

</div>