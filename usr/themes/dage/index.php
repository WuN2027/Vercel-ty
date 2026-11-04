<?php
/**
 * 一款基于 Typecho 开发的个人博客主题，功能丰富、界面美观，适合用于个人日志、相册、视频、音乐等多种内容展示。
 * @author 柏哥
 * @package 怀旧主题
 * @version 3.5
 * @link https://www.baidage.com
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');

// 分页
$indexpage = $this->_currentPage ?? $this->currentPage;

// 列表模式
$lq = $this->request->get('lq', 'ok');

// 主题资源
$themeUrl = Helper::options()->themeUrl;

// 分类图标由后台主题设置 categoryIcons 统一管理，函数定义在 functions.php

// 懒加载
function getLazyImg($imgurl, $themeUrl, $lazyload) {
    return $lazyload ? 'data-src="'.$imgurl.'" src="'.$themeUrl.'/bai/jz.svg"' : 'src="'.$imgurl.'"';
}

// 懒加载
$lazyload = $this->options->lazyload;
?>

<?php $this->need('sidebar.php'); ?>

<div class="baige-mainContent">
    <div class="baige-innermainContent">

        <?php if ($this->have()): ?>
        <div class="baige-content-width">
            <div class="baige-pageContent">
                <span class="baige-float-left">近期更新 <?php echo xm::getNumPosts(7); ?> 篇文章</span>
                <span class="baige-margin-right">预览模式: </span>
                <a href="?lq=ok" title="图文列表">普通</a>
                <span class="baige-margin">|</span>
                <a href="<?php echo $this->archiveUrl; ?>?lq=no" title="简洁列表">列表</a>
            </div>          
        </div>

        <?php if($this->options->tongyong && $this->is('index') && $indexpage == 1):?>
        <div class="baige-tonggao">
            <span class="zuo">动态</span>
            <span class="you"><?php $this->options->tongyong();?></span>
        </div>
        <?php endif; ?>

        <?php $this->need('sticy.php');?>

        <?php if($lq == 'ok'): while ($this->next()): ?>

        <?php
        // 分类
        $category = $this->categories[0]['name'] ?? '';

        // 置顶
        $is_sticky = $this->sticky;

        // 自定义排版
        $listCss = $this->fields->listcss ?? '';

        // 图片显示
        $suiji = $this->fields->suiji ?? '';

        // 置顶
        $sticky_tag_html = $is_sticky ? '<span class="baige-zhiding">置顶</span>' : '';

        // 分类图标
        $current_svg = getCategoryIcon($category);
        $svg_icon_html = '<img src="'.$current_svg.'" alt="'.$category.'">';
        ?>

        <div class="baige-Content">
            <div class="baige-Content-top <?php echo $listCss =='bt' ? 'baige-margin-bottom2 baige-list-bt' : ''; ?>">
                <?php echo $sticky_tag_html; ?>
                <span class="baige-tubiao">
                    <?php echo $svg_icon_html; ?>
                </span>
                <span class="baige-line-height baige-text-yichu">
                    <h1 class="baige-ContentTitle">
                        <a class="titleA" href="<?php $this->permalink() ?>">
                            <?php $this->title() ?>
                        </a>
                    </h1>
                    <h2 class="baige-ContentAuthor">
                        作者: <?php $this->author(); ?> 
                        日期: <?php echo xm::time($this->created); ?>
                    </h2>
                </span>
            </div>
            
            <?php if($listCss != 'bt'): ?>
            <div>
                <div class="baige-Content-body">
                    <?php if ($suiji != 'no'): ?>

                    <?php if($listCss == 'si'): ?>
                    <div class="baige-img-2">
                        <?php for ($i=0; $i <= 3; $i++):
                            $imgurl = xm::showThumbnail($this, $i);
                            $img = getLazyImg($imgurl, $themeUrl, $lazyload);
                            $bigImg = $imgurl;
                        ?>
                        <div class="baige-list-tu">
                            <?php if ($this->options->fancybox === 'oka' || $this->options->fancybox === 'okc'): ?>
                            <a data-fancybox="gallery" href="<?php echo htmlspecialchars($bigImg); ?>">
                                <img class="lazyload lazimg" <?php echo $img; ?> alt="<?php $this->title(); ?>">
                            </a>
                            <?php else: ?>
                                <img class="lazyload lazimg" <?php echo $img; ?> alt="<?php $this->title(); ?>">
                            <?php endif; ?>
                        </div>
                        <?php endfor; ?>
                    </div>

                    <?php elseif($listCss == 'tp'): ?>
                    <div class="baige-img-3">
                        <?php for ($i=0; $i <= 2; $i++):
                            $imgurl = xm::showThumbnail($this, $i);
                            $img = getLazyImg($imgurl, $themeUrl, $lazyload);
                            $bigImg = $imgurl;
                        ?>
                        <div class="baige-list-tu">
                            <?php if ($this->options->fancybox === 'oka' || $this->options->fancybox === 'okc'): ?>
                            <a data-fancybox="gallery" href="<?php echo htmlspecialchars($bigImg); ?>">
                                <img class="lazyload lazimg" <?php echo $img; ?> alt="<?php $this->title(); ?>">
                            </a>
                            <?php else: ?>
                                <img class="lazyload lazimg" <?php echo $img; ?> alt="<?php $this->title(); ?>">
                            <?php endif; ?>
                        </div>
                        <?php endfor; ?>
                    </div>

                    <?php elseif($listCss == 'jiu'): ?>
                    <div class="baige-img-3">
                        <?php for ($i=0; $i <= 8; $i++):
                            $imgurl = xm::showThumbnail($this, $i);
                            $img = getLazyImg($imgurl, $themeUrl, $lazyload);
                            $bigImg = $imgurl;
                        ?>
                        <div class="baige-list-tu">
                            <?php if ($this->options->fancybox === 'oka' || $this->options->fancybox === 'okc'): ?>
                            <a data-fancybox="gallery" href="<?php echo htmlspecialchars($bigImg); ?>">
                                <img class="lazyload lazimg" <?php echo $img; ?> alt="<?php $this->title(); ?>">
                            </a>
                            <?php else: ?>
                                <img class="lazyload lazimg" <?php echo $img; ?> alt="<?php $this->title(); ?>">
                            <?php endif; ?>
                        </div>
                        <?php endfor; ?>
                    </div>

                    <?php elseif($listCss == 'dx'): ?>
                    <div class="baige-post-images baige-post-img">
                        <?php for ($i=0; $i <= 2; $i++):
                            $imgurl = xm::showThumbnail($this, $i);
                            $img = getLazyImg($imgurl, $themeUrl, $lazyload);
                            $bigImg = $imgurl;
                        ?>
                        <span>
                            <?php if ($this->options->fancybox === 'oka' || $this->options->fancybox === 'okc'): ?>
                            <a data-fancybox="gallery" href="<?php echo htmlspecialchars($bigImg); ?>">
                                <img class="lazyload lazimg" <?php echo $img; ?> alt="<?php $this->title(); ?>">
                            </a>
                            <?php else: ?>
                                <img class="lazyload lazimg" <?php echo $img; ?> alt="<?php $this->title(); ?>">
                            <?php endif; ?>
                        </span>
                        <?php endfor; ?>
                    </div>

                    <?php elseif($listCss == 'dx6'): ?>
                    <div class="baige-post-images baige-post-img">
                        <?php for ($i=0; $i <= 5; $i++):
                            $imgurl = xm::showThumbnail($this, $i);
                            $img = getLazyImg($imgurl, $themeUrl, $lazyload);
                            $bigImg = $imgurl;
                        ?>
                        <span>
                            <?php if ($this->options->fancybox === 'oka' || $this->options->fancybox === 'okc'): ?>
                            <a data-fancybox="gallery" href="<?php echo htmlspecialchars($bigImg); ?>">
                                <img class="lazyload lazimg" <?php echo $img; ?> alt="<?php $this->title(); ?>">
                            </a>
                            <?php else: ?>
                                <img class="lazyload lazimg" <?php echo $img; ?> alt="<?php $this->title(); ?>">
                            <?php endif; ?>
                        </span>
                        <?php endfor; ?>
                    </div>

                    <?php elseif($listCss == 'zz'): 
                        if ($this->fields->imgurl && $suiji == 'nosj') {
                            $imgurl = $this->fields->imgurl;
                        } elseif ($suiji == 'yessj') {
                            $imgurl = XM_TPL.'bai/png/suiji'.rand(1,30).'.png';
                        } else {
                            $imgurl = xm::showThumbnail($this, 0, true);
                        }
                        $bigImgUrl = $imgurl;

                        if ($imgurl):
                            $img = getLazyImg($imgurl, $themeUrl, $lazyload);
                    ?>
                        <div class="baige-post-thumb">
                        <?php if ($this->options->fancybox === 'oka' || $this->options->fancybox === 'okc'): ?>
                        <a data-fancybox="gallery" href="<?php echo htmlspecialchars($bigImgUrl); ?>">
                            <img class="lazyload lazimg" <?php echo $img; ?> alt="<?php $this->title(); ?>">
                        </a>
                        <?php else: ?>
                            <img class="lazyload lazimg" <?php echo $img; ?> alt="<?php $this->title(); ?>">
                        <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php endif; ?>
                    <?php endif; ?>

                    <?php if($this->fields->miaoss == 'ok'): 
                        echo xm::biaoqing(getExcerptWithParagraphs($this, 250, '...'));
                    endif; ?>
                    
                    <?php 
                    // 最近评论
                    if ($this->options->homeComments): 
                    $homeComments = getRecentComments($this->cid, 5);
                    if (!empty($homeComments)): 
                    ?>
                    <div class="baige-home-comments-section">
                        <div class="baige-home-comments-list">
                            <?php foreach ($homeComments as $hc): 
                                $avatarUrl = xm::getGravatar($hc['mail'], 100, null, $hc['author']);
                            ?>
                            <div class="baige-home-comment-item">
                                <img class="baige-home-comment-avatar" src="<?php echo $avatarUrl; ?>" alt="<?php echo htmlspecialchars($hc['author']); ?>" loading="lazy">
                                <span class="baige-home-comment-author"><?php echo htmlspecialchars($hc['author']); ?>:</span>
                                <span class="baige-home-comment-content">
                                    <a class="baige-home-comment-link" href="<?php echo htmlspecialchars($hc['permalink']); ?>" title="查看该评论"><?php echo $hc['reply']; ?><?php echo $hc['text']; ?></a>
                                    <?php if ($hc['images']): ?>
                                    <span class="baige-home-comment-photos"><?php echo $hc['images']; ?></span>
                                    <?php endif; ?>
                                </span>
                                <span class="baige-home-comment-time"><?php echo xm::time($hc['created']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                    
                    <div class="baige-tags">Tags: <?php $this->tags(' ', true, 'none'); ?></div>
                </div>
                
                <div class="baige-Content-bottom">
                    分类: <?php $this->category(' | '); ?> | 
                    <a href="<?php $this->permalink() ?>#comments">评论: <?php $this->commentsNum('0', '1', '%d'); ?></a> | 
                    查看: <?php echo xm::get_post_view($this); ?> 
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php endwhile; ?>

        <?php $this->pageNav('&laquo; 前一页', '后一页 &raquo;'); ?>

        <?php else: ?>
        <div id="content">
            <?php while ($this->next()): ?>
            <?php
            $sticky_tag_html = $this->sticky ? '<span class="baige-zhiding">置顶</span>' : '';
            ?>
            <div class="baige-jjcontent">
                <a href="<?php $this->permalink() ?>">
                    <span class="baige-jjtime"><?php echo date('y-m-d',$this->created); ?></span>
                    <p>
                        <?php echo $sticky_tag_html; ?>
                        <?php $this->title() ?>
                    </p>
                    <span class="baige-pinglun"><?php $this->commentsNum('0', '1', '%d'); ?> 条</span>
                </a>
            </div>
            <?php endwhile; ?>
        </div>
        <div class="baige-list-no"><?php $this->pageLink('点击查看更多','next'); ?></div>
        <?php endif; ?>

        <?php else: ?>
        <article class="post">
            <h2 class="post-title"><?php _e('没有找到内容'); ?></h2>
        </article>
        <?php endif; ?>

    </div>
</div>

<?php $this->need('footer.php'); ?>