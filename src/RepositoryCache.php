<?php

/**
 * Author: Nil Portugués Calderó <contact@nilportugues.com>
 * Date: 26/01/16
 * Time: 23:55.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace NilPortugues\Foundation\Infrastructure\Model\Repository\Cache;

use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Fields;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Filter;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Identity;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Page;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Pageable;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\PageRepository;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\ReadRepository;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Sort;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\WriteRepository;
use Psr\Cache\CacheItemPoolInterface;
use Stash\Interfaces\PoolInterface;

class RepositoryCache implements ReadRepository, WriteRepository, PageRepository
{
    /** @var CacheItemPoolInterface */
    protected $cache;
    /** @var ReadRepository|WriteRepository|PageRepository */
    protected $repository;
    /** @var string */
    protected $cacheNamespace;
    /** @var string */
    protected $cacheNamespaceFindBy;
    /** @var string */
    protected $cacheNamespaceFindByDistinct;
    /** @var string */
    protected $cacheNamespacePage;
    /** @var string */
    protected $cacheNamespaceCount;
    /** @var null */
    protected $cacheTime = null;
    /** @var  string */
    protected $cacheNamespaceExists;

    /**
     * RepositoryCache constructor.
     *
     * @param PoolInterface $cache
     * @param mixed $repository
     * @param string $classFQN
     * @param null $cacheTime
     */
    public function __construct(PoolInterface $cache, $repository, $classFQN, $cacheTime = null)
    {
        $this->cache = $cache;
        $this->repository = $repository;
        $this->cacheTime = ($cacheTime) ? strtotime(sprintf('now + %s seconds', $cacheTime)) : $cacheTime;

        $this->buildCacheKeys($classFQN);
    }

    /**
     * Creates the cache key for the repository.
     *
     * @param string $classFQN
     */
    protected function buildCacheKeys($classFQN)
    {
        $parts = explode('\\', $classFQN);
        $classFQN = array_pop($parts);

        $baseKey = str_replace('\\', '.', strtolower($classFQN));
        $this->cacheNamespace = sprintf('/%s/', $baseKey);
        $this->cacheNamespaceFindBy = sprintf('/%s/findby/', $baseKey);
        $this->cacheNamespaceFindByDistinct = sprintf('/%s/finddistinct/', $baseKey);
        $this->cacheNamespacePage = sprintf('/%s/paginated/', $baseKey);
        $this->cacheNamespaceCount = sprintf('/%s/count/', $baseKey);
        $this->cacheNamespaceExists = sprintf('/%s/exists/', $baseKey);
    }

    /**
     * {@inheritdoc}
     */
    public function find(Identity $id, Fields $fields = null)
    {
        $key = $this->cacheNamespace . $id->id();
        $cachedItem = $this->cache->getItem($key);

        if ($cachedItem->isHit() && null !== ($result = $cachedItem->get())) {
            return $result;
        }

        $entity = $this->repository->find($id, $fields);
        $this->saveToCache($cachedItem, $entity);

        return $entity;
    }

    /**
     * @param $cachedItem
     * @param $result
     */
    protected function saveToCache($cachedItem, $result)
    {
        $cachedItem->set($result);
        $cachedItem->expiresAfter($this->cacheTime);
        $cachedItem->save();
    }

    /**
     * {@inheritdoc}
     */
    public function findBy(Filter $filter = null, Sort $sort = null, Fields $fields = null) : array
    {
        $hashFilter = ($filter) ? serialize($filter->filters()) : '';
        $hashSort = ($sort) ? serialize($sort->orders()) : '';
        $hashFields = ($fields) ? serialize($fields->get()) : '';
        $key = $this->cacheNamespaceFindBy . md5($hashFilter . $hashSort . $hashFields);

        $cachedItem = $this->cache->getItem($key);

        if ($cachedItem->isHit() && null !== ($result = $cachedItem->get())) {
            return $result;
        }

        $result = $this->repository->findBy($filter, $sort, $fields);
        $this->saveToCache($cachedItem, $result);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function add(Identity $value)
    {
        $result = $this->repository->add($value);
        $key = $this->cacheNamespace . $value->id();

        $cachedItem = $this->cache->getItem($key);
        $this->saveToCache($cachedItem, $value);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function remove(Identity $id)
    {
        $this->repository->remove($id);

        $keys = [
            $this->cacheNamespace . $id->id(),
            $this->cacheNamespacePage,
            $this->cacheNamespaceFindBy,
            $this->cacheNamespaceFindByDistinct,
            $this->cacheNamespaceCount,
            $this->cacheNamespaceExists,
        ];

        foreach ($keys as $key) {
            $item = $this->cache->getItem($key);
            if ($item) {
                $item->clear();
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function findAll(Pageable $pageable = null): Page
    {
        $key = $this->cacheNamespacePage . md5(serialize($pageable));
        $cachedItem = $this->cache->getItem($key);

        if ($cachedItem->isHit() && null !== ($result = $cachedItem->get())) {
            return $result;
        }

        $result = $this->repository->findAll($pageable);
        $this->saveToCache($cachedItem, $result);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function count(Filter $filter = null) : int
    {
        $key = $this->cacheNamespaceCount . md5(serialize($filter));
        $cachedItem = $this->cache->getItem($key);

        if ($cachedItem->isHit() && null !== ($result = $cachedItem->get())) {
            return $result;
        }

        $result = $this->repository->count($filter);
        $this->saveToCache($cachedItem, $result);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function exists(Identity $id) : bool
    {
        $key = $this->cacheNamespaceExists . $id->id();
        $cachedItem = $this->cache->getItem($key);

        if ($cachedItem->isHit() && null !== ($result = $cachedItem->get())) {
            return $result;
        }

        $result = $this->repository->exists($id);
        $this->saveToCache($cachedItem, $result);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function addAll(array $values)
    {
        $this->repository->addAll($values);
        $this->cachePurge();
    }

    /**
     * Clears all cache, as it's using the root key.
     */
    protected function cachePurge()
    {
        $item = $this->cache->getItem($this->cacheNamespace);
        $item->clear();
    }

    /**
     * {@inheritdoc}
     */
    public function removeAll(Filter $filter = null)
    {
        $this->repository->removeAll($filter);
        $this->cachePurge();
    }

    /**
     * {@inheritdoc}
     */
    public function findByDistinct(Fields $distinctFields, Filter $filter = null, Sort $sort = null) : array
    {
        $hashFilter = ($filter) ? serialize($filter->filters()) : '';
        $hashSort = ($sort) ? serialize($sort->orders()) : '';
        $hashFields = ($distinctFields) ? serialize($distinctFields->get()) : '';
        $key = $this->cacheNamespaceFindByDistinct . md5($hashFilter . $hashSort . $hashFields);

        $cachedItem = $this->cache->getItem($key);

        if ($cachedItem->isHit() && null !== ($result = $cachedItem->get())) {
            return (array)$result;
        }

        $result = $this->repository->findByDistinct($distinctFields, $filter, $sort);
        $this->saveToCache($cachedItem, $result);

        return (array)$result;
    }

    /**
     * {@inheritdoc}
     */
    public function transactional(callable $transaction)
    {
        $this->repository->transactional($transaction);
    }
}
