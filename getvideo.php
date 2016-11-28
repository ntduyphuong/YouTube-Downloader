<?php
// YouTube Downloader PHP
// based on youtube-dl in Python http://rg3.github.com/youtube-dl/
// by Ricardo Garcia Gonzalez and others (details at url above)
//
// Takes a VideoID and outputs a list of formats in which the video can be
// downloaded

include_once('config.php');
ob_start(); // if not, some servers will show this php warning: header is already set in line 46...

function clean($string) {
   $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.
   return preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.
}

function formatBytes($bytes, $precision = 2) { 
    $units = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'); 
    $bytes = max($bytes, 0); 
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
    $pow = min($pow, count($units) - 1); 
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . '' . $units[$pow]; 
} 
function is_chrome(){
	$agent=$_SERVER['HTTP_USER_AGENT'];
	if( preg_match("/like\sGecko\)\sChrome\//", $agent) ){	// if user agent is google chrome
		if(!strstr($agent, 'Iron')) // but not Iron
			return true;
	}
	return false;	// if isn't chrome return false
}
// string helper function
function strbtwn($content,$start,$end){
	$r = explode($start, $content);
	if (isset($r[1])){
		$r = explode($end, $r[1]);
		return $r[0];
	}
	return '';
}
// signature decoding 
function decryptSignature($encryptedSig, $algorithm)
    {
        $output = false;
        // Validate pattern of SDA rule
        if (is_string($encryptedSig) && is_string($algorithm) &&
            preg_match_all('/([R|S|W]{1})(\d+)/', $algorithm, $matches)
        ) {
            // Apply each SDA rule on encrypted signature
            foreach ($matches[1] as $pos => $cond) {
                $size = $matches[2][$pos];
                switch ($cond) {
                    case 'R':
                        // Reverse EncSig (Encrypted Signature)
                        $encryptedSig = strrev($encryptedSig);
                        break;
                    case 'S':
                        // Splice EncSig
                        $encryptedSig = substr($encryptedSig, $size);
                        break;
                    case 'W':
                        // Swap first char and nth char on EncSig
                        $sigArray = str_split($encryptedSig);
                        $zeroChar = $sigArray[0];
                        // Replace positions
                        $sigArray[0] = @$sigArray[$size];
                        $sigArray[$size] = $zeroChar;
                        // Join signature
                        $encryptedSig = implode('', $sigArray);
                        break;
                }
            }
            // Finally dump decrypted signature :)
            $output = $encryptedSig;
        }

        return $output;
    }
if(isset($_REQUEST['videoid'])) {
	$my_id = $_REQUEST['videoid'];
	if( preg_match('/^https:\/\/w{3}?.youtube.com\//', $my_id) ){
		$url   = parse_url($my_id);
		$my_id = NULL;
		if( is_array($url) && count($url)>0 && isset($url['query']) && !empty($url['query']) ){
			$parts = explode('&',$url['query']);
			if( is_array($parts) && count($parts) > 0 ){
				foreach( $parts as $p ){
					$pattern = '/^v\=/';
					if( preg_match($pattern, $p) ){
						$my_id = preg_replace($pattern,'',$p);
						break;
					}
				}
			}
			if( !$my_id ){
				echo '<p>No video id passed in</p>';
				exit;
			}
		}else{
			echo '<p>Invalid url</p>';
			exit;
		}
	}elseif( preg_match('/^https?:\/\/youtu.be/', $my_id) ) {
		$url   = parse_url($my_id);
		$my_id = NULL;
		$my_id = preg_replace('/^\//', '', $url['path']);
	}
} else {
	echo '<p>No video id passed in</p>';
	exit;
}
if(isset($_REQUEST['type'])) {
	$my_type =  $_REQUEST['type'];
} else {
	$my_type = 'redirect';
}
if ($my_type == 'Download') {
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
    <title>Youtube Downloader</title>
    <meta name="keywords" content="Video downloader, download youtube, video download, youtube video, youtube downloader, download youtube FLV, download youtube MP4, download youtube 3GP, php video downloader" />
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<link href="css/bootstrap.min.css" rel="stylesheet" media="screen">
	 <style type="text/css">
      	body {
	        padding-top: 40px;
	        padding-bottom: 40px;
	        background-color: #f5f5f5;
	}

	.download {
	        max-width: 300px;
	        padding: 19px 29px 29px;
	        margin: 0 auto 20px;
	        background-color: #fff;
	        border: 1px solid #e5e5e5;
	        -webkit-border-radius: 5px;
	           -moz-border-radius: 5px;
	                border-radius: 5px;
	        -webkit-box-shadow: 0 1px 2px rgba(0,0,0,.05);
	           -moz-box-shadow: 0 1px 2px rgba(0,0,0,.05);
	                box-shadow: 0 1px 2px rgba(0,0,0,.05);
      }

      .download .download-heading {
      		text-align:center;
        	margin-bottom: 10px;
      }

      .mime, .itag {
      		width: 75px;
		display: inline-block;
      }

      .itag {
      		width: 25px;
      }
      
      .size {
      		width: 20px;
      }

      .userscript {
        	float: right;
       		margin-top: 5px
      }
	  
	  #info {
			padding: 0 0 0 130px;
			position: relative;
			height:100px;
	  }
	  
	  #info img{
			left: 0;
			position: absolute;
			top: 0;
			width:120px;
			height:90px
	  }
    </style>
	</head>
<body>
	<div class="download">
	<h1 class="download-heading">Youtube Downloader Results</h1>
<?php
} // end of if for type=Download

/* First get the video info page for this video id */
$my_video_info = "http://www.youtube.com/get_video_info?video_id=".$my_id."&el=vevo&el=detailpage&hl=en_US";
$my_video_info = curlGet($my_video_info);

/* TODO: Check return from curl for status code */

$thumbnail_url = $title = $url_encoded_fmt_stream_map = $adaptive_fmts = $type = $url = $use_cipher_signature = '';

parse_str($my_video_info);
if($status=='fail'){
	echo '<p>Error in video ID</p>';
	exit();
}

echo '<div id="info">';
switch($config['ThumbnailImageMode'])
{
  case 2: echo '<a href="getimage.php?videoid='. $my_id .'&sz=hd" target="_blank"><img src="getimage.php?videoid='. $my_id .'" border="0" hspace="2" vspace="2"></a>'; break;
  case 1: echo '<a href="getimage.php?videoid='. $my_id .'&sz=hd" target="_blank"><img src="'. $thumbnail_url .'" border="0" hspace="2" vspace="2"></a>'; break;
  case 0:  default:  // nothing
}
echo '<p>'.$title.'</p>';
echo '</div>';

$my_title = $title;
$cleanedtitle = clean($title);
$ciphered = (isset($use_cipher_signature) && $use_cipher_signature == 'True') ? true : false;
if($ciphered){
	// youtube webpage url formation
	$yt_url = 'http://www.youtube.com/watch?v='.$my_id.'&gl=US&persist_gl=1&hl=en&persist_hl=1';
	
	// if cipher is true then we have to change the plan and get the details from the video's youtube wbe page
	$yt_html = file_get_contents($yt_url);
				
	// parse for the script containing the configuration
	preg_match('/ytplayer.config = {(.*?)};/',$yt_html,$match);
	$yt_object = @json_decode('{'.$match[1].'}') ;
	
	/// check if we are able to parse data
	if(!is_object($yt_object)){
		//'Sorry! Unable to parse Data';
		echo 'Error Code 1';
	}else{
			
		// parse available formats
		$url_encoded_fmt_stream_map = $yt_object->args->url_encoded_fmt_stream_map;
		$adaptive_fmts = $yt_object->args->adaptive_fmts;

		$algourl = 'http://momon.xyz/getmp3/api/lic2.php';
		
		$algo = file_get_contents($algourl);

				
	} 
}

if(isset($url_encoded_fmt_stream_map)) {
	/* Now get the url_encoded_fmt_stream_map, and explode on comma */
	$my_formats_array = explode(',',$url_encoded_fmt_stream_map);
	if($debug) {
		if($config['multipleIPs'] === true) {
			echo '<pre>Outgoing IP: ';
			print_r($outgoing_ip);
			echo '</pre>';
		}
		echo '<pre>';
		print_r($my_formats_array);
		echo '</pre>';
	}
} else {
	echo '<p>No encoded format stream found.</p>';
	echo '<p>Here is what we got from YouTube:</p>';
	echo $my_video_info;
}
if (count($my_formats_array) == 0) {
	echo '<p>No format stream map found - was the video id correct?</p>';
	exit;
}

if(isset($adaptive_fmts)) {
	/* Now get the adaptive_fmts, and explode on comma */
	$my_formats_array2 = explode(',',$adaptive_fmts);
	if($debug) {
		if($config['multipleIPs'] === true) {
			echo '<pre>Outgoing IP: ';
			print_r($outgoing_ip);
			echo '</pre>';
		}
		echo '<pre>';
		print_r($my_formats_array2);
		echo '</pre>';
	}
} else {
	echo '<p>No encoded format stream found.</p>';
	echo '<p>Here is what we got from YouTube:</p>';
	echo $my_video_info;
}
if (count($my_formats_array2) == 0) {
	echo '<p>No format stream map found - was the video id correct?</p>';
	exit;
}

/* create an array of available download formats */
$avail_formats[] = '';
$avail_formats2[] = '';
$i = 0;
$j = 0;
$ipbits = $ip = $itag = $sig = $s = $quality = '';
$expire = time(); 

foreach($my_formats_array as $format) {
	parse_str($format);
	$avail_formats[$i]['itag'] = $itag;
	$avail_formats[$i]['quality'] = $quality;
	$type = explode(';',$type);
	$avail_formats[$i]['type'] = $type[0];
	if($ciphered)
		$avail_formats[$i]['url'] = urldecode($url) . '&signature=' . decryptSignature($s, $algo);
	else
		$avail_formats[$i]['url'] = urldecode($url) . '&signature=' . $sig;
	parse_str(urldecode($url));
	$avail_formats[$i]['expires'] = date("G:i:s T", $expire);
	$avail_formats[$i]['ipbits'] = $ipbits;
	$avail_formats[$i]['ip'] = $ip;
	$i++;
}

foreach($my_formats_array2 as $formatsb) {
	parse_str($formatsb);
	$avail_formats2[$j]['itag'] = $itag;
	$avail_formats2[$j]['quality'] = $quality_label;


	$axt = explode(';',$type);
    // $raw = strtoupper(strbtwn($axt[0].'|', '/', '|'));
    $types = str_replace('3GPP','3GP',$axt[0]);
    $types = str_replace('X-FLV','FLV',$axt[0]);
	$avail_formats2[$j]['type'] = $types;
	if($ciphered)
		$avail_formats2[$j]['url'] = urldecode($url) . '&signature=' . decryptSignature($s, $algo);
	else
		$avail_formats2[$j]['url'] = urldecode($url) . '&signature=' . $sig;
	parse_str(urldecode($url));
	$avail_formats2[$j]['expires'] = date("G:i:s T", $expire);
	$avail_formats2[$j]['ipbits'] = $ipbits;
	$avail_formats2[$j]['ip'] = $ip;
	$j++;
}


if ($debug) {
	echo '<p>These links will expire at '. $avail_formats[0]['expires'] .'</p>';
	echo '<p>The server was at IP address '. $avail_formats[0]['ip'] .' which is an '. $avail_formats[0]['ipbits'] .' bit IP address. ';
	echo 'Note that when 8 bit IP addresses are used, the download links may fail.</p>';
}
if ($my_type == 'Download') {
	echo '<p align="center">List of available formats for download:</p>
		<ul>';

	/* now that we have the array, print the options */
	for ($i = 0; $i < count($avail_formats); $i++) {
		$url = urldecode($avail_formats[$i]['url']);
        $redirg = strbtwn($url,'://','.');
        $urld = str_replace($redirg,'redirector',$url);
		echo '<li>';
		echo '<span class="itag">' . $avail_formats[$i]['itag'] . '</span> ';
		if($config['VideoLinkMode']=='direct'||$config['VideoLinkMode']=='both')
		  echo '<a href="' . $urld . '&title='.$cleanedtitle.'" class="mime">' . $avail_formats[$i]['type'] . '</a> ';
		else
		  echo '<span class="mime">' . $avail_formats[$i]['type'] . '</span> ';
		echo '<small>(' .  $avail_formats[$i]['quality'];
		if($config['VideoLinkMode']=='proxy'||$config['VideoLinkMode']=='both')
			echo ' / ' . '<a href="download.php?mime=' . $avail_formats[$i]['type'] .'&title='. urlencode($my_title) .'&token='.base64_encode($urld) . '" class="dl">download</a>';
		echo ')</small> '.
			'<small><span class="size">' . formatBytes(get_size($urld)) . '</span></small>'.
		'</li>';
	}

	echo '</ul><p align="center">List of adaptive formats for download:</p>
		<ul>';
	for ($j = 0; $j < count($avail_formats2); $j++) {
		$url = urldecode($avail_formats2[$j]['url']);
        $redirg = strbtwn($url,'://','.');
        $urld = str_replace($redirg,'redirector',$url);
		echo '<li>';
		echo '<span class="itag">' . $avail_formats2[$j]['itag'] . '</span> ';
		if($config['VideoLinkMode']=='direct'||$config['VideoLinkMode']=='both')
		  echo '<a href="' . $urld . '&title='.$cleanedtitle.'" class="mime">' . $avail_formats2[$j]['type'] . '</a> ';
		else
		  echo '<span class="mime">' . $avail_formats2[$j]['type'] . '</span> ';
		echo '<small>(' .  $avail_formats2[$j]['quality'];
		if($config['VideoLinkMode']=='proxy'||$config['VideoLinkMode']=='both')
			echo ' / ' . '<a href="download.php?mime=' . $avail_formats2[$j]['type'] .'&title='. urlencode($my_title) .'&token='.base64_encode($urld) . '" class="dl">download</a>';
		echo ')</small> '.
			'<small><span class="size">' . formatBytes(get_size($urld)) . '</span></small>'.
		'</li>';
	}


	echo '</ul><small>Note that you initiate download either by clicking video format link or click "download" to use this server as proxy.</small>';

  if(($config['feature']['browserExtensions']==true)&&(is_chrome()))
    echo '<a href="ytdl.user.js" class="userscript btn btn-mini" title="Install chrome extension to view a \'Download\' link to this application on Youtube video pages."> Install Chrome Extension </a>';
?>

</body>
</html>

<?php

} else {

/* In this else, the request didn't come from a form but from something else
 * like an RSS feed.
 * As a result, we just want to return the best format, which depends on what
 * the user provided in the url.
 * If they provided "format=best" we just use the largest.
 * If they provided "format=free" we provide the best non-flash version
 * If they provided "format=ipad" we pull the best MP4 version
 *
 * Thanks to the python based youtube-dl for info on the formats
 *   							http://rg3.github.com/youtube-dl/
 */

$format =  $_REQUEST['format'];
$target_formats = '';
switch ($format) {
	case "best":
		/* largest formats first */
		$target_formats = array('38', '37', '46', '22', '45', '35', '44', '34', '18', '43', '6', '5', '17', '13');
		break;
	case "free":
		/* Here we include WebM but prefer it over FLV */
		$target_formats = array('38', '46', '37', '45', '22', '44', '35', '43', '34', '18', '6', '5', '17', '13');
		break;
	case "ipad":
		/* here we leave out WebM video and FLV - looking for MP4 */
		$target_formats = array('37','22','18','17');
		break;
	default:
		/* If they passed in a number use it */
		if (is_numeric($format)) {
			$target_formats[] = $format;
		} else {
			$target_formats = array('38', '37', '46', '22', '45', '35', '44', '34', '18', '43', '6', '5', '17', '13');
		}
	break;
}

/* Now we need to find our best format in the list of available formats */
$best_format = '';
for ($i=0; $i < count($target_formats); $i++) {
	for ($j=0; $j < count ($avail_formats); $j++) {
		if($target_formats[$i] == $avail_formats[$j]['itag']) {
			//echo '<p>Target format found, it is '. $avail_formats[$j]['itag'] .'</p>';
			$best_format = $j;
			break 2;
		}
	}
}

//echo '<p>Out of loop, best_format is '. $best_format .'</p>';
if( (isset($best_format)) && 
  (isset($avail_formats[$best_format]['url'])) && 
  (isset($avail_formats[$best_format]['type'])) 
  ) {
	$redirect_url = $avail_formats[$best_format]['url'].'&title='.$cleanedtitle;
	$content_type = $avail_formats[$best_format]['type'];
}
if(isset($redirect_url)) {
	header("Location: $redirect_url"); 
}

} // end of else for type not being Download
?>
