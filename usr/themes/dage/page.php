<?php
/**
 * 独立页面模板 3.5
 * @author 柏哥
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>
<?php $this->need('sidebar.php'); ?>
<div class="baige-mainContent">
    <div class="baige-innermainContent">
        <div class="baige-Content">
            <div class="baige-Content-body baige-text-yichu">
                <?php
                $content = $this->content;

                // 隐藏内容
                if (strpos($content, '{hide') !== false) {
                    $db = Typecho_Db::get();
                    $sql = $db->select()
                        ->from('table.comments')
                        ->where('cid = ?', $this->cid)
                        ->where('mail = ?', $this->remember('mail', true))
                        ->limit(1);
                    $result = $db->fetchAll($sql);

                    if ($this->user->hasLogin() || $result) {
                        $content = preg_replace("/{hide}(.*?){\/hide}/sm", '<div class="baige-reply-view">$1</div>', $content);
                    } else {
                        $content = preg_replace("/{hide}(.*?){\/hide}/sm", '<div class="baige-wx-ying">此处内容需要评论 <a href="#baige-comment-form">回复</a> 后方可阅读。</div>', $content);
                    }
                }

                echo xm::biaoqing(xm::ubb($content,true));
                ?>
            </div>

            <!-- 页面底部信息 -->
            <div class="baige-Content-bottom">
                日期: <?php echo xm::time($this->created); ?> |
                评论: <?php $this->commentsNum('0', '1', '%d'); ?> |
                查看: <?php echo xm::get_post_view($this); ?>
            </div>
        </div>

        <?php if($this->options->pinglun == '1'): $this->need('commentsa.php'); else: $this->need('comments.php'); endif; ?>
    </div>
</div>

<?php $this->need('footer.php'); ?>
