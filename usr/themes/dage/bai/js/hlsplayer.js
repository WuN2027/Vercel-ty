// 封装HLS初始化逻辑
function initHlsPlayer(video) {
  // 安全获取HLS地址
  const source = video.querySelector('source[type="application/x-mpegURL"]') || 
                 video.querySelector('source[src$=".m3u8"]');
  if (!source) return; // 跳过非HLS视频

  const hlsUrl = source.src;
  let hls;

  // 清理函数
  const cleanup = () => {
    if (hls) {
      hls.destroy();
      hls = null;
    }
  };

  // 页面卸载时清理
  window.addEventListener('beforeunload', cleanup);

  // 初始化HLS
  if (Hls.isSupported()) {
    hls = new Hls({
      autoStartLoad: true,
      startPosition: -1,
      abrEwmaDefaultEstimate: 500000 // 初始码率估计
    });
    hls.loadSource(hlsUrl);
    hls.attachMedia(video);
    
    hls.on(Hls.Events.MANIFEST_PARSED, () => {
      // 尝试播放（需用户交互后生效）
      const playPromise = video.play();
      if (playPromise !== undefined) {
        playPromise.catch(error => {
          console.log('自动播放被阻止，需用户交互:', error);
        });
      }
    });

    // 增强错误处理
    hls.on(Hls.Events.ERROR, (event, data) => {
      if (data.fatal) {
        switch(data.type) {
          case Hls.ErrorTypes.NETWORK_ERROR:
            console.warn('网络错误，尝试重连...');
            hls.startLoad();
            break;
          case Hls.ErrorTypes.MEDIA_ERROR:
            console.warn('媒体错误，尝试恢复...');
            hls.recoverMediaError();
            break;
          default:
            console.error('致命错误:', data.details);
            cleanup();
            break;
        }
      }
    });

  } else if (video.canPlayType('application/x-mpegURL')) {
    // Safari原生支持
    video.src = hlsUrl;
    video.addEventListener('loadedmetadata', () => {
      video.play().catch(e => console.log('Safari自动播放限制:', e));
    });
  }

  // 返回清理函数
  return cleanup;
}

// 初始化现有视频
const cleanups = [];
document.querySelectorAll('video').forEach(video => {
  cleanups.push(initHlsPlayer(video));
});

// 动态监听新增视频（可选）
const observer = new MutationObserver(mutations => {
  mutations.forEach(mutation => {
    mutation.addedNodes.forEach(node => {
      if (node.nodeName === 'VIDEO') {
        cleanups.push(initHlsPlayer(node));
      }
    });
  });
});

observer.observe(document.body, {
  childList: true,
  subtree: true
});

// 页面卸载时清理所有实例
window.addEventListener('beforeunload', () => {
  cleanups.forEach(cleanup => cleanup());
  observer.disconnect();
});
