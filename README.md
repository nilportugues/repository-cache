# Repository Cache
![PHP7 Tested](http://php-eye.com/badge/nilportugues/repository-cache/php70.svg)
[![Build Status](https://travis-ci.org/PHPRepository/php-repository-cache.svg)](https://travis-ci.org/PHPRepository/php-repository-cache) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/nilportugues/php-repository-cache/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/nilportugues/php-repository-cache/?branch=master) [![SensioLabsInsight](https://insight.sensiolabs.com/projects/2941364f-c744-4680-ac53-a77f5328a46d/mini.png)](https://insight.sensiolabs.com/projects/2941364f-c744-4680-ac53-a77f5328a46d) [![Latest Stable Version](https://poser.pugx.org/nilportugues/repository-cache/v/stable)](https://packagist.org/packages/nilportugues/repository-cache) [![Total Downloads](https://poser.pugx.org/nilportugues/repository-cache/downloads)](https://packagist.org/packages/nilportugues/repository-cache) [![License](https://poser.pugx.org/nilportugues/repository-cache/license)](https://packagist.org/packages/nilportugues/repository-cache)
[![Donate](https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif)](https://paypal.me/nilportugues)

Repository to be used with **[nilportugues/repository](https://github.com/nilportugues/php-repository)** implementing a cache layer using [StashPHP](http://www.stashphp.com/). 

## Installation

Use [Composer](https://getcomposer.org) to install the package:

```json
$ composer require nilportugues/repository-cache
```

## Usage

```php
use NilPortugues\Foundation\Infrastructure\Model\Repository\Cache\RepositoryCache;
use Stash\Driver\Ephemeral;
use Stash\Driver\Memcache;
use Stash\Pool;

$memcached = new Memcache();
$memcached->setOptions(array('servers' => array('127.0.0.1', '11211')));

$cachePool = new Pool();
$cachePool->setDriver(new Ephemeral());
$cachePool->setDriver($memcached);

/**
 * MySQLColorRepository is an implementation of:
 *  - NilPortugues\Foundation\Domain\Model\Repository\Contracts\PageRepository
 *  - NilPortugues\Foundation\Domain\Model\Repository\Contracts\ReadRepository
 *  - NilPortugues\Foundation\Domain\Model\Repository\Contracts\WriteRepository
 */
$repository = new MySQLColorRepository($data);
$cacheRepository = new RepositoryCache($cachePool, $repository, Color::class, '3600');

/**
 * Color Repository is a wrapper class implementing the same interfaces
 * as the previously defined MySQLColorRepository.
 */
$repository = new ColorRepository($cacheRepository);

//Now use as normal... 

$color = new Color('#@@@@@@', 'New color');
$repository->add($color);

$repository->find(ColorId('#@@@@@@')); //should hit cache and return an instance of Color.
```



## Quality

To run the PHPUnit tests at the command line, go to the tests directory and issue phpunit.

This library attempts to comply with [PSR-1](http://www.php-fig.org/psr/psr-1/), [PSR-2](http://www.php-fig.org/psr/psr-2/), [PSR-4](http://www.php-fig.org/psr/psr-4/).

If you notice compliance oversights, please send a patch via [Pull Request](https://github.com/nilportugues/php-repository-cache/pulls).


## Contribute

Contributions to the package are always welcome!

* Report any bugs or issues you find on the [issue tracker](https://github.com/nilportugues/php-repository-cache/issues/new).
* You can grab the source code at the package's [Git repository](https://github.com/nilportugues/php-repository-cache).


## Support

Get in touch with me using one of the following means:

 - Emailing me at <contact@nilportugues.com>
 - Opening an [Issue](https://github.com/nilportugues/php-repository-cache/issues/new)


## Authors

* [Nil Portugués Calderó](http://nilportugues.com)
* [The Community Contributors](https://github.com/nilportugues/php-repository-cache/graphs/contributors)


## License
The code base is licensed under the [MIT license](LICENSE).

