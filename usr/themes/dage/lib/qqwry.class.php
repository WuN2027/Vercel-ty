<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 纯真IP数据库解析类
 */
class QQWry
{
    private $fp = null;
    private $first = 0;
    private $last = 0;
    private $total = 0;
    private $valid = true;
    private static $gbkMap = null;

    public function __construct($datFile = '')
    {
        try {
            if (empty($datFile)) {
                $datFile = dirname(__FILE__) . '/qqwry.dat';
            }
            if (!file_exists($datFile) || !is_readable($datFile)) {
                return;
            }
            $this->fp = @fopen($datFile, 'rb');
            if (!$this->fp) {
                return;
            }
            $firstBytes = @fread($this->fp, 4);
            $lastBytes  = @fread($this->fp, 4);
            if (!is_string($firstBytes) || strlen($firstBytes) < 4 || !is_string($lastBytes) || strlen($lastBytes) < 4) {
                @fclose($this->fp);
                $this->fp = null;
                return;
            }
            $this->first = $this->bytesToUInt($firstBytes);
            $this->last  = $this->bytesToUInt($lastBytes);
            $fileSize = @filesize($datFile);
            if ($fileSize === false || $fileSize < 8) {
                @fclose($this->fp);
                $this->fp = null;
                return;
            }
            if ($this->first > $fileSize || $this->last > $fileSize || $this->first > $this->last) {
                @fclose($this->fp);
                $this->fp = null;
                return;
            }
            $this->total = floor(($this->last - $this->first) / 7) + 1;
        } catch (Exception $e) {
            $this->valid = false;
        } catch (Error $e) {
            $this->valid = false;
        }
    }

    public function __destruct()
    {
        if ($this->fp) {
            @fclose($this->fp);
        }
    }

    public function getProvince($ip)
    {
        try {
            $location = $this->getLocation($ip);
            if (empty($location)) {
                return '';
            }
            return $this->extractProvince($location);
        } catch (Exception $e) {
            return '';
        } catch (Error $e) {
            return '';
        }
    }

    private function getLocation($ip)
    {
        if (!$this->fp || $this->total <= 0) {
            return '';
        }
        $nip = ip2long($ip);
        if ($nip === false || $nip === -1) {
            return '';
        }
        $lip2 = $this->toUInt32($nip);
        $lo = 0;
        $hi = (int)($this->total - 1);

        while ($lo <= $hi) {
            $mid = (int)(($lo + $hi) / 2);
            $pos = (int)($this->first + $mid * 7);
            if ($pos + 8 > $this->last) {
                break;
            }
            @fseek($this->fp, $pos);
            $begin = $this->toUInt32($this->readLong4());
            $end   = $this->toUInt32($this->readLong4());
            if ($lip2 < $begin) {
                $hi = $mid - 1;
            } elseif ($lip2 > $end) {
                $lo = $mid + 1;
            } else {
                $offset = $this->readLong3();
                if ($offset <= 0) {
                    return '';
                }
                @fseek($this->fp, $offset);
                return $this->readArea();
            }
        }
        return '';
    }

    private function extractProvince($location)
    {
        if (empty($location)) {
            return '';
        }
        $location = preg_replace('/^中国\s*/u', '', $location);
        if (preg_match('/^(.+?省)/u', $location, $m)) {
            return $m[1];
        }
        $municipalities = array('北京', '上海', '天津', '重庆');
        foreach ($municipalities as $city) {
            if (strpos($location, $city) === 0) {
                return $city;
            }
        }
        $provinces = array(
            '香港', '澳门', '台湾',
            '内蒙古', '广西', '西藏', '宁夏', '新疆',
            '河北', '山西', '辽宁', '吉林', '黑龙江',
            '江苏', '浙江', '安徽', '福建', '江西', '山东',
            '河南', '湖北', '湖南', '广东', '海南', '四川',
            '贵州', '云南', '陕西', '甘肃', '青海',
        );
        foreach ($provinces as $prov) {
            if (strpos($location, $prov) === 0) {
                return $prov;
            }
        }
        if (preg_match('/^[^\s]+/u', $location, $m)) {
            return $m[0];
        }
        return '';
    }

    private function readLong4()
    {
        $d = @fread($this->fp, 4);
        if (!is_string($d) || strlen($d) < 4) {
            return 0;
        }
        return $this->bytesToUInt($d);
    }

    private function readLong3()
    {
        $d = @fread($this->fp, 3);
        if (!is_string($d) || strlen($d) < 3) {
            return 0;
        }
        $arr = unpack('V', $d . "\x00");
        return isset($arr[1]) ? $arr[1] : 0;
    }

    private function readArea()
    {
        $modeByte = @fread($this->fp, 1);
        if (!is_string($modeByte) || $modeByte === '') {
            return '';
        }
        $mode = ord($modeByte);

        if ($mode === 1) {
            $offset = $this->readLong3();
            if ($offset <= 0) {
                return '';
            }
            @fseek($this->fp, $offset);
            $mode2Byte = @fread($this->fp, 1);
            if (!is_string($mode2Byte) || $mode2Byte === '') {
                return '';
            }
            $mode2 = ord($mode2Byte);
            if ($mode2 === 2) {
                $countryOffset = $this->readLong3();
                if ($countryOffset <= 0) {
                    return '';
                }
                @fseek($this->fp, $countryOffset);
                $country = $this->readString();
                @fseek($this->fp, $offset + 4);
                $area = $this->readString();
            } else {
                @fseek($this->fp, $offset);
                $country = $this->readString();
                $area = $this->readString();
            }
        } elseif ($mode === 2) {
            $offset = $this->readLong3();
            if ($offset <= 0) {
                return '';
            }
            @fseek($this->fp, $offset);
            $country = $this->readString();
            $area = $this->readString();
        } else {
            $country = $this->gbkToUtf8(chr($mode) . $this->readRawString());
            $area = $this->readString();
        }

        $result = trim($country);
        $area = trim($area);
        if (!empty($area) && $area !== 'CZ88.NET') {
            $result .= ' ' . $area;
        }
        return trim($result);
    }

    private function readString()
    {
        $str = '';
        while (true) {
            $c = @fread($this->fp, 1);
            if (!is_string($c) || $c === "\x00" || $c === '') {
                break;
            }
            $str .= $c;
        }
        return $this->gbkToUtf8($str);
    }

    private function readRawString()
    {
        $str = '';
        while (true) {
            $c = @fread($this->fp, 1);
            if (!is_string($c) || $c === "\x00" || $c === '') {
                break;
            }
            $str .= $c;
        }
        return $str;
    }

    private function gbkToUtf8($str)
    {
        if (empty($str)) {
            return '';
        }
        // GBK 转 UTF
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($str, 'UTF-8', 'GBK');
        }
        if (function_exists('iconv')) {
            $converted = @iconv('GBK', 'UTF-8//IGNORE', $str);
            if ($converted !== false) {
                return $converted;
            }
        }
        // 解码
        if (self::$gbkMap === null) {
            $mapFile = dirname(__FILE__) . '/gbk_utf8_map.php';
            if (file_exists($mapFile)) {
                self::$gbkMap = require $mapFile;
            }
            if (!is_array(self::$gbkMap)) {
                self::$gbkMap = [];
            }
        }

        $utf8 = '';
        $len = strlen($str);
        $i = 0;
        while ($i < $len) {
            $c = ord($str[$i]);
            if ($c < 0x80) {
                $utf8 .= chr($c);
                $i++;
            } elseif ($c >= 0x81 && $c <= 0xFE && $i + 1 < $len) {
                $c2 = ord($str[$i + 1]);
                if ($c2 >= 0x40 && $c2 <= 0xFE && $c2 !== 0x7F) {
                    $gb = chr($c) . chr($c2);
                    if (isset(self::$gbkMap[$gb])) {
                        $utf8 .= self::$gbkMap[$gb];
                    } else {
                        $utf8 .= chr(0xEF) . chr(0xBF) . chr(0xBD);
                    }
                    $i += 2;
                } else {
                    $utf8 .= chr(0xEF) . chr(0xBF) . chr(0xBD);
                    $i++;
                }
            } else {
                $utf8 .= chr(0xEF) . chr(0xBF) . chr(0xBD);
                $i++;
            }
        }
        return $utf8;
    }

    private function unicodeToUtf8($code)
    {
        if ($code <= 0x7F) {
            return chr($code);
        } elseif ($code <= 0x7FF) {
            return chr(0xC0 | ($code >> 6)) . chr(0x80 | ($code & 0x3F));
        } elseif ($code <= 0xFFFF) {
            return chr(0xE0 | ($code >> 12)) . chr(0x80 | (($code >> 6) & 0x3F)) . chr(0x80 | ($code & 0x3F));
        } else {
            return chr(0xEF) . chr(0xBF) . chr(0xBD);
        }
    }

    private function bytesToUInt($bytes)
    {
        if (!is_string($bytes) || strlen($bytes) < 4) {
            return 0;
        }
        $arr = unpack('V', $bytes);
        return isset($arr[1]) ? $arr[1] : 0;
    }

    private function toUInt32($val)
    {
        if ($val < 0) {
            return $val + 4294967296;
        }
        return $val;
    }
}
