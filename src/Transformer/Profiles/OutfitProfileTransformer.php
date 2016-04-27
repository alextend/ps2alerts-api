<?php

namespace Ps2alerts\Api\Transformer\Profiles;

use League\Fractal\TransformerAbstract;
use Ps2alerts\Api\Contract\HttpClientAwareInterface;
use Ps2alerts\Api\Contract\HttpClientAwareTrait;
use Ps2alerts\Api\Contract\RedisAwareInterface;
use Ps2alerts\Api\Contract\RedisAwareTrait;
use Ps2alerts\Api\Repository\Metrics\OutfitRepository;
use Ps2alerts\Api\Repository\Metrics\OutfitTotalRepository;
use Ps2alerts\Api\Repository\Metrics\PlayerRepository;
use Ps2alerts\Api\Repository\Metrics\PlayerTotalRepository;
use Ps2alerts\Api\Transformer\Profiles\OutfitInvolvementTransformer;
use Ps2alerts\Api\Transformer\Profiles\OutfitPlayersTransformer;

class OutfitProfileTransformer extends TransformerAbstract
    implements HttpClientAwareInterface, RedisAwareInterface
{
    use HttpClientAwareTrait;
    use RedisAwareTrait;

    /**
     * List of available includes to this resource
     *
     * @var array
     */
    protected $availableIncludes = [
        'involvement',
        'metrics',
        'players'
    ];

    protected $outfitRepo;
    protected $outfitTotalRepo;
    protected $playerRepo;
    protected $playerTotalRepo;

    public function __construct(
        OutfitRepository $outfitRepo,
        OutfitTotalRepository $outfitTotalRepo,
        PlayerRepository      $playerRepo,
        PlayerTotalRepository $playerTotalRepo
    ) {
        $this->outfitRepo      = $outfitRepo;
        $this->outfitTotalRepo = $outfitTotalRepo;
        $this->playerRepo      = $playerRepo;
        $this->playerTotalRepo = $playerTotalRepo;
    }

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

    public function getOutfitMembers($data, $offset)
    {
        $client = $this->getHttpClientDriver();
        $redis = $this->getRedisDriver();

        $key = "ps2alerts:api:profiles:outfit:census:members:{$data['outfitID']}:{$offset}";

        // If we have this cached already
        if (! empty($redis->exists($key))) {
            return json_decode($redis->get($key));
        }

        $namespace = 'ps2:v2';

        if ($data['outfitServer'] >= 2000) {
            $namespace = 'ps2ps4eu';
        } elseif ($data['outfitServer'] >= 1000) {
            $namespace = 'ps2ps4us';
        }

        $response = $client->get(
            "https://census.daybreakgames.com/s:planetside2alertstats/get/{$namespace}/outfit_member?outfit_id={$data['outfitID']}&c:limit=5000&c:start={$offset}"
        );

        $body = json_decode($response->getBody()->getContents(), true);

        $count = count($body['outfit_member_list']);
        $chars = [];

        for ($i = 0; $i < $count; $i++) {
            $chars[] = $body['outfit_member_list'][$i]['character_id'];
        }

        // Cache results in redis
        $redis->setEx($key, 3600, json_encode($chars));

        return $chars;
    }

    public function includeInvolvement($data)
    {
        $data = $this->outfitRepo->readAllById($data['outfitID'], 'outfitID');
        return $this->collection($data, new OutfitInvolvementTransformer);
    }

    public function includeMetrics($data)
    {
        $metrics = [
            'kills'     => 0,
            'deaths'    => 0,
            'teamkills' => 0,
            'suicides'  => 0,
            'headshots' => 0
        ];

        $alerts = $this->outfitRepo->readAllById($data['outfitID'], 'outfitID');
        $count = count($alerts);
        $metrics['involvement'] = $count;

        // Calculate metrics
        for ($i = 0; $i < $count; $i++) {
            $metrics['kills']     = $metrics['kills'] + $alerts[$i]['outfitKills'];
            $metrics['deaths']    = $metrics['deaths'] + $alerts[$i]['outfitDeaths'];
            $metrics['teamkills'] = $metrics['teamkills'] + $alerts[$i]['outfitTKs'];
            $metrics['suicides']  = $metrics['suicides'] + $alerts[$i]['outfitSuicides'];
        }

        return $this->item($metrics, new OutfitMetricsTransformer);
    }

    public function includePlayers($data)
    {
        $chars = $this->getOutfitMembers($data, 0);

        // If we've hit the upper census limit
        if (count($chars) == 5000) {
            $chars = array_merge($chars, $this->getOutfitMembers($data, 5000));
        }

        $count = count($chars);

        if ($count == 0) {
            return null;
        }

        $players = $this->getOutfitPlayers($data, $chars);

        return $this->collection(
            $players,
            new OutfitPlayersTransformer
        );
    }

    public function getOutfitPlayers($data, $chars)
    {
        $redis = $this->getRedisDriver();
        $key = "ps2alerts:api:profiles:outfit:players:{$data['outfitID']}";

        // If we have this cached already
        if (! empty($redis->exists($key))) {
            return json_decode($redis->get($key), true);
        }

        $count = count($chars);
        $whereIn = '';

        // Get all members out of the database
        for ($i = 0; $i < $count; $i++) {
            $whereIn .= "'{$chars[$i]}',";
        }

        // Trim trailing comma
        $whereIn = rtrim($whereIn, ',');

        $query = $this->playerTotalRepo->newQuery();

        $query->cols(['playerID, playerName, playerKills, playerDeaths, playerTeamKills, playerSuicides, headshots']);
        $query->where("playerID IN ({$whereIn})");

        $players = $this->playerTotalRepo->fireStatementAndReturn($query);

        // Cache results in redis
        $redis->setEx($key, 3600, json_encode($players));

        return $players;
    }
}
