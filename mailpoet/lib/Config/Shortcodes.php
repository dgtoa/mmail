<?php
namespace MailPoet\Config;
use MailPoet\Models\Newsletter;
use MailPoet\Models\Subscriber;
use MailPoet\Models\SubscriberSegment;
use MailPoet\Newsletter\Url as NewsletterUrl;
use MailPoet\WP\Hooks;

class Shortcodes {
  function __construct() {
  }

  function init() {
    // form widget shortcode
    add_shortcode('mailpoet_form', array($this, 'formWidget'));

    // subscribers count shortcode
    add_shortcode('mailpoet_subscribers_count', array(
      $this, 'getSubscribersCount'
    ));
    add_shortcode('wysija_subscribers_count', array(
      $this, 'getSubscribersCount'
    ));

    // archives page
    add_shortcode('mailpoet_archive', array(
      $this, 'getArchive'
    ));

    Hooks::addFilter('mailpoet_archive_date', array(
      $this, 'renderArchiveDate'
    ), 2);
    Hooks::addFilter('mailpoet_archive_date_day', array(
      $this, 'renderArchiveDateDay'
    ), 2);
    Hooks::addFilter('mailpoet_archive_date_month', array(
      $this, 'renderArchiveDateMonth'
    ), 2);
    Hooks::addFilter('mailpoet_archive_date_year', array(
      $this, 'renderArchiveDateYear'
    ), 2);    
    Hooks::addFilter('mailpoet_archive_subject', array(
      $this, 'renderArchiveSubject'
    ), 2, 3);
    Hooks::addFilter('mailpoet_archive_preheader', array(
      $this, 'renderArchivePreheader'
    ), 2, 3);
  }

  function formWidget($params = array()) {
    // IMPORTANT: fixes conflict with MagicMember
    remove_shortcode('user_list');

    if(isset($params['id']) && (int)$params['id'] > 0) {
      $form_widget = new \MailPoet\Form\Widget();
      return $form_widget->widget(array(
        'form' => (int)$params['id'],
        'form_type' => 'shortcode'
      ));
    }
  }

  function getSubscribersCount($params) {
    if(!empty($params['segments'])) {
      $segment_ids = array_map(function($segment_id) {
        return (int)trim($segment_id);
      }, explode(',', $params['segments']));
    }

    if(empty($segment_ids)) {
      return number_format_i18n(Subscriber::filter('subscribed')->count());
    } else {
      return number_format_i18n(
        SubscriberSegment::whereIn('segment_id', $segment_ids)
          ->select('subscriber_id')->distinct()
          ->filter('subscribed')
          ->findResultSet()->count()
      );
    }
  }
  

  function getArchive($params) {
      
    $segment_ids = array();
    if(!empty($params['segments'])) {
      $segment_ids = array_map(function($segment_id) {
        return (int)trim($segment_id);
      }, explode(',', $params['segments']));
    }

    $html = '';

    $newsletters = Newsletter::getArchives($segment_ids);

    $subscriber = Subscriber::getCurrentWPUser();

    if(empty($newsletters)) {
      return Hooks::applyFilters(
        'mailpoet_archive_no_newsletters',
        __('발송된 뉴스레터가 없습니다.', 'mailpoet')
      );
    } else {
      $title = Hooks::applyFilters('mailpoet_archive_title', '');
      if(!empty($title)) {
        $html .= '<h3 class="mailpoet_archive_title">'.$title.'</h3>';
      }
      $html .= '<ul class="mailpoet_archive">';
      $count = 0;
      foreach($newsletters as $newsletter) {
        $queue = $newsletter->queue()->findOne();
        $html .= '<li>'.
          '<span class="mailpoet_archive_date">'.
            Hooks::applyFilters('mailpoet_archive_date', $newsletter).
          '</span>
          <span class="mailpoet_archive_subject">'.
            Hooks::applyFilters('mailpoet_archive_subject', $newsletter, $subscriber, $queue).
          '</span>
        </li>';
        if($count > 9) break;
        $count++;
      }
      $html .= '</ul>';
    }
    return $html;
  }

  function renderArchiveDate($newsletter) {
    return date_i18n(
      'Y년 m월 d일 - ',
      strtotime($newsletter->processed_at)
    );
  }

  function renderArchiveDateDay($newsletter) {
    return date_i18n(
      'd',
      strtotime($newsletter->processed_at)
    );
  }
   function renderArchiveDateMonth($newsletter) {
    return date_i18n(
      'F',
      strtotime($newsletter->processed_at)
    );
  }
  function renderArchiveDateYear($newsletter) {
    return date_i18n(
      'y',
      strtotime($newsletter->processed_at)
    );
  }
  
  function renderArchiveSubject($newsletter, $subscriber, $queue) {
    $preview_url = NewsletterUrl::getViewInBrowserUrl(
      NewsletterUrl::TYPE_ARCHIVE,
      $newsletter,
      $subscriber,
      $queue
    );
    $n_subject = ($newsletter->type == 'notification_history') ? $newsletter->newsletter_rendered_subject : $newsletter->subject;
    return '<a href="'.esc_attr($preview_url).'" target="_blank" title="'
      .esc_attr(__('Preview in a new tab', 'mailpoet')).'">'
      .esc_attr($n_subject).
    '</a>';
  }
  function renderArchivePreheader($newsletter, $subscriber, $queue) {
    $preview_url = NewsletterUrl::getViewInBrowserUrl(
      NewsletterUrl::TYPE_ARCHIVE,
      $newsletter,
      $subscriber,
      $queue
    );
    return 
      esc_attr($newsletter->preheader);
      //print_r($newsletter);
  }
  
}
