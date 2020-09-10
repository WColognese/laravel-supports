<?php

namespace WColognese\LaravelSupports\ServiceRequisitionValidate;

use ReflectionClass;
use WColognese\LaravelSupports\ServiceRequisitionValidate\Contracts\IRequisition;

class ServiceRequisitionValidate
{
    protected $service;

    /* @var ReflectionClass */
    protected $reflector;

    public static function of($service): self
    {
        if(is_null($service))
            throw new \InvalidArgumentException('$service cannot be null');

        $instance = new static();

        $instance->setService($service);

        return $instance;
    }

    public function setService(&$service)
    {
        $this->service = $service;

        $this->reflector = new ReflectionClass($this->service);

        if(method_exists($this->service, 'setValidatorLayerService'))
        {
            $this->service->setValidatorLayerService($this);
        }
    }

    public function __call($method, $arguments)
    {
        if(method_exists($this->service, $method))
        {
            return call_user_func_array(array($this->service, $method), $this->parseArgumentsToMethod($this->reflector->getMethod($method), $arguments));
        }
    }

    protected function parseArgumentsToMethod(\ReflectionMethod $method, $arguments): array
    {
        foreach ($method->getParameters() as $param)
        {
            if($class = $param->getClass())
            {
                // A validação da requisição é executada
                // naqueles parametros do metodo que implementam o
                // contrato \WColognese\ServiceRequisitionValidate\Contracts\IRequisition
                if($class->implementsInterface(IRequisition::class))
                {
                    // Cria-se uma nova instancia do parametro
                    // para se obter as regras e efetuar a validação
                    /* @var IRequisition $requisition */
                    $requisition = $class->newInstance();

                    // Seta a nova instancia do parametro com
                    // os argumentos passados na chamada do metodo
                    $requisition->setData($arguments[$param->getPosition()]);

                    // Substitui o argumento pela instancia,
                    // que é o que o metodo espera. Caso contrario
                    // uma excessão será lançada.
                    $arguments[$param->getPosition()] = $requisition;

                    $this->onFailsThrowValidationException($requisition);
                }
            }
        }

        return $arguments;
    }

    public function onFailsThrowValidationException(IRequisition $requisition)
    {
        if($requisition->getValidator()->fails())
        {
            throw new \Illuminate\Validation\ValidationException($requisition->getValidator());
        }
    }
}