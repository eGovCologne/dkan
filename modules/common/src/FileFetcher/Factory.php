<?php

namespace Drupal\common\FileFetcher;

use Contracts\FactoryInterface;
use Drupal\common\Storage\JobStoreFactory;
use FileFetcher\FileFetcher;

/**
 * FileFetcher Factory.
 */
class Factory implements FactoryInterface {

  private $factory;

  /**
   * Constructor.
   */
  public function __construct(JobStoreFactory $factory) {
    $this->factory = $factory;
  }

  /**
   * Inherited.
   *
   * @inheritdoc
   */
  public function getInstance(string $identifier, array $config = []) {
    return FileFetcher::get($identifier, $this->getFileFetcherJobStore(), $config);
  }

  /**
   * Get file fetcher job store.
   */
  public function getFileFetcherJobStore() {
    return $this->factory->getInstance(FileFetcher::class);
  }

}
