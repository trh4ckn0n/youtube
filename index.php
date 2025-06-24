<?php
include 'src/YouTube.php';
$youtube = new YouTube();

$videoId = $info = $message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $url = $_POST['video_url'] ?? '';
    $client = $_POST['client'] ?? 'ios';
    $action = $_POST['action'] ?? '';
    $youtube->setUserAgent('com.google.android.apps.youtube.vr.oculus/1.60.19 (Linux; U; Android 12L; eureka-user Build/SQ3A.220605.009.A1) gzip');
    $youtube->setBufferSize(1024 * 512);

    $videoId = $youtube->extractVideoId($url);
    $info = $youtube->extractVideoInfo($videoId, $client);

    if ($action === 'download' && isset($info['url'])) {
        $youtube->download('video.mp4', $info['url']);
        exit;
    }

    if ($action === 'stream' && isset($info['url'])) {
        $youtube->stream($info['url']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>YouTube Downloader - trhacknon</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <h1>🎬 YouTube Tool by <span>trhacknon</span></h1>
        <form method="post">
            <input type="text" name="video_url" placeholder="Collez ici une URL YouTube..." required>
            <select name="client">
                <option value="ios">Client iOS (stable)</option>
                <option value="android">Client Android</option>
                <option value="android_vr">Client Android VR</option>
            </select>
            <div class="btns">
                <button type="submit" name="action" value="info">Afficher Info</button>
                <button type="submit" name="action" value="download">Télécharger</button>
                <button type="submit" name="action" value="stream">Streamer</button>
            </div>
        </form>

        <?php if ($info): ?>
        <div class="result">
            <h2>📄 Infos Vidéo</h2>
            <p><strong>ID:</strong> <?= htmlspecialchars($videoId) ?></p>
            <p><strong>Titre:</strong> <?= htmlspecialchars($info['title'] ?? 'N/A') ?></p>
            <p><strong>Auteur:</strong> <?= htmlspecialchars($info['author'] ?? 'N/A') ?></p>
            <p><strong>Durée:</strong> <?= htmlspecialchars($info['duration'] ?? 'N/A') ?> sec</p>
            <p><strong>Qualité:</strong> <?= htmlspecialchars($info['quality'] ?? 'N/A') ?></p>
            <?php if (isset($info['url'])): ?>
                <p><strong>URL direct:</strong> <a href="<?= $info['url'] ?>" target="_blank">Ouvrir</a></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
