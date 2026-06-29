<?php

namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;

class Profiles
{
    public function get(): void
    {
        $uid = Auth::requireLocalGet();
        require_once('include/features.php');

        $multi_enabled = (bool) feature_enabled($uid, 'multi_profiles');
        $id  = \App::$argv[2] ?? null;
        $sub = \App::$argv[3] ?? null;

        if ($id && ctype_digit((string) $id)) {
            if ($sub === 'contacts') {
                $this->getProfileContacts($uid, intval($id));
            } else {
                $this->getProfile($uid, intval($id));
            }
        } else {
            $this->listProfiles($uid, $multi_enabled);
        }
    }

    private function listProfiles(int $uid, bool $multi_enabled): void
    {
        $profiles = q(
            "SELECT id, profile_name, is_default, pdesc, fullname
             FROM profile
             WHERE uid = %d
             ORDER BY is_default DESC, profile_name ASC",
            intval($uid)
        );

        $result     = [];
        $default_id = null;
        foreach (($profiles ?: []) as $p) {
            if ($p['is_default']) $default_id = intval($p['id']);
            // When the feature is disabled, expose only the default profile.
            if (!$multi_enabled && !$p['is_default']) continue;
            $result[] = [
                'id'           => intval($p['id']),
                'profile_name' => $p['profile_name'] ?? '',
                'is_default'   => (bool) $p['is_default'],
                'fullname'     => $p['fullname'] ?? '',
                'pdesc'        => $p['pdesc'] ?? '',
            ];
        }

        Response::send($result, [
            'multi_profiles_enabled' => $multi_enabled,
            'default_profile_id'     => $default_id,
        ]);
    }

    private function getProfile(int $uid, int $id): void
    {
        $profile = q(
            "SELECT * FROM profile WHERE id = %d AND uid = %d LIMIT 1",
            intval($id),
            intval($uid)
        );

        if (!$profile) {
            Response::error(404, 'Profile not found');
        }

        Response::send($this->formatProfile($profile[0], $uid));
    }

    private function formatProfile(array $p, int $uid = 0): array
    {
        $avatar_l  = null;
        $cover_url = null;

        if ($uid) {
            require_once('include/channel.php');
            $avatar_l  = z_root() . '/photo/profile/l/' . $uid;
            $cover     = get_cover_photo($uid, 'array', PHOTO_RES_COVER_1200);
            $def_cover = \Zotlabs\Lib\Config::Get('system', 'default_cover_photo', 'hubzilla');
            $cover_url = $cover
                ? $cover['url']
                : z_root() . '/images/default_cover_photos/' . $def_cover . '/1200.png';
        }

        return [
            'id'           => intval($p['id']),
            'profile_name' => $p['profile_name'] ?? '',
            'is_default'   => (bool) $p['is_default'],
            'fullname'     => $p['fullname'] ?? '',
            'pdesc'        => $p['pdesc'] ?? '',
            'homepage'     => $p['homepage'] ?? '',
            'hometown'     => $p['hometown'] ?? '',
            'gender'       => $p['gender'] ?? '',
            'dob'          => $p['dob'] ?? '',
            'about'        => $p['about'] ?? '',
            'keywords'     => $p['keywords'] ?? '',
            'hide_friends' => intval($p['hide_friends'] ?? 0),
            'publish'      => intval($p['publish'] ?? 0),
            'marital'      => $p['marital'] ?? '',
            'sexual'       => $p['sexual'] ?? '',
            'politic'      => $p['politic'] ?? '',
            'religion'     => $p['religion'] ?? '',
            'music'        => $p['music'] ?? '',
            'book'         => $p['book'] ?? '',
            'tv'           => $p['tv'] ?? '',
            'film'         => $p['film'] ?? '',
            'interest'     => $p['interest'] ?? '',
            'romance'      => $p['romance'] ?? '',
            'employment'   => $p['employment'] ?? '',
            'education'    => $p['education'] ?? '',
            'likes'        => $p['likes'] ?? '',
            'dislikes'     => $p['dislikes'] ?? '',
            'contact'      => $p['contact'] ?? '',
            'channels'     => $p['channels'] ?? '',
            'avatar_l'     => $avatar_l,
            'cover_url'    => $cover_url,
        ];
    }

    public function post(): void
    {
        $uid  = Auth::requireLocalJson();
        $data = Auth::$parsedBody;

        if (!$data) {
            Response::error(400, 'Invalid JSON body');
        }

        require_once('include/features.php');
        $multi_enabled = feature_enabled($uid, 'multi_profiles');

        $segment = \App::$argv[2] ?? null;
        $action  = \App::$argv[3] ?? null;

        if ($segment === 'new') {
            if (!$multi_enabled)
                Response::error(403, 'Multiple profiles feature is not enabled');
            $this->createProfile($uid, $data);
        } elseif ($segment && ctype_digit((string) $segment) && $action === 'delete') {
            if (!$multi_enabled)
                Response::error(403, 'Multiple profiles feature is not enabled');
            $this->deleteProfile($uid, intval($segment));
        } elseif ($segment && ctype_digit((string) $segment) && $action === 'contacts') {
            $this->toggleProfileContact($uid, intval($segment), $data);
        } elseif ($segment && ctype_digit((string) $segment)) {
            $this->updateProfile($uid, intval($segment), $data);
        } else {
            Response::error(400, 'Invalid request');
        }
    }

    private function createProfile(int $uid, array $data): void
    {
        $default = q(
            "SELECT * FROM profile WHERE uid = %d AND is_default = 1 LIMIT 1",
            intval($uid)
        );

        $profile_name = notags(trim($data['profile_name'] ?? 'New Profile'));
        if (!$profile_name) $profile_name = 'New Profile';

        $guid = new_uuid();

        if ($default) {
            $d = $default[0];
            q(
                "INSERT INTO profile
                 (uid, profile_guid, profile_name, is_default, fullname, pdesc, homepage, hometown, gender, dob, about, keywords, hide_friends)
                 VALUES (%d, '%s', '%s', 0, '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', %d)",
                intval($uid),
                dbesc($guid),
                dbesc($profile_name),
                dbesc($d['fullname'] ?? ''),
                dbesc($d['pdesc'] ?? ''),
                dbesc($d['homepage'] ?? ''),
                dbesc($d['hometown'] ?? ''),
                dbesc($d['gender'] ?? ''),
                dbesc($d['dob'] ?? ''),
                dbesc($d['about'] ?? ''),
                dbesc($d['keywords'] ?? ''),
                0
            );
        } else {
            q(
                "INSERT INTO profile (uid, profile_guid, profile_name, is_default)
                 VALUES (%d, '%s', '%s', 0)",
                intval($uid),
                dbesc($guid),
                dbesc($profile_name)
            );
        }

        $row = q(
            "SELECT id FROM profile WHERE uid = %d AND profile_guid = '%s' LIMIT 1",
            intval($uid),
            dbesc($guid)
        );

        Response::send(['status' => 'ok', 'id' => $row ? intval($row[0]['id']) : 0]);
    }

    private function updateProfile(int $uid, int $id, array $data): void
    {
        $profile = q(
            "SELECT * FROM profile WHERE id = %d AND uid = %d LIMIT 1",
            intval($id),
            intval($uid)
        );

        if (!$profile) {
            Response::error(404, 'Profile not found');
        }

        $p          = $profile[0];
        $is_default = (bool) $p['is_default'];

        $f = [
            'profile_name' => notags(trim($data['profile_name'] ?? $p['profile_name'])),
            'fullname'     => notags(trim($data['fullname']     ?? $p['fullname'])),
            'pdesc'        => notags(trim($data['pdesc']        ?? $p['pdesc'])),
            'homepage'     => notags(trim($data['homepage']     ?? $p['homepage'])),
            'hometown'     => notags(trim($data['hometown']     ?? $p['hometown'])),
            'gender'       => notags(trim($data['gender']       ?? $p['gender'])),
            'dob'          => notags(trim($data['dob']          ?? $p['dob'])),
            'about'        => escape_tags($data['about']        ?? $p['about']),
            'keywords'     => notags(trim($data['keywords']     ?? $p['keywords'])),
            'hide_friends' => intval($data['hide_friends']      ?? $p['hide_friends']),
            'publish'      => intval($data['publish']           ?? $p['publish']),
            'marital'      => notags(trim($data['marital']      ?? $p['marital'])),
            'sexual'       => notags(trim($data['sexual']       ?? $p['sexual'])),
            'politic'      => notags(trim($data['politic']      ?? $p['politic'])),
            'religion'     => notags(trim($data['religion']     ?? $p['religion'])),
            'music'        => notags(trim($data['music']        ?? $p['music'])),
            'book'         => notags(trim($data['book']         ?? $p['book'])),
            'tv'           => notags(trim($data['tv']           ?? $p['tv'])),
            'film'         => notags(trim($data['film']         ?? $p['film'])),
            'interest'     => notags(trim($data['interest']     ?? $p['interest'])),
            'romance'      => notags(trim($data['romance']      ?? $p['romance'])),
            'employment'   => notags(trim($data['employment']   ?? $p['employment'])),
            'education'    => notags(trim($data['education']    ?? $p['education'])),
            'likes'        => notags(trim($data['likes']        ?? $p['likes'])),
            'dislikes'     => notags(trim($data['dislikes']     ?? $p['dislikes'])),
            'contact'      => notags(trim($data['contact']      ?? $p['contact'])),
            'channels'     => notags(trim($data['channels']     ?? $p['channels'])),
        ];

        q(
            "UPDATE profile SET
             profile_name = '%s', fullname = '%s', pdesc = '%s',
             homepage = '%s', hometown = '%s', gender = '%s', dob = '%s',
             about = '%s', keywords = '%s', hide_friends = %d, publish = %d,
             marital = '%s', sexual = '%s', politic = '%s', religion = '%s',
             music = '%s', book = '%s', tv = '%s', film = '%s',
             interest = '%s', romance = '%s', employment = '%s', education = '%s',
             likes = '%s', dislikes = '%s', contact = '%s', channels = '%s'
             WHERE id = %d AND uid = %d",
            dbesc($f['profile_name']),  dbesc($f['fullname']),   dbesc($f['pdesc']),
            dbesc($f['homepage']),      dbesc($f['hometown']),   dbesc($f['gender']),
            dbesc($f['dob']),           dbesc($f['about']),      dbesc($f['keywords']),
            intval($f['hide_friends']),   intval($f['publish']),
            dbesc($f['marital']),       dbesc($f['sexual']),     dbesc($f['politic']),
            dbesc($f['religion']),
            dbesc($f['music']),         dbesc($f['book']),       dbesc($f['tv']),
            dbesc($f['film']),
            dbesc($f['interest']),      dbesc($f['romance']),    dbesc($f['employment']),
            dbesc($f['education']),
            dbesc($f['likes']),         dbesc($f['dislikes']),
            dbesc($f['contact']),       dbesc($f['channels']),
            intval($id),                intval($uid)
        );

        // Propagate name change to channel for the default profile
        if ($is_default && $f['fullname']) {
            q(
                "UPDATE channel SET channel_name = '%s' WHERE channel_id = %d",
                dbesc($f['fullname']),
                intval($uid)
            );
        }

        // Sync xchan_hidden immediately when publish changes on the default profile
        if ($is_default) {
            $channel = q("SELECT channel_hash FROM channel WHERE channel_id = %d LIMIT 1", intval($uid));
            if ($channel) {
                $hidden = 1 - $f['publish'];
                q("UPDATE xchan SET xchan_hidden = %d WHERE xchan_hash = '%s'",
                    intval($hidden),
                    dbesc($channel[0]['channel_hash'])
                );
            }
        }

        Response::send(['status' => 'ok']);
    }

    private function getProfileContacts(int $uid, int $profile_id): void
    {
        $profile = q(
            "SELECT id FROM profile WHERE id = %d AND uid = %d LIMIT 1",
            intval($profile_id),
            intval($uid)
        );
        if (!$profile) Response::error(404, 'Profile not found');

        $rows = q(
            "SELECT abook.abook_id, xchan.xchan_hash, xchan.xchan_name,
                    xchan.xchan_addr, xchan.xchan_photo_m
             FROM abook
             LEFT JOIN xchan ON abook.abook_xchan = xchan.xchan_hash
             WHERE abook.abook_channel = %d
               AND abook.abook_profile = %d
               AND abook.abook_self    = 0
               AND xchan.xchan_deleted = 0
             ORDER BY xchan.xchan_name ASC",
            intval($uid),
            intval($profile_id)
        );

        Response::send(array_map(fn($r) => [
            'abook_id' => intval($r['abook_id']),
            'xchan_hash' => $r['xchan_hash'],
            'name'       => $r['xchan_name'] ?? '',
            'address'    => $r['xchan_addr'] ?? '',
            'photo'      => $r['xchan_photo_m'] ?? '',
        ], $rows ?? []));
    }

    private function toggleProfileContact(int $uid, int $profile_id, array $data): void
    {
        $profile = q(
            "SELECT id, is_default FROM profile WHERE id = %d AND uid = %d LIMIT 1",
            intval($profile_id),
            intval($uid)
        );
        if (!$profile) Response::error(404, 'Profile not found');
        if ($profile[0]['is_default']) Response::error(400, 'Cannot assign contacts to the default profile');

        $abook_id = intval($data['abook_id'] ?? 0);
        if (!$abook_id) Response::error(400, 'abook_id required');

        $abook = q(
            "SELECT abook_id, abook_profile FROM abook
             WHERE abook_id = %d AND abook_channel = %d LIMIT 1",
            intval($abook_id),
            intval($uid)
        );
        if (!$abook) Response::error(404, 'Connection not found');

        // Toggle: clear if already assigned to this profile, otherwise assign
        $currently = intval($abook[0]['abook_profile']) === $profile_id;
        $new_id    = $currently ? 0 : $profile_id;

        q(
            "UPDATE abook SET abook_profile = %d WHERE abook_id = %d AND abook_channel = %d",
            intval($new_id),
            intval($abook_id),
            intval($uid)
        );

        Response::send([
            'abook_id'   => $abook_id,
            'profile_id' => $new_id > 0 ? $new_id : null,
            'assigned'   => $new_id > 0,
        ]);
    }

    private function deleteProfile(int $uid, int $id): void
    {
        $profile = q(
            "SELECT id, is_default FROM profile WHERE id = %d AND uid = %d LIMIT 1",
            intval($id),
            intval($uid)
        );

        if (!$profile) {
            Response::error(404, 'Profile not found');
        }

        if ($profile[0]['is_default']) {
            Response::error(400, 'Cannot delete the default profile');
        }

        q("DELETE FROM profile WHERE id = %d AND uid = %d", intval($id), intval($uid));

        Response::send(['status' => 'ok']);
    }
}
