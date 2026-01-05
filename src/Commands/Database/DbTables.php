<?php namespace Roline\Commands\Database;

/**
 * DbTables Command
 *
 * Lists all tables in the database with row counts.
 *
 * Usage:
 *   php roline db:tables
 *   php roline db:tables mydb
 *
 * @author Geoffrey Okongo <code@rachie.dev>
 * @copyright 2015 - 2050 Geoffrey Okongo
 * @category Roline
 * @package Roline\Commands\Database
 * @link https://github.com/glivers/roline
 * @license http://opensource.org/licenses/MIT MIT License
 * @version 1.0.0
 */

use Roline\Output;
use Rackage\Registry;

class DbTables extends DatabaseCommand
{
    public function description()
    {
        return 'List all tables with row counts';
    }

    public function usage()
    {
        return '[database]';
    }

    public function help()
    {
        parent::help();

        Output::info('Description:');
        Output::line('  Lists all tables in a database with their row counts.');
        Output::line();

        Output::info('Arguments:');
        Output::line('  [database]  Optional database name (defaults to config)');
        Output::line();

        Output::info('Examples:');
        Output::line('  php roline db:tables');
        Output::line('  php roline db:tables myapp');
        Output::line();
    }

    public function execute($arguments)
    {
        try {
            // Get database configuration
            $dbConfig = Registry::database();
            $driver = $dbConfig['default'] ?? 'mysql';
            $config = $dbConfig[$driver] ?? [];

            $host = $config['host'] ?? 'localhost';
            $username = $config['username'] ?? 'root';
            $password = $config['password'] ?? '';
            $port = $config['port'] ?? 3306;

            // Get database name from argument or config
            $databaseName = !empty($arguments[0]) ? $arguments[0] : ($config['database'] ?? '');

            if (empty($databaseName)) {
                $this->error('No database name specified!');
                exit(1);
            }

            // Connect to MySQL
            $conn = new \mysqli($host, $username, $password, '', $port);

            if ($conn->connect_error) {
                throw new \Exception("Connection failed: " . $conn->connect_error);
            }

            // Check if database exists
            $result = $conn->query("SHOW DATABASES LIKE '{$databaseName}'");
            if ($result->num_rows === 0) {
                $conn->close();
                $this->error("Database '{$databaseName}' does not exist!");
                exit(1);
            }

            // Get tables with row counts from INFORMATION_SCHEMA
            $sql = "SELECT TABLE_NAME, TABLE_ROWS
                    FROM INFORMATION_SCHEMA.TABLES
                    WHERE TABLE_SCHEMA = '{$databaseName}'
                    ORDER BY TABLE_NAME";

            $result = $conn->query($sql);

            $this->line();
            $this->info("Database: {$databaseName}");
            $this->line();

            if ($result->num_rows === 0) {
                $this->info("No tables found.");
                $this->line();
                $conn->close();
                exit(0);
            }

            // Find max table name length for padding
            $tables = [];
            $maxLen = 0;
            while ($row = $result->fetch_assoc()) {
                $tables[] = $row;
                $maxLen = max($maxLen, strlen($row['TABLE_NAME']));
            }

            // Display table with row counts
            $totalRows = 0;
            foreach ($tables as $table) {
                $name = $table['TABLE_NAME'];
                $rows = (int) $table['TABLE_ROWS'];
                $totalRows += $rows;

                $padding = str_repeat(' ', $maxLen - strlen($name) + 2);
                $this->line("  {$name}{$padding}" . number_format($rows) . " rows");
            }

            $this->line();
            $this->info(count($tables) . " tables, ~" . number_format($totalRows) . " total rows");
            $this->line();

            $conn->close();

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            exit(1);
        }
    }
}
