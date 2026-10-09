<?php

namespace Tests\Support;

/**
 * Spec019 T034 (small slice): proc_open wrapper plus file-barrier polling for
 * real child-process translation races.
 *
 * Spawns `php tests/Support/Spec019TranslationWorker.php` children with
 * proc_open in argv-array form (no shell, no new packages), so the handle
 * owns the real PHP process directly: proc_terminate acts on the child PHP
 * itself, never on an intermediate shell. The parent never sleeps for
 * ordering: it polls barrier files with deadlines, and every timeout carries
 * the child's stderr tail for diagnosis.
 */
final class Spec019TranslationProcess
{
    /** @var resource|null */
    private $handle = null;

    /** @var array<int, resource> */
    private array $pipes = [];

    private bool $closed = false;

    private ?int $pid = null;

    private ?int $knownExit = null;

    private function __construct(
        private readonly string $label,
        private readonly string $stderrLog,
    ) {}

    /**
     * Spawn a child worker via argv-array proc_open (no shell). Stdout/stderr
     * go to parent-owned log files (no pipe deadlocks); stdin is closed
     * immediately.
     *
     * @param  array<int, string>  $args
     */
    public static function spawn(string $worker, array $args, string $stdoutLog, string $stderrLog, string $label): self
    {
        foreach ([$worker, ...$args] as $part) {
            if (! is_string($part) || $part === '') {
                throw new \RuntimeException("{$label}: array-form spawn requires non-empty string command parts.");
            }
        }

        $instance = new self($label, $stderrLog);
        $handle = proc_open([PHP_BINARY, $worker, ...$args], [
            0 => ['pipe', 'r'],
            1 => ['file', $stdoutLog, 'w'],
            2 => ['file', $stderrLog, 'w'],
        ], $instance->pipes, null, null);

        if (! is_resource($handle)) {
            throw new \RuntimeException("{$label}: proc_open failed to spawn child worker.");
        }
        $instance->handle = $handle;

        if (isset($instance->pipes[0]) && is_resource($instance->pipes[0])) {
            fclose($instance->pipes[0]);
        }
        unset($instance->pipes[0]);

        // Capture the real child PHP pid immediately (no shell in between).
        $instance->refreshStatus();

        return $instance;
    }

    /**
     * Refresh cached pid/exit from proc_get_status. A nonnegative exitcode on
     * a non-running handle is retained so later reads never lose it.
     */
    private function refreshStatus(): void
    {
        if ($this->handle === null || $this->closed) {
            return;
        }

        $status = proc_get_status($this->handle);
        if (($status['pid'] ?? 0) > 0) {
            $this->pid = (int) $status['pid'];
        }
        if (! ($status['running'] ?? false) && ($status['exitcode'] ?? -1) >= 0) {
            $this->knownExit = (int) $status['exitcode'];
        }
    }

    public function isAlive(): bool
    {
        if ($this->handle === null || $this->closed) {
            return false;
        }

        $status = proc_get_status($this->handle);
        if (($status['pid'] ?? 0) > 0) {
            $this->pid = (int) $status['pid'];
        }
        if ((bool) ($status['running'] ?? false)) {
            return true;
        }

        // Exited: retain a valid exitcode instead of consuming the status.
        if (($status['exitcode'] ?? -1) >= 0) {
            $this->knownExit = (int) $status['exitcode'];
        }

        return false;
    }

    /**
     * Actual child PHP pid (null until the first status read succeeds).
     */
    public function pid(): ?int
    {
        $this->refreshStatus();

        return $this->pid;
    }

    /**
     * Stop ONLY this owned child; never touches unrelated processes.
     */
    public function terminate(): void
    {
        if ($this->handle !== null && ! $this->closed && $this->isAlive()) {
            @proc_terminate($this->handle);
        }
    }

    /**
     * Poll until the child exits (deadline, not ordering sleep), then reap it.
     * On deadline the owned child is SIGTERMed, given a bounded grace, then
     * SIGKILLed via its own handle only; a loud timeout exception (with the
     * child stderr tail) follows instead of a blocking reap.
     */
    public function waitExit(float $timeoutSeconds, float $killGraceSeconds = 5.0): ?int
    {
        $deadline = microtime(true) + $timeoutSeconds;
        while ($this->isAlive() && microtime(true) < $deadline) {
            usleep(50000);
        }

        if ($this->isAlive()) {
            @proc_terminate($this->handle);
            $grace = microtime(true) + $killGraceSeconds;
            while ($this->isAlive() && microtime(true) < $grace) {
                usleep(50000);
            }
            if ($this->isAlive() && is_resource($this->handle)) {
                @proc_terminate($this->handle, 9);
            }
            $this->close();
            $pid = $this->pid ?? -1;

            throw new \RuntimeException(
                "{$this->label}: timed out after {$timeoutSeconds}s waiting for child exit (pid {$pid})." . $this->stderrTail()
            );
        }

        return $this->close();
    }

    /**
     * Reap the child without ever blocking indefinitely: a still-live owned
     * child is terminated (bounded grace, SIGKILL fallback on its own handle)
     * before proc_close. A previously retained exitcode is never overwritten
     * by a later -1.
     */
    public function close(): ?int
    {
        if ($this->closed) {
            return $this->knownExit;
        }

        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        $this->pipes = [];

        if (is_resource($this->handle)) {
            if ($this->isAlive()) {
                @proc_terminate($this->handle);
                $grace = microtime(true) + 5.0;
                while ($this->isAlive() && microtime(true) < $grace) {
                    usleep(50000);
                }
                if ($this->isAlive()) {
                    @proc_terminate($this->handle, 9);
                }
            }
            $code = proc_close($this->handle);
            if ($this->knownExit === null && $code >= 0) {
                $this->knownExit = $code;
            }
        }
        $this->handle = null;
        $this->closed = true;

        return $this->knownExit;
    }

    public function stderrLog(): string
    {
        return $this->stderrLog;
    }

    private function stderrTail(): string
    {
        if ($this->stderrLog === '' || ! is_file($this->stderrLog)) {
            return '';
        }

        $tail = file_get_contents($this->stderrLog);

        return ' Child stderr tail: \'' . substr(is_string($tail) ? $tail : '', -2000) . '\'';
    }

    /**
     * Poll for a barrier file with a deadline. Ordering comes from file
     * observation, never from sleeps.
     */
    public static function waitForFile(string $path, float $timeoutSeconds, string $label, string $stderrLog = ''): void
    {
        $deadline = microtime(true) + $timeoutSeconds;
        while (true) {
            clearstatcache(true, $path);
            if (is_file($path)) {
                return;
            }
            if (microtime(true) >= $deadline) {
                break;
            }
            usleep(50000);
        }

        $hint = '';
        if ($stderrLog !== '' && is_file($stderrLog)) {
            $tail = file_get_contents($stderrLog);
            $hint = ' Child stderr tail: \'' . substr(is_string($tail) ? $tail : '', -2000) . '\'';
        }

        throw new \RuntimeException("{$label}: timed out after {$timeoutSeconds}s waiting for {$path}.{$hint}");
    }

    public static function signal(string $path, string $content = "1\n"): void
    {
        if (file_put_contents($path, $content) === false) {
            throw new \RuntimeException("Failed to write barrier signal {$path}.");
        }
    }
}
