<?php
/**
 * Feed 订阅页面模板
 *
 * @package custom
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$cacheFile = dirname(__FILE__) . '/cache/feed_cache_v4.json';
$refreshLimitFile = dirname(__FILE__) . '/cache/feed_refresh_limits.json';
$maxRefreshPerDay = isset($this->options->feed_refresh_limit) ? intval($this->options->feed_refresh_limit) : 2;

function baigeFeedClientIp()
{
    $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = $_SERVER[$h];
            if (strpos($ip, ',') !== false) {
                $ips = explode(',', $ip);
                $ip = trim($ips[0]);
            }
            return $ip;
        }
    }
    return '';
}

function baigeFeedRefreshLimit($ip, $limitFile, $max)
{
    $today = date('Y-m-d');
    $lockFile = $limitFile . '.lock';
    $fp = @fopen($lockFile, 'c');
    if ($fp) {
        @flock($fp, LOCK_SH);
    }
    $data = [];
    if (file_exists($limitFile)) {
        $data = @json_decode(file_get_contents($limitFile), true) ?: [];
    }
    if ($fp) {
        @flock($fp, LOCK_UN);
        @fclose($fp);
    }
    foreach ($data as $k => $v) {
        if (($v['date'] ?? '') !== $today) {
            unset($data[$k]);
        }
    }
    $count = isset($data[$ip]['count']) ? intval($data[$ip]['count']) : 0;
    // 0 表示不限制
    if ($max <= 0) {
        return ['can' => true, 'count' => $count, 'left' => -1];
    }
    return [
        'can' => $count < $max,
        'count' => $count,
        'left' => max(0, $max - $count),
    ];
}

function baigeFeedRecordRefresh($ip, $limitFile)
{
    $today = date('Y-m-d');
    $lockFile = $limitFile . '.lock';
    $fp = @fopen($lockFile, 'c');
    if ($fp) {
        @flock($fp, LOCK_EX);
    }
    $data = [];
    if (file_exists($limitFile)) {
        $data = @json_decode(file_get_contents($limitFile), true) ?: [];
    }
    foreach ($data as $k => $v) {
        if (($v['date'] ?? '') !== $today) {
            unset($data[$k]);
        }
    }
    if (!isset($data[$ip])) {
        $data[$ip] = ['date' => $today, 'count' => 0];
    }
    $data[$ip]['count']++;
    @file_put_contents($limitFile, json_encode($data, JSON_PRETTY_PRINT));
    if ($fp) {
        @flock($fp, LOCK_UN);
        @fclose($fp);
    }
}

// AJAX 无刷新刷新
$isAjaxRefresh = isset($_GET['refresh_feed']) && $_GET['refresh_feed'] == '1' &&
                 isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if ($isAjaxRefresh) {
    header('Content-Type: application/json; charset=UTF-8');
    $clientIp = baigeFeedClientIp();
    $limitInfo = baigeFeedRefreshLimit($clientIp, $refreshLimitFile, $maxRefreshPerDay);
    if (!$limitInfo['can']) {
        echo json_encode([
            'success' => false,
            'message' => '今天手动刷新次数已用完（同一 IP 每天限 ' . $maxRefreshPerDay . ' 次），请明天再来或更换 IP 后刷新。',
            'left' => 0,
            'stats' => null,
            'nextRefresh' => 0,
        ]);
        exit;
    }
    if (file_exists($cacheFile)) {
        @unlink($cacheFile);
    }
    baigeFeedRecordRefresh($clientIp, $refreshLimitFile);
    $feedResult = baigeGetFeeds($this->options);
    $stats = $feedResult['stats'] ?? [
        'total' => 0,
        'success' => 0,
        'failed' => 0,
        'articles' => 0,
    ];
    $cacheMinutes = isset($this->options->feed_cache) ? intval($this->options->feed_cache) : 60;
    if ($cacheMinutes < 1) $cacheMinutes = 60;
    $nextRefresh = time() + ($cacheMinutes * 60);
    $left = $limitInfo['left'] - 1;
    if ($left === -1 || $left < -1) {
        // 不限制次数
        $message = '刷新成功！';
        $left = -1;
    } elseif ($left <= 0) {
        $message = '刷新成功！今天刷新次数已用完';
        $left = 0;
    } else {
        $message = '刷新成功！今天还可以刷新 ' . $left . ' 次';
    }
    // 渲染刷新后的 feed HTML
    $ajaxFeedPageSize = isset($this->options->feed_page_size) ? max(1, intval($this->options->feed_page_size)) : 10;
    $feedHtml = baigeRenderFeed($this->options, 1, $ajaxFeedPageSize);
    echo json_encode([
        'success' => true,
        'message' => $message,
        'left' => $left,
        'stats' => $stats,
        'nextRefresh' => $nextRefresh,
        'feedHtml' => $feedHtml,
    ]);
    exit;
}

$this->need('header.php');

$ajaxRefreshUrl = $this->permalink . (strpos($this->permalink, '?') === false ? '?' : '&') . 'refresh_feed=1';

// 当前页码
$feedPage = isset($_GET['feed_page']) ? max(1, intval($_GET['feed_page'])) : 1;
// 每页条数
$feedPageSize = isset($this->options->feed_page_size) ? max(1, intval($this->options->feed_page_size)) : 10;
// 获取数据与统计
$feedResult = baigeGetFeeds($this->options);
$feedTotalItems = $feedResult['items'] ?? [];
$feedStats = $feedResult['stats'] ?? [
    'total' => 0,
    'success' => 0,
    'failed' => 0,
    'articles' => 0,
];
$feedTotalPages = max(1, ceil(count($feedTotalItems) / $feedPageSize));
$feedPage = min($feedPage, $feedTotalPages);

// 缓存时间
$cacheTimeStr = '无缓存';
$nextRefreshTs = 0;
$cacheMinutes = isset($this->options->feed_cache) ? intval($this->options->feed_cache) : 60;
if ($cacheMinutes < 1) $cacheMinutes = 60;
if (file_exists($cacheFile)) {
    $mtime = filemtime($cacheFile);
    if ($mtime) {
        $cacheTimeStr = date('Y-m-d H:i:s', $mtime);
        $nextRefreshTs = $mtime + ($cacheMinutes * 60);
    }
}

// 生成分页链接（保留其他 URL 参数，只替换 feed_page）
$feedBaseUrl = $this->permalink;
$feedQuery = $_GET;
function feedPageUrl($baseUrl, $query, $page) {
    $query['feed_page'] = $page;
    $sep = strpos($baseUrl, '?') === false ? '?' : '&';
    return $baseUrl . $sep . http_build_query($query);
}
?>
<?php $this->need('sidebar.php'); ?>
<div class="baige-mainContent">
    <div class="baige-innermainContent">
        <div class="baige-Content">
            <div class="baige-Content-body baige-text-yichu" style="padding-top:0;margin-top:0;">
                <?php
                // 显示用户编辑的页面内容
                $this->content();
                ?>

                <?php if (isset($this->options->feed_switch) && $this->options->feed_switch === 'ok'): ?>
                <div class="baige-feed-stats">
                    <h2 class="baige-feed-stats-title">皮友圈动态</h2>
                    <p class="baige-feed-stats-desc">皮友圈动态卡片聚合展示，定时自动更新，也支持手动刷新。假如你不想被收录可留言取消，当然，有意加入直接评论留下 RSS 地址就好。皮皮收到信息第一时间添加！</p>
                    <div class="baige-feed-stats-meta">
                        <span class="baige-feed-stats-tag">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            更新时间：<?php echo $cacheTimeStr; ?>
                        </span>
                        <span class="baige-feed-stats-tag">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>
                            自动刷新：<?php echo $cacheMinutes; ?> 分钟
                        </span>
                        <span class="baige-feed-stats-tag baige-feed-countdown" data-next-refresh="<?php echo $nextRefreshTs; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            剩余：计算中…
                        </span>
                    </div>
                    <div class="baige-feed-stats-grid">
                        <div class="baige-feed-stats-card">
                            <div class="baige-feed-stats-num"><?php echo $feedStats['total']; ?></div>
                            <div class="baige-feed-stats-label">订阅总数</div>
                        </div>
                        <div class="baige-feed-stats-card">
                            <div class="baige-feed-stats-num"><?php echo $feedStats['success']; ?></div>
                            <div class="baige-feed-stats-label">抓取成功</div>
                        </div>
                        <div class="baige-feed-stats-card">
                            <div class="baige-feed-stats-num"><?php echo $feedStats['failed']; ?></div>
                            <div class="baige-feed-stats-label">抓取失败</div>
                        </div>
                        <div class="baige-feed-stats-card">
                            <div class="baige-feed-stats-num"><?php echo $feedStats['articles']; ?></div>
                            <div class="baige-feed-stats-label">文章数量</div>
                        </div>
                    </div>
                    <a class="baige-feed-refresh" href="<?php echo $ajaxRefreshUrl; ?>" data-ajax-refresh="1">
                        <svg class="baige-feed-refresh-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.3"></path></svg>
                        <span class="baige-feed-refresh-text">刷新缓存</span>
                    </a>
                </div>
                <?php endif; ?>

                <div class="baige-feed-wrap" id="baige-feed-wrap">
                    <?php echo baigeRenderFeed($this->options, $feedPage, $feedPageSize); ?>
                </div>
                <div class="baige-feed-loading" id="baige-feed-loading" style="display:none;">
                    <p class="baige-feed-loading-text">正在刷新订阅内容，请稍候…</p>
                </div>

                <?php if ($feedTotalPages > 1): ?>
                <ol class="page-navigator">
                    <?php if ($feedPage > 1): ?>
                    <li class="prev"><a href="<?php echo feedPageUrl($feedBaseUrl, $feedQuery, $feedPage - 1); ?>">&laquo;</a></li>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $feedTotalPages; $i++): ?>
                        <?php if ($i == $feedPage): ?>
                        <li class="current"><a href="<?php echo feedPageUrl($feedBaseUrl, $feedQuery, $i); ?>"><?php echo $i; ?></a></li>
                        <?php else: ?>
                        <li><a href="<?php echo feedPageUrl($feedBaseUrl, $feedQuery, $i); ?>"><?php echo $i; ?></a></li>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($feedPage < $feedTotalPages): ?>
                    <li class="next"><a href="<?php echo feedPageUrl($feedBaseUrl, $feedQuery, $feedPage + 1); ?>">&raquo;</a></li>
                    <?php endif; ?>
                </ol>
                <?php endif; ?>
            </div>
            <div class="baige-Content-bottom">
                日期: <?php echo xm::time($this->created); ?> |
                评论: <?php $this->commentsNum('0', '1', '%d'); ?> |
                查看: <?php echo xm::get_post_view($this); ?>
            </div>
        </div>
        <?php if($this->options->pinglun == '1'): $this->need('commentsa.php'); else: $this->need('comments.php'); endif; ?>
    </div>
</div>
<script>
(function() {
    var el = document.querySelector('.baige-feed-countdown');
    if (!el) return;
    var nextRefresh = parseInt(el.getAttribute('data-next-refresh'), 10) || 0;
    if (!nextRefresh) {
        el.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> 剩余：无缓存';
        return;
    }
    function pad(n) { return n < 10 ? '0' + n : n; }
    function update() {
        var now = Math.floor(Date.now() / 1000);
        var left = nextRefresh - now;
        var svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> ';
        if (left <= 0) {
            el.innerHTML = svg + '剩余：即将刷新';
            return;
        }
        var h = Math.floor(left / 3600);
        var m = Math.floor((left % 3600) / 60);
        var s = left % 60;
        var text = '剩余：';
        if (h > 0) text += h + '时';
        text += pad(m) + '分' + pad(s) + '秒';
        el.innerHTML = svg + text;
    }
    update();
    setInterval(update, 1000);
})();

// AJAX 无刷新刷新缓存
(function() {
    var btn = document.querySelector('a[data-ajax-refresh]');
    if (!btn) return;
    var icon = btn.querySelector('.baige-feed-refresh-icon');
    var text = btn.querySelector('.baige-feed-refresh-text');
    var countdownEl = document.querySelector('.baige-feed-countdown');
    var feedWrap = document.getElementById('baige-feed-wrap');
    var feedLoading = document.getElementById('baige-feed-loading');
    var originalText = '刷新缓存';
    var resetTimer = null;

    function updateStats(stats) {
        if (!stats) return;
        document.querySelectorAll('[data-stat]').forEach(function(el) {
            var key = el.getAttribute('data-stat');
            if (stats[key] !== undefined) {
                el.textContent = stats[key];
            }
        });
    }

    function resetButton() {
        if (icon) icon.classList.remove('baige-feed-spin');
        if (text) text.textContent = originalText;
        btn.style.pointerEvents = '';
        if (resetTimer) clearTimeout(resetTimer);
        resetTimer = null;
    }

    function showFeedLoading() {
        if (feedWrap) feedWrap.style.display = 'none';
        if (feedLoading) feedLoading.style.display = 'flex';
    }

    function hideFeedLoading() {
        if (feedLoading) feedLoading.style.display = 'none';
        if (feedWrap) feedWrap.style.display = '';
    }

    btn.addEventListener('click', function(e) {
        e.preventDefault();
        if (resetTimer) return; // 正在刷新中

        var url = btn.getAttribute('href');
        if (!url) return;

        if (icon) icon.classList.add('baige-feed-spin');
        if (text) text.textContent = '刷新中…';
        btn.style.pointerEvents = 'none';

        // 显示加载提示，隐藏订阅内容
        showFeedLoading();

        var xhr = new XMLHttpRequest();
        xhr.open('GET', url + (url.indexOf('?') === -1 ? '?' : '&') + '_t=' + Date.now(), true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onreadystatechange = function() {
            if (xhr.readyState !== 4) return;
            try {
                var res = JSON.parse(xhr.responseText);
                if (res.success) {
                    updateStats(res.stats);
                    // 更新倒计时
                    if (countdownEl && res.nextRefresh) {
                        countdownEl.setAttribute('data-next-refresh', res.nextRefresh);
                    }
                    if (text) text.textContent = res.message;
                    // 替换订阅内容
                    if (res.feedHtml && feedWrap) {
                        feedWrap.innerHTML = res.feedHtml;
                    }
                } else {
                    if (text) text.textContent = res.message;
                }
            } catch (err) {
                if (text) text.textContent = '刷新失败，请稍后再试';
            }
            // 恢复显示订阅内容
            hideFeedLoading();
            // 5 秒后还原按钮
            resetTimer = setTimeout(resetButton, 5000);
        };
        xhr.send();
    });
})();
</script>
<?php $this->need('footer.php'); ?>
