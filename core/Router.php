<?php
namespace TypechoPlugin\SolitudeX;

use Solitude\Theme;

class Router extends \Widget\Base
{
    private function ready(): void { if(!Plugin::theme()||$this->options->theme!=='Solitude')throw new \Typecho\Widget\Exception('Not found',404); }
    private function xml(string $xml): void {$this->response->setContentType('application/xml');echo '<?xml version="1.0" encoding="UTF-8"?>'.$xml;}
    private function json($data): void {$this->response->setContentType('application/json');echo Theme::json($data);}
    public function search(): void { $this->ready();$xml='<search>';foreach(Theme::publicPosts() as $p){$xml.='<entry><title>'.Theme::e($p->title).'</title><url>'.Theme::e(Theme::permalink($p)).'</url><content>'.Theme::e(strip_tags($p->content)).'</content><tags>'.Theme::e(implode(',',array_column($p->tags,'name'))).'</tags></entry>';}$this->xml($xml.'</search>'); }
    public function links(): void { $this->ready();$this->response->setContentType('application/json');echo Theme::datasetJson('links'); }
    public function comments(): void { $this->ready();$result=[];foreach(Theme::comments() as $c){$p=\Utils\Helper::widgetById('contents',(int)$c['cid']);$result[]=['id'=>(int)$c['coid'],'author'=>$c['author'],'avatar'=>Theme::avatar($c['mail']??''),'content'=>strip_tags($c['text']),'created'=>date('c',$c['created']),'url'=>Theme::permalink($p).'#comment-'.$c['coid']];}$this->json($result); }
    public function sitemap(): void { $this->ready();$xml='<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>'.Theme::e(Theme::url()).'</loc></url>';foreach(Theme::publicPosts() as $p)$xml.='<url><loc>'.Theme::e(Theme::permalink($p)).'</loc><lastmod>'.date('c',$p->modified).'</lastmod></url>';$db=$this->db;$pages=$db->fetchAll($db->select('cid')->from('table.contents')->where('type = ?','page')->where('status = ?','publish')->where('created <= ?',time())->where('(password IS NULL OR password = ?)',''));foreach($pages as $row){$p=\Utils\Helper::widgetById('contents',(int)$row['cid']);$xml.='<url><loc>'.Theme::e(Theme::permalink($p)).'</loc></url>';}$this->xml($xml.'</urlset>'); }
    public function manifest(): void { $this->ready();$this->response->setContentType('application/manifest+json');echo Theme::json(['name'=>$this->options->title,'short_name'=>$this->options->title,'start_url'=>Theme::url(),'scope'=>rtrim(parse_url($this->options->siteUrl,PHP_URL_PATH)?:'/','/').'/','display'=>'standalone','background_color'=>Theme::get('pwa.background_color','#f7f9fe'),'theme_color'=>Theme::get('pwa.theme_color',Theme::get('theme_color.light')),'icons'=>[['src'=>Theme::media(Theme::get('site.icon')),'sizes'=>'any']]]); }
    public function page(): void { $this->ready();$slug=trim($this->request->getPathInfo(),'/');$row=$this->db->fetchRow($this->db->select('cid')->from('table.contents')->where('slug = ?',$slug)->where('type = ?','page')->where('status = ?','publish'));if(!$row)throw new \Typecho\Widget\Exception('请先初始化特色页面。',404);$page=\Utils\Helper::widgetById('contents',(int)$row['cid']);$this->response->redirect($page->permalink,true); }
    public function custom(): void {
        $this->ready();$path=$this->request->getPathInfo();$paths=Plugin::paths();$cid=$paths[rtrim(rawurldecode($path),'/')?:'/']??null;if(!$cid)throw new \Typecho\Widget\Exception('Not found',404);
        $post=\Utils\Helper::widgetById('contents',$cid);if(!$post||$post->status!=='publish'||$post->created>time())throw new \Typecho\Widget\Exception('Not found',404);
        $canonical=Theme::field($post,'url');if($canonical&&$canonical!==$path){$this->response->redirect(Theme::url($canonical),true);return;}if(!$canonical){$this->response->redirect($post->permalink,true);return;}
        \Widget\Archive::alloc(['type'=>$post->type,'checkPermalink'=>false],['cid'=>$cid])->render();
    }
    public function series(): void {
        $this->ready();require_once __TYPECHO_ROOT_DIR__.__TYPECHO_THEME_DIR__.'/Solitude/core/View.php';
        $slug=rawurldecode((string)$this->request->get('slug',''));$groups=[];
        foreach(Theme::publicPosts() as $post)foreach(Theme::series($post) as $name)$groups[$name][]=$post;
        if($slug&&!isset($groups[$slug]))throw new \Typecho\Widget\Exception('Not found',404);
        $title=$slug?:Theme::t('series');Theme::render('header',['type'=>$slug?'term':'taxonomy','title'=>$title]);
        echo '<main class="layout '.($slug?'':'hide-aside').' tw:[.layout&]:relative" id="content-inner"><div id="'.($slug?'tag':'page').'">';
        if($slug){
            echo '<div class="article-sort-title">'.Theme::e($title).'</div><div class="article-sort">';
            foreach($groups[$slug] as $post)\Solitude\View::sortItem($post);
            echo '</div>';
        }else{
            uksort($groups,fn($a,$b)=>(count($groups[$b])<=>count($groups[$a]))?:strcmp($a,$b));
            echo '<h1 class="page-title">'.Theme::e($title).'</h1><div class="category-lists"><div class="tag-cloud-list is-center">';
            foreach($groups as $name=>$posts)echo '<a href="'.Theme::e(Theme::url('solitude/series/'.rawurlencode($name))).'" title="'.Theme::e($name).'"><span class="tags-punctuation"><i class="solitude fas fa-hashtag"></i>'.Theme::e($name).'<span class="tagsPageCount">'.count($posts).'</span></span></a>';
            echo '</div></div>';
        }
        echo '</div>';if($slug)Theme::render('aside',['scope'=>'page']);echo '</main>';Theme::render('footer');
    }
}
