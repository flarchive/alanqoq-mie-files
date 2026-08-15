# Mie Files：Flarum 2.x 私有文件库

面向 Flarum 2.x 的私有文件库与上传扩展。上传文件会根据规范化后的扩展名和 PHP `finfo` 检测到的 MIME 类型进行双重校验。默认情况下，文件存储在 Flarum Web 根目录之外，并通过需鉴权的流式路由访问。

## 安装

```bash
composer require alanqoq/mie-files
php flarum extension:enable mie-files
php flarum cache:clear
```

本扩展兼容 Flarum `^2.0@beta`，并已在 `v2.0.0-rc.5` 上完成验证。

## 功能

- 编辑器中会新增 `fas fa-file-upload` 和 `fas fa-photo-video` 两个图标按钮。
- 支持通过文件选择或拖放上传多个文件，并显示上传进度；文件类型、大小或权限不符合要求时会给出提示。每位用户均拥有独立的文件库。
- 双击文件库中的项目时，客户端会请求服务器生成已配置的插入模板；浏览器不会自行拼接对象存储 URL。
- 文件分类通过明确的扩展名和 MIME 类型组合进行定义。仅当检测出的完整 MIME 类型不同，`ogg` 和 `webm` 才可同时用于音频和视频分类；其余重复扩展名均会被拒绝。
- 内置预设涵盖图片、PDF、Word 文档、表格、压缩包、音频和视频，并使用实际扩展名与完整 MIME 字符串。
- 分类权限键的格式为 `mie-files.category.{permission-name}.{view|download|upload}`，在 Flarum 权限页面中显示为 `File category-{permission-name}-{action}`。扩展还会注册 `mie-files.view-other` 和 `mie-files.delete-other`。

## 存储

`local` 存储始终可用，文件保存在公共 Web 根目录之外的 `storage/mie-files` 中。

DogeCloud 存储使用 AWS S3 兼容 SDK。`AccessKeyId` 与 `AccessKeySecret` 会在服务端加密，API 响应中不会返回它们，也不会包含内部对象键或 `endpoint` 地址。

- 未设置公共基础 URL 时，使用 Flarum 代理模式。每次访问均会经过鉴权、流式传输和计数；生成的插入模板会遵循防盗链设置。
- 设置公共基础 URL 后，使用直链模式。服务端完成授权后，会使用随机对象路径生成并返回基于该公共基础 URL 的链接。一旦向客户端返回公共 URL，PHP 下载统计、防盗链与每次下载时的鉴权将不再生效。在管理界面中，必须明确确认后才能保存此模式。

## 清理未关联文件

请先在扩展设置页面中设置未关联文件的保留期限，然后运行：

```bash
php flarum mie-files:clean-orphans
```

该命令还会注册为 Flarum 的每日计划任务。它仅删除超过保留期限、上传状态为成功且未关联任何帖子的文件。若删除存储对象失败，文件记录会保留并标记为 `delete_failed`，以便后续重试；系统不会将其误标记为已删除。

## 开发检查

```bash
composer validate --strict
composer run lint
composer run analyse
composer run test
composer run test:integration

cd js
npm ci --ignore-scripts
npm run typecheck:common
npm run typecheck:forum
npm run typecheck:admin
npm run test:common
npm run test:forum
npm run test:admin
npm run build
```

## 架构

本扩展按运行时边界划分模块，而非按功能名称划分。请求流程、各目录的职责，以及刷新本地 Graphify 代码图的命令，请参阅 [架构图](docs/ARCHITECTURE.md)。

由 Graphify 生成的代码图保存在 `graphify-out/`，且已随仓库提交。项目结构变更后，可使用以下命令刷新：

```bash
graphify update . --no-cluster
graphify tree --root . --label "Mie Files for Flarum"
```
