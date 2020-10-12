<?php

namespace Drupal\metastore\EventSubscriber;

use Drupal\metastore\Events\DatasetPublication;
use Drupal\metastore\Storage\Data;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Metastore event subscriber.
 */
class MetastoreSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      Data::EVENT_DATASET_PUBLICATION => ['purgeUnneededResources'],
    ];
  }

  public function purgeUnneededResources(DatasetPublication $event) {
    $node = $event->getNode();

    /* @var $factory \Drupal\metastore\Storage\DataFactory */
    $factory = \Drupal::service('dkan.metastore.storage');

    /* @var $storage \Drupal\metastore\Storage\Data */
    $storage = $factory->getInstance('dataset');

    $storage->purgeUnneededResources($node->uuid());
  }

}
