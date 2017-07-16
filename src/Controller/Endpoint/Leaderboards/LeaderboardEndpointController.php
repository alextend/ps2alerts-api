<?php

namespace Ps2alerts\Api\Controller\Endpoint\Leaderboards;

use League\Fractal\Manager;
use Ps2alerts\Api\Controller\Endpoint\AbstractEndpointController;
use Ps2alerts\Api\Controller\Endpoint\Data\DataEndpointController;
use Ps2alerts\Api\Exception\CensusEmptyException;
use Ps2alerts\Api\Exception\CensusErrorException;
use Ps2alerts\Api\Exception\InvalidArgumentException;
use Ps2alerts\Api\Repository\Metrics\OutfitTotalRepository;
use Ps2alerts\Api\Repository\Metrics\PlayerTotalRepository;
use Ps2alerts\Api\Repository\Metrics\WeaponTotalRepository;
use Ps2alerts\Api\Transformer\Leaderboards\LeaderboardUpdatedTransformer;
use Ps2alerts\Api\Transformer\Leaderboards\OutfitLeaderboardTransformer;
use Ps2alerts\Api\Transformer\Leaderboards\PlayerLeaderboardTransformer;
use Ps2alerts\Api\Transformer\Leaderboards\WeaponLeaderboardTransformer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class LeaderboardEndpointController extends AbstractEndpointController
{
    protected $playerTotalRepository;
    protected $outfitTotalRepository;
    protected $weaponTotalRepository;
    protected $dataEndpoint;

    /**
     * Construct
     *
     * @param League\Fractal\Manager $fractal
     */
    public function __construct(
        Manager                $fractal,
        PlayerTotalRepository  $playerTotalRepository,
        OutfitTotalRepository  $outfitTotalRepository,
        WeaponTotalRepository  $weaponTotalRepository,
        DataEndpointController $dataEndpoint
    ) {

        $this->fractal = $fractal;
        $this->playerTotalRepository = $playerTotalRepository;
        $this->outfitTotalRepository = $outfitTotalRepository;
        $this->weaponTotalRepository = $weaponTotalRepository;
        $this->dataEndpoint          = $dataEndpoint;
    }

    /**
     * Get Player Leaderboard
     *
     * @return \League\Fractal\Manager
     */
    public function players()
    {
        $valid = $this->validateRequestVars();

        // If validation didn't pass, chuck 'em out
        if ($valid !== true) {
            return $this->errorWrongArgs($valid->getMessage());
        }

        $server = $_GET['server'];
        $limit  = $_GET['limit'];
        $offset = $_GET['offset'];

        // Translate field into table specific columns
        if (isset($_GET['field'])) {
            $field = $this->getField('players', $_GET['field']);
        }

        if (! isset($field)) {
            return $this->errorWrongArgs('Field wasn\'t provided and is required.');
        }

        // Perform Query
        $query = $this->playerTotalRepository->newQuery();
        $query->cols(['*']);
        $query->orderBy(["{$field} desc"]);

        if (isset($server)) {
            $query->where('playerServer = ?', $server);
        }

        if (isset($limit)) {
            $query->limit($limit);
        } else {
            $query->limit(10); // Set default limit
        }

        if (isset($offset)) {
            $query->offset($offset);
        }

        $players = $this->playerTotalRepository->fireStatementAndReturn($query);

        $count = count($players);

        // Gets outfit details
        for ($i = 0; $i < $count; $i++) {
            if (! empty($players[$i]['playerOutfit'])) {
                // Gets outfit details
                try {
                    $outfit = $this->dataEndpoint->getOutfit($players[$i]['playerOutfit']);
                } catch (CensusErrorException $e) {
                    $outfit = null;
                } catch (CensusEmptyException $e) {
                    $outfit = null;
                }

                $players[$i]['playerOutfit'] = $outfit;
            }
        }

        return $this->respond(
            'collection',
            $players,
            new PlayerLeaderboardTransformer
        );
    }

    /**
     * Get Outfit Leaderboard
     *
     * @return \League\Fractal\Manager
     */
    public function outfits()
    {
        $valid = $this->validateRequestVars();

        // If validation didn't pass, chuck 'em out
        if ($valid !== true) {
            return $this->errorWrongArgs($valid->getMessage());
        }

        $server = $_GET['server'];
        $limit  = $_GET['limit'];
        $offset = $_GET['offset'];

        // Translate field into table specific columns

        if (isset($_GET['field'])) {
            $field = $this->getField('outfits', $_GET['field']);
        }

        if (! isset($field)) {
            return $this->errorWrongArgs('Field wasn\'t provided and is required.');
        }

        // Perform Query
        $query = $this->outfitTotalRepository->newQuery();
        $query->cols(['*']);
        $query->orderBy(["{$field} desc"]);
        $query->where('outfitID > 0');

        if (isset($server)) {
            $query->where('outfitServer = ?', $server);
        }

        if (isset($limit)) {
            $query->limit($limit);
        } else {
            $query->limit(10); // Set default limit
        }

        if (isset($offset)) {
            $query->offset($offset);
        }

        return $this->respond(
            'collection',
            $this->outfitTotalRepository->fireStatementAndReturn($query),
            new OutfitLeaderboardTransformer
        );
    }

    /**
     * Get Weapon Leaderboard
     *
     * @return \League\Fractal\Manager
     */
    public function weapons()
    {
        $valid = $this->validateRequestVars();

        // If validation didn't pass, chuck 'em out
        if ($valid !== true) {
            return $this->errorWrongArgs($valid->getMessage());
        }

        // Translate field into table specific columns
        if (isset($_GET['field'])) {
            $field = $this->getField('weapons', $_GET['field']);
        }

        if (! isset($field)) {
            return $this->errorWrongArgs('Field wasn\'t provided and is required.');
        }

        $weapons = $this->checkRedis('api', 'leaderboards', "weapons:{$field}");

        // If we have this cached already
        if (empty($weapons)) {
            // Perform Query
            $query = $this->weaponTotalRepository->newQuery();
            $query->cols([
                'weaponID',
                'SUM(killCount) as killCount',
                'SUM(teamkills) as teamkills',
                'SUM(headshots) as headshots'
            ]);
            $query->where('weaponID > 0');
            $query->orderBy(["{$field} desc"]);
            $query->groupBy(['weaponID']);

            $weapons = $this->weaponTotalRepository->fireStatementAndReturn($query);

            // Cache results in redis
            $this->storeInRedis('api', 'leaderboards', "weapons:{$field}", $weapons);
        }

        return $this->respond(
            'collection',
            $weapons,
            new WeaponLeaderboardTransformer
        );
    }

    /**
     * Validates the request variables
     *
     * @throws InvalidArgumentException
     * @return boolean
     */
    public function validateRequestVars()
    {
        try {
            if (! empty($_GET['field'])) {
                $this->parseField($_GET['field']);
            }

            if (! empty($_GET['server'])) {
                $this->parseServer($_GET['server']);
            }

            if (! empty($_GET['limit'])) {
                $this->parseOffset($_GET['limit']);
            }

            if (! empty($_GET['offset'])) {
                $this->parseOffset($_GET['offset']);
            }
        } catch (InvalidArgumentException $e) {
            return $e;
        }

        return true;
    }

    /**
     * Validate the field requested
     *
     * @return string
     */
    public function parseField($field)
    {
        $validFields = [
            'kills',
            'deaths',
            'teamkills',
            'suicides',
            'headshots',
            'captures'
        ];

        if (! empty($field) && in_array($field, $validFields)) {
            return $field;
        }

        throw new InvalidArgumentException("Field '{$field}' is not supported.");
    }

    /**
     * Validate the server requested
     *
     * @return string
     */
    public function parseServer($server)
    {
        $validServers = $this->getConfigItem('servers');

        // Remove Jaeger
        if (($key = array_search(19, $validServers)) !== false) {
            unset($validServers[$key]);
        }

        if (! empty($server) && in_array($server, $validServers)) {
            return $server;
        }

        throw new InvalidArgumentException("Server '{$server}' is not supported.");
    }

    /**
     * Parses limit, making sure it's numerical and valid
     *
     * @return boolean
     */
    public function parseLimit($limit)
    {
        if (! isset($limit) && ! is_numeric($limit)) {
            throw new InvalidArgumentException("Limit needs to be in numerical format.");
        }

        return $limit;
    }

    /**
     * Parses offset, making sure it's numerical and valid
     */
    public function parseOffset($offset)
    {
        if (! isset($offset) && ! is_numeric($offset)) {
            throw new InvalidArgumentException("Offset needs to be in numerical format.");
        }

        return $offset;
    }

    /**
     * Gets the appropiate field for the table and handles some table naming oddities
     * @param  string $mode  Where it's coming from
     * @param  string $input Field to look at
     * @return string
     */
    public function getField($mode, $input) {
        $field = null;

        // Handle headshots case
        if (($mode === 'players' || $mode === 'weapons') && $input === 'headshots') {
            return 'headshots';
        }

        if ($mode === 'players') {
            $prefix = 'player';

            if ($input === 'teamkills') {
                return 'playerTeamKills'; # Deal with discrepency
            }

            return $prefix . ucfirst($input);
        }

        if ($mode === 'outfits') {
            $prefix = 'outfit';

            if ($input === 'teamkills') {
                return 'outfitTKs'; # Deal with discrepency
            }

            return $prefix . ucfirst($input);
        }

        if ($mode === 'weapons') {
            switch ($input) {
                case 'kills':
                    $field = 'killCount';
                    break;
                case 'headshots':
                    $field = 'headshots';
                    break;
                case 'teamkills':
                    $field = 'teamkills';
                    break;
            }
        }

        return $field;
    }
}
