<?php

namespace Drupal\dashboard;

use Symfony\Component\HttpFoundation\Response;

class Routes {
  public function dashboard() {

    $outputs = [];

    $outputs[] = $this->harvestsInfo();

    $build = [
      '#markup' => implode("", $outputs),
    ];

    return $build;
  }

  private function harvestsInfo() {
    /* @var $harvestService \Drupal\harvest\Service */
    $harvestService = \Drupal::service('dkan.harvest.service');

    $htmlParts = ["<table>"];
    $htmlParts[] = "<tr><th>Harvests</th><th>Last Run</th><th>Status</th><th>Datasets</th></tr>";

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
      $status = $loadStatus->{$datasetId};
      return "<tr><td>{$datasetId}</td><td>{$status}</td></tr>";
    }, $datasets);

    $htmlParts[] = "</table>";
    return implode("", $htmlParts);
  }

  private function datasetsTable($datasets) {
    $htmlParts = ["<table><tr><th>Datasets</th><th></th></tr>"];

    $htmlParts += array_map(function ($datasetId) {
      /* @var \Drupal\metastore\Service $service */
      $service = \Drupal::service('dkan.metastore.service');
      try {
        $datasetJson = $service->get('dataset', $datasetId);
        $dataset = json_decode($datasetJson);
        $resources = $dataset->distribution;
      }
      catch (\Exception $e) {
        $resources = [];
      }

      return "<tr><td>{$datasetId}</td><td>{$this->resourcesTable($resources)}</td></tr>";
    }, $datasets);

    $htmlParts[] = "</table>";
    return implode("", $htmlParts);
  }

  private function resourcesTable($resources = []) {
    $htmlParts = ["<table><tr><th>Resources</th></tr>"];

    $htmlParts = array_merge($htmlParts, array_map(function ($resource) {
      $url = $resource->downloadURL;
      $url = pathinfo($url);
      $filename = "";
      if (!empty($url)) {
        $filename = "{$url['filename']}.{$url['extension']}";
      }
      return "<tr><td>{$filename}</td></tr>";
    }, array_values($resources)));

    $htmlParts[] = "</table>";

    return implode("", $htmlParts);
  }
}
