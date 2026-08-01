<?php

namespace SummerCraft\Core;

use SummerCraft\Core\ComponentManaging\LifeCycle\SharedComponent;

class BenchmarkHolder implements SharedComponent
{
    private static BenchmarkHolder $instance;

    const APP_START = 'AppStart';

    /**
     * List of all benchmark markers
     *
     * @var array<string, float> <name, microTime>
     */
    private array $times = [];

    private array $markers = [];

    public array $loadedClasses = [];

    private function __construct() {
        $this->point(self::APP_START);
    }

    public static function getInstance(): self
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Forget the markers of the request that just ended.
     *
     * The holder is a singleton outside the container, so destroying the request
     * scope does not touch it: in a process that serves one request that is
     * harmless, in one that serves many the markers pile up for good and every
     * elapsed time is measured from when the process booted rather than from when
     * this request began.
     */
    public function reset(): void
    {
        $this->times = [];
        $this->markers = [];
        $this->loadedClasses = [];
        $this->point(self::APP_START);
    }

    /**
     * @return string[] marker names in the order they were set
     */
    public function getMarkers(): array
    {
        return $this->markers;
    }

    /**
     * Set a benchmark marker
     *
     * @param string $name Marker name
     */
    public function point(string $name): void
    {
        if (isset($this->times[$name])) {
            $testIndex = 2;
            while (isset($this->times[$name . '-' . $testIndex])) {
                $testIndex++;
            }
            $name = $name . '-' . $testIndex;
        }
        $this->times[$name] = microtime(true);
        $this->markers[] = $name;
    }
    
    /**
     * Elapsed time
     * Calculates the time difference between two marked points.
     */
    public function elapsedTime(string $point1 = '', string $point2 = ''): float
    {
        if ($point2 === '') {
            $point2 = $point1;
            $point1 = self::APP_START;
        }
        if (!isset($this->times[$point2])) {
            $this->point($point2);
        }
        return $this->times[$point2] - $this->times[$point1];
    }
    
    /**
     * Elapsed time
     * Calculates the time difference between two marked points.
     */
    public function elapsedString(string $point1 = '', string $point2 = ''): string
    {
        return number_format($this->elapsedTime($point1, $point2), 4);
    }
    
    /**
     * Memory Usage
     */
    public function usedMemory(): int
    {
        return memory_get_usage(false);
    }

    /**
     * Memory Usage as string
     */
    public function usedMemoryAsString(): string
    {
        return round($this->usedMemory() / 1024 / 1024, 2).' MB';
    }

    private function generateTimeData(): array
    {
        $results = [];
        $totalElapsedTime = 0;
        foreach ($this->markers as $key => $markerName) {
            if ($key === 0) continue;
            $previousMarkerName = $this->markers[$key-1];
            $elapsedTime = $this->times[$markerName] - $this->times[$previousMarkerName];
            $totalElapsedTime += $elapsedTime;
            $results[] = [
                'marker' => "$previousMarkerName - {$markerName}",
                'time' => $elapsedTime,
                'format_time' => number_format($elapsedTime,8),
                'elapsed_time' => number_format($totalElapsedTime,8),
                'percent' => '',
            ];
        }
        foreach ($results as $key => $result) {
            $results[$key]['percent'] = number_format(
                ($result['time'] * 100 / $totalElapsedTime),
                3, '.', ''
            );
        }
        return $results;
    }

    public function benchmarkTotalTimeTable(): string
    {
        $this->point('BenchmarkEND');
        $results = $this->generateTimeData();

        $ret = '<div style="">';
        $ret .= '    <div style=";">';
        $ret .= '        <div style=" width: 24%; display:inline-block; vertical-align:top;">MARK</div>';
        $ret .= '        <div style=" width: 24%; display:inline-block; vertical-align:top;">ELAPSED STEP</div>';
        $ret .= '        <div style=" width: 24%; display:inline-block; vertical-align:top;">ELAPSED TOTAL</div>';
        $ret .= '        <div style=" width: 24%; display:inline-block; vertical-align:top;">PERCENT</div>';
        $ret .= '    </div>';
        foreach ($results as $result) {
            $ret .= '    <div style=";">';
            $ret .= '        <div style=" width: 24%; display:inline-block; vertical-align:top;">'.$result['marker'].'</div>';
            $ret .= '        <div style=" width: 24%; display:inline-block; vertical-align:top;">'.$result['format_time'].'</div>';
            $ret .= '        <div style=" width: 24%; display:inline-block; vertical-align:top;">'.$result['elapsed_time'].'</div>';
            $ret .= '        <div style=" width: 24%; display:inline-block; vertical-align:top;">'.$result['percent'].'</div>';
            $ret .= '    </div>';
        }
        $ret .= '</div>';

        return $ret;
    }

    public function benchmarkTotalLoadedTable(array $loadedClasses): string
    {
        $ret = '<div style="">';
        $ret .= '    <div style=";">';
        $ret .= '        <div style=" width: 66%; display:inline-block; vertical-align:top;">LOADED CLASSES ('.count($loadedClasses).')</div>';
        $ret .= '    </div>';
        foreach ($loadedClasses as $class => $result) {
            $ret .= '    <div style=";">';
            $ret .= '        <div style=" width: 66%; display:inline-block; vertical-align:top;">'. $class .' | '  . $result . '</div>';
            $ret .= '    </div>';
        }
        $ret .= '</div>';

        return $ret;
    }
}
