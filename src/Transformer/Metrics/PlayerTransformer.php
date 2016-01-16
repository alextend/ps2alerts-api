<?php

namespace Ps2alerts\Api\Transformer\Metrics;

use League\Fractal\TransformerAbstract;

class PlayerTransformer extends TransformerAbstract
{
    /**
     * The transform method required by Fractal to parse the data and return proper typing and fields.
     *
     * @param  array $data Data to transform
     *
     * @return array
     */
    public function transform($data)
    {
        return [
            'player'  => [
                'id'       => (int) $data['playerID'],
                'name'     => (string) $data['playerName'],
                'outfitID' => (int) $data['playerOutfit'],
                'faction'  => (int) $data['playerFaction']
            ],
            'metrics' => [
                'kills'     => (int) $data['playerKills'],
                'deaths'    => (int) $data['playerDeaths'],
                'teamkills' => (int) $data['playerTeamKills'],
                'suicides'  => (int) $data['playerSuicides'],
                'headshots' => (int) $data['headshots'],
            ]
        ];
    }
}
