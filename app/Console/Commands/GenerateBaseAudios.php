<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TTS\TTSService;

class GenerateBaseAudios extends Command
{
    protected $signature = 'audio:generate-base';
    protected $description = 'Generate base audio files (numbers, dialogs)';

    public function handle(TTSService $tts): int
    {
        $this->info('Generating numbers 1-50...');
        $tts->generateNumbers(1, 50);

        $this->info('Generating common dialogs...');

        $dialogs = [
            'narrator.welcome' => '¡Bienvenidos a Ruleta Familiar!',
            'narrator.game_start' => 'El juego está por comenzar',
            'narrator.eliminated' => 'Has sido eliminado',
            'narrator.winner' => '¡Felicitaciones! Eres el ganador',
        ];

        foreach ($dialogs as $key => $text) {
            $this->info("Generating: {$key}");
            $tts->generateAndStore($key, $text, 'voice');
        }

        $this->info('✅ Base audios generated successfully!');

        return Command::SUCCESS;
    }
}
