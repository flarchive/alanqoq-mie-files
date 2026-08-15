# Flarum 2.x 的 Mie Files

面向 Flarum 2.x 的私有文件库和上传扩展。文件会同时通过标准化扩展名和 PHP `finfo` MIME 类型进行验证，默认存储在 Flarum 公共目录之外，并通过需要鉴权的流式路由提供访问。

## 安装

```bash
composer require alanqoq/mie-files
php flarum extension:enable mie-files
php flarum cache:clear
```

本扩展面向 Flarum `^2.0@beta`，已在 `v2.0.0-rc.5` 上完成验证。

## 功能行为

- 编辑器会添加 `fas fa-file-upload` 和 `fas fa-photo-video` 图标按钮。
- 上传支持文件选择、拖放、多文件进度，以及类型、大小和权限错误提示；每位用户都有独立的文件库。
- 双击文件库项目时，客户端会请求服务器生成已配置的插入模板。浏览器不会自行拼接对象存储 URL。
- 文件分类使用明确的扩展名/MIME 类型组合。只有完整检测到的 MIME 类型不同时，`ogg` 和 `webm` 才可以同时出现在音频和视频分类中；其他重复扩展名会被拒绝。
- 内置预设覆盖图片、PDF、Word、表格、压缩包、音频和视频，并使用真实扩展名及完整 MIME 字符串。
- 分类权限键为 `mie-files.category.{permission-name}.{view|download|upload}`，会以 `File category-{permission-name}-{action}` 的形式显示在 Flarum 权限界面中。扩展还会注册 `mie-files.view-other` 和 `mie-files.delete-other`。

## 存储

`local` 始终可用，会将文件存储在公共 Web 根目录之外的 `storage/mie-files` 中。

DogeCloud 使用兼容 AWS S3 的 SDK。AccessKeyId 和 AccessKeySecret 会在服务端加密，API 永远不会返回它们。API 负载也不会包含内部对象键和 endpoint 值。

- 公共基础 URL 为空：使用 Flarum 代理模式。每次交付都会进行鉴权、流式传输和计数；跟踪模板会遵守防盗链设置。
- 设置公共基础 URL：使用直链模式。服务端授权生成后，会使用随机对象路径返回配置的公共 URL。一旦公共 URL 发出，PHP 统计、防盗链和每次下载鉴权都无法继续生效。管理界面要求明确确认后才能保存此模式。

## 维护

在扩展设置页设置孤儿文件保留期限，然后运行：

```bash
php flarum mie-files:clean-orphans
```

该命令也会注册到 Flarum 的每日计划任务中。它只会删除超过保留期限、状态为成功且没有关联帖子的文件。对象删除失败时，文件记录会保留为 `delete_failed`，等待重试，不会被错误标记为已成功删除。

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

本扩展按运行时边界而不是功能名称划分。请求流程、各目录的职责，以及刷新本地 Graphify 代码图的命令，请参阅[架构图](docs/ARCHITECTURE.md)。

Graphify 生成的代码图文件位于 `graphify-out/`，并已随本仓库提交。结构发生变化后，可使用以下命令刷新：

```bash
graphify update . --no-cluster
graphify tree --root . --label "Mie Files for Flarum"
```
