<?php

namespace App\Services\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Services\Actor\ActorChangeWorkflow;
use App\Services\State\Processor\Common\GeoDataProcessor;
use App\Services\State\Processor\Common\ValidatorProcessor;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ResourceProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private ValidatorProcessor $validatorProcessor,
        private GeoDataProcessor $geoDataProcessor,
        private ActorChangeWorkflow $workflow,
    ) {
    }

    /**
     * @param resource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        // Admin PDC / point focal de l'acteur lié : traitement direct. Administrateur de page : mise en
        // attente de validation du point focal (cf. ActorChangeWorkflow). Sans acteur lié : inchangé.
        return $this->workflow->handle($data, $operation, $context, function () use ($data, $operation, $uriVariables, $context) {
            $this->validatorProcessor->process($data, $operation, $uriVariables, $context);
            $this->geoDataProcessor->process($data, $operation, $uriVariables, $context, true);

            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        });
    }
}