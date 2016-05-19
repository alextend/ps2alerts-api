<?php

namespace Ps2alerts\Api\Command;

use Ps2alerts\Api\Repository\AlertRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteAlertCommand extends Command
{
    protected $alertRepo;
    protected $auraFactory;
    protected $db;

    protected function configure()
    {
        $this
            ->setName('DeleteAlert')
            ->setDescription('Deletes an alert and corrects totals')
            ->addArgument(
                'alert',
                InputArgument::REQUIRED,
                'Alert ID to process'
            )
        ;

        global $container; // Inject Container
        $this->alertRepo = $container->get('Ps2alerts\Api\Repository\AlertRepository');
        $this->auraFactory = $container->get('Ps2alerts\Api\Factory\AuraFactory');
        $this->db = $container->get('Database');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $id = $input->getArgument('alert');

        $this->processAlert($id, $output);
    }

    public function processAlert($id, $output, $force = null)
    {
        $alert = $this->alertRepo->readSingleById($id, 'primary', true);

        if (empty($force) && empty($id)) {
            $output->writeln("ALERT {$id} DOES NOT EXIST!");
            return false;
        }

        $output->writeln("DELETING ALERT {$id}");

        $players = $this->processPlayers($id);
        $output->writeln("{$players} players processed");

        $outfits = $this->processOutfits($id);
        $output->writeln("{$outfits} outfits processed");

        $types = $this->processXP($id);
        $output->writeln("{$types} XP types processed");
        $tables = [
            'ws_classes',
            'ws_classes_totals',
            'ws_combat_history',
            'ws_factions',
            'ws_instances',
            'ws_map',
            'ws_map_initial',
            'ws_outfits',
            'ws_players',
            'ws_pops',
            'ws_vehicles',
            'ws_vehicles_totals',
            'ws_weapons',
            'ws_weapons_totals',
            'ws_xp'
        ];

        $this->deleteAllFromTables($tables, $id, $output);

        // Finally delete the alert
        $this->deleteAlert($id);
        $output->writeln("Alert {$id} successfully deleted!");

        return true;
    }

    protected function processPlayers($id)
    {
        $cols = [
            'playerID',
            'playerKills',
            'playerDeaths',
            'playerTeamKills',
            'playerSuicides',
            'headshots'
        ];

        $fields = [
            'playerKills',
            'playerDeaths',
            'playerTeamKills',
            'playerSuicides',
            'headshots'
        ];

        return $this->runProcess(
            $id,
            $cols,
            'ws_players',
            'ws_players_total',
            'playerID',
            $fields
        );
    }

    protected function processOutfits($id)
    {
        $cols = [
            'outfitID',
            'outfitKills',
            'outfitDeaths',
            'outfitTKs',
            'outfitSuicides'
        ];

        $fields = [
            'outfitKills',
            'outfitDeaths',
            'outfitTKs',
            'outfitSuicides'
        ];

        return $this->runProcess(
            $id,
            $cols,
            'ws_outfits',
            'ws_outfits_total',
            'outfitID',
            $fields
        );
    }

    protected function processXP($id)
    {
        $cols = [
            'SUM(occurances) AS occurances',
            'type'
        ];

        $fields = [
            'occurances'
        ];

        return $this->runProcess(
            $id,
            $cols,
            'ws_xp',
            'ws_xp_totals',
            'type',
            $fields,
            ['type']
        );
    }

    protected function runProcess(
        $id,
        array $cols,
        $table,
        $totalsTable,
        $filter,
        array $fields,
        $groupBy = null
    ) {
        // Check each cols to make sure we handle SUM(BLAH) AS BLAH issues

        foreach($cols as $key => $col) {
            if (strpos($col, 'AS ') !== false) {
                $pos = strrpos($col, 'AS ') + 3; // Plus 3 for "AS "
                $len = strlen($col);
                $diff = $len - $pos;
                $field = substr($col, $pos, $diff);

                $cols[$key] = $field;
            }
        }

        $query = $this->auraFactory->newSelect();
        $query->cols($cols);
        $query->from($table);
        $query->where('resultID = ?', $id);

        if (! empty($groupBy)) {
            $query->groupBy($groupBy);
        }

        $allQuery = $this->db->prepare($query->getStatement());
        $allQuery->execute($query->getBindValues());

        $count = 0;

        while ($row = $allQuery->fetch(\PDO::FETCH_OBJ)) {
            $count++;

            $update = $this->auraFactory->newUpdate();
            $update->table($totalsTable);
            $update->where("{$filter} = ?", $row->$filter);

            foreach($fields as $field) {
                $update->set($field, "{$field} - {$row->$field}");
            }

            $updateQuery = $this->db->prepare($update->getStatement());
            $updateQuery->execute($update->getBindValues());
        }

        return $count;
    }

    protected function deleteAllFromTables(array $tables, $id, OutputInterface $output)
    {
        foreach($tables as $table) {
            $delete = $this->auraFactory->newDelete();
            $delete->from($table);
            $delete->where('resultID = ?', $id);

            $deleteQuery = $this->db->prepare($delete->getStatement());
            $deleteQuery->execute($delete->getBindValues());

            $affected = $deleteQuery->rowCount();
            $output->writeln("{$affected} rows deleted from table \"{$table}\"");
        }
    }

    protected function deleteAlert($id)
    {
        $delete = $this->auraFactory->newDelete();
        $delete->from('ws_results');
        $delete->where('ResultID = ?', $id);

        $deleteQuery = $this->db->prepare($delete->getStatement());
        $deleteQuery->execute($delete->getBindValues());
    }
}
