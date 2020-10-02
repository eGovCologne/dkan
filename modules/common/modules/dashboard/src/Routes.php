<?php

namespace Drupal\dashboard;

use Symfony\Component\HttpFoundation\Response;

class Routes {

  private $datasets = [];

  public function dashboard() {

    $outputs = [];

    $outputs[] = $this->harvestsInfo();
    $outputs[] = $this->datasetsInfo();

    $build = [
      '#markup' => implode("", $outputs),
    ];

    return $build;
  }

  private function harvestsInfo() {
    /* @var $harvestService \Drupal\harvest\Service */
    $harvestService = \Drupal::service('dkan.harvest.service');

    $htmlParts = ["<table>"];
    $htmlParts[] = "<tr><th>Harvest</th><th>Last Run</th><th>Status</th><th>Datasets</th></tr>";

    foreach ($harvestService->getAllHarvestIds() as $harvestId) {
      $htmlParts[] = "<tr><td>{$harvestId}</td>";

      $runIds = $harvestService->getAllHarvestRunInfo($harvestId);
      $runId = end($runIds);

      $json = $harvestService->getHarvestRunInfo($harvestId, $runId);
      $info = json_decode($json);
      $status = $info->status;
      date_default_timezone_set('EST');
      $time = date('m/d/y H:m:s T', $runId);

      $htmlParts[] = "<td>{$time}</td><td>{$status->extract}</td><td>{$this->harvestLoadStatusTable($status->load)}</td></tr>";

      $htmlParts[] = "</tr>";
    }

    $htmlParts[] = "</table>";
    return implode("", $htmlParts);
  }

  private function harvestLoadStatusTable($loadStatus) {
    $datasets = array_keys((array) $loadStatus);

    $htmlParts = ["<table>"];

    $htmlParts += array_map(function ($datasetId) use ($loadStatus) {
      $this->datasets[] = $datasetId;
      $status = $loadStatus->{$datasetId};
      return "<tr><td><a href='#{$datasetId}'>{$datasetId}</a></td><td>{$status}</td></tr>";
    }, $datasets);

    $htmlParts[] = "</table>";
    return implode("", $htmlParts);
  }

  private function datasetsInfo() {
    $htmlParts = ["<table><tr><th>Dataset</th><th>Title</th><th>Modified Date (Metadata)</th><th>Modified Date (DKAN)</th><th>Resources</th></tr>"];

    /* @var \Drupal\metastore\Service $service */
    $service = \Drupal::service('dkan.metastore.service');

    $htmlParts += array_map(function ($datasetId) use ($service) {

      try {
        $datasetJson = $service->get('dataset', $datasetId);
        $dataset = json_decode($datasetJson);
        $resources = $dataset->{"%Ref:distribution"};
      }
      catch (\Exception $e) {
        $resources = [];
      }

      $htmlParts = ["<tr><td><a name='{$datasetId}'>{$datasetId}</a></td>"];

      if (empty($dataset)) {
        $htmlParts[] = "<td colspan='4'>Not Published</td>";
      }
      else {
        $dkanModified = $dataset->{"%modified"};
        $htmlParts[] = "<td>{$dataset->title}</td><td>{$dataset->modified}</td><td>{$dkanModified}</td><td>{$this->resourcesInfo($resources)}</td>";
      }
      $htmlParts[] = "</td>";
      return implode("", $htmlParts);

    }, $this->datasets);

    $htmlParts[] = "</table>";
    return implode("", $htmlParts);
  }

  private function resourcesInfo($resources) {
    /* @var $service \Drupal\datastore\Service\Info\ImportInfo */
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
