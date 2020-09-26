<?php

namespace Drupal\dashboard;

use Symfony\Component\HttpFoundation\Response;

class Routes {
  public function dashboard() {

    /* @var $harvestService \Drupal\harvest\Service */
    $harvestService = \Drupal::service('dkan.harvest.service');

    $htmlParts = ["<table>"];
    $htmlParts[] = "<th>Harvests</th><th>Info</th>";

    foreach ($harvestService->getAllHarvestIds() as $harvestId) {
      $htmlParts[] = "<tr><td>{$harvestId}</td></tr>";
      foreach($harvestService->getAllHarvestRunInfo($harvestId) as $runId) {
        $info = $harvestService->getHarvestRunInfo($harvestId, $runId);
        $htmlParts[] = "<td>{$info}</td>";
        break;
      }
      $htmlParts[] = "</tr>";
    }

    $htmlParts[] = "</table>";

    return new Response(implode("", $htmlParts));
  }
}
