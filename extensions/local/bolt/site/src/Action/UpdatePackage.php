<?php
namespace Bolt\Extension\Bolt\MarketPlace\Action;

use Aura\Router\Router;
use Bolt\Extension\Bolt\MarketPlace\Entity;
use Bolt\Extension\Bolt\MarketPlace\Service\PackageManager;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Twig_Environment;

class UpdatePackage
{
    public $renderer;
    public $em;
    public $router;
    public $packageManager;
    
    public function __construct(Twig_Environment $renderer, EntityManager $em, Router $router, PackageManager $packageManager)
    {
        $this->renderer = $renderer;
        $this->em = $em;
        $this->router = $router;
        $this->packageManager = $packageManager;
    }
    
    public function __invoke(Request $request, $params)
    {
        $repo = $this->em->getRepository(Entity\Package::class);
        $package = $repo->findOneBy(['id' => $params['package']]);
        if ($package->account->admin) {
            $isAdmin = true;
        } else {
            $isAdmin = false;
        }
        try {
            $this->packageManager->validate($package, $isAdmin);
            $package = $this->packageManager->syncPackage($package);
            $request->getSession()->getFlashBag()->add('success', 'Package ' . $package->name . ' has been updated');
            if ($package->account->approved) {
                $package->approved = true;
            }
            if (!$package->token) {
                $package->regenerateToken();
            }
        } catch (\Exception $e) {
            $request->getSession()->getFlashBag()->add('error', 'Package has an invalid composer.json and will be disabled!');
            $request->getSession()->getFlashBag()->add('warning', implode(' : ', [$e->getMessage(), $e->getFile(), $e->getLine()]));
            $package->approved = false;
        }
        
        $this->em->flush();

        return new RedirectResponse($this->router->generate('profile'));
    }
}
