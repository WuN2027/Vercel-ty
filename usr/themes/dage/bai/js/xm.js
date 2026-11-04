// 暗黑模式切换js
function switchNightMode() {
    var night = document.cookie.replace(/(?:(?:^|.*;\s*)night\s*\=\s*([^;]*).*$)|^.*$/, "$1") || '0';
    var darkLink = document.querySelector('link[title="dark"]');
    if (!darkLink) return;
    if (night == '0') {
        darkLink.disabled = false;
        document.cookie = "night=1;path=/";
    } else {
        darkLink.disabled = true;
        document.cookie = "night=0;path=/";
    }
}

// 返回顶部 & 护眼按钮：滚动时显示，停止滚动30秒后隐藏
(function() {
    var obtn = document.getElementById('baige-btn');
    var themeBtn = document.querySelector('.baige-themecolor-btn');
    var muluBtn = document.getElementById('baige-mulu-btn');
    var timer = null;
    var isTop = true;
    var hideTimer = null;

    // 获取/设置滚动位置，统一使用 document.scrollingElement，避免同时操作 html/body 导致抖动
    function getScrollTop() {
        var scrollEl = document.scrollingElement || document.documentElement;
        return scrollEl.scrollTop || document.body.scrollTop || 0;
    }
    function setScrollTop(value) {
        if (document.scrollingElement) {
            document.scrollingElement.scrollTop = value;
        } else {
            document.documentElement.scrollTop = value;
            document.body.scrollTop = value;
        }
    }

    // 隐藏悬浮按钮
    function hideFabs() {
        if (obtn) obtn.classList.add('baige-fab-hidden');
        if (themeBtn) themeBtn.classList.add('baige-fab-hidden');
        if (muluBtn) muluBtn.classList.add('baige-fab-hidden');
    }
    // 显示悬浮按钮
    function showFabs() {
        var osTop = getScrollTop();
        if (obtn) {
            if (osTop >= 100) {
                obtn.classList.remove('baige-fab-hidden');
            } else {
                obtn.classList.add('baige-fab-hidden');
            }
        }
        if (themeBtn) themeBtn.classList.remove('baige-fab-hidden');
        if (muluBtn && muluBtn.classList.contains('baige-mulu-has-content')) {
            muluBtn.classList.remove('baige-fab-hidden');
        }
    }

    // 重置隐藏定时器（每次滚动时重置）
    function resetHideTimer() {
        clearTimeout(hideTimer);
        hideTimer = setTimeout(hideFabs, 30000);
    }

    window.onscroll = function() {
        showFabs();
        resetHideTimer();

        if (!isTop) {
            clearInterval(timer);
        }
        isTop = false;
    };

    // 初始隐藏
    setTimeout(hideFabs, 30000);

    // 返回顶部点击
    if (obtn) {
        obtn.onclick = function() {
            // 点击后立即隐藏按钮，滚动过程中由 onscroll 接管显隐
            if (obtn) obtn.classList.add('baige-fab-hidden');
            if (timer) clearInterval(timer);

            // 使用自定义缓动滚动，避免原生 smooth 滚动过快或部分浏览器不兼容
            timer = setInterval(function() {
                var osTop = getScrollTop();
                var speed = Math.floor(-osTop / 12);
                isTop = true;
                if (osTop <= Math.max(2, Math.abs(speed))) {
                    setScrollTop(0);
                    clearInterval(timer);
                } else {
                    setScrollTop(osTop + speed);
                }
            }, 16);
        };
    }
})();

// 时间线年份折叠/展开
(function() {
    document.addEventListener('click', function(e) {
        var toggle = e.target.closest('.baige-timeline-year, .baige-timeline-toggle, .baige-timeline-icon');
        if (!toggle) return;
        var item = toggle.closest('.baige-timeline-item');
        if (!item) return;
        item.classList.toggle('is-collapsed');
        item.classList.toggle('is-expanded');
    });
})();

// 手机端顶部菜单按钮：仅在页面顶部显示，向下滚动后隐藏
(function() {
    var menuToggle = document.getElementById('mobileMenuToggle');
    if (!menuToggle) return;

    var threshold = 80;
    function updateMenuToggle() {
        var scrollTop = window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0;
        if (scrollTop <= threshold) {
            menuToggle.classList.remove('baige-menu-hidden');
        } else {
            menuToggle.classList.add('baige-menu-hidden');
        }
    }

    window.addEventListener('scroll', updateMenuToggle);
    updateMenuToggle();
})();

// 文章目录
(function() {
    var muluBtn = document.getElementById('baige-mulu-btn');
    var muluPanel = document.getElementById('baige-mulu-panel');
    var muluBody = document.getElementById('baige-mulu-body');
    var muluClose = document.getElementById('baige-mulu-close');
    if (!muluBtn || !muluPanel || !muluBody) return;

    var contentBody = document.querySelector('.baige-Content-body');
    if (!contentBody) return;

    // 只取 h1-h6，过滤掉标题区、侧边栏等无关标题
    var headings = Array.prototype.slice.call(contentBody.querySelectorAll('h1, h2, h3, h4, h5, h6'));
    // 排除文章大标题（.baige-ContentTitle 所在的 h1）
    headings = headings.filter(function(h) {
        return !h.closest('.baige-Content-top') && !h.classList.contains('baige-ContentTitle');
    });
    if (headings.length < 4) return;

    // 生成目录
    var fragment = document.createDocumentFragment();
    headings.forEach(function(h, index) {
        var id = h.id || ('baige-mulu-heading-' + index);
        h.id = id;

        var a = document.createElement('a');
        a.href = '#' + id;
        a.className = 'baige-mulu-item baige-mulu-level-' + h.tagName.toLowerCase();
        a.textContent = (index + 1) + '. ' + (h.textContent.trim() || '无标题');
        a.dataset.target = id;
        a.title = h.textContent.trim() || '无标题';

        a.addEventListener('click', function(e) {
            e.preventDefault();
            var target = document.getElementById(id);
            if (!target) return;
            var offset = 80; // 预留顶部空间
            var top = target.getBoundingClientRect().top + (window.pageYOffset || document.documentElement.scrollTop) - offset;
            if ('scrollBehavior' in document.documentElement.style) {
                window.scrollTo({ top: top, behavior: 'smooth' });
            } else {
                window.scrollTo(0, top);
            }
            // 移动端点击后关闭面板
            if (window.innerWidth < 768) {
                muluPanel.classList.add('baige-fab-hidden');
            }
        });

        fragment.appendChild(a);
    });
    muluBody.appendChild(fragment);

    // 标记目录按钮有内容，showFabs 才允许显示
    muluBtn.classList.add('baige-mulu-has-content');
    // 显示目录按钮
    muluBtn.classList.remove('baige-fab-hidden');

    // 打开/关闭目录面板
    function togglePanel() {
        muluPanel.classList.toggle('baige-fab-hidden');
    }
    function closePanel() {
        muluPanel.classList.add('baige-fab-hidden');
    }

    muluBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        togglePanel();
    });
    if (muluClose) muluClose.addEventListener('click', closePanel);

    // 点击面板外部关闭
    document.addEventListener('click', function(e) {
        if (!muluPanel.classList.contains('baige-fab-hidden') &&
            !muluPanel.contains(e.target) &&
            !muluBtn.contains(e.target)) {
            closePanel();
        }
    });

    // 滚动高亮当前章节
    var links = muluBody.querySelectorAll('a');
    function highlight() {
        var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        var offset = 100;
        var current = null;

        for (var i = 0; i < headings.length; i++) {
            var h = headings[i];
            var top = h.getBoundingClientRect().top + scrollTop - offset;
            if (scrollTop >= top) {
                current = links[i];
            } else {
                break;
            }
        }

        links.forEach(function(link) { link.classList.remove('active'); });
        if (current) {
            current.classList.add('active');
            current.scrollIntoView({ block: 'nearest' });
        }
    }

    var highlightTimer = null;
    window.addEventListener('scroll', function() {
        clearTimeout(highlightTimer);
        highlightTimer = setTimeout(highlight, 80);
    });
    highlight();
})();