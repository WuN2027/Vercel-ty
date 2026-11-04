<?php
// 引入音乐短代码文件
$musicShortcodeFile = __DIR__ . '/music-shortcode.php';
if (file_exists($musicShortcodeFile)) {
    require_once $musicShortcodeFile;
    if (function_exists('registerMusicShortcode')) {
        registerMusicShortcode();
    }
}
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 启动 session，供音乐盒密码保护跨页面使用
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 后台友链管理操作处理
if (isset($_GET['friend_link_admin_action']) && strpos($_SERVER['REQUEST_URI'], 'options-theme.php') !== false) {
    $user = Typecho_Widget::widget('Widget_User');
    if ($user->hasLogin() && $user->group === 'administrator') {
        $action = $_GET['friend_link_admin_action'];
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        switch ($action) {
            case 'approve':
                baigeUpdateLinkStatus($id, 1);
                break;
            case 'reject':
                baigeUpdateLinkStatus($id, 2);
                break;
            case 'delete':
                baigeDeleteLink($id);
                break;
        }
    }
    $backUrl = Helper::options()->adminUrl . 'options-theme.php';
    header('Location: ' . $backUrl);
    exit;
}

// 日志保存
function getSecurityLogFile() {
    $cacheDir = __DIR__ . '/cache/';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    return $cacheDir . 'security_log.json';
}
function initSecurityData() {
    $file = getSecurityLogFile();
    if (!file_exists($file)) {
        file_put_contents($file, json_encode([
            'total' => 0, 'today' => 0, 'last_date' => date('Y-m-d'), 'logs' => []
        ]));
    }
}
function addSecurityLog($type, $ip, $msg) {
    initSecurityData();
    $data = json_decode(file_get_contents(getSecurityLogFile()), true);
    if ($data['last_date'] != date('Y-m-d')) {
        $data['today'] = 0;
        $data['last_date'] = date('Y-m-d');
    }
    $data['total']++; $data['today']++;
    array_unshift($data['logs'], [
        'time' => date('Y-m-d H:i:s'), 'type' => $type, 'ip' => $ip, 'msg' => $msg
    ]);
    $data['logs'] = array_slice($data['logs'], 0, 10);
    file_put_contents(getSecurityLogFile(), json_encode($data, JSON_UNESCAPED_UNICODE));
}
function getSecurityStats() {
    initSecurityData();
    return json_decode(file_get_contents(getSecurityLogFile()), true);
}

// 基础配置
define("XM_TPL", Helper::options()->rootUrl . __TYPECHO_THEME_DIR__ . '/' . Helper::options()->theme . '/');
$GLOBALS['version'] = Typecho_Plugin::parseInfo(__DIR__ . '/index.php');

// 域名黑名单
function checkRefererBlacklist() {
    $options = Helper::options();

    if (empty($options->opt_referer) || $options->opt_referer === 'none') {
        return;
    }

    $referer = isset($_SERVER['HTTP_REFERER']) ? trim($_SERVER['HTTP_REFERER']) : '';
    if ($referer === '') {
        return;
    }

    $blacklist = empty($options->words_referer) ? [] : explode("\n", $options->words_referer);
    $blacklist = array_filter(array_map('trim', $blacklist));
    if (empty($blacklist)) {
        return;
    }

    $refererHost = strtolower(parse_url($referer, PHP_URL_HOST) ?: '');
    if ($refererHost === '') {
        return;
    }

    $isBlocked = false;
    foreach ($blacklist as $domain) {
        $domain = strtolower(trim($domain));
        if ($domain === '') continue;
        $pattern = str_replace(['.', '*'], ['\.', '.*'], $domain);
        if (preg_match("/{$pattern}$/", $refererHost)) {
            $isBlocked = true;
            break;
        }
    }

    if ($isBlocked) {
        if (function_exists('addSecurityLog')) {
            addSecurityLog('域名拦截', $_SERVER['REMOTE_ADDR'], "来路：{$refererHost}");
        }

        $tip = !empty($options->referer_block_tip) ? $options->referer_block_tip : '禁止从该域名访问';
        // 读取后台主题背景色设置
        $isDark = ($options->anhei === 'ok');

        if ($options->opt_referer === 'forbid') {
            header('HTTP/1.1 403 Forbidden');
            // 区分明暗两套样式
            if($isDark){
                $bodyBg = '#121212';
                $boxBg = '#1e1e1e';
                $textCol = '#cccccc';
                $borderCol = '#333333';
            }else{
                $bodyBg = '#f5f5f5';
                $boxBg = '#ffffff';
                $textCol = '#666666';
                $borderCol = '#eeeeee';
            }
            echo '<!DOCTYPE html>
            <html lang="zh-CN">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width,initial-scale=1">
                <title>访问被拒绝</title>
                <style>
                    *{margin:0;padding:0;box-sizing:border-box;}
                    body{
                        margin:0;padding:50px;font-family:Arial;
                        background:'.$bodyBg.' !important;
                        background-image:none !important;
                    }
                    .container{
                        max-width:600px;margin:0 auto;
                        background:'.$boxBg.';
                        padding:40px;border-radius:8px;
                        box-shadow:0 2px 10px rgba(0,0,0,0.15);
                        border:1px solid '.$borderCol.';
                    }
                    h1{color:#dc2626;font-size:24px;margin-bottom:20px;}
                    p{color:'.$textCol.';font-size:16px;line-height:1.6;margin:8px 0;}
                </style>
            </head>
            <body>
                <div class="container">
                    <h1>🚫 访问被拒绝</h1>
                    <p>' . htmlspecialchars($tip) . '</p>
                    <p>请直接输入网址访问本站。</p>
                </div>
            </body>
            </html>';
            exit;
        } elseif ($options->opt_referer === 'redirect') {
            header('Location: ' . Helper::options()->siteUrl);
            exit;
        }
    }
}
checkRefererBlacklist();

// ========== 音乐盒密码保护全局逻辑 ==========
function getMusicBoxPasswordState()
{
    $options = Helper::options();
    $switch = ($options->music_password_switch ?? '0') === '1';
    $password = trim((string)($options->music_password ?? ''));
    $tip = trim((string)($options->music_password_tip ?? '请输入密码访问音乐盒'));
    $tip = $tip ?: '请输入密码访问音乐盒';

    $unlocked = false;
    try {
        $user = Typecho_Widget::widget('Widget_User');
        if ($user->hasLogin() && $user->pass('administrator')) {
            $unlocked = true;
        }
    } catch (Exception $e) {
        $unlocked = false;
    }

    if (!$unlocked && !empty($_SESSION['pipi_music_unlocked']) && $_SESSION['pipi_music_unlocked'] === true) {
        $unlocked = true;
    }

    return [$switch, $password, $tip, $unlocked];
}

// 全局处理密码校验 AJAX（音乐盒页面、文章页播放器共用）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'music_password_verify') {
    header('Content-Type: application/json; charset=utf-8');
    list($musicPasswordSwitch, $musicPassword, $musicPasswordTip, $musicBoxUnlocked) = getMusicBoxPasswordState();
    $inputPassword = trim($_POST['password'] ?? '');

    if (!$musicPasswordSwitch || $musicPassword === '') {
        echo json_encode(['success' => true, 'msg' => '密码保护未开启']);
        exit;
    }

    if ($inputPassword === $musicPassword) {
        $_SESSION['pipi_music_unlocked'] = true;
        echo json_encode(['success' => true, 'msg' => '密码正确']);
    } else {
        echo json_encode(['success' => false, 'msg' => '密码错误，请重试']);
    }
    exit;
}

// 输出音乐盒密码弹窗与全局校验脚本（整页仅输出一次）
function pipi_music_password_assets()
{
    static $output = false;
    if ($output) return;
    $output = true;

    list($mbSwitch, $mbPassword, $mbTip, $mbUnlocked) = getMusicBoxPasswordState();

    $switchJson = $mbSwitch ? 'true' : 'false';
    $verifiedJson = $mbUnlocked ? 'true' : 'false';
    $tipJson = json_encode($mbTip, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $lockIconUrl = XM_TPL . 'bai/img/music-lock.svg';
    $modalHtml = '';
    if ($mbSwitch && !$mbUnlocked) {
        $modalHtml = <<<MODAL
<div id="music-box-password-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.55);z-index:9999;align-items:center;justify-content:center;backdrop-filter:blur(2px);">
    <div style="background:var(--bj-zhuse);padding:30px 25px;border-radius:10px;max-width:360px;width:90%;text-align:center;box-shadow:0 10px 30px rgba(0,0,0,0.2);box-sizing:border-box;">
        <img src="{$lockIconUrl}" alt="音乐锁" style="width:48px;height:48px;margin-bottom:12px;">
        <div id="music-box-password-modal-tip" style="margin-bottom:15px;color:var(--color-huise);font-size:var(--font-size-base);line-height:1.5;">{$mbTip}</div>
        <input type="password" id="music-box-password-modal-input" placeholder="请输入密码" style="width:100%;height:42px;padding:0 14px;margin-bottom:10px;border:1px solid var(--bj-cifuse);border-radius:6px;background:#fff;color:var(--color-chunhei);font-size:16px;outline:none;box-sizing:border-box;">
        <div style="display:flex;gap:10px;width:100%;">
            <button type="button" id="music-box-password-modal-submit" style="flex:1;height:42px;border:none;border-radius:6px;background:var(--bj-fuse);color:#fff;font-size:16px;cursor:pointer;">确认</button>
            <button type="button" id="music-box-password-modal-close" style="flex:1;height:42px;border:none;border-radius:6px;background:#e5e7eb;color:#374151;font-size:16px;cursor:pointer;">离开</button>
        </div>
        <div id="music-box-password-modal-error" style="color:#dc2626;margin-top:10px;font-size:var(--font-size-sm);min-height:18px;"></div>
    </div>
</div>
MODAL;
    }

    echo $modalHtml;
    echo <<<SCRIPT
<script>
(function () {
    if (window.pipiMusicBoxPassword) return;
    window.pipiMusicBoxPassword = {
        switch: {$switchJson},
        verified: {$verifiedJson},
        tip: {$tipJson}
    };
    window.checkMusicBoxPassword = function (onSuccess) {
        const state = window.pipiMusicBoxPassword;
        if (!state.switch || state.verified) {
            onSuccess();
            return;
        }
        const modal = document.getElementById('music-box-password-modal');
        if (!modal) {
            onSuccess();
            return;
        }
        const input = document.getElementById('music-box-password-modal-input');
        const submit = document.getElementById('music-box-password-modal-submit');
        const closeBtn = document.getElementById('music-box-password-modal-close');
        const error = document.getElementById('music-box-password-modal-error');
        const tip = document.getElementById('music-box-password-modal-tip');
        if (tip) tip.textContent = state.tip || '请输入密码';
        modal.style.display = 'flex';
        if (input) {
            input.value = '';
            input.focus();
        }
        if (error) error.textContent = '';

        modal.onclick = function (e) {
            if (e.target === modal) {
                modal.style.display = 'none';
                cleanup();
            }
        };

        const cleanup = function () {
            if (submit) submit.onclick = null;
            if (closeBtn) closeBtn.onclick = null;
            if (input) input.onkeyup = null;
            modal.onclick = null;
        };

        if (closeBtn) {
            closeBtn.onclick = function () {
                modal.style.display = 'none';
                cleanup();
            };
        }

        const doVerify = function () {
            const password = input ? input.value.trim() : '';
            if (!password) {
                if (error) error.textContent = '请输入密码';
                return;
            }
            if (submit) {
                submit.disabled = true;
                submit.textContent = '校验中...';
            }
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=music_password_verify&password=' + encodeURIComponent(password)
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (submit) {
                    submit.disabled = false;
                    submit.textContent = '确认';
                }
                if (data.success) {
                    state.verified = true;
                    modal.style.display = 'none';
                    cleanup();
                    onSuccess();
                } else {
                    if (error) error.textContent = data.msg || '密码错误';
                }
            })
            .catch(function (err) {
                console.error('密码校验失败:', err);
                if (submit) {
                    submit.disabled = false;
                    submit.textContent = '确认';
                }
                if (error) error.textContent = '校验失败，请重试';
            });
        };

        if (submit) submit.onclick = doVerify;
        if (input) input.onkeyup = function (e) { if (e.key === 'Enter') doVerify(); };
    };
})();
</script>
SCRIPT;
}
// ========== 音乐盒密码保护全局逻辑结束 ==========

// 加载核心类库
require_once 'lib/xm.class.php';

// 注册钩子
Typecho_Plugin::factory('Widget_Feedback')->comment = ['CommentFilter', 'handle'];
Typecho_Plugin::factory('Widget_Feedback')->comment_20 = ['xm', 'insertSecret'];
// 输出
Typecho_Plugin::factory('Widget_Feedback')->finishComment = ['CommentFilter', 'handleEditFinish'];
Typecho_Plugin::factory('admin/write-post.php')->bottom = ['editor', 'reset'];
Typecho_Plugin::factory('admin/write-page.php')->bottom = ['editor', 'reset'];

Typecho_Plugin::factory('Widget_Abstract_Comments')->contentEx = array('CommentFilter', 'exceptFeed');

// 评论过滤
class CommentFilter {

    // 全局静态内存缓存限流
    private static $ipLimitCache = [];
    // 标记当前评论是否被设为待审核状态
    public static $isAuditWaiting = false;

    public static function exceptFeed($con, $obj, $text)
    {
        $text = empty($text) ? $con : $text;
        $user = Typecho_Widget::widget('Widget_User');

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
            $userMail = Typecho_Widget::widget('Widget_Archive')->remember('mail', true);
        }

        if (!$canView && $userMail) {
            $commentMail = $obj->mail ?? '';
            // 评论作者本人可见
            if ($commentMail && $commentMail === $userMail) {
                $canView = true;
            }
            // 被回复的评论作者本人可见
            if (!$canView && !empty($obj->parent) && !empty($obj->coid)) {
                $parentMail = xm::get_comment_at($obj->coid);
                if ($parentMail && $parentMail === $userMail) {
                    $canView = true;
                }
            }
        }

        // 仅评论者/被回复者/管理员保留 {secret} 标签，交给 simi() 解析显示真实内容
        if ($canView) {
            return $text;
        }
        $text = preg_replace("/{secret}(.*?){\/secret}/sm", '<span class="iconfont icon-jingbao iconcolor11"></span> 这是一条私密内容哦', $text);
        return $text;
    }

    public static function handle($comment, $widget) {
        $options = Helper::options();
        $ip = $_SERVER['REMOTE_ADDR'];
        $content = trim($comment['text'] ?? '');

        // 自动审核（不受安全总开关影响，单独在审核管控中设置）
        if (!empty($options->comment_auto_audit)) {
            // 已登录管理员免审核
            $isAdmin = false;
            try {
                $user = Typecho_Widget::widget('Widget_User');
                $isAdmin = $user->hasLogin() && $user->group === 'administrator';
            } catch (Exception $e) {
                $isAdmin = false;
            }
            if ($isAdmin) {
                return $comment;
            }

            $postAuthorId = 0;
            if (isset($widget->content) && is_object($widget->content) && isset($widget->content->authorId)) {
                $postAuthorId = (int)$widget->content->authorId;
            }
            $currentUserId = isset($comment['authorId']) ? (int)$comment['authorId'] : 0;

            // 不审核文章作者自己
            if ($currentUserId <= 0 || $currentUserId !== $postAuthorId) {
                $auditWords = [];
                // 后端自定义敏感词
                if (!empty($options->auto_audit_words)) {
                    $auditWords = array_filter(array_map('trim', explode("\n", $options->auto_audit_words)));
                }
                // 同时合并主题内置默认词库
                $defaultWordsFile = __DIR__ . '/lib/auto-audit-words.txt';
                if (file_exists($defaultWordsFile)) {
                    $defaultWords = file($defaultWordsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    $defaultWords = array_filter(array_map('trim', $defaultWords), function($line) {
                        return $line !== '' && strpos($line, '#') !== 0;
                    });
                    $auditWords = array_merge($auditWords, $defaultWords);
                }

                foreach ($auditWords as $word) {
                    if (!empty($word) && stripos($content, $word) !== false) {
                        addSecurityLog('自动审核', $ip, "命中敏感词：{$word}");
                        $comment['status'] = 'waiting';
                        self::$isAuditWaiting = true;
                        break;
                    }
                }
            }
        }

        if (!$options->security_switch) return $comment;

        // 1. IP 黑名单
        if (!empty($options->words_ip)) {
            $blackIps = array_filter(array_map('trim', explode("\n", $options->words_ip)));
            foreach ($blackIps as $rule) {
                if (empty($rule)) continue;
                // 将 * 转为正则 \d+ 匹配数字段
                $reg = '/^' . str_replace(['.', '*'], ['\.', '\d+'], $rule) . '$/';
                if (preg_match($reg, $ip)) {
                    addSecurityLog('IP拦截', $ip, "命中IP规则：{$rule}");
                    echo "BLOCKED_IP";
                    exit;
                }
            }
        }

        // 评论频率限制
        $limit = (int)($options->comment_frequency ?: 10);
        $now = time();
        if (isset(self::$ipLimitCache[$ip]) && $now - self::$ipLimitCache[$ip] < $limit) {
            addSecurityLog('频率拦截', $ip, "{$limit}秒内重复评论");
            echo "FREQUENT_LIMIT";
            exit;
        }
        self::$ipLimitCache[$ip] = $now;

        // 3. 空评论检测
        $clean = preg_replace('/\s+|[^\w\p{Han}]/u', '', $content);
        if (mb_strlen($clean) < 2) {
            addSecurityLog('空评论', $ip, '评论为空/纯符号');
            echo "EMPTY_COMMENT";
            exit;
        }

        // 4. 必须包含中文
        $hasChinese = preg_match('/[\p{Han}]/u', $content);
        if (!$hasChinese) {
            addSecurityLog('无中文', $ip, '评论不含中文');
            echo "NEED_CHINESE";
            exit;
        }

        // 5. 违规关键词拦截
        if (!empty($options->words_keyword)) {
            $badWords = array_filter(array_map('trim', explode("\n", $options->words_keyword)));
            foreach ($badWords as $word) {
                if (!empty($word) && stripos($content, $word) !== false) {
                    addSecurityLog('违规词', $ip, "包含违禁词：{$word}");
                    echo "HAS_BAD_WORD";
                    exit;
                }
            }
        }

        // 修复
        // 6. 禁止邮箱黑名单匹配（支持完整邮箱、* 通配符、纯域名）
        if (!empty($options->words_mail)) {
            $blackMails = array_filter(array_map('trim', explode("\n", $options->words_mail)));
            $userMail = strtolower(trim($comment['mail'] ?? ''));
            foreach ($blackMails as $banMail) {
                $banMail = strtolower(trim($banMail));
                if (empty($banMail)) continue;

                $matched = false;
                if (strpos($banMail, '*') !== false) {
                    // 通配符匹配，* 代表任意字符
                    $reg = '/^' . str_replace(['\\*', '\\.'], ['.*', '\\.'], preg_quote($banMail, '/')) . '$/i';
                    $matched = preg_match($reg, $userMail);
                } elseif (strpos($banMail, '@') === false) {
                    // 纯域名，匹配邮箱后缀
                    $atPos = strrpos($userMail, '@');
                    $matched = ($atPos !== false && substr($userMail, $atPos + 1) === $banMail);
                } else {
                    // 完整邮箱精确匹配
                    $matched = ($userMail === $banMail);
                }

                if ($matched) {
                    addSecurityLog('邮箱拦截', $ip, "命中禁用邮箱规则：{$banMail}（{$userMail}）");
                    echo "BLOCKED_MAIL";
                    exit;
                }
            }
        }

        // 7. 禁止用户名称黑名单匹配
        if (!empty($options->words_author)) {
            $blackAuthors = array_filter(array_map('trim', explode("\n", $options->words_author)));
            $userAuthor = strtolower(trim($comment['author'] ?? ''));
            foreach ($blackAuthors as $banAuthor) {
                $banAuthor = strtolower(trim($banAuthor));
                if (empty($banAuthor)) continue;
                if (self::matchBlackRule($userAuthor, $banAuthor)) {
                    addSecurityLog('用户名拦截', $ip, "命中禁用用户名规则：{$banAuthor}（{$userAuthor}）");
                    echo "BLOCKED_AUTHOR";
                    exit;
                }
            }
        }

        // 8. 禁止评论者网址/域名
        if (!empty($options->words_url)) {
            $blackUrls = array_filter(array_map('trim', explode("\n", $options->words_url)));
            $userUrl = strtolower(trim($comment['url'] ?? ''));
            if ($userUrl !== '') {
                $host = parse_url($userUrl, PHP_URL_HOST);
                if (empty($host)) {
                    $host = parse_url('https://' . $userUrl, PHP_URL_HOST);
                }
                if (!empty($host)) {
                    foreach ($blackUrls as $banUrl) {
                        $banUrl = strtolower(trim($banUrl));
                        if (empty($banUrl)) continue;

                        // 如果填写的是完整 URL，先提取域名
                        $ruleHost = parse_url($banUrl, PHP_URL_HOST);
                        if (empty($ruleHost)) {
                            $ruleHost = parse_url('https://' . $banUrl, PHP_URL_HOST);
                        }
                        $ruleHost = $ruleHost ?: $banUrl;

                        $matched = false;
                        if (strpos($ruleHost, '*') !== false) {
                            $reg = '/^' . str_replace(['\\*', '\\.'], ['.*', '\\.'], preg_quote($ruleHost, '/')) . '$/i';
                            $matched = preg_match($reg, $host);
                        } else {
                            $matched = ($host === $ruleHost);
                        }

                        if ($matched) {
                            addSecurityLog('网址拦截', $ip, "命中禁用网址规则：{$banUrl}（{$host}）");
                            echo "BLOCKED_URL";
                            exit;
                        }
                    }
                }
            }
        }

        return $comment;
    }

    /**
     * 通用黑名单匹配（支持 * 通配符和精确匹配）
     */
    private static function matchBlackRule($value, $rule) {
        if (strpos($rule, '*') !== false) {
            $reg = '/^' . str_replace(['\\*', '\\.'], ['.*', '\\.'], preg_quote($rule, '/')) . '$/i';
            return preg_match($reg, $value);
        }
        return $value === $rule;
    }

    // finishComment 钩子
    // 输出
    public static function handleEditFinish($widget) {
        if (self::$isAuditWaiting) {
            $tip = Helper::options()->auto_audit_tip ?: '评论已提交，正在审核中，审核通过后显示';
            echo 'COMMENT_NEED_AUDIT|' . $tip;
            exit;
        }
    }
}



// 读取纯真 IP 库获取归属地
function convertip($ip){
    $ip1num = 0;
    $ip2num = 0;
    $ipAddr1 = "";
    $ipAddr2 = "";
    $dat_path = __DIR__ . '/lib/qqwry.dat';
    if(!preg_match("/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/", $ip)) {
        return '';
    }
    if(!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE)){
        return '';
    }
    if(!$fd = @fopen($dat_path, 'rb')){
        return '';
    }
    $ip = explode('.', $ip);
    $ipNum = $ip[0] * 16777216 + $ip[1] * 65536 + $ip[2] * 256 + $ip[3];
    $DataBegin = fread($fd, 4);
    $DataEnd = fread($fd, 4);
    $ipbegin = implode('', unpack('L', $DataBegin));
    if($ipbegin < 0) $ipbegin += pow(2, 32);
    $ipend = implode('', unpack('L', $DataEnd));
    if($ipend < 0) $ipend += pow(2, 32);
    $ipAllNum = ($ipend - $ipbegin) / 7 + 1;
    $BeginNum = 0;
    $EndNum = $ipAllNum;
    while($ip1num>$ipNum || $ip2num<$ipNum) {
        $Middle= intval(($EndNum + $BeginNum) / 2);
        fseek($fd, $ipbegin + 7 * $Middle);
        $ipData1 = fread($fd, 4);
        if(strlen($ipData1) < 4) {
            fclose($fd);
            return '';
        }
        $ip1num = implode('', unpack('L', $ipData1));
        if($ip1num < 0) $ip1num += pow(2, 32);

        if($ip1num > $ipNum) {
            $EndNum = $Middle;
            continue;
        }
        $DataSeek = fread($fd, 3);
        if(strlen($DataSeek) < 3) {
            fclose($fd);
            return '';
        }
        $DataSeek = implode('', unpack('L', $DataSeek.chr(0)));
        fseek($fd, $DataSeek);
        $ipData2 = fread($fd, 4);
        if(strlen($ipData2) < 4) {
            fclose($fd);
            return '';
        }
        $ip2num = implode('', unpack('L', $ipData2));
        if($ip2num < 0) $ip2num += pow(2, 32);
        if($ip2num < $ipNum) {
            if($Middle == $BeginNum) {
                fclose($fd);
                return '';
            }
            $BeginNum = $Middle;
        }
    }
    $ipFlag = fread($fd, 1);
    if($ipFlag == chr(1)) {
        $ipSeek = fread($fd, 3);
        if(strlen($ipSeek) < 3) {
            fclose($fd);
            return '';
        }
        $ipSeek = implode('', unpack('L', $ipSeek.chr(0)));
        fseek($fd, $ipSeek);
        $ipFlag = fread($fd, 1);
    }
    if($ipFlag == chr(2)) {
        $AddrSeek = fread($fd, 3);
        if(strlen($AddrSeek) < 3) {
            fclose($fd);
            return '';
        }
        $ipFlag = fread($fd, 1);
        if($ipFlag == chr(2)) {
            $AddrSeek2 = fread($fd, 3);
            if(strlen($AddrSeek2) < 3) {
                fclose($fd);
                return '';
            }
            $AddrSeek2 = implode('', unpack('L', $AddrSeek2.chr(0)));
            fseek($fd, $AddrSeek2);
        } else {
            fseek($fd, -1, SEEK_CUR);
        }
        while(($char = fread($fd, 1)) != chr(0))
            $ipAddr2 .= $char;
        $AddrSeek = implode('', unpack('L', $AddrSeek.chr(0)));
        fseek($fd, $AddrSeek);
        while(($char = fread($fd, 1)) != chr(0))
            $ipAddr1 .= $char;
    } else {
        fseek($fd, -1, SEEK_CUR);
        while(($char = fread($fd, 1)) != chr(0))
            $ipAddr1 .= $char;
        $ipFlag = fread($fd, 1);
        if($ipFlag == chr(2)) {
            $AddrSeek2 = fread($fd, 3);
            if(strlen($AddrSeek2) < 3) {
                fclose($fd);
                return '';
            }
            $AddrSeek2 = implode('', unpack('L', $AddrSeek2.chr(0)));
            fseek($fd, $AddrSeek2);
        } else {
            fseek($fd, -1, SEEK_CUR);
        }
        while(($char = fread($fd, 1)) != chr(0)){
            $ipAddr2 .= $char;
        }
    }
    fclose($fd);
    if(preg_match('/http/i', $ipAddr2)) {
        $ipAddr2 = '';
    }
    $ipaddr = "$ipAddr1";
    $ipaddr = preg_replace('/CZ88.NET/is', '', $ipaddr);
    $ipaddr = trim($ipaddr);
    if(preg_match('/http/i', $ipaddr) || $ipaddr == '') {
        return '';
    }
    if (function_exists('iconv')) {
        $ipaddr = @iconv('gbk', 'utf-8//IGNORE', $ipaddr);
    } elseif (function_exists('mb_convert_encoding')) {
        $ipaddr = @mb_convert_encoding($ipaddr, 'UTF-8', 'GBK');
    }
    return $ipaddr;
}

// 从完整归属地中提取省份
function extractProvinceFromLocation($location)
{
    if (empty($location)) return '';
    $location = preg_replace('/^中国\s*/u', '', $location);
    $location = preg_replace('/^\s+|\s+$/u', '', $location);
    // 省
    if (preg_match('/^(.+?)省/u', $location, $m)) {
        return $m[1];
    }
    // 直辖市
    foreach (array('北京', '上海', '天津', '重庆') as $city) {
        if (strpos($location, $city) === 0) {
            return $city;
        }
    }
    // 自治区
    foreach (array('内蒙古', '广西', '西藏', '宁夏', '新疆', '香港', '澳门', '台湾') as $prov) {
        if (strpos($location, $prov) === 0) {
            return $prov;
        }
    }
    // 兜底
    if (preg_match('/^[^\s]+/u', $location, $m)) {
        return $m[0];
    }
    return '';
}

// 获取评论者 IP 归属地
function getCommentLocation($ip)
{
    $options = Helper::options();
    // 兼容 Radio 返回值
    $xmip = isset($options->xmip) ? $options->xmip : false;
    if ($xmip === false || $xmip === '0' || $xmip === 0 || $xmip === '') {
        return '';
    }

    $ip = trim((string)$ip);
    if (empty($ip) || $ip === '127.0.0.1') {
        return '';
    }

    // 兜底
    $targetIp = $ip;
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $targetIp = '';
        $headers = ['HTTP_X_FORWARDED_FOR', 'X_FORWARDED_FOR', 'HTTP_CF_CONNECTING_IP', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            $val = isset($_SERVER[$header]) ? $_SERVER[$header] : '';
            if (empty($val)) continue;
            foreach (explode(',', $val) as $candidate) {
                $candidate = trim($candidate);
                if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    $targetIp = $candidate;
                    break 2;
                }
            }
        }
    }

    if (empty($targetIp) || !filter_var($targetIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return '';
    }

    // 解析归属地
    $location = convertip($targetIp);
    $province = extractProvinceFromLocation($location);
    return empty($province) ? '' : htmlspecialchars($province);
}

// 评论网址管控：判断当前评论的作者网址是否应该显示
function shouldShowCommentUrl($created)
{
    $options = Helper::options();
    $mode = isset($options->comment_url_control) ? $options->comment_url_control : 'none';
    if (empty($mode) || $mode === 'none') {
        return true;
    }
    if ($mode === 'hide') {
        return false;
    }
    // hide_15 / hide_30 / hide_60
    $days = (int) str_replace('hide_', '', $mode);
    if ($days <= 0) {
        return true;
    }
    return $created >= strtotime("-{$days} days");
}

// 获取评论者网址（已受评论网址管控）
function getCommentAuthorUrl($comment)
{
    $url = isset($comment->url) ? trim((string)$comment->url) : '';
    if ($url === '') {
        return '';
    }
    $created = isset($comment->created) ? (int)$comment->created : 0;
    return shouldShowCommentUrl($created) ? $url : '';
}


// 生成未登录评论框登录提示 HTML
function renderCommentLoginFormTip()
{
    $loginUrl = Helper::options()->adminUrl . 'login.php';
    return '<div class="baige-comment-login-form-tip">请先 <a href="' . $loginUrl . '" class="baige-comment-login-btn">登录</a> 后发表评论 (｡･ω･｡)</div>';
}

// 评论等级
function dengji($i, $widget) {
$db = Typecho_Db::get();

$sql = $db->select('COUNT(mail) as emnum')->from('table.comments')
		 ->where('status = ?','approved')
		 ->where('mail = ?',$i);
	 $result = $db->fetchAll($sql);
	$rbq = $result[0]['emnum'] ?? 0;
    $options = Typecho_Widget::widget('Widget_Options');
    if (!$options->dengji) return;

    $baseStyle = 'baige-dengji';
    $iconStyle = 'baige-dengjiimg';

    $goldIcon        = XM_TPL . 'bai/img/dengji/gold.svg';
    $platinumIcon    = XM_TPL . 'bai/img/dengji/platinum.svg';
    $diamondIcon     = XM_TPL . 'bai/img/dengji/diamond.svg';
    $starIcon        = XM_TPL . 'bai/img/dengji/starlight.svg';
    $kingIcon        = XM_TPL . 'bai/img/dengji/king.svg';
    $legendKingIcon  = XM_TPL . 'bai/img/dengji/noble.svg';
    $yifuIcon        = XM_TPL . 'bai/img/dengji/godfather.svg'; //新增义父图标路径

    if ($widget->authorId == $widget->ownerId){
        echo '<span class="'.$baseStyle.' baige-bozhu">博主</span>';
        echo '<img src="' . XM_TPL . 'bai/img/dengji/blogger.svg" alt="博主标识" class="' . $iconStyle . '">';
        return;
    }

    if ($rbq >= 6 && $rbq <= 10) {
        echo '<span class="'.$baseStyle . ' baige-lv-1">青铜</span>';
    } elseif ($rbq >= 11 && $rbq <= 25) {
        echo '<span class="'.$baseStyle . ' baige-lv-2">白银</span>';
    } elseif ($rbq >= 26 && $rbq <= 40) {
        echo '<span class="'.$baseStyle . ' baige-lv-3">黄金</span><img src="' . $goldIcon . '" alt="黄金" class="' . $iconStyle . '">';
    } elseif ($rbq >= 41 && $rbq <= 55) {
        echo '<span class="'.$baseStyle . ' baige-lv-4">铂金</span><img src="' . $platinumIcon . '" alt="铂金" class="' . $iconStyle . '">';
    } elseif ($rbq >= 56 && $rbq <= 70) {
        echo '<span class="'.$baseStyle . ' baige-lv-5">钻石</span><img src="' . $diamondIcon . '" alt="钻石" class="' . $iconStyle . '">';
    } elseif ($rbq >= 71 && $rbq <= 85) {
        echo '<span class="'.$baseStyle . ' baige-lv-6">星耀</span><img src="' . $starIcon . '" alt="星耀" class="' . $iconStyle . '">';
    } elseif ($rbq >= 86 && $rbq <= 129) {
        echo '<span class="'.$baseStyle . ' baige-lv-7">王者</span><img src="' . $kingIcon . '" alt="王者" class="' . $iconStyle . '">';
    } elseif ($rbq >= 130 && $rbq <= 199) {
        echo '<span class="'.$baseStyle . ' baige-lv-8">贵人</span><img src="' . $legendKingIcon . '" alt="贵人" class="' . $iconStyle . '">';
    } elseif ($rbq >= 200) { //200条评论触发义父等级
        echo '<span class="'.$baseStyle . ' baige-lv-9">义父</span><img src="' . $yifuIcon . '" alt="义父" class="' . $iconStyle . '">';
    }
}


// 编辑器扩展
class editor
{
    public static function reset()
    {
?>
<style>
// 自定义字段表单美化
.typecho-option {
    margin-bottom: 18px !important;
    padding: 16px !important;
    background: #fafafa;
    border-radius: 10px;
    border: 1px solid #eee;
}
.typecho-label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: #333;
    margin-bottom: 10px;
}
.field-value > ul,
.field-value > div > ul {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.field-value > ul > li,
.field-value > div > ul > li {
    display: inline-flex;
    align-items: center;
    margin: 0;
}
.field-value li input[type="radio"] {
    margin: 0 4px 0 0;
    cursor: pointer;
}
.field-value li input[type="radio"] + label {
    font-size: 13px;
    color: #555;
    cursor: pointer;
    padding: 5px 10px;
    border-radius: 5px;
    background: #fff;
    border: 1px solid #ddd;
    transition: all 0.2s;
    margin: 0;
}
.field-value li input[type="radio"]:checked + label {
    background: #e8f4fd;
    border-color: #4a90d9;
    color: #333;
}
.field-value input[type="text"],
.field-value textarea {
    width: 100% !important;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 13px;
    line-height: 1.5;
    transition: border-color 0.2s, box-shadow 0.2s;
    box-sizing: border-box;
}
.field-value input[type="text"]:focus,
.field-value textarea:focus {
    border-color: #4a90d9;
    box-shadow: 0 0 0 3px rgba(74,144,217,0.1);
    outline: none;
}
.field-value .description {
    margin-top: 8px !important;
    font-size: 12px;
    color: #888;
}
</style>
<script>
$(function(){
    // 让编辑器按钮栏自动换行
    $('#wmd-button-bar').css({
        'height': 'auto',
        'overflow': 'visible'
    });
    $('.wmd-button-row').css({
        'height': 'auto',
        'display': 'flex',
        'flex-wrap': 'wrap',
        'align-items': 'center',
        'gap': '4px',
        'padding': '4px 0'
    });
    $('.field-value .description').css({
        'margin-top': '10px',
    });
        $('.field-value div').css({
        'width': '100%',
    });
    $('.field-value div input[type="text"]').css({
        'width': '100%',
    });

    $('.field-value div textarea').css({
        'width': '100%',
    });

    // 自定义按钮
    var btns = `
        <li class="wmd-button" id="j-emoji" title="插入表情">表情</li>
        <li class="wmd-button" id="j-sfq" title="折叠面板">折叠</li>
        <li class="wmd-button" id="j-an" title="彩色按钮">按钮</li>
        <li class="wmd-button" id="j-bili-av" title="B站视频">哔哩</li>
        <li class="wmd-button" id="j-ts" title="提示框">提示</li>
        <li class="wmd-button" id="j-neizhilink" title="友链">友链</li>
        <li class="wmd-button" id="j-mp4" title="视频">视频</li>
        <li class="wmd-button" id="j-yinyuehe" title="音乐">音乐</li>
        <li class="wmd-button" id="j-login" title="登录可见">登陆</li>
        <li class="wmd-button" id="j-hide" title="回复可见">回复</li>
        <li class="wmd-button" id="j-suoj" title="缩进">缩进</li>
        <li class="wmd-button" id="j-juzhong" title="居中">居中</li>
        <li class="wmd-button" id="j-timeline" title="时间线">时间线</li>
        <li class="wmd-button" id="j-video-set" title="视频集">视频集</li>
    `;

    $('#wmd-fullscreen-button').after(btns);

    // 按钮小样式
    $('.wmd-button[id^="j-"]').css({
        'font-size':'12px',
        'padding':'0 6px',
        'line-height':'24px'
    });

    // 插入方法
    function insertAtCursor(myValue) {
        var myField = $('#text')[0];
        if (document.selection) {
            myField.focus();
            var sel = document.selection.createRange();
            sel.text = myValue;
            sel.select();
        } else if (myField.selectionStart || myField.selectionStart === 0) {
            var startPos = myField.selectionStart;
            var endPos = myField.selectionEnd;
            myField.value = myField.value.substring(0, startPos) + myValue + myField.value.substring(endPos);
            myField.selectionStart = myField.selectionEnd = startPos + myValue.length;
            myField.focus();
        } else {
            myField.value += myValue;
            myField.focus();
        }
    }

    $("#j-sfq").on("click", function(e) {
        e.preventDefault();
        insertAtCursor('\n{sfq title="标题"}这个内容默认是关闭的{/sfq}\n{sfqa title="标题"}这个内容默认是展开的{/sfqa}\n');
    });
    $("#j-timeline").on("click", function(e) {
        e.preventDefault();
        insertAtCursor('\n{timeline}\n{timeline-item year="2023"}\n{item}2023年1月1日 内容1{/item}\n{item}2023年2月1日 内容2{/item}\n{\/timeline-item}\n\n{timeline-item year="2026"}\n{item}2026年1月1日 内容1{/item}\n{\/timeline-item}\n{\/timeline}\n');
    });
    $("#j-video-set").on("click", function(e) {
    e.preventDefault();
    insertAtCursor('\n{playlist}\n{item src="MP4地址"  pic="封面地址"}第1集{\/item}\n{item src="MP4地址"  pic="封面地址"}第2集{\/item}\n{\/playlist}\n');
});
    $("#j-an").on("click", function(e) {
        e.preventDefault();
        insertAtCursor('\n{an id="1到5可选"}内容{/an}\n');
    });
    $("#j-juzhong").on("click", function(e) {
        e.preventDefault();
        insertAtCursor('\n{center}内容{/center}\n');
    });
    $("#j-suoj").on("click", function(e) {
        e.preventDefault();
        insertAtCursor('　　');
    });
    $("#j-neizhilink").on("click",function(e){
        e.preventDefault();
        insertAtCursor('\n{index title="正常友链"}\n{linkc url="https://" img="图片"}名称{/linkc m="描述"}\n{/index}\n\n{fyindex title="分流友链"}\n{linkc url="https://" img="图片"}名称{/linkc m="描述"}\n{/fyindex}\n\n{nolinkindex title="纯展示友链"}\n{linkc url="https://" img="图片"}名称{/linkc m="描述"}\n{/nolinkindex}\n');
    });
    $("#j-hide").on("click", function(e) {
        e.preventDefault();
        insertAtCursor('\n{hide}内容{/hide}\n');
    });
    $("#j-ts").on("click", function(e) {
        e.preventDefault();
        insertAtCursor('\n{ts id="1到10自由选择"}内容{/ts}\n');
    });
    $("#j-mp4").on("click", function(e) {
        e.preventDefault();
        insertAtCursor('\n{video src="MP4地址"}\n');
    });
    $("#j-yinyuehe").on("click", function() {
        insertAtCursor('\n[music 名称="xx"]\n[music 列表="xx|oo|xx"]\n[music 名称="xx" mp3="" pic="" lrc=""]\n');
    });
    $("#j-login").on("click", function(e) {
        e.preventDefault();
        insertAtCursor('\n{login}隐藏内容{/login}\n');
    });
    $("#j-bili-av").on("click", function(e) {
         e.preventDefault();
         insertAtCursor('\n{bl av="数字类型"}\n{bl bv="字母类型"}\n');
    });

});
</script>
<link rel="stylesheet" href="<?php echo XM_TPL; ?>bai/owo/owo.css">
<style>
#j-emoji {
  position:relative;
}
#admin-owo-container {
  position:absolute;
  top:0;
  left:0;
  width:100%;
  height:100%;
  pointer-events:none;
}
#admin-owo-container .OwO-logo {
  display:none !important;
}
#admin-owo-container .OwO-body {
  position:absolute;
  left:0;
  top:100%;
  margin-top:6px;
  width:460px;
  border-radius:12px;
  box-shadow:0 8px 24px rgba(0,0,0,0.15);
  background:#ffffff;
  border:1px solid #e8e8e8;
  pointer-events:auto;
}
#admin-owo-container .OwO-items {
  padding:10px !important;
  gap:5px !important;
}
#admin-owo-container .OwO-items .OwO-item {
  height:26px !important;
  padding:2px 4px !important;
}
#admin-owo-container .OwO-items .OwO-item img {
  max-height:21px !important;
  max-width:21px !important;
}
</style>
<div class="OwO admin-owo" id="admin-owo-container"></div>
<script src="<?php echo XM_TPL; ?>bai/owo/owo.js"></script>
<script>
$(function(){
    var $text = $('#text');
    var $owo = $('#admin-owo-container');
    if ($text.length && $owo.length) {
        // 把表情容器嵌入按钮内
        $('#j-emoji').append($owo);

        new OwO({
            logo: '',
            container: $owo[0],
            target: $text[0],
            api: '<?php echo XM_TPL; ?>bai/owo/OwO.json',
            position: 'down',
            width: '320px',
            maxHeight: '260px'
        });
        // 隐藏 OwO 自带 logo
        $owo.find('.OwO-logo').hide();

        $('#j-emoji').on('click', function(e){
            e.preventDefault();
            e.stopPropagation();
            $owo.toggleClass('OwO-open');
        });
    }
});
</script>
<?php
    }
}

// 上下篇
function theNext($widget, $word = '下一篇', $default = NULL)
{
    $db = Typecho_Db::get();
    $sql = $db->select()->from('table.contents')
        ->where('table.contents.created > ?', $widget->created)
        ->where('table.contents.status = ?', 'publish')
        ->where('table.contents.type = ?', $widget->type)
        ->where('table.contents.password IS NULL')
        ->order('table.contents.created', Typecho_Db::SORT_ASC)
        ->limit(1);
    $content = $db->fetchRow($sql);
    if ($content) {
        $cid = $content['cid']; $title = htmlspecialchars($content['title']);
        $permalink = Helper::options()->siteUrl . 'archives/' . $cid . '/';
        echo '<a href="' . $permalink . '" title="' . $title . '"><img src="' . XM_TPL . 'bai/Cnexta.gif">' . $word . '</a>';
    } else {
        echo $default;
    }
}
function thePrev($widget, $word = '上一篇', $default = NULL)
{
    $db = Typecho_Db::get();
    $sql = $db->select()->from('table.contents')
        ->where('table.contents.created < ?', $widget->created)
        ->where('table.contents.status = ?', 'publish')
        ->where('table.contents.type = ?', $widget->type)
        ->where('table.contents.password IS NULL')
        ->order('table.contents.created', Typecho_Db::SORT_DESC)
        ->limit(1);
    $content = $db->fetchRow($sql);
    if ($content) {
        $cid = $content['cid']; $title = htmlspecialchars($content['title']);
        $permalink = Helper::options()->siteUrl . 'archives/' . $cid . '/';
        echo '<a href="' . $permalink . '" title="' . $title . '"><img src="' . XM_TPL . 'bai/Cprevious1.gif">' . $word . '</a>';
    } else {
        echo $default;
    }
}

// 主题初始化
function themeInit($archive)
{
    // 强制默认
    $archive->parameter->list = 0;

    // 列表模式
    if(isset($_GET['lq']) && $_GET['lq'] == 'no'){
        $archive->parameter->list = 1;

        // 列表条数生效
        $pageSize = intval(Helper::options()->listts);
        if ($pageSize > 0) {
            $archive->parameter->pageSize = $pageSize;
        }
    }

    // 单页处理
    if ($archive->is('single')) {
        $desc = preg_replace('/{mm id="(.+?)"}(.+?){\/mm}/', '', $archive->getDescription());
        $archive->setDescription($desc);
    }
}

// 主题后台
/**
 * 获取分类图标 URL
 * 读取后台主题设置中的 categoryIcons 映射，每行格式：分类名=图标文件名 或 分类名=完整URL
 * 图标文件名会自动拼接主题路径 /bai/img/fenlei/；完整URL（http/https 开头）则直接使用
 *
 * @param string $categoryName 分类名称
 * @return string 图标 URL
 */
function getCategoryIcon($categoryName)
{
    $options = Helper::options();
    $defaultIcon = $options->themeUrl . '/bai/img/fenlei/journal.svg';

    if (empty($categoryName)) {
        return $defaultIcon;
    }

    // 同请求内缓存解析结果
    static $mappingCache = null;
    if ($mappingCache === null) {
        // 内置默认映射：后台未配置或新安装时兜底，避免全部显示日志图标
        $defaultMapping = [
            '日志' => 'journal.svg',
            '视频' => 'video.svg',
            '相册' => 'album.svg',
            '分享' => 'share.svg',
            '音乐' => 'music.svg',
            '收藏' => 'favorite.svg',
            '其他' => 'other.svg',
        ];

        $mapping = [];
        if (!empty($options->categoryIcons)) {
            $lines = explode("\n", $options->categoryIcons);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || strpos($line, '=') === false) {
                    continue;
                }
                list($cat, $icon) = array_map('trim', explode('=', $line, 2));
                if (!empty($cat) && !empty($icon)) {
                    $mapping[$cat] = $icon;
                }
            }
        }
        $mappingCache = [$defaultIcon, $defaultMapping, $mapping];
    }
    list($defaultIcon, $defaultMapping, $mapping) = $mappingCache;

    // 后台配置优先，未命中再回退到内置默认映射
    $icon = $mapping[$categoryName] ?? $defaultMapping[$categoryName] ?? '';

    if (!empty($icon)) {
        if (preg_match('/^https?:\/\//i', $icon)) {
            return $icon;
        }
        return $options->themeUrl . '/bai/img/fenlei/' . $icon;
    }

    return $defaultIcon;
}

function themeConfig($form)
{
    xm::BackSet();
    $stats = getSecurityStats();
    $headerHtml = '<link rel="stylesheet" href="' . XM_TPL . 'bai/css/admin.css"><script src="' . XM_TPL . 'bai/js/admin.js"></script>';
    $headerHtml .= '<div class="tip"><span class="tip-var">V ' . $GLOBALS['version']['version'] . ' </span>';
    $headerHtml .= '<div class="tip-header"><h1>' . $GLOBALS['version']['title'] . '设置面板</h1></div>';
    $headerHtml .= '<p>作者：<a href="' . $GLOBALS['version']['homepage'] . '">' . $GLOBALS['version']['author'] . '</a></p>';
    $headerHtml .= '<p>欢迎使用 ' . $GLOBALS['version']['title'] . '。</p>';

    $headerHtml .= '<div style="background:#f8f9fa;padding:12px 15px;border-radius:8px;margin:10px 0;border:1px solid #ddd;">
    <h4 style="margin:0 0 8px;font-size:14px;color:#222;">🔐 安全拦截统计</h4>
    <p style="margin:2px 0;font-size:13px;">总计拦截：<strong style="color:#dc2626;">'.$stats['total'].'</strong> 次｜今日拦截：<strong style="color:#dc2626;">'.$stats['today'].'</strong> 次</p>
    <div style="margin-top:8px;font-size:12px;line-height:1.5;color:#333;">
    最近拦截：<br>';
    foreach($stats['logs'] as $log){
        $headerHtml .= '• '.$log['time'].' 【'.$log['type'].'】'.$log['msg'].'<br>';
    }
    if(empty($stats['logs'])) $headerHtml .= '暂无拦截记录';
    $headerHtml .= '</div></div>';

    $headerHtml .= '<form class="protected home col-mb-12" action="?xmjianbf" method="post">';
    $headerHtml .= '<div class="typecho-option"><label class="typecho-label">主题设置备份：' . xm::CheckSetBack() . '</label></div>';
    $headerHtml .= '<input type="submit" name="type" class="btn lan" value="备份" />&#160;&#160;';
    $headerHtml .= '<input type="submit" name="type" class="btn lan" value="还原" />&#160;&#160;';
    $headerHtml .= '<input type="submit" name="type" class="btn hong" style="float:right" value="删除" />';
    $headerHtml .= '</form></div>';
    echo $headerHtml;

    $form->addItem(new EchoHtml('<div class="allTab"><div class="tab">'));
    $form->addItem(new EchoHtml('<div class="tabLinks">基础设置</div>'));
    $form->addItem(new EchoHtml('<div class="tabLinks">音乐盒</div>'));
    $form->addItem(new EchoHtml('<div class="tabLinks">显示优化</div>'));
    $form->addItem(new EchoHtml('<div class="tabLinks">主题外观</div>'));
    $form->addItem(new EchoHtml('<div class="tabLinks">评论互动</div>'));
    $form->addItem(new EchoHtml('<div class="tabLinks">头像设置</div>'));
    $form->addItem(new EchoHtml('<div class="tabLinks">侧边栏</div>'));
    $form->addItem(new EchoHtml('<div class="tabLinks">安全中心</div>'));
    $form->addItem(new EchoHtml('<div class="tabLinks">Feed订阅</div>'));
    $form->addItem(new EchoHtml('<div class="tabLinks">友链管理</div>'));
    $form->addItem(new EchoHtml('</div>'));

    // ===== 基础设置 tab =====
    $form->addItem(new EchoHtml('<div class="tabContent">'));
    $form->addItem(new EchoHtml('<h4 style="margin:0 0 12px;font-size:14px;color:#0369a1;">网站基本信息</h4>'));

    $tongyong = new Typecho_Widget_Helper_Form_Element_Textarea('tongyong', null, null, '通用公告', '网站顶部显示的公告内容');
    $form->addInput($tongyong);
    $subTitle = new Typecho_Widget_Helper_Form_Element_Text('subTitle', null, null, '网站副标题', '首页标题后显示的副标题，例如：皮一下～很开心!');
    $form->addInput($subTitle);
    $ico = new Typecho_Widget_Helper_Form_Element_Text('ico', null, null, 'favicon.ico', '浏览器标签栏小图标');
    $form->addInput($ico);
    $jianzhan = new Typecho_Widget_Helper_Form_Element_Text('jianzhan',null,'2023.08.18','建站时间','用于显示网站运行时长');
    $form->addInput($jianzhan);
    $footer = new Typecho_Widget_Helper_Form_Element_Textarea('footer',null,null,'ICP备案号','网站底部备案号');
    $form->addInput($footer);
    $ganBeiAn = new Typecho_Widget_Helper_Form_Element_Textarea('ganBeiAn',null,null,'公网安备号','网站底部公安备案号');
    $form->addInput($ganBeiAn);

    $form->addItem(new EchoHtml('<h4 style="margin:20px 0 12px;font-size:14px;color:#0369a1;">外观与布局</h4>'));

    $bjUrl = new Typecho_Widget_Helper_Form_Element_Text('bjUrl', null, null, '页面背景图', '整个网页的背景图片');
    $form->addInput($bjUrl);
    $blogtx = new Typecho_Widget_Helper_Form_Element_Text('blogtx',null,'/usr/themes/dage/bai/img/tx.png','博主头像','网站显示的博主头像地址');
    $form->addInput($blogtx);

    $form->addItem(new EchoHtml('<h4 style="margin:20px 0 12px;font-size:14px;color:#0369a1;">列表与容器</h4>'));

    $sticky = new Typecho_Widget_Helper_Form_Element_Text('sticky', null, null, '置顶文章CID', '填写文章ID，可让文章固定在首页顶部');
    $form->addInput($sticky);
    $listts = new Typecho_Widget_Helper_Form_Element_Text('listts', null, '10', '列表条数', '每页显示多少篇文章');
    $form->addInput($listts);
    $userwidth = new Typecho_Widget_Helper_Form_Element_Radio('userwidth', [
        'w920'  => '920px（默认）',
        'w1080' => '1080px'
    ], 'w920', 'Container宽度设置', '调整页面主体容器最大宽度');
    $form->addInput($userwidth);
    $fonthm = new Typecho_Widget_Helper_Form_Element_Radio('fonthm', ['PY'=>'苹方','no'=>'关闭'], 'no', '中文字体', '网站使用的中文字体');
    $form->addInput($fonthm);
    $form->addItem(new EchoHtml('</div>'));

    // ===== 音乐盒 tab =====
    $form->addItem(new EchoHtml('<div class="tabContent">'));
    $form->addItem(new EchoHtml('<h4 style="margin:0 0 12px;font-size:14px;color:#dc2626;">音乐盒访问控制</h4>'));

    $music_password_switch = new Typecho_Widget_Helper_Form_Element_Radio('music_password_switch', [
        '1' => '开启',
        '0' => '关闭'
    ], '0', '密码保护开关', '关闭时音乐盒页面保持原样，无任何影响');
    $form->addInput($music_password_switch);

    $music_password = new Typecho_Widget_Helper_Form_Element_Text('music_password', null, null, '访问密码', '开启密码保护后，前台需要输入此密码才能查看音乐盒内容');
    $form->addInput($music_password);

    $music_password_tip = new Typecho_Widget_Helper_Form_Element_Text('music_password_tip', null, '请输入密码访问音乐盒', '密码输入提示', '前台密码框上方显示的提示文字');
    $form->addInput($music_password_tip);

    $form->addItem(new EchoHtml('<div style="margin:12px 0;padding:10px;background:#fff7e6;border-radius:6px;border:1px solid #ffe0b2;font-size:12px;color:#d46b08;">
        说明：开启后音乐盒与文章中音乐需要输入KEY才能播放；管理员登录状态下自动免密访问。
    </div>'));
    $form->addItem(new EchoHtml('</div>'));

    // ===== 显示优化 tab =====
    $form->addItem(new EchoHtml('<div class="tabContent">'));
    $form->addItem(new EchoHtml('<h4 style="margin:0 0 12px;font-size:14px;color:#15803d;">导航与菜单</h4>'));

    $yemian = new Typecho_Widget_Helper_Form_Element_Radio('yemian', [
        'sidebar' => '侧边栏',
        'bottom'  => '底部',
        'nav'     => '顶部导航栏（分类后面）'
    ], 'bottom', '独立页面位置', '选择显示位置，多选会互相冲突，建议只选一个');
    $form->addInput($yemian);
$categoryMenuMode = new Typecho_Widget_Helper_Form_Element_Radio('categoryMenuMode', [
    'expand' => '全部展开显示',
    'dropdown' => '折叠为下拉菜单'
], 'expand', '分类菜单模式', '控制顶部导航栏分类的显示方式');
$form->addInput($categoryMenuMode);

$categoryIcons = new Typecho_Widget_Helper_Form_Element_Textarea(
    'categoryIcons',
    null,
    "日志=journal.svg\n视频=video.svg\n相册=album.svg\n分享=share.svg\n音乐=music.svg\n收藏=favorite.svg\n其他=other.svg",
    '分类图标映射',
    '每行一条，格式：分类名=图标文件名（如：技术=journal.svg）。图标文件请放到主题 bai/img/fenlei/ 目录；也可填完整 URL（http/https 开头）。未配置的分类默认显示journal.svg。'
);
$form->addInput($categoryIcons);

// 页面菜单显示模式
$pageMenuMode = new Typecho_Widget_Helper_Form_Element_Radio('pageMenuMode', [
    'expand' => '全部展开显示',
    'dropdown' => '折叠为下拉菜单'
], 'dropdown', '独立页面菜单模式', '控制顶部导航栏页面的显示方式，在"分类后面"下生效~');
$form->addInput($pageMenuMode);

    $form->addItem(new EchoHtml('<h4 style="margin:20px 0 12px;font-size:14px;color:#15803d;">图片与媒体</h4>'));

    $lazyload = new Typecho_Widget_Helper_Form_Element_Radio('lazyload', [true=>'开启',false=>'关闭'], false, 'lazyload图片加载', '图片滚动到屏幕再加载，加快网页速度');
    $form->addInput($lazyload);
    $fancybox = new Typecho_Widget_Helper_Form_Element_Radio('fancybox', ['oka'=>'全局','okb'=>'内页','okc'=>'列表','okd'=>'关闭'], 'okd', 'fancybox灯箱', '点击图片放大查看');
    $form->addInput($fancybox);
    $suiji = new Typecho_Widget_Helper_Form_Element_Radio('suiji', [true=>'启用',false=>'关闭'], false, '文章随机图片', '文章没有封面时自动使用随机图片');
    $form->addInput($suiji);

    $form->addItem(new EchoHtml('<h4 style="margin:20px 0 12px;font-size:14px;color:#15803d;">性能优化</h4>'));

    $JOverdue = new Typecho_Widget_Helper_Form_Element_Select('JOverdue',['off'=>'关闭','30'=>'30天','60'=>'60天','90'=>'90天','120'=>'120天','180'=>'180天'],'off','文章过期提示','文章发布多久后显示过期提醒');
    $form->addInput($JOverdue);
    $compressHtml = new Typecho_Widget_Helper_Form_Element_Radio('compressHtml', [true=>'启用',false=>'关闭'], false, 'HTML压缩', '压缩网页代码，加快访问速度');
    $form->addInput($compressHtml);
    $form->addItem(new EchoHtml('</div>'));

    // ===== 主题外观 tab =====
    $form->addItem(new EchoHtml('<div class="tabContent">'));
    $form->addItem(new EchoHtml('<h4 style="margin:0 0 12px;font-size:14px;color:#8b5cf6;">主题色与模式</h4>'));

    $defaultTheme = new Typecho_Widget_Helper_Form_Element_Select('defaultTheme',[
        'theme-1' => '主题1 · 清新绿',
        'theme-2' => '主题2 · 暗夜黑',
        'theme-3' => '主题3 · 樱花粉',
        'theme-4' => '主题4 · 薄荷青',
        'theme-5' => '主题5 · 薰衣草紫',
        'theme-6' => '主题6 · 天空蓝',
        'theme-7' => '主题7 · 活力橙'
    ],'theme-1','默认主题色','用户首次访问或未手动切换时使用的主题色');
    $form->addInput($defaultTheme);
    $anhei = new Typecho_Widget_Helper_Form_Element_Radio('anhei',['no'=>'关闭','ok'=>'打开'],'no','主题背景色','开启后网站支持主题背景色切换，最多可在 7 种配色之间循环切换');
    $form->addInput($anhei);

    $form->addItem(new EchoHtml('<h4 style="margin:20px 0 12px;font-size:14px;color:#8b5cf6;">页面视频</h4>'));

    $video = new Typecho_Widget_Helper_Form_Element_Textarea('video',null,null,'页面随机视频 API','用于“页面视频”页面播放，填写随机视频接口地址，可实现换一换功能');
    $form->addInput($video);
    $form->addItem(new EchoHtml('</div>'));

    // ===== 评论互动 tab =====
    $form->addItem(new EchoHtml('<div class="tabContent">'));
    $form->addItem(new EchoHtml('<h4 style="margin:0 0 12px;font-size:14px;color:#dc2626;">评论样式</h4>'));

    $pinglun = new Typecho_Widget_Helper_Form_Element_Radio('pinglun', [true=>'现代化',false=>'复古风'], false, '评论框样式', '切换评论样式模板');
    $form->addInput($pinglun);
    $homeComments = new Typecho_Widget_Helper_Form_Element_Radio('homeComments', [true=>'开启',false=>'关闭'], false, '首页评论展示', '首页文章下方显示最近5条评论，默认关闭');
    $form->addInput($homeComments);
    $dengji = new Typecho_Widget_Helper_Form_Element_Radio('dengji', [true=>'启用',false=>'关闭'], false, '用户评论等级', '评论者根据评论数量显示等级');
    $form->addInput($dengji);
    $xmip = new Typecho_Widget_Helper_Form_Element_Radio('xmip', [
        '0' => '关闭',
        '1' => '开启'
    ], '0', '评论归属地显示', '评论者IP归属地（纯真IP库），只显示省份，如"广东"、"湖南"');
    $form->addInput($xmip);

    $comment_not_login_hide_form = new Typecho_Widget_Helper_Form_Element_Radio('comment_not_login_hide_form', ['1'=>'开启','0'=>'关闭'], '0', '未登录时评论框显示登录提示', '开启后，未登录用户的评论输入框会变为"请先登录后发表评论"的提示样式');
    $form->addInput($comment_not_login_hide_form);

    $form->addItem(new EchoHtml('<h4 style="margin:20px 0 12px;font-size:14px;color:#dc2626;">文章版权</h4>'));

    $copyright = new Typecho_Widget_Helper_Form_Element_Textarea('copyright',null,'本文部分内容来源于网络，仅供学习交流，侵权联系删除。','文章版权提示','文章底部显示的版权声明');
    $form->addInput($copyright);
    $form->addItem(new EchoHtml('</div>'));

    // ===== 头像设置 tab =====
    $form->addItem(new EchoHtml('<div class="tabContent">'));
    $form->addItem(new EchoHtml('<h4 style="margin:0 0 12px;font-size:14px;color:#d46b08;">头像配置</h4>'));

    $touxiangsj = new Typecho_Widget_Helper_Form_Element_Radio('touxiangsj', [true=>'开启',false=>'关闭'], false, '随机头像', '访客头像每次刷新随机变化');
    $form->addInput($touxiangsj);
    $gravatars = new Typecho_Widget_Helper_Form_Element_Select('gravatars',[
        'cravatar.cn/avatar'=>'cravatar',
        'weavatar.com/avatar/'=>'weavatar',
        'cdn.helingqi.com/avatar'=>'禾令奇'
    ],'cravatar.cn/avatar','头像源','访客头像加载的服务器地址');
    $form->addInput($gravatars->multiMode());

    // 首字母头像开关
    $letterAvatar = new Typecho_Widget_Helper_Form_Element_Radio('letter_avatar', [true=>'开启',false=>'关闭'], false, '首字母头像', '开启后评论者头像显示为昵称首字母彩色圆形，关闭则正常加载头像');
    $form->addInput($letterAvatar);
    $form->addItem(new EchoHtml('</div>'));

    // ===== 侧边栏 tab =====
    $form->addItem(new EchoHtml('<div class="tabContent">'));
    $form->addItem(new EchoHtml('<h4 style="margin:0 0 12px;font-size:14px;color:#0d9488;">侧边栏模块</h4>'));

    $sidebarBlock = new Typecho_Widget_Helper_Form_Element_Checkbox('sidebarBlock',[
        'Showsuiji'=>'随机文章','Showsousuo'=>'搜索','Showtongji'=>'统计','Showtag'=>'标签','Showguidang'=>'归档',
        'Showkuaijie'=>'其他','Showside'=>'左侧边栏','Showpinglun'=>'热门评论','Showyunxing'=>'底部运行时间'
    ],['Showsousuo'],'侧边栏显示','勾选需要在侧边栏显示的模块');
    $form->addInput($sidebarBlock);

    $sidebarPosition = new Typecho_Widget_Helper_Form_Element_Radio('sidebarPosition', [
        'off'   => '关闭',
        'left'  => '左侧显示',
        'right' => '右侧显示'
    ], 'left', '侧边栏位置', '关闭后主题变为单栏布局；移动端固定从左侧抽屉滑出；平板与电脑端按所选位置显示');
    $form->addInput($sidebarPosition);

    $form->addItem(new EchoHtml('<h4 style="margin:20px 0 12px;font-size:14px;color:#0d9488;">友链与随机文章</h4>'));

    $clink = new Typecho_Widget_Helper_Form_Element_Text('clink', null, null, '侧边栏友链CID', '侧边栏显示友链，填写友链页面ID');
    $form->addInput($clink);
    $dongtailink = new Typecho_Widget_Helper_Form_Element_Radio('dongtailink', [true=>'显示部分',false=>'显示全部'], false, '友链显示模式', '侧边栏友链只显示几条或全部显示');
    $form->addInput($dongtailink);
    $suijiwen = new Typecho_Widget_Helper_Form_Element_Text('suijiwen', null, '10', '随机文章条数', '侧边栏随机文章显示数量');
    $form->addInput($suijiwen);

    $form->addItem(new EchoHtml('<h4 style="margin:20px 0 12px;font-size:14px;color:#0d9488;">归档与标签</h4>'));

    $archiveFormat = new Typecho_Widget_Helper_Form_Element_Radio('archiveFormat',['cn'=>'中文2026年02月','en'=>'英文2026 February'],'cn','归档格式','文章归档显示中文还是英文');
    $form->addInput($archiveFormat);
    $tagMode = new Typecho_Widget_Helper_Form_Element_Radio('tagMode',['hot'=>'热门','rand'=>'随机'],'hot','标签模式','标签云按热门还是随机排序');
    $form->addInput($tagMode);

    $form->addItem(new EchoHtml('<h4 style="margin:20px 0 12px;font-size:14px;color:#0d9488;">文章目录</h4>'));

    $mulu = new Typecho_Widget_Helper_Form_Element_Radio('mulu',['no'=>'关闭','ok'=>'开启'],'no','文章目录','文章页右下角显示目录按钮，需文章包含 h1-h6 标题');
    $form->addInput($mulu);
    $form->addItem(new EchoHtml('</div>'));

    $form->addItem(new EchoHtml('<div class="tabContent">'));

    $security_switch = new Typecho_Widget_Helper_Form_Element_Radio('security_switch', ['1'=>'开启','0'=>'关闭'], '0', '安全总开关', '关闭后所有评论/域名拦截规则失效');
    $form->addInput($security_switch);

    $comment_frequency = new Typecho_Widget_Helper_Form_Element_Text('comment_frequency', null, '10', '评论频率限制（秒）', '同一IP多少秒内只能评论一次，防刷屏');
    $form->addInput($comment_frequency);
    $frequency_tip = new Typecho_Widget_Helper_Form_Element_Text('frequency_tip', null, '评论过快，请10秒后再试。', '频率限制提示', '');
    $form->addInput($frequency_tip);

    $words_ip = new Typecho_Widget_Helper_Form_Element_Textarea('words_ip',NULL,"0.0.0.0",'禁止评论IP','一行一个，禁止这些IP发表评论');
    $form->addInput($words_ip);
    $block_ip_tip = new Typecho_Widget_Helper_Form_Element_Text('block_ip_tip', null, '您的IP已被禁止评论', 'IP被禁提示', '');
    $form->addInput($block_ip_tip);

    // 新增
    $words_mail = new Typecho_Widget_Helper_Form_Element_Textarea('words_mail',NULL,"",'禁止评论邮箱','一行一个规则，支持完整邮箱（精确匹配）、* 通配符（如 56524*@qq.com、*@qq.com）或纯域名（如 qq.com），匹配后直接拦截评论');
    $form->addInput($words_mail);
    $block_mail_tip = new Typecho_Widget_Helper_Form_Element_Text('block_mail_tip', null, '该邮箱已被禁止发表评论', '禁用邮箱提示', '');
    $form->addInput($block_mail_tip);

    $words_author = new Typecho_Widget_Helper_Form_Element_Textarea('words_author',NULL,"",'禁止评论用户名','一行一个规则，支持完整用户名（精确匹配）或 * 通配符（如 *算命*、生辰八字*），匹配后直接拦截评论');
    $form->addInput($words_author);
    $block_author_tip = new Typecho_Widget_Helper_Form_Element_Text('block_author_tip', null, '该用户名称已被禁止发表评论', '禁用用户名提示', '');
    $form->addInput($block_author_tip);

    $words_url = new Typecho_Widget_Helper_Form_Element_Textarea('words_url',NULL,"",'禁止评论网址域名','一行一个规则，支持完整域名（如 example.com）、* 通配符（如 *.example.com）或完整 URL，匹配后直接拦截评论');
    $form->addInput($words_url);
    $block_url_tip = new Typecho_Widget_Helper_Form_Element_Text('block_url_tip', null, '该网址域名已被禁止发表评论', '禁用网址提示', '');
    $form->addInput($block_url_tip);

    $words_keyword = new Typecho_Widget_Helper_Form_Element_Textarea('words_keyword',NULL,"",'评论违规关键词','一行一个，包含这些词的评论会被拦截');
    $form->addInput($words_keyword);
    $bad_word_tip = new Typecho_Widget_Helper_Form_Element_Text('bad_word_tip', null, '评论包含不当内容', '违规词提示', '');
    $form->addInput($bad_word_tip);

    $opt_referer = new Typecho_Widget_Helper_Form_Element_Select('opt_referer',["none"=>"关闭","forbid"=>"禁止访问","redirect"=>"跳首页"],"none","来路域名拦截", "从这些域名跳转过来直接拦截");
    $form->addInput($opt_referer->multiMode());
    $words_referer = new Typecho_Widget_Helper_Form_Element_Textarea('words_referer',NULL,"",'禁止访问的域名','支持 * 通配符，一行一个');
    $form->addInput($words_referer);
    $referer_block_tip = new Typecho_Widget_Helper_Form_Element_Text('referer_block_tip', null, '检测到您来自不友好网站的访问请求，本站已禁止来自该域名的访问。', '域名拦截提示', '');
    $form->addInput($referer_block_tip);

    $empty_comment_tip = new Typecho_Widget_Helper_Form_Element_Text('empty_comment_tip', null, '请输入有效评论内容', '空评论提示', '');
    $form->addInput($empty_comment_tip);
    $no_chinese_tip = new Typecho_Widget_Helper_Form_Element_Text('no_chinese_tip', null, '评论必须包含中文', '无中文提示', '');
    $form->addInput($no_chinese_tip);

    $form->addItem(new EchoHtml('<div style="margin:16px 0;padding:10px;background:#f0f9ff;border-radius:6px;border:1px solid #bae6fd;">'));
    $form->addItem(new EchoHtml('<h4 style="margin:0 0 8px;font-size:13px;color:#0369a1;">评论网址管控</h4>'));

    $comment_url_control = new Typecho_Widget_Helper_Form_Element_Radio('comment_url_control', [
        'none' => '不处理',
        'hide' => '隐藏评论url',
        'hide_30' => '隐藏30天前的评论url',
        'hide_90' => '隐藏90天前的评论url',
        'hide_180' => '隐藏180天前的评论url'
    ], 'none', '评论网址管控', '过于久远评论url，可能存在失效或变为违法站点，所以可以开启此功能关闭过久的评论url');
    $form->addInput($comment_url_control);

    $form->addItem(new EchoHtml('</div>'));

    $form->addItem(new EchoHtml('<div style="margin:12px 0;padding:10px;background:#fff7e6;border-radius:6px;border:1px solid #ffe0b2;">'));
    $form->addItem(new EchoHtml('<h4 style="margin:0 0 8px;font-size:13px;color:#d46b08;">自动审核设置</h4>'));

    $comment_auto_audit = new Typecho_Widget_Helper_Form_Element_Radio('comment_auto_audit', ['1'=>'开启','0'=>'关闭'], '0', '评论自动审核', '开启后，命中敏感词的评论不会直接拦截，而是进入待审核状态，站长后台审核后才能显示');
    $form->addInput($comment_auto_audit);

    $auto_audit_words = new Typecho_Widget_Helper_Form_Element_Textarea('auto_audit_words',NULL,"",'自动审核敏感词','一行一个，命中后评论进入待审核。留空则读取主题内置默认词库（lib/auto-audit-words.txt）', ['rows' => 6]);
    $form->addInput($auto_audit_words);

    $auto_audit_tip = new Typecho_Widget_Helper_Form_Element_Text('auto_audit_tip', null, '评论已提交，正在审核中，审核通过后显示', '自动审核提示', '前端提交命中敏感词后显示的提示');
    $form->addInput($auto_audit_tip);

    $form->addItem(new EchoHtml('</div>'));

    $form->addItem(new EchoHtml('</div>'));

    $form->addItem(new EchoHtml('<div class="tabContent">'));
    $form->addItem(new EchoHtml('<h4 style="margin:0 0 12px;font-size:14px;color:#15803d;">Feed 订阅</h4>'));

    $feed_switch = new Typecho_Widget_Helper_Form_Element_Radio('feed_switch', ['ok'=>'开启','no'=>'关闭'], 'no', 'Feed 订阅开关', '开启后可在订阅独立页面展示网友博客的最新文章');
    $form->addInput($feed_switch);

    $feed_urls = new Typecho_Widget_Helper_Form_Element_Textarea('feed_urls', NULL, "", '订阅地址', '一行一个 RSS/Atom 订阅地址，例如：https://www.example.com/feed/');
    $form->addInput($feed_urls);

    $feed_count = new Typecho_Widget_Helper_Form_Element_Text('feed_count', NULL, '100', '总显示条数', '订阅页面最多缓存多少篇文章');
    $form->addInput($feed_count);

    $feed_page_size = new Typecho_Widget_Helper_Form_Element_Text('feed_page_size', NULL, '10', '每页显示条数', '订阅页面每页显示多少篇文章，超过自动生成分页');
    $form->addInput($feed_page_size);

    $feed_per_source = new Typecho_Widget_Helper_Form_Element_Text('feed_per_source', NULL, '5', '单源显示条数', '每个订阅源最多抓取多少篇文章');
    $form->addInput($feed_per_source);

    $feed_cache = new Typecho_Widget_Helper_Form_Element_Text('feed_cache', NULL, '60', '缓存时间（分钟）', '多久刷新一次订阅数据，建议 30-120 分钟');
    $form->addInput($feed_cache);

    $feed_refresh_limit = new Typecho_Widget_Helper_Form_Element_Text('feed_refresh_limit', NULL, '2', '每日手动刷新次数', '同一 IP 每天允许手动刷新缓存的次数上限，设为 0 则不限制');
    $form->addInput($feed_refresh_limit);

    $feed_summary = new Typecho_Widget_Helper_Form_Element_Radio('feed_summary', ['ok'=>'显示','no'=>'隐藏'], 'no', '文章摘要', '是否在订阅列表中显示文章摘要');
    $form->addInput($feed_summary);

    $feed_summary_length = new Typecho_Widget_Helper_Form_Element_Text('feed_summary_length', NULL, '120', '摘要长度', '显示摘要时截取多少字符');
    $form->addInput($feed_summary_length);

    $feed_background = new Typecho_Widget_Helper_Form_Element_Radio('feed_background', ['ok'=>'开启','no'=>'关闭'], 'no', '文章背景图', '开启后抓取文章中的第一张图作为卡片背景半透明显示，无图则不显示');
    $form->addInput($feed_background);

    $form->addItem(new EchoHtml('</div>'));

    $form->addItem(new EchoHtml('<div class="tabContent">'));
    $form->addItem(new EchoHtml('<h4 style="margin:0 0 12px;font-size:14px;color:#8b5cf6;">友链管理</h4>'));

    $friend_link_switch = new Typecho_Widget_Helper_Form_Element_Radio('friend_link_switch', ['ok'=>'开启','no'=>'关闭'], 'no', '友链申请功能', '开启后友链页面会显示申请表单，并自动创建数据库表存储申请记录');
    $form->addInput($friend_link_switch);

    $friend_link_apply_tip = new Typecho_Widget_Helper_Form_Element_Textarea('friend_link_apply_tip', NULL, '需要交换链接的朋友，可以填写以下信息直接提交申请，审核通过后会自动显示在友链列表中。', '申请提示语', '显示在友链申请表单上方的提示文字');
    $form->addInput($friend_link_apply_tip);

    $friend_link_limit_ip = new Typecho_Widget_Helper_Form_Element_Text('friend_link_limit_ip', NULL, '3', '单 IP 每日申请次数', '同一 IP 每天最多提交多少次友链申请，防止恶意刷申请');
    $form->addInput($friend_link_limit_ip);

    $form->addItem(new EchoHtml('<div style="margin-top:20px;">'));
    $form->addItem(new EchoHtml('<h4 style="margin:0 0 12px;font-size:14px;color:#374151;">待审核友链</h4>'));

    $pendingLinks = baigeGetLinksByStatus(0);
    if (empty($pendingLinks)) {
        $form->addItem(new EchoHtml('<p style="color:#6b7280;font-size:13px;">暂无待审核友链。</p>'));
    } else {
        $form->addItem(new EchoHtml('<div style="border:1px solid #e5e7eb;border-radius:8px;background:#fff;overflow:hidden;">'));
        foreach ($pendingLinks as $link) {
            $card = '<div style="display:flex;flex-wrap:wrap;gap:12px;padding:12px;border-bottom:1px solid #f3f4f6;align-items:flex-start;box-sizing:border-box;">';
            $card .= '<div style="flex:1 1 220px;min-width:0;box-sizing:border-box;">';
            $card .= '<div style="font-weight:600;color:#374151;font-size:14px;word-break:break-word;">' . htmlspecialchars($link['name']) . '</div>';
            if (!empty($link['description'])) {
                $card .= '<div style="font-size:12px;color:#6b7280;margin-top:3px;word-break:break-word;">' . htmlspecialchars($link['description']) . '</div>';
            }
            $card .= '<div style="font-size:12px;margin-top:5px;word-break:break-all;"><a href="' . htmlspecialchars($link['url']) . '" target="_blank" style="color:#2563eb;">' . htmlspecialchars($link['url']) . '</a></div>';
            if (!empty($link['email'])) {
                $card .= '<div style="font-size:12px;color:#6b7280;margin-top:3px;word-break:break-all;">' . htmlspecialchars($link['email']) . '</div>';
            }
            $card .= '</div>';
            $card .= '<div style="display:flex;flex-wrap:wrap;gap:6px;flex:0 0 auto;align-items:flex-start;box-sizing:border-box;">';
            $card .= '<a style="display:inline-block;color:#fff;background:#10b981;padding:4px 10px;border-radius:4px;font-size:12px;text-decoration:none;white-space:nowrap;" href="' . Helper::options()->adminUrl . 'options-theme.php?friend_link_admin_action=approve&id=' . $link['id'] . '">通过</a>';
            $card .= '<a style="display:inline-block;color:#fff;background:#f59e0b;padding:4px 10px;border-radius:4px;font-size:12px;text-decoration:none;white-space:nowrap;" href="' . Helper::options()->adminUrl . 'options-theme.php?friend_link_admin_action=reject&id=' . $link['id'] . '">拒绝</a>';
            $card .= '<a style="display:inline-block;color:#fff;background:#6b7280;padding:4px 10px;border-radius:4px;font-size:12px;text-decoration:none;white-space:nowrap;" href="' . Helper::options()->adminUrl . 'options-theme.php?friend_link_admin_action=delete&id=' . $link['id'] . '" onclick="return confirm(\'确定删除？\')">删除</a>';
            $card .= '</div>';
            $card .= '</div>';
            $form->addItem(new EchoHtml($card));
        }
        $form->addItem(new EchoHtml('</div>'));
    }

    $form->addItem(new EchoHtml('<h4 style="margin:20px 0 12px;font-size:14px;color:#374151;">已通过友链</h4>'));
    $approvedLinks = baigeGetLinksByStatus(1);
    if (empty($approvedLinks)) {
        $form->addItem(new EchoHtml('<p style="color:#6b7280;font-size:13px;">暂无已通过友链。</p>'));
    } else {
        $form->addItem(new EchoHtml('<div style="border:1px solid #e5e7eb;border-radius:8px;background:#fff;overflow:hidden;">'));
        foreach ($approvedLinks as $link) {
            $card = '<div style="display:flex;flex-wrap:wrap;gap:12px;padding:12px;border-bottom:1px solid #f3f4f6;align-items:flex-start;box-sizing:border-box;">';
            $card .= '<div style="flex:1 1 220px;min-width:0;box-sizing:border-box;">';
            $card .= '<div style="font-weight:600;color:#374151;font-size:14px;word-break:break-word;">' . htmlspecialchars($link['name']) . '</div>';
            if (!empty($link['description'])) {
                $card .= '<div style="font-size:12px;color:#6b7280;margin-top:3px;word-break:break-word;">' . htmlspecialchars($link['description']) . '</div>';
            }
            $card .= '<div style="font-size:12px;margin-top:5px;word-break:break-all;"><a href="' . htmlspecialchars($link['url']) . '" target="_blank" style="color:#2563eb;">' . htmlspecialchars($link['url']) . '</a></div>';
            if (!empty($link['email'])) {
                $card .= '<div style="font-size:12px;color:#6b7280;margin-top:3px;word-break:break-all;">' . htmlspecialchars($link['email']) . '</div>';
            }
            $card .= '</div>';
            $card .= '<div style="display:flex;flex-wrap:wrap;gap:6px;flex:0 0 auto;align-items:flex-start;box-sizing:border-box;">';
            $card .= '<a style="display:inline-block;color:#fff;background:#f59e0b;padding:4px 10px;border-radius:4px;font-size:12px;text-decoration:none;white-space:nowrap;" href="' . Helper::options()->adminUrl . 'options-theme.php?friend_link_admin_action=reject&id=' . $link['id'] . '">改为拒绝</a>';
            $card .= '<a style="display:inline-block;color:#fff;background:#6b7280;padding:4px 10px;border-radius:4px;font-size:12px;text-decoration:none;white-space:nowrap;" href="' . Helper::options()->adminUrl . 'options-theme.php?friend_link_admin_action=delete&id=' . $link['id'] . '" onclick="return confirm(\'确定删除？\')">删除</a>';
            $card .= '</div>';
            $card .= '</div>';
            $form->addItem(new EchoHtml($card));
        }
        $form->addItem(new EchoHtml('</div>'));
    }

    $form->addItem(new EchoHtml('<h4 style="margin:20px 0 12px;font-size:14px;color:#374151;">已拒绝友链</h4>'));
    $rejectedLinks = baigeGetLinksByStatus(2);
    if (empty($rejectedLinks)) {
        $form->addItem(new EchoHtml('<p style="color:#6b7280;font-size:13px;">暂无已拒绝友链。</p>'));
    } else {
        $form->addItem(new EchoHtml('<div style="border:1px solid #e5e7eb;border-radius:8px;background:#fff;overflow:hidden;">'));
        foreach ($rejectedLinks as $link) {
            $card = '<div style="display:flex;flex-wrap:wrap;gap:12px;padding:12px;border-bottom:1px solid #f3f4f6;align-items:flex-start;box-sizing:border-box;">';
            $card .= '<div style="flex:1 1 220px;min-width:0;box-sizing:border-box;">';
            $card .= '<div style="font-weight:600;color:#374151;font-size:14px;word-break:break-word;">' . htmlspecialchars($link['name']) . '</div>';
            if (!empty($link['description'])) {
                $card .= '<div style="font-size:12px;color:#6b7280;margin-top:3px;word-break:break-word;">' . htmlspecialchars($link['description']) . '</div>';
            }
            $card .= '<div style="font-size:12px;margin-top:5px;word-break:break-all;"><a href="' . htmlspecialchars($link['url']) . '" target="_blank" style="color:#2563eb;">' . htmlspecialchars($link['url']) . '</a></div>';
            if (!empty($link['email'])) {
                $card .= '<div style="font-size:12px;color:#6b7280;margin-top:3px;word-break:break-all;">' . htmlspecialchars($link['email']) . '</div>';
            }
            $card .= '</div>';
            $card .= '<div style="display:flex;flex-wrap:wrap;gap:6px;flex:0 0 auto;align-items:flex-start;box-sizing:border-box;">';
            $card .= '<a style="display:inline-block;color:#fff;background:#10b981;padding:4px 10px;border-radius:4px;font-size:12px;text-decoration:none;white-space:nowrap;" href="' . Helper::options()->adminUrl . 'options-theme.php?friend_link_admin_action=approve&id=' . $link['id'] . '">重新通过</a>';
            $card .= '<a style="display:inline-block;color:#fff;background:#6b7280;padding:4px 10px;border-radius:4px;font-size:12px;text-decoration:none;white-space:nowrap;" href="' . Helper::options()->adminUrl . 'options-theme.php?friend_link_admin_action=delete&id=' . $link['id'] . '" onclick="return confirm(\'确定删除？\')">删除</a>';
            $card .= '</div>';
            $card .= '</div>';
            $form->addItem(new EchoHtml($card));
        }
        $form->addItem(new EchoHtml('</div>'));
    }

    $form->addItem(new EchoHtml('</div>'));
    $form->addItem(new EchoHtml('</div>'));
}

// 自定义字段
function themeFields($layout)
{
    $listcss = new Typecho_Widget_Helper_Form_Element_Radio('listcss',[
        'bt'=>'标题','tp'=>'三宫格','si'=>'四宫格','jiu'=>'九宫格','dx'=>'大小宫格','dx6'=>'大六宫','zz'=>'正常'
    ],'zz','列表样式','本篇文章在列表中显示的样式');
    $layout->addItem($listcss);

    $suiji = new Typecho_Widget_Helper_Form_Element_Radio('suiji',['no'=>'关闭图片','yessj'=>'随机','nosj'=>'不随机'],'nosj','图片功能','文章封面图使用固定图还是随机');
    $layout->addItem($suiji);

    $miaoss = new Typecho_Widget_Helper_Form_Element_Radio('miaoss',['no'=>'隐藏','ok'=>'显示'],'ok','摘要开关','是否显示文章摘要');
    $layout->addItem($miaoss);

    $imgurl = new Typecho_Widget_Helper_Form_Element_Text('imgurl',null,null,'新缩略图','文章封面图地址');
    $layout->addItem($imgurl);

    $zhaiyao = new Typecho_Widget_Helper_Form_Element_Textarea('zhaiyao',null,null,'文章摘要','列表中显示的简介内容');
    $layout->addItem($zhaiyao);

    $weizhi = new Typecho_Widget_Helper_Form_Element_Textarea('weizhi',null,null,'坐标位置','用于特殊布局使用');
    $layout->addItem($weizhi);
}

// 随机文章
#[AllowDynamicProperties]
class Widget_Post_rand extends Widget_Abstract_Contents
{
    public function __construct($request, $response, $params = NULL)
    {
        parent::__construct($request, $response, $params);
        $this->parameter->setDefault(['pageSize'=>$this->options->commentsListSize,'parentId'=>0,'ignoreAuthor'=>false]);
    }
    public function execute()
    {
        $select = $this->select()->from('table.contents')
            ->where("table.contents.password IS NULL OR table.contents.password = ''")
            ->where('table.contents.status = ?','publish')
            ->where('table.contents.created <= ?',time())
            ->where('table.contents.type = ?','post')
            ->limit($this->parameter->pageSize)
            ->order('RAND()');
        $this->db->fetchAll($select,[$this,'push']);
    }
}

// 是否置顶
function isSticky($cid) {
    $sticky = Typecho_Widget::widget('Widget_Options')->sticky ?? null;
    if(empty($sticky)){
        return false;
    }
    $sticky_cids = explode(',', strtr($sticky, ' ', ','));
    return in_array($cid, $sticky_cids);
}

// 首页URL
function getWebsiteHomeUrl()
{
    $rewrite = Helper::options()->rewrite;
    $tmpUrl = Helper::options()->siteUrl;
    if(!$rewrite){ $tmpUrl .= 'index.php/'; }
    return $tmpUrl;
}

// 获取真实IP
function online_get_real_ip() {
    $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($keys as $k) {
        if (!empty($_SERVER[$k])) {
            foreach (explode(',', $_SERVER[$k]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

// 判断是否为爬虫
function online_is_bot($userAgent) {
    $botPattern = '/bot|crawl|spider|slurp|bingpreview|google|baidu|yandex|sogou|360spider|bytespider|petalbot|duckduckgo|facebookexternalhit|twitterbot|whatsapp|iphonecpu|slurp|wget|curl|python-requests|scrapy|httpclient| okhttp|zgrab|guzzle|java\//i';
    return !empty($userAgent) && preg_match($botPattern, $userAgent);
}

// 在线人数
function online_users($expire = 10) {
    $cacheDir = __DIR__ . '/cache/';
    // 创建目录并容错
    if (!is_dir($cacheDir)) {
        $mk = @mkdir($cacheDir, 0755, true);
        if (!$mk) return 0;
    }
    $filename = $cacheDir . 'online.txt';
    $tmpFile  = $filename . '.tmp';
    $lockFile = $filename . '.lock';

    $expireSeconds = (int)$expire * 60;
    $now = time();

    $userIp = online_get_real_ip();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    // 过滤搜索引擎爬虫和自动化请求
    if (online_is_bot($userAgent)) {
        $userAgent = 'bot';
    }

    $currentUid = md5($userIp . '|' . $userAgent);
    $cookieName = 'xm_online_visitor';
    $isNewVisitor = !isset($_COOKIE[$cookieName]);

    // cookie设置
    $isHttps = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) === 'on';
    if ($isNewVisitor) {
        setcookie(
            $cookieName,
            $currentUid,
            $now + 86400,
            '/',
            '',
            $isHttps,
            true
        );
    }

    // 尝试获取独占锁
    $fpLock = @fopen($lockFile, 'c');
    $hasLock = false;
    if ($fpLock) {
        $hasLock = flock($fpLock, LOCK_EX | LOCK_NB);
    }

    $onlineUsers = [];
    // 读取文件全部屏蔽警告
    if (file_exists($filename)) {
        $content = @file_get_contents($filename);
        if ($content !== false) {
            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                $parts = explode('|', $line);
                if (count($parts)!==2) continue;
                list($uid,$stime) = $parts;
                $stime = (int)$stime;
                if($now-$stime <= $expireSeconds) $onlineUsers[$uid] = $stime;
            }
        }
    }

    // 没拿到锁时只返回当前计数
    if (!$hasLock) {
        if($fpLock) fclose($fpLock);
        return count($onlineUsers);
    }

    // 更新当前访客时间戳
    $onlineUsers[$currentUid] = $now;

    // 生成待写入内容
    $data = '';
    foreach($onlineUsers as $uid=>$stime) $data .= "$uid|$stime\n";

    // 清理残留临时文件并安全写入
    if(file_exists($tmpFile)) @unlink($tmpFile);
    @file_put_contents($tmpFile, $data);
    if(file_exists($tmpFile)){
        rename($tmpFile, $filename);
    }

    // 释放锁
    if($fpLock){
        flock($fpLock, LOCK_UN);
        fclose($fpLock);
    }

    return count($onlineUsers);
}

// 总字符数（文件缓存，避免每次请求都遍历全表）
function allOfCharacters() {
    $cacheDir = __DIR__ . '/cache/';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    $cacheFile = $cacheDir . 'all_chars.cache';
    $ttl = 3600; // 1 小时

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
        return file_get_contents($cacheFile);
    }

    $chars = 0;
    $db = Typecho_Db::get();
    $rows = $db->fetchAll($db->select('text')->from('table.contents'));
    foreach($rows as $row) $chars += mb_strlen(trim($row['text']),'UTF-8');
    $unit = '';
    if($chars>=10000){ $chars/=10000; $unit='w'; }
    elseif($chars>=1000){ $chars/=1000; $unit='k'; }
    $result = sprintf('%.2lf %s',$chars,$unit);
    @file_put_contents($cacheFile, $result);
    return $result;
}

// 过滤文章中的短代码
function stripShortcodes($content)
{
    // [music ...] 音乐短代码
    $content = preg_replace('/\[music\s+[^\]]*\]/i', '', $content);

    // 视频短代码
    $content = preg_replace('/\{video\s+[^}]*\}/i', '', $content);

    // 音频短代码
    $content = preg_replace('/\{mp3\s+[^}]*\}/i', '', $content);

    // B站视频短代码
    $content = preg_replace('/\{bl\s+[^}]*\}/i', '', $content);

    // 成对短代码
    $content = preg_replace('/\{ts\s+[^}]*\}.*?\{\/ts\}/is', '', $content);

    // 居中文本短代码
    $content = preg_replace('/\{center\}.*?\{\/center\}/is', '', $content);

    // 登录可见短代码
    $content = preg_replace('/\{login\}.*?\{\/login\}/is', '', $content);

    // 手风琴短代码
    $content = preg_replace('/\{sfq\s+[^}]*\}.*?\{\/sfq\}/is', '', $content);

    // 手风琴子项短代码
    $content = preg_replace('/\{sfqa\s+[^}]*\}.*?\{\/sfqa\}/is', '', $content);

    // 锚点短代码
    $content = preg_replace('/\{an\s+[^}]*\}.*?\{\/an\}/is', '', $content);

    // 时间线短代码
    $content = preg_replace('/\{timeline\}.*?\{\/timeline\}/is', '', $content);

    // 播放列表短代码
    $content = preg_replace('/\{playlist\}.*?\{\/playlist\}/is', '', $content);

    return $content;
}

// 获取带段落格式的文章摘要
function getExcerptWithParagraphs($archive, $length = 250, $trim = '...')
{
    // 优先使用自定义摘要
    if (!empty($archive->fields->zhaiyao)) {
        $content = $archive->fields->zhaiyao;
        // 过滤短代码
        $content = stripShortcodes($content);
        // 处理 {br} 短代码
        if (function_exists('xm') && method_exists('xm', 'ubbhh')) {
            $content = xm::ubbhh($content);
        }
        // 把换行符分割成段落
        $paragraphs = preg_split('/\r\n|\r|\n/', $content);
        $result = '';
        $count = 0;
        foreach ($paragraphs as $para) {
            $para = trim($para);
            if ($para === '') continue;
            $paraLen = mb_strlen(strip_tags($para), 'UTF-8');
            if ($count + $paraLen > $length && $count > 0) {
                $result .= $trim;
                break;
            }
            $result .= '<p>' . htmlspecialchars($para) . '</p>';
            $count += $paraLen;
            if ($count >= $length) {
                $result .= $trim;
                break;
            }
        }
        return $result;
    }

    // 自动摘要
    $html = $archive->content;
    // 过滤短代码
    $html = stripShortcodes($html);
    // 标签
    $html = strip_tags($html, '<p><br>');
    // 清理空段落
    $html = preg_replace('/<p[^>]*>\s*<\/p>/is', '', $html);

    // 匹配段落块
    preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $html, $matches, PREG_SET_ORDER);

    $result = '';
    $count = 0;

    foreach ($matches as $match) {
        $text = strip_tags($match[0]);
        $textLen = mb_strlen($text, 'UTF-8');

        if ($count + $textLen > $length && $count > 0) {
            $result .= $trim;
            break;
        }

        $result .= $match[0];
        $count += $textLen;

        if ($count >= $length) {
            $result .= $trim;
            break;
        }
    }

    return $result;
}

// 自适应副标题
function getSiteSubTitle()
{
    $opts = Helper::options();
    // 读取系统自带副标题
    $sysDesc = trim((string) $opts->description);
    if ($sysDesc !== '') {
        return htmlspecialchars($sysDesc);
    }
    // 读取主题后台自定义副标题
    $themeSub = trim((string) $opts->subTitle);
    if ($themeSub !== '') {
        return htmlspecialchars($themeSub);
    }
    return '';
}

// 获取文章最近评论
function getRecentComments($cid, $limit = 5)
{
    $db = Typecho_Db::get();
    $options = Helper::options();
    $ownerMail = strtolower((string)$options->mail);

    // 获取所有管理员 UID（同请求内缓存，避免每个文章重复查询）
    static $adminIds = null;
    if ($adminIds === null) {
        $adminRows = $db->fetchAll($db->select('uid')->from('table.users')->where('group = ?', 'administrator'));
        $adminIds = array_map('intval', array_column($adminRows, 'uid'));
    }

    // 多取一些再过滤
    $rows = $db->fetchAll($db->select()
        ->from('table.comments')
        ->where('cid = ?', $cid)
        ->where('status = ?', 'approved')
        ->order('created', Typecho_Db::SORT_DESC)
        ->limit(50)
    );

    $comments = [];
    foreach ($rows as $row) {
        // 过滤博主
        $rowAuthorId = isset($row['authorId']) ? intval($row['authorId']) : 0;
        $rowMail = strtolower((string)($row['mail'] ?? ''));
        if (in_array($rowAuthorId, $adminIds, true)) continue;
        if ($rowAuthorId === 0 && $ownerMail !== '' && $rowMail === $ownerMail) continue;

        if (count($comments) >= $limit) break;

        // 解析回复对象
        $replyHtml = '';
        if (!empty($row['parent']) && $row['parent'] > 0) {
            $parent = $db->fetchRow($db->select('author, coid')
                ->from('table.comments')
                ->where('coid = ?', $row['parent'])
            );
            if ($parent) {
                $replyHtml = '<span class="baige-parent">@' . htmlspecialchars($parent['author']) . '</span> ';
            }
        }

        $content = $row['text'];

        // 处理私密评论
        if (strpos($content, '{secret') !== false) {
            $content = preg_replace('/{secret}(.+?){\/secret}/', '[私密]', $content);
        }

        // 处理表情
        $content = xm::biaoqing($content);

        // Markdown 图片灯箱
        $imageHtml = '';
        $content = preg_replace_callback(
            '/!\[(.*?)\]\((.*?)\)/i',
            function ($m) use (&$imageHtml, $options) {
                $src = $m[2];
                $alt = $m[1];
                $fancybox = isset($options->fancybox) && ($options->fancybox === 'oka' || $options->fancybox === 'okc');
                if ($fancybox) {
                    $imageHtml .= '<a data-fancybox="baige-home-comment-gallery" href="' . htmlspecialchars($src) . '"><img class="baige-home-comment-photo" src="' . htmlspecialchars($src) . '" alt="' . htmlspecialchars($alt) . '"></a>';
                } else {
                    $imageHtml .= '<img class="baige-home-comment-photo" src="' . htmlspecialchars($src) . '" alt="' . htmlspecialchars($alt) . '">';
                }
                return '';
            },
            $content
        );

        // 标签
        $content = strip_tags($content, '<img><a>');

        // 纯文本截断
        $plain = trim(strip_tags($content));
        // 首页评论中过滤掉网址文本
        $plain = preg_replace('/https?:\/\/[^\s<>"\']+/i', '', $plain);
        $plain = preg_replace('/\s+/', ' ', $plain);
        if (mb_strlen($plain, 'UTF-8') > 50) {
            $plain = mb_substr($plain, 0, 50, 'UTF-8') . '…';
        }

        $comments[] = [
            'coid'      => $row['coid'],
            'author'    => $row['author'],
            'mail'      => $row['mail'],
            'created'   => $row['created'],
            'reply'     => $replyHtml,
            'text'      => $plain,
            'images'    => $imageHtml,
            'parent'    => $row['parent'],
            'url'       => shouldShowCommentUrl($row['created'] ?? 0) ? ($row['url'] ?? '') : '',
            'permalink' => Helper::options()->siteUrl . 'archives/' . $cid . '/#comment-' . $row['coid'],
        ];
    }

    return $comments;
}

// ==================== Feed 订阅功能 ====================

/**
 * 从 URL 提取域名
 * @param string $url
 * @return string
 */
function baigeFeedDomain($url)
{
    $host = @parse_url($url, PHP_URL_HOST);
    return $host ?: '';
}

/**
 * 过滤摘要中的网址，只保留文字
 * @param string $text
 * @return string
 */
function baigeFeedCleanText($text)
{
    $text = strip_tags($text);
    // 去掉 URL（http/https/ftp）
    $text = preg_replace('/https?:\/\/\S+/i', '', $text);
    $text = preg_replace('/ftp:\/\/\S+/i', '', $text);
    // 去掉 Markdown 链接语法，只留文字 [text](url) -> text
    $text = preg_replace('/\[(.*?)\]\(.*?\)/', '$1', $text);
    // 去掉多余空白
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

/**
 * 判断图片 URL 是否为文章配图（过滤表情、图标、base64、svg 等）
 * @param string $url
 * @return bool
 */
function baigeFeedIsRealImage($url)
{
    if (empty($url)) return false;
    $low = strtolower($url);
    // 过滤 base64 内联图
    if (strpos($low, 'data:image') === 0) return false;
    // 过滤 svg
    if (substr($low, -4) === '.svg') return false;
    // 过滤表情/小图标
    $bad = ['emoji', 'smiley', 'face', 'icon', 'avatar', 'gravatar', 'logo', 'qrcode', 'weixin', 'wx.qlogo'];
    foreach ($bad as $b) {
        if (strpos($low, $b) !== false) return false;
    }
    return true;
}

/**
 * 从 HTML 中提取第一张文章配图 URL
 * @param string $html
 * @return string
 */
function baigeFeedFirstImage($html)
{
    if (empty($html)) return '';

    // 先找所有 <img> 标签
    if (preg_match_all('/<img[^>]+>/i', $html, $imgs)) {
        foreach ($imgs[0] as $img) {
            $url = '';
            // 优先懒加载真实大图
            if (preg_match('/data-src=["\']([^"\']+)["\']/i', $img, $m)) {
                $url = $m[1];
            } elseif (preg_match('/data-original=["\']([^"\']+)["\']/i', $img, $m)) {
                $url = $m[1];
            } elseif (preg_match('/src=["\']([^"\']+)["\']/i', $img, $m)) {
                $url = $m[1];
            }
            $url = trim($url);
            if (baigeFeedIsRealImage($url)) {
                return $url;
            }
        }
    }

    return '';
}

/**
 * 抓取单个 RSS/Atom 订阅源
 * @param string $url
 * @param int $limit
 * @return array ['items'=>..., 'success'=>bool]
 */
function baigeFetchFeed($url, $limit = 5)
{
    $items = [];
    if (empty($url)) return ['items' => $items, 'success' => false];

    $domain = baigeFeedDomain($url);
    $favicon = $domain ? 'https://' . $domain . '/favicon.ico' : '';

    // 优先使用 cURL 抓取，超时更短，避免单个源拖慢整页；无 cURL 时回退 file_get_contents
    $content = '';
    $httpCode = 0;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; FeedReader)',
        ]);
        $content = @curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } elseif (ini_get('allow_url_fopen')) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'user_agent' => 'Mozilla/5.0 (compatible; FeedReader)'
            ]
        ]);
        $content = @file_get_contents($url, false, $context);
        $httpCode = !empty($content) ? 200 : 0;
    }
    if (empty($content) || $httpCode < 200 || $httpCode >= 400) return ['items' => $items, 'success' => false];

    $xml = @simplexml_load_string($content);
    if (!$xml) return ['items' => $items, 'success' => false];

    // 提取订阅源站点名称
    $source = '';
    if (isset($xml->channel->title)) {
        $source = (string)$xml->channel->title;
    } elseif (isset($xml->title)) {
        $source = (string)$xml->title;
    }
    if (empty($source)) {
        $source = $domain;
    }

    $hasItems = false;
    if (isset($xml->channel->item)) {
        // RSS 2.0
        $hasItems = true;
        foreach ($xml->channel->item as $item) {
            $link = (string)$item->link;
            $title = html_entity_decode((string)$item->title, ENT_QUOTES, 'UTF-8');
            $summary = html_entity_decode((string)$item->description, ENT_QUOTES, 'UTF-8');
            // 优先读取 content:encoded 完整内容，用于提取首图
            $contentEncoded = '';
            $contentNs = $item->children('content', true);
            if (isset($contentNs->encoded)) {
                $contentEncoded = html_entity_decode((string)$contentNs->encoded, ENT_QUOTES, 'UTF-8');
            }
            $pubDate = (string)$item->pubDate;
            $time = strtotime($pubDate);
            if ($time === false) $time = time();
            $items[] = [
                'title'   => trim($title),
                'link'    => trim($link),
                'summary' => baigeFeedCleanText($summary),
                'image'   => baigeFeedFirstImage($contentEncoded ?: $summary),
                'time'    => $time,
                'date'    => date('Y-m-d H:i', $time),
                'domain'  => $domain,
                'favicon' => $favicon,
                'source'  => html_entity_decode(trim($source), ENT_QUOTES, 'UTF-8'),
            ];
            if (count($items) >= $limit) break;
        }
    } elseif (isset($xml->entry)) {
        // Atom
        $hasItems = true;
        foreach ($xml->entry as $entry) {
            $title = html_entity_decode((string)$entry->title, ENT_QUOTES, 'UTF-8');
            $link = '';
            foreach ($entry->link as $l) {
                $rel = (string)$l['rel'];
                if ($rel === 'alternate' || $rel === '') {
                    $link = (string)$l['href'];
                    break;
                }
            }
            $summary = html_entity_decode((string)$entry->summary, ENT_QUOTES, 'UTF-8');
            $content = html_entity_decode((string)$entry->content, ENT_QUOTES, 'UTF-8');
            $desc = $summary ?: $content;
            $published = (string)$entry->published;
            $updated = (string)$entry->updated;
            $time = strtotime($published ?: $updated);
            if ($time === false) $time = time();
            $items[] = [
                'title'   => trim($title),
                'link'    => trim($link),
                'summary' => baigeFeedCleanText($desc),
                'image'   => baigeFeedFirstImage($desc),
                'time'    => $time,
                'date'    => date('Y-m-d H:i', $time),
                'domain'  => $domain,
                'favicon' => $favicon,
                'source'  => html_entity_decode(trim($source), ENT_QUOTES, 'UTF-8'),
            ];
            if (count($items) >= $limit) break;
        }
    }

    return ['items' => $items, 'success' => $hasItems];
}

/**
 * 获取所有订阅源文章（带缓存）
 * @param mixed $options
 * @return array
 */
function baigeGetFeeds($options = null)
{
    if ($options === null) $options = Helper::options();

    $cacheFile = dirname(__FILE__) . '/cache/feed_cache_v4.json';
    $cacheTime = isset($options->feed_cache) ? intval($options->feed_cache) : 60;
    if ($cacheTime < 1) $cacheTime = 60;

    // 尝试读取缓存
    if (file_exists($cacheFile)) {
        $data = @json_decode(file_get_contents($cacheFile), true);
        if (!empty($data['time']) && (time() - $data['time']) < ($cacheTime * 60)) {
            return [
                'items' => $data['items'] ?? [],
                'stats' => $data['stats'] ?? [
                    'total' => 0,
                    'success' => 0,
                    'failed' => 0,
                    'articles' => 0,
                ],
            ];
        }
    }

    $allItems = [];
    $urls = isset($options->feed_urls) ? $options->feed_urls : '';
    $urls = array_filter(array_map('trim', explode("\n", $urls)));

    $perSource = isset($options->feed_per_source) ? intval($options->feed_per_source) : 5;
    if ($perSource < 1) $perSource = 5;

    $success = 0;
    $failed = 0;
    foreach ($urls as $url) {
        $result = baigeFetchFeed($url, $perSource);
        if ($result['success']) {
            $success++;
        } else {
            $failed++;
        }
        $allItems = array_merge($allItems, $result['items']);
    }

    // 按时间倒序
    usort($allItems, function ($a, $b) {
        return $b['time'] - $a['time'];
    });

    $limit = isset($options->feed_count) ? intval($options->feed_count) : 20;
    if ($limit < 1) $limit = 20;
    $allItems = array_slice($allItems, 0, $limit);

    $stats = [
        'total' => count($urls),
        'success' => $success,
        'failed' => $failed,
        'articles' => count($allItems),
    ];

    // 写入缓存
    $cacheDir = dirname($cacheFile);
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
    @file_put_contents($cacheFile, json_encode(['time' => time(), 'items' => $allItems, 'stats' => $stats]));

    return ['items' => $allItems, 'stats' => $stats];
}

/**
 * 渲染订阅列表 HTML
 * @param mixed $options
 * @param int $page 当前页码
 * @param int $pageSize 每页条数
 * @return string
 */
function baigeRenderFeed($options = null, $page = 1, $pageSize = 10)
{
    if ($options === null) $options = Helper::options();

    if (!isset($options->feed_switch) || $options->feed_switch !== 'ok') {
        return '<p class="baige-feed-tip">Feed 订阅功能未开启。</p>';
    }

    $feedResult = baigeGetFeeds($options);
    $items = $feedResult['items'] ?? [];
    if (empty($items)) {
        return '<p class="baige-feed-tip">暂无订阅更新，请检查后台订阅地址是否正确。</p>';
    }

    $page = max(1, intval($page));
    $pageSize = max(1, intval($pageSize));
    $total = count($items);
    $totalPages = max(1, ceil($total / $pageSize));
    $page = min($page, $totalPages);
    $items = array_slice($items, ($page - 1) * $pageSize, $pageSize);

    $showSummary = isset($options->feed_summary) && $options->feed_summary === 'ok';
    $summaryLength = isset($options->feed_summary_length) ? intval($options->feed_summary_length) : 120;
    $showBackground = isset($options->feed_background) && $options->feed_background === 'ok';

    $html = '<div class="baige-feed-list">';
    foreach ($items as $item) {
        $title = htmlspecialchars($item['title']);
        $link = htmlspecialchars($item['link']);
        $date = htmlspecialchars($item['date']);
        $favicon = !empty($item['favicon']) ? htmlspecialchars($item['favicon']) : '';
        $domain = !empty($item['domain']) ? htmlspecialchars($item['domain']) : '';
        $source = !empty($item['source']) ? htmlspecialchars($item['source']) : $domain;
        $image = !empty($item['image']) ? htmlspecialchars($item['image']) : '';
        $itemClass = 'baige-feed-item';
        if ($showBackground && $image) {
            $itemClass .= ' baige-feed-item-bg';
        }
        $html .= '<div class="' . $itemClass . '">';
        if ($showBackground && $image) {
            $html .= '<div class="baige-feed-bg" style="background-image:url(' . $image . ')"></div>';
        }
        $html .= '<div class="baige-feed-header">';
        if ($favicon) {
            $html .= '<img src="' . $favicon . '" alt="' . $domain . '" class="baige-feed-favicon" onerror="this.src=\'/no.jpg\';this.onerror=null;">';
        }
        $html .= '<a href="' . $link . '" target="_blank" rel="noopener" class="baige-feed-title">';
        $html .= '<span class="baige-feed-title-text">' . $title . '</span>';
        $html .= '<span class="baige-feed-date">' . $date . '</span>';
        $html .= '</a>';
        $html .= '</div>';
        if ($showSummary && !empty($item['summary'])) {
            $summary = strip_tags($item['summary']);
            if (mb_strlen($summary, 'UTF-8') > $summaryLength) {
                $summary = mb_substr($summary, 0, $summaryLength, 'UTF-8') . '…';
            }
            $html .= '<p class="baige-feed-summary">' . htmlspecialchars($summary) . '</p>';
        }
        if ($source) {
            $html .= '<div class="baige-feed-source">来自：' . $source . '</div>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';

    return $html;
}

/**
 * 友链申请：确保数据库表存在
 * @return bool
 */
function baigeEnsureLinksTable()
{
    static $checked = false;
    if ($checked) return true;
    $checked = true;

    try {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $table = $prefix . 'dage_links';
        $adapter = $db->getAdapterName();

        if (strpos($adapter, 'Mysql') !== false || strpos($adapter, 'Pdo_Mysql') !== false) {
            $sql = "SHOW TABLES LIKE '" . $table . "'";
            $result = $db->fetchAll($sql);
            if (!empty($result)) return true;

            $createSql = "CREATE TABLE IF NOT EXISTS `" . $table . "` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `name` varchar(100) NOT NULL DEFAULT '',
                `url` varchar(255) NOT NULL DEFAULT '',
                `avatar` varchar(255) NOT NULL DEFAULT '',
                `description` varchar(255) NOT NULL DEFAULT '',
                `email` varchar(100) NOT NULL DEFAULT '',
                `rss` varchar(255) NOT NULL DEFAULT '',
                `status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '0 pending 1 approved 2 rejected',
                `ip` varchar(100) NOT NULL DEFAULT '',
                `created_at` int(10) unsigned NOT NULL DEFAULT '0',
                `updated_at` int(10) unsigned NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`),
                KEY `status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
            $db->query($createSql);
        } elseif (strpos($adapter, 'Sqlite') !== false || strpos($adapter, 'Pdo_SQLite') !== false) {
            $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name='" . $table . "'";
            $result = $db->fetchAll($sql);
            if (!empty($result)) return true;

            $createSql = "CREATE TABLE IF NOT EXISTS `" . $table . "` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `name` TEXT DEFAULT '',
                `url` TEXT DEFAULT '',
                `avatar` TEXT DEFAULT '',
                `description` TEXT DEFAULT '',
                `email` TEXT DEFAULT '',
                `rss` TEXT DEFAULT '',
                `status` INTEGER DEFAULT 0,
                `ip` TEXT DEFAULT '',
                `created_at` INTEGER DEFAULT 0,
                `updated_at` INTEGER DEFAULT 0
            );";
            $db->query($createSql);
            $db->query("CREATE INDEX IF NOT EXISTS idx_status ON `" . $table . "` (`status`);");
        }
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * 获取已审核友链
 * @return array
 */
function baigeGetApprovedLinks()
{
    try {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $table = $prefix . 'dage_links';
        $rows = $db->fetchAll($db->select()->from($table)->where('status = ?', 1)->order('created_at', Typecho_Db::SORT_DESC));
        return $rows ?: [];
    } catch (Exception $e) {
        return [];
    }
}

/**
 * 按状态获取友链
 * @param int|array $status
 * @return array
 */
function baigeGetLinksByStatus($status = 0)
{
    try {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $table = $prefix . 'dage_links';
        $query = $db->select()->from($table);
        if (is_array($status)) {
            $query->where('status IN ?', $status);
        } else {
            $query->where('status = ?', $status);
        }
        $rows = $db->fetchAll($query->order('created_at', Typecho_Db::SORT_DESC));
        return $rows ?: [];
    } catch (Exception $e) {
        return [];
    }
}

/**
 * 添加友链申请
 * @param array $data
 * @return bool
 */
function baigeAddLinkApply($data)
{
    try {
        baigeEnsureLinksTable();
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $table = $prefix . 'dage_links';
        $now = time();
        $db->query($db->insert($table)->rows([
            'name' => mb_substr(trim($data['name'] ?? ''), 0, 100),
            'url' => mb_substr(trim($data['url'] ?? ''), 0, 255),
            'avatar' => mb_substr(trim($data['avatar'] ?? ''), 0, 255),
            'description' => mb_substr(trim($data['description'] ?? ''), 0, 255),
            'email' => mb_substr(trim($data['email'] ?? ''), 0, 100),
            'rss' => mb_substr(trim($data['rss'] ?? ''), 0, 255),
            'status' => 0,
            'ip' => $data['ip'] ?? '',
            'created_at' => $now,
            'updated_at' => $now,
        ]));
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * 更新友链状态
 * @param int $id
 * @param int $status
 * @return bool
 */
function baigeUpdateLinkStatus($id, $status)
{
    try {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $table = $prefix . 'dage_links';
        $db->query($db->update($table)->rows([
            'status' => $status,
            'updated_at' => time(),
        ])->where('id = ?', $id));
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * 删除友链
 * @param int $id
 * @return bool
 */
function baigeDeleteLink($id)
{
    try {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $table = $prefix . 'dage_links';
        $db->query($db->delete($table)->where('id = ?', $id));
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * 规范化域名：转小写并去除 www. 前缀
 * @param string $url URL 或域名
 * @return string
 */
function baigeNormalizeDomain($url)
{
    if (empty($url)) return '';
    $tmp = $url;
    if (strpos($tmp, 'http://') !== 0 && strpos($tmp, 'https://') !== 0) {
        $tmp = 'https://' . $tmp;
    }
    $host = parse_url($tmp, PHP_URL_HOST);
    if (empty($host)) return '';
    $host = strtolower($host);
    if (strpos($host, 'www.') === 0) {
        $host = substr($host, 4);
    }
    return $host;
}

/**
 * 判断来路域名是否属于某个友链站点
 * 规则：完全相等，或来路是友链域名的子域名
 * @param string $refererDomain 来路域名（已规范化）
 * @param string $linkDomain 友链域名（已规范化）
 * @return bool
 */
function baigeMatchRefererDomain($refererDomain, $linkDomain)
{
    if (empty($refererDomain) || empty($linkDomain)) return false;
    if ($refererDomain === $linkDomain) return true;
    $suffix = '.' . $linkDomain;
    return substr($refererDomain, -strlen($suffix)) === $suffix;
}

/**
 * 获取友链来访统计文件路径
 * @return string
 */
function baigeGetLinkStatsFile()
{
    $dir = __TYPECHO_ROOT_DIR__ . '/usr/themes/dage/cache/links/';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir . 'stats.json';
}

/**
 * 读取友链来访统计
 * @return array
 */
function baigeReadLinkStats()
{
    $file = baigeGetLinkStatsFile();
    if (!file_exists($file)) return [];
    $content = @file_get_contents($file);
    return $content ? (json_decode($content, true) ?: []) : [];
}

/**
 * 写入友链来访统计
 * @param array $stats
 * @return bool
 */
function baigeWriteLinkStats($stats)
{
    $file = baigeGetLinkStatsFile();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return @file_put_contents($file, json_encode($stats, JSON_UNESCAPED_UNICODE)) !== false;
}

/**
 * 记录一次友链来访
 * @param string $refererUrl 来路 URL
 * @param array $links 友链列表（缓存中的结构）
 */
function baigeRecordLinkClick($refererUrl, $links)
{
    if (empty($refererUrl) || empty($links)) return;

    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (function_exists('online_is_bot') && online_is_bot($userAgent)) return;

    $refererDomain = baigeNormalizeDomain($refererUrl);
    if (empty($refererDomain)) return;

    // 排除本站来路
    $siteDomain = baigeNormalizeDomain(Helper::options()->siteUrl);
    if ($siteDomain && baigeMatchRefererDomain($refererDomain, $siteDomain)) return;

    $matchedDomain = '';
    foreach ($links as $link) {
        if (!empty($link['norank'])) continue;
        $url = $link['url'] ?? '';
        if (empty($url)) continue;
        $linkDomain = baigeNormalizeDomain($url);
        if (empty($linkDomain)) continue;
        if (baigeMatchRefererDomain($refererDomain, $linkDomain)) {
            $matchedDomain = $linkDomain;
            break;
        }
    }

    if (empty($matchedDomain)) return;

    $stats = baigeReadLinkStats();
    if (!isset($stats[$matchedDomain])) {
        $stats[$matchedDomain] = ['count' => 0, 'last_at' => 0, 'ips' => []];
    }

    $now = time();
    $ip = function_exists('baigeGetClientIp') ? baigeGetClientIp() : ($_SERVER['REMOTE_ADDR'] ?? '');
    $cooldown = 300; // 同一 IP 5 分钟内只计一次

    if (!empty($ip) && isset($stats[$matchedDomain]['ips'][$ip])) {
        if ($now - intval($stats[$matchedDomain]['ips'][$ip]) < $cooldown) {
            return;
        }
    }

    $stats[$matchedDomain]['count'] = intval($stats[$matchedDomain]['count']) + 1;
    $stats[$matchedDomain]['last_at'] = $now;
    if (!empty($ip)) {
        $stats[$matchedDomain]['ips'][$ip] = $now;
    }

    // 清理 7 天未更新的 IP 记录
    if (!empty($stats[$matchedDomain]['ips'])) {
        foreach ($stats[$matchedDomain]['ips'] as $k => $t) {
            if ($now - intval($t) > 7 * 86400) {
                unset($stats[$matchedDomain]['ips'][$k]);
            }
        }
    }

    baigeWriteLinkStats($stats);
}

/**
 * 按来访次数对友链排序（仅 norank=false 的参与排序）
 * @param array $links
 * @return array
 */
function baigeSortLinksByRank($links)
{
    if (empty($links)) return $links;

    $stats = baigeReadLinkStats();

    $ranked = [];
    $unranked = [];
    foreach ($links as $link) {
        if (!empty($link['norank'])) {
            $unranked[] = $link;
        } else {
            $domain = baigeNormalizeDomain($link['url'] ?? '');
            $link['_count'] = isset($stats[$domain]['count']) ? intval($stats[$domain]['count']) : 0;
            $link['_last_at'] = isset($stats[$domain]['last_at']) ? intval($stats[$domain]['last_at']) : 0;
            $ranked[] = $link;
        }
    }

    usort($ranked, function ($a, $b) {
        if ($a['_count'] !== $b['_count']) {
            return $b['_count'] <=> $a['_count'];
        }
        return $b['_last_at'] <=> $a['_last_at'];
    });

    foreach ($ranked as &$link) {
        unset($link['_count'], $link['_last_at']);
    }
    unset($link);

    return array_merge($ranked, $unranked);
}

/**
 * 获取客户端 IP
 * @return string
 */
function baigeGetClientIp()
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


// 友链申请功能：开启时自动确保数据表存在
$__baige_options__ = Helper::options();
if (isset($__baige_options__->friend_link_switch) && $__baige_options__->friend_link_switch === 'ok') {
    baigeEnsureLinksTable();
}
