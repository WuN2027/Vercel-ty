/* 代码块处理：语法高亮 + 左侧行号 + 复制按钮 */
!function() {
  // ========== HTML 安全工具 ==========

  // 判断内容是否已经被 HTML 转义过
  function isEncoded(html) {
    return /&(?:lt|gt|amp|quot|#\d+|#x[\da-fA-F]+);/.test(html);
  }

  // 已转义文本的正则别名
  var LT = '&lt;', GT = '&gt;', AMP = '&amp;', QUOT = '&quot;';
  var LT_RE = '&(?:lt|#60);', GT_RE = '&(?:gt|#62);', AMP_RE = '&(?:amp|#38);';

  // 未转义文本用于模式匹配
  function getPatternSource() {
    return {
      lt: '<', gt: '>', amp: '&', quot: '"',
      ltOrGt: '[<>]', ws: '[\\s\\S]'
    };
  }

  // ========== 语法高亮规则（纯文本匹配） ==========

  // URL 统一高亮（放在各语言规则最前面，避免被注释规则拆开）
  var patternURL = {type: 'url', re: /https?:\/\/[^\s'"]+/};

  // PHP 规则
  var patternsPHP = [
    // PHP 标签（放在最前面优先匹配）
    {type: 'tag',       re: /<\?(?:php|=)?|\?>/},
    // 注释（包含未闭合的多行注释开头）
    {type: 'comment',   re: /\/\/.*|\/\*[\s\S]*?\*\/|\/\*.*|#(?!\[)[^\n]*/},
    {type: 'string',    re: /`(?:[^`\\]|\\.)*`|"(?:[^"\\]|\\.)*"|'(?:[^'\\]|\\.)*'/},
    {type: 'variable',  re: /\$[a-zA-Z_]\w*/},
    {type: 'keyword',   re: /\b(?:function|return|if|else|elseif|for|foreach|while|class|public|static|var|let|const|echo|print|new|this|self|parent|true|false|null|undefined|async|await|import|export|default|extends|super|try|catch|throw|break|continue|case|switch|do|in|of|typeof|instanceof|delete|void|yield|namespace|use|trait|interface|implements|final|abstract|protected|private|global|goto|declare|require|include|require_once|include_once|isset|unset|array|list|callable|mixed|int|float|bool|string|object|iterable|enum|match|fn|as|or|and|xor|die|exit|eval)\b/},
    {type: 'builtin',   re: /\b(?:strlen|substr|strpos|str_replace|strtolower|strtoupper|trim|explode|implode|in_array|array_push|array_merge|json_encode|json_decode|preg_match|preg_replace|preg_split|strtotime|date|time|mktime|count|sort|array_keys|array_values|empty|var_dump|print_r|htmlspecialchars|nl2br|basename|dirname|file_exists|is_array|is_string|is_numeric|intval|floatval|strval|abs|max|min|round|floor|ceil|sprintf|printf|pathinfo|parse_url|http_build_query|curl_init|curl_exec|curl_close|mysqli_connect|mysqli_query|mysqli_fetch_assoc|header|setcookie|session_start|session_destroy|md5|sha1|password_hash|password_verify|base64_encode|base64_decode|chmod|mkdir|unlink|file_get_contents|file_put_contents|fopen|fread|fwrite|fclose|sizeof|compact|extract)\b/},
    {type: 'number',    re: /\b(?:0x[\da-fA-F]+|\d+(?:\.\d+)?(?:e[+-]?\d+)?)\b/},
    {type: 'function',  re: /\b[a-zA-Z_]\w*(?=\s*\()/},
    {type: 'operator',  re: /=>|[+\-*\/%]=|===|!==|==|!=|<=|>=|<<|>>|&&|\|\||[+\-*\/%=<>!&|^~?:]/},
  ];

  // CSS 规则
  var patternsCSS = [
    {type: 'comment',   re: /\/\*[\s\S]*?\*\//},
    {type: 'string',    re: /"(?:[^"\\]|\\.)*"|'(?:[^'\\]|\\.)*'/},
    {type: 'important', re: /!important/},
    {type: 'selector',  re: /[.#@][a-zA-Z_-][\w-]*(?=[\s,{])/},
    {type: 'property',  re: /\b[a-z-]+(?=\s*:)/},
    {type: 'number',    re: /\b\d+(?:\.\d+)?(?:px|em|rem|%|s|ms|vh|vw|ex|ch|cm|mm|in|pt|pc|deg|rad|grad|turn|Hz|kHz|dpi|dpcm|dppx)?\b/},
    {type: 'keyword',   re: /@(?:import|media|keyframes|font-face|charset|namespace|supports|layer)/},
    {type: 'color',     re: /#[0-9a-fA-F]{3,8}\b/},
    {type: 'operator',  re: /[{}();,:>+~*]/},
  ];

  // HTML 规则
  var patternsHTML = [
    {type: 'comment',   re: /<!--[\s\S]*?-->/},
    {type: 'doctype',   re: /<!DOCTYPE[^>]*>/i},
    {type: 'tag',       re: /<\/?[a-zA-Z][\w-]*(?:\s[^>]*)?>/},
    {type: 'string',    re: /"(?:[^"\\]|\\.)*"|'(?:[^'\\]|\\.)*'/},
    {type: 'attribute', re: /\b[a-zA-Z_][\w-]*(?=\s*=\s*["'])/},
  ];

  // JavaScript 规则
  var patternsJS = [
    {type: 'comment',   re: /\/\/.*|\/\*[\s\S]*?\*\//},
    {type: 'string',    re: /`(?:[^`\\]|\\.)*`|"(?:[^"\\]|\\.)*"|'(?:[^'\\]|\\.)*'/},
    {type: 'keyword',   re: /\b(?:function|return|if|else|for|while|class|public|static|var|let|const|new|this|true|false|null|undefined|async|await|import|export|default|extends|super|try|catch|throw|break|continue|case|switch|do|in|of|typeof|instanceof|delete|void|yield|from|as|with|finally|debugger|arguments)\b/},
    {type: 'builtin',   re: /\b(?:console|document|window|Math|Array|Object|String|Number|Boolean|Date|RegExp|Error|Promise|JSON|parseInt|parseFloat|isNaN|isFinite|encodeURI|decodeURI|encodeURIComponent|decodeURIComponent|setTimeout|setInterval|clearTimeout|clearInterval|requestAnimationFrame|fetch|alert|confirm|prompt|addEventListener|removeEventListener|querySelector|querySelectorAll|getElementById|getElementsByClassName|getElementsByTagName|createElement|appendChild|removeChild|setAttribute|getAttribute|classList|style|textContent|innerHTML|value|length|push|pop|shift|unshift|splice|slice|concat|join|map|filter|reduce|forEach|find|indexOf|includes|keys|values|entries|assign|freeze|define|require|module|exports|process|Buffer|__dirname|__filename)\b/},
    {type: 'number',    re: /\b(?:0x[\da-fA-F]+|\d+(?:\.\d+)?(?:e[+-]?\d+)?)\b/},
    {type: 'function',  re: /\b[a-zA-Z_$]\w*(?=\s*\()/},
    {type: 'operator',  re: /=>|[+\-*\/%]=|===|!==|==|!=|<=|>=|<<|>>|&&|\|\||[+\-*\/%=<>!&|^~?:]/},
  ];

  // Shell/Bash 规则
  var patternsBash = [
    {type: 'comment',   re: /#(?!\[)[^\n]*/},
    {type: 'string',    re: /"(?:[^"\\]|\\.)*"|'(?:[^'\\]|\\.)*'/},
    {type: 'keyword',   re: /\b(?:if|then|else|elif|fi|for|while|do|done|case|esac|in|function|return|exit|local|export|source|alias|unset|readonly|set|shift|echo|printf|read|cd|ls|cp|mv|rm|mkdir|rmdir|chmod|chown|cat|grep|sed|awk|find|sort|uniq|wc|head|tail|cut|tr|tee|xargs|curl|wget|tar|gzip|ssh|scp|rsync|apt|yum|npm|pip|git|docker|make|bash|sh|true|false|test)\b/},
    {type: 'variable',  re: /\$[a-zA-Z_]\w*|\$\{[^}]+\}/},
    {type: 'number',    re: /\b\d+(?:\.\d+)?\b/},
    {type: 'operator',  re: /[|&;<>!`=+\-*\/]/},
  ];

  // Nginx 规则
  var patternsNginx = [
    {type: 'comment',   re: /#(?!\[)[^\n]*/},
    {type: 'string',    re: /"(?:[^"\\]|\\.)*"|'(?:[^'\\]|\\.)*'/},
    {type: 'keyword',   re: /\b(?:server|location|listen|server_name|root|index|rewrite|return|if|set|proxy_pass|proxy_set_header|try_files|alias|include|error_page|ssl_certificate|ssl_certificate_key|ssl_protocols|access_log|error_log|expires|add_header|gzip|gzip_types|fastcgi_pass|fastcgi_param|client_max_body_size|sendfile|tcp_nopush|tcp_nodelay|keepalive_timeout|autoindex|deny|allow|limit_conn|limit_req|upstream|map|log_format|on|off|permanent|redirect|last|break)\b/},
    {type: 'number',    re: /\b\d+(?:\.\d+)?(?:[smhd]|ms)?\b/},
    {type: 'variable',  re: /\$[a-zA-Z_]\w*/},
    {type: 'operator',  re: /[{}();|&;<>~]/},
  ];

  // SQL 规则
  var patternsSQL = [
    {type: 'comment',   re: /--(?!\[)[^\n]*|\/\*[\s\S]*?\*\//},
    {type: 'string',    re: /'(?:[^'\\]|\\.)*'|"(?:[^"\\]|\\.)*"/},
    {type: 'keyword',   re: /\b(?:SELECT|FROM|WHERE|INSERT|INTO|VALUES|UPDATE|SET|DELETE|CREATE|TABLE|ALTER|DROP|INDEX|UNIQUE|PRIMARY|KEY|FOREIGN|REFERENCES|ON|AND|OR|NOT|IN|EXISTS|LIKE|BETWEEN|IS|NULL|AS|JOIN|LEFT|RIGHT|INNER|OUTER|FULL|CROSS|GROUP|BY|ORDER|ASC|DESC|HAVING|LIMIT|OFFSET|UNION|ALL|DISTINCT|CASE|WHEN|THEN|ELSE|END|COUNT|SUM|AVG|MAX|MIN|IFNULL|COALESCE|CAST|CONVERT|CONCAT|SUBSTRING|TRIM|LENGTH|UPPER|LOWER|NOW|DATE|YEAR|MONTH|DAY|INT|VARCHAR|TEXT|FLOAT|DOUBLE|DECIMAL|BOOLEAN|SERIAL|AUTO_INCREMENT|ENGINE|CHARSET|COLLATE|GRANT|REVOKE|TRUNCATE|BEGIN|COMMIT|ROLLBACK|TRANSACTION|VIEW|TRIGGER|PROCEDURE|FUNCTION|RETURN|DECLARE|DEFAULT|TRUE|FALSE|SHOW|DESCRIBE|EXPLAIN|USE|DATABASE|SCHEMA)\b/i},
    {type: 'number',    re: /\b\d+(?:\.\d+)?\b/},
    {type: 'function',  re: /\b[a-zA-Z_]\w*(?=\s*\()/},
    {type: 'operator',  re: /[<>!=]=?|[+\-*\/%&|^~]/},
  ];

  // 通用规则
  var patternsGeneric = [
    {type: 'comment',   re: /\/\/.*|\/\*[\s\S]*?\*\/|#(?!\[)[^\n]*/},
    {type: 'string',    re: /`(?:[^`\\]|\\.)*`|"(?:[^"\\]|\\.)*"|'(?:[^'\\]|\\.)*'/},
    {type: 'keyword',   re: /\b(?:function|return|if|else|for|while|class|public|static|var|let|const|echo|print|new|this|true|false|null|undefined|async|await|import|export|default|extends|super|try|catch|throw|break|continue|case|switch|do|in|of|typeof|instanceof|delete|void|yield|namespace|use|trait|interface|implements|final|abstract|protected|private)\b/},
    {type: 'variable',  re: /\$[a-zA-Z_]\w*/},
    {type: 'number',    re: /\b(?:0x[\da-fA-F]+|\d+(?:\.\d+)?)\b/},
    {type: 'function',  re: /\b[a-zA-Z_]\w*(?=\s*\()/},
    {type: 'operator',  re: /=>|[+\-*\/%]=|===|!==|==|!=|<=|>=|<<|>>|&&|\|\||[+\-*\/%=<>!&|^~?:]/},
  ];

  function getPatterns(lang) {
    var l = (lang || '').toLowerCase();
    var patterns;
    if (l === 'php') patterns = patternsPHP;
    else if (l === 'css' || l === 'scss' || l === 'sass' || l === 'less') patterns = patternsCSS;
    else if (l === 'html' || l === 'xml' || l === 'markup') patterns = patternsHTML;
    else if (l === 'javascript' || l === 'js') patterns = patternsJS;
    else if (l === 'bash' || l === 'sh' || l === 'shell' || l === 'zsh') patterns = patternsBash;
    else if (l === 'nginx' || l === 'conf') patterns = patternsNginx;
    else if (l === 'sql' || l === 'mysql' || l === 'sqlite') patterns = patternsSQL;
    else patterns = patternsGeneric;
    return [patternURL].concat(patterns);
  }

  // ========== Tokenizer（纯文本模式） ==========
  function tokenize(text, patterns) {
    var tokens = [];
    var pos = 0;
    var safety = 0;
    var maxIter = text.length * 3 + 100;
    while (pos < text.length && safety < maxIter) {
      safety++;
      var matched = false;
      for (var i = 0; i < patterns.length; i++) {
        var p = patterns[i];
        var re = new RegExp(p.re.source, 'y');
        re.lastIndex = pos;
        var m = re.exec(text);
        if (m && m[0].length > 0) {
          tokens.push({type: p.type, value: m[0]});
          pos += m[0].length;
          matched = true;
          break;
        }
      }
      if (!matched) {
        tokens.push({type: 'text', value: text[pos]});
        pos++;
      }
    }
    return tokens;
  }

  // 渲染 token 为 HTML（对纯文本做 HTML 转义）
  function encodeHtml(str) {
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function renderTokens(tokens) {
    var out = '';
    for (var i = 0; i < tokens.length; i++) {
      var t = tokens[i];
      var escaped = encodeHtml(t.value);
      if (t.type === 'text') {
        out += escaped;
      } else {
        out += '<span class="token ' + t.type + '">' + escaped + '</span>';
      }
    }
    return out;
  }

  function highlightLine(text, lang) {
    if (!text) return '';
    var patterns = getPatterns(lang);
    var tokens = tokenize(text, patterns);
    return renderTokens(tokens);
  }

  // ========== 语言检测（同时检查 code 和 pre 元素的 class） ==========
  function detectLangFromEl(el) {
    var knownLangs = ['php','css','scss','sass','less','html','xml','markup','javascript','js','bash','sh','shell','zsh','nginx','conf','sql','mysql','sqlite','python','py','java','go','rust','rs','json','yaml','yml','c','cpp','cxx','csharp','cs','typescript','ts','vue','react','jsx','tsx','markdown','md'];
    if (!el || !el.className) return '';
    var classes = el.className.split(/\s+/);
    for (var i = 0; i < classes.length; i++) {
      var c = classes[i].toLowerCase();
      if (c.indexOf('language-') === 0) {
        return c.replace('language-', '');
      }
      if (knownLangs.indexOf(c) !== -1) {
        return c;
      }
    }
    return '';
  }

  function detectLang(code, pre) {
    // 优先从 PHP 注入的 data-lang 属性读取
    var dataLang = code.getAttribute('data-lang');
    if (dataLang !== null && dataLang !== '') return dataLang.toLowerCase();
    // 再检查 code 和 pre 元素的 class
    var lang = detectLangFromEl(code);
    if (lang) return lang;
    if (pre) {
      lang = detectLangFromEl(pre);
      if (lang) return lang;
    }
    return '';
  }

  // ========== 行号 + 高亮 + 复制 ==========
  function addLineNumbers() {
    var codes = document.querySelectorAll('pre code');
    codes.forEach(function(code) {
      // 关键：用 textContent 获取浏览器解码后的纯文本
      var rawText = code.textContent || '';
      var lines = rawText.split('\n');
      if (lines.length > 1 && lines[lines.length - 1] === '') {
        lines.pop();
      }

      // 检测语言并保存到 data 属性
      var pre = code.parentElement;
      var lang = detectLang(code, pre);
      if (lang) {
        code.setAttribute('data-lang', lang);
      }

      var html = '';
      for (var i = 0; i < lines.length; i++) {
        var lineNum = i + 1;
        var highlighted = highlightLine(lines[i], lang);
        html += '<span class="code-line"><span class="line-number">' + lineNum + '</span><span class="line-content">' + highlighted + '</span></span>';
      }

      code.innerHTML = html;
      code.classList.add('has-line-numbers');
    });
  }

  function addCopyButton() {
    var pres = document.querySelectorAll('pre');
    pres.forEach(function(pre) {
      var code = pre.querySelector('code');
      if (!code) return;
      if (pre.querySelector('.code-toolbar')) return;

      // 从 data 属性读取语言，或重新检测（同时检查 pre 元素）
      var lang = code.getAttribute('data-lang') || detectLang(code, pre) || '';

      // 工具栏容器
      var toolbar = document.createElement('div');
      toolbar.className = 'code-toolbar';

      // 复制按钮
      var btn = document.createElement('div');
      btn.className = 'code-copy-btn';
      btn.textContent = '复制';
      toolbar.appendChild(btn);

      // 语言标签
      var langTag = document.createElement('div');
      langTag.className = 'code-lang-tag';
      langTag.textContent = lang ? lang.toUpperCase() : 'CODE';
      toolbar.appendChild(langTag);

      pre.appendChild(toolbar);

      btn.addEventListener('click', function() {
        var contents = code.querySelectorAll('.line-content');
        var text = Array.from(contents).map(function(span) {
          return span.textContent;
        }).join('\n');
        navigator.clipboard.writeText(text).then(function() {
          btn.textContent = '复制成功';
          setTimeout(function() { btn.textContent = '复制'; }, 3000);
        });
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function() {
    addLineNumbers();
    addCopyButton();
  });
}();
