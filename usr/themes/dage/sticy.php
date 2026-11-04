<?php
/**
 * BDG 3.5 文章置顶
 */

$sticky = $this->options->sticky;
if ($sticky && ($this->is('index') || $this->is('front'))) {
    // CID
    $sticky_cids = explode(',', strtr($sticky, ' ', ','));
    $sticky_html = "<span class='baige-list-zhiding'>[置顶]</span>";

    $db = Typecho_Db::get();
    $pageSize = $this->options->pageSize ?? $this->parameter->pageSize;

    // 构建查询
    $select1 = $this->select()->where('type = ?', 'post');
    $select2 = $this->select()->where('type = ? && status = ? && created < ?', 'post', 'publish', time());

    // 清空文章队列
    $this->row = [];
    $this->stack = [];
    $this->length = 0;

    // 置顶
    $order = '';
    foreach ($sticky_cids as $i => $cid) {
        $cid = trim($cid);
        if (empty($cid)) continue;

        $select1 = $i === 0 ? $select1->where('cid = ?', $cid) : $select1->orWhere('cid = ?', $cid);
        $order .= " when $cid then $i";
        $select2->where('table.contents.cid != ?', $cid);
    }

    // 自定义排序
    if ($order) {
        $select1->order('', "(case cid$order end)");
    }


    // 置顶
    if ($this->currentPage == 1) {
        foreach ($db->fetchAll($select1) as $sticky_post) {
            $sticky_post['sticky'] = $sticky_html;
            $this->push($sticky_post);
        }
    }

    // 登录用户可见私密文章
    $uid = $this->user->uid;
    if ($uid) {
        $select2->orWhere('authorId = ? && status = ?', $uid, 'private');
    }

    // 查询并加入普通文章
    $sticky_posts = $db->fetchAll(
        $select2->order('table.contents.created', Typecho_Db::SORT_DESC)
                ->page($this->currentPage, $this->parameter->pageSize)
    );
    foreach ($sticky_posts as $sticky_post) {
        $this->push($sticky_post);
    }

    // 分页
    $this->setTotal($this->getTotal() - count(array_filter(array_map('trim', $sticky_cids))));
}
?>