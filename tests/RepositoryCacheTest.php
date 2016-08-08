<?php

namespace NilPortugues\Tests\Foundation\Infrastructure\Model\Repository\Cache;

use DateTime;
use NilPortugues\Foundation\Domain\Model\Repository\Fields;
use NilPortugues\Foundation\Domain\Model\Repository\Filter;
use NilPortugues\Foundation\Infrastructure\Model\Repository\InMemory\InMemoryRepository;
use NilPortugues\Foundation\Infrastructure\Model\Repository\Cache\RepositoryCache;
use Stash\Driver\Redis;
use Stash\Pool;

class RepositoryCacheTest extends \PHPUnit_Framework_TestCase
{
    const TTL_IN_SECONDS = 3;
    /** @var RepositoryCache */
    protected $repository;
    /** @var  Redis */
    protected $redis;

    protected function setUp()
    {
        $this->redis = new Redis();
        $cachePool = new Pool();
        $cachePool->setDriver($this->redis);

        $this->repository = new RepositoryCache(
            $cachePool,
            new InMemoryRepository(),
            rand(PHP_INT_MIN, PHP_INT_MAX),
            self::TTL_IN_SECONDS
        );

        $this->repository->add(new Customer(1, 'John Doe', 3, 25.125, new DateTime('2014-12-11')));
        $this->repository->add(new Customer(2, 'Junichi Masuda', 3, 50978.125, new DateTime('2013-02-22')));
        $this->repository->add(new Customer(3, 'Shigeru Miyamoto', 5, 47889850.125, new DateTime('2010-12-01')));
        $this->repository->add(new Customer(4, 'Ken Sugimori', 4, 69158.687, new DateTime('2010-12-10')));
    }

    protected function tearDown()
    {
        $this->redis->purge();
    }

    public function testItHitsCacheWhenFind()
    {
        $customer1a = $this->repository->find(new CustomerId(1));
        $customer1b = $this->repository->find(new CustomerId(1));

        $this->assertEquals($customer1a, $customer1b);
    }

    public function testItRebuildCacheWhenExpiredFind()
    {
        $customer1a = $this->repository->find(new CustomerId(1));
        $customer1b = $this->repository->find(new CustomerId(1));

        $this->assertEquals($customer1a, $customer1b);
    }

    public function testItHitsCacheWhenFindBy()
    {
        $filter = new Filter();
        $filter->must()->equal('name', 'Ken Sugimori');

        $customer1a = $this->repository->findBy($filter);
        $customer1b = $this->repository->findBy($filter);

        $this->assertEquals($customer1a, $customer1b);
    }

    public function testItAddsToCacheWhenAdd()
    {
        $customer = $this->repository->find(new CustomerId(1));
        $customer->setName('Nil Portugués');
        $this->repository->add($customer);

        $updatedCustomer = $this->repository->find(new CustomerId(1));
        $this->assertEquals($customer->name(), $updatedCustomer->name());
    }

    public function testItHitsCacheWhenFindAll()
    {
        $result = $this->repository->findAll();
        $resultCached = $this->repository->findAll();

        $this->assertEquals($result, $resultCached);
    }

    public function testItHitsCacheWhenFindByDistinct()
    {
        $fields = new Fields(['name']);
        $result = $this->repository->findByDistinct($fields);
        $resultCached = $this->repository->findByDistinct($fields);

        $this->assertEquals($result, $resultCached);
    }

    public function testItHitsCacheWhenCount()
    {
        $total = $this->repository->count();
        $cachedTotal = $this->repository->count();

        $this->assertEquals($total, $cachedTotal);
    }

    public function testItUpdatesCacheWhenRemove()
    {
        $this->assertTrue($this->repository->exists(new CustomerId(1)));
        $this->repository->remove(new CustomerId(1));
        $this->assertFalse($this->repository->exists(new CustomerId(1)));
    }

    public function testItUpdatesCachedValueWhenCount()
    {
        $this->assertEquals(4, $this->repository->count());
        $this->repository->remove(new CustomerId(1));
        $this->assertEquals(3, $this->repository->count());
    }
    public function testItHitsCacheWhenExists()
    {
        $this->assertTrue($this->repository->exists(new CustomerId(1)));
        $this->assertTrue($this->repository->exists(new CustomerId(1)));
    }

    public function testItUpdatesCachedValueWhenExists()
    {
        $this->assertTrue($this->repository->exists(new CustomerId(1)));
        $this->repository->removeAll();
        $this->assertFalse($this->repository->exists(new CustomerId(1)));
    }

    public function testItAddsToCacheWhenAddAll()
    {
        $this->repository->removeAll();

        $this->repository->addAll([
            new Customer(1, 'John Doe', 3, 25.125, new DateTime('2014-12-11')),
            new Customer(2, 'Junichi Masuda', 3, 50978.125, new DateTime('2013-02-22')),
            new Customer(3, 'Shigeru Miyamoto', 5, 47889850.125, new DateTime('2010-12-01')),
            new Customer(4, 'Ken Sugimori', 4, 69158.687, new DateTime('2010-12-10')),
        ]);

        $this->assertEquals(4, $this->repository->count());
    }

    public function testItClearsCacheWhenRemoveAll()
    {
        $customerId = new CustomerId(1);

        $this->repository->find($customerId);
        $this->repository->removeAll();

        $this->assertEmpty($this->repository->find($customerId));
    }

    public function testTransactional()
    {
        $callable = function () {
            return 1;
        };

        $this->repository->transactional($callable);
    }
}
