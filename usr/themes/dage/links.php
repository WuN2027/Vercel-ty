<?php 
/**
 * 友链 3.5
 * @author 柏哥
 * @package custom
 */
class LinkCacheHandler {
    private $cacheDir;
    private $cacheFile;
    private $cid;

    public function __construct($cid) {
        $this->cid = $cid;
        $this->cacheDir = __TYPECHO_ROOT_DIR__ . '/usr/themes/dage/cache/links/';
        $this->cacheFile = $this->cacheDir . $this->cid . '.cache';
        $this->initCacheDir();
    }

    private function initCacheDir() {
        if (!file_exists($this->cacheDir)) {
            $created = @mkdir($this->cacheDir, 0755, true);
            if (!$created && !is_dir($this->cacheDir)) {
                trigger_error("无法创建缓存目录: " . $this->cacheDir . "，请检查权限", E_USER_WARNING);
            }
        }
    }

    public function needUpdateCache($pageModified) {
        if (!is_writable($this->cacheDir)) return true;
        if (!file_exists($this->cacheFile)) return true;
        $cacheTime = filemtime($this->cacheFile);
        return $pageModified > $cacheTime;
    }

    public function readFromCache() {
        if (!file_exists($this->cacheFile)) return [];
        $cacheContent = @file_get_contents($this->cacheFile);
        return $cacheContent ? json_decode($cacheContent, true) : [];
    }

    public function writeToCache($links) {
        if (!is_writable($this->cacheDir)) {
            trigger_error("缓存目录不可写: " . $this->cacheDir . "，跳过写入", E_USER_WARNING);
            return false;
        }
        $result = @file_put_contents($this->cacheFile, json_encode($links, JSON_UNESCAPED_UNICODE));
        if ($result === false) {
            trigger_error("缓存文件写入失败: " . $this->cacheFile, E_USER_WARNING);
            return false;
        }
        return true;
    }
}

class LinkParser {
    public static function parse($content) {
        $links = [];
        
        // 参与排名
        preg_match_all('/{fyindex\s+title="([^"]+)"\s*}(.*?){\/fyindex}/s', $content, $groups);
        foreach ($groups[0] as $k => $fullMatch) {
            $title = $groups[1][$k];
            $groupContent = $groups[2][$k];

            preg_match_all('/{linkc url="(.+?)" img="(.+?)"}(.+?){\/linkc m="(.+?)"}/', $groupContent, $matches, PREG_SET_ORDER);
            foreach ($matches as $m) {
                $links[] = [
                    'url'    => $m[1],
                    'img'    => $m[2],
                    'title'  => $m[3],
                    'ms'     => $m[4],
                    'group'  => $title,
                    'norank' => true,
                    'nolink' => false
                ];
            }
            $content = str_replace($fullMatch, '', $content);
        }

        // 仅展示
        preg_match_all('/{nolinkindex\s+title="([^"]+)"\s*}(.*?){\/nolinkindex}/s', $content, $groups);
        foreach ($groups[0] as $k => $fullMatch) {
            $title = $groups[1][$k];
            $groupContent = $groups[2][$k];

            preg_match_all('/{linkc url="(.+?)" img="(.+?)"}(.+?){\/linkc m="(.+?)"}/', $groupContent, $matches, PREG_SET_ORDER);
            foreach ($matches as $m) {
                $links[] = [
                    'url'    => $m[1],
                    'img'    => $m[2],
                    'title'  => $m[3],
                    'ms'     => $m[4],
                    'group'  => $title,
                    'norank' => true,
                    'nolink' => true
                ];
            }
            $content = str_replace($fullMatch, '', $content);
        }

        // 正常友链
        preg_match_all('/{linkc url="(.+?)" img="(.+?)"}(.+?){\/linkc m="(.+?)"}/', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $links[] = [
                'url'    => $m[1],
                'img'    => $m[2],
                'title'  => $m[3],
                'ms'     => $m[4],
                'group'  => '',
                'norank' => false,
                'nolink' => false
            ];
        }

        return $links;
    }
}

class LinkRenderer {
    public static function render($links) {
        if (empty($links)) {
            echo '<div class="baige-cess"><p class="empty-tip">暂无友链数据</p></div>';
            return;
        }

        // 分组整理
        $normal = [];
        $groups = [];
        foreach ($links as $link) {
            if (!empty($link['group'])) {
                $groups[$link['group']][] = $link;
            } else {
                $normal[] = $link;
            }
        }

        // 输出
        echo '<div class="baige-cess link-flex-container">';
        foreach ($normal as $index => $link) {
            $url = htmlspecialchars($link['url'], ENT_QUOTES);
            $img = htmlspecialchars($link['img'], ENT_QUOTES);
            $title = htmlspecialchars($link['title'], ENT_QUOTES);
            $ms = $link['ms'];
            $truncatedMs = mb_strlen($ms) > 20 ? mb_substr($ms, 0, 20) . '...' : $ms;
            
            $linkClass = 'baige-linkc-body';
            $titleColorClass = '';
            $msClass = '';
            $rankHtml = '';

            if ($index === 0) {
                $linkClass .= ' highlight-link highlight-link--1 top-two-vertical';
                $titleColorClass = 'title-color--red';
                $msClass = 'gradient-text-red';
                $rankHtml = '<div class="rank-tag rank-tag--first">榜一大哥</div>';
            } elseif ($index === 1) {
                $linkClass .= ' highlight-link highlight-link--2 top-two-vertical';
                $titleColorClass = 'title-color--blue';
                $msClass = 'gradient-text-blue';
                $rankHtml = '<div class="rank-tag rank-tag--second">榜二大哥</div>';
            } else {
                $truncatedMs = htmlspecialchars($truncatedMs, ENT_QUOTES);
            }

            echo <<<HTML
<div class="{$linkClass} link-item">
    {$rankHtml}
    <a href="{$url}" target="_blank" rel="noopener noreferrer">
        <div class="baige-linkc-you">
            <img src="{$img}" alt="{$title}">
        </div>
    </a>
    <div class="baige-linkc-zuo">
        <div class="baige-linkc-title {$titleColorClass}">
            <a href="{$url}" target="_blank" rel="noopener noreferrer" style="text-decoration:none">{$title}</a>
        </div>
        <div class="baige-linkc-ms {$msClass}">{$truncatedMs}</div>
    </div>
</div>
HTML;
        }
        echo '</div>';

        // 输出
        foreach ($groups as $groupTitle => $items) {
            echo '<div style="margin:20px 0 10px;font-size:16px;font-weight:bold;border-left:3px solid #ff6b6b;padding-left:8px;">';
            echo htmlspecialchars($groupTitle);
            echo '</div>';
            echo '<div class="baige-cess link-flex-container">';
            foreach ($items as $link) {
                $url = htmlspecialchars($link['url'], ENT_QUOTES);
                $img = htmlspecialchars($link['img'], ENT_QUOTES);
                $title = htmlspecialchars($link['title'], ENT_QUOTES);
                $ms = htmlspecialchars(mb_strlen($link['ms'])>20 ? mb_substr($link['ms'],0,20).'...' : $link['ms'], ENT_QUOTES);
                $nolink = $link['nolink'] ?? false;

                if ($nolink) {
                    // 不可点击版本
                    echo <<<HTML
<div class="baige-linkc-body link-item">
    <div style="flex-shrink:0;border-radius:5px;position:relative;z-index:2;width:35px;height:35px">
        <img src="{$img}" alt="{$title}" style="width:100%;height:100%;object-fit:cover;border-radius:5px">
    </div>
    <div class="baige-linkc-zuo">
        <div class="baige-linkc-title" style="font-weight:bold;margin-bottom:1px;font-size:15px;cursor:default;">
            {$title}
        </div>
        <div class="baige-linkc-ms">{$ms}</div>
    </div>
</div>
HTML;
                } else {
                    // 原始可点击版本
                    echo <<<HTML
<div class="baige-linkc-body link-item">
    <a href="{$url}" target="_blank" rel="noopener noreferrer" style="text-decoration:none;outline:none">
        <div class="baige-linkc-you" style="flex-shrink:0;border-radius:5px;cursor:pointer;position:relative;z-index:2">
            <img src="{$img}" alt="{$title}" style="width:100%;height:100%;object-fit:cover;border-radius:5px">
        </div>
    </a>
    <div class="baige-linkc-zuo" style="flex:1;min-width:0;z-index:2;position:relative;margin-left:5px;width:100%">
        <div class="baige-linkc-title" style="font-weight:bold;margin-bottom:1px;font-size:15px">
            <a href="{$url}" target="_blank" rel="noopener noreferrer" style="text-decoration:none">{$title}</a>
        </div>
        <div class="baige-linkc-ms">{$ms}</div>
    </div>
</div>
HTML;
                }
            }
            echo '</div>';
        }
    }
}

if (!defined('__TYPECHO_ROOT_DIR__')) exit('Access denied');

// 处理友链申请 AJAX 提交
$options = Helper::options();
if (isset($options->friend_link_switch) && $options->friend_link_switch === 'ok' &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['friend_link_action']) && $_POST['friend_link_action'] === 'apply') {

    header('Content-Type: application/json; charset=UTF-8');

    // IP 频率限制
    $limitFile = dirname(__FILE__) . '/cache/friend_link_apply_limits.json';
    $maxPerDay = isset($options->friend_link_limit_ip) ? intval($options->friend_link_limit_ip) : 3;
    if ($maxPerDay < 1) $maxPerDay = 3;

    $clientIp = baigeGetClientIp();
    $today = date('Y-m-d');
    $limits = [];
    if (file_exists($limitFile)) {
        $limits = @json_decode(file_get_contents($limitFile), true) ?: [];
    }
    foreach ($limits as $k => $v) {
        if (($v['date'] ?? '') !== $today) {
            unset($limits[$k]);
        }
    }
    $currentCount = $limits[$clientIp]['count'] ?? 0;
    if ($currentCount >= $maxPerDay) {
        echo json_encode(['success' => false, 'message' => '今天提交次数已达上限（同一 IP 每天限 ' . $maxPerDay . ' 次），请明天再来']);
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $avatar = trim($_POST['avatar'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $rss = trim($_POST['rss'] ?? '');

    if (empty($name) || empty($url)) {
        echo json_encode(['success' => false, 'message' => '请填写网站名称和网址']);
        exit;
    }
    if (!preg_match('/^https?:\/\/.+/i', $url)) {
        echo json_encode(['success' => false, 'message' => '网址格式不正确，需以 http:// 或 https:// 开头']);
        exit;
    }
    if (!empty($avatar) && !preg_match('/^https?:\/\/.+/i', $avatar)) {
        echo json_encode(['success' => false, 'message' => '头像地址格式不正确']);
        exit;
    }
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => '邮箱格式不正确']);
        exit;
    }
    if (!empty($rss) && !preg_match('/^https?:\/\/.+/i', $rss)) {
        echo json_encode(['success' => false, 'message' => 'RSS 地址格式不正确']);
        exit;
    }

    // 过滤重复域名
    $applyDomain = '';
    $parsedUrl = parse_url($url);
    if (!empty($parsedUrl['host'])) {
        $applyDomain = strtolower($parsedUrl['host']);
        if (strpos($applyDomain, 'www.') === 0) {
            $applyDomain = substr($applyDomain, 4);
        }
    }
    if (!empty($applyDomain)) {
        try {
            $db = Typecho_Db::get();
            $prefix = $db->getPrefix();
            $table = $prefix . 'dage_links';
            $existing = $db->fetchRow($db->select('id')->from($table)->where('url LIKE ?', '%' . $applyDomain . '%'));
            if (!empty($existing)) {
                echo json_encode(['success' => false, 'message' => '该域名已经提交过友链申请，请勿重复提交']);
                exit;
            }
        } catch (Exception $e) {
            // 数据库查询失败时继续提交
        }
    }

    if (baigeAddLinkApply([
        'name' => $name,
        'url' => $url,
        'avatar' => $avatar,
        'description' => $description,
        'email' => $email,
        'rss' => $rss,
        'ip' => $clientIp,
    ])) {
        if (!isset($limits[$clientIp])) {
            $limits[$clientIp] = ['date' => $today, 'count' => 0];
        }
        $limits[$clientIp]['count']++;
        @file_put_contents($limitFile, json_encode($limits));
        $left = max(0, $maxPerDay - $limits[$clientIp]['count']);
        echo json_encode(['success' => true, 'message' => '申请已提交，审核通过后会显示在友链列表中。今天还可提交 ' . $left . ' 次']);
    } else {
        echo json_encode(['success' => false, 'message' => '提交失败，请稍后再试']);
    }
    exit;
}

$this->need('header.php');
$this->need('sidebar.php');
?>
<div class="baige-mainContent">
    <div class="baige-innermainContent">
        <?php
        try {
            $pageModified = $this->modified ?? $this->created;
            $content = $this->content;

            $cacheHandler = new LinkCacheHandler($this->cid);

            if ($cacheHandler->needUpdateCache($pageModified) && strpos($content, '{linkc') !== false) {
                $links = LinkParser::parse($content);
                $cacheHandler->writeToCache($links);
            } else {
                $links = $cacheHandler->readFromCache();
            }

            // 合并数据库中已审核的友链申请
            $options = Helper::options();
            if (isset($options->friend_link_switch) && $options->friend_link_switch === 'ok') {
                $dbLinks = baigeGetApprovedLinks();
                foreach ($dbLinks as $dbLink) {
                    $links[] = [
                        'url' => $dbLink['url'],
                        'img' => !empty($dbLink['avatar']) ? $dbLink['avatar'] : '/no.jpg',
                        'title' => $dbLink['name'],
                        'ms' => $dbLink['description'],
                        'group' => '',
                        'norank' => false,
                        'nolink' => false
                    ];
                }
            }

            // 按来访统计排序（仅正常友链参与排名，分组/纯展示不参与）
            $links = baigeSortLinksByRank($links);

            LinkRenderer::render($links);

        } catch (Exception $e) {
            echo '<div class="baige-cess"><p class="error-tip">友链加载失败: ' . $e->getMessage() . '</p></div>';
        }
        ?>

        <?php if (isset(Helper::options()->friend_link_switch) && Helper::options()->friend_link_switch === 'ok'): ?>
        <div class="baige-friend-link-apply">
            <button type="button" class="baige-friend-link-toggle" id="baigeFriendLinkToggle">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                自助申请友链
            </button>
            <div class="baige-friend-link-collapse" id="baigeFriendLinkCollapse">
                <h3 class="baige-friend-link-title">自助提交</h3>
                <p class="baige-friend-link-desc"><?php echo nl2br(htmlspecialchars(Helper::options()->friend_link_apply_tip ?? '需要交换链接的朋友，可以填写以下信息直接提交申请，审核通过后会自动显示在友链列表中。')); ?></p>
                <form class="baige-friend-link-form" id="baigeFriendLinkForm" action="<?php echo $this->permalink; ?>" method="post">
                <input type="hidden" name="friend_link_action" value="apply">
                <div class="baige-friend-link-row">
                    <label>网站名称 *</label>
                    <input type="text" name="name" required placeholder="你的网站名称" maxlength="100">
                </div>
                <div class="baige-friend-link-row">
                    <label>网站地址 *</label>
                    <input type="url" name="url" required placeholder="https://example.com" maxlength="255">
                </div>
                <div class="baige-friend-link-row">
                    <label>网站头像</label>
                    <input type="url" name="avatar" placeholder="https://example.com/avatar.png" maxlength="255">
                </div>
                <div class="baige-friend-link-row">
                    <label>网站描述</label>
                    <input type="text" name="description" placeholder="一句话介绍你的网站" maxlength="255">
                </div>
                <div class="baige-friend-link-row">
                    <label>联系邮箱</label>
                    <input type="email" name="email" placeholder="your@email.com" maxlength="100">
                </div>
                <div class="baige-friend-link-row">
                    <label>RSS 订阅</label>
                    <input type="url" name="rss" placeholder="https://example.com/feed" maxlength="255">
                </div>
                <div class="baige-friend-link-message" id="baigeFriendLinkMessage"></div>
                <button type="submit" class="baige-friend-link-submit">提交申请</button>
            </form>
            </div>
        </div>
        <script>
        (function() {
            var toggle = document.getElementById('baigeFriendLinkToggle');
            var collapse = document.getElementById('baigeFriendLinkCollapse');
            if (toggle && collapse) {
                toggle.addEventListener('click', function() {
                    var isOpen = collapse.classList.contains('open');
                    if (isOpen) {
                        collapse.classList.remove('open');
                        toggle.classList.remove('open');
                    } else {
                        collapse.classList.add('open');
                        toggle.classList.add('open');
                    }
                });
            }

            var form = document.getElementById('baigeFriendLinkForm');
            var msgEl = document.getElementById('baigeFriendLinkMessage');
            if (!form) return;
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var btn = form.querySelector('button[type="submit"]');
                var originalText = btn.textContent;
                btn.disabled = true;
                btn.textContent = '提交中…';
                msgEl.style.display = 'none';
                msgEl.className = 'baige-friend-link-message';

                var formData = new FormData(form);
                var xhr = new XMLHttpRequest();
                xhr.open('POST', form.action, true);
                xhr.onreadystatechange = function() {
                    if (xhr.readyState !== 4) return;
                    try {
                        var res = JSON.parse(xhr.responseText);
                        msgEl.textContent = res.message;
                        msgEl.className = 'baige-friend-link-message ' + (res.success ? 'success' : 'error');
                        msgEl.style.display = 'block';
                        if (res.success) {
                            form.reset();
                            // 提交成功 3 秒后自动收起表单
                            setTimeout(function() {
                                if (collapse) {
                                    collapse.classList.remove('open');
                                }
                                if (toggle) {
                                    toggle.classList.remove('open');
                                }
                            }, 3000);
                        }
                    } catch (err) {
                        msgEl.textContent = '提交失败，请检查网络后重试';
                        msgEl.className = 'baige-friend-link-message error';
                        msgEl.style.display = 'block';
                    }
                    btn.disabled = false;
                    btn.textContent = originalText;
                    setTimeout(function() {
                        if (msgEl) msgEl.style.display = 'none';
                    }, 5000);
                };
                xhr.send(formData);
            });
        })();
        </script>
        <?php endif; ?>

        <div class="baige-linka-content baige-text-yichu">
            <?php 
            echo xm::xm_qklink(xm::ubb($content));
            ?>
        </div>
        <div class="baige-Content-bottom">
            <div>
                评论: <?php $this->commentsNum('0', '1', '%d'); ?> | 
                查看: <?php echo xm::get_post_view($this); ?>
            </div>
        </div>
        <?php if($this->options->pinglun == '1'): $this->need('commentsa.php'); else: $this->need('comments.php'); endif; ?>
    </div>
</div>

<?php $this->need('footer.php'); ?>
