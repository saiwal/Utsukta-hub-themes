<?php
use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Chatroom;
use Zotlabs\Lib\Config;

require_once ('include/security.php');
require_once ('include/menu.php');
require_once ('include/items.php');  // for fetch_post_tags, item_normal
require_once ('include/conversation.php');  // for helper functions

function widget_hero($args)
{
    // If viewing a specific post (e.g., ?mid=...), return nothing
  if (argc() >= 2 && argv(0) === 'channel' && isset($_GET['mid']) && $_GET['mid']) {
    $o = '';
    $o .= <<<EOT
<style>
.ss-home .s-header__branding a {
 color: black;
}
.ss-home .s-header__nav-wrap {
 margin-left: 0%;
}
</style>
EOT;  
      return $o;
    }
    if (isset($args['channel'])) {
        $channel = channelx_by_nick($args['channel']);
    } else {
        // fallback to local channel if logged in
        $channel = App::get_channel();
    }


$cat = ((x($_REQUEST, 'cat')) ? notags(trim($_REQUEST['cat'])) : '');
$tag = ((x($_REQUEST, 'tag')) ? notags(trim($_REQUEST['tag'])) : '');

$o = '';

// ---- render category or tag header ----
if ($cat || $tag) {
    $label_type  = $cat ? t('Category:') : t('Tag:');
    $label_value = $cat ? htmlspecialchars($cat) : htmlspecialchars($tag);

    $o .= <<<EOT
<div class="s-pageheader">
  <div class="row">
    <div class="column large-12">
      <h1 class="page-title">
        <span class="page-title__small-type">$label_type</span>
        $label_value
      </h1>
    </div>
  </div>
    </div>

EOT;
  return $o;
  }
    // If still null, fallback to a default public channel
    if (!$channel) {
        $channel = channelx_by_nick('admin');  // change 'admin' to your public channel
    }

    // Accept a 'category' arg; default to 'Featured'
    $rawcat = (isset($args['category']) && strlen(trim($args['category']))) ? trim($args['category']) : 'Featured';
    $limit = (isset($args['limit']) ? intval($args['limit']) : 3);

    // Determine if it's a hashtag or category
    $is_hashtag = (substr($rawcat, 0, 1) === '#');

    if ($is_hashtag) {
        $term_sql = protect_sprintf(term_query('item', substr($rawcat, 1), TERM_HASHTAG, TERM_COMMUNITYTAG));
    } else {
        $term_sql = protect_sprintf(term_query('item', $rawcat, TERM_CATEGORY));
    }

    $item_normal = item_normal();
    //$sql_extra = public_permissions_sql(intval($channel['channel_id']));
    $permission_sql = item_permissions_sql(intval($channel['channel_id']));

    $sql = "SELECT item.* FROM item
            WHERE item.uid = %d
            $item_normal
            $term_sql
            $permission_sql
            ORDER BY item.created DESC
            LIMIT %d";

    $items = q($sql, intval($channel['channel_id']), intval($limit));

    if (!$items) {
    $display = htmlspecialchars($rawcat, ENT_QUOTES, 'UTF-8');
    $o = '';
    $o .= <<<EOT
<style>
.ss-home .s-header__branding a {
 color: black;
}
.ss-home .s-header__nav-wrap {
 margin-left: 0%;
}
</style>
EOT;  
      return $o;
    }

    xchan_query($items);
    $items = fetch_post_tags($items, true);

    // Start building HTML
    $html = '<div class="hero">';
    $html .= '<div class="hero__slider swiper-container swiper-container-fade swiper-container-initialized swiper-container-horizontal">';
    $html .= '<div class="swiper-wrapper" aria-live="polite">';

    $count = 0;
    $total = count($items);

    foreach ($items as $item) {
        $count++;

        $mid = $item['mid'];

        // If the MID looks like a full URL (contains '/item/'), extract the UUID part
        if (preg_match('#/item/([a-f0-9\-]+)$#', $mid, $matches)) {
            $mid = $matches[1];
        }

        // Now build the display link
        $plink = z_root() . '/item/' . $mid;

        $title = htmlspecialchars($item['title'] ?: '(No title)', ENT_QUOTES, 'UTF-8');
        $desc = htmlspecialchars(trim(substr(strip_tags(bbcode($item['body'])), 0, 200)), ENT_QUOTES, 'UTF-8');

        // Fix image URLs by extracting from [zmg=...] tags first
        $img = '';
        if (preg_match_all('/\[zmg=(https?:\/\/[^\]]+)\]/i', $item['body'], $matches)) {
            $img = $matches[1][0];
        }

        // Fallback: any direct image URLs in body
        if (!$img && preg_match('/(https?:\/\/[^\s"\'<>]+\.(?:jpg|jpeg|png|gif))(?:\?[^\s"\'<>]*)?/i', $item['body'], $m)) {
            $img = $m[1];
        }

        // Final fallback
        if (!$img) {
            $img = z_root() . '/images/default_featured.jpg';
        }

        $img = htmlspecialchars($img, ENT_QUOTES, 'UTF-8');

        // Determine swiper-slide classes
        $slide_class = 'hero__slide swiper-slide';
        if ($count === 1) {
            $slide_class .= ' swiper-slide-active';
            $opacity = 1;
            $transform = 'translate3d(0px, 0px, 0px)';
        } elseif ($count === 2) {
            $slide_class .= ' swiper-slide-next';
            $opacity = 0;
            $transform = 'translate3d(-1832px, 0px, 0px)';
        } else {
            $opacity = 0;
            $transform = 'translate3d(-' . (1832 * ($count - 1)) . 'px, 0px, 0px)';
        }

        $html .= '<article class="' . $slide_class . '" role="group" aria-label="' . $count . ' / ' . $total . '" style="width: 1832px; opacity: ' . $opacity . '; transform: ' . $transform . ';">';
        $html .= '<div class="hero__entry-image" style="background-image: url(\'' . $img . '\');"></div>';
        $html .= '<div class="hero__entry-text">';
        $html .= '<div class="hero__entry-text-inner">';
        $html .= '<div class="hero__entry-meta"><span class="cat-links">';
        $cat_link = z_root() . '/channel/' . $channel['channel_address'] . '?f=&cat=' . urlencode($rawcat);
        $html .= '<a href="' . htmlspecialchars($cat_link, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($rawcat, ENT_QUOTES, 'UTF-8') . '</a>';
        $html .= '</span></div>';
        $html .= '<h2 class="hero__entry-title"><a href="' . htmlspecialchars($plink, ENT_QUOTES, 'UTF-8') . '">' . $title . '</a></h2>';
        $html .= '<p class="hero__entry-desc">' . $desc . '</p>';
        $html .= '<a class="hero__more-link" href="' . htmlspecialchars($plink, ENT_QUOTES, 'UTF-8') . '">Read More</a>';
        $html .= '</div></div></article>';
    }

    $html .= '</div>';  // swiper-wrapper
    $html .= '<div class="swiper-pagination swiper-pagination-clickable swiper-pagination-bullets">';
    for ($i = 1; $i <= $total; $i++) {
        $bullet_class = ($i === 1) ? 'swiper-pagination-bullet-active' : '';
        $html .= '<span class="swiper-pagination-bullet ' . $bullet_class . '" tabindex="0">' . $i . '</span>';
    }
    $html .= '</div>';  // swiper-pagination
    $html .= '<span class="swiper-notification" aria-live="assertive" aria-atomic="true"></span>';
    $html .= '</div>';  // hero__slider

    $html .= '<a href="#region_2" class="hero__scroll-down smoothscroll">';
    $html .= '<svg width="24" height="24" fill="none" viewBox="0 0 24 24">';
    $html .= '<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.25 6.75L4.75 12L10.25 17.25"></path>';
    $html .= '<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.25 12H5"></path>';
    $html .= '</svg><span>Scroll</span></a>';

    $html .= '</div>';  // hero
    $html .= '<style>.s-content{ padding-top: 0;} .s-header__nav-wrap{ margin-left: 50%;} .s-header__branding a{color: white;}</style>';  // hero

    return $html;
}
