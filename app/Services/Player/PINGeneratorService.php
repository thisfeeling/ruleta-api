<?php

namespace App\Services\Player;

use App\Models\Player;

class PINGeneratorService
{
    /**
     * Generate unique 4-digit PIN
     */
    public function generate(): string
    {
        $attempts = 0;
        $maxAttempts = 100;

        do {
            $pin = $this->generateRandom();
            $exists = Player::where('pin', $pin)->exists();
            $attempts++;

            if ($attempts >= $maxAttempts) {
                throw new \Exception('Unable to generate unique PIN after ' . $maxAttempts . ' attempts');
            }
        } while ($exists);

        return $pin;
    }

    /**
     * Generate multiple unique PINs
     */
    public function generateBatch(int $count): array
    {
        $pins = [];

        for ($i = 0; $i < $count; $i++) {
            $pins[] = $this->generate();
        }

        return $pins;
    }

    /**
     * Validate PIN format
     */
    public function validate(string $pin): bool
    {
        return preg_match('/^\d{4}$/', $pin) === 1;
    }

    /**
     * Find player by PIN
     */
    public function findPlayer(string $pin): ?Player
    {
        if (!$this->validate($pin)) {
            return null;
        }

        return Player::where('pin', $pin)
            ->whereIn('status', ['active', 'disconnected'])
            ->first();
    }

    // Helpers
    protected function generateRandom(): string
    {
        return str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
    }
}
