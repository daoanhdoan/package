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
    $data = \Drupal::config('package.settings')->set('repositories');
    if (!empty($data)) {
      $packages = $data;
    }
    else {
      $packages = "No Repository found.";
    }

    $response = new JsonResponse(((object)["packages" => $packages]));
    return $response;
  }
}
