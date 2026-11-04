<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Typecho 主题核心类
 * @author 柏哥
 */
class xm
{
    // 时间格式化
    public static function time($time)
    {
        $text = '';
        $time = intval($time);
        $currentTime = time();
        $timeDiff = $currentTime - $time;

        if ($timeDiff < 0) {
            return date('Y-m-d', $time);
        }

        $yearDiff = date('Y', $currentTime) - date('Y', $time);

        switch (true) {
            case $timeDiff == 0:
                $text = '刚刚';
                break;
            case $timeDiff < 60:
                $text = $timeDiff . '秒前';
                break;
            case $timeDiff < 3600:
                $text = floor($timeDiff / 60) . '分钟前';
                break;
            case $timeDiff < 86400:
                $text = floor($timeDiff / 3600) . '小时前';
                break;
            case $timeDiff < 2592000:
                if ($time > strtotime(date('Ymd', strtotime("-1 day")))) {
                    $text = '昨天';
                } elseif ($time > strtotime(date('Ymd', strtotime("-2 days")))) {
                    $text = '前天';
                } else {
                    $text = floor($timeDiff / 86400) . '天前';
                }
                break;
            case $timeDiff < 31536000:
                $monthDiff = (date('Y', $currentTime) * 12 + date('m', $currentTime))
                    - (date('Y', $time) * 12 + date('m', $time));
                $text = $monthDiff > 0 ? $monthDiff . '个月前' : floor($timeDiff / 86400) . '天前';
                break;
            default:
                $text = $yearDiff . '年前';
                break;
        }

        return $text;
    }

    // 获取文章缩略图（同请求内缓存，减少重复正则与附件查询）
    public static function showThumbnail($widget, $imgnum = 0, $pan = false)
    {
        $customThumbnail = $widget->fields->thumbnail ?? '';
        if (!empty($customThumbnail)) {
            return $customThumbnail;
        }

        $cid = $widget->cid ?? 0;
        $cacheKey = (int)$cid . ':' . (int)$imgnum . ':' . ($pan ? '1' : '0');
        static $cache = [];
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $attach = $widget->attachments(1)->attachment;

        $patternHtml = '|<img[^>]+src=\"([^>\"]+)\"?[^>]*>|is';
        $patternMD = '/\!\[.*?\]\((http(s)?:\/\/.*?(jpeg|gif|jpg|png))/i';
        $patternMDfoot = '/\[.*?\]:\s*(http(s)?:\/\/.*?(jpeg|gif|jpg|png))/i';

        if (!is_numeric($imgnum) || $imgnum < 0) {
            $imgnum = 0;
        }

        $randomImg = XM_TPL . 'bai/png/suiji' . rand(1, 20) . '.png';
        $loadingImg = XM_TPL . 'bai/jz.svg';
        $defaultImg = $pan ? '' : $randomImg;

        $useLoading = !Typecho_Widget::widget('Widget_Options')->suiji && !$pan;
        if ($useLoading) {
            $defaultImg = $loadingImg;
        }

        if (preg_match_all($patternHtml, $widget->content, $thumbUrl)) {
            $img = isset($thumbUrl[1][$imgnum]) ? $thumbUrl[1][$imgnum] : $defaultImg;
        } elseif ($attach && $attach->isImage) {
            $img = $attach->url;
        } elseif (preg_match_all($patternMD, $widget->content, $thumbUrl)) {
            $img = isset($thumbUrl[1][$imgnum]) ? $thumbUrl[1][$imgnum] : $defaultImg;
        } elseif (preg_match_all($patternMDfoot, $widget->content, $thumbUrl)) {
            $img = isset($thumbUrl[1][$imgnum]) ? $thumbUrl[1][$imgnum] : $defaultImg;
        } else {
            $img = $defaultImg;
        }

        // 跳过播放按钮和暂停按钮图片
        if (stripos($img, 'call-play.svg') !== false || stripos($img, 'call-pause.svg') !== false) {
            $img = $defaultImg;
        }

        $cache[$cacheKey] = $img;
        return $img;
    }

    // 获取头像
    public static function getGravatar($mail, int $size = 100, $out = null, $authorHint = '')
    {
        // ✅ 标准安全密钥
        $aesKey  = 'z9x7b5q2w8e3r1t6y0u4i8o2p5a7s9d';
        $aesIv   = '5g7h2j4k6l1z8x0c';

        // ✅ 必须先定义 options
        $options = Typecho_Widget::widget('Widget_Options');

        // 博主头像（后台有填写时优先使用，留空则走普通头像链路）
        if ($out && $out->authorId == $out->ownerId) {
            $blogAvatar = trim((string)$options->blogtx);
            if ($blogAvatar !== '') {
                return $blogAvatar;
            }
            // 后台留空，传入博主昵称继续走下方头像链路
            $authorHint = !empty($out->author) ? $out->author : $authorHint;
        }

        $mailLower = strtolower(trim((string) $mail));
        $md5Mail   = md5($mailLower);

        // QQ 邮箱头像
        if (str_contains($mailLower, '@qq.com')) {
            $qqNumber = str_replace('@qq.com', '', $mailLower);
            $pureQq   = preg_replace('/\D/', '', $qqNumber);

            if (strlen($pureQq) >= 5 && strlen($pureQq) <= 10) {
                $encryptedQq = openssl_encrypt($pureQq, 'aes-256-cbc', $aesKey, OPENSSL_RAW_DATA, $aesIv);
                $encryptedQq = base64_encode($encryptedQq);
                
                return XM_TPL . 'avatar.php?hash=' . $encryptedQq . '&s=' . $size;
            }
        }

        // 随机头像
        if ($options->touxiangsj) {
            return XM_TPL . 'bai/tx/' . rand(1, 20) . '.jpg';
        }

        // 首字母头像
        if (isset($options->letter_avatar) && $options->letter_avatar) {
            $authorName = '';
            if (!empty($authorHint)) {
                $authorName = $authorHint;
            } elseif ($out && !empty($out->author)) {
                $authorName = $out->author;
            }
            if (empty($authorName) && !empty($mailLower)) {
                $authorName = $mailLower;
            }
            if (!empty($authorName)) {
                return self::letterAvatar($authorName);
            }
        }

        // 第三方头像
        $gravatarSource = 'cravatar.cn/avatar';
        if (!empty($options->gravatars)) {
            $source = is_array($options->gravatars) ? reset($options->gravatars) : $options->gravatars;
            $source = trim((string)$source);
            if ($source !== '') {
                $gravatarSource = $source;
            }
        }
        return 'https://' . rtrim($gravatarSource, '/') . '/' . $md5Mail . '?s=' . $size;
    }

    // HSV转RGB
    private static function hsv2rgb($h, $s, $v)
    {
        $r = $g = $b = 0;
        $i = floor($h * 6);
        $f = $h * 6 - $i;
        $p = $v * (1 - $s);
        $q = $v * (1 - $f * $s);
        $t = $v * (1 - (1 - $f) * $s);
        switch ($i % 6) {
            case 0: $r = $v; $g = $t; $b = $p; break;
            case 1: $r = $q; $g = $v; $b = $p; break;
            case 2: $r = $p; $g = $v; $b = $t; break;
            case 3: $r = $p; $g = $q; $b = $v; break;
            case 4: $r = $t; $g = $p; $b = $v; break;
            case 5: $r = $v; $g = $p; $b = $q; break;
        }
        return [
            'r' => round($r * 255),
            'g' => round($g * 255),
            'b' => round($b * 255)
        ];
    }

    // 首字母头像
    public static function letterAvatar($name, $size = 100)
    {
        $name = trim((string)$name);
        $letter = mb_substr($name, 0, 1, 'UTF-8');
        if (empty($letter)) $letter = '?';

        $hash = md5(strtolower($name));
        $hue = (hexdec(substr($hash, 0, 2)) % 360) / 360;
        $rgb = self::hsv2rgb($hue, 0.65, 0.85);
        $bgColor = sprintf('#%02x%02x%02x', $rgb['r'], $rgb['g'], $rgb['b']);

        $fontSize = round($size * 0.45);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $size . ' ' . $size . '">'
            . '<rect width="' . $size . '" height="' . $size . '" fill="' . $bgColor . '" rx="' . round($size * 0.15) . '"/>'
            . '<text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" font-size="' . $fontSize . '" font-family="sans-serif" fill="#fff">' . htmlspecialchars($letter) . '</text>'
            . '</svg>';

        return 'data:image/svg+xml,' . rawurlencode($svg);
    }

    // 解析表情
    public static function biaoqing($content)
    {
        if ($content === null) return '';
        $content = preg_replace_callback(
            '/:\s*\(\s*(.*?)\s*\)/is',
            array('xm', 'parseHeoBiaoqingCallback'),
            $content
        );
        return $content;
    }

    // 表情解析回调（同请求内缓存文件存在性，减少重复 file_exists）
    private static function parseHeoBiaoqingCallback($match): string
    {
        // 直接获取表情名称
        $biaoqing = $match[1];

        static $existsCache = [];
        if (isset($existsCache[$biaoqing])) {
            if ($existsCache[$biaoqing] === false) {
                return $match[0];
            }
            return '<img class="biaoqing" src="' . $existsCache[$biaoqing] . '" title="' . $biaoqing . '" alt="' . $biaoqing . '">';
        }

        // 自动匹配本地表情文件后缀
        $baseUrl  = XM_TPL . 'bai/owo/biaoqing/' . $biaoqing;
        $basePath = __DIR__ . '/../bai/owo/biaoqing/' . $biaoqing;
        foreach (array('gif', 'png', 'jpg', 'jpeg', 'webp') as $ext) {
            if (file_exists($basePath . '.' . $ext)) {
                $src = $baseUrl . '.' . $ext;
                $existsCache[$biaoqing] = $src;
                return '<img class="biaoqing" src="' . $src . '" title="' . $biaoqing . '" alt="' . $biaoqing . '">';
            }
        }

        // 本地不存在该表情文件
        $existsCache[$biaoqing] = false;
        return $match[0];
    }

    // 文章阅读量
    public static function get_post_view($archive)
    {
        $cid = $archive->cid;
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();

        static $hasViewsColumn = null;
        if ($hasViewsColumn === null) {
            $rowFields = $db->fetchRow($db->select()->from('table.contents')->limit(1));
            $hasViewsColumn = array_key_exists('views', $rowFields);
        }
        if (!$hasViewsColumn) {
            $db->query('ALTER TABLE `' . $prefix . 'contents` ADD `views` INT(10) DEFAULT 0;');
            $hasViewsColumn = true;
            return '0';
        }

        $row = $db->fetchRow($db->select('views')->from('table.contents')->where('cid = ?', $cid));
        $currentViews = isset($row['views']) ? (int)$row['views'] : 0;

        if ($archive->is('single')) {
            // 防重复计数
            $cookieName = 'post_view_' . $cid;
            if (!Typecho_Cookie::get($cookieName)) {
                $db->query($db->update('table.contents')->rows(array('views' => $currentViews + 1))->where('cid = ?', $cid));
                Typecho_Cookie::set($cookieName, '1', time() + 10);
                $currentViews += 1;
            }
        }

        if ($currentViews >= 100000) {
            $showViews = round($currentViews / 10000) . 'W+';
        } elseif ($currentViews >= 10000) {
            $showViews = round($currentViews / 10000, 1) . 'W+';
        } elseif ($currentViews >= 1000) {
            $showViews = round($currentViews / 1000, 1) . 'K+';
        } else {
            $showViews = $currentViews;
        }

        return $showViews;
    }

    // 隐私评论处理
    public static function insertSecret($comment)
    {
        if (!empty($_POST['secret'])) {
            $comment['text'] = '{secret}' . $comment['text'] . '{/secret}';
        }
        return $comment;
    }

    // 评论@回复
    public static function reply($parent, $a = true)
    {
        if ($parent == 0) {
            return '';
        }

        $db = Typecho_Db::get();
        $commentInfo = $db->fetchRow($db->select('author,status,mail')->from('table.comments')->where('coid = ?', $parent));

        if (!$commentInfo) {
            return '';
        }

        if ($a == true) {
            $link = '<a class="baige-parent" href="#comment-' . $parent . '">@' . htmlspecialchars($commentInfo['author']) . '</a>';
        } else {
            $link = ' 回复 <span class="baige-huifu-aite">' . htmlspecialchars($commentInfo['author']) . '</span>';
        }

        return $link;
    }

    // 获取回复邮箱
    public static function get_comment_at($coid)
    {
        $db = Typecho_Db::get();
        $commentData = $db->fetchRow($db->select('parent,status')->from('table.comments')->where('coid = ?', $coid));

        if (!$commentData) {
            return '';
        }

        $parentMail = '';
        $parentId = $commentData['parent'];

        if ($parentId != '0') {
            $parentComment = $db->fetchRow($db->select('author,status,mail')->from('table.comments')->where('coid = ?', $parentId));
            if ($parentComment) {
                $parentMail = $parentComment['mail'];
            }
        }

        return $parentMail;
    }

    // 私密评论解析
    public static function simi($content)
    {
        if (strpos($content, '{secret') !== false) {
            $user = Typecho_Widget::widget("Widget_User");
            $comment = Typecho_Widget::widget("Widget_Comments_Archive");
            $parentMail = self::get_comment_at($comment->coid);

            $canView = false;
            $userMail = '';
            if ($user->hasLogin()) {
                $userMail = $user->mail ?? '';
                // 管理员可见
                if ($user->pass('administrator', true)) {
                    $canView = true;
                }
            } else {
                // 未登录访客尝试用 remember cookie 中的邮箱匹配
                $userMail = Typecho_Widget::widget("Widget_Archive")->remember("mail", true);
            }

            if (!$canView && $userMail) {
                // 评论作者本人可见
                if ($comment->mail == $userMail && $comment->mail != "") {
                    $canView = true;
                }
                // 被回复的评论作者本人可见
                if (!$canView && $parentMail == $userMail && $userMail != "") {
                    $canView = true;
                }
            }

            $content = preg_replace('/{secret}(.+?){\/secret}/', '<div class="baige-reply-view">$1</div>', $content);
            $content = $canView ? $content : '该评论为私密评论，仅文章作者与评论发起者可见！';
        }

        return $content;
    }

    // 换行UBB
    public static function ubbhh($content)
    {
        if (strpos($content, '{br') !== false) {
            $content = preg_replace('/{br}/', "<br>", $content);
        }

        return $content;
    }

    // UBB解析
    public static function ubb($content, $type = false)
    {
        // 视频短代码回调
        if (strpos($content, '{video') !== false) {
            $content = preg_replace_callback('/{video src="(.+?)"(?: pic="(.+?)")?}/', function ($matches) {
                return self::renderVideoTag($matches[1], isset($matches[2]) ? $matches[2] : "");
            }, $content);
        }

        if (strpos($content, '{mp3') !== false) {
            $content = preg_replace('/{mp3 src="(.+?)"}/', "<audio id=\"media\" src=\"$1\" controls=\"controls\" style=\"width:100%\" controlsList=\"nodownload\" oncontextmenu=\"return false\">这里是一段音乐,由于您的浏览器原因不能显示播放器", $content);
        }

        if (strpos($content, '{ts') !== false) {
            $content = preg_replace('/{ts id="(.+?)"}(.+?){\/ts}/', "<div class=\"baige-ts-$1\">$2</div>", $content);
        }

        if (strpos($content, '{center') !== false) {
            $content = preg_replace('/{center}(.+?){\/center}/', "<center>$1</center>", $content);
        }

        if (strpos($content, '{login') !== false) {
            if (Typecho_Widget::widget('Widget_User')->hasLogin()) {
                $content = preg_replace('/{login}(.+?){\/login}/', '<div class="baige-login-box"><div class="baige-login-content">$1</div></div>', $content);
            } else {
                $content = preg_replace('/{login}(.+?){\/login}/', '<div class="baige-login-box">本部分内容需 <a href="' . Helper::options()->adminUrl . 'login.php" style="color:#48bb78;">登录</a> 后可见</div>', $content);
            }
        }

        if (strpos($content, '{bl') !== false) {
            $content = preg_replace('/{bl av="([0-9]+)"}/', "<div class=\"media media-16x9 bilibili-player\"><iframe class=\"iframe_video\" onload=\"this.parentElement.classList.add('bilibili-loaded')\" src=\"//www.bilibili.com/blackboard/html5mobileplayer.html?aid=\\1&page=1\" scrolling=\"no\" border=\"0\" frameborder=\"no\" framespacing=\"0\" allowfullscreen=\"true\"> </iframe></div>", $content);
            $content = preg_replace('/{bl bv="(BV[0-9a-zA-Z]+)"}/i', "<div class=\"media media-16x9 bilibili-player\"><iframe class=\"iframe_video\" onload=\"this.parentElement.classList.add('bilibili-loaded')\" src=\"//www.bilibili.com/blackboard/html5mobileplayer.html?bvid=\\1&page=1\" scrolling=\"no\" border=\"0\" frameborder=\"no\" framespacing=\"0\" allowfullscreen=\"true\"> </iframe></div>", $content);
        }

        if (strpos($content, '{sfq') !== false) {
            $content = preg_replace('/{sfq title="(.+?)"}(.+?){\/sfq}/s', "<div class='baige-sfq-body'><div class='baige-sfq'><span class='baige-sfq-title'>\\1</span></div><div class='panelcd'><p>\\2</p></div></div>", $content);
        }

        if (strpos($content, '{sfqa') !== false) {
            $content = preg_replace('/{sfqa title="(.+?)"}(.+?){\/sfqa}/s', "<div class='baige-sfq-body'><div class='baige-sfq'><span class='baige-sfq-title'>\\1</span></div><div class='panelcd active' style='max-height: 100%;'><p>\\2</p></div></div>", $content);
        }

        if (strpos($content, '{an') !== false) {
            $content = preg_replace('/{an id="(.+?)"}(.+?){\/an}/', "<div class=\"baige-anniu baige-anniu-$1\">$2</div>", $content);
        }

        // 时间线短代码
        if (strpos($content, '{timeline}') !== false) {
            $content = preg_replace_callback('/{timeline}(.*?){\/timeline}/s', function ($matches) {
                $text = $matches[1];

                // 标签
                $text = preg_replace('/<\/?p[^>]*>/i', '', $text);
                $text = preg_replace('/<br\s*\/?>/i', '', $text);
                $text = trim($text);

                // 提取所有年份项
                $currentYear = date('Y');
                $items = [];
                preg_match_all('/{timeline-item year="([^"]+?)"}(.*?){\/timeline-item}/s', $text, $itemMatches, PREG_SET_ORDER);
                foreach ($itemMatches as $m) {
                    $year = trim($m[1]);
                    $body = trim($m[2]);

                    // 解析 item
                    $body = preg_replace_callback('/{item}(.*?){\/item}/s', function($itemMatch) {
                        $contentText = trim($itemMatch[1]);
                        return !empty($contentText) ? "<div class='baige-timeline-content'>{$contentText}</div>" : '';
                    }, $body);

                    $items[] = ['year' => $year, 'body' => $body];
                }

                // 按年份降序排列：新年份在上，旧年份在下
                usort($items, function ($a, $b) {
                    return (int)$b['year'] - (int)$a['year'];
                });

                // 渲染
                $html = '';
                foreach ($items as $it) {
                    $year = $it['year'];
                    $body = $it['body'];
                    $isCurrent = ($year == $currentYear);
                    $stateClass = $isCurrent ? 'is-expanded' : 'is-collapsed';
                    $yearHtml = htmlspecialchars($year);

                    $html .= <<<HTML
<div class="baige-timeline-item {$stateClass}" data-year="{$yearHtml}">
    <div class="baige-timeline-year" title="点击折叠/展开">
        <span class="baige-timeline-toggle">
            {$yearHtml}
            <span class="baige-timeline-icon"></span>
        </span>
    </div>
    <div class="baige-timeline-body">{$body}</div>
</div>
HTML;
                }

                return "<div class='timeline'>{$html}</div>";
            }, $content);
        }

        // 视频选集
        if (strpos($content, '{playlist}') !== false) {
            $content = preg_replace_callback('/{playlist}(.*?){\/playlist}/s', function ($pl_match) {
                $list = trim($pl_match[1]);
                $videos = [];
                $default_pic = "/usr/themes/dage/bai/img/404.webp";

                if (preg_match_all('/{item src="(.+?)"(?: pic="(.+?)")?}(.*?){\/item}/s', $list, $items, PREG_SET_ORDER)) {
                    foreach ($items as $item) {
                        $src = $item[1];
                        $src = str_replace('\\/', '/', $src);
                        $pic = isset($item[2]) && !empty($item[2]) ? $item[2] : $default_pic;
                        $title = isset($item[3]) ? trim($item[3]) : '第'.(count($videos)+1).'集';
                        $videos[] = [
                            'src' => $src,
                            'pic' => $pic,
                            'title' => $title
                        ];
                    }
                }

                if (empty($videos)) return '';
                $first = $videos[0];
                $uid = uniqid();

                $html = '
                <div class="video-wrapper landscape-mode" id="videoWrapper_'.$uid.'">
                    <div class="video-container" id="player-container_'.$uid.'">
                        <div class="video-watermark">皮皮视频</div>
                        <video class="dynamic-width-video" id="video_'.$uid.'" preload="metadata" controls poster="'.$first['pic'].'" oncontextmenu="return false">
                            <source src="'.$first['src'].'">
                        </video>
                    </div>
                </div>
                <div class="playlist-episodes">';

                foreach ($videos as $k => $v) {
                    $active = $k == 0 ? 'active' : '';
                    $html .= '<button class="'.$active.'" onclick="switchVideo_'.$uid.'(this)" data-src="'.$v['src'].'" data-pic="'.$v['pic'].'">'.$v['title'].'</button>';
                }

                $html .= '
                </div>
                <script>
                function switchVideo_'.$uid.'(btn){
                    const container = document.getElementById("player-container_'.$uid.'");
                    const wrapper = document.getElementById("videoWrapper_'.$uid.'");
                    const src = btn.getAttribute("data-src");
                    const pic = btn.getAttribute("data-pic");

                    container.innerHTML = \'<div class="video-watermark">皮皮视频</div><video class="dynamic-width-video" id="video_'.$uid.'" preload="metadata" controls poster="\'+pic+\'" oncontextmenu="return false"><source src="\'+src+\'"></video>\';

                    const video = document.getElementById("video_'.$uid.'");
                    
                    video.play().catch(err => {});

                    setTimeout(()=>{
                        if (video.videoWidth && video.videoHeight) {
                            const ratio = video.videoWidth / video.videoHeight;
                            container.style.paddingBottom = (1/ratio*100) + "%";
                            wrapper.classList.remove("landscape-mode","portrait-mode");
                            ratio>1 ? wrapper.classList.add("landscape-mode") : wrapper.classList.add("portrait-mode");
                        }
                    },300);

                    document.querySelectorAll(".playlist-episodes button").forEach(b=>b.classList.remove("active"));
                    btn.classList.add("active");
                }

                (function(){
                    const video = document.getElementById("video_'.$uid.'");
                    const wrapper = document.getElementById("videoWrapper_'.$uid.'");
                    const container = document.getElementById("player-container_'.$uid.'");
                    const adjust = ()=>{
                        if(video.videoWidth&&video.videoHeight){
                            const r = video.videoWidth/video.videoHeight;
                            container.style.paddingBottom = (1/r*100)+"%";
                            wrapper.classList.remove("landscape-mode","portrait-mode");
                            r>1?wrapper.classList.add("landscape-mode"):wrapper.classList.add("portrait-mode");
                        }
                    };
                    video.addEventListener("loadedmetadata",adjust);
                    window.addEventListener("resize",adjust);
                })();
                </script>';
                return $html;
            }, $content);
        }

        if ($type === false) {
            $content = self::parseFancyBox($content);
        }

        if (Typecho_Widget::widget('Widget_Options')->xmurl == true) {
            $content = self::xmurl($content);
        }

        return $content;
    }

    // 渲染视频标签
    private static function renderVideoTag($src, $pic = "", $width = "100%", $allow_fullscreen = true, $loop = false)
    {
        $defaultPic = "/usr/themes/dage/bai/img/404.webp";
        $pic = empty($pic) ? $defaultPic : $pic;

        $fullscreenAttr = $allow_fullscreen ? "" : "controlsList=\"nodownload nofullscreen\"";
        $loopAttr = $loop ? "loop" : "";

        $uid = uniqid();
        return "
        <div class=\"video-wrapper landscape-mode\" id=\"videoWrapper_{$uid}\">
            <div class=\"video-container\" id=\"player-container_{$uid}\">
                <div class=\"video-watermark\">皮皮视频</div>
                <video class=\"dynamic-width-video\" preload=\"metadata\" controls {$fullscreenAttr} oncontextmenu=\"return false\" poster=\"{$pic}\" {$loopAttr}><source src=\"{$src}\"></video>
            </div>
        </div>
        <script>
        (function() {
            const wrapperId = 'videoWrapper_{$uid}';
            const containerId = 'player-container_{$uid}';
            const videoWrapper = document.getElementById(wrapperId);
            const playerContainer = document.getElementById(containerId);
            const player = playerContainer.querySelector('.dynamic-width-video');
            const adjustPlayerRatio = () => {
                if (player.videoWidth && player.videoHeight) {
                    const nativeRatio = player.videoWidth / player.videoHeight;
                    const paddingBottom = (1 / nativeRatio) * 100 + '%';
                    playerContainer.classList.remove('landscape', 'portrait');
                    videoWrapper.classList.remove('landscape-mode', 'portrait-mode');
                    nativeRatio > 1 ? videoWrapper.classList.add('landscape-mode') : videoWrapper.classList.add('portrait-mode');
                    playerContainer.style.paddingBottom = paddingBottom;
                } else {
                    playerContainer.style.paddingBottom = '56.25%';
                    videoWrapper.classList.add('landscape-mode');
                }
            };
            player.addEventListener('loadedmetadata', adjustPlayerRatio);
            window.addEventListener('DOMContentLoaded', () => setTimeout(adjustPlayerRatio, 300));
            window.addEventListener('resize', adjustPlayerRatio);
            player.addEventListener('error', () => { playerContainer.style.paddingBottom = '56.25%'; });
        })();
        </script>";
    }

    // 图片灯箱
    private static function parseFancyBox($content)
    {
        $options = Typecho_Widget::widget('Widget_Options');
        $archive = Typecho_Widget::widget('Widget_Archive');

        $enableFancybox = $options->fancybox === 'oka' ||
            ($options->fancybox === 'okb' && $archive->is('single')) ||
            ($options->fancybox === 'okc' && ($archive->is('archive') || $archive->is('index')));

        if (strpos($content, '<img') !== false && !preg_match('/data-src="(.*?)">/s', $content)) {
            $reg = '/<img(.*?)src="(.*?)"(.*?)>/s';
            $imgAttr = $options->lazyload ? 'data-src="${2}" src="' . Helper::options()->themeUrl . '/bai/jz.svg"' : 'src="${2}"';
            $replace = $enableFancybox ?
                '<a data-fancybox="gallery" href="${2}"><img class="lazyload" ${1} ' . $imgAttr . '${3}></a>' :
                '<img class="lazyload" ${1} ' . $imgAttr . '${3}>';
            $content = preg_replace($reg, $replace, $content);
        }
        return $content;
    }

    // HTML压缩
    public static function compressHtml($htmlSource)
    {
        $chunks = preg_split('/(<!-- <nocompress> -->.*?<!-- <\/nocompress> -->|<nocompress>.*?<\/nocompress>|<pre.*?\/pre>|<textarea.*?\/textarea>|<script.*?\/script>)/msi', $htmlSource, -1, PREG_SPLIT_DELIM_CAPTURE);
        $compressed = '';
        foreach ($chunks as $chunk) {
            if (strtolower(substr($chunk, 0, 19)) == '<!-- <nocompress> -->') {
                $chunk = substr($chunk, 19, strlen($chunk) - 19 - 20);
                $compressed .= $chunk;
                continue;
            } elseif (strtolower(substr($chunk, 0, 12)) == '<nocompress>') {
                $chunk = substr($chunk, 12, strlen($chunk) - 12 - 13);
                $compressed .= $chunk;
                continue;
            } elseif (strtolower(substr($chunk, 0, 4)) == '<pre' || strtolower(substr($chunk, 0, 9)) == '<textarea') {
                $compressed .= $chunk;
                continue;
            } elseif (strtolower(substr($chunk, 0, 7)) == '<script' && strpos($chunk, '//') != false && (strpos($chunk, "\r") !== false || strpos($chunk, "\n") !== false)) {
                $lines = preg_split('/(\r|\n)/ms', $chunk, -1, PREG_SPLIT_NO_EMPTY);
                $chunk = '';
                foreach ($lines as $line) {
                    if (strpos($line, '//') !== false) {
                        if (substr(trim($line), 0, 2) == '//') continue;
                        $chars = preg_split('//', $line, -1, PREG_SPLIT_NO_EMPTY);
                        $inQuote = $inApostrophe = false;
                        foreach ($chars as $key => $char) {
                            if ($key > 0 && $char == '"' && $chars[$key - 1] != '\\' && !$inApostrophe) {
                                $inQuote = !$inQuote;
                            } elseif ($key > 0 && $char == '\'' && $chars[$key - 1] != '\\' && !$inQuote) {
                                $inApostrophe = !$inApostrophe;
                            } elseif ($char == '/' && isset($chars[$key + 1]) && $chars[$key + 1] == '/' && !$inQuote && !$inApostrophe) {
                                $line = substr($line, 0, $key);
                                break;
                            }
                        }
                    }
                    $chunk .= $line;
                }
            }
            $chunk = preg_replace('/[\\n\\r\\t]+/', ' ', $chunk);
            $chunk = preg_replace('/\\s{2,}/', ' ', $chunk);
            $chunk = preg_replace('/>\\s</', '> <', $chunk);
            $chunk = preg_replace('/\\/\\*.*?\\*\\//i', '', $chunk);
            $chunk = preg_replace('/<!-- [^!]* -->/', '', $chunk);
            $compressed .= $chunk;
        }
        return $compressed;
    }

    // 修复
    public static function xm_indexyl($content)
    {
        if (Typecho_Widget::widget('Widget_Options')->dongtailink) {
            $cacheDir = __TYPECHO_ROOT_DIR__ . '/usr/themes/dage/cache/links/';
            if (!file_exists($cacheDir)) mkdir($cacheDir, 0777, true);
            $cacheFile = $cacheDir . Typecho_Widget::widget('Widget_Options')->clink . '.cache';

            if (file_exists($cacheFile)) {
                $links = json_decode(file_get_contents($cacheFile), true) ?? [];
                $validLinks = array_filter($links, fn($link) => empty($link['norank']));
                $validLinks = array_values($validLinks);
                // 按来访统计排序
                if (function_exists('baigeSortLinksByRank')) {
                    $validLinks = baigeSortLinksByRank($validLinks);
                }
                $limitedLinks = array_slice($validLinks, 0, 10);
                $linkHtml = '';
                foreach ($limitedLinks as $link) {
                    $linkHtml .= '<a href="' . htmlspecialchars($link['url']) . '" class="baige-sideA baige-txt-ellipsis">' . htmlspecialchars($link['title']) . '</a>';
                }
                return $linkHtml;
            }
        } else {
            preg_match('/{index title="(.+?)"}(.+?){\/index}/', $content, $match);
            if ($match) {
                $linkContent = $match[0];
                $linkContent = preg_replace_callback('/{index title="(.+?)"}(.+?){\/index}/', fn($m) => str_replace(['<br>', '<p>', '</p>'], '', $m[2]), $linkContent);
                $linkContent = preg_replace('/{an id="(.+?)"}(.+?){\/an}/', '\\2', $linkContent);
                $linkContent = preg_replace('/{fyindex title=".+?"}.*?{\/fyindex}/is', '', $linkContent);
                $linkContent = preg_replace('/{nolinkindex title=".+?"}.*?{\/nolinkindex}/is', '', $linkContent);
                $linkContent = preg_replace('/{linkc url="(.+?)" img="(.+?)"}(.+?){\/linkc m="(.+?)"}/', '<a href="\\1" class="baige-sideA baige-txt-ellipsis">\\3</a>', $linkContent);
                return $linkContent;
            }
        }
        return '';
    }

    // 清理友链UBB
    public static function xm_qklink($content)
    {
        $content = preg_replace('/{fyindex title=".+?"}.*?{\/fyindex}/is', '', $content);
        $content = preg_replace('/{nolinkindex title=".+?"}.*?{\/nolinkindex}/is', '', $content);
        if (strpos($content, '{index') !== false) {
            $content = preg_replace_callback('/{index title="(.+?)"}(.+?){\/index}/', fn($m) => str_replace(['<br>', '<p>', '</p>'], '', $m[2]), $content);
        }
        if (strpos($content, '{linkc') !== false) {
            $content = preg_replace('/{linkc url="(.+?)" img="(.+?)"}(.+?){\/linkc m="(.+?)"}/', '', $content);
        }
        return $content;
    }

    // 友链缓存 / 来访统计
    public static function xm_linkc()
    {
        define('CACHE_DIR', __TYPECHO_ROOT_DIR__ . '/usr/themes/dage/cache/links/');
        if (!file_exists(CACHE_DIR)) mkdir(CACHE_DIR, 0777, true);
        $cacheFile = CACHE_DIR . Typecho_Widget::widget('Widget_Options')->clink . '.cache';

        if (file_exists($cacheFile)) {
            $visitorUrl = $_GET['from'] ?? $_SERVER['HTTP_REFERER'] ?? '';
            $links = json_decode(file_get_contents($cacheFile), true) ?? [];

            // 合并数据库中已审核的自助申请友链，确保它们也能被统计到来访
            $options = Typecho_Widget::widget('Widget_Options');
            if (isset($options->friend_link_switch) && $options->friend_link_switch === 'ok' && function_exists('baigeGetApprovedLinks')) {
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

            // 使用 functions.php 中的统一统计函数：规范化域名、反刷、持久化计数
            if (function_exists('baigeRecordLinkClick')) {
                baigeRecordLinkClick($visitorUrl, $links);
            }
        }
    }

    // 友链UBB解析
    public static function xm_yl($content)
    {
        if (strpos($content, '{index') !== false) {
            $content = preg_replace_callback('/{index title="(.+?)"}(.+?){\/index}/', function ($match) {
                $c = str_replace(['<br>', '<p>', '</p>'], '', $match[2]);
                return '<div class="link_body baige-tm-background"><div class="link_title">' . $match[1] . '</div><div class="baige-cess">' . $c . '</div></div>';
            }, $content);
        }
        if (strpos($content, '{linkc') !== false) {
            $content = preg_replace('/{linkc url="(.+?)" img="(.+?)"}(.+?){\/linkc m="(.+?)"}/', '<div class="baige-linkc-body baige-txt-ellipsis"><div class="baige-linkc-zuo"><div class="baige-linkc-title baige-txt-ellipsis"><a href="\\1" target="_blank">\\3</a></div><div class="baige-linkc-ms baige-txt-ellipsis">\\4</div></div><div class="baige-linkc-you"><a href="\\1" target="_blank"><img src="\\2"></a></div></div>', $content);
        }
        return $content;
    }

    // 主题资源
    public static function ThemeUrl($path = '')
    {
        return Helper::options()->themeUrl . $path;
    }

    // 主题设置备份
    public static function BackSet()
    {
        $themeName = Helper::options()->theme;
        $db = Typecho_Db::get();
        $current = $db->fetchRow($db->select()->from('table.options')->where('name = ?', 'theme:' . $themeName));
        $val = $current['value'] ?? '';

        if (isset($_POST['type'])) {
            if ($_POST["type"] == "备份") {
                if ($db->fetchRow($db->select()->from('table.options')->where('name = ?', 'theme:' . $themeName . 'bf'))) {
                    $db->query($db->update('table.options')->rows(array('value' => $val))->where('name = ?', 'theme:' . $themeName . 'bf'));
                } else {
                    $db->query($db->insert('table.options')->rows(array('name' => 'theme:' . $themeName . 'bf', 'user' => '0', 'value' => $val)));
                }
                exit("<script>alert('备份成功！');history.go(-1);</script>");
            }
            if ($_POST["type"] == "还原") {
                if ($bf = $db->fetchRow($db->select()->from('table.options')->where('name = ?', 'theme:' . $themeName . 'bf'))) {
                    $db->query($db->update('table.options')->rows(array('value' => $bf['value']))->where('name = ?', 'theme:' . $themeName));
                    exit("<script>alert('还原成功！');history.go(-1);</script>");
                } else {
                    exit("<script>alert('无备份');history.go(-1);</script>");
                }
            }
            if ($_POST["type"] == "删除") {
                $db->query($db->delete('table.options')->where('name = ?', 'theme:' . $themeName . 'bf'));
                exit("<script>alert('删除成功');history.go(-1);</script>");
            }
        }
    }

    // 备份状态
    public static function CheckSetBack()
    {
        $themeName = Helper::options()->theme;
        $db = Typecho_Db::get();
        return $db->fetchRow($db->select()->from('table.options')->where('name = ?', 'theme:' . $themeName . 'bf')) ? '<span style="color:#1462ff">已备份</span>' : '<span style="color:red">未备份</span>';
    }

    // 统计文章数（改用 COUNT 查询，减少内存占用）
    public static function getNumPosts($days)
    {
        $db = Typecho_Db::get();
        $row = $db->fetchRow(
            $db->select('COUNT(*) AS total')
                ->from('table.contents')
                ->where('status = ?', 'publish')
                ->where('type = ?', 'post')
                ->where('modified >= ?', time() - $days * 86400)
        );
        return $row['total'] ?? 0;
    }

    // 总访问量（带文件缓存，避免每次请求都执行全表 SUM）
    public static function theAllViews()
    {
        $cacheFile = __DIR__ . '/../cache/all_views.cache';
        $ttl = 300; // 5 分钟
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
            return file_get_contents($cacheFile);
        }

        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $row = $db->fetchAll('SELECT SUM(`views`) AS total FROM `' . $prefix . 'contents`');
        $total = number_format($row[0]['total'] ?? 0);

        @file_put_contents($cacheFile, $total);
        return $total;
    }

    // 获取页面
    public static function cid(int $cid)
    {
        Typecho_Widget::widget('Widget_Archive@cid' . $cid, 'pageSize=1&type=page', 'cid=' . $cid)->to($opt);
        return $opt;
    }

    // 文章摘要
    public static function getArticleSummary($that, $maxLen = 120, $a = true, $b = false, $c = true)
    {
        $text = preg_replace(['/<(.+?)>(.+?)<\/(.+?)>/', '/<(.+?)>(.+?)/'], '$2', $that->content);
        if ($a) $text = self::biaoqing(htmlspecialchars($text));
        else $text = $text;
        if ($b && $c) $text = preg_replace('/{secret}(.*?){\/secret}/sm', '私密评论', $text);
        $text = Typecho_Common::fixHtml(Typecho_Common::subStr($text, 0, $maxLen, "..."));
        $text = htmlspecialchars($text);
        return $text;
    }
}

// 标签
#[AllowDynamicProperties]
class EchoHtml extends Typecho_Widget_Helper_Layout
{
    public function __construct($html)
    {
        $this->html($html);
    }
    public function start() {}
    public function end() {}
}

// 列表页隐藏UBB
Typecho_Plugin::factory('Widget_Abstract_Contents')->excerptEx = array('myyodux', 'one');
Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx = array('myyodux', 'one');

#[AllowDynamicProperties]
class myyodux
{
    public static function one($c, $obj, $text)
    {
        $t = empty($text) ? $c : $text;
        if (!$obj->is('single')) {
            $t = preg_replace([
                '/{sfq title="(.+?)"}(.+?){\/sfq}/sm',
                '/{an id="(.+?)"}(.+?){\/an}/sm',
                '/{sfqa title="(.+?)"}(.+?){\/sfqa}/sm',
                '/{bl av="(.+?)"}/sm',
                '/{bl bv="(.+?)"}/sm',
                '/{mp3 src="(.+?)"}/sm',
                '/{login}(.+?){\/login}/sm',
                '/{ts id="(.+?)"}(.+?){\/ts}/sm',
                "/{hide}(.*?){\/hide}/sm",
                '/{center}(.+?){\/center}/sm',
                '/{video src="(.+?)"(?: pic="(.+?)")?}/sm',
                '/{index title="(.+?)"}(.+?){\/index}/sm',
                '/{linkc url="(.+?)" img="(.+?)"}(.+?){\/linkc m="(.+?)"}/sm',
                '/{secret}(.+?){\/secret}/sm',
                '/{br}/sm',
                '/{timeline}(.*?){\/timeline}/s',
                '/{playlist}(.*?){\/playlist}/s',
                '/\:\s*\(\s*(.*?)\s*\)/is'
            ], '', $t);
        }
        return $t;
    }
}