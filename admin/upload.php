<?php
//this uploads and converts the video, should switch to a better solution!
require('lib/common.php');
use Intervention\Image\ImageManager;
use Streaming\FFMpeg;
use FFMpeg\Coordinate;
use FFMpeg\Media;
use FFMpeg\Filters;

$manager = new ImageManager();

$video_id = bin2hex(random_bytes(6));
$new = '';
foreach(str_split($video_id) as $char){
	if (rand(0, 1) == 1) {
		$char = str_rot13($char);
	} else if (rand(0, 2) == 2) {
		$char = '_';
	} else if (rand(0, 3) == 3) {
		$char = mb_strtoupper($char);
	}
	$new .= $char;
}
$new = substr($new, 0, 11);

if(isset($_POST['upload']) AND isset($currentUser['username'])){
	$name       = $_FILES['fileToUpload']['name'];  
    $temp_name  = $_FILES['fileToUpload']['tmp_name'];  // gets video info and thumbnail info
	$ext  = pathinfo( $_FILES['fileToUpload']['name'], PATHINFO_EXTENSION );
	$target_file = $_SERVER['DOCUMENT_ROOT'] . '/videos/'.$new.'.'.$ext;
	if(move_uploaded_file($temp_name, $target_file)){
		$config = [
			'timeout'          => 3600, // The timeout for the underlying process
			'ffmpeg.threads'   => 12,   // The number of threads that FFmpeg should use
		];
			
		$ffmpeg = FFMpeg::create($config);
		$video = $ffmpeg->open($target_file);
		$dash = $video->dash()
			->setAdaption('id=0,streams=v id=1,streams=a') // Set the adaption.
			->x264() // Format of the video. Alternatives: x264() and vp9()
			->autoGenerateRepresentations() // Auto generate representations
			->save(); // It can be passed a path to the method or it can be null
		$metadata = $dash->metadata();
		if (intval($metadata->getFormat()->get('duration')) < 10) {
			$video->frame(Coordinate\TimeCode::fromSeconds(intval($metadata->getFormat()->get('duration'))))
				->save($_SERVER['DOCUMENT_ROOT'] . '/assets/thumb/' . $new . '.png');
		} else {
			$video->frame(Coordinate\TimeCode::fromSeconds(10))
				->save($_SERVER['DOCUMENT_ROOT'] . '/assets/thumb/' . $new . '.png');
		}
		$img = $manager->make($_SERVER['DOCUMENT_ROOT'] . '/assets/thumb/' . $new . '.png');
		$img->resize(640, 360);
		$img->save($_SERVER['DOCUMENT_ROOT'] . '/assets/thumb/' . $new . '.png');
		unlink($target_file);
		query("INSERT INTO videos (video_id, title, description, author, time, videofile, videolength) VALUES (?,?,?,?,?,?,?)",
			[$new,$_POST['title'],$_POST['desc'],$currentUser['id'],time(),'videos/'.$new.'.mpd',intval($metadata->getFormat()->get('duration'))]);
		redirect('./watch.php?v='.$new);
	}
}

$twig = twigloader();
echo $twig->render('admin/upload.twig');
?>