<?php

namespace Drupal\package;

use Composer\Factory;
use Composer\IO\NullIO;
use Composer\Repository\Vcs\GitHubDriver;
use Composer\Repository\Vcs\VcsDriverInterface;
use Composer\Repository\VcsRepository;
use Composer\Util\HttpDownloader;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\package\PackageInterface;
use Drupal\user\EntityOwnerTrait;
use Drupal\user\StatusItem;

/**
 * Defines the package package.
 *
 */
class Package
{
  const VCS_REPO_DRIVERS = [
    'github' => 'Composer\Repository\Vcs\GitHubDriver',
    'gitlab' => 'Composer\Repository\Vcs\GitLabDriver',
    'git-bitbucket' => 'Composer\Repository\Vcs\GitBitbucketDriver',
    'git' => 'Composer\Repository\Vcs\GitDriver',
    'hg-bitbucket' => 'Composer\Repository\Vcs\HgBitbucketDriver',
    'hg' => 'Composer\Repository\Vcs\HgDriver',
    'svn' => 'Composer\Repository\Vcs\SvnDriver',
  ];

  public static function getVcsRepository($repoUrl, $config = []) {
    // prevent local filesystem URLs
    if (preg_match('{^(\.|[a-z]:|/)}i', $repoUrl)) {
      return;
    }

    $repoUrl = preg_replace('{^git@github.com:}i', 'https://github.com/', $repoUrl);
    $repoUrl = preg_replace('{^git://github.com/}i', 'https://github.com/', $repoUrl);
    $repoUrl = preg_replace('{^(https://github.com/.*?)\.git$}i', '$1', $repoUrl);

    $repoUrl = preg_replace('{^git@gitlab.com:}i', 'https://gitlab.com/', $repoUrl);
    $repoUrl = preg_replace('{^(https://gitlab.com/.*?)\.git$}i', '$1', $repoUrl);

    $repoUrl = preg_replace('{^git@+bitbucket.org:}i', 'https://bitbucket.org/', $repoUrl);
    $repoUrl = preg_replace('{^bitbucket.org:}i', 'https://bitbucket.org/', $repoUrl);
    $repoUrl = preg_replace('{^https://[a-z0-9_-]*@bitbucket.org/}i', 'https://bitbucket.org/', $repoUrl);
    $repoUrl = preg_replace('{^(https://bitbucket.org/[^/]+/[^/]+)/src/[^.]+}i', '$1.git', $repoUrl);

    // normalize protocol case
    $repoUrl = preg_replace_callback('{^(https?|git|svn)://}i', function ($match) { return strtolower($match[1]) . '://'; }, $repoUrl);

    // avoid user@host URLs
    if (preg_match('{https?://.+@}', $repoUrl)) {
      return;
    }

    // validate that this is a somewhat valid URL
    if (!preg_match('{^([a-z0-9][^@\s]+@[a-z0-9-_.]+:\S+ | [a-z0-9]+://\S+)$}Dx', $repoUrl)) {
      return;
    }
    try {
      $io = new NullIO();
      $ioConfig = Factory::createConfig();
      if (!empty($config)) {
        $ioConfig->merge($config);
      }
      $io->loadConfiguration($ioConfig);
      $httpDownloader = new HttpDownloader($io, $ioConfig);
      $repository = new VcsRepository(['url' => $repoUrl], $io, $ioConfig, $httpDownloader, null, null, Package::VCS_REPO_DRIVERS);
      return $repository;
    }
    catch (\Exception $e) {

    }
    return NULL;
  }
}
