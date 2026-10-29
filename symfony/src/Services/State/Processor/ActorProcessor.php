<?php

namespace App\Services\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Actor;
use App\Services\Actor\ActorChangeWorkflow;
use App\Services\State\Processor\Common\GeoDataProcessor;
use App\Services\State\Processor\Common\ValidatorProcessor;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ActorProcessor implements ProcessorInterface
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
     * @param Actor $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        // Admin PDC / point focal : traitement direct. Administrateur de page : la modification est mise en
        // attente de validation du point focal (cf. ActorChangeWorkflow).
        $result = $this->workflow->handle($data, $operation, $context, function () use ($data, $operation, $uriVariables, $context) {
            $this->validatorProcessor->process($data, $operation, $uriVariables, $context);
            $this->geoDataProcessor->process($data, $operation, $uriVariables, $context);

            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        });

        // Le créateur de la page, s'il n'est pas admin PDC, en devient le point focal.
        if ($operation instanceof Post) {
            $this->workflow->onActorCreated($data);
        }

        return $result;
    }
}