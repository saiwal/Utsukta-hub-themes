<?php

/**
 *   * Name: Mailbox
 *   * Description: Quick access to messages, direct messages, starred messages (if enabled) and notifications in mailbox form
 *   * Author: SK (sk@utsukta.org)
 *   * Requires: hq
 */


namespace Zotlabs\Widget;

use App;
use Zotlabs\Lib\IConfig;

class Mailbox {

	public static function widget($arr) {
		if (!local_channel())
			return EMPTY_STR;

		$page = self::get_messages_page([]);

		$_SESSION['messages_loadtime'] = datetime_convert();

		$r = q("SELECT DISTINCT(term) FROM term WHERE uid = %d AND ttype = %d ORDER BY term",
			intval(local_channel()),
			intval(TERM_FILE)
		);

		$file_tags = [];

		if ($r) {
			foreach($r as $rr) {
				$file_tags[] = $rr['term'];
			}
		}

		$tpl = get_markup_template('mailbox_widget.tpl');
		$o = replace_macros($tpl, [
			'$entries' => $page['entries'] ?? [],
			'$offset' => $page['offset'] ?? 0,
			'$feature_star' => feature_enabled(local_channel(), 'star_posts'),
			'$feature_file' => feature_enabled(local_channel(), 'filing'),
			'$file_tags' => $file_tags,
			'$strings' => [
				'messages_title' => t('Public and restricted conversations'),
				'direct_messages_title' => t('Private conversations'),
				'starred_messages_title' => t('Starred conversations'),
				'filed_messages_title' => t('Filed messages'),
				'notice_messages_title' => t('Notifications'),
				'loading' => t('Loading'),
				'empty' => t('No conversations'),
				'unseen_count' => t('Unseen reactions'),
				'filter' => t('Filter by name or address'),
				'file_filter' => t('Filter by file name')
			]
		]);

		return $o;
	}

	public static function get_messages_page($options) {
		if (!local_channel())
			return;

		$offset = $options['offset'] ?? 0;
		$type = $options['type'] ?? '';
		$author = $options['author'] ?? '';
		$file = $options['file'] ?? '';

		if ($offset == -1) {
			return;
		}

		if ($type == 'notification') {
			return self::get_notices_page($options);
		}

		$channel = App::get_channel();
		$item_normal = item_normal();
		// Filter internal follow activities and strerams add/remove activities
		$item_normal .= " and item.verb not in ('Add', 'Remove', 'Follow', 'Ignore', '" . ACTIVITY_FOLLOW . "') ";
		$item_normal_i = str_replace('item.', 'i.', $item_normal);
		$item_normal_c = str_replace('item.', 'c.', $item_normal);
		$entries = [];
		$limit = 30;
		$order_sql = 'i.created DESC';
		$loadtime = (($offset) ? $_SESSION['messages_loadtime'] : datetime_convert());
		$vnotify = get_pconfig(local_channel(), 'system', 'vnotify', -1);

		$vnotify_sql_c = '';
		$vnotify_sql_i = '';

		if (!($vnotify & VNOTIFY_LIKE)) {
			$vnotify_sql_c = " AND c.verb NOT IN ('Like', 'Dislike', '" . dbesc(ACTIVITY_LIKE) . "', '" . dbesc(ACTIVITY_DISLIKE) . "') ";
			$vnotify_sql_i = " AND i.verb NOT IN ('Like', 'Dislike', '" . dbesc(ACTIVITY_LIKE) . "', '" . dbesc(ACTIVITY_DISLIKE) . "') ";
		}
		elseif (!feature_enabled(local_channel(), 'dislike')) {
			$vnotify_sql_c = " AND c.verb NOT IN ('Dislike', '" . dbesc(ACTIVITY_DISLIKE) . "') ";
			$vnotify_sql_i = " AND i.verb NOT IN ('Dislike', '" . dbesc(ACTIVITY_DISLIKE) . "') ";
		}

		$filter_sql = '';
		if($type !== 'filed' && $author) {
			$filter_sql = " AND (i.owner_xchan = '" . protect_sprintf(dbesc($author)) . "') ";
		}

		$filed_filter_sql = '';
		if($type === 'filed' && $file) {
			$filed_filter_sql = " AND (term.term = '" . protect_sprintf(dbesc($file)) . "') ";
		}

		$dummy_order_sql = '';

		switch($type) {
			case 'direct':
				$type_sql = ' AND i.item_private = 2 AND i.item_thread_top = 1 ';
				// $dummy_order_sql has no other meaning but to trick
				// some mysql backends into using the right index.
				$dummy_order_sql = ', i.received DESC ';
				break;
			case 'starred':
				$type_sql = ' AND i.item_starred = 1 AND i.item_thread_top = 1 ';
				break;
			case 'filed':
				$type_sql = ' AND i.id IN (SELECT term.oid FROM term WHERE term.ttype = ' . TERM_FILE . ' AND term.uid = i.uid ' . $filed_filter_sql . ')';
				break;
			default:
				$type_sql = ' AND i.item_private IN (0, 1) AND i.item_thread_top = 1 ';
		}

		$items = q("SELECT *,
			(SELECT count(*) FROM item c WHERE c.uid = %d AND c.parent = i.parent AND c.item_unseen = 1 AND c.item_thread_top = 0 $item_normal_c $vnotify_sql_c) AS unseen_count
			FROM item i
			WHERE i.uid = %d
			AND i.created <= '%s'
			$type_sql
			$filter_sql
			$item_normal_i
			ORDER BY $order_sql $dummy_order_sql
			LIMIT $limit OFFSET $offset",
			intval(local_channel()),
			intval(local_channel()),
			dbescdate($loadtime)
		);

		if ($type === 'filed') {
			$items = fetch_post_tags($items);
		}

		xchan_query($items, false);

		$i = 0;
		$entries = [];
		$ids = [];

		foreach($items as $item) {

			$hook_data = [
				'uid' => $item['uid'],
				'owner_xchan' => $item['owner_xchan'],
				'author_xchan' => $item['author_xchan'],
				'cancel' => false
			];

			call_hooks('messages_widget', $hook_data);

			if ($hook_data['cancel']) {
				continue;
			}

			$info = '';
			if ($type == 'direct') {
				$info .= self::get_dm_recipients($channel, $item);
			}

			if($item['owner_xchan'] !== $item['author_xchan']) {
				$info .= t('via') . ' ' . $item['owner']['xchan_name'];
			}
			elseif($item['verb'] === 'Announce' && isset($item['source'])) {
				$info .= t('via') . ' ' . $item['source']['xchan_name'];
			}

			if ($type == 'filed') {
				$info = '';
				foreach ($item['term'] as $t) {
					if ($t['ttype'] !== TERM_FILE) {
						continue;
					}
					$info .= '<span class="badge rounded-pill bg-danger me-1"><i class="bi bi-folder"></i>&nbsp;' . $t['term'] . '</span>';
				}
			}

			$summary = $item['title'];
			if (!$summary) {
				$summary = $item['summary'];
			}

			if (!$summary) {
				$summary = html2plain(bbcode($item['body'], ['drop_media' => true, 'tryoembed' => false]), 75, true);
				if ($summary) {
					$summary = htmlentities($summary, ENT_QUOTES, 'UTF-8', false);
				}
			}

			if (!$summary) {
				$summary = '...';
			}
			else {
				$summary = substr_words($summary, 140);
			}

			switch(intval($item['item_private'])) {
				case 1:
					$icon = '<i class="bi bi-lock"></i>';
					break;
				case 2:
					$icon = '<i class="bi bi-envelope"></i>';
					break;
				default:
					$icon = '';
			}

			$entries[$i]['author_name'] = $item['author']['xchan_name'];
			$entries[$i]['author_addr'] = (($item['author']['xchan_addr']) ? $item['author']['xchan_addr'] : $item['author']['xchan_url']);
			$entries[$i]['author_img'] = $item['author']['xchan_photo_s'];
			$entries[$i]['info'] = $info;
			$entries[$i]['created'] = datetime_convert('UTC', date_default_timezone_get(), $item['created']);
			$entries[$i]['summary'] = $summary;
			//$entries[$i]['b64mid'] = gen_link_id($item['mid']);
			$entries[$i]['b64mid'] = $item['uuid'];
			$entries[$i]['href'] = z_root() . '/hq/' . $item['uuid'];
			$entries[$i]['icon'] = $icon;
			$entries[$i]['unseen_count'] = (($item['unseen_count']) ? $item['unseen_count'] : (($item['item_unseen']) ? '&#8192;' : ''));
			$entries[$i]['unseen_class'] = (($item['item_unseen']) ? 'primary' : 'secondary');

			$i++;
		}

		$result = [
			'offset' => ((count($entries) < $limit) ? -1 : intval($offset + $limit)),
			'entries' => $entries
		];

		return $result;
	}

	public static function get_dm_recipients($channel, $item) {

		if($channel['channel_hash'] === $item['owner']['xchan_hash']) {
			// we are the owner, get the recipients from the item
			$recips = expand_acl($item['allow_cid']);
			if (is_array($recips)) {
				array_unshift($recips, $item['owner']['xchan_hash']);
				$column = 'xchan_hash';
			}
		}
		else {
			$recips = IConfig::Get($item, 'activitypub', 'recips');
			if (isset($recips['to']) && is_array($recips['to'])) {
				$recips = $recips['to'];
				array_unshift($recips, $item['owner']['xchan_url']);
				$column = 'xchan_url';
			}
			else {
				$hookinfo = [
					'item' => $item,
					'recips' => null,
					'column' => ''
				];

				call_hooks('direct_message_recipients', $hookinfo);

				$recips = $hookinfo['recips'];
				$column = $hookinfo['column'];
			}
		}

		$recipients = '';

		if(is_array($recips)) {
			stringify_array_elms($recips, true);

			$query_str = implode(',', $recips);
			$xchans = dbq("SELECT DISTINCT xchan_name FROM xchan WHERE $column IN ($query_str) AND xchan_deleted = 0");
			foreach($xchans as $xchan) {
				$recipients .= $xchan['xchan_name'] . ', ';
			}
		}

		return trim($recipients, ', ');
	}

	public static function get_notices_page($options) {

		if (!local_channel())
			return;


		$limit  = 30;

		$offset = 0;
		if ($options['offset']) {
			$offset = intval($options['offset']);
		}

		$author_url = $options['author'] ?? '';
		$author_sql = '';

		if($author_url) {
			$author_sql = " AND url = '" . protect_sprintf(dbesc($author_url)) . "' ";
		}

		$notices = q("SELECT * FROM notify WHERE uid = %d $author_sql
			ORDER BY created DESC LIMIT $limit OFFSET $offset",
			intval(local_channel())
		);

		$i = 0;
		$entries = [];

		foreach($notices as $notice) {

			$summary = trim(strip_tags(bbcode($notice['msg'])));

			if(strpos($summary, $notice['xname']) === 0) {
				$summary = substr($summary, strlen($notice['xname']) + 1);
			}

			$entries[$i]['author_name'] = $notice['xname'];
			$entries[$i]['author_addr'] = $notice['url'];
			$entries[$i]['author_img'] = $notice['photo'];// $item['author']['xchan_photo_s'];
			$entries[$i]['info'] = '';
			$entries[$i]['created'] = datetime_convert('UTC', date_default_timezone_get(), $notice['created']);
			$entries[$i]['summary'] = $summary;
			$entries[$i]['b64mid'] = (($notice['ntype'] & NOTIFY_INTRO) ? '' : ((str_contains($notice['hash'], '-')) ? $notice['hash'] : basename($notice['link'])));
			$entries[$i]['href'] = (($notice['ntype'] & NOTIFY_INTRO) ? $notice['link'] : z_root() . '/hq/' . ((str_contains($notice['hash'], '-')) ? $notice['hash'] : basename($notice['link'])));
			$entries[$i]['icon'] = (($notice['ntype'] & NOTIFY_INTRO) ? '<i class="bi bi-person-plus"></i>' : '');

			$i++;
		}

		$result = [
			'offset' => ((count($entries) < $limit) ? -1 : intval($offset + $limit)),
			'entries' => $entries
		];

		return $result;
	}
}
