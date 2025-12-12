<?php 

function notification_nav(&$x) {

    $current_theme = App::$channel['channel_theme'];

    if (! str_starts_with($current_theme, 'adminlte')) {
        return;
    }

    $channel = App::get_channel();

		$notifications = [];

		if(local_channel()) {
			$notifications[] = [
				'type' => 'network',
				'icon' => 'grid-3x3',
				'severity' => 'secondary',
				'label' => t('Network'),
				'title' => t('Unseen network activity'),
				'viewall' => [
					'url' => 'network',
					'label' => t('Network stream')
				],
				'markall' => [
					'label' => t('Mark all read')
				],
				'filter' => [
					'posts_label' => t('Conversation starters'),
					'name_label' => t('Filter by name or address')
				]
			];


			$notifications[] = [
				'type' => 'home',
				'icon' => 'house',
				'severity' => 'danger',
				'label' => t('Channel'),
				'title' => t('Unseen channel activity'),
				'viewall' => [
					'url' => 'channel/' . $channel['channel_address'],
					'label' => t('Channel stream')
				],
				'markall' => [
					'label' => t('Mark all seen')
				],
				'filter' => [
					'posts_label' => t('Conversation starters'),
					'name_label' => t('Filter by name or address')
				]
			];

			$notifications[] = [
				'type' => 'dm',
				'icon' => 'envelope',
				'severity' => 'danger',
				'label' => t('Private'),
				'title' => t('Unseen private activity'),
				'viewall' => [
					'url' => 'network/?dm=1',
					'label' => t('Private stream')
				],
				'markall' => [
					'label' => t('Mark all read')
				],
				'filter' => [
					'posts_label' => t('Conversation starters'),
					'name_label' => t('Filter by name or address')
				]
			];

			$notifications[] = [
				'type' => 'all_events',
				'icon' => 'calendar-date',
				'severity' => 'secondary',
				'label' => t('Events'),
				'title' => t('Unseen events activity'),
				'viewall' => [
					'url' => 'cdav/calendar',
					'label' => t('View events')
				],
				'markall' => [
					'label' => t('Mark all seen')
				]
			];

			$notifications[] = [
				'type' => 'intros',
				'icon' => 'people',
				'severity' => 'danger',
				'label' => t('New Connections'),
				'title' => t('New connections'),
				'viewall' => [
					'url' => 'connections',
					'label' => t('View all')
				]
			];

			$notifications[] = [
				'type' => 'files',
				'icon' => 'folder',
				'severity' => 'danger',
				'label' => t('Files'),
				'title' => t('Useen files activity'),
			];

			$notifications[] = [
				'type' => 'notify',
				'icon' => 'exclamation-circle',
				'severity' => 'danger',
				'label' => t('Notifications'),
				'title' => t('Unseen notifications'),
				'viewall' => [
					'url' => 'notifications/system',
					'label' => t('View all')
				],
				'markall' => [
					'label' => t('Mark all seen')
				]
			];

    $forums = get_forum_channels(local_channel());
			foreach($forums as $forum) {
				$notifications[] = [
					'type' => 'forum_' . $forum['abook_id'],
					'icon' => 'chat-quote',
					'severity' => 'secondary',
					'label' => $forum['xchan_name'],
					'title' => t('Unseen forum activity'),
					'filter' => [
						'posts_label' => t('Conversation starters'),
						'name_label' => t('Filter by name or address')
					],
					'viewall' => [
						'url' => 'network?pf=1&cid=' . $forum['abook_id'],
						'label' => t('View all')
					],
					'markall' => [
						'label' => t('Mark all seen')
					],
        ];
      }

    }
    
		if(local_channel() && is_site_admin()) {
			$notifications[] = [
				'type' => 'register',
				'icon' => 'person-exclamation',
				'severity' => 'danger',
				'label' => t('Registrations'),
				'title' => t('Unseen registration activity'),
			];
		}

		if(can_view_public_stream()) {
			$notifications[] = [
				'type' => 'pubs',
				'icon' => 'globe',
				'severity' => 'secondary',
				'label' => t('Public Stream'),
				'title' => t('Unseen public stream activity'),
				'viewall' => [
					'url' => 'pubstream',
					'label' => t('Public stream')
				],
				'markall' => [
					'label' => t('Mark all notifications seen')
				],
				'filter' => [
					'posts_label' => t('Conversation starters'),
					'name_label' => t('Filter by name or address')
				]
			];
		}

    // You can also add entirely new keys if needed:
    $x['nav']['ntd'] = [
        'notifications' => $notifications,
        'no_notifications' => t("No Notifications"),
        'loading' => t("Loading"),
        'sys_only' => empty($arr["sys_only"]) ? 0 : 1,
			  'count_limit' => get_pconfig(local_channel(), 'system', 'notifications_count_limit', 100),

    ];
    logger("notification hook out " . $x['nav']['ntd']['no_notifications'], LOGGER_DEBUG);
}
