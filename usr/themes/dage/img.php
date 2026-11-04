<?php 
/**
 * 图片转换工具 3.5
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
            <h2>在线图片转换压缩</h2>
            <p class="tool-desc-text">支持 JPG / PNG / WEBP 互转，自由调节画质，全部在浏览器本地完成</p>
        </section>

        <div class="tool-card img-tool">
            <div class="img-preview-wrap">
                <canvas id="previewCanvas"></canvas>
            </div>

            <div class="img-controls">
                <div class="img-selects">
                    <select class="tool-select" id="qualitySelect">
                        <option value="0.3" selected>画质:30% (小体积)</option>
                        <option value="0.7">画质:70% (平衡)</option>
                        <option value="1.0">画质:100% (无损)</option>
                    </select>
                    <select class="tool-select" id="formatSelect">
                        <option value="webp" selected>输出格式:WEBP</option>
                        <option value="jpg">输出格式:JPG</option>
                        <option value="png">输出格式:PNG</option>
                    </select>
                </div>
                <div class="img-actions">
                    <label class="tool-btn">
                        <input type="file" id="imageLoader" accept="image/jpg,image/jpeg,image/png,image/webp">
                        <span>选择图片</span>
                    </label>
                    <button class="tool-btn tool-btn-success" id="downloadButton">下载图片</button>
                </div>
            </div>

            <div class="img-tips">
                提示：1. PNG输出为无损格式，画质设置仅对JPG/WEBP生效；2. 百分比越高画质越好，文件越大；3. 支持JPG/PNG/WEBP格式互转
            </div>
        </div>

<br>
        <div class="baige-Content-bottom">
            <div>
                评论: <?php $this->commentsNum('0', '1', '%d'); ?> | 
                查看: <?php echo xm::get_post_view($this); ?>
            </div>
        </div>

        <?php $this->need('comments.php'); ?>
    </div>
</div>

<div class="res-toast" id="resToast"></div>
<?php $this->need('footer.php'); ?>

<script>
let currentImage = null, canvas = null, ctx = null, hasValidContent = false;
let imageSrc = '', originalFileName = 'converted', currentQuality = 0.3;
let currentFormat = 'webp', originalFileFormat = '';
const ALLOWED_TYPES = ['image/jpg','image/jpeg','image/png','image/webp'];
const FORMAT_MIME_TYPES = { jpg:'image/jpeg', png:'image/png', webp:'image/webp' };
const FORMAT_SUFFIX = { 'image/jpg':'jpg', 'image/jpeg':'jpg', 'image/png':'png', 'image/webp':'webp' };

function initCanvas() {
    canvas = document.getElementById('previewCanvas');
    ctx = canvas.getContext('2d');

    let savedData = null;
    if (hasValidContent && canvas.width > 0 && canvas.height > 0) {
        savedData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    }

    const size = Math.min(400, canvas.clientWidth);
    canvas.width = size;
    canvas.height = size;

    if (savedData) {
        ctx.putImageData(savedData, 0, 0);
    } else if (hasValidContent && currentImage) {
        previewImage();
    } else {
        let cs = getComputedStyle(document.documentElement);
        ctx.font = '16px Arial';
        ctx.fillStyle = cs.getPropertyValue('--color-chunhei');
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('请上传图片', canvas.width / 2, canvas.height / 2);
    }
}

function previewImage() {
    if (!currentImage || !imageSrc) { initCanvas(); return; }
    let img = new Image();
    img.onload = function() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        let scale = Math.min(canvas.width / img.width, canvas.height / img.height);
        let w = img.width * scale;
        let h = img.height * scale;
        let x = (canvas.width - w) / 2;
        let y = (canvas.height - h) / 2;
        ctx.drawImage(img, x, y, w, h);
        hasValidContent = true;
    };
    img.src = imageSrc;
}

function handleImageUpload(e) {
    let file = e.target.files[0];
    if (!file) return;
    if (!ALLOWED_TYPES.includes(file.type)) {
        alert('仅支持 JPG / PNG / WEBP');
        e.target.value = '';
        return;
    }
    originalFileFormat = FORMAT_SUFFIX[file.type];
    originalFileName = file.name.split('.').slice(0, -1).join('.').replace(/[^\w\u4e00-\u9fa5]/g, '_');
    let reader = new FileReader();
    reader.onload = function(ev) {
        imageSrc = ev.target.result;
        currentImage = new Image();
        currentImage.src = imageSrc;
        currentImage.onload = previewImage;
    };
    reader.readAsDataURL(file);
}

function switchQuality(v) {
    currentQuality = parseFloat(v);
}

function switchFormat(f) {
    currentFormat = f;
}

function downloadImage() {
    if (!hasValidContent || !currentImage) {
        alert('请先上传图片');
        return;
    }
    let c = document.createElement('canvas');
    c.width = currentImage.width;
    c.height = currentImage.height;
    let ct = c.getContext('2d');
    ct.drawImage(currentImage, 0, 0);
    let mime = FORMAT_MIME_TYPES[currentFormat];
    let name = `${originalFileName}_${Date.now()}.${currentFormat}`;
    let q = currentQuality;
    if (q == 1) {
        if (currentFormat === 'webp') q = 0.95;
        if (currentFormat === 'jpg') q = 0.92;
    }
    c.toBlob(blob => {
        let url = URL.createObjectURL(blob);
        let a = document.createElement('a');
        a.href = url;
        a.download = name;
        a.click();
        URL.revokeObjectURL(url);
    }, mime, q);
}

window.addEventListener('DOMContentLoaded', () => {
    initCanvas();
    window.addEventListener('resize', initCanvas);
    document.getElementById('imageLoader').addEventListener('change', handleImageUpload);
    document.getElementById('qualitySelect').addEventListener('change', e => switchQuality(e.target.value));
    document.getElementById('formatSelect').addEventListener('change', e => switchFormat(e.target.value));
    document.getElementById('downloadButton').onclick = downloadImage;
});
</script>
