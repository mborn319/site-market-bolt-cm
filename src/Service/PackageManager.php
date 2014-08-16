<?php

namespace Bolt\Extensions\Service;

use Composer\Config;
use Composer\IO\NullIO;
use Composer\Repository\VcsRepository;


class PackageManager
{
    
    public $config;
    
    public function __construct(Config $config)
    {
        $this->config = $config;
    }
    
    
    public function syncPackage($package)
    {
        $repository = $this->loadRepository($package);
        $information = $this->loadInformation($package);
        

        $versions = $repository->getPackages();
        $pv = [];
        foreach($versions as $version) {
            $pv[]=$version->getPrettyVersion();
        }
        
        $package->setName($information['name']);
        if(isset($information['type'])) {
            $package->setType($information['type']);
        }
        if(isset($information['keywords'])) {
            $package->setKeywords(implode(',',$information['keywords']));
        }
        if(isset($information['authors'])) {
            $authors = [];
            foreach($information['authors'] as $author) {
                $authors[]=$author['name'];
            }
            $package->setAuthors(implode(',',$authors));
        }
        $package->setRequirements(json_encode($information['require']));
        $package->setVersions(implode(',', $pv));
        $package->updated = new \DateTime;
        return $package;
    }
    
    public function loadInformation($package, $identifier = null)
    {
        $repository = $this->loadRepository($package);
        $driver = $repository->getDriver();
        
        if (null === $identifier) {
            $identifier = $driver->getRootIdentifier();
        }
        $information = $driver->getComposerInformation($identifier);
        return $information;
    }
    
    public function loadRepository($package)
    {
        putenv("COMPOSER_HOME=".sys_get_temp_dir());
        $io = new NullIO();
        $io->loadConfiguration($this->config);
        $repository = new VcsRepository(['url' => $package->getSource()], $io, $this->config);
        return $repository;
    }
    
    public function getVersions($package)
    {
        $rep = $this->loadRepository($package);
        $versions = $rep->getPackages();
        foreach($versions as $version) {
            $pv[]=$version->getPrettyVersion();
        }

        foreach($package->versions as $version) {
            $info[$package->getStability()][$version] = $this->loadInformation($package, $version);
        }
        print_r($info); exit;
        return $info;
    }



}