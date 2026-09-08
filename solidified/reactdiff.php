<?php
// The reaction datarray, old inline literal vs buildReactionArray().
// usage: reactdiff.php <old|new> <nick> <id> <verb>
for ($d=__DIR__;$d!=='/';$d=dirname($d)) if (file_exists("$d/include/cli_startup.php")) { chdir($d); break; }
require_once 'include/cli_startup.php'; cli_startup();
require_once __DIR__.'/vendor/autoload.php';
$ver=$argv[1]; session_id('reactdiff');
$ch=q("SELECT * FROM channel WHERE channel_address='%s' LIMIT 1",dbesc($argv[2]));
$_SESSION['uid']=intval($ch[0]['channel_id']); $_SESSION['authenticated']=1;
App::$channel=$ch[0];
$x=q("SELECT * FROM xchan WHERE xchan_hash='%s' LIMIT 1",dbesc($ch[0]['channel_hash'])); App::$observer=$x[0];
$t=q("SELECT * FROM item WHERE id=%d LIMIT 1",intval($argv[3]))[0];
$verb=$argv[4]; $ob=$ch[0]['channel_hash'];
if ($ver==='old') {
  // the two literals as they were, with the volatile fields pinned
  $mk=function($aid) use($t,$verb,$ob){ return [
    'aid'=>$aid,'uid'=>intval($t['uid']),'uuid'=>'X','mid'=>'M','parent_mid'=>$t['mid'],
    'thr_parent'=>$t['mid'],'owner_xchan'=>$t['owner_xchan'],'author_xchan'=>$ob,
    'created'=>'T','edited'=>'T','commented'=>'T','received'=>'T','changed'=>'T',
    'verb'=>$verb,'obj_type'=>'Activity','body'=>'','title'=>'','mimetype'=>'text/bbcode',
    'allow_cid'=>$t['allow_cid'],'allow_gid'=>$t['allow_gid'],'deny_cid'=>$t['deny_cid'],
    'deny_gid'=>$t['deny_gid'],'item_private'=>intval($t['item_private']),
    'item_wall'=>intval($t['item_wall']),'item_origin'=>1,'item_thread_top'=>0,
    'item_notshown'=>1,'plink'=>'M','route'=>$t['route']??'']; };
  $out=['reaction'=>$mk(intval($t['aid'])),'rsvp'=>$mk(intval($ch[0]['channel_account_id']))];
} else {
  $r=new ReflectionMethod(Utsukta\SpaCore\Api\Handlers\Item::class,'buildReactionArray');
  $r->setAccessible(true);
  $pin=function(array $a){ $a['uuid']='X'; $a['mid']='M'; $a['plink']='M';
    foreach(['created','edited','commented','received','changed'] as $k) $a[$k]='T'; return $a; };
  $out=['reaction'=>$pin($r->invoke(null,$t,$verb,$ob,intval($t['aid']))),
        'rsvp'=>$pin($r->invoke(null,$t,$verb,$ob,intval($ch[0]['channel_account_id'])))];
}
foreach($out as &$v) ksort($v);
echo json_encode($out,128),"\n";
