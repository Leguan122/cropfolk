<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use JsonException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class FarmController extends Controller
{
    private const CROPS = ['radish' => [2, 5, 12], 'carrot' => [5, 11, 25], 'corn' => [10, 23, 45],
        'potato' => [7, 8, 35], 'strawberry' => [9, 12, 30], 'pumpkin' => [14, 36, 80]];
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
                        $position = $body['position'] ?? null;
                        if ($position !== null && !is_array($position)) {
                            throw new HttpException(400, 'Neplatná poloha.');
                        }
                        if ($action === 'position') {
                            [$coins, $plots] = $this->loadFarm($code);
                            $this->updatePosition($code, $playerId, $body);
                        } elseif ($action === 'buy-seed') {
                            [$coins, $plots] = $this->buySeed($code, $playerId, $body, $position);
                        } elseif (in_array($action, ['expand-shop', 'open-east', 'open-south', 'buy-wheelbarrow'], true)) {
                            [$coins, $plots] = $this->expandFarm($code, $playerId, $action, $position);
                        } elseif ($action === 'upgrade-tool') {
                            [$coins, $plots] = $this->upgradeTool($code, $playerId, $body, $position);
                        } elseif ($action === 'refill-water') {
                            [$coins, $plots] = $this->refillWater($code, $playerId, $position);
                        } elseif ($action === 'fulfill-order') {
                            [$coins, $plots] = $this->fulfillOrder($code, $playerId, $body, $position);
                        } elseif ($action === 'sell-bag') {
                            [$coins, $plots] = $this->sellBag($code, $playerId, $position);
                        } elseif (in_array($action, ['deposit', 'sell'], true)) {
                            [$coins, $plots] = $this->workBarn($code, $playerId, $action, $position);
                        } else {
                            [$coins, $plots] = $this->updateFarm($code, $playerId, $body, $position);
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
                ->whereIn('owner', array_filter([self::BARN, $playerId]))
                ->get(['owner', 'contents', 'seeds', 'water'])->keyBy('owner');
            $personal = $inventories->get($playerId ?? '');
            $shared = $inventories->get(self::BARN);
            $upgrades = $this->upgrades($code);
            $layout = $this->layout($upgrades);
            // A concurrent unlock may finish after the farm snapshot was read.
            if (count($plots) < count($layout['plots'])) {
                [$coins, $plots] = $this->loadFarm($code);
            }
            $stock = $this->decodeContents($shared->contents ?? null);
            return response()->json([
                'order' => $this->currentOrder((int) $upgrades->orders_completed, $stock),
                'seeds' => (object) ($this->decodeContents($personal->seeds ?? null)),
                'wheelbarrow' => (bool) $upgrades->wheelbarrow,
                'shopExpanded' => (bool) $upgrades->shop_expanded,
                'southOpen' => (bool) $upgrades->south_open,
                'eastOpen' => (bool) $upgrades->east_open,
                'tools' => ['hoe' => (int) $upgrades->hoe_level, 'can' => (int) $upgrades->can_level],
                'weather' => $this->weather($now),
                'water' => (int) ($personal->water ?? $this->waterCapacity((int) $upgrades->can_level)),
                'bag' => (object) $this->decodeContents($personal->contents ?? null),
                'barn' => (object) $stock,
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

    private function upgrades(string $code): object
    {
        return DB::table('farms')->where('code', $code)->first(['wheelbarrow', 'shop_expanded', 'south_open', 'east_open', 'hoe_level', 'can_level', 'orders_completed']);
    }

    private function layout(object $upgrades): array
    {
        $map = config('farm');
        $south = $map['south'];
        unset($map['south'], $map['orders']);
        $map['lockedPlots'] = $upgrades->east_open ? [] : range(8, 15);
        if (!$upgrades->east_open) {
            $map['obstacles'][] = [
                'kind' => 'locked-field', 'x' => 651, 'y' => 144, 'w' => 393, 'h' => 285,
                'text' => 'Východné pole · zamknuté',
            ];
        }
        $map['playableHeight'] = $upgrades->south_open ? $map['height'] : $map['closedHeight'];
        if ($upgrades->south_open) {
            $map['plots'] = [...$map['plots'], ...$south['plots']];
            $map['landmarks'] = [...$map['landmarks'], ...$south['landmarks']];
        } else {
            $map['obstacles'][] = ['kind' => 'fence', 'x' => 522, 'y' => 756, 'w' => 108, 'h' => 16];
            $map['obstacles'][] = ['kind' => 'fence', 'x' => 928, 'y' => 756, 'w' => 98, 'h' => 16];
        }
        if ($upgrades->wheelbarrow) {
            $map['bagCapacity'] = $map['wheelbarrow']['capacity'];
        }
        return $map;
    }

    private function expandFarm(string $code, string $playerId, string $action, ?array $position): array
    {
        [$coins, $plots] = $this->loadFarm($code, true);
        $upgrades = $this->upgrades($code);
        $map = config('farm');
        [$column, $cost] = match ($action) {
            'expand-shop' => ['shop_expanded', $map['shopExpansionCost']],
            'open-east' => ['east_open', $map['eastFieldCost']],
            'open-south' => ['south_open', $map['southGate']['cost']],
            'buy-wheelbarrow' => ['wheelbarrow', $map['wheelbarrow']['cost']],
        };
        $target = $map['shopDoor'];
        $this->checkReach($code, $playerId, $target, $target['reach'], 'obchodu', $position, $upgrades);
        if ($upgrades->{$column}) {
            throw new HttpException(409, 'Toto vylepšenie už máte.');
        }
        if ($action === 'open-south' && (!$upgrades->shop_expanded || !$upgrades->east_open)) {
            throw new HttpException(409, 'Najprv rozšír obchod a odomkni východné pole.');
        }
        if ($coins < $cost) {
            throw new HttpException(409, 'Na vylepšenie zatiaľ nemáte dosť mincí.');
        }
        $coins -= $cost;
        if ($action === 'open-south') {
            $plots = [...$plots, ...array_fill(0, count($map['south']['plots']), null)];
        }
        DB::table('farms')->where('code', $code)->update([
            $column => true, 'coins' => $coins, 'plots' => json_encode($plots, JSON_THROW_ON_ERROR),
        ]);
        return [$coins, $plots];
    }

    private function weather(int $now): array
    {
        $cycle = config('farm.weather');
        $phase = $now % $cycle['period'];
        $raining = $phase < $cycle['rain'];
        return ['rain' => $raining, 'changesAt' => $now + ($raining ? $cycle['rain'] : $cycle['period']) - $phase];
    }

    private function upgradeTool(string $code, string $playerId, array $body, ?array $position): array
    {
        [$coins, $plots] = $this->loadFarm($code, true);
        $farm = $this->upgrades($code);
        $tool = $body['tool'] ?? null;
        if (!in_array($tool, ['hoe', 'can'], true)) {
            throw new HttpException(400, 'Neplatný nástroj.');
        }
        $column = $tool.'_level';
        $next = (int) $farm->{$column} + 1;
        $tier = config('farm.toolTiers.'.$tool)[$next] ?? null;
        $door = config('farm.shopDoor');
        $this->checkReach($code, $playerId, $door, $door['reach'], 'obchodu', $position, $farm);
        if (!$tier || ($body['level'] ?? null) !== $next) {
            throw new HttpException(409, 'Táto úroveň už nie je dostupná.');
        }
        if ($coins < $tier['cost']) {
            throw new HttpException(409, 'Na vylepšenie nástroja nemáte dosť mincí.');
        }
        $coins -= $tier['cost'];
        DB::table('farms')->where('code', $code)->update([$column => $next, 'coins' => $coins]);

        return [$coins, $plots];
    }

    private function prepareSoil(array $plots, array $map, int $index, string $action, int $level, ?int $water = null): array
    {
        $required = $action === 'hoe' ? ['soil' => 'spent'] : null;
        if ($plots[$index] !== $required) {
            throw new HttpException(409, 'Stav záhona sa zmenil.');
        }
        $tool = $action === 'hoe' ? 'hoe' : 'can';
        $tier = $map['toolTiers'][$tool][$level];
        $columns = $map['fieldGrid']['columns'];
        $fieldSize = $columns * $map['fieldGrid']['rows'];
        $field = intdiv($index, $fieldSize);
        $targets = [];
        foreach ($plots as $key => $plot) {
            if ($plot !== $required || intdiv($key, $fieldSize) !== $field ||
                in_array($key, $map['lockedPlots'], true)) {
                continue;
            }
            $deltaColumn = ($key % $columns) - ($index % $columns);
            $deltaRow = intdiv($key, $columns) - intdiv($index, $columns);
            $distanceSquared = $deltaColumn ** 2 + $deltaRow ** 2;
            if ($distanceSquared <= $tier['rangeSquared']) {
                $targets[$key] = $distanceSquared;
            }
        }
        asort($targets);
        $limit = min($tier['limit'], $water ?? $tier['limit']);
        $affected = array_slice(array_keys($targets), 0, $limit);
        foreach ($affected as $key) {
            $plots[$key] = $action === 'hoe' ? null : ['soil' => 'wet'];
        }

        return [$plots, count($affected)];
    }

    private function waterCapacity(int $level): int
    {
        return config('farm.toolTiers.can')[$level]['capacity'];
    }

    private function saveWater(string $code, string $playerId, int $water): void
    {
        DB::table('farm_inventories')->upsert([
            'farm' => $code, 'owner' => $playerId, 'contents' => '{}', 'water' => $water,
        ], ['farm', 'owner'], ['water']);
    }

    private function refillWater(string $code, string $playerId, ?array $position): array
    {
        [$coins, $plots] = $this->loadFarm($code, true);
        $farm = $this->upgrades($code);
        $well = config('farm.well');
        $this->checkReach($code, $playerId, $well, $well['reach'], 'studni', $position, $farm);
        $this->saveWater($code, $playerId, $this->waterCapacity((int) $farm->can_level));

        return [$coins, $plots];
    }

    private function updateFarm(string $code, string $playerId, array $body, ?array $position): array {
        [$coins, $plots] = $this->loadFarm($code, true);
        $index = $body['plot'] ?? null;
        if (!is_int($index) || !array_key_exists($index, $plots)) {
            throw new HttpException(400, 'Neplatný záhon.');
        }
        $upgrades = $this->upgrades($code);
        $map = $this->layout($upgrades);
        if (in_array($index, $map['lockedPlots'], true)) {
            throw new HttpException(409, 'Toto pole odomkneš v obchode.');
        }
        $this->checkReach($code, $playerId, $map['plots'][$index], $map['reach'], 'záhonu', $position, $upgrades);

        $plant = $plots[$index];
        $action = $body['action'] ?? null;
        if ($action === 'hoe' || $action === 'water') {
            $level = $action === 'hoe' ? $upgrades->hoe_level : $upgrades->can_level;
            $water = null;
            if ($action === 'water') {
                if ($this->weather($this->timestamp())['rain']) {
                    throw new HttpException(409, 'Prší, záhon môžeš rovno osiať.');
                }
                $remaining = DB::table('farm_inventories')->where('farm', $code)
                    ->where('owner', $playerId)->value('water');
                $water = (int) ($remaining ?? $this->waterCapacity((int) $level));
                if ($water === 0) {
                    throw new HttpException(409, 'Krhla je prázdna. Naplň ju pri studni.');
                }
            }
            [$plots, $affected] = $this->prepareSoil($plots, $map, $index, $action, (int) $level, $water);
            if ($water !== null) {
                $this->saveWater($code, $playerId, $water - $affected);
            }
        } elseif ($action === 'plant') {
            $crop = $body['crop'] ?? null;
            if (!is_string($crop) || !isset(self::CROPS[$crop])) {
                throw new HttpException(400, 'Neznáma plodina.');
            }
            if (isset($plant['crop'])) {
                throw new HttpException(409, 'Tento záhon je už obsadený.');
            }
            if (($plant['soil'] ?? null) === 'spent') {
                throw new HttpException(409, 'Najprv obrob záhon motykou.');
            }
            if (($plant['soil'] ?? null) !== 'wet' && !$this->weather($this->timestamp())['rain']) {
                throw new HttpException(409, 'Najprv polej záhon krhlou.');
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
            if (!isset($plant['crop']) || ($body['planted'] ?? null) !== $plant['planted'] || $plant['ready'] > $this->timestamp()) {
                throw new HttpException(409, 'Na tomto záhone zatiaľ nie je čo zožať.');
            }
            $bag = $this->inventory($code, $playerId);
            $crop = $plant['crop'];
            $yield = $crop === 'potato' ? 2 : 1;
            if (array_sum($bag) + $yield > $map['bagCapacity']) {
                throw new HttpException(409, 'Máš plný inventár. Odnes úrodu do stodoly.');
            }
            $bag[$crop] = ($bag[$crop] ?? 0) + $yield;
            $this->saveInventory($code, $playerId, $bag);
            $now = max($this->timestamp(), $plant['planted'] + 1);
            $plots[$index] = $crop === 'strawberry' && empty($plant['regrown'])
                ? ['crop' => $crop, 'planted' => $now, 'ready' => $now + self::CROPS[$crop][2] * 1000, 'regrown' => true]
                : ['soil' => 'spent'];
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

    private function buySeed(string $code, string $playerId, array $body, ?array $position): array
    {
        [$coins, $plots] = $this->loadFarm($code, true);
        $door = config('farm.shopDoor');
        $upgrades = $this->upgrades($code);
        $this->checkReach($code, $playerId, $door, $door['reach'], 'obchodu', $position, $upgrades);
        $crop = $body['crop'] ?? null;
        $quantity = $body['quantity'] ?? 1;
        $packSizes = $upgrades->shop_expanded ? [1, 5, 10] : [1, 5];
        if (!is_string($crop) || !isset(self::CROPS[$crop]) || !in_array($quantity, $packSizes, true)) {
            throw new HttpException(400, 'Neplatný nákup semienok.');
        }
        if ((in_array($crop, ['potato', 'strawberry'], true) && !$upgrades->shop_expanded)
            || ($crop === 'pumpkin' && !$upgrades->south_open)) {
            throw new HttpException(409, 'Táto plodina ešte nie je odomknutá.');
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

    private function sellBag(string $code, string $playerId, ?array $position): array
    {
        [$coins, $plots] = $this->loadFarm($code, true);
        $door = config('farm.shopDoor');
        $this->checkReach($code, $playerId, $door, $door['reach'], 'obchodu', $position);
        $bag = $this->inventory($code, $playerId);
        if (!$bag) {
            throw new HttpException(409, 'V inventári nemáš úrodu.');
        }
        foreach ($bag as $crop => $count) {
            $coins += self::CROPS[$crop][1] * $count;
        }
        DB::table('farms')->where('code', $code)->update(['coins' => $coins]);
        $this->saveInventory($code, $playerId, []);

        return [$coins, $plots];
    }

    private function currentOrder(int $completed, array $stock): array
    {
        $orders = config('farm.orders');
        $order = $orders[$completed % count($orders)];
        $progress = [];
        foreach ($order['crops'] as $crop => $required) {
            $progress[$crop] = min($required, $stock[$crop] ?? 0);
        }

        return $order + [
            'id' => $completed + 1,
            'progress' => $progress,
            'ready' => $progress === $order['crops'],
        ];
    }

    private function fulfillOrder(string $code, string $playerId, array $body, ?array $position): array
    {
        [$coins, $plots] = $this->loadFarm($code, true);
        $farm = $this->upgrades($code);
        $barn = config('farm.barnDoor');
        $this->checkReach($code, $playerId, $barn, $barn['reach'], 'stodole', $position, $farm);
        $stock = $this->inventory($code, self::BARN);
        $order = $this->currentOrder((int) $farm->orders_completed, $stock);
        if (($body['order'] ?? null) !== $order['id']) {
            throw new HttpException(409, 'Objednávka sa už zmenila.');
        }
        if (!$order['ready']) {
            throw new HttpException(409, 'V stodole ešte chýba časť úrody.');
        }
        foreach ($order['crops'] as $crop => $required) {
            $stock[$crop] -= $required;
            if ($stock[$crop] === 0) {
                unset($stock[$crop]);
            }
        }
        $coins += $order['reward'];
        $this->saveInventory($code, self::BARN, $stock);
        DB::table('farms')->where('code', $code)->update([
            'coins' => $coins, 'orders_completed' => $order['id'],
        ]);

        return [$coins, $plots];
    }

    private function workBarn(string $code, string $playerId, string $action, ?array $position): array
    {
        // Use the same farm lock as harvesting so every crop can be transferred or sold once.
        [$coins, $plots] = $this->loadFarm($code, true);
        $door = config('farm.barnDoor');
        $this->checkReach($code, $playerId, $door, $door['reach'], 'dverám stodoly', $position);
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

    private function checkReach(
        string $code, string $id, array $target, float $reach, string $label,
        ?array $position, ?object $upgrades = null,
    ): void {
        $map = config('farm');
        if ($position !== null) {
            [$x, $y] = $this->validatePosition($position, $this->layout($upgrades ?? $this->upgrades($code)));
        } else {
            $player = DB::table('players')->where('farm', $code)->where('id', $id)
                ->where('seen', '>=', $this->timestamp() - self::PRESENCE_TTL)->first(['x', 'y']);
            $x = $player->x ?? null;
            $y = $player->y ?? null;
        }
        if ($x === null || $this->interactionDistance($x * $map['width'], $y * $map['height'], $target, $map) > $reach) {
            throw new HttpException(409, 'Najprv pristúp bližšie k '.$label.'.');
        }
    }

    private function interactionDistance(float $x, float $y, array $target, array $map): float
    {
        foreach ($map['obstacles'] as $building) {
            if ($building['kind'] !== ($target['building'] ?? null)) {
                continue;
            }
            $dx = max($building['x'] - $x, 0, $x - $building['x'] - $building['w']);
            $dy = max($building['y'] - $y, 0, $y - $building['y'] - $building['h']);

            return hypot($dx, $dy);
        }

        return hypot($x - $target['x'], $y - $target['y']);
    }

    private function validatePosition(array $body, array $map): array
    {
        $coordinates = [];
        foreach (['x', 'y'] as $axis) {
            $value = $body[$axis] ?? null;
            if ((!is_int($value) && !is_float($value)) || !is_finite($value) || $value < 0 || $value > 1) {
                throw new HttpException(400, 'Neplatná poloha.');
            }
            $coordinates[] = $value;
        }
        $x = $coordinates[0] * $map['width'];
        $y = $coordinates[1] * $map['height'];
        $radius = $map['radius'];
        if ($x < $radius || $x > $map['width'] - $radius ||
            $y < $radius || $y > $map['playableHeight'] - $radius) {
            throw new HttpException(409, 'Táto poloha je mimo mapy.');
        }
        foreach ($map['obstacles'] as $wall) {
            if ($x > $wall['x'] - $radius && $x < $wall['x'] + $wall['w'] + $radius &&
                $y > $wall['y'] - $radius && $y < $wall['y'] + $wall['h'] + $radius) {
                throw new HttpException(409, 'Na tomto mieste je prekážka.');
            }
        }

        return $coordinates;
    }

    private function updatePosition(string $code, string $id, array $body): void
    {
        [$x, $y] = $this->validatePosition($body, $this->layout($this->upgrades($code)));
        $now = $this->timestamp();
        DB::table('players')->where('farm', $code)->where('seen', '<', $now - self::PRESENCE_TTL)->delete();
        DB::table('players')->upsert([
            'farm' => $code, 'id' => $id, 'x' => $x, 'y' => $y, 'seen' => $now,
        ], ['farm', 'id'], ['x', 'y', 'seen']);
    }
}
