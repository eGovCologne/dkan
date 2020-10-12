<?php

namespace Drupal\metastore;

use Drupal\metastore\Storage\DataFactory;
use Drush\Commands\DrushCommands;

/**
 * Metastore drush commands.
 */
class Drush extends DrushCommands {

  /**
   * Metastore data storage service.
   *
   * @var \Drupal\metastore\Storage\DataFactory
   */
  protected $factory;

  /**
   * Drush constructor.
   *
   * @param \Drupal\metastore\Storage\DataFactory $factory
   *   A data factory.
   */
  public function __construct(DataFactory $factory) {
    parent::__construct();
    $this->factory = $factory;
  }

  /**
   * Publish the latest version of a dataset.
   *
   * @param string $uuid
   *   Dataset identifier.
   *
   * @command dkan:metastore:publish
   */
  public function publish(string $uuid) {
    try {
      $storage = $this->factory->getInstance('dataset');
      $storage->publish($uuid);
      $this->logger()->info("Dataset {$uuid} published.");
    }
    catch (\Exception $e) {
      $this->logger()->error("Error while attempting to publish dataset {$uuid}: " . $e->getMessage());
    }
  }

  /**
   * Queue the purging of resources no longer needed by a specific dataset.
   *
   * @param string $uuid
   *   A dataset uuid.
   *
   * @usage dkan:metastore:purge-unneeded-resources 1111-1111
   *   Unburden resources of dataset 1111-1111.
   *
   * @command dkan:metastore:purge-unneeded-resources
   * @aliases dkan:metastore:pur
   *
   * @todo Add an optional parameter to purge either target all unneeded
   *   resources or only those since the last publication.
   */
  public function purgeUnneededResources(string $uuid) {
    try {
      $storage = $this->factory->getInstance('dataset');
      // @todo Pass boolean as second parameter.
      $storage->purgeUnneededResources($uuid);
      $this->logger()->info("Queued the purging of unneeded resources in dataset {$uuid}.");
    }
    catch (\Exception $e) {
      $this->logger()->error("Error while queueing the purging of unneeded resources in dataset {$uuid}: " . $e->getMessage());
    }
  }

}
