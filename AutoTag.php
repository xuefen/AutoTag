<?php

/**
 * 自动获取标签，目前只支持utf-8编码,标签依据是词语在文章中出现次数最多的词
 * 其中标题的权重值1为和内容相当，值2为内容的2倍，值3为...  不支持小数，负数
 * 
 * @author Xuefen.Tong
 * @date 2012-09-25
 * @version 1.0
 */
class AutoTag {

    /**
     * 统计字符串中词语的个数数组
     *
     * @param string $str
     * @return array
     * @author Xuefen.Tong
     * @date 2012-09-25
     */
    public static function countWords($str) {
        $words_arr = array();
        $cn_words = self::getCnWords();
        $tmp_words = array();
        $i = 0;
        $str_length = strlen($str); // .字符串的字节数
        while (true) {
            $temp_str = substr($str, $i, 1);
            if ($temp_str === false) {
                break;
            }
            $asc_ord = Ord($temp_str); // 得到字符串中第$i位字符的ascii码
            $word = '';
            if ($asc_ord >= 192) {// 如果ASCII位高与192，
                if ($asc_ord >= 224) { // 如果ASCII位高于224，
                    $word = substr($str, $i, 3); // 根据UTF-8编码规范，将3个连续的字符计为单个字符
                    $i = $i + 3; // 实际Byte计为3
                } else {
                    $word = substr($str, $i, 2); // 根据UTF-8编码规范，将2个连续的字符计为单个字符
                    $i = $i + 2; // 实际Byte计为2
                }
                self::pop_shift($tmp_words, $word);
            } else { // 其他情况下，包括小写字母和半角标点符号，
                $i = $i + 1; //实际的Byte数计1个
                self::countLast($tmp_words, $words_arr, $cn_words);
                $tmp_words = array();
                continue;
            }

            //如果临时数组中的数据有四个字，则可以开始对比是否有这个词语
            $tmp_count = count($tmp_words);
            if ($tmp_count == 4) {
                //如果存在四字词语，则忽略三字词语，如果存在三字成语则忽略二字词语
                if (($words = implode("", $tmp_words))//四字可能词语
                        && isset($cn_words[$words])) {
                    self::plusOne($words, $words_arr);
                    $tmp_words = array();
                } elseif (($words = $tmp_words[0] . $tmp_words[1] . $tmp_words[2])//三字可能词语
                        && isset($cn_words[$words])) {
                    self::plusOne($words, $words_arr);
                    $tmp_words = array($tmp_words[3]);
                } elseif (($words = $tmp_words[0] . $tmp_words[1])//两字可能词语
                        && isset($cn_words[$words])) {
                    self::plusOne($words, $words_arr);
                    $tmp_words = array($tmp_words[2], $tmp_words[3]);
                }
            }
            if ($i >= $str_length) {
                self::countLast($tmp_words, $words_arr, $cn_words);
                break;
            }
        }

        return $words_arr;
    }

    /**
     * 根据标题和内容取得文章的标签
     *
     * @param string $title 文章标题
     * @param string $content 文章内容
     * @param int $num 返回的标签的数量  @默认 4
     * @param int $weight标签占用比重 @默认 2
     * @author Xuefen.Tong
     * @date 2012-09-25
     * @return array
     */
    public static function getTags($title, $content, $k = 5, $weight = 2) {
        //标题的权重值，默认为内容的两倍
        $weight_title = "";
        for ($i = 0; $i < $weight; $i++) {
            $weight_title .= $title . "|";
        }
        //默认标题占用比重是内容的两倍
        return self::topK(self::countWords($weight_title . "$content"), $k);
    }

    /**
     * 取得前K名的键值
     * 
     * @param array $words
     * @param int $k
     * @author Xuefen.Tong
     * @date 2012-09-25
     * @return array
     */
    private static function topK($words, $k) {
        arsort($words, SORT_NUMERIC);
        $tags = array();
        $i = 0;
        $last_count = 0;
        foreach ($words as $word => $count) {
            if ($i < $k) {
                $tags[] = $word;
                $last_count = $count;
            } elseif ($last_count == $count) {//如果出现次数和原来的一样，则并列排名返回
                $tags[] = $word;
            } else {
                break;
            }
            $i++;
        }
        return $tags;
    }

    /**
     * 返回的数组中键值为$words的值加1
     *
     * @param string $words
     * @param array $words_arr
     * @author Xuefen.Tong
     * @date 2012-09-25
     */
    private static function plusOne($words, &$words_arr) {
        $words_arr[$words] = (isset($words_arr[$words]) ? $words_arr[$words] : 0) + 1;
    }

    /**
     * 计算最后几个单词所组成的字组中可能的词语
     *
     * @param array $tmp_words
     * @param array &$words_arr
     * @param array $cn_words
     * @author Xuefen.Tong
     * @date 2012-09-25
     */
    private static function countLast($tmp_words, &$words_arr, $cn_words) {
        $count = count($tmp_words);
        $from_prev = false;
        //判断遗漏
        switch ($count) {
            case 4:
                //三字可能词语
                if (($words = $tmp_words[1] . $tmp_words[2] . $tmp_words[3]) && isset($cn_words[$words])) {
                    self::plusOne($words, $words_arr);
                    break;
                } else {
                    //继续走count为3的case
                    $count = 3;
                    $tmp_words = array($tmp_words[1], $tmp_words[2], $tmp_words[3]);
                    $from_prev = true;
                }
            case 3:
                if ((!$from_prev) && ($words = $tmp_words[0] . $tmp_words[1] . $tmp_words[2])
                        && isset($cn_words[$words])) {//三字可能词语
                    self::plusOne($words, $words_arr);
                } elseif (($words = $tmp_words[0] . $tmp_words[1]) && isset($cn_words[$words])) { //二字0，1可能词语
                    self::plusOne($words, $words_arr);
                } elseif (($words = $tmp_words[1] . $tmp_words[2]) && isset($cn_words[$words])) { //二字1，2可能词语
                    self::plusOne($words, $words_arr);
                }
                break;
            case 2:
                if (($words = $tmp_words[0] . $tmp_words[1]) && isset($cn_words[$words])) {//二字1，2可能词语
                    self::plusOne($words, $words_arr);
                }
                break;
            default:;
        }
    }

    /**
     * 取得所有的中文词组的数组
     * 
     * @return array()
     * @author Xuefen.Tong
     * @date 2012-09-25
     */
    private static function getCnWords() {
        return include (dirname(__FILE__) . '/phpdict/cn_words.php');
    }

    /**
     * 往数组中插入一个元素，并且弹出第一个元素
     *
     * @param array $words
     * @param string $last
     * @return array
     * @author Xuefen.Tong
     * @date 2012-09-25
     */
    private static function pop_shift(&$words, $last) {
        if (count($words) == 4)
            array_shift($words);
        array_push($words, $last);
    }

}