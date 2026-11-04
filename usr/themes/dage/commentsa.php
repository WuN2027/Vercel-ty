<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 登录状态
$GLOBALS['isLogin'] = $this->user->hasLogin();
$GLOBALS['rememberEmail'] = $this->remember('mail', true);

// 评论区图片灯箱
$GLOBALS['enableCommentFancybox'] = isset(Helper::options()->fancybox) && (Helper::options()->fancybox === 'oka' || Helper::options()->fancybox === 'okb');

// 解析评论内容中的Markdown图片
function parseImageLinks($content)
{
    if (empty($content)) return '';
    $imageMarkers = [];
    $markerIndex = 0;

    // 处理 Typecho 已解析成 HTML <img> 的评论图片（排除表情图）
    $content = preg_replace_callback(
        '/<img(?![^>]*\bclass\s*=\s*["\']biaoqing["\'])[^>]*?\bsrc\s*=\s*["\']([^"\']+)["\'][^>]*?>/i',
        function ($matches) use (&$imageMarkers, &$markerIndex) {
            $src = $matches[1];
            $alt = '';
            if (preg_match('/\balt\s*=\s*["\']([^"\']*)["\']/i', $matches[0], $m)) {
                $alt = $m[1];
            }
            $marker = "<!--IMAGE_MARKER_{$markerIndex}-->";
            $imageMarkers[$markerIndex] = ['alt' => htmlspecialchars($alt), 'src' => htmlspecialchars($src)];
            $markerIndex++;
            return $marker;
        },
        $content
    );

    // 处理 Markdown 图片语法
    $content = preg_replace_callback('/!\[(.*?)\]\((.*?)\)/i', function ($matches) use (&$imageMarkers, &$markerIndex) {
        $marker = "<!--IMAGE_MARKER_{$markerIndex}-->";
        $imageMarkers[$markerIndex] = ['alt' => htmlspecialchars($matches[1]), 'src' => htmlspecialchars($matches[2])];
        $markerIndex++;
        return $marker;
    }, $content);

    $parts = explode('<!--IMAGE_MARKER', $content);
    $finalContent = '';

    foreach ($parts as $index => $part) {
        if ($index === 0) {
            $text = trim($part);
            if ($text) $finalContent .= "<p class='baige-comment-text'>{$text}</p>";
        } else {
            preg_match('/(\d+)-->(.*)/s', $part, $matches);
            if (isset($matches[1], $imageMarkers[$matches[1]])) {
                $imgIndex = $matches[1];
                $remainingText = trim($matches[2]);
                $alt = $imageMarkers[$imgIndex]['alt'];
                $src = $imageMarkers[$imgIndex]['src'];

                if ($GLOBALS['enableCommentFancybox']) {
                    $finalContent .= "<a href='{$src}' data-fancybox data-caption='{$alt}' class='baige-comment-image-container square mb-4'>";
                    $finalContent .= "<div class='baige-comment-image-wrapper'><img class='lazyload lazimg baige-comment-img thumbnail' data-original='{$src}' src=\"" . Helper::options()->themeUrl . "/bai/jz.svg\" alt='{$alt}' width='1' height='1'></div></a>";
                } else {
                    $finalContent .= "<div class='baige-comment-image-container square mb-4'><div class='baige-comment-image-wrapper'><img class='lazyload lazimg baige-comment-img thumbnail' data-original='{$src}' src=\"" . Helper::options()->themeUrl . "/bai/jz.svg\" alt='{$alt}' width='1' height='1'></div></div>";
                }
                if ($remainingText) $finalContent .= "<p class='baige-comment-text'>{$remainingText}</p>";
            }
        }
    }
    return $finalContent;
}

// 嵌套评论渲染核心方法
function threadedComments($comments, $options)
{
    $commentClass = $comments->authorId ? ($comments->authorId == $options->ownerId ? ' comment-by-author' : ' comment-by-user') : '';
    $commentLevelClass = $comments->levels > 0 ? 'baige-comment-parents' : 'baige-comment-childrena';
    $commentUrl = getCommentAuthorUrl($comments);
    $authorName = htmlspecialchars($comments->author);
    $author = $commentUrl ? '<a href="' . htmlspecialchars($commentUrl) . '" target="_blank" rel="external nofollow">' . $authorName . '</a>' : $authorName;
    $isParentComment = $comments->levels == 0;
    $hasChildren = $comments->children ? true : false;
    $showToggleBtn = $isParentComment && $hasChildren;

?>
    <li id="li-<?php $comments->theId(); ?>" class="baige-comment-body<?php if ($comments->levels > 0) { echo' comment-child ss-children';
                                                                    $comments->levelsAlt(' comment-level-odd', ' comment-level-even');
                                                                } else {
                                                                    echo 'baige-comment-parent';
                                                                }
                                                                $comments->alt(' comment-odd', ' comment-even');
                                                                echo $commentClass;
                                                                ?>">

        <div id="<?php $comments->theId(); ?>" class="baige-comment baige-comment-2">
            <div class="baige-comment-list-box">
                <!-- 评论者头像 -->
                <div class="baige-comment-list-avatar">
                    <img class="baige-tx" src="<?php echo xm::getGravatar($comments->mail, 100, $comments); ?>" alt="<?php $comments->author(false); ?>的头像" title="<?php $comments->author(false); ?>的头像">
                    
                </div>
                
                <!-- 主体内容 -->
                <div class="baige-comment-main">
                    <div class="baige-comment-author baige-txt-ellipsis">
                        <?php echo $author; ?><?php dengji($comments->mail,$comments);?>
                    </div>
                    
                    <div class="baige-comment-excerpt baige-text-yichu">
                        <?php
                $content = xm::biaoqing(xm::simi($comments->content));
                echo xm::reply($comments->parent) . parseImageLinks($content);
                ?>
                    </div>
                    
                    <div class="baige-comment-meta">
                        <span class="comment_time"><?php echo xm::time($comments->created); ?></span>
                        <?php $loc2 = getCommentLocation($comments->ip); if (!empty($loc2)) echo ' · <span class="comment-location">' . $loc2 . '</span>'; ?>
                        <i class="text-primary"></i>

                        
                        · <span class="baige-comment-reply"><?php $comments->reply('回复'); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 嵌套子评论 -->
        <?php if ($comments->children) : ?>
            <div class="<?php echo $commentLevelClass; ?>">
                <?php $comments->threadedComments($options); ?>
            </div>
        <?php endif; ?>
    </li>
<?php } ?>

<div id="comments">
    <?php $this->comments()->to($comments); ?>
<?php if ($comments->have()) : ?>
    <div class="baige-comment-container">
        <?php $comments->listComments(); ?>
    </div>
    <?php $comments->pageNav('&laquo; 前一页', '后一页 &raquo;'); ?>
    <?php endif; ?>
    <?php if ($this->allow('comment')) : ?>
        <div id="<?php $this->respondId(); ?>" class="nbj">
            <div class="baige-ping-title">发表评论</div>

            <form method="post" action="<?php $this->commentUrl() ?>" id="baige-comment-form">
                <input name="_" type="hidden" value="<?php echo Helper::security()->getToken(str_replace(array('?_pjax=%23wrap', '&_pjax=%23wrap'), '', Typecho_Request::getInstance()->getRequestUrl())); ?>" />
                <input type="hidden" id="is-logged-in" value="<?php echo $this->user->hasLogin() ? '1' : '0'; ?>">
                <input type="hidden" name="parent" id="baige-comment-parent" value="0">

                <?php $commentLoginFormHide = !empty(Helper::options()->comment_not_login_hide_form) && Helper::options()->comment_not_login_hide_form == '1'; ?>
                <input type="hidden" id="baige-comment-tips"
                    data-no-chinese="<?php echo htmlspecialchars(Helper::options()->no_chinese_tip ?: '评论必须包含中文'); ?>"
                    data-empty="<?php echo htmlspecialchars(Helper::options()->empty_comment_tip ?: '请输入有效评论内容'); ?>"
                    data-bad-word="<?php echo htmlspecialchars(Helper::options()->bad_word_tip ?: '评论包含不当内容'); ?>"
                    data-frequent="<?php echo htmlspecialchars(Helper::options()->frequency_tip ?: '评论过快，请稍后再试'); ?>"
                    data-blocked-ip="<?php echo htmlspecialchars(Helper::options()->block_ip_tip ?: '您的IP已被禁止评论'); ?>"
                    data-blocked-mail="<?php echo htmlspecialchars(Helper::options()->block_mail_tip ?: '该邮箱已被禁止发表评论'); ?>"
                    data-blocked-author="<?php echo htmlspecialchars(Helper::options()->block_author_tip ?: '该用户名称已被禁止发表评论'); ?>"
                    data-blocked-url="<?php echo htmlspecialchars(Helper::options()->block_url_tip ?: '该网址域名已被禁止发表评论'); ?>"
                    data-success-msg="评论发送成功，页面即将刷新">

                <?php if (!$this->user->hasLogin() && empty($commentLoginFormHide)) : ?>
                <div class="baige-comment-input baige-display-grid baige-comment-input-bottom">
                    <div class="baige-comment-input-title">
                        <label for="baige-comment-name" class="baige-comment-name">昵称</label>
                        <input type="text" class="baige-userpassa" name="author" id="baige-comment-name" value="<?php $this->remember('author'); ?>" placeholder="*" required />
                    </div>
                    <div class="baige-comment-input-title">
                        <label for="baige-comment-mail" class="baige-comment-name">邮箱</label>
                        <input type="email" class="baige-userpassa" name="mail" id="baige-comment-mail" value="<?php $this->remember('mail'); ?>" placeholder="*" <?php if ($this->options->commentsRequireMail) : ?>required<?php endif; ?>>
                    </div>
                    <div class="baige-comment-input-title">
                        <label for="baige-comment-url" class="baige-comment-name">网址</label>
                        <input type="url" class="baige-userpassa" name="url" id="baige-comment-url" value="<?php $this->remember('url'); ?>" placeholder="https://" <?php if ($this->options->commentsRequireURL) : ?>required<?php endif; ?>>
                    </div>
                </div>
                <?php endif; ?>

                <div class="xxx baige-input-comment">
                    <?php if (!$this->user->hasLogin() && !empty($commentLoginFormHide)) : ?>
                        <?php echo renderCommentLoginFormTip(); ?>
                    <?php else : ?>
                    <div class="baige-comment-editor baige-position-relative baige-margin-bottom baige-align-items">
                        <textarea name="text" id="textarea" placeholder="请文明用语！" class="textarea textarea_box OwO-textarea" required><?php $this->remember('text'); ?></textarea>
                        <div class="baige-display-flex baige-comment-submit">
                            <div class="OwO baige-comments-owo"></div>
                            <div class="submit-btn-container">
                                <button type="submit" class="baige-denglua" id="baige-comment-submit">
                                    <span class="btn-text">发表</span>
                                    <span class="submitting-text">发表中...</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div id="baige-comment-message-box" class="baige-comment-message" style="display:none;margin-bottom:12px;"></div>

                <div class="baige-display-flex baige-margin-top baige-margin-bottom baige-comment-bottom">
                    <?php if ($this->user->hasLogin() || empty($commentLoginFormHide)) : ?>
                    <div class="baige-privacy">
                        <div class="baige-privacy_btn baige-class-m-r-5">
                            <input type="checkbox" id="inset_3" name="secret" />
                            <label for="inset_3" class="green" onclick="tsok()"></label>
                            <span class="btn-text">隐私评论</span>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="baige-margin-quxiao"><?php $comments->cancelReply(); ?></div>
                </div>
            </form>
        </div>
    <?php else : ?>
        <div class="comments_off"><?php _e('评论已关闭'); ?></div>
    <?php endif; ?>
</div>

<script>
    // 懒加载
    function initLazyLoad() {
        const lazyImages = document.querySelectorAll('.baige-comment-img.thumbnail[data-original]');
        lazyImages.forEach(img => {
            if (img.classList.contains('loaded')) return;
            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            loadLazyImage(entry.target);
                            observer.unobserve(entry.target);
                        }
                    });
                }, {
                    rootMargin: '100px'
                });
                observer.observe(img);
            } else {
                loadLazyImage(img);
            }
        });
    }

    // 懒加载
    function loadLazyImage(img) {
        const originalSrc = img.getAttribute('data-original');
        if (!originalSrc || img.src === originalSrc) return;
        const tempImg = new Image();
        tempImg.src = originalSrc;
        tempImg.onload = tempImg.onerror = function() {
            img.src = originalSrc;
            img.classList.add('loaded');
            const wrapper = img.closest('.baige-comment-image-wrapper');
            if (wrapper) wrapper.classList.add('loaded');
            const container = img.closest('.baige-comment-image-container');
            if (container) container.classList.add('loaded');
            setImageContainerSize(img);
            img.removeAttribute('data-original');
        };
    }

    // 设置图片容器尺寸
    function setImageContainerSize(img) {
        const container = img.closest('.baige-comment-image-container');
        if (!container) return;
        if (!img.complete || !img.naturalWidth || !img.naturalHeight) {
            const retry = () => {
                if (img.complete && img.naturalWidth) calculateAndSetSize(img, container);
                else setTimeout(retry, 200);
            };
            setTimeout(retry, 100);
            return;
        }
        calculateAndSetSize(img, container);
    }

    // 计算图片比例设置容器类名
    function calculateAndSetSize(img, container) {
        const width = img.naturalWidth || img.width;
        const height = img.naturalHeight || img.height;
        const ratio = width / height;
        container.classList.remove('square', 'landscape', 'portrait');
        if (Math.abs(ratio - 1) < 0.1) container.classList.add('square');
        else if (ratio >= 1.2) container.classList.add('landscape');
        else container.classList.add('portrait');
        container.style.display = 'none';
        container.offsetHeight;
        container.style.display = 'inline-block';
    }

    // 初始化子评论折叠
    function initCommentChildrenToggle() {
        document.querySelectorAll('.baige-toggle-children-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const commentId = this.getAttribute('data-comment-id');
                const childrenContainer = document.getElementById(`comment-children-${commentId}`);
                if (childrenContainer.classList.contains('hidden')) {
                    childrenContainer.classList.remove('hidden');
                    this.textContent = '-';
                } else {
                    childrenContainer.classList.add('hidden');
                    this.textContent = '+';
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('baige-comment-form');
        const submitBtn = document.getElementById('baige-comment-submit');
        const btnText = submitBtn?.querySelector('.btn-text');
        const submittingText = submitBtn?.querySelector('.submitting-text');
        const textarea = document.getElementById('textarea');
        const parentInput = document.getElementById('baige-comment-parent');
        const msgBox = document.getElementById('baige-comment-message-box');
        const commentsList = document.querySelector('.baige-comments');
        // 全局提交锁定开关 彻底防重复提交
        let isSubmitting = false;

        // 提示语
        const tipsEl = document.getElementById('baige-comment-tips');
        const TIPS = {
            needChinese: tipsEl.dataset.noChinese,
            empty: tipsEl.dataset.empty,
            badWord: tipsEl.dataset.badWord,
            frequent: tipsEl.dataset.frequent,
            blockedIp: tipsEl.dataset.blockedIp,
            blockedMail: tipsEl.dataset.blockedMail,
            blockedAuthor: tipsEl.dataset.blockedAuthor,
            blockedUrl: tipsEl.dataset.blockedUrl,
            success: tipsEl.dataset.successMsg
        };

        // 回复
        document.querySelectorAll('.baige-comment-reply a').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                textarea.focus();
            });
        });

        // 取消回复
    const cancelReply = document.querySelector('.baige-margin-quxiao a');
    if (cancelReply) {
        cancelReply.addEventListener('click', function(e) {
            e.preventDefault();
            parentInput.value = 0;
        });
    }

    // 提交评论
        form.addEventListener('submit', function(e) {
            // 正在提交直接拦截 禁止任何操作
            if(isSubmitting){
                e.preventDefault();
                return;
            }
            e.preventDefault();
            // 开启全局锁定
            isSubmitting = true;
            btnText.style.display = 'none';
            submittingText.style.display = 'inline';
            submitBtn.disabled = true;
            submitBtn.style.cursor = 'not-allowed';
            hideMsg();

            const formData = new FormData(form);
            fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(res => res.text())
                .then(data => {
                    data = data.trim();

                    // 拦截错误判断
                    if (data === 'HAS_BAD_WORD') {
                        showMsg(TIPS.badWord, 'error');
                        throw new Error('stop');
                    }
                    if (data === 'NEED_CHINESE') {
                        showMsg(TIPS.needChinese, 'error');
                        throw new Error('stop');
                    }
                    if (data === 'EMPTY_COMMENT') {
                        showMsg(TIPS.empty, 'error');
                        throw new Error('stop');
                    }
                    if (data === 'FREQUENT_LIMIT') {
                        showMsg(TIPS.frequent, 'error');
                        throw new Error('stop');
                    }
                    if (data === 'BLOCKED_IP') {
                        showMsg(TIPS.blockedIp, 'error');
                        throw new Error('stop');
                    }
                    // 新增邮箱黑名单拦截判断
                    if (data === 'BLOCKED_MAIL') {
                        showMsg(TIPS.blockedMail, 'error');
                        throw new Error('stop');
                    }
                    // 新增用户名黑名单拦截判断
                    if (data === 'BLOCKED_AUTHOR') {
                        showMsg(TIPS.blockedAuthor, 'error');
                        throw new Error('stop');
                    }
                    // 新增网址/域名黑名单拦截判断
                    if (data === 'BLOCKED_URL') {
                        showMsg(TIPS.blockedUrl, 'error');
                        throw new Error('stop');
                    }

                    // 评论被设为待审核状态
                    if (data.indexOf('COMMENT_NEED_AUDIT|') === 0) {
                        var auditMsg = data.substring('COMMENT_NEED_AUDIT|'.length) || '评论已提交，正在审核中，审核通过后显示';
                        showMsg(auditMsg, 'success');
                        textarea.value = '';
                        parentInput.value = 0;
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                        return;
                    }

                    showMsg(TIPS.success, 'success');
                    textarea.value = '';
                    parentInput.value = 0;
                    // 成功直接刷新页面
                    setTimeout(() => {
                        window.location.reload();
                    }, 1200);

                })
                .catch(err => {
                    if (err.message !== 'stop') {
                        showMsg('发布失败，请重试', 'error');
                    }
                    // 失败才解除锁定
                    isSubmitting = false;
                })
                .finally(() => {
                    // 只有失败才恢复按钮
                    if(!isSubmitting){
                        btnText.style.display = 'inline';
                        submittingText.style.display = 'none';
                        submitBtn.disabled = false;
                        submitBtn.style.cursor = 'pointer';
                    }
                });
        });

        // 提示框
        let commentMsgTimer;

        function showMsg(text, type = 'error') {
            if (commentMsgTimer) clearTimeout(commentMsgTimer);
            msgBox.className = 'baige-comment-message ' + (type === 'success' ? 'baige-comment-success' : 'baige-comment-error');
            msgBox.textContent = text;
            msgBox.style.display = 'block';
            if (type !== 'success') {
                commentMsgTimer = setTimeout(() => {
                    msgBox.style.display = 'none';
                }, 3000);
            }
        }

        function hideMsg() {
            if (commentMsgTimer) clearTimeout(commentMsgTimer);
            msgBox.style.display = 'none';
        }

        initLazyLoad();
        initCommentChildrenToggle();
        setTimeout(initLazyLoad, 500);
    });

    window.isUserLoggedIn = <?php echo $this->user->hasLogin() ? 'true' : 'false'; ?>;
    window.addEventListener('scroll', initLazyLoad);
    window.addEventListener('resize', initLazyLoad);
</script>