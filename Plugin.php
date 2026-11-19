<?php
namespace TypechoPlugin\SolitudeX;

use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Utils\Helper;

if(!defined('__TYPECHO_ROOT_DIR__'))exit;
require_once __DIR__.'/core/Data.php';require_once __DIR__.'/core/Shortcodes.php';require_once __DIR__.'/core/Action.php';require_once __DIR__.'/core/Router.php';

/**
 * Solitude 的内容组件、特色页面管理和扩展接口。
 * @package SolitudeX
 * @author everfu
 * @version 1.0.0
 */
final class Plugin implements PluginInterface
{
    private const CONTENT_PANELS = [
        'SolitudeX/admin/about.php' => '关于',
        'SolitudeX/admin/links.php' => '友链',
        'SolitudeX/admin/kit.php' => '装备',
        'SolitudeX/admin/brevity.php' => '即刻'
    ];

    public static function theme(): bool {
        $file=__TYPECHO_ROOT_DIR__.__TYPECHO_THEME_DIR__.'/Solitude/core/Theme.php';if(!is_file($file))return false;require_once $file;require_once dirname($file).'/Settings.php';return true;
    }
    public static function activate(){
        if(version_compare(PHP_VERSION,'8.2','<'))throw new \Typecho\Plugin\Exception('SolitudeX 需要 PHP 8.2+。');
        if(!self::theme())throw new \Typecho\Plugin\Exception('请先安装 Solitude 主题。');
        Helper::addAction('solitude-x',Action::class);
        self::registerRoutes();
        \Typecho\Plugin::factory('Widget\\Base\\Contents')->content=__CLASS__.'::content';
        \Typecho\Plugin::factory('Widget\\Base\\Contents')->filter=__CLASS__.'::canonical';
        \Typecho\Plugin::factory('Widget\\Archive')->handleInit=__CLASS__.'::archiveFilters';
        \Typecho\Plugin::factory('admin/write-post.php')->bottom=__CLASS__.'::editor';
        \Typecho\Plugin::factory('admin/write-page.php')->bottom=__CLASS__.'::editor';
        \Typecho\Plugin::factory('Widget\\Contents\\Post\\Edit')->write=__CLASS__.'::validatePaths';
        \Typecho\Plugin::factory('Widget\\Contents\\Page\\Edit')->write=__CLASS__.'::validatePaths';
        foreach(['finishPublish','finishSave','finishDelete'] as $hook){\Typecho\Plugin::factory('Widget\\Contents\\Post\\Edit')->{$hook}=__CLASS__.'::registerRoutes';\Typecho\Plugin::factory('Widget\\Contents\\Page\\Edit')->{$hook}=__CLASS__.'::registerRoutes';}
        self::ensureContentPanel();
    }
    public static function deactivate(){
        Helper::removeAction('solitude-x');
        $original = Helper::options()->panelTable;
        $panelTable = $original;
        foreach ([2, 3] as $index) {
            if (isset($panelTable['child'][$index])) {
                $panelTable['child'][$index] = array_filter(
                    $panelTable['child'][$index],
                    fn($entry) => !self::isContentPanelEntry($entry)
                );
            }
        }
        if (isset($panelTable['file'])) {
            $panelFiles = array_map('urlencode', self::contentPanelFiles());
            $panelTable['file'] = array_filter($panelTable['file'], fn($file) => !in_array($file, $panelFiles, true));
        }
        if ($panelTable !== $original) Helper::setOption('panelTable', $panelTable);
        if(self::theme())foreach(\Solitude\Theme::stored('solitude:routes',[]) as $name)Helper::removeRoute($name);
    }
    public static function config(Form $form){$form->addInput(new \Typecho\Widget\Helper\Form\Element\Text('notice',null,'在「管理」菜单中分别打开「关于」「友链」「装备」「即刻」。主题参数在「外观 → 设置外观」管理。','SolitudeX'));}
    public static function personalConfig(Form $form){}
    private static function contentPanelFiles(): array {
        return [...array_keys(self::CONTENT_PANELS), 'SolitudeX/admin/panel.php'];
    }
    private static function isContentPanelEntry(array $entry): bool {
        $url = $entry[2] ?? '';
        $parameters = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $parameters);
        return parse_url($url, PHP_URL_PATH) === 'extending.php'
            && in_array($parameters['panel'] ?? null, self::contentPanelFiles(), true);
    }
    public static function ensureContentPanel(): bool {
        $panelTable = Helper::options()->panelTable;
        $panelEntries = [];
        foreach (self::CONTENT_PANELS as $file => $label) {
            $panelEntries[] = [$label, $label, 'extending.php?panel=' . urlencode($file), 'administrator', false, ''];
        }
        $panelFiles = array_map('urlencode', self::contentPanelFiles());
        $registeredFiles = array_intersect($panelTable['file'] ?? [], $panelFiles);
        $legacyEntries = array_filter($panelTable['child'][2] ?? [], self::isContentPanelEntry(...));
        $currentEntries = array_values(array_filter($panelTable['child'][3] ?? [], self::isContentPanelEntry(...)));
        if (
            !$legacyEntries
            && $currentEntries === $panelEntries
            && count($registeredFiles) === count($panelFiles)
            && !array_diff($panelFiles, $registeredFiles)
        ) {
            return false;
        }

        // Register all four native panels together and preserve other plugins' entries and keys.
        foreach ([2, 3] as $index) {
            $panelTable['child'][$index] = array_filter(
                $panelTable['child'][$index] ?? [],
                fn($entry) => !self::isContentPanelEntry($entry)
            );
        }
        foreach ($panelEntries as $entry) $panelTable['child'][3][] = $entry;
        $panelTable['file'] = array_filter($panelTable['file'] ?? [], fn($file) => !in_array($file, $panelFiles, true));
        foreach ($panelFiles as $file) $panelTable['file'][] = $file;
        Helper::setOption('panelTable', $panelTable);
        return true;
    }
    public static function content($text,$widget): string {
        if(\Widget\Options::alloc()->theme!=='Solitude')return $widget->isMarkdown?\Utils\Markdown::convert($text):$text;
        self::theme();return (new Shortcodes())->render((string)$text,(bool)$widget->isMarkdown);
    }
    public static function canonical(array $row,$widget): array {
        if(\Widget\Options::alloc()->theme!=='Solitude'||!in_array($row['type']??'', ['post','page'],true))return $row;
        if(!self::theme())return $row;
        $path=\Solitude\Theme::canonicalPath((int)$row['cid']);
        if(!empty($path))$row['permalink']=\Solitude\Theme::url(trim($path));
        return $row;
    }
    public static function archiveFilters($archive,$select): void {
        if(\Widget\Options::alloc()->theme!=='Solitude'||($archive->parameter->type??'')!=='page')return;
        $year=$archive->request->get('year');if($year===null)return;
        $query=\Typecho\Db::get()->select('template')->from('table.contents')->where('type = ?','page');
        if($archive->request->is('cid'))$query->where('cid = ?',(int)$archive->request->get('cid'));
        elseif($archive->request->is('slug'))$query->where('slug = ?',$archive->request->get('slug'));
        else return;
        $row=\Typecho\Db::get()->fetchRow($query);
        if(($row['template']??'')!=='page-archives.php')return;
        // A UI year filter must not restrict the native page's creation date.
        $archive->request->setParam('sl_archive_year',$year);
        $archive->request->setParam('year','');
    }
    private static function pathKey(string $path): string {return rtrim(rawurldecode($path),'/')?:'/';}
    public static function editor($widget=null): void {
        if(\Widget\Options::alloc()->theme!=='Solitude'||!self::theme())return;
        \Solitude\Theme::bundle('editor');echo '<div data-sl-editor="'.\Solitude\Theme::e(Helper::security()->getIndex('/action/solitude-x')).'" style="display:flex;gap:8px;align-items:center;margin:16px 0"><label for="sl-component-picker">Solitude 组件</label><select id="sl-component-picker">';foreach(Shortcodes::KINDS as $kind)echo '<option value="'.$kind.'">'.$kind.'</option>';echo '</select><button type="button" class="btn" data-insert>插入组件</button><button type="button" class="btn" data-preview>组件预览</button></div>';
    }
    private static function customPaths(): iterable
    {
        $db = \Typecho\Db::get();
        $rows = $db->fetchAll($db->select('cid', 'str_value')->from('table.fields')
            ->where('name = ? OR name = ?', 'sl_url', 'sl_aliases'));

        foreach ($rows as $row) {
            foreach (preg_split('/\r?\n/', (string) $row['str_value']) as $value) {
                $path = trim($value);
                if ($path !== '') {
                    yield [$path, (int) $row['cid']];
                }
            }
        }
    }
    public static function paths(int $exclude = 0): array
    {
        if (!self::theme()) {
            return [];
        }

        $paths = [];
        foreach (self::customPaths() as [$path, $cid]) {
            if ($cid !== $exclude) {
                $paths[self::pathKey($path)] = $cid;
            }
        }
        return $paths;
    }
    public static function validatePaths(array $contents,$widget): array {
        $fields=$widget->request->getArray('fields');$cid=(int)$widget->request->get('cid',0);$paths=self::paths($cid);$seen=[];
        foreach(['sl_url','sl_aliases'] as $key){$value=$fields[$key]??'';if(is_array($value))$value=$value['value']??'';foreach(preg_split('/\r?\n/',(string)$value) as $path){$path=trim($path);if($path==='')continue;
            $key=self::pathKey($path);
            if(!preg_match('~^/(?!/)[^?#\\\\\x00-\x20]*$~',$path)||str_contains($key,'..')||preg_match('~^/(?:admin|action|solitude|feed|usr|install|index\.php)(?:/|$)~i',$key)||in_array($key,['/','/install.php','/index.xml'],true)||preg_match('/%(?:2f|5c|00|2e)/i',$path))throw new \Typecho\Widget\Exception('自定义路径必须是有效的站内路径，且不能占用系统地址。',400);
            if($key!==$path&&$key!==(rtrim($path,'/')?:'/'))throw new \Typecho\Widget\Exception('路径请直接使用文字，勿使用百分号编码。',400);
            if(isset($paths[$key])||isset($seen[$key]))throw new \Typecho\Widget\Exception('路径已被使用：'.$path,400);$seen[$key]=true;
            $table=\Solitude\Theme::options()->routingTable;unset($table[0]);$found=(new \Typecho\Router\Parser($table))->parse();foreach($found as $name=>$route){if(!is_array($route)||str_starts_with((string)$name,'sl_'))continue;$url=$route['url']??'';if($url===$path)throw new \Typecho\Widget\Exception('路径与系统路由冲突。',400);}
            $db=\Typecho\Db::get();$rows=$db->fetchAll($db->select('cid')->from('table.contents')->where('type = ? OR type = ?','post','page'));
            $indexPath=rtrim(parse_url(\Solitude\Theme::options()->index,PHP_URL_PATH)?:'','/');
            foreach($rows as $row)if((int)$row['cid']!==$cid){$p=Helper::widgetById('contents',(int)$row['cid']);$native=\Typecho\Common::url(\Typecho\Router::url($p->type,$p),\Solitude\Theme::options()->index);$nativePath=parse_url($native,PHP_URL_PATH);if(self::pathKey(substr($nativePath,strlen($indexPath)))===$key)throw new \Typecho\Widget\Exception('路径与已有文章或页面冲突。',400);}
            if(preg_match('~/(?:comment|trackback)$~',$key))throw new \Typecho\Widget\Exception('路径与评论操作冲突。',400);
            foreach($found as $name=>$route)if(is_array($route)&&!str_starts_with((string)$name,'sl_')&&!in_array($name,['post','page','feedback'],true)&&isset($route['regx'])&&preg_match($route['regx'],$key))throw new \Typecho\Widget\Exception('路径与系统路由冲突。',400);
        }}return $contents;
    }
    public static function registerRoutes(...$unused): void {
        if(!self::theme())return;
        // Existing installations already point their finish-write hooks here.
        if($unused)\Solitude\Theme::invalidatePosts();
        foreach(\Solitude\Theme::stored('solitude:routes',[]) as $name)Helper::removeRoute($name);
        $routes=[];$add=function($name,$path,$action)use(&$routes){Helper::addRoute($name,$path,Router::class,$action,'index');$routes[]=$name;};
        foreach(['search.xml'=>'search','links.json'=>'links','comments.json'=>'comments','sitemap.xml'=>'sitemap','manifest.webmanifest'=>'manifest','series/'=>'series'] as $path=>$action)$add('sl_'.md5($path),'/solitude/'.$path,$action);
        $add('sl_series_term','/solitude/series/[slug]','series');
        foreach(['about','links','equipment','brevity','music','message','recentcomments','archives','categories','tags'] as $slug)$add('sl_page_'.$slug,'/'.$slug.'/','page');
        foreach (self::customPaths() as [$path]) {
            $add('sl_path_' . md5($path), $path, 'custom');
        }
        \Solitude\Theme::put('solitude:routes',$routes);
    }
}
