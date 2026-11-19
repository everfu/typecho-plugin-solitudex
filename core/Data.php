<?php
namespace TypechoPlugin\SolitudeX;

final class Data
{
    public const TYPES = ['about','links','kit','brevity'];
    public static ?string $errorPath=null;
    private static function require(bool $condition,string $message,string $path=''): void {
        if(!$condition){self::$errorPath=$path;throw new \InvalidArgumentException($message);}
    }
    public static function validate(string $type,array $data): void {
        self::$errorPath=null;
        self::require(in_array($type,self::TYPES,true),'未知内容类型。');
        self::require(!array_is_list($data),'内容必须包含页面字段。');
        if($type==='about') {
            self::require(isset($data['title'])&&is_string($data['title']),'请填写关于页面标题。','title');
            self::require(isset($data['sections'])&&is_array($data['sections'])&&array_is_list($data['sections']),'关于页面需要内容区块列表。','sections');
            foreach($data['sections'] as $i=>$s)self::require(is_array($s)&&in_array($s['type']??'', ['intro','skills_career','personality_photo','values','interests','stats_profile','story'],true),'请选择有效的关于页面区块类型。',"sections.$i.type");
        } elseif($type==='links') {
            self::require(isset($data['links'])&&is_array($data['links'])&&array_is_list($data['links']),'友链需要分组列表。','links');
            foreach($data['links'] as $i=>$group){
                self::require(is_array($group)&&is_array($group['link_list']??null)&&array_is_list($group['link_list']),'友链分组需要链接列表。',"links.$i.link_list");
                foreach($group['link_list'] as $j=>$item){
                    self::require(is_array($item)&&is_string($item['name']??null)&&trim($item['name'])!=='','请填写友链名称。',"links.$i.link_list.$j.name");
                    self::require(is_string($item['link']??null)&&trim($item['link'])!=='','请填写友链地址。',"links.$i.link_list.$j.link");
                }
            }
        } elseif($type==='kit') {
            self::require(isset($data['groups'])&&is_array($data['groups'])&&array_is_list($data['groups']),'装备需要分组列表。','groups');
            foreach($data['groups'] as $i=>$group){
                self::require(is_array($group)&&is_string($group['name']??null)&&is_array($group['items']??null)&&array_is_list($group['items']),'请填写装备分组名称和条目。',"groups.$i.name");
                foreach($group['items'] as $j=>$item)self::require(is_array($item)&&is_string($item['name']??null)&&trim($item['name'])!=='','请填写装备名称。',"groups.$i.items.$j.name");
            }
        } else {
            self::require(isset($data['items'])&&is_array($data['items'])&&array_is_list($data['items']),'即刻需要动态列表。','items');
            foreach($data['items'] as $i=>$item){
                self::require(is_array($item)&&is_string($item['date']??null)&&strtotime($item['date'])!==false,'请填写有效的动态日期。',"items.$i.date");
                self::require(is_string($item['content']??null),'请填写动态正文。',"items.$i.content");
            }
        }
        self::walk($data);
    }
    private static function walk($value,string $key='',int $depth=0,string $path=''): void {
        self::require($depth<=32,'内容嵌套过深。',$path);
        if(in_array($key,['sections','links','link_list','groups','items','lines','rotating_words','heroes','title_lines','events','metrics','summary'],true))self::require(is_array($value)&&array_is_list($value),'此处需要条目列表。',$path);
        if(is_array($value)&&in_array($key,['items','link_list','sections','groups','heroes','events','metrics','summary'],true))foreach($value as $i=>$item)self::require(is_array($item),'列表条目格式无效。',ltrim($path.'.'.$i,'.'));
        if(is_array($value))foreach($value as $k=>$v){self::require(!in_array($k,['__proto__','constructor','prototype'],true),'内容包含保留字段。',$path);self::walk($v,(string)$k,$depth+1,ltrim($path.'.'.$k,'.'));}
        elseif(is_string($value)&&in_array($key,['url','link','src','image','avatar','topimg','host','background','cover','photo','map_light','map_dark','video'],true)&&$value!=='')self::require((bool)preg_match('~^(https?://|/(?!/)|#)~i',$value),'图片和链接请使用 HTTP(S) 地址或站内路径。',$path);
    }
}
