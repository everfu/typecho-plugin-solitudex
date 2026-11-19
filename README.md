# SolitudeX for Typecho

SolitudeX 是 Solitude 主题的配套插件，提供短代码、原生编辑器工具、特色内容管理和扩展路由。

## 安装

需要 Typecho 1.3.0、PHP 8.2 或更新版本，以及安装在 `usr/themes/Solitude/` 的 Solitude 主题。

1. 从 GitHub Releases 下载 `SolitudeX-1.0.0.zip`。解压后将 `SolitudeX/` 放入 Typecho 的 `usr/plugins/`，使 `Plugin.php` 位于 `usr/plugins/SolitudeX/` 根部。
2. 在「控制台 → 插件」启用 **SolitudeX**。
3. 在「管理」菜单编辑关于、友链、装备和即刻。在主题的「页面设置 → 页面初始化」可以补建特色页面。

升级时覆盖同名目录。停用插件会撤销注册的菜单和扩展路由，已保存的特色内容继续保存在 Typecho 数据库中。

## 源码目录

`Plugin.php` 注册 Typecho 扩展，`admin/` 提供原生后台入口，`core/` 包含动作、路由、数据处理和短代码解析。编辑器与后台界面使用 Solitude 主题的资源。

## 许可证

项目采用 Apache-2.0 许可证。[LICENSE](LICENSE)
