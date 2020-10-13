<?php

namespace Drupal\datastore;

use Drupal\common\Resource;
use Drupal\common\Storage\JobStoreFactory;
use Drupal\datastore\Service\DatastoreQuery;
use Procrastinator\Result;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\datastore\Service\ResourceLocalizer;
use Drupal\datastore\Service\Factory\Import;
use Drupal\datastore\Service\ImporterList\ImporterList;
use Drupal\common\Storage\Query;
use stdClass;

/**
 * Main services for the datastore.
 */
class Service implements ContainerInjectionInterface {

  /**
   * Resource localizer for handling remote resource URLs.
   *
   * @var \Drupal\datastore\Service\ResourceLocalizer
   */
  private $resourceLocalizer;

  /**
   * Datastore import factory class.
   *
   * @var \Drupal\datastore\Service\Factory\Import
   */
  private $importServiceFactory;

  /**
   * Drupal queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  private $queue;

  /**
   * JobStore factory object.
   *
   * @var \Drupal\common\Storage\JobStoreFactory
   */
  private $jobStoreFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new Service(
      $container->get('dkan.datastore.service.resource_localizer'),
      $container->get('dkan.datastore.service.factory.import'),
      $container->get('queue'),
      $container->get('dkan.common.job_store')
    );
  }

  /**
   * Constructor for datastore service.
   */
  public function __construct(ResourceLocalizer $resourceLocalizer, Import $importServiceFactory, QueueFactory $queueFactory, JobStoreFactory $jobStoreFactory) {
    $this->queue = $queueFactory->get('datastore_import');
    $this->resourceLocalizer = $resourceLocalizer;
    $this->importServiceFactory = $importServiceFactory;
    $this->jobStoreFactory = $jobStoreFactory;
  }

  /**
   * Start import process for a resource, provided by UUID.
   *
   * @param string $identifier
   *   A resource identifier.
   * @param bool $deferred
   *   Send to the queue for later? Will import immediately if FALSE..
   * @param string $version
   *   A resource's version.
   */
  public function import(string $identifier, bool $deferred = FALSE, $version = NULL): array {

    // If we passed $deferred, immediately add to the queue for later.
    if ($deferred == TRUE) {
      $this->queueImport($identifier, $version);
      return [
        'message' => "Resource {$identifier}:{$version} has been queued to be imported.",
      ];
    }

    [$resource, $result] = $this->getResource($identifier, $version);

    if (!$resource) {
      return $result;
    }

    $result2 = $this->doImport($resource);

    return array_merge($result, $result2);
  }

  /**
   * Private.
   */
  private function doImport($resource) {
    $importService = $this->getImportService($resource);
    $importService->import();
    return [$this->getLabelFromObject($importService) => $importService->getResult()];
  }

  /**
   * Private.
   */
  private function getLabelFromObject($object) {
    return substr(strrchr(get_class($object), "\\"), 1);
  }

  /**
   * Private.
   */
  private function getResource($identifier, $version) {
    $label = $this->getLabelFromObject($this->resourceLocalizer);

    /* @var $resource \Drupal\common\Resource */
    $resource = $this->resourceLocalizer->get($identifier, $version);

    if ($resource) {
      $result = [
        $label => $this->resourceLocalizer->getResult($identifier, $version),
      ];
      return [$resource, $result];
    }

    // @todo we should not do this, we need a filefetcher queue worker.
    $result = [
      $label => $this->resourceLocalizer->localize($identifier, $version),
    ];

    if ($result[$label]->getStatus() == Result::DONE) {
      $resource = $this->resourceLocalizer->get($identifier, $version);
    }

    return [$resource, $result];
  }

  /**
   * Getter.
   */
  public function getImportService(Resource $resource) {
    return $this->importServiceFactory->getInstance($resource->getUniqueIdentifier(), ['resource' => $resource]);
  }

  /**
   * Drop a resources datastore.
   *
   * @param string $identifier
   *   A resource's identifier.
   * @param string $version
   *   A resource's version.
   */
  public function drop($identifier, $version = NULL) {
    $storage = $this->getStorage($identifier, $version);

    if ($storage) {
      $storage->destroy();
    }

    $this->resourceLocalizer->remove($identifier, $version);
  }

  /**
   * Queue a resource for import.
   *
   * @param string $identifier
   *   A resource's identifier.
   * @param string $version
   *   A resource's version.
   *
   * @return int
   *   Queue ID for new queued item.
   */
  private function queueImport($identifier, $version) {
    // Attempt to fetch the file in a queue so as to not block user.
    $queueId = $this->queue->createItem(['identifier' => $identifier, 'version' => $version]);

    if ($queueId === FALSE) {
      throw new \RuntimeException("Failed to create file fetcher queue for {$identifier}:{$version}");
    }

    return $queueId;
  }

  /**
   * Get a list of all stored importers and filefetchers, and their status.
   *
   * @return \Drupal\datastore\Service\ImporterList\ImporterList
   *   The importer list object.
   */
  public function list() {
    return ImporterList::getList(
      $this->jobStoreFactory,
      $this->resourceLocalizer,
      $this->importServiceFactory);
  }

  /**
   * Get Storage.
   *
   * @param string $identifier
   *   The unique identifier of a resource.
   * @param string $version
   *   The version of the resource.
   */
  public function getStorage($identifier, $version = NULL) {
    /* @var $resource \Drupal\common\Resource */
    $resource = $this->resourceLocalizer->get($identifier, $version);
    if ($resource) {
      $importService = $this->getImportService($resource);
      return $importService->getStorage();
    }
    throw new \Exception("No datastore storage found for {$identifier}:{$version}.");
  }

  /**
   * Run query.
   *
   * @param Drupal\datastore\Service\DatastoreQuery $datastoreQuery
   *   DKAN Datastore Query API object.
   *
   * @return object
   *   Array of row/record objects.
   */
  public function runQuery(DatastoreQuery $datastoreQuery) {

    $return = new stdClass();

    $storageMap = $this->getQueryStorageMap($datastoreQuery);

    if ($datastoreQuery->results) {
      $return->results = $this->runResultsQuery($datastoreQuery, $storageMap);
    }

    if ($datastoreQuery->count) {
      $return->count = $this->runCountQuery($datastoreQuery, $storageMap);
    }

    if ($datastoreQuery->schema) {
      foreach ($datastoreQuery->resources as $resource) {
        $storage = $storageMap[$resource->alias];
        $return->schema->{$resource->id} = (object) $storage->getSchema();
      }
    }

    $return->query = $datastoreQuery;

    return $return;
  }

  /**
   * Retrieve storage objects for all resources, and map to their aliases.
   *
   * @param Drupal\datastore\Service\DatastoreQuery $datastoreQuery
   *   DatastoreQuery object.
   *
   * @return array
   *   Array of storage objects, keyed to resource aliases.
   */
  private function getQueryStorageMap(DatastoreQuery $datastoreQuery) {
    $storageMap = [];
    foreach ($datastoreQuery->resources as $resource) {
      [$identifier, $version] = Resource::getIdentifierAndVersion($resource->id);
      $storage = $this->getStorage($identifier, $version);
      $storageMap[$resource->alias] = $storage;
    }
    return $storageMap;
  }

  /**
   * Build query object for main "results query" for datastore.
   *
   * @param Drupal\datastore\Service\DatastoreQuery $datastoreQuery
   *   DatastoreQuery object.
   * @param array $storageMap
   *   Array of storage objects, keyed to resource aliases.
   *
   * @return Drupal\common\Storage\Query
   *   Query object.
   */
  private function runResultsQuery(DatastoreQuery $datastoreQuery, array $storageMap) {
    if ($this->results = FALSE) {
      throw new \Exception("Results query requested on non-results datastore query.");
    }
    $query = $this->populateQuery($datastoreQuery, $storageMap);
    $primaryAlias = $datastoreQuery->resources[0]->alias;

    $result = $storageMap[$primaryAlias]->query($query, $primaryAlias);

    if ($datastoreQuery->keys === FALSE) {
      $result = array_map(function ($row) {
        $arrayRow = (array) $row;
        foreach ($arrayRow as $value) {
          $newRow[] = $value;
        }
        return $newRow;
      }, $result);
    }
    return $result;

  }

  /**
   * Build count query object for datastore.
   *
   * @param Drupal\datastore\Service\DatastoreQuery $datastoreQuery
   *   DatastoreQuery object.
   * @param array $storageMap
   *   Array of storage objects, keyed to resource aliases.
   *
   * @return Drupal\common\Storage\Query
   *   Query object.
   */
  private function runCountQuery(DatastoreQuery $datastoreQuery, array $storageMap) {
    if ($datastoreQuery->count == FALSE) {
      throw new \Exception("Results query requested on non-results datastore query.");
    }

    $primaryAlias = $datastoreQuery->resources[0]->alias;
    $query = $this->populateQuery($datastoreQuery, $storageMap);
    unset($query->limit, $query->offset);
    $query->count();

    return $storageMap[$primaryAlias]->query($query, $primaryAlias)[0]->expression;
  }

  /**
   * Helper function to populate Query properties with DatastoreQuery object.
   *
   * @param Drupal\datastore\Service\DatastoreQuery $datastoreQuery
   *   DatastoreQuery object.
   * @param array $storageMap
   *   Array of storage objects, keyed to resource aliases.
   *
   * @return Drupal\common\Storage\Query
   *   Query object.
   */
  private function populateQuery(DatastoreQuery $datastoreQuery, array $storageMap) {
    $dqClone = $this->cloneQueryObject($datastoreQuery);
    $query = new Query();
    $this->populateQueryProperties($query, $dqClone);
    $query->conditions = $dqClone->conditions;
    $this->populateQueryConditions($query, $dqClone);
    $this->populateQueryJoins($query, $dqClone, $storageMap);
    $this->populateQuerySort($query, $dqClone);
    $query->limit = $dqClone->limit;
    $query->offset = $dqClone->offset;
    $query->showDbColumns = TRUE;
    unset($dqClone);
    return $query;
  }

  private function populateQueryProperties($query, $datastoreQuery) {
    foreach ($datastoreQuery->properties as $property) {
      $query->properties[] = $this->propertyConvert($property);
    }
  }

  private function propertyConvert($property) {
    if (is_object($property) && isset($property->resource)) {
      $property->collection = $property->resource;
      unset($property->resource);
    }
    elseif (is_object($property) && isset($property->expression)) {
      $property->expression = $this->expressionConvert($property->expression);
    }
    elseif (!is_string($property)) {
      throw new \Exception("Bad query property.");
    }
    return $property;
  }

  private function expressionConvert($expression) {
    foreach($expression->operands as $key => $operand) {
      if (is_object($operand) && isset($operand->operator)) {
        $expression->operands[$key] = $this->expressionConvert($operand);
      }
      elseif (is_numeric($operand)) {
        continue;
      }
      else {
        $expression->operands[$key] = $this->propertyConvert($operand);
      }
    }
    return $expression;
  }

  /**
   * Helper function for sorting queries.
   *
   * @param Drupal\common\Storage\Query $query
   *   DKAN query object we're building.
   * @param Drupal\datastore\Service\DatastoreQuery $datastoreQuery
   *   DatastoreQuery object.
   */
  private function populateQuerySort(Query $query, DatastoreQuery $datastoreQuery) {
    foreach ($datastoreQuery->sort->desc as $desc) {
      $query->sort["desc"][] = (object) [
        "collection" => $desc->resource,
        "property" => $desc->property,
      ];
    }
    foreach ($datastoreQuery->sort->asc as $asc) {
      $query->sort["asc"][] = (object) [
        "collection" => $asc->resource,
        "property" => $asc->property,
      ];
    }
  }

  private function populateQueryConditions($query, $datastoreQuery) {
    $conditions = [];
    foreach ($datastoreQuery->conditions as $c) {
      $conditions[] = $this->populateQueryCondition($c);
    }
  }

  private function populateQueryCondition($datastoreCondition) {
    if (isset($datastoreCondition->resource)) {
      return (object) [
        "collection" => $datastoreCondition->resource,
        "property" => $datastoreCondition->property,
        "operator" => $datastoreCondition->operator,
        "value" => $datastoreCondition->value,
      ];
    }
    elseif (isset($datastoreCondition->groupOperator)) {
      foreach ($datastoreCondition->conditions as $c) {
        $conditions[] = $this->populateQueryCondition($c);
      }
      return (object) [
        "groupOperator" => $datastoreCondition->groupOperator,
        "conditions" => $conditions,
      ];
    }
  }

  /**
   * Helper function for converting joins to Query format.
   *
   * @param Drupal\common\Storage\Query $query
   *   DKAN query object we're building.
   * @param Drupal\datastore\Service\DatastoreQuery $datastoreQuery
   *   DatastoreQuery object.
   * @param array $storageMap
   *   Array of storage objects, keyed to resource aliases.
   */
  private function populateQueryJoins(Query $query, DatastoreQuery $datastoreQuery, array $storageMap) {
    if (empty($datastoreQuery->joins) && count($datastoreQuery->resources) <= 1) {
      return;
    }
    if (count($datastoreQuery->resources) > 1
      && count($datastoreQuery->joins) < (count($datastoreQuery->resources) - 1)) {
      throw new \Exception("Too many collections specified.");
    }
    foreach ($datastoreQuery->joins as $join) {
      $storage = $storageMap[$join->resource];
      $queryJoin = new stdClass();
      $queryJoin->collection = $storage->getTableName();
      $queryJoin->alias = $join->resource;
      foreach ($join->on as $on) {
        $queryJoin->on[] = (object) ["collection" => $on->resource, "property" => $on->property];
      }
      $query->joins[] = $queryJoin;
    }
  }

  private function cloneQueryObject($input) {
    $output = unserialize(serialize($input));
    return $output;
  }

}
