<?php
$resolutions = [
    [
        'width' => 640,
        'height' => 360
    ],
    [
        'width' => 1024,
        'height' => 576
    ],
    [
        'width' => 1152,
        'height' => 648
    ],
    [
        'width' => 1280,
        'height' => 720
    ],
    [
        'width' => 1366,
        'height' => 768
    ],
    [
        'width' => 1600,
        'height' => 900
    ],
    [
        'width' => 1920,
        'height' => 1080
    ],
    [
        'width' => 2560,
        'height' => 1440
    ],
    [
        'width' => 3840,
        'height' => 2160
    ],
    [
        'width' => 1920,
        'height' => 1080
    ]
];
echo "<pre>CI SONO!!!</pre>";

include(__DIR__ . '/config/db.php');
set_time_limit(7800);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$MAX_CHUNK = 32;
define('PATH', __DIR__ . '/upload/uploads/');
define('PATH_INTRO', __DIR__ . '/upload/');

function generateRandomString($length = 30) {
    return bin2hex(random_bytes($length));
}

function logMessage($folder, $message) {
    $logFile = PATH . $folder . 'process.log';
    if (is_dir(PATH . $folder))
		file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

function executeFFmpeg($command, $folder, $step) {
    logMessage($folder, "Executing step $step: $command");
    $output = [];
    $returnVar = null;
    exec($command . " 2>&1", $output, $returnVar);
    $outputStr = implode("\n", $output);
    logMessage($folder, "Return code: $returnVar");
    logMessage($folder, "Output: $outputStr");
    return $returnVar === 0;
}




function convertToHLS($mp4Path, $path_dest) {
    if (!file_exists($mp4Path)) {
        return "File non trovato: $mp4Path";
    }

    if (!is_dir($path_dest)) {
		mkdir($path_dest, 0755, true);
		copy(PATH_INTRO . "index.php", $path_dest . "index.php");
	}

    $dirPath = $path_dest;
    $fileName = pathinfo($mp4Path, PATHINFO_FILENAME);

    $segPattern = $dirPath . '/' . $fileName . '_%d.ts';
    $m3u8Path  = $dirPath . '/' . $fileName . '.m3u8';

    // Stream copy: il MP4 ha keyframe ogni 6s (CFR + GOP fisso)
    $command = sprintf(
        'ffmpeg -y -i %s -c copy -f hls -hls_time 6 -hls_list_size 0 -start_number 0 -hls_segment_filename %s %s',
        escapeshellarg($mp4Path),
        escapeshellarg($segPattern),
        escapeshellarg($m3u8Path)
    );

    $output = [];
    $returnVal = 0;
    exec($command . " 2>&1", $output, $returnVal);

    if ($returnVal !== 0) {
        return "Errore nella conversione: " . implode("\n", $output);
    }

    return "Conversione completata con successo. File creati in: $dirPath";
}



echo "<pre>CI SONO!!!</pre>";


// Main execution
$demon_id = generateRandomString();

// Get jobs (escludi Live-Seg che sono gestiti da demonlive.php)
$sql = "SELECT * FROM jobs WHERE maked = '0' AND folder NOT LIKE '%Live-Seg%' AND (dont_elaborate_before IS NULL OR dont_elaborate_before <= NOW()) ORDER BY priority,data_creation LIMIT 1";
$jobs = mysqli_query($connection, $sql);
echo "<pre>" . $sql . "</pre>";


if (mysqli_num_rows($jobs) == 0) {
    $sql = "SELECT * FROM jobs WHERE maked = '1' AND folder NOT LIKE '%Live-Seg%' AND passing<130 AND (dont_elaborate_before IS NULL OR dont_elaborate_before <= NOW()) ORDER BY passing,priority,data_creation LIMIT 1";
echo "<pre>" . $sql . "</pre>";
    $jobs = mysqli_query($connection, $sql);
}

echo "<pre>" . print_r($jobs,true) . "</pre>";



while ($job = mysqli_fetch_array($jobs)) {
	$folder = "";
	
	$stmt = mysqli_prepare($connection, "UPDATE jobs SET passing = passing + 1, maked = 3 WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $job['id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
	logMessage($folder, "keeped: " . $sql);

	
	
    $folder = $job['folder'] . DIRECTORY_SEPARATOR;

echo "<pre>" . print_r($job,true) . "</pre>";

//echo "<pre>" . $filder . "</pre>";
    
    if (empty(trim($folder, "/"))) continue;
    
    logMessage($folder, "Starting processing for folder: $folder");
    
    $f_arr = explode(DIRECTORY_SEPARATOR, $folder);
    
    echo "<pre>" . print_r($f_arr,true) . "</pre>";
    logMessage($folder, print_r($f_arr,true));

    $json = false;
// Read the JSON file
	if (file_exists(PATH . $folder . DIRECTORY_SEPARATOR . 'detail.json'))
		$json = file_get_contents(PATH . $folder . DIRECTORY_SEPARATOR . 'detail.json'); 
	
	
    echo "<pre>" . $json . "</pre>";
    logMessage($folder, $json);
	
	
	if (!$json)
	{
		$stmt = mysqli_prepare($connection, "UPDATE jobs SET passing = passing + 1, maked = 1 WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $job['id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
		logMessage($folder, "UPDATE jobs SET passing = passing + 1, maked = 1 WHERE id = {$job['id']}");
        
		die("No detail.json");

	}
	
	$json_data = json_decode($json, true); 

    echo "<pre>" . print_r($json_data,true) . "</pre>";


	logMessage($folder,"check path exists: " . PATH . $folder . DIRECTORY_SEPARATOR . 'detail.json');
	echo "check path exists: " . PATH . $folder . DIRECTORY_SEPARATOR . 'detail.json';
	
	
	$esiste = file_exists(PATH . $folder . DIRECTORY_SEPARATOR . 'detail.json');
	
	if ($esiste == true)
	{
		echo "RESULT: TRUE!";
		logMessage($folder,"RESULT: TRUE!");
	}
	else
	{
		echo "RESULT: FALSE!";
		logMessage($folder,"RESULT: FALSE!");
	}	
	
	if ($esiste == false)
	{
		$stmt = mysqli_prepare($connection, "UPDATE jobs SET passing = passing + 1, maked = 1 WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $job['id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
		logMessage($folder, "UPDATE jobs SET passing = passing + 1, maked = 1 WHERE id = {$job['id']}");
		die("No folder");

	}

	$MAX_CHUNK = intval($json_data['chunk_num']);
	$M_VBPS = (intval($json_data['vbps']) / 1000000);

if ($json_data['vbps'] == "-1")
{
	$crf = 20;
}
else
{
	$crf = 26;
	if ($M_VBPS > 1.5)  $crf = 24;
	if ($M_VBPS > 3.0)  $crf = 22;
	if ($M_VBPS > 4.5)  $crf = 20;
	if ($M_VBPS > 6.0)  $crf = 19;
	if ($M_VBPS > 8.0)  $crf = 18;
	if ($M_VBPS > 13.0) $crf = 16;
	if ($M_VBPS > 18.0) $crf = 14;
}		

    
    
$validParts = [];
$missingParts = [];
for ($i = 0; $i < $MAX_CHUNK; $i++) {
    $partFile = PATH . $folder . 'part' . $i . '.webm';
    if (file_exists($partFile) && filesize($partFile) > 0) {
        $validParts[] = $i;
    } else {
        $missingParts[] = $i;
        logMessage($folder, "Missing or empty file: part$i.webm");

        $stmt = mysqli_prepare($connection, "UPDATE jobs SET passing = passing + 1, maked = 1 WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $job['id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

    }
}

if (empty($validParts)) {
		die("no valid parts");
}
else
{
	
	//echo "VP: " . count($validParts) ." == MC: ". MAX_CHUNK;
if ((count($validParts) == $MAX_CHUNK) || ($job['passing']>128) )
{
 
// Rileva fps dal primo chunk per calcolo GOP
$firstPart = PATH . $folder . 'part' . $validParts[0] . '.webm';
$fpsCmd = sprintf('ffprobe -v 0 -of csv=p=0 -select_streams v:0 -show_entries stream=r_frame_rate %s',
    escapeshellarg($firstPart));
$fpsOut = [];
exec($fpsCmd, $fpsOut);
$fpsParts = explode('/', $fpsOut[0] ?? '24/1');
$fps = intval($fpsParts[0]) / intval($fpsParts[1] ?: 1);

$hlsTime = 6;
$gopSize = round($fps * $hlsTime);

// Maxrate/bufsize proporzionati al CRF
$maxrates = [
    14 => '30M', 15 => '25M', 16 => '22M', 17 => '18M',
    18 => '15M', 19 => '12M', 20 => '10M', 21 => '9M',
    22 => '8M',  23 => '7M',  24 => '6M',  25 => '5M', 26 => '4M',
];
$maxrate = $maxrates[$crf] ?? '8M';

// Non superare il bitrate sorgente: inutile gonfiare oltre quello che l'utente ha registrato
if ($json_data['vbps'] != "-1") {
    $sourceCapMbps = ceil($M_VBPS);
    if (intval($maxrate) > $sourceCapMbps) {
        $maxrate = $sourceCapMbps . 'M';
    }
}

$bufsizeVal = intval($maxrate) * 2;
$bufsize = $bufsizeVal . 'M';

// Concat list
$listFile = PATH . $folder . 'concat_list.txt';
$listContent = '';
for ($i = 0; $i < count($validParts); $i++) {
    $partNum = $validParts[$i];
    $listContent .= "file " . escapeshellarg('part' . $partNum . '.webm') . "\n";
}
file_put_contents($listFile, $listContent);

// Watermark
$height = $resolutions[$json_data['version']];
$outputMp4 = PATH . $f_arr[0] . '/' . basename($folder) . '-output.mp4';
$hasAudio = (strpos($folder, "audio") !== false) || (strpos($folder, "esultanza") !== false);
$concatRaw = PATH . $folder . 'concat_raw.mkv';

// Step A: concat WebM → MKV intermedio (stream copy, istantaneo, sistema i timestamp)
$command_concat = 'ffmpeg -y -f concat -safe 0 -i ' . escapeshellarg($listFile) .
    ' -c copy -avoid_negative_ts make_zero ' . escapeshellarg($concatRaw);

logMessage($folder, "Concat: " . $command_concat);

if (!executeFFmpeg($command_concat, $folder, 1)) {
    logMessage($folder, "Concat failed");
    @unlink($listFile);
    continue;
}

@unlink($listFile);

if (!file_exists($concatRaw)) {
    logMessage($folder, "concat_raw.mkv was not created");
    continue;
}

// Step B: trim + genpts + watermark + audio → MP4 finale (unico encode reale)
$command_single = 'ffmpeg -y -fflags +genpts -ss 0.5 -i ' . escapeshellarg($concatRaw);

if (!$hasAudio) {
    $command_single .= ' -stream_loop -1 -i ' . escapeshellarg(PATH_INTRO . 'base' . rand(0, 12) . '.mp3');
}

$overlayTextPath = PATH . $folder . 'overlay_text.txt';
$overlayText = $json_data['text_home'] . ' - ' . $json_data['text_away'] . ' ' . $json_data['minutes'];
if (file_put_contents($overlayTextPath, $overlayText) === false) {
    logMessage($folder, 'Unable to create overlay text file');
    continue;
}

// Il testo controllato dall'utente non entra più nella command line.
$filterTextPath = str_replace(['\\', ':', "'"], ['\\\\', '\\:', "\\'"], $overlayTextPath);
$videoFilter = "drawtext=textfile='" . $filterTextPath . "'" .
    ":fontcolor=#000000" .
    ":fontsize=" . round($height['height'] / 24) .
    ":box=1" .
    ":boxcolor=white@0.5" .
    ":boxborderw=5" .
    ":x=(w-text_w)/2" .
    ":y=th+2," .
    "drawtext=text='YalpeR.it'" .
    ":fontcolor=#000000" .
    ":fontsize=" . round($height['height'] / 12) .
    ":box=1" .
    ":boxcolor=white@0.5" .
    ":boxborderw=5" .
    ":x=(w-text_w)/2" .
    ":y=h-th-10";

$command_single .= ' -avoid_negative_ts make_zero -vf ' . escapeshellarg($videoFilter);

$command_single .= ' -c:v libx264 -crf ' . $crf . ' -preset medium -tune film' .
    ' -profile:v high -level 5.1' .
    ' -maxrate ' . $maxrate . ' -bufsize ' . $bufsize .
    ' -force_key_frames "expr:gte(t,n_forced*' . $hlsTime . ')"' .
    ' -sc_threshold 0' .
    ' -vsync vfr -c:a aac -b:a 128k';

if (!$hasAudio) {
    $command_single .= ' -map 0:v:0 -map 1:a:0 -shortest';
}

$command_single .= ' -movflags +faststart ' . escapeshellarg($outputMp4);

logMessage($folder, "Encode: " . $command_single);

if (!executeFFmpeg($command_single, $folder, 2)) {
    logMessage($folder, "Encode failed");
    @unlink($concatRaw);
    continue;
}

@unlink($concatRaw);

if (file_exists($outputMp4)) {
    logMessage($folder, "Processing completed successfully");

    // Update credits and status
    $stmt = mysqli_prepare($connection, "UPDATE user_credits SET used_credits = used_credits + 1, left_credits = left_credits - 1 WHERE user_token = ?");
    mysqli_stmt_bind_param($stmt, "s", $f_arr[0]);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($connection, "UPDATE jobs SET maked = 2 WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $job['id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Wall thumbnail dal video finale
    $commandWall = 'ffmpeg -y -accurate_seek -ss 1.0 -i ' . escapeshellarg($outputMp4) . ' -vsync vfr ' .
        '-vf "select=isnan(prev_selected_t)+gte(t-prev_selected_t\,0.8),scale=200:112.5,tile=3x3" ' .
        '-frames:v 1 -qscale:v 3 ' . escapeshellarg(PATH . $f_arr[0] . '/' . basename($folder) . '-output_wall.jpg');
    executeFFmpeg($commandWall, $folder, 2);

    // Copy detail.json
    copy(PATH . $folder . 'detail.json', PATH . $f_arr[0] . '/' . basename($folder) . '-output.json');

    // MP4 → HLS (stream copy, nessuna ri-codifica)
    convertToHLS($outputMp4, PATH . $f_arr[0] . '/');

    // Cleanup
    foreach ($validParts as $partNum) {
        $partFile = PATH . $folder . 'part' . $partNum . '.webm';
        if (file_exists($partFile)) {
            @unlink($partFile);
        }
    }

    $filesToDelete = ['detail.json', 'process.log', 'concat_list.txt', 'concat_raw.mkv', 'overlay_text.txt'];
    foreach ($filesToDelete as $file) {
        $fullPath = PATH . $folder . $file;
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
    }

    if (is_dir(PATH . $folder)) {
        @rmdir(PATH . $folder);
    }
} else {
    logMessage($folder, "Final file was not created");
    $stmt = mysqli_prepare($connection, "UPDATE jobs SET passing = passing + 1, maked = 1 WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $job['id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}  
  
  

}
else
{
        $stmt = mysqli_prepare($connection, "UPDATE jobs SET passing = passing + 1, maked = 1 WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $job['id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

}

}
}
?>
