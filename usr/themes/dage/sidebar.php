<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php 
/**
 * BDG 3.5 侧边栏组件
 */
if (!empty($this->options->sidebarBlock) && in_array('Showside', $this->options->sidebarBlock)): 
?>

<aside class="baige-sidebar" id="sidebar">
    <div id="innersidebar">
        <!-- 搜索框 -->
        <?php if (!empty($this->options->sidebarBlock) && in_array('Showsousuo', $this->options->sidebarBlock)): ?>
        <div class="baige-sidepanel" id="sousuo">
            <h4 class="baige-Ptitle">站内搜索</h4>
            <div class="baige-Pcontent">
                <form class="baige-form" method="post" action="<?php $this->options->siteUrl(); ?>">
                    <input class="baige-userpass" id="s" name="s" placeholder="输入关键词搜索...">
                    <input class="baige-userbutton" type="submit" value="查找">
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- 快捷导航 -->
        <?php if($this->options->yemian === 'sidebar'): ?>
        <div class="baige-sidepanel" id="kuaijie">
            <h4 class="baige-Ptitle">快捷导航</h4>
            <div class="baige-Pcontent">
                <?php \Widget\Contents\Page\Rows::alloc()->to($pages); ?>
                <?php while ($pages->next()): ?>
                    <a href="<?php $pages->permalink(); ?>" class="baige-sideA baige-txt-ellipsis" title="<?php $pages->title(); ?>"><?php $pages->title(); ?></a>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 数据统计 -->
        <?php if (!empty($this->options->sidebarBlock) && in_array('Showtongji', $this->options->sidebarBlock)): ?>
        <div class="baige-sidepanel" id="tongji">
            <h4 class="baige-Ptitle">数据统计</h4>
            <div class="baige-Pcontent">
                <?php Typecho_Widget::widget('Widget_Stat')->to($stat); ?>
                <div class="baige-tongji">文章: <b><?php $stat->publishedPostsNum() ?></b> 篇</div>
                <div class="baige-tongji">评论: <b><?php $stat->publishedCommentsNum() ?></b> 条</div>
                <div class="baige-tongji">在线: <b><?php echo function_exists('online_users') ? online_users() : 0 ?></b> 人</div>
                <div class="baige-tongji">浏览: <b><?php echo class_exists('xm') ? xm::theAllViews() : 0 ?></b> 次</div>
                <div class="baige-tongji">全站: <b><?php echo function_exists('allOfCharacters') ? allOfCharacters() : 0 ?></b> 字</div>
                <div class="baige-tongji">建站: <b><?php $this->options->jianzhan(); ?></b></div>
            </div>
        </div>
        <?php endif; ?>
    
<!-- 最新评论 -->
<?php if (!empty($this->options->sidebarBlock) && in_array('Showpinglun', $this->options->sidebarBlock)): ?>
<div class="baige-sidepanel" id="pinglun">
    <h4 class="baige-Ptitle">最新评论</h4>
    <div class="baige-Pcontent">
        <div class="commentTable baige-pinglun-ce">
            <?php $this->widget('Widget_Comments_Recent','ignoreAuthor=true')->to($comments); ?>
            <?php if ($comments->have()) { 
                 while ($comments->next()): ?>
                <a href="<?php $comments->permalink(); ?>" class="baige-sideA baige-text-yichu baige-txt-ellipsis">
                    <?php 
                    // 去掉HTML标签
                    $commentContent = strip_tags($comments->content);
                    // 截取前30个字符
                    $cutStr = mb_substr($commentContent, 0, 30, 'UTF-8');
                    // 判断
                    if (mb_strlen($cutStr, 'UTF-8') < 10) {
                        echo xm::biaoqing($cutStr);
                    } else {
                        echo xm::biaoqing($cutStr . '...');
                    }
                    ?>
                </a>
            <?php endwhile; } ?>
        </div>
    </div>
</div>
<?php endif; ?>

        <!-- 随机文章 -->
        <?php if (!empty($this->options->sidebarBlock) && in_array('Showsuiji', $this->options->sidebarBlock)): ?>
        <div class="baige-sidepanel" id="suiji">
            <h4 class="baige-Ptitle">随机文章</h4>
            <div class="baige-Pcontent">
                <div class="commentTable">
                    <?php 
                    $randCount = $this->options->suijiwen ? (int)$this->options->suijiwen : 5;
                    $this->widget('Widget_Post_rand@rand', 'pageSize=' . $randCount)->to($rand); 
                    ?>
                    <?php while($rand->next()):?>
                    <a href="<?php $rand->permalink() ?>" class="baige-sideA baige-txt-ellipsis" title="<?php $rand->title(); ?>"><?php $rand->title(); ?></a>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- 标签 -->
        <?php if (!empty($this->options->sidebarBlock) && in_array('Showtag', $this->options->sidebarBlock)): ?>
        <div class="baige-sidepanel" id="tag">
            <h4 class="baige-Ptitle"><?php echo $this->options->tagMode == 'rand' ? '随机标签' : '热门标签'; ?></h4>
            <div class="baige-Pcontent">
                <?php 
                $tagsArr = array();
                $tagMode = $this->options->tagMode ?? 'hot';
                $widgetParams = array(
                    'ignoreZeroCount' => true,
                    'limit' => 999,
                    'sort' => 'count',
                    'desc' => true
                );
                $this->widget('Widget_Metas_Tag_Cloud', $widgetParams)->to($allTags);
                while ($allTags->next()) $tagsArr[] = $allTags->row;
                if ($tagMode == 'rand') shuffle($tagsArr);
                $showTags = array_slice($tagsArr, 0, 10);
                foreach ($showTags as $tag) {
                    $tagObj = Typecho_Widget::widget('Widget_Metas_Tag_Cloud');
                    $tagObj->push($tag);
                ?>
                <a rel="tag" class="baige-sideA baige-txt-ellipsis" href="<?php $tagObj->permalink(); ?>"><?php $tagObj->name(); ?> (<?php $tagObj->count(); ?>)</a>
                <?php } ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 日期归档 -->
        <?php if (!empty($this->options->sidebarBlock) && in_array('Showguidang', $this->options->sidebarBlock)): ?>
        <div class="baige-sidepanel" id="guidang">
            <h4 class="baige-Ptitle">日期归档</h4>
            <div class="baige-Pcontent">
                <?php
                $archiveFormat = $this->options->archiveFormat ?? 'cn';
                $format = $archiveFormat == 'cn' ? 'Y年m月' : 'Y F';
                \Widget\Contents\Post\Date::alloc('type=month&format='.$format.'&limit=10')
                    ->parse('<a href="{permalink}" class="baige-sideA baige-txt-ellipsis">{date}</a>');
                ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 推荐网站 -->
        <?php if ($this->options->clink): ?>
        <?php
        // 自动获取友链页面链接，无需再手动修改
        $friendPage = null;
        if (class_exists('xm')) {
            try {
                $friendPage = xm::cid($this->options->clink);
            } catch (Exception $e) {
                $friendPage = null;
            }
        }
        ?>
        <div class="baige-sidepanel" id="tuijian">
            <div class="baige-Ptitle-container">
                <h4 class="baige-Ptitle">推荐网站<?php if ($friendPage): ?><a href="<?php $friendPage->permalink(); ?>" class="baige-more-link">更多</a><?php endif; ?></h4>
            </div>
            <div class="baige-Pcontent">
                <div class="baige-text-yichu shuo-content">
                    <?php
                    if ($friendPage) {
                        $linkContent = xm::xm_indexyl($friendPage->content);
                        $linkContent = preg_replace('/<a (.*?)>/i', '<a $1 target="_blank">', $linkContent);
                        echo $linkContent;
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- 功能入口 -->
        <?php if (!empty($this->options->sidebarBlock) && in_array('Showkuaijie', $this->options->sidebarBlock)): ?>
        <div class="baige-sidepanel" id="qita">
            <h4 class="baige-Ptitle">其他功能</h4>
            <div class="baige-Pcontent">
                <?php if ($this->user->hasLogin()): ?>
                <a href="<?php $this->options->adminUrl(); ?>" class="baige-sideA baige-txt-ellipsis">进入后台 (<?php $this->user->screenName(); ?>)</a>
                <a href="<?php $this->options->logoutUrl(); ?>" class="baige-sideA baige-txt-ellipsis">退出</a>
                <?php else: ?>
                <a href="/admin/login.php" class="baige-sideA baige-txt-ellipsis">登录</a>
                <a href="/admin/register.php" class="baige-sideA baige-txt-ellipsis">注册</a>
                <?php endif; ?>
                <a href="<?php $this->options->siteUrl('feed/'); ?>" class="baige-sideA baige-txt-ellipsis">订阅 RSS</a>
                <a href="<?php $this->options->siteUrl('sitemap.xml'); ?>" class="baige-sideA baige-txt-ellipsis">地图 XML</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</aside>

<?php endif; ?>