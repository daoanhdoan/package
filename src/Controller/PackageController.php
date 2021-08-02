<?php
/**
 *
 */

namespace Drupal\package\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class PackageController extends ControllerBase
{
  public function load(Request $request)
  {
    $data = \Drupal::cache()->get("package.github");
    if (!empty($data)) {
      $packages = $data->data;
    }
    else {
      $packages = "No Repository found.";
    }

    $response = new JsonResponse(((object)["packages" => $packages]));
    return $response;
  }
}
