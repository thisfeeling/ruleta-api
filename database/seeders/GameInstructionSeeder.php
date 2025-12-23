<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GameInstruction;

class GameInstructionSeeder extends Seeder
{
    public function run(): void
    {
        $instructions = [
            [
                'game_type' => 'millionaire',
                'content_es' => '¡Bienvenidos al Juego del Millonario! Responderás preguntas de cultura general con 4 opciones. Tienes 15 segundos por pregunta. Los jugadores con menos respuestas correctas serán eliminados.',
                'content_en' => 'Welcome to the Millionaire Game! You will answer general knowledge questions with 4 options. You have 15 seconds per question. Players with fewer correct answers will be eliminated.',
                'estimated_duration_seconds' => 30,
            ],
            [
                'game_type' => 'rope',
                'content_es' => 'En La Cuerda, serán divididos en grupos. Primero, votarán para elegir a un miembro. Luego, los grupos competirán haciendo click. El grupo perdedor será eliminado.',
                'content_en' => 'In The Rope, you will be divided into groups. First, you will vote to choose a member. Then, groups will compete by clicking. The losing group will be eliminated.',
                'estimated_duration_seconds' => 40,
            ],
            [
                'game_type' => 'spell',
                'content_es' => 'Deletréalo: Se te asignará una palabra. Deberás deletrearla usando tu voz. Un supervisor validará tu respuesta. Si te equivocas, serás eliminado.',
                'content_en' => 'Spell It: You will be assigned a word. You must spell it using your voice. A supervisor will validate your answer. If you are wrong, you will be eliminated.',
                'estimated_duration_seconds' => 35,
            ],
            [
                'game_type' => 'roulette',
                'content_es' => 'La Ruleta Final: Cada jugador girará la ruleta para acumular puntos. El jugador con menos puntos al final será eliminado. ¡Solo quedará UN ganador!',
                'content_en' => 'The Final Roulette: Each player will spin the roulette to accumulate points. The player with the fewest points at the end will be eliminated. Only ONE winner will remain!',
                'estimated_duration_seconds' => 30,
            ],
            [
                'game_type' => 'word_search',
                'content_es' => '¡A Buscar! Este es un juego bonus. Encuentra todas las palabras en la sopa de letras lo más rápido posible. NO hay eliminación, solo puntos para el scoreboard.',
                'content_en' => 'Word Search! This is a bonus game. Find all words in the word search as fast as possible. NO elimination, only points for the scoreboard.',
                'estimated_duration_seconds' => 25,
            ],
            [
                'game_type' => 'flappy',
                'content_es' => 'No Lo Choques: Juego bonus tipo Flappy Bird. Sobrevive el mayor tiempo posible. NO hay eliminación, solo puntos por tiempo de supervivencia.',
                'content_en' => 'Don\'t Crash It: Flappy Bird-style bonus game. Survive as long as possible. NO elimination, only points for survival time.',
                'estimated_duration_seconds' => 20,
            ],
        ];

        foreach ($instructions as $instruction) {
            GameInstruction::updateOrCreate(
                ['game_type' => $instruction['game_type']],
                $instruction
            );
        }
    }
}
