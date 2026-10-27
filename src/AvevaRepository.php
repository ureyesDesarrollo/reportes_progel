<?php

declare(strict_types=1);

namespace ReportesProgel\Votators;

use InvalidArgumentException;
use PDO;
use RuntimeException;

final class AvevaRepository
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function latest(array $sensors): array
    {
        $server = trim((string)($this->config['server'] ?? ''));
        $database = trim((string)($this->config['database'] ?? ''));
        $user = trim((string)($this->config['user'] ?? ''));
        if ($server === '' || $database === '' || $user === '') {
            throw new RuntimeException('La conexión AVEVA no está configurada.');
        }
        if (!extension_loaded('pdo_sqlsrv')) {
            throw new RuntimeException('La extensión pdo_sqlsrv no está disponible.');
        }

        $table = $this->quote((string)($this->config['table'] ?? 'TREND001'));
        $timestamp = $this->quote((string)($this->config['timestamp_field'] ?? 'Time_Stamp'));
        $sensorNames = array_values(array_unique(array_filter(array_map('strval', $sensors))));
        if ($sensorNames === []) {
            throw new RuntimeException('No hay sensores configurados.');
        }

        $select = [$timestamp . ' AS [__timestamp]'];
        foreach ($sensorNames as $sensor) {
            $select[] = $this->quote($sensor);
        }

        $port = max(1, (int)($this->config['port'] ?? 1433));
        $timeout = max(1, (int)($this->config['login_timeout'] ?? 5));
        $encrypt = !empty($this->config['encrypt']) ? 'yes' : 'no';
        $trust = !empty($this->config['trust_server_certificate']) ? 'yes' : 'no';
        $dsn = sprintf(
            'sqlsrv:Server=%s,%d;Database=%s;Encrypt=%s;TrustServerCertificate=%s;LoginTimeout=%d',
            $server,
            $port,
            $database,
            $encrypt,
            $trust,
            $timeout
        );
        $pdo = new PDO($dsn, $user, (string)($this->config['password'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $sql = 'SELECT TOP (1) ' . implode(', ', $select)
            . ' FROM ' . $table
            . ' WHERE CAST(' . $timestamp . ' AS date) = CAST(GETDATE() AS date)'
            . ' ORDER BY ' . $timestamp . ' DESC';

        $row = $pdo->query($sql)->fetch();
        return is_array($row) ? $row : [];
    }

    private function quote(string $identifier): string
    {
        if ($identifier === '' || preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
            throw new InvalidArgumentException('Identificador AVEVA no válido.');
        }
        return '[' . $identifier . ']';
    }
}

