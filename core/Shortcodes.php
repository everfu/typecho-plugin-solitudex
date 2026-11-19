<?php
namespace TypechoPlugin\SolitudeX;

final class Shortcodes
{
    public const KINDS=['audio','bubble','button','bvideo','card','chartjs','checkbox','flink','fold','gallery','galleryGroup','galleryItem','gitea','gitee','github','gitlab','hideBlock','hideInline','hideToggle','img','inlineImg','keyboard','label','link','mermaid','note','p','radio','score','series','span','spoiler','subnote','tab','tabs','timeline','timenode','typeit','video','videos','youtube'];
    private array $html=[];
    private string $prefix;
    public function __construct(){ $this->prefix='SLTOKEN'.bin2hex(random_bytes(8)); }
    private function token(string $html): string {$key=$this->prefix.count($this->html).'END';$this->html[$key]=$html;return $key;}
    private static function e($value): string {return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
    private static function url($value,bool $media=false): string {
        $value=(string)$value;if($value===''||$value==='#')return '#';
        if(!preg_match('~^(https?://|/|#|mailto:|tel:)~i',$value))throw new \InvalidArgumentException('Unsafe URL');
        if(preg_match('~^(https?://|#|mailto:|tel:)~i',$value))return $value;
        if(class_exists('\Solitude\Theme'))return $media?\Solitude\Theme::media($value):\Solitude\Theme::url($value);
        return $value;
    }
    public function render(string $text,bool $markdown=true): string {
        $text=preg_replace_callback('~<(pre|code)\b[^>]*>[\s\S]*?</\1>~i',fn($m)=>$this->token($m[0]),$text);
        if($markdown){
            $text=preg_replace_callback('/(^[ \t]*(`{3,}|~{3,})[^\n]*\n[\s\S]*?^[ \t]*\2[`~]*[ \t]*\r?$)/m',fn($m)=>$this->token(\Utils\Markdown::convert($m[1])),$text);
            $text=preg_replace_callback('/(?:^(?: {4}|\t)[^\n]*(?:\n|$))+/m',fn($m)=>$this->token(\Utils\Markdown::convert($m[0])),$text);
            $text=preg_replace_callback('/(`+)([^`\n]+)\1/',fn($m)=>$this->token('<code>'.self::e($m[2]).'</code>'),$text);
        }
        $html=$this->parse($text,$markdown);
        foreach(array_reverse($this->html,true) as $token=>$value){$html=str_replace('<p>'.$token.'</p>',$value,$html);$html=str_replace($token,$value,$html);}
        return $html;
    }
    private function parse(string $text,bool $markdown=true): string {
        $pattern='/\[sl-([a-zA-Z][a-zA-Z0-9]*)(\s+(?:"[^"]*"|\x27[^\x27]*\x27|[^\]"\x27])*)?\]/';
        $offset=0;$result='';$map=array_change_key_case(array_combine(self::KINDS,self::KINDS));
        while(preg_match($pattern,$text,$m,PREG_OFFSET_CAPTURE,$offset)){
            $start=$m[0][1];$end=$start+strlen($m[0][0]);$name=strtolower($m[1][0]);$result.=substr($text,$offset,$start-$offset);
            if(!isset($map[$name])){$result.=$m[0][0];$offset=$end;continue;}
            $kind=$map[$name];$body='';$finish=$end;$depth=1;
            $both='/\[(\/?)sl-'.preg_quote($m[1][0],'/').'\b(?:"[^"]*"|\x27[^\x27]*\x27|[^\]"\x27])*\]/i';$scan=$end;$closed=false;
            while(preg_match($both,$text,$next,PREG_OFFSET_CAPTURE,$scan)){ $scan=$next[0][1]+strlen($next[0][0]);$depth += $next[1][0]==='/'?-1:1;if($depth===0){$body=substr($text,$end,$next[0][1]-$end);$finish=$scan;$closed=true;break;} }
            $void=in_array($kind,['img','inlineImg','audio','video','youtube','bvideo','button','github','gitlab','gitee','gitea','series'],true);
            if(!$closed&&!$void){$result.=$m[0][0];$offset=$end;continue;}
            try { $params=self::params($m[2][0]??'');$html=$this->component($kind,$params,$body);$result.=$this->token($html); }
            catch(\Throwable){$result.=$this->token(self::e(substr($text,$start,$finish-$start)));}
            $offset=$finish;
        }
        $result.=substr($text,$offset);
        if($markdown)$result=preg_replace('/(^|\n)[ \t]*('.preg_quote($this->prefix,'/').'\d+END)[ \t]*(?=\r?\n|$)/',"$1\n\n$2\n\n",$result);
        return $markdown?\Utils\Markdown::convert($result):$result;
    }
    public static function params(string $text): array {
        $params=[];$pattern='/\G\s*([a-zA-Z][\w-]*)\s*=\s*(?:"([^"]*)"|\x27([^\x27]*)\x27|([^\s]+))/';$offset=0;
        while(preg_match($pattern,$text,$m,0,$offset)){$value=$m[2]!==''?$m[2]:($m[3]??'');if($value===''&&isset($m[4]))$value=$m[4];$params[$m[1]]=html_entity_decode($value,ENT_QUOTES|ENT_HTML5,'UTF-8');$offset+=strlen($m[0]);}
        if(trim(substr($text,$offset))!=='')throw new \InvalidArgumentException('Invalid attributes');
        return $params;
    }
    private function component(string $kind,array $p,string $raw): string {
        if(class_exists('\Solitude\Theme')&&(($kind==='mermaid'&&!\Solitude\Theme::get('mermaid'))||($kind==='chartjs'&&!\Solitude\Theme::get('chart'))))return '';
        if(in_array($kind,['img','inlineImg','galleryItem','audio','video'],true)&&empty($p['src'])&&empty($p['url']))throw new \InvalidArgumentException('Missing media URL');
        if(in_array($kind,['youtube','bvideo'],true)&&empty($p['id'])&&empty($p['bvid']))throw new \InvalidArgumentException('Missing video ID');
        if(isset($p['columns'])&&(!ctype_digit($p['columns'])||(int)$p['columns']<1||(int)$p['columns']>6))throw new \InvalidArgumentException('Invalid columns');
        if(in_array($kind,['note','subnote'],true)&&isset($p['type'])&&!in_array($p['type'],['default','primary','success','info','warning','danger','blue','red','green','purple','orange','disabled'],true))throw new \InvalidArgumentException('Invalid note type');
        $e=fn($v)=>self::e($v);$u=fn($v)=>$e(self::url($v,true));$link=fn($v)=>$e(self::url($v));$id=isset($p['id'])&&preg_match('/^[\pL\pN_-]+$/u',$p['id'])?$e($p['id']):'sl-'.$kind.'-'.bin2hex(random_bytes(5));$body=$this->parse($raw);$title=$e($p['title']??$p['name']??'');$text=$e($p['text']??'');$type=$e($p['type']??'default');$src=$u($p['url']??$p['src']??'#');
        if(!str_contains(trim($raw),"\n\n")||in_array($kind,['bubble','label','spoiler','span','hideInline','keyboard','p'],true))$body=preg_replace('~^<p>((?:(?!</?p>).)*)</p>\s*$~s','$1',$body);
        if(in_array($kind,['button','link','card','galleryGroup'],true))$src=$link($p['url']??'#');
        $t=fn($key)=>class_exists('\\Solitude\\Theme')?\Solitude\Theme::t($key):$key;
        $external=preg_match('~^https?://~i',$p['url']??$p['link']??'')?' target="_blank" rel="noopener noreferrer"':'';
        $length=fn($v)=>preg_match('/^\d+(?:\.\d+)?(?:px|rem|em|%|vh|vw)?$/',(string)$v)?$e(is_numeric($v)?$v.'px':$v):'auto';
        switch($kind){
            case 'note':case 'subnote':$icons=['info'=>'circle-info','blue'=>'circle-info','danger'=>'circle-exclamation','red'=>'circle-exclamation','warning'=>'triangle-exclamation','success'=>'circle-check','green'=>'circle-check','primary'=>'star','purple'=>'star','orange'=>'bell','disabled'=>'ban'];return '<aside class="solitude-tag note '.($kind==='subnote'?'subnote ':'').$type.' '.$e($p['style']??'flat').' no-icon has-icon"><span class="note-icon" aria-hidden="true"><i class="fas fa-'.($icons[$p['type']??'']??'note-sticky').'"></i></span><div class="note-content">'.$body.'</div></aside>';
            case 'fold':return '<details class="solitude-tag tag-fold" '.(($p['open']??'false')==='true'?'open':'').'><summary class="tag-fold-summary"><span class="tag-fold-title">'.$title.'</span><span class="tag-fold-icon" aria-hidden="true"></span></summary><div class="tag-fold-content">'.$body.'</div></details>';
            case 'hideInline':case 'hideBlock':case 'hideToggle':$tag=$kind==='hideInline'?'span':'div';return '<'.$tag.' class="solitude-tag tag-hide tag-hide-'.($kind==='hideToggle'?'toggle':($kind==='hideInline'?'inline':'block')).'"><button type="button" class="tag-hide-button" aria-expanded="false" aria-controls="'.$id.'">'.($title?:$text?:'Click').'</button><'.$tag.' id="'.$id.'" class="tag-hide-content" hidden>'.$body.'</'.$tag.'></'.$tag.'>';
            case 'tabs':return '<div class="solitude-tag tag-tabs tabs" id="'.$id.'" data-solitude-tabs><div class="tab-contents">'.$body.'</div></div>';
            case 'tab':return '<section class="tab-item-content" id="'.$id.'" data-tab-title="'.$title.'" data-tab-icon="'.$e($p['icon']??'').'">'.$body.'</section>';
            case 'gallery':return '<div class="solitude-tag tag-gallery gallery-container" data-columns="'.$e($p['columns']??3).'">'.$body.'</div>';
            case 'galleryItem':return '<div class="solitude-tag tag-gallery-item gallery-item"><img class="nolazyload" src="'.$u($p['src']??'#').'" alt="'.$e($p['alt']??'').'" loading="lazy"></div>';
            case 'galleryGroup':return '<figure class="solitude-tag tag-gallery-group gallery-group">'.(isset($p['img'])?'<img class="gallery-group-img no-lightbox" src="'.$u($p['img']).'" alt="'.$title.'">':'').'<figcaption><div class="gallery-group-name">'.$title.'</div><div>'.$body.'</div>'.(isset($p['url'])?'<a href="'.$src.'" aria-label="'.$title.'"></a>':'').'</figcaption></figure>';
            case 'img':return '<figure class="solitude-tag tag-image-wrap"><img class="solitude-tag tag-image" src="'.$u($p['src']??'#').'" alt="'.$e($p['alt']??'').'" loading="lazy" '.(isset($p['style'])?'style="'.$e($p['style']).'"':'').'>'.(isset($p['caption'])?'<figcaption>'.$e($p['caption']).'</figcaption>':'').'</figure>';
            case 'inlineImg':return '<img class="solitude-tag inline-img tag-inline-image no-lightbox" src="'.$u($p['src']??'#').'" alt="'.$e($p['alt']??'').'" loading="lazy" '.(isset($p['height'])?'style="height:'.$length($p['height']).'"':'').'>';
            case 'button':return '<a class="solitude-tag st-btn tag-button '.$e($p['option']??'').' '.(!empty($p['icon'])?'has-icon':'').' '.(!empty($p['icon'])&&empty($p['text'])?'is-icon-only':'').'" href="'.$src.'"'.$external.'>'.(isset($p['icon'])?'<i class="solitude '.$e($p['icon']).'" aria-hidden="true"></i>':'').($text?'<span>'.$text.'</span>':$body).'</a>';
            case 'link':return '<div class="solitude-tag tag-link-card"><a class="solitude-tag tag-link" href="'.$link($p['url']??$p['link']??'#').'"'.$external.'><div class="tag-link-tips"><i class="fas fa-'.($external?'arrow-up-right-from-square':'house').'"></i>'.$e($t($external?'externalLink':'internalLink')).'</div><div class="tag-link-bottom"><div class="tag-link-left"><i class="solitude fas fa-link"></i></div><div class="tag-link-right"><div class="tag-link-title">'.$title.'</div><div class="tag-link-sitename">'.$e($p['desc']??$p['subtitle']??'').'</div></div><i class="solitude fas fa-chevron-right"></i></div></a></div>';
            case 'card':$cover=$p['cover']??$p['bg']??null;$name=$title?:$e($t('untitled'));return '<article class="solitude-tag card_box tag-card '.($cover?'has-cover':'no-cover').' '.(isset($p['url'])?'has-action':'no-action').'" style="width:'.$length($p['width']??'100%').';'.(isset($p['height'])?'height:'.$length($p['height']).';':'').'">'.($cover?'<div class="card_cover" aria-hidden="true" style="background-image:url(&quot;'.$u($cover).'&quot;)"></div>':'').'<div class="card_body"><div class="card_top">'.(isset($p['icon'])?'<i class="'.$e($p['icon']).'" aria-hidden="true"></i>':'').(isset($p['tag'])?'<span class="card_tag">'.$e($p['tag']).'</span>':'').'</div><div class="card_mask card_info"><div class="card_content"><span class="card_title">'.$name.'</span><span class="card_description">'.$e($p['desc']??'').$body.'</span></div><div class="card_footer">'.(isset($p['star'])?'<div class="card_rating" aria-label="'.$e(str_replace('{rating}',$p['star'],$t('cardRating'))).'"><i class="fa-solid fa-star" aria-hidden="true"></i><span aria-hidden="true">'.max(0,min(5,(float)$p['star'])).' / 5</span></div>':'').(isset($p['url'])?'<a class="card_action" href="'.$src.'" aria-label="'.$e(str_replace('{name}',html_entity_decode($name),$t('cardDetails'))).'"'.$external.'><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>':'').'</div></div></div></article>';
            case 'github':case 'gitlab':case 'gitee':case 'gitea':$repo=$p['repo']??'';if(!preg_match('~^[\w.-]+(?:/[\w.-]+)+$~',$repo))throw new \InvalidArgumentException('Invalid repo');$host=$kind==='gitea'?rtrim($p['host']??$p['server']??'', '/'):'https://'.$kind.'.com';$endpoint=match($kind){'github'=>'https://api.github.com/repos/'.$repo,'gitlab'=>$host.'/api/v4/projects/'.rawurlencode($repo),'gitee'=>$host.'/api/v5/repos/'.$repo,default=>$host.'/api/v1/repos/'.$repo};return '<article class="solitude-tag repo-card tag-repo" data-repo-provider="'.$kind.'" data-repo-endpoint="'.$u($endpoint).'" aria-busy="true"><div class="repo-card-header"><span class="repo-provider"><i class="fab fa-'.(in_array($kind,['gitee','gitea'],true)?'git-alt':$kind).'"></i>'.$kind.'</span><img class="repo-language no-lightbox" alt="" loading="lazy" hidden></div><a class="repo-title" href="'.$u($host.'/'.$repo).'" target="_blank" rel="noopener noreferrer">'.$e(basename($repo)).'</a><p class="repo-desc">'.$e($t('repoLoading')).'</p><div class="repo-card-footer"><div class="repo-gist" aria-label="Repository statistics"><span><i class="fas fa-star"></i><span class="repo-stars">—</span></span><span><i class="fas fa-code-branch"></i><span class="repo-forks">—</span></span></div><span class="repo-status" role="status">'.$e($t('loading')).'</span></div></article>';
            case 'audio':return '<div class="solitude-tag audio tag-audio"><span class="tag-media-icon" aria-hidden="true"><i class="fas fa-music"></i></span><audio controls preload="metadata" aria-label="'.$e($p['name']??'').'"><source src="'.$src.'"></audio></div>';
            case 'video':return '<div class="solitude-tag video tag-video"><video controls preload="metadata" '.(isset($p['poster'])?'poster="'.$u($p['poster']).'"':'').'><source src="'.$src.'"></video></div>';
            case 'videos':$videos='';foreach(json_decode($p['sources']??'[]',true,512,JSON_THROW_ON_ERROR) as $source)$videos.='<video controls preload="metadata"><source src="'.$u($source).'"></video>';return '<div class="solitude-tag videos tag-videos" data-columns="'.(int)($p['col']??2).'">'.$videos.$body.'</div>';
            case 'youtube':case 'bvideo':$embed=$kind==='youtube'?'https://www.youtube'.(($p['privacy']??'true')==='false'?'':'-nocookie').'.com/embed/'.rawurlencode($p['id']??''):'https://player.bilibili.com/player.html?autoplay=0&bvid='.rawurlencode($p['bvid']??$p['id']??'');return '<div class="solitude-tag video-container tag-video-embed tag-video-'.($kind==='youtube'?'youtube':'bilibili').'"><iframe src="'.$e($embed).'" title="'.($kind==='youtube'?'YouTube':'Bilibili').' video player" loading="lazy" allowfullscreen></iframe></div>';
            case 'mermaid':return '<div class="solitude-tag tag-mermaid mermaid">'.$e($p['code']??trim($raw)).'</div>';
            case 'chartjs':$json=$p['config']??$p['code']??trim($raw);json_decode($json,true,512,JSON_THROW_ON_ERROR);return '<div class="solitude-tag chartjs-container tag-chart '.(isset($p['layout'])?'chartjs-abreast':'').'" data-chartjs-id="'.$id.'" '.(isset($p['width'])?'data-width="'.max(1,min(100,(float)$p['width'])).'%"':'').'><pre class="chartjs-src" hidden>'.$e($json).'</pre>'.(isset($p['description'])?'<div class="chartjs-desc">'.$e($p['description']).'</div>':'').'</div>';
            case 'score':return '<div class="solitude-tag abc-music-sheet tag-score" data-params="'.$e($p['params']??'{}').'" aria-label="Music notation">'.$e($p['score']??trim($raw)).'</div>';
            case 'typeit':return '<div class="solitude-tag tag-typeit typeit"><span id="'.$id.'" data-typeit data-speed="'.(int)($p['speed']??80).'">'.$body.'</span></div>';
            case 'timeline':return '<div class="solitude-tag timeline tag-timeline '.$e($p['color']??'').'">'.($title?'<div class="timeline-item headline"><div class="timeline-item-title"><div class="item-circle">'.$title.'</div></div></div>':'').$body.'</div>';
            case 'timenode':return '<div class="timeline-item solitude-tag timenode tag-timenode"><div class="timeline-item-title meta"><div class="item-circle"><p>'.$e($p['time']??'').'</p></div></div><div class="timeline-item-content body"><strong>'.$title.'</strong>'.$body.'</div></div>';
            case 'checkbox':case 'radio':return '<div class="solitude-tag checkbox tag-choice '.$e($p['style']??'').'"><input type="'.$kind.'" '.(($p['checked']??'true')==='false'?'':'checked').' disabled aria-label="'.$e($p['label']??$kind).'"><span class="tag-choice-content">'.$body.'</span></div>';
            case 'bubble':return '<span class="solitude-tag bubble tag-bubble"><span class="bubble-content" tabindex="0">'.$text.$body.'</span><span class="bubble-notation bg-'.$e($p['color']??'blue').'">'.$e($p['notation']??$p['position']??'').'</span></span>';
            case 'keyboard':return '<kbd class="solitude-tag keyboard-tag tag-keyboard">'.$text.strip_tags($body).'</kbd>';
            case 'label':return '<mark class="solitude-tag hl-label tag-label '.$e($p['color']??'default').'">'.$text.$body.'</mark>';
            case 'spoiler':return '<span class="solitude-tag spoiler tag-spoiler '.$e($p['style']??'block').'-text" tabindex="0" aria-label="Hidden text">'.$body.'</span>';
            case 'p':case 'span':return '<'.$kind.' class="solitude-tag tag-text tag-'.($kind==='p'?'paragraph':'span').' '.$e($p['color']??'').'">'.$body.'</'.$kind.'>';
            case 'series':$items='';$posts=class_exists('\\Solitude\\Theme')?\Solitude\Theme::publicPosts():[];$name=$p['name']??null;if($name===null)foreach($posts as $post)if(parse_url(\Solitude\Theme::permalink($post),PHP_URL_PATH)===parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH)){$name=\Solitude\Theme::series($post)[0]??null;break;}usort($posts,fn($a,$b)=>$a->created<=>$b->created);foreach($posts as $post)if($name!==null&&in_array($name,\Solitude\Theme::series($post),true))$items.='<li><a href="'.$e(\Solitude\Theme::permalink($post)).'">'.$e($post->title).'</a></li>';return '<ol class="solitude-tag series-items tag-series">'.$items.'</ol>';
            case 'flink':$groups=json_decode($p['groups']??trim($raw)?:'[]',true,512,JSON_THROW_ON_ERROR);$html='<div class="solitude-tag flink tag-flink">';foreach($groups as $group){$html.=''.(isset($group['class_name'])?'<div class="flink-name">'.$e($group['class_name']).'</div>':'').(isset($group['class_desc'])?'<div class="flink-desc">'.$e($group['class_desc']).'</div>':'').'<div class="flink-list">';foreach($group['link_list']??[] as $item)$html.='<div class="flink-list-item"><a class="cf-friends-link" href="'.$link($item['link']).'" target="_blank" rel="noopener noreferrer"><img class="flink-avatar cf-friends-avatar no-lightbox" src="'.$u($item['avatar']??'/img/default_avatar.avif').'" alt="" loading="lazy"><div class="flink-item-info"><span class="flink-item-name cf-friends-name">'.$e($item['name']).'</span><span class="flink-item-desc">'.$e($item['descr']??$item['description']??'').'</span></div></a></div>';$html.='</div>'; }return $html.'</div>';
        }
        throw new \InvalidArgumentException('Unsupported shortcode');
    }
}
