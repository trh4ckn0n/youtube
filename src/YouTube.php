<?php

class YouTube{
	protected $userAgent = null;
	protected $headersSent = false;
	protected $bufferSize = 256 * 1024;
	
	protected function sendHeader($h){
		header($h);
	}
	
	protected function arrayHeaders($r, $s){
		$d = [];
		$hs = explode("\r\n", trim(substr($r, 0, $s)));
		foreach($hs as $i => $h){
			if(strpos($h, ":") !== false){
				list($n, $v) = explode(":", $h, 2);
				$d[] = [strtolower(trim($n)) => trim($v)];
			}
		}
		return $d;
	}
	
    public function headerCallback($ch, $data){
		if(preg_match('/HTTP\/[\d.]+\s*(\d+)/', $data, $matches)){
			$status_code = $matches[1];
			if($status_code == 200 || $status_code == 206 || $status_code == 403 || $status_code == 404){
				$this->headersSent = true;
				$this->sendHeader(rtrim($data));
			}
		}else{
			$forward = array('content-type', 'content-length', 'accept-ranges', 'content-range');
			$parts = explode(':', $data, 2);
			if($this->headersSent && count($parts) == 2 && in_array(trim(strtolower($parts[0])), $forward)){
				$this->sendHeader(rtrim($data));
			}
		}
		return strlen($data);
	}
	
	protected function curl($a, $d = 0, $hd = [], $f = 0){
		$c = curl_init();
		curl_setopt($c, CURLOPT_URL, $a);
		curl_setopt($c, CURLOPT_HEADER, 1);
		curl_setopt($c, CURLOPT_TIMEOUT, 30);
		if($d){
			curl_setopt($c, CURLOPT_POST, true);
			curl_setopt($c, CURLOPT_POSTFIELDS, $d);
		}
		curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($c, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($c, CURLOPT_FOLLOWLOCATION, $f);
		curl_setopt($c, CURLOPT_HTTPHEADER, $hd);
		$r = curl_exec($c);
		$s = curl_getinfo($c, CURLINFO_HTTP_CODE);;
		$hs = curl_getinfo($c, CURLINFO_HEADER_SIZE);
		curl_close($c);
		return [
			'headers' => $this->arrayHeaders($r, $hs),
			'body' => substr($r, $hs), 
			'ok' => $s
		];
	}
	
	protected function durl($u){
		$c = curl_init();
		curl_setopt($c, CURLOPT_URL, $u);
		curl_setopt($c, CURLOPT_NOBODY, 1);
		curl_setopt($c, CURLOPT_HEADER, 1);
		curl_setopt($c, CURLOPT_TIMEOUT, 15);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($c, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($c, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($c, CURLOPT_HTTPHEADER, [
			'user-agent:'.($this->userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36')
		]);
		curl_exec($c);
		$r = curl_getinfo($c, CURLINFO_EFFECTIVE_URL);
		$s = curl_getinfo($c, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
		if(!$r || !$s || $s <= 0){
			return [false, "Failed to retrieve final URL or file size.", null];
		}else{
			return [true, $r, $s];
		}
	}
	
	protected function murl($u, $hs, $t){
		$r = null;
		$j = $o = [];
		$m = curl_multi_init();
		
		foreach($hs as $i => $h){
			$c = curl_init($u);
			curl_setopt($c, CURLOPT_TIMEOUT, $t);
			curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($c, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($c, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($c, CURLOPT_HTTPHEADER, $h);
			curl_multi_add_handle($m, $c);
			$j[$i] = $c;
		}

		do{
			curl_multi_exec($m, $r);
			curl_multi_select($m);
		}while($r > 0);
	 
		foreach ($j as $i => $c) {
			$o[$i] = curl_multi_getcontent($c);
			curl_multi_remove_handle($m, $c);
			curl_close($c);
		}

		curl_multi_close($m);
		return $o;
	}
	
	protected function getStringBetween($d, $s, $e, $ss = 0, $se = 0){
		$si = 0;
		for($i = 0; $i <= $ss; $i++){
			$si = strpos($d, $s, $si);
			if($si === false){return false;}
			$si += strlen($s);
		}
		$ei = $si;
		for($i = 0; $i <= $se; $i++){
			$ei = strpos($d, $e, $ei);
			if($ei === false){return false;}
			$ei += strlen($e);
		}
		if($ei <= $si){return false;}
		return substr($d, $si, $ei - $si - strlen($e));
	}

	protected function getStringJson($d, $s, $ss = 0){
		$se = $ls = 0;
		while(true){
			$sb = $this->getStringBetween($d, $s.'{', '}', $ss, $se);
			$ts = substr_count($sb, '{');
			$te = substr_count($sb, '}');
			if($ts != $te){
				if($ts != $ls){
					$se = $ts;
					$ls = $ts;
				}else{
					return [];
					break;
				}
			}else{
				return json_decode('{'.$sb.'}', true);
				break;
			}
		}
	}
	
	public function bodyCallback($ch, $data){
		echo $data;
		flush();
		return strlen($data);
	}
	
	protected function getVideo($v){
		$u = 'https://www.youtube.com/watch?'.http_build_query(['v' => $v]);
		$h = [
			'accept-language:en-US,en;q=0.5',
			'accept:text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'user-agent:'.($this->userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36')
		];
		return $this->curl($u, 0, $h, 1);
	}
	
	protected function getVideoInfo($d){
		$r = ['ok' => false];
		$vd = $this->getStringJson($d ,'"videoDetails":');
		$m = $this->getStringJson($d ,'"microformat":');
		if(isset($vd['videoId'], $m['playerMicroformatRenderer'])){
			$r['ok'] = true;
			$r['id'] = $vd['videoId'];
			$r['title'] = $vd['title'];
			$r['description'] = $vd['shortDescription'];
			$r['channelId'] = $vd['channelId'];
			$r['channelTitle'] = $vd['author'];
			$r['channelUrl'] = $m['playerMicroformatRenderer']['ownerProfileUrl'];
			$r['isLive'] = $vd['isLiveContent'];
			$r['isFamilySafe'] = $m['playerMicroformatRenderer']['isFamilySafe'];
			$r['isShortsEligible'] = $m['playerMicroformatRenderer']['isShortsEligible'];
			$r['upload'] = $m['playerMicroformatRenderer']['uploadDate'];
			$r['publish'] = $m['playerMicroformatRenderer']['publishDate'];
			$r['category'] = $m['playerMicroformatRenderer']['category'];
			$r['view'] = (int) $m['playerMicroformatRenderer']['viewCount'];
			$r['duration'] = (int) $m['playerMicroformatRenderer']['lengthSeconds'];
			$r['thumbnails'] = [];
			foreach($vd['thumbnail']['thumbnails'] as $thumbnail){
				$r['thumbnails'][$thumbnail['width'].'x'.$thumbnail['height']] = $thumbnail['url'];
			}
			$r['thumbnails'] = array_reverse($r['thumbnails']);
			$r['availableCountries'] = $m['playerMicroformatRenderer']['availableCountries'] ?? [];
		}
		return $r;
	}
	
	protected function getYouTubeConfig($d){
		$r = ['ok' => 0];
		if(preg_match('/"VISITOR_DATA":"(.*?)"/', $d, $m)){
			$r['VISITOR_DATA'] = $m[1];
			if(preg_match('/"INNERTUBE_API_KEY":"(.*?)"/', $d, $m)){
				$r['INNERTUBE_API_KEY'] = $m[1];
				if(preg_match('/"INNERTUBE_CONTEXT_CLIENT_NAME":(.*?),/', $d, $m)){
					$r['INNERTUBE_CONTEXT_CLIENT_NAME'] = $m[1];
					if(preg_match('/"INNERTUBE_CONTEXT_CLIENT_VERSION":"(.*?)"/', $d, $m)){
						$r['INNERTUBE_CONTEXT_CLIENT_VERSION'] = $m[1];
						$r['ok'] = 1;
					}
				}
			}
		}
		return $r;
	}
	
	protected function getPlayerResponse($v, $c, $i){
		$cl = [
			"android_vr" => [
				"client" => [
					"androidSdkVersion" => 32,
					"clientName" => "ANDROID_VR",
					"clientVersion" => "1.60.19",
					"deviceMake" => "Oculus",
					"deviceModel" => "Quest 3",
					"osName" => "Android",
					"osVersion" => "12L",
					"userAgent" => "com.google.android.apps.youtube.vr.oculus/1.60.19 (Linux; U; Android 12L; eureka-user Build/SQ3A.220605.009.A1) gzip",
					"hl" => "en",
					"timeZone" => "UTC",
					"utcOffsetMinutes" => 0
				]
			],
			"android" => [
				"client" => [
					"androidSdkVersion" => 30,
					"clientName" => "ANDROID",
					"clientVersion" => "19.44.38",
					"osName" => "Android",
					"osVersion" => "11",
					"userAgent" => "com.google.android.youtube/19.44.38 (Linux; U; Android 11) gzip",
					"hl" => "en",
					"timeZone" => "UTC",
					"utcOffsetMinutes" => 0
				]
			],
			"ios" => [
				"client" => [
					"clientName" => "IOS",
					"clientVersion" => "19.45.4",
					"deviceMake" => "Apple",
					"deviceModel" => "iPhone16,2",
					"osName" => "iPhone",
					"osVersion" => "18.1.0.22B83",
					"userAgent" => "com.google.ios.youtube/19.45.4 (iPhone16,2; U; CPU iOS 18_1_0 like Mac OS X;)",
					"hl" => "en",
					"timeZone" => "UTC",
					"utcOffsetMinutes" => 0
				]
			]
		];
		$m = $cl[$i] ?? $cl['android_vr'];
		$u = 'https://www.youtube.com/youtubei/v1/player?key='.$c['INNERTUBE_API_KEY'];
		$d = json_encode([
			"context" => $m,
			"videoId" => $v,
			"playbackContext" => [
				"contentPlaybackContext" => [
					"html5Preference" => "HTML5_PREF_WANTS"
				]
			],
			"racyCheckOk" => true
		]);
		$h = [
			'content-type: application/json',
			'user-agent: '.$m['client']['userAgent'],
			'x-goog-visitor-id: '.$c['VISITOR_DATA'],
			'x-youtube-client-name: '.$c['INNERTUBE_CONTEXT_CLIENT_NAME'],
			'x-youtube-client-version: '.$c['INNERTUBE_CONTEXT_CLIENT_VERSION'],
		];
		$this->userAgent = $m['client']['userAgent'];
		return $this->curl($u, $d, $h, 0);
	}
	
	protected function sortBySize($a, $b){
		return $a['size'] - $b['size'];
	}
	
	protected function getPlayerMedias($d){
		$r = [];
		$j = json_decode($d, true);
		$combined = $video = $audio = [];
		if(isset($j['streamingData']['formats'])){
			foreach($j['streamingData']['formats'] as $s){
				if(strpos($s['mimeType'], 'audio') === 0){
					$index = [];
					$index['bitrate'] = (int) $s['bitrate'];
					$index['quality'] = $s['quality'];
					$index['size'] = (int) $s['contentLength'];
					$index['mimeType'] = $s['mimeType'];
					$index['url'] = $s['url'];
					$audio[] = $index;
				}else if(strpos($s['mimeType'], 'video') === 0 && empty($s['audioQuality'])){
					$index = [];
					$index['height'] = (int) $s['height'];
					$index['width'] = (int) $s['width'];
					$index['bitrate'] = (int) $s['bitrate'];
					$index['quality'] = $s['quality'];
					$index['size'] = (int) $s['contentLength'];
					$index['mimeType'] = $s['mimeType'];
					$index['url'] = $s['url'];
					$video[] = $index;
				}else{
					$index = [];
					$index['height'] = (int) $s['height'];
					$index['width'] = (int) $s['width'];
					$index['bitrate'] = (int) $s['bitrate'];
					$index['quality'] = $s['quality'];
					$index['size'] = (int) $s['contentLength'];
					$index['mimeType'] = $s['mimeType'];
					$index['url'] = $s['url'];
					$combined[] = $index;
				}
			}
		}
		if(isset($j['streamingData']['adaptiveFormats'])){
			foreach($j['streamingData']['adaptiveFormats'] as $s){
				if(strpos($s['mimeType'], 'audio') === 0){
					$index = [];
					$index['bitrate'] = (int) $s['bitrate'];
					$index['quality'] = $s['quality'];
					$index['size'] = (int) $s['contentLength'];
					$index['mimeType'] = $s['mimeType'];
					$index['url'] = $s['url'];
					$audio[] = $index;
				}else if(strpos($s['mimeType'], 'video') === 0 && empty($s['audioQuality'])){
					$index = [];
					$index['height'] = (int) $s['height'];
					$index['width'] = (int) $s['width'];
					$index['bitrate'] = (int) $s['bitrate'];
					$index['quality'] = $s['quality'];
					$index['size'] = (int) $s['contentLength'];
					$index['mimeType'] = $s['mimeType'];
					$index['url'] = $s['url'];
					$video[] = $index;
				}else{
					$index = [];
					$index['height'] = (int) $s['height'];
					$index['width'] = (int) $s['width'];
					$index['bitrate'] = (int) $s['bitrate'];
					$index['quality'] = $s['quality'];
					$index['size'] = (int) $s['contentLength'];
					$index['mimeType'] = $s['mimeType'];
					$index['url'] = $s['url'];
					$combined[] = $index;
				}
			}
		}
		if(!empty($audio)){
			usort($audio, [$this, 'sortBySize']);
		}
		if(!empty($video)){
			usort($video, [$this, 'sortBySize']);
		}
		if(!empty($combined)){
			usort($combined, [$this, 'sortBySize']);
		}
		$r['audio'] = $audio;
		$r['video'] = $video;
		$r['combined'] = $combined;
		return $r;
	}
	
	public function extractVideoId($s){
		if(strlen($s) === 11){
			return $s;
		}
		if(preg_match('/(?:\/|%3D|v=|vi=)([a-z0-9_-]{11})(?:[%#?&\/]|$)/ui', $s, $m)){
			return $m[1];
		}
		return false;
	}
	
	public function setUserAgent($s){
		$this->userAgent = $s;
	}
	
	public function setBufferSize($i){
		$s = intval($i);
		$this->bufferSize = ($s > 1024) ? $s : 1024;
	}
	
	public function extractVideoInfo($v, $i = 'ios'){
		$res = ['ok' => false];
		$video = $this->getVideo($v);
		if($video['ok'] === 200){
			$videoInfo = $this->getVideoInfo($video['body']);
			if($videoInfo['ok']){
				$config = $this->getYouTubeConfig($video['body']);
				if($config['ok']){
					$player = $this->getPlayerResponse($v, $config, $i);
					if($player['ok'] === 200){
						$res = $videoInfo;
						$res['medias'] = $this->getPlayerMedias($player['body']);
					}else{
						$res['status'] = $player['ok'];
						$res['message'] = 'Unknown player status code to continue.';
					}
				}else{
					$res['status'] = $video['ok'];
					$res['message'] = 'Video config not found.';	
				}
			}else{
				$res['status'] = $video['ok'];
				$res['message'] = 'Video info not found.';	
			}
		}else{
			$res['status'] = $video['ok'];
			$res['message'] = 'Unknown status code to continue.';
		}
		return $res;
	}
	
	public function download($p, $u, $mt = 50, $to = 50){
		$hs = [];
		list($o, $r, $s) = $this->durl($u);

		if(!$o || !$s){
			return ['ok' => false, 'message' => 'Invalid file size or URL'];
		}

		$c = ceil($s / $this->bufferSize);
		
		if($c > $mt){
			return ['ok' => false, 'message' => 'Max threads limit executed on buffer size: '.$this->bufferSize.' | Requere '.$c.' threads for file size:'.$s];
		}

		for($i = 0; $i < $c; $i++){
			$st = $i * $this->bufferSize;
			$et = min($st + $this->bufferSize - 1, $s - 1);
			$hs[] = [
				'range:bytes='.$st.'-'.$et,
				'user-agent:'.($this->userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36')
			];
		}
		
		$m = '';
		$ds = $this->murl($r, $hs, $to);
		foreach($ds as $i => $d){
			if(!empty($d)){
				$m .= $d;
			}else{
				return ['ok' => false, "message" => 'Failed to download chunk: '.$i];
			}
		}
		
		if(file_put_contents($p, $m)){
			return ['ok' => true, 'message' => 'Download complete!', 'output' => realpath($p)];
		}else{
			return ['ok' => false, "message" => 'Failed to save file'];
		}
	}
	
	public function stream($u){
		$c = curl_init();
		$h = array();
		$h[] = 'user-agent:'.($this->userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36');
		if(isset($_SERVER['HTTP_RANGE'])){
			$h[] = 'range:' . $_SERVER['HTTP_RANGE'];
		}
		curl_setopt($c, CURLOPT_FORBID_REUSE, 1);
		curl_setopt($c, CURLOPT_FRESH_CONNECT, 1);
		curl_setopt($c, CURLOPT_HTTPHEADER, $h);
		curl_setopt($c, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($c, CURLOPT_BUFFERSIZE, $this->bufferSize);
		curl_setopt($c, CURLOPT_URL, $u);
		curl_setopt($c, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
		curl_setopt($c, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
		curl_setopt($c, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($c, CURLOPT_MAXREDIRS, 5);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, 0);
		curl_setopt($c, CURLOPT_HEADER, 0);
		curl_setopt($c, CURLOPT_HEADERFUNCTION, [$this, 'headerCallback']);
		curl_setopt($c, CURLOPT_WRITEFUNCTION, [$this, 'bodyCallback']);
		$ret = curl_exec($c);
		$error = ($ret === false) ? sprintf('curl error: %s, num: %s', curl_error($c), curl_errno($c)) : null;
		curl_close($c);
		exit;
	}
}
