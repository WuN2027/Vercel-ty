<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>

<?php $this->need('sidebar.php'); ?>
<div class="baige-mainContent">
    <div class="baige-innermainContent">
        <!-- 文章导航区 -->
        <div class="baige-pageContent baige-display-flex">
            <span><?php $this->category(' | '); ?></span>
            <div class="baige-display-flex-right">
                <?php thePrev($this, '上一篇', '<img src="' . XM_TPL . 'bai/Cprevious1.gif">没有了'); ?> |
                <?php theNext($this, '下一篇', '<img src="' . XM_TPL . 'bai/Cnexta.gif">没有了'); ?>
            </div>
        </div>

        <!-- 文章主体内容 -->
        <article class="baige-Content">
            <!-- 文章标题与作者信息 -->
            <header class="baige-Content-top">
                <?php
                $title = $this->title;
                $category_name = '';
                if (!empty($this->categories)) {
                    $category_name = $this->categories[0]['name'];
                }
                $is_sticky = isSticky($this->cid);

                // 分类图标由后台主题设置 categoryIcons 统一管理
                $current_svg = getCategoryIcon($category_name);

                // 置顶
                $sticky_tag_html = '';
                if ($is_sticky) {
                    $sticky_tag_html = '<span class="baige-view-zhiding">[置顶]</span>';
                }

                // 生成SVG图标HTML
                $svg_icon_html = '<img src="' . $current_svg . '" alt="' . $category_name . '">';
                ?>
                <div class="baige-line-height">
                    <!-- 置顶 -->
                    <span class="baige-tubiao">
                        <?php echo $svg_icon_html; ?>
                    </span>
                    <h1 class="baige-ContentTitle">
                        <a class="titleA" href="<?php $this->permalink() ?>">
                            <?php echo $sticky_tag_html; ?><?php $this->title() ?>
                        </a>
                    </h1>
                    <h2 class="baige-ContentAuthor">
                        作者: <?php $this->author(); ?>
                        日期: <?php echo xm::time($this->created); ?>
                    </h2>

                    <?php if ($this->user->hasLogin()): ?>
                        <a href="<?php $this->options->adminUrl(); ?>write-post.php?cid=<?php echo $this->cid; ?>"
                            target="_blank"
                            style="color: #ffa500;">[ 编辑 ]</a>
                    <?php endif; ?>
                </div>
            </header>

            <!-- 过期提示 -->
            <?php
            $overdueDays = $this->options->JOverdue ? (int)$this->options->JOverdue : 0;
            $isOverdue = $overdueDays > 0 && floor((time() - $this->modified) / 86400) > $overdueDays;

            if ($isOverdue) :
            ?>
                <div class="baige-overdue-wrapper">
                    <div class="baige-overdue-title">
                        <img src="<?php echo Helper::options()->themeUrl(); ?>/bai/img/notice.svg" alt="提示">
                        温馨提示:
                    </div>
                    本文最后更新于<?php echo date('Y年m月d日', $this->modified); ?>，已超过<?php echo floor((time() - $this->modified) / 86400); ?>天没有更新，若内容或图片失效，请留言反馈。
                </div>
            <?php endif; ?>

            <!-- 文章内容区 -->
            <div class="baige-Content-body baige-text-yichu">
                <?php
                $content = $this->content;
                $content = (string) $content;

                // 解析音乐短代码
                if (function_exists('parseMusicShortcode')) {
                    $content = parseMusicShortcode($content);
                }

                // 处理图片
                $replaced = preg_replace_callback(
                    '/<p[^>]*>((?:(?!<p[^>]*>|<\/p>).)*?<img[^>]*\/?>(?:(?!<\/p>).)*?)<\/p>/is',
                    function ($m) {
                        return $m[1];
                    },
                    $content
                );
                if ($replaced !== null) {
                    $content = $replaced;
                }

                // 处理图片
                $replaced = preg_replace_callback(
                    '/<img(.*?)\/?>/is',
                    function ($matches) {
                        $img = $matches[0];
                        $alt = '';
                        if (preg_match('/alt="([^"]*)"/i', $img, $altMatch)) {
                            $alt = trim($altMatch[1]);
                        }

                        $wrap = '
        <div class="baige-view-img">
            <img ' . substr($img, 4) . '
        </div>';

                        if (!empty($alt)) {
                            $wrap .= '<figcaption class="baige-view-img-ts">' . $alt . '</figcaption>';
                        }
                        return $wrap;
                    },
                    $content
                );
                if ($replaced !== null) {
                    $content = $replaced;
                }

                // 隐藏内容
                if (strpos($content, '{hide') !== false) {
                    $db = Typecho_Db::get();
                    $sql = $db->select()
                        ->from('table.comments')
                        ->where('cid = ?', $this->cid)
                        ->where('mail = ?', $this->remember('mail', true))
                        ->limit(1);
                    $result = $db->fetchAll($sql);

                    $isVisible = $this->user->hasLogin() || !empty($result);

                    if ($isVisible) {
                        $content = preg_replace(
                            "/{hide}(.*?){\/hide}/sm",
                            '<div class="baige-hide-box">$1</div>',
                            $content
                        );
                    } else {
                        $content = preg_replace(
                            "/{hide}(.*?){\/hide}/sm",
                            '<div class="baige-hide-box">
                                本文部分内容需 <a href="#baige-comment-form">回复</a> 后可见
                            </div>',
                            $content
                        );
                    }
                }

                $content = xm::biaoqing(xm::ubb($content));
                // 在 UBB 和表情解析之后
                // 避免播放器被污染
                if (function_exists('replaceMusicPlaceholders')) {
                    $content = replaceMusicPlaceholders($content);
                }

                // 标签
                $content = preg_replace_callback(
                    '/<pre(?:\s+class="([^"]*)")?\s*>[\s]*<code(?:\s+class="([^"]*)")?[\s]*>/i',
                    function ($m) {
                        $preClass = isset($m[1]) ? $m[1] : '';
                        $codeClass = isset($m[2]) ? $m[2] : '';
                        $lang = '';
                        // 从 class 中提取语言名
                        foreach ([$preClass, $codeClass] as $cls) {
                            if (empty($cls)) continue;
                            if (preg_match('/language-(\w+)/i', $cls, $lm)) {
                                $lang = strtolower($lm[1]);
                                break;
                            }
                            if (preg_match('/\b(php|css|scss|sass|less|html|xml|markup|javascript|js|bash|sh|shell|zsh|nginx|conf|sql|mysql|sqlite|python|py|java|go|rust|json|yaml|c|cpp|typescript|ts)\b/i', $cls, $lm)) {
                                $lang = strtolower($lm[1]);
                                break;
                            }
                        }
                        $dataAttr = $lang ? ' data-lang="' . $lang . '"' : ' data-lang=""';
                        $preAttr = !empty($preClass) ? ' class="' . $preClass . '"' : '';
                        $codeAttr = !empty($codeClass) ? ' class="' . $codeClass . '"' : '';
                        return '<pre' . $preAttr . '><code' . $codeAttr . $dataAttr . '>';
                    },
                    $content
                );

                echo $content;
                ?>
            </div>

            <!-- 文章附加信息 -->
            <br>
            <div class="baige-Content-bodya">
                <?php if ($this->fields->weizhi): ?>
                    <div class="baige-weizhi baige-tongji">
                        <img src="<?php echo Helper::options()->themeUrl(); ?>/bai/img/fenlei/location.svg">
                        <span><?php $this->fields->weizhi(); ?></span>
                    </div>
                <?php endif; ?>

                <div class="baige-tongji">
                    <img src="<?php echo Helper::options()->themeUrl(); ?>/bai/img/statement.svg">
                    <strong>文章声明：</strong><?php $this->options->copyright(); ?>
                </div>

                <div class="baige-tongji">
                    <img src="<?php echo Helper::options()->themeUrl(); ?>/bai/img/tag.svg">
                    <strong>标签：</strong><?php $this->tags(' ', true, 'none'); ?>
                </div>
            </div>

            <!-- 文章统计 -->
            <div class="baige-Content-bottom">
                <div>评论: <?php $this->commentsNum('0', '1', '%d'); ?> | 查看: <?php echo xm::get_post_view($this); ?></div>
            </div>
        </article>

        <!-- 相关文章 -->
        <?php $this->related(5)->to($relatedPosts); ?>
        <?php if ($relatedPosts->have()): ?>
            <div class="baige-xiangguan-list">
                <div class="baige-xiangguan-list-title">
                    <img src="<?php echo Helper::options()->themeUrl(); ?>/bai/img/related-posts.svg" alt="相关文章" style="width:24px; height:24px; vertical-align:middle; margin-right:8px; object-fit:contain;">
                    相关文章
                </div>
                <div class="baige-xiangguan-box">
                    <ul class="baige-xiangguan-list-body">
                        <?php while ($relatedPosts->next()): ?>
                            <li class="baige-xiangguan-title">
                                <span class="baige-txt-ellipsis">
                                    <a href="<?php $relatedPosts->permalink() ?>"><?php $relatedPosts->title() ?></a>
                                </span>
                                <span class="baige-article-date">
                                    <?php $relatedPosts->date('Y-m-d'); ?>
                                </span>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <?php if($this->options->pinglun == '1'): $this->need('commentsa.php'); else: $this->need('comments.php'); endif; ?>
    </div>
</div>

<?php $this->need('footer.php'); ?>