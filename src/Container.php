<?php
namespace Kontener\Container;

use Interop\Container\ContainerInterface as InteropContainerInterface;
use Kontener\Container\Exception\ServiceNotFoundException;
use Kontener\Container\Exception\ParameterNotFoundException;
use Kontener\Container\Exception\ContainerException;
use Kontener\Container\Reference\ParameterReference;
use Kontener\Container\Reference\ServiceReference;

class Container implements InteropContainerInterface
{
    private $services;
    private $parameters;
    private $serviceStore;

    public function __construct(array $service = [], array $parameters=[])
    {
        $this->services = $services;
        $this->parameters = $parameters;
        $this->serviceStore = [];
    }

    public function get($name)
    {
        if (!$this->has($name))
        {
            throw new ServiceNotFoundException('Service not found ' . $name);
        }

        if (!isset($this->serviceStore[$name]))
        {
            $this->serviceStore[$name] = $this->createService($name);
        }

        return $this->serviceStore[$name];
    }

    public function has($name)
    {
        return isset($this->services[$name]);
    }

    public function getParameter($name)
    {
        $tokens = explode('.', $name);
        $context = $this->parameters;
        while(null !== ( $token = array_shift($tokens) ) ) {
            if (!isset($context[$token]))
            {
                throw new ParameterNotFoundException('Parameter not found'. $name);
            }

            $context = $context[$token];
        }

        return $context;
    }

    private function createService($name)
    {
        $entry = &$this->services[$name];
        if(!is_array || !isset($entry['class'])) {
            throw new ContainerException(
                $nama . " service entry must be an array containing a \"class\" key"
            );
        } elseif(!class_exist($entry['class'])) {
            throw new ContainerException(
                $name . " service class does not exist: " . $entry['class'];
            )
        } elseif(isset($entry['lock'])) {
            throw new ContainerException(
                $name . " service contains a circular reference"
            );
        }

        $entry['lock'] = true;

        $arguments = isset($entry['arguments']) ? $this->resolveArguments($name, $entry['arguments']) : [] ;

        $reflector =new \ReflectionClas($entry['class']);
        $service = $reflector->newInstanceArgs($arguments);

        if (isset($entry['calls'])){
            $this->initializeService($service, $name, $entry['calls']);
        }

        return $service;
    }

    private function resolveArguments($name, array $argumentDefinitions)
    {
        $arguments = [];

        foreach ($argumentDefinitions as $argumentDefinition) {
            if ($argumentDefinition instanceof ServiceReference){
                $argumentServiceName = $argumentDefinition->getName();
            } elseif ($argumentDefinition instanceof ParameterReference) {
                $argumentParameterName = $argumentDefinition->getName();
                $arguments[] = $this->getParameter($argumentParameterName);
            } else {
                $arguments[] = $argumentDefinition;
            }

            return $arguments;
        }
    }

    private function initializeService($service, $name, array $callDefinitions)
    {
        foreach ($callDefinitions as $callDefinition){
            if (!is_array($callDefinition) || !isset($callDefinition['method'])) {
                throw new ContainerException(
                    $name . " service call must be arrays containing \"method\" ";
                );
            } elseif(!is_callable([$service, $callDefinition['method']])) {
                throw new ContainerException(
                    $name . " service asks for call to uncallable method : " . $callDefinition['method'];
                );
            }

            $arguments = isset($callDefinition['arguments']) ? $this->resolveArguments($name, $callDefinition['arguments']) : [];

            call_user_func_array([$service, $callDefinition['method']], $arguments);
        }
    }

}