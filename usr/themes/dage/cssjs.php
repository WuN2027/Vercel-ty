<?php 
/**
 * 代码工具 3.5
 * @author 柏哥
 * @package custom
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit; 
$this->setPingback(false);
$this->need('header.php');
?>

<?php $this->need('sidebar.php'); ?>
<div class="baige-mainContent">
    <div class="baige-innermainContent">

        <section class="tool-hero">
            <h2>CSS / JS 在线压缩格式化</h2>
            <p class="tool-desc-text">粘贴代码即可格式化、压缩、清空或一键复制，纯前端处理</p>
        </section>

        <div class="tool-card baige-code-tool">
            <div class="baige-code-editor-wrap">
                <label class="tool-label" for="codeInput">输入代码</label>
                <textarea id="codeInput" placeholder="请粘贴 CSS / JS 代码..."></textarea>
            </div>

            <div class="baige-code-toolbar">
                <button class="tool-btn" id="formatBtn">CSS/JS 格式化</button>
                <button class="tool-btn tool-btn-secondary" id="advancedCompressBtn">CSS/JS 压缩</button>
                <button class="tool-btn tool-btn-danger" id="clearBtn">清空</button>
                <button class="tool-btn tool-btn-success" id="copyBtn">复制</button>
            </div>

            <div class="baige-code-output-wrap">
                <label class="tool-label" for="outputDisplay">处理结果</label>
                <pre id="outputDisplay"></pre>
            </div>
        </div>

        <div class="tool-info">
            <p><strong>1.</strong> 压缩CSS代码可以大幅度减少网页的大小，加快网页加载速度</p>
            <p><strong>2.</strong> Js/Css文件过大，用户访问站点时下载时间长，会感觉网站响应慢，甚至造成页面初始打开凌乱</p>
            <p><strong>3.</strong> 我们无法改变用户的网络状况，只能优化自身网站，让服务器与资源加载更快，提升用户体验</p>
        </div><br>
		
        <div class="baige-Content-bottom">
            <div>评论: <?php $this->commentsNum('0', '1', '%d'); ?> | 查看: <?php echo xm::get_post_view($this); ?></div>
        </div>

        <?php $this->need('comments.php'); ?>
    </div>
</div>

<div class="tool-toast" id="toast"></div>
<?php $this->need('footer.php'); ?>

<script>
const App = {
    init() { this.bind(); },
    bind() {
        $('#formatBtn').click(()=>this.formatCssJs());
        $('#advancedCompressBtn').click(()=>this.advancedCssJs());
        $('#clearBtn').click(()=>this.clear());
        $('#copyBtn').click(()=>this.copy());
    },
    toast(m){
        $('#toast').text(m).fadeIn();
        setTimeout(()=>$('#toast').fadeOut(),2000);
    },

    formatCssJs(){
        let v=$('#codeInput').val().trim();
        if(!v)return this.toast('请输入代码');

        const tokens=[];
        let idx=0;

        // 保护字符串
        const tokenRegex=/("(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|\/\*[\s\S]*?\*\/|\/\/.*|url\([^)]+\))/g;
        v=v.replace(tokenRegex,(match)=>{
            tokens[idx]=match;
            return `__TOKEN_${idx++}__`;
        });

        // 压缩空白
        v=v.replace(/\s+/g,' ').trim();

        // 按 { } ; 进行换行分割
        v=v.replace(/\s*\{\s*/g,' {\n')
             .replace(/\s*\}\s*/g,'\n}\n')
             .replace(/;\s*/g,';\n')
             .replace(/,\s*/g,', ');

        const lines=v.split('\n').map(i=>i.trim()).filter(i=>i);
        let indent=0,out='';
        lines.forEach(line=>{
            if(line==='}')indent=Math.max(0,indent-1);
            out+='  '.repeat(indent)+line+'\n';
            if(line.endsWith('{'))indent++;
        });

        // 恢复被保护的内容
        tokens.forEach((tok,i)=>{
            out=out.replace(`__TOKEN_${i}__`,tok);
        });

        $('#outputDisplay').text(out);
        this.toast('CSS/JS 格式化完成');
    },

    advancedCssJs() {
        let v = $('#codeInput').val().trim();
        if (!v) return this.toast('请输入代码');

        const strings = [];
        let idx = 0;

        v = v.replace(/("(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|url\([^)]+\))/g, (match) => {
            strings[idx] = match;
            return `__STRING_${idx++}__`;
        });

        v = v
            .replace(/\/\*[\s\S]*?\*\//g, '')
            .replace(/\/\/.*/g, '')
            .replace(/\s+/g, ' ')
            .replace(/\s*{\s*/g, '{')
            .replace(/\s*}\s*/g, '}')
            .replace(/\s*:\s*/g, ':')
            .replace(/\s*;\s*/g, ';')
            .replace(/\s*,\s*/g, ',')
            .trim();

        strings.forEach((str, i) => {
            v = v.replace(`__STRING_${i}__`, str);
        });

        $('#outputDisplay').text(v);
        this.toast('CSS/JS 压缩完成');
    },

    clear(){
        $('#codeInput').val('');
        $('#outputDisplay').text('');
        this.toast('已清空');
    },

    copy(){
        let t=$('#outputDisplay').text();
        if(!t)return this.toast('暂无内容');
        navigator.clipboard.writeText(t).then(()=>this.toast('复制成功'));
    }
};

$(function(){ App.init(); });
</script>