<?php

namespace Drupal\metastore\Plugin\QueueWorker;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\datastore\Service as Datastore;
use Drupal\datastore\Service\ResourceLocalizer;
use Drupal\metastore\Storage\DataFactory;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines 'unneeded_resource_purger' queue worker.
 *
 * @QueueWorker(
 *   id = "unneeded_resource_purger",
 *   title = @Translation("Queue to purge unneeded resources of a dataset."),
 *   cron = {"time" = 10}
 * )
 */
class UnneededResourcePurger extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Config value from datastore.settings' purge_unneeded_tables.
   *
   * @var bool
   */
  private $purge_tables;

  /**
   * Config value from datastore.settings' purge_unneeded_files.
   *
   * @var bool
   */
  private $purge_files;

  /**
   * Metastore storage data factory.
   *
   * @var \Drupal\metastore\Storage\DataFactory
   */
  private $dataFactory;

  /**
   * Node storage.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  private $nodeStorage;

  /**
   * Resource localizer.
   *
   * @var \Drupal\datastore\Service\ResourceLocalizer
   */
  private $resourceLocalizer;

  /**
   * Datastore service.
   *
   * @var \Drupal\datastore\Service
   */
  private $datastore;

  /**
   * UnneededResourcePurger constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   Config factory.
   * @param \Drupal\metastore\Storage\DataFactory $dataFactory
   *   Metastore storage data factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\datastore\Service\ResourceLocalizer $resourceLocalizer
   *   Resource localizer.
   * @param \Drupal\datastore\Service $datastore
   *   Datastore service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config, DataFactory $dataFactory, EntityTypeManagerInterface $entityTypeManager, ResourceLocalizer $resourceLocalizer, Datastore $datastore) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $datastoreConfig = $config->get('datastore.settings');
    $this->purge_tables = (bool) $datastoreConfig->get('purge_unneeded_tables');
    $this->purge_files = (bool) $datastoreConfig->get('purge_unneeded_files');
    $this->dataFactory = $dataFactory;
    $this->nodeStorage = $entityTypeManager->getStorage('node');
    $this->resourceLocalizer = $resourceLocalizer;
    $this->datastore = $datastore;
  }

  /**
   * @inheritDoc
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('dkan.metastore.storage'),
      $container->get('entity_type.manager'),
      $container->get('dkan.datastore.service.resource_localizer'),
      $container->get('dkan.datastore.service')
    );
  }

  /**
   * @inheritDoc
   */
  public function processItem($data) {
    // @todo Array $data should contain boolean to change the purge's scope.
    $uuid = $data['uuid'];
    try {
      if (!$this->purge_tables && !$this->purge_files) {
        return;
      }

      $revisionsToKeep = $this->getRevisionsToKeep($uuid);
      // @todo Purge scope modifier could be added to getRevisionsToConsider.
      $revisionsToConsider = $this->getRevisionsToConsider(reset($revisionsToKeep));
      $revisionsToFilter = array_diff($revisionsToConsider, array_keys($revisionsToKeep));

      $resourcesToKeep = $this->getResourcesToKeep($revisionsToKeep);
      $resourcesToPurge = $this->filterRevisions($revisionsToFilter, $resourcesToKeep);

      $this->purgeResources($resourcesToPurge);
    }
    catch (\Exception $e) {
    }
  }

  /**
   * Get dataset revisions to keep.
   *
   * @param string $uuid
   *   Dataset uuid.
   *
   * @return array
   *   Array of dataset revisions.
   */
  private function getRevisionsToKeep(string $uuid) : array {
    $storage = $this->dataFactory->getInstance('dataset');
    $revisionsToKeep = [];

    // There should be a latest version of the node.
    $latestRevision = $storage->getNodeLatestRevision($uuid);
    if ($latestRevision) {
      $vid = $latestRevision->getLoadedRevisionId();
      $revisionsToKeep[$vid] = $latestRevision;
    }
    // If the latest revision is not published, search for a published revision.
    if ($latestRevision->get('moderation_state') !== 'published') {
      $publishedRevision = $storage->getNodePublishedRevision($uuid);
      if ($publishedRevision) {
        $vid = $publishedRevision->getLoadedRevisionId();
        $revisionsToKeep[$vid] = $publishedRevision;
      }
    }

    return $revisionsToKeep;
  }

  /**
   * Get resources to keep.
   *
   * @param array $nodes
   *   Array of dataset node revisions.
   *
   * @return array
   *   Modified array of dataset node revisions.
   */
  private function getResourcesToKeep(array $nodes) : array {
    $resourcesToKeep = [];
    foreach ($nodes as $revisionId => $node) {
      $resourcesToKeep[$revisionId] = $this->getResourceIdAndVersion($node);
    }
    return $resourcesToKeep;
  }

  /**
   * Get revisions to consider, based on boolean parameter.
   *
   * @param \Drupal\node\NodeInterface $node
   *   A dataset node.
   *
   * @return array
   *   Array of revisions.
   */
  private function getRevisionsToConsider(NodeInterface $node) : array {
    // @todo Purge scope modifier would allow considering revisions between
    //   previous publication and the latest one. Decide if inclusive or not.
    return $this->nodeStorage->revisionIds($node);
  }

  /**
   * Get a dataset's resource identifier and version.
   *
   * @param \Drupal\node\NodeInterface $node
   *   A dataset node.
   *
   * @return array
   *   Array containing a resource's identifier and version.
   */
  private function getResourceIdAndVersion(NodeInterface $node) : array {
    $object = json_decode($node->get('field_json_metadata')->getString());
    $refDistData = $object->{'%Ref:distribution'}[0]->data;
    $resource = $refDistData->{'%Ref:downloadURL'}[0]->data;
    return [$resource->identifier, $resource->version];
  }

  /**
   * Filter revisions.
   *
   * @param array $revisionsToFilter
   *   Array of revisions to filter.
   * @param array $resourcesToKeep
   *   Array of revisions to keep.
   *
   * @return array
   *   Filtered array of revisions.
   */
  private function filterRevisions(array $revisionsToFilter, array $resourcesToKeep) : array {
    $resourcesToPurge = [];
    foreach ($revisionsToFilter as $revisionId) {
      $nodeRevision = $this->nodeStorage->loadRevision($revisionId);
      $resourceIdAndVersion = $this->getResourceIdAndVersion($nodeRevision);
      if (!in_array($resourceIdAndVersion, $resourcesToKeep)) {
        $resourcesToPurge[] = $resourceIdAndVersion;
      }
    }
    return $resourcesToPurge;
  }

  /**
   * Purge resources.
   *
   * @param array $resources
   *   Array of resources.
   */
  private function purgeResources(array $resources) {
    foreach ($resources as [$id, $version]) {
      $resource = $this->resourceLocalizer->get($id, $version);
      if (!$resource) {
        continue;
      }
      if ($this->purge_files) {
        $this->resourceLocalizer->remove($id, $version);
      }
      if ($this->purge_tables) {
        // @todo Get datastore table name for this resource, delete it.
      }
      // @todo Consider purging any datastore_import entry for this resource.
    }
  }

}
