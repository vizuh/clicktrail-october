English | [Português](README.pt-BR.md) | [Deutsch](README.de.md) | [中文](README.zh-CN.md)

<div align="center">

**vizuh/october-clicktrail**

将观测到的获客上下文附加到已配置的 October CMS 表单提交中。

</div>

[![CI](https://github.com/vizuh/clicktrail-october/actions/workflows/ci.yml/badge.svg)](https://github.com/vizuh/clicktrail-october/actions/workflows/ci.yml)
[![Packagist Version](https://img.shields.io/packagist/v/vizuh/october-clicktrail)](https://packagist.org/packages/vizuh/october-clicktrail)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

## 目录

- [为什么](#为什么)
- [安装](#安装)
- [快速上手](#快速上手)
- [组件](#组件)
- [归因采集](#归因采集)
- [设置](#设置)
- [同意状态](#同意状态)
- [投递](#投递)
- [有何不同](#有何不同)
- [测试](#测试)
- [许可协议](#许可协议)

## 为什么

ClickTrail 将已存储的首次触点和末次触点上下文附加到已配置的 October 表单提交中，但不判断哪个营销触点导致了提交。共享内核 [`clicktrail/php-sdk`](https://github.com/vizuh/clicktrail-php) 使用经黄金 fixtures 验证的合并规则计算 payload。

需要 October CMS 3（Laravel 10 底座）、PHP 8.1+ 以及 `clicktrail/php-sdk`（^0.1@dev）。

## 安装

本仓库根目录**就是**插件目录。将其克隆或复制到 October 项目中：

```bash
cd <october-project>/plugins
mkdir -p vizuh && cd vizuh
git clone https://github.com/vizuh/clicktrail-october clicktrail
php artisan october:up
```

然后在“设置”中启用 **ClickTrail** 并填入你的 Site ID。

## 快速上手

把追踪器加入布局的 `<head>`，把隐藏字段加进任意 October 表单：

```twig
{# layouts/default.htm；<head> 内 #}
{% component 'clickTrailTracker' %}

{# 任意 October 表单 #}
<form data-request="onSubmit">
    {% component 'attributionHidden' %}
    ...
</form>
```

一位访客从付费搜索广告进入，浏览后提交了表单。此时 POST 请求携带完整的首次触点上下文：

```text
ct_utm_source=google          ← 首次触点，不可变
ct_utm_medium=cpc             ← 即使之后有直接访问也不变
ct_gclid=EAIaIQobChMI...      ← 在获得广告同意的前提下采集的点击 ID
ct_landing_page=https://example.com/promo
ct_initial_referrer=https://www.google.com/
ct_consent_state=granted
```

之后的任何直接访问都不会改变结果；首次触点保持不变，已存储的末次触点持续保留。这条合并法则由共享 SDK 实现，是被测试验证过的，不是口头承诺。

## 组件

### `clickTrailTracker`；第一方加载器

```twig
{% component 'clickTrailTracker' %}
```

渲染一个包含已配置 ClickTrail 加载器和 Site ID 的 script 标签。端点由宿主选择；此组件不注入其他标签。

### `attributionHidden`；任意表单上的归因字段

```twig
{% component 'attributionHidden' %}
```

为每个采集到的属性渲染一个隐藏输入框，字段清单与 GTM 归因变量一致：访客/会话/事件 ID、`utm_*`、广告点击 ID（`gclid`、`fbclid`、`msclkid`、`ttclid` 等）、落地页、初始 referrer 以及同意状态。取值来自会话中保存的合并后的触点对；字段值本身从不参与归因逻辑判定。

## 归因采集

页面展示和 October AJAX 框架请求会被自动观测（`cms.page.beforeDisplay` / `ajax.beforeRun`）。每个请求都会被读取为一个触点，并由共享的 `TouchMerger` 合并进会话状态。无需编写任何胶水代码。

## 设置

所有选项位于 设置 → ClickTrail：

| 设置项 | 默认值 | 用途 |
|---|---|---|
| Site ID | 空 | 向你的 ClickTrail 账户标识本站点 |
| API 端点 | 空 | Payload 的发送目标；同时提供加载器 |
| 同意解析器类 | 空 | 自定义的 `ConsentResolverInterface` 实现，返回规范化快照；留空 = 所有信号均为 "unknown" |
| 归因持久化需要 `analytics_storage` | 开启 | 未获得分析类同意时不存储任何数据 |
| 广告点击 ID 存储需要 `advertising_storage` | 开启 | 未获得广告类同意时，将 gclid/fbclid/... 从存储中剔除 |
| 转发哈希后的线索数据（`ad_user_data`） | 关闭 | 哈希线索转发的额外闸门；仍需获得 `ad_user_data` 授权 |
| 第一方代理 | 关闭 | 从你自己的域名提供 ClickTrail 加载器 |

## 同意状态

ClickTrail 不取代你的同意管理平台；它服从该平台。规范化的同意契约（能力、快照结构、行为矩阵）见 [`docs/consent-compatibility-plan.md`](../../docs/consent-compatibility-plan.md)。

- 提供方：实现 `Vizuh\ClickTrail\Classes\Consent\ConsentResolverInterface`（返回当前 `ClickTrail\Consent\ConsentSnapshot`），并在 设置 → 隐私 → Consent resolver class 中注册。真正的 CMP 适配器暂缓；WordPress 插件直接读取 WP Consent API。
- 同意状态未知时：**不存储、不发送**。被抑制的操作会通过 `suppressionReason()` 记录到诊断信息中。
- 解析出的快照与归因状态一同持久化，并随每次提交传递（每个 payload 中的 `consent` 键）。

## 投递

规范化 payload 由共享 SDK 针对已存储的归因触点对进行序列化（带 `schema_version` 标记、点分式 `attribution.*` 键）。定时队列刷新钩子已注册就绪；持久化事件的传输通道将在 clicktrail-php 通过一致性门禁（parity gate）后上线。

## 有何不同

| 常见的分析方案 | ClickTrail for October |
|---|---|
| 会话和页面躺在面板里 | 广告系列、关键词、点击 ID 和落地页落在**每一条提交记录上** |
| 自己维护的客户端标签 | 两个 Twig 组件，一个第一方脚本 |
| 各平台各自重复实现归因逻辑 | 一台确定性引擎，在 WordPress、GTM 和 PHP 集成间以样例统一验证 |

## 测试

GitHub Actions CI 在每次推送时对所有 PHP 文件执行 lint（[workflow](https://github.com/vizuh/clicktrail-october/blob/main/.github/workflows/ci.yml)）。

## 许可协议

MIT; Copyright (c) 2026 Vizuh OÜ。详见 [LICENSE](LICENSE)。
