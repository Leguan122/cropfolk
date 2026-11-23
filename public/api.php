<?php
declare(strict_types=1);

const CROPS = ['radish' => [2, 5, 12], 'carrot' => [5, 11, 25], 'corn' => [10, 23, 45]];
const PRESENCE_TTL = 10000;

final class GameError extends RuntimeException {}

function respond(int $status, array $data): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function timestamp(): int {
    return (int) floor(microtime(true) * 1000);
}

function database(): PDO {
    $path = getenv('FARM_DB') ?: dirname(__DIR__) . '/data/farm.sqlite3';
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Cannot create database directory');
    }
    $db = new PDO('sqlite:' . $path);
    $db->exec('PRAGMA busy_timeout = 5000');
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('CREATE TABLE IF NOT EXISTS farms (code TEXT PRIMARY KEY, coins INTEGER, plots TEXT)');
    $db->exec('CREATE TABLE IF NOT EXISTS players (
        farm TEXT NOT NULL, id TEXT NOT NULL, x REAL NOT NULL, y REAL NOT NULL,
        seen INTEGER NOT NULL, PRIMARY KEY (farm, id)
    )');
    return $db;
}

function loadFarm(PDO $db, string $code): array {
    $query = $db->prepare('SELECT coins, plots FROM farms WHERE code = ?');
    $query->execute([$code]);
    $row = $query->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new GameError('Táto farma sa nenašla.', 404);
    }
    return [(int) $row['coins'], json_decode($row['plots'], true, 512, JSON_THROW_ON_ERROR)];
}

function updateFarm(PDO $db, string $code, array $body): array {
    [$coins, $plots] = loadFarm($db, $code);
    $index = $body['plot'] ?? null;
    if (!is_int($index) || !array_key_exists($index, $plots)) {
        throw new GameError('Neplatný záhon.', 400);
    }
    $plant = $plots[$index];
    $action = $body['action'] ?? null;
    if ($action === 'plant') {
        $crop = $body['crop'] ?? null;
        if (!is_string($crop) || !isset(CROPS[$crop])) {
            throw new GameError('Neznáma plodina.', 400);
        }
        if ($plant !== null) {
            throw new GameError('Tento záhon je už obsadený.', 409);
        }
        [$cost, , $duration] = CROPS[$crop];
        if ($coins < $cost) {
            throw new GameError('Na tieto semienka nemáš dosť mincí.', 409);
        }
        $coins -= $cost;
        $now = timestamp();
        $plots[$index] = ['crop' => $crop, 'planted' => $now, 'ready' => $now + $duration * 1000];
    } elseif ($action === 'harvest') {
        if (!$plant || ($body['planted'] ?? null) !== $plant['planted'] || $plant['ready'] > timestamp()) {
            throw new GameError('Na tomto záhone zatiaľ nie je čo zožať.', 409);
        }
        $coins += CROPS[$plant['crop']][1];
        $plots[$index] = null;
    } else {
        throw new GameError('Neznáma akcia.', 400);
    }
    $db->prepare('UPDATE farms SET coins = ?, plots = ? WHERE code = ?')
       ->execute([$coins, json_encode($plots, JSON_THROW_ON_ERROR), $code]);
    return [$coins, $plots];
}

function updatePosition(PDO $db, string $code, array $body): void {
    $id = $body['id'] ?? null;
    if (!is_string($id) || !preg_match('/^[0-9a-f]{32}$/D', $id)) {
        throw new GameError('Neplatný hráč.', 400);
    }
    $coordinates = [];
    foreach (['x', 'y'] as $axis) {
        $value = $body[$axis] ?? null;
        if ((!is_int($value) && !is_float($value)) || !is_finite($value) || $value < 0 || $value > 1) {
            throw new GameError('Neplatná poloha.', 400);
        }
        $coordinates[] = $value;
    }
    $now = timestamp();
    $db->prepare('DELETE FROM players WHERE farm = ? AND seen < ?')->execute([$code, $now - PRESENCE_TTL]);
    $db->prepare('INSERT INTO players (farm, id, x, y, seen) VALUES (?, ?, ?, ?, ?)
        ON CONFLICT(farm, id) DO UPDATE SET x = excluded.x, y = excluded.y, seen = excluded.seen')
       ->execute([$code, $id, ...$coordinates, $now]);
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $code = $_GET['farm'] ?? '';
    if (!is_string($code) || ($code !== '' && !preg_match('/^[A-F0-9]{12}$/D', $code))) {
        throw new GameError('Neplatný kód farmy.', 400);
    }
    if ($method !== 'GET' && $method !== 'POST') {
        header('Allow: GET, POST');
        throw new GameError('Neplatná metóda.', 405);
    }
    $body = [];
    if ($method === 'POST') {
        if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
            throw new GameError('Očakávam JSON.', 415);
        }
        $raw = file_get_contents('php://input', false, null, 0, 2049);
        if (!$raw || strlen($raw) > 2048) {
            throw new GameError('Neplatná požiadavka.', 400);
        }
        $body = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($body)) {
            throw new GameError('Neplatná požiadavka.', 400);
        }
    }
    $db = database();
    if ($method === 'POST') {
        $db->exec('BEGIN IMMEDIATE');
        try {
            if ($code === '') {
                $code = strtoupper(bin2hex(random_bytes(6)));
                $coins = 24;
                $plots = array_fill(0, 16, null);
                $db->prepare('INSERT INTO farms (code, coins, plots) VALUES (?, ?, ?)')
                   ->execute([$code, $coins, json_encode($plots, JSON_THROW_ON_ERROR)]);
            } elseif (($body['action'] ?? null) === 'position') {
                [$coins, $plots] = loadFarm($db, $code);
                updatePosition($db, $code, $body);
            } else {
                [$coins, $plots] = updateFarm($db, $code, $body);
            }
            $db->exec('COMMIT');
        } catch (Throwable $error) {
            $db->exec('ROLLBACK');
            throw $error;
        }
    } else {
        [$coins, $plots] = loadFarm($db, $code);
    }
    $now = timestamp();
    $query = $db->prepare('SELECT id, x, y FROM players WHERE farm = ? AND seen >= ?');
    $query->execute([$code, $now - PRESENCE_TTL]);
    respond(200, ['code' => $code, 'coins' => $coins, 'plots' => $plots,
                  'time' => $now, 'players' => $query->fetchAll(PDO::FETCH_ASSOC)]);
} catch (GameError $error) {
    respond($error->getCode(), ['error' => $error->getMessage()]);
} catch (JsonException $error) {
    respond(400, ['error' => 'Neplatný JSON.']);
} catch (Throwable $error) {
    error_log($error->getMessage());
    respond(503, ['error' => 'Farma je chvíľu nedostupná.']);
}
