<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPTV Pulse - Live Feeds</title>
    <style>
        :root {
            --bg-color: #0f172a;
            --card-bg: #1e293b;
            --text-main: #f8fafc;
            --accent: #3b82f6;
            --success: #10b981;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            margin: 0;
            padding: 20px;
        }

        .container { max-width: 1200px; margin: 0 auto; }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            padding: 20px 0;
        }

        .card {
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
            transition: transform 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            border: 1px solid transparent;
        }

        .card:hover {
            transform: translateY(-5px);
            border: 1px solid var(--accent);
        }

        .card img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            background: #000;
        }

        .card-content { padding: 15px; flex-grow: 1; }

        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            height: 2.8rem;
        }

        .btn-watch {
            display: block;
            width: 100%;
            text-align: center;
            background: var(--accent);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            font-size: 1rem;
        }

        /* MODAL STYLES */
        #modalOverlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }

        .modal {
            background: var(--card-bg);
            padding: 30px;
            border-radius: 16px;
            width: 90%;
            max-width: 480px;
            text-align: center;
            border: 1px solid #334155;
        }

        .loader {
            border: 4px solid #334155;
            border-top: 4px solid var(--accent);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }

        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        #resultContainer {
            margin-top: 20px;
            display: none;
        }

        #linkDisplay {
            background: #0f172a;
            padding: 15px;
            border-radius: 8px;
            word-break: break-all;
            font-family: monospace;
            font-size: 13px;
            color: var(--success);
            border: 1px solid var(--success);
            margin-bottom: 15px;
        }

        .copy-btn {
            background: var(--success);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
        }

        .close-btn {
            background: transparent;
            color: #94a3b8;
            border: none;
            margin-top: 15px;
            cursor: pointer;
            font-size: 14px;
        }

        .header { text-align: center; border-bottom: 1px solid #334155; padding-bottom: 20px; }
    </style>
</head>
<body>

<div class="container">
    <header class="header">
        <h1>IPTV Pulse Portal</h1>
    </header>

    <div class="grid">
        <?php
        $url = "https://www.iptvpulse.top/feeds/posts/default?alt=json&max-results=5000";
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36"
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);

        if ($data && isset($data['feed']['entry'])) {
            foreach ($data['feed']['entry'] as $entry) {
                $title = $entry['title']['$t'];
                $link = "";
                foreach ($entry['link'] as $l) {
                    if ($l['rel'] == 'alternate') { $link = $l['href']; break; }
                }
                $image = "https://via.placeholder.com/300x180?text=No+Image";
                if (isset($entry['content']['$t'])) {
                    preg_match('/<img.*?src="(.*?)"/', $entry['content']['$t'], $m);
                    if (isset($m[1])) $image = $m[1];
                }
                ?>
                <div class="card">
                    <img src="<?php echo $image; ?>" alt="Thumbnail">
                    <div class="card-content">
                        <div class="card-title"><?php echo $title; ?></div>
                        <button onclick="fetchLink('<?php echo $link; ?>')" class="btn-watch">Watch Now</button>
                    </div>
                </div>
                <?php
            }
        } else {
            echo "<p>No entries found.</p>";
        }
        ?>
    </div>
</div>

<div id="modalOverlay">
    <div class="modal">
        <div id="loadingState">
            <h3 id="modalTitle">Bypassing Security...</h3>
            <p style="color: #94a3b8;">Please wait about 20 seconds for the dynamic link to be generated.</p>
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
function fetchLink(idUrl) {
    // 1. Show the Modal
    document.getElementById('modalOverlay').style.display = 'flex';
    document.getElementById('loadingState').style.display = 'block';
    document.getElementById('resultContainer').style.display = 'none';
    
    // 2. Call your php file (Make sure it is named live.php or change it below)
    fetch('live.php?id=' + encodeURIComponent(idUrl))
        .then(response => response.text())
        .then(data => {
            // 3. Update the UI with the result
            document.getElementById('loadingState').style.display = 'none';
            document.getElementById('resultContainer').style.display = 'block';
            document.getElementById('linkDisplay').innerText = data.trim();
        })
        .catch(error => {
            alert("Error: Could not retrieve link.");
            closeModal();
        });
}

function closeModal() {
    document.getElementById('modalOverlay').style.display = 'none';
}

function copyLink() {
    const text = document.getElementById('linkDisplay').innerText;
    navigator.clipboard.writeText(text).then(() => {
        const btn = document.querySelector('.copy-btn');
        btn.innerText = "COPIED!";
        btn.style.background = "#059669";
        setTimeout(() => {
            btn.innerText = "Copy M3U8 Link";
            btn.style.background = "#10b981";
        }, 2000);
    });
}
</script>

</body>
</html>