<?php
/**
 * 文章调用音乐盒音乐 3.5
 */

// 阶段一
function parseMusicShortcode($content)
{
    if (empty($content)) {
        return '';
    }

    // 剥离 p 标签
    $replaced = preg_replace_callback(
        '/<p[^>]*>((?:(?!<p[^>]*>|<\/p>).)*?\[music\s+[^\]]*\](?:(?!<\/p>).)*?)<\/p>/is',
        function ($m) {
            return $m[1];
        },
        $content
    );
    // 防止 PCRE 回溯溢出
    if ($replaced !== null) {
        $content = $replaced;
    }

    $GLOBALS['_music_placeholders'] = [];

    $replaced = preg_replace_callback(
        '/\[music\s+([^\]]*)\]/i',
        function ($matches) {
            $index = count($GLOBALS['_music_placeholders']);
            $placeholder = "<!-- MUSIC_PLACEHOLDER_{$index} -->";
            $GLOBALS['_music_placeholders'][$index] = $matches[1] ?? '';
            return $placeholder;
        },
        $content
    );
    if ($replaced !== null) {
        $content = $replaced;
    }

    return $content;
}

// 阶段二
function replaceMusicPlaceholders($content)
{
    if (empty($GLOBALS['_music_placeholders'])) {
        return $content;
    }

    static $musicIndex = 0;

    foreach ($GLOBALS['_music_placeholders'] as $index => $attrs) {
        $musicIndex++;
        $placeholder = "<!-- MUSIC_PLACEHOLDER_{$index} -->";
        $playerHtml = buildMusicPlayerHtml($attrs, $musicIndex);
        $content = str_replace($placeholder, $playerHtml, $content);
    }

    unset($GLOBALS['_music_placeholders']);
    return $content;
}

// 构建播放器 HTML
function buildMusicPlayerHtml($attrs, $musicIndex)
{
    $musicName = '';
    $musicMp3 = '';
    $customCover = '';
    $customLrc = '';
    $listName = '';

    if (preg_match('/(?:名称|name)="([^"]*)"/i', $attrs, $m)) {
        $musicName = trim($m[1]);
    }
    if (preg_match('/mp3="([^"]*)"/i', $attrs, $m)) {
        $musicMp3 = trim($m[1]);
    }
    if (preg_match('/pic="([^"]*)"/i', $attrs, $m)) {
        $customCover = trim($m[1]);
    }
    if (preg_match('/lrc="([^"]*)"/i', $attrs, $m)) {
        $customLrc = trim($m[1]);
    }
    if (preg_match('/列表="([^"]*)"/i', $attrs, $m)) {
        $listName = trim($m[1]);
    }

    $isPlaylist = !empty($listName);
    $playlist = [];
    $music = [];

    if ($isPlaylist) {
        $names = explode('|', $listName);
        foreach ($names as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }

            $song = getSingleMusicForShortcode($name);
            if (!empty($song['url'])) {
                $playlist[] = [
                    'name'   => $name,
                    'url'    => $song['url'],
                    'cover'  => $song['cover'] ?? '',
                    'lyrics' => parseLrcLyrics($song['lyrics'] ?? '')
                ];
            }
        }
    } else {
        if (!empty($musicMp3)) {
            $music = getMusicByMp3($musicMp3);
        } else {
            $music = getSingleMusicForShortcode($musicName);
        }
    }

    $realMusicName = '';
    if (!$isPlaylist) {
        $realMusicName = !empty($musicName) ? $musicName : ($music['name'] ?? '未知歌曲');
    }

    $lyricsStr = '';
    $musicUrl = '';
    $musicCover = '';
    $defaultCover = '/usr/themes/dage/bai/img/tx.png';

    if (!$isPlaylist) {
        $lyricsStr = !empty($customLrc) ? (string) @file_get_contents($customLrc) : ($music['lyrics'] ?? '');
        $musicUrl = !empty($musicMp3) ? $musicMp3 : ($music['url'] ?? '');
        $musicCover = !empty($customCover) ? $customCover : (!empty($music['cover']) ? $music['cover'] : $defaultCover);
    } else {
        $musicUrl = $playlist[0]['url'] ?? '';
        $musicCover = !empty($playlist[0]['cover']) ? $playlist[0]['cover'] : $defaultCover;
    }

    $lyrics = !$isPlaylist ? parseLrcLyrics($lyricsStr) : ($playlist[0]['lyrics'] ?? []);
    $initLyricText = htmlspecialchars($isPlaylist ? ($playlist[0]['name'] ?? '未知歌曲') : $realMusicName, ENT_QUOTES, 'UTF-8');

    if (empty($musicUrl) && !$isPlaylist) {
        $tip = !empty($musicMp3) ? "歌曲路径无效：{$musicMp3}" : "未找到歌曲：{$musicName}";
        return "<div style='color:red;padding:10px;margin:10px 0;background:#fdd;border-radius:5px;'>{$tip}</div>";
    }

    if ($isPlaylist && empty($playlist)) {
        return "<div style='color:red;padding:10px;margin:10px 0;background:#fdd;border-radius:5px;'>歌曲列表格式错误</div>";
    }

    // 音乐盒密码保护：全局弹窗与校验脚本（整页仅输出一次）
    ob_start();
    pipi_music_password_assets();
    $musicPasswordGlobalHtml = ob_get_clean();

    $playIconId = "play-icon-{$musicIndex}";
    $volumeIconId = "volume-icon-{$musicIndex}";
    $playerId = "music-player-{$musicIndex}";
    $lyricsId = "music-lyrics-{$musicIndex}";
    $basketballId = "basketball-icon-{$musicIndex}";
    $listId = "music-list-{$musicIndex}";

    $svgPlay = '<img src="/usr/themes/dage/bai/img/bofangqi/call-play.svg" style="width:20px;height:20px;">';
    $svgPause = '<img src="/usr/themes/dage/bai/img/bofangqi/call-pause.svg" style="width:20px;height:20px;">';
    $svgVolume = '<img src="/usr/themes/dage/bai/img/bofangqi/volume.svg" style="width:16px;height:16px;">';
    $svgVolumeMute = '<img src="/usr/themes/dage/bai/img/bofangqi/mute.svg" style="width:16px;height:16px;">';

    $lyricsJson = json_encode($lyrics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $songNameJson = json_encode($initLyricText, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $playlistJson = $isPlaylist
        ? json_encode($playlist, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : '[]';

    $svgPlayJson = json_encode($svgPlay, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $svgPauseJson = json_encode($svgPause, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $svgVolumeJson = json_encode($svgVolume, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $svgVolumeMuteJson = json_encode($svgVolumeMute, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $defaultCoverJson = json_encode($defaultCover, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $listHtml = '';
    if ($isPlaylist) {
        $listHtml = <<<LIST
<div id="{$listId}" class="music-play-list" style="margin-top:-1px; padding:6px 0; background:var(--bj-zhuse);border-radius:0 0 8px 8px; border:1px solid var(--bj-fuse); border-top:none;max-height:220px; overflow-y:auto; overflow-x:hidden; scrollbar-width:none; -ms-overflow-style:none; box-sizing:border-box;">
LIST;
        foreach ($playlist as $key => $song) {
            $num = $key + 1;
            $active = $key === 0 ? 'music-list-item-active' : '';
            $songTitle = htmlspecialchars($song['name'], ENT_QUOTES, 'UTF-8');

            $listHtml .= <<<SONG
<div class="music-list-item {$active}" data-index="{$key}" style="padding:6px 10px; margin:0; border-radius:0;cursor:pointer; font-size:13px; color:var(--color-huise);transition:all 0.2s; display:flex; align-items:center; justify-content:space-between;gap:8px;">
    <div style="display:flex; align-items:center; gap:6px; flex:1; min-width:0;">
        <span style="flex-shrink:0; opacity:0.6;">{$num}.</span>
        <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{$songTitle}</span>
    </div>
    <span style="flex-shrink:0; font-size:12px; opacity:0.7;" class="list-duration">--:--</span>
</div>
SONG;
        }
        $listHtml .= '</div>';
    }

    $radius = $isPlaylist ? '8px 8px 0 0' : '8px';

    $playerHtml = <<<HTML
{$musicPasswordGlobalHtml}
<div style="margin:15px 0 8px; width:100%; box-sizing:border-box; clear:both;">
    <div class="baige-custom-music-player" data-music-index="{$musicIndex}" style="padding:0; background:var(--bj-zhuse); border-radius:{$radius};box-shadow:0 2px 8px rgba(0,0,0,0.06), inset 0 0 0 1px var(--bj-fuse);position:relative; overflow:hidden; height:60px; box-sizing:border-box;display:flex; align-items:center; width:100%; max-width:100%;">
        <audio id="{$playerId}" class="single-music-player" src="{$musicUrl}" preload="metadata" style="display:none;"></audio>
        
        <div class="music-cover-wrap" data-index="{$musicIndex}" style="width:60px; height:60px; border-radius:8px 0 0 0;background:url({$musicCover}) center center / cover no-repeat;flex-shrink:0; position:relative; border:none; margin:0; cursor:pointer;">
        </div>

    <div class="music-play-btn" data-index="{$musicIndex}" style="position:absolute; left:36px; top:36px; width:24px; height:24px; background:none; border:none; display:flex; align-items:center; justify-content:center; cursor:pointer; z-index:10;">
    <div id="{$playIconId}" style="width:20px; height:20px; display:flex; align-items:center; justify-content:center;">{$svgPlay}</div>
</div>

        <div class="music-info-area" style="flex:1; height:100%; display:flex; flex-direction:column; justify-content:center; padding:0 12px; box-sizing:border-box;">
            <div style="width:100%; height:18px; overflow:hidden; position:relative; margin-bottom:6px;">
                <div id="{$lyricsId}" style="width:100%; transition:transform 0.3s ease; text-align:center;">
                    <div style="height:18px; line-height:18px; font-size:12px; color:var(--color-huise);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{$initLyricText}</div>
                </div>
            </div>

<div class="music-progress-row" style="display:flex; align-items:center; gap:2px; width:100%; box-sizing:border-box; position:relative;">
    <div class="music-time-wrap" style="font-size:10px; color:var(--color-huise); min-width:22px; flex-shrink:1; text-align:center; line-height:1;">
        <span class="music-current-time" data-index="{$musicIndex}">00:00</span>
    </div>

    <div style="flex:1; position:relative; height:3px; padding:0 3px; box-sizing:border-box; min-width:20px;">
        <div style="height:100%; background:var(--bj-cifuse); border-radius:2px; cursor:pointer; width:100%;" class="music-progress-bar" data-index="{$musicIndex}">
            <div class="music-progress" style="width:0%; height:100%; background:var(--bj-fuse); border-radius:2px; transition:width 0.1s linear;"></div>
        </div>
        <span id="{$basketballId}" class="music-kun-thumb"><img src="/usr/themes/dage/bai/img/bofangqi/kun.gif" alt="坤"></span>
    </div>

    <div class="music-time-wrap" style="font-size:10px; color:var(--color-huise); min-width:22px; flex-shrink:1; text-align:center; line-height:1;">
        <span class="music-duration" data-index="{$musicIndex}">00:00</span>
    </div>

    <div class="music-volume-area" style="display:flex; align-items:center; gap:4px; flex:0 0 auto; min-width:50px; justify-content:flex-end;">
        <div id="{$volumeIconId}" style="color:var(--color-huise); cursor:pointer; flex-shrink:0; display:flex; align-items:center;">{$svgVolume}</div>
        <div class="music-volume-bar-wrap" style="width:36px; height:3px; background:var(--bj-cifuse); border-radius:2px; cursor:pointer; flex-shrink:0;">
            <div class="music-volume-bar" data-index="{$musicIndex}" style="width:80%; height:100%; background:var(--bj-fuse); border-radius:2px; transition:width 0.1s linear;"></div>
        </div>
    </div>
</div>
        </div>
    </div>
    {$listHtml}
<script>
(function () {
    const player = document.getElementById('{$playerId}');
    const playBtn = document.querySelector('.music-play-btn[data-index="{$musicIndex}"]');
    const playIcon = document.getElementById('{$playIconId}');
    const progressBar = document.querySelector('.music-progress-bar[data-index="{$musicIndex}"]');
    const progress = progressBar ? progressBar.querySelector('.music-progress') : null;
    const currentTimeEl = document.querySelector('.music-current-time[data-index="{$musicIndex}"]');
    const durationEl = document.querySelector('.music-duration[data-index="{$musicIndex}"]');
    const volumeBar = document.querySelector('.music-volume-bar[data-index="{$musicIndex}"]');
    const volumeIcon = document.getElementById('{$volumeIconId}');
    const lyricsEl = document.getElementById('{$lyricsId}');
    const basketball = document.getElementById('{$basketballId}');
    const listEl = document.getElementById('{$listId}');
    const coverWrap = document.querySelector('.music-cover-wrap[data-index="{$musicIndex}"]');

    const songName = {$songNameJson};
    const lyricsData = {$lyricsJson};
    const playlist = {$playlistJson};
    const isPlaylist = playlist.length > 0;

    const svgPlay = {$svgPlayJson};
    const svgPause = {$svgPauseJson};
    const svgVolume = {$svgVolumeJson};
    const svgVolumeMute = {$svgVolumeMuteJson};
    const defaultCover = {$defaultCoverJson};

    let currentLine = 0;
    let isMetadataLoaded = false;
    let currentSongIndex = 0;
    const durationCache = {};

    const formatTime = (s) => isNaN(s) || s < 0 || !isFinite(s)
        ? "00:00"
        : Math.floor(s / 60).toString().padStart(2, "0") + ":" + Math.floor(s % 60).toString().padStart(2, "0");

    let html = "<div style='height:18px;line-height:18px;font-size:12px;color:var(--color-huise);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;'>" + songName + "</div>";
    if (lyricsData && lyricsData.length) {
        lyricsData.forEach(item => {
            html += "<div style='height:18px;line-height:18px;font-size:12px;color:var(--color-huise);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;'>" + item.text + "</div>";
        });
    }
    lyricsEl.innerHTML = html;

    const setDuration = () => {
        if (isMetadataLoaded) {
            return;
        }
        if (player.duration && player.duration > 0) {
            durationEl.textContent = formatTime(player.duration);
            isMetadataLoaded = true;
        }
    };

    function switchSong(index) {
        if (!playlist[index]) {
            return;
        }
        window.checkMusicBoxPassword(function () {
            currentSongIndex = index;
            const song = playlist[index];

            player.src = song.url;
            isMetadataLoaded = false;

            if(coverWrap) coverWrap.style.backgroundImage = "url('" + (song.cover || defaultCover) + "')";

            const lrcData = song.lyrics || [];
            let lrcHtml = "<div style='height:18px;line-height:18px;font-size:12px;color:var(--color-huise);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;'>" + song.name + "</div>";
            lrcData.forEach(item => {
                lrcHtml += "<div style='height:18px;line-height:18px;font-size:12px;color:var(--color-huise);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;'>" + item.text + "</div>";
            });
            lyricsEl.innerHTML = lrcHtml;
            currentLine = 0;
            lyricsEl.style.transform = "translateY(0)";

            if (progress) progress.style.width = "0%";
            if (basketball) basketball.style.left = "8px";
            currentTimeEl.textContent = "00:00";
            durationEl.textContent = "00:00";

            document.querySelectorAll("#{$listId} .music-list-item").forEach((item, i) => {
                item.style.cssText = "padding:6px 10px; margin:0; border-radius:0;cursor:pointer; font-size:13px; color:var(--color-huise);transition:all 0.2s; display:flex; align-items:center; justify-content:space-between; gap:8px;";
                if (i === index) {
                    item.classList.add('music-list-item-active');
                } else {
                    item.classList.remove('music-list-item-active');
                }
            });

            player.load();
            player.play();
            playIcon.innerHTML = svgPause;
        });
    }

    if (isPlaylist && listEl) {
        document.querySelectorAll("#{$listId} .music-list-item").forEach((item, index) => {
            item.addEventListener("click", () => {
                pauseAllOthers();
                switchSong(index);
            });
        });
    }

    player.addEventListener("ended", () => {
        if (isPlaylist && currentSongIndex < playlist.length - 1) {
            switchSong(currentSongIndex + 1);
        } else {
            playIcon.innerHTML = svgPlay;
            if (progress) progress.style.width = "0%";
            currentTimeEl.textContent = "00:00";
            if (basketball) basketball.style.left = "8px";
            lyricsEl.style.transform = "translateY(0)";
            currentLine = 0;
        }
    });

    player.addEventListener("loadedmetadata", setDuration);
    player.addEventListener("canplay", setDuration);
    player.addEventListener("loadeddata", setDuration);
    // 页面打开时主动加载音频元数据
    try {
        player.preload = "metadata";
        player.load();
    } catch (e) {}
    if (player.readyState >= 1) {
        setDuration();
    }
    setTimeout(setDuration, 800);
    setTimeout(setDuration, 1800);
    setTimeout(setDuration, 3500);

    player.volume = 0.8;

    const pauseAllOthers = () => {
        document.querySelectorAll(".single-music-player").forEach(audio => {
            if (audio !== player && !audio.paused) {
                audio.pause();
                const wrapper = audio.closest(".baige-custom-music-player");
                if (!wrapper) {
                    return;
                }
                const idx = wrapper.dataset.musicIndex;
                const icon = document.getElementById("play-icon-" + idx);
                if (icon) {
                    icon.innerHTML = svgPlay;
                }
            }
        });
    };

    const togglePlay = () => {
        window.checkMusicBoxPassword(function () {
            if (player.paused) {
                pauseAllOthers();
                player.play();
                playIcon.innerHTML = svgPause;
            } else {
                player.pause();
                playIcon.innerHTML = svgPlay;
            }
        });
    };

    playBtn.addEventListener("click", togglePlay);
    // 点击封面任意位置也能播放
    if (coverWrap) {
        coverWrap.addEventListener("click", togglePlay);
    }

    player.addEventListener("timeupdate", () => {
        if (!isMetadataLoaded) {
            setDuration();
        }

        const pct = (player.currentTime / (player.duration || 1)) * 100;
        if (progress) progress.style.width = Math.min(100, pct) + "%";
        currentTimeEl.textContent = formatTime(player.currentTime);
        if (basketball && progressBar) basketball.style.left = (8 + (progressBar.offsetWidth * pct / 100)) + "px";

        let activeLine = 0;
        const currentLyrics = isPlaylist ? playlist[currentSongIndex].lyrics : lyricsData;
        if (currentLyrics && currentLyrics.length) {
            for (let i = 0; i < currentLyrics.length; i++) {
                if (player.currentTime >= currentLyrics[i].time - 0.3) {
                    activeLine = i + 1;
                }
            }
        }

        if (activeLine !== currentLine) {
            currentLine = activeLine;
            lyricsEl.style.transform = "translateY(-" + (currentLine * 18) + "px)";
        }
        
        
    });

    if (progressBar) {
        progressBar.addEventListener("click", (e) => {
            if (!player.duration) {
                return;
            }
            player.currentTime = (e.offsetX / progressBar.offsetWidth) * player.duration;
        });
    }

    const updateVolume = (v) => {
        player.volume = Math.max(0, Math.min(1, v));
        if (volumeBar) volumeBar.style.width = (player.volume * 100) + "%";
        volumeIcon.innerHTML = player.volume <= 0 ? svgVolumeMute : svgVolume;
    };

    if (volumeBar && volumeBar.parentElement) {
        volumeBar.parentElement.addEventListener("click", (e) => {
            updateVolume(e.offsetX / volumeBar.parentElement.offsetWidth);
        });
    }

    volumeIcon.addEventListener("click", () => {
        updateVolume(player.volume > 0 ? 0 : 0.8);
    });

if (isPlaylist && playlist.length) {
    let loaded = false;
    const loadAll = () => {
        if(loaded) return; loaded = true;
        playlist.forEach((song, idx) => {
            const audio = new Audio();
            audio.preload = "metadata";
            let resolved = false;
            
            const updateDuration = (dur) => {
                if (resolved) return;
                const d = (dur && isFinite(dur) && dur > 0) ? dur : null;
                if (d !== null) {
                    resolved = true;
                    durationCache[idx] = d;
                    const item = document.querySelector("#{$listId} .music-list-item[data-index='"+idx+"'] .list-duration");
                    if(item) item.textContent = formatTime(d);
                }
            };
            
            audio.onloadedmetadata = () => updateDuration(audio.duration);
            audio.ondurationchange = () => updateDuration(audio.duration);
            audio.oncanplay = () => updateDuration(audio.duration);
            audio.onerror = () => {
                if (!resolved) {
                    resolved = true;
                    const item = document.querySelector("#{$listId} .music-list-item[data-index='"+idx+"'] .list-duration");
                    if(item) item.textContent = "--:--";
                }
            };
            
            audio.src = song.url;
            try { audio.load(); } catch(e) {}
            
            // 兜底
            setTimeout(() => {
                if (!resolved) {
                    resolved = true;
                    const item = document.querySelector("#{$listId} .music-list-item[data-index='"+idx+"'] .list-duration");
                    if(item) item.textContent = "--:--";
                }
            }, 1500);
        });
    };

    loadAll();
    
    document.addEventListener('touchstart', loadAll, { once: true });
    document.addEventListener('click', loadAll, { once: true });

    setTimeout(loadAll, 3000);
}
})();
</script>
</div>
HTML;

    return $playerHtml;
}

function is_absolute_path($path)
{
    if (empty($path)) {
        return false;
    }

    return str_starts_with($path, '/') || (bool) preg_match('/^[A-Za-z]:\\\\/', $path);
}

function getMusicUploadRoots(): array
{
    $base = rtrim(__TYPECHO_ROOT_DIR__, '/') . '/usr/uploads/';

    return [
        'music'  => $base . 'music_files/',
        'lyrics' => $base . 'music_lyrics/',
        'images' => $base . 'music_images/',
    ];
}

function getMonthSubDirs(string $rootDir): array
{
    if (!is_dir($rootDir)) {
        return [];
    }

    $dirs = [];
    $items = scandir($rootDir);
    if ($items === false) {
        return [];
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        if (!preg_match('/^\d{6}$/', $item)) {
            continue;
        }

        $dir = rtrim($rootDir, '/') . '/' . $item . '/';
        if (is_dir($dir)) {
            $dirs[] = $dir;
        }
    }

    rsort($dirs, SORT_STRING);
    return $dirs;
}

function findMusicCoverForShortcode(string $fileName): string
{
    if ($fileName === '') {
        return '';
    }

    $roots = getMusicUploadRoots();
    $imageDirs = getMonthSubDirs($roots['images']);
    $allowedImageExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    foreach ($imageDirs as $dir) {
        foreach ($allowedImageExt as $ext) {
            $imagePath = $dir . $fileName . '.' . $ext;
            if (is_file($imagePath)) {
                return str_replace(__TYPECHO_ROOT_DIR__, '', $imagePath);
            }
        }
    }

    return '';
}

function findMusicLyricsContent(string $fileName): string
{
    if ($fileName === '') {
        return '';
    }

    $roots = getMusicUploadRoots();
    $lyricDirs = getMonthSubDirs($roots['lyrics']);

    foreach ($lyricDirs as $dir) {
        $lrcPath = $dir . $fileName . '.lrc';
        if (is_file($lrcPath)) {
            $lyrics = trim((string) file_get_contents($lrcPath));
            $cleaned = preg_replace('/^\xEF\xBB\xBF/', '', $lyrics);
            return is_string($cleaned) ? $cleaned : $lyrics;
        }
    }

    return '';
}

function getSingleMusicForShortcode($name = '')
{
    $roots = getMusicUploadRoots();
    $musicDir = $roots['music'];
    $allowedExt = ['mp3'];

    $music = [];
    $searchName = trim((string) $name);

    if ($searchName === '' || !is_dir($musicDir)) {
        return $music;
    }

    clearstatcache();

    foreach (getMonthSubDirs($musicDir) as $dir) {
        $dh = opendir($dir);
        if ($dh === false) {
            continue;
        }

        while (($file = readdir($dh)) !== false) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt, true)) {
                continue;
            }

            $fname = pathinfo($file, PATHINFO_FILENAME);
            if (stripos($fname, $searchName) === false) {
                continue;
            }

            $lyrics = findMusicLyricsContent($fname);
            $cover = findMusicCoverForShortcode($fname);

            $siteUrl = rtrim(Helper::options()->siteUrl, '/');
            $url = $siteUrl . str_replace(__TYPECHO_ROOT_DIR__, '', $dir . $file);

            closedir($dh);

            return [
                'name'   => $fname,
                'url'    => $url,
                'cover'  => $cover,
                'lyrics' => $lyrics,
            ];
        }

        closedir($dh);
    }

    return $music;
}

function getMusicByMp3($path)
{
    $music = [];
    $path = trim((string) $path);

    if ($path === '') {
        return $music;
    }

    $allowedExt = ['mp3'];
    $siteUrl = rtrim(Helper::options()->siteUrl, '/');
    $rootDir = __TYPECHO_ROOT_DIR__;

    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return [
            'name'   => '',
            'url'    => $path,
            'cover'  => '',
            'lyrics' => '',
        ];
    }

    $absPath = !is_absolute_path($path) ? $rootDir . '/' . ltrim($path, '/') : $path;

    if (!file_exists($absPath)) {
        $resolved = realpath($absPath);
        if ($resolved !== false) {
            $absPath = $resolved;
        }
    }

    if (!file_exists($absPath)) {
        return $music;
    }

    $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return $music;
    }

    $fname = pathinfo($absPath, PATHINFO_FILENAME);

    $cover = findMusicCoverForShortcode($fname);
    $lyrics = findMusicLyricsContent($fname);
    $url = $siteUrl . str_replace($rootDir, '', $absPath);

    return [
        'name'   => $fname,
        'url'    => $url,
        'cover'  => $cover,
        'lyrics' => $lyrics,
    ];
}

function parseLrcLyrics($lrcContent)
{
    $lyrics = [];
    if (empty($lrcContent)) {
        return $lyrics;
    }

    $lines = preg_split('/\r\n|\r|\n/', (string) $lrcContent);
    foreach ($lines as $line) {
        if (preg_match('/\[(\d+):(\d+(?:\.\d+)?)\](.*)/', trim($line), $m)) {
            $text = trim($m[3]);
            if ($text !== '') {
                $lyrics[] = [
                    'time' => (int) $m[1] * 60 + (float) $m[2],
                    'text' => $text
                ];
            }
        }
    }

    usort($lyrics, fn($a, $b) => $a['time'] <=> $b['time']);
    return $lyrics;
}

function registerMusicShortcode()
{
    // 不再注册到 contentEx
    // 改为在 post.php 中手动调用
}
