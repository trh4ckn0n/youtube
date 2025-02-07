## Youtube PHP Library

This project was inspired by a very popular youtube-dl python package:  
https://github.com/ytdl-org/youtube-dl

Yes, there are multiple other PHP-based youtube downloaders on the Internet, 
but most of them haven't been updated in years, or they depend on youtube-dl itself.  

Pure PHP-based youtube downloaders that work, and are **kept-up-to date** just do not exist.
This script uses no Javascript interpreters, no calls to shell... nothing but pure PHP with no heavy dependencies either.

## :warning: Legal Disclaimer

This program is for personal use only. 
Downloading copyrighted material without permission is against [YouTube's terms of services](https://www.youtube.com/static?template=terms). 
By using this program, you are solely responsible for any copyright violations. 
We are not responsible for people who attempt to use this program in any way that breaks YouTube's terms of services.

### Installation
```php
include 'src/YouTube.php';
$youtube = new YouTube();
```

### Usage

#### Set User Agent
```php
$youtube->setUserAgent('com.google.android.apps.youtube.vr.oculus/1.60.19 (Linux; U; Android 12L; eureka-user Build/SQ3A.220605.009.A1) gzip');
```
Default: Server User-Agent

#### Set Buffer Size
```php
$youtube->setBufferSize(1024*512);
```
Default: `1024*256` (256KB)

#### Extract Video ID
```php
$id = $youtube->extractVideoId('https://youtube.com/watch?v=XzaMwgglY');
```

#### Extract Video Info
```php
$info = $youtube->extractVideoInfo($id);
//or user android client.
$info = $youtube->extractVideoInfo($id, 'android');
```
Default Client: `ios`. More clients: `ios`, `android` (broken or link expires in 30s), `android_vr`

#### Download Video
```php
$download = $youtube->download('song.mp3', 'https://r4---sn-n4v7knll.googlevideo.com/videoplayback?...');
// Or
$youtube->download(file_path, url, max_threads, timeout);
```

#### Stream Video
```php
$youtube->stream('https://r4---sn-n4v7knll.googlevideo.com/videoplayback?...');
```
