<?php 
if (!defined('__TYPECHO_ROOT_DIR__')) exit; 
header("HTTP/1.1 404 Not Found");
header("Status: 404 Not Found");
?>
<?php $this->need('header.php'); ?>
<?php $this->need('sidebar.php'); ?> 
<div class="baige-mainContent">      
<div class="baige-innermainContent"> 
<div class="baige-error-page">
    <h2 class="m-error-title">404 - <?php _e('页面没找到'); ?></h2>
    <p class="baige-error-ts"><?php _e('你想查看的页面已被转移或删除了, 要不搜索看看。 '); ?></p>

    <p style="margin-top: 15px; color: #666; font-size: 14px;">
        <?php _e(' <span id="countdown">10</span> 秒后将自动进入留言本...'); ?>
    </p>

</div>
</div></div>
<script>
// 用JS实现倒计时跳转
let seconds = 10;
const countdownEl = document.getElementById('countdown');
const timer = setInterval(() => {
    seconds--;
    countdownEl.textContent = seconds;
    if (seconds <= 0) {
        clearInterval(timer);
        window.location.href = 'https://www.pipishe.com/message.html';
    }
}, 1000);
</script>

<?php $this->need('footer.php'); ?>
