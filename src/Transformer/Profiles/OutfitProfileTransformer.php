<?php

namespace Ps2alerts\Api\Transformer\Profiles;

use League\Fractal\TransformerAbstract;

class OutfitProfileTransformer extends TransformerAbstract
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
            'name'      => (string) $data['outfitName'],
            'tag'       => (string) $data['outfitTag'],
            'faction'   => (int) $data['outfitFaction'],
            'kills'     => (int) $data['outfitKills'],
            'deaths'    => (int) $data['outfitDeaths'],
            'teamkills' => (int) $data['outfitTKs'],
            'suicides'  => (int) $data['outfitSuicides'],
            'server'    => (int) $data['outfitServer'],
            'captures'  => (int) $data['outfitCaptures']
        ];
    }
}
