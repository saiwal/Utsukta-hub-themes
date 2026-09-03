<?php
// Api/Concerns/CachesRanking.php
namespace Utsukta\SpaCore\Api\Concerns;

use Zotlabs\Lib\Cache;

// Caches the ordered id list a ranked stream sort produces.
//
// Ranking is the expensive half of a ranked page: it sorts every candidate in
// the stream, while fetching the ten rows it selected is trivial. Worse,
// pagination is LIMIT/OFFSET, so scrolling to page 5 used to re-rank the whole
// set five times. Caching the ordered ids collapses a whole scroll session —
// and every revisit inside the window — into one ranking pass.
//
// Only ranked orders go through here. The chronological orders walk an index
// and stop at LIMIT; they're already cheap and stay live.
trait CachesRanking
{
    // A ranked view may lag this far behind new likes. Live polling is already
    // disabled client-side under ranked orders, so nothing contradicts it.
    private const RANK_CACHE_AGE = '15 MINUTE';

    // How many ranked ids to keep. The database still ranks the *whole*
    // candidate set — this only bounds how much of the sorted output we carry
    // around, i.e. how deep pagination can go before falling back to a plain
    // paged query. ~100 pages at the default page size.
    private const RANK_CACHE_DEPTH = 1000;

    protected function rankCacheDepth(): int
    {
        return self::RANK_CACHE_DEPTH;
    }

    // True when this page fits inside the cached window.
    protected function rankCacheCovers(int $offset, int $limit): bool
    {
        return $offset + $limit <= self::RANK_CACHE_DEPTH;
    }

    /**
     * The ranked id list, from cache when warm.
     *
     * @param callable():array $rank Runs the ranking query and returns up to
     *        rankCacheDepth() ordered ids. Only called on a miss.
     * @return array{ids: array, cached: bool}
     */
    protected function rankedIds(string $key, callable $rank): array
    {
        $hit = Cache::get($key, self::RANK_CACHE_AGE);
        if ($hit !== null) {
            $decoded = json_decode($hit, true);
            if (is_array($decoded)) {
                return ['ids' => $decoded, 'cached' => true];
            }
        }

        $ids = $rank();
        Cache::set($key, json_encode($ids));

        return ['ids' => $ids, 'cached' => false];
    }

    /**
     * Cache key for one ranked view.
     *
     * Every filter has to be in the key: a tag-filtered stream and an
     * unfiltered one produce different rankings and must not serve each
     * other's. Params are sorted so query-string order can't split the cache,
     * and `start` is excluded on purpose — one ranking serves all its pages.
     */
    protected function rankCacheKey(string $scope, int $uid, string $order, array $params): string
    {
        unset($params['start'], $params['order']);
        $params = array_filter($params, fn($v) => $v !== '' && $v !== null && $v !== 0);
        ksort($params);

        return "spa:rank:$scope:$uid:$order:" . md5(json_encode($params));
    }
}
