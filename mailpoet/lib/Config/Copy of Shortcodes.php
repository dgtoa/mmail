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
    
    //$html = '';

    $newsletters = Newsletter::getArchives($segment_ids);
    
    $subscriber = Subscriber::getCurrentWPUser();

    if(empty($newsletters)) {
      return Hooks::applyFilters(
        'mailpoet_archive_no_newsletters',
        __('Oops! There are no newsletters to display.', 'mailpoet')
      );
    } else {
        
        $newsletters_counts = count($newsletters);
        $per_pages = get_query_var('posts_per_page');
        $num_page_links = ceil($newsletters_counts/$per_pages);
        $current_url = "http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
        $arr_croped_url = split("\/", $current_url);
        $croped_url = '';
        for($j=0; $j<4;$j++) {
             $croped_url .=  $arr_croped_url[$j];
             $croped_url .= '/';
        }
        $current_post_num = (strstr($current_url, "page/")) ? (int)(preg_replace("/[^0-9]*/s", "", $current_url))-1  : 0 ;
        // print_r($current_post_num);
        // print_r($num_page_links);
        if($current_post_num>0 && $current_post_num+1 > $num_page_links){
             wp_redirect($croped_url . 'page/' . $num_page_links);
        }
        
      $title = Hooks::applyFilters('mailpoet_archive_title', '');
      if(!empty($title)) { ?>
        <h3 class="mailpoet_archive_title"> <?php echo $title; ?> </h3> 
      <?php } ?>
      <ul class="mailpoet_archive">
      <?php 
      
      for($i=0;$i<$per_pages; $i++) {
          if(isset($newsletters[(int)($current_post_num*$per_pages + $i)])) {
                $newsletter = $newsletters[(int)($current_post_num*$per_pages + $i)];
                $queue = $newsletter->queue()->findOne();
                //print_r($queue);
            
        $post_id = $GLOBALS['wpdb']->get_row( 'SELECT * FROM kias.wp_mailpoet_newsletter_posts where created_at="' . $newsletter->created_at . '"' )->post_id;
        $post = get_post($post_id);
        $thumbnail_url = $GLOBALS['wpdb']->get_row( 'SELECT * FROM kias.wp_mailpoet_newsletters where id="' . $newsletter->id . '"' )->thumbnail_url;
        $thumbnail_url_name = '';
        $arr_thumbnail_url_name = split("\.", $thumbnail_url);
        for($j=0; $j<sizeof($arr_thumbnail_url_name)-1;$j++) {
             $thumbnail_url_name .=  $arr_thumbnail_url_name[$j];
             if($j<sizeof($arr_thumbnail_url_name)-2)
                $thumbnail_url_name .= '.';
        }
        //print_r($thumbnail_url_name . "=========="); 
        ?>
        
        <li>
          <div class="mailpoet_archive_date">
                <p class="mailpoet_archive_date_day" > <?php echo Hooks::applyFilters('mailpoet_archive_date_day', $newsletter); ?>  </p>
                <p class="mailpoet_archive_date_month"> <?php  echo Hooks::applyFilters('mailpoet_archive_date_month', $newsletter); ?> </p> 
          </div>
          <div class="mailpoet_archive_thumbnail">
            <?php echo ($newsletter->type == 'notification_history') ? 
                get_the_post_thumbnail($post, 'thevoux-masonry-wide') : 
                '<img width="400" height="225" src="'.$thumbnail_url_name.'-400x225.jpg" class="attachment-thumbnail size-thumbnail wp-post-image" sizes="(max-width: 150px) 100vw, 150px">'; ?>
          </div>
          <div class="newsletter-title-area">
                <div class="mailpoet_archive_preheader">
                    <?php echo ($newsletter->type == 'notification_history') ? get_the_category($post_id)[0]->name  : Hooks::applyFilters('mailpoet_archive_preheader', $newsletter, $subscriber, $queue) ; ?>
                </div>
                <div class="mailpoet_archive_subject">
                      <?php echo ($newsletter->type == 'notification_history') ? Hooks::applyFilters('mailpoet_archive_subject', $newsletter, $subscriber, $queue) : $newsletter->subject; ?>
                </div>
         </div>
       </li>
        <?php }
      } ?>
     </ul>
      <?php //print_r($newsletter_queue);
      //print_r($i);
    
    }
    newsletter_pagination($newsletters_counts, $current_post_num, $per_pages);
    return;
  }

  function renderArchiveDate($newsletter) {
    return date_i18n(
      get_option('date_format'),
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
    return '<a href="'.esc_attr($preview_url).'" target="_blank" title="'
      .esc_attr(__('Preview in a new tab', 'mailpoet')).'">'
      .esc_attr($newsletter->newsletter_rendered_subject).
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


function newsletter_pagination($newsletters_counts, $current_post_num=0, $per_pages=3) {
    if($newsletters_counts <= $per_pages)
        return;
    //print_r(ceil($favorites_counts/$per_pages));
    $num_page_links = ceil($newsletters_counts/$per_pages);
    $current_url = "http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
    $arr_croped_url = split("\/", $current_url);
    $croped_url = '';
    for($j=0; $j<4;$j++) {
         $croped_url .=  $arr_croped_url[$j];
         $croped_url .= '/';
    }
    
    ?>
<div class="blog_nav">
    <?php if(($current_post_num+1) != 1) { ?> <a class="prev page-numbers" href="<?php echo $croped_url; ?>page/<?php echo $current_post_num; ?>/">prev</a> <?php } ?>
    <?php if(($current_post_num+1) != 1) { ?> <a class="page-numbers" href="<?php echo $croped_url; ?>">1</a> <?php } ?>
    <?php if(($current_post_num+1) > 3) { ?> <span class="page-numbers dots">…</span> <?php } ?>
     <!--<?php if(($current_post_num-1) > 0 && ($current_post_num-1)!=1) { ?> <a class="page-numbers" href="<?php echo $croped_url; ?>page/<?php echo $current_post_num-1; ?>/"><?php echo $current_post_num-1; ?></a> <?php } ?>--> 
    <?php if(($current_post_num-1) > 0 && ($current_post_num+1)!=1) { ?> <a class="page-numbers" href="<?php echo $croped_url; ?>page/<?php echo $current_post_num; ?>/"><?php echo $current_post_num; ?></a> <?php } ?>
    <span aria-current="page" class="page-numbers current"><?php echo $current_post_num+1; ?></span>
    <?php if(($current_post_num+2) < $num_page_links) { ?> <a class="page-numbers" href="<?php echo $croped_url; ?>page/<?php echo $current_post_num+2; ?>/"><?php echo $current_post_num+2; ?></a> <?php } ?>
    <!-- <?php if(($current_post_num+3) < $num_page_links) { ?> <a class="page-numbers" href="<?php echo $croped_url; ?>page/<?php echo $current_post_num+3; ?>/"><?php echo $current_post_num+3; ?></a> <?php } ?>-->
    <?php if(($current_post_num+1) < $num_page_links+1 - 3) { ?> <span class="page-numbers dots">…</span> <?php } ?>
    <?php if(($current_post_num+1) < $num_page_links) { ?> <a class="page-numbers" href="<?php echo $croped_url; ?>page/<?php echo $num_page_links; ?>/"><?php echo $num_page_links; ?></a> <?php } ?>
    <?php if(($current_post_num+1) < $num_page_links) { ?> <a class="next page-numbers" href="<?php echo $croped_url; ?>page/<?php echo $current_post_num+2; ?>/">next</a> <?php } ?>
</div>
<?php
}