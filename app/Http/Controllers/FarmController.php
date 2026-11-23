<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use JsonException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class FarmController extends Controller
{
    private const CROPS = ['radish' => [2, 5, 12], 'carrot' => [5, 11, 25], 'corn' => [10, 23, 45]];
    private const PRESENCE_TTL = 10000;
    private const BARN = 'barn';

    public function __invoke(Request $request, string $code = ''): JsonResponse {
        try {
            $playerId = $request->header('X-Cropfolk-Player');
            if ($playerId !== null && !preg_match('/^[0-9a-f]{32}$/D', $playerId)) {
                throw new HttpException(400, 'Neplatný hráč.');
            }
            if (!$request->isMethod('POST')) {
                [$coins, $plots] = $this->loadFarm($code);
            } else {
                $raw = $request->getContent();
                if (!$request->isJson() || strlen($raw) > 2048) {
                    throw new HttpException(400, 'Neplatná požiadavka.');
                }
                $body = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
                if (!is_array($body)) {
                    throw new HttpException(400, 'Neplatná požiadavka.');
                }
                [$code, $coins, $plots] = DB::transaction(function () use ($code, $body, $playerId) {
                    if ($code === '') {
                        $code = strtoupper(bin2hex(random_bytes(6)));
                        $coins = 24;
                        $plots = array_fill(0, count(config('farm.plots')), null);
                        DB::table('farms')->insert([
                            'code' => $code, 'coins' => $coins, 'plots' => json_encode($plots, JSON_THROW_ON_ERROR),
                        ]);
                    } else {
                        if ($playerId === null) {
                            throw new HttpException(400, 'Neplatný hráč.');
                        }
                        $action = $body['action'] ?? null;
                        if ($action === 'position') {
                            [$coins, $plots] = $this->loadFarm($code);
                            $this->updatePosition($code, $playerId, $body);
                        } elseif ($action === 'buy-seed') {
                            [$coins, $plots] = $this->buySeed($code, $playerId, $body);
                        } elseif (in_array($action, ['deposit', 'sell', 'buy-wheelbarrow'], true)) {
                            [$coins, $plots] = $this->workBarn($code, $playerId, $action);
                        } else {
                            [$coins, $plots] = $this->updateFarm($code, $playerId, $body);
                        }
                    }
                    return [$code, $coins, $plots];
                }, 3);
            }
            $now = $this->timestamp();
            $players = DB::table('players')->where('farm', $code)->where('seen', '>=', $now - self::PRESENCE_TTL)
                ->get(['id', 'x', 'y'])->map(fn ($player) => [
                    'id' => substr(hash('sha256', $player->id), 0, 24),
                    'self' => $player->id === $playerId, 'x' => (float) $player->x, 'y' => (float) $player->y,
                ]);
            $inventories = DB::table('farm_inventories')->where('farm', $code)
                ->whereIn('owner', array_filter([self::BARN, $playerId]))->pluck('contents', 'owner');
            $wheelbarrow = (bool) DB::table('farms')->where('code', $code)->value('wheelbarrow');
            $layout = config('farm');
            if ($wheelbarrow) {
                $layout['bagCapacity'] = $layout['wheelbarrow']['capacity'];
            }
            return response()->json([
                'seeds' => (object) ($playerId === null ? [] : $this->seedStock($code, $playerId)),
                'wheelbarrow' => $wheelbarrow,
                'bag' => (object) $this->decodeContents($inventories->get($playerId ?? '')),
                'barn' => (object) $this->decodeContents($inventories->get(self::BARN)),
                'code' => $code, 'coins' => $coins, 'plots' => $plots, 'time' => $now, 'players' => $players,
                'layout' => $layout,
            ])->header('Cache-Control', 'no-store');
        } catch (HttpException $error) {
            return response()->json(['error' => $error->getMessage()], $error->getStatusCode());
        } catch (JsonException) {
            return response()->json(['error' => 'Neplatný JSON.'], 400);
        }
    }

    private function timestamp(): int {
        return (int) floor(microtime(true) * 1000);
    }

    private function loadFarm(string $code, bool $lock = false): array {
        $query = DB::table('farms')->where('code', $code);
        $row = ($lock ? $query->lockForUpdate() : $query)->first();
        if (!$row) {
            throw new HttpException(404, 'Táto farma sa nenašla.');
        }
        return [(int) $row->coins, json_decode($row->plots, true, 512, JSON_THROW_ON_ERROR)];
    }

    private function updateFarm(string $code, string $playerId, array $body): array {
        [$coins, $plots] = $this->loadFarm($code, true);
        $index = $body['plot'] ?? null;
        if (!is_int($index) || !array_key_exists($index, $plots)) {
            throw new HttpException(400, 'Neplatný záhon.');
        }
        $map = config('farm');
        $this->checkReach($code, $playerId, $map['plots'][$index], $map['reach'], 'záhonu');

        $plant = $plots[$index];
        $action = $body['action'] ?? null;
        if ($action === 'plant') {
            $crop = $body['crop'] ?? null;
            if (!is_string($crop) || !isset(self::CROPS[$crop])) {
                throw new HttpException(400, 'Neznáma plodina.');
            }
            if ($plant !== null) {
                throw new HttpException(409, 'Tento záhon je už obsadený.');
            }
            [, , $duration] = self::CROPS[$crop];
            $seeds = $this->seedStock($code, $playerId);
            if (empty($seeds[$crop])) {
                throw new HttpException(409, 'Toto semienko najprv kúp v obchode.');
            }
            if (--$seeds[$crop] === 0) {
                unset($seeds[$crop]);
            }
            $this->saveSeeds($code, $playerId, $seeds);
            $now = $this->timestamp();
            $plots[$index] = ['crop' => $crop, 'planted' => $now, 'ready' => $now + $duration * 1000];
        } elseif ($action === 'harvest') {
            if (!$plant || ($body['planted'] ?? null) !== $plant['planted'] || $plant['ready'] > $this->timestamp()) {
                throw new HttpException(409, 'Na tomto záhone zatiaľ nie je čo zožať.');
            }
            $bag = $this->inventory($code, $playerId);
            $wheelbarrow = (bool) DB::table('farms')->where('code', $code)->value('wheelbarrow');
            $capacity = $wheelbarrow ? $map['wheelbarrow']['capacity'] : $map['bagCapacity'];
            if (array_sum($bag) >= $capacity) {
                throw new HttpException(409, 'Máš plný inventár. Odnes úrodu do stodoly.');
            }
            $crop = $plant['crop'];
            $bag[$crop] = ($bag[$crop] ?? 0) + 1;
            $this->saveInventory($code, $playerId, $bag);
            $plots[$index] = null;
        } else {
            throw new HttpException(400, 'Neznáma akcia.');
        }
        DB::table('farms')->where('code', $code)->update([
            'coins' => $coins,
            'plots' => json_encode($plots, JSON_THROW_ON_ERROR),
        ]);
        return [$coins, $plots];
    }

    private function decodeContents(?string $contents): array
    {
        return $contents === null ? [] : json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }

    private function inventory(string $code, string $owner): array
    {
        return $this->decodeContents(DB::table('farm_inventories')->where('farm', $code)
            ->where('owner', $owner)->value('contents'));
    }

    private function saveInventory(string $code, string $owner, array $contents): void
    {
        DB::table('farm_inventories')->upsert([
            'farm' => $code, 'owner' => $owner, 'contents' => json_encode($contents, JSON_THROW_ON_ERROR),
        ], ['farm', 'owner'], ['contents']);
    }

    private function seedStock(string $code, string $playerId): array
    {
        return $this->decodeContents(DB::table('farm_inventories')->where('farm', $code)
            ->where('owner', $playerId)->value('seeds'));
    }

    private function saveSeeds(string $code, string $playerId, array $seeds): void
    {
        DB::table('farm_inventories')->upsert([
            'farm' => $code, 'owner' => $playerId, 'contents' => '{}',
            'seeds' => json_encode($seeds, JSON_THROW_ON_ERROR),
        ], ['farm', 'owner'], ['seeds']);
    }

    private function buySeed(string $code, string $playerId, array $body): array
    {
        [$coins, $plots] = $this->loadFarm($code, true);
        $door = config('farm.shopDoor');
        $this->checkReach($code, $playerId, $door, $door['reach'], 'obchodu');
        $crop = $body['crop'] ?? null;
        $quantity = $body['quantity'] ?? 1;
        if (!is_string($crop) || !isset(self::CROPS[$crop]) || !in_array($quantity, [1, 5], true)) {
            throw new HttpException(400, 'Neplatný nákup semienok.');
        }
        $cost = self::CROPS[$crop][0] * $quantity;
        if ($coins < $cost) {
            throw new HttpException(409, 'Na tieto semienka nemáš dosť mincí.');
        }
        $seeds = $this->seedStock($code, $playerId);
        $seeds[$crop] = ($seeds[$crop] ?? 0) + $quantity;
        $this->saveSeeds($code, $playerId, $seeds);
        $coins -= $cost;
        DB::table('farms')->where('code', $code)->update(['coins' => $coins]);

        return [$coins, $plots];
    }

    private function workBarn(string $code, string $playerId, string $action): array
    {
        // Use the same farm lock as harvesting so every crop can be transferred or sold once.
        [$coins, $plots] = $this->loadFarm($code, true);
        $door = config('farm.barnDoor');
        $this->checkReach($code, $playerId, $door, $door['reach'], 'dverám stodoly');
        if ($action === 'buy-wheelbarrow') {
            $farm = DB::table('farms')->where('code', $code);
            if ($farm->value('wheelbarrow')) {
                throw new HttpException(409, 'Fúrik už máte.');
            }
            $cost = config('farm.wheelbarrow.cost');
            if ($coins < $cost) {
                throw new HttpException(409, 'Na fúrik zatiaľ nemáte dosť mincí.');
            }
            $coins -= $cost;
            $farm->update(['coins' => $coins, 'wheelbarrow' => true]);

            return [$coins, $plots];
        }

        $stock = $this->inventory($code, self::BARN);
        if ($action === 'deposit') {
            $bag = $this->inventory($code, $playerId);
            if (!$bag) {
                throw new HttpException(409, 'V inventári nemáš úrodu.');
            }
            foreach ($bag as $crop => $count) {
                $stock[$crop] = ($stock[$crop] ?? 0) + $count;
            }
            $this->saveInventory($code, self::BARN, $stock);
            $this->saveInventory($code, $playerId, []);
        } else {
            if (!$stock) {
                throw new HttpException(409, 'Stodola je prázdna.');
            }
            foreach ($stock as $crop => $count) {
                $coins += self::CROPS[$crop][1] * $count;
            }
            DB::table('farms')->where('code', $code)->update(['coins' => $coins]);
            $this->saveInventory($code, self::BARN, []);
        }

        return [$coins, $plots];
    }

    private function checkReach(string $code, string $id, array $target, float $reach, string $label): void
    {
        $player = DB::table('players')->where('farm', $code)->where('id', $id)
            ->where('seen', '>=', $this->timestamp() - self::PRESENCE_TTL)->first(['x', 'y']);
        $map = config('farm');
        if (!$player || hypot($player->x * $map['width'] - $target['x'],
                              $player->y * $map['height'] - $target['y']) > $reach) {
            throw new HttpException(409, 'Najprv pristúp bližšie k '.$label.'.');
        }
    }

    private function updatePosition(string $code, string $id, array $body): void {
        $coordinates = [];
        foreach (['x', 'y'] as $axis) {
            $value = $body[$axis] ?? null;
            if ((!is_int($value) && !is_float($value)) || !is_finite($value) || $value < 0 || $value > 1) {
                throw new HttpException(400, 'Neplatná poloha.');
            }
            $coordinates[] = $value;
        }
        $map = config('farm');
        $x = $coordinates[0] * $map['width'];
        $y = $coordinates[1] * $map['height'];
        $radius = $map['radius'];
        if ($x < $radius || $x > $map['width'] - $radius ||
            $y < $radius || $y > $map['height'] - $radius) {
            throw new HttpException(409, 'Táto poloha je mimo mapy.');
        }
        foreach ($map['obstacles'] as $wall) {
            if ($x > $wall['x'] - $radius && $x < $wall['x'] + $wall['w'] + $radius &&
                $y > $wall['y'] - $radius && $y < $wall['y'] + $wall['h'] + $radius) {
                throw new HttpException(409, 'Na tomto mieste je prekážka.');
            }
        }

        $now = $this->timestamp();
        DB::table('players')->where('farm', $code)->where('seen', '<', $now - self::PRESENCE_TTL)->delete();
        DB::table('players')->upsert([
            'farm' => $code, 'id' => $id, 'x' => $coordinates[0], 'y' => $coordinates[1], 'seen' => $now,
        ], ['farm', 'id'], ['x', 'y', 'seen']);
    }
}
