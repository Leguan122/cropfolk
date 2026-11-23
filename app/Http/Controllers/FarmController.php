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

    public function __invoke(Request $request, string $code = ''): JsonResponse {
        try {
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
                [$code, $coins, $plots] = DB::transaction(function () use ($code, $body) {
                    if ($code === '') {
                        $code = strtoupper(bin2hex(random_bytes(6)));
                        $coins = 24;
                        $plots = array_fill(0, 16, null);
                        DB::table('farms')->insert([
                            'code' => $code, 'coins' => $coins, 'plots' => json_encode($plots, JSON_THROW_ON_ERROR),
                        ]);
                    } elseif (($body['action'] ?? null) === 'position') {
                        [$coins, $plots] = $this->loadFarm($code);
                        $this->updatePosition($code, $body);
                    } else {
                        [$coins, $plots] = $this->updateFarm($code, $body);
                    }
                    return [$code, $coins, $plots];
                }, 3);
            }
            $now = $this->timestamp();
            $players = DB::table('players')->where('farm', $code)->where('seen', '>=', $now - self::PRESENCE_TTL)
                ->get(['id', 'x', 'y'])->map(fn ($player) => [
                    'id' => $player->id, 'x' => (float) $player->x, 'y' => (float) $player->y,
                ]);
            return response()->json([
                'code' => $code, 'coins' => $coins, 'plots' => $plots, 'time' => $now, 'players' => $players,
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

    private function updateFarm(string $code, array $body): array {
        [$coins, $plots] = $this->loadFarm($code, true);
        $index = $body['plot'] ?? null;
        if (!is_int($index) || !array_key_exists($index, $plots)) {
            throw new HttpException(400, 'Neplatný záhon.');
        }
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
            [$cost, , $duration] = self::CROPS[$crop];
            if ($coins < $cost) {
                throw new HttpException(409, 'Na tieto semienka nemáš dosť mincí.');
            }
            $coins -= $cost;
            $now = $this->timestamp();
            $plots[$index] = ['crop' => $crop, 'planted' => $now, 'ready' => $now + $duration * 1000];
        } elseif ($action === 'harvest') {
            if (!$plant || ($body['planted'] ?? null) !== $plant['planted'] || $plant['ready'] > $this->timestamp()) {
                throw new HttpException(409, 'Na tomto záhone zatiaľ nie je čo zožať.');
            }
            $coins += self::CROPS[$plant['crop']][1];
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

    private function updatePosition(string $code, array $body): void {
        $id = $body['id'] ?? null;
        if (!is_string($id) || !preg_match('/^[0-9a-f]{32}$/D', $id)) {
            throw new HttpException(400, 'Neplatný hráč.');
        }
        $coordinates = [];
        foreach (['x', 'y'] as $axis) {
            $value = $body[$axis] ?? null;
            if ((!is_int($value) && !is_float($value)) || !is_finite($value) || $value < 0 || $value > 1) {
                throw new HttpException(400, 'Neplatná poloha.');
            }
            $coordinates[] = $value;
        }
        $now = $this->timestamp();
        DB::table('players')->where('farm', $code)->where('seen', '<', $now - self::PRESENCE_TTL)->delete();
        DB::table('players')->upsert([
            'farm' => $code, 'id' => $id, 'x' => $coordinates[0], 'y' => $coordinates[1], 'seen' => $now,
        ], ['farm', 'id'], ['x', 'y', 'seen']);
    }
}
