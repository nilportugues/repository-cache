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
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Pageable;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\PageRepository;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\ReadRepository;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Sort;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\WriteRepository;
use Psr\Cache\CacheItemPoolInterface;
use Stash\Interfaces\PoolInterface;

class RepositoryCache implements ReadRepository, WriteRepository, PageRepository
{
    /**
     * @var CacheItemPoolInterface
     */
    protected $cache;
    /**
     * @var ReadRepository|WriteRepository|PageRepository
     */
    protected $repository;
    /**
     * @var string
     */
    protected $cacheNamespace;
    /**
     * @var string
     */
    protected $cacheNamespaceFindBy;
    /**
     * @var string
     */
    protected $cacheNamespaceFindByDistinct;
    /**
     * @var string
     */
    protected $cacheNamespacePage;
    /**
     * @var string
     */
    protected $cacheNamespaceCount;

    /**
     * @var null
     */
    protected $cacheTime = null;

    /**
     * RepositoryCache constructor.
     *
     * @param PoolInterface $cache
     * @param mixed         $repository
     * @param string        $classFQN
     * @param null          $cacheTime
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
    }

    /**
     * {@inheritdoc}
     */
    public function find(Identity $id, Fields $fields = null)
    {
        $key = $this->cacheNamespace.$id->id();
        $cachedItem = $this->cache->getItem($key);

        if (!$cachedItem->isMiss() && null !== ($result = $cachedItem->get())) {
            return $result;
        }

        $entity = $this->repository->find($id, $fields);
        $cachedItem->set($entity, $this->cacheTime);

        return $entity;
    }

    /**
     * {@inheritdoc}
     */
    public function findBy(Filter $filter = null, Sort $sort = null, Fields $fields = null)
    {
        $cachedItem = $this->cache->getItem(
            $this->cacheNamespaceFindBy.md5(serialize($filter).serialize($sort).serialize($fields))
        );

        if (!$cachedItem->isMiss() && null !== ($result = $cachedItem->get())) {
            return $result;
        }

        $result = $this->repository->findBy($filter, $sort, $fields);
        $cachedItem->set($result, $this->cacheTime);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function add(Identity $value)
    {
        $this->repository->add($value);

        $cachedItem = $this->cache->getItem($this->cacheNamespace.$value->id());
        $cachedItem->set($value, $this->cacheTime);
    }

    /**
     * {@inheritdoc}
     */
    public function remove(Identity $id)
    {
        $result = $this->repository->remove($id);

        $this->cache->getItem($this->cacheNamespace.$id->id())->clear();
        $this->cache->getItem($this->cacheNamespacePage)->clear();
        $this->cache->getItem($this->cacheNamespaceFindBy)->clear();
        $this->cache->getItem($this->cacheNamespaceFindByDistinct)->clear();
        $this->cache->getItem($this->cacheNamespaceCount)->clear();

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function findAll(Pageable $pageable = null)
    {
        $cachedItem = $this->cache->getItem($this->cacheNamespacePage.md5(serialize($pageable)));

        if (!$cachedItem->isMiss() && null !== ($result = $cachedItem->get())) {
            return $result;
        }

        $result = $this->repository->findAll($pageable);
        $cachedItem->set($result, $this->cacheTime);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function count(Filter $filter = null)
    {
        $cachedItem = $this->cache->getItem($this->cacheNamespaceCount.md5(serialize($filter)));

        if (!$cachedItem->isMiss() && null !== ($result = $cachedItem->get())) {
            return $result;
        }

        $result = $this->repository->count($filter);
        $cachedItem->set($result, $this->cacheTime);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function exists(Identity $id)
    {
        return $this->repository->exists($id);
    }

    /**
     * {@inheritdoc}
     */
    public function addAll(array $values)
    {
        $result = $this->repository->addAll($values);

        $this->cache->getItem($this->cacheNamespace)->clear();

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function removeAll(Filter $filter = null)
    {
        $result = $this->repository->removeAll($filter);

        $this->cache->getItem($this->cacheNamespace)->clear();

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function findByDistinct(Fields $distinctFields, Filter $filter = null, Sort $sort = null) {
        $cachedItem = $this->cache->getItem(
            $this->cacheNamespaceFindByDistinct.md5(
                serialize($filter).serialize($sort).serialize($distinctFields)
            )
        );

        if (!$cachedItem->isMiss() && null !== ($result = $cachedItem->get())) {
            return $result;
        }

        $result = $this->repository->findByDistinct($distinctFields, $filter, $sort);
        $cachedItem->set($result, $this->cacheTime);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function transactional(callable $transaction)
    {
        $this->repository->transactional($transaction);
    }
}
