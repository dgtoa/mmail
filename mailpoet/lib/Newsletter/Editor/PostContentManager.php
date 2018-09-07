<?php

namespace MailPoet\Newsletter\Editor;

use MailPoet\WP\Hooks;

if(!defined('ABSPATH')) exit;

class PostContentManager {
  const WP_POST_CLASS = 'mailpoet_wp_post';

  public $max_excerpt_length = 60;

  function __construct() {
    $this->max_excerpt_length = Hooks::applyFilters('mailpoet_newsletter_post_excerpt_length', $this->max_excerpt_length);
  }

function cut_str2($str, $len, $suffix="…", $mb_strimwidth=false)
{
    $str = str_replace ( array("&quot;", "&apos;", "&amp;", "&lt;", "&gt;") , array("\"", "\'", "&", "<", ">"), $str);
    //if로 mb_strimwidth의 불린값을 받아와서 사용할껀지 말껀지 처리
    if ($mb_strimwidth == false) {
        $arr_str = preg_split("//u", $str, -1, PREG_SPLIT_NO_EMPTY);
        $str_len = count($arr_str);

        if ($str_len >= $len) {
            $slice_str = array_slice($arr_str, 0, $len);
            $str = join("", $slice_str);

            return $str . ($str_len > $len ? $suffix : '');
        } else {
            $str = join("", $arr_str);
            return $str;
        }
    } else {
        //mb_strimwidth를 사용할 경우 아래처럼 처리

        //글자수를 줄이려는 문자열이 인코딩이 어떻게 되는지 판단
        $str_encoding = mb_detect_encoding($str, 'UTF-8, EUC-KR');
        //mb_strimwidth 내장함수를 이용하여 글씨를 자름
        $result_str = mb_strimwidth($str, 0, $len, $suffix, $str_encoding);
        return $result_str;
    }
}

  function getContent($post, $displayType) {
    if($displayType === 'titleOnly') {
      return '';
    } elseif($displayType === 'excerpt') {
      // get excerpt
      if(!empty($post->post_excerpt)) {
        return self::stripShortCodes($post->post_excerpt);
      } else {
        return $this->generateExcerpt(self::stripShortCodes($post->post_content));
      }
    } else {
      return self::stripShortCodes($post->post_content);
    }
  }

  function filterContent($content) {
    $content = self::convertEmbeddedContent($content);

    // convert h4 h5 h6 to h3
    $content = preg_replace('/<([\/])?h[456](.*?)>/', '<$1h3$2>', $content);

    // convert currency signs
    $content = str_replace(
      array('$', '€', '£', '¥'),
      array('&#36;', '&euro;', '&pound;', '&#165;'),
      $content
    );

    // strip useless tags
    $tags_not_being_stripped = array(
      '<img>', '<p>', '<em>', '<span>', '<b>', '<strong>', '<i>', '<h1>',
      '<h2>', '<h3>', '<a>', '<ul>', '<ol>', '<li>', '<br>', '<blockquote>'
    );
    $content = strip_tags($content, implode('', $tags_not_being_stripped));
    $content = str_replace('<p', '<p class="' . self::WP_POST_CLASS .'"', wpautop($content));
    $content = trim($content);
    $content = $this->cut_str2($content,220,"...",true);

    return $content;
  }

  private function generateExcerpt($content) {
    // if excerpt is empty then try to find the "more" tag
    $excerpts = explode('<!--more-->', $content);
    if(count($excerpts) > 1) {
      // <!--more--> separator was present
      return $excerpts[0];
    } else {
      // Separator not present, try to shorten long posts
      return wp_trim_words($content, $this->max_excerpt_length, ' &hellip;');
    }
  }

  private function stripShortCodes($content) {
    // remove captions
    $content = preg_replace(
      "/\[caption.*?\](.*<\/a>)(.*?)\[\/caption\]/",
      '$1',
      $content
    );

    // remove other shortcodes
    $content = preg_replace('/\[[^\[\]]*\]/', '', $content);

    return $content;
  }

  private function convertEmbeddedContent($content = '') {
    // remove embedded video and replace with links
    $content = preg_replace(
      '#<iframe.*?src=\"(.+?)\".*><\/iframe>#',
      '<a href="$1">'.__('Click here to view media.', 'mailpoet').'</a>',
      $content
    );

    // replace youtube links
    $content = preg_replace(
      '#http://www.youtube.com/embed/([a-zA-Z0-9_-]*)#Ui',
      'http://www.youtube.com/watch?v=$1',
      $content
    );

    return $content;
  }
}
