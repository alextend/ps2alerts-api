<?php

namespace Ps2alerts\Api\Transformer\Metrics;

use League\Fractal\TransformerAbstract;

class MapTransformer extends TransformerAbstract
{
    /**
     * The tranform method required by Fractal to parse the data and return proper typing and fields.
     *
     * @param  array $data Data to transform
     *
     * @return array
     */
    public function transform($data)
    {
        return [
            'id'                 => (int) $data['dataID'],
            'alertID'            => (int) $data['resultID'],
            'timestamp'          => (int) $data['timestamp'],
            'facilityID'         => (int) $data['facilityID'],
            'facilityNewFaction' => (int) $data['facilityOwner'],
            'facilityOldFaction' => (int) $data['facilityOldOwner'],
            'durationHeld'       => (int) $data['durationHeld'],
            'controlVS'          => (int) $data['controlVS'],
            'controlNC'          => (int) $data['controlNC'],
            'controlTR'          => (int) $data['controlTR'],
            'server'             => (int) $data['world'],
            'zone'               => (int) $data['zone'],
            'outfitCaptured'     => (int) $data['outfitCaptured'],
            'isDefence'          => (boolean) $data['defence']
        ];
    }
}