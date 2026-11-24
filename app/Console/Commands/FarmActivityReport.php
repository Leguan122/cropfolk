<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class FarmActivityReport extends Command
{
    protected $signature = 'cropfolk:activity
        {--farm= : Kód farmy}
        {--player= : Identifikátor hráča z prehľadu}
        {--since=24 hours ago : Začiatok obdobia alebo all}
        {--limit=100 : Maximálny počet udalostí v detaile}
        {--timezone=Europe/Bratislava : Časové pásmo výpisu}
        {--include-test : Zahrnúť aj testovacie mince}
        {--csv : Export detailu vo formáte CSV}';

    protected $description = 'Zobraz úspešné herné akcie Cropfolku podľa farmy, hráča a času.';

    public function handle(): int
    {
        $farm = strtoupper((string) $this->option('farm'));
        $player = strtolower((string) $this->option('player'));
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 10000],
        ]);
        if (($farm !== '' && !preg_match('/^[0-9A-F]{12}$/D', $farm)) ||
            ($player !== '' && !preg_match('/^[0-9a-f]{24}$/D', $player)) || $limit === false) {
            $this->error('Skontroluj kód farmy, identifikátor hráča a limit od 1 do 10 000.');
            return self::INVALID;
        }

        try {
            $zone = new DateTimeZone((string) $this->option('timezone'));
            $period = trim((string) $this->option('since'));
            if ($period === '') {
                throw new InvalidArgumentException();
            }
            $start = $period === 'all' ? null : CarbonImmutable::parse($period, $zone)->getTimestampMs();
        } catch (\Exception) {
            $this->error('Zadaj platný začiatok obdobia a časové pásmo.');
            return self::INVALID;
        }

        $activity = DB::table('farm_activity')
            ->when($start !== null, fn ($query) => $query->where('occurred_at', '>=', $start))
            ->when($farm !== '', fn ($query) => $query->where('farm', $farm))
            ->when($player !== '', fn ($query) => $query->where('player', $player))
            ->when(!$this->option('include-test'), fn ($query) => $query->where('action', '<>', 'test-coins'));

        if ($this->option('csv')) {
            fputcsv(STDOUT, ['time', 'farm', 'player', 'action', 'coins_after', 'details'], ',', '"', '');
            foreach ((clone $activity)->orderByDesc('occurred_at')->orderByDesc('id')->limit($limit)->cursor() as $event) {
                fputcsv(STDOUT, $this->eventRow($event, $zone), ',', '"', '');
            }
            return self::SUCCESS;
        }

        $summary = (clone $activity)->select('action')->selectRaw('COUNT(*) AS events')
            ->groupBy('action')->orderByDesc('events')->orderBy('action')->get();
        if ($summary->isEmpty()) {
            $this->info('V tomto období sa nenašli žiadne herné akcie.');
            return self::SUCCESS;
        }

        $farms = (clone $activity)->distinct()->count('farm');
        $players = (clone $activity)->distinct()->count('player');
        $this->info(sprintf('Akcie: %d · Farmy: %d · Hráči: %d', $summary->sum('events'), $farms, $players));
        $this->table(['Akcia', 'Počet'], $summary->map(fn ($row) => [$row->action, $row->events]));
        $events = (clone $activity)->orderByDesc('occurred_at')->orderByDesc('id')->limit($limit)->get();
        $this->table(['Čas', 'Farma', 'Hráč', 'Akcia', 'Mince po akcii', 'Detaily'],
            $events->map(fn ($event) => $this->eventRow($event, $zone)));

        return self::SUCCESS;
    }

    private function eventRow(object $event, DateTimeZone $zone): array
    {
        return [
            CarbonImmutable::createFromTimestampMs($event->occurred_at, $zone)->format('Y-m-d H:i:s.v P'),
            $event->farm,
            $event->player,
            $event->action,
            $event->coins_after,
            $event->details,
        ];
    }
}
