<?php

declare(strict_types=1);

use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Redis\RedisFactory;
use Carbon\Carbon;

if (!function_exists('cfg')) {
    /**
     * 获取配置
     *
     * @param string|null $key 配置键，例如 'jwt.secret'
     * @param mixed $default 默认值
     * @return mixed
     */
    function cfg(?string $key = null, $default = null)
    {
        /** @var ConfigInterface $config */
        $config = ApplicationContext::getContainer()->get(ConfigInterface::class);

        if ($key === null) {
            return $config;
        }

        return $config->get($key, $default);
    }
}

if (! function_exists('redis')) {
    /**
     * 获取 Redis 客户端（连接池名可选，默认 default）。
     *
     * @param string|null $pool 连接池名称，如 'default'
     * @return mixed Redis 客户端实例（PhpRedis 代理）
     */
    function redis(?string $pool = 'default')
    {
        /** @var RedisFactory $factory */
        $factory = ApplicationContext::getContainer()->get(RedisFactory::class);
        return $factory->get($pool ?? 'default');
    }
}


/**
 * 将对象或嵌套对象转换为数组
 * 递归处理对象和数组，将所有对象转换为关联数组
 *
 * @param mixed $array 要转换的对象或数组
 * @return array 转换后的数组
 */
function object_array($array): array
{
    if (is_object($array)) {
        $array = (array) $array;
    }
    if (is_array($array)) {
        foreach ($array as $key => $value) {
            $array[$key] = object_array($value);
        }
    }
    return $array;
}

if (! function_exists('mb_rtrim')) {
    /**
     * 多字节字符串右修剪
     * 从字符串右侧移除指定的字符
     *
     * @param string $string 要修剪的字符串
     * @param string $trim 要移除的字符
     * @param string $encoding 字符编码，默认为UTF-8
     * @return string 修剪后的字符串
     */
    function mb_rtrim(string $string, string $trim, string $encoding = 'UTF-8'): string
    {
        if (empty($string) || empty($trim)) {
            return $string;
        }

        $mask = [];
        $trimLength = mb_strlen($trim, $encoding);
        for ($i = 0; $i < $trimLength; ++$i) {
            $item = mb_substr($trim, $i, 1, $encoding);
            $mask[] = $item;
        }

        $len = mb_strlen($string, $encoding);
        if ($len > 0) {
            $i = $len - 1;
            do {
                $item = mb_substr($string, $i, 1, $encoding);
                if (in_array($item, $mask)) {
                    --$len;
                } else {
                    break;
                }
            } while ($i-- != 0);
        }

        return mb_substr($string, 0, $len, $encoding);
    }
}

if (! function_exists('mb_ltrim')) {
    /**
     * 多字节字符串左修剪
     * 从字符串左侧移除指定的字符
     *
     * @param string $str 要修剪的字符串
     * @param string $char 要移除的字符，默认为空格
     * @param string $encoding 字符编码，默认为UTF-8
     * @return string 修剪后的字符串
     */
    function mb_ltrim(string $str, string $char = ' ', string $encoding = 'UTF-8'): string
    {
        if (empty($str)) {
            return '';
        }
        while (mb_substr($str, 0, 1, $encoding) == $char) {
            $str = mb_substr($str, 1, null, $encoding);
        }
        return $str;
    }
}

if (! function_exists('mb_trim')) {
    /**
     * 多字节字符串两端修剪
     * 从字符串两端移除指定的字符
     *
     * @param string $str 要修剪的字符串
     * @param string $char 要移除的字符，默认为空格
     * @param string $encoding 字符编码，默认为UTF-8
     * @return string 修剪后的字符串
     */
    function mb_trim(string $str, string $char = ' ', string $encoding = 'UTF-8'): string
    {
        return mb_rtrim(mb_ltrim($str, $char, $encoding), $char, $encoding);
    }
}

/**
 * 数字金额转换成中文大写金额
 * 将阿拉伯数字金额转换为中文大写金额，精确到分
 *
 * @param int|float|string $num 要转换的金额数字或字符串
 * @return string 转换后的中文大写金额，如：壹佰贰拾叁元肆角伍分
 */
function toRmb(int|float|string $num): string
{
    $c1 = '零壹贰叁肆伍陆柒捌玖';
    $c2 = '分角元拾佰仟万拾佰仟亿';
    // 精确到分后面就不要了，所以只留两个小数位
    $num = str_replace(',', '', (string) $num);
    $num = round(floatval($num), 2);
    // 将数字转化为整数
    $num = strval($num * 100);
    if (strlen($num) > 10) {
        return '金额太大，请检查';
    }
    $i = 0;
    $c = '';
    while (1) {
        if ($i == 0) {
            // 获取最后一位数字
            $n = substr($num, strlen($num) - 1, 1);
        } else {
            $n = $num % 10;
        }
        // 每次将最后一位数字转化为中文
        $p1 = substr($c1, 3 * $n, 3);
        $p2 = substr($c2, 3 * $i, 3);
        if ($n != '0' || ($n == '0' && ($p2 == '亿' || $p2 == '万' || $p2 == '元'))) {
            $c = $p1 . $p2 . $c;
        } else {
            $c = $p1 . $c;
        }
        $i = $i + 1;
        // 去掉数字最后一位了
        $num = $num / 10;
        $num = (int) $num;
        // 结束循环
        if ($num == 0) {
            break;
        }
    }
    $j = 0;
    $slen = strlen($c);
    while ($j < $slen) {
        // utf8一个汉字相当3个字符
        $m = substr($c, $j, 6);
        // 处理数字中很多0的情况,每次循环去掉一个汉字“零”
        if ($m == '零元' || $m == '零万' || $m == '零亿' || $m == '零零') {
            $left = substr($c, 0, $j);
            $right = substr($c, $j + 3);
            $c = $left . $right;
            $j = $j - 3;
            $slen = $slen - 3;
        }
        $j = $j + 3;
    }
    // 这个是为了去掉类似23.0中最后一个“零”字
    if (substr($c, strlen($c) - 3, 3) == '零') {
        $c = substr($c, 0, strlen($c) - 3);
    }
    // 将处理的汉字加上“整”
    if (empty($c)) {
        return '零元整';
    }
    return $c . '整';
}

/**
 * 阿拉伯数字转中文数字
 * 将阿拉伯数字转换为中文小写数字
 *
 * @param int|string $num 要转换的数字
 * @return string 转换后的中文数字，如：一百二十三
 */
function numToCn(int|string $num): string
{
    if (! is_numeric($num)) {
        return (string) $num;
    }
    $chiNum = ['零', '一', '二', '三', '四', '五', '六', '七', '八', '九'];
    $chiUni = ['', '十', '百', '千', '万', '十', '百', '千', '亿'];

    $num_str = (string) $num;
    $count = strlen($num_str);
    $last_flag = true; //上一个 是否为0
    $zero_flag = true; //是否第一个
    $temp_num = null; //临时数字
    $chiStr = ''; //拼接结果
    if ($count == 2) {//两位数
        $temp_num = $num_str[0];
        $chiStr = $temp_num == 1 ? $chiUni[1] : $chiNum[$temp_num] . $chiUni[1];
        $temp_num = $num_str[1];
        $chiStr .= $temp_num == 0 ? '' : $chiNum[$temp_num];
    } elseif ($count > 2) {
        $index = 0;
        for ($i = $count - 1; $i >= 0; --$i) {
            $temp_num = $num_str[$i];
            if ($temp_num == 0) {
                if (! $zero_flag && ! $last_flag) {
                    $chiStr = $chiNum[$temp_num] . $chiStr;
                    $last_flag = true;
                }

                if ($index == 4 && $temp_num == 0) {
                    $chiStr = '万' . $chiStr;
                }
            } else {
                if ($i == 0 && $temp_num == 1 && $index == 1 && $index == 5) {
                    $chiStr = $chiUni[$index % 9] . $chiStr;
                } else {
                    $chiStr = $chiNum[$temp_num] . $chiUni[$index % 9] . $chiStr;
                }
                $zero_flag = false;
                $last_flag = false;
            }
            ++$index;
        }
    } else {
        $chiStr = $chiNum[$num_str[0]];
    }
    return $chiStr;
}

/**
 * 从字符串中提取数字
 * 使用正则表达式匹配并提取字符串中的数字部分（包括小数点）
 *
 * @param string $string 要提取数字的字符串
 * @return float|string 提取到的数字，如果没有数字则返回空字符串
 */
function findNum(string $string = ''): float|string
{
    $string = trim($string);
    if (empty($string)) {
        return '';
    }
    $num = preg_replace('/[^.0123456789]/s', '', $string);
    return ! empty($num) ? floatval($num) : '';
}

/**
 * 格式化价格
 * 按指定精度格式化价格数值，整数保持为整数，小数按精度四舍五入
 *
 * @param int|float|string $price 要格式化的价格
 * @param int $format 保留的小数位数，默认为2位
 * @return int|float 格式化后的价格
 */
function priceFormat(int|float|string $price, int $format = 2): int|float
{
    $precision = $format; // 保留小数点后面的位数
    return is_int($price) ? intval($price) : (float) sprintf('%.' . $precision . 'f', round(floatval($price), $precision));
}

/**
 * 处理URL别名参数
 * 将URL参数数组添加到别名URL中
 *
 * @param string $alias URL别名
 * @param array $urlPatch URL参数数组
 * @return string 完整的URL字符串
 */
function handelUrlAliasParam(string $alias, array $urlPatch): string
{
    return sprintf($alias . '%s' . str_replace('%', '%%', http_build_query($urlPatch)), '?');
}

/**
 * 二维数组根据指定键去重
 * 根据数组中指定的键值对二维数组进行去重处理
 *
 * @param array $arr 要去重的二维数组
 * @param string $key 用于去重的键名
 * @return array 去重后的数组
 */
function second_array_unique_bykey(array $arr, string $key): array
{
    $tmp_arr = [];
    foreach ($arr as $k => $v) {
        if (in_array($v[$key], $tmp_arr)) {   // 搜索$v[$key]是否在$tmp_arr数组中存在，若存在返回true
            unset($arr[$k]); // 销毁一个变量  如果$tmp_arr中已存在相同的值就删除该值
        } else {
            $tmp_arr[$k] = $v[$key];  // 将不同的值放在该数组中保存
        }
    }
    // ksort($arr); //ksort函数对数组进行排序(保留原键值key)  sort为不保留key值
    return $arr;
}

/**
 * 精确加法
 * 使用BC Math进行高精度加法运算，避免浮点数精度问题
 *
 * @param int|float|string $a 加数
 * @param int|float|string $b 被加数
 * @param int $scale 小数点后保留的位数，默认为2位
 * @return string 计算结果
 */
function math_add(int|float|string $a, int|float|string $b, int $scale = 2): string
{
    return bcadd((string) $a, (string) $b, $scale);
}

/**
 * 精确减法
 * 使用BC Math进行高精度减法运算，避免浮点数精度问题
 *
 * @param int|float|string $a 被减数
 * @param int|float|string $b 减数
 * @param int $scale 小数点后保留的位数，默认为2位
 * @return string 计算结果
 */
function math_sub(int|float|string $a, int|float|string $b, int $scale = 2): string
{
    return bcsub((string) $a, (string) $b, $scale);
}

/**
 * 精确乘法
 * 使用BC Math进行高精度乘法运算，避免浮点数精度问题
 *
 * @param int|float|string $a 乘数
 * @param int|float|string $b 被乘数
 * @param int $scale 小数点后保留的位数，默认为2位
 * @return string 计算结果
 */
function math_mul(int|float|string $a, int|float|string $b, int $scale = 2): string
{
    return bcmul((string) $a, (string) $b, $scale);
}

/**
 * 精确除法
 * 使用BC Math进行高精度除法运算，避免浮点数精度问题
 *
 * @param int|float|string $a 被除数
 * @param int|float|string $b 除数
 * @param int $scale 小数点后保留的位数，默认为2位
 * @return string 计算结果
 */
function math_div(int|float|string $a, int|float|string $b, int $scale = 2): string
{
    return bcdiv((string) $a, (string) $b, $scale);
}

/**
 * 精确求余/取模
 * 使用BC Math进行高精度求余运算
 *
 * @param int|float|string $a 被除数
 * @param int|float|string $b 除数
 * @return string 余数
 */
function math_mod(int|float|string $a, int|float|string $b): string
{
    return bcmod((string) $a, (string) $b);
}

/**
 * 比较数值大小
 * 使用BC Math进行高精度数值比较
 *
 * @param int|float|string $a 第一个数
 * @param int|float|string $b 第二个数
 * @param int $scale 比较的小数位数，默认为5位
 * @return int 返回1表示a>b，返回0表示a=b，返回-1表示a<b
 */
function math_comp(int|float|string $a, int|float|string $b, int $scale = 5): int
{
    return bccomp((string) $a, (string) $b, $scale);
}

/**
 * 比例转化为百分比
 * 将小数比例转换为百分比数值（如0.25转换为25）
 *
 * @param int|float|string $num 要转换的比例数值
 * @return float 转换后的百分比数值，保留2位小数
 */
function getRate(int|float|string $num): float
{
    return round(floatval($num) * 100, 2);
}

/**
 * 检查是否为JSON字符串
 * 判断传入的内容是否为有效的JSON格式字符串
 *
 * @param mixed $content 要检查的内容
 * @return bool true表示是JSON字符串，false表示不是
 */
function isJson(mixed $content): bool
{
    if (is_string($content)) {
        $jObject = json_decode($content);
        return (is_object($jObject) || is_array($jObject)) ? true : false;
    }
    return false;
}


/**
 * 数组百分比转换
 * 将数组中的数值转换为百分比，并确保总和为100%
 *
 * @param array|mixed $array 要转换的数组
 * @return array|false 转换后的百分比数组，如果参数不是数组则返回false
 */
function percentArray(mixed $array): array|false
{
    if (! is_array($array)) {
        return false;
    }
    $total = array_sum($array);
    if ($total > 0) {
        array_walk($array, function (&$item, $key, $prefix) {
            $item = round($item * 100 / $prefix, 2);
        }, $total);
        if ($d = (100 - array_sum($array))) {
            $max_key = array_search(max($array), $array);
            $array[$max_key] = round($array[$max_key] + round($d, 2), 2);
        }
    }
    return $array;
}


/**
 * 查询指定时间范围内的所有日期、月份、季度或年份
 * 根据指定的类型返回时间范围内的所有时间单元及其开始结束日期
 *
 * @param string $startDate 指定开始时间，Y-m-d格式
 * @param string $endDate 指定结束时间，Y-m-d格式
 * @param string $type 类型：day(天)、month(月份)、quarter(季度)、year(年份)
 * @return array|string 成功返回时间数组，失败返回错误信息字符串
 */
function getDateYMD(string $startDate, string $endDate, string $type): array|string
{
    // 验证日期格式
    if (date('Y-m-d', strtotime($startDate)) != $startDate || date('Y-m-d', strtotime($endDate)) != $endDate) {
        return '日期格式不正确';
    }

    $returnData = [];
    $startTimestamp = strtotime($startDate);
    $endTimestamp = strtotime($endDate);
    $i = 0;

    // 处理日期类型
    if ($type == 'day') {
        do {
            $currentDate = date('Y-m-d', strtotime('+' . $i . ' day', $startTimestamp));
            $returnData[] = $currentDate;
            ++$i;
        } while (strtotime($currentDate) < $endTimestamp);

    } elseif ($type == 'month') {
        $tempDate = $startDate;
        do {
            $month = strtotime('first day of +' . $i . ' month', $startTimestamp);
            $temp = [
                'name' => ltrim(date('m', $month), '0'),
                'startDate' => date('Y-m-01', $month),
                'endDate' => date('Y-m-t', $month)
            ];
            $tempDate = $temp['endDate'];
            $returnData[] = $temp;
            ++$i;
        } while (strtotime($tempDate) < $endTimestamp);

    } elseif ($type == 'quarter') {
        $tempDate = $startDate;
        do {
            $quarter = strtotime('first day of +' . $i . ' month', $startTimestamp);
            $q = ceil(date('n', $quarter) / 3);
            $year = (int) date('Y', $quarter);
            $temp = [
                'name' => $year . '第' . $q . '季度',
                'startDate' => date('Y-m-01', mktime(0, 0, 0, $q * 3 - 3 + 1, 1, $year)),
                'endDate' => date('Y-m-t', mktime(23, 59, 59, $q * 3, 1, $year))
            ];
            $tempDate = $temp['endDate'];
            $returnData[] = $temp;
            $i += 3;
        } while (strtotime($tempDate) < $endTimestamp);

    } elseif ($type == 'year') {
        $tempDate = $startDate;
        do {
            $year = strtotime('+' . $i . ' year', $startTimestamp);
            $temp = [
                'name' => date('Y', $year),
                'startDate' => date('Y-01-01', $year),
                'endDate' => date('Y-12-31', $year)
            ];
            $tempDate = $temp['endDate'];
            $returnData[] = $temp;
            ++$i;
        } while (strtotime($tempDate) < $endTimestamp);
    }

    return $returnData;
}

/**
 * 计算账单收缴率
 * 计算已缴账单数占全部账单数的百分比
 *
 * @param int $val1 已缴账单数
 * @param int $val2 全部账单数
 * @return float 收缴率百分比，保留2位小数
 */
function getCollectionRate(int $val1, int $val2): float
{
    // 如果已缴账单数或全部账单数为0，则返回0
    if ($val1 <= 0 || $val2 <= 0) {
        return 0.0;
    }

    $ratio = ($val1 / $val2) * 100;
    return round($ratio, 2);
}

if (! function_exists('isDateValid')) {
    /**
     * 校验日期格式是否合法
     * 检查日期字符串是否符合指定的格式
     *
     * @param string $date 要校验的日期字符串
     * @param array $formats 支持的日期格式数组，默认['Y-m-d', 'Y/m/d', 'Ymd']
     * @return bool true表示日期格式合法，false表示不合法
     */
    function isDateValid(string $date, array $formats = ['Y-m-d', 'Y/m/d', 'Ymd']): bool
    {
        $dateArr = explode(' ', $date);
        [$date] = $dateArr;
        if (empty($date)) {
            return false;
        }
        if (! strtotime($date)) {
            return false;
        }
        $formatDate = date('Y-m-d H:i', strtotime($date)); // 统一转换格式
        $unixTime = strtotime($formatDate);
        if (! $unixTime) { // 无法用strtotime转换，说明日期格式非法
            return false;
        }
        // 校验日期合法性，只要满足其中一个格式就可以
        foreach ($formats as $format) {
            if (date($format, $unixTime) == $date) { // 依旧和原比较
                return true;
            }
        }
        return false;
    }
}

if (! function_exists('createNonceStr')) {
    function createNonceStr($length = 16)
    {
        $characters = '0123456789';
        $orderNumber = '';

        while (true) { // 无限循环，直到找到唯一的订单号为止
            // 生成指定长度的随机字符串
            for ($i = 0; $i < $length; ++$i) {
                $orderNumber .= $characters[mt_rand(0, strlen($characters) - 1)];
            }

//            if (! OrgAppid::query()->where('app_id', $orderNumber)->exists()) {
//                break; // 跳出循环
//            }
            $orderNumber = ''; // 重置订单号
        }
        return $orderNumber;
    }
}

if (! function_exists('getMonthsCovered')) {
    /**
     * 计算两个日期之间跨越的月数
     * 返回从开始日期到结束日期之间包含的月份数量
     *
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @return int 跨越的月数
     */
    function getMonthsCovered(string $startDate, string $endDate): int
    {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $interval = DateInterval::createFromDateString('1 month');
        $period = new DatePeriod($start, $interval, $end);
        return iterator_count($period);
    }
}

if (! function_exists('getDiffDay')) {
    /**
     * 计算两个时间点之间相差多少天
     * 使用Carbon库计算日期差值
     *
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @return int 相差的天数
     */
    function getDiffDay(string $startDate, string $endDate): int
    {
        $datetime1 = Carbon::parse($startDate); // 第一个时间点
        $datetime2 = Carbon::parse($endDate); // 第二个时间点
        return $datetime1->diffInDays($datetime2); // 计算两个时间点相差的天数
    }
}

if (! function_exists('getDiffYear')) {
    /**
     * 计算两个时间点之间相差多少年
     * 使用Carbon库计算年份差值，如果不提供结束日期则默认为当前日期
     *
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期，默认为当前日期
     * @return int 相差的年数
     */
    function getDiffYear(string $startDate, string $endDate = ''): int
    {
        if (empty($endDate)) {
            $endDate = date('Y-m-d');
        }
        $datetime1 = Carbon::parse($startDate); // 第一个时间点
        $datetime2 = Carbon::parse($endDate); // 第二个时间点
        return $datetime1->diffInYears($datetime2); // 计算两个时间点相差的年数
    }
}


if (! function_exists('delHis')) {
    /**
     * 日期去除时分秒
     * 将完整的日期时间格式转换为只保留年月日的格式
     *
     * @param string $date 完整的日期时间字符串
     * @return string 只包含年月日的日期字符串(Y-m-d格式)
     */
    function delHis(string $date): string
    {
        $timestamp = strtotime($date);
        return date('Y-m-d', $timestamp);
    }
}

if (! function_exists('safeGetValue')) {
    /**
     * SQL格式化入参
     * 对输入字符串进行安全处理，防止SQL注入和XSS攻击
     *
     * @param string $string 要处理的字符串
     * @return string 安全处理后的字符串
     */
    function safeGetValue(string $string): string
    {
        return htmlspecialchars(filter_var(mb_trim($string), FILTER_SANITIZE_ADD_SLASHES));
    }
}

if (! function_exists('isDateTime')) {
    /**
     * 是否为正常的时间格式
     * 验证字符串是否可以被解析为有效的日期时间（用于校验前端传值）
     * 若前端传值为"Invalid date"等无效值，可用此方法校验
     *
     * @param string|null $dateTime 要验证的日期时间字符串
     * @return bool true表示是有效的日期时间，false表示无效
     */
    function isDateTime(string|null $dateTime): bool
    {
        if (empty($dateTime)) {
            return false;
        }
        $ret = strtotime($dateTime);
        return $ret !== false && $ret != -1;
    }
}
