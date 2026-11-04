<?php 
if (!defined('__TYPECHO_ROOT_DIR__')) exit; 
?>
<!DOCTYPE HTML>
<html>
<head>
    <meta charset="<?php $this->options->charset(); ?>">
    <meta name="renderer" content="webkit">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    
<title><?php $this->archiveTitle([
        'category' => _t('分类 %s 下的文章'),
        'search'   => _t('包含关键字 %s 的文章'),
        'tag'      => _t('标签 %s 下的文章'),
        'author'   => _t('%s 发布的文章')
    ], '', ' - '); ?><?php $this->options->title(); ?>

<?php if($this->is('index')): ?>
    <?php if(!empty($this->options->description)): ?>
        - <?php $this->options->description(); ?>
    <?php elseif(!empty($this->options->subTitle)): ?>
        - <?php $this->options->subTitle(); ?>
    <?php endif; ?>
<?php endif; ?>
</title>
    
    <link rel="shortcut icon" href="<?php $this->options->ico(); ?>" />
    <link rel="stylesheet" href="<?php echo XM_TPL.'bai/css/style.css?v='.filemtime(__DIR__.'/bai/css/style.css'); ?>">
    <?php $userwidth = Helper::options()->userwidth; if (!empty($userwidth) && $userwidth !== 'w920'): ?>
    <link rel="stylesheet" href="<?php echo XM_TPL.'bai/css/'.$userwidth.'.css?v='.filemtime(__DIR__.'/bai/css/'.$userwidth.'.css'); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="/usr/themes/dage/bai/css/color-theme.css">
    
    <?php if (
        $this->options->fancybox === 'oka' || 
        ($this->options->fancybox === 'okb' && $this->is('single')) || 
        ($this->options->fancybox === 'okc' && ($this->is('archive') || $this->is('index')))
    ): ?>
    <link rel="stylesheet" href="<?php echo XM_TPL.'bai/css/fancybox.min.css'; ?>">
    <?php endif; ?>

    <?php if ($this->is('single')): ?>
        <link rel="stylesheet" href="<?php echo XM_TPL; ?>bai/prism/prism.css" type="text/css" media="all">
        <link rel="stylesheet" href="<?php echo XM_TPL; ?>bai/owo/owo.css?2">
    <?php endif; ?>

    <?php $this->header('rss1=&rss2=&atom=&generator=&template=&pingback=&xmlrpc=&wlw='); ?>

<style>
        <?php $this->options->header(); ?>
        
        <?php if ($this->options->fonthm != 'no'): ?>
            <?php 
            $fontMap = [
                'PY' => ['name' => 'PY Font', 'file' => 'PY']
            ];
            $font = isset($fontMap[$this->options->fonthm]) ? $fontMap[$this->options->fonthm] : $fontMap['PY'];
            ?>
            @font-face {
                font-family: '<?php echo $font['name']; ?>';
                src: url('<?php echo XM_TPL; ?>bai/<?php echo $font['file']; ?>.woff2') format("woff2");
            }
            body, h1, h2, h3, p, div {
                font-family: '<?php echo $font['name']; ?>', sans-serif;
            }
        <?php endif; ?>

        <?php $sidebarPos = $this->options->sidebarPosition ?: 'left'; ?>

        <?php if ($sidebarPos === 'off' || empty($this->options->sidebarBlock) || !in_array('Showside', (array)$this->options->sidebarBlock)): ?>
        #container .baige-sidebar {
            display: none !important;
        }
        #container .baige-mainContent {
            width: 100%;
            float: none;
        }
        <?php elseif ($sidebarPos === 'right'): ?>
        @media screen and (min-width:821px) {
          #container .baige-sidebar {
            float: right;
          }
          #container .baige-mainContent {
            float: left;
          }
        }
        <?php endif; ?>
</style>

<script>
window.isUserLoggedIn = <?php echo $this->user->hasLogin() ? 'true' : 'false'; ?>;
window.themeUrl = '<?php echo XM_TPL; ?>';
</script>

</head>

<body <?php if ($this->options->bjUrl): ?>style="background-image: url(<?php $this->options->bjUrl(); ?>);"<?php endif; ?>>
<div id="container">
    <?php if ($sidebarPos !== 'off'): ?>
    <div id="mobileMenuToggle">
        <img src="<?php echo XM_TPL; ?>bai/img/menu.svg" alt="菜单" style="width:24px;height:24px;filter:invert(1);">
    </div>
    <?php endif; ?>

    <div class="baige-header">
        <div class="baige-blogname">
            <?php $this->options->title(); ?>
            <div class="baige-blogTitle">
                <?php if (!empty($this->options->description)): ?>
                    <?php $this->options->description(); ?>
                <?php elseif (!empty($this->options->subTitle)): ?>
                    <?php $this->options->subTitle(); ?>
                <?php endif; ?>
            </div>
        </div>
        
<div class="baige-menu">
    <ul>
        <li><a class="menuA" href="/" title="首页">首页</a></li>
        
        <?php 
        // 分类
        $categoryMode = $this->options->categoryMenuMode ?: 'expand';
        
        if ($categoryMode === 'dropdown'): 
        ?>
        <!-- 分类 -->
        <li class="baige-nav-page-item" id="categoryItem">
            <a href="javascript:;" class="menuA" id="categoryBtn">
                <span class="baige-margin-right">|</span>分类 ▾
            </a>
            <div class="baige-nav-popup" id="categorySub">
                <div class="baige-nav-popup-content">
                    <ul class="baige-nav-popup-list">
                        <?php
                        $this->widget('Widget_Metas_Category_List')->to($categorys);
                        ?>
                        <?php while($categorys->next()): ?>
                        <?php if ($categorys->levels === 0): ?>
                        <?php
                        ob_start();
                        $categorys->name();
                        $cat_name = ob_get_clean();
                        $cat_icon = getCategoryIcon($cat_name);
                        ?>
                        <li>
                            <a href="<?php $categorys->permalink(); ?>">
                                <img src="<?php echo $cat_icon; ?>" alt="<?php echo $cat_name; ?>" class="baige-nav-popup-icon">
                                <?php echo $cat_name; ?>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php endwhile; ?>
                    </ul>
                </div>
            </div>
        </li>
        <?php else: ?>
        <!-- 分类 -->
        <?php $this->widget('Widget_Metas_Category_List')->to($categorys); ?>
        <?php while($categorys->next()): ?>
        <?php if ($categorys->levels === 0): ?>
        <li>
            <a href="<?php $categorys->permalink(); ?>" class="menuA">
                <span class="baige-margin-right">|</span> <?php $categorys->name(); ?> 
            </a>
        </li>
        <?php endif; ?>
        <?php endwhile; ?>
        <?php endif; ?>

        <!-- 页面下拉菜单 -->
        <?php if ($this->options->yemian === 'nav'): 
            $pageMode = $this->options->pageMenuMode ?: 'dropdown';
        ?>
            <?php if ($pageMode === 'dropdown'): ?>
            <!-- 页面折叠为弹窗 -->
            <li class="baige-nav-page-item" id="pageItem">
                <a href="javascript:;" class="menuA" id="pageBtn">
                    <span class="baige-margin-right">|</span>页面 ▾
                </a>
                <div class="baige-nav-popup" id="pageSub">
                    <div class="baige-nav-popup-content">
                        <ul class="baige-nav-popup-list">
                            <?php $this->widget('Widget_Contents_Page_List')->to($pages); ?>
                            <?php while($pages->next()): ?>
                            <li><a href="<?php $pages->permalink(); ?>"><?php $pages->title(); ?></a></li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                </div>
            </li>
            <?php else: ?>
            <!-- 页面全部展开显示 -->
            <?php $this->widget('Widget_Contents_Page_List')->to($pages); ?>
            <?php while($pages->next()): ?>
            <li><a href="<?php $pages->permalink(); ?>" class="menuA">
                <span class="baige-margin-right">|</span> <?php $pages->title(); ?>
            </a></li>
            <?php endwhile; ?>
            <?php endif; ?>
        <?php endif; ?>
    </ul>
</div>

    </div>

<script>
// 分类
(function() {
    var categoryBtn = document.getElementById('categoryBtn');
    var categorySub = document.getElementById('categorySub');
    var categoryItem = document.getElementById('categoryItem');
    var pageBtn = document.getElementById('pageBtn');
    var pageSub = document.getElementById('pageSub');
    var pageItem = document.getElementById('pageItem');
    
    function closeAllMenus() {
        if (categorySub) categorySub.classList.remove('is-open');
        if (pageSub) pageSub.classList.remove('is-open');
    }
    
    function bindPopup(btn, popup, item) {
        if (!btn || !popup) return;

        // 手机端弹窗定位
        function positionPopup() {
            if (window.innerWidth <= 768) {
                var rect = btn.getBoundingClientRect();
                popup.style.position = 'fixed';
                popup.style.top = (rect.bottom + 6) + 'px';
                popup.style.left = '50%';
                popup.style.transform = 'translateX(-50%) translateY(0)';
            } else {
                popup.style.position = '';
                popup.style.top = '';
                popup.style.left = '';
                popup.style.transform = '';
            }
        }
        
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var isOpen = popup.classList.contains('is-open');
            closeAllMenus();
            if (!isOpen) {
                positionPopup();
                popup.classList.add('is-open');
            }
        });
        
        // 点击弹窗内链接后自动关闭
        popup.addEventListener('click', function() {
            popup.classList.remove('is-open');
        });
    }
    
    bindPopup(categoryBtn, categorySub, categoryItem);
    bindPopup(pageBtn, pageSub, pageItem);
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeAllMenus();
    });
    
    document.addEventListener('click', function(e) {
        var isClickInCategory = categoryItem && categoryItem.contains(e.target);
        var isClickInPage = pageItem && pageItem.contains(e.target);
        if (!isClickInCategory && categorySub) categorySub.classList.remove('is-open');
        if (!isClickInPage && pageSub) pageSub.classList.remove('is-open');
    });
})();
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function updateHeaderBg() {
        // 真实读取你主题保存的位置
        let theme = localStorage.getItem('theme') || 'theme-1';
        let num = theme.replace('theme-', '');

        // 正确图片
        let imgUrl = '/usr/themes/dage/bai/img/bg/theme-' + num + '.webp';

        // 头部
        let header = document.querySelector('.baige-header');
        if (header) {
            header.style.backgroundImage = 'url(' + imgUrl + ')';
            header.style.backgroundSize = 'cover';
            header.style.backgroundPosition = 'center';
        }
    }

    // 首次加载
    updateHeaderBg();

    // 点击切换主题按钮时立即刷新背景
    var themeBtn = document.querySelector('.baige-themecolor');
    if (themeBtn) {
        themeBtn.addEventListener('click', function () {
            setTimeout(updateHeaderBg, 30);
        });
    }
});
</script>
<!-- 主体内容 -->
<div class="baige-Tbody">