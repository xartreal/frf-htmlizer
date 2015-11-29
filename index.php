<?php

function timeline ($offset,$name) {
    $sock = fsockopen("ssl://freefeed.net", 443, $errno, $errstr, 30);
    if (!$sock) die("$errstr ($errno)\n");
    // https://freefeed.net/v1/timelines/username?offset=0
    fwrite($sock, "GET /v1/timelines/".$name."?offset=".$offset." HTTP/1.0\r\n");
    fwrite($sock, "Host: freefeed.net\r\n");
    fwrite($sock, "Content-type: application/json; charset=utf-8\r\n");
    fwrite($sock, "\r\n");
    fwrite($sock, "\r\n");

    $headers = "";
    while ($str = trim(fgets($sock, 4096)))
    $headers .= "$str\n";

    $body = "";
    while (!feof($sock))
    $body .= fgets($sock, 4096);
    //echo $body;

    fclose($sock);


    return $body;
}

function getpost($id) {
     $sock = fsockopen("ssl://freefeed.net", 443, $errno, $errstr, 30);
     if (!$sock) die("$errstr ($errno)\n");
     // https://freefeed.net/v1/timelines/artreal?offset=0
     fwrite($sock, "GET /v1/posts/".$id."?maxComments=all&maxLikes=all"." HTTP/1.0\r\n");
     fwrite($sock, "Host: freefeed.net\r\n");
     fwrite($sock, "Content-type: application/json; charset=utf-8\r\n");
     fwrite($sock, "\r\n");
     fwrite($sock, "\r\n");

     $headers = "";
     while ($str = trim(fgets($sock, 4096)))
     $headers .= "$str\n";
     $body = "";
     while (!feof($sock))
     $body .= fgets($sock, 4096);
     //echo $body;

     fclose($sock);
     return $body;
}


function rticktime()
{
	list($usec, $sec) = explode(' ', microtime());
	return (double) $usec + (double) $sec;
}


function showtimeline($offset,$name) {
$tmstart=rticktime();
$tmljsn=timeline ($offset,$name);
$tmtime=number_format (rticktime() - $tmstart, 3);
$starttime=rticktime();

if (strpos($tmljsn,'"err"')!==false) {
 print "Sorry, this feed unavailable";
 exit;
}
header("HTTP/1.0 200 Ok");
$tml=json_decode($tmljsn);

// -- make groups list
$ga=$tml->subscribers;
$ga_cnt=sizeof($ga);
$ga_out=array();
for ($i=0;$i<$ga_cnt;$i++) {
    if ($ga[$i]->type=="group") {
      $ga_out[$ga[$i]->id]=$ga[$i]->username;
    }
}
$gb=$tml->subscriptions;
$gb_cnt=sizeof($gb);
$groups=array();
for ($i=0;$i<$gb_cnt;$i++) {
    if (isset($ga_out[$gb[$i]->user])) {
       $groups[$gb[$i]->id]=$ga_out[$gb[$i]->user];
    }
}
// end make groups list
$postarr=$tml->posts;
$postcnt=sizeof($postarr);
$commarr=$tml->comments; // all timeline comments
$carrcnt=sizeof($commarr);
$commidx=array(); //comments index
for ($k=0;$k<$carrcnt;$k++){
	$commidx[$commarr[$k]->id]=$k;
}
//$usrarr=$tml->subscribers; //users
$usrarr=$tml->users; //users
$usrcnt=sizeof($usrarr);
$usridx=array();
$usracs=array();
for ($k=0;$k<$usrcnt;$k++){
	$usridx[$usrarr[$k]->id]=$usrarr[$k]->username;
	$usracs[$usrarr[$k]->id]=$usrarr[$k]->isPrivate;
}
//start html
print "<!DOCTYPE html><html><head><title>frf: ".$name." / offset ".$offset."</title></head><body bgcolor=\"#FFFFFF\">";

	$attindex=array();
    $cattacharr = array();
    if (isset($tml->attachments)) $cattacharr=$tml->attachments;
	$cattcnt=sizeof($cattacharr);
	for ($ci=0;$ci<$cattcnt;$ci++){
		$attindex[$cattacharr[$ci]->id]=$ci;
	}

//print_r ($usridx);
if ($postcnt==0) {
  print "Ой, всё"; exit;
}
for ($i=0;$i<$postcnt;$i++){
	if ($postarr[$i]->isHidden) continue;
        // check groups
        print "<b>";
        $ps_cnt=sizeof($postarr[$i]->postedTo);
        if ($ps_cnt>1) print "+";
        for ($j=0;$j<$ps_cnt;$j++) {
         $xz=$postarr[$i]->postedTo[$j];
         if (isset($groups[$xz])) print $groups[$xz].":";
        }
        print "</b>";

        // post author
        $ausr=$usridx[$postarr[$i]->createdBy];

	print "<b>".$ausr."</b><br>";
	//post body
	print htmlspecialchars($postarr[$i]->body); print "<br>";
	$time=$postarr[$i]->createdAt;
	$time_html=date("d.m.y H:i",($time+0)/1000)."\n";
	print "<a href=\"/".$name."/".$postarr[$i]->id."\">".$time_html."</a><br>";
//	print $postarr[$i]->omittedComments."<br>";
	//likes
	$likearr=$postarr[$i]->likes;
	$likecnt=sizeof($likearr);
	if ($likecnt!=0) print "Likes: ";
	for ($z=0;$z<$likecnt;$z++) {
		print $usridx[$likearr[$z]].", ";
	}
	if ($postarr[$i]->omittedLikes!="0") print "... [".$postarr[$i]->omittedLikes."]";
	if ($likecnt!=0) print "<br>";
	//attachments
           $attach_html='';
           if (isset($postarr[$i]->attachments)) $attacharr=$postarr[$i]->attachments;
           else $attacharr=array();
           $attachasize=sizeof($attacharr);
//	   print_r($attacharr);
           if ($attachasize!=0){
             for ($ai=0;$ai<$attachasize;$ai++){
              $citm=$cattacharr[$attindex[$attacharr[$ai]]];
              if ($citm->mediaType!="image") $attach_html.="<a href=\"".$citm->url."\">".$citm->fileName."</a><br>\n";
              else $attach_html.="<img src=\"".$citm->thumbnailUrl."\"><br>\n";
             }
           }
           print $attach_html;
	//comments
	$commlist=$postarr[$i]->comments;
	$commcnt=sizeof($commlist);
	if ($commcnt>0) print "<ul>\n";
	$cmtdiv=$postarr[$i]->omittedComments;
	for ($j=0;$j<$commcnt;$j++) {
		print "<li>";
		$cid=$commlist[$j];
		$cx=$commidx[$cid];
//		print $cid.":".$cx.":";
		print htmlspecialchars($commarr[$cx]->body);
		print " - <i>".$usridx[$commarr[$cx]->createdBy]."</i>";
		print "</li>";
		if (($j==0)&&($cmtdiv!="0")) print " ...[ ".$cmtdiv." ]...<br>";
//		print $commarr[$commidx[$commlist[$j]]]->id;
	}
	if ($commcnt>0) print "</ul>\n";
	print "<hr><p>";
}
$gentime=number_format (rticktime() - $starttime, 3);
if ($offset>29) print "<a href=/".$name."/offset/".($offset-30).">Prev</a> | ";
print "<a href=/".$name."/offset/".($offset+30).">Next</a> | ";

print "<p>\n".$tmtime."/".$gentime;
print "</body></html>";
}


function tohtml($id) {
   $tmstart=rticktime();
   $x=getpost($id);;
   $tmtime=number_format (rticktime() - $tmstart, 3);
   $starttime=rticktime();
   header("HTTP/1.0 200 Ok");
   $y=json_decode($x);
   // -- make groups list
   $ga=$y->subscribers;
   $ga_cnt=sizeof($ga);
   $ga_out=array();
   for ($i=0;$i<$ga_cnt;$i++) {
       if ($ga[$i]->type=="group") {
         $ga_out[$ga[$i]->id]=$ga[$i]->username;
       }
   }
   $groups=array();
   foreach ($y->subscriptions as $g) {
       if (isset($ga_out[$g->user])) {
           $groups[$g->id]=$ga_out[$g->user];
       }
   }
   // end make groups list
   //body
   if (!isset($y->posts->body)) print "html warn: ".$id."\n";
   $text=$y->posts->body;
   //preg_match('/^(.*){1,10}/U',$text,$result1)
   mb_internal_encoding("UTF-8");
   $title=mb_substr($text,0,50);
   //time
   $time=$y->posts->createdAt;
   $time_html=date("d.m.y H:i",($time+0)/1000)."\n";
   // check groups
   $ghtml="<b>";
   $ps_cnt=sizeof($y->posts->postedTo);
   if ($ps_cnt>1) $ghtml.= "+";
   for ($j=0;$j<$ps_cnt;$j++) {
         $xz=$y->posts->postedTo[$j];
         if (isset($groups[$xz])) $ghtml.= $groups[$xz].":";
   }
   $ghtml.="</b>";

   // users
   $usersarr=$y->users; $userasize=sizeof($usersarr);
   $users=array();
   $usracs=array();
   if ($userasize!=0){
     for ($i=0;$i<$userasize;$i++){
      $users[$usersarr[$i]->id]=$usersarr[$i]->username;
      $usracs[$usersarr[$i]->id]=$usersarr[$i]->isPrivate;
     }
   }
   //author
   $auser=$y->posts->createdBy;
   $mmname=$users[$auser];
   $auname=$ghtml."<b>".$mmname."</b>";
   $time_html=$auname.' / '.$time_html;
   //likes
   $likes_html='';
   if (isset($y->posts->likes)) $likesarr=$y->posts->likes;
   else $likesarr=array();
   $likesasize=sizeof($likesarr);
   if ($likesasize!=0){
     for ($i=0;$i<$likesasize;$i++){
      $likes_html.=$users[$likesarr[$i]].", ";
     }
     $likes_html="<p>Likes: ".$likes_html."</p>\n";
   }
   //attach
   $attach_html='';
   if (isset($y->attachments)) $attacharr=$y->attachments;
   else $attacharr=array();
   $attachasize=sizeof($attacharr);
   if ($attachasize!=0){
     for ($i=0;$i<$attachasize;$i++){
      if ($attacharr[$i]->mediaType!="image") $attach_html.="<a href=\"".$attacharr[$i]->url."\">".$attacharr[$i]->fileName."</a><br>\n";
      else $attach_html.="<img src=\"".$attacharr[$i]->thumbnailUrl."\"><br>\n";
     }
   }
   //comments
   if (isset($y->posts->comments)) $commntarr=$y->posts->comments;
   else $commntarr=array();
   $commasize=sizeof($commntarr);
   $comm_html='';
   if ($commasize!=0){
     $comm_html.='<ul>';

     for ($i=0;$i<$commasize;$i++){
      $zbody=htmlspecialchars($y->comments[$i]->body);
      $zuser=$y->comments[$i]->createdBy;
      $comm_html.= "<li>".$zbody." - <i>".$users[$zuser]."</i>\n";
     }
     $comm_html.='</ul>';
   }

   $gentime=number_format (rticktime() - $starttime, 3);
   $origin="<a href=\"https://freefeed.net/".$mmname."/".$id."\">Original record</a>";

   $hhead="<!DOCTYPE html><html><head><title>frf: ".htmlspecialchars($title)." (".$id.")</title></head><body bgcolor=\"#FFFFFF\">";
   $htail="<p>\n".$tmtime."/".$gentime."</body></html>";
   $hout="<p>".htmlspecialchars($text)."<br>$time_html</p><p>$attach_html</p>$likes_html<p>$comm_html</p><p>$origin</p>";
//   print $hout;
   return $hhead.$hout.$htail;
}

function errout($str) {
   print ($str); exit;
}

// main

$eurls=$_SERVER["REQUEST_URI"];
// parse urls
$urls = explode ( "/", $eurls );
//print_r($urls);
$urlcnt=sizeof($urls);
if (($urlcnt<2)||($urls[1]=="")) { //no feed name
  $whitelist=file("whitelist", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  print "<p>Usage: http://".$_SERVER["SERVER_NAME"]."/username</p>";
//  print_r($whitelist);
  foreach ($whitelist as $value) {
    print "<a href=/".htmlspecialchars($value).">".htmlspecialchars($value)."</a><br>";
  }
  exit;
}
$blacklist=file("blacklist", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
//print_r($blacklist);
$name=$urls[1];
if (!preg_match('|^[a-z\-0-9]+$|',$name)) errout("Incorrect offset or username");
if (in_array($name, $blacklist)) outerr("Sorry, this feed unavailable");
$offset=0; $id="";
if ($urlcnt==4) {
   if ($urls[2]=="offset") {
     if (!preg_match('|^[0-9]+$|',$urls[3])) errout("Incorrect offset or username");
     $offset=intval($urls[3]);
   }
}
elseif ($urlcnt==3) { $id=$urls[2];
      if (!preg_match('|^[a-f\-0-9]+$|',$id)) errout("Incorrect offset or username");
//       $xx=getpost($id);
//       print_r ($xx);
       print tohtml($id);
}
else { // ?
}
if ($id=="") showtimeline($offset,$name);

?>
