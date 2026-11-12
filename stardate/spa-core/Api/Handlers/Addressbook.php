<?php
namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Auth;
use Utsukta\SpaCore\Api\Response;
use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Libsync;

require_once('include/event.php');   // cdav_principal(), cdav_perms(), translate_type()
require_once('include/cdav.php');    // process_cdav_card()

/**
 * CardDAV address books.
 *
 * GET  /spa/addressbook                  — list the channel's address books
 * GET  /spa/addressbook/:id              — address book :id plus its cards
 *
 * POST /spa/addressbook/create           { name }
 * POST /spa/addressbook/:id/edit         { name }
 * POST /spa/addressbook/:id/delete
 * POST /spa/addressbook/:id/card/create  { fn, org, title, note, tel[], tel_type[],
 *                                          email[], email_type[], impp[], impp_type[],
 *                                          url[], url_type[], adr[][], adr_type[] }
 * POST /spa/addressbook/:id/card/update  { uri, ...same fields }
 * POST /spa/addressbook/:id/card/delete  { uri }
 *
 * Card uris travel in the body rather than the path, matching core's
 * $_REQUEST['uri'] and avoiding ".vcf" path-segment handling.
 *
 * Backwards compatibility: every write goes through \Sabre\CardDAV\Backend\PDO.
 * Those methods set etag/size/lastmodified and call the *protected* addChange(),
 * which writes the addressbookchanges row and bumps addressbooks.synctoken. Touch
 * the tables directly and DAV clients silently stop syncing. Each mutation also
 * emits the same Libsync packet core does, so clone hubs stay in step.
 */
class Addressbook
{
    private array $channel;
    private string $principalUri;
    private \Sabre\CardDAV\Backend\PDO $backend;

    public function get(): void
    {
        $uid = Auth::requireLocalGet();
        $this->boot($uid, true);

        $arg = \App::$argv[2] ?? '';
        if ($arg === '') {
            $this->listBooks();
        }

        $id = intval($arg);
        if (!$id) {
            Response::error(400, 'Invalid address book id');
        }
        $this->listCards($this->requireBook($id));
    }

    public function post(): void
    {
        $uid = Auth::requireLocalJson();
        $this->boot($uid, false);

        $body = Auth::$parsedBody;
        $arg2 = \App::$argv[2] ?? '';

        if ($arg2 === 'create') {
            $this->createBook($body);
        }

        $id = intval($arg2);
        if (!$id) {
            Response::error(400, 'Invalid address book id');
        }
        $book = $this->requireBook($id);

        if ((\App::$argv[3] ?? '') === 'card') {
            match (\App::$argv[4] ?? '') {
                'create' => $this->createCard($book, $body),
                'update' => $this->updateCard($book, $body),
                'delete' => $this->deleteCard($book, $body),
                default  => Response::error(400, 'Unknown card action'),
            };
        }

        match (\App::$argv[3] ?? '') {
            'edit'   => $this->editBook($book, $body),
            'delete' => $this->deleteBook($book),
            default  => Response::error(400, 'Unknown action'),
        };
    }

    // ── setup ─────────────────────────────────────────────────────────────────

    private function boot(int $uid, bool $activate): void
    {
        require_once 'vendor/autoload.php';

        if (!Apps::system_app_installed($uid, 'CardDAV')) {
            Response::error(403, 'The CardDAV app is not installed');
        }

        $channel = \App::get_channel();
        if (!$channel) {
            Response::error(403, 'Not logged in');
        }

        $this->channel      = $channel;
        $this->principalUri = 'principals/' . $channel['channel_address'];

        // Core creates the principal lazily when /cdav/addressbook is first
        // visited. The SPA owns that URL now, so core never runs for a channel
        // that has only ever used the SPA. Call core's own activate() rather
        // than reimplementing it — it also creates the default *calendar*, and
        // creating only the address book here would leave the channel without one.
        if (!cdav_principal($this->principalUri)) {
            if (!$activate) {
                Response::error(403, 'CardDAV not available for this channel');
            }
            (new \Zotlabs\Module\Cdav())->activate(\DBA::$dba->db, $channel);
        }

        $this->backend = new \Sabre\CardDAV\Backend\PDO(\DBA::$dba->db);
    }

    /** Resolve an address book id to its row, or bail. */
    private function requireBook(int $id): array
    {
        $books = $this->backend->getAddressBooksForUser($this->principalUri);

        // getAddressBooksForUser() is principal-scoped, so matching the id
        // against it is the ownership check. Core does the same via cdav_perms().
        if (!cdav_perms($id, $books)) {
            Response::error(403, 'Permission denied');
        }

        foreach ($books as $book) {
            if (intval($book['id']) === $id) {
                return $book;
            }
        }

        Response::error(404, 'Address book not found');
    }

    private function bookToArray(array $book): array
    {
        return [
            'id'          => intval($book['id']),
            'uri'         => $book['uri'],
            'displayname' => Response::decodeEntities($book['{DAV:}displayname'] ?: 'Addressbook'),
            'exportUrl'   => '/cdav/addressbooks/' . $this->channel['channel_address']
                             . '/' . $book['uri'] . '/?export',
        ];
    }

    // ── read ──────────────────────────────────────────────────────────────────

    private function listBooks(): never
    {
        $out = [];
        foreach ($this->backend->getAddressBooksForUser($this->principalUri) as $book) {
            $out[] = $this->bookToArray($book);
        }

        Response::send($out);
    }

    private function listCards(array $book): never
    {
        $id   = intval($book['id']);
        $rows = $this->backend->getCards($id);
        $uris = array_column($rows, 'uri');

        $cards = [];
        if ($uris) {
            foreach ($this->backend->getMultipleCards($id, $uris) as $object) {
                $cards[] = $this->cardToArray(
                    \Sabre\VObject\Reader::read($object['carddata']),
                    $object
                );
            }
        }

        usort($cards, fn($a, $b) => strcasecmp($a['fn'], $b['fn']));

        Response::send([
            'addressbook' => $this->bookToArray($book),
            'cards'       => $cards,
        ]);
    }

    /**
     * Mirrors the extraction loop in Zotlabs\Module\Cdav::get().
     *
     * Deliberately not get_vcard_array() from include/connections.php: that uses
     * vcard_translate_type(), which returns only the translated label. The editor
     * needs the raw TYPE to round-trip, which translate_type() gives us.
     */
    private function cardToArray(\Sabre\VObject\Component\VCard $vc, array $row): array
    {
        $photo = '';
        if ($vc->PHOTO) {
            if (strtolower((string)$vc->PHOTO->getValueType()) === 'binary') {
                $photo = 'data:image/' . strtolower((string)$vc->PHOTO['TYPE'])
                         . ';base64,' . base64_encode((string)$vc->PHOTO);
            } else {
                $url   = parse_url((string)$vc->PHOTO);
                $photo = 'data:' . ($url['path'] ?? '');
            }
        }

        return [
            'id'     => intval($row['id']),
            'uri'    => $row['uri'],
            'photo'  => $photo,
            'fn'     => (string)($vc->FN ?? ''),
            'org'    => (string)($vc->ORG ?? ''),
            'title'  => (string)($vc->TITLE ?? ''),
            'note'   => (string)($vc->NOTE ?? ''),
            'tels'   => $this->typedValues($vc->TEL),
            'emails' => $this->typedValues($vc->EMAIL),
            'impps'  => $this->typedValues($vc->IMPP),
            'urls'   => $this->typedValues($vc->URL),
            'adrs'   => $this->typedAddresses($vc->ADR),
        ];
    }

    private function typedValues($nodes): array
    {
        $out = [];
        if (!$nodes) {
            return $out;
        }
        foreach ($nodes as $node) {
            [$type, $label] = $this->splitType((string)$node['TYPE']);
            $out[] = ['type' => $type, 'label' => $label, 'value' => (string)$node];
        }

        return $out;
    }

    private function typedAddresses($nodes): array
    {
        $out = [];
        if (!$nodes) {
            return $out;
        }
        foreach ($nodes as $node) {
            [$type, $label] = $this->splitType((string)$node['TYPE']);
            $parts = $node->getParts();
            $parts = is_array($parts) ? $parts : [(string)$parts];
            // ADR is 7 components: po box, extended, street, locality, region,
            // postal code, country. A component may itself be multi-valued.
            $out[] = [
                'type'  => $type,
                'label' => $label,
                'parts' => array_map(
                    fn($p) => is_array($p) ? implode(',', $p) : (string)$p,
                    array_pad($parts, 7, '')
                ),
            ];
        }

        return $out;
    }

    /** translate_type() returns [RAW, Translated], or null for an empty type. */
    private function splitType(string $type): array
    {
        if ($type === '') {
            return ['', ''];
        }
        $t = translate_type($type);

        return is_array($t) ? [(string)$t[0], (string)$t[1]] : [$type, $type];
    }

    // ── write: address books ──────────────────────────────────────────────────

    private function createBook(array $body): never
    {
        $name = trim(escape_tags((string)($body['name'] ?? '')));
        if ($name === '') {
            Response::error(400, 'Address book name is required');
        }

        do {
            $uri = random_string(20);
            $dup = q("SELECT uri FROM addressbooks WHERE principaluri = '%s' AND uri = '%s' LIMIT 1",
                dbesc($this->principalUri), dbesc($uri)
            );
        } while ($dup);

        $properties = ['{DAV:}displayname' => $name];
        $id = $this->backend->createAddressBook($this->principalUri, $uri, $properties);

        Libsync::build_sync_packet($this->channel['channel_id'], [
            'addressbook' => [
                'action'     => 'create',
                'uri'        => $uri,
                'properties' => $properties,
            ],
        ]);

        Response::send($this->bookToArray([
            'id'                => $id,
            'uri'               => $uri,
            '{DAV:}displayname' => $name,
        ]));
    }

    private function editBook(array $book, array $body): never
    {
        $name = trim(escape_tags((string)($body['name'] ?? '')));
        if ($name === '') {
            Response::error(400, 'Address book name is required');
        }

        $mutations = ['{DAV:}displayname' => $name];
        $patch = new \Sabre\DAV\PropPatch($mutations);
        $this->backend->updateAddressBook(intval($book['id']), $patch);
        $patch->commit();

        Libsync::build_sync_packet($this->channel['channel_id'], [
            'addressbook' => [
                'action'    => 'edit',
                'uri'       => $book['uri'],
                'mutations' => $mutations,
            ],
        ]);

        Response::send($this->bookToArray(array_merge($book, ['{DAV:}displayname' => $name])));
    }

    private function deleteBook(array $book): never
    {
        // deleteAddressBook() clears cards, addressbooks and addressbookchanges.
        $this->backend->deleteAddressBook(intval($book['id']));

        Libsync::build_sync_packet($this->channel['channel_id'], [
            'addressbook' => [
                'action' => 'drop',
                'uri'    => $book['uri'],
            ],
        ]);

        Response::send(['deleted' => true]);
    }

    // ── write: cards ──────────────────────────────────────────────────────────

    private function createCard(array $book, array $body): never
    {
        $id = intval($book['id']);
        $fn = trim((string)($body['fn'] ?? ''));
        if ($fn === '') {
            Response::error(400, 'A name is required');
        }

        do {
            $uri = random_string(40) . '.vcf';
            $dup = q("SELECT uri FROM cards WHERE addressbookid = %d AND uri = '%s' LIMIT 1",
                intval($id), dbesc($uri)
            );
        } while ($dup);

        $vcard = new \Sabre\VObject\Component\VCard([
            'FN' => $fn,
            'N'  => array_reverse(explode(' ', $fn)),
        ]);

        $fields = $this->fieldsFromBody($body);
        process_cdav_card($fields, $vcard);

        $cardData = $vcard->serialize();
        $this->backend->createCard($id, $uri, $cardData);

        // Core emits 'import' for a newly created card — sync_addressbook() has
        // no 'create_card' action.
        Libsync::build_sync_packet($this->channel['channel_id'], [
            'addressbook' => [
                'action' => 'import',
                'uri'    => $book['uri'],
                'ids'    => [$uri],
                'card'   => $cardData,
            ],
        ]);

        Response::send(['uri' => $uri]);
    }

    private function updateCard(array $book, array $body): never
    {
        $id  = intval($book['id']);
        $uri = trim((string)($body['uri'] ?? ''));
        if ($uri === '') {
            Response::error(400, 'Card uri is required');
        }

        $object = $this->backend->getCard($id, $uri);
        if (!$object) {
            Response::error(404, 'Card not found');
        }

        $vcard = \Sabre\VObject\Reader::read($object['carddata']);

        $fn = trim((string)($body['fn'] ?? ''));
        if ($fn !== '') {
            $vcard->FN = $fn;
            $vcard->N  = array_reverse(explode(' ', $fn));
        }

        $fields = $this->fieldsFromBody($body);
        process_cdav_card($fields, $vcard, true);

        $cardData = $vcard->serialize();
        $this->backend->updateCard($id, $uri, $cardData);

        Libsync::build_sync_packet($this->channel['channel_id'], [
            'addressbook' => [
                'action'  => 'update_card',
                'uri'     => $book['uri'],
                'carduri' => $uri,
                'card'    => $cardData,
            ],
        ]);

        Response::send(['uri' => $uri]);
    }

    private function deleteCard(array $book, array $body): never
    {
        $id  = intval($book['id']);
        $uri = trim((string)($body['uri'] ?? ''));
        if ($uri === '') {
            Response::error(400, 'Card uri is required');
        }

        $this->backend->deleteCard($id, $uri);

        Libsync::build_sync_packet($this->channel['channel_id'], [
            'addressbook' => [
                'action'  => 'delete_card',
                'uri'     => $book['uri'],
                'carduri' => $uri,
            ],
        ]);

        Response::send(['deleted' => true]);
    }

    /**
     * Build the field array process_cdav_card() expects — the same shape
     * Cdav::request_to_array() produces from $_REQUEST.
     *
     * An absent or empty scalar is falsy, which is what makes process_cdav_card()
     * unset the property on edit. That matches the core form exactly, including
     * its habit of rebuilding TEL/EMAIL/IMPP/URL/ADR from scratch.
     */
    private function fieldsFromBody(array $body): array
    {
        $str = fn(string $k) => trim((string)($body[$k] ?? ''));
        $arr = function (string $k) use ($body): array {
            $v = $body[$k] ?? [];
            if (!is_array($v)) {
                return [];
            }

            // ADR entries are themselves arrays of components; everything else
            // is a flat list of strings.
            return array_values(array_map(fn($x) => is_array($x) ? $x : (string)$x, $v));
        };

        return [
            'org'        => $str('org'),
            'title'      => $str('title'),
            'note'       => $str('note'),
            'tel'        => $arr('tel'),
            'tel_type'   => $arr('tel_type'),
            'email'      => $arr('email'),
            'email_type' => $arr('email_type'),
            'impp'       => $arr('impp'),
            'impp_type'  => $arr('impp_type'),
            'url'        => $arr('url'),
            'url_type'   => $arr('url_type'),
            'adr'        => $arr('adr'),
            'adr_type'   => $arr('adr_type'),
        ];
    }
}
