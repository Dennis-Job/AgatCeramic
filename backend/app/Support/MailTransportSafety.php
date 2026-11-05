<?php

namespace App\Support;

final class MailTransportSafety
{
    /** @param list<string> $visited */
    public function usesLogTransport(string $name, array $visited = []): bool
    {
        if (in_array($name, $visited, true)) {
            return true;
        }
        $transport = config("mail.mailers.{$name}.transport");
        if ($transport === 'log') {
            return true;
        }
        if (! in_array($transport, ['failover', 'roundrobin'], true)) {
            return false;
        }
        $children = config("mail.mailers.{$name}.mailers", []);
        if (is_array($children)) {
            foreach ($children as $child) {
                if (is_string($child) && $this->usesLogTransport($child, [...$visited, $name])) {
                    return true;
                }
            }
        }

        return false;
    }
}
