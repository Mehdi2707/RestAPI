<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class VersioningService
{
    private $requestStack;

    /**
     * @param RequestStack $requestStack
     * @param ParameterBagInterface $params
     */
    public function __construct(RequestStack $requestStack, ParameterBagInterface $params)
    {
        $this->requestStack = $requestStack;
        $this->defaultVersion = $params->get("default_api_version");
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        $version = $this->defaultVersion;

        $request = $this->requestStack->getCurrentRequest();
        $accept = $request->headers->get('Accept');

        $entete = explode(';', $accept);

        foreach($entete as $value) {
            if(strpos($value, 'version') !== false) {
                $version = explode('=', $value);
                $version = $version[1];
                break;
            }
        }

        return $version;
    }
}