<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPTV Pulse - Live Feeds</title>
    <style>
        :root { --bg-color: #0f172a; --card-bg: #1e293b; --text-main: #f8fafc; --accent: #3b82f6; --success: #10b981; }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--bg-color); color: var(--text-main); margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; padding: 20px 0; }
        .card { background: var(--card-bg); border-radius: 12px; overflow: hidden; transition: transform 0.3s ease; border: 1px solid transparent; }
        .card:hover { transform: translateY(-5px); border: 1px solid var(--accent); }
        .card img { width: 100%; height: 180px; object-fit: cover; background: #000; }
        .card-content { padding: 15px; }
        .card-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 15px; height: 2.8rem; overflow: hidden; }
        .btn-watch { width: 100%; background: var(--accent); color: white; border: none; padding: 12px; border-radius: 6px; font-weight: bold; cursor: pointer; }
        #modalOverlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
        .modal { background: var(--card-bg); padding: 30px; border-radius: 16px; width: 90%; max-width: 480px; text-align: center; border: 1px solid #334155; }
        .loader { border: 4px solid #334155; border-top: 4px solid var(--accent); border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 20px auto; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        #resultContainer { margin-top: 20px; display: none; }
        #linkDisplay { background: #0f172a; padding: 15px; border-radius: 8px; word-break: break-all; font-family: monospace; color: var(--success); border: 1px solid var(--success); margin-bottom: 15px; }
        .copy-btn { background: var(--success); color: white; border: none; padding: 12px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; width: 100%; }
        .close-btn { background: transparent; color: #94a3b8; border: none; margin-top: 15px; cursor: pointer; }
    </style>
</head>
<body>

<div class="container">
    <header style="text-align:center; padding-bottom:20px; border-bottom:1px solid #334155;">
        <h1>IPTV Pulse Portal</h1>
    </header>

    <div class="grid">
        <?php
        $url = "https://www.iptvpulse.top/feeds/posts/default?alt=json&max-results=50";
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);

        if ($data && isset($data['feed']['entry'])) {
            foreach ($data['feed']['entry'] as $entry) {
                $title = $entry['title']['$t'];
                $link = "";
                foreach ($entry['link'] as $l) { if ($l['rel'] == 'alternate') { $link = $l['href']; break; } }
                $image = "https://via.placeholder.com/300x180?text=No+Image";
                if (isset($entry['content']['$t'])) {
                    preg_match('/<img.*?src="(.*?)"/', $entry['content']['$t'], $m);
                    if (isset($m[1])) $image = $m[1];
                }
                echo '<div class="card">
                        <img src="'.$image.'" alt="Thumbnail">
                        <div class="card-content">
                            <div class="card-title">'.$title.'</div>
                            <button onclick="fetchLink(\''.$link.'\')" class="btn-watch">Watch Now</button>
                        </div>
                      </div>';
            }
        }
        ?>
    </div>
</div>

<div id="modalOverlay">
    <div class="modal">
        <div id="loadingState">
            <h3>Generating Link...</h3>
            <p id="timerText" style="color: #94a3b8;">Waiting 20 seconds for security bypass...</p>
            <div class="loader"></div>
        </div>
        <div id="resultContainer">
            <h3>Link Generated!</h3>
            <div id="linkDisplay"></div>
            <button class="copy-btn" onclick="copyLink()">Copy M3U8 Link</button>
        </div>
        <button class="close-btn" onclick="closeModal()">Close Window</button>
    </div>
</div>

<script>
let countdown;
function fetchLink(idUrl) {
    document.getElementById('modalOverlay').style.display = 'flex';
    document.getElementById('loadingState').style.display = 'block';
    document.getElementById('resultContainer').style.display = 'none';
    
    let seconds = 20;
    const timerText = document.getElementById('timerText');
    
    countdown = setInterval(() => {
        seconds--;
        timerText.innerText = `Waiting ${seconds} seconds for security bypass...`;
        if (seconds <= 0) {
            clearInterval(countdown);
            timerText.innerText = "Fetching final link...";
            
            // Now call the PHP after the 20s wait is done on the frontend
            fetch('/live.php?id=' + encodeURIComponent(idUrl))
                .then(r => r.text())
                .then(data => {
                    document.getElementById('loadingState').style.display = 'none';
                    document.getElementById('resultContainer').style.display = 'block';
                    document.getElementById('linkDisplay').innerText = data.trim();
                })
                .catch(() => { alert("Error retrieving link."); closeModal(); });
        }
    }, 1000);
}

function closeModal() { clearInterval(countdown); document.getElementById('modalOverlay').style.display = 'none'; }
function copyLink() {
    const text = document.getElementById('linkDisplay').innerText;
    navigator.clipboard.writeText(text).then(() => {
        document.querySelector('.copy-btn').innerText = "COPIED!";
        setTimeout(() => document.querySelector('.copy-btn').innerText = "Copy M3U8 Link", 2000);
    });
}
</script>
</body>
</html>
