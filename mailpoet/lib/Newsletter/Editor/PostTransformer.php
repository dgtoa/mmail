<?php
namespace MailPoet\Newsletter\Editor;

use MailPoet\Newsletter\Editor\PostContentManager;
use MailPoet\Newsletter\Editor\MetaInformationManager;
use MailPoet\Newsletter\Editor\StructureTransformer;

if(!defined('ABSPATH')) exit;

class PostTransformer {

  function __construct($args) {
    $this->args = $args;
  }

  function transform($post) {
    $content_manager = new PostContentManager();
    $meta_manager = new MetaInformationManager();

    $content = $content_manager->getContent($post, $this->args['displayType']);
    //$content = $meta_manager->appendMetaInformation($content, $post, $this->args);
    $content = $content_manager->filterContent($content);

    $structure_transformer = new StructureTransformer();
    $structure = $structure_transformer->transform($content, $this->args['imageFullWidth'] === true);

    if($this->args['featuredImagePosition'] === 'aboveTitle') {
      $structure = $this->appendPostTitle($post, $structure);
      $structure = $this->appendFeaturedImage(
        $post,
        $this->args['displayType'],
        filter_var($this->args['imageFullWidth'], FILTER_VALIDATE_BOOLEAN),
        $structure
      );
    } else {
      if($this->args['featuredImagePosition'] === 'belowTitle') {
        $structure = $this->appendFeaturedImage(
          $post,
          $this->args['displayType'],
          filter_var($this->args['imageFullWidth'], FILTER_VALIDATE_BOOLEAN),
          $structure
        );
      }
      $structure = $this->appendPostTitle($post, $structure);
    }
    $structure = $this->appendReadMore($post->ID, $structure);

    return $structure;
  }

  private function appendFeaturedImage($post, $display_type, $image_full_width, $structure) {
    if($display_type !== 'excerpt') {
      // Append featured images only on excerpts
      return $structure;
    }

    $featured_image = $this->getFeaturedImage(
      $post->ID,
      $post->post_title,
      (bool)$image_full_width
    );

    if(is_array($featured_image)) {
      return array_merge(array($featured_image), $structure);
    }

    return $structure;
  }

  private function getFeaturedImage($post_id, $post_title, $image_full_width) {
    if(get_post_meta($post_id, 'post-second-thumbnail', true) != ''){ //if(has_post_thumbnail($post_id)) { 
      $thumbnail_id = get_post_thumbnail_id($post_id);

      // get attachment data (src, width, height)
      $image_info = wp_get_attachment_image_src(
        $thumbnail_id,
        'thevoux-newsletter-wide'
      );
      
      $thumbnail_url = get_post_meta($post_id, 'post-second-thumbnail', true);
      
      //get img_src
      if(strpos($thumbnail_url, '-660x371') === false) {
          $pos = strrpos($thumbnail_url, '.');
          $thumbnail_url = substr_replace($thumbnail_url, '-660x371', $pos, 0);
      }

      // get alt text
      $alt_text = trim(strip_tags(get_post_meta(
        $thumbnail_id,
        '_wp_attachment_image_alt',
        true
      )));
      if(strlen($alt_text) === 0) {
        // if the alt text is empty then use the post title
        $alt_text = trim(strip_tags($post_title));
      }

      return array(
        'type' => 'image',
        'link' => get_permalink($post_id),
        'src' => $thumbnail_url,
        'alt' => $alt_text,
        'fullWidth' => $image_full_width,
        'width' => '660px',
        'height' => '371px',
        'styles' => array(
          'block' => array(
            'textAlign' => 'center',
          ),
        ),
      );
    }
  }
  
  private function getPostCategories($post_id, $post_type) {

    // Get categories
    $categories = wp_get_post_terms(
      $post_id,
      array('category'),
      array('fields' => 'names', 'exclude' => '2, 3, 4, 73, 91, 92')
    );
    if(!empty($categories)) {
      // check if the user specified a label to be displayed before the author's name
      return $content . join(', ', $categories);
    } else {
      return '';
    }
  }

  private function appendPostTitle($post, $structure) {
    $title = $this->getPostTitle($post);
    
    $coauthor_terms = wp_get_object_terms( $post->ID, 'author', array(
        'orderby' => 'term_order',
        'order' => 'ASC',
    ) );
    $co_authors = "";
    $coauthor_terms_array = objectToArray($coauthor_terms);
    $co_authors = $coauthor_terms_array[0]['name'];
    if(count($coauthor_terms_array)>1){
        foreach ($coauthor_terms_array as $co) {
           if($co != $coauthor_terms_array[0])
           $co_authors .= ", ".$co['name'];
        }
    }
    
    $title = "<span style='color:red; font-size:12px; letter-spacing:1px; font-weight:600; font-family: Arial,\"Helvetica Neue\",Helvetica,sans-serif;'>". $this->getPostCategories($post->ID,$post->post_type) 
                . "</span><div style='height:7px'></div>"."<strong style='font-weight:600 !important;'>". $title . "</strong>"
                . "<span style='color:#9e9e9e; font-size:12px; letter-spacing:1px; font-weight:600; margin-bottom:15px; font-family: Arial,\"Helvetica Neue\",Helvetica,sans-serif; '>" . $co_authors . "</span><div style='height:11px'></div>";    

    // Append title always at the top of the post structure
    // Reuse an existing text block if needed

    if(count($structure) > 0 && $structure[0]['type'] === 'text') {
      $structure[0]['text'] = $title . $structure[0]['text'];
    } else {
      array_unshift(
        $structure,
        array(
          'type' => 'text',
          'text' => $title,
        )
      );
    }

    return $structure;
  }

  private function appendReadMore($post_id, $structure) {
    if($this->args['readMoreType'] === 'button') {
      $button = $this->args['readMoreButton'];
      $button['url'] = get_permalink($post_id);
      $structure[] = $button;
    } else {
      $total_blocks = count($structure);
      $read_more_text = sprintf(
        '<p><a href="%s">%s</a></p>',
        get_permalink($post_id),
        $this->args['readMoreText']
      );

      if($structure[$total_blocks - 1]['type'] === 'text') {
        $structure[$total_blocks - 1]['text'] .= $read_more_text;
      } else {
        $structure[] = array(
          'type' => 'text',
          'text' => $read_more_text,
        );
      }
    }

    return $structure;
  }

  private function getPostTitle($post) {
    $title = $post->post_title;

    if(filter_var($this->args['titleIsLink'], FILTER_VALIDATE_BOOLEAN)) {
      $title = '<a href="' . get_permalink($post->ID) . '">' . $title . '</a>';
    }

    if(in_array($this->args['titleFormat'], array('h1', 'h2', 'h3'))) {
      $tag = $this->args['titleFormat'];
    } elseif($this->args['titleFormat'] === 'ul') {
      $tag = 'li';
    } else {
      $tag = 'h1';
    }

    $alignment = (in_array($this->args['titleAlignment'], array('left', 'right', 'center'))) ? $this->args['titleAlignment'] : 'left';

    return '<' . $tag . ' data-post-id="' . $post->ID . '" style="text-align: ' . $alignment . ';">' . $title . '</' . $tag . '>';
  }
}
