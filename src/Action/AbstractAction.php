<?php

namespace Bolt\Extension\Bolt\MarketPlace\Action;

use Bolt\Config;
use Bolt\Extension\Bolt\MarketPlace\Storage\Entity;
use Bolt\Extension\Bolt\MarketPlace\Storage\Repository;
use Bolt\Storage\EntityManager;
use Silex\Application;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Abstract 'Action' class.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
abstract class AbstractAction implements ActionInterface
{
    /** @var Application */
    private $app;

    /**
     * Constructor.
     *
     * @TODO We don't need app for live
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function getAppService($name)
    {
        return $this->app[$name];
    }

    /**
     * @param Entity\Package $package
     *
     * @return array|false
     */
    public function getWebhookData(Entity\Package $package)
    {
        /** @var \Bolt\Extension\Bolt\Members\AccessControl\Session $members */
        $members = $this->getAppService('members.session');
        if (!$members->hasAuthorisation()) {
            return false;
        }
        if ($package->getAccountId() !== $members->getAuthorisation()->getAccount()->getGuid()) {
            return false;
        }

        /** @var EntityManager $em */
        $em = $this->getAppService('storage');
        if (!$package->getToken()) {
            $package->regenerateToken();
            $em->getRepository(Entity\Package::class)->save($package);
        }
        $statRepo = $em->getRepository(Entity\Stat::class);
        /** @var Entity\Stat $stat */
        $stat = $statRepo->findOneBy(['package_id' => $package->getId(), 'type' => 'webhook'], ['recorded', 'DESC']);
        if ($stat !== false) {
            /** @var Session $session */
            $session = $this->getAppService('session');
            if ($session->get('pending-' .  $package->getToken(), false)) {
                return false;
            }
        }

        /** @var UrlGeneratorInterface $urlGen */
        $urlGen = $this->getAppService('url_generator');
        $source = explode('/', ltrim(parse_url($package->getSource(), PHP_URL_PATH), '/'));

        return [
            'callback' => $package->getToken() ? $urlGen->generate('hookListener', ['token' => $package->getToken()], UrlGeneratorInterface::ABSOLUTE_URL) : false,
            'latest'   => $stat,
            'user'     => $source[0],
            'repo'     => $source[1],
            'token'    => $package->getToken(),
        ];
    }

    /**
     * @param Entity\Package $package
     *
     * @return array
     */
    protected function getUpdated(Entity\Package $package)
    {
        $em = $this->getAppService('storage');
        /** @var Repository\PackageVersions $repo */
        $repo = $em->getRepository(Entity\PackageVersions::class);

        return [
            'dev'    => $repo->getLatestReleaseForStability($package->getId(), 'dev'),
            'stable' => $repo->getLatestReleaseForStability($package->getId(), 'stable'),
        ];
    }

    /**
     * @param Entity\Package $package
     *
     * @return Entity\PackageVersions[]
     */
    protected function getVersions(Entity\Package $package)
    {
        $em = $this->getAppService('storage');
        /** @var Repository\PackageVersions $repo */
        $repo = $em->getRepository(Entity\PackageVersions::class);
        /** @var Config $config */
        $config = $this->getAppService('config');
        $boltMajorVersions = $config->get('general/bolt_major_versions');

        $versions = [];
        foreach ($boltMajorVersions as $boltMajorVersion) {
            $entity = $repo->getLatestCompatibleVersion($package->getId(), 'stable', $boltMajorVersion);
            if ($entity === false) {
                continue;
            }
            $versions[$boltMajorVersion] = $entity;
        }

        return $versions;
    }
}
