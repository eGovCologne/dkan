<?php

namespace Drupal\dashboard\Controller;

/**
 *
 */
class Controller {

  private $datasets = [];

  /**
   *
   */
  public function harvests() {

    $outputs = [];

    $outputs[] = $this->harvestsInfo();

    $build = [
      '#markup' => implode("", $outputs),
    ];

    return $build;
  }

  /**
   *
   */
  public function harvestDatasets($harvestId) {

    $outputs[] = $this->datasetsInfo($harvestId);

    $build = [
      '#markup' => "<h2>{$harvestId}</h2>" . implode("", $outputs),
    ];

    return $build;
  }

  /**
   *
   */
  private function harvestsInfo() {
    /** @var \Drupal\harvest\Service $harvestService */
    $harvestService = \Drupal::service('dkan.harvest.service');

    $htmlParts = ["<table>"];
    $htmlParts[] = "<tr><th>Harvest ID</th><th>Last Run</th><th>Status</th><th># of Datasets</th></tr>";

    foreach ($harvestService->getAllHarvestIds() as $harvestId) {
      $htmlParts[] = "<tr><td><a href='/admin/reports/dkan/dashboard/{$harvestId}'>{$harvestId}</a></td>";

      $runIds = $harvestService->getAllHarvestRunInfo($harvestId);
      $runId = end($runIds);

      $json = $harvestService->getHarvestRunInfo($harvestId, $runId);
      $info = json_decode($json);
      $status = $info->status;
      date_default_timezone_set('EST');
      $time = date('m/d/y H:m:s T', $runId);

      $datasets = array_keys((array) $status->load);
      $numDatasets = count($datasets);

      $htmlParts[] = "<td>{$time}</td><td>{$status->extract}</td><td>{$numDatasets}</td></tr>";

      $htmlParts[] = "</tr>";
    }

    $htmlParts[] = "</table>";
    return implode("", $htmlParts);
  }

  /**
   *
   */
  private function harvestLoadStatusTable($loadStatus) {
    $datasets = array_keys((array) $loadStatus);

    $htmlParts = ["<table><tr><th>Dataset ID</th><th>Status</th></tr>"];

    $datasetStuff = array_map(function ($datasetId) use ($loadStatus) {
      $this->datasets[] = $datasetId;
      $status = $loadStatus->{$datasetId};
      return "<tr><td>{$datasetId}</td><td>{$status}</td></tr>";
    }, $datasets);

    $htmlParts = array_merge($htmlParts, $datasetStuff);

    $htmlParts[] = "</table>";
    return implode("", $htmlParts);
  }

  /**
   *
   */
  private function datasetsInfo($harvestId) {
    $harvestService = \Drupal::service('dkan.harvest.service');
    $runIds = $harvestService->getAllHarvestRunInfo($harvestId);
    $runId = end($runIds);

    $json = $harvestService->getHarvestRunInfo($harvestId, $runId);
    $info = json_decode($json);
    $status = $info->status;
    $datasets = array_keys((array) $status->load);

    $htmlParts = [];
    $htmlParts[] = $this->harvestLoadStatusTable($status->load);
    $htmlParts[] = "<table><tr><th>Dataset ID</th><th>Title</th><th>Modified Date (Metadata)</th><th>Modified Date (DKAN)</th><th>Resources</th></tr>";

    /** @var \Drupal\metastore\Service $service */
    $service = \Drupal::service('dkan.metastore.service');

    $datasetStuff = array_map(function ($datasetId) use ($service) {

      try {
        $datasetJson = $service->get('dataset', $datasetId);
        $dataset = json_decode($datasetJson);
        $resources = $dataset->{"%Ref:distribution"};
      }
      catch (\Exception $e) {
        $resources = [];
      }

      $htmlParts = ["<tr><td>{$datasetId}</td>"];

      if (empty($dataset)) {
        $htmlParts[] = "<td colspan='4'>Not Published</td>";
      }
      else {
        $dkanModified = $dataset->{"%modified"};
        $htmlParts[] = "<td>{$dataset->title}</td><td>{$dataset->modified}</td><td>{$dkanModified}</td><td>{$this->resourcesInfo($resources)}</td>";
      }
      $htmlParts[] = "</tr>";
      return implode("", $htmlParts);
    }, $datasets);

    $htmlParts = array_merge($htmlParts, $datasetStuff);

    $htmlParts[] = "</table>";
    return implode("", $htmlParts);
  }

  /**
   *
   */
  private function resourcesInfo($resources) {
    /** @var \Drupal\datastore\Service\Info\ImportInfo $service */
    $service = \Drupal::service('dkan.datastore.import_info');
    $htmlParts = ["<table><tr><th>Identifier</th><th>Local File</th><th></th><th>Datastore</th><th></th></tr>"];
    foreach ($resources as $resource) {
      $identifier = $resource->identifier;

      $prop = "%Ref:downloadURL";
      $data = $resource->data->{$prop}[0]->data;

      $info = $service->getItem($data->identifier, $data->version);

      $htmlParts[] = "<tr><td>{$identifier}</td><td>{$info->fileFetcherStatus}</td><td>{$info->fileFetcherPercentDone}%</td><td>{$info->importerStatus}</td><td>{$info->importerPercentDone}%</td></tr>";
    }
    $htmlParts[] = "</table>";
    return implode("", $htmlParts);
  }

}
