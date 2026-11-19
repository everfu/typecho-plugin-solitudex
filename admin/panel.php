<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
\Widget\User::alloc()->pass('administrator');
$request=\Typecho\Request::getInstance();
$available=\Widget\Options::alloc()->theme==='Solitude'&&\TypechoPlugin\SolitudeX\Plugin::theme();
$types=['about'=>['关于','user','介绍与内容区块'],'links'=>['友链','link','分组与链接'],'kit'=>['装备','laptop','设备与规格'],'brevity'=>['即刻','comment-dots','短文与媒体']];
$type=$solitudeContentType??$request->get('type');
if(!is_string($type)||!isset($types[$type]))$type='about';
$menuUpdated=\TypechoPlugin\SolitudeX\Plugin::ensureContentPanel();
if($request->isGet()&&(!isset($solitudeContentType)||($menuUpdated&&$request->get('sl-inline')!=='1'))){
    $url=\Widget\Options::alloc()->adminUrl.'extending.php?panel='.urlencode('SolitudeX/admin/'.$type.'.php');
    if($request->get('sl-inline')==='1')$url.='&sl-inline=1';
    \Widget\Options::alloc()->response->redirect($url);
}
$title=$types[$type][0];
include 'header.php';include 'menu.php';
?>
<main class="main sl-admin-panel"><div class="body container">
<div class="typecho-page-title"><h2 id="sl-content-page-title"><?php echo $title; ?></h2><a href="<?php \Widget\Options::alloc()->adminUrl('options-theme.php'); ?>">主题设置</a></div>
<?php if(!$available): ?>
<h2>Solitude 主题尚未启用</h2><p>请先安装并启用 Solitude 主题。</p><a class="btn primary" href="<?php \Widget\Options::alloc()->adminUrl('themes.php'); ?>">打开外观管理</a>
<?php else:
\Solitude\Theme::migrateContent();\Solitude\Theme::bundle('admin');
$form=new \Typecho\Widget\Helper\Form(\Utils\Helper::security()->getIndex('/action/solitude-x?op=save-data'),\Typecho\Widget\Helper\Form::POST_METHOD);
$form->setAttribute('data-sl-content-form','1');
$form->addInput(new \Typecho\Widget\Helper\Form\Element\Hidden('type',null,$type));
\Solitude\Settings::workspace(['content'=>['页面内容','pen-to-square',array_map(fn($item)=>$item[0],$types)]],'Solitude 内容','data-content-type="'.$type.'"');
echo '<section class="sl-static" data-group="content" data-tab="'.$type.'"><a target="_blank" rel="noopener" href="'.\Solitude\Theme::e(\Solitude\Theme::url(($type==='kit'?'equipment':$type).'/')).'">查看'.$title.'页面 ↗</a><div class="sl-content-tools typecho-list-operate"><label>筛选条目<input type="search" data-sl-filter placeholder="名称、介绍或链接"></label><button type="button" data-sl-expand="true">全部展开</button><button type="button" data-sl-expand="false">全部收起</button><span role="status" data-sl-count></span></div></section>';
$field=new \Solitude\ArrayField('sl_data_'.$type,null,\Solitude\Theme::dataset($type),$title.'内容','');
$field->setAttribute('class','sl-field sl-type-array');$field->setAttribute('data-group','content');$field->setAttribute('data-tab',$type);$form->addInput($field);
$form->addInput(new \Typecho\Widget\Helper\Form\Element\Hidden('sl_complete',null,'1'));
$form->render();\Solitude\Settings::saveBar(true);
endif; ?>
</div></main>
<?php include 'footer.php'; ?>
