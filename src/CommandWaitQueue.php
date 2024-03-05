<?php
declare(strict_types=1);

namespace PHPinnacle\Ridge;

use Amp\DeferredFuture;

final class CommandWaitQueue
{
    /** @var DeferredFuture[] */
    private array $waitingCommands = [];

    public function add(DeferredFuture $deferred): void {
        $this->waitingCommands[spl_object_hash($deferred)] = $deferred;
        $deferred->getFuture()->map(function() use ($deferred) {
            unset($this->waitingCommands[spl_object_hash($deferred)]);
        });
    }

    public function cancel(\Throwable $throwable): void {
        foreach ($this->waitingCommands as $id => $deferred) {
            $deferred->error($throwable);
            unset($this->waitingCommands[$id]);
        }
    }
}
