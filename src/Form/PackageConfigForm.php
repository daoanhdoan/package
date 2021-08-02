<?php

namespace Drupal\package\Form;

use Composer\Repository\Vcs\VcsDriverInterface;
use Drupal\authman\Entity\AuthmanAuthInterface;
use Drupal\authman\EntityHandlers\AuthmanAuthStorage;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\package\Package;

/**
 * Base for handler for taxonomy term edit forms.
 *
 * @internal
 */
class PackageConfigForm extends ConfigFormBase {
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $storage = \Drupal::entityTypeManager()->getStorage('authman_auth');
    assert($storage instanceof AuthmanAuthStorage);
    $items = $storage->loadMultiple();
    $options = ["" => "<-- None -->"];
    if ($items) {
      /** @var AuthmanAuthInterface $item */
      foreach($items as $item) {
        if($item->getPluginId() === 'authman_github') {
          $options[$item->id()] = $item->label();
        }
      }
    }
    if (empty($items)) {
      $form['item'] = [
        '#type' => 'markup',
        '#markup' => 'No Github Authman Instances found.',
      ];
      $form['link'] = [
        '#type' => 'link',
        '#title' => 'Create Github Authman Instance',
        '#url' => Url::fromUserInput('/admin/config/authman/instances'),
      ];
    }
    else {
      $ids = $this->config("package.settings")->get('instance_id');
      $form['instance_id'] = [
        '#type' => 'select',
        '#title' => 'Github Authman Instance',
        '#options' => $options,
        '#default_value' => $ids,
        '#required' => TRUE,
        '#multiple' => TRUE
      ];
      $form += parent::buildForm($form, $form_state);
      if (!empty($ids)) {
        $form['actions']['get_package'] = [
          '#type' => 'submit',
          '#value' => $this->t('Get Packages'),
          '#submit' => [[$this, 'getPackages']],
          '#button_type' => 'primary',
        ];
      }
      return $form;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $authmanInstanceId = $form_state->getValue('instance_id');
    $config = $this->config('package.settings');
    $config->set('instance_id', $authmanInstanceId);
    $config->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getPackages(array &$form, FormStateInterface $form_state) {
    $ids = $this->config("package.settings")->get('instance_id');
    $repos = [];
    $factory = \Drupal::service('authman.oauth');
    foreach($ids as $id) {
      try {
        $authmanInstance = $factory->get($id);
        $token = $authmanInstance->getToken(true)->getAccessToken()->getToken();
        try {
          $response = $authmanInstance->authenticatedRequest('GET', 'https://api.github.com/user/repos');
          $repoJson = \json_decode((string)$response->getBody());
          if (!empty($repoJson)) {
            foreach($repoJson as &$repo) {
              $repo->config = ['config' => ['github-oauth' => ['github.com' => $token]]];
            }
            $repos = array_merge($repos, $repoJson);
          }
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
          $errorJson = \json_decode((string)$e->getResponse()->getBody());
          \Drupal::messenger()->addError($errorJson);
          \Drupal::logger('package')->error($errorJson);
        }
      }
      catch (\Exception $e) {
        \Drupal::messenger()->addError($e->getMessage());
      }
    }

    if (empty($repos)) {
      \Drupal::messenger()->addError("No repository found.");
      return;
    }
    $batch = array(
      'title' => t('Retrieve packages from svn server'),
      'finished' => '\Drupal\package\Form\PackageConfigForm::finishedRetrievePackages',
      'operations' => [
        ['\Drupal\package\Form\PackageConfigForm::retrievePackages', [$repos]]
      ]
    );
    batch_set($batch);
  }

  public static function retrievePackages($repos, &$context) {
    if (empty($context['sandbox'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['current_id'] = 0;
      $context['sandbox']['repositories'] = $repos;
      $context['sandbox']['max'] = count($repos);
    }
    $limit = 5;
    $items = array_splice($context['sandbox']['repositories'], 0, $limit);

    foreach ($items as $repo) {
      $repository = Package::getVcsRepository($repo->git_url, $repo->config);
      /** @var VcsDriverInterface $driver */
      $driver = $repository->getDriver();
      $composerJson = $driver->getComposerInformation($driver->getRootIdentifier());
      $package = $composerJson;
      foreach($driver->getBranches() as $branch => $reference) {
        $package['version'] = $branch;
        $package['dist'] = (object)$driver->getDist($reference);
        $package['source'] = (object)$driver->getSource($reference);
        NestedArray::setValue($context['results'], [$composerJson['name'], $branch], (object)$package);
      }

      $context['sandbox']['progress']++;
      $context['sandbox']['current_id'] = $composerJson['name'];
      $context['message'] = Html::escape($composerJson['name']);
    }
    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
  }

  public static function finishedRetrievePackages($success, $results, $operations, $elapsed) {
    if ($success) {
      $message = \Drupal::translation()->formatPlural(count($results), 'One item processed.', '@count items processed.');
    }
    else {
      $message = t('Finished with an error.');
    }
    if (!empty($results)) {
      $cid = "package.github";
      \Drupal::cache()
        ->set($cid, $results);
    }
    $items = [];
    foreach ($results as $repoName => $repo) {
      $items[] = $repoName;
    }
    \Drupal::messenger()->addMessage($message . "\n Loaded repositories: " . implode(", ", $items));

  }

  /**
   * @inheritDoc
   */
  protected function getEditableConfigNames()
  {
    return ['package.settings'];
  }

  /**
   * @inheritDoc
   */
  public function getFormId()
  {
    return "package_config";
  }
}
