<?php

namespace Ps2alerts\Api\Controller\Endpoint;

use League\Fractal\Manager;
use Ps2alerts\Api\Controller\Endpoint\AbstractEndpointController;
use Ps2alerts\Api\Contract\ConfigAwareInterface;
use Ps2alerts\Api\Contract\ConfigAwareTrait;
use Ps2alerts\Api\Repository\AlertRepository;
use Ps2alerts\Api\Transformer\AlertTotalTransformer;
use Ps2alerts\Api\Transformer\AlertTransformer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AlertEndpointController extends AbstractEndpointController implements
    ConfigAwareInterface
{
    use ConfigAwareTrait;

    /**
     * Construct
     *
     * @param Ps2alerts\Api\Repository\AlertRepository   $repository
     * @param Ps2alerts\Api\Transformer\AlertTransformer $transformer
     * @param League\Fractal\Manager                     $fractal
     */
    public function __construct(
        AlertRepository  $repository,
        AlertTransformer $transformer,
        Manager          $fractal
    ) {
        $this->repository  = $repository;
        $this->transformer = $transformer;
        $this->fractal     = $fractal;
    }

    /**
     * Returns a single alert's information
     *
     * @param  Symfony\Component\HttpFoundation\Request  $request
     * @param  Symfony\Component\HttpFoundation\Response $response
     * @param  array                                     $args
     *
     * @return \League\Fractal\TransformerAbstract
     */
    public function getSingle(Request $request, Response $response, array $args)
    {
        $alert = $this->repository->readSingleById($args['id']);

        if (empty($alert)) {
            return $this->errorEmpty($response);
        }

        return $this->respond('item', $alert, $this->transformer, $request, $response);
    }

    /**
     * Returns all currently running alerts
     *
     * @param  Symfony\Component\HttpFoundation\Request  $request
     * @param  Symfony\Component\HttpFoundation\Response $response
     *
     * @return \League\Fractal\TransformerAbstract
     */
    public function getActives(Request $request, Response $response)
    {
        $actives = $this->repository->readAllByFields(['InProgress' => 1]);

        if (empty($actives)) {
            return $this->errorEmpty($response);
        }

        return $this->respond('collection', $actives, $this->transformer, $request, $response);
    }
}
