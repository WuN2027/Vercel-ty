<?php 
 /**
 * 小姐姐视频 3.5
 * @author 柏哥
 * @package custom
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit; 
$this->need('header.php'); 
?>
<?php $this->need('sidebar.php'); ?>
<div class="baige-mainContent">
    <div class="baige-innermainContent">
        <section>
            <article>
                <section class="widget baige-margin-top20 baige-list-wai">
                    <ul class="widget-list">
                        <div class="video-wrapper portrait-mode" id="videoWrapper">
                            <div id="player-container">
                                <div class="video-watermark">皮皮视频</div>
                                <div class="video-loading" id="videoLoading" style="display: flex;">
                                    <img src="/usr/themes/dage/bai/spjz.gif" class="loading-gif" alt="视频加载中">
                                    <div class="video-loading-text">视频正在加载中...</div>
                                </div>
                                <video id="player" src="<?php $this->options->video(); ?>" controls preload="metadata"></video>
                            </div>
                            
                            <div class="video-controls">
                                <section id="buttons">
                                    <button id="switch">连续: 开</button>
                                    <button id="next1">换一个</button>
                                </section>
                            </div>
                        </div>
                    </ul>
                </section>
            </article>
        </section>

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

<script>
    (function (window, document) {
        if (top != self) window.top.location.replace(self.location.href);
        
        const player = document.getElementById('player');
        const loadingIndicator = document.getElementById('videoLoading');
        const playerContainer = document.getElementById('player-container');
        const videoWrapper = document.getElementById('videoWrapper');
        const btnSwitch = document.getElementById('switch');
        const btnNext = document.getElementById('next1');
        
        let auto = true;

        const showLoading = () => {
            loadingIndicator.style.display = 'flex';
            player.classList.remove('loaded');
        };

        const hideLoading = () => {
            loadingIndicator.style.display = 'none';
            player.classList.add('loaded');
        };

        const adjustPlayerRatio = () => {
            hideLoading();
            
            if (player.videoWidth && player.videoHeight) {
                const aspectRatio = player.videoWidth / player.videoHeight;
                
                playerContainer.classList.remove('landscape');
                videoWrapper.classList.remove('landscape-mode', 'portrait-mode');
                
                if (aspectRatio > 1) {
                    playerContainer.classList.add('landscape');
                    videoWrapper.classList.add('landscape-mode');
                } 
                else {
                    videoWrapper.classList.add('portrait-mode');
                }
            } else {
                videoWrapper.classList.add('portrait-mode');
            }
        };

        const randomm = () => {
            showLoading();
            const baseUrl = '<?php $this->options->video(); ?>';
            const newSrc = baseUrl + (baseUrl.indexOf('?') > -1 ? '&' : '?') + '_t=' + Math.random();
            player.src = newSrc;
            
            player.play().then(() => {
                setTimeout(adjustPlayerRatio, 500);
            }).catch(() => setTimeout(adjustPlayerRatio, 500));
        };

        window.addEventListener('DOMContentLoaded', () => {
            videoWrapper.classList.add('portrait-mode');
            
            player.addEventListener('loadedmetadata', adjustPlayerRatio);
            
            setTimeout(adjustPlayerRatio, 300);
        });

        btnNext.addEventListener('click', randomm);
        btnSwitch.addEventListener('click', () => {
            auto = !auto;
            btnSwitch.innerText = '连续: ' + (auto ? '开' : '关');
        });
        
        player.addEventListener('error', () => {
            showLoading();
            setTimeout(randomm, 1000);
        });
        player.addEventListener('canplay', hideLoading);
        player.addEventListener('play', hideLoading);
        player.addEventListener('pause', hideLoading);
        player.addEventListener('waiting', showLoading);
        player.addEventListener('stalled', showLoading);
        player.addEventListener('ended', () => {
            if (auto) {
                showLoading();
                setTimeout(randomm, 500);
            }
        });
        player.addEventListener('loadstart', showLoading);

        window.addEventListener('resize', adjustPlayerRatio);
    })(window, document);
</script>
