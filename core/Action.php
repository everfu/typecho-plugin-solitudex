<?php
namespace TypechoPlugin\SolitudeX;

use Solitude\Theme;

class Action extends \Widget\Base implements \Widget\ActionInterface
{
    public function action(){
        $op=(string)$this->request->get('op','');$this->user->pass($op==='preview'?'contributor':'administrator');$this->security->protect();
        $this->response->setContentType('application/json');
        try{
            if(!Plugin::theme())throw new \RuntimeException('请安装 Solitude 主题。');
            if(!in_array($op,['preview','attachments'],true)&&Theme::options()->theme!=='Solitude')throw new \RuntimeException('请先启用 Solitude 主题。');
            if(!in_array($op,['attachments'],true)&&!$this->request->isPost())throw new \InvalidArgumentException('请使用 POST 提交。');
            $input=file_get_contents('php://input');$data=$input!==''&&str_contains($_SERVER['CONTENT_TYPE']??'','application/json')?json_decode($input,true,512,JSON_THROW_ON_ERROR):[];
            $result=null;$message='操作完成';
            switch($op){
                case 'preview':
                    ob_start();Theme::bundle('site',false);$styles=ob_get_clean();
                    $result='<!doctype html><html lang="'.Theme::e(Theme::locale()).'" data-theme="light" style="--solitude-theme-light:'.Theme::e(Theme::get('theme_color.light')).'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'.$styles.'</head><body><main id="post" style="padding:20px"><article class="article-container">'.(new Shortcodes())->render((string)($data['text']??'')).'</article></main></body></html>';break;
                case 'save-data':
                    if(($_POST['sl_complete']??null)!=='1')throw new \InvalidArgumentException('表单未完整提交，请检查服务器 max_input_vars 和上传大小限制后重试。');
                    $type=$this->request->get('type');
                    if(!is_string($type)||!in_array($type,Data::TYPES,true))throw new \InvalidArgumentException('未知内容类型。');
                    $value=$this->request->get('sl_data_'.$type,[]);
                    if($this->request->get('sl_complete')!=='1'||!is_array($value))throw new \InvalidArgumentException('表单未完整提交，请检查服务器 max_input_vars 和上传大小限制后重试。');
                    if(isset($value['__sl_bool'])||isset($value['__sl_number']))throw new \InvalidArgumentException('内容根节点格式无效。');
                    $raw=$value;$value=\Solitude\ArrayField::decode('sl_data_'.$type,$value);Data::validate($type,$value);if(!\Solitude\ArrayField::valid($raw))throw new \InvalidArgumentException('字段格式或链接协议无效，请检查后重试。');
                    Theme::migrateContent();Theme::transaction(fn()=>Theme::put('solitude:data:'.$type,$value));$message='内容已保存';break;
                case 'backup':Theme::migrateContent();Theme::transaction(function()use(&$result){$result=Theme::snapshot();Theme::put('solitude:backup',$result);});$message='配置与内容备份已创建。';break;
                case 'restore':$backup=Theme::stored('solitude:backup');if(!is_array($backup))throw new \RuntimeException('请先创建备份。');Theme::restoreSnapshot($backup);$message='备份已恢复。';break;
                case 'undo-restore':$backup=Theme::stored('solitude:restore:undo');if(!is_array($backup))throw new \RuntimeException('没有可以撤销的恢复。');Theme::restoreSnapshot($backup,true);$message='已撤销最近一次恢复。';break;
                case 'initialize':$result=$this->initialize();$message='特色页面已初始化；已有页面保持原样。';break;
                case 'attachments':$rows=$this->db->fetchAll($this->db->select()->from('table.contents')->where('type = ?','attachment')->order('created',\Typecho\Db::SORT_DESC)->limit(100));$result=[];foreach($rows as $row){$p=\Utils\Helper::widgetById('contents',(int)$row['cid']);if(str_starts_with($p->attachment->mime??'','image/'))$result[]=['title'=>$p->title,'url'=>$p->attachment->url];}break;
                default:throw new \InvalidArgumentException('未知操作。');
            }
            $this->response->throwJson(['ok'=>true,'message'=>$message,'data'=>$result]);
        }catch(\Throwable $error){$this->response->setStatus(400)->throwJson(['ok'=>false,'message'=>$error->getMessage(),'field'=>($op==='save-data'&&Data::$errorPath!==null&&isset($type)?\Solitude\ArrayField::inputName('sl_data_'.$type,Data::$errorPath,$raw??[]):null)]);}
    }
    public function initialize(): array {
        $created=[];$names=['about'=>['关于','about'],'links'=>['友链','links'],'equipment'=>['装备','kit'],'brevity'=>['即刻','brevity'],'music'=>['音乐馆','music'],'message'=>['留言板','message'],'recentcomments'=>['最近评论','recentcomment'],'archives'=>['归档','archives'],'categories'=>['分类','categories'],'tags'=>['标签','tags']];
        foreach($names as $slug=>[$title,$type]){if($this->db->fetchRow($this->db->select('cid')->from('table.contents')->where('slug = ?',$slug)))continue;$this->db->query($this->db->insert('table.contents')->rows(['title'=>$title,'slug'=>$slug,'created'=>time(),'modified'=>time(),'text'=>'<!--markdown-->','order'=>0,'authorId'=>$this->user->uid,'template'=>'page-'.$type.'.php','type'=>'page','status'=>'publish','password'=>null,'commentsNum'=>0,'allowComment'=>in_array($type,['message','links'])?'1':'0','allowPing'=>'0','allowFeed'=>'0','parent'=>0]));$created[]=$slug;}
        return $created;
    }
}
