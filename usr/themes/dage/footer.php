<?php 
if (!defined('__TYPECHO_ROOT_DIR__')) exit; 
?>
<div style="clear: both;"></div>
</div>

<div class="baige-foot">
    <!-- 底部页面导航 -->
    <?php if($this->options->yemian === 'bottom'): ?>
    <div class="baige-yemian">
        <?php \Widget\Contents\Page\Rows::alloc()->to($pages); ?>
        <?php while ($pages->next()): ?>
        <a href="<?php $pages->permalink(); ?>" title="<?php $pages->title(); ?>"><?php $pages->title(); ?></a>
        <?php endwhile; ?>
        <?php $this->options->daolink() ?>
    </div>
    <?php endif; ?>

<!-- 独立运行时间 -->
<?php 
$showRuntime = !empty($this->options->sidebarBlock) && in_array('Showyunxing', $this->options->sidebarBlock);
if ($showRuntime): 
?>
<span id="runtime_span" style="margin-left: 2px;"></span>
<script>
function show_runtime() {
    function updateRuntime() {
        const start = new Date("4/1/2024 8:00:00");
        const now = new Date();
        const diff = now - start;
        const day = Math.floor(diff / (24 * 60 * 60 * 1000));
        const hour = Math.floor((diff % (24 * 60 * 60 * 1000)) / (60 * 60 * 1000));
        const min = Math.floor((diff % (60 * 60 * 1000)) / (60 * 1000));
        const sec = Math.floor((diff % (60 * 1000)) / 1000);
        
        document.getElementById('runtime_span').innerText = 
            `小站勉强运行:${day}天${hour}小时${String(min).padStart(2, '0')}分${String(sec).padStart(2, '0')}秒`;
    }
    updateRuntime();
    setInterval(updateRuntime, 1000);
}
show_runtime(); 
</script>
<br>
<?php endif; ?>

<!-- 网站备案信息 -->
<?php 
$showIcp = $this->options->footer || $this->options->ganBeiAn;
if ($showIcp): 
?>
    <?php if ($this->options->footer): ?>
    <img src="/usr/themes/dage/bai/img/icp.webp" width="20" height="20" style="vertical-align: middle;"> 
    <a href="https://beian.miit.gov.cn/" target="_blank" style="vertical-align: middle;">
        <?php $this->options->footer(); ?>
    </a>
    <?php endif; ?>

    <?php if ($this->options->ganBeiAn): ?>
    <img src="/usr/themes/dage/bai/img/gaba.webp" width="20" height="20" style="vertical-align: middle; <?php if ($this->options->footer): ?>margin-left: 8px;<?php endif; ?>">
    <a href="https://beian.mps.gov.cn/#/query/webSearch" target="_blank" rel="noopener" style="vertical-align: middle;">
        <?php $this->options->ganBeiAn(); ?>
    </a>
    <?php endif; ?>

    <br>
<?php endif; ?>

<!-- 网站版权信息 -->
<span id="copyright_span">©2024-<?php echo date('Y'); ?> <?php echo $this->options->title(); ?> </span>

</div>
</div>

<!-- 移动端遮罩层 -->
<div id="mobileMenuMask"></div>

<!-- 返回顶部按钮 -->
<div class="baige-fanhui baige-fab-hidden" id="baige-btn">
    <img src="<?php echo Helper::options()->themeUrl(); ?>/bai/img/back-top.svg" alt="返回顶部">
</div>

<!-- 文章目录按钮 -->
<?php if ($this->options->mulu == 'ok' && $this->is('single')): ?>
<div class="baige-mulu baige-mulu-btn baige-fab-hidden" id="baige-mulu-btn" title="文章目录">
    <img src="<?php echo Helper::options()->themeUrl(); ?>/bai/img/catalog.svg" alt="文章目录">
</div>
<div class="baige-mulu-panel baige-fab-hidden" id="baige-mulu-panel">
    <div class="baige-mulu-header">
        <span>文章目录</span>
        <span class="baige-mulu-close" id="baige-mulu-close">&times;</span>
    </div>
    <div class="baige-mulu-body" id="baige-mulu-body"></div>
</div>
<?php endif; ?>

<!-- 主题色切换按钮：仅在首页显示 -->
<?php if ($this->options->anhei == 'ok' && $this->is('index')): ?>
<div class="baige-themecolor baige-themecolor-btn baige-fab-hidden" onclick="toggleThemeColor()">
    <img src="<?php echo Helper::options()->themeUrl(); ?>/bai/img/palette.svg" alt="主题切换">
</div>
<?php endif; ?>


<!-- 公共脚本加载 -->
<?php if ($this->options->jquery): ?>
<?php $this->options->jquery(); ?>
<?php else: ?>
<script src="<?php echo XM_TPL; ?>bai/js/jquery.min.js"></script>
<?php endif; ?>

<script src="<?php echo XM_TPL; ?>bai/js/xm.js"></script>
<script src="<?php echo XM_TPL; ?>bai/js/hls.min.js" defer></script>
<script src="<?php echo XM_TPL; ?>bai/js/hlsplayer.js" defer></script>

<!-- 懒加载 -->
<?php if ($this->options->lazyload): ?>
<script src="<?php echo XM_TPL; ?>bai/js/lazyload.min.js" defer></script>
<?php endif; ?>

<!-- 图片灯箱效果脚本 -->
<?php if (
    $this->options->fancybox === 'oka' || 
    ($this->options->fancybox === 'okb' && $this->is('single')) || 
    ($this->options->fancybox === 'okc' && ($this->is('archive') || $this->is('index')))
): ?>
<script src="<?php echo XM_TPL; ?>bai/js/fancybox.min.js" defer></script>
<?php endif; ?>

<!-- 文章页专属脚本 -->
<?php if($this->is('single')): ?>
<script src="<?php echo XM_TPL; ?>bai/prism/prism.js"></script>
<script src="<?php echo XM_TPL; ?>bai/owo/owo.js" defer></script>
<?php endif; ?>

<!-- 移动端侧边栏抽屉脚本 -->
<?php $sidebarPosFooter = $this->options->sidebarPosition ?: 'left'; ?>
<?php if ($sidebarPosFooter !== 'off'): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('mobileMenuToggle');
    const sidebar = document.getElementById('sidebar');
    const mask = document.getElementById('mobileMenuMask');

    if (!toggle || !sidebar || !mask) return;

    const showSidebar = () => {
        sidebar.classList.add('mobile-show');
        mask.classList.add('show');
        document.body.style.overflow = 'hidden';
    };

    const hideSidebar = () => {
        sidebar.classList.remove('mobile-show');
        mask.classList.remove('show');
        document.body.style.overflow = 'auto';
    };

    toggle.addEventListener('click', () => {
        sidebar.classList.contains('mobile-show') ? hideSidebar() : showSidebar();
    });
    mask.addEventListener('click', hideSidebar);
});
</script>
<?php endif; ?>

<!-- 核心功能脚本 -->
<script>
// 禁止SVG图标右键菜单
document.addEventListener('contextmenu', function(e) {
    var el = e.target;
    // IMG标签的SVG
    if (el.tagName === 'IMG' && el.src && el.src.endsWith('.svg')) {
        e.preventDefault();
        return;
    }
    // 向上查父元素是否包含SVG背景图
    var t = el;
    while (t && t !== document.body) {
        var bg = window.getComputedStyle(t).backgroundImage;
        if (bg && bg.includes('.svg')) {
            e.preventDefault();
            return;
        }
        t = t.parentElement;
    }
});
// 隐私评论提示切换
function tsok(){
    var kkk = document.getElementById("textarea")?.getAttribute('placeholder');
    if (kkk == '正在隐私评论中') {
        document.getElementById("textarea").setAttribute("placeholder", "请文明用语！");
    } else {
        document.getElementById("textarea").setAttribute("placeholder", "正在隐私评论中");
    }
}

// 懒加载
<?php if ($this->options->lazyload): ?>
document.addEventListener('DOMContentLoaded', () => {
    window.lazyload && lazyload();
});
<?php endif; ?>

// 手风琴效果
<?php if($this->is('single')): ?>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.baige-sfq').forEach(acc => {
        acc.addEventListener('click', () => {
            acc.classList.toggle('active');
            const panel = acc.nextElementSibling;
            panel.style.maxHeight = panel.style.maxHeight ? '' : `${panel.scrollHeight}px`;
        });
    });
});
<?php endif; ?>

// 分页
<?php if (($this->is('index') || $this->is('archive') || $this->is('category') || $this->is('tag')) && $this->request->lq == 'no'): ?>
document.addEventListener('DOMContentLoaded', () => {
    const nextBtn = document.querySelector('.next');
    let isLoading = false;
    if (nextBtn) {
        nextBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (isLoading) return;
            isLoading = true;
            nextBtn.classList.add('loading');
            nextBtn.textContent = '正在努力加载';
            const href = nextBtn.getAttribute('href') + '?lq=no';
            fetch(href)
                .then(res => { if (!res.ok) throw new Error('请求失败'); return res.text(); })
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const contentList = doc.querySelectorAll('.baige-jjcontent');
                    const contentContainer = document.getElementById('content');
                    if (contentList.length > 0) {
                        contentList.forEach(item => {
                            const cloneItem = item.cloneNode(true);
                            cloneItem.style.display = 'none';
                            contentContainer.appendChild(cloneItem);
                            setTimeout(() => { cloneItem.style.display = 'block'; }, 10);
                        });
                    }
                    const newHref = doc.querySelector('.next')?.getAttribute('href');
                    if (newHref) { nextBtn.setAttribute('href', newHref); } else { nextBtn.remove(); }
                    nextBtn.classList.remove('loading');
                    nextBtn.textContent = '点击查看更多';
                    isLoading = false;
                })
                .catch(err => {
                    console.error('加载失败：', err);
                    nextBtn.classList.remove('loading');
                    nextBtn.textContent = '加载失败，点击重试';
                    isLoading = false;
                });
        });
    }
});
<?php endif; ?>

// 后端配置的默认主题色
window.baigeDefaultTheme = <?php echo json_encode($this->options->defaultTheme ?: 'theme-1'); ?>;

// 主题切换
function toggleThemeColor() {
    const themes = ['theme-1', 'theme-2', 'theme-3', 'theme-4', 'theme-5', 'theme-6', 'theme-7'];
    let current = localStorage.getItem('site-theme') || window.baigeDefaultTheme || 'theme-1';
    let index = themes.indexOf(current);
    index = (index + 1) % themes.length;
    let theme = themes[index];
    
    document.documentElement.className = theme;
    localStorage.setItem('site-theme', theme);
    // 固定按钮为主题主色
    document.querySelector('.baige-themecolor')?.classList.add('active-theme');
}

// 页面加载自动恢复主题
document.addEventListener('DOMContentLoaded', () => {
    let saved = localStorage.getItem('site-theme') || window.baigeDefaultTheme || 'theme-1';
    document.documentElement.className = saved;
    document.querySelector('.baige-themecolor')?.classList.add('active-theme');
});
</script>

<!-- Typecho 底部钩子 -->
<?php $this->footer(); ?>

<?php
// 全局友链来访统计：任何页面被友链站点访问时都计入排名
// 只要有配置友链页面 CID 就启用，与侧边栏显示模式无关
if (class_exists('xm') && method_exists('xm', 'xm_linkc')) {
    xm::xm_linkc();
}
?>
</body>
</html>

<!-- 输出 -->
<?php 
if ($this->options->compressHtml) : 
    $html_source = ob_get_contents();
    if ($html_source !== false) {
        ob_end_clean();
        echo xm::compressHtml($html_source);
    }
endif; 
?>