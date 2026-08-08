<?php
	session_start();
	
	header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
//error_reporting(E_ALL);
//ini_set('display_errors', 1);
	
	include('config/db.php');
	require_once('config/auth.php');

	$div = "6";
	if (isset($_REQUEST['div']))
		$div = $_REQUEST['div'];
		
	$OTT = "";
	if (isset($_REQUEST['OTT']))
		$OTT = $_REQUEST['OTT'];
		
	$token = "";
	if (isset($_REQUEST['token']))
		$token = $_REQUEST['token'];


if ($OTT == "" || $token == "")
	{
		die("Manca Autorizzazione!");
	}

$authenticatedUser = auth_validate_credentials($connection, $OTT, $token);
if (!$authenticatedUser) {
    http_response_code(401);
    die("Sessione non valida o scaduta.");
}

// Il token usato nei percorsi deve provenire dal DB, non dalla richiesta.
$token = $authenticatedUser['token'];

$day =	date("Y_m_d");
$dayRev = date("d_m_Y");
	if (isset($_REQUEST['day']))
		{
			$day = $_REQUEST['day'];
			$arr_d = explode("_",$day);
			
			$dayRev= $arr_d[2]."_".$arr_d[1]."_".$arr_d[0];
			
		}
		
		
//	$oggi = date("-j/n/Y");
	$oggi = date("-Y/m/d");
	$oggirev = date("d_m_Y");
	$tags = array("Goal","Gol-Casa","Gol-Ospiti","Occasione-Casa","Occasione-Ospiti","Parata-Casa","Parata-Ospiti");
	
	
	
// Escludi i file Live-Seg (gestiti da reload_live_btn.php)
$allFiles = glob((__DIR__) . DIRECTORY_SEPARATOR . "upload". DIRECTORY_SEPARATOR ."uploads". DIRECTORY_SEPARATOR . $token . DIRECTORY_SEPARATOR . "*.m3u8");
$files = array_filter($allFiles, function($file) {
    return strpos(basename($file), 'Live-Seg') === false;
});
$uniqueDates = array_unique(array_map(function($file) {
    $date = substr(basename($file), 0, 10); // ottiene gg_mm_aaaa
    // Divide la data nelle sue componenti
    list($giorno, $mese, $anno) = explode('_', $date);
    // Ricompone la data nel nuovo formato
    return str_pad($anno,4,'0',STR_PAD_LEFT) . '_' . str_pad($mese,2,'0',STR_PAD_LEFT) . '_' . str_pad($giorno,2,'0',STR_PAD_LEFT);
}, $files));
$uniqueDates = array_values($uniqueDates);
rsort($uniqueDates); // Ordina in ordine decrescente

/*$uniqueDates = array_map(function($date) {
    list($anno, $mese, $giorno) = explode('_', $date);
    return $giorno . '_' . $mese . '_' . $anno;
}, $uniqueDates);
*/

	//print_r($uniqueDates);

	//echo (__DIR__) . DIRECTORY_SEPARATOR . "upload". DIRECTORY_SEPARATOR ."uploads". DIRECTORY_SEPARATOR . token . DIRECTORY_SEPARATOR . $day . "*.m3u8";
	$allFiles = glob((__DIR__) . DIRECTORY_SEPARATOR . "upload". DIRECTORY_SEPARATOR ."uploads". DIRECTORY_SEPARATOR . $token . DIRECTORY_SEPARATOR . $dayRev . "*.m3u8");
	$files = array_filter($allFiles, function($file) {
    return strpos(basename($file), 'Live-Seg') === false;
});
//	array_multisort(array_map('filemtime', $files), SORT_NUMERIC, SORT_DESC, $files);
function extractDateParts($filename) {
    if (preg_match('/^(\d{1,2})_(\d{1,2})_(\d{4})_+/', $filename, $matches)) {
        $giorno = str_pad((int)$matches[1],2,'0',STR_PAD_LEFT);
        $mese = str_pad((int)$matches[2],2,'0',STR_PAD_LEFT);
        $anno = str_pad((int)$matches[3],4,'0',STR_PAD_LEFT);
        return [$anno, $mese, $giorno];
    } else {
        return ["00", "00", "0000"];
    }
}



	


usort($files, function($a, $b) {
    list($annoA, $meseA, $giornoA) = extractDateParts(basename($a));
    list($annoB, $meseB, $giornoB) = extractDateParts(basename($b));
    
    if ($annoA !== $annoB) {
        return $annoB <=> $annoA;
    }
    if ($meseA !== $meseB) {
        return $meseB <=> $meseA;
    }
    return $giornoB <=> $giornoA;
});


	$html = "<div class='row'>
					<div class='col-6'>
						<a style='margin-bottom:2px;width:100%;' class='btn btn-sm btn-danger' href='javascript:;' onclick=load_replay_btn('".$div."')>Aggiorna</a>
					</div>
					<div class='col-2'>
						<a style='margin-bottom:2px;width:100%;padding-left:3px;' class='btn btn-sm btn-danger' href='javascript:;' onclick=load_replay_btn('12')>1Col.</a>
					</div>
					<div class='col-2'>
						<a style='margin-bottom:2px;width:100%;padding-left:3px;' class='btn btn-sm btn-danger' href='javascript:;' onclick=load_replay_btn('6')>2Col.</a>
					</div>
					<div class='col-2'>
						<a style='margin-bottom:2px;width:100%;padding-left:3px;' class='btn btn-sm btn-danger' href='javascript:;' onclick=load_replay_btn('4')>3Col.</a>
					</div>
				</div>";
	//$html .= "<div class='row'>";

	$inc_id = 0;	

	foreach (glob((__DIR__) . DIRECTORY_SEPARATOR . "upload". DIRECTORY_SEPARATOR ."uploads". DIRECTORY_SEPARATOR. $token . DIRECTORY_SEPARATOR."*", GLOB_ONLYDIR ) as $filename) 
	{
		$text = basename($filename);
		if (strpos($text, 'Live-Seg') !== false) {
			continue;
		}
		$text_array = explode("_",$text);
		
	
		if ((count($text_array)==6)||(count($text_array)==7))
		{
			
			if (count($text_array)==6)
				$testo_bottone = $text_array[0] . "/".$text_array[1] . "/".$text_array[2] . " " . $text_array[3] . ":" .$text_array[4].":".str_replace("-output.m3u8","",$text_array[5]);   
			
			if (count($text_array)==7)
				$testo_bottone = $text_array[0] . "/".$text_array[1] . "/".$text_array[2] . " " . $text_array[4] . ":" .$text_array[5].":".str_replace("-output.m3u8","",$text_array[6]);   
				
		}
		else
			$testo_bottone = $text;

		$testo_bottone = str_replace("Goal","<br /><b>Goal!</b>&nbsp;",$testo_bottone);
		$testo_bottone = str_replace("Gol-Casa","<br /><b style='color:#2f2;'>Goal Casa</b>&nbsp;",$testo_bottone);
		$testo_bottone = str_replace("Gol-Ospiti","<br /><b style='color:#f22;'>Goal Ospiti</b>&nbsp;",$testo_bottone);
		$testo_bottone = str_replace("Occasione-Casa","<br /><b style='color:#2f2;'>Occasione Casa</b>&nbsp;",$testo_bottone);
		$testo_bottone = str_replace("Occasione-Ospiti","<br /><b style='color:#f22;'>Occasione Ospiti</b>&nbsp;",$testo_bottone);
		$testo_bottone = str_replace("Parata-Casa","<br /><b style='color:#2f2;'>Parata Casa</b>&nbsp;",$testo_bottone);
		$testo_bottone = str_replace("Parata-Ospiti","<br /><b style='color:#f22;'>Parata Ospiti</b>&nbsp;",$testo_bottone);
		
		if (strpos($testo_bottone,"foto-") !== false)
		{
			$inc_id++;
			$html .=  "<div class='col-".$div."' style='margin-bottom:5px;position:relative;'>
									<a href='javascript:;' id='photo_".$inc_id."' onclick=visualizza_photo(this,'https://yalper.it/upload/uploads/".$token.DIRECTORY_SEPARATOR.basename($filename).DIRECTORY_SEPARATOR.str_replace("foto-","",basename($filename)) . ".png') style='position: relative;text-decoration: none !important;text-align: center;'>
										<img id='cover_".$inc_id."' style='position:relative;width:100%;top:0px;border-style:solid;border-width:2px;border-color:yellow;'  src='https://yalper.it/upload/uploads/".$token.DIRECTORY_SEPARATOR.basename($filename).DIRECTORY_SEPARATOR.str_replace("foto-","",basename($filename)) . ".png'>
										<div style='position: absolute;top: 33%;left: 50%;transform: translate(-50%, -50%);font-size:".($div*0.2)."vw;color:#fff;text-shadow: -1px 0 black, 0 1px black, 1px 0 black, 0 -1px black;bottom:50px;z-index:1000;'>FOTO: " . str_replace("foto-","",$testo_bottone) . "
										</div>
									</a>
							</div>";
		}
		else	
			$html .=  "<a style='margin-left:10%;margin-bottom:2px;width:80%;' class='btn btn-sm btn-warning' href='javascript:;'>In elaborazione...<br />".$testo_bottone ."</a>";
	}
		
	$inc_id = 0;	

	for ($i=0;$i<count($uniqueDates);$i++)
		if ($uniqueDates[$i]>$day)
			$html .= "<a href='javascript:;' class='btn btn-info' style='margin-bottom:6px;margin-left:25%;width:50%;' onclick=load_replay_btn('".$div."','".$uniqueDates[$i]."');>".str_replace("_","/",$uniqueDates[$i])."</a>";


	if (count($files)>0)
		$html .= "<h4 class='btn btn-info' onclick='if(event.target.id != \"chk_all\") toggle(\"videos\")' style='margin-bottom:6px;margin-left:25%;width:50%;'>".str_replace("_","/",$day) . "<div class='form-check form-switch' style='float:right;'><input id='chk_all' class='form-check-input' style='z-index:20000;' type='checkbox' onchange=switch_sel_desel(event); /></div></h4>";	
	
	//	$html .= "<h4 class='btn btn-info' onclick=toggle('videos') style='margin-bottom:6px;margin-left:25%;width:50%;'>".str_replace("_","/",$day) . "<div class='form-check form-switch' style='float:right;'><input  id='chk_all' class='form-check-input' style='z-index:20000;' type='checkbox' onchange=switch_sel_desel(event); /></div></h4>";
										
	$html .= "<div class='row video_".$day."' id='videos'>";
	foreach ( $files as $filename) 
	{
		$inc_id++;
		$text = basename($filename);
		$text_array = explode("_",$text);
		if ((count($text_array)==6)||(count($text_array)==7))
		{
			
			if (count($text_array)==6)
				$testo_bottone = $text_array[0] . "/".$text_array[1] . "/".$text_array[2] . " " . $text_array[3] . ":" .$text_array[4].":".str_replace("-output.m3u8","",$text_array[5]);   
			
			if (count($text_array)==7)
				$testo_bottone = $text_array[0] . "/".$text_array[1] . "/".$text_array[2] . " " . $text_array[4] . ":" .$text_array[5].":".str_replace("-output.m3u8","",$text_array[6]);   
				
		}
		else
			$testo_bottone = $text;



//		$html_giorno[$text_array[0] . "/".$text_array[1] . "/".$text_array[2]] = 

		//$html .= $text_array[0] . "/".$text_array[1] . "/".$text_array[2];
		//$html .= "<br />file: " . $text_array[0] . "/".$text_array[1] . "/".$text_array[2] . " oggi:" . $oggi . " pos:".strpos($oggi,$text_array[0] . "/".$text_array[1] . "/".$text_array[2]);
		if (strpos($oggi,str_replace($tags,"","-".$text_array[0] . "/".$text_array[1] . "/".$text_array[2]))!== false)
		{
			$testo_bottone = str_replace("Goal","<br /><b>Goal!</b>&nbsp;",$testo_bottone);
			$testo_bottone = str_replace("Gol-Casa","<br /><b style='color:#2f2;'>Goal Casa</b>&nbsp;",$testo_bottone);
			$testo_bottone = str_replace("Gol-Ospiti","<br /><b style='color:#f22;'>Goal Ospiti</b>&nbsp;",$testo_bottone);
			$testo_bottone = str_replace("Occasione-Casa","<br /><b style='color:#2f2;'>Occasione Casa</b>&nbsp;",$testo_bottone);
			$testo_bottone = str_replace("Occasione-Ospiti","<br /><b style='color:#f22;'>Occasione Ospiti</b>&nbsp;",$testo_bottone);
			$testo_bottone = str_replace("Parata-Casa","<br /><b style='color:#2f2;'>Parata Casa</b>&nbsp;",$testo_bottone);
			$testo_bottone = str_replace("Parata-Ospiti","<br /><b style='color:#f22;'>Parata Ospiti</b>&nbsp;",$testo_bottone);			
			//$testo_bottone .= "ZZZ";
			if (file_exists("upload/uploads/".$token.DIRECTORY_SEPARATOR.str_replace(".m3u8","_wall.jpg",basename($filename))))
				$html .=  "<div class='col-".$div."' style='margin-bottom:5px;position:relative;'>
									<div class='form-check form-switch' style='position:absolute;top:10px;left:60px;'>
										<input  id='chk_".$inc_id."' file='".base64_encode("upload/uploads/".$token.DIRECTORY_SEPARATOR.basename($filename))."' class='selezionati form-check-input' style='position:absolute;top:0px;left:0px;z-index:20000;' type='checkbox' onchange=selezionati()>
									</div>
									<a href='javascript:;' id='href_".$inc_id."' onclick=visualizza_replay(this,'".base64_encode("upload/uploads/".token.DIRECTORY_SEPARATOR.basename($filename))."') style='position: relative;text-decoration: none !important;text-align: center;'>
										<img id='cover_".$inc_id."' style='position:relative;width:100%;top:0px;border-style:solid;border-width:2px;border-color:#146c43;' src='https://yalper.it/upload/uploads/".token.DIRECTORY_SEPARATOR.str_replace(".m3u8","_wall.jpg",basename($filename))."' />
										<div style='position: absolute;top: 33%;left: 50%;transform: translate(-50%, -50%);font-size:".($div*0.2)."vw;color:#fff;text-shadow: -1px 0 black, 0 1px black, 1px 0 black, 0 -1px black;bottom:50px;z-index:1000;'>" . $testo_bottone . "
										</div>
									</a>
								</div>";
			else
			if (file_exists("upload/uploads/".token.DIRECTORY_SEPARATOR.str_replace(".m3u8","_wall.jpg",basename($filename))))
				$html .=  "<div class='col-".$div."' style='margin-bottom:5px;position:relative;'>
									<div class='form-check form-switch' style='position:absolute;top:10px;left:60px;'>
										<input  id='chk_".$inc_id."' file='".base64_encode("upload/uploads/".token.DIRECTORY_SEPARATOR.basename($filename))."' class='selezionati form-check-input' style='position:absolute;top:0px;left:0px;z-index:20000;' type='checkbox' onchange=selezionati()>
									</div>
									<a href='javascript:;' id='href_".$inc_id."' onclick=visualizza_replay(this,'".base64_encode("upload/uploads/".token.DIRECTORY_SEPARATOR.basename($filename))."') style='position: relative;text-decoration: none !important;text-align: center;'>
										<img id='cover_".$inc_id."' style='position:relative;width:100%;top:0px;border-style:solid;border-width:2px;border-color:#146c43;' src='https://yalper.it/upload/uploads/".token.DIRECTORY_SEPARATOR.str_replace(".m3u8","_wall.jpg",basename($filename))."' />
											<div style='position: absolute;top: 33%;left: 50%;transform: translate(-50%, -50%);font-size:".($div*0.2)."vw;color:#fff;text-shadow: -1px 0 black, 0 1px black, 1px 0 black, 0 -1px black;bottom:50px;z-index:1000;'>" . $testo_bottone . "</div>
									</a>
								</div>";
			else
				$html .=  "<a style='margin-bottom:2px;width:100%;' class='btn btn-sm btn-success' href='javascript:;' onclick=visualizza_replay(this,'".base64_encode("upload/uploads/".token.DIRECTORY_SEPARATOR.basename($filename))."')>".$testo_bottone."</a>";
	//				echo "<a href='?file=".basename($filename)."'>".basename($filename) . " (".date('d/m/Y H:i:s', filemtime( $filename ) ).")</a><br />";
		}
		else
		{
			//$html .= "OPZ2";
			$testo_bottone = str_replace("Goal","<br /><b>Goal!</b>&nbsp;",$testo_bottone);
			$testo_bottone = str_replace("Gol-Casa","<br /><b style='color:#2f2;'>Goal Casa</b>&nbsp;",$testo_bottone);
			$testo_bottone = str_replace("Gol-Ospiti","<br /><b style='color:#f22;'>Goal Ospiti</b>&nbsp;",$testo_bottone);
			$testo_bottone = str_replace("Occasione-Casa","<br /><b style='color:#2f2;'>Occasione Casa</b>&nbsp;",$testo_bottone);
			$testo_bottone = str_replace("Occasione-Ospiti","<br /><b style='color:#f22;'>Occasione Ospiti</b>&nbsp;",$testo_bottone);
			$testo_bottone = str_replace("Parata-Casa","<br /><b style='color:#2f2;'>Parata Casa</b>&nbsp;",$testo_bottone);
			$testo_bottone = str_replace("Parata-Ospiti","<br /><b style='color:#f22;'>Parata Ospiti</b>&nbsp;",$testo_bottone);			

			if (file_exists("upload/uploads/".$token.DIRECTORY_SEPARATOR.str_replace(".m3u8","_wall.jpg",basename($filename)).""))
				$html .=  "<div class='col-".$div."' style='margin-bottom:5px;position:relative;'>
										<div class='form-check form-switch' style='position:absolute;top:10px;left:60px;'>
											<input  id='chk_".$inc_id."' file='".base64_encode("upload/uploads/".$token.DIRECTORY_SEPARATOR.basename($filename))."' class='selezionati form-check-input' style='position:absolute;top:0px;left:0px;z-index:20000;' type='checkbox' onchange=selezionati()>
										</div>
										<a href='javascript:;' id='href_".$inc_id."' onclick=visualizza_replay(this,'".base64_encode("upload/uploads/".$token.DIRECTORY_SEPARATOR.basename($filename))."') style='position: relative;text-decoration: none !important;text-align: center;'>
											<img id='cover_".$inc_id."' style='position:relative;width:100%;top:0px;border-style:solid;border-width:2px;border-color:#146c43;' src='https://yalper.it/upload/uploads/".$token.DIRECTORY_SEPARATOR.str_replace(".m3u8","_wall.jpg",basename($filename))."' />
											<div style='position: absolute;top: 33%;left: 50%;transform: translate(-50%, -50%);font-size:".($div*0.2)."vw;color:#fff;text-shadow: -1px 0 black, 0 1px black, 1px 0 black, 0 -1px black;bottom:50px;z-index:1000;'>" . $testo_bottone . "</div>
										</a>
									</div>";
			
				//$html_in =  "<div class='col-sm-6 col-md-6' style='margin-bottom:5px;'><a href='javascript:;' onclick=visualizza_replay(this,'upload/uploads/".token.DIRECTORY_SEPARATOR.basename($filename)."') style='position: relative;text-decoration: none !important;text-align: center;'><img style='position:relative;width:100%;top:0px;border-style:solid;border-width:2px;border-color:#146c43;' src='upload/uploads/".token.DIRECTORY_SEPARATOR.str_replace(".mp4",".jpg",basename($filename))."' /><div style='position: absolute;top: 50%;left: 50%;transform: translate(-50%, -50%);font-size:11px;color:#fff;text-shadow: -1px 0 black, 0 1px black, 1px 0 black, 0 -1px black;bottom:50px;z-index:1000;'>" . str_replace("Goal","<b>Goal!</b>&nbsp;",$testo_bottone) . "</div></a></div>";
			else
				$html .=  "<a style='margin-bottom:2px;width:100%;' class='btn btn-sm btn-success' href='javascript:;' onclick=visualizza_replay(this,'".base64_encode("upload/uploads/".$token.DIRECTORY_SEPARATOR.basename($filename))."')>".$testo_bottone."</a>";

			//$html_giorno[str_replace($tags,"",$text_array[0] . "/".$text_array[1] . "/".$text_array[2])][] = $html_in;
		}
	}
//		if (isset($html_giorno))
//		if (count($html_giorno)>0)
//			foreach ($html_giorno as $k=>$v)

	/*		foreach ($uniqueDates as $k)
				if ($k != $day)
					$html .= "<a href='javascript:;' class='btn btn-info' style='margin-bottom:6px;margin-left:25%;width:50%;' onclick= load_replay_btn('".$div."','".$k."')>".str_replace("_","/",$k)."</a>";
*/
	$html .= "</div> <!-- videos-->";
	for ($i=0;$i<count($uniqueDates);$i++)
		if ($uniqueDates[$i]<$day)
			$html .= "<a href='javascript:;' class='btn btn-info' style='margin-bottom:6px;margin-left:25%;width:50%;' onclick= load_replay_btn('".$div."','".$uniqueDates[$i]."')>".str_replace("_","/",$uniqueDates[$i])."</a>";


//		$html .=  "</div>";
	
//$html .= "<script>document.getElementById('videos').scrollIntoView(); </script>";
//$html .= "<script>document.getElementById('videos').scrollIntoView();</script>";

echo $html;
?>
<script>
containerRRR = document.querySelector('#replay_btn');
elementoRRR = document.querySelector('#videos');
containerRRR.scrollTop = elementoRRR.offsetTop - 50;	
</script>	
