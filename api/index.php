<?php
/**
 * Fancode Live Events
 * Branding: NOWFLIXTV
 */

// --- DATA SOURCE & CACHE ---
$json_url = "https://raw.githubusercontent.com/drmlive/fancode-live-events/refs/heads/main/fancode.json";
$matches = [];

$res = @file_get_contents($json_url);
if ($res) {
    $data = json_decode($res, true);
    $matches = $data['matches'] ?? [];
}

// --- FILTERING LOGIC ---
$current_cat = $_GET['cat'] ?? 'all';
$categories = array_unique(array_column($matches, 'event_category'));
sort($categories);

$display_matches = ($current_cat === 'all') 
    ? $matches 
    : array_filter($matches, fn($m) => ($m['event_category'] ?? '') === $current_cat);

// --- STATISTICS ---
$total_count = count($matches);
$live_count = count(array_filter($matches, fn($m) => strtoupper($m['status'] ?? '') === 'LIVE'));
$upcoming_count = $total_count - $live_count;

$icons = [
    'Cricket' => 'fa-bat-ball', 'Football' => 'fa-futbol',
    'Tennis' => 'fa-table-tennis-paddle-ball', 'Golf' => 'fa-golf-ball-tee',
    'MotoGP' => 'fa-motorcycle', 'Formula 1' => 'fa-car-side'
];
?>

<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fancode Live - Sports Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { colors: { navy: '#1e3a8a' } } }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; transition: background 0.3s; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .live-dot { animation: pulse 1.5s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }
    </style>
</head>
<body class="bg-[#f8fafc] dark:bg-[#0f172a] text-slate-900 dark:text-slate-100 pb-20">

    <header class="bg-[#2d3291] text-white pt-10 pb-20 px-6 shadow-2xl rounded-b-[3.5rem] relative">
        <div class="container mx-auto text-center">
            <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight mb-4 uppercase">Fancode Live Events</h1>
            
            <div class="inline-flex items-center bg-black/30 backdrop-blur-xl px-8 py-2.5 rounded-full border border-white/10 text-[10px] md:text-[11px] font-bold uppercase tracking-widest gap-4">
                <span class="text-red-400 flex items-center"><i class="fas fa-tower-broadcast mr-2"></i> <?php echo $live_count; ?> LIVE</span>
                <span class="opacity-20">|</span>
                <span class="text-yellow-400 flex items-center"><i class="far fa-clock mr-2"></i> <?php echo $upcoming_count; ?> UPCOMING</span>
                <span class="opacity-20">|</span>
                <span class="text-blue-300 flex items-center"><i class="fas fa-list-ul mr-2"></i> <?php echo $total_count; ?> TOTAL</span>
            </div>

            <div id="header-clock" class="mt-6 text-[12px] font-bold opacity-80 tracking-widest bg-white/10 inline-block px-4 py-1.5 rounded-xl border border-white/5">
                LOADING TIME...
            </div>
        </div>

        <button onclick="toggleTheme()" class="absolute top-6 right-6 w-12 h-12 bg-white/10 rounded-full flex items-center justify-center border border-white/20 hover:bg-white/20 transition-all shadow-xl">
            <i id="theme-icon" class="fas fa-moon text-lg"></i>
        </button>
    </header>

    <main class="container mx-auto px-4 -mt-12">
        
        <div class="bg-white dark:bg-[#1e293b] rounded-[2rem] shadow-xl p-6 mb-10 border border-gray-100 dark:border-gray-800">
            <div class="flex items-center gap-2 mb-4 text-blue-600 font-black text-[11px] uppercase tracking-wider">
                <i class="fas fa-filter"></i> Filter by Category
            </div>
            <div class="flex gap-3 overflow-x-auto no-scrollbar pb-1">
                <a href="?cat=all" class="flex-none px-6 py-3 rounded-2xl text-[11px] font-black transition-all <?php echo $current_cat == 'all' ? 'bg-blue-600 text-white shadow-lg' : 'bg-gray-100 dark:bg-[#334155] text-gray-500 hover:bg-gray-200'; ?>">
                    <i class="fas fa-globe mr-2"></i> ALL EVENTS
                </a>
                <?php foreach($categories as $cat): if(empty($cat)) continue; ?>
                    <a href="?cat=<?php echo urlencode($cat); ?>" class="flex-none px-6 py-3 rounded-2xl text-[11px] font-black transition-all <?php echo $current_cat == $cat ? 'bg-blue-600 text-white shadow-lg' : 'bg-gray-100 dark:bg-[#334155] text-gray-500'; ?>">
                        <i class="fas <?php echo $icons[$cat] ?? 'fa-tag'; ?> mr-2"></i> <?php echo strtoupper($cat); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
            <?php foreach($display_matches as $match): 
                $status = strtoupper($match['status'] ?? 'UPCOMING');
                $is_live = ($status === 'LIVE');
                $watch_url = $match['adfree_url'] ?? $match['dai_url'] ?? '#';
                $category = $match['event_category'] ?? 'Sports';
            ?>
            <div class="bg-white dark:bg-[#1e293b] rounded-[2.5rem] overflow-hidden shadow-sm border border-gray-100 dark:border-gray-800 flex flex-col group transition-all hover:shadow-2xl hover:-translate-y-2">
                
                <div class="relative aspect-[16/10]">
                    <img src="<?php echo $match['src'] ?? ''; ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" loading="lazy">
                    <div class="absolute top-5 right-5">
                        <?php if($is_live): ?>
                            <span class="bg-red-600 text-white text-[9px] font-black px-3 py-1.5 rounded-xl shadow-xl flex items-center uppercase tracking-widest">
                                <span class="w-1.5 h-1.5 bg-white rounded-full mr-2 live-dot"></span> LIVE
                            </span>
                        <?php else: ?>
                            <span class="bg-yellow-500 text-white text-[9px] font-black px-3 py-1.5 rounded-xl shadow-xl uppercase tracking-widest">
                                <i class="far fa-clock mr-1.5"></i> UPCOMING
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="p-7 flex-grow">
                    <h3 class="font-extrabold text-slate-800 dark:text-white text-[15px] leading-snug mb-4 h-11 line-clamp-2">
                        <?php echo htmlspecialchars($match['title'] ?? 'Live Match'); ?>
                    </h3>
                    
                    <div class="space-y-3 pt-4 border-t border-slate-50 dark:border-slate-800">
                        <div class="flex items-center text-blue-600 dark:text-blue-400 text-[10px] font-black uppercase tracking-widest">
                            <i class="fas <?php echo $icons[$category] ?? 'fa-trophy'; ?> mr-2"></i>
                            <?php echo $category; ?>
                        </div>
                        <div class="flex items-center text-slate-400 dark:text-slate-500 text-[10px] font-bold">
                           <i class="far fa-calendar-check mr-2 text-purple-500"></i>
                           <?php echo $match['startTime'] ?? 'TBD'; ?>
                        </div>
                    </div>
                </div>

                <div class="px-7 pb-7 mt-auto">
                    <?php if($is_live): ?>
                        <a href="<?php echo htmlspecialchars($watch_url); ?>" target="_blank" class="block w-full text-center py-4 bg-blue-600 hover:bg-blue-700 text-white text-[11px] font-black uppercase tracking-widest rounded-[1.2rem] shadow-xl shadow-blue-200 dark:shadow-none transition-all scale-100 active:scale-95">
                            Watch Live
                        </a>
                    <?php else: ?>
                        <div class="w-full text-center py-4 bg-slate-50 dark:bg-slate-900/40 text-slate-400 text-[10px] font-black uppercase rounded-[1.2rem] border-2 border-dashed border-slate-200 dark:border-slate-800 tracking-widest">
                           Available Soon
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>

    <script>
        // Real-time Header Clock
        function updateClock() {
            const now = new Date();
            let hours = now.getHours();
            let minutes = now.getMinutes();
            let seconds = now.getSeconds();
            const ampm = hours >= 12 ? 'PM' : 'AM';
            
            hours = hours % 12 || 12;
            minutes = minutes < 10 ? '0' + minutes : minutes;
            seconds = seconds < 10 ? '0' + seconds : seconds;
            
            const day = String(now.getDate()).padStart(2, '0');
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const year = now.getFullYear();

            document.getElementById('header-clock').innerText = `${hours}:${minutes}:${seconds} ${ampm} ${day}-${month}-${year}`;
        }
        setInterval(updateClock, 1000); updateClock();

        // Theme Switcher
        function toggleTheme() {
            const isDark = document.documentElement.classList.toggle('dark');
            const icon = document.getElementById('theme-icon');
            icon.classList.replace(isDark ? 'fa-moon' : 'fa-sun', isDark ? 'fa-sun' : 'fa-moon');
            localStorage.setItem('fancode_theme', isDark ? 'dark' : 'light');
        }

        if (localStorage.getItem('fancode_theme') === 'dark') {
            document.documentElement.classList.add('dark');
            document.getElementById('theme-icon').classList.replace('fa-moon', 'fa-sun');
        }
    </script>
</body>
</html>
